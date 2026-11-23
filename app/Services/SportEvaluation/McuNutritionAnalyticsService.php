<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use App\Repositories\SportEvaluation\McuNutritionRepository;
use Illuminate\Support\Carbon;

/**
 * Agregasi & view-model untuk dashboard MCU x Nutrisi — SEMUA angka di
 * bawah ini dihitung dari data real (`McuNutritionRepository`): MCU
 * Postgres (`bcsid.mv_ftw_mcu`) + BeWell MySQL (`food_analyses`). Tidak
 * ada data dummy/fiktif lagi di class ini (lihat git history untuk versi
 * lama yang masih pakai `mt_rand`).
 *
 * Catatan batasan sumber data (SEMUA dicek langsung ke skema asli,
 * bukan asumsi):
 * - MCU Postgres cuma punya flag normal/abnormal per nama kondisi (JSONB
 *   kondisi_kritis/kondisi_non_kritis) — TIDAK ADA angka BMI/tensi/
 *   kolesterol/LDL/HDL/trigliserida/GDP/skor Framingham mentah. Karena itu
 *   tabel karyawan & detail di bawah pakai BADGE status per kondisi, bukan
 *   angka lab.
 * - Kondisi "Obesitas/BMI" DIHAPUS dari 6 kondisi versi lama — tidak ada
 *   satupun nama_kondisi terkait BMI di seluruh tabel MCU.
 * - food_analyses (BeWell) cuma dipakai untuk total_calories/carbs_g/
 *   protein_g (kolom yang terbukti ada & dipakai NutritionEvaluationService)
 *   — avg_fat_g/avg_fiber_g/avg_sodium_mg versi lama DIHAPUS karena belum
 *   terverifikasi ada sebagai kolom.
 * - Chart yang butuh data historis/snapshot dari waktu ke waktu (heatmap
 *   pola harian, tren bulanan 9 bulan, sparkline KPI) DIHAPUS — sumber data
 *   saat ini cuma punya kondisi TERKINI per karyawan, bukan snapshot
 *   historis, jadi tidak ada cara membuat angka itu secara valid.
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
     * NutritionEvaluationService), bukan standar klinis. Faktor "Lemak
     * Tinggi"/"Serat Rendah" versi lama DIHAPUS — kolom fat_g/fiber_g belum
     * terverifikasi ada di food_analyses.
     */
    private const CARB_OVER_THRESHOLD = 300.0;

    private const PROTEIN_UNDER_THRESHOLD = 52.5;

    /** @var list<string> */
    private const CONDITION_KEYS = ['dislipidemia', 'hipertensi', 'gula_darah', 'sindrom_metabolik', 'framingham'];

    /** @var array<string, string> */
    private const CONDITION_LABELS = [
        'dislipidemia' => 'Dislipidemia',
        'hipertensi' => 'Hipertensi',
        'gula_darah' => 'Gula Darah Tinggi',
        'sindrom_metabolik' => 'Sindrom Metabolik',
        'framingham' => 'Framingham High Risk',
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
        $matched = array_values(array_filter(
            $records,
            static fn (array $r): bool => $r['has_mcu'] && $r['has_nutrition']
        ));

        return [
            'dateRangeLabel' => $rangeStart->translatedFormat('d M Y').' – '.$today->translatedFormat('d M Y'),
            'filterOptions' => $this->filterOptions($records),
            'coverage' => $this->coverageSummary($coverage),
            'kpiTop' => $this->kpiTop($coverage, $records, $matched),
            'kpiConditions' => $this->kpiConditions($records),
            'associationMatrix' => $this->associationMatrix($matched),
            'macroChart' => $this->macroChart($matched),
            'comparisonChart' => $this->comparisonChart($matched),
            'conditionAnalysis' => $this->conditionAnalysis($matched),
            'employeeTabs' => $this->employeeTabs($records),
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
     * @param  list<array<string, mixed>>  $matched
     * @return array<string, array<string, mixed>>
     */
    private function kpiTop(array $coverage, array $records, array $matched): array
    {
        $berisiko = count(array_filter(
            $records,
            static fn (array $r): bool => $r['has_mcu'] && $this->conditionsFor($r) !== []
        ));
        $memenuhiTarget = count(array_filter(
            $matched,
            static fn (array $r): bool => $r['target_calories'] !== null && $r['avg_calories'] <= $r['target_calories'] * 1.15
        ));

        return [
            'total_karyawan' => [
                'label' => 'Total Karyawan (MCU)',
                'icon' => 'solar:users-group-rounded-bold',
                'color' => 'success',
                'value' => $coverage['total_mcu'],
                'caption' => 'Karyawan AKTIF yang punya data MCU',
            ],
            'karyawan_berisiko' => [
                'label' => 'Karyawan Berisiko',
                'icon' => 'solar:heart-pulse-bold',
                'color' => 'danger',
                'value' => $berisiko,
                'sub_pct' => $coverage['total_mcu'] > 0 ? round($berisiko / $coverage['total_mcu'] * 100, 1) : 0.0,
                'caption' => 'Memiliki ≥1 kondisi abnormal terkonfirmasi',
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
     * @return list<array<string, mixed>>
     */
    private function kpiConditions(array $records): array
    {
        $withMcu = array_filter($records, static fn (array $r): bool => $r['has_mcu']);
        $totalMcu = max(1, count($withMcu));

        $meta = [
            'dislipidemia' => ['sub_label' => 'Kolesterol/LDL/Trigliserida', 'icon' => 'solar:test-tube-bold', 'color' => '#2563EB', 'bg' => '#EFF6FF'],
            'hipertensi' => ['sub_label' => 'Tekanan Darah', 'icon' => 'solar:heart-pulse-bold', 'color' => '#DC2626', 'bg' => '#FEF2F2'],
            'gula_darah' => ['sub_label' => 'GDP', 'icon' => 'solar:test-tube-minimalistic-bold', 'color' => '#F4941E', 'bg' => '#FFFBEB'],
            'sindrom_metabolik' => ['sub_label' => 'Diagnosis MCU', 'icon' => 'solar:pulse-bold', 'color' => '#8252E9', 'bg' => '#FAF5FF'],
            'framingham' => ['sub_label' => 'Skor risiko 10 tahun', 'icon' => 'solar:danger-triangle-bold', 'color' => '#DB2777', 'bg' => '#FDF2F8'],
        ];

        $cards = [];
        foreach (self::CONDITION_KEYS as $key) {
            $count = count(array_filter($withMcu, static fn (array $r): bool => $r['conditions'][$key] ?? false));
            $cards[] = [
                'key' => $key,
                'label' => self::CONDITION_LABELS[$key],
                'sub_label' => $meta[$key]['sub_label'],
                'icon' => $meta[$key]['icon'],
                'color' => $meta[$key]['color'],
                'bg' => $meta[$key]['bg'],
                'value' => $count,
                'pct' => round($count / $totalMcu * 100, 1),
            ];
        }

        return $cards;
    }

    /**
     * Keterkaitan deskriptif (proporsi overlap antara kondisi MCU dan pola
     * makan berisiko), BUKAN hasil uji statistik.
     *
     * @param  list<array<string, mixed>>  $matched
     * @return array{rows: list<string>, cols: list<string>, cells: list<list<array<string, mixed>>>}
     */
    private function associationMatrix(array $matched): array
    {
        $cols = ['calorie_over' => 'Kalori Berlebih', 'carb_over' => 'Karbohidrat Tinggi', 'protein_under' => 'Protein Rendah'];

        $cells = [];
        $rows = [];
        foreach (self::CONDITION_KEYS as $key) {
            $group = array_values(array_filter($matched, static fn (array $r): bool => $r['conditions'][$key] ?? false));
            $denominator = count($group);
            $rows[] = self::CONDITION_LABELS[$key];

            $rowCells = [];
            foreach ($cols as $factorKey => $colLabel) {
                $count = count(array_filter($group, fn (array $r): bool => $this->hasFactor($r, $factorKey)));
                $pct = $denominator > 0 ? round($count / $denominator * 100, 1) : 0.0;
                $rowCells[] = [
                    'level' => $this->associationLevel($pct),
                    'condition' => self::CONDITION_LABELS[$key],
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
     * @return array{tabs: array<string, string>, data: array<string, array<string, mixed>>}
     */
    private function macroChart(array $matched): array
    {
        $tabs = ['semua' => 'Semua'] + self::CONDITION_LABELS;
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
     * @return array{calories: array<string, mixed>, macros: array<string, mixed>}
     */
    private function comparisonChart(array $matched): array
    {
        $berisiko = array_values(array_filter($matched, fn (array $r): bool => $this->conditionsFor($r) !== []));
        $tidakBerisiko = array_values(array_filter($matched, fn (array $r): bool => $this->conditionsFor($r) === []));

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
     * @return list<array<string, mixed>>
     */
    private function conditionAnalysis(array $matched): array
    {
        $totalMatched = count($matched);
        $factors = [
            'calorie_over' => ['label' => 'Kalori berlebih', 'color' => '#DC2626', 'icon' => 'mdi:fire'],
            'carb_over' => ['label' => 'Karbohidrat tinggi', 'color' => '#F4941E', 'icon' => 'mdi:rice'],
            'protein_under' => ['label' => 'Protein rendah', 'color' => '#94A3B8', 'icon' => 'mdi:food-drumstick-off'],
        ];

        $cards = [];
        foreach (self::CONDITION_KEYS as $key) {
            $group = array_values(array_filter($matched, static fn (array $r): bool => $r['conditions'][$key] ?? false));
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
                'key' => $key,
                'title' => 'Analisis '.self::CONDITION_LABELS[$key],
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
     * @return array<string, array{label: string, total: int}>
     */
    private function employeeTabs(array $records): array
    {
        $withMcuAndCondition = array_filter($records, static fn (array $r): bool => $r['has_mcu']);
        $tabs = ['semua' => ['label' => 'Semua', 'total' => count(array_filter($withMcuAndCondition, fn (array $r): bool => $this->conditionsFor($r) !== []))]];

        foreach (self::CONDITION_KEYS as $key) {
            $tabs[$key] = [
                'label' => self::CONDITION_LABELS[$key],
                'total' => count(array_filter($withMcuAndCondition, static fn (array $r): bool => $r['conditions'][$key] ?? false)),
            ];
        }

        return $tabs;
    }

    /**
     * Baris tabel karyawan berisiko (punya ≥1 kondisi MCU abnormal
     * terkonfirmasi). Server-side: caller (controller) yang menerapkan
     * search/order/paginasi.
     *
     * @return list<array<string, mixed>>
     */
    public function employeeRows(): array
    {
        $rows = [];
        foreach ($this->repository->records() as $record) {
            if (! $record['has_mcu']) {
                continue;
            }

            $conditions = $this->conditionsFor($record);
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
     * Detail 1 karyawan untuk modal "Detail" — profil MCU x Nutrisi, plus
     * flag data yang belum lengkap. Tidak ada lagi "profile_pairs" (versi
     * lama memasangkan BMI/LDL/GDP numerik vs nutrisi) — sisi MCU sekarang
     * cuma status normal/abnormal per kondisi, tidak ada angka yang bisa
     * dipasangkan.
     *
     * @return array<string, mixed>|null
     */
    public function employeeDetail(int $id): ?array
    {
        foreach ($this->repository->records() as $record) {
            if ($record['id'] !== $id) {
                continue;
            }

            $conditions = $this->conditionsFor($record);
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
                        static fn (string $key): array => [
                            'key' => $key,
                            'label' => self::CONDITION_LABELS[$key],
                            'status' => ($record['conditions'][$key] ?? false) ? 'Abnormal' : 'Normal',
                            'abnormal' => (bool) ($record['conditions'][$key] ?? false),
                        ],
                        self::CONDITION_KEYS
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
     * @return list<string>
     */
    private function conditionsFor(array $record): array
    {
        if (! $record['has_mcu']) {
            return [];
        }

        $out = [];
        foreach (self::CONDITION_KEYS as $key) {
            if ($record['conditions'][$key] ?? false) {
                $out[] = $key;
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
            'risiko_label' => implode(', ', array_map(static fn (string $c): string => self::CONDITION_LABELS[$c] ?? $c, $conditions)),
            'status_nutrisi' => $statusNutrisi,
            'has_nutrition' => $record['has_nutrition'],
        ];
    }
}
