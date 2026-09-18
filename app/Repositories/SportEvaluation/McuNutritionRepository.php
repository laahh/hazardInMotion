<?php

declare(strict_types=1);

namespace App\Repositories\SportEvaluation;

/**
 * Sumber data mentah untuk dashboard MCU x Nutrisi.
 *
 * Saat ini mengembalikan data dummy (lihat catatan di dashboard()) sambil
 * menunggu HealthNutritionRiskService (join real MCU Postgres `mv_ftw_mcu` +
 * BeWell MySQL `food_analyses`) dipetakan ke kontrak yang sama. Bentuk data
 * SUDAH disamakan dengan skema real: setiap "record" punya flag has_mcu /
 * has_nutrition terpisah, dan field yang memang tidak ada di sumber aslinya
 * (mis. natrium — food_analyses tidak punya kolom sodium) selalu null, bukan
 * dikarang jadi 0. Ganti isi method di sini ke query asli tanpa perlu ubah
 * McuNutritionAnalyticsService atau Blade begitu koneksi MCU siap dites.
 *
 * @phpstan-type McuNutritionRecord array{
 *     id: int,
 *     nama: string,
 *     nik: string,
 *     perusahaan: string,
 *     site: string,
 *     departemen: string,
 *     jabatan: string,
 *     has_mcu: bool,
 *     has_nutrition: bool,
 *     bmi: float|null,
 *     tensi_sistol: int|null,
 *     tensi_diastol: int|null,
 *     gdp: int|null,
 *     kolesterol: int|null,
 *     ldl: int|null,
 *     hdl: int|null,
 *     trigliserida: int|null,
 *     framingham_score: int|null,
 *     metabolic_component_count: int|null,
 *     avg_calories: float|null,
 *     target_calories: float|null,
 *     avg_carbs_g: float|null,
 *     avg_fat_g: float|null,
 *     avg_protein_g: float|null,
 *     avg_fiber_g: float|null,
 *     avg_sodium_mg: null,
 *     logging_days_30d: int|null,
 * }
 */
final class McuNutritionRepository
{
    private const TOTAL_MCU = 19401;

    private const TOTAL_NUTRITION = 17289;

    private const TOTAL_MATCHED = 16842;

    /**
     * Cakupan data secara keseluruhan (bukan hanya sample di records()).
     * Analisis MCU x Nutrisi lain di service HARUS memakai matched_total
     * sebagai denominator, bukan total_mcu atau total_nutrition.
     *
     * @return array{total_mcu: int, total_nutrition: int, matched_total: int}
     */
    public function coverage(): array
    {
        return [
            'total_mcu' => self::TOTAL_MCU,
            'total_nutrition' => self::TOTAL_NUTRITION,
            'matched_total' => self::TOTAL_MATCHED,
        ];
    }

    /**
     * Sample record employee-level (dipakai tabel + drill-down). Jumlah
     * sample jauh lebih kecil dari coverage() di atas — representatif untuk
     * demo, bukan dump seluruh populasi ke browser (lih. catatan performa
     * pada McuNutritionAnalyticsService).
     *
     * @return list<array<string, mixed>>
     */
    public function records(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $firstNames = ['Andi', 'Budi', 'Citra', 'Dedi', 'Eka', 'Fajar', 'Gita', 'Hendra', 'Indra', 'Joko', 'Kartika', 'Lina', 'Made', 'Nur', 'Oscar', 'Putri', 'Rian', 'Sari', 'Taufik', 'Umi', 'Vina', 'Wahyu', 'Yani', 'Zaki'];
        $lastNames = ['Pratama', 'Santoso', 'Dewi', 'Kurniawan', 'Lestari', 'Wibowo', 'Anggraini', 'Saputra', 'Wijaya', 'Ramadhan', 'Utami', 'Setiawan', 'Puspita', 'Firmansyah', 'Handayani'];
        $sites = ['LMO', 'SMO', 'BMO 1', 'GMO', 'BMO 3', 'BMO 2'];
        $companies = ['PT ABC', 'PT XYZ', 'PT PAMA', 'PT BUMA', 'PT BAR', 'PT DNX Indonesia'];
        $departements = ['Produksi', 'Engineering', 'HSE', 'HR', 'Logistik', 'Plant'];
        $jabatans = ['Operator', 'Supervisor', 'Engineer', 'Admin', 'Teknisi', 'Foreman'];

        $seed = [
            ['nama' => 'Andi Pratama', 'nik' => '123456', 'company' => 'PT ABC', 'site' => 'LMO', 'bmi' => 32.1, 'tensi' => [145, 95], 'gdp' => 126, 'kolesterol' => 240, 'ldl' => 160, 'hdl' => 42, 'trigliserida' => 220],
            ['nama' => 'Budi Santoso', 'nik' => '234567', 'company' => 'PT XYZ', 'site' => 'SMO', 'bmi' => 29.8, 'tensi' => [138, 92], 'gdp' => 112, 'kolesterol' => 210, 'ldl' => 140, 'hdl' => 45, 'trigliserida' => 180],
            ['nama' => 'Citra Dewi', 'nik' => '345678', 'company' => 'PT PAMA', 'site' => 'BMO 1', 'bmi' => 31.5, 'tensi' => [128, 88], 'gdp' => 118, 'kolesterol' => 190, 'ldl' => 130, 'hdl' => 48, 'trigliserida' => 160],
            ['nama' => 'Dedi Kurniawan', 'nik' => '456789', 'company' => 'PT BUMA', 'site' => 'GMO', 'bmi' => 29.8, 'tensi' => [150, 98], 'gdp' => 134, 'kolesterol' => 260, 'ldl' => 170, 'hdl' => 40, 'trigliserida' => 240],
            ['nama' => 'Eka Lestari', 'nik' => '567890', 'company' => 'PT BAR', 'site' => 'BMO 3', 'bmi' => 30.5, 'tensi' => [140, 90], 'gdp' => 128, 'kolesterol' => 200, 'ldl' => 135, 'hdl' => 44, 'trigliserida' => 190],
        ];

        $records = [];
        $id = 1;
        foreach ($seed as $row) {
            $records[] = $this->buildRecord($id++, $row['nama'], $row['nik'], $row['company'], $row['site'], $departements[array_rand($departements)], $jabatans[array_rand($jabatans)], $row, true, true);
        }

        mt_srand(20260918);
        for ($i = 0; $i < 95; $i++) {
            $nama = $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)];
            $hasMcu = mt_rand(1, 100) <= 92;
            // Sekitar 89% dari yang punya MCU juga punya log nutrisi (≈ matched/total_mcu asli).
            $hasNutrition = $hasMcu && mt_rand(1, 100) <= 89;

            $row = [
                'bmi' => round(mt_rand(180, 360) / 10, 1),
                'tensi' => [mt_rand(105, 155), mt_rand(70, 100)],
                'gdp' => mt_rand(80, 160),
                'kolesterol' => mt_rand(150, 280),
                'ldl' => mt_rand(90, 190),
                'hdl' => mt_rand(35, 65),
                'trigliserida' => mt_rand(100, 260),
            ];

            $records[] = $this->buildRecord(
                $id++,
                $nama,
                (string) mt_rand(100000, 999999),
                $companies[array_rand($companies)],
                $sites[array_rand($sites)],
                $departements[array_rand($departements)],
                $jabatans[array_rand($jabatans)],
                $row,
                $hasMcu,
                $hasNutrition,
            );
        }

        return $cached = $records;
    }

    /**
     * @param  array{bmi: float, tensi: array{0: int, 1: int}, gdp: int, kolesterol: int, ldl: int, hdl: int, trigliserida: int}  $mcu
     * @return array<string, mixed>
     */
    private function buildRecord(
        int $id,
        string $nama,
        string $nik,
        string $company,
        string $site,
        string $departemen,
        string $jabatan,
        array $mcu,
        bool $hasMcu,
        bool $hasNutrition,
    ): array {
        $metabolicComponents = 0;
        if ($hasMcu) {
            if ($mcu['bmi'] >= 30) {
                $metabolicComponents++;
            }
            if ($mcu['tensi'][0] >= 130 || $mcu['tensi'][1] >= 85) {
                $metabolicComponents++;
            }
            if ($mcu['gdp'] >= 100) {
                $metabolicComponents++;
            }
            if ($mcu['trigliserida'] >= 150) {
                $metabolicComponents++;
            }
            if ($mcu['hdl'] < 40) {
                $metabolicComponents++;
            }
        }

        $framinghamScore = $hasMcu
            ? min(30, max(1, (int) round(($mcu['bmi'] - 18) + ($mcu['tensi'][0] - 110) / 4 + ($mcu['kolesterol'] - 150) / 10)))
            : null;

        $avgCalories = null;
        $targetCalories = null;
        $avgCarbs = null;
        $avgFat = null;
        $avgProtein = null;
        $avgFiber = null;
        $loggingDays = null;
        if ($hasNutrition) {
            $targetCalories = 2300.0;
            $avgCalories = (float) mt_rand(1600, 3200);
            $avgCarbs = (float) mt_rand(180, 480);
            $avgFat = (float) mt_rand(40, 150);
            $avgProtein = (float) mt_rand(40, 100);
            $avgFiber = (float) mt_rand(6, 30);
            $loggingDays = mt_rand(1, 30);
        }

        return [
            'id' => $id,
            'nama' => $nama,
            'nik' => $nik,
            'perusahaan' => $company,
            'site' => $site,
            'departemen' => $departemen,
            'jabatan' => $jabatan,
            'has_mcu' => $hasMcu,
            'has_nutrition' => $hasNutrition,
            'bmi' => $hasMcu ? $mcu['bmi'] : null,
            'tensi_sistol' => $hasMcu ? $mcu['tensi'][0] : null,
            'tensi_diastol' => $hasMcu ? $mcu['tensi'][1] : null,
            'gdp' => $hasMcu ? $mcu['gdp'] : null,
            'kolesterol' => $hasMcu ? $mcu['kolesterol'] : null,
            'ldl' => $hasMcu ? $mcu['ldl'] : null,
            'hdl' => $hasMcu ? $mcu['hdl'] : null,
            'trigliserida' => $hasMcu ? $mcu['trigliserida'] : null,
            'framingham_score' => $framinghamScore,
            'metabolic_component_count' => $hasMcu ? $metabolicComponents : null,
            'avg_calories' => $avgCalories,
            'target_calories' => $targetCalories,
            'avg_carbs_g' => $avgCarbs,
            'avg_fat_g' => $avgFat,
            'avg_protein_g' => $avgProtein,
            'avg_fiber_g' => $avgFiber,
            // food_analyses (BeWell) tidak punya kolom natrium/sodium — jangan
            // dikarang, biarkan null supaya UI menampilkan "Data belum tersedia".
            'avg_sodium_mg' => null,
            'logging_days_30d' => $loggingDays,
        ];
    }
}
