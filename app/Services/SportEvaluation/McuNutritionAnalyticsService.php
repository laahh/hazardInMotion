<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use App\Repositories\SportEvaluation\McuNutritionRepository;
use Illuminate\Support\Carbon;

/**
 * Agregasi & view-model untuk dashboard MCU x Nutrisi — SEMUA angka di
 * bawah ini dihitung dari data real (`McuNutritionRepository`): riwayat
 * penyakit kritis TERKONFIRMASI ClickHouse (`behealth.
 * clean_data_konfirmasi_penyakit_kritis`) + BeWell MySQL (`food_analyses`).
 * Tidak ada data dummy/fiktif lagi di class ini (lihat git history untuk
 * versi lama yang pakai `mt_rand`, dan versi sebelum itu lagi yang coba
 * pakai MCU Postgres — dibatalkan karena kredensial `pgsql_ssh` di server
 * produksi tidak valid, atas arahan user diganti ke sumber ClickHouse yang
 * sudah terbukti jalan di tab "Riwayat Penyakit Kritis").
 *
 * Kategori "kondisi kesehatan" TIDAK di-hardcode — diambil dinamis dari
 * `McuNutritionRepository::conditionLabels()` (6 label penyakit
 * terkonfirmasi paling sering, apa adanya dari ClickHouse, tanpa
 * taksonomi buatan sendiri).
 *
 * Semua rasio kondisi x nutrisi memakai matched records (has_mcu &&
 * has_nutrition) sebagai denominator — bukan total_mcu atau total_nutrition
 * — supaya perbandingannya valid.
 */
final class McuNutritionAnalyticsService
{
    private const TARGET_CALORIES = 2300.0;

    /**
     * Ambang "berisiko" untuk 3 faktor pola makan di bawah ini BELUM punya
     * band resmi dari tim medis/gizi — ini pilihan pragmatis (konsisten
     * dengan DEFAULT_CARB_TARGET/DEFAULT_PROTEIN_TARGET di
     * NutritionEvaluationService), bukan standar klinis.
     */
    private const CARB_OVER_THRESHOLD = 300.0;

    private const PROTEIN_UNDER_THRESHOLD = 52.5;

    /** @var list<string> palet warna digilir per kondisi (dinamis, bukan per-nama) */
    private const PALETTE = [
        ['color' => '#2563EB', 'bg' => '#EFF6FF', 'icon' => 'solar:test-tube-bold'],
        ['color' => '#DC2626', 'bg' => '#FEF2F2', 'icon' => 'solar:heart-pulse-bold'],
        ['color' => '#F4941E', 'bg' => '#FFFBEB', 'icon' => 'solar:test-tube-minimalistic-bold'],
        ['color' => '#8252E9', 'bg' => '#FAF5FF', 'icon' => 'solar:pulse-bold'],
        ['color' => '#DB2777', 'bg' => '#FDF2F8', 'icon' => 'solar:danger-triangle-bold'],
        ['color' => '#16A34A', 'bg' => '#ECFDF5', 'icon' => 'solar:shield-cross-bold'],
    ];

    public function __construct(
        private readonly McuNutritionRepository $repository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $today = Carbon::now();
        $rangeStart = $today->copy()->subDays(24);
        $records = $this->repository->records();
        $coverage = $this->repository->coverage();
        $conditionLabels = $this->repository->conditionLabels();
        $matched = array_values(array_filter(
            $records,
            static fn (array $r): bool => $r['has_mcu'] && $r['has_nutrition']
        ));

        return [
            'dateRangeLabel' => $rangeStart->translatedFormat('d M Y').' – '.$today->translatedFormat('d M Y'),
            'filterOptions' => $this->filterOptions($records),
            'coverage' => $this->coverageSummary($coverage),
            'kpiTop' => $this->kpiTop($coverage, $records, $conditionLabels, $matched),
            'kpiConditions' => $this->kpiConditions($records, $conditionLabels),
            'associationMatrix' => $this->associationMatrix($matched, $conditionLabels),
            'macroChart' => $this->macroChart($matched, $conditionLabels),
            'comparisonChart' => $this->comparisonChart($matched, $conditionLabels),
            'conditionAnalysis' => $this->conditionAnalysis($matched, $conditionLabels),
            'employeeTabs' => $this->employeeTabs($records, $conditionLabels),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array{sites: list<string>, companies: list<string>}
     */
    private function filterOptions(array $records): array
    {
        return [
            'sites' => collect($records)->pluck('site')->filter(static fn ($v): bool => $v !== '-')->unique()->sort()->values()->all(),
            'companies' => collect($records)->pluck('perusahaan')->filter(static fn ($v): bool => $v !== '-')->unique()->sort()->values()->all(),
        ];
    }

    /**
     * @param  array{total_mcu: int, total_nutrition: int, matched_total: int}  $coverage
     * @return array<string, mixed>
     */
    private function coverageSummary(array $coverage): array
    {
        $matchedPct = round($coverage['matched_total'] / max(1, $coverage['total_mcu']) * 100, 1);
        $isGoodQuality = $matchedPct >= 80.0;

        return [
            'total_mcu' => $coverage['total_mcu'],
            'total_nutrition' => $coverage['total_nutrition'],
            'matched_total' => $coverage['matched_total'],
            'matched_pct_of_mcu' => $matchedPct,
            'quality_level' => $isGoodQuality ? 'good' : 'warning',
            'quality_title' => $isGoodQuality
                ? 'Data lengkap, analisis lebih akurat'
                : 'Data nutrisi belum lengkap',
            'quality_message' => $isGoodQuality
                ? 'Dengan lebih banyak data nutrisi, rekomendasi lebih tepat sasaran.'
                : 'Lengkapi pencatatan nutrisi karyawan agar hasil analisis lebih akurat.',
        ];
    }

    /**
     * @param  array{total_mcu: int, total_nutrition: int, matched_total: int}  $coverage
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $conditionLabels
     * @param  list<array<string, mixed>>  $matched
     * @return array<string, array<string, mixed>>
     */
    private function kpiTop(array $coverage, array $records, array $conditionLabels, array $matched): array
    {
        $berisiko = count(array_filter(
            $records,
            fn (array $r): bool => $r['has_mcu'] && $this->conditionsFor($r, $conditionLabels) !== []
        ));
        $memenuhiTarget = count(array_filter(
            $matched,
            static fn (array $r): bool => $r['target_calories'] !== null && $r['avg_calories'] <= $r['target_calories'] * 1.15
        ));

        return [
            'total_karyawan' => [
                'label' => 'Total Karyawan (AKTIF)',
                'icon' => 'solar:users-group-rounded-bold',
                'color' => 'success',
                'value' => $coverage['total_mcu'],
                'caption' => 'Punya riwayat penyakit kritis terkonfirmasi',
            ],
            'karyawan_berisiko' => [
                'label' => 'Karyawan Berisiko',
                'icon' => 'solar:heart-pulse-bold',
                'color' => 'danger',
                'value' => $berisiko,
                'sub_pct' => $coverage['total_mcu'] > 0 ? round($berisiko / $coverage['total_mcu'] * 100, 1) : 0.0,
                'caption' => 'Memiliki ≥1 penyakit kritis terkonfirmasi',
            ],
            'target_kalori' => [
                'label' => 'Memenuhi Target Kalori',
                'icon' => 'solar:donut-bitten-bold',
                'color' => 'success',
                'value' => $memenuhiTarget,
                'sub_pct' => $coverage['matched_total'] > 0 ? round($memenuhiTarget / $coverage['matched_total'] * 100, 1) : 0.0,
                'caption' => 'Dari matched records, ≤115% target kalori harian',
            ],
            'data_nutrisi' => [
                'label' => 'Data Nutrisi Tercatat',
                'icon' => 'solar:clipboard-list-bold',
                'color' => 'info',
                'value' => $coverage['total_nutrition'],
                'sub_pct' => $coverage['total_mcu'] > 0 ? round($coverage['total_nutrition'] / $coverage['total_mcu'] * 100, 1) : 0.0,
                'caption' => 'Punya minimal 1 hari log makan (30 hari terakhir)',
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $conditionLabels
     * @return list<array<string, mixed>>
     */
    private function kpiConditions(array $records, array $conditionLabels): array
    {
        $withMcu = array_filter($records, static fn (array $r): bool => $r['has_mcu']);
        $totalMcu = max(1, count($withMcu));

        $cards = [];
        foreach ($conditionLabels as $i => $label) {
            $palette = self::PALETTE[$i % count(self::PALETTE)];
            $count = count(array_filter($withMcu, static fn (array $r): bool => $r['conditions'][$label] ?? false));
            $cards[] = [
                'key' => $label,
                'label' => $label,
                'sub_label' => 'Penyakit kritis terkonfirmasi',
                'icon' => $palette['icon'],
                'color' => $palette['color'],
                'bg' => $palette['bg'],
                'value' => $count,
                'pct' => round($count / $totalMcu * 100, 1),
            ];
        }

        return $cards;
    }

    /**
     * Keterkaitan deskriptif (proporsi overlap antara kondisi terkonfirmasi
     * dan pola makan berisiko), BUKAN hasil uji statistik.
     *
     * @param  list<array<string, mixed>>  $matched
     * @param  list<string>  $conditionLabels
     * @return array{rows: list<string>, cols: list<string>, cells: list<list<array<string, mixed>>>}
     */
    private function associationMatrix(array $matched, array $conditionLabels): array
    {
        $cols = ['calorie_over' => 'Kalori Berlebih', 'carb_over' => 'Karbohidrat Tinggi', 'protein_under' => 'Protein Rendah'];

        $cells = [];
        $rows = [];
        foreach ($conditionLabels as $label) {
            $group = array_values(array_filter($matched, static fn (array $r): bool => $r['conditions'][$label] ?? false));
            $denominator = count($group);
            $rows[] = $label;

            $rowCells = [];
            foreach ($cols as $factorKey => $colLabel) {
                $count = count(array_filter($group, fn (array $r): bool => $this->hasFactor($r, $factorKey)));
                $pct = $denominator > 0 ? round($count / $denominator * 100, 1) : 0.0;
                $rowCells[] = [
                    'level' => $this->associationLevel($pct),
                    'condition' => $label,
                    'factor' => $colLabel,
                    'pct' => $pct,
                    'count' => $count,
                    'denominator' => $denominator,
                ];
            }
            $cells[] = $rowCells;
        }

        return ['rows' => $rows, 'cols' => array_values($cols), 'cells' => $cells];
    }

    private function associationLevel(float $pct): int
    {
        return match (true) {
            $pct >= 65.0 => 4,
            $pct >= 50.0 => 3,
            $pct >= 35.0 => 2,
            $pct >= 20.0 => 1,
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function hasFactor(array $record, string $factorKey): bool
    {
        $avgCalories = (float) ($record['avg_calories'] ?? 0);
        $targetCalories = (float) ($record['target_calories'] ?? self::TARGET_CALORIES);
        $avgCarbs = (float) ($record['avg_carbs_g'] ?? 0);
        $avgProtein = (float) ($record['avg_protein_g'] ?? 0);

        return match ($factorKey) {
            'calorie_over' => $targetCalories > 0 && $avgCalories >= $targetCalories * 1.15,
            'carb_over' => $avgCarbs >= self::CARB_OVER_THRESHOLD,
            'protein_under' => $avgProtein > 0 && $avgProtein <= self::PROTEIN_UNDER_THRESHOLD,
            default => false,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $matched
     * @param  list<string>  $conditionLabels
     * @return array{tabs: array<string, string>, data: array<string, array<string, mixed>>}
     */
    private function macroChart(array $matched, array $conditionLabels): array
    {
        $tabs = ['semua' => 'Semua'];
        foreach ($conditionLabels as $label) {
            $tabs[$label] = $label;
        }
        $categories = ['Karbohidrat', 'Protein'];

        $data = [];
        foreach ($tabs as $key => $label) {
            $group = $key === 'semua'
                ? $matched
                : array_values(array_filter($matched, static fn (array $r): bool => $r['conditions'][$key] ?? false));

            $berisiko = array_values(array_filter($group, fn (array $r): bool => $this->hasFactor($r, 'carb_over') || $this->hasFactor($r, 'calorie_over')));
            $tidakBerisiko = array_values(array_filter($group, fn (array $r): bool => ! ($this->hasFactor($r, 'carb_over') || $this->hasFactor($r, 'calorie_over'))));

            $data[$key] = [
                'categories' => $categories,
                'berisiko' => [
                    round($this->avgOf($berisiko, 'avg_carbs_g'), 1),
                    round($this->avgOf($berisiko, 'avg_protein_g'), 1),
                ],
                'tidak_berisiko' => [
                    round($this->avgOf($tidakBerisiko, 'avg_carbs_g'), 1),
                    round($this->avgOf($tidakBerisiko, 'avg_protein_g'), 1),
                ],
            ];
        }

        return ['tabs' => $tabs, 'data' => $data];
    }

    /**
     * @param  list<array<string, mixed>>  $matched
     * @param  list<string>  $conditionLabels
     * @return array{calories: array<string, mixed>, macros: array<string, mixed>}
     */
    private function comparisonChart(array $matched, array $conditionLabels): array
    {
        $berisiko = array_values(array_filter($matched, fn (array $r): bool => $this->conditionsFor($r, $conditionLabels) !== []));
        $tidakBerisiko = array_values(array_filter($matched, fn (array $r): bool => $this->conditionsFor($r, $conditionLabels) === []));

        return [
            'calories' => [
                'categories' => ['Kalori (kkal)'],
                'berisiko' => [round($this->avgOf($berisiko, 'avg_calories'), 0)],
                'tidak_berisiko' => [round($this->avgOf($tidakBerisiko, 'avg_calories'), 0)],
            ],
            'macros' => [
                'categories' => ['Karbohidrat (g)', 'Protein (g)'],
                'berisiko' => [
                    round($this->avgOf($berisiko, 'avg_carbs_g'), 0),
                    round($this->avgOf($berisiko, 'avg_protein_g'), 0),
                ],
                'tidak_berisiko' => [
                    round($this->avgOf($tidakBerisiko, 'avg_carbs_g'), 0),
                    round($this->avgOf($tidakBerisiko, 'avg_protein_g'), 0),
                ],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $group
     */
    private function avgOf(array $group, string $field): float
    {
        if ($group === []) {
            return 0.0;
        }

        $sum = array_sum(array_map(static fn (array $r): float => (float) ($r[$field] ?? 0), $group));

        return $sum / count($group);
    }

    /**
     * @param  list<array<string, mixed>>  $matched
     * @param  list<string>  $conditionLabels
     * @return list<array<string, mixed>>
     */
    private function conditionAnalysis(array $matched, array $conditionLabels): array
    {
        $totalMatched = count($matched);
        $factors = [
            'calorie_over' => ['label' => 'Kalori berlebih', 'color' => '#DC2626', 'icon' => 'mdi:fire'],
            'carb_over' => ['label' => 'Karbohidrat tinggi', 'color' => '#F4941E', 'icon' => 'mdi:rice'],
            'protein_under' => ['label' => 'Protein rendah', 'color' => '#94A3B8', 'icon' => 'mdi:food-drumstick-off'],
        ];

        $cards = [];
        foreach ($conditionLabels as $label) {
            $group = array_values(array_filter($matched, static fn (array $r): bool => $r['conditions'][$label] ?? false));
            $denominator = count($group);

            $legend = [];
            $topLabel = null;
            $topPct = -1.0;
            foreach ($factors as $factorKey => $factorMeta) {
                $count = count(array_filter($group, fn (array $r): bool => $this->hasFactor($r, $factorKey)));
                $pct = $denominator > 0 ? round($count / $denominator * 100, 1) : 0.0;
                $legend[] = [
                    'label' => $factorMeta['label'],
                    'pct' => $pct,
                    'count' => $count,
                    'color' => $factorMeta['color'],
                    'icon' => $factorMeta['icon'],
                    'available' => true,
                ];
                if ($pct > $topPct) {
                    $topPct = $pct;
                    $topLabel = $factorMeta['label'];
                }
            }

            $insight = $denominator > 0 && $topLabel !== null
                ? number_format($topPct, 1).'% dari karyawan dengan kondisi ini memiliki pola makan "'.mb_strtolower($topLabel).'" (data 30 hari terakhir).'
                : 'Belum cukup data pola makan (matched) untuk kondisi ini.';

            $cards[] = [
                'key' => $label,
                'title' => 'Analisis '.$label,
                'total' => $denominator,
                'pct' => $totalMatched > 0 ? round($denominator / $totalMatched * 100, 1) : 0.0,
                'legend' => $legend,
                'insight' => $insight,
            ];
        }

        return $cards;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $conditionLabels
     * @return array<string, array{label: string, total: int}>
     */
    private function employeeTabs(array $records, array $conditionLabels): array
    {
        $withMcu = array_filter($records, static fn (array $r): bool => $r['has_mcu']);
        $tabs = ['semua' => ['label' => 'Semua', 'total' => count(array_filter($withMcu, fn (array $r): bool => $this->conditionsFor($r, $conditionLabels) !== []))]];

        foreach ($conditionLabels as $label) {
            $tabs[$label] = [
                'label' => $label,
                'total' => count(array_filter($withMcu, static fn (array $r): bool => $r['conditions'][$label] ?? false)),
            ];
        }

        return $tabs;
    }

    /**
     * Baris tabel karyawan berisiko (punya ≥1 penyakit kritis terkonfirmasi
     * termasuk dalam 6 kondisi teratas). Server-side: caller (controller)
     * yang menerapkan search/order/paginasi.
     *
     * @return list<array<string, mixed>>
     */
    public function employeeRows(): array
    {
        $conditionLabels = $this->repository->conditionLabels();
        $rows = [];
        foreach ($this->repository->records() as $record) {
            if (! $record['has_mcu']) {
                continue;
            }

            $conditions = $this->conditionsFor($record, $conditionLabels);
            if ($conditions === []) {
                continue;
            }

            $rows[] = $this->toEmployeeRow($record, $conditions);
        }

        usort($rows, static fn (array $a, array $b): int => count($b['conditions']) <=> count($a['conditions']));

        foreach ($rows as $i => $row) {
            $rows[$i]['no'] = $i + 1;
        }

        return $rows;
    }

    /**
     * Detail 1 karyawan untuk modal "Detail" — profil kondisi x nutrisi,
     * plus flag data yang belum lengkap.
     *
     * @return array<string, mixed>|null
     */
    public function employeeDetail(int $id): ?array
    {
        $conditionLabels = $this->repository->conditionLabels();

        foreach ($this->repository->records() as $record) {
            if ($record['id'] !== $id) {
                continue;
            }

            $conditions = $this->conditionsFor($record, $conditionLabels);
            $caloriesVsTargetPct = ($record['avg_calories'] !== null && $record['target_calories'] !== null && $record['target_calories'] > 0)
                ? round((($record['avg_calories'] - $record['target_calories']) / $record['target_calories']) * 100, 0)
                : null;

            return [
                'profile' => [
                    'nama' => $record['nama'],
                    'nik' => $record['nik'],
                    'perusahaan' => $record['perusahaan'],
                    'site' => $record['site'],
                    'departemen' => $record['departemen'],
                ],
                'mcu' => $record['has_mcu'] ? [
                    'exam_date' => $record['exam_date'],
                    'conditions' => array_map(
                        static fn (string $label): array => [
                            'label' => $label,
                            'status' => ($record['conditions'][$label] ?? false) ? 'Terkonfirmasi' : 'Tidak Ada',
                            'abnormal' => (bool) ($record['conditions'][$label] ?? false),
                        ],
                        $conditionLabels
                    ),
                ] : null,
                'nutrition' => $record['has_nutrition'] ? [
                    'avg_calories' => $record['avg_calories'],
                    'target_calories' => $record['target_calories'],
                    'calories_vs_target_pct' => $caloriesVsTargetPct,
                    'avg_carbs_g' => $record['avg_carbs_g'],
                    'avg_protein_g' => $record['avg_protein_g'],
                    'logging_days_30d' => $record['logging_days_30d'],
                ] : null,
                'conditions' => $conditions,
                'data_completeness' => [
                    'has_mcu' => $record['has_mcu'],
                    'has_nutrition' => $record['has_nutrition'],
                    'matched' => $record['has_mcu'] && $record['has_nutrition'],
                ],
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<string>  $conditionLabels
     * @return list<string>
     */
    private function conditionsFor(array $record, array $conditionLabels): array
    {
        if (! $record['has_mcu']) {
            return [];
        }

        $out = [];
        foreach ($conditionLabels as $label) {
            if ($record['conditions'][$label] ?? false) {
                $out[] = $label;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<string>  $conditions
     * @return array<string, mixed>
     */
    private function toEmployeeRow(array $record, array $conditions): array
    {
        $statusNutrisi = 'Data belum tersedia';
        if ($record['has_nutrition'] && $record['avg_calories'] !== null && $record['target_calories'] !== null) {
            $ratio = $record['avg_calories'] / $record['target_calories'];
            $statusNutrisi = $ratio > 1.15 ? 'Perlu Intervensi' : ($ratio < 0.85 ? 'Pantau' : 'Baik');
        }

        return [
            'id' => $record['id'],
            'nama' => $record['nama'],
            'nik' => $record['nik'],
            'perusahaan' => $record['perusahaan'],
            'site' => $record['site'],
            'departemen' => $record['departemen'],
            'conditions' => $conditions,
            'risiko_label' => implode(', ', $conditions),
            'status_nutrisi' => $statusNutrisi,
            'has_nutrition' => $record['has_nutrition'],
        ];
    }
}
