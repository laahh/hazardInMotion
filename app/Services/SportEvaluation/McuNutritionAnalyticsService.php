<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use App\Repositories\SportEvaluation\McuNutritionRepository;
use Illuminate\Support\Carbon;

/**
 * Agregasi & view-model untuk dashboard MCU x Nutrisi. Controller tidak boleh
 * melakukan kalkulasi sendiri — semua angka yang ditampilkan Blade datang
 * dari sini, dibentuk dari McuNutritionRepository (saat ini dummy, lihat
 * catatan di repository).
 *
 * Semua rasio kondisi x nutrisi memakai matched records (has_mcu &&
 * has_nutrition) sebagai denominator — bukan total_mcu atau total_nutrition
 * — supaya perbandingannya valid.
 */
final class McuNutritionAnalyticsService
{
    private const TARGET_CALORIES = 2300.0;

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
        $coverage = $this->repository->coverage();

        return [
            'dummyMode' => true,
            'dateRangeLabel' => $rangeStart->translatedFormat('d M Y').' – '.$today->translatedFormat('d M Y'),
            'filterOptions' => [
                'sites' => ['BMO 1', 'BMO 2', 'BMO 3', 'GMO', 'LMO', 'SMO'],
                'companies' => ['PT ABC', 'PT XYZ', 'PT PAMA', 'PT BUMA', 'PT BAR', 'PT DNX Indonesia'],
            ],
            'coverage' => $this->coverageSummary($coverage),
            'kpiTop' => $this->kpiTop($coverage),
            'kpiConditions' => $this->kpiConditions(),
            'heatmap' => $this->heatmap($rangeStart, $today),
            'compliance' => $this->compliance(),
            'associationMatrix' => $this->associationMatrix(),
            'macroChart' => $this->macroChart(),
            'comparisonChart' => $this->comparisonChart(),
            'conditionAnalysis' => $this->conditionAnalysis(),
            'trendChart' => $this->trendChart(),
            'employeeTabs' => $this->employeeTabs(),
        ];
    }

    /**
     * @param  array{total_mcu: int, total_nutrition: int, matched_total: int}  $coverage
     * @return array<string, mixed>
     */
    private function coverageSummary(array $coverage): array
    {
        return [
            'total_mcu' => $coverage['total_mcu'],
            'total_nutrition' => $coverage['total_nutrition'],
            'matched_total' => $coverage['matched_total'],
            'matched_pct_of_mcu' => round($coverage['matched_total'] / max(1, $coverage['total_mcu']) * 100, 1),
        ];
    }

    /**
     * @param  array{total_mcu: int, total_nutrition: int, matched_total: int}  $coverage
     * @return array<string, array<string, mixed>>
     */
    private function kpiTop(array $coverage): array
    {
        return [
            'total_karyawan' => [
                'label' => 'Total Karyawan (MCU)',
                'icon' => 'solar:users-group-rounded-bold',
                'color' => 'success',
                'value' => $coverage['total_mcu'],
                'sub_label' => 'dari periode sebelumnya',
                'delta_pct' => 5.2,
                'sparkline' => [18400, 18600, 18750, 18900, 19050, 19180, $coverage['total_mcu']],
            ],
            'karyawan_berisiko' => [
                'label' => 'Karyawan Berisiko',
                'icon' => 'solar:heart-pulse-bold',
                'color' => 'danger',
                'value' => 3482,
                'sub_label' => 'dari total MCU',
                'sub_pct' => round(3482 / $coverage['total_mcu'] * 100, 1),
                'delta_pct' => 2.1,
                'sparkline' => [3120, 3210, 3260, 3300, 3350, 3410, 3482],
            ],
            'target_kalori' => [
                'label' => 'Memenuhi Target Kalori',
                'icon' => 'solar:donut-bitten-bold',
                'color' => 'success',
                'value' => 14216,
                'sub_label' => 'dari matched records',
                'sub_pct' => round(14216 / $coverage['matched_total'] * 100, 1),
                'delta_pct' => 4.5,
                'sparkline' => [12800, 13100, 13400, 13650, 13900, 14050, 14216],
            ],
            'data_nutrisi' => [
                'label' => 'Data Nutrisi Tercatat',
                'icon' => 'solar:clipboard-list-bold',
                'color' => 'info',
                'value' => $coverage['total_nutrition'],
                'sub_label' => 'coverage dari total MCU',
                'sub_pct' => round($coverage['total_nutrition'] / $coverage['total_mcu'] * 100, 1),
                'delta_pct' => 3.8,
                'sparkline' => [15900, 16200, 16500, 16750, 16950, 17100, $coverage['total_nutrition']],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function kpiConditions(): array
    {
        return [
            ['key' => 'obesitas', 'label' => 'Obesitas', 'sub_label' => 'BMI ≥30', 'icon' => 'solar:scale-bold', 'color' => '#F86624', 'value' => 1482, 'pct' => 7.6, 'delta_pct' => 1.4],
            ['key' => 'dislipidemia', 'label' => 'Dislipidemia', 'sub_label' => 'Kol/LDL/TG tinggi', 'icon' => 'solar:test-tube-bold', 'color' => '#2563EB', 'value' => 1126, 'pct' => 5.8, 'delta_pct' => 0.9],
            ['key' => 'hipertensi', 'label' => 'Hipertensi', 'sub_label' => 'Tekanan Darah', 'icon' => 'solar:heart-pulse-bold', 'color' => '#DC2626', 'value' => 1904, 'pct' => 9.8, 'delta_pct' => 2.6],
            ['key' => 'gula_darah', 'label' => 'Gula Darah Tinggi', 'sub_label' => 'GDP', 'icon' => 'solar:test-tube-minimalistic-bold', 'color' => '#F4941E', 'value' => 962, 'pct' => 5.0, 'delta_pct' => 1.1],
            ['key' => 'sindrom_metabolik', 'label' => 'Sindrom Metabolik', 'sub_label' => '≥3 komponen', 'icon' => 'solar:pulse-bold', 'color' => '#8252E9', 'value' => 684, 'pct' => 3.5, 'delta_pct' => 0.8],
            ['key' => 'framingham', 'label' => 'Framingham High Risk', 'sub_label' => 'Skor risiko 10 tahun', 'icon' => 'solar:danger-triangle-bold', 'color' => '#7F27FF', 'value' => 421, 'pct' => 2.2, 'delta_pct' => 0.6],
        ];
    }

    /**
     * @return array{categories: list<string>, days: list<string>, series: list<array<string, mixed>>, stats: array<string, mixed>}
     */
    private function heatmap(Carbon $start, Carbon $end): array
    {
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        $byDow = array_fill(0, 7, []);
        $labels = [];
        $dailyTotals = [];
        $weekdaySum = 0;
        $weekdayDays = 0;
        $weekendSum = 0;
        $weekendDays = 0;
        $peakLabel = '-';
        $peakValue = -1;

        $cursor = $start->copy();
        $i = 0;
        while ($cursor->lte($end)) {
            $label = $cursor->format('d M');
            $labels[] = $label;
            $dow = $cursor->dayOfWeekIso - 1;
            $isWeekday = $cursor->dayOfWeekIso <= 5;
            $weekProgress = $i / 24;
            $weekdayBoost = $isWeekday ? 1.25 : 0.7;
            $base = 60 + ($weekProgress * 260);
            $wave = sin($i / 3) * 60;
            $value = max(0, (int) round(($base + $wave) * $weekdayBoost));

            foreach ($byDow as $d => $row) {
                $byDow[$d][] = $d === $dow ? $value : null;
            }

            $dailyTotals[] = $value;
            if ($isWeekday) {
                $weekdaySum += $value;
                $weekdayDays++;
            } else {
                $weekendSum += $value;
                $weekendDays++;
            }
            if ($value > $peakValue) {
                $peakValue = $value;
                $peakLabel = $cursor->translatedFormat('d M Y');
            }

            $i++;
            $cursor->addDay();
        }

        $series = [];
        foreach ($days as $index => $dayLabel) {
            $data = [];
            foreach ($labels as $colIndex => $label) {
                $data[] = ['x' => $label, 'y' => $byDow[$index][$colIndex]];
            }
            $series[] = ['name' => $dayLabel, 'data' => $data];
        }

        $avgDaily = $dailyTotals === [] ? 0 : (int) round(array_sum($dailyTotals) / count($dailyTotals));
        $weekdayAvg = $weekdayDays > 0 ? $weekdaySum / $weekdayDays : 0.0;
        $weekendAvg = $weekendDays > 0 ? $weekendSum / $weekendDays : 0.0;
        $ratio = $weekendAvg > 0 ? round($weekdayAvg / $weekendAvg, 1) : 0.0;

        return [
            'categories' => $labels,
            'days' => $days,
            'series' => $series,
            'stats' => [
                'peak_day_label' => $peakLabel,
                'peak_day_count' => max(0, $peakValue),
                'avg_daily' => $avgDaily,
                'weekday_ratio' => $ratio,
                'peak_hour_label' => '08:00 – 10:00',
            ],
        ];
    }

    /**
     * @return array{center_value: int, center_label: string, legend: list<array<string, mixed>>}
     */
    private function compliance(): array
    {
        return [
            'center_value' => 17289,
            'center_label' => 'Karyawan',
            'legend' => [
                ['label' => 'Rutin (≥5 hari/minggu)', 'pct' => 62.4, 'count' => 12107, 'color' => '#16A34A'],
                ['label' => 'Cukup (3-4 hari/minggu)', 'pct' => 18.7, 'count' => 3624, 'color' => '#F4941E'],
                ['label' => 'Jarang (1-2 hari/minggu)', 'pct' => 8.5, 'count' => 1651, 'color' => '#2563EB'],
                ['label' => 'Tidak pernah', 'pct' => 10.4, 'count' => 2019, 'color' => '#DC2626'],
            ],
        ];
    }

    /**
     * Keterkaitan deskriptif (proporsi overlap), BUKAN hasil uji statistik —
     * lihat label & tooltip di Blade yang menegaskan ini "Association /
     * Keterkaitan Deskriptif".
     *
     * @return array{rows: list<string>, cols: list<string>, cells: list<list<array<string, mixed>>>}
     */
    private function associationMatrix(): array
    {
        $rows = ['Obesitas (BMI≥30)', 'Dislipidemia', 'Hipertensi', 'Gula Darah Tinggi', 'Sindrom Metabolik', 'Framingham High Risk'];
        $cols = ['Kalori Berlebih', 'Karbohidrat Tinggi', 'Lemak Tinggi', 'Protein Rendah', 'Serat Rendah'];
        $rowDenominators = [1482, 1126, 1904, 962, 684, 421];

        // Level 0-4: 0=Tidak Signifikan, 1=Rendah, 2=Sedang, 3=Tinggi, 4=Sangat Tinggi.
        $levels = [
            [4, 3, 4, 2, 3],
            [3, 3, 4, 2, 3],
            [3, 2, 3, 1, 3],
            [3, 4, 2, 1, 2],
            [4, 4, 3, 2, 4],
            [4, 3, 4, 2, 3],
        ];
        // Perkiraan % overlap per level, dipakai untuk hitung jumlah karyawan pada tooltip.
        $levelPct = [0 => 8.0, 1 => 20.0, 2 => 35.0, 3 => 50.0, 4 => 65.0];

        $cells = [];
        foreach ($rows as $ri => $rowLabel) {
            $rowCells = [];
            foreach ($cols as $ci => $colLabel) {
                $level = $levels[$ri][$ci];
                $denominator = $rowDenominators[$ri];
                $pct = $levelPct[$level];
                $rowCells[] = [
                    'level' => $level,
                    'condition' => $rowLabel,
                    'factor' => $colLabel,
                    'pct' => $pct,
                    'count' => (int) round($denominator * $pct / 100),
                    'denominator' => $denominator,
                ];
            }
            $cells[] = $rowCells;
        }

        return ['rows' => $rows, 'cols' => $cols, 'cells' => $cells];
    }

    /**
     * @return array{tabs: array<string, string>, data: array<string, array<string, mixed>>}
     */
    private function macroChart(): array
    {
        $tabs = [
            'semua' => 'Semua',
            'obesitas' => 'Obesitas',
            'dislipidemia' => 'Dislipidemia',
            'hipertensi' => 'Hipertensi',
            'gula_darah' => 'Gula Darah',
            'sindrom_metabolik' => 'Sindrom Metabolik',
        ];
        $categories = ['Karbohidrat', 'Lemak', 'Protein', 'Serat'];
        $base = [
            'semua' => ['berisiko' => [28.4, 32.5, 12.1, 8.7], 'tidak_berisiko' => [12.1, 14.6, 8.4, 22.3]],
            'obesitas' => ['berisiko' => [31.2, 38.6, 14.0, 9.4], 'tidak_berisiko' => [13.5, 15.9, 9.1, 24.0]],
            'dislipidemia' => ['berisiko' => [26.8, 41.2, 11.5, 7.9], 'tidak_berisiko' => [12.8, 16.4, 8.0, 20.7]],
            'hipertensi' => ['berisiko' => [24.5, 27.1, 13.2, 10.1], 'tidak_berisiko' => [11.6, 13.2, 8.9, 23.4]],
            'gula_darah' => ['berisiko' => [36.9, 22.4, 10.8, 8.2], 'tidak_berisiko' => [14.2, 12.5, 7.6, 21.5]],
            'sindrom_metabolik' => ['berisiko' => [34.1, 36.4, 12.9, 6.5], 'tidak_berisiko' => [13.0, 15.1, 8.2, 19.8]],
        ];

        $data = [];
        foreach ($base as $key => $series) {
            $data[$key] = array_merge(['categories' => $categories], $series);
        }

        return ['tabs' => $tabs, 'data' => $data];
    }

    /**
     * Dipisah jadi dua sumbu/unit (kkal vs gram) — jangan gabung ke satu
     * axis, skalanya jauh berbeda dan bisa menyesatkan.
     *
     * @return array{calories: array<string, mixed>, macros: array<string, mixed>}
     */
    private function comparisonChart(): array
    {
        return [
            'calories' => [
                'categories' => ['Kalori (kkal)'],
                'berisiko' => [2850],
                'tidak_berisiko' => [2120],
            ],
            'macros' => [
                'categories' => ['Karbohidrat (g)', 'Lemak (g)', 'Protein (g)', 'Serat (g)'],
                'berisiko' => [420, 130, 65, 14],
                'tidak_berisiko' => [310, 95, 72, 22],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conditionAnalysis(): array
    {
        return [
            [
                'key' => 'obesitas',
                'title' => 'Analisis Obesitas (BMI≥30)',
                'total' => 1482,
                'pct' => 7.6,
                'legend' => [
                    ['label' => 'Melebihi target kalori', 'pct' => 38.2, 'count' => 566, 'color' => '#DC2626', 'icon' => 'mdi:arrow-up-bold', 'available' => true],
                    ['label' => '<50% target kalori', 'pct' => 22.1, 'count' => 327, 'color' => '#F86624', 'icon' => 'mdi:arrow-down-bold', 'available' => true],
                    ['label' => 'Tidak ada data kalori', 'pct' => 39.7, 'count' => 589, 'color' => '#94A3B8', 'icon' => 'mdi:help-circle', 'available' => true],
                ],
                'insight' => '62,3% kelompok obesitas ditemukan memiliki pola asupan kalori di atas target harian.',
            ],
            [
                'key' => 'dislipidemia',
                'title' => 'Analisis Dislipidemia',
                'total' => 1126,
                'pct' => 5.8,
                'legend' => [
                    ['label' => 'Kalori berlebih', 'pct' => 34.7, 'color' => '#DC2626', 'icon' => 'mdi:fire', 'available' => true],
                    ['label' => 'Lemak tinggi', 'pct' => 41.2, 'color' => '#F86624', 'icon' => 'mdi:oil', 'available' => true],
                    ['label' => 'Karbohidrat tinggi', 'pct' => 28.1, 'color' => '#F4941E', 'icon' => 'mdi:rice', 'available' => true],
                    ['label' => 'Serat rendah', 'pct' => 36.5, 'color' => '#94A3B8', 'icon' => 'mdi:leaf-off', 'available' => true],
                ],
                'insight' => 'Dislipidemia paling banyak ditemukan bersama pola konsumsi lemak tinggi.',
            ],
            [
                'key' => 'hipertensi',
                'title' => 'Analisis Hipertensi',
                'total' => 1904,
                'pct' => 9.8,
                'legend' => [
                    ['label' => 'Kalori berlebih', 'pct' => 28.9, 'color' => '#DC2626', 'icon' => 'mdi:fire', 'available' => true],
                    // food_analyses (BeWell) tidak mencatat natrium — tampilkan status
                    // "belum tersedia" apa adanya, jangan dikarang jadi sebuah persentase.
                    ['label' => 'Natrium tinggi', 'pct' => null, 'color' => '#94A3B8', 'icon' => 'mdi:shaker-outline', 'available' => false],
                    ['label' => 'Lemak tinggi', 'pct' => 27.1, 'color' => '#F86624', 'icon' => 'mdi:oil', 'available' => true],
                    ['label' => 'Serat rendah', 'pct' => 33.8, 'color' => '#94A3B8', 'icon' => 'mdi:leaf-off', 'available' => true],
                ],
                'insight' => 'Data natrium belum tersedia dari log nutrisi — asosiasi hipertensi saat ini hanya dihitung dari kalori, lemak, dan serat.',
            ],
            [
                'key' => 'gula_darah',
                'title' => 'Analisis Gula Darah Tinggi',
                'total' => 962,
                'pct' => 5.0,
                'legend' => [
                    ['label' => 'Kalori berlebih', 'pct' => 26.1, 'color' => '#DC2626', 'icon' => 'mdi:fire', 'available' => true],
                    ['label' => 'Karbohidrat tinggi', 'pct' => 48.6, 'color' => '#F4941E', 'icon' => 'mdi:rice', 'available' => true],
                    ['label' => 'Lemak tinggi', 'pct' => 22.4, 'color' => '#F86624', 'icon' => 'mdi:oil', 'available' => true],
                    ['label' => 'Serat rendah', 'pct' => 41.2, 'color' => '#94A3B8', 'icon' => 'mdi:leaf-off', 'available' => true],
                ],
                'insight' => 'Karbohidrat tinggi adalah faktor yang paling sering ditemukan bersama gula darah tinggi.',
            ],
            [
                'key' => 'sindrom_metabolik',
                'title' => 'Analisis Sindrom Metabolik',
                'total' => 684,
                'pct' => 3.5,
                'legend' => [
                    ['label' => 'Kalori berlebih', 'pct' => 45.3, 'color' => '#DC2626', 'icon' => 'mdi:fire', 'available' => true],
                    ['label' => 'Karbohidrat tinggi', 'pct' => 48.1, 'color' => '#F4941E', 'icon' => 'mdi:rice', 'available' => true],
                    ['label' => 'Lemak tinggi', 'pct' => 36.4, 'color' => '#F86624', 'icon' => 'mdi:oil', 'available' => true],
                    ['label' => 'Serat rendah', 'pct' => 51.7, 'color' => '#94A3B8', 'icon' => 'mdi:leaf-off', 'available' => true],
                ],
                'insight' => 'Lebih dari separuh kelompok sindrom metabolik memiliki asupan serat rendah.',
            ],
            [
                'key' => 'framingham',
                'title' => 'Analisis Framingham Risk Score',
                'total' => 421,
                'pct' => 2.2,
                'legend' => [
                    ['label' => 'Kalori berlebih', 'pct' => 42.5, 'color' => '#DC2626', 'icon' => 'mdi:fire', 'available' => true],
                    ['label' => 'Lemak tinggi', 'pct' => 39.4, 'color' => '#F86624', 'icon' => 'mdi:oil', 'available' => true],
                    ['label' => 'Kolesterol tinggi', 'pct' => 37.8, 'color' => '#F4941E', 'icon' => 'mdi:water', 'available' => true],
                    ['label' => 'Aktivitas rendah', 'pct' => 46.1, 'color' => '#94A3B8', 'icon' => 'mdi:run-fast', 'available' => true],
                ],
                'insight' => 'Skor Framingham High Risk berasosiasi secara deskriptif dengan pola makan tinggi lemak dan kolesterol — bukan hubungan sebab-akibat yang teruji secara statistik.',
            ],
        ];
    }

    /**
     * @return array{categories: list<string>, series: list<array<string, mixed>>}
     */
    private function trendChart(): array
    {
        return [
            'categories' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep'],
            'series' => [
                ['name' => 'Obesitas', 'data' => [1180, 1220, 1260, 1290, 1330, 1370, 1410, 1450, 1482]],
                ['name' => 'Dislipidemia', 'data' => [950, 980, 1010, 1035, 1060, 1080, 1100, 1115, 1126]],
                ['name' => 'Hipertensi', 'data' => [1550, 1600, 1650, 1690, 1730, 1780, 1820, 1860, 1904]],
                ['name' => 'Gula Darah Tinggi', 'data' => [780, 800, 820, 845, 865, 890, 915, 940, 962]],
                ['name' => 'Sindrom Metabolik', 'data' => [560, 580, 600, 615, 630, 648, 660, 672, 684]],
                ['name' => 'Framingham High Risk', 'data' => [340, 355, 365, 378, 388, 398, 405, 413, 421]],
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, total: int}>
     */
    private function employeeTabs(): array
    {
        return [
            'semua' => ['label' => 'Semua', 'total' => 3482],
            'obesitas' => ['label' => 'Obesitas', 'total' => 1482],
            'dislipidemia' => ['label' => 'Dislipidemia', 'total' => 1126],
            'hipertensi' => ['label' => 'Hipertensi', 'total' => 1904],
            'gula_darah' => ['label' => 'Gula Darah Tinggi', 'total' => 962],
            'sindrom_metabolik' => ['label' => 'Sindrom Metabolik', 'total' => 684],
            'framingham' => ['label' => 'Framingham High Risk', 'total' => 421],
        ];
    }

    /**
     * Baris tabel karyawan berisiko, di-flag per kondisi dari sample record.
     * Server-side: caller (controller) yang menerapkan search/order/paginasi.
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
     * Detail 1 karyawan untuk modal "Detail" — profil MCU x Nutrisi
     * berpasangan (paired comparison), plus flag data yang belum lengkap.
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
                    'jabatan' => $record['jabatan'],
                ],
                'mcu' => $record['has_mcu'] ? [
                    'bmi' => $record['bmi'],
                    'tensi' => $record['tensi_sistol'].'/'.$record['tensi_diastol'],
                    'gdp' => $record['gdp'],
                    'kolesterol' => $record['kolesterol'],
                    'ldl' => $record['ldl'],
                    'hdl' => $record['hdl'],
                    'trigliserida' => $record['trigliserida'],
                    'framingham_score' => $record['framingham_score'],
                    'metabolic_component_count' => $record['metabolic_component_count'],
                ] : null,
                'nutrition' => $record['has_nutrition'] ? [
                    'avg_calories' => $record['avg_calories'],
                    'target_calories' => $record['target_calories'],
                    'calories_vs_target_pct' => $caloriesVsTargetPct,
                    'avg_carbs_g' => $record['avg_carbs_g'],
                    'avg_fat_g' => $record['avg_fat_g'],
                    'avg_protein_g' => $record['avg_protein_g'],
                    'avg_fiber_g' => $record['avg_fiber_g'],
                    'avg_sodium_mg' => $record['avg_sodium_mg'],
                    'logging_days_30d' => $record['logging_days_30d'],
                ] : null,
                'conditions' => $conditions,
                'data_completeness' => [
                    'has_mcu' => $record['has_mcu'],
                    'has_nutrition' => $record['has_nutrition'],
                    'matched' => $record['has_mcu'] && $record['has_nutrition'],
                ],
                'profile_pairs' => $this->profilePairs($record),
            ];
        }

        return null;
    }

    /**
     * "MCU x Nutrition Profile": baris perbandingan berpasangan MCU vs
     * nutrisi, hanya diisi kalau kedua sisi punya data.
     *
     * @return list<array{mcu_label: string, mcu_value: string, mcu_flag: string, nutrition_label: string, nutrition_value: string, nutrition_flag: string}>
     */
    private function profilePairs(array $record): array
    {
        if (! $record['has_mcu'] || ! $record['has_nutrition']) {
            return [];
        }

        $pairs = [];

        if ($record['bmi'] !== null && $record['avg_calories'] !== null && $record['target_calories'] !== null) {
            $pct = round((($record['avg_calories'] - $record['target_calories']) / $record['target_calories']) * 100);
            $pairs[] = [
                'mcu_label' => 'BMI',
                'mcu_value' => number_format($record['bmi'], 1, ',', '.'),
                'mcu_flag' => $record['bmi'] >= 30 ? 'HIGH' : 'NORMAL',
                'nutrition_label' => 'Kalori vs Target',
                'nutrition_value' => ($pct >= 0 ? '+' : '').$pct.'%',
                'nutrition_flag' => $pct > 0 ? 'ABOVE TARGET' : 'ON TARGET',
            ];
        }

        if ($record['ldl'] !== null && $record['avg_fat_g'] !== null) {
            $pairs[] = [
                'mcu_label' => 'LDL',
                'mcu_value' => (string) $record['ldl'],
                'mcu_flag' => $record['ldl'] >= 130 ? 'HIGH' : 'NORMAL',
                'nutrition_label' => 'Asupan Lemak',
                'nutrition_value' => number_format($record['avg_fat_g'], 0, ',', '.').' g',
                'nutrition_flag' => $record['avg_fat_g'] >= 90 ? 'ABOVE TARGET' : 'ON TARGET',
            ];
        }

        if ($record['gdp'] !== null && $record['avg_carbs_g'] !== null) {
            $pairs[] = [
                'mcu_label' => 'GDP',
                'mcu_value' => (string) $record['gdp'],
                'mcu_flag' => $record['gdp'] >= 100 ? 'HIGH' : 'NORMAL',
                'nutrition_label' => 'Asupan Karbohidrat',
                'nutrition_value' => number_format($record['avg_carbs_g'], 0, ',', '.').' g',
                'nutrition_flag' => $record['avg_carbs_g'] >= 350 ? 'ABOVE TARGET' : 'ON TARGET',
            ];
        }

        return $pairs;
    }

    /**
     * @return list<string>
     */
    private function conditionsFor(array $record): array
    {
        $conditions = [];
        if ($record['bmi'] !== null && $record['bmi'] >= 30) {
            $conditions[] = 'obesitas';
        }
        if (($record['kolesterol'] !== null && $record['kolesterol'] >= 200)
            || ($record['ldl'] !== null && $record['ldl'] >= 130)
            || ($record['trigliserida'] !== null && $record['trigliserida'] >= 150)) {
            $conditions[] = 'dislipidemia';
        }
        if (($record['tensi_sistol'] !== null && $record['tensi_sistol'] >= 130)
            || ($record['tensi_diastol'] !== null && $record['tensi_diastol'] >= 85)) {
            $conditions[] = 'hipertensi';
        }
        if ($record['gdp'] !== null && $record['gdp'] >= 100) {
            $conditions[] = 'gula_darah';
        }
        if ($record['metabolic_component_count'] !== null && $record['metabolic_component_count'] >= 3) {
            $conditions[] = 'sindrom_metabolik';
        }
        if ($record['framingham_score'] !== null && $record['framingham_score'] >= 20) {
            $conditions[] = 'framingham';
        }

        return $conditions;
    }

    /**
     * @param  list<string>  $conditions
     * @return array<string, mixed>
     */
    private function toEmployeeRow(array $record, array $conditions): array
    {
        $conditionLabels = [
            'obesitas' => 'Obesitas',
            'dislipidemia' => 'Dislipidemia',
            'hipertensi' => 'Hipertensi',
            'gula_darah' => 'Gula Darah Tinggi',
            'sindrom_metabolik' => 'Sindrom Metabolik',
            'framingham' => 'Framingham High Risk',
        ];

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
            'bmi' => $record['bmi'],
            'kolesterol' => $record['kolesterol'],
            'ldl' => $record['ldl'],
            'trigliserida' => $record['trigliserida'],
            'tensi' => $record['tensi_sistol'] !== null ? $record['tensi_sistol'].'/'.$record['tensi_diastol'] : null,
            'gdp' => $record['gdp'],
            'conditions' => $conditions,
            'risiko_label' => implode(', ', array_map(static fn (string $c): string => $conditionLabels[$c] ?? $c, $conditions)),
            'status_nutrisi' => $statusNutrisi,
            'has_nutrition' => $record['has_nutrition'],
        ];
    }
}
