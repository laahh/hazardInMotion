<?php

declare(strict_types=1);

namespace App\Console\Commands\EmergencyResponse;

use App\Models\EmergencyResponse\Incident\Incident;
use App\Models\EmergencyResponse\MasterData\EscalationMatrix;
use App\Models\EmergencyResponse\Maintenance\WorkOrder;
use App\Models\EmergencyResponse\Notification\Alert;
use App\Services\EmergencyResponse\NotificationService;
use Illuminate\Console\Command;

/**
 * Menjalankan er_escalation_matrices (Fase 1): insiden yang belum
 * dikonfirmasi / work order yang belum disetujui melewati delay_minutes
 * suatu level eskalasi akan memicu notifikasi ke notify_role level itu.
 * Idempoten per level lewat er_alerts (threshold_days dipakai ulang untuk
 * menyimpan nomor level eskalasi, unique per alertable+alert_type+level).
 */
class CheckEscalations extends Command
{
    protected $signature = 'emergency-response:check-escalations';

    protected $description = 'Cek insiden/work order yang perlu dieskalasi berdasarkan escalation matrix';

    public function __construct(private readonly NotificationService $notifications)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $escalated = 0;
        $escalated += $this->checkIncidents();
        $escalated += $this->checkWorkOrders();

        $this->info("{$escalated} eskalasi baru dipicu.");

        return self::SUCCESS;
    }

    private function checkIncidents(): int
    {
        $rules = EscalationMatrix::query()->where('applies_to', 'incident')->where('is_active', true)->orderBy('level')->get();
        if ($rules->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach (Incident::query()->where('status', 'open')->cursor() as $incident) {
            $minutesSinceReported = $incident->reported_at->diffInMinutes(now());

            foreach ($rules as $rule) {
                if ($minutesSinceReported < $rule->delay_minutes) {
                    continue;
                }

                $alert = Alert::firstOrCreate(
                    ['alertable_type' => Incident::class, 'alertable_id' => $incident->id, 'alert_type' => 'escalation', 'threshold_days' => $rule->level],
                    ['status' => 'pending', 'created_at' => now()],
                );

                if (! $alert->wasRecentlyCreated) {
                    continue;
                }

                $roleSlug = $rule->notifyRole->slug ?? null;
                if ($roleSlug) {
                    $this->notifications->notifyRole(
                        $roleSlug,
                        'escalation',
                        "Eskalasi Level {$rule->level}: Insiden Belum Ditangani",
                        "Insiden {$incident->incident_number} belum dikonfirmasi setelah {$minutesSinceReported} menit sejak dilaporkan.",
                        route('emergency-response.incident.show', $incident),
                    );
                }

                $incident->update(['is_escalated' => true]);
                $incident->addTimelineEntry('escalation', "Eskalasi level {$rule->level} dipicu (belum dikonfirmasi setelah {$rule->delay_minutes} menit).", null);
                $alert->update(['status' => 'sent', 'sent_at' => now()]);
                $count++;
            }
        }

        return $count;
    }

    private function checkWorkOrders(): int
    {
        $rules = EscalationMatrix::query()->where('applies_to', 'work_order')->where('is_active', true)->orderBy('level')->get();
        if ($rules->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach (WorkOrder::query()->where('status', 'requested')->cursor() as $workOrder) {
            $minutesSinceRequested = $workOrder->requested_at->diffInMinutes(now());

            foreach ($rules as $rule) {
                if ($minutesSinceRequested < $rule->delay_minutes) {
                    continue;
                }

                $alert = Alert::firstOrCreate(
                    ['alertable_type' => WorkOrder::class, 'alertable_id' => $workOrder->id, 'alert_type' => 'escalation', 'threshold_days' => $rule->level],
                    ['status' => 'pending', 'created_at' => now()],
                );

                if (! $alert->wasRecentlyCreated) {
                    continue;
                }

                $roleSlug = $rule->notifyRole->slug ?? null;
                if ($roleSlug) {
                    $this->notifications->notifyRole(
                        $roleSlug,
                        'escalation',
                        "Eskalasi Level {$rule->level}: Work Order Belum Disetujui",
                        "Work order {$workOrder->work_order_number} belum disetujui setelah {$minutesSinceRequested} menit.",
                        route('emergency-response.work-order.show', $workOrder),
                    );
                }

                $alert->update(['status' => 'sent', 'sent_at' => now()]);
                $count++;
            }
        }

        return $count;
    }
}
