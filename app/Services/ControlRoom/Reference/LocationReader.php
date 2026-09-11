<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Reference;

use App\Enums\ControlRoomSiteCode;
use App\Services\ControlRoom\ControlRoomSiteDutyBoardService;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Master lokasi dari bcbeats.bep_vw_site_lokasi_detil_lokasi (kolom slim,
 * tanpa geometry). Kekritisan = CONTAINS pada nama, bukan flag DB.
 */
final class LocationReader implements LocationReaderContract
{
    private const CACHE_TTL_SECONDS = 600;

    private const CACHE_KEY = 'control-room:locations:v3:site-lokasi-detil';

    /** @var array<string, list<string>> */
    private const SITE_SOURCE_ALIASES = [
        'BMO2' => ['BMO 2', 'BMO 2 BLOK 7', 'BMO 2 BLOK 8'],
    ];

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * @return Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>
     */
    public function all(ControlRoomSiteCode $site): Collection
    {
        $key = $site->sourceKey();

        return $this->fetchForSites([$key]);
    }

    /**
     * @return Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>
     */
    public function forCoverage(ControlRoomSiteCode $site): Collection
    {
        return $this->fetchForSites($this->sourceKeysFor($site));
    }

    /**
     * @param  Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>|list<array{site: string, lokasi: string, detail_lokasi: string}>  $rows
     * @return Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>
     */
    public function filterBySite(Collection|array $rows, ControlRoomSiteCode $site): Collection
    {
        $allowed = array_fill_keys($this->sourceKeysFor($site), true);

        return collect($rows)
            ->filter(fn (array $row): bool => isset($allowed[$row['site'] ?? '']))
            ->values();
    }

    /**
     * Nilai kolom `site` di view lokasi untuk filter coverage.
     * HO = semua site papan + alias BMO2; Marine/Eksplorasi/Jakarta tidak ikut.
     *
     * @return list<string>
     */
    public function sourceKeysFor(ControlRoomSiteCode $site): array
    {
        if ($site === ControlRoomSiteCode::HeadOffice) {
            $keys = [];
            foreach (ControlRoomSiteDutyBoardService::BOARD_SITE_CODES as $code) {
                foreach ($this->aliasesFor(ControlRoomSiteCode::from($code)) as $key) {
                    $keys[$key] = $key;
                }
            }

            return array_values($keys);
        }

        return $this->aliasesFor($site);
    }

    /**
     * @return array{site: string, lokasi: string, detail_lokasi: string}|null
     */
    public function find(string $lokasi, string $detilLokasi): ?array
    {
        return $this->fetchAll()->first(
            fn (array $row): bool => $this->normalize($row['lokasi']) === $this->normalize($lokasi)
                && $this->normalize($row['detail_lokasi']) === $this->normalize($detilLokasi)
        );
    }

    /**
     * Pola CONTAINS() dari Tableau existing (dikonfirmasi user 2026-09-06),
     * dicek murni sebagai string match — tidak perlu query tambahan karena
     * lokasi/detilLokasi sudah jadi parameter method ini. Keyword dikonfigurasi
     * di config('control-room.critical_area_keywords') supaya bisa disesuaikan
     * tanpa ubah kode kalau daftar areanya berubah.
     */
    public function isCritical(string $lokasi, string $detilLokasi): bool
    {
        $normalizedLokasi = $this->normalize($lokasi);
        $normalizedDetil = $this->normalize($detilLokasi);

        foreach ((array) config('control-room.critical_area_keywords.lokasi', []) as $keyword) {
            if ($keyword !== '' && str_contains($normalizedLokasi, mb_strtolower((string) $keyword))) {
                return true;
            }
        }

        foreach ((array) config('control-room.critical_area_keywords.detil_lokasi', []) as $keyword) {
            if ($keyword !== '' && str_contains($normalizedDetil, mb_strtolower((string) $keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>
     */
    public function criticalAreas(ControlRoomSiteCode $site): Collection
    {
        return $this->all($site)->filter(
            fn (array $row): bool => $this->isCritical($row['lokasi'], $row['detail_lokasi'])
        )->values();
    }

    /**
     * @return list<string>
     */
    private function aliasesFor(ControlRoomSiteCode $site): array
    {
        return self::SITE_SOURCE_ALIASES[$site->value] ?? [$site->sourceKey()];
    }

    /**
     * @param  list<string>  $sites
     * @return Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>
     */
    private function fetchForSites(array $sites): Collection
    {
        $sites = array_values(array_unique(array_filter($sites)));
        if ($sites === []) {
            return collect();
        }
        sort($sites);
        $cacheKey = self::CACHE_KEY.':'.hash('sha1', implode("\n", $sites));
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return collect($cached);
        }

        if (! $this->olap->isReachable()) {
            Log::warning('LocationReader: Postgres OLAP (pgsql_direct/pgsql_ssh) tidak terjangkau, mengembalikan collection kosong.');

            return collect();
        }

        $placeholders = implode(',', array_fill(0, count($sites), '?'));
        $sql = "
            SELECT
                TRIM(site) AS site,
                TRIM(lokasi) AS lokasi,
                TRIM(\"Detil Lokasi\") AS detail_lokasi
            FROM bcbeats.bep_vw_site_lokasi_detil_lokasi
            WHERE COALESCE(status_detil_lokasi, '0') = '1'
              AND BTRIM(COALESCE(lokasi, '')) <> ''
              AND TRIM(site) IN ({$placeholders})
        ";

        $rows = collect($this->olap->select($sql, $sites, 3000))
            ->map(fn (object $row): array => [
                'site' => trim((string) ($row->site ?? '')),
                'lokasi' => trim((string) ($row->lokasi ?? '')),
                'detail_lokasi' => trim((string) ($row->detail_lokasi ?? '')),
            ])
            ->filter(fn (array $row): bool => $row['lokasi'] !== '')
            ->values()
            ->all();

        if ($rows !== []) {
            Cache::put($cacheKey, $rows, self::CACHE_TTL_SECONDS);
        }

        return collect($rows);
    }

    /**
     * @return Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>
     */
    private function fetchAll(): Collection
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && $cached !== []) {
            return collect($cached);
        }

        if (! $this->olap->isReachable()) {
            Log::warning('LocationReader: Postgres OLAP (pgsql_direct/pgsql_ssh) tidak terjangkau, mengembalikan collection kosong.');

            return collect();
        }

        $sql = <<<'SQL'
            SELECT
                TRIM(site) AS site,
                TRIM(lokasi) AS lokasi,
                TRIM("Detil Lokasi") AS detail_lokasi
            FROM bcbeats.bep_vw_site_lokasi_detil_lokasi
            WHERE COALESCE(status_detil_lokasi, '0') = '1'
              AND BTRIM(COALESCE(lokasi, '')) <> ''
            SQL;

        $rows = collect($this->olap->select($sql, [], 3000))
            ->map(fn (object $row): array => [
                'site' => trim((string) ($row->site ?? '')),
                'lokasi' => trim((string) ($row->lokasi ?? '')),
                'detail_lokasi' => trim((string) ($row->detail_lokasi ?? '')),
            ])
            ->filter(fn (array $row): bool => $row['lokasi'] !== '')
            ->values()
            ->all();

        if ($rows !== []) {
            Cache::put(self::CACHE_KEY, $rows, self::CACHE_TTL_SECONDS);
        }

        return collect($rows);
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
