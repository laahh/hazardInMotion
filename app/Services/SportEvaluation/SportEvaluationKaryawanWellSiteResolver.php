<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Resolve site modul EvaluasiWell dari app_mixer.karyawan_well.site_dedicated
 * (match kode_sid), fallback ke employee_profiles.site.
 */
final class SportEvaluationKaryawanWellSiteResolver
{
    private const CACHE_TTL = 300;

    private const CACHE_KEY = 'evaluasi_well:karyawan_well_site_map_v1';

    /** @var array<string, string>|null UPPER(TRIM(kode_sid)) => site_dedicated */
    private ?array $map = null;

    /**
     * @return array<string, string>
     */
    public function siteMap(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        try {
            /** @var array<string, string> $cached */
            $cached = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
                $rows = DB::table('karyawan_well')
                    ->whereNotNull('kode_sid')
                    ->where('kode_sid', '<>', '')
                    ->whereNotNull('site_dedicated')
                    ->whereRaw("TRIM(site_dedicated) <> ''")
                    ->get(['kode_sid', 'site_dedicated']);

                $map = [];
                foreach ($rows as $row) {
                    $sid = mb_strtoupper(trim((string) $row->kode_sid));
                    $site = trim((string) $row->site_dedicated);
                    if ($sid === '' || $site === '' || isset($map[$sid])) {
                        continue;
                    }
                    $map[$sid] = $site;
                }

                return $map;
            });

            $this->map = $cached;
        } catch (Throwable $e) {
            report($e);
            $this->map = [];
        }

        return $this->map;
    }

    /**
     * Site ter-resolve; string kosong jika keduanya kosong.
     */
    public function resolve(?string $kodeSid, ?string $fallbackSite): string
    {
        $sid = mb_strtoupper(trim((string) ($kodeSid ?? '')));
        $fallback = trim((string) ($fallbackSite ?? ''));

        if ($sid !== '') {
            $dedicated = $this->siteMap()[$sid] ?? '';
            if ($dedicated !== '') {
                return $dedicated;
            }
        }

        return $fallback;
    }

    public function resolveOrDash(?string $kodeSid, ?string $fallbackSite): string
    {
        $site = $this->resolve($kodeSid, $fallbackSite);

        return $site !== '' ? $site : '-';
    }

    /**
     * @return list<string>
     */
    public function distinctDedicatedSites(): array
    {
        $sites = array_values(array_unique(array_values($this->siteMap())));
        sort($sites, SORT_STRING);

        return $sites;
    }

    /**
     * Gabungkan site_dedicated + site fallback (untuk opsi filter dropdown).
     *
     * @param  list<string>  $fallbackSites
     * @return list<string>
     */
    public function mergeFilterSites(array $fallbackSites): array
    {
        $merged = [];
        foreach (array_merge($this->distinctDedicatedSites(), $fallbackSites) as $site) {
            $site = trim((string) $site);
            if ($site === '') {
                continue;
            }
            $merged[$site] = true;
        }

        $list = array_keys($merged);
        sort($list, SORT_STRING);

        return $list;
    }

    /**
     * @return list<string> UPPER SID
     */
    public function mappedSids(): array
    {
        return array_keys($this->siteMap());
    }

    /**
     * @return list<string> UPPER SID
     */
    public function sidsForSite(string $site): array
    {
        $site = trim($site);
        if ($site === '') {
            return [];
        }

        $sids = [];
        foreach ($this->siteMap() as $sid => $dedicated) {
            if ($dedicated === $site) {
                $sids[] = $sid;
            }
        }

        return $sids;
    }

    /**
     * SID yang site_dedicated-nya cocok dengan term pencarian (substring, case-insensitive).
     *
     * @return list<string>
     */
    public function sidsMatchingSiteSearch(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $needle = mb_strtolower($term);
        $sids = [];
        foreach ($this->siteMap() as $sid => $dedicated) {
            if (mb_strpos(mb_strtolower($dedicated), $needle) !== false) {
                $sids[] = $sid;
            }
        }

        return $sids;
    }

    /**
     * Filter builder employee_profiles (alias e) berdasarkan resolved site.
     */
    public function applySiteFilter(Builder $query, string $siteFilter): Builder
    {
        $siteFilter = trim($siteFilter);
        if ($siteFilter === '') {
            return $query;
        }

        $sidsForSite = $this->sidsForSite($siteFilter);
        $mappedSids = $this->mappedSids();

        return $query->where(function (Builder $outer) use ($siteFilter, $sidsForSite, $mappedSids): void {
            if ($sidsForSite !== []) {
                $outer->where(function (Builder $inner) use ($sidsForSite): void {
                    $this->whereSidIn($inner, $sidsForSite);
                });
            }

            $outer->orWhere(function (Builder $inner) use ($siteFilter, $mappedSids): void {
                $inner->where('e.site', $siteFilter);
                if ($mappedSids !== []) {
                    $this->whereSidNotIn($inner, $mappedSids);
                }
            });
        });
    }

    /**
     * Tambah kondisi OR: resolved site cocok dengan term pencarian.
     */
    public function orWhereSiteMatchesSearch(Builder $query, string $like, string $searchTerm): Builder
    {
        $matchingSids = $this->sidsMatchingSiteSearch($searchTerm);
        $mappedSids = $this->mappedSids();

        return $query->orWhere(function (Builder $outer) use ($like, $matchingSids, $mappedSids): void {
            if ($matchingSids !== []) {
                $outer->where(function (Builder $inner) use ($matchingSids): void {
                    $this->whereSidIn($inner, $matchingSids);
                });
            }

            $outer->orWhere(function (Builder $inner) use ($like, $mappedSids): void {
                $inner->where('e.site', 'like', $like);
                if ($mappedSids !== []) {
                    $this->whereSidNotIn($inner, $mappedSids);
                }
            });
        });
    }

    /**
     * @param  list<string>  $sids
     */
    private function whereSidIn(Builder $query, array $sids): void
    {
        $first = true;
        foreach (array_chunk($sids, 800) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = 'UPPER(TRIM(COALESCE(e.kode_sid, \'\'))) IN ('.$placeholders.')';
            if ($first) {
                $query->whereRaw($sql, $chunk);
                $first = false;
            } else {
                $query->orWhereRaw($sql, $chunk);
            }
        }
    }

    /**
     * @param  list<string>  $sids
     */
    private function whereSidNotIn(Builder $query, array $sids): void
    {
        foreach (array_chunk($sids, 800) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $query->whereRaw(
                'UPPER(TRIM(COALESCE(e.kode_sid, \'\'))) NOT IN ('.$placeholders.')',
                $chunk
            );
        }
    }
}
