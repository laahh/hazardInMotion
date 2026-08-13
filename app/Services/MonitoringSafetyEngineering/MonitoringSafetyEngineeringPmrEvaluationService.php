<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Models\MonitoringSafetyEngineeringRecord;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MonitoringSafetyEngineeringPmrEvaluationService
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
        $pmrGroups = array_keys(config('monitoring_safety_engineering.pmr_evaluation.groups', []));

        if (! $this->tablesReady()) {
            return $this->emptyDashboard($filters, $pmrGroups);
        }

        $records = $this->fetchFilteredRecords($filters);
        $items = $records->map(fn (MonitoringSafetyEngineeringRecord $record): array => $this->recordToItem($record))->all();
        $summary = $this->buildSummary($items, $pmrGroups);
        $validationMatrix = $this->validationMatrixDefinition();
        $followUpSummary = $this->buildFollowUpSummary($items);
        $priorityItems = $this->buildPriorityUpgradeItems($items);

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'summary' => $summary,
            'items' => $items,
            'pmr_groups' => $pmrGroups,
            'effectiveness_levels' => $this->effectivenessLevels(),
            'validation_matrix' => $validationMatrix,
            'follow_up_summary' => $followUpSummary,
            'priority_items' => $priorityItems,
            'fokus_analisis' => $this->fokusAnalisisPoints(),
            'brief_analysis' => $this->buildBriefAnalysis($records, $items),
            'next_todo' => $this->buildNextTodo($records),
            'charts' => $this->buildCharts($items, $pmrGroups, $summary, $followUpSummary),
        ];
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('monitoring_safety_engineering_records');
    }

    /**
     * @param  list<string>  $pmrGroups
     * @return array<string, mixed>
     */
    private function emptyDashboard(array $filters, array $pmrGroups): array
    {
        $summary = $this->buildSummary([], $pmrGroups);
        $followUpSummary = $this->buildFollowUpSummary([]);

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'summary' => $summary,
            'items' => [],
            'pmr_groups' => $pmrGroups,
            'effectiveness_levels' => $this->effectivenessLevels(),
            'validation_matrix' => $this->validationMatrixDefinition(),
            'follow_up_summary' => $followUpSummary,
            'priority_items' => [],
            'fokus_analisis' => $this->fokusAnalisisPoints(),
            'brief_analysis' => [
                [
                    'title' => 'Status Evaluasi',
                    'points' => ['Tabel monitoring_safety_engineering_records belum tersedia. Jalankan migration terlebih dahulu.'],
                ],
            ],
            'next_todo' => ['Upload atau input data rekayasa melalui menu Update Data.'],
            'charts' => $this->buildCharts([], $pmrGroups, $summary, $followUpSummary),
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
                'pengendalian_rekayasa',
                'aktivitas',
                'tanggal_ideation',
                'deteksi_deviasi',
                'intervensi_deviasi',
                'prediksi_penurunan_tangga_risiko',
                'terkait_hazard',
                'terkait_insiden',
                'brief_analysis_challenge',
                'next_to_do',
                'period_year',
            ])
            ->whereIn('sumber_rekayasa', $this->pmrSumberValues())
            ->orderBy('sort_order')
            ->orderBy('row_no')
            ->orderBy('id');

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * @return list<string>
     */
    private function pmrSumberValues(): array
    {
        $values = config('monitoring_safety_engineering.pmr_evaluation.sumber_rekayasa', []);
        $labels = config('monitoring_safety_engineering.sumber_rekayasa', []);

        foreach ($values as $value) {
            if (isset($labels[$value])) {
                $values[] = (string) $labels[$value];
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param  Builder<MonitoringSafetyEngineeringRecord>  $query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['company'] !== '') {
            $query->where('perusahaan', $filters['company']);
        }

        if ($filters['period_year'] > 0) {
            $query->where('period_year', $filters['period_year']);
        }

        if ($filters['date_from'] !== '') {
            $query->where(function (Builder $builder) use ($filters): void {
                $builder->whereDate('tanggal_ideation', '>=', $filters['date_from'])
                    ->orWhereDate('created_at', '>=', $filters['date_from']);
            });
        }

        if ($filters['date_to'] !== '') {
            $query->where(function (Builder $builder) use ($filters): void {
                $builder->whereDate('tanggal_ideation', '<=', $filters['date_to'])
                    ->orWhereDate('created_at', '<=', $filters['date_to']);
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function recordToItem(MonitoringSafetyEngineeringRecord $record): array
    {
        $deteksiRaw = $record->deteksi_deviasi !== null ? (string) $record->deteksi_deviasi : null;
        $intervensiRaw = $record->intervensi_deviasi !== null ? (string) $record->intervensi_deviasi : null;
        $storedPrediksi = $record->prediksi_penurunan_tangga_risiko;
        $prediksi = $this->riskReductionCalculator->resolveEffectivePrediksi(
            $storedPrediksi,
            $deteksiRaw,
            $intervensiRaw,
        );
        $isDerived = $this->riskReductionCalculator->isDerivedPrediksi(
            $storedPrediksi,
            $deteksiRaw,
            $intervensiRaw,
        );

        $hazard = $record->terkait_hazard ? 1 : 0;
        $insiden = $record->terkait_insiden ? 1 : 0;
        $level = $prediksi ?? 0;
        $validation = $this->resolveEffectivenessValidation($level, $hazard, $insiden);

        return [
            'id' => $record->id,
            'name' => $record->pengendalian_rekayasa,
            'icon' => $this->resolveIcon($record->pengendalian_rekayasa),
            'pmr' => $this->resolvePmrGroup($record),
            'level' => $level,
            'level_label' => $this->levelLabel($prediksi),
            'prediksi_is_derived' => $isDerived,
            'hazard' => $hazard,
            'insiden' => $insiden,
            'hazard_label' => $hazard > 0 ? 'Ada' : 'Tidak Ada',
            'insiden_label' => $insiden > 0 ? 'Ada' : 'Tidak Ada',
            'validation_status' => $validation['status'],
            'validation_label' => $validation['label'],
            'follow_up' => $validation['follow_up'],
            'upgrade_potential' => $validation['upgrade_potential'],
            'site' => $record->site,
            'perusahaan' => $record->perusahaan,
            'deteksi_deviasi' => $this->formatDeteksiLabel($deteksiRaw),
            'intervensi_deviasi' => $this->formatIntervensiLabel($intervensiRaw),
            'sumber_rekayasa' => $this->normalizeEnumValue($record->sumber_rekayasa),
        ];
    }

    /**
     * Validasi efektivitas berbasis Hazard × Incident setelah rekayasa.
     *
     * @return array{status: string, label: string, follow_up: string, upgrade_potential: bool}
     */
    private function resolveEffectivenessValidation(int $level, int $hazard, int $insiden): array
    {
        if ($level <= 0) {
            return [
                'status' => 'needs_data',
                'label' => 'Perlu Validasi Data',
                'follow_up' => 'Lengkapi Prediksi',
                'upgrade_potential' => false,
            ];
        }

        if ($hazard === 0 && $insiden === 0) {
            return [
                'status' => 'effective',
                'label' => 'Efektif',
                'follow_up' => 'Pertahankan Level',
                'upgrade_potential' => false,
            ];
        }

        if ($hazard === 1 && $insiden === 0) {
            return [
                'status' => 'partial',
                'label' => 'Sebagian Efektif',
                'follow_up' => 'Pertahankan + Monitoring',
                'upgrade_potential' => $level < 3,
            ];
        }

        if ($hazard === 0 && $insiden === 1) {
            return [
                'status' => 'partial',
                'label' => 'Sebagian Efektif',
                'follow_up' => 'Naikkan Level',
                'upgrade_potential' => true,
            ];
        }

        return [
            'status' => 'ineffective',
            'label' => 'Tidak Efektif',
            'follow_up' => 'Re-evaluasi',
            'upgrade_potential' => true,
        ];
    }

    private function formatDeteksiLabel(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        $labels = config('monitoring_safety_engineering.deteksi_deviasi', []);
        $key = $this->normalizeEnumValue($value);

        return (string) ($labels[$key] ?? $value);
    }

    private function formatIntervensiLabel(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        $labels = config('monitoring_safety_engineering.intervensi_deviasi', []);
        $key = $this->normalizeEnumValue($value);

        return (string) ($labels[$key] ?? $value);
    }

    private function resolvePmrGroup(MonitoringSafetyEngineeringRecord $record): string
    {
        $sumber = $this->normalizeEnumValue($record->sumber_rekayasa);
        $groups = config('monitoring_safety_engineering.pmr_evaluation.groups', []);

        foreach ($groups as $label => $sumberList) {
            if (in_array($sumber, $sumberList, true)) {
                return (string) $label;
            }
        }

        foreach (['PMR 2023', 'PMR 2024', 'PMR 2025', 'PMR 1', 'PMR 2', 'PMR 3'] as $pmrLabel) {
            if (stripos($record->pengendalian_rekayasa, $pmrLabel) !== false
                || stripos((string) $record->aktivitas, $pmrLabel) !== false) {
                return match ($pmrLabel) {
                    'PMR 1' => 'PMR 2023',
                    'PMR 2' => 'PMR 2024',
                    'PMR 3' => 'PMR 2025',
                    default => $pmrLabel,
                };
            }
        }

        $labels = config('monitoring_safety_engineering.sumber_rekayasa', []);

        return (string) ($labels[$sumber] ?? 'PMR');
    }

    private function levelLabel(?int $prediksi): string
    {
        return match ($prediksi) {
            1 => 'Turun 1 Tangga',
            2 => 'Turun 2 Tangga',
            3 => 'Turun 3 Tangga',
            default => 'Belum Ada Prediksi',
        };
    }

    private function resolveIcon(string $name): string
    {
        $lower = strtolower($name);

        return match (true) {
            str_contains($lower, 'cctv') || str_contains($lower, 'camera') => 'videocam',
            str_contains($lower, 'inclino') => 'rule',
            str_contains($lower, 'wheel') || str_contains($lower, 'nut') || str_contains($lower, 'lock') => 'radio_button_checked',
            str_contains($lower, 'seatbelt') => 'airline_seat_recline_normal',
            str_contains($lower, 'buzzer') || str_contains($lower, 'alarm') => 'notifications_active',
            default => 'engineering',
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
     * @param  list<string>  $pmrGroups
     * @return array<string, mixed>
     */
    private function buildSummary(array $items, array $pmrGroups): array
    {
        $total = count($items);
        $summary = [
            'total' => $total,
            'total_hazard' => (int) array_sum(array_column($items, 'hazard')),
            'total_insiden' => (int) array_sum(array_column($items, 'insiden')),
            'evaluated_count' => 0,
            'incomplete_count' => 0,
            'auto_count' => 0,
            'manual_count' => 0,
            'upgrade_potential_count' => 0,
            'level_counts' => [
                3 => 0,
                2 => 0,
                1 => 0,
                0 => 0,
            ],
            'validation_counts' => [
                'effective' => 0,
                'partial' => 0,
                'ineffective' => 0,
                'needs_data' => 0,
            ],
        ];

        foreach ($pmrGroups as $pmr) {
            $summary[$pmr] = count(array_filter($items, static fn (array $item): bool => $item['pmr'] === $pmr));
        }

        foreach ($items as $item) {
            $level = (int) ($item['level'] ?? 0);
            if ($level >= 1 && $level <= 3) {
                $summary['level_counts'][$level]++;
                $summary['evaluated_count']++;
            } else {
                $summary['level_counts'][0]++;
                $summary['incomplete_count']++;
            }

            if (! empty($item['prediksi_is_derived'])) {
                $summary['auto_count']++;
            } elseif ($level > 0) {
                $summary['manual_count']++;
            }

            if (! empty($item['upgrade_potential'])) {
                $summary['upgrade_potential_count']++;
            }

            $status = (string) ($item['validation_status'] ?? 'needs_data');
            if (array_key_exists($status, $summary['validation_counts'])) {
                $summary['validation_counts'][$status]++;
            }
        }

        $summary['evaluated_pct'] = $total > 0
            ? (int) round(($summary['evaluated_count'] / $total) * 100)
            : 0;
        $summary['upgrade_potential_pct'] = $total > 0
            ? round(($summary['upgrade_potential_count'] / $total) * 100, 1)
            : 0.0;

        foreach ([3, 2, 1, 0] as $lvl) {
            $summary['level_pct'][$lvl] = $total > 0
                ? round(($summary['level_counts'][$lvl] / $total) * 100, 1)
                : 0.0;
        }

        foreach ($summary['validation_counts'] as $key => $count) {
            $summary['validation_pct'][$key] = $total > 0
                ? round(($count / $total) * 100, 1)
                : 0.0;
        }

        $prediksiCounts = [];
        foreach ($items as $item) {
            $label = (string) $item['level_label'];
            $prediksiCounts[$label] = ($prediksiCounts[$label] ?? 0) + 1;
        }

        arsort($prediksiCounts);
        $dominantLabel = array_key_first($prediksiCounts) ?? 'Belum Ada Prediksi';
        $summary['dominant_level_label'] = $dominantLabel;
        $summary['dominant_level_count'] = $prediksiCounts[$dominantLabel] ?? 0;

        return $summary;
    }

    /**
     * Matriks referensi Hazard × Incident → status validasi & tindak lanjut.
     *
     * @return list<array<string, mixed>>
     */
    private function validationMatrixDefinition(): array
    {
        return [
            [
                'hazard' => 'Tidak Ada',
                'insiden' => 'Tidak Ada',
                'status' => 'Efektif',
                'status_key' => 'effective',
                'follow_up' => 'Pertahankan Level',
            ],
            [
                'hazard' => 'Ada',
                'insiden' => 'Tidak Ada',
                'status' => 'Sebagian Efektif',
                'status_key' => 'partial',
                'follow_up' => 'Pertahankan + Monitoring',
            ],
            [
                'hazard' => 'Tidak Ada',
                'insiden' => 'Ada',
                'status' => 'Sebagian Efektif',
                'status_key' => 'partial',
                'follow_up' => 'Naikkan Level',
            ],
            [
                'hazard' => 'Ada',
                'insiden' => 'Ada',
                'status' => 'Tidak Efektif',
                'status_key' => 'ineffective',
                'follow_up' => 'Re-evaluasi',
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{key: string, label: string, count: int}>
     */
    private function buildFollowUpSummary(array $items): array
    {
        $order = [
            'Pertahankan Level' => 0,
            'Pertahankan + Monitoring' => 0,
            'Naikkan Level' => 0,
            'Re-evaluasi' => 0,
            'Lengkapi Prediksi' => 0,
        ];

        foreach ($items as $item) {
            $action = (string) ($item['follow_up'] ?? 'Lengkapi Prediksi');
            if (! array_key_exists($action, $order)) {
                $order[$action] = 0;
            }
            $order[$action]++;
        }

        $rows = [];
        foreach ($order as $label => $count) {
            $rows[] = [
                'key' => strtolower(str_replace([' ', '+', '/'], ['_', '', '_'], $label)),
                'label' => $label,
                'count' => $count,
            ];
        }

        return $rows;
    }

    /**
     * Prioritas upgrade: item dengan potensi naik level / tidak efektif / turun 1.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function buildPriorityUpgradeItems(array $items): array
    {
        $priority = array_values(array_filter(
            $items,
            static fn (array $item): bool => ! empty($item['upgrade_potential'])
                || in_array((string) ($item['validation_status'] ?? ''), ['ineffective', 'partial'], true)
                || (int) ($item['level'] ?? 0) === 1,
        ));

        usort($priority, static function (array $a, array $b): int {
            $rank = static function (array $item): int {
                return match ((string) ($item['validation_status'] ?? '')) {
                    'ineffective' => 3,
                    'partial' => 2,
                    'needs_data' => 1,
                    default => 0,
                } * 10
                    + (int) ($item['insiden'] ?? 0) * 5
                    + (int) ($item['hazard'] ?? 0) * 2
                    + ((int) ($item['level'] ?? 0) === 1 ? 1 : 0);
            };

            return $rank($b) <=> $rank($a);
        });

        return array_slice($priority, 0, 40);
    }

    /**
     * @return list<array{icon: string, text: string}>
     */
    private function fokusAnalisisPoints(): array
    {
        return [
            ['icon' => 'track_changes', 'text' => 'Apakah prediksi penurunan tangga sudah selaras dengan Deteksi × Intervensi?'],
            ['icon' => 'analytics', 'text' => 'Rekayasa mana yang masih Turun 1 Tangga dan berpotensi naik level?'],
            ['icon' => 'warning', 'text' => 'Item mana yang masih punya hazard terkait setelah rekayasa?'],
            ['icon' => 'report', 'text' => 'Item mana yang terkait insiden dan perlu re-evaluasi segera?'],
            ['icon' => 'settings_suggest', 'text' => 'Tindak lanjut mana yang paling banyak muncul untuk upgrade pengendalian?'],
        ];
    }

    /**
     * @return list<array{level: int, sifat: string, prediksi: string, keterangan: string}>
     */
    private function effectivenessLevels(): array
    {
        return [
            ['level' => 1, 'sifat' => 'Menghilangkan / Eliminasi', 'prediksi' => 'Turun 3 tangga', 'keterangan' => 'Aktivitas atau sumber bahaya dihilangkan'],
            ['level' => 2, 'sifat' => 'Alat mendeteksi + alat mengintervensi', 'prediksi' => 'Turun 2 tangga', 'keterangan' => 'Masih ada potensi alat error/risiko baru, tapi tidak bergantung pada respons manusia'],
            ['level' => 3, 'sifat' => 'Alat mendeteksi + manusia mengintervensi', 'prediksi' => 'Turun 1 tangga', 'keterangan' => 'Masih bergantung pada kecepatan respons manusia'],
            ['level' => 4, 'sifat' => 'Manusia mendeteksi + alat mengintervensi', 'prediksi' => 'Turun 1 tangga', 'keterangan' => 'Masih bergantung pada kecepatan respons manusia'],
            ['level' => 5, 'sifat' => 'Manusia mendeteksi + manusia mengintervensi', 'prediksi' => 'Turun 1 tangga', 'keterangan' => 'Potensi human error tinggi'],
        ];
    }

    /**
     * @param  Collection<int, MonitoringSafetyEngineeringRecord>  $records
     * @param  list<array<string, mixed>>  $items
     * @return list<array{title: string, points: list<string>}>
     */
    private function buildBriefAnalysis(Collection $records, array $items): array
    {
        if ($items === []) {
            return [
                [
                    'title' => 'Status Evaluasi',
                    'points' => ['Belum ada data evaluasi PMR pada filter yang dipilih.'],
                ],
            ];
        }

        $total = count($items);
        $withPrediksi = count(array_filter($items, static fn (array $item): bool => $item['level'] > 0));
        $incomplete = $total - $withPrediksi;
        $withInsiden = count(array_filter($items, static fn (array $item): bool => $item['insiden'] > 0));
        $autoCount = count(array_filter($items, static fn (array $item): bool => ! empty($item['prediksi_is_derived'])));
        $weakItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => (int) ($item['level'] ?? 0) === 1 || (int) ($item['level'] ?? 0) === 0,
        ));
        $priority = collect($weakItems)
            ->sortByDesc(static fn (array $item): int => ((int) $item['insiden'] * 10) + (int) $item['hazard'])
            ->first();

        $points = [
            sprintf(
                'Cakupan evaluasi: %d dari %d item (%d%%) sudah punya prediksi penurunan tangga risiko.',
                $withPrediksi,
                $total,
                $total > 0 ? (int) round(($withPrediksi / $total) * 100) : 0,
            ),
            sprintf(
                '%d item belum lengkap (Deteksi/Intervensi/Prediksi). %d prediksi dihitung otomatis dari matriks.',
                $incomplete,
                $autoCount,
            ),
            sprintf(
                'Exposure: %s hazard terkait, %d item terkait insiden.',
                number_format((int) array_sum(array_column($items, 'hazard'))),
                $withInsiden,
            ),
        ];

        if (is_array($priority)) {
            $points[] = sprintf(
                'Prioritas perbaikan: %s (%s · %s).',
                $priority['name'],
                $priority['level_label'],
                ($priority['insiden'] ?? 0) > 0 ? 'ada insiden terkait' : 'hazard terkait',
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
                'title' => 'Temuan Evaluasi',
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
            'Lengkapi kolom Next To Do dan prediksi penurunan tangga risiko pada data PMR.',
            'Validasi implementasi di lapangan melalui foto, checklist inspeksi, dan evidence efektivitas.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $pmrGroups
     * @param  array<string, mixed>  $summary
     * @param  list<array{key: string, label: string, count: int}>  $followUpSummary
     * @return array<string, mixed>
     */
    private function buildCharts(array $items, array $pmrGroups, array $summary, array $followUpSummary): array
    {
        $levelCounts = $summary['level_counts'] ?? [3 => 0, 2 => 0, 1 => 0, 0 => 0];
        $validationCounts = $summary['validation_counts'] ?? [
            'effective' => 0,
            'partial' => 0,
            'ineffective' => 0,
            'needs_data' => 0,
        ];

        return [
            'prediction_distribution' => [
                'labels' => ['Turun 1 Tangga', 'Turun 2 Tangga', 'Turun 3 Tangga', 'Belum Ada Prediksi'],
                'data' => [
                    (int) ($levelCounts[1] ?? 0),
                    (int) ($levelCounts[2] ?? 0),
                    (int) ($levelCounts[3] ?? 0),
                    (int) ($levelCounts[0] ?? 0),
                ],
                'colors' => ['#0891B2', '#D97706', '#BE123C', '#64748B'],
            ],
            'validation_distribution' => [
                'labels' => ['Efektif', 'Sebagian Efektif', 'Tidak Efektif', 'Perlu Validasi Data'],
                'data' => [
                    (int) ($validationCounts['effective'] ?? 0),
                    (int) ($validationCounts['partial'] ?? 0),
                    (int) ($validationCounts['ineffective'] ?? 0),
                    (int) ($validationCounts['needs_data'] ?? 0),
                ],
                'colors' => ['#0F766E', '#CA8A04', '#E11D48', '#94A3B8'],
            ],
            'follow_up_distribution' => [
                'labels' => array_column($followUpSummary, 'label'),
                'data' => array_map(static fn (array $row): int => (int) $row['count'], $followUpSummary),
                'colors' => ['#0F766E', '#0284C7', '#EA580C', '#BE123C', '#64748B'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        $dateFrom = (string) $request->get('date_from', now()->startOfYear()->format('Y-m-d'));
        $periodYear = (int) date('Y', strtotime($dateFrom) ?: time());

        return [
            'company' => (string) $request->get('company', ''),
            'date_from' => $dateFrom,
            'date_to' => (string) $request->get('date_to', now()->format('Y-m-d')),
            'period_year' => $periodYear > 0 ? $periodYear : (int) now()->year,
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
                    ->whereIn('sumber_rekayasa', $this->pmrSumberValues())
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
