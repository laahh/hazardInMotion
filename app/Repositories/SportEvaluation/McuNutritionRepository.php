<?php

declare(strict_types=1);

namespace App\Repositories\SportEvaluation;

use App\Services\SportEvaluation\BewellConnectionService;
use App\Services\SportEvaluation\McuConnectionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sumber data REAL untuk dashboard MCU x Nutrisi — MCU Postgres
 * (`bcsid.mv_ftw_mcu`, kolom `kondisi_kritis`/`kondisi_non_kritis` JSONB)
 * + BeWell MySQL (`food_analyses`).
 *
 * PENTING: skema `mv_ftw_mcu` sudah dicek langsung (lihat pg_attribute) —
 * HANYA ada flag normal/abnormal per nama kondisi di JSONB, TIDAK ADA kolom
 * angka BMI/tensi/kolesterol/LDL/HDL/trigliserida/GDP/skor Framingham
 * sama sekali. Karena itu kontrak record di sini pakai boolean per kondisi
 * (conditions), BUKAN angka lab seperti versi dummy sebelumnya.
 *
 * 5 kondisi yang dilacak (bukan 6 — "Obesitas/BMI" DIHAPUS karena tidak ada
 * satupun nama_kondisi terkait BMI/obesitas di seluruh tabel, dicek via
 * SELECT DISTINCT nama_kondisi):
 * dislipidemia, hipertensi, gula_darah, sindrom_metabolik, framingham.
 */
final class McuNutritionRepository
{
    private const CACHE_TTL = 1800;

    private const DEFAULT_CALORIE_TARGET = 2300.0;

    private const NUTRITION_WINDOW_DAYS = 30;

    /** @var array<string, string> kategori => nama_kondisi persis di MCU Postgres */
    private const CONDITION_NAME_MAP = [
        'hipertensi' => 'Tekanan Darah Tinggi',
        'gula_darah' => 'Gula Darah Puasa (GDP) tinggi',
        'sindrom_metabolik' => 'Memiliki Sindrome Metabolik',
        'framingham' => 'Penilaian Framingham Score Risiko Tinggi',
    ];

    /** @var list<string> dislipidemia = gabungan 3 nama_kondisi ini (salah satu abnormal saja sudah cukup) */
    private const DISLIPIDEMIA_NAMES = ['Kolestrol Total', 'LDL', 'Trigliserida'];

    public function __construct(
        private readonly McuConnectionService $mcu,
        private readonly BewellConnectionService $bewell,
    ) {}

    /**
     * @return array{total_mcu: int, total_nutrition: int, matched_total: int}
     */
    public function coverage(): array
    {
        $records = $this->records();

        return [
            'total_mcu' => count(array_filter($records, static fn (array $r): bool => $r['has_mcu'])),
            'total_nutrition' => count(array_filter($records, static fn (array $r): bool => $r['has_nutrition'])),
            'matched_total' => count(array_filter($records, static fn (array $r): bool => $r['has_mcu'] && $r['has_nutrition'])),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function records(): array
    {
        try {
            return Cache::remember(
                'evaluasi_well:health_nutrition:mcu_records:v1',
                self::CACHE_TTL,
                fn (): array => $this->buildRecords()
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRecords(): array
    {
        $profiles = $this->fetchActiveEmployees();
        if ($profiles->isEmpty()) {
            return [];
        }

        $mcuBySid = $this->fetchMcuConditions();

        $userIds = $profiles->map(static fn (object $p): int => (int) $p->id)->values()->all();
        $nutritionByUser = $this->fetchNutritionAverages($userIds);

        $emptyConditions = [
            'dislipidemia' => false, 'hipertensi' => false, 'gula_darah' => false,
            'sindrom_metabolik' => false, 'framingham' => false,
        ];

        $records = [];
        foreach ($profiles as $sid => $profile) {
            $uid = (int) $profile->id;
            $mcu = $mcuBySid[$sid] ?? null;
            $nutrition = $nutritionByUser[$uid] ?? null;
            $hasNutrition = $nutrition !== null && $nutrition['days_logged'] > 0;

            $records[] = [
                'id' => $uid,
                'nama' => (string) ($profile->nama ?: 'User #'.$uid),
                'nik' => (string) ($profile->nik ?: '-'),
                'kode_sid' => $sid,
                'perusahaan' => trim((string) ($profile->nama_perusahaan ?? '')) !== '' ? $profile->nama_perusahaan : '-',
                'site' => trim((string) ($profile->site ?? '')) !== '' ? $profile->site : '-',
                'departemen' => trim((string) ($profile->divisi ?? '')) !== '' ? $profile->divisi : '-',
                'has_mcu' => $mcu !== null,
                'has_nutrition' => $hasNutrition,
                'exam_date' => $mcu['exam_date'] ?? null,
                'conditions' => $mcu['conditions'] ?? $emptyConditions,
                'avg_calories' => $hasNutrition ? $nutrition['avg_calories'] : null,
                'target_calories' => $hasNutrition ? self::DEFAULT_CALORIE_TARGET : null,
                'avg_carbs_g' => $hasNutrition ? $nutrition['avg_carbs_g'] : null,
                'avg_protein_g' => $hasNutrition ? $nutrition['avg_protein_g'] : null,
                'logging_days_30d' => $hasNutrition ? $nutrition['days_logged'] : null,
            ];
        }

        return $records;
    }

    /**
     * Satu baris per SID (data MCU terbaru), kondisi diambil dari JSONB
     * kondisi_kritis + kondisi_non_kritis.
     *
     * @return array<string, array{exam_date: ?string, conditions: array<string, bool>}>
     */
    private function fetchMcuConditions(): array
    {
        if (! $this->mcu->isUp() || ! $this->mcu->isMappingReady()) {
            return [];
        }

        $table = (string) config('bemcu.table');
        $examDate = (string) config('bemcu.exam_date');
        $sidCol = trim((string) config('bemcu.identity.sid', ''));
        if ($table === '' || $examDate === '' || $sidCol === '') {
            return [];
        }

        $sql = 'SELECT UPPER(TRIM('.$sidCol.')) AS sid, '.$examDate.' AS exam_date, kondisi_kritis, kondisi_non_kritis'
            .' FROM ('
            .' SELECT DISTINCT ON (UPPER(TRIM('.$sidCol.'))) '.$sidCol.', '.$examDate.', kondisi_kritis, kondisi_non_kritis'
            .' FROM '.$table
            .' WHERE '.$sidCol.' IS NOT NULL AND TRIM('.$sidCol.") <> ''"
            .' ORDER BY UPPER(TRIM('.$sidCol.')), '.$examDate.' DESC NULLS LAST'
            .' ) latest';

        try {
            $rows = DB::connection(McuConnectionService::CONNECTION)->select($sql);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $sid = trim((string) ($row->sid ?? ''));
            if ($sid === '') {
                continue;
            }

            $items = array_merge(
                $this->decodeJson($row->kondisi_kritis ?? null),
                $this->decodeJson($row->kondisi_non_kritis ?? null),
            );

            $flags = [
                'dislipidemia' => false, 'hipertensi' => false, 'gula_darah' => false,
                'sindrom_metabolik' => false, 'framingham' => false,
            ];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $nama = trim((string) ($item['nama_kondisi'] ?? ''));
                if ($nama === '' || ! $this->isAbnormal($item)) {
                    continue;
                }

                if (in_array($nama, self::DISLIPIDEMIA_NAMES, true)) {
                    $flags['dislipidemia'] = true;
                }
                foreach (self::CONDITION_NAME_MAP as $key => $targetName) {
                    if ($nama === $targetName) {
                        $flags[$key] = true;
                    }
                }
            }

            $out[$sid] = [
                'exam_date' => $row->exam_date !== null ? (string) $row->exam_date : null,
                'conditions' => $flags,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJson(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * kondisi_kritis pakai flag is_yes/is_no/is_na; kondisi_non_kritis cuma
     * punya 'note' ('normal'/'abnormal') tanpa flag is_yes/is_no/is_na.
     *
     * @param  array<string, mixed>  $item
     */
    private function isAbnormal(array $item): bool
    {
        if (array_key_exists('is_yes', $item) || array_key_exists('is_no', $item) || array_key_exists('is_na', $item)) {
            return ! empty($item['is_yes']);
        }

        return mb_strtolower(trim((string) ($item['note'] ?? ''))) === 'abnormal';
    }

    /**
     * @return Collection<string, object> keyed by UPPER(kode_sid)
     */
    private function fetchActiveEmployees(): Collection
    {
        if (! $this->bewell->isUp()) {
            return collect();
        }

        try {
            $rows = DB::connection(BewellConnectionService::CONNECTION)
                ->table('employee_profiles')
                ->where('status_karyawan', 'AKTIF')
                ->whereNotNull('kode_sid')
                ->where('kode_sid', '!=', '')
                ->get(['id', 'nama', 'nik', 'kode_sid', 'site', 'nama_perusahaan', 'divisi']);
        } catch (Throwable $e) {
            report($e);

            return collect();
        }

        $out = [];
        foreach ($rows as $row) {
            $sid = mb_strtoupper(trim((string) ($row->kode_sid ?? '')));
            if ($sid === '' || isset($out[$sid])) {
                continue;
            }
            $out[$sid] = $row;
        }

        return collect($out);
    }

    /**
     * Rata-rata kalori/karbohidrat/protein per user selama 30 hari terakhir.
     * avg_fat_g/avg_fiber_g/avg_sodium_mg SENGAJA tidak disertakan — belum
     * terverifikasi ada sebagai kolom di `food_analyses` (lihat juga catatan
     * di NutritionEvaluationService yang cuma pakai total_calories/carbs_g/
     * protein_g untuk hal yang sama).
     *
     * @param  list<int>  $userIds
     * @return array<int, array{days_logged:int, avg_calories:float, avg_carbs_g:float, avg_protein_g:float}>
     */
    private function fetchNutritionAverages(array $userIds): array
    {
        if ($userIds === [] || ! $this->bewell->isUp()) {
            return [];
        }

        $from = Carbon::now()->subDays(self::NUTRITION_WINDOW_DAYS)->startOfDay()->format('Y-m-d H:i:s');
        $to = Carbon::now()->endOfDay()->format('Y-m-d H:i:s');

        $dayRows = collect();
        foreach (array_chunk($userIds, 1000) as $chunk) {
            try {
                $rows = DB::connection(BewellConnectionService::CONNECTION)
                    ->table('food_analyses')
                    ->selectRaw('user_id, DATE(created_at) AS d, COALESCE(SUM(total_calories), 0) AS cal, COALESCE(SUM(carbs_g), 0) AS carb, COALESCE(SUM(protein_g), 0) AS prot')
                    ->whereIn('user_id', $chunk)
                    ->whereBetween('created_at', [$from, $to])
                    ->groupByRaw('user_id, DATE(created_at)')
                    ->get();
                $dayRows = $dayRows->concat($rows);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $out = [];
        foreach ($dayRows->groupBy('user_id') as $uid => $rows) {
            $out[(int) $uid] = [
                'days_logged' => $rows->count(),
                'avg_calories' => round((float) $rows->avg('cal'), 1),
                'avg_carbs_g' => round((float) $rows->avg('carb'), 1),
                'avg_protein_g' => round((float) $rows->avg('prot'), 1),
            ];
        }

        return $out;
    }
}
