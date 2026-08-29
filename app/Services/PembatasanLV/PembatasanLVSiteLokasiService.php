<?php

declare(strict_types=1);

namespace App\Services\PembatasanLV;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Master lokasi. View bcbeats.bep_vw_site_lokasi_detil_lokasi ikut menarik
 * geo_tagging (geometry/GeoJSON) yang tidak dibutuhkan combobox.
 * Query langsung hierarki m_lokasi (id_tipe 100/200/300), cache 10 menit.
 */
class PembatasanLVSiteLokasiService
{
    private const CACHE_KEY = 'pembatasan_lv:site_lokasi_master_v2';

    private const CACHE_TTL_SECONDS = 600;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    public function lokasiOptions(string $q = '', int $limit = 50): Collection
    {
        $q = trim($q);

        return $this->fetchMasterRows()
            ->pluck('lokasi')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->when($q !== '', fn (Collection $items) => $items->filter(
                fn (string $value) => stripos($value, $q) !== false
            ))
            ->take($limit)
            ->values();
    }

    public function detailLokasiOptions(string $lokasi = '', string $q = '', int $limit = 50): Collection
    {
        $rows = $this->fetchMasterRows();
        $lokasi = trim($lokasi);
        $q = trim($q);

        if ($lokasi !== '') {
            $normalizedLokasi = $this->normalize($lokasi);
            $rows = $rows->filter(
                fn (array $row) => $this->normalize($row['lokasi']) === $normalizedLokasi
            );
        }

        return $rows
            ->pluck('detail_lokasi')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->when($q !== '', fn (Collection $items) => $items->filter(
                fn (string $value) => stripos($value, $q) !== false
            ))
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array{lokasi: string, detail_lokasi: string, site: string}>
     */
    private function fetchMasterRows(): Collection
    {
        try {
            /** @var list<array{lokasi: string, detail_lokasi: string, site: string}> $rows */
            $rows = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
                if (! $this->olap->isReachable()) {
                    return [];
                }

                $sql = <<<'SQL'
SELECT
    TRIM(site.nama) AS site,
    TRIM(lokasi.nama) AS lokasi,
    TRIM(detil.nama) AS detail_lokasi
FROM bcbeats.m_lokasi site
JOIN bcbeats.m_lokasi lokasi
    ON lokasi.id_parent = site.id
   AND lokasi.id_tipe = 200
   AND lokasi.is_active = '1'
JOIN bcbeats.m_lokasi detil
    ON detil.id_parent = lokasi.id
   AND detil.id_tipe = 300
   AND detil.is_active = '1'
WHERE site.id_tipe = 100
  AND site.is_active = '1'
SQL;

                return collect($this->olap->select($sql, [], 3000))
                    ->map(function (object $row): array {
                        return [
                            'site' => trim((string) ($row->site ?? '')),
                            'lokasi' => trim((string) ($row->lokasi ?? '')),
                            'detail_lokasi' => trim((string) ($row->detail_lokasi ?? '')),
                        ];
                    })
                    ->filter(fn (array $row) => $row['lokasi'] !== '')
                    ->unique(fn (array $row) => mb_strtolower($row['site'].'|'.$row['lokasi'].'|'.$row['detail_lokasi']))
                    ->values()
                    ->all();
            });

            return collect($rows);
        } catch (Throwable $e) {
            Log::warning('PembatasanLVSiteLokasiService: '.$e->getMessage());

            return collect();
        }
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
