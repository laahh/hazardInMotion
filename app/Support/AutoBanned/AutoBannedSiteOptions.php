<?php

declare(strict_types=1);

namespace App\Support\AutoBanned;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Opsi filter site kanonik Auto Banned + pola alias di sumber data.
 */
final class AutoBannedSiteOptions
{
    /** @var list<string> */
    public const FILTER_SITES = [
        'BMO 1',
        'BMO 2',
        'BMO 3',
        'GMO',
        'SMO',
        'LMO',
        'Marine',
        'HOTE',
    ];

    /** @var array<string, list<string>> */
    private const MATCH_PATTERNS = [
        'BMO 1' => ['BMO 1', 'BMO1', 'BMO-1'],
        'BMO 2' => ['BMO 2', 'BMO2', 'BMO-2'],
        'BMO 3' => ['BMO 3', 'BMO3', 'BMO-3'],
        'GMO' => ['GMO'],
        'SMO' => ['SMO'],
        'LMO' => ['LMO'],
        'Marine' => ['Marine', 'MARINE', 'MTL', 'CPP', 'PORT', 'JETTY'],
        'HOTE' => ['HOTE', 'HO', 'HEAD OFFICE'],
    ];

    /**
     * @return list<string>
     */
    public static function matchValuesForFilter(string $canonicalSite): array
    {
        $patterns = self::MATCH_PATTERNS[$canonicalSite] ?? [$canonicalSite];

        return array_values(array_unique($patterns));
    }

    /**
     * @param  Collection<int, string>  $fromDatabase
     * @return Collection<int, string>
     */
    public static function mergeFilterOptions(Collection $fromDatabase): Collection
    {
        return collect(self::FILTER_SITES)
            ->merge($fromDatabase)
            ->map(static fn ($site): string => trim((string) $site))
            ->filter(static fn (string $site): bool => $site !== '')
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyBannedLogSiteFilter(Builder $query, string $canonicalSite): void
    {
        $values = self::matchValuesForFilter($canonicalSite);

        $query->where(function (Builder $inner) use ($values): void {
            $inner->whereIn('site_dedicated', $values);

            if (AutoBannedSchema::hasScrDailyBannedTable()) {
                $inner->orWhereHas(
                    'scrDailyBanned',
                    static fn (Builder $scr): Builder => $scr->whereIn(ScrDailyBannedColumns::SITE, $values),
                );
            }
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyBannedLogWeeklySiteFilter(Builder $query, string $canonicalSite): void
    {
        $values = self::matchValuesForFilter($canonicalSite);

        $query->where(function (Builder $inner) use ($values): void {
            $inner->whereIn('site_dedicated', $values);

            if (AutoBannedSchema::hasScrWeeklyBannedTable()) {
                $inner->orWhereHas(
                    'scrWeeklyBanned',
                    static fn (Builder $scr): Builder => $scr->whereIn(ScrWeeklyBannedColumns::SITE, $values),
                );
            }
        });
    }
}
