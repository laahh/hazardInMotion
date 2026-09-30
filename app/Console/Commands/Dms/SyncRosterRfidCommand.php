<?php

declare(strict_types=1);

namespace App\Console\Commands\Dms;

use App\Services\Dms\Roster\DmsRosterSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sinkronisasi Kepatuhan Roster dari scan RFID ke skema ternormalisasi.
 *
 *   php artisan dms:sync-roster-rfid --master          # tahap 1 saja (harian)
 *   php artisan dms:sync-roster-rfid --full            # backfill 1 Jan s/d hari ini
 *   php artisan dms:sync-roster-rfid                   # inkremental (tiap 30 menit)
 *   php artisan dms:sync-roster-rfid --from=2026-09-01 --to=2026-09-30
 *
 * Master karyawan tidak ikut ditarik pada mode inkremental: jabatan dan SIMPER
 * tidak berubah menit-ke-menit, sementara view-nya lambat. Jadwalkan --master
 * sekali sehari.
 */
final class SyncRosterRfidCommand extends Command
{
    protected $signature = 'dms:sync-roster-rfid
        {--tahun= : Tahun yang disinkronkan (default: tahun berjalan)}
        {--from= : Tanggal awal YYYY-MM-DD}
        {--to= : Tanggal akhir YYYY-MM-DD (default: hari ini)}
        {--full : Backfill dari 1 Januari tahun tersebut}
        {--master : Segarkan master karyawan + SIMPER + flag wajib_cek}
        {--only-master : Hanya master, lewati penarikan scan & kompilasi}
        {--no-compile : Jangan rakit ulang pola setelah menarik scan}';

    protected $description = 'Tarik scan RFID ke dms_roster_scan_harian dan rakit pola roster';

    public function handle(DmsRosterSyncService $sync): int
    {
        if (! $sync->isUp()) {
            $this->error('Postgres OLAP tidak terjangkau (pgsql_direct / pgsql_ssh). Sinkronisasi dibatalkan.');

            return self::FAILURE;
        }

        $tahun = (int) ($this->option('tahun') ?: CarbonImmutable::now()->year);
        $mulai = microtime(true);

        try {
            if ($this->option('master') || $this->option('only-master') || $this->option('full')) {
                $this->info('Tahap 1 — master karyawan ...');
                $hasil = $sync->sinkronKaryawan();
                $this->line("  {$this->angka($hasil['total'])} karyawan aktif tersimpan.");
                $this->line("  {$this->angka($hasil['wajib_cek'])} di antaranya masuk base WAJIB DICEK.");

                if ($hasil['total'] === 0) {
                    $this->warn('  Master kosong — cek koneksi OLAP atau filter di config/dms_roster.php.');

                    return self::FAILURE;
                }
            }

            if ($this->option('only-master')) {
                $this->info('Selesai dalam '.round(microtime(true) - $mulai, 1).' detik.');

                return self::SUCCESS;
            }

            [$dari, $sampai] = $this->rentang($tahun);
            $this->info("Tahap 3 — scan RFID {$dari->toDateString()} s/d {$sampai->toDateString()} ...");

            $ditulis = $sync->backfill($dari, $sampai, function (string $a, string $b, int $n): void {
                $this->line("  {$a} … {$b} → {$this->angka($n)} baris hari");
            });
            $this->line("  Total {$this->angka($ditulis)} baris fakta tersimpan.");

            if (! $this->option('no-compile')) {
                $this->info('Tahap 4 — kompilasi pola ...');
                $jml = $sync->kompilasiPola($tahun);
                $this->line("  {$this->angka($jml)} pola karyawan ditulis.");
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
