<?php

declare(strict_types=1);

namespace App\Console\Commands\PraOperasi;

use App\Services\PraOperasi\PraOperasiDailyEvaluationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * "Tutup buku harian" Fase 3 (Pasca Operasi) — hitung & simpan evaluasi
 * harian semua operator yang checkin pada tanggal tertentu (default: kemarin,
 * supaya seluruh checkin hari itu — termasuk shift malam — sudah pasti selesai).
 */
final class EvaluateDayCommand extends Command
{
    protected $signature = 'pra-operasi:evaluate-day {date? : Y-m-d, default kemarin}';

    protected $description = 'Hitung & simpan evaluasi harian Pra Operasi (Fase 3) untuk satu tanggal';

    public function handle(PraOperasiDailyEvaluationService $service): int
    {
        $date = (string) ($this->argument('date') ?? '');
        if ($date === '') {
            $date = Carbon::now(config('app.timezone'))->subDay()->toDateString();
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $this->error("Format tanggal tidak valid: {$date} (harus Y-m-d)");

            return self::FAILURE;
        }

        $this->info("Menghitung evaluasi harian untuk {$date} ...");
        $started = microtime(true);
        $summary = $service->evaluateDate($date);
        $elapsed = round(microtime(true) - $started, 2);

        if ($summary['processed'] === 0) {
            $this->warn("Tidak ada data diproses untuk {$date} — cek koneksi hse_automation atau apakah ada checkin di tanggal itu.");

            return self::FAILURE;
        }

        $this->info(sprintf(
            "Selesai dalam %s detik: %s operator · Baik=%s · Perlu Pembinaan=%s · Kritis=%s",
            $elapsed,
            number_format($summary['processed']),
            number_format($summary['baik']),
            number_format($summary['perlu_pembinaan']),
            number_format($summary['kritis']),
        ));

        return self::SUCCESS;
    }
}
