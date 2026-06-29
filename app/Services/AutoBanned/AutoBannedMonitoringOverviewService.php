<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AutoBannedMonitoringOverviewService
{
    public function __construct(
        private readonly AutoBannedDailyDashboardService $dailyDashboardService,
        private readonly AutoBannedUnbanMonitoringService $unbanMonitoringService,
        private readonly AutoBannedMonitoringSummaryService $summaryService,
    ) {}

    /**
     * @return array{filter_date: string, site: string, perusahaan: string, q: string}
     */
    public function resolveFilters(Request $request): array
    {
        return [
            'filter_date' => trim((string) $request->query('filter_date', '')),
            'site' => trim((string) $request->query('site', '')),
            'perusahaan' => trim((string) $request->query('perusahaan', '')),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    /**
     * @param  array{filter_date?: string, site?: string, perusahaan?: string, q?: string}  $filters
     * @return array{
     *     filters: array{filter_date: string, site: string, perusahaan: string, q: string},
     *     filterOptions: array{dates: Collection, sites: Collection, perusahaan: Collection},
     *     summaryStats: array{totalSudahDiBanned: int, totalMasihBanned: int, totalUnBanned: int},
     *     banned: array<string, mixed>,
     *     unban: array<string, mixed>
     * }
     */
    public function buildOverview(array $filters): array
    {
        $bannedFilters = [
            'filter_date' => $filters['filter_date'] ?? '',
            'site' => $filters['site'] ?? '',
            'perusahaan' => $filters['perusahaan'] ?? '',
            'automation_status' => '',
            'q' => $filters['q'] ?? '',
        ];

        $unbanFilters = [
            'filter_date' => $filters['filter_date'] ?? '',
            'site' => $filters['site'] ?? '',
            'status' => '',
            'q' => $filters['q'] ?? '',
        ];

        $banned = $this->dailyDashboardService->buildDashboard($bannedFilters);
        $unban = $this->unbanMonitoringService->buildDashboard($unbanFilters);

        return [
            'filters' => $filters,
            'filterOptions' => $this->mergeFilterOptions($banned, $unban),
            'summaryStats' => $this->summaryService->buildSummaryStats([
                'site' => $filters['site'] ?? '',
                'perusahaan' => $filters['perusahaan'] ?? '',
                'q' => $filters['q'] ?? '',
            ]),
            'banned' => $banned,
            'unban' => $unban,
        ];
    }

    /**
     * @param  array<string, mixed>  $banned
     * @param  array<string, mixed>  $unban
     * @return array{dates: Collection, sites: Collection, perusahaan: Collection}
     */
    private function mergeFilterOptions(array $banned, array $unban): array
    {
        /** @var Collection<int, string> $bannedDates */
        $bannedDates = $banned['filterOptions']['dates'] ?? collect();
        /** @var Collection<int, string> $unbanDates */
        $unbanDates = $unban['filterOptions']['dates'] ?? collect();

        $dates = $bannedDates
            ->merge($unbanDates)
            ->unique()
            ->sortDesc()
            ->values();

        /** @var Collection<int, string> $bannedSites */
        $bannedSites = $banned['filterOptions']['sites'] ?? collect();
        /** @var Collection<int, string> $unbanSites */
        $unbanSites = $unban['filterOptions']['sites'] ?? collect();

        $sites = $bannedSites
            ->merge($unbanSites)
            ->unique()
            ->sort()
            ->values();

        return [
            'dates' => $dates,
            'sites' => $sites,
            'perusahaan' => $banned['filterOptions']['perusahaan'] ?? collect(),
        ];
    }
}
