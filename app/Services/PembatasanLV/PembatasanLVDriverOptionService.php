<?php

declare(strict_types=1);

namespace App\Services\PembatasanLV;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Autocomplete driver/SID dari view safety (bukan bep_vw_wp_karyawan).
 *
 * Sumber: bcsid.bep_vw_safety_karyawan_aktif di database hse_automation
 * (koneksi pgsql_direct). View ini live, status AKTIF, ~22k baris, dan
 * punya site_dedicated / departemen yang lebih lengkap dari wp_karyawan.
 * Cron crontable_bep_vw_m_karyawan_aktif tidak dipakai — snapshot itu
 * memotong sebagian karyawan aktif.
 *
 * Query hanya 6 kolom, prefix ILIKE (tanpa leading %), LIMIT, cache 45 detik.
 */
class PembatasanLVDriverOptionService
{
    private const SOURCE = 'bcsid.bep_vw_safety_karyawan_aktif';

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

        $cacheKey = 'pembatasan_lv:driver_options:v2:'.md5(mb_strtolower($q).'|'.$limit);

        try {
            /** @var list<array{id: string, nama: string, kode_sid: string, nik: string, nama_perusahaan: string, site: string, dept: string}> $rows */
            $rows = Cache::remember($cacheKey, self::SEARCH_CACHE_TTL_SECONDS, function () use ($q, $limit): array {
                return $this->queryPrefix($q, $limit);
            });

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
    private function queryPrefix(string $q, int $limit): array
    {
        if (! $this->olap->isReachable()) {
            return [];
        }

        $prefix = addcslashes($q, '%_\\').'%';
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
            $this->olap->select($sql, [$prefix, $prefix, $prefix, $fetch], 2500),
            $limit
        );
    }

    /**
     * @return array{id: string, nama: string, kode_sid: string, nik: string, nama_perusahaan: string, site: string, dept: string}|null
     */
    private function queryExactSid(string $sid): ?array
    {
        if (! $this->olap->isReachable()) {
            return null;
        }

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

        $mapped = $this->mapUnique($this->olap->select($sql, [$sid], 2000), 1);
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

        $mapped = $this->mapUnique($this->olap->select($sqlCi, [$sid], 2000), 1);

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
