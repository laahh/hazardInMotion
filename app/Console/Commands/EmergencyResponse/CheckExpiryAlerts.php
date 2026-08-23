<?php

declare(strict_types=1);

namespace App\Console\Commands\EmergencyResponse;

use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\Inspection\Inspection;
use App\Models\EmergencyResponse\Inspection\InspectionSchedule;
use App\Models\EmergencyResponse\Maintenance\MaintenanceSchedule;
use App\Models\EmergencyResponse\Maintenance\WorkOrder;
use App\Models\EmergencyResponse\Manpower\EmployeeCertification;
use App\Models\EmergencyResponse\Manpower\EmployeeTraining;
use App\Models\EmergencyResponse\Notification\Alert;
use App\Models\EmergencyResponse\SafetyDevice\SafetyDevice;
use App\Services\EmergencyResponse\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Reminder H-90/60/30/14/7/0 + 1x notifikasi overdue untuk: kedaluwarsa
 * equipment, sertifikat equipment/safety device, kalibrasi safety device,
 * jadwal maintenance & inspeksi yang jatuh tempo, dan work order yang
 * melewati target. Idempoten lewat unique constraint di er_alerts
 * (alertable + alert_type + threshold_days) — aman dijalankan berkali-kali.
 */
class CheckExpiryAlerts extends Command
{
    private const THRESHOLDS = [90, 60, 30, 14, 7, 0];

    private const OVERDUE_BUCKET = -1;

    protected $signature = 'emergency-response:check-alerts';

    protected $description = 'Cek tanggal kedaluwarsa/jatuh tempo dan kirim alert H-90..H-0 serta overdue';

    public function __construct(private readonly NotificationService $notifications)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = 0;

        foreach (EmergencyEquipment::query()->cursor() as $equipment) {
            $count += $this->checkDate($equipment, 'expires_at', $equipment->expires_at, 'hse-admin', 'Equipment akan kedaluwarsa');
            $count += $this->checkDate($equipment, 'certificate_expires_at', $equipment->certificate_expires_at, 'hse-admin', 'Sertifikat/SKO equipment akan habis masa berlaku');
            $count += $this->checkDate($equipment, 'inspection_overdue', $equipment->next_inspection_at, 'hse-admin', 'Jadwal inspeksi equipment');
        }

        foreach (SafetyDevice::query()->cursor() as $device) {
            $count += $this->checkDate($device, 'certificate_expires_at', $device->certificate_expires_at, 'hse-admin', 'Sertifikat safety device akan habis masa berlaku');
            $count += $this->checkDate($device, 'calibration_due', $device->next_calibration_at, 'hse-admin', 'Kalibrasi safety device jatuh tempo');
            $count += $this->checkDate($device, 'inspection_overdue', $device->next_inspection_at, 'hse-admin', 'Jadwal inspeksi safety device');
        }

        foreach (MaintenanceSchedule::query()->where('is_active', true)->cursor() as $schedule) {
            $count += $this->checkDate($schedule, 'maintenance_overdue', $schedule->next_due_date, 'hse-admin', 'Jadwal preventive maintenance jatuh tempo');
        }

        foreach (InspectionSchedule::query()->where('is_active', true)->cursor() as $schedule) {
            $count += $this->checkDate($schedule, 'inspection_overdue', $schedule->next_due_date, 'inspector', 'Jadwal inspeksi terjadwal jatuh tempo');
            $this->generateScheduledInspectionIfDue($schedule);
        }

        foreach (WorkOrder::query()->whereNotIn('status', ['closed'])->whereNotNull('target_end_at')->cursor() as $workOrder) {
            $count += $this->checkDate($workOrder, 'wo_overdue', $workOrder->target_end_at, 'technician', 'Work order melewati target selesai');
        }

        foreach (EmployeeTraining::query()->with('employee', 'training')->whereNotNull('expires_at')->cursor() as $training) {
            $identifier = ($training->employee->full_name ?? '-').' — '.($training->training->name ?? '-');
            $count += $this->checkDate($training, 'training_expiring', $training->expires_at, 'hse-admin', 'Training personel akan expired', $identifier, $training->employee?->user);
        }

        foreach (EmployeeCertification::query()->with('employee', 'certification')->whereNotNull('expires_at')->cursor() as $certification) {
            $identifier = ($certification->employee->full_name ?? '-').' — '.($certification->certification->name ?? '-');
            $count += $this->checkDate($certification, 'certification_expiring', $certification->expires_at, 'hse-admin', 'Sertifikasi personel akan expired', $identifier, $certification->employee?->user);
        }

        $this->info("{$count} alert baru dibuat & dikirim.");

        return self::SUCCESS;
    }

    private function checkDate(
        Model $model,
        string $alertType,
        mixed $dueDate,
        string $roleSlug,
        string $label,
        ?string $identifierOverride = null,
        ?\App\Models\User $alsoNotifyUser = null,
    ): int {
        if (! $dueDate) {
            return 0;
        }

        $due = $dueDate instanceof \Carbon\CarbonInterface ? $dueDate : \Illuminate\Support\Carbon::parse($dueDate);
        $daysUntil = (int) today()->diffInDays($due, false);

        $bucket = match (true) {
            $daysUntil < 0 => self::OVERDUE_BUCKET,
            in_array($daysUntil, self::THRESHOLDS, true) => $daysUntil,
            default => null,
        };

        if ($bucket === null) {
            return 0;
        }

        $alert = Alert::firstOrCreate(
            ['alertable_type' => get_class($model), 'alertable_id' => $model->id, 'alert_type' => $alertType, 'threshold_days' => $bucket],
            ['due_date' => $due->toDateString(), 'status' => 'pending', 'created_at' => now()],
        );

        if (! $alert->wasRecentlyCreated) {
            return 0;
        }

        $identifier = $identifierOverride ?? ($model->code ?? $model->work_order_number ?? $model->id);
        $suffix = $bucket === self::OVERDUE_BUCKET
            ? 'sudah lewat dari tanggal '.$due->format('d M Y').'.'
            : ($bucket === 0 ? 'jatuh tempo hari ini ('.$due->format('d M Y').').' : "akan jatuh tempo dalam {$bucket} hari (".$due->format('d M Y').').');

        $message = "{$label}: {$identifier} {$suffix}";
        $this->notifications->notifyRole($roleSlug, $alertType, $label, $message);
        if ($alsoNotifyUser) {
            $this->notifications->notifyUser($alsoNotifyUser, $alertType, $label, $message);
        }

        $alert->update(['status' => 'sent', 'sent_at' => now()]);

        return 1;
    }

    private function generateScheduledInspectionIfDue(InspectionSchedule $schedule): void
    {
        if ($schedule->next_due_date->isFuture()) {
            return;
        }

        $alreadyExists = Inspection::query()
            ->where('inspection_schedule_id', $schedule->id)
            ->whereDate('created_at', '>=', $schedule->next_due_date)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $year = now()->format('Y');
        $count = Inspection::query()->whereYear('created_at', $year)->lockForUpdate()->count();

        Inspection::create([
            'inspection_number' => sprintf('INSP-%s-%06d', $year, $count + 1),
            'inspection_schedule_id' => $schedule->id,
            'target_type' => $schedule->target_type,
            'target_id' => $schedule->target_id,
            'checklist_template_id' => $schedule->checklist_template_id,
            'site_id' => $schedule->target->site_id ?? null,
            'inspector_id' => $schedule->assigned_inspector_id,
            'status' => 'scheduled',
            'inspected_at' => null,
        ]);

        $schedule->update(['next_due_date' => $schedule->next_due_date->addDays($schedule->frequency_days)]);
    }
}
