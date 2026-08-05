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
            'summary' => $this->buildSummary($records, $replikasiItems, $safetyEngineeringItems, $additionalSafetyItems),
            'overdue_summary' => $this->buildOverdueSummary($replikasiItems, $safetyEngineeringItems, $additionalSafetyItems),
            'active_category' => $activeCategory,
            'active_items' => $activeItems,
            'replikasi_items' => $replikasiItems,
            'safety_engineering_items' => $safetyEngineeringItems,
            'additional_safety_items' => $additionalSafetyItems,
            'brief_analysis' => $this->buildBriefAnalysis($records, $replikasiItems, $safetyEngineeringItems, $additionalSafetyItems),
            'next_todo' => $this->buildNextTodo($records),
            'charts' => $this->buildCharts($replikasiItems, $safetyEngineeringItems, $additionalSafetyItems),
            'risk_reduction_matrix' => $this->buildRiskReductionMatrix($records),
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
            'summary' => $this->buildSummary(collect(), $emptyItems, $emptyItems, $emptyItems),
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
            'risk_reduction_matrix' => $this->buildRiskReductionMatrix(collect()),
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
                'row_no',
                'site',
                'perusahaan',
                'aktivitas',
                'sumber_rekayasa',
                'pelaksana_rekayasa',
                'pengendalian_rekayasa',
                'tanggal_ideation',
                'kajian_teknis_due_date',
                'kajian_teknis_status',
                'kajian_teknis_status_compliance',
                'pengadaan_due_date',
                'pengadaan_status',
                'pengadaan_status_compliance',
                'uji_coba_due_date',
                'uji_coba_status',
                'uji_coba_status_compliance',
                'standardisasi_due_date',
                'standardisasi_status',
                'standardisasi_status_compliance',
                'replikasi_due_date',
                'replikasi_total_populasi',
                'replikasi_satuan',
                'replikasi_target_komitmen',
                'replikasi_diusulkan_pjo',
                'replikasi_ditinjau',
                'replikasi_disetujui',
                'replikasi_aktual',
                'deteksi_deviasi',
                'intervensi_deviasi',
                'prediksi_penurunan_tangga_risiko',
                'terkait_hazard',
                'terkait_insiden',
                'brief_analysis_challenge',
                'next_to_do',
                'potensi_peningkatan_efektivitas',
                'pengendalian_peningkatan_efektivitas',
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

            if ($category === null || ! array_key_exists($category, $grouped)) {
                continue;
            }

            $item = $this->recordToItem($record, $filters);
            $item['detail'] = $this->buildRecordDetail($record, $item);

            $grouped[$category][] = $item;
        }

        return $grouped;
    }

    private function resolveCategory(MonitoringSafetyEngineeringRecord $record): ?string
    {
        $sumber = $this->normalizeEnumValue($record->sumber_rekayasa);
        $categories = config('monitoring_safety_engineering.dashboard_categories', []);

        if (in_array($sumber, $categories['additional_safety_engineering']['sumber_rekayasa'] ?? [], true)) {
            return 'additional_safety_engineering';
        }

        if (in_array($sumber, $categories['safety_engineering']['sumber_rekayasa'] ?? [], true)) {
            return 'safety_engineering';
        }

        if (in_array($sumber, $categories['replikasi']['sumber_rekayasa'] ?? [], true)) {
            return 'replikasi';
        }

        return null;
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
        $replikasiStatus = $this->resolveReplikasiProgressStatus($record);

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
            'replikasi_status' => $replikasiStatus,
            'replikasi_target_komitmen' => (int) $record->replikasi_target_komitmen,
            'replikasi_aktual' => (int) $record->replikasi_aktual,
            'replikasi_due_date' => $record->replikasi_due_date?->format('Y-m-d'),
            'due_in_review_week' => $this->recordHasDueDateInReviewWeek($record, $filters),
            'site' => $record->site,
            'perusahaan' => $record->perusahaan,
        ];
    }

    /**
     * Status progress khusus field Replikasi:
     * - selesai: aktual >= target (target harus > 0)
     * - overdue: aktual < target dan hari ini > replikasi_due_date
     * - onprogress: aktual < target dan hari ini belum melewati replikasi_due_date (atau tanpa due date)
     */
    private function resolveReplikasiProgressStatus(MonitoringSafetyEngineeringRecord $record): string
    {
        $target = (int) $record->replikasi_target_komitmen;
        $aktual = (int) $record->replikasi_aktual;

        if ($target > 0 && $aktual >= $target) {
            return 'selesai';
        }

        // Belum selesai (atau belum ada target komitmen)
        $dueDate = $record->replikasi_due_date;

        if (
            $target > 0
            && $aktual < $target
            && $dueDate !== null
            && now()->startOfDay()->gt($dueDate->copy()->startOfDay())
        ) {
            return 'overdue';
        }

        return 'onprogress';
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function buildRecordDetail(MonitoringSafetyEngineeringRecord $record, array $item): array
    {
        $sumberLabels = config('monitoring_safety_engineering.sumber_rekayasa', []);
        $pelaksanaLabels = config('monitoring_safety_engineering.pelaksana_rekayasa', []);
        $phaseStatusLabels = config('monitoring_safety_engineering.phase_status', []);
        $complianceLabels = config('monitoring_safety_engineering.status_compliance', []);
        $intervensiLabels = config('monitoring_safety_engineering.intervensi_deviasi', []);

        $sumber = $this->normalizeEnumValue($record->sumber_rekayasa);
        $pelaksana = $this->normalizeEnumValue($record->pelaksana_rekayasa);
        $intervensi = $this->normalizeEnumValue($record->intervensi_deviasi);

        $phases = [];

        foreach (config('monitoring_safety_engineering.trace_phases', []) as $phase) {
            $statusField = (string) ($phase['status'] ?? '');
            $dueField = (string) ($phase['due'] ?? '');
            $complianceField = (string) ($phase['compliance'] ?? '');
            $statusValue = $this->normalizeEnumValue($record->{$statusField} ?? null);
            $complianceValue = $this->normalizeEnumValue($record->{$complianceField} ?? null);
            $dueDate = $record->{$dueField} ?? null;

            $phases[] = [
                'label' => (string) ($phase['label'] ?? $statusField),
                'status' => (string) ($phaseStatusLabels[$statusValue] ?? ($statusValue !== '' ? ucfirst(str_replace('_', ' ', $statusValue)) : '-')),
                'due_date' => $dueDate !== null ? $dueDate->format('d M Y') : '-',
                'compliance' => (string) ($complianceLabels[$complianceValue] ?? ($complianceValue !== '' ? ucfirst(str_replace('_', ' ', $complianceValue)) : '-')),
            ];
        }

        $replikasi = null;

        if ((int) $record->replikasi_target_komitmen > 0) {
            $replikasi = [
                'total_populasi' => (int) $record->replikasi_total_populasi,
                'target_komitmen' => (int) $record->replikasi_target_komitmen,
                'diusulkan_pjo' => $record->replikasi_diusulkan_pjo,
                'ditinjau' => $record->replikasi_ditinjau,
                'disetujui' => $record->replikasi_disetujui,
                'aktual' => (int) $record->replikasi_aktual,
                'satuan' => $record->replikasi_satuan !== '' ? $record->replikasi_satuan : 'Kegiatan',
                'due_date' => $record->replikasi_due_date?->format('d M Y') ?? '-',
            ];
        }

        return [
            'id' => $record->id,
            'row_no' => (int) $record->row_no,
            'pengendalian_rekayasa' => $record->pengendalian_rekayasa,
            'site' => $record->site,
            'perusahaan' => $record->perusahaan,
            'aktivitas' => $record->aktivitas !== '' && $record->aktivitas !== '-' ? $record->aktivitas : '-',
            'sumber_rekayasa' => (string) ($sumberLabels[$sumber] ?? $sumber),
            'pelaksana_rekayasa' => (string) ($pelaksanaLabels[$pelaksana] ?? $pelaksana),
            'tanggal_ideation' => $record->tanggal_ideation?->format('d M Y') ?? '-',
            'period_year' => (int) $record->period_year,
            'progress' => [
                'plan' => (int) $item['plan'],
                'done' => (int) $item['done'],
                'percentage' => (int) $item['percentage'],
                'unit' => (string) $item['unit'],
            ],
            'phases' => $phases,
            'replikasi' => $replikasi,
            'terkait_hazard' => (bool) $record->terkait_hazard,
            'terkait_insiden' => (bool) $record->terkait_insiden,
            'deteksi_deviasi' => $record->deteksi_deviasi,
            'intervensi_deviasi' => (string) ($intervensiLabels[$intervensi] ?? ($record->intervensi_deviasi ?? '-')),
            'prediksi_penurunan_tangga_risiko' => $record->prediksi_penurunan_tangga_risiko,
            'potensi_peningkatan_efektivitas' => (bool) $record->potensi_peningkatan_efektivitas,
            'pengendalian_peningkatan_efektivitas' => $record->pengendalian_peningkatan_efektivitas ?? '-',
            'brief_analysis_challenge' => trim((string) ($record->brief_analysis_challenge ?? '')),
            'next_to_do' => trim((string) ($record->next_to_do ?? '')),
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
     * @param  Collection<int, MonitoringSafetyEngineeringRecord>  $records
     * @param  list<array<string, mixed>>  $replikasi
     * @param  list<array<string, mixed>>  $safety
     * @param  list<array<string, mixed>>  $additional
     * @return array<string, mixed>
     */
    private function buildSummary(Collection $records, array $replikasi, array $safety, array $additional): array
    {
        $allowedSumber = $this->totalPengendalianSumberValues();

        $totalPengendalian = $records
            ->filter(function (MonitoringSafetyEngineeringRecord $record) use ($allowedSumber): bool {
                return in_array($this->normalizeEnumValue($record->sumber_rekayasa), $allowedSumber, true);
            })
            ->count();

        return [
            'total_komitmen' => $totalPengendalian,
            'replikasi' => $this->buildReplikasiCategoryStat($replikasi),
            'safety_engineering' => $this->buildCategoryStat($safety),
            'additional_safety_engineering' => $this->buildCategoryStat($additional),
        ];
    }

    /**
     * Sumber rekayasa untuk KPI Total Pengendalian:
     * Safety Engineering, Additional Engineering, Replikasi 2026, Arahan Manajemen (PMR).
     *
     * @return list<string>
     */
    private function totalPengendalianSumberValues(): array
    {
        $configured = config('monitoring_safety_engineering.total_pengendalian_sumber_rekayasa', []);

        if (! is_array($configured) || $configured === []) {
            $arahan = config(
                'monitoring_safety_engineering.outside_commitment_categories.arahan_manajemen.sumber_rekayasa',
                [],
            );

            $configured = array_merge(
                [
                    'safety_engineering',
                    'additional_engineering',
                    'replikasi_2026',
                ],
                is_array($arahan) ? $arahan : [],
            );
        }

        return array_values(array_unique(array_map(
            static fn (mixed $value): string => (string) $value,
            $configured,
        )));
    }

    /**
     * Ringkasan card Total Replikasi berdasarkan status replikasi_*.
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
    private function buildReplikasiCategoryStat(array $items): array
    {
        $onprogress = 0;
        $overdue = 0;
        $selesai = 0;

        foreach ($items as $item) {
            match ((string) ($item['replikasi_status'] ?? '')) {
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
            'meta_mode' => 'replikasi_status',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{count: int, overdue: int, done: int, plan: int, completed: int, progress: int, meta_mode: string}
     */
    private function buildCategoryStat(array $items): array
    {
        return [
            'count' => count($items),
            'overdue' => $this->sumOverdue($items),
            'done' => (int) array_sum(array_column($items, 'done')),
            'plan' => (int) array_sum(array_column($items, 'plan')),
            'completed' => count(array_filter(
                $items,
                static fn (array $item): bool => (int) ($item['percentage'] ?? 0) >= 100,
            )),
            'progress' => $this->calculateOverallProgress($items),
            'meta_mode' => 'default',
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

    /**
     * Matriks: baris = level kontrol dari Deteksi/Intervensi Deviasi,
     * kolom = prediksi penurunan tangga risiko (1–3).
     *
     * @param  Collection<int, MonitoringSafetyEngineeringRecord>  $records
     * @return array{
     *     columns: list<array{key: int, label: string}>,
     *     rows: list<array{key: string, label: string, cells: array<int, array{count: int, items: list<array<string, mixed>>}>}>,
     *     total: int,
     *     without_prediksi: int
     * }
     */
    private function buildRiskReductionMatrix(Collection $records): array
    {
        $rowLabels = config('monitoring_safety_engineering.risk_reduction_matrix.rows', []);
        $columnLabels = config('monitoring_safety_engineering.risk_reduction_matrix.columns', [
            3 => 'Turun 3 Tangga',
            2 => 'Turun 2 Tangga',
            1 => 'Turun 1 Tangga',
        ]);

        $columns = [];
        foreach ($columnLabels as $key => $label) {
            $columns[] = [
                'key' => (int) $key,
                'label' => (string) $label,
            ];
        }

        $cells = [];
        foreach (array_keys($rowLabels) as $rowKey) {
            foreach (array_keys($columnLabels) as $columnKey) {
                $cells[(string) $rowKey][(int) $columnKey] = [
                    'count' => 0,
                    'items' => [],
                ];
            }
        }

        $total = 0;
        $withoutPrediksi = 0;

        foreach ($records as $record) {
            $rowKey = $this->resolveRiskControlRowKey(
                $record->deteksi_deviasi !== null ? (string) $record->deteksi_deviasi : null,
                $record->intervensi_deviasi !== null ? (string) $record->intervensi_deviasi : null,
            );
            if (! isset($cells[$rowKey])) {
                $rowKey = 'menahan_mengurangi';
            }

            $prediksi = $record->prediksi_penurunan_tangga_risiko;
            $isDerived = false;

            if ($prediksi === null || ! isset($columnLabels[$prediksi])) {
                $derived = $this->defaultPrediksiForRiskRow($rowKey);
                if ($derived === null) {
                    $withoutPrediksi++;
                    continue;
                }
                $prediksi = $derived;
                $isDerived = true;
                $withoutPrediksi++;
            }

            $item = [
                'id' => $record->id,
                'name' => $record->pengendalian_rekayasa,
                'site' => $record->site,
                'perusahaan' => $record->perusahaan,
                'deteksi_deviasi' => $record->deteksi_deviasi !== null && (string) $record->deteksi_deviasi !== ''
                    ? (string) $record->deteksi_deviasi
                    : '—',
                'intervensi_deviasi' => (string) ($record->intervensi_deviasi ?? '-'),
                'prediksi' => (int) $prediksi,
                'prediksi_label' => (string) ($columnLabels[$prediksi] ?? 'Turun '.$prediksi.' Tangga'),
                'is_derived' => $isDerived,
            ];

            $cells[$rowKey][$prediksi]['count']++;
            $cells[$rowKey][$prediksi]['items'][] = $item;
            $total++;
        }

        $rows = [];
        foreach ($rowLabels as $rowKey => $rowLabel) {
            $rowCells = [];
            foreach (array_keys($columnLabels) as $columnKey) {
                $rowCells[(int) $columnKey] = $cells[(string) $rowKey][(int) $columnKey]
                    ?? ['count' => 0, 'items' => []];
            }

            $rows[] = [
                'key' => (string) $rowKey,
                'label' => (string) $rowLabel,
                'cells' => $rowCells,
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'total' => $total,
            'without_prediksi' => $withoutPrediksi,
        ];
    }

    /**
     * Klasifikasi baris matriks dari DETEKSI + INTERVENSI (kolom terpisah).
     * Mendukung juga format legacy "Deteksi -> Intervensi" di kolom intervensi saja.
     */
    private function resolveRiskControlRowKey(?string $deteksi, ?string $intervensi): string
    {
        $deteksiLevel = $this->normalizeRiskControlLevel((string) ($deteksi ?? ''));
        $intervensiLevel = $this->normalizeRiskControlLevel((string) ($intervensi ?? ''));

        // Legacy: "Alat -> Manusia" tersimpan hanya di intervensi_deviasi
        if (
            ($deteksiLevel === '' || $deteksiLevel === '0')
            && preg_match('/^(.+?)\s*->\s*(.+)$/u', (string) ($intervensi ?? ''), $matches) === 1
        ) {
            $deteksiLevel = $this->normalizeRiskControlLevel($matches[1]);
            $intervensiLevel = $this->normalizeRiskControlLevel($matches[2]);
        }

        if ($deteksiLevel === 'eliminasi' || $intervensiLevel === 'eliminasi') {
            return 'eliminasi';
        }

        if ($deteksiLevel === 'alat' && $intervensiLevel === 'alat') {
            return 'full_automasi';
        }

        if (
            ($deteksiLevel === 'alat' && $intervensiLevel === 'manusia')
            || ($deteksiLevel === 'manusia' && $intervensiLevel === 'alat')
        ) {
            return 'hybrid';
        }

        if ($deteksiLevel === 'manusia' && $intervensiLevel === 'manusia') {
            return 'manusia';
        }

        if (
            $deteksiLevel === 'tidak_mendeteksi'
            || $intervensiLevel === 'menahan_mengurangi'
            || $intervensiLevel === 'menahan'
        ) {
            return 'menahan_mengurangi';
        }

        if ($deteksiLevel === '' && $intervensiLevel === '') {
            return 'menahan_mengurangi';
        }

        return match ($intervensiLevel !== '' ? $intervensiLevel : $deteksiLevel) {
            'alat' => 'full_automasi',
            'manusia' => 'manusia',
            default => 'menahan_mengurangi',
        };
    }

    private function normalizeRiskControlLevel(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['–', '—', '→', '⇒'], '->', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = str_replace([' ', '-', '/'], '_', $normalized);

        return match ($normalized) {
            'eliminasi', 'eliminate', 'elimination' => 'eliminasi',
            'alat', 'machine', 'equipment', 'tool' => 'alat',
            'manusia', 'human', 'people' => 'manusia',
            'tidak_mendeteksi', 'tidak_ada_deteksi', 'tidakmendeteksi' => 'tidak_mendeteksi',
            'menahan_mengurangi', 'menahan_mengurangi_dampak', 'menahan_mengurangi_dampak_' => 'menahan_mengurangi',
            'menahan', 'mengurangi' => 'menahan_mengurangi',
            default => $normalized,
        };
    }

    /**
     * Fallback prediksi tangga bila kolom prediksi belum diisi,
     * berdasarkan hierarki Deteksi/Intervensi.
     */
    private function defaultPrediksiForRiskRow(string $rowKey): ?int
    {
        return match ($rowKey) {
            'eliminasi', 'full_automasi' => 3,
            'hybrid' => 2,
            'manusia', 'menahan_mengurangi' => 1,
            default => null,
        };
    }
}
