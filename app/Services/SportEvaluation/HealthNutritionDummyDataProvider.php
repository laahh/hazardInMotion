<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Carbon;

/**
 * Dataset dummy untuk preview desain baru dashboard "MCU x Nutrisi" sebelum
 * HealthNutritionRiskService (MCU Postgres + BeWell MySQL) dipetakan ulang ke
 * struktur data yang sama. Semua angka meniru mockup — bukan hasil query.
 */
final class HealthNutritionDummyDataProvider
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $today = Carbon::now();
        $rangeStart = $today->copy()->subDays(24);

        return [
            'dummyMode' => true,
            'dateRangeLabel' => $rangeStart->translatedFormat('d M Y').' – '.$today->translatedFormat('d M Y'),
            'dateRangeFrom' => $rangeStart->toDateString(),
            'dateRangeTo' => $today->toDateString(),
            'filterOptions' => [
                'sites' => ['BMO 1', 'BMO 2', 'BMO 3', 'GMO', 'LMO', 'SMO'],
                'companies' => ['PT ABC', 'PT XYZ', 'PT PAMA', 'PT BUMA', 'PT BAR', 'PT DNX Indonesia'],
            ],
            'kpiTop' => $this->kpiTop(),
            'kpiConditions' => $this->kpiConditions(),
            'heatmap' => $this->heatmap($rangeStart, $today),
            'compliance' => $this->compliance(),
            'correlationMatrix' => $this->correlationMatrix(),
            'macroChart' => $this->macroChart(),
            'analysisCards' => $this->analysisCards(),
            'trendChart' => $this->trendChart(),
            'comparisonChart' => $this->comparisonChart(),
            'employeeTable' => $this->employeeTable(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function kpiTop(): array
    {
        return [
            'total_karyawan' => [
                'label' => 'Total Karyawan (MCU)',
                'icon' => 'solar:users-group-rounded-bold',
                'color' => 'primary',
                'value' => 19401,
                'sub_label' => 'dari periode sebelumnya',
                'delta_pct' => 5.2,
                'delta_up' => true,
                'sparkline' => [18400, 18600, 18750, 18900, 19050, 19180, 19401],
            ],
            'karyawan_berisiko' => [
                'label' => 'Karyawan Berisiko',
                'icon' => 'solar:heart-pulse-bold',
                'color' => 'danger',
                'value' => 3482,
                'sub_label' => 'dari total',
                'sub_pct' => 18.0,
                'delta_pct' => 2.1,
                'delta_up' => true,
                'sparkline' => [3120, 3210, 3260, 3300, 3350, 3410, 3482],
            ],
            'target_kalori' => [
                'label' => 'Memenuhi Target Kalori',
                'icon' => 'solar:donut-bitten-bold',
                'color' => 'success',
                'value' => 14216,
                'sub_label' => 'dari total',
                'sub_pct' => 73.3,
                'delta_pct' => 4.5,
                'delta_up' => true,
                'sparkline' => [12800, 13100, 13400, 13650, 13900, 14050, 14216],
            ],
            'data_nutrisi' => [
                'label' => 'Data Nutrisi Tercatat',
                'icon' => 'solar:clipboard-list-bold',
                'color' => 'info',
                'value' => 17289,
                'sub_label' => 'dari total',
                'sub_pct' => 89.1,
                'delta_pct' => 3.8,
                'delta_up' => true,
                'sparkline' => [15900, 16200, 16500, 16750, 16950, 17100, 17289],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function kpiConditions(): array
    {
        return [
            [
                'key' => 'obesitas',
                'label' => 'Obesitas',
                'sub_label' => 'BMI ≥30',
                'icon' => 'solar:scale-bold',
                'color' => '#F97316',
                'value' => 1482,
                'pct' => 7.6,
                'delta_pct' => 1.4,
            ],
            [
                'key' => 'dislipidemia',
                'label' => 'Dislipidemia',
                'sub_label' => 'Kol/LDL/TG tinggi',
                'icon' => 'solar:test-tube-bold',
                'color' => '#3B82F6',
                'value' => 1126,
                'pct' => 5.8,
                'delta_pct' => 0.9,
            ],
            [
                'key' => 'hipertensi',
                'label' => 'Hipertensi',
                'sub_label' => 'Tekanan Darah',
                'icon' => 'solar:heart-pulse-bold',
                'color' => '#EF4444',
                'value' => 1904,
                'pct' => 9.8,
                'delta_pct' => 2.6,
            ],
            [
                'key' => 'gula_darah',
                'label' => 'Gula Darah Tinggi',
                'sub_label' => 'GDS',
                'icon' => 'solar:test-tube-minimalistic-bold',
                'color' => '#EAB308',
                'value' => 962,
                'pct' => 5.0,
                'delta_pct' => 1.1,
            ],
            [
                'key' => 'sindrom_metabolik',
                'label' => 'Sindrom Metabolik',
                'sub_label' => '≥3 komponen',
                'icon' => 'solar:pulse-bold',
                'color' => '#A855F7',
                'value' => 684,
                'pct' => 3.5,
                'delta_pct' => 0.8,
            ],
            [
                'key' => 'framingham',
                'label' => 'Framingham High Risk',
                'sub_label' => 'Skor risiko 10 tahun',
                'icon' => 'solar:danger-triangle-bold',
                'color' => '#DC2626',
                'value' => 421,
                'pct' => 2.2,
                'delta_pct' => 0.6,
            ],
        ];
    }

    /**
     * @return array{categories: list<string>, days: list<string>, series: list<array<string, mixed>>}
     */
    private function heatmap(Carbon $start, Carbon $end): array
    {
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        $byDow = array_fill(0, 7, []);
        $labels = [];

        $cursor = $start->copy();
        $i = 0;
        while ($cursor->lte($end)) {
            $labels[] = $cursor->format('d M');
            $dow = ($cursor->dayOfWeekIso - 1); // 0 = Senin ... 6 = Minggu
            $weekProgress = $i / 24;
            $weekdayBoost = in_array($cursor->dayOfWeekIso, [1, 2, 3, 4, 5], true) ? 1.25 : 0.7;
            $base = 60 + ($weekProgress * 260);
            $wave = sin($i / 3) * 60;
            $value = max(0, (int) round(($base + $wave) * $weekdayBoost));

            foreach ($byDow as $d => $row) {
                $byDow[$d][] = $d === $dow ? $value : null;
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

        return [
            'categories' => $labels,
            'days' => $days,
            'series' => $series,
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
                ['label' => 'Cukup (3-4 hari/minggu)', 'pct' => 18.7, 'count' => 3624, 'color' => '#F59E0B'],
                ['label' => 'Jarang (1-2 hari/minggu)', 'pct' => 8.5, 'count' => 1651, 'color' => '#3B82F6'],
                ['label' => 'Tidak pernah', 'pct' => 10.4, 'count' => 2019, 'color' => '#EF4444'],
            ],
        ];
    }

    /**
     * @return array{rows: list<string>, cols: list<string>, levels: list<list<int>>}
     */
    private function correlationMatrix(): array
    {
        $rows = ['Obesitas (BMI≥30)', 'Dislipidemia', 'Hipertensi', 'Gula Darah', 'Sindrom Metabolik', 'Framingham High Risk'];
        $cols = ['Kalori Berlebih', 'Karbo Tinggi', 'Lemak Tinggi', 'Protein Rendah', 'Serat Rendah'];

        // Level 0-4: 0=Tidak Signifikan, 1=Rendah, 2=Sedang, 3=Tinggi, 4=Sangat Tinggi.
        $levels = [
            [4, 3, 4, 2, 3],
            [3, 3, 4, 2, 3],
            [3, 2, 3, 1, 3],
            [3, 4, 2, 1, 2],
            [4, 4, 3, 2, 4],
            [4, 3, 4, 2, 3],
        ];

        return ['rows' => $rows, 'cols' => $cols, 'levels' => $levels];
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
     * @return list<array<string, mixed>>
     */
    private function analysisCards(): array
    {
        return [
            [
                'key' => 'obesitas',
                'title' => 'Analisis Obesitas (BMI≥30)',
                'total' => 1482,
                'pct' => 7.6,
                'colors' => ['#EF4444', '#F97316', '#94A3B8'],
                'legend' => [
                    ['label' => 'Melebihi target kalori', 'pct' => 38.2, 'count' => 566, 'color' => '#EF4444', 'icon' => 'mdi:arrow-up-bold'],
                    ['label' => '<50% target kalori', 'pct' => 22.1, 'count' => 327, 'color' => '#F97316', 'icon' => 'mdi:arrow-down-bold'],
                    ['label' => 'Tidak ada data kalori', 'pct' => 39.7, 'count' => 589, 'color' => '#94A3B8', 'icon' => 'mdi:help-circle'],
                ],
                'insight' => '62,3% karyawan obesitas memiliki asupan kalori di atas target harian.',
            ],
            [
                'key' => 'dislipidemia',
                'title' => 'Analisis Dislipidemia',
                'total' => 1126,
                'pct' => 5.8,
                'colors' => ['#EF4444', '#F97316', '#F97316', '#94A3B8'],
                'legend' => [
                    ['label' => 'Kalori berlebih', 'pct' => 34.7, 'color' => '#EF4444', 'icon' => 'mdi:fire'],
                    ['label' => 'Lemak tinggi', 'pct' => 41.2, 'color' => '#F97316', 'icon' => 'mdi:oil'],
                    ['label' => 'Karbohidrat tinggi', 'pct' => 28.1, 'color' => '#F59E0B', 'icon' => 'mdi:rice'],
                    ['label' => 'Serat rendah', 'pct' => 36.5, 'color' => '#94A3B8', 'icon' => 'mdi:leaf-off'],
                ],
                'insight' => 'Dislipidemia paling berkorelasi dengan konsumsi lemak tinggi.',
            ],
            [
                'key' => 'hipertensi',
                'title' => 'Analisis Hipertensi',
                'total' => 1904,
                'pct' => 9.8,
                'colors' => ['#EF4444', '#DC2626', '#F97316', '#94A3B8'],
                'legend' => [
                    ['label' => 'Kalori berlebih', 'pct' => 28.9, 'color' => '#EF4444', 'icon' => 'mdi:fire'],
                    ['label' => 'Natrium tinggi', 'pct' => 52.4, 'color' => '#DC2626', 'icon' => 'mdi:shaker-outline'],
                    ['label' => 'Lemak tinggi', 'pct' => 27.1, 'color' => '#F97316', 'icon' => 'mdi:oil'],
                    ['label' => 'Serat rendah', 'pct' => 33.8, 'color' => '#94A3B8', 'icon' => 'mdi:leaf-off'],
                ],
                'insight' => '52,4% karyawan hipertensi memiliki asupan natrium di atas rekomendasi.',
            ],
            [
                'key' => 'gula_darah',
                'title' => 'Analisis Gula Darah Tinggi',
                'total' => 962,
                'pct' => 5.0,
                'colors' => ['#EF4444', '#F59E0B', '#F97316', '#94A3B8'],
                'legend' => [
                    ['label' => 'Kalori berlebih', 'pct' => 26.1, 'color' => '#EF4444', 'icon' => 'mdi:fire'],
                    ['label' => 'Karbohidrat tinggi', 'pct' => 48.6, 'color' => '#F59E0B', 'icon' => 'mdi:rice'],
                    ['label' => 'Lemak tinggi', 'pct' => 22.4, 'color' => '#F97316', 'icon' => 'mdi:oil'],
                    ['label' => 'Serat rendah', 'pct' => 41.2, 'color' => '#94A3B8', 'icon' => 'mdi:leaf-off'],
                ],
                'insight' => 'Karbohidrat tinggi menjadi faktor utama pada gula darah tinggi.',
            ],
            [
                'key' => 'sindrom_metabolik',
                'title' => 'Analisis Sindrom Metabolik',
                'total' => 684,
                'pct' => 3.5,
                'colors' => ['#EF4444', '#F59E0B', '#F97316', '#94A3B8'],
                'legend' => [
                    ['label' => 'Kalori berlebih', 'pct' => 45.3, 'color' => '#EF4444', 'icon' => 'mdi:fire'],
                    ['label' => 'Karbohidrat tinggi', 'pct' => 48.1, 'color' => '#F59E0B', 'icon' => 'mdi:rice'],
                    ['label' => 'Lemak tinggi', 'pct' => 36.4, 'color' => '#F97316', 'icon' => 'mdi:oil'],
                    ['label' => 'Serat rendah', 'pct' => 51.7, 'color' => '#94A3B8', 'icon' => 'mdi:leaf-off'],
                ],
                'insight' => '>50% karyawan sindrom metabolik memiliki asupan serat rendah.',
            ],
            [
                'key' => 'framingham',
                'title' => 'Analisis Framingham Risk Score',
                'total' => 421,
                'pct' => 2.2,
                'colors' => ['#EF4444', '#F97316', '#F59E0B', '#94A3B8'],
                'legend' => [
                    ['label' => 'Kalori berlebih', 'pct' => 42.5, 'color' => '#EF4444', 'icon' => 'mdi:fire'],
                    ['label' => 'Lemak tinggi', 'pct' => 39.4, 'color' => '#F97316', 'icon' => 'mdi:oil'],
                    ['label' => 'Kolesterol tinggi', 'pct' => 37.8, 'color' => '#F59E0B', 'icon' => 'mdi:water'],
                    ['label' => 'Aktivitas rendah', 'pct' => 46.1, 'color' => '#94A3B8', 'icon' => 'mdi:run-fast'],
                ],
                'insight' => 'Pola makan tinggi lemak dan kolesterol meningkatkan risiko kardiovaskular.',
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
     * @return array{categories: list<string>, berisiko: list<float>, tidak_berisiko: list<float>}
     */
    private function comparisonChart(): array
    {
        return [
            'categories' => ['Kalori (kcal)', 'Karbohidrat (g)', 'Lemak (g)', 'Protein (g)', 'Serat (g)'],
            'berisiko' => [2850, 420, 130, 65, 14],
            'tidak_berisiko' => [2120, 310, 95, 72, 22],
        ];
    }

    /**
     * @return array{tabs: array<string, array{label: string, total: int}>, rows: list<array<string, mixed>>}
     */
    private function employeeTable(): array
    {
        $tabs = [
            'semua' => ['label' => 'Semua', 'total' => 3482],
            'obesitas' => ['label' => 'Obesitas', 'total' => 1482],
            'dislipidemia' => ['label' => 'Dislipidemia', 'total' => 1126],
            'hipertensi' => ['label' => 'Hipertensi', 'total' => 1904],
            'gula_darah' => ['label' => 'Gula Darah Tinggi', 'total' => 962],
            'sindrom_metabolik' => ['label' => 'Sindrom Metabolik', 'total' => 684],
            'framingham' => ['label' => 'Framingham High Risk', 'total' => 421],
        ];

        $firstNames = ['Andi', 'Budi', 'Citra', 'Dedi', 'Eka', 'Fajar', 'Gita', 'Hendra', 'Indra', 'Joko', 'Kartika', 'Lina', 'Made', 'Nur', 'Oscar', 'Putri', 'Rian', 'Sari', 'Taufik', 'Umi', 'Vina', 'Wahyu', 'Yani', 'Zaki'];
        $lastNames = ['Pratama', 'Santoso', 'Dewi', 'Kurniawan', 'Lestari', 'Wibowo', 'Anggraini', 'Saputra', 'Wijaya', 'Ramadhan', 'Utami', 'Setiawan', 'Puspita', 'Firmansyah', 'Handayani'];
        $sites = ['LMO', 'SMO', 'BMO 1', 'GMO', 'BMO 3', 'BMO 2'];
        $companies = ['PT ABC', 'PT XYZ', 'PT PAMA', 'PT BUMA', 'PT BAR', 'PT DNX Indonesia'];
        $conditionKeys = ['obesitas', 'dislipidemia', 'hipertensi', 'gula_darah', 'sindrom_metabolik', 'framingham'];
        $conditionLabels = [
            'obesitas' => 'Obesitas',
            'dislipidemia' => 'Dislipidemia',
            'hipertensi' => 'Hipertensi',
            'gula_darah' => 'Gula Darah Tinggi',
            'sindrom_metabolik' => 'Sindrom Metabolik',
            'framingham' => 'Framingham High Risk',
        ];
        $findingPool = ['Kalori tinggi', 'Lemak tinggi', 'Karbohidrat tinggi', 'Serat rendah', 'Natrium tinggi'];
        $nutritionStatus = ['Perlu Intervensi', 'Pantau', 'Baik'];

        $seed = [
            ['nama' => 'Andi Pratama', 'nik' => '123456', 'company' => 'PT ABC', 'site' => 'LMO', 'bmi' => 32.1, 'kolesterol' => 240, 'ldl' => 160, 'trigliserida' => 220, 'tensi' => '145/95', 'gds' => 126, 'conditions' => ['obesitas', 'dislipidemia'], 'findings' => ['Kalori tinggi', 'Lemak tinggi']],
            ['nama' => 'Budi Santoso', 'nik' => '234567', 'company' => 'PT XYZ', 'site' => 'SMO', 'bmi' => 29.8, 'kolesterol' => 210, 'ldl' => 140, 'trigliserida' => 180, 'tensi' => '138/92', 'gds' => 112, 'conditions' => ['hipertensi'], 'findings' => ['Karbohidrat tinggi']],
            ['nama' => 'Citra Dewi', 'nik' => '345678', 'company' => 'PT PAMA', 'site' => 'BMO 1', 'bmi' => 31.5, 'kolesterol' => 190, 'ldl' => 130, 'trigliserida' => 160, 'tensi' => '128/88', 'gds' => 118, 'conditions' => ['obesitas'], 'findings' => ['Serat rendah']],
            ['nama' => 'Dedi Kurniawan', 'nik' => '456789', 'company' => 'PT BUMA', 'site' => 'GMO', 'bmi' => 29.8, 'kolesterol' => 260, 'ldl' => 170, 'trigliserida' => 240, 'tensi' => '150/98', 'gds' => 134, 'conditions' => ['dislipidemia', 'hipertensi'], 'findings' => ['Kalori tinggi', 'Lemak tinggi']],
            ['nama' => 'Eka Lestari', 'nik' => '567890', 'company' => 'PT BAR', 'site' => 'BMO 3', 'bmi' => 30.5, 'kolesterol' => 200, 'ldl' => 135, 'trigliserida' => 190, 'tensi' => '140/90', 'gds' => 128, 'conditions' => ['obesitas', 'gula_darah'], 'findings' => ['Karbohidrat tinggi']],
        ];

        $rows = [];
        $rank = 1;
        foreach ($seed as $row) {
            $rows[] = $this->buildEmployeeRow($rank++, $row, $conditionLabels, $nutritionStatus);
        }

        // Tambahan baris acak (deterministik lewat seed) untuk mengisi paginasi tabel dummy.
        mt_srand(20260918);
        $count = count($firstNames) * count($lastNames);
        for ($i = 0; $i < 55; $i++) {
            $nama = $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)];
            $conditionCount = mt_rand(1, 2);
            $conditions = (array) array_rand(array_flip($conditionKeys), $conditionCount);
            $findings = (array) array_rand(array_flip($findingPool), mt_rand(1, 2));

            $rows[] = $this->buildEmployeeRow($rank++, [
                'nama' => $nama,
                'nik' => (string) mt_rand(100000, 999999),
                'company' => $companies[array_rand($companies)],
                'site' => $sites[array_rand($sites)],
                'bmi' => round(mt_rand(220, 360) / 10, 1),
                'kolesterol' => mt_rand(160, 280),
                'ldl' => mt_rand(100, 190),
                'trigliserida' => mt_rand(120, 260),
                'tensi' => mt_rand(110, 155).'/'.mt_rand(75, 100),
                'gds' => mt_rand(90, 160),
                'conditions' => array_values($conditions),
                'findings' => array_values($findings),
            ], $conditionLabels, $nutritionStatus);
        }

        return ['tabs' => $tabs, 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $conditionLabels
     * @param  list<string>  $nutritionStatusPool
     * @return array<string, mixed>
     */
    private function buildEmployeeRow(int $rank, array $row, array $conditionLabels, array $nutritionStatusPool): array
    {
        $riskLabels = array_map(static fn (string $key): string => $conditionLabels[$key] ?? $key, $row['conditions']);
        $statusIndex = min(2, (int) floor((count($row['conditions']) - 1)));

        return [
            'no' => $rank,
            'nama' => $row['nama'],
            'nik' => $row['nik'],
            'perusahaan' => $row['company'],
            'site' => $row['site'],
            'bmi' => $row['bmi'],
            'kolesterol' => $row['kolesterol'],
            'ldl' => $row['ldl'],
            'trigliserida' => $row['trigliserida'],
            'tensi' => $row['tensi'],
            'gds' => $row['gds'],
            'conditions' => $row['conditions'],
            'risiko_label' => implode(', ', $riskLabels),
            'temuan_label' => implode(', ', $row['findings']),
            'status_nutrisi' => $nutritionStatusPool[$statusIndex] ?? $nutritionStatusPool[0],
        ];
    }
}
