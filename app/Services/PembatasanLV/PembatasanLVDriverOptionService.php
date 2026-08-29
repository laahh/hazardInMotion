<?php

declare(strict_types=1);

namespace App\Services\PembatasanLV;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Autocomplete driver/SID dari data safety karyawan aktif.
 *
 * View live bcsid.bep_vw_safety_karyawan_aktif join ke m_karyawan (~6GB)
 * tanpa index — autocomplete kena statement_timeout jadi hasilnya kosong.
 * Snapshot bcsid.crontable_bep_vw_m_karyawan_aktif (~17MB, ~24.7k AKTIF)
 * punya kolom yang sama (kode_sid, nama, nik, perusahaan, site_dedicated,
 * departement) dan aman untuk request web.
 */
class PembatasanLVDriverOptionService
{
    private const SOURCE = 'bcsid.crontable_bep_vw_m_karyawan_aktif';

    private const MIN_QUERY_LENGTH = 2;

    private const SEARCH_CACHE_TTL_SECONDS = 45;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    public function options(string $q = '', int $limit = 30): Collection
    {
        $q = trim($q);
        $limit = max(1, min($limit, 100));

        if (mb_strlen($q) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        $cacheKey = 'pembatasan_lv:driver_options:v3:'.md5(mb_strtolower($q).'|'.$limit);

        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return collect($cached)->values();
            }

            $rows = $this->querySearch($q, $limit);
            Cache::put($cacheKey, $rows, self::SEARCH_CACHE_TTL_SECONDS);

            return collect($rows)->values();
        } catch (Throwable $e) {
            Log::warning('PembatasanLVDriverOptionService: '.$e->getMessage());

            return collect();
        }
    }

    public function findBySid(string $sid): ?array
    {
        $sid = trim($sid);
        if ($sid === '') {
            return null;
        }

        try {
            $row = $this->queryExactSid($sid);
            if ($row !== null) {
                return $row;
            }

            return $this->options($sid, 20)
                ->first(fn (array $item) => mb_strtolower($item['kode_sid']) === mb_strtolower($sid));
        } catch (Throwable $e) {
            Log::warning('PembatasanLVDriverOptionService findBySid: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Contains-match (%q%) di tabel 17MB aman; prefix-only membuat SID/nama
     * di tengah kata tidak ketemu.
     *
     * @return list<array{id: string, nama: string, kode_sid: string, nik: string, nama_perusahaan: string, site: string, dept: string}>
     */
    private function querySearch(string $q, int $limit): array
    {
        $like = '%'.addcslashes($q, '%_\\').'%';
        $fetch = min($limit * 4, 120);

        $sql = <<<SQL
SELECT
    TRIM(kode_sid) AS kode_sid,
    TRIM(nama) AS nama,
    TRIM(COALESCE(nik, '')) AS nik,
    TRIM(COALESCE(nama_perusahaan, '')) AS nama_perusahaan,
    TRIM(COALESCE(site_dedicated, '')) AS site,
    TRIM(COALESCE(departement, '')) AS departement
FROM {$this->source()}
WHERE kode_sid IS NOT NULL
  AND BTRIM(kode_sid) <> ''
  AND BTRIM(nama) <> ''
  AND (
      kode_sid ILIKE ?
      OR nama ILIKE ?
      OR nik ILIKE ?
  )
LIMIT ?
SQL;

        return $this->mapUnique(
            $this->olap->select($sql, [$like, $like, $like, $fetch], 4000),
            $limit
        );
    }

    /**
     * @return array{id: string, nama: string, kode_sid: string, nik: string, nama_perusahaan: string, site: string, dept: string}|null
     */
    private function queryExactSid(string $sid): ?array
    {
        $sql = <<<SQL
SELECT
    TRIM(kode_sid) AS kode_sid,
    TRIM(nama) AS nama,
    TRIM(COALESCE(nik, '')) AS nik,
    TRIM(COALESCE(nama_perusahaan, '')) AS nama_perusahaan,
    TRIM(COALESCE(site_dedicated, '')) AS site,
    TRIM(COALESCE(departement, '')) AS departement
FROM {$this->source()}
WHERE kode_sid = ?
LIMIT 1
SQL;

        $mapped = $this->mapUnique($this->olap->select($sql, [$sid], 3000), 1);
        if ($mapped !== []) {
            return $mapped[0];
        }

        $sqlCi = <<<SQL
SELECT
    TRIM(kode_sid) AS kode_sid,
    TRIM(nama) AS nama,
    TRIM(COALESCE(nik, '')) AS nik,
    TRIM(COALESCE(nama_perusahaan, '')) AS nama_perusahaan,
    TRIM(COALESCE(site_dedicated, '')) AS site,
    TRIM(COALESCE(departement, '')) AS departement
FROM {$this->source()}
WHERE LOWER(kode_sid) = LOWER(?)
LIMIT 1
SQL;

        $mapped = $this->mapUnique($this->olap->select($sqlCi, [$sid], 3000), 1);

        return $mapped[0] ?? null;
    }

    /**
     * @param  list<object>  $rows
     * @return list<array{id: string, nama: string, kode_sid: string, nik: string, nama_perusahaan: string, site: string, dept: string}>
     */
    private function mapUnique(array $rows, int $limit): array
    {
        $seen = [];
        $out = [];

        foreach ($rows as $row) {
            $nama = trim((string) ($row->nama ?? ''));
            $kodeSid = trim((string) ($row->kode_sid ?? ''));
            if ($nama === '' || $kodeSid === '') {
                continue;
            }

            $key = mb_strtolower($kodeSid);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $out[] = [
                'id' => $kodeSid,
                'nama' => $nama,
                'kode_sid' => $kodeSid,
                'nik' => trim((string) ($row->nik ?? '')),
                'nama_perusahaan' => trim((string) ($row->nama_perusahaan ?? '')),
                'site' => trim((string) ($row->site ?? '')),
                'dept' => trim((string) ($row->departement ?? '')),
            ];

            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    private function source(): string
    {
        return self::SOURCE;
    }
}
