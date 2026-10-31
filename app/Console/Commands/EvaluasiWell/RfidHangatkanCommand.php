<?php

declare(strict_types=1);

namespace App\Console\Commands\EvaluasiWell;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Isi/segarkan snapshot RFID per tanggal (SportEvaluationPvtRfidCheckinReader
 * ::snapshotHari) di latar, supaya request /evaluasi-well/pvt dan Pra Operasi
 * tidak pernah menunggu query view RFID (±6 dtk per tanggal).
 *
 *   hari ini      → selalu disegarkan (check-in & check-out)
 *   N hari lalu   → hanya diisi bila belum ada di cache
 */
final class RfidHangatkanCommand extends Command
{
    protected $signature = 'evaluasi-well:rfid-hangatkan {--hari=7 : Jumlah hari ke belakang yang dipastikan ter-cache}';

    protected $description = 'Segarkan snapshot RFID harian untuk dashboard PVT / Pra Operasi';

    public function handle(SportEvaluationPvtRfidCheckinReader $rfid): int
    {
        if (! $rfid->isUp()) {
            $this->warn('OLAP RFID tidak terjangkau — dilewati.');

            return self::SUCCESS;
        }

        $hariIni = CarbonImmutable::now((string) config('app.timezone'))->startOfDay();
        $mundur = max(0, min(31, (int) $this->option('hari')));

        for ($i = 0; $i <= $mundur; $i++) {
            $tanggal = $hariIni->subDays($i)->toDateString();
            foreach ([false, true] as $checkout) {
                $mulai = microtime(true);
                try {
                    $n = count($rfid->snapshotHari($tanggal, $checkout, segarkan: $i === 0));
                } catch (Throwable $e) {
                    report($e);
                    $this->warn("  {$tanggal} ".($checkout ? 'check-out' : 'check-in').' gagal: '.$e->getMessage());

                    continue;
                }
                $this->line(sprintf('  %s %-9s %6d SID (%.1f dtk)', $tanggal, $checkout ? 'check-out' : 'check-in', $n, microtime(true) - $mulai));
            }
        }

        return self::SUCCESS;
    }
}
