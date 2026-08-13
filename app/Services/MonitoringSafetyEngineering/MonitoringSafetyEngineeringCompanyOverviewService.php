<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Models\MonitoringSafetyEngineeringRecord;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MonitoringSafetyEngineeringCompanyOverviewService
{
    public function __construct(
        private readonly MonitoringSafetyEngineeringRiskReductionCalculator $riskReductionCalculator,
    ) {}

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
        $companyScorecards = $this->buildCompanyScorecards($items);
        $totals = $this->buildTotals($items, $mitraRows, $companyScorecards);

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'totals' => $totals,
            'company_scorecards' => $companyScorecards,
            'mitra_rows' => $mitraRows,
            'sumber_program_rows' => $this->buildSumberProgramRows($items),
            'top_overdue' => $this->topOverdueItems($items),
            'trend' => $this->buildTrend($records, $totals['progress'], $filters['period_year']),
            'charts' => $this->buildCharts($companyScorecards),
            'brief_analysis' => $this->buildBriefAnalysis($records, $totals, $companyScorecards),
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
            'companies' => 0,
            'mitra' => 0,
            'item' => 0,
            'plan' => 0,
            'done' => 0,
            'overdue' => 0,
            'item_closed' => 0,
            'gap' => 0,
            'progress' => 0,
            'evaluated_count' => 0,
            'evaluated_pct' => 0,
            'avg_effectiveness' => 0.0,
            'hazard' => 0,
            'insiden' => 0,
            'avg_score' => 0,
        ];

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'totals' => $emptyTotals,
            'company_scorecards' => [],
            'mitra_rows' => [],
            'sumber_program_rows' => $this->emptySumberProgramRows(),
            'top_overdue' => [],
            'trend' => $this->buildTrend(collect(), 0, $filters['period_year']),
            'charts' => $this->buildCharts([]),
            'brief_analysis' => [
                [
                    'title' => 'Brief Analysis',
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
                'deteksi_deviasi',
                'intervensi_deviasi',
                'prediksi_penurunan_tangga_risiko',
                'terkait_hazard',
                'terkait_insiden',
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

        $deteksi = $record->deteksi_deviasi !== null ? (string) $record->deteksi_deviasi : null;
        $intervensi = $record->intervensi_deviasi !== null ? (string) $record->intervensi_deviasi : null;
        $level = $this->riskReductionCalculator->resolveEffectivePrediksi(
            $record->prediksi_penurunan_tangga_risiko,
            $deteksi,
            $intervensi,
        );

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
            'perusahaan' => (string) $record->perusahaan,
            'mitra_key' => trim($record->perusahaan . ' ' . $record->site),
            'sumber_program' => $this->resolveSumberProgramLabel($record),
            'tanggal_ideation' => $record->tanggal_ideation,
            'created_at' => $record->created_at,
            'level' => $level ?? 0,
            'evaluated' => ($level ?? 0) > 0,
            'hazard' => $record->terkait_hazard ? 1 : 0,
            'insiden' => $record->terkait_insiden ? 1 : 0,
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
                'perusahaan' => (string) $item['perusahaan'],
                'item' => 0,
                'plan' => 0,
                'done' => 0,
                'overdue' => 0,
                'hazard' => 0,
                'insiden' => 0,
                'level_sum' => 0,
                'evaluated' => 0,
            ];

            $grouped[$key]['item']++;
            $grouped[$key]['plan'] += (int) $item['plan'];
            $grouped[$key]['done'] += (int) $item['done'];
            $grouped[$key]['overdue'] += (int) $item['overdue'];
            $grouped[$key]['hazard'] += (int) $item['hazard'];
            $grouped[$key]['insiden'] += (int) $item['insiden'];

            if (! empty($item['evaluated'])) {
                $grouped[$key]['evaluated']++;
                $grouped[$key]['level_sum'] += (int) $item['level'];
            }
        }

        $rows = array_map(function (array $row): array {
            $progress = $row['plan'] > 0 ? (int) round(($row['done'] / $row['plan']) * 100) : 0;
            $avgEffectiveness = $row['evaluated'] > 0
                ? round($row['level_sum'] / $row['evaluated'], 2)
                : 0.0;

            return [
                'name' => $row['name'],
                'perusahaan' => $row['perusahaan'],
                'item' => $row['item'],
                'plan' => $row['plan'],
                'done' => $row['done'],
                'gap' => $row['plan'] - $row['done'],
                'progress' => $progress,
                'overdue' => $row['overdue'],
                'hazard' => $row['hazard'],
                'insiden' => $row['insiden'],
                'avg_effectiveness' => $avgEffectiveness,
                'evaluated' => $row['evaluated'],
                'status' => $this->statusFor($progress),
            ];
        }, array_values($grouped));

        usort($rows, static fn (array $a, array $b): int => $b['plan'] <=> $a['plan']);

        return $rows;
    }

    /**
     * Ranking scorecard per perusahaan.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function buildCompanyScorecards(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $company = trim((string) $item['perusahaan']);
            if ($company === '') {
                $company = 'Tanpa Perusahaan';
            }

            $grouped[$company] ??= [
                'perusahaan' => $company,
                'item' => 0,
                'plan' => 0,
                'done' => 0,
                'overdue' => 0,
                'hazard' => 0,
                'insiden' => 0,
                'level_sum' => 0,
                'evaluated' => 0,
                'mitra_keys' => [],
            ];

            $grouped[$company]['item']++;
            $grouped[$company]['plan'] += (int) $item['plan'];
            $grouped[$company]['done'] += (int) $item['done'];
            $grouped[$company]['overdue'] += (int) $item['overdue'];
            $grouped[$company]['hazard'] += (int) $item['hazard'];
            $grouped[$company]['insiden'] += (int) $item['insiden'];
            $grouped[$company]['mitra_keys'][(string) $item['mitra_key']] = true;

            if (! empty($item['evaluated'])) {
                $grouped[$company]['evaluated']++;
                $grouped[$company]['level_sum'] += (int) $item['level'];
            }
        }

        $scorecards = [];

        foreach ($grouped as $row) {
            $itemCount = max(1, (int) $row['item']);
            $progress = $row['plan'] > 0 ? (int) round(($row['done'] / $row['plan']) * 100) : 0;
            $avgEffectiveness = $row['evaluated'] > 0
                ? round($row['level_sum'] / $row['evaluated'], 2)
                : 0.0;
            $evaluatedPct = (int) round(($row['evaluated'] / $itemCount) * 100);
            $score = $this->computeCompanyScore(
                $progress,
                $avgEffectiveness,
                (int) $row['overdue'],
                (int) $row['item'],
                (int) $row['hazard'],
                (int) $row['insiden'],
            );

            $scorecards[] = [
                'perusahaan' => $row['perusahaan'],
                'mitra' => count($row['mitra_keys']),
                'item' => (int) $row['item'],
                'plan' => (int) $row['plan'],
                'done' => (int) $row['done'],
                'progress' => $progress,
                'overdue' => (int) $row['overdue'],
                'hazard' => (int) $row['hazard'],
                'insiden' => (int) $row['insiden'],
                'evaluated' => (int) $row['evaluated'],
                'evaluated_pct' => $evaluatedPct,
                'avg_effectiveness' => $avgEffectiveness,
                'score' => $score,
                'band' => $this->scoreBand($score),
            ];
        }

        usort($scorecards, static function (array $a, array $b): int {
            $scoreCmp = $b['score'] <=> $a['score'];
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }

            return $b['progress'] <=> $a['progress'];
        });

        foreach ($scorecards as $index => &$card) {
            $card['rank'] = $index + 1;
        }
        unset($card);

        return $scorecards;
    }

    private function computeCompanyScore(
        int $progress,
        float $avgEffectiveness,
        int $overdue,
        int $itemCount,
        int $hazard,
        int $insiden,
    ): int {
        $safeItems = max(1, $itemCount);
        $progressScore = min(100, max(0, $progress));
        $effectivenessScore = min(100, max(0, ($avgEffectiveness / 3) * 100));
        $overdueScore = min(100, max(0, (1 - ($overdue / $safeItems)) * 100));
        $exposureRate = min(1, ($hazard + ($insiden * 2)) / $safeItems);
        $exposureScore = (1 - $exposureRate) * 100;

        return (int) round(
            ($progressScore * 0.40)
            + ($effectivenessScore * 0.25)
            + ($overdueScore * 0.20)
            + ($exposureScore * 0.15)
        );
    }

    /**
     * @return array{label: string, class: string}
     */
    private function scoreBand(int $score): array
    {
        return match (true) {
            $score >= 80 => ['label' => 'Excellent', 'class' => 'mse-band--excellent'],
            $score >= 60 => ['label' => 'On Track', 'class' => 'mse-band--ontrack'],
            $score >= 40 => ['label' => 'Watch', 'class' => 'mse-band--watch'],
            default => ['label' => 'Critical', 'class' => 'mse-band--critical'],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $mitraRows
     * @param  list<array<string, mixed>>  $companyScorecards
     * @return array<string, mixed>
     */
    private function buildTotals(array $items, array $mitraRows, array $companyScorecards): array
    {
        $plan = (int) array_sum(array_column($items, 'plan'));
        $done = (int) array_sum(array_column($items, 'done'));
        $itemClosed = count(array_filter($items, static fn (array $item): bool => $item['progress'] >= 100));
        $evaluated = count(array_filter($items, static fn (array $item): bool => ! empty($item['evaluated'])));
        $levelSum = (int) array_sum(array_map(
            static fn (array $item): int => ! empty($item['evaluated']) ? (int) $item['level'] : 0,
            $items,
        ));
        $itemCount = count($items);
        $avgEffectiveness = $evaluated > 0 ? round($levelSum / $evaluated, 2) : 0.0;
        $avgScore = count($companyScorecards) > 0
            ? (int) round(array_sum(array_column($companyScorecards, 'score')) / count($companyScorecards))
            : 0;

        return [
            'companies' => count($companyScorecards),
            'mitra' => count($mitraRows),
            'item' => $itemCount,
            'plan' => $plan,
            'done' => $done,
            'overdue' => (int) array_sum(array_column($items, 'overdue')),
            'item_closed' => $itemClosed,
            'gap' => max(0, $plan - $done),
            'progress' => $plan > 0 ? (int) round(($done / $plan) * 100) : 0,
            'evaluated_count' => $evaluated,
            'evaluated_pct' => $itemCount > 0 ? (int) round(($evaluated / $itemCount) * 100) : 0,
            'avg_effectiveness' => $avgEffectiveness,
            'hazard' => (int) array_sum(array_column($items, 'hazard')),
            'insiden' => (int) array_sum(array_column($items, 'insiden')),
            'avg_score' => $avgScore,
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
                'perusahaan' => $item['perusahaan'],
                'plan' => $item['plan'],
                'done' => $item['done'],
                'progress' => $item['progress'],
                'overdue' => $gap > 0 ? $gap : max(0, (int) $item['plan'] - (int) $item['done']),
            ];
        }, array_slice(array_values($candidates), 0, 5));
    }

    /**
     * @param  Collection<int, MonitoringSafetyEngineeringRecord>  $records
     * @return array{labels: list<string>, target: list<int>, realisasi: list<?int>}
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
            'labels' => array_map(static fn (string $m): string => $m.' '.$periodYear, $months),
            'target' => $target,
            'realisasi' => $realisasi,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $companyScorecards
     * @return array<string, mixed>
     */
    private function buildCharts(array $companyScorecards): array
    {
        $labels = array_column($companyScorecards, 'perusahaan');

        return [
            'company_comparison' => [
                'labels' => $labels,
                'progress' => array_map(static fn (array $c): int => (int) $c['progress'], $companyScorecards),
                'effectiveness' => array_map(
                    static fn (array $c): float => round(((float) $c['avg_effectiveness'] / 3) * 100, 1),
                    $companyScorecards,
                ),
                'scores' => array_map(static fn (array $c): int => (int) $c['score'], $companyScorecards),
            ],
        ];
    }

    /**
     * @param  Collection<int, MonitoringSafetyEngineeringRecord>  $records
     * @param  array<string, mixed>  $totals
     * @param  list<array<string, mixed>>  $companyScorecards
     * @return list<array{title: string, points: list<string>}>
     */
    private function buildBriefAnalysis(Collection $records, array $totals, array $companyScorecards): array
    {
        if ($records->isEmpty()) {
            return [
                [
                    'title' => 'Brief Analysis',
                    'points' => ['Belum ada data pada filter yang dipilih.'],
                ],
            ];
        }

        $points = [
            sprintf(
                'Realisasi penyelesaian rekayasa mencapai %d%% (%s dari %s plan).',
                (int) $totals['progress'],
                number_format((int) $totals['done']),
                number_format((int) $totals['plan']),
            ),
            sprintf(
                'Sebanyak %d dari %d item (%d%%) telah selesai 100%%. Gap tersisa: %s.',
                (int) $totals['item_closed'],
                (int) $totals['item'],
                ($totals['item'] ?? 0) > 0 ? (int) round(($totals['item_closed'] / $totals['item']) * 100) : 0,
                number_format((int) $totals['gap']),
            ),
            sprintf(
                'Terdapat %d mitra kerja (DIC) dengan %d item overdue yang perlu dipercepat.',
                (int) $totals['mitra'],
                (int) $totals['overdue'],
            ),
        ];

        if ($companyScorecards !== []) {
            $lowestProgress = $companyScorecards;
            usort($lowestProgress, static fn (array $a, array $b): int => $a['progress'] <=> $b['progress']);
            $weak = $lowestProgress[0];
            $points[] = sprintf(
                'Perusahaan dengan progress terendah: %s (%d%%, overdue %d).',
                $weak['perusahaan'],
                (int) $weak['progress'],
                (int) $weak['overdue'],
            );
        }

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
                'title' => 'Brief Analysis',
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
            'Prioritaskan perusahaan/mitra dengan skor Critical atau Watch dan overdue tinggi.',
            'Lengkapi Deteksi & Intervensi agar prediksi efektivitas terisi otomatis.',
            'Susun recovery plan mingguan untuk item overdue dan exposure hazard/insiden.',
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

        $currentYear = (int) now()->year;
        $years = range($currentYear, $currentYear - 4);

        return [
            'companies' => array_merge(
                ['' => 'Semua Perusahaan Inisiator'],
                array_combine($companies, $companies) ?: [],
            ),
            'period_years' => array_combine($years, $years) ?: [],
        ];
    }
}
