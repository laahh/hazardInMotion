<?php

declare(strict_types=1);

namespace App\Console\Commands\Dms;

use App\Services\Dms\Roster\DmsRosterComplianceService;
use App\Services\Dms\Roster\DmsRosterRfidSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sinkronisasi data Kepatuhan Roster dari scan RFID (bcsid.mv_checkinout_rfid).
 *
 *   php artisan dms:sync-roster-rfid --full     # backfill 1 Jan s/d hari ini
 *   php artisan dms:sync-roster-rfid            # inkremental beberapa hari terakhir
 *   php artisan dms:sync-roster-rfid --from=2026-09-01 --to=2026-09-30
 *
 * Jalankan --full sekali saat pertama kali dipasang; setelah itu jadwal rutin
 * (lihat app/Console/Kernel.php) cukup memakai mode inkremental.
 */
final class SyncRosterRfidCommand extends Command
{
    protected $signature = 'dms:sync-roster-rfid
        {--tahun= : Tahun yang disinkronkan (default: tahun berjalan)}
        {--from= : Tanggal awal YYYY-MM-DD (default: hari_incremental hari ke belakang)}
        {--to= : Tanggal akhir YYYY-MM-DD (default: hari ini)}
        {--full : Backfill dari 1 Januari tahun tersebut}
        {--skip-populasi : Lewati penyegaran daftar karyawan}
        {--no-compile : Jangan rakit ulang string pola setelah menarik scan}';

    protected $description = 'Tarik scan RFID PASSED ke tabel lokal dan rakit pola roster untuk /dms/roster-compliance';

    public function handle(DmsRosterRfidSyncService $sync, DmsRosterComplianceService $dashboard): int
    {
        if (! $sync->isUp()) {
            $this->error('Postgres OLAP tidak terjangkau (pgsql_direct / pgsql_ssh). Sinkronisasi dibatalkan.');

            return self::FAILURE;
        }

        $tahun = (int) ($this->option('tahun') ?: CarbonImmutable::now()->year);
        $mulai = microtime(true);

        try {
            if (! $this->option('skip-populasi')) {
                $this->info("Menyegarkan populasi roster {$tahun} ...");
                $jumlah = $sync->sinkronPopulasi($tahun);
                $this->line("  {$this->angka($jumlah)} karyawan dalam populasi (operator/driver & mekanik, WP PASSED).");

                if ($jumlah === 0) {
                    $this->warn('  Populasi kosong — cek filter jabatan di config/dms_roster.php.');

                    return self::FAILURE;
                }
            }

            [$dari, $sampai] = $this->rentang($tahun);
            $this->info("Menarik scan RFID {$dari->toDateString()} s/d {$sampai->toDateString()} ...");

            $ditulis = $sync->backfill($tahun, $dari, $sampai, function (string $a, string $b, int $n): void {
                $this->line("  {$a} … {$b} → {$this->angka($n)} baris hari");
            });
            $this->line("  Total {$this->angka($ditulis)} baris hari tersimpan.");

            if (! $this->option('no-compile')) {
                $this->info('Merakit ulang pola roster ...');
                $diperbarui = $sync->kompilasiPola($tahun);
                $this->line("  {$this->angka($diperbarui)} pola karyawan diperbarui.");

                // Evaluasi seluruh populasi ±3 detik; dihangatkan di sini agar
                // pengunjung pertama setelah sinkronisasi tidak menanggungnya.
                $this->info('Menghangatkan cache dashboard ...');
                $mulaiWarm = microtime(true);
                $payload = $dashboard->dashboard(['tahun' => $tahun]);
                $this->line(sprintf(
                    '  %s karyawan dievaluasi dalam %.1f detik (%s pelanggaran, %s wajib cuti).',
                    $this->angka((int) $payload['agregat']['total']),
                    microtime(true) - $mulaiWarm,
                    $this->angka((int) $payload['agregat']['pelanggaran']),
                    $this->angka((int) $payload['agregat']['wajib_cuti']),
                ));
            }
        } catch (Throwable $e) {
            $this->error('Sinkronisasi gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Selesai dalam '.round(microtime(true) - $mulai, 1).' detik.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function rentang(int $tahun): array
    {
        $hariIni = CarbonImmutable::now()->startOfDay();
        $akhirTahun = CarbonImmutable::create($tahun, 12, 31)->startOfDay();

        $sampai = $this->option('to')
            ? CarbonImmutable::parse((string) $this->option('to'))->startOfDay()
            : ($hariIni->gt($akhirTahun) ? $akhirTahun : $hariIni);

        if ($this->option('full')) {
            return [CarbonImmutable::create($tahun, 1, 1)->startOfDay(), $sampai];
        }

        if ($this->option('from')) {
            return [CarbonImmutable::parse((string) $this->option('from'))->startOfDay(), $sampai];
        }

        $hari = max(1, (int) config('dms_roster.sync.hari_incremental', 3));

        return [$sampai->subDays($hari - 1), $sampai];
    }

    private function angka(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }
}
