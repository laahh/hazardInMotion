<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Master lokasi/detail lokasi untuk form Laporan Hazard ISC.
 * Sumber sama dengan Control Room dashboard:
 * bcbeats.bep_vw_site_lokasi_detil_lokasi (kolom slim, tanpa geometry).
 */
final class IscHazardLocationLookupService
{
    public const VIEW = 'bcbeats.bep_vw_site_lokasi_detil_lokasi';

    private const CACHE_KEY = 'isc:hazard:lokasi_master_v1';

    private const CACHE_TTL_SECONDS = 600;

    private const LIMIT = 50;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * @return list<array{value: string, label: string, site: string}>
     */
    public function lokasiOptions(string $q = '', string $site = '', int $limit = self::LIMIT): array
    {
        $q = trim($q);
        $site = trim($site);

        return $this->fetchMasterRows()
            ->when($site !== '', fn (Collection $rows) => $rows->filter(
                fn (array $row) => $this->siteMatches($row['site'], $site)
            ))
            ->pluck('lokasi')
            ->filter()
            ->unique(fn (string $v) => mb_strtolower($v))
            ->sort()
            ->values()
            ->when($q !== '', fn (Collection $items) => $items->filter(
                fn (string $value) => stripos($value, $q) !== false
            ))
            ->take($limit)
            ->map(fn (string $lokasi): array => [
                'value' => $lokasi,
                'label' => $lokasi,
                'site' => $site,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string, lokasi: string}>
     */
    public function detailLokasiOptions(string $lokasi = '', string $q = '', string $site = '', int $limit = self::LIMIT): array
    {
        $lokasi = trim($lokasi);
        $q = trim($q);
        $site = trim($site);

        $rows = $this->fetchMasterRows();
        if ($site !== '') {
            $rows = $rows->filter(fn (array $row) => $this->siteMatches($row['site'], $site));
        }
        if ($lokasi !== '') {
            $norm = $this->normalize($lokasi);
            $rows = $rows->filter(fn (array $row) => $this->normalize($row['lokasi']) === $norm);
        }

        return $rows
            ->pluck('detail_lokasi')
            ->filter()
            ->unique(fn (string $v) => mb_strtolower($v))
            ->sort()
            ->values()
            ->when($q !== '', fn (Collection $items) => $items->filter(
                fn (string $value) => stripos($value, $q) !== false
            ))
            ->take($limit)
            ->map(fn (string $detail): array => [
                'value' => $detail,
                'label' => $detail,
                'lokasi' => $lokasi,
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>
     */
    private function fetchMasterRows(): Collection
    {
        try {
            /** @var list<array{site: string, lokasi: string, detail_lokasi: string}> $rows */
            $rows = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
                if (! $this->olap->isReachable()) {
                    return [];
                }

                $sql = <<<'SQL'
SELECT
    TRIM(site) AS site,
    TRIM(lokasi) AS lokasi,
    TRIM("Detil Lokasi") AS detail_lokasi
FROM bcbeats.bep_vw_site_lokasi_detil_lokasi
WHERE COALESCE(status_site, '0') = '1'
  AND COALESCE(status_lokasi, '0') = '1'
  AND COALESCE(status_detil_lokasi, '0') = '1'
  AND BTRIM(COALESCE(lokasi, '')) <> ''
SQL;

                return collect($this->olap->select($sql, [], 8000))
                    ->map(fn (object $row): array => [
                        'site' => trim((string) ($row->site ?? '')),
                        'lokasi' => trim((string) ($row->lokasi ?? '')),
                        'detail_lokasi' => trim((string) ($row->detail_lokasi ?? '')),
                    ])
                    ->filter(fn (array $row): bool => $row['lokasi'] !== '')
                    ->unique(fn (array $row) => mb_strtolower($row['site'].'|'.$row['lokasi'].'|'.$row['detail_lokasi']))
                    ->values()
                    ->all();
            });

            return collect($rows);
        } catch (Throwable $e) {
            Log::warning('IscHazardLocationLookupService: '.$e->getMessage());

            return collect();
        }
    }

    private function siteMatches(string $viewSite, string $formSite): bool
    {
        $view = mb_strtoupper(trim($viewSite));
        $form = mb_strtoupper(trim($formSite));
        if ($view === '' || $form === '') {
            return true;
        }
        if ($view === $form) {
            return true;
        }

        // Form EXPLORASI ↔ view EKSPLORASI
        if (in_array($form, ['EXPLORASI', 'EKSPLORASI'], true)
            && in_array($view, ['EXPLORASI', 'EKSPLORASI'], true)) {
            return true;
        }

        // BMO → BMO 1 / BMO 2 / BMO 3 (legacy); exact "BMO 1" already handled above
        return str_starts_with($view, $form.' ') || str_starts_with($view, $form);
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
