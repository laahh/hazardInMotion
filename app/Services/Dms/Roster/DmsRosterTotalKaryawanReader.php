<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Total karyawan untuk kartu ringkasan di halaman Ringkasan Roster
 * (/dms/roster-compliance/overview dan /dms/roster-compliance-static/overview).
 *
 * Definisinya TERPISAH dari populasi rule engine: di sini karyawan AKTIF pada
 * bcsid.bep_vw_wp_karyawan yang jabatan strukturalnya ada di daftar putih
 * config('dms_roster.total_karyawan.jabatan'), lalu dicek kepemilikan SIMPER
 * aktif terhadap bcsid.bep_vw_sid_dokumen_aktif_nonaktif.
 *
 * Satu karyawan bisa punya banyak baris Working Permit, karena itu CTE `k`
 * meringkasnya jadi satu baris per id sebelum dicocokkan ke daftar jabatan —
 * tanpa itu orang dengan beberapa WP akan terhitung berkali-kali.
 *
 * Halaman pemanggilnya adalah mockup statis; hanya angka inilah yang hidup.
 * Kalau OLAP tidak terjangkau, kembalikan null dan biarkan view memakai
 * angka contoh — halaman tidak boleh gagal render hanya karena angka ini.
 */
final class DmsRosterTotalKaryawanReader
{
    private const CACHE_KEY = 'dms_roster:total_karyawan:v1';

    private const CACHE_KEY_SITE = 'dms_roster:total_karyawan_site:v1';

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * @return array{
     *     total: int,
     *     punya_simper_aktif: int,
     *     tanpa_simper: int,
     *     punya_wp_unit: int,
     *     wp_unit_passed: int,
     *     wp_unit_tanpa_simper: int,
     *     kelompok: list<array<string, mixed>>,
     * }|null null bila OLAP tidak terjangkau atau query gagal
     */
    public function ambil(): ?array
    {
        $ttl = (int) config('dms_roster.total_karyawan.cache_ttl', 600);

        /** @var array<string, mixed>|null $hasil */
        $hasil = Cache::remember(self::CACHE_KEY, $ttl, fn (): ?array => $this->query());

        if ($hasil === null) {
            // Jangan kunci kegagalan selama TTL penuh — koneksi OLAP di sini
            // memang kadang putus-sambung.
            Cache::forget(self::CACHE_KEY);
        }

        /** @var array{total:int,punya_simper_aktif:int,tanpa_simper:int,punya_wp_unit:int,wp_unit_passed:int,wp_unit_tanpa_simper:int,kelompok:list<array<string,mixed>>}|null */
        return $hasil;
    }

    /**
     * Sebaran karyawan per site, dipecah menurut kelompok jabatan struktural
     * yang sama dengan ambil(). Dipakai bar chart di kartu Total Karyawan.
     *
     * @return list<array{site:string,operator_driver:int,mekanik:int,trainer:int,total:int}>|null
     */
    public function perSite(): ?array
    {
        $ttl = (int) config('dms_roster.total_karyawan.cache_ttl', 600);

        /** @var list<array<string, mixed>>|null $hasil */
        $hasil = Cache::remember(self::CACHE_KEY_SITE, $ttl, fn (): ?array => $this->querySite());

        if ($hasil === null) {
            Cache::forget(self::CACHE_KEY_SITE);
        }

        /** @var list<array{site:string,operator_driver:int,mekanik:int,trainer:int,total:int}>|null */
        return $hasil;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function querySite(): ?array
    {
        if (! $this->olap->isReachable()) {
            return null;
        }

        /** @var list<string> $jabatan */
        $jabatan = config('dms_roster.total_karyawan.jabatan', []);
        if ($jabatan === []) {
            return null;
        }

        $phJabatan = implode(',', array_fill(0, count($jabatan), '?'));

        $sql = <<<SQL
            WITH lst(j) AS (
              SELECT DISTINCT upper(trim(x)) FROM unnest(ARRAY[{$phJabatan}]::text[]) AS x
            ),
            k AS (
              SELECT id,
                     max(upper(trim(jabatan_struktural))) AS j,
                     max(coalesce(nullif(trim(site), ''), '(tanpa site)')) AS site
              FROM bcsid.bep_vw_wp_karyawan
              WHERE status_karyawan = 'AKTIF'
              GROUP BY id
            ),
            m AS (
              SELECT k.site,
                     CASE
                       WHEN k.j ~ '(MECHANIC|MEKANIK|TYRE|FITTER|WELDER)' THEN 'mekanik'
                       WHEN k.j ~ '(TRAINER|TRAINING|SISWA)'              THEN 'trainer'
                       ELSE 'operator_driver'
                     END AS grp
              FROM k JOIN lst USING (j)
            )
            SELECT site,
                   count(*) FILTER (WHERE grp = 'operator_driver') AS operator_driver,
                   count(*) FILTER (WHERE grp = 'mekanik')          AS mekanik,
                   count(*) FILTER (WHERE grp = 'trainer')          AS trainer,
                   count(*) AS total
            FROM m
            GROUP BY site
            ORDER BY total DESC
            SQL;

        try {
            $rows = $this->olap->select(
                $sql,
                array_values($jabatan),
                (int) config('dms_roster.total_karyawan.timeout_ms', 20000),
            );
        } catch (Throwable $e) {
            Log::warning('DmsRoster total karyawan per site gagal: '.$e->getMessage());

            return null;
        }

        if ($rows === []) {
            return null;
        }

        return array_map(static fn (object $r): array => [
            'site' => (string) $r->site,
            'operator_driver' => (int) $r->operator_driver,
            'mekanik' => (int) $r->mekanik,
            'trainer' => (int) $r->trainer,
            'total' => (int) $r->total,
        ], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function query(): ?array
    {
        if (! $this->olap->isReachable()) {
            return null;
        }

        /** @var list<string> $jabatan */
        $jabatan = config('dms_roster.total_karyawan.jabatan', []);
        /** @var list<int> $wpIds */
        $wpIds = config('dms_roster.total_karyawan.wp_unit_ids', []);
        /** @var list<int> $tipeIds */
        $tipeIds = config('dms_roster.total_karyawan.simper_tipe_ids', []);
        $statusAktif = (int) config('dms_roster.total_karyawan.simper_status_aktif', 1);

        if ($jabatan === [] || $wpIds === [] || $tipeIds === []) {
            return null;
        }

        // ARRAY[...] dibangun dari placeholder, bukan string yang disambung,
        // supaya nama jabatan tetap lewat binding.
        $phJabatan = implode(',', array_fill(0, count($jabatan), '?'));
        $wpList = $this->intList($wpIds);
        $tipeList = $this->intList($tipeIds);

        $sql = <<<SQL
            WITH lst(j) AS (
              SELECT DISTINCT upper(trim(x)) FROM unnest(ARRAY[{$phJabatan}]::text[]) AS x
            ),
            k AS (
              SELECT id,
                     max(nik) AS nik,
                     max(nama_perusahaan) AS nama_perusahaan,
                     max(upper(trim(jabatan_struktural))) AS j,
                     bool_or(id_work_permit IN ({$wpList})) AS wp_unit,
                     bool_or(id_work_permit IN ({$wpList}) AND status_permit = 'PASSED') AS wp_unit_passed
              FROM bcsid.bep_vw_wp_karyawan
              WHERE status_karyawan = 'AKTIF'
              GROUP BY id
            ),
            s AS (
              SELECT DISTINCT nik, nama_perusahaan
              FROM bcsid.bep_vw_sid_dokumen_aktif_nonaktif
              WHERE id_status_sid_dokumen = ?
                AND id_jenis_tipe IN ({$tipeList})
            ),
            m AS (
              SELECT k.*,
                     (s.nik IS NOT NULL) AS simper,
                     CASE
                       WHEN k.j ~ '(MECHANIC|MEKANIK|TYRE|FITTER|WELDER)' THEN 'B. Mekanik / Tyre / Welder'
                       WHEN k.j ~ '(TRAINER|TRAINING|SISWA)'              THEN 'C. Trainer / Siswa Operator'
                       ELSE 'A. Operator / Driver'
                     END AS grp
              FROM k
              JOIN lst USING (j)
              LEFT JOIN s ON s.nik = k.nik AND s.nama_perusahaan = k.nama_perusahaan
            )
            SELECT coalesce(grp, 'TOTAL') AS kelompok,
                   count(*) AS total,
                   count(*) FILTER (WHERE simper) AS punya_simper_aktif,
                   count(*) FILTER (WHERE NOT simper) AS tanpa_simper,
                   count(*) FILTER (WHERE wp_unit) AS punya_wp_unit,
                   count(*) FILTER (WHERE wp_unit_passed) AS wp_unit_passed,
                   count(*) FILTER (WHERE wp_unit AND NOT simper) AS wp_unit_tanpa_simper
            FROM m
            GROUP BY ROLLUP (grp)
            ORDER BY grp NULLS LAST
            SQL;

        try {
            $rows = $this->olap->select(
                $sql,
                [...array_values($jabatan), $statusAktif],
                (int) config('dms_roster.total_karyawan.timeout_ms', 20000),
            );
        } catch (Throwable $e) {
            Log::warning('DmsRoster total karyawan gagal: '.$e->getMessage());

            return null;
        }

        if ($rows === []) {
            return null;
        }

        $kelompok = [];
        $total = null;

        foreach ($rows as $row) {
            $baris = [
                'kelompok' => (string) $row->kelompok,
                'total' => (int) $row->total,
                'punya_simper_aktif' => (int) $row->punya_simper_aktif,
                'tanpa_simper' => (int) $row->tanpa_simper,
                'punya_wp_unit' => (int) $row->punya_wp_unit,
                'wp_unit_passed' => (int) $row->wp_unit_passed,
                'wp_unit_tanpa_simper' => (int) $row->wp_unit_tanpa_simper,
            ];

            if ($baris['kelompok'] === 'TOTAL') {
                $total = $baris;
            } else {
                $kelompok[] = $baris;
            }
        }

        if ($total === null) {
            return null;
        }

        return [
            'total' => $total['total'],
            'punya_simper_aktif' => $total['punya_simper_aktif'],
            'tanpa_simper' => $total['tanpa_simper'],
            'punya_wp_unit' => $total['punya_wp_unit'],
            'wp_unit_passed' => $total['wp_unit_passed'],
            'wp_unit_tanpa_simper' => $total['wp_unit_tanpa_simper'],
            'kelompok' => $kelompok,
        ];
    }

    /**
     * Daftar bilangan bulat untuk klausa IN. Nilainya dari config (bukan input
     * pengguna) dan dipaksa jadi int, jadi aman disisipkan langsung.
     *
     * @param  list<int>  $ids
     */
    private function intList(array $ids): string
    {
        return implode(',', array_map(static fn (mixed $v): int => (int) $v, $ids));
    }
}
