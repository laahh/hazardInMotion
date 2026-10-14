<?php

declare(strict_types=1);

namespace App\Console\Commands\ControlRoom;

use App\Services\ControlRoom\ControlRoomIsoWeekPeriod;
use App\Services\ControlRoom\PengawasMonitorService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Pre-warm snapshot /control-room/pengawas untuk "Semua Site" (±250 SID).
 * Hitungannya ±40 query OBDS berurutan — terlalu lama untuk request HTTP
 * (504 di ALB), jadi halaman hanya membaca snapshot hasil command ini.
 *
 * Minggu yang di-warm: minggu default halaman (minggu lalu), minggu berjalan,
 * plus minggu lain yang sempat diminta user (antrean pending).
 */
final class WarmPengawasMonitorCommand extends Command
{
    protected $signature = 'control-room:pengawas-warm {--week=* : iso_week tambahan, mis. 2026-W41}';

    protected $description = 'Pre-warm snapshot Monitoring Pengawas Control Room untuk Semua Site';

    public function handle(PengawasMonitorService $monitor): int
    {
        $weeks = array_values(array_unique([
            ControlRoomIsoWeekPeriod::fromRequest(Request::create('/'))->isoWeekValue(),
            sprintf('%04d-W%02d', (int) now()->isoWeekYear(), (int) now()->isoWeek()),
            ...$monitor->pendingWeeks(),
            ...array_map('strval', (array) $this->option('week')),
        ]));

        $failed = 0;
        foreach ($weeks as $isoWeek) {
            $period = ControlRoomIsoWeekPeriod::fromRequest(Request::create('/', 'GET', ['iso_week' => $isoWeek]));
            $started = microtime(true);
            $payload = $monitor->warm(null, $period);
            $elapsed = round(microtime(true) - $started, 1);

            if ($payload['loaded'] && $payload['dataQuality']['loaded']) {
                $monitor->forgetPendingWeek($isoWeek);
                $this->info("{$isoWeek}: {$payload['people']} pengawas dalam {$elapsed} detik.");

                continue;
            }

            $failed++;
            $this->warn("{$isoWeek}: OBDS gagal/terputus setelah {$elapsed} detik — snapshot lama dipertahankan.");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
