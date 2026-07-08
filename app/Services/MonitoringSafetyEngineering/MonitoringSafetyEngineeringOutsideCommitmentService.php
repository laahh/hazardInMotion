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

class MonitoringSafetyEngineeringOutsideCommitmentService
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
        $categories = $this->buildCategories($records, $filters);

        $activeCategory = $filters['category'];
        $activeItems = $categories[$activeCategory]['items'] ?? [];

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'summary' => $this->buildSummary($categories),
            'overdue_summary' => $this->buildOverdueSummary($categories),
            'active_category' => $activeCategory,
            'active_items' => $activeItems,
            'brief_analysis' => $this->buildBriefAnalysis($records, $categories),
            'next_todo' => $this->buildNextTodo($records),
            'charts' => $this->buildCharts($categories),
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
        $categories = $this->emptyCategories();

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'summary' => $this->buildSummary($categories),
            'overdue_summary' => $this->buildOverdueSummary($categories),
            'active_category' => $filters['category'],
            'active_items' => [],
            'brief_analysis' => [
                [
                    'title' => 'Status Penyelesaian',
                    'points' => ['Tabel monitoring_safety_engineering_records belum tersedia. Jalankan migration terlebih dahulu.'],
                ],
            ],
            'next_todo' => ['Upload atau input data rekayasa melalui menu Update Data.'],
            'charts' => $this->buildCharts($categories),
        ];
    }

    /**
     * @return array<string, array{label: string, color: string, items: list<array<string, mixed>>}>
     */
    private function emptyCategories(): array
    {
        $categories = [];

        foreach (config('monitoring_safety_engineering.outside_commitment_categories', []) as $key => $meta) {
            $categories[$key] = [
                'label' => (string) ($meta['label'] ?? $key),
                'color' => (string) ($meta['color'] ?? '#7366FF'),
                'items' => [],
            ];
        }

        return $categories;
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
            ->whereIn('sumber_rekayasa', $this->outsideCommitmentSumberValues())
            ->orderBy('sort_order')
            ->orderBy('row_no')
            ->orderBy('id');

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * @return list<string>
     */
    private function outsideCommitmentSumberValues(): array
    {
        $values = [];
        $labels = config('monitoring_safety_engineering.sumber_rekayasa', []);

        foreach (config('monitoring_safety_engineering.outside_commitment_categories', []) as $category) {
            foreach ($category['sumber_rekayasa'] ?? [] as $value) {
                $values[] = (string) $value;

                if (isset($labels[$value])) {
                    $values[] = (string) $labels[$value];
                }
            }
        }

        return array_values(array_unique($values));
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
     * @return array<string, array{label: string, color: string, items: list<array<string, mixed>>}>
     */
    private function buildCategories(Collection $records, array $filters): array
    {
        $categories = $this->emptyCategories();

        foreach ($records as $record) {
            $category = $this->resolveCategory($record);
            $categories[$category]['items'][] = $this->recordToItem($record, $filters);
        }

        return $categories;
    }

    private function resolveCategory(MonitoringSafetyEngineeringRecord $record): string
    {
        $sumber = $this->normalizeEnumValue($record->sumber_rekayasa);

        foreach (config('monitoring_safety_engineering.outside_commitment_categories', []) as $key => $meta) {
            if (in_array($sumber, $meta['sumber_rekayasa'] ?? [], true)) {
                return (string) $key;
            }
        }

        return 'arahan_manajemen';
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
            'sumber_rekayasa' => $this->normalizeEnumValue($record->sumber_rekayasa),
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
     * @param  array<string, array{label: string, color: string, items: list<array<string, mixed>>}>  $categories
     * @return list<array{title: string, points: list<string>}>
     */
    private function buildBriefAnalysis(Collection $records, array $categories): array
    {
        if ($records->isEmpty()) {
            return [
                [
                    'title' => 'Status Penyelesaian',
                    'points' => ['Belum ada data luar komitmen pada periode YTD yang dipilih.'],
                ],
            ];
        }

        $points = [];

        foreach ($categories as $category) {
            $items = $category['items'];
            if ($items === []) {
                continue;
            }

            $completed = count(array_filter($items, static fn (array $item): bool => $item['percentage'] >= 100));
            $overdue = $this->sumOverdue($items);

            $points[] = sprintf(
                '%s: %d dari %d item selesai 100%%, %d item overdue.',
                $category['label'],
                $completed,
                count($items),
                $overdue,
            );
        }

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
                'points' => $points !== [] ? $points : ['Data tersedia, namun belum ada ringkasan analisis.'],
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
            'Lengkapi kolom Next To Do pada data luar komitmen untuk menampilkan rencana tindak lanjut.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        $category = (string) $request->get('category', 'arahan_manajemen');
        $allowedCategories = array_keys(config('monitoring_safety_engineering.outside_commitment_categories', []));

        if (! in_array($category, $allowedCategories, true)) {
            $category = 'arahan_manajemen';
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
        $sumberValues = $this->outsideCommitmentSumberValues();

        if ($this->tablesReady()) {
            $baseQuery = MonitoringSafetyEngineeringRecord::query()
                ->whereIn('sumber_rekayasa', $sumberValues);

            $sites = array_values(array_unique(array_merge(
                $sites,
                (clone $baseQuery)
                    ->select('site')
                    ->distinct()
                    ->orderBy('site')
                    ->pluck('site')
                    ->filter()
                    ->all(),
            )));

            $companies = array_values(array_unique(array_merge(
                $companies,
                (clone $baseQuery)
                    ->select('perusahaan')
                    ->distinct()
                    ->orderBy('perusahaan')
                    ->pluck('perusahaan')
                    ->filter()
                    ->all(),
            )));
        }

        sort($sites);

        $categories = [];
        foreach (config('monitoring_safety_engineering.outside_commitment_categories', []) as $key => $meta) {
            $categories[$key] = (string) ($meta['label'] ?? $key);
        }

        return [
            'bars' => array_merge(['' => 'Semua Site'], array_combine($sites, $sites) ?: []),
            'companies' => array_merge(
                ['' => 'Semua Perusahaan'],
                array_combine($companies, $companies) ?: [],
            ),
            'review_weeks' => collect(range(1, 53))->map(fn (int $w): string => 'W' . $w)->all(),
            'categories' => $categories,
        ];
    }

    /**
     * @param  array<string, array{label: string, color: string, items: list<array<string, mixed>>}>  $categories
     * @return array<string, mixed>
     */
    private function buildSummary(array $categories): array
    {
        $total = array_sum(array_map(static fn (array $category): int => count($category['items']), $categories));

        $summary = ['total_luar_komitmen' => $total];

        foreach ($categories as $key => $category) {
            $summary[$key] = [
                'label' => $category['label'],
                'count' => count($category['items']),
                'progress' => $this->calculateOverallProgress($category['items']),
            ];
        }

        return $summary;
    }

    /**
     * @param  array<string, array{label: string, color: string, items: list<array<string, mixed>>}>  $categories
     * @return list<array{label: string, overdue: int}>
     */
    private function buildOverdueSummary(array $categories): array
    {
        return array_values(array_map(
            fn (array $category): array => [
                'label' => $category['label'],
                'overdue' => $this->sumOverdue($category['items']),
            ],
            $categories,
        ));
    }

    /**
     * @param  array<string, array{label: string, color: string, items: list<array<string, mixed>>}>  $categories
     * @return array<string, mixed>
     */
    private function buildCharts(array $categories): array
    {
        $allItems = array_merge(...array_values(array_map(static fn (array $category): array => $category['items'], $categories)));

        return [
            'category_distribution' => [
                'labels' => array_column($categories, 'label'),
                'data' => array_values(array_map(static fn (array $category): int => count($category['items']), $categories)),
                'colors' => array_column($categories, 'color'),
            ],
            'progress_by_category' => [
                'labels' => array_column($categories, 'label'),
                'data' => array_values(array_map(fn (array $category): int => $this->calculateOverallProgress($category['items']), $categories)),
                'colors' => array_column($categories, 'color'),
            ],
            'status_breakdown' => $this->buildStatusBreakdown($allItems),
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
}
