<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lookup Area PJA BC / Mitra Kerja dari bcbeats.wan_vw_relasi_lokasi_pja.
 */
final class IscHazardPjaLookupService
{
    public const VIEW = 'bcbeats.wan_vw_relasi_lokasi_pja';

    private const CACHE_KEY = 'isc:hazard:pja_relasi_v1';

    private const CACHE_TTL_SECONDS = 600;

    private const LIMIT = 50;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * @return list<array{value: string, label: string, lokasi: ?string, site: ?string}>
     */
    public function bcOptions(string $q = '', string $lokasi = '', string $site = '', int $limit = self::LIMIT): array
    {
        return $this->options('kelompok_pja_bc', 'status_kelompok_pja_bc', $q, $lokasi, $site, $limit);
    }

    /**
     * @return list<array{value: string, label: string, lokasi: ?string, site: ?string}>
     */
    public function mitraOptions(string $q = '', string $lokasi = '', string $site = '', int $limit = self::LIMIT): array
    {
        return $this->options('kelompok_pja_mk', 'status_kelompok_pja_mk', $q, $lokasi, $site, $limit);
    }

    /**
     * Prefill PJA dari lokasi (bila relasi unik).
     *
     * @return array{area_pja_bc: ?string, area_pja_mitra: ?string}
     */
    public function suggestForLokasi(string $lokasi, string $site = ''): array
    {
        $lokasi = trim($lokasi);
        if ($lokasi === '') {
            return ['area_pja_bc' => null, 'area_pja_mitra' => null];
        }

        $rows = $this->filterRows($this->fetchMasterRows(), $lokasi, $site);
        $bc = $rows->pluck('kelompok_pja_bc')->filter()->unique()->values();
        $mk = $rows->pluck('kelompok_pja_mk')->filter()->unique()->values();

        return [
            'area_pja_bc' => $bc->count() === 1 ? (string) $bc->first() : null,
            'area_pja_mitra' => $mk->count() === 1 ? (string) $mk->first() : null,
        ];
    }

    /**
     * @return list<array{value: string, label: string, lokasi: ?string, site: ?string}>
     */
    private function options(
        string $labelColumn,
        string $statusColumn,
        string $q,
        string $lokasi,
        string $site,
        int $limit,
    ): array {
        $q = trim($q);
        $rows = $this->filterRows($this->fetchMasterRows(), $lokasi, $site);

        return $rows
            ->filter(fn (array $row) => (int) ($row[$statusColumn] ?? 0) === 1)
            ->pluck($labelColumn)
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn (string $v) => ! str_starts_with(mb_strtolower($v), '(jangan dipilih)'))
            ->unique(fn (string $v) => mb_strtolower($v))
            ->sort()
            ->values()
            ->when($q !== '', fn (Collection $items) => $items->filter(
                fn (string $value) => stripos($value, $q) !== false
            ))
            ->take($limit)
            ->map(fn (string $label): array => [
                'value' => $label,
                'label' => $label,
                'lokasi' => $lokasi !== '' ? $lokasi : null,
                'site' => $site !== '' ? $site : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{nama_site: string, lokasi: string, kelompok_pja_bc: ?string, status_kelompok_pja_bc: int, kelompok_pja_mk: ?string, status_kelompok_pja_mk: int}>  $rows
     * @return Collection<int, array{nama_site: string, lokasi: string, kelompok_pja_bc: ?string, status_kelompok_pja_bc: int, kelompok_pja_mk: ?string, status_kelompok_pja_mk: int}>
     */
    private function filterRows(Collection $rows, string $lokasi, string $site): Collection
    {
        $lokasi = trim($lokasi);
        $site = trim($site);

        return $rows
            ->when($site !== '', fn (Collection $c) => $c->filter(
                fn (array $row) => $this->siteMatches($row['nama_site'], $site)
            ))
            ->when($lokasi !== '', fn (Collection $c) => $c->filter(
                fn (array $row) => mb_strtolower($row['lokasi']) === mb_strtolower($lokasi)
                    || stripos($row['lokasi'], $lokasi) !== false
            ))
            ->values();
    }

    /**
     * @return Collection<int, array{nama_site: string, lokasi: string, kelompok_pja_bc: ?string, status_kelompok_pja_bc: int, kelompok_pja_mk: ?string, status_kelompok_pja_mk: int}>
     */
    private function fetchMasterRows(): Collection
    {
        try {
            /** @var list<array{nama_site: string, lokasi: string, kelompok_pja_bc: ?string, status_kelompok_pja_bc: int, kelompok_pja_mk: ?string, status_kelompok_pja_mk: int}> $rows */
            $rows = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
                if (! $this->olap->isReachable()) {
                    return [];
                }

                $sql = <<<'SQL'
SELECT
    TRIM(nama_site::text) AS nama_site,
    TRIM(lokasi::text) AS lokasi,
    NULLIF(TRIM(kelompok_pja_bc::text), '') AS kelompok_pja_bc,
    COALESCE(status_kelompok_pja_bc, 0) AS status_kelompok_pja_bc,
    NULLIF(TRIM(kelompok_pja_mk::text), '') AS kelompok_pja_mk,
    COALESCE(status_kelompok_pja_mk, 0) AS status_kelompok_pja_mk
FROM bcbeats.wan_vw_relasi_lokasi_pja
WHERE BTRIM(COALESCE(lokasi::text, '')) <> ''
SQL;

                return collect($this->olap->select($sql, [], 8000))
                    ->map(fn (object $row): array => [
                        'nama_site' => trim((string) ($row->nama_site ?? '')),
                        'lokasi' => trim((string) ($row->lokasi ?? '')),
                        'kelompok_pja_bc' => $this->nullableString($row->kelompok_pja_bc ?? null),
                        'status_kelompok_pja_bc' => (int) ($row->status_kelompok_pja_bc ?? 0),
                        'kelompok_pja_mk' => $this->nullableString($row->kelompok_pja_mk ?? null),
                        'status_kelompok_pja_mk' => (int) ($row->status_kelompok_pja_mk ?? 0),
                    ])
                    ->filter(fn (array $row): bool => $row['lokasi'] !== '')
                    ->values()
                    ->all();
            });

            return collect($rows);
        } catch (Throwable $e) {
            Log::warning('IscHazardPjaLookupService: '.$e->getMessage());

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

        return str_starts_with($view, $form.' ') || str_starts_with($view, $form);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
