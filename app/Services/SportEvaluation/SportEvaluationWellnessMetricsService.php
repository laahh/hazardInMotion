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

    private const CACHE_VERSION = 'v1';

    private const CHUNK_SIZE = 500;

    private const TREND_WEEKS = 12;

    private const WEEK_START_DAY = Carbon::SUNDAY;

    private const WEEK_END_DAY = Carbon::SATURDAY;

    private const HR_LOW_MAX = 119.0;

    private const HR_MED_MAX = 149.0;

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
    public function getDashboardPayload(array $scope = [], ?string $weekStart = null): array
    {
        $week = $this->resolveWeek($weekStart);
        $empty = $this->emptyDashboardPayload($week);

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            $scope = $this->normalizeScopeFilters($scope);
            $scopeKey = $this->mitraAssignmentService->cacheKeySuffix($scope);
            $cacheKey = 'evaluasi_well:wellness_metrics:dash:'.self::CACHE_VERSION.':'.sha1(
                $week['start'].'|'.$scopeKey
            );

            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($week, $scope): array {
                $current = $this->aggregateWeekMetrics($week['start'], $week['end'], $scope);
                $prevWeek = $this->resolveWeek($week['prev_start']);
                $previous = $this->aggregateWeekMetrics($prevWeek['start'], $prevWeek['end'], $scope);

                return array_merge(
                    $this->mapMetricsToCardPayload($current, $previous),
                    [
                        'wellnessWeek' => $week,
                        'wellnessWeekOptions' => $this->buildWeekOptions(),
                        'wellnessSites' => $this->filterSites($scope),
                        'wellnessCompanies' => $this->filterCompanies($scope),
                        'wellnessUserCount' => $current['active_users'],
                    ]
                );
            });
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * KPI JSON saat ganti minggu di UI.
     *
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    public function getKpiPayload(array $scope = [], ?string $weekStart = null): array
    {
        $payload = $this->getDashboardPayload($scope, $weekStart);

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
            $week = $this->resolveWeek($weekStart);
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

            $orderable = [
                0 => 'e.nama',
                1 => 'e.site',
                2 => 'e.nama_perusahaan',
                3 => 'e.jabatan_fungsional',
                7 => 'frekuensi',
                8 => 'kalori_out',
                9 => 'kalori_in',
                10 => 'protein_g',
                11 => 'carbs_g',
                12 => 'fats_g',
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

            $data = [];
            foreach ($rows as $row) {
                $userId = (int) $row->user_id;
                $metrics = $parsed[$userId] ?? ['duration_minutes' => 0.0, 'avg_hr' => null, 'hr_samples' => 0];
                $avgHr = $metrics['avg_hr'];
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
                    'kalori_in' => round((float) ($row->kalori_in ?? 0), 1),
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
    ): array {
        $week = $this->resolveWeek($weekStart);
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
            $query->orderBy('e.nama')->chunk(self::CHUNK_SIZE, function ($chunk) use (&$rows, $from, $to): void {
                $userIds = $chunk->pluck('user_id')->map(static fn ($id): int => (int) $id)->all();
                $parsed = $this->parsedWorkoutMetricsForUsers($userIds, $from, $to);

                foreach ($chunk as $row) {
                    $userId = (int) $row->user_id;
                    $metrics = $parsed[$userId] ?? ['duration_minutes' => 0.0, 'avg_hr' => null];
                    $avgHr = $metrics['avg_hr'];
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
                        'kalori_in' => round((float) ($row->kalori_in ?? 0), 1),
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
        ];
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
    private function aggregateWeekMetrics(string $startDate, string $endDate, array $scope): array
    {
        $from = $startDate.' 00:00:00';
        $to = Carbon::parse($endDate)->endOfDay()->format('Y-m-d H:i:s');

        $workoutAgg = $this->workoutBaseQuery($from, $to, $scope)
            ->selectRaw('COUNT(w.id) as frekuensi')
            ->selectRaw('COUNT(DISTINCT w.user_id) as users')
            ->selectRaw('COALESCE(SUM(w.calories_kcal), 0) as kalori_out')
            ->first();

        $foodAgg = $this->foodBaseQuery($from, $to, $scope)
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

        $this->workoutBaseQuery($from, $to, $scope)
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
        $activeUsers = (int) (clone $this->usersWithActivityBaseQuery($from, $to, $scope))->count();

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

        return [
            'site' => $normalized['site'],
            'company' => $normalized['perusahaan'],
            'perusahaan' => $normalized['perusahaan'],
            'pairs' => $normalized['pairs'],
            'companies' => $normalized['companies'],
        ];
    }
}
