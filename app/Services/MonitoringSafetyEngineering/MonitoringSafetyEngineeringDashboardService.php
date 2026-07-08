<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Models\MonitoringSafetyEngineeringRecord;
use App\Services\MonitoringSafetyEngineering\Concerns\BuildsMonitoringSafetyEngineeringItems;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MonitoringSafetyEngineeringDashboardService
{
    use BuildsMonitoringSafetyEngineeringItems;

    /**
     * @return array<string, mixed>
     */
    public function buildDashboard(Request $request): array
    {
        $filters = $this->resolveFilters($request);

        if (! $this->tablesReady()) {
            return $this->emptyDashboard($filters);
        }

        $records = $this->fetchFilteredRecords($filters);
        $itemsByCategory = $this->groupItemsByCategory($records, $filters);

        $replikasiItems = $itemsByCategory['replikasi'];
        $safetyEngineeringItems = $itemsByCategory['safety_engineering'];
        $additionalSafetyItems = $itemsByCategory['additional_safety_engineering'];

        $activeCategory = $filters['category'];
        $activeItems = $itemsByCategory[$activeCategory] ?? [];

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'summary' => $this->buildSummary($replikasiItems, $safetyEngineeringItems, $additionalSafetyItems),
            'overdue_summary' => $this->buildOverdueSummary($replikasiItems, $safetyEngineeringItems, $additionalSafetyItems),
            'active_category' => $activeCategory,
            'active_items' => $activeItems,
            'replikasi_items' => $replikasiItems,
            'safety_engineering_items' => $safetyEngineeringItems,
            'additional_safety_items' => $additionalSafetyItems,
            'brief_analysis' => $this->buildBriefAnalysis($records, $replikasiItems, $safetyEngineeringItems, $additionalSafetyItems),
            'next_todo' => $this->buildNextTodo($records),
            'charts' => $this->buildCharts($replikasiItems, $safetyEngineeringItems, $additionalSafetyItems),
        ];
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('monitoring_safety_engineering_records');
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboard(array $filters): array
    {
        $emptyItems = [];

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'summary' => $this->buildSummary($emptyItems, $emptyItems, $emptyItems),
            'overdue_summary' => $this->buildOverdueSummary($emptyItems, $emptyItems, $emptyItems),
            'active_category' => $filters['category'],
            'active_items' => $emptyItems,
            'replikasi_items' => $emptyItems,
            'safety_engineering_items' => $emptyItems,
            'additional_safety_items' => $emptyItems,
            'brief_analysis' => [
                [
                    'title' => 'Status Penyelesaian',
                    'points' => ['Tabel monitoring_safety_engineering_records belum tersedia. Jalankan migration terlebih dahulu.'],
                ],
            ],
            'next_todo' => ['Upload atau input data rekayasa melalui menu Update Data.'],
            'charts' => $this->buildCharts($emptyItems, $emptyItems, $emptyItems),
        ];
    }

    /**
     * @return Collection<int, MonitoringSafetyEngineeringRecord>
     */
    private function fetchFilteredRecords(array $filters): Collection
    {
        $query = MonitoringSafetyEngineeringRecord::query()
            ->select([
                'id',
                'site',
                'perusahaan',
                'sumber_rekayasa',
                'pelaksana_rekayasa',
                'pengendalian_rekayasa',
                'tanggal_ideation',
                'kajian_teknis_due_date',
                'kajian_teknis_status',
                'pengadaan_due_date',
                'pengadaan_status',
                'uji_coba_due_date',
                'uji_coba_status',
                'standardisasi_due_date',
                'standardisasi_status',
                'replikasi_due_date',
                'replikasi_satuan',
                'replikasi_target_komitmen',
                'replikasi_aktual',
                'brief_analysis_challenge',
                'next_to_do',
                'period_year',
            ])
            ->orderBy('sort_order')
            ->orderBy('row_no')
            ->orderBy('id');

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * @param  Builder<MonitoringSafetyEngineeringRecord>  $query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['bar'] !== '') {
            $query->where('site', $filters['bar']);
        }

        if ($filters['company'] !== '') {
            $query->where('perusahaan', $filters['company']);
        }

        if ($filters['period_year'] > 0) {
            $query->where('period_year', $filters['period_year']);
        }
    }

    /**
     * @param  Collection<int, MonitoringSafetyEngineeringRecord>  $records
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupItemsByCategory(Collection $records, array $filters): array
    {
        $grouped = [
            'replikasi' => [],
            'safety_engineering' => [],
            'additional_safety_engineering' => [],
        ];

        foreach ($records as $record) {
            $category = $this->resolveCategory($record);
            $grouped[$category][] = $this->recordToItem($record, $filters);
        }

        return $grouped;
    }

    private function resolveCategory(MonitoringSafetyEngineeringRecord $record): string
    {
        $sumber = $this->normalizeEnumValue($record->sumber_rekayasa);
        $pelaksana = $this->normalizeEnumValue($record->pelaksana_rekayasa);
        $categories = config('monitoring_safety_engineering.dashboard_categories', []);

        if (in_array($sumber, $categories['additional_safety_engineering']['sumber_rekayasa'] ?? [], true)) {
            return 'additional_safety_engineering';
        }

        if (in_array($sumber, $categories['safety_engineering']['sumber_rekayasa'] ?? [], true)) {
            return 'safety_engineering';
        }

        if (
            in_array($sumber, $categories['replikasi']['sumber_rekayasa'] ?? [], true)
            || in_array($pelaksana, $categories['replikasi']['pelaksana_rekayasa'] ?? [], true)
        ) {
            return 'replikasi';
        }

        return 'replikasi';
    }

    /**
     * @return array<string, mixed>
     */
    private function recordToItem(MonitoringSafetyEngineeringRecord $record, array $filters): array
    {
        $plan = (int) $record->replikasi_target_komitmen;
        $done = (int) $record->replikasi_aktual;

        if ($plan <= 0) {
            $phaseMetrics = $this->phaseMetrics($record);
            $plan = $phaseMetrics['plan'];
            $done = $phaseMetrics['done'];
        }

        $dueDate = $record->replikasi_due_date?->format('Y-m-d')
            ?? $record->standardisasi_due_date?->format('Y-m-d')
            ?? $record->uji_coba_due_date?->format('Y-m-d')
            ?? $record->pengadaan_due_date?->format('Y-m-d')
            ?? $record->kajian_teknis_due_date?->format('Y-m-d')
            ?? '';

        $percentage = $plan > 0 ? (int) round(($done / $plan) * 100) : 0;

        return [
            'id' => $record->id,
            'name' => $record->pengendalian_rekayasa,
            'unit' => $record->replikasi_satuan !== '' ? $record->replikasi_satuan : 'Kegiatan',
            'plan' => $plan,
            'done' => $done,
            'percentage' => $percentage,
            'percentage_color' => $this->percentageColor($percentage),
            'due_date' => $dueDate,
            'due_date_label' => $dueDate !== '' ? date('d M Y', strtotime($dueDate)) : '-',
            'overdue' => $this->calculateRecordOverdue($record, $percentage),
            'due_in_review_week' => $this->recordHasDueDateInReviewWeek($record, $filters),
            'site' => $record->site,
            'perusahaan' => $record->perusahaan,
        ];
    }

    /**
     * @return array{plan: int, done: int}
     */
    private function phaseMetrics(MonitoringSafetyEngineeringRecord $record): array
    {
        $phaseStatuses = [
            $record->kajian_teknis_status,
            $record->pengadaan_status,
            $record->uji_coba_status,
            $record->standardisasi_status,
        ];

        $done = 0;

        foreach ($phaseStatuses as $status) {
            if ($this->normalizeEnumValue($status) === 'done') {
                $done++;
            }
        }

        return [
            'plan' => 4,
            'done' => $done,
        ];
    }

    private function calculateRecordOverdue(MonitoringSafetyEngineeringRecord $record, int $percentage): int
    {
        if ($percentage >= 100) {
            return 0;
        }

        $dueDates = array_filter([
            $record->replikasi_due_date,
            $record->kajian_teknis_due_date,
            $record->pengadaan_due_date,
            $record->uji_coba_due_date,
            $record->standardisasi_due_date,
        ]);

        foreach ($dueDates as $dueDate) {
            if ($dueDate !== null && $dueDate->isPast()) {
                return 1;
            }
        }

        return 0;
    }

    private function normalizeEnumValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        $normalized = strtolower(trim((string) $value));
        $normalized = str_replace(' ', '_', $normalized);

        return match ($normalized) {
            'additional_safety_engineering' => 'additional_engineering',
            default => $normalized,
        };
    }

    /**
     * @param  Collection<int, MonitoringSafetyEngineeringRecord>  $records
     * @param  list<array<string, mixed>>  $replikasi
     * @param  list<array<string, mixed>>  $safety
     * @param  list<array<string, mixed>>  $additional
     * @return list<array{title: string, points: list<string>}>
     */
    private function buildBriefAnalysis(
        Collection $records,
        array $replikasi,
        array $safety,
        array $additional,
    ): array {
        if ($records->isEmpty()) {
            return [
                [
                    'title' => 'Status Penyelesaian',
                    'points' => ['Belum ada data komitmen pada periode YTD yang dipilih.'],
                ],
            ];
        }

        $allItems = array_merge($replikasi, $safety, $additional);
        $completed = count(array_filter($allItems, static fn (array $item): bool => $item['percentage'] >= 100));
        $onTrack = count(array_filter($allItems, static fn (array $item): bool => $item['percentage'] >= 50 && $item['percentage'] < 100));
        $overdue = $this->sumOverdue($allItems);

        $points = [
            sprintf(
                'Dari %d pengendalian, %d item sudah selesai 100%% dan %d item berada pada progress 50–99%%.',
                count($allItems),
                $completed,
                $onTrack,
            ),
            sprintf(
                '%d item belum selesai dengan total %d item overdue berdasarkan due date fase/replikasi.',
                count($allItems) - $completed,
                $overdue,
            ),
        ];

        $dbPoints = $records
            ->pluck('brief_analysis_challenge')
            ->filter(static fn (?string $text): bool => $text !== null && trim($text) !== '')
            ->map(static fn (string $text): string => trim($text))
            ->unique()
            ->take(3)
            ->values()
            ->all();

        if ($dbPoints !== []) {
            $points = array_merge($points, $dbPoints);
        }

        return [
            [
                'title' => 'Status Penyelesaian',
                'points' => $points,
            ],
        ];
    }

    /**
     * @param  Collection<int, MonitoringSafetyEngineeringRecord>  $records
     * @return list<string>
     */
    private function buildNextTodo(Collection $records): array
    {
        $todos = $records
            ->pluck('next_to_do')
            ->filter(static fn (?string $text): bool => $text !== null && trim($text) !== '')
            ->flatMap(static function (string $text): array {
                $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];

                return array_values(array_filter(array_map('trim', $lines)));
            })
            ->unique()
            ->take(8)
            ->values()
            ->all();

        if ($todos !== []) {
            return $todos;
        }

        return [
            'Lengkapi kolom Next To Do pada data rekayasa untuk menampilkan rencana tindak lanjut.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $replikasi
     * @param  list<array<string, mixed>>  $safety
     * @param  list<array<string, mixed>>  $additional
     * @return array<string, mixed>
     */
    private function buildCharts(array $replikasi, array $safety, array $additional): array
    {
        $categories = [
            'replikasi' => ['label' => 'Replikasi', 'color' => '#2563eb', 'items' => $replikasi],
            'safety_engineering' => ['label' => 'Safety Engineering', 'color' => '#15803d', 'items' => $safety],
            'additional_safety_engineering' => ['label' => 'Additional Safety Engineering', 'color' => '#65a30d', 'items' => $additional],
        ];

        return [
            'category_distribution' => [
                'labels' => array_column($categories, 'label'),
                'data' => array_values(array_map(static fn (array $c): int => count($c['items']), $categories)),
                'colors' => array_column($categories, 'color'),
            ],
            'progress_by_category' => [
                'labels' => array_column($categories, 'label'),
                'data' => [
                    $this->calculateOverallProgress($replikasi),
                    $this->calculateOverallProgress($safety),
                    $this->calculateOverallProgress($additional),
                ],
                'colors' => array_column($categories, 'color'),
            ],
            'status_breakdown' => $this->buildStatusBreakdown(array_merge($replikasi, $safety, $additional)),
            'due_timeline' => $this->buildDueTimeline($categories),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function buildStatusBreakdown(array $items): array
    {
        $buckets = [
            'green' => ['label' => 'Selesai (100%)', 'color' => '#15803d', 'count' => 0],
            'amber' => ['label' => 'On Track (50–99%)', 'color' => '#b45309', 'count' => 0],
            'orange' => ['label' => 'Berjalan (1–49%)', 'color' => '#c2410c', 'count' => 0],
            'red' => ['label' => 'Belum Mulai (0%)', 'color' => '#b91c1c', 'count' => 0],
        ];

        foreach ($items as $item) {
            $buckets[$item['percentage_color']]['count']++;
        }

        return [
            'labels' => array_column($buckets, 'label'),
            'data' => array_column($buckets, 'count'),
            'colors' => array_column($buckets, 'color'),
        ];
    }

    /**
     * @param  array<string, array{label: string, color: string, items: list<array<string, mixed>>}>  $categories
     * @return array<string, mixed>
     */
    private function buildDueTimeline(array $categories): array
    {
        $quarterKeys = [];
        $seriesByCategory = [];

        foreach ($categories as $key => $category) {
            $counts = [];

            foreach ($category['items'] as $item) {
                $dueDate = (string) ($item['due_date'] ?? '');
                if ($dueDate === '') {
                    continue;
                }

                $timestamp = strtotime($dueDate);
                if ($timestamp === false) {
                    continue;
                }

                $quarterKey = date('Y', $timestamp) . '-Q' . (int) ceil((int) date('n', $timestamp) / 3);
                $quarterKeys[$quarterKey] = true;
                $counts[$quarterKey] = ($counts[$quarterKey] ?? 0) + 1;
            }

            $seriesByCategory[$key] = $counts;
        }

        $sortedQuarters = array_keys($quarterKeys);
        sort($sortedQuarters);

        $labels = array_map(static fn (string $q): string => str_replace('-', ' ', $q), $sortedQuarters);

        $datasets = [];
        foreach ($categories as $key => $category) {
            $datasets[] = [
                'label' => $category['label'],
                'color' => $category['color'],
                'data' => array_map(static fn (string $q): int => $seriesByCategory[$key][$q] ?? 0, $sortedQuarters),
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        $category = (string) $request->get('category', 'replikasi');
        $allowedCategories = array_keys(config('monitoring_safety_engineering.categories', []));

        if (! in_array($category, $allowedCategories, true)) {
            $category = 'replikasi';
        }

        $dateFrom = (string) $request->get('date_from', now()->startOfYear()->format('Y-m-d'));
        $periodYear = (int) date('Y', strtotime($dateFrom) ?: time());

        return [
            'bar' => (string) $request->get('bar', ''),
            'company' => (string) $request->get('company', ''),
            'review_week' => (string) $request->get('review_week', 'W' . now()->isoWeek()),
            'date_from' => $dateFrom,
            'date_to' => (string) $request->get('date_to', now()->format('Y-m-d')),
            'category' => $category,
            'period_year' => $periodYear > 0 ? $periodYear : (int) now()->year,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        $sites = config('monitoring_safety_engineering.sites', []);
        $companies = config('monitoring_safety_engineering.perusahaan', []);

        if ($this->tablesReady()) {
            $sites = array_values(array_unique(array_merge(
                $sites,
                MonitoringSafetyEngineeringRecord::query()
                    ->select('site')
                    ->distinct()
                    ->orderBy('site')
                    ->pluck('site')
                    ->filter()
                    ->all(),
            )));

            $companies = array_values(array_unique(array_merge(
                $companies,
                MonitoringSafetyEngineeringRecord::query()
                    ->select('perusahaan')
                    ->distinct()
                    ->orderBy('perusahaan')
                    ->pluck('perusahaan')
                    ->filter()
                    ->all(),
            )));
        }

        sort($sites);

        return [
            'bars' => array_merge(['' => 'Semua Site'], array_combine($sites, $sites) ?: []),
            'companies' => array_merge(
                ['' => 'Semua Perusahaan'],
                array_combine($companies, $companies) ?: [],
            ),
            'review_weeks' => collect(range(1, 53))->map(fn (int $w): string => 'W' . $w)->all(),
            'categories' => config('monitoring_safety_engineering.categories', []),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $replikasi
     * @param  list<array<string, mixed>>  $safety
     * @param  list<array<string, mixed>>  $additional
     * @return array<string, mixed>
     */
    private function buildSummary(array $replikasi, array $safety, array $additional): array
    {
        $totalPlan = count($replikasi) + count($safety) + count($additional);

        return [
            'total_komitmen' => $totalPlan,
            'replikasi' => [
                'count' => count($replikasi),
                'progress' => $this->calculateOverallProgress($replikasi),
            ],
            'safety_engineering' => [
                'count' => count($safety),
                'progress' => $this->calculateOverallProgress($safety),
            ],
            'additional_safety_engineering' => [
                'count' => count($additional),
                'progress' => $this->calculateOverallProgress($additional),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $replikasi
     * @param  list<array<string, mixed>>  $safety
     * @param  list<array<string, mixed>>  $additional
     * @return list<array{label: string, overdue: int}>
     */
    private function buildOverdueSummary(array $replikasi, array $safety, array $additional): array
    {
        return [
            ['label' => 'Replikasi', 'overdue' => $this->sumOverdue($replikasi)],
            ['label' => 'Safety Engineering', 'overdue' => $this->sumOverdue($safety)],
            ['label' => 'Additional Safety Engineering', 'overdue' => $this->sumOverdue($additional)],
        ];
    }
}
