<?php

declare(strict_types=1);

namespace App\Console\Commands\OhsDashboard;

use App\Services\OhsDashboard\HseSyncService;
use Illuminate\Console\Command;
use Throwable;

final class SyncHseEmployees extends Command
{
    protected $signature = 'ohs-dashboard:hse-sync {--force : Jalankan tanpa cek jadwal Senin 08:00}';

    protected $description = 'Sinkronisasi karyawan HSE (AKTIF) ke ohs_employees. Default hanya Senin 08:00 WIB.';

    public function handle(HseSyncService $service): int
    {
        try {
            $result = $this->option('force')
                ? $service->syncNow(false)
                : $service->runScheduled();
            $this->info($result['message']);

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
