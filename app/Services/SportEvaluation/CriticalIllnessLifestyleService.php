<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Riwayat penyakit kritis TERKONFIRMASI per karyawan (read-only ClickHouse
 * `behealth.clean_data_konfirmasi_penyakit_kritis`, koneksi `clickhouse_obds`
 * 10.42.7.39 — lihat RfidClickHouseClient), dipasangkan dengan:
 * - Olahraga: `behealth.clean_data_data_well_being` (sumber data sama, join by SID).
 * - Makan: BeWell `food_analyses` lewat NutritionEvaluationService (tidak ada
 *   tabel pola makan di behealth).
 *
 * Dipakai oleh tab "Riwayat Penyakit Kritis" di /evaluasi-well/health-nutrition.
 */
final class CriticalIllnessLifestyleService
{
    private const CACHE_KEY = 'evaluasi_well:health_nutrition:critical_illness:v1';

    private const CACHE_TTL = 600;

    private const DIET_WINDOW_DAYS = 30;

    /**
     * Variasi huruf besar/kecil YANG SUDAH TERKONFIRMASI merujuk kondisi yang
     * sama (mis. "HIPERTENSI" vs "Hipertensi") — bukan taksonomi penyakit,
     * jangan digabung lebih lanjut (mis. "DM&Prediabet" vs "Diabetes
     * Melitus") karena belum ada kategori resmi dari tim medis.
     *
     * @var array<string, string>
     */
    private const CASE_ALIASES = [
        'hipertensi' => 'Hipertensi',
    ];

    public function __construct(
        private readonly RfidClickHouseClient $clickhouse,
        private readonly BewellConnectionService $bewell,
        private readonly NutritionEvaluationService $nutrition,
        private readonly SportEvaluationKaryawanWellSiteResolver $siteResolver,
    ) {}

    public function isUp(): bool
    {
        return $this->clickhouse->up() && $this->bewell->isUp();
    }

    /**
     * @return array{
     *     up:bool,
     *     kpi:array<string,int|float>,
     *     byCondition:list<array<string,mixed>>,
     *     filterOptions:array{sites:list<string>,companies:list<string>,conditions:list<string>}
     * }
     */
    public function summary(): array
    {
        $data = $this->dataset();
        $rows = $data['rows'];
        $total = count($rows);

        $withExercise = count(array_filter($rows, static fn (array $r): bool => $r['exercise']['has_data']));
        $withDiet = count(array_filter($rows, static fn (array $r): bool => $r['diet']['has_data']));
        $poorDiet = count(array_filter($rows, static fn (array $r): bool => $r['diet']['has_poor_diet']));

        $byCondition = [];
        foreach ($rows as $row) {
            foreach ($row['conditions'] as $condition) {
                $byCondition[$condition] = ($byCondition[$condition] ?? 0) + 1;
            }
        }
        arsort($byCondition);

        $byConditionList = [];
        foreach ($byCondition as $label => $count) {
            $byConditionList[] = [
                'label' => $label,
                'total' => $count,
                'pct' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
            ];
        }

        return [
            'up' => $this->isUp(),
            'kpi' => [
                'total_karyawan' => $total,
                'raw_sid_total' => $data['raw_sid_total'],
                'with_exercise' => $withExercise,
                'with_exercise_pct' => $total > 0 ? round($withExercise / $total * 100, 1) : 0.0,
                'with_diet' => $withDiet,
                'with_diet_pct' => $total > 0 ? round($withDiet / $total * 100, 1) : 0.0,
                'poor_diet' => $poorDiet,
                'poor_diet_pct' => $total > 0 ? round($poorDiet / $total * 100, 1) : 0.0,
            ],
            'byCondition' => $byConditionList,
            'filterOptions' => [
                'sites' => collect($rows)->pluck('site')->filter(static fn ($v): bool => $v !== '-')->unique()->sort()->values()->all(),
                'companies' => collect($rows)->pluck('perusahaan')->filter(static fn ($v): bool => $v !== '-')->unique()->sort()->values()->all(),
                'conditions' => array_keys($byCondition),
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function rows(): array
    {
        return $this->dataset()['rows'];
    }

    /**
     * @return array{rows:list<array<string,mixed>>, raw_sid_total:int}
     */
    private function dataset(): array
    {
        $empty = ['rows' => [], 'raw_sid_total' => 0];

        // Sengaja TIDAK di-gate oleh isUp() di sini: isUp() pakai ping cepat
        // (timeout 3-5 detik, di-cache 20 detik) yang gampang false-negative
        // kalau koneksi sedang sedikit lambat — sementara query asli di
        // fetchIllnessRows()/fetchExerciseRows() sudah punya timeout sendiri
        // (30 detik) + try/catch masing-masing. Kalau langsung di-gate di
        // sini, 1x ping lambat bikin seluruh fitur kosong selama 20 detik
        // walau query aslinya sebenarnya berhasil.
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn (): array => $this->buildDataset());
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @return array{rows:list<array<string,mixed>>, raw_sid_total:int}
     */
    private function buildDataset(): array
    {
        $illnessBySid = $this->fetchIllnessRows();
        if ($illnessBySid === []) {
            return ['rows' => [], 'raw_sid_total' => 0];
        }

        $profilesBySid = $this->mapEmployees(array_keys($illnessBySid));
        $matchedSids = $profilesBySid->keys()->all();

        $exerciseBySid = $this->fetchExerciseRows($matchedSids);

        $userIds = $profilesBySid->map(static fn (object $p): int => (int) $p->id)->values()->all();
        $dietByUser = $this->nutrition->evaluateDietRiskForUsers(
            $userIds,
            Carbon::now()->subDays(self::DIET_WINDOW_DAYS)->startOfDay(),
            Carbon::now()->endOfDay(),
        );

        $rows = [];
        foreach ($matchedSids as $sid) {
            $profile = $profilesBySid->get($sid);
            $illness = $illnessBySid[$sid];
            $exercise = $exerciseBySid[$sid] ?? null;
            $uid = (int) $profile->id;
            $diet = $dietByUser[$uid] ?? null;

            $rows[] = [
                'id' => $uid,
                'nama' => (string) ($profile->nama ?: 'User #'.$uid),
                'nik' => (string) ($profile->nik ?: '-'),
                'kode_sid' => $sid,
                'perusahaan' => trim((string) ($profile->nama_perusahaan ?? '')) !== '' ? $profile->nama_perusahaan : '-',
                'site' => $this->siteResolver->resolveOrDash(
                    isset($profile->kode_sid) ? (string) $profile->kode_sid : null,
                    isset($profile->site) ? (string) $profile->site : null,
                ),
                'divisi' => trim((string) ($profile->divisi ?? '')) !== '' ? $profile->divisi : '-',
                'conditions' => $illness['conditions'],
                'tanggal_terkonfirmasi' => $illness['tanggal_terkonfirmasi'],
                'tindakan' => $illness['tindakan_terakhir'],
                'exercise' => [
                    'has_data' => $exercise !== null,
                    'log_count' => $exercise['log_count'] ?? 0,
                    'avg_durasi_menit' => $exercise['avg_durasi_menit'] ?? null,
                    'avg_langkah' => $exercise['avg_langkah'] ?? null,
                    'avg_kalori' => $exercise['avg_kalori'] ?? null,
                    'avg_denyut_jantung' => $exercise['avg_denyut_jantung'] ?? null,
                    'aktivitas_terakhir' => $exercise['aktivitas_terakhir'] ?? null,
                    'tanggal_terakhir' => $exercise['tanggal_terakhir'] ?? null,
                ],
                'diet' => [
                    'has_data' => $diet !== null && ($diet['days_logged'] ?? 0) > 0,
                    'days_logged' => $diet['days_logged'] ?? 0,
                    'avg_calories' => $diet['avg_calories'] ?? 0.0,
                    'has_poor_diet' => $diet['has_poor_diet'] ?? false,
                    'alert_codes' => $diet['alert_codes'] ?? [],
                ],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['nama'], (string) $b['nama']));
        foreach ($rows as $i => &$row) {
            $row['no'] = $i + 1;
        }
        unset($row);

        return ['rows' => $rows, 'raw_sid_total' => count($illnessBySid)];
    }

    /**
     * Satu baris per SID yang PERNAH terkonfirmasi ≥1 penyakit kritis (bukan
     * "Tidak Ada") — kondisi diambil dari SELURUH riwayat (groupUniqArray),
     * bukan hanya record terbaru, supaya "riwayat" benar-benar riwayat.
     *
     * @return array<string, array{conditions:list<string>, tanggal_terkonfirmasi:?string, tindakan_terakhir:?string}>
     */
    private function fetchIllnessRows(): array
    {
        $sql = "SELECT
                upper(trim(sid)) AS sid,
                groupUniqArray(penyakit_terkonfirmasi) AS penyakit_list,
                max(tanggal_terkonfirmasi_penyakit_kritis) AS tanggal_terkonfirmasi,
                argMax(tindakan, uploaded_at) AS tindakan_terakhir
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

            $conditions = [];
            foreach ((array) ($row['penyakit_list'] ?? []) as $label) {
                $canon = $this->canonicalizeCondition((string) $label);
                if ($canon !== '' && ! in_array($canon, $conditions, true)) {
                    $conditions[] = $canon;
                }
            }
            if ($conditions === []) {
                continue;
            }

            $tanggal = trim((string) ($row['tanggal_terkonfirmasi'] ?? ''));
            $tindakan = trim((string) ($row['tindakan_terakhir'] ?? ''));

            $out[$sid] = [
                'conditions' => $conditions,
                'tanggal_terkonfirmasi' => $tanggal !== '' ? $tanggal : null,
                'tindakan_terakhir' => $tindakan !== '' ? $tindakan : null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $upperSids
     * @return array<string, array{log_count:int, avg_durasi_menit:float, avg_langkah:float, avg_kalori:float, avg_denyut_jantung:float, aktivitas_terakhir:?string, tanggal_terakhir:?string}>
     */
    private function fetchExerciseRows(array $upperSids): array
    {
        if ($upperSids === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($upperSids, 800) as $chunk) {
            $quoted = implode(',', array_map(static fn (string $s): string => RfidClickHouseClient::quote($s), $chunk));
            $sql = "SELECT
                    upper(trim(sid)) AS sid,
                    count() AS log_count,
                    avg(toFloat64OrZero(ifNull(durasi__menit_, '0'))) AS avg_durasi_menit,
                    avg(toFloat64OrZero(ifNull(jumlah_langkah, '0'))) AS avg_langkah,
                    avg(toFloat64OrZero(ifNull(aktivitas_kalori__kkal_, '0'))) AS avg_kalori,
                    avg(toFloat64OrZero(ifNull(rata_rata_denyut_jantung__bpm_, '0'))) AS avg_denyut_jantung,
                    argMax(aktivitas_olahraga, uploaded_at) AS aktivitas_terakhir,
                    argMax(jadwal_aktivitas, uploaded_at) AS tanggal_terakhir
                FROM behealth.clean_data_data_well_being FINAL
                WHERE upper(trim(sid)) IN ({$quoted})
                GROUP BY sid";

            try {
                $rawRows = $this->clickhouse->select($sql, 30);
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            foreach ($rawRows as $row) {
                $sid = trim((string) ($row['sid'] ?? ''));
                if ($sid === '') {
                    continue;
                }

                $aktivitas = trim((string) ($row['aktivitas_terakhir'] ?? ''));
                $tanggal = trim((string) ($row['tanggal_terakhir'] ?? ''));

                $out[$sid] = [
                    'log_count' => (int) ($row['log_count'] ?? 0),
                    'avg_durasi_menit' => round((float) ($row['avg_durasi_menit'] ?? 0), 1),
                    'avg_langkah' => round((float) ($row['avg_langkah'] ?? 0)),
                    'avg_kalori' => round((float) ($row['avg_kalori'] ?? 0)),
                    'avg_denyut_jantung' => round((float) ($row['avg_denyut_jantung'] ?? 0)),
                    'aktivitas_terakhir' => $aktivitas !== '' ? $aktivitas : null,
                    'tanggal_terakhir' => $tanggal !== '' ? $tanggal : null,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $upperSids
     * @return Collection<string, object>  keyed by UPPER(kode_sid)
     */
    private function mapEmployees(array $upperSids): Collection
    {
        if ($upperSids === [] || ! $this->bewell->isUp()) {
            return collect();
        }

        $db = DB::connection(BewellConnectionService::CONNECTION);
        $profiles = collect();

        foreach (array_chunk($upperSids, 800) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $rows = $db->table('employee_profiles')
                ->where('status_karyawan', 'AKTIF')
                ->whereRaw('UPPER(TRIM(kode_sid)) IN ('.$placeholders.')', $chunk)
                ->get(['id', 'nama', 'nik', 'kode_sid', 'site', 'nama_perusahaan', 'divisi']);
            $profiles = $profiles->concat($rows);
        }

        return $profiles->keyBy(static fn (object $p): string => mb_strtoupper(trim((string) ($p->kode_sid ?? ''))));
    }

    private function canonicalizeCondition(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '' || mb_strtolower($trimmed) === 'tidak ada') {
            return '';
        }

        return self::CASE_ALIASES[mb_strtolower($trimmed)] ?? $trimmed;
    }
}
