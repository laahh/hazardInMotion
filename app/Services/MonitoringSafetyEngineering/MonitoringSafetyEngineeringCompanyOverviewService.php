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
     * Tabel progress per perusahaan — metrik sama dengan Dashboard Komitmen.
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
        $companyRows = $this->buildCompanyRows(
            $dashboard['replikasi_items'] ?? [],
            $dashboard['safety_engineering_items'] ?? [],
            $dashboard['additional_safety_items'] ?? [],
            $companyFilter,
        );

        $filters = $dashboard['filters'];
        $filters['company'] = $companyFilter;

        return [
            'filters' => $filters,
            'filter_options' => $dashboard['filter_options'],
            'company_rows' => $companyRows,
            'totals' => $this->buildTotalsRow($companyRows),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $replikasiItems
     * @param  list<array<string, mixed>>  $safetyItems
     * @param  list<array<string, mixed>>  $additionalItems
     * @return list<array<string, mixed>>
     */
    private function buildCompanyRows(
        array $replikasiItems,
        array $safetyItems,
        array $additionalItems,
        string $companyFilter,
    ): array {
        $companies = [];

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

                if (! isset($companies[$company])) {
                    $companies[$company] = [
                        'replikasi' => [],
                        'safety_engineering' => [],
                        'additional_safety_engineering' => [],
                    ];
                }

                $companies[$company][$category][] = $item;
            }
        }

        ksort($companies, SORT_NATURAL | SORT_FLAG_CASE);

        $rows = [];

        foreach ($companies as $company => $grouped) {
            $replikasi = $this->buildStatusStat($grouped['replikasi'], 'replikasi_status');
            $safety = $this->buildStatusStat($grouped['safety_engineering'], 'standardisasi_status');
            $additional = $this->buildStatusStat($grouped['additional_safety_engineering'], 'additional_status');
            $allItems = array_merge(
                $grouped['replikasi'],
                $grouped['safety_engineering'],
                $grouped['additional_safety_engineering'],
            );
            $total = $this->buildStatusStat($allItems, 'overall');

            $rows[] = [
                'perusahaan' => $company,
                'total' => $total,
                'replikasi' => $replikasi,
                'safety_engineering' => $safety,
                'additional_safety_engineering' => $additional,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildTotalsRow(array $rows): array
    {
        $merge = static function (string $key) use ($rows): array {
            $items = [];

            foreach ($rows as $row) {
                $stat = $row[$key] ?? [];
                $items[] = [
                    'progress_status' => 'onprogress',
                    '_onprogress' => (int) ($stat['onprogress'] ?? 0),
                    '_overdue' => (int) ($stat['overdue'] ?? 0),
                    '_selesai' => (int) ($stat['selesai'] ?? 0),
                    'done' => (int) ($stat['done'] ?? 0),
                    'plan' => (int) ($stat['plan'] ?? 0),
                    'count' => (int) ($stat['count'] ?? 0),
                ];
            }

            $onprogress = (int) array_sum(array_column($items, '_onprogress'));
            $overdue = (int) array_sum(array_column($items, '_overdue'));
            $selesai = (int) array_sum(array_column($items, '_selesai'));
            $count = (int) array_sum(array_column($items, 'count'));

            return [
                'count' => $count,
                'onprogress' => $onprogress,
                'overdue' => $overdue,
                'selesai' => $selesai,
                'done' => (int) array_sum(array_column($items, 'done')),
                'plan' => (int) array_sum(array_column($items, 'plan')),
                'completed' => $selesai,
                'progress' => $count > 0 ? (int) round(($selesai / $count) * 100) : 0,
            ];
        };

        return [
            'perusahaan' => 'TOTAL',
            'total' => $merge('total'),
            'replikasi' => $merge('replikasi'),
            'safety_engineering' => $merge('safety_engineering'),
            'additional_safety_engineering' => $merge('additional_safety_engineering'),
        ];
    }

    /**
     * Sama dengan Dashboard Komitmen: progress_status → OP / OV / OK.
     *
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
