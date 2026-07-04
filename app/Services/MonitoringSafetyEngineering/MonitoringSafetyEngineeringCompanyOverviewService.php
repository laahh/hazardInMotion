<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Models\MonitoringSafetyEngineeringRecord;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MonitoringSafetyEngineeringCompanyOverviewService
{
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
        $items = $records->map(fn (MonitoringSafetyEngineeringRecord $record): array => $this->recordToItem($record))->all();
        $mitraRows = $this->buildMitraRows($items);
        $totals = $this->buildTotals($items, $mitraRows);

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'totals' => $totals,
            'mitra_rows' => $mitraRows,
            'sumber_program_rows' => $this->buildSumberProgramRows($items),
            'top_overdue' => $this->topOverdueItems($items),
            'trend' => $this->buildTrend($records, $totals['progress'], $filters['period_year']),
            'brief_analysis' => $this->buildBriefAnalysis($records, $totals),
            'next_todo' => $this->buildNextTodo($records),
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
        $emptyTotals = [
            'mitra' => 0,
            'item' => 0,
            'plan' => 0,
            'done' => 0,
            'overdue' => 0,
            'item_closed' => 0,
            'gap' => 0,
            'progress' => 0,
        ];

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'totals' => $emptyTotals,
            'mitra_rows' => [],
            'sumber_program_rows' => $this->emptySumberProgramRows(),
            'top_overdue' => [],
            'trend' => $this->buildTrend(collect(), 0, $filters['period_year']),
            'brief_analysis' => [
                [
                    'title' => 'Ringkasan Perusahaan',
                    'points' => ['Tabel monitoring_safety_engineering_records belum tersedia. Jalankan migration terlebih dahulu.'],
                ],
            ],
            'next_todo' => ['Upload atau input data rekayasa melalui menu Update Data.'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function emptySumberProgramRows(): array
    {
        return array_map(
            static fn (string $label): array => [
                'label' => $label,
                'item' => 0,
                'plan' => 0,
                'done' => 0,
                'progress' => 0,
                'overdue' => 0,
            ],
            config('monitoring_safety_engineering.company_overview_sumber_program', []),
        );
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
                'replikasi_target_komitmen',
                'replikasi_aktual',
                'brief_analysis_challenge',
                'next_to_do',
                'period_year',
                'created_at',
            ])
            ->orderBy('perusahaan')
            ->orderBy('site')
            ->orderBy('sort_order')
            ->orderBy('row_no')
            ->orderBy('id');

        if ($filters['company'] !== '') {
            $query->where('perusahaan', $filters['company']);
        }

        if ($filters['period_year'] > 0) {
            $query->where('period_year', $filters['period_year']);
        }

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function recordToItem(MonitoringSafetyEngineeringRecord $record): array
    {
        $plan = (int) $record->replikasi_target_komitmen;
        $done = (int) $record->replikasi_aktual;

        if ($plan <= 0) {
            $phaseMetrics = $this->phaseMetrics($record);
            $plan = $phaseMetrics['plan'];
            $done = $phaseMetrics['done'];
        }

        $progress = $plan > 0 ? (int) round(($done / $plan) * 100) : 0;
        $gap = max(0, $plan - $done);
        $isOverdue = $this->calculateRecordOverdue($record, $progress) > 0;

        return [
            'id' => $record->id,
            'name' => $record->pengendalian_rekayasa,
            'plan' => $plan,
            'done' => $done,
            'gap' => $gap,
            'progress' => $progress,
            'overdue' => $isOverdue ? 1 : 0,
            'overdue_gap' => $isOverdue ? $gap : 0,
            'site' => $record->site,
            'perusahaan' => $record->perusahaan,
            'mitra_key' => trim($record->perusahaan . ' ' . $record->site),
            'sumber_program' => $this->resolveSumberProgramLabel($record),
            'tanggal_ideation' => $record->tanggal_ideation,
            'created_at' => $record->created_at,
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

    private function calculateRecordOverdue(MonitoringSafetyEngineeringRecord $record, int $progress): int
    {
        if ($progress >= 100) {
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

    private function resolveSumberProgramLabel(MonitoringSafetyEngineeringRecord $record): string
    {
        $sumber = $this->normalizeEnumValue($record->sumber_rekayasa);

        return match (true) {
            $sumber === 'rekom_gr' => 'Pelanggaran Golden Rules',
            $sumber === 'rekom_insiden' => 'Rekomendasi Insiden',
            in_array($sumber, ['pmr_2023', 'pmr_2024', 'pmr_2025'], true) => 'Arahan Manajemen',
            $sumber === 'safety_engineering' => 'Safety Engineering',
            $sumber === 'additional_engineering' => 'Additional Safety Engineering',
            in_array($sumber, ['replikasi_2024', 'replikasi_2025', 'replikasi_2026'], true) => 'Komitmen',
            default => 'Di Luar Komitmen',
        };
    }

    private function normalizeEnumValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        $normalized = strtolower(trim((string) $value));

        return str_replace(' ', '_', $normalized);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function buildMitraRows(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $key = (string) $item['mitra_key'];
            $grouped[$key] ??= [
                'name' => $key,
                'item' => 0,
                'plan' => 0,
                'done' => 0,
                'overdue' => 0,
            ];

            $grouped[$key]['item']++;
            $grouped[$key]['plan'] += (int) $item['plan'];
            $grouped[$key]['done'] += (int) $item['done'];
            $grouped[$key]['overdue'] += (int) $item['overdue'];
        }

        $rows = array_map(function (array $row): array {
            $progress = $row['plan'] > 0 ? (int) round(($row['done'] / $row['plan']) * 100) : 0;

            return [
                'name' => $row['name'],
                'item' => $row['item'],
                'plan' => $row['plan'],
                'done' => $row['done'],
                'gap' => $row['plan'] - $row['done'],
                'progress' => $progress,
                'overdue' => $row['overdue'],
                'status' => $this->statusFor($progress),
            ];
        }, array_values($grouped));

        usort($rows, static fn (array $a, array $b): int => $b['plan'] <=> $a['plan']);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $mitraRows
     * @return array<string, int>
     */
    private function buildTotals(array $items, array $mitraRows): array
    {
        $plan = (int) array_sum(array_column($items, 'plan'));
        $done = (int) array_sum(array_column($items, 'done'));
        $itemClosed = count(array_filter($items, static fn (array $item): bool => $item['progress'] >= 100));

        return [
            'mitra' => count($mitraRows),
            'item' => count($items),
            'plan' => $plan,
            'done' => $done,
            'overdue' => (int) array_sum(array_column($items, 'overdue')),
            'item_closed' => $itemClosed,
            'gap' => max(0, $plan - $done),
            'progress' => $plan > 0 ? (int) round(($done / $plan) * 100) : 0,
        ];
    }

    private function statusFor(int $progress): array
    {
        return match (true) {
            $progress >= 100 => ['label' => 'Closed', 'class' => 'mse-status--closed'],
            $progress >= 70 => ['label' => 'On Track', 'class' => 'mse-status--ontrack'],
            $progress >= 40 => ['label' => 'Need Acceleration', 'class' => 'mse-status--acceleration'],
            default => ['label' => 'Critical', 'class' => 'mse-status--critical'],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function buildSumberProgramRows(array $items): array
    {
        $labels = config('monitoring_safety_engineering.company_overview_sumber_program', []);
        $buckets = [];

        foreach ($labels as $label) {
            $buckets[$label] = [
                'label' => $label,
                'item' => 0,
                'plan' => 0,
                'done' => 0,
                'overdue' => 0,
            ];
        }

        foreach ($items as $item) {
            $label = (string) $item['sumber_program'];

            if (! isset($buckets[$label])) {
                $buckets[$label] = [
                    'label' => $label,
                    'item' => 0,
                    'plan' => 0,
                    'done' => 0,
                    'overdue' => 0,
                ];
            }

            $buckets[$label]['item']++;
            $buckets[$label]['plan'] += (int) $item['plan'];
            $buckets[$label]['done'] += (int) $item['done'];
            $buckets[$label]['overdue'] += (int) $item['overdue'];
        }

        return array_map(static function (array $row): array {
            $row['progress'] = $row['plan'] > 0 ? (int) round(($row['done'] / $row['plan']) * 100) : 0;

            return $row;
        }, array_values($buckets));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function topOverdueItems(array $items): array
    {
        $candidates = array_filter(
            $items,
            static fn (array $item): bool => ($item['overdue_gap'] ?? 0) > 0 || ($item['overdue'] ?? 0) > 0,
        );

        usort($candidates, static function (array $a, array $b): int {
            $gapCompare = ($b['overdue_gap'] ?? 0) <=> ($a['overdue_gap'] ?? 0);

            if ($gapCompare !== 0) {
                return $gapCompare;
            }

            return ($b['gap'] ?? 0) <=> ($a['gap'] ?? 0);
        });

        return array_map(static function (array $item): array {
            $gap = (int) ($item['overdue_gap'] ?? $item['gap'] ?? 0);

            return [
                'name' => $item['name'],
                'plan' => $item['plan'],
                'done' => $item['done'],
                'progress' => $item['progress'],
                'overdue' => $gap > 0 ? $gap : max(0, (int) $item['plan'] - (int) $item['done']),
            ];
        }, array_slice(array_values($candidates), 0, 5));
    }

    /**
     * @param  Collection<int, MonitoringSafetyEngineeringRecord>  $records
     * @return array<string, mixed>
     */
    private function buildTrend(Collection $records, int $currentProgress, int $periodYear): array
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $target = [];
        $realisasi = [];
        $now = now();
        $currentMonth = (int) $now->month;
        $currentYear = (int) $now->year;

        foreach ($months as $index => $label) {
            $target[] = (int) round(5 + (100 - 5) * $index / max(1, count($months) - 1));

            $month = $index + 1;
            $isFuture = $periodYear > $currentYear || ($periodYear === $currentYear && $month > $currentMonth);

            if ($isFuture || $records->isEmpty()) {
                $realisasi[] = null;

                continue;
            }

            $endOfMonth = Carbon::create($periodYear, $month, 1)->endOfMonth();
            $subset = $records->filter(function (MonitoringSafetyEngineeringRecord $record) use ($endOfMonth): bool {
                $referenceDate = $record->tanggal_ideation ?? $record->created_at;

                return $referenceDate !== null && $referenceDate->lte($endOfMonth);
            });

            if ($subset->isEmpty()) {
                $realisasi[] = 0;

                continue;
            }

            $plan = 0;
            $done = 0;

            foreach ($subset as $record) {
                $item = $this->recordToItem($record);
                $plan += (int) $item['plan'];
                $done += (int) $item['done'];
            }

            $realisasi[] = $plan > 0 ? (int) round(($done / $plan) * 100) : 0;
        }

        if ($records->isEmpty()) {
            for ($i = 0; $i < min(6, $currentMonth); $i++) {
                $realisasi[$i] = (int) round($currentProgress * ($i + 1) / max(1, min(6, $currentMonth)));
            }
        }

        return [
            'labels' => array_map(static fn (string $m) => $m . ' ' . $periodYear, $months),
            'target' => $target,
            'realisasi' => $realisasi,
        ];
    }

    /**
     * @param  Collection<int, MonitoringSafetyEngineeringRecord>  $records
     * @param  array<string, int>  $totals
     * @return list<array{title: string, points: list<string>}>
     */
    private function buildBriefAnalysis(Collection $records, array $totals): array
    {
        if ($records->isEmpty()) {
            return [
                [
                    'title' => 'Ringkasan Perusahaan',
                    'points' => ['Belum ada data pada filter yang dipilih.'],
                ],
            ];
        }

        $closedPct = $totals['item'] > 0 ? round(($totals['item_closed'] / $totals['item']) * 100) : 0;

        $points = [
            sprintf(
                'Secara perusahaan, realisasi penyelesaian rekayasa mencapai %d%% atau %s dari %s plan.',
                $totals['progress'],
                number_format($totals['done']),
                number_format($totals['plan']),
            ),
            sprintf(
                'Sebanyak %d dari %d item (%s%%) telah selesai 100%%.',
                $totals['item_closed'],
                $totals['item'],
                $closedPct,
            ),
            sprintf(
                'Terdapat %d mitra kerja (DIC) dengan total %d item overdue.',
                $totals['mitra'],
                $totals['overdue'],
            ),
        ];

        $dbPoints = $records
            ->pluck('brief_analysis_challenge')
            ->filter(static fn (?string $text): bool => $text !== null && trim($text) !== '')
            ->map(static fn (string $text): string => trim($text))
            ->unique()
            ->take(2)
            ->values()
            ->all();

        if ($dbPoints !== []) {
            $points = array_merge($points, $dbPoints);
        }

        return [
            [
                'title' => 'Ringkasan Perusahaan',
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
            'Prioritaskan percepatan pada mitra/item dengan progress rendah dan overdue tinggi.',
            'Lakukan recovery plan dan monitoring mingguan terhadap progress, kendala material, vendor, dan readiness unit.',
            'Pastikan evidence dan update realisasi dilakukan tepat waktu di sistem.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'company' => (string) $request->get('company', ''),
            'period_year' => (int) $request->get('period_year', now()->year),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        $companies = config('monitoring_safety_engineering.perusahaan', []);

        if ($this->tablesReady()) {
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

        return [
            'companies' => array_merge(
                ['' => 'Semua Perusahaan Inisiator'],
                array_combine($companies, $companies) ?: [],
            ),
        ];
    }
}
