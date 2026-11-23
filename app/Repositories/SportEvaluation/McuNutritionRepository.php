<?php

declare(strict_types=1);

namespace App\Repositories\SportEvaluation;

use App\Services\SportEvaluation\BewellConnectionService;
use App\Services\SportEvaluation\RfidClickHouseClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sumber data REAL untuk dashboard "MCU x Nutrisi" — nama halaman tetap
 * "MCU" (tidak diganti, di luar scope), tapi sisi "kondisi kesehatan" di
 * sini SEKARANG diambil dari riwayat penyakit kritis TERKONFIRMASI
 * ClickHouse (`behealth.clean_data_konfirmasi_penyakit_kritis`, via
 * `RfidClickHouseClient`), BUKAN MCU Postgres (`bcsid.mv_ftw_mcu`).
 *
 * Kenapa pindah dari MCU Postgres: kredensial koneksi `pgsql_ssh` di
 * server produksi tidak valid (password authentication failed,
 * dikonfirmasi langsung ke RDS) dan belum ada yang bisa memberi kredensial
 * yang benar — atas arahan user, sisi kondisi kesehatan diganti ke sumber
 * yang SUDAH terbukti jalan (sama persis dengan yang dipakai tab "Riwayat
 * Penyakit Kritis", lihat CriticalIllnessLifestyleService).
 *
 * Kategori kondisi TIDAK di-hardcode lagi (dulu 5 kategori tetap dari MCU)
 * — sekarang dihitung dinamis: 6 label penyakit TERKONFIRMASI paling
 * sering muncul di antara karyawan AKTIF, apa adanya dari ClickHouse,
 * tanpa taksonomi buatan sendiri.
 */
final class McuNutritionRepository
{
    private const CACHE_KEY = 'evaluasi_well:health_nutrition:mcu_records:v2';

    private const CACHE_TTL = 1800;

    private const DEFAULT_CALORIE_TARGET = 2300.0;

    private const NUTRITION_WINDOW_DAYS = 30;

    private const TOP_CONDITION_COUNT = 6;

    /**
     * Variasi huruf besar/kecil yang sudah terkonfirmasi kondisi sama —
     * SAMA PERSIS dengan CASE_ALIASES di CriticalIllnessLifestyleService,
     * sengaja duplikat kecil (bukan di-share) supaya 2 fitur ini independen.
     *
     * @var array<string, string>
     */
    private const CASE_ALIASES = [
        'hipertensi' => 'Hipertensi',
    ];

    public function __construct(
        private readonly RfidClickHouseClient $clickhouse,
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
        return $this->dataset()['records'];
    }

    /**
     * 6 label penyakit terkonfirmasi paling sering muncul (di antara
     * karyawan AKTIF), urut dari yang paling banyak.
     *
     * @return list<string>
     */
    public function conditionLabels(): array
    {
        return $this->dataset()['condition_labels'];
    }

    /**
     * @return array{records: list<array<string, mixed>>, condition_labels: list<string>}
     */
    private function dataset(): array
    {
        $empty = ['records' => [], 'condition_labels' => []];

        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn (): array => $this->buildDataset());
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @return array{records: list<array<string, mixed>>, condition_labels: list<string>}
     */
    private function buildDataset(): array
    {
        $profiles = $this->fetchActiveEmployees();
        if ($profiles->isEmpty()) {
            return ['records' => [], 'condition_labels' => []];
        }

        $illnessBySid = $this->fetchCriticalIllnessConditions();
        $conditionLabels = $this->topConditionLabels($illnessBySid, $profiles);

        $userIds = $profiles->map(static fn (object $p): int => (int) $p->id)->values()->all();
        $nutritionByUser = $this->fetchNutritionAverages($userIds);

        $records = [];
        foreach ($profiles as $sid => $profile) {
            $uid = (int) $profile->id;
            $illness = $illnessBySid[$sid] ?? null;
            $nutrition = $nutritionByUser[$uid] ?? null;
            $hasNutrition = $nutrition !== null && $nutrition['days_logged'] > 0;

            $conditions = [];
            foreach ($conditionLabels as $label) {
                $conditions[$label] = $illness !== null && in_array($label, $illness['labels'], true);
            }

            $records[] = [
                'id' => $uid,
                'nama' => (string) ($profile->nama ?: 'User #'.$uid),
                'nik' => (string) ($profile->nik ?: '-'),
                'kode_sid' => $sid,
                'perusahaan' => trim((string) ($profile->nama_perusahaan ?? '')) !== '' ? $profile->nama_perusahaan : '-',
                'site' => trim((string) ($profile->site ?? '')) !== '' ? $profile->site : '-',
                'departemen' => trim((string) ($profile->divisi ?? '')) !== '' ? $profile->divisi : '-',
                'has_mcu' => $illness !== null,
                'has_nutrition' => $hasNutrition,
                'exam_date' => $illness['confirmed_date'] ?? null,
                'conditions' => $conditions,
                'avg_calories' => $hasNutrition ? $nutrition['avg_calories'] : null,
                'target_calories' => $hasNutrition ? self::DEFAULT_CALORIE_TARGET : null,
                'avg_carbs_g' => $hasNutrition ? $nutrition['avg_carbs_g'] : null,
                'avg_protein_g' => $hasNutrition ? $nutrition['avg_protein_g'] : null,
                'logging_days_30d' => $hasNutrition ? $nutrition['days_logged'] : null,
            ];
        }

        return ['records' => $records, 'condition_labels' => $conditionLabels];
    }

    /**
     * Satu baris per SID — SELURUH riwayat (groupUniqArray), bukan cuma
     * record terbaru, supaya "riwayat" benar-benar riwayat (sama seperti
     * CriticalIllnessLifestyleService::fetchIllnessRows()).
     *
     * @return array<string, array{confirmed_date: ?string, labels: list<string>}>
     */
    private function fetchCriticalIllnessConditions(): array
    {
        if (! $this->clickhouse->up()) {
            return [];
        }

        $sql = "SELECT
                upper(trim(sid)) AS sid,
                groupUniqArray(penyakit_terkonfirmasi) AS penyakit_list,
                max(tanggal_terkonfirmasi_penyakit_kritis) AS tanggal_terkonfirmasi
            FROM behealth.clean_data_konfirmasi_penyakit_kritis FINAL
            WHERE sid IS NOT NULL AND trim(sid) != ''
              AND penyakit_terkonfirmasi IS NOT NULL
              AND lower(trim(penyakit_terkonfirmasi)) NOT IN ('tidak ada', '')
            GROUP BY sid";

        try {
            $rawRows = $this->clickhouse->select($sql, 30);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $out = [];
        foreach ($rawRows as $row) {
            $sid = trim((string) ($row['sid'] ?? ''));
            if ($sid === '') {
                continue;
            }

            $labels = [];
            foreach ((array) ($row['penyakit_list'] ?? []) as $label) {
                $canon = $this->canonicalizeCondition((string) $label);
                if ($canon !== '' && ! in_array($canon, $labels, true)) {
                    $labels[] = $canon;
                }
            }
            if ($labels === []) {
                continue;
            }

            $tanggal = trim((string) ($row['tanggal_terkonfirmasi'] ?? ''));
            $out[$sid] = [
                'confirmed_date' => $tanggal !== '' ? $tanggal : null,
                'labels' => $labels,
            ];
        }

        return $out;
    }

    private function canonicalizeCondition(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '' || mb_strtolower($trimmed) === 'tidak ada') {
            return '';
        }

        return self::CASE_ALIASES[mb_strtolower($trimmed)] ?? $trimmed;
    }

    /**
     * @param  array<string, array{confirmed_date: ?string, labels: list<string>}>  $illnessBySid
     * @return list<string>
     */
    private function topConditionLabels(array $illnessBySid, Collection $profiles): array
    {
        $counts = [];
        foreach ($profiles as $sid => $profile) {
            $illness = $illnessBySid[$sid] ?? null;
            if ($illness === null) {
                continue;
            }
            foreach ($illness['labels'] as $label) {
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
        }

        arsort($counts);

        return array_slice(array_keys($counts), 0, self::TOP_CONDITION_COUNT);
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
     * terverifikasi ada sebagai kolom di `food_analyses`.
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
