<?php

declare(strict_types=1);

namespace App\Console\Commands\Dms;

use App\Services\Dms\Roster\DmsLongShiftService;
use App\Services\Dms\Roster\DmsRosterKaryawanAktif;
use App\Services\Dms\Roster\DmsRosterPeringatanStore;
use App\Services\Dms\Roster\DmsRosterAutoBannedService;
use App\Services\Dms\Roster\DmsRosterRegLiveService;
use App\Services\Dms\Roster\DmsRosterSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sinkronisasi Kepatuhan Roster dari scan RFID ke skema ternormalisasi.
 *
 *   php artisan dms:sync-roster-rfid --master          # tahap 1 saja (harian)
 *   php artisan dms:sync-roster-rfid --full            # backfill 1 Jan s/d hari ini
 *   php artisan dms:sync-roster-rfid                   # inkremental (tiap 30 menit) + kartu aturan banned
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

    public function handle(DmsRosterSyncService $sync, DmsRosterRegLiveService $live): int
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

        $this->segarkanAturanBanned($live);
        $this->segarkanLongShift();
        $this->segarkanKaryawanAktif();

        $this->info('Selesai dalam '.round(microtime(true) - $mulai, 1).' detik.');

        return self::SUCCESS;
    }

    /**
     * Tahap 5 — hitung ulang empat kartu aturan banned (Ringkasan Roster)
     * dari RFID dan simpan ke cache. Dikerjakan di sini karena proses
     * terjadwal ini memang terhubung ke OLAP; halaman web cukup membaca
     * hasilnya. Gagal di tahap ini tidak menggagalkan sinkronisasi.
     */
    private function segarkanAturanBanned(DmsRosterRegLiveService $live): void
    {
        $this->info('Tahap 5 — kartu aturan banned (RFID langsung) ...');

        try {
            $hasil = $live->segarkan(function (string $rentang, float $detik, int $sid): void {
                $this->line(sprintf('  %s → %s SID (%.1f dtk)', $rentang, $this->angka($sid), $detik));
            });
        } catch (Throwable $e) {
            $this->warn('  Gagal: '.$e->getMessage());

            return;
        }

        if ($hasil === null) {
            $this->warn('  Gagal: '.($live->galatTerakhir() ?? 'tidak diketahui'));

            return;
        }

        $this->line('  '.$this->angka((int) $hasil['populasi']).' karyawan dinilai; '
            .$this->angka(count($hasil['daftar']['onsite'])).' periode kerja > 70 hari.');

        $this->simpanPeringatan($hasil);
        $this->masukkanKeMasterBanned($live, $hasil);
    }

    /**
     * Tahap 5b — mendekati batas (belum melanggar): periode kerja 60–70 hari
     * dan kerja beruntun 10–13 hari, disimpan ke
     * dms_roster_peringatan sebagai sumber notifikasi. Gagal di tahap ini
     * tidak menggagalkan sinkronisasi.
     *
     * @param  array<string, mixed>  $hasil  Hasil DmsRosterRegLiveService::segarkan().
     */
    private function simpanPeringatan(array $hasil): void
    {
        if (! config('dms_roster.reg_live.peringatan.simpan', true)) {
            return;
        }

        $store = app(DmsRosterPeringatanStore::class);
        try {
            if (! $store->siap()) {
                $this->warn('Tahap 5b — tabel '.DmsRosterPeringatanStore::TABEL.' belum ada (jalankan migrasinya).');

                return;
            }
            $r = $store->simpan($hasil);
        } catch (Throwable $e) {
            $this->warn('Tahap 5b — gagal simpan peringatan: '.$e->getMessage());

            return;
        }

        $per = array_count_values(array_column($hasil['mendekati'] ?? [], 'kode'));
        $this->line(sprintf('Tahap 5b — peringatan disimpan: %s periode kerja 60–70 hari, %s kerja beruntun 10–13 hari; %s dihapus (keluar rentang).',
            $this->angka($per['onsite'] ?? 0), $this->angka($per['beruntun'] ?? 0), $this->angka($r['dihapus'])));
    }

    /**
     * Tahap 6 — pelanggaran periode kerja > 70 hari otomatis masuk Master
     * Roster Banned. Hanya bila DMS_AUTO_BANNED=true (bawaan mati, lihat
     * config dms_roster.reg_live.auto_banned).
     *
     * @param  array<string, mixed>  $hasil  Hasil DmsRosterRegLiveService::segarkan().
     */
    private function masukkanKeMasterBanned(DmsRosterRegLiveService $live, array $hasil): void
    {
        if (! config('dms_roster.reg_live.auto_banned.aktif', false)) {
            $this->line('Tahap 6 — auto-banned mati (DMS_AUTO_BANNED). Pratinjau: php artisan dms:roster-auto-banned');

            return;
        }

        $beruntun = (bool) config('dms_roster.reg_live.auto_banned.beruntun', false);
        $this->info('Tahap 6 — masukkan periode kerja > 70 hari'.($beruntun ? ' & kerja > 13 hari berturut-turut' : '')
            .' ke Master Roster Banned ...');

        try {
            $banned = app(DmsRosterAutoBannedService::class);
            $ditambah = $banned->masukkan($banned->calon(
                $hasil['daftar']['onsite'],
                $beruntun ? $hasil['daftar']['beruntun'] : [],
            )['calon']);
        } catch (Throwable $e) {
            $this->warn('  Gagal: '.$e->getMessage());

            return;
        }

        $this->line("  {$this->angka($ditambah)} karyawan ditambahkan ke sid_roster_banned_master.");

        // Master berubah → hitung ulang supaya yang baru di-banned hilang dari kartu.
        if ($ditambah > 0) {
            $live->segarkan();
        }
    }

    /**
     * Tahap 7 — Long Shift pengawas (docs/overshift.md) untuk panel Long
     * Shift di Ringkasan Roster, disimpan ke cache. Gagal di tahap ini tidak
     * menggagalkan sinkronisasi.
     */
    private function segarkanLongShift(): void
    {
        $this->info('Tahap 7 — Long Shift pengawas ...');

        $svc = app(DmsLongShiftService::class);
        try {
            $hasil = $svc->segarkan();
        } catch (Throwable $e) {
            $this->warn('  Gagal: '.$e->getMessage());

            return;
        }

        if ($hasil === null) {
            $this->warn('  Gagal: '.($svc->galatTerakhir() ?? 'tidak diketahui'));

            return;
        }

        $r = $hasil['ringkas'];
        $this->line(sprintf(
            '  %s pengawas (%s ada tap); %s Long Shift oleh %s orang (%.1f dtk).',
            $this->angka($r['pengawas']), $this->angka($r['ada_tap']),
            $this->angka($r['kejadian_long']), $this->angka($r['LONG_SHIFT']), $hasil['detik'],
        ));
    }

    /**
     * Tahap 8 — daftar seluruh karyawan aktif (filter perusahaan di template
     * Master Roster) ke cache. Gagal di tahap ini tidak menggagalkan sinkronisasi.
     */
    private function segarkanKaryawanAktif(): void
    {
        $this->info('Tahap 8 — karyawan aktif untuk template Master Roster ...');

        $svc = app(DmsRosterKaryawanAktif::class);
        $mulai = microtime(true);
        try {
            $hasil = $svc->segarkan();
        } catch (Throwable $e) {
            $this->warn('  Gagal: '.$e->getMessage());

            return;
        }

        if ($hasil === null) {
            $this->warn('  Gagal: '.($svc->galatTerakhir() ?? 'tidak diketahui'));

            return;
        }

        $this->line(sprintf('  %s karyawan aktif dari %s perusahaan (%.1f dtk).',
            $this->angka(count($hasil['karyawan'])), $this->angka(count($svc->perusahaan() ?? [])), microtime(true) - $mulai));
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
