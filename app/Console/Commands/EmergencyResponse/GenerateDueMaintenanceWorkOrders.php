<?php

declare(strict_types=1);

namespace App\Console\Commands\EmergencyResponse;

use App\Models\EmergencyResponse\Maintenance\MaintenanceSchedule;
use App\Models\EmergencyResponse\Maintenance\WorkOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Membuat Work Order (preventive) untuk setiap er_maintenance_schedules yang
 * sudah jatuh tempo (next_due_date <= hari ini), lalu memajukan next_due_date.
 *
 * BELUM didaftarkan ke scheduler (app/Console/Kernel.php) — sengaja ditunda,
 * disatukan dengan alert engine di Fase 6 (Notifikasi) supaya reminder H-90..H-0
 * dan pembuatan Work Order otomatis dibangun dalam satu mekanisme yang sama.
 * Untuk sekarang jalankan manual: php artisan emergency-response:generate-due-maintenance
 */
class GenerateDueMaintenanceWorkOrders extends Command
{
    protected $signature = 'emergency-response:generate-due-maintenance';

    protected $description = 'Buat Work Order preventive untuk jadwal maintenance yang sudah jatuh tempo';

    public function handle(): int
    {
        $dueSchedules = MaintenanceSchedule::query()
            ->where('is_active', true)
            ->whereDate('next_due_date', '<=', now()->toDateString())
            ->with('target', 'maintenanceType')
            ->get();

        $created = 0;

        foreach ($dueSchedules as $schedule) {
            if (! $schedule->target) {
                continue;
            }

            DB::transaction(function () use ($schedule): void {
                $year = now()->format('Y');
                $count = WorkOrder::query()->whereYear('created_at', $year)->lockForUpdate()->count();

                $workOrder = WorkOrder::create([
                    'work_order_number' => sprintf('WO-%s-%06d', $year, $count + 1),
                    'equipmentable_type' => $schedule->target_type,
                    'equipmentable_id' => $schedule->target_id,
                    'site_id' => $schedule->target->site_id ?? null,
                    'work_type' => 'preventive',
                    'source' => 'alert',
                    'description' => 'Preventive maintenance terjadwal: '.($schedule->maintenanceType->name ?? '-'),
                    'assigned_technician_id' => $schedule->assigned_technician_id,
                    'target_start_at' => $schedule->next_due_date,
                    'target_end_at' => $schedule->next_due_date,
                    'requested_at' => now(),
                    'status' => 'requested',
                ]);
                $workOrder->recordStatusChange('requested', 'Dibuat otomatis dari jadwal maintenance.', null);

                $schedule->update(['next_due_date' => $schedule->next_due_date->addDays($schedule->frequency_days)]);
            });

            $created++;
        }

        $this->info("{$created} work order preventive dibuat dari jadwal yang jatuh tempo.");

        return self::SUCCESS;
    }
}
