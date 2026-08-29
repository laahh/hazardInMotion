<?php

declare(strict_types=1);

namespace App\Services\PembatasanLV;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Autocomplete driver/SID = karyawan safety AKTIF.
 *
 * Cron crontable_bep_vw_m_karyawan_aktif tidak lengkap (contoh HO C5BXK /
 * IFA APRILLIANTO tidak ada). View live ikut join sys_user/jabatan dan berat.
 * Query ini memakai filter yang sama dengan bep_vw_safety_karyawan_aktif
 * (id_status = 1, perusahaan aktif, exclude dummy) langsung dari m_karyawan.
 */
class PembatasanLVDriverOptionService
{
    private const CRON_SOURCE = 'bcsid.crontable_bep_vw_m_karyawan_aktif';

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

        $cacheKey = 'pembatasan_lv:driver_options:v4:'.md5(mb_strtolower($q).'|'.$limit);

        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && $cached !== []) {
                return collect($cached)->values();
            }

            $rows = $this->querySearch($q, $limit);
            if ($rows !== []) {
                Cache::put($cacheKey, $rows, self::SEARCH_CACHE_TTL_SECONDS);
            }

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
     * @return list<array{id: string, nama: string, kode_sid: string, nik: string, nama_perusahaan: string, site: string, dept: string}>
     */
    private function querySearch(string $q, int $limit): array
    {
        $like = $this->containsPattern($q);
        $fetch = min($limit * 4, 120);
        $bindings = [$like, $like, $like, $fetch];

        try {
            return $this->mapUnique(
                $this->olap->select($this->liveSearchSql(), $bindings, 8000),
                $limit
            );
        } catch (Throwable $e) {
            Log::warning('PembatasanLVDriverOptionService live search fallback cron: '.$e->getMessage());

            return $this->mapUnique(
                $this->olap->select($this->cronSearchSql(), $bindings, 4000),
                $limit
            );
        }
    }

    /**
     * @return array{id: string, nama: string, kode_sid: string, nik: string, nama_perusahaan: string, site: string, dept: string}|null
     */
    private function queryExactSid(string $sid): ?array
    {
        try {
            $mapped = $this->mapUnique(
                $this->olap->select($this->liveExactSidSql(), [$sid], 8000),
                1
            );
            if ($mapped !== []) {
                return $mapped[0];
            }
        } catch (Throwable $e) {
            Log::warning('PembatasanLVDriverOptionService live SID fallback cron: '.$e->getMessage());
        }

        $mapped = $this->mapUnique(
            $this->olap->select($this->cronExactSidSql(), [$sid], 3000),
            1
        );

        return $mapped[0] ?? null;
    }

    private function liveSearchSql(): string
    {
        return <<<'SQL'
SELECT
    TRIM(mk.kode_sid) AS kode_sid,
    TRIM(mk.nama) AS nama,
    TRIM(COALESCE(mk.nik, '')) AS nik,
    TRIM(COALESCE(mp.nama, '')) AS nama_perusahaan,
    TRIM(COALESCE(sites.nama, '')) AS site,
    TRIM(COALESCE(dept.nama, '')) AS departement
FROM bcsid.m_karyawan mk
INNER JOIN bcsid.m_perusahaan mp
    ON mp.id = mk.id_perusahaan
   AND mp.id_status = 1
LEFT JOIN bcsid.m_departemen dept
    ON dept.id = mk.id_departemen
LEFT JOIN bcsid.sid_penugasan p
    ON p.id_karyawan = mk.id
   AND p.dedikasi = 1
   AND p.id_status = 1
LEFT JOIN bcsid.m_sites sites
    ON sites.id = p.id_site
WHERE mk.id_status = 1
  AND mk.id_perusahaan <> 5384
  AND mk.kode_sid IS NOT NULL
  AND mk.id <> 45682
  AND BTRIM(mk.nama) <> ''
  AND (
      mk.kode_sid ILIKE ?
      OR mk.nama ILIKE ?
      OR COALESCE(mk.nik, '') ILIKE ?
  )
LIMIT ?
SQL;
    }

    private function liveExactSidSql(): string
    {
        return <<<'SQL'
SELECT
    TRIM(mk.kode_sid) AS kode_sid,
    TRIM(mk.nama) AS nama,
    TRIM(COALESCE(mk.nik, '')) AS nik,
    TRIM(COALESCE(mp.nama, '')) AS nama_perusahaan,
    TRIM(COALESCE(sites.nama, '')) AS site,
    TRIM(COALESCE(dept.nama, '')) AS departement
FROM bcsid.m_karyawan mk
INNER JOIN bcsid.m_perusahaan mp
    ON mp.id = mk.id_perusahaan
   AND mp.id_status = 1
LEFT JOIN bcsid.m_departemen dept
    ON dept.id = mk.id_departemen
LEFT JOIN bcsid.sid_penugasan p
    ON p.id_karyawan = mk.id
   AND p.dedikasi = 1
   AND p.id_status = 1
LEFT JOIN bcsid.m_sites sites
    ON sites.id = p.id_site
WHERE mk.id_status = 1
  AND mk.id_perusahaan <> 5384
  AND mk.id <> 45682
  AND mk.kode_sid = ?
LIMIT 1
SQL;
    }

    private function cronSearchSql(): string
    {
        return <<<SQL
SELECT
    TRIM(kode_sid) AS kode_sid,
    TRIM(nama) AS nama,
    TRIM(COALESCE(nik, '')) AS nik,
    TRIM(COALESCE(nama_perusahaan, '')) AS nama_perusahaan,
    TRIM(COALESCE(site_dedicated, '')) AS site,
    TRIM(COALESCE(departement, '')) AS departement
FROM {$this->cronSource()}
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
    }

    private function cronExactSidSql(): string
    {
        return <<<SQL
SELECT
    TRIM(kode_sid) AS kode_sid,
    TRIM(nama) AS nama,
    TRIM(COALESCE(nik, '')) AS nik,
    TRIM(COALESCE(nama_perusahaan, '')) AS nama_perusahaan,
    TRIM(COALESCE(site_dedicated, '')) AS site,
    TRIM(COALESCE(departement, '')) AS departement
FROM {$this->cronSource()}
WHERE kode_sid = ?
LIMIT 1
SQL;
    }

    private function containsPattern(string $q): string
    {
        $normalized = preg_replace('/\s+/u', ' ', $q) ?? $q;
        $escaped = addcslashes($normalized, '%_\\');

        return '%'.str_replace(' ', '%', $escaped).'%';
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

    private function cronSource(): string
    {
        return self::CRON_SOURCE;
    }
}
