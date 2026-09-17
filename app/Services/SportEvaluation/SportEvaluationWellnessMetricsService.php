<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Agregasi metrik wellness dashboard: Durasi, Intensitas, Frekuensi, Kalori, Makronutrien.
 * Read-only dari bewell_db; minggu kalender Minggu–Sabtu.
 */
final class SportEvaluationWellnessMetricsService
{
    private const CACHE_TTL = 300;

    private const CACHE_VERSION = 'v2';

    private const CHUNK_SIZE = 500;

    private const TREND_WEEKS = 12;

    private const WEEK_START_DAY = Carbon::SUNDAY;

    private const WEEK_END_DAY = Carbon::SATURDAY;

    private const HR_LOW_MAX = 119.0;

    private const HR_MED_MAX = 149.0;

    private const DEFAULT_CALORIE_TARGET = 2000.0;

    private const DEFAULT_PROTEIN_TARGET = 75.0;

    private const DEFAULT_CARB_TARGET = 250.0;

    private const DEFAULT_FAT_TARGET = 70.0;

    private const DEFAULT_FIBER_TARGET = 25.0;

    /** Default target kalori harian untuk kolom "Target Kalori" saat karyawan belum punya goal aktif. */
    private const TARGET_KALORI_DEFAULT = 2300.0;

    private const DURATION_ADEQUATE_MIN = 150.0;

    private const FREQUENCY_ADEQUATE_DAYS = 5;

    public function __construct(
        private readonly BewellConnectionService $connection,
        private readonly WorkoutMetricParser $parser,
        private readonly SportEvaluationEmployeeExclusionRules $exclusionRules,
        private readonly SportEvaluationKaryawanWellSiteResolver $siteResolver,
        private readonly SportEvaluationMitraAssignmentService $mitraAssignmentService,
    ) {}

    /**
     * Payload KPI SSR untuk dashboard (+ opsi minggu filter).
     *
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    public function getDashboardPayload(
        array $scope = [],
        ?string $weekStart = null,
        string $site = '',
        string $company = '',
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $week = $this->resolveRange($dateFrom, $dateTo, $weekStart);
        $empty = $this->emptyDashboardPayload($week);

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            $scope = $this->normalizeScopeFilters($scope);
            $site = trim($site);
            $company = trim($company);
            $scopeKey = $this->mitraAssignmentService->cacheKeySuffix($scope);
            $cacheKey = 'evaluasi_well:wellness_metrics:dash:'.self::CACHE_VERSION.':'.sha1(
                $week['start'].'|'.$week['end'].'|'.$scopeKey.'|'.$site.'|'.$company
            );

            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($week, $scope, $site, $company): array {
                $current = $this->aggregateWeekMetrics($week['start'], $week['end'], $scope, $site, $company);
                $previous = $this->aggregateWeekMetrics($week['prev_start'], $week['prev_end'], $scope, $site, $company);

                return array_merge(
                    $this->mapMetricsToCardPayload($current, $previous),
                    [
                        'wellnessWeek' => $week,
                        'wellnessWeekOptions' => $this->buildWeekOptions(),
                        'wellnessSites' => $this->filterSites($scope),
                        'wellnessCompanies' => $this->filterCompanies($scope),
                        'wellnessUserCount' => $current['active_users'],
                        'wellnessCharts' => $this->getDistributionCharts($week, $scope, $site, $company),
                    ]
                );
            });
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * KPI JSON saat ganti minggu / filter di UI.
     *
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    public function getKpiPayload(
        array $scope = [],
        ?string $weekStart = null,
        string $site = '',
        string $company = '',
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $payload = $this->getDashboardPayload($scope, $weekStart, $site, $company, $dateFrom, $dateTo);

        return [
            'available' => $this->connection->isUp(),
            'week' => $payload['wellnessWeek'],
            'user_count' => $payload['wellnessUserCount'],
            'durasi_total_minutes' => $payload['wellnessDurasiTotal'],
            'durasi_increase' => $payload['wellnessDurasiIncrease'],
            'durasi_increase_percent' => $payload['wellnessDurasiIncreasePercent'],
            'intensitas_avg_hr' => $payload['wellnessIntensitasAvgHr'],
            'intensitas_increase' => $payload['wellnessIntensitasIncrease'],
            'intensitas_increase_percent' => $payload['wellnessIntensitasIncreasePercent'],
            'intensitas_low' => $payload['wellnessIntensitasLow'],
            'intensitas_med' => $payload['wellnessIntensitasMed'],
            'intensitas_high' => $payload['wellnessIntensitasHigh'],
            'frekuensi_total' => $payload['wellnessFrekuensiTotal'],
            'frekuensi_increase' => $payload['wellnessFrekuensiIncrease'],
            'frekuensi_increase_percent' => $payload['wellnessFrekuensiIncreasePercent'],
            'kalori_out' => $payload['wellnessKaloriOut'],
            'kalori_in' => $payload['wellnessKaloriIn'],
            'kalori_increase' => $payload['wellnessKaloriIncrease'],
            'kalori_increase_percent' => $payload['wellnessKaloriIncreasePercent'],
            'makro_protein' => $payload['wellnessMakroProtein'],
            'makro_carbs' => $payload['wellnessMakroCarbs'],
            'makro_fats' => $payload['wellnessMakroFats'],
            'makro_increase' => $payload['wellnessMakroIncrease'],
            'makro_increase_percent' => $payload['wellnessMakroIncreasePercent'],
            'charts' => $payload['wellnessCharts'] ?? $this->emptyChartsPayload(),
        ];
    }

    /**
     * DataTables server-side per karyawan.
     *
     * @param  array<string, mixed>  $scope
     * @return array{draw:int, recordsTotal:int, recordsFiltered:int, data:list<array<string, mixed>>}
     */
    public function datatable(
        int $draw,
        int $start,
        int $length,
        string $search,
        int $orderColumnIndex,
        string $orderDir,
        array $scope = [],
        ?string $weekStart = null,
        string $site = '',
        string $company = '',
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $empty = [
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ];

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            $week = $this->resolveRange($dateFrom, $dateTo, $weekStart);
            $scope = $this->normalizeScopeFilters($scope);
            $from = $week['start'].' 00:00:00';
            $to = Carbon::parse($week['end'])->endOfDay()->format('Y-m-d H:i:s');

            $base = $this->usersWithActivityBaseQuery($from, $to, $scope, $site, $company);
            $recordsTotal = (int) (clone $base)->count();

            $filtered = clone $base;
            if ($search !== '') {
                $like = '%'.$search.'%';
                $filtered->where(function (Builder $q) use ($like): void {
                    $q->where('e.nama', 'like', $like)
                        ->orWhere('e.nama_perusahaan', 'like', $like)
                        ->orWhere('e.jabatan_fungsional', 'like', $like)
                        ->orWhere('e.site', 'like', $like)
                        ->orWhere('e.kode_sid', 'like', $like);
                });
            }
            $recordsFiltered = (int) (clone $filtered)->count();

            // Kolom index 10 (Target Kalori) sengaja tidak ada di map ini karena
            // dihitung setelah query (bukan kolom SQL) — non-orderable di JS (columnDefs).
            $orderable = [
                0 => 'e.nama',
                1 => 'e.site',
                2 => 'e.nama_perusahaan',
                3 => 'e.jabatan_fungsional',
                7 => 'frekuensi',
                8 => 'kalori_out',
                9 => 'kalori_in',
                11 => 'protein_g',
                12 => 'carbs_g',
                13 => 'fats_g',
            ];
            $orderCol = $orderable[$orderColumnIndex] ?? 'e.nama';
            $dir = strtolower($orderDir) === 'desc' ? 'desc' : 'asc';

            $rows = (clone $filtered)
                ->orderBy($orderCol, $dir)
                ->orderBy('e.nama')
                ->offset(max(0, $start))
                ->limit(max(1, min(100, $length)))
                ->get();

            $userIds = $rows->pluck('user_id')->map(static fn ($id): int => (int) $id)->all();
            $parsed = $this->parsedWorkoutMetricsForUsers($userIds, $from, $to);
            $targets = $this->loadCalorieTargetTotals($userIds, $week['start'], $week['end']);

            $data = [];
            foreach ($rows as $row) {
                $userId = (int) $row->user_id;
                $metrics = $parsed[$userId] ?? ['duration_minutes' => 0.0, 'avg_hr' => null, 'hr_samples' => 0];
                $avgHr = $metrics['avg_hr'];
                $kaloriIn = round((float) ($row->kalori_in ?? 0), 1);
                $targetKalori = round((float) ($targets[$userId] ?? self::TARGET_KALORI_DEFAULT), 1);
                $data[] = [
                    'id' => $userId,
                    'nama' => (string) ($row->nama ?: 'User #'.$userId),
                    'site' => $this->siteResolver->resolveOrDash(
                        isset($row->kode_sid) ? (string) $row->kode_sid : null,
                        isset($row->site) ? (string) $row->site : null,
                    ),
                    'perusahaan' => $this->displayOrDash($row->nama_perusahaan ?? null),
                    'jabatan' => $this->displayOrDash($row->jabatan_fungsional ?? null),
                    'durasi_minutes' => round((float) $metrics['duration_minutes'], 1),
                    'avg_hr' => $avgHr !== null ? round((float) $avgHr, 1) : null,
                    'intensitas' => $this->intensityLabel($avgHr !== null ? (float) $avgHr : null),
                    'frekuensi' => (int) ($row->frekuensi ?? 0),
                    'kalori_out' => round((float) ($row->kalori_out ?? 0), 1),
                    'kalori_in' => $kaloriIn,
                    'target_kalori' => $targetKalori,
                    'kalori_progress_pct' => $this->calorieProgressPercent($kaloriIn, $targetKalori),
                    'protein_g' => round((float) ($row->protein_g ?? 0), 1),
                    'carbs_g' => round((float) ($row->carbs_g ?? 0), 1),
                    'fats_g' => round((float) ($row->fats_g ?? 0), 1),
                ];
            }

            return [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * Baris export Excel.
     *
     * @param  array<string, mixed>  $scope
     * @return array{
     *     week: array{start: string, end: string, label: string, prev_start: string},
     *     rows: list<array<string, mixed>>
     * }
     */
    public function exportRows(
        array $scope = [],
        ?string $weekStart = null,
        string $site = '',
        string $company = '',
        string $search = '',
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $week = $this->resolveRange($dateFrom, $dateTo, $weekStart);
        $empty = ['week' => $week, 'rows' => []];

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            $scope = $this->normalizeScopeFilters($scope);
            $from = $week['start'].' 00:00:00';
            $to = Carbon::parse($week['end'])->endOfDay()->format('Y-m-d H:i:s');

            $query = $this->usersWithActivityBaseQuery($from, $to, $scope, $site, $company);
            if ($search !== '') {
                $like = '%'.$search.'%';
                $query->where(function (Builder $q) use ($like): void {
                    $q->where('e.nama', 'like', $like)
                        ->orWhere('e.nama_perusahaan', 'like', $like)
                        ->orWhere('e.jabatan_fungsional', 'like', $like)
                        ->orWhere('e.site', 'like', $like)
                        ->orWhere('e.kode_sid', 'like', $like);
                });
            }

            $rows = [];
            $weekStartDate = $week['start'];
            $weekEndDate = $week['end'];
            $query->orderBy('e.nama')->chunk(self::CHUNK_SIZE, function ($chunk) use (&$rows, $from, $to, $weekStartDate, $weekEndDate): void {
                $userIds = $chunk->pluck('user_id')->map(static fn ($id): int => (int) $id)->all();
                $parsed = $this->parsedWorkoutMetricsForUsers($userIds, $from, $to);
                $targets = $this->loadCalorieTargetTotals($userIds, $weekStartDate, $weekEndDate);

                foreach ($chunk as $row) {
                    $userId = (int) $row->user_id;
                    $metrics = $parsed[$userId] ?? ['duration_minutes' => 0.0, 'avg_hr' => null];
                    $avgHr = $metrics['avg_hr'];
                    $kaloriIn = round((float) ($row->kalori_in ?? 0), 1);
                    $targetKalori = round((float) ($targets[$userId] ?? self::TARGET_KALORI_DEFAULT), 1);
                    $rows[] = [
                        'nama' => (string) ($row->nama ?: 'User #'.$userId),
                        'site' => $this->siteResolver->resolveOrDash(
                            isset($row->kode_sid) ? (string) $row->kode_sid : null,
                            isset($row->site) ? (string) $row->site : null,
                        ),
                        'perusahaan' => $this->displayOrDash($row->nama_perusahaan ?? null),
                        'jabatan' => $this->displayOrDash($row->jabatan_fungsional ?? null),
                        'durasi_minutes' => round((float) $metrics['duration_minutes'], 1),
                        'avg_hr' => $avgHr !== null ? round((float) $avgHr, 1) : null,
                        'intensitas' => $this->intensityLabel($avgHr !== null ? (float) $avgHr : null),
                        'frekuensi' => (int) ($row->frekuensi ?? 0),
                        'kalori_out' => round((float) ($row->kalori_out ?? 0), 1),
                        'kalori_in' => $kaloriIn,
                        'target_kalori' => $targetKalori,
                        'kalori_progress_pct' => $this->calorieProgressPercent($kaloriIn, $targetKalori),
                        'protein_g' => round((float) ($row->protein_g ?? 0), 1),
                        'carbs_g' => round((float) ($row->carbs_g ?? 0), 1),
                        'fats_g' => round((float) ($row->fats_g ?? 0), 1),
                    ];
                }
            });

            return ['week' => $week, 'rows' => $rows];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @return array{start: string, end: string, label: string, prev_start: string}
     */
    public function resolveWeek(?string $weekStart = null): array
    {
        try {
            $start = $weekStart !== null && $weekStart !== ''
                ? Carbon::parse($weekStart)->startOfWeek(self::WEEK_START_DAY)
                : Carbon::now()->startOfWeek(self::WEEK_START_DAY);
        } catch (Throwable) {
            $start = Carbon::now()->startOfWeek(self::WEEK_START_DAY);
        }

        $end = $start->copy()->endOfWeek(self::WEEK_END_DAY);
        $prevStart = $start->copy()->subWeek()->startOfWeek(self::WEEK_START_DAY);

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'label' => $start->format('d M').' – '.$end->format('d M Y'),
            'prev_start' => $prevStart->toDateString(),
        ];
    }

    /**
     * Resolve rentang tanggal custom (date_from/date_to) untuk filter "Detail Metrik
     * Wellness". Kalau salah satu kosong/tidak valid, fallback ke minggu (Minggu–Sabtu)
     * seperti sebelumnya supaya perilaku default tetap sama.
     *
     * @return array{start: string, end: string, label: string, prev_start: string, prev_end: string}
     */
    public function resolveRange(?string $dateFrom = null, ?string $dateTo = null, ?string $weekStart = null): array
    {
        $from = $this->parseDateOrNull($dateFrom);
        $to = $this->parseDateOrNull($dateTo);

        if ($from === null || $to === null) {
            $week = $this->resolveWeek($weekStart);
            $prevWeek = $this->resolveWeek($week['prev_start']);

            return [
                'start' => $week['start'],
                'end' => $week['end'],
                'label' => $week['label'],
                'prev_start' => $prevWeek['start'],
                'prev_end' => $prevWeek['end'],
            ];
        }

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $maxSpanDays = 366;
        if ($from->diffInDays($to) > $maxSpanDays) {
            $from = $to->copy()->subDays($maxSpanDays);
        }

        $daySpan = $from->diffInDays($to) + 1;
        $prevEnd = $from->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($daySpan - 1);

        return [
            'start' => $from->toDateString(),
            'end' => $to->toDateString(),
            'label' => $from->isSameDay($to)
                ? $from->format('d M Y')
                : $from->format('d M Y').' – '.$to->format('d M Y'),
            'prev_start' => $prevStart->toDateString(),
            'prev_end' => $prevEnd->toDateString(),
        ];
    }

    private function parseDateOrNull(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{start: string, label: string}>
     */
    public function buildWeekOptions(): array
    {
        $options = [];
        $now = Carbon::now();

        for ($i = 0; $i < self::TREND_WEEKS; $i++) {
            $start = $now->copy()->subWeeks($i)->startOfWeek(self::WEEK_START_DAY);
            $end = $start->copy()->endOfWeek(self::WEEK_END_DAY);
            $options[] = [
                'start' => $start->toDateString(),
                'label' => $start->format('d M').' – '.$end->format('d M Y'),
            ];
        }

        return $options;
    }

    /**
     * Label bucket intensitas dari avg HR.
     */
    public function intensityLabel(?float $avgHr): string
    {
        if ($avgHr === null || $avgHr <= 0) {
            return '-';
        }
        if ($avgHr <= self::HR_LOW_MAX) {
            return 'Low';
        }
        if ($avgHr <= self::HR_MED_MAX) {
            return 'Med';
        }

        return 'High';
    }

    /**
     * @param  array{
     *     duration_minutes: float,
     *     avg_hr: float|null,
     *     hr_low: int,
     *     hr_med: int,
     *     hr_high: int,
     *     frekuensi: int,
     *     kalori_out: float,
     *     kalori_in: float,
     *     protein_g: float,
     *     carbs_g: float,
     *     fats_g: float,
     *     active_users: int
     * }  $current
     * @param  array{
     *     duration_minutes: float,
     *     avg_hr: float|null,
     *     frekuensi: int,
     *     kalori_out: float,
     *     protein_g: float
     * }  $previous
     * @return array<string, mixed>
     */
    private function mapMetricsToCardPayload(array $current, array $previous): array
    {
        $durasi = (float) $current['duration_minutes'];
        $durasiPrev = (float) $previous['duration_minutes'];
        $durasiInc = max(0.0, $durasi - $durasiPrev);

        $hr = (float) ($current['avg_hr'] ?? 0);
        $hrPrev = (float) ($previous['avg_hr'] ?? 0);
        $hrInc = max(0.0, $hr - $hrPrev);

        $freq = (int) $current['frekuensi'];
        $freqPrev = (int) $previous['frekuensi'];
        $freqInc = max(0, $freq - $freqPrev);

        $kcalOut = (float) $current['kalori_out'];
        $kcalOutPrev = (float) $previous['kalori_out'];
        $kcalInc = max(0.0, $kcalOut - $kcalOutPrev);

        $protein = (float) $current['protein_g'];
        $proteinPrev = (float) $previous['protein_g'];
        $proteinInc = max(0.0, $protein - $proteinPrev);

        return [
            'wellnessDurasiTotal' => round($durasi, 1),
            'wellnessDurasiIncrease' => round($durasiInc, 1),
            'wellnessDurasiIncreasePercent' => $this->weekIncreasePercent($durasiInc, $durasi),
            'wellnessIntensitasAvgHr' => round($hr, 1),
            'wellnessIntensitasIncrease' => round($hrInc, 1),
            'wellnessIntensitasIncreasePercent' => $this->weekIncreasePercent($hrInc, $hr),
            'wellnessIntensitasLow' => (int) $current['hr_low'],
            'wellnessIntensitasMed' => (int) $current['hr_med'],
            'wellnessIntensitasHigh' => (int) $current['hr_high'],
            'wellnessFrekuensiTotal' => $freq,
            'wellnessFrekuensiIncrease' => $freqInc,
            'wellnessFrekuensiIncreasePercent' => $this->weekIncreasePercent((float) $freqInc, (float) $freq),
            'wellnessKaloriOut' => round($kcalOut, 1),
            'wellnessKaloriIn' => round((float) $current['kalori_in'], 1),
            'wellnessKaloriIncrease' => round($kcalInc, 1),
            'wellnessKaloriIncreasePercent' => $this->weekIncreasePercent($kcalInc, $kcalOut),
            'wellnessMakroProtein' => round($protein, 1),
            'wellnessMakroCarbs' => round((float) $current['carbs_g'], 1),
            'wellnessMakroFats' => round((float) $current['fats_g'], 1),
            'wellnessMakroIncrease' => round($proteinInc, 1),
            'wellnessMakroIncreasePercent' => $this->weekIncreasePercent($proteinInc, $protein),
            'wellnessUserCount' => (int) $current['active_users'],
        ];
    }

    /**
     * @param  array{start: string, end: string, label: string, prev_start: string}  $week
     * @return array<string, mixed>
     */
    private function emptyDashboardPayload(array $week): array
    {
        return [
            'wellnessDurasiTotal' => 0.0,
            'wellnessDurasiIncrease' => 0.0,
            'wellnessDurasiIncreasePercent' => 0.0,
            'wellnessIntensitasAvgHr' => 0.0,
            'wellnessIntensitasIncrease' => 0.0,
            'wellnessIntensitasIncreasePercent' => 0.0,
            'wellnessIntensitasLow' => 0,
            'wellnessIntensitasMed' => 0,
            'wellnessIntensitasHigh' => 0,
            'wellnessFrekuensiTotal' => 0,
            'wellnessFrekuensiIncrease' => 0,
            'wellnessFrekuensiIncreasePercent' => 0.0,
            'wellnessKaloriOut' => 0.0,
            'wellnessKaloriIn' => 0.0,
            'wellnessKaloriIncrease' => 0.0,
            'wellnessKaloriIncreasePercent' => 0.0,
            'wellnessMakroProtein' => 0.0,
            'wellnessMakroCarbs' => 0.0,
            'wellnessMakroFats' => 0.0,
            'wellnessMakroIncrease' => 0.0,
            'wellnessMakroIncreasePercent' => 0.0,
            'wellnessUserCount' => 0,
            'wellnessWeek' => $week,
            'wellnessWeekOptions' => $this->buildWeekOptions(),
            'wellnessSites' => [],
            'wellnessCompanies' => [],
            'wellnessCharts' => $this->emptyChartsPayload(),
        ];
    }

    /**
     * Chart distribusi di atas Detail Metrik Wellness.
     *
     * @param  array{start: string, end: string, label: string, prev_start: string}  $week
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    public function getDistributionCharts(
        array $week,
        array $scope = [],
        string $site = '',
        string $company = '',
    ): array {
        $empty = $this->emptyChartsPayload();
        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            $scope = $this->normalizeScopeFilters($scope);
            $site = trim($site);
            $company = trim($company);
            $from = $week['start'].' 00:00:00';
            $to = Carbon::parse($week['end'])->endOfDay()->format('Y-m-d H:i:s');
            $totalAktif = $this->countAktifEmployees($scope, $site, $company);

            return [
                'total_employees' => $totalAktif,
                'top_sports' => $this->buildTopSportsChart($from, $to, $scope, $site, $company, $totalAktif),
                'duration_buckets' => $this->buildDurationBuckets($from, $to, $scope, $site, $company, $totalAktif),
                'frequency_buckets' => $this->buildFrequencyBuckets($from, $to, $scope, $site, $company, $totalAktif),
                'calorie_buckets' => $this->buildCalorieBuckets($from, $to, $week['start'], $week['end'], $scope, $site, $company, $totalAktif),
                'macro_attainment' => $this->buildMacroAttainment($from, $to, $week['start'], $week['end'], $scope, $site, $company, $totalAktif),
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyChartsPayload(): array
    {
        return [
            'total_employees' => 0,
            'top_sports' => [],
            'duration_buckets' => [
                $this->bucketRow('≥150 menit/minggu', 0, 0),
                $this->bucketRow('<150 menit/minggu', 0, 0),
                $this->bucketRow('Tidak ada olahraga', 0, 0),
            ],
            'frequency_buckets' => [
                $this->bucketRow('5–7 hari/minggu', 0, 0),
                $this->bucketRow('<5 hari/minggu', 0, 0),
                $this->bucketRow('Tidak ada olahraga', 0, 0),
            ],
            'calorie_buckets' => [
                $this->bucketRow('Melebihi target kalori', 0, 0),
                $this->bucketRow('50–100% target kalori', 0, 0),
                $this->bucketRow('<50% target kalori', 0, 0),
                $this->bucketRow('Tidak ada kalori', 0, 0),
            ],
            'macro_attainment' => [
                $this->macroRow('Protein', 0, 0, true),
                $this->macroRow('Karbohidrat', 0, 0, true),
                $this->macroRow('Lemak', 0, 0, true),
                $this->macroRow('Serat', 0, 0, false),
            ],
        ];
    }

    /**
     * @return array{label: string, count: int, pct: float}
     */
    private function bucketRow(string $label, int $count, int $total): array
    {
        return [
            'label' => $label,
            'count' => $count,
            'pct' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * @return array{label: string, count: int, pct: float, available: bool}
     */
    private function macroRow(string $label, int $count, int $total, bool $available): array
    {
        return [
            'label' => $label,
            'count' => $available ? $count : 0,
            'pct' => ($available && $total > 0) ? round(($count / $total) * 100, 1) : 0.0,
            'available' => $available,
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function countAktifEmployees(array $scope, string $site = '', string $company = ''): int
    {
        return (int) $this->applyEmployeeFilters(
            $this->applyScopeToEmployees(
                $this->exclusionRules->applyToQuery(
                    DB::connection(BewellConnectionService::CONNECTION)
                        ->table('employee_profiles as e')
                        ->where('e.status_karyawan', 'AKTIF')
                ),
                $scope
            ),
            $site,
            $company
        )->count('e.id');
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return list<array{label: string, count: int, pct: float}>
     */
    private function buildTopSportsChart(
        string $from,
        string $to,
        array $scope,
        string $site,
        string $company,
        int $totalAktif,
    ): array {
        $jenisExpr = "CASE WHEN TRIM(COALESCE(w.activity_type, '')) = '' THEN 'Lainnya' ELSE w.activity_type END";
        $rows = $this->workoutBaseQuery($from, $to, $scope, $site, $company)
            ->selectRaw($jenisExpr.' as jenis')
            ->selectRaw('COUNT(DISTINCT w.user_id) as c')
            ->groupByRaw($jenisExpr)
            ->orderByDesc('c')
            ->limit(5)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $count = (int) ($row->c ?? 0);
            $out[] = [
                'label' => (string) ($row->jenis ?: 'Lainnya'),
                'count' => $count,
                'pct' => $totalAktif > 0 ? round(($count / $totalAktif) * 100, 1) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return list<array{label: string, count: int, pct: float}>
     */
    private function buildDurationBuckets(
        string $from,
        string $to,
        array $scope,
        string $site,
        string $company,
        int $totalAktif,
    ): array {
        $userIds = $this->workoutBaseQuery($from, $to, $scope, $site, $company)
            ->distinct()
            ->pluck('w.user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $parsed = $this->parsedWorkoutMetricsForUsers($userIds, $from, $to);
        $parsedBelowOrZero = 0;
        $parsedAdequate = 0;
        foreach ($userIds as $uid) {
            $minutes = (float) (($parsed[$uid]['duration_minutes'] ?? 0));
            if ($minutes >= self::DURATION_ADEQUATE_MIN) {
                $parsedAdequate++;
            } else {
                $parsedBelowOrZero++;
            }
        }

        $withWorkout = count($userIds);
        $none = max(0, $totalAktif - $withWorkout);

        return [
            $this->bucketRow('≥150 menit/minggu', $parsedAdequate, $totalAktif),
            $this->bucketRow('<150 menit/minggu', $parsedBelowOrZero, $totalAktif),
            $this->bucketRow('Tidak ada olahraga', $none, $totalAktif),
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return list<array{label: string, count: int, pct: float}>
     */
    private function buildFrequencyBuckets(
        string $from,
        string $to,
        array $scope,
        string $site,
        string $company,
        int $totalAktif,
    ): array {
        $rows = $this->workoutBaseQuery($from, $to, $scope, $site, $company)
            ->selectRaw('w.user_id')
            ->selectRaw('COUNT(DISTINCT DATE(w.created_at)) as days')
            ->groupBy('w.user_id')
            ->get();

        $adequate = 0;
        $below = 0;
        foreach ($rows as $row) {
            $days = (int) ($row->days ?? 0);
            if ($days >= self::FREQUENCY_ADEQUATE_DAYS) {
                $adequate++;
            } elseif ($days > 0) {
                $below++;
            }
        }

        $withWorkout = $adequate + $below;
        $none = max(0, $totalAktif - $withWorkout);

        return [
            $this->bucketRow('5–7 hari/minggu', $adequate, $totalAktif),
            $this->bucketRow('<5 hari/minggu', $below, $totalAktif),
            $this->bucketRow('Tidak ada olahraga', $none, $totalAktif),
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return list<array{label: string, count: int, pct: float}>
     */
    private function buildCalorieBuckets(
        string $from,
        string $to,
        string $weekStart,
        string $weekEnd,
        array $scope,
        string $site,
        string $company,
        int $totalAktif,
    ): array {
        $nutrition = $this->perUserNutritionAverages($from, $to, $weekStart, $weekEnd, $scope, $site, $company);
        $over = 0;
        $mid = 0;
        $low = 0;
        $none = 0;

        $loggedUserIds = [];
        foreach ($nutrition as $userId => $row) {
            $loggedUserIds[$userId] = true;
            $avg = (float) ($row['avg_calories'] ?? 0);
            $target = (float) ($row['calorie_target'] ?? self::DEFAULT_CALORIE_TARGET);
            if ($target <= 0) {
                $target = self::DEFAULT_CALORIE_TARGET;
            }
            if ($avg <= 0) {
                $none++;
                continue;
            }
            $ratio = $avg / $target;
            if ($ratio > 1.0) {
                $over++;
            } elseif ($ratio >= 0.5) {
                $mid++;
            } else {
                $low++;
            }
        }

        $none += max(0, $totalAktif - count($loggedUserIds));

        return [
            $this->bucketRow('Melebihi target kalori', $over, $totalAktif),
            $this->bucketRow('50–100% target kalori', $mid, $totalAktif),
            $this->bucketRow('<50% target kalori', $low, $totalAktif),
            $this->bucketRow('Tidak ada kalori', $none, $totalAktif),
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return list<array{label: string, count: int, pct: float, available: bool}>
     */
    private function buildMacroAttainment(
        string $from,
        string $to,
        string $weekStart,
        string $weekEnd,
        array $scope,
        string $site,
        string $company,
        int $totalAktif,
    ): array {
        $hasFiber = $this->foodHasFiberColumn();
        $nutrition = $this->perUserNutritionAverages($from, $to, $weekStart, $weekEnd, $scope, $site, $company, $hasFiber);

        $proteinOk = 0;
        $carbOk = 0;
        $fatOk = 0;
        $fiberOk = 0;

        foreach ($nutrition as $row) {
            if ((float) ($row['avg_protein'] ?? 0) >= (float) ($row['protein_target'] ?? self::DEFAULT_PROTEIN_TARGET)) {
                $proteinOk++;
            }
            if ((float) ($row['avg_carbs'] ?? 0) >= (float) ($row['carb_target'] ?? self::DEFAULT_CARB_TARGET)) {
                $carbOk++;
            }
            if ((float) ($row['avg_fats'] ?? 0) >= (float) ($row['fat_target'] ?? self::DEFAULT_FAT_TARGET)) {
                $fatOk++;
            }
            if ($hasFiber && (float) ($row['avg_fiber'] ?? 0) >= (float) ($row['fiber_target'] ?? self::DEFAULT_FIBER_TARGET)) {
                $fiberOk++;
            }
        }

        return [
            $this->macroRow('Protein', $proteinOk, $totalAktif, true),
            $this->macroRow('Karbohidrat', $carbOk, $totalAktif, true),
            $this->macroRow('Lemak', $fatOk, $totalAktif, true),
            $this->macroRow('Serat', $fiberOk, $totalAktif, $hasFiber),
        ];
    }

    /**
     * Rata-rata asupan harian per user di minggu (hari dengan log makanan).
     *
     * @param  array<string, mixed>  $scope
     * @return array<int, array{
     *     avg_calories: float,
     *     avg_protein: float,
     *     avg_carbs: float,
     *     avg_fats: float,
     *     avg_fiber: float,
     *     calorie_target: float,
     *     protein_target: float,
     *     carb_target: float,
     *     fat_target: float,
     *     fiber_target: float
     * }>
     */
    private function perUserNutritionAverages(
        string $from,
        string $to,
        string $weekStart,
        string $weekEnd,
        array $scope,
        string $site,
        string $company,
        bool $includeFiber = false,
    ): array {
        $fiberSelect = $includeFiber
            ? 'COALESCE(SUM(f.fiber_g), 0) as fiber_g'
            : '0 as fiber_g';

        $dailyRows = $this->foodBaseQuery($from, $to, $scope, $site, $company)
            ->selectRaw('f.user_id')
            ->selectRaw('DATE(f.created_at) as d')
            ->selectRaw('COALESCE(SUM(f.total_calories), 0) as calories')
            ->selectRaw('COALESCE(SUM(f.protein_g), 0) as protein_g')
            ->selectRaw('COALESCE(SUM(f.carbs_g), 0) as carbs_g')
            ->selectRaw('COALESCE(SUM(f.fats_g), 0) as fats_g')
            ->selectRaw($fiberSelect)
            ->groupByRaw('f.user_id, DATE(f.created_at)')
            ->get();

        /** @var array<int, array<string, array{calories: float, protein: float, carbs: float, fats: float, fiber: float}>> $byUserDay */
        $byUserDay = [];
        $userIds = [];
        foreach ($dailyRows as $row) {
            $userId = (int) $row->user_id;
            $date = (string) $row->d;
            $userIds[$userId] = true;
            $byUserDay[$userId][$date] = [
                'calories' => (float) ($row->calories ?? 0),
                'protein' => (float) ($row->protein_g ?? 0),
                'carbs' => (float) ($row->carbs_g ?? 0),
                'fats' => (float) ($row->fats_g ?? 0),
                'fiber' => (float) ($row->fiber_g ?? 0),
            ];
        }

        $ids = array_keys($userIds);
        $targetsByUser = $this->loadUserTargets($ids, $weekStart, $weekEnd);

        $result = [];
        foreach ($byUserDay as $userId => $days) {
            $dayCount = count($days);
            if ($dayCount < 1) {
                continue;
            }
            $sumCal = 0.0;
            $sumPro = 0.0;
            $sumCarb = 0.0;
            $sumFat = 0.0;
            $sumFiber = 0.0;
            $sumCalTarget = 0.0;
            $sumProTarget = 0.0;
            $sumCarbTarget = 0.0;
            foreach ($days as $date => $vals) {
                $sumCal += $vals['calories'];
                $sumPro += $vals['protein'];
                $sumCarb += $vals['carbs'];
                $sumFat += $vals['fats'];
                $sumFiber += $vals['fiber'];
                $t = $targetsByUser[$userId][$date] ?? null;
                $sumCalTarget += (float) ($t['calorie_target'] ?? self::DEFAULT_CALORIE_TARGET);
                $sumProTarget += (float) ($t['protein_target'] ?? self::DEFAULT_PROTEIN_TARGET);
                $sumCarbTarget += (float) ($t['carb_target'] ?? self::DEFAULT_CARB_TARGET);
            }
            $result[$userId] = [
                'avg_calories' => $sumCal / $dayCount,
                'avg_protein' => $sumPro / $dayCount,
                'avg_carbs' => $sumCarb / $dayCount,
                'avg_fats' => $sumFat / $dayCount,
                'avg_fiber' => $sumFiber / $dayCount,
                'calorie_target' => $sumCalTarget / $dayCount,
                'protein_target' => $sumProTarget / $dayCount,
                'carb_target' => $sumCarbTarget / $dayCount,
                'fat_target' => self::DEFAULT_FAT_TARGET,
                'fiber_target' => self::DEFAULT_FIBER_TARGET,
            ];
        }

        return $result;
    }

    /**
     * Total target kalori per karyawan untuk seluruh rentang tanggal (bukan rata-rata
     * harian), supaya sebanding dengan "Kalori In" di tabel/export yang juga dijumlah
     * per rentang. Hari yang tidak punya goal aktif (tidak ada baris di goal_daily_targets)
     * memakai default TARGET_KALORI_DEFAULT per hari.
     *
     * @param  list<int>  $userIds
     * @return array<int, float>
     */
    private function loadCalorieTargetTotals(array $userIds, string $rangeStart, string $rangeEnd): array
    {
        $out = [];
        if ($userIds === []) {
            return $out;
        }

        $totalDays = max(1, (int) Carbon::parse($rangeStart)->diffInDays(Carbon::parse($rangeEnd)) + 1);

        foreach (array_chunk($userIds, 800) as $chunkIds) {
            $rows = DB::connection(BewellConnectionService::CONNECTION)
                ->table('goal_daily_targets')
                ->whereIn('user_id', $chunkIds)
                ->whereBetween('target_date', [$rangeStart, $rangeEnd])
                ->selectRaw('user_id, COALESCE(SUM(calorie_target), 0) as target_sum, COUNT(*) as target_days')
                ->groupBy('user_id')
                ->get();

            foreach ($rows as $row) {
                $userId = (int) $row->user_id;
                $targetDays = (int) $row->target_days;
                $missingDays = max(0, $totalDays - $targetDays);
                $out[$userId] = (float) $row->target_sum + $missingDays * self::TARGET_KALORI_DEFAULT;
            }
        }

        foreach ($userIds as $userId) {
            if (! array_key_exists($userId, $out)) {
                $out[$userId] = $totalDays * self::TARGET_KALORI_DEFAULT;
            }
        }

        return $out;
    }

    /**
     * Persentase capaian kalori terhadap target, dibatasi ke bawah di 0%.
     * Tidak dibatasi ke atas supaya "melebihi target" tetap terlihat di progress bar.
     */
    private function calorieProgressPercent(float $kaloriIn, float $targetKalori): float
    {
        if ($targetKalori <= 0) {
            return 0.0;
        }

        return round(max(0.0, ($kaloriIn / $targetKalori) * 100), 1);
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array<string, array{calorie_target: float, protein_target: float, carb_target: float}>>
     */
    private function loadUserTargets(array $userIds, string $weekStart, string $weekEnd): array
    {
        $out = [];
        if ($userIds === []) {
            return $out;
        }

        foreach (array_chunk($userIds, 800) as $chunkIds) {
            $rows = DB::connection(BewellConnectionService::CONNECTION)
                ->table('goal_daily_targets')
                ->whereIn('user_id', $chunkIds)
                ->whereBetween('target_date', [$weekStart, $weekEnd])
                ->get(['user_id', 'target_date', 'calorie_target', 'protein_target_g', 'carb_target_g']);

            foreach ($rows as $row) {
                $userId = (int) $row->user_id;
                $date = (string) $row->target_date;
                $out[$userId][$date] = [
                    'calorie_target' => (float) ($row->calorie_target ?? self::DEFAULT_CALORIE_TARGET) ?: self::DEFAULT_CALORIE_TARGET,
                    'protein_target' => (float) ($row->protein_target_g ?? self::DEFAULT_PROTEIN_TARGET) ?: self::DEFAULT_PROTEIN_TARGET,
                    'carb_target' => (float) ($row->carb_target_g ?? self::DEFAULT_CARB_TARGET) ?: self::DEFAULT_CARB_TARGET,
                ];
            }
        }

        return $out;
    }

    private function foodHasFiberColumn(): bool
    {
        return (bool) Cache::remember('evaluasi_well:food_analyses_has_fiber_g', 3600, function (): bool {
            try {
                $cols = DB::connection(BewellConnectionService::CONNECTION)
                    ->select("SHOW COLUMNS FROM food_analyses LIKE 'fiber_g'");

                return $cols !== [];
            } catch (Throwable $e) {
                report($e);

                return false;
            }
        });
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{
     *     duration_minutes: float,
     *     avg_hr: float|null,
     *     hr_low: int,
     *     hr_med: int,
     *     hr_high: int,
     *     frekuensi: int,
     *     kalori_out: float,
     *     kalori_in: float,
     *     protein_g: float,
     *     carbs_g: float,
     *     fats_g: float,
     *     active_users: int
     * }
     */
    private function aggregateWeekMetrics(
        string $startDate,
        string $endDate,
        array $scope,
        string $site = '',
        string $company = '',
    ): array {
        $from = $startDate.' 00:00:00';
        $to = Carbon::parse($endDate)->endOfDay()->format('Y-m-d H:i:s');

        $workoutAgg = $this->workoutBaseQuery($from, $to, $scope, $site, $company)
            ->selectRaw('COUNT(w.id) as frekuensi')
            ->selectRaw('COUNT(DISTINCT w.user_id) as users')
            ->selectRaw('COALESCE(SUM(w.calories_kcal), 0) as kalori_out')
            ->first();

        $foodAgg = $this->foodBaseQuery($from, $to, $scope, $site, $company)
            ->selectRaw('COALESCE(SUM(f.total_calories), 0) as kalori_in')
            ->selectRaw('COALESCE(SUM(f.protein_g), 0) as protein_g')
            ->selectRaw('COALESCE(SUM(f.carbs_g), 0) as carbs_g')
            ->selectRaw('COALESCE(SUM(f.fats_g), 0) as fats_g')
            ->selectRaw('COUNT(DISTINCT f.user_id) as users')
            ->first();

        $durationMinutes = 0.0;
        $hrSum = 0.0;
        $hrCount = 0;
        $hrLow = 0;
        $hrMed = 0;
        $hrHigh = 0;

        $this->workoutBaseQuery($from, $to, $scope, $site, $company)
            ->select(['w.workout_time', 'w.avg_heart_rate'])
            ->orderBy('w.id')
            ->chunk(self::CHUNK_SIZE, function ($chunk) use (&$durationMinutes, &$hrSum, &$hrCount, &$hrLow, &$hrMed, &$hrHigh): void {
                foreach ($chunk as $row) {
                    $seconds = $this->parser->durationToSeconds(
                        isset($row->workout_time) ? (string) $row->workout_time : null
                    );
                    if ($seconds !== null && $seconds > 0) {
                        $durationMinutes += $seconds / 60;
                    }

                    $hr = $this->parseHeartRate(
                        isset($row->avg_heart_rate) ? (string) $row->avg_heart_rate : null
                    );
                    if ($hr === null) {
                        continue;
                    }
                    $hrSum += $hr;
                    $hrCount++;
                    $label = $this->intensityLabel($hr);
                    if ($label === 'Low') {
                        $hrLow++;
                    } elseif ($label === 'Med') {
                        $hrMed++;
                    } elseif ($label === 'High') {
                        $hrHigh++;
                    }
                }
            });

        $workoutUsers = (int) ($workoutAgg->users ?? 0);
        $foodUsers = (int) ($foodAgg->users ?? 0);
        $activeUsers = (int) (clone $this->usersWithActivityBaseQuery($from, $to, $scope, $site, $company))->count();

        return [
            'duration_minutes' => round($durationMinutes, 1),
            'avg_hr' => $hrCount > 0 ? round($hrSum / $hrCount, 1) : null,
            'hr_low' => $hrLow,
            'hr_med' => $hrMed,
            'hr_high' => $hrHigh,
            'frekuensi' => (int) ($workoutAgg->frekuensi ?? 0),
            'kalori_out' => round((float) ($workoutAgg->kalori_out ?? 0), 1),
            'kalori_in' => round((float) ($foodAgg->kalori_in ?? 0), 1),
            'protein_g' => round((float) ($foodAgg->protein_g ?? 0), 1),
            'carbs_g' => round((float) ($foodAgg->carbs_g ?? 0), 1),
            'fats_g' => round((float) ($foodAgg->fats_g ?? 0), 1),
            'active_users' => $activeUsers > 0 ? $activeUsers : max($workoutUsers, $foodUsers),
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function usersWithActivityBaseQuery(
        string $from,
        string $to,
        array $scope,
        string $site = '',
        string $company = '',
    ): Builder {
        $workoutUsers = $this->workoutBaseQuery($from, $to, $scope, $site, $company)
            ->select('w.user_id')
            ->selectRaw('COUNT(w.id) as frekuensi')
            ->selectRaw('COALESCE(SUM(w.calories_kcal), 0) as kalori_out')
            ->groupBy('w.user_id');

        $foodUsers = $this->foodBaseQuery($from, $to, $scope, $site, $company)
            ->select('f.user_id')
            ->selectRaw('COALESCE(SUM(f.total_calories), 0) as kalori_in')
            ->selectRaw('COALESCE(SUM(f.protein_g), 0) as protein_g')
            ->selectRaw('COALESCE(SUM(f.carbs_g), 0) as carbs_g')
            ->selectRaw('COALESCE(SUM(f.fats_g), 0) as fats_g')
            ->groupBy('f.user_id');

        $db = DB::connection(BewellConnectionService::CONNECTION);

        return $this->applyEmployeeFilters(
            $this->applyScopeToEmployees(
                $this->exclusionRules->applyToQuery(
                    $db->table('employee_profiles as e')
                        ->where('e.status_karyawan', 'AKTIF')
                ),
                $scope
            ),
            $site,
            $company
        )
            ->leftJoinSub($workoutUsers, 'ww', function ($join): void {
                $join->on('ww.user_id', '=', 'e.id');
            })
            ->leftJoinSub($foodUsers, 'ff', function ($join): void {
                $join->on('ff.user_id', '=', 'e.id');
            })
            ->where(function (Builder $q): void {
                $q->whereNotNull('ww.user_id')->orWhereNotNull('ff.user_id');
            })
            ->select([
                'e.id as user_id',
                'e.nama',
                'e.kode_sid',
                'e.site',
                'e.nama_perusahaan',
                'e.jabatan_fungsional',
            ])
            ->selectRaw('COALESCE(ww.frekuensi, 0) as frekuensi')
            ->selectRaw('COALESCE(ww.kalori_out, 0) as kalori_out')
            ->selectRaw('COALESCE(ff.kalori_in, 0) as kalori_in')
            ->selectRaw('COALESCE(ff.protein_g, 0) as protein_g')
            ->selectRaw('COALESCE(ff.carbs_g, 0) as carbs_g')
            ->selectRaw('COALESCE(ff.fats_g, 0) as fats_g');
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{duration_minutes: float, avg_hr: float|null, hr_samples: int}>
     */
    private function parsedWorkoutMetricsForUsers(array $userIds, string $from, string $to): array
    {
        $result = [];
        foreach ($userIds as $id) {
            $result[$id] = ['duration_minutes' => 0.0, 'avg_hr' => null, 'hr_samples' => 0];
        }

        if ($userIds === []) {
            return $result;
        }

        $hrSums = [];
        $hrCounts = [];

        foreach (array_chunk($userIds, 800) as $chunkIds) {
            $rows = DB::connection(BewellConnectionService::CONNECTION)
                ->table('workout_analyses as w')
                ->whereIn('w.user_id', $chunkIds)
                ->whereBetween('w.created_at', [$from, $to])
                ->select(['w.user_id', 'w.workout_time', 'w.avg_heart_rate'])
                ->orderBy('w.id')
                ->get();

            foreach ($rows as $row) {
                $userId = (int) $row->user_id;
                if (! isset($result[$userId])) {
                    continue;
                }

                $seconds = $this->parser->durationToSeconds(
                    isset($row->workout_time) ? (string) $row->workout_time : null
                );
                if ($seconds !== null && $seconds > 0) {
                    $result[$userId]['duration_minutes'] += $seconds / 60;
                }

                $hr = $this->parseHeartRate(
                    isset($row->avg_heart_rate) ? (string) $row->avg_heart_rate : null
                );
                if ($hr === null) {
                    continue;
                }
                $hrSums[$userId] = ($hrSums[$userId] ?? 0.0) + $hr;
                $hrCounts[$userId] = ($hrCounts[$userId] ?? 0) + 1;
            }
        }

        foreach ($result as $userId => $metrics) {
            $result[$userId]['duration_minutes'] = round($metrics['duration_minutes'], 1);
            if (($hrCounts[$userId] ?? 0) > 0) {
                $result[$userId]['avg_hr'] = round($hrSums[$userId] / $hrCounts[$userId], 1);
                $result[$userId]['hr_samples'] = $hrCounts[$userId];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function workoutBaseQuery(
        string $from,
        string $to,
        array $scope,
        string $site = '',
        string $company = '',
    ): Builder {
        return $this->applyEmployeeFilters(
            $this->applyScopeToEmployees(
                $this->exclusionRules->applyToQuery(
                    DB::connection(BewellConnectionService::CONNECTION)
                        ->table('employee_profiles as e')
                        ->where('e.status_karyawan', 'AKTIF')
                ),
                $scope
            )->join('workout_analyses as w', 'w.user_id', '=', 'e.id')
                ->whereBetween('w.created_at', [$from, $to]),
            $site,
            $company
        );
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function foodBaseQuery(
        string $from,
        string $to,
        array $scope,
        string $site = '',
        string $company = '',
    ): Builder {
        return $this->applyEmployeeFilters(
            $this->applyScopeToEmployees(
                $this->exclusionRules->applyToQuery(
                    DB::connection(BewellConnectionService::CONNECTION)
                        ->table('employee_profiles as e')
                        ->where('e.status_karyawan', 'AKTIF')
                ),
                $scope
            )->join('food_analyses as f', 'f.user_id', '=', 'e.id')
                ->whereBetween('f.created_at', [$from, $to]),
            $site,
            $company
        );
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function applyScopeToEmployees(Builder $query, array $scope): Builder
    {
        return $this->mitraAssignmentService->applyScopeToEmployeeQuery($query, $scope);
    }

    private function applyEmployeeFilters(Builder $query, string $site, string $company): Builder
    {
        if ($site !== '') {
            $this->siteResolver->applySiteFilter($query, $site);
        }
        if ($company !== '') {
            $query->where('e.nama_perusahaan', $company);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return list<string>
     */
    private function filterSites(array $scope): array
    {
        try {
            $query = $this->applyScopeToEmployees(
                $this->exclusionRules->applyToQuery(
                    DB::connection(BewellConnectionService::CONNECTION)
                        ->table('employee_profiles as e')
                        ->where('e.status_karyawan', 'AKTIF')
                ),
                $scope
            );

            $fallback = (clone $query)
                ->whereNotNull('e.site')
                ->where('e.site', '<>', '')
                ->distinct()
                ->orderBy('e.site')
                ->pluck('e.site')
                ->map(static fn (mixed $v): string => (string) $v)
                ->all();

            return $this->siteResolver->mergeFilterSites($fallback);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return list<string>
     */
    private function filterCompanies(array $scope): array
    {
        try {
            $query = $this->applyScopeToEmployees(
                $this->exclusionRules->applyToQuery(
                    DB::connection(BewellConnectionService::CONNECTION)
                        ->table('employee_profiles as e')
                        ->where('e.status_karyawan', 'AKTIF')
                ),
                $scope
            );

            return (clone $query)
                ->whereNotNull('e.nama_perusahaan')
                ->where('e.nama_perusahaan', '<>', '')
                ->distinct()
                ->orderBy('e.nama_perusahaan')
                ->pluck('e.nama_perusahaan')
                ->map(static fn (mixed $v): string => (string) $v)
                ->all();
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    private function parseHeartRate(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $text = trim($raw);
        if ($text === '') {
            return null;
        }
        if (! preg_match('/([0-9]+(?:[.,][0-9]+)?)/', $text, $m)) {
            return null;
        }
        $value = (float) str_replace(',', '.', $m[1]);
        if ($value < 40 || $value > 220) {
            return null;
        }

        return $value;
    }

    private function weekIncreasePercent(float $increase, float $total): float
    {
        $increase = max(0.0, $increase);
        $baseline = max(0.0, $total - $increase);

        if ($baseline <= 0) {
            return $increase > 0 ? 100.0 : 0.0;
        }

        return round(($increase / $baseline) * 100, 1);
    }

    private function displayOrDash(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : '-';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     site: string,
     *     company: string,
     *     perusahaan: string,
     *     pairs: list<array{site: string, perusahaan: string}>,
     *     companies: list<array{perusahaan: string, sites: list<string>}>
     * }
     */
    private function normalizeScopeFilters(array $filters): array
    {
        $normalized = $this->mitraAssignmentService->normalizeScope([
            'site' => $filters['site'] ?? '',
            'perusahaan' => $filters['perusahaan'] ?? $filters['company'] ?? '',
            'company' => $filters['company'] ?? '',
            'companies' => $this->mitraAssignmentService->decodeScopeCollection($filters['companies'] ?? null),
            'pairs' => $this->mitraAssignmentService->decodeScopeCollection($filters['pairs'] ?? null),
        ]);
        $division = trim((string) ($filters['division_group'] ?? $filters['division'] ?? $filters['divisi'] ?? ''));

        return [
            'site' => $normalized['site'],
            'company' => $normalized['perusahaan'],
            'perusahaan' => $normalized['perusahaan'],
            'pairs' => $normalized['pairs'],
            'companies' => $normalized['companies'],
            'division_group' => $division,
            'division' => $division,
            'divisi' => $division,
        ];
    }
}
