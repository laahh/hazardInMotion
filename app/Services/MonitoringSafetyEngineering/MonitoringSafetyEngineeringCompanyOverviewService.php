<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use Illuminate\Http\Request;

class MonitoringSafetyEngineeringCompanyOverviewService
{
    public function __construct(
        private readonly MonitoringSafetyEngineeringDashboardService $dashboardService,
    ) {}

    /**
     * Overview progress komitmen: statistik + per site + per perusahaan.
     *
     * @return array<string, mixed>
     */
    public function buildDashboard(Request $request): array
    {
        $dashboardRequest = Request::create(
            $request->url(),
            'GET',
            array_merge($request->query(), ['company' => '']),
        );

        $dashboard = $this->dashboardService->buildDashboard($dashboardRequest);

        $companyFilter = (string) $request->get('company', '');
        $allItems = $this->mergeCategorizedItems(
            $dashboard['replikasi_items'] ?? [],
            $dashboard['safety_engineering_items'] ?? [],
            $dashboard['additional_safety_items'] ?? [],
            $companyFilter,
        );

        $siteGroups = $this->buildSiteGroups($allItems);
        $companyRows = $this->flattenCompanyRows($siteGroups);
        $siteRows = array_map(
            static fn (array $group): array => [
                'site' => $group['site'],
                'companies_count' => count($group['companies']),
                'total' => $group['total'],
                'replikasi' => $group['replikasi'],
                'safety_engineering' => $group['safety_engineering'],
                'additional_safety_engineering' => $group['additional_safety_engineering'],
            ],
            $siteGroups,
        );
        $totals = $this->buildTotalsFromItems($allItems);
        $stats = $this->buildStats($siteRows, $companyRows, $totals);

        $filters = $dashboard['filters'];
        $filters['company'] = $companyFilter;

        return [
            'filters' => $filters,
            'filter_options' => $dashboard['filter_options'],
            'stats' => $stats,
            'site_rows' => $siteRows,
            'site_groups' => $siteGroups,
            'company_rows' => $companyRows,
            'totals' => $totals,
            'charts' => $this->buildCharts($siteRows, $companyRows),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $replikasiItems
     * @param  list<array<string, mixed>>  $safetyItems
     * @param  list<array<string, mixed>>  $additionalItems
     * @return list<array<string, mixed>>
     */
    private function mergeCategorizedItems(
        array $replikasiItems,
        array $safetyItems,
        array $additionalItems,
        string $companyFilter,
    ): array {
        $merged = [];

        foreach ([
            'replikasi' => $replikasiItems,
            'safety_engineering' => $safetyItems,
            'additional_safety_engineering' => $additionalItems,
        ] as $category => $items) {
            foreach ($items as $item) {
                $company = trim((string) ($item['perusahaan'] ?? ''));
                if ($company === '') {
                    $company = '(Tanpa Perusahaan)';
                }

                if ($companyFilter !== '' && $company !== $companyFilter) {
                    continue;
                }

                $item['_category'] = $category;
                $item['perusahaan'] = $company;
                $item['site'] = trim((string) ($item['site'] ?? '')) !== ''
                    ? trim((string) $item['site'])
                    : '(Tanpa Site)';
                $merged[] = $item;
            }
        }

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $allItems
     * @return list<array<string, mixed>>
     */
    private function buildSiteGroups(array $allItems): array
    {
        $bySite = [];

        foreach ($allItems as $item) {
            $site = (string) $item['site'];
            $company = (string) $item['perusahaan'];
            $category = (string) $item['_category'];

            if (! isset($bySite[$site])) {
                $bySite[$site] = [];
            }

            if (! isset($bySite[$site][$company])) {
                $bySite[$site][$company] = [
                    'replikasi' => [],
                    'safety_engineering' => [],
                    'additional_safety_engineering' => [],
                ];
            }

            $bySite[$site][$company][$category][] = $item;
        }

        ksort($bySite, SORT_NATURAL | SORT_FLAG_CASE);

        $groups = [];

        foreach ($bySite as $site => $companiesMap) {
            ksort($companiesMap, SORT_NATURAL | SORT_FLAG_CASE);

            $companyRows = [];
            $siteBucket = [
                'replikasi' => [],
                'safety_engineering' => [],
                'additional_safety_engineering' => [],
            ];

            foreach ($companiesMap as $company => $grouped) {
                foreach (array_keys($siteBucket) as $category) {
                    $siteBucket[$category] = array_merge($siteBucket[$category], $grouped[$category]);
                }

                $companyRows[] = $this->buildEntityRow($company, $grouped, 'perusahaan');
            }

            $groups[] = [
                'site' => $site,
                'companies' => $companyRows,
                'total' => $this->buildStatusStat(array_merge(
                    $siteBucket['replikasi'],
                    $siteBucket['safety_engineering'],
                    $siteBucket['additional_safety_engineering'],
                ), 'overall'),
                'replikasi' => $this->buildStatusStat($siteBucket['replikasi'], 'replikasi_status'),
                'safety_engineering' => $this->buildStatusStat($siteBucket['safety_engineering'], 'standardisasi_status'),
                'additional_safety_engineering' => $this->buildStatusStat($siteBucket['additional_safety_engineering'], 'additional_status'),
            ];
        }

        return $groups;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $grouped
     * @return array<string, mixed>
     */
    private function buildEntityRow(string $name, array $grouped, string $nameKey): array
    {
        $replikasi = $this->buildStatusStat($grouped['replikasi'], 'replikasi_status');
        $safety = $this->buildStatusStat($grouped['safety_engineering'], 'standardisasi_status');
        $additional = $this->buildStatusStat($grouped['additional_safety_engineering'], 'additional_status');
        $total = $this->buildStatusStat(array_merge(
            $grouped['replikasi'],
            $grouped['safety_engineering'],
            $grouped['additional_safety_engineering'],
        ), 'overall');

        return [
            $nameKey => $name,
            'total' => $total,
            'replikasi' => $replikasi,
            'safety_engineering' => $safety,
            'additional_safety_engineering' => $additional,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $siteGroups
     * @return list<array<string, mixed>>
     */
    private function flattenCompanyRows(array $siteGroups): array
    {
        $rows = [];

        foreach ($siteGroups as $group) {
            foreach ($group['companies'] as $company) {
                $rows[] = array_merge($company, ['site' => $group['site']]);
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $allItems
     * @return array<string, mixed>
     */
    private function buildTotalsFromItems(array $allItems): array
    {
        $bucket = [
            'replikasi' => [],
            'safety_engineering' => [],
            'additional_safety_engineering' => [],
        ];

        foreach ($allItems as $item) {
            $bucket[(string) $item['_category']][] = $item;
        }

        return [
            'label' => 'TOTAL',
            'total' => $this->buildStatusStat($allItems, 'overall'),
            'replikasi' => $this->buildStatusStat($bucket['replikasi'], 'replikasi_status'),
            'safety_engineering' => $this->buildStatusStat($bucket['safety_engineering'], 'standardisasi_status'),
            'additional_safety_engineering' => $this->buildStatusStat($bucket['additional_safety_engineering'], 'additional_status'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $siteRows
     * @param  list<array<string, mixed>>  $companyRows
     * @param  array<string, mixed>  $totals
     * @return array<string, mixed>
     */
    private function buildStats(array $siteRows, array $companyRows, array $totals): array
    {
        $totalStat = $totals['total'] ?? [];
        $topSites = collect($siteRows)
            ->sortByDesc(static fn (array $row): int => (int) ($row['total']['progress'] ?? 0))
            ->take(3)
            ->values()
            ->all();
        $topCompanies = collect($companyRows)
            ->sortByDesc(static fn (array $row): int => (int) ($row['total']['progress'] ?? 0))
            ->take(5)
            ->values()
            ->all();
        $attentionCompanies = collect($companyRows)
            ->filter(static fn (array $row): bool => (int) ($row['total']['count'] ?? 0) > 0)
            ->sortBy(static fn (array $row): int => (int) ($row['total']['progress'] ?? 0))
            ->take(5)
            ->values()
            ->all();

        return [
            'sites_count' => count($siteRows),
            'companies_count' => count($companyRows),
            'items_count' => (int) ($totalStat['count'] ?? 0),
            'onprogress' => (int) ($totalStat['onprogress'] ?? 0),
            'overdue' => (int) ($totalStat['overdue'] ?? 0),
            'selesai' => (int) ($totalStat['selesai'] ?? 0),
            'progress' => (int) ($totalStat['progress'] ?? 0),
            'replikasi_progress' => (int) ($totals['replikasi']['progress'] ?? 0),
            'safety_progress' => (int) ($totals['safety_engineering']['progress'] ?? 0),
            'additional_progress' => (int) ($totals['additional_safety_engineering']['progress'] ?? 0),
            'top_sites' => $topSites,
            'top_companies' => $topCompanies,
            'attention_companies' => $attentionCompanies,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $siteRows
     * @param  list<array<string, mixed>>  $companyRows
     * @return array<string, mixed>
     */
    private function buildCharts(array $siteRows, array $companyRows): array
    {
        return [
            'site_progress' => [
                'labels' => array_column($siteRows, 'site'),
                'data' => array_map(
                    static fn (array $row): int => (int) ($row['total']['progress'] ?? 0),
                    $siteRows,
                ),
            ],
            'company_progress' => [
                'labels' => array_map(
                    static fn (array $row): string => (string) ($row['perusahaan'] ?? ''),
                    $companyRows,
                ),
                'data' => array_map(
                    static fn (array $row): int => (int) ($row['total']['progress'] ?? 0),
                    $companyRows,
                ),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{
     *     count: int,
     *     onprogress: int,
     *     overdue: int,
     *     selesai: int,
     *     done: int,
     *     plan: int,
     *     completed: int,
     *     progress: int,
     *     meta_mode: string
     * }
     */
    private function buildStatusStat(array $items, string $metaMode): array
    {
        $onprogress = 0;
        $overdue = 0;
        $selesai = 0;

        foreach ($items as $item) {
            $status = (string) ($item['progress_status'] ?? $item['replikasi_status'] ?? '');

            match ($status) {
                'onprogress' => $onprogress++,
                'overdue' => $overdue++,
                'selesai' => $selesai++,
                default => null,
            };
        }

        $count = count($items);

        return [
            'count' => $count,
            'onprogress' => $onprogress,
            'overdue' => $overdue,
            'selesai' => $selesai,
            'done' => (int) array_sum(array_column($items, 'done')),
            'plan' => (int) array_sum(array_column($items, 'plan')),
            'completed' => $selesai,
            'progress' => $count > 0 ? (int) round(($selesai / $count) * 100) : 0,
            'meta_mode' => $metaMode,
        ];
    }
}
