<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sinkronisasi RFID → skema ternormalisasi Kepatuhan Roster.
 *
 * Empat tahap:
 *   1. sinkronKaryawan()  master karyawan + SIMPER + flag wajib_cek  (±8 rb)
 *   2. (gate disinkronkan otomatis di dalam tahap 3)                 (puluhan)
 *   3. sinkronScan()      fakta harian per rentang tanggal           (±1,2 jt/tahun)
 *   4. kompilasiPola()    rakit string pola per karyawan per tahun   (±8 rb)
 *
 * Kenapa penarikan scan dilakukan per RENTANG TANGGAL dan bukan per daftar
 * SID: indeks tanggal_checkinout bekerja baik untuk rentang sempit, sedangkan
 * IN-list ribuan SID memaksa rencana eksekusi buruk pada materialized view
 * 4,59 juta baris. Konsekuensinya, menarik populasi luas sama mahalnya dengan
 * populasi sempit — itulah sebabnya master karyawan menyimpan lebih banyak
 * orang daripada yang wajib dicek, dan pembedanya cuma kolom wajib_cek.
 */
final class DmsRosterSyncService
{
    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
        private readonly DmsRosterJabatanKategori $kategori,
    ) {}

    public function isUp(): bool
    {
        return $this->olap->isReachable();
    }

    // =================================================================
    // TAHAP 1 — master karyawan
    // =================================================================

    /**
     * Tarik karyawan AKTIF beserta status SIMPER dan Working Permit unit,
     * lalu tandai siapa yang masuk base "wajib dicek".
     *
     * @return array{total:int,wajib_cek:int}
     */
    public function sinkronKaryawan(): array
    {
        $rows = $this->ambilKaryawanOlap();
        if ($rows === []) {
            return ['total' => 0, 'wajib_cek' => 0];
        }

        $simper = $this->ambilSimperOlap();
        $jabatanWajib = $this->daftarJabatanWajib();
        $grupWp = $this->grupWp();
        $urutanGrup = $this->urutanGrup();
        $sekarang = CarbonImmutable::now();

        $batch = [];
        $sudahAda = [];
        $wajib = 0;

        foreach ($rows as $row) {
            $sid = strtoupper(trim((string) ($row->kode_sid ?? '')));
            // View memuat kode_sid ganda (beberapa baris Working Permit per
            // orang sudah diringkas di SQL, tapi SID kembar tetap mungkin).
            if ($sid === '' || isset($sudahAda[$sid])) {
                continue;
            }
            $sudahAda[$sid] = true;

            $jabatan = trim((string) ($row->jabatan_struktural ?? ''));

            // Master dibatasi ke jabatan yang relevan roster. Menyimpan SEMUA
            // karyawan aktif (±25 rb) akan menggelembungkan tabel fakta jadi
            // ±2,9 juta baris/tahun, padahal yang dinilai cuma operator,
            // driver, dan mekanik. Dengan batas ini master ±6.900 dan fakta
            // ±1,2 juta — superset 1,45x dari base wajib dicek, cukup longgar
            // untuk menampung perubahan definisi tanpa menarik ulang RFID.
            if (! isset($jabatanWajib[strtoupper($jabatan)])) {
                continue;
            }

            $nik = trim((string) ($row->nik ?? ''));
            $perusahaan = trim((string) ($row->nama_perusahaan ?? ''));

            $punyaSimper = $nik !== '' && isset($simper[$this->kunciSimper($nik, $perusahaan)]);

            // Satu kelompok per orang, urut prioritas — 442 orang memegang
            // A2B dan Hauler sekaligus, jadi harus ada yang menang.
            $wpGrup = 'tanpa';
            foreach ($urutanGrup as $kunci) {
                if ((bool) ($row->{'wp_'.$kunci} ?? false)) {
                    $wpGrup = $kunci;
                    break;
                }
            }

            // Jabatan sudah tersaring di atas, jadi yang membedakan base
            // "wajib dicek" tinggal kepemilikan SIMPER aktif.
            $wajibCek = $punyaSimper;
            if ($wajibCek) {
                $wajib++;
            }

            $batch[] = [
                'kode_sid' => $sid,
                'nik' => $nik !== '' ? mb_substr($nik, 0, 50) : null,
                'nama' => mb_substr(trim((string) ($row->nama ?? '')), 0, 150),
                'jabatan_struktural' => $jabatan !== '' ? mb_substr($jabatan, 0, 150) : null,
                'kategori' => $this->kategori->kategoriDari($jabatan),
                'perusahaan' => $perusahaan !== '' ? mb_substr($perusahaan, 0, 255) : null,
                'kode_pt' => $this->kodePt($perusahaan),
                'site' => mb_substr(trim((string) ($row->site ?? '')), 0, 60) ?: null,
                'status_karyawan' => mb_substr(trim((string) ($row->status_karyawan ?? '')), 0, 50) ?: null,
                'status_permit' => mb_substr(trim((string) ($row->status_permit ?? '')), 0, 50) ?: null,
                'wp_grup' => $wpGrup,
                'simper_aktif' => $punyaSimper,
                'wajib_cek' => $wajibCek,
                'disinkron_pada' => $sekarang,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
        }

        foreach (array_chunk($batch, $this->chunkUpsert()) as $chunk) {
            DB::table('dms_roster_karyawan')->upsert($chunk, ['kode_sid'], [
                'nik', 'nama', 'jabatan_struktural', 'kategori', 'perusahaan', 'kode_pt',
                'site', 'status_karyawan', 'status_permit', 'wp_grup', 'simper_aktif',
                'wajib_cek', 'disinkron_pada', 'updated_at',
            ]);
        }

        return ['total' => count($batch), 'wajib_cek' => $wajib];
    }

    // =================================================================
    // TAHAP 3 — fakta harian
    // =================================================================

    /**
     * Tarik scan PASSED pada rentang tanggal (inklusif) dan simpan sebagai
     * satu baris per karyawan per hari.
     *
     * @return int jumlah baris fakta yang ditulis
     */
    public function sinkronScan(CarbonImmutable $dari, CarbonImmutable $sampai, ?int $timeoutMs = null): int
    {
        $rows = $this->ambilScanOlap($dari, $sampai, $timeoutMs);
        if ($rows === []) {
            return 0;
        }

        $petaKaryawan = $this->petaKaryawan();
        if ($petaKaryawan === []) {
            // Tanpa master karyawan, karyawan_id tidak bisa diisi sama sekali.
            return 0;
        }

        $petaGate = $this->sinkronGate($rows);
        $batch = [];

        foreach ($rows as $row) {
            $sid = strtoupper(trim((string) ($row->kode_sid ?? '')));
            $karyawanId = $petaKaryawan[$sid] ?? null;
            // SID di luar master (mis. karyawan non-aktif) sengaja dilewati.
            if ($karyawanId === null) {
                continue;
            }

            $batch[] = [
                'karyawan_id' => $karyawanId,
                'tanggal' => (string) $row->tanggal,
                'menit_masuk' => $this->keMenit($row->waktu_in ?? null),
                'menit_keluar' => $this->keMenit($row->waktu_out ?? null),
                'gate_masuk_id' => $petaGate[$this->namaGate($row->gate_in ?? null)] ?? null,
                'gate_keluar_id' => $petaGate[$this->namaGate($row->gate_out ?? null)] ?? null,
            ];
        }

        // Commit per potongan — jangan bungkus 1,2 juta baris dalam satu
        // transaksi, undo log-nya membengkak tak perlu.
        foreach (array_chunk($batch, $this->chunkUpsert()) as $chunk) {
            DB::table('dms_roster_scan_harian')->upsert(
                $chunk,
                ['karyawan_id', 'tanggal'],
                ['menit_masuk', 'menit_keluar', 'gate_masuk_id', 'gate_keluar_id'],
            );
        }

        return count($batch);
    }

    /**
     * TAHAP 2 — daftarkan nama gate yang belum ada, lalu kembalikan petanya.
     * Dipanggil dari dalam tahap 3 karena nama gate baru bisa muncul kapan
     * saja di tengah backfill.
     *
     * @param  list<object>  $rows
     * @return array<string, int> nama gate => id
     */
    private function sinkronGate(array $rows): array
    {
        $nama = [];
        foreach ($rows as $row) {
            foreach ([$row->gate_in ?? null, $row->gate_out ?? null] as $g) {
                $bersih = $this->namaGate($g);
                if ($bersih !== '') {
                    $nama[$bersih] = true;
                }
            }
        }

        if ($nama !== []) {
            $sekarang = CarbonImmutable::now();
            $baris = array_map(
                static fn (string $n): array => ['nama' => $n, 'dibuat_pada' => $sekarang],
                array_keys($nama),
            );

            // upsert tanpa kolom update: baris yang sudah ada dibiarkan.
            DB::table('dms_roster_gate')->upsert($baris, ['nama'], ['nama']);
        }

        /** @var array<string, int> */
        return DB::table('dms_roster_gate')->pluck('id', 'nama')->all();
    }

    // =================================================================
    // TAHAP 4 — kompilasi pola
    // =================================================================

    /**
     * Rakit ulang string pola dari tabel fakta.
     *
     * Karakter ke-0 = 1 Januari tahun tersebut; panjangnya berhenti di hari
     * terakhir yang benar-benar punya data. Pola TIDAK boleh lebih panjang
     * dari itu — ekor hari kosong akan terbaca rule engine sebagai satu blok
     * cuti raksasa dan membuat semua orang tampil berstatus "Cuti".
     *
     * @return int jumlah baris pola yang ditulis
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

        /** @var array<int, string> $pola */
        $pola = [];
        /** @var array<int, int> $adaScan */
        $adaScan = [];
        foreach (DB::table('dms_roster_karyawan')->pluck('id') as $id) {
            $pola[(int) $id] = str_repeat('o', $panjang);
            $adaScan[(int) $id] = 0;
        }

        if ($pola === []) {
            return 0;
        }

        // chunkById, bukan chunk: paginasi offset melambat drastis di
        // halaman-halaman akhir tabel jutaan baris.
        DB::table('dms_roster_scan_harian')
            ->select('karyawan_id', 'tanggal', 'menit_masuk')
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->whereNotNull('menit_masuk')
            ->orderBy('karyawan_id')
            ->orderBy('tanggal')
            ->chunk(20000, function ($rows) use (&$pola, &$adaScan, $awal, $panjang, $batasPagi): void {
                foreach ($rows as $row) {
                    $id = (int) $row->karyawan_id;
                    if (! isset($pola[$id])) {
                        continue;
                    }

                    $idx = $this->selisihHari($awal, CarbonImmutable::parse((string) $row->tanggal));
                    if ($idx < 0 || $idx >= $panjang) {
                        continue;
                    }

                    $pola[$id][$idx] = ((int) $row->menit_masuk) < $batasPagi ? 'P' : 'M';
                    $adaScan[$id]++;
                }
            });

        $sekarang = CarbonImmutable::now();
        $batch = [];
        foreach ($pola as $id => $isi) {
            $batch[] = [
                'karyawan_id' => $id,
                'tahun' => $tahun,
                'pola' => $isi,
                'hari_pertama' => $awal->toDateString(),
                'hari_terakhir' => $akhir->toDateString(),
                'hari_ada_scan' => min(65535, $adaScan[$id]),
                'dikompilasi_pada' => $sekarang,
            ];
        }

        // Bulk upsert, BUKAN satu UPDATE per karyawan — versi per-baris tidak
        // pernah selesai dalam satu siklus penjadwalan.
        foreach (array_chunk($batch, $this->chunkUpsert()) as $chunk) {
            DB::table('dms_roster_pola')->upsert(
                $chunk,
                ['karyawan_id', 'tahun'],
                ['pola', 'hari_pertama', 'hari_terakhir', 'hari_ada_scan', 'dikompilasi_pada'],
            );
        }

        return count($batch);
    }

    // =================================================================
    // Backfill & util
    // =================================================================

    /**
     * Backfill dipecah per beberapa hari supaya tiap query tetap memakai
     * indeks tanggal dan tidak menabrak statement_timeout.
     *
     * @param  callable(string, string, int): void|null  $progress
     */
    public function backfill(CarbonImmutable $dari, CarbonImmutable $sampai, ?callable $progress = null): int
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

    public function akhirDataTersedia(int $tahun): CarbonImmutable
    {
        $max = DB::table('dms_roster_scan_harian')
            ->whereBetween('tanggal', [
                $this->awalTahun($tahun)->toDateString(),
                $this->akhirTahun($tahun)->toDateString(),
            ])
            ->max('tanggal');

        return is_string($max) && $max !== '' ? CarbonImmutable::parse($max) : $this->awalTahun($tahun);
    }

    /**
     * Kode singkat PT. Perusahaan di luar config diberi singkatan inisial.
     */
    public function kodePt(string $perusahaan): string
    {
        /** @var array<string, array<string, mixed>> $daftar */
        $daftar = config('dms_roster.perusahaan', []);
        if (isset($daftar[$perusahaan]['kode'])) {
            return (string) $daftar[$perusahaan]['kode'];
        }

        $tanpaBadan = preg_replace('/^(PT|CV|UD|KOPERASI)\.?\s+/i', '', $perusahaan) ?? $perusahaan;
        $inisial = '';
        foreach (preg_split('/\s+/', trim($tanpaBadan)) ?: [] as $kata) {
            if ($kata !== '') {
                $inisial .= mb_strtoupper(mb_substr($kata, 0, 1));
            }
        }

        return mb_substr($inisial === '' ? 'NA' : $inisial, 0, 20);
    }

    // =================================================================
    // Pengambilan dari OLAP
    // =================================================================

    /**
     * @return list<object>
     */
    private function ambilKaryawanOlap(): array
    {
        $grup = $this->grupWp();
        $kolomGrup = '';
        foreach ($this->urutanGrup() as $kunci) {
            $ids = $this->intList($grup[$kunci]['ids'] ?? []);
            $kolomGrup .= ",\n                   bool_or(id_work_permit IN ({$ids})) AS wp_{$kunci}";
        }

        $view = (string) config('dms_roster.sync.view_karyawan', 'bcsid.bep_vw_wp_karyawan');
        $status = (string) config('dms_roster.sync.status_karyawan', 'AKTIF');

        try {
            return $this->olap->select(
                "SELECT id,
                        max(kode_sid) AS kode_sid,
                        max(nik) AS nik,
                        max(nama) AS nama,
                        max(nama_perusahaan) AS nama_perusahaan,
                        max(jabatan_struktural) AS jabatan_struktural,
                        max(site) AS site,
                        max(status_karyawan) AS status_karyawan,
                        max(status_permit) AS status_permit{$kolomGrup}
                 FROM {$view}
                 WHERE upper(trim(coalesce(status_karyawan, ''))) = ?
                 GROUP BY id",
                [strtoupper($status)],
                (int) config('dms_roster.sync.timeout_master_ms', 30000),
            );
        } catch (Throwable $e) {
            Log::warning('DmsRoster sinkron karyawan gagal: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @return array<string, true> kunci "NIK|PERUSAHAAN"
     */
    private function ambilSimperOlap(): array
    {
        $tipe = $this->intList((array) config('dms_roster.total_karyawan.simper_tipe_ids', []));
        $status = (int) config('dms_roster.total_karyawan.simper_status_aktif', 1);

        if ($tipe === '') {
            return [];
        }

        try {
            $rows = $this->olap->select(
                "SELECT DISTINCT nik, nama_perusahaan
                 FROM bcsid.bep_vw_sid_dokumen_aktif_nonaktif
                 WHERE id_status_sid_dokumen = ?
                   AND id_jenis_tipe IN ({$tipe})",
                [$status],
                (int) config('dms_roster.sync.timeout_master_ms', 30000),
            );
        } catch (Throwable $e) {
            Log::warning('DmsRoster sinkron SIMPER gagal: '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[$this->kunciSimper((string) ($row->nik ?? ''), (string) ($row->nama_perusahaan ?? ''))] = true;
        }

        return $out;
    }

    /**
     * @return list<object>
     */
    private function ambilScanOlap(CarbonImmutable $dari, CarbonImmutable $sampai, ?int $timeoutMs): array
    {
        $statusLolos = (string) config('dms_roster.populasi.status_lolos_scan', 'PASSED');

        try {
            return $this->olap->select(
                "SELECT s.kode_sid,
                        s.tanggal_checkinout::date AS tanggal,
                        MIN(s.tanggal_checkinout) FILTER (WHERE s.jenis_checkinout = 'CHECK IN')  AS waktu_in,
                        MAX(s.tanggal_checkinout) FILTER (WHERE s.jenis_checkinout = 'CHECK OUT') AS waktu_out,
                        (ARRAY_AGG(s.gate ORDER BY s.tanggal_checkinout ASC)
                            FILTER (WHERE s.jenis_checkinout = 'CHECK IN'))[1] AS gate_in,
                        (ARRAY_AGG(s.gate ORDER BY s.tanggal_checkinout DESC)
                            FILTER (WHERE s.jenis_checkinout = 'CHECK OUT'))[1] AS gate_out
                 FROM bcsid.mv_checkinout_rfid s
                 WHERE upper(trim(coalesce(s.status_lolos, ''))) = ?
                   AND s.tanggal_checkinout >= ?
                   AND s.tanggal_checkinout < ?
                   AND s.kode_sid IS NOT NULL
                   AND trim(s.kode_sid) <> ''
                 GROUP BY 1, 2",
                [
                    strtoupper($statusLolos),
                    $dari->startOfDay()->toDateTimeString(),
                    $sampai->addDay()->startOfDay()->toDateTimeString(),
                ],
                $timeoutMs ?? (int) config('dms_roster.sync.timeout_incremental_ms', 20000),
            );
        } catch (Throwable $e) {
            Log::warning('DmsRoster tarik scan gagal: '.$e->getMessage());

            return [];
        }
    }

    // =================================================================
    // Helper
    // =================================================================

    /**
     * @return array<string, int> kode_sid => id
     */
    private function petaKaryawan(): array
    {
        /** @var array<string, int> */
        return DB::table('dms_roster_karyawan')->pluck('id', 'kode_sid')->all();
    }

    /**
     * @return array<string, true> jabatan (UPPER) yang masuk base wajib dicek
     */
    private function daftarJabatanWajib(): array
    {
        /** @var list<string> $jabatan */
        $jabatan = config('dms_roster.total_karyawan.jabatan', []);

        return array_fill_keys(array_map(
            static fn (string $j): string => strtoupper(trim($j)),
            $jabatan,
        ), true);
    }

    /**
     * @return array<string, array{label:string,ids:list<int>}>
     */
    private function grupWp(): array
    {
        /** @var array<string, array{label:string,ids:list<int>}> */
        return config('dms_roster.total_karyawan.wp_grup', []);
    }

    /**
     * @return list<string>
     */
    private function urutanGrup(): array
    {
        $grup = $this->grupWp();
        /** @var list<string> $prioritas */
        $prioritas = config('dms_roster.total_karyawan.wp_prioritas', []);

        $urut = array_values(array_filter($prioritas, static fn (string $k): bool => isset($grup[$k])));
        foreach (array_keys($grup) as $k) {
            if (! in_array($k, $urut, true)) {
                $urut[] = $k;
            }
        }

        return $urut;
    }

    private function kunciSimper(string $nik, string $perusahaan): string
    {
        return strtoupper(trim($nik)).'|'.strtoupper(trim($perusahaan));
    }

    private function namaGate(mixed $gate): string
    {
        return mb_substr(trim((string) ($gate ?? '')), 0, 150);
    }

    private function keMenit(mixed $waktu): ?int
    {
        if ($waktu === null || $waktu === '') {
            return null;
        }

        try {
            $t = CarbonImmutable::parse((string) $waktu);
        } catch (Throwable $e) {
            return null;
        }

        return $t->hour * 60 + $t->minute;
    }

    /**
     * @param  array<int, mixed>  $ids
     */
    private function intList(array $ids): string
    {
        return implode(',', array_map(static fn (mixed $v): int => (int) $v, $ids));
    }

    private function chunkUpsert(): int
    {
        return max(100, (int) config('dms_roster.sync.chunk_upsert', 1000));
    }

    /**
     * Selisih hari bertanda. Carbon 3 mengembalikan float, jadi dibulatkan
     * eksplisit agar indeks pola selalu bilangan bulat.
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
