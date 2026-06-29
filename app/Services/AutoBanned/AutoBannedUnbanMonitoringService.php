<?php

declare(strict_types=1);

namespace App\Services\AutoBanned;

use App\Enums\AutoBannedUnbanStatus;
use App\Models\AutoBannedUnbanRequest;
use App\Support\AutoBanned\AutoBannedSchema;
use App\Support\AutoBanned\ScrDailyBannedColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AutoBannedUnbanMonitoringService
{
    /**
     * @return array{filter_date: string, site: string, status: string, q: string}
     */
    public function resolveFilters(Request $request): array
    {
        return [
            'filter_date' => trim((string) $request->query('filter_date', '')),
            'site' => trim((string) $request->query('site', '')),
            'status' => trim((string) $request->query('status', '')),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    public function tableAvailable(): bool
    {
        return AutoBannedSchema::hasUnbanRequestsTable();
    }

    /**
     * @param  array{filter_date?: string, site?: string, status?: string, q?: string}  $filters
     * @return array{
     *     filters: array{filter_date: string, site: string, status: string, q: string},
     *     period: array{filter_date: string},
     *     filterOptions: array{dates: Collection, sites: Collection, statuses: Collection},
     *     stats: array<string, int|float>,
     *     chartData: array<string, mixed>,
     *     unbanRows: Collection,
     *     tableAvailable: bool
     * }
     */
    public function buildDashboard(array $filters): array
    {
        $tableAvailable = $this->tableAvailable();

        if (! $tableAvailable) {
            return [
                'filters' => array_merge($filters, ['filter_date' => '', 'site' => '', 'status' => '', 'q' => '']),
                'period' => ['filter_date' => ''],
                'filterOptions' => [
                    'dates' => collect(),
                    'sites' => collect(),
                    'statuses' => $this->statusOptions(),
                ],
                'stats' => $this->emptyStats(),
                'chartData' => $this->emptyChartData(),
                'unbanRows' => collect(),
                'tableAvailable' => false,
            ];
        }

        $period = $this->resolvePeriod($filters);
        $resolvedFilters = array_merge($filters, [
            'filter_date' => $period['filter_date'],
        ]);

        $filterOptions = $this->filterOptions($resolvedFilters);
        $unbanRows = $this->unbanRows($resolvedFilters);
        $stats = $this->buildStats($resolvedFilters);
        $chartData = $this->buildChartData($resolvedFilters, $stats);

        return [
            'filters' => $resolvedFilters,
            'period' => $period,
            'filterOptions' => $filterOptions,
            'stats' => $stats,
            'chartData' => $chartData,
            'unbanRows' => $unbanRows,
            'tableAvailable' => true,
        ];
    }

    /**
     * @param  array{filter_date?: string}  $filters
     * @return array{filter_date: string}
     */
    private function resolvePeriod(array $filters): array
    {
        return [
            'filter_date' => $filters['filter_date'] ?? '',
        ];
    }

    /**
     * @param  array{filter_date?: string, site?: string, status?: string}  $filters
     * @return array{dates: Collection, sites: Collection, statuses: Collection}
     */
    private function filterOptions(array $filters): array
    {
        $baseQuery = AutoBannedUnbanRequest::query();
        $this->applyFilters($baseQuery, array_merge($filters, ['status' => '', 'q' => '']));

        $dates = AutoBannedUnbanRequest::query()
            ->selectRaw('DATE(created_at) as submit_date')
            ->distinct()
            ->orderByDesc('submit_date')
            ->pluck('submit_date')
            ->map(fn ($date) => $date instanceof Carbon ? $date->toDateString() : (string) $date)
            ->values();

        $sites = (clone $baseQuery)
            ->whereNotNull('site_dedicated')
            ->where('site_dedicated', '!=', '')
            ->select('site_dedicated')
            ->distinct()
            ->orderBy('site_dedicated')
            ->pluck('site_dedicated')
            ->values();

        return [
            'dates' => $dates,
            'sites' => $sites,
            'statuses' => $this->statusOptions(),
        ];
    }

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    private function statusOptions(): Collection
    {
        return collect(AutoBannedUnbanStatus::cases())
            ->map(fn (AutoBannedUnbanStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]);
    }

    /**
     * @param  array{filter_date?: string, site?: string, status?: string, q?: string}  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (($filters['filter_date'] ?? '') !== '') {
            $query->whereDate('created_at', $filters['filter_date']);
        }

        if (($filters['site'] ?? '') !== '') {
            $query->where('site_dedicated', $filters['site']);
        }

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('sid', 'like', $term)
                    ->orWhere('karyawan', 'like', $term)
                    ->orWhere('perusahaan', 'like', $term)
                    ->orWhere('alasan_pengajuan', 'like', $term)
                    ->orWhere('banned_reason', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * @param  array{filter_date?: string, site?: string, status?: string, q?: string}  $filters
     * @return Collection<int, AutoBannedUnbanRequest>
     */
    private function unbanRows(array $filters): Collection
    {
        $query = AutoBannedUnbanRequest::query()
            ->with([
                'scrDailyBanned:id,filter_date,'.ScrDailyBannedColumns::BANNED_REASON.','.ScrDailyBannedColumns::BANNED_STATUS.','.ScrDailyBannedColumns::SITE.','.ScrDailyBannedColumns::NAMA.','.ScrDailyBannedColumns::SID,
            ]);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();
    }

    /**
     * Total pengajuan unban disetujui — kumulatif, tanpa filter tanggal.
     *
     * @param  array{site?: string, q?: string}  $filters
     */
    public function countApprovedTotal(array $filters = []): int
    {
        if (! $this->tableAvailable()) {
            return 0;
        }

        $query = AutoBannedUnbanRequest::query()
            ->where('status', AutoBannedUnbanStatus::Approved->value);

        $this->applyFilters($query, array_merge([
            'filter_date' => '',
            'status' => '',
        ], $filters));

        return (int) $query->count();
    }

    /**
     * @param  array{filter_date?: string, site?: string, status?: string, q?: string}  $filters
     * @return array<string, int|float>
     */
    private function buildStats(array $filters): array
    {
        $baseQuery = AutoBannedUnbanRequest::query();
        $this->applyFilters($baseQuery, array_merge($filters, ['status' => '']));

        $stats = [
            'total' => (int) (clone $baseQuery)->count(),
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'withEvidence' => 0,
            'linkedScr' => 0,
            'hsctNotified' => 0,
            'approvalRate' => 0.0,
        ];

        foreach (AutoBannedUnbanStatus::cases() as $status) {
            $stats[$status->value] = (int) (clone $baseQuery)
                ->where('status', $status->value)
                ->count();
        }

        $stats['pending'] = (int) $stats[AutoBannedUnbanStatus::Pending->value];
        $stats['approved'] = (int) $stats[AutoBannedUnbanStatus::Approved->value];
        $stats['rejected'] = (int) $stats[AutoBannedUnbanStatus::Rejected->value];

        $stats['withEvidence'] = (int) (clone $baseQuery)
            ->whereNotNull('evidence_file_path')
            ->where('evidence_file_path', '!=', '')
            ->count();

        if (AutoBannedSchema::hasScrDailyBannedTable()) {
            $stats['linkedScr'] = (int) (clone $baseQuery)
                ->whereNotNull('scr_daily_banned_id')
                ->count();
        }

        $stats['hsctNotified'] = (int) (clone $baseQuery)
            ->whereNotNull('hsct_notified_at')
            ->count();

        $reviewed = $stats['approved'] + $stats['rejected'];
        $stats['approvalRate'] = $reviewed > 0
            ? round(($stats['approved'] / $reviewed) * 100, 1)
            : 0.0;

        return $stats;
    }

    /**
     * @param  array{filter_date?: string, site?: string, q?: string}  $filters
     * @param  array<string, int|float>  $stats
     * @return array<string, mixed>
     */
    private function buildChartData(array $filters, array $stats): array
    {
        $chartData = $this->emptyChartData();

        $chartData['byStatus'] = [
            'labels' => [
                AutoBannedUnbanStatus::Pending->label(),
                AutoBannedUnbanStatus::Approved->label(),
                AutoBannedUnbanStatus::Rejected->label(),
            ],
            'values' => [
                (int) $stats['pending'],
                (int) $stats['approved'],
                (int) $stats['rejected'],
            ],
        ];

        $chartData['bySite'] = $this->chartBySite($filters);
        $chartData['byPerusahaan'] = $this->chartGrouped($filters, 'perusahaan', 8);
        $chartData['dailyTrend'] = $this->chartDailyTrend($filters);
        $chartData['reviewSplit'] = [
            'labels' => ['Disetujui', 'Ditolak', 'Menunggu'],
            'values' => [
                (int) $stats['approved'],
                (int) $stats['rejected'],
                (int) $stats['pending'],
            ],
        ];

        return $chartData;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyChartData(): array
    {
        return [
            'byStatus' => ['labels' => [], 'values' => []],
            'bySite' => ['labels' => [], 'values' => []],
            'byPerusahaan' => ['labels' => [], 'values' => []],
            'dailyTrend' => ['labels' => [], 'total' => [], 'approved' => [], 'rejected' => []],
            'reviewSplit' => ['labels' => [], 'values' => []],
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function emptyStats(): array
    {
        return [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'withEvidence' => 0,
            'linkedScr' => 0,
            'hsctNotified' => 0,
            'approvalRate' => 0.0,
        ];
    }

    /**
     * @param  array{filter_date?: string, site?: string, q?: string}  $filters
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function chartBySite(array $filters): array
    {
        $query = AutoBannedUnbanRequest::query()
            ->selectRaw('site_dedicated as label, COUNT(*) as total')
            ->whereNotNull('site_dedicated')
            ->where('site_dedicated', '!=', '');

        $this->applyFilters($query, array_merge($filters, ['status' => '']));

        $rows = $query
            ->groupBy('site_dedicated')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'labels' => $rows->pluck('label')->map(fn ($v) => (string) $v)->all(),
            'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * @param  array{filter_date?: string, site?: string, q?: string}  $filters
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function chartGrouped(array $filters, string $column, int $limit): array
    {
        $query = AutoBannedUnbanRequest::query()
            ->selectRaw($column.' as label, COUNT(*) as total')
            ->whereNotNull($column)
            ->where($column, '!=', '');

        $this->applyFilters($query, array_merge($filters, ['status' => '']));

        $rows = $query
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return [
            'labels' => $rows->pluck('label')->map(fn ($v) => (string) $v)->all(),
            'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * @param  array{filter_date?: string, site?: string, q?: string}  $filters
     * @return array{labels: array<int, string>, total: array<int, int>, approved: array<int, int>, rejected: array<int, int>}
     */
    private function chartDailyTrend(array $filters): array
    {
        $endDate = ($filters['filter_date'] ?? '') !== ''
            ? Carbon::parse($filters['filter_date'])
            : now();

        $labels = [];
        $total = [];
        $approved = [];
        $rejected = [];

        for ($i = 13; $i >= 0; $i--) {
            $date = $endDate->copy()->subDays($i);
            $dateStr = $date->toDateString();
            $labels[] = $date->format('d M');

            $dayQuery = AutoBannedUnbanRequest::query()->whereDate('created_at', $dateStr);
            $this->applyFilters($dayQuery, array_merge($filters, ['filter_date' => '']));

            $total[] = (int) (clone $dayQuery)->count();
            $approved[] = (int) (clone $dayQuery)
                ->where('status', AutoBannedUnbanStatus::Approved->value)
                ->count();
            $rejected[] = (int) (clone $dayQuery)
                ->where('status', AutoBannedUnbanStatus::Rejected->value)
                ->count();
        }

        return compact('labels', 'total', 'approved', 'rejected');
    }
}
