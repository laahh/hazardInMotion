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

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'summary' => $this->buildSummary($items, $pmrGroups),
            'items' => $items,
            'pmr_groups' => $pmrGroups,
            'effectiveness_levels' => $this->effectivenessLevels(),
            'brief_analysis' => $this->buildBriefAnalysis($records, $items),
            'next_todo' => $this->buildNextTodo($records),
            'charts' => $this->buildCharts($items, $pmrGroups),
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
        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions(),
            'summary' => $this->buildSummary([], $pmrGroups),
            'items' => [],
            'pmr_groups' => $pmrGroups,
            'effectiveness_levels' => $this->effectivenessLevels(),
            'brief_analysis' => [
                [
                    'title' => 'Status Evaluasi',
                    'points' => ['Tabel monitoring_safety_engineering_records belum tersedia. Jalankan migration terlebih dahulu.'],
                ],
            ],
            'next_todo' => ['Upload atau input data rekayasa melalui menu Update Data.'],
            'charts' => $this->buildCharts([], $pmrGroups),
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
        $prediksi = $record->prediksi_penurunan_tangga_risiko;

        return [
            'id' => $record->id,
            'name' => $record->pengendalian_rekayasa,
            'icon' => $this->resolveIcon($record->pengendalian_rekayasa),
            'pmr' => $this->resolvePmrGroup($record),
            'level' => $prediksi ?? 0,
            'level_label' => $this->levelLabel($prediksi),
            'hazard' => $record->terkait_hazard ? 1 : 0,
            'insiden' => $record->terkait_insiden ? 1 : 0,
            'site' => $record->site,
            'perusahaan' => $record->perusahaan,
            'deteksi_deviasi' => $record->deteksi_deviasi,
            'intervensi_deviasi' => $record->intervensi_deviasi,
            'sumber_rekayasa' => $this->normalizeEnumValue($record->sumber_rekayasa),
        ];
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

        foreach (['PMR 1', 'PMR 2', 'PMR 3'] as $pmrLabel) {
            if (stripos($record->pengendalian_rekayasa, $pmrLabel) !== false
                || stripos((string) $record->aktivitas, $pmrLabel) !== false) {
                return $pmrLabel;
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
        $summary = [
            'total' => count($items),
            'total_hazard' => (int) array_sum(array_column($items, 'hazard')),
            'total_insiden' => (int) array_sum(array_column($items, 'insiden')),
        ];

        foreach ($pmrGroups as $pmr) {
            $summary[$pmr] = count(array_filter($items, static fn (array $item): bool => $item['pmr'] === $pmr));
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
        $withInsiden = count(array_filter($items, static fn (array $item): bool => $item['insiden'] > 0));
        $maxHazardItem = collect($items)->sortByDesc('hazard')->first();

        $points = [
            sprintf(
                'Terdapat %d pengendalian rekayasa standar PMR, %d item memiliki prediksi penurunan tangga risiko.',
                $total,
                $withPrediksi,
            ),
            sprintf(
                'Total exposure hazard terkait: %s, dengan %d item memiliki insiden terkait.',
                number_format((int) array_sum(array_column($items, 'hazard'))),
                $withInsiden,
            ),
        ];

        if (is_array($maxHazardItem) && ($maxHazardItem['hazard'] ?? 0) > 0) {
            $points[] = sprintf(
                'Prioritas evaluasi: %s (hazard %s%s).',
                $maxHazardItem['name'],
                number_format((int) $maxHazardItem['hazard']),
                ($maxHazardItem['insiden'] ?? 0) > 0 ? ', terdapat insiden terkait' : '',
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
                'title' => 'Status Evaluasi',
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
     * @return array<string, mixed>
     */
    private function buildCharts(array $items, array $pmrGroups): array
    {
        $groupColors = config('monitoring_safety_engineering.pmr_evaluation.group_colors', []);
        $palette = ['#7366FF', '#CFC8FF', '#51BB25', '#FFAA05', '#3B97FF'];

        return [
            'category_distribution' => [
                'labels' => $pmrGroups,
                'data' => array_map(
                    static fn (string $pmr): int => count(array_filter($items, static fn (array $item): bool => $item['pmr'] === $pmr)),
                    $pmrGroups,
                ),
                'colors' => array_map(
                    static fn (string $pmr): string => (string) ($groupColors[$pmr] ?? '#7366FF'),
                    $pmrGroups,
                ),
            ],
            'hazard_by_item' => [
                'labels' => array_map(
                    static fn (array $item): string => mb_strlen((string) $item['name']) > 24
                        ? mb_substr((string) $item['name'], 0, 24) . '…'
                        : (string) $item['name'],
                    $items,
                ),
                'data' => array_column($items, 'hazard'),
                'colors' => array_map(
                    static fn (int $index): string => $palette[$index % count($palette)],
                    array_keys($items),
                ),
            ],
            'insiden_by_item' => [
                'labels' => array_column($items, 'name'),
                'data' => array_column($items, 'insiden'),
                'colors' => array_fill(0, count($items), '#b91c1c'),
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
                ['' => 'Semua Perusahaan'],
                array_combine($companies, $companies) ?: [],
            ),
        ];
    }
}
