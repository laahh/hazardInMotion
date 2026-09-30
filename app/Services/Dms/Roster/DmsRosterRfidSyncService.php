<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sinkronisasi scan RFID → tabel lokal untuk dashboard Kepatuhan Roster.
 *
 * Kenapa tidak query langsung ke OLAP saat request: bcsid.mv_checkinout_rfid
 * berisi 4,6 juta baris (758 MB) dan agregasi satu tahun penuh memindai
 * hampir seluruh materialized view — puluhan detik, jauh di atas anggaran
 * satu request HTTP. Jadi pola:
 *
 *   backfill sekali  → dms_roster_rfid_days (seluruh tahun)
 *   inkremental rutin → beberapa hari terakhir saja (indeks tanggal terpakai)
 *   kompilasi        → dms_roster_patterns (satu string pola per karyawan)
 *
 * Dashboard hanya membaca dms_roster_patterns (±12 ribu baris), sehingga
 * halaman tetap ringan sementara datanya mengikuti scan terbaru.
 */
final class DmsRosterRfidSyncService
{
    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
        private readonly DmsRosterJabatanKategori $kategori,
    ) {}

    public function isUp(): bool
    {
        return $this->olap->isReachable();
    }

    /**
     * Segarkan daftar karyawan yang masuk populasi roster: operator/driver &
     * mekanik lapangan, status AKTIF, Working Permit PASSED.
     *
     * Sumber: bcsid.bep_vw_safety_karyawan_aktif (lihat config/dms_roster.php
     * untuk alasan view ini dipilih dan mengapa penyaringan dilakukan di PHP).
     *
     * Atribut karyawan di-denormalisasi ke dms_roster_patterns supaya
     * dashboard tidak perlu menyentuh Postgres sama sekali.
     *
     * @return int jumlah karyawan dalam populasi
     */
    public function sinkronPopulasi(int $tahun): int
    {
        $view = (string) config('dms_roster.populasi.view_karyawan', 'bcsid.bep_vw_safety_karyawan_aktif');

        // SENGAJA tanpa WHERE: view ini bersumber dari m_karyawan (6 GB) lewat
        // dua lapis view, dan predikat apa pun terdorong ke tabel dasar
        // sehingga query melewati 30 detik. Scan polos ±22.800 baris selesai
        // cepat, jadi penyaringan dikerjakan di PHP di bawah.
        $rows = $this->olap->select(
            "SELECT kode_sid, nama, jabatan_struktural, jabatan_fungsional,
                    nama_perusahaan, site_dedicated, status_karyawan, status_permit
             FROM {$view}",
            [],
            20000,
        );

        if ($rows === []) {
            return 0;
        }

        $batch = $this->petakanPopulasi(
            $rows,
            $tahun,
            $this->hariTerakhirTersinkron($tahun) ?? $this->awalTahun($tahun)->toDateString(),
        );

        // Kolom 'pola' & 'hari_terakhir' TIDAK ditimpa di sini supaya
        // sinkronisasi populasi tidak menghapus pola yang sudah terkompilasi.
        foreach (array_chunk($batch, (int) config('dms_roster.sync.chunk_upsert', 1000)) as $chunk) {
            DB::table('dms_roster_patterns')->upsert(
                $chunk,
                ['kode_sid', 'tahun'],
                ['nama', 'jabatan', 'kategori', 'perusahaan', 'kode_pt', 'site', 'disinkron_pada', 'updated_at'],
            );
        }

        return count($batch);
    }

    /**
     * Saring & petakan baris mentah view karyawan menjadi baris
     * dms_roster_patterns. Dipisah dari I/O supaya bisa diuji langsung dengan
     * baris sintetis (PembatasanLVOlapQuery bersifat final, tidak bisa di-stub).
     *
     * @param  list<object>  $rows
     * @return list<array<string, mixed>>
     */
    public function petakanPopulasi(array $rows, int $tahun, string $hariTerakhir): array
    {
        $panjang = $this->panjangTahun($tahun);
        $sekarang = CarbonImmutable::now();
        $polaJabatan = '/'.((string) config('dms_roster.populasi.regex_jabatan')).'/';
        $statusKaryawan = strtoupper(trim((string) config('dms_roster.populasi.status_karyawan', 'AKTIF')));
        $statusPermit = strtoupper(trim((string) config('dms_roster.populasi.status_permit', 'PASSED')));
        $batch = [];
        // View memuat ±666 kode_sid ganda; baris pertama yang menang supaya
        // jumlah populasi sama dengan jumlah SID unik.
        $sudahAda = [];

        foreach ($rows as $row) {
            $sid = strtoupper(trim((string) ($row->kode_sid ?? '')));
            if ($sid === '' || isset($sudahAda[$sid])) {
                continue;
            }

            if ($statusKaryawan !== '' && strtoupper(trim((string) ($row->status_karyawan ?? ''))) !== $statusKaryawan) {
                continue;
            }

            // Kosongkan 'status_permit' di config untuk memasukkan karyawan
            // yang WP-nya sedang NOT PASSED. View safety jauh lebih ketat soal
            // ini daripada cron table (5.294 vs 1.538 NOT PASSED), sehingga
            // filter ini yang paling menentukan besar populasi.
            if ($statusPermit !== '' && strtoupper(trim((string) ($row->status_permit ?? ''))) !== $statusPermit) {
                continue;
            }

            $struktural = trim((string) ($row->jabatan_struktural ?? ''));
            $fungsional = trim((string) ($row->jabatan_fungsional ?? ''));
            if (preg_match($polaJabatan, strtoupper($struktural)) !== 1
                && preg_match($polaJabatan, strtoupper($fungsional)) !== 1) {
                continue;
            }

            $sudahAda[$sid] = true;
            $jabatan = $struktural !== '' ? $struktural : $fungsional;
            $perusahaan = trim((string) ($row->nama_perusahaan ?? ''));
            if ($perusahaan === '') {
                $perusahaan = '(Tidak diketahui)';
            }
            $site = trim((string) ($row->site_dedicated ?? ''));

            $batch[] = [
                'kode_sid' => $sid,
                'tahun' => $tahun,
                'nama' => mb_substr(trim((string) ($row->nama ?? '')), 0, 150),
                'jabatan' => mb_substr($jabatan, 0, 255),
                'kategori' => $this->kategori->kategoriDari($jabatan),
                'perusahaan' => mb_substr($perusahaan, 0, 255),
                'kode_pt' => $this->kodePt($perusahaan),
                'site' => mb_substr($site, 0, 60) ?: null,
                // Pola sebenarnya diisi kompilasiPola(); nilai awal ini hanya
                // dipakai kalau karyawan baru masuk populasi sebelum kompilasi.
                'pola' => str_repeat('o', $panjang),
                'hari_terakhir' => $hariTerakhir,
                'disinkron_pada' => $sekarang,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
        }

        return $batch;
    }

    /**
     * Tarik scan RFID PASSED pada rentang tanggal dan simpan sebagai fakta
     * harian (check-in pertama + check-out terakhir + gate-nya).
     *
     * @param  CarbonImmutable  $dari  inklusif
     * @param  CarbonImmutable  $sampai  inklusif
     * @return int jumlah baris hari yang ditulis
     */
    public function sinkronScan(CarbonImmutable $dari, CarbonImmutable $sampai, int $timeoutMs = 20000): int
    {
        $statusLolos = (string) config('dms_roster.populasi.status_lolos_scan', 'PASSED');

        $rows = $this->olap->select(
            "SELECT s.kode_sid,
                    s.tanggal_checkinout::date AS tanggal,
                    MIN(s.tanggal_checkinout) FILTER (WHERE s.jenis_checkinout = 'CHECK IN')  AS waktu_in,
                    MAX(s.tanggal_checkinout) FILTER (WHERE s.jenis_checkinout = 'CHECK OUT') AS waktu_out,
                    (ARRAY_AGG(s.gate ORDER BY s.tanggal_checkinout ASC)
                        FILTER (WHERE s.jenis_checkinout = 'CHECK IN'))[1] AS gate_in,
                    (ARRAY_AGG(s.gate ORDER BY s.tanggal_checkinout DESC)
                        FILTER (WHERE s.jenis_checkinout = 'CHECK OUT'))[1] AS gate_out
             FROM bcsid.mv_checkinout_rfid s
             WHERE UPPER(TRIM(COALESCE(s.status_lolos, ''))) = ?
               AND s.tanggal_checkinout >= ?
               AND s.tanggal_checkinout < ?
               AND s.kode_sid IS NOT NULL
               AND TRIM(s.kode_sid) <> ''
             GROUP BY 1, 2",
            [$statusLolos, $dari->startOfDay()->toDateTimeString(), $sampai->addDay()->startOfDay()->toDateTimeString()],
            $timeoutMs,
        );

        if ($rows === []) {
            return 0;
        }

        $populasi = $this->sidPopulasi((int) $dari->year);
        $sekarang = CarbonImmutable::now();
        $batch = [];
        $total = 0;

        foreach ($rows as $row) {
            $sid = strtoupper(trim((string) ($row->kode_sid ?? '')));
            // Hanya simpan karyawan yang masuk populasi roster — MV berisi
            // seluruh karyawan Berau Coal (±10,5 ribu SID per hari).
            if ($sid === '' || ($populasi !== [] && ! isset($populasi[$sid]))) {
                continue;
            }

            $batch[] = [
                'kode_sid' => $sid,
                'tanggal' => (string) $row->tanggal,
                'menit_checkin' => $this->keMenit($row->waktu_in ?? null),
                'menit_checkout' => $this->keMenit($row->waktu_out ?? null),
                'gate_in' => $this->potongGate($row->gate_in ?? null),
                'gate_out' => $this->potongGate($row->gate_out ?? null),
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
            $total++;
        }

        foreach (array_chunk($batch, (int) config('dms_roster.sync.chunk_upsert', 1000)) as $chunk) {
            DB::table('dms_roster_rfid_days')->upsert(
                $chunk,
                ['kode_sid', 'tanggal'],
                ['menit_checkin', 'menit_checkout', 'gate_in', 'gate_out', 'updated_at'],
            );
        }

        return $total;
    }

    /**
     * Rakit ulang string pola dari dms_roster_rfid_days.
     *
     * Karakter ke-0 = 1 Januari $tahun. Hari tanpa check-in tetap 'o';
     * konversi o → c (Cuti) dikerjakan rule engine, bukan di sini.
     *
     * @return int jumlah karyawan yang polanya diperbarui
     */
    public function kompilasiPola(int $tahun, ?string $hariTerakhir = null): int
    {
        $awal = $this->awalTahun($tahun);
        $akhir = $hariTerakhir !== null
            ? CarbonImmutable::parse($hariTerakhir)
            : $this->akhirDataTersedia($tahun);

        if ($akhir->lt($awal)) {
            return 0;
        }

        $panjang = $this->selisihHari($awal, $akhir) + 1;
        $batasPagi = (int) config('dms_roster.populasi.batas_menit_pagi', 720);

        $pola = [];
        foreach ($this->sidPopulasi($tahun) as $sid => $_) {
            $pola[$sid] = str_repeat('o', $panjang);
        }

        if ($pola === []) {
            return 0;
        }

        // chunkById, bukan chunk/offset: tabel harian bisa berisi jutaan baris
        // dan paginasi offset akan melambat drastis di halaman-halaman akhir.
        DB::table('dms_roster_rfid_days')
            ->select('id', 'kode_sid', 'tanggal', 'menit_checkin')
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->whereNotNull('menit_checkin')
            ->chunkById(5000, function ($rows) use (&$pola, $awal, $panjang, $batasPagi): void {
                foreach ($rows as $row) {
                    $sid = (string) $row->kode_sid;
                    if (! isset($pola[$sid])) {
                        continue;
                    }

                    $idx = $this->selisihHari($awal, CarbonImmutable::parse((string) $row->tanggal));
                    if ($idx < 0 || $idx >= $panjang) {
                        continue;
                    }

                    $pola[$sid][$idx] = ((int) $row->menit_checkin) < $batasPagi ? 'P' : 'M';
                }
            });

        $sekarang = CarbonImmutable::now();
        $akhirStr = $akhir->toDateString();
        $diperbarui = 0;

        foreach (array_chunk($pola, 500, true) as $chunk) {
            DB::transaction(function () use ($chunk, $tahun, $akhirStr, $sekarang, &$diperbarui): void {
                foreach ($chunk as $sid => $isi) {
                    $diperbarui += DB::table('dms_roster_patterns')
                        ->where('kode_sid', $sid)
                        ->where('tahun', $tahun)
                        ->update([
                            'pola' => $isi,
                            'hari_terakhir' => $akhirStr,
                            'disinkron_pada' => $sekarang,
                            'updated_at' => $sekarang,
                        ]);
                }
            });
        }

        return $diperbarui;
    }

    /**
     * Backfill satu tahun, dipecah per beberapa hari supaya setiap query tetap
     * memakai indeks tanggal dan tidak menabrak statement_timeout.
     *
     * @param  callable(string, string, int): void|null  $progress
     * @return int total baris hari yang ditulis
     */
    public function backfill(int $tahun, CarbonImmutable $dari, CarbonImmutable $sampai, ?callable $progress = null): int
    {
        $chunkHari = max(1, (int) config('dms_roster.sync.chunk_hari_backfill', 14));
        $timeout = (int) config('dms_roster.sync.timeout_backfill_ms', 20000);
        $total = 0;
        $kursor = $dari;

        while ($kursor->lte($sampai)) {
            $akhirChunk = $kursor->addDays($chunkHari - 1);
            if ($akhirChunk->gt($sampai)) {
                $akhirChunk = $sampai;
            }

            $ditulis = $this->sinkronScan($kursor, $akhirChunk, $timeout);
            $total += $ditulis;

            if ($progress !== null) {
                $progress($kursor->toDateString(), $akhirChunk->toDateString(), $ditulis);
            }

            $kursor = $akhirChunk->addDay();
        }

        return $total;
    }

    /**
     * Tanggal terakhir yang sudah punya baris scan di tabel lokal.
     */
    public function akhirDataTersedia(int $tahun): CarbonImmutable
    {
        $max = DB::table('dms_roster_rfid_days')
            ->whereBetween('tanggal', [$this->awalTahun($tahun)->toDateString(), $this->akhirTahun($tahun)->toDateString()])
            ->max('tanggal');

        if (! is_string($max) || $max === '') {
            return $this->awalTahun($tahun);
        }

        return CarbonImmutable::parse($max);
    }

    public function hariTerakhirTersinkron(int $tahun): ?string
    {
        $max = DB::table('dms_roster_patterns')->where('tahun', $tahun)->max('hari_terakhir');

        return is_string($max) && $max !== '' ? CarbonImmutable::parse($max)->toDateString() : null;
    }

    /**
     * Kode singkat PT untuk kolom & filter. Perusahaan yang tidak ada di
     * config diberi singkatan dari inisial namanya.
     */
    public function kodePt(string $perusahaan): string
    {
        /** @var array<string, array<string, mixed>> $daftar */
        $daftar = config('dms_roster.perusahaan', []);
        if (isset($daftar[$perusahaan]['kode'])) {
            return (string) $daftar[$perusahaan]['kode'];
        }

        $tanpaBadan = preg_replace('/^(PT|CV|UD|KOPERASI)\.?\s+/i', '', $perusahaan) ?? $perusahaan;
        $kata = preg_split('/\s+/', trim($tanpaBadan)) ?: [];
        $inisial = '';
        foreach ($kata as $k) {
            if ($k !== '') {
                $inisial .= mb_strtoupper(mb_substr($k, 0, 1));
            }
        }

        return mb_substr($inisial === '' ? 'NA' : $inisial, 0, 20);
    }

    /**
     * @return array<string, true>
     */
    private function sidPopulasi(int $tahun): array
    {
        /** @var list<string> $sids */
        $sids = DB::table('dms_roster_patterns')->where('tahun', $tahun)->pluck('kode_sid')->all();

        return array_fill_keys($sids, true);
    }

    private function keMenit(mixed $waktu): ?int
    {
        if ($waktu === null || $waktu === '') {
            return null;
        }

        try {
            $t = CarbonImmutable::parse((string) $waktu);
        } catch (Throwable $e) {
            Log::warning('DmsRoster: waktu scan tidak bisa diparse: '.$e->getMessage());

            return null;
        }

        return $t->hour * 60 + $t->minute;
    }

    private function potongGate(mixed $gate): ?string
    {
        $g = trim((string) ($gate ?? ''));

        return $g === '' ? null : mb_substr($g, 0, 255);
    }

    private function panjangTahun(int $tahun): int
    {
        return $this->selisihHari($this->awalTahun($tahun), $this->akhirTahun($tahun)) + 1;
    }

    /**
     * Selisih hari bertanda. Carbon 3 mengembalikan float, jadi dibulatkan
     * ke bawah secara eksplisit agar indeks pola selalu bilangan bulat.
     */
    private function selisihHari(CarbonImmutable $dari, CarbonImmutable $sampai): int
    {
        return (int) floor($dari->startOfDay()->diffInDays($sampai->startOfDay(), false));
    }

    private function awalTahun(int $tahun): CarbonImmutable
    {
        return CarbonImmutable::create($tahun, 1, 1)->startOfDay();
    }

    private function akhirTahun(int $tahun): CarbonImmutable
    {
        return CarbonImmutable::create($tahun, 12, 31)->startOfDay();
    }
}
