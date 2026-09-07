<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Dashboard Tren Aktivitas WELL — read-only dari workout_analyses + food_analyses.
 */
final class SportEvaluationWorkoutActivityService
{
    private const CACHE_TTL = 300;

    private const MAX_PERIOD_DAYS = 90;

    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly BewellConnectionService $connection,
        private readonly SportEvaluationKaryawanWellSiteResolver $siteResolver,
        private readonly SportEvaluationEmployeeExclusionRules $exclusionRules,
        private readonly SportEvaluationWorkoutActivityAggregator $aggregator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Request $request): array
    {
        $filters = $this->readFilters($request);

        if (! $this->connection->isUp()) {
            return $this->emptyDashboard($filters, false);
        }

        try {
            $payload = $this->buildSqlDashboard($filters);
            try {
                $filterOptions = $this->buildFilterOptions($filters);
            } catch (Throwable $e) {
                report($e);
                $filterOptions = [
                    'sites' => [],
                    'companies' => [],
                    'divisions' => [],
                    'activity_types' => [],
                ];
            }

            return [
                'connectionUp' => true,
                'loadError' => null,
                'filters' => $filters,
                'filterOptions' => $filterOptions,
                'kpi' => $payload['kpi'],
                'trendDaily' => $payload['trendDaily'],
                'trendWeekly' => $payload['trendWeekly'],
                'distribution' => $payload['distribution'],
                'leaderboard' => $payload['leaderboard'],
                'periodLabel' => $this->periodLabel($filters),
            ];
        } catch (Throwable $e) {
            report($e);

            $empty = $this->emptyDashboard($filters, true);
            $empty['loadError'] = 'Gagal memuat ringkasan aktivitas. Coba perkecil rentang tanggal, lalu Terapkan.';

            return $empty;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(Request $request): array
    {
        $draw = (int) $request->input('draw', 1);
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
            return $this->userDatatable($request, $draw);
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * Laporan siapa yang olahraga per hari atau per minggu (SQL GROUP BY + pagination).
     *
     * @return array<string, mixed>
     */
    public function periodDatatable(Request $request): array
    {
        $draw = (int) $request->input('draw', 1);
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
            return $this->periodReportDatatable($request, $draw);
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rawDatatable(Request $request): array
    {
        $draw = (int) $request->input('draw', 1);
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
            $filters = $this->readFilters($request);
            $search = trim((string) $request->input('search.value', ''));
            $start = max(0, (int) $request->input('start', 0));
            $length = (int) $request->input('length', 10);
            if ($length < 1) {
                $length = 10;
            }
            if ($length > 100) {
                $length = 100;
            }

            $orderColumnIndex = (int) data_get($request->input('order'), '0.column', 0);
            $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'desc')) === 'asc'
                ? 'asc'
                : 'desc';
            $orderable = [
                0 => 'w.created_at',
                1 => 'e.nama',
                2 => 'w.activity_type',
                3 => 'w.workout_time',
                4 => 'w.distance',
                5 => 'w.calories_kcal',
                6 => 'w.avg_heart_rate',
            ];
            $orderColumn = $orderable[$orderColumnIndex] ?? 'w.created_at';

            $recordsTotal = (int) $this->workoutBaseQuery($filters)->count('w.id');
            $recordsFiltered = (int) $this->applyWorkoutSearch(
                $this->workoutBaseQuery($filters),
                $search
            )->count('w.id');

            $rows = $this->applyWorkoutSearch(
                $this->workoutBaseQuery($filters)->select($this->workoutSelectColumns()),
                $search
            )
                ->orderBy($orderColumn, $orderDir)
                ->orderBy('w.id', 'desc')
                ->offset($start)
                ->limit($length)
                ->get();

            $data = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $site = $this->siteResolver->resolveOrDash(
                    isset($arr['kode_sid']) ? (string) $arr['kode_sid'] : null,
                    isset($arr['site']) ? (string) $arr['site'] : null,
                );
                $data[] = $this->aggregator->parseWorkoutRow($arr, $site);
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
     * @return array{
     *     users: list<array<string, mixed>>,
     *     rawWorkouts: list<array<string, mixed>>,
     *     rawFoods: list<array<string, mixed>>,
     *     trendDaily: array<string, mixed>,
     *     filters: array<string, string>
     * }
     */
    public function exportPayload(Request $request): array
    {
        $filters = $this->readFilters($request);

        if (! $this->connection->isUp()) {
            return [
                'users' => [],
                'rawWorkouts' => [],
                'rawFoods' => [],
                'trendDaily' => $this->emptyTrend(),
                'filters' => $filters,
            ];
        }

        $payload = $this->loadPayload($filters);

        return [
            'users' => $payload['users'],
            'rawWorkouts' => $this->fetchParsedWorkouts($filters),
            'rawFoods' => $this->fetchRawFoods($filters),
            'trendDaily' => $payload['trendDaily'],
            'filters' => $filters,
        ];
    }

    /**
     * @return array{from:string,to:string,site:string,company:string,division:string,activity_type:string}
     */
    public function readFilters(Request $request): array
    {
        $read = static fn (mixed $value): string => is_string($value)
            ? mb_substr(trim($value), 0, 150)
            : '';

        $from = $this->parseDate($request->input('from', $request->query('from')));
        $to = $this->parseDate($request->input('to', $request->query('to')));

        if ($from === null) {
            $from = SportEvaluationWorkoutActivityPeriodFormatter::weekStartMonday(Carbon::now());
        }
        if ($to === null) {
            $to = Carbon::now()->startOfDay();
        }
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $days = $from->diffInDays($to) + 1;
        if ($days > self::MAX_PERIOD_DAYS) {
            $from = $to->copy()->subDays(self::MAX_PERIOD_DAYS - 1)->startOfDay();
        }

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'site' => $read($request->input('site', $request->query('site', ''))),
            'company' => $read($request->input('company', $request->query('company', ''))),
            'division' => $read($request->input('division', $request->query('division', ''))),
            'activity_type' => $read($request->input('activity_type', $request->query('activity_type', ''))),
            'nama' => $read($request->input('nama', $request->query('nama', ''))),
            'report_mode' => SportEvaluationWorkoutActivityPeriodFormatter::normalizeMode(
                $read($request->input('report_mode', $request->query('report_mode', '')))
            ),
        ];
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     * @return array<string, mixed>
     */
    public function loadPayload(array $filters): array
    {
        $cacheKey = 'evaluasi_well:workout_activity:v2:'.sha1(json_encode($filters, JSON_THROW_ON_ERROR));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters): array {
            $parsed = $this->fetchParsedWorkouts($filters);
            [$foodByUser, $foodByDate] = $this->fetchFoodCalories($filters);
            $from = Carbon::parse($filters['from']);
            $to = Carbon::parse($filters['to']);
            $periodDays = $from->diffInDays($to) + 1;

            $aggregated = $this->aggregator->aggregate(
                $parsed,
                $foodByUser,
                $foodByDate,
                $periodDays,
                $filters['from'],
                $filters['to'],
            );
            unset($aggregated['rawWorkouts']);

            return $aggregated;
        });
    }

    /**
     * KPI + chart dari agregasi SQL (tanpa menarik semua baris workout).
     *
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     * @return array<string, mixed>
     */
    private function buildSqlDashboard(array $filters): array
    {
        $cacheKey = 'evaluasi_well:workout_activity:sql_dash:v3:'.sha1(json_encode($filters, JSON_THROW_ON_ERROR));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters): array {
            $from = Carbon::parse($filters['from']);
            $to = Carbon::parse($filters['to']);
            $periodDays = $from->diffInDays($to) + 1;
            $weeks = max(1.0, $periodDays / 7);

            $totals = $this->workoutBaseQuery($filters)
                ->selectRaw('COUNT(w.id) as sesi, COUNT(DISTINCT w.user_id) as users, COALESCE(SUM(w.calories_kcal), 0) as kcal_out')
                ->first();

            $sesi = (int) ($totals->sesi ?? 0);
            $users = (int) ($totals->users ?? 0);
            $kcalOut = (float) ($totals->kcal_out ?? 0);
            $kcalIn = (float) $this->foodBaseQuery($filters)->sum('f.total_calories');

            $dailyWorkout = [];
            foreach ($this->workoutBaseQuery($filters)
                ->selectRaw('DATE(w.created_at) as d, COUNT(w.id) as sesi, COUNT(DISTINCT w.user_id) as users, COALESCE(SUM(w.calories_kcal), 0) as kcal_out')
                ->groupByRaw('DATE(w.created_at)')
                ->get() as $row) {
                $date = (string) $row->d;
                $dailyWorkout[$date] = [
                    'sesi' => (int) $row->sesi,
                    'users' => (int) $row->users,
                    'kcal_out' => (float) $row->kcal_out,
                ];
            }

            $dailyFood = [];
            foreach ($this->foodBaseQuery($filters)
                ->selectRaw('DATE(f.created_at) as d, SUM(f.total_calories) as kcal')
                ->groupByRaw('DATE(f.created_at)')
                ->get() as $row) {
                $dailyFood[(string) $row->d] = (float) ($row->kcal ?? 0);
            }

            $jenisExpr = "CASE WHEN TRIM(COALESCE(w.activity_type, '')) = '' THEN 'Lainnya' ELSE w.activity_type END";
            $distRows = $this->workoutBaseQuery($filters)
                ->selectRaw($jenisExpr.' as jenis, COUNT(w.id) as c')
                ->groupByRaw($jenisExpr)
                ->orderByDesc('c')
                ->limit(12)
                ->get();

            $trend = $this->fillDailyAndWeeklyTrends($filters['from'], $filters['to'], $dailyWorkout, $dailyFood);

            return [
                'kpi' => [
                    'total_sessions' => $sesi,
                    'active_users' => $users,
                    'total_minutes' => 0,
                    'total_km' => 0.0,
                    'kcal_out' => (int) round($kcalOut),
                    'kcal_in' => (int) round($kcalIn),
                    'avg_sessions_per_week' => round($sesi / $weeks, 1),
                    'avg_sessions_per_user' => $users > 0 ? round($sesi / $users, 1) : 0.0,
                    'period_days' => $periodDays,
                ],
                'trendDaily' => $trend['daily'],
                'trendWeekly' => $trend['weekly'],
                'distribution' => [
                    'labels' => $distRows->pluck('jenis')->map(static fn (mixed $v): string => mb_substr((string) $v, 0, 40))->all(),
                    'counts' => $distRows->pluck('c')->map(static fn (mixed $v): int => (int) $v)->all(),
                ],
                'leaderboard' => $this->buildLeaderboard($filters),
            ];
        });
    }

    /**
     * @param  array<string, array{sesi:int, users:int, kcal_out:float}>  $dailyWorkout
     * @param  array<string, float>  $dailyFood
     * @return array{daily: array<string, mixed>, weekly: array<string, mixed>}
     */
    private function fillDailyAndWeeklyTrends(string $fromDate, string $toDate, array $dailyWorkout, array $dailyFood): array
    {
        $labels = [];
        $sesi = [];
        $users = [];
        $kcalOut = [];
        $kcalIn = [];
        $menit = [];
        $km = [];

        $cursor = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->startOfDay();
        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m-d');
            $bucket = $dailyWorkout[$key] ?? ['sesi' => 0, 'users' => 0, 'kcal_out' => 0.0];
            $labels[] = $cursor->format('d M');
            $sesi[] = (int) $bucket['sesi'];
            $users[] = (int) $bucket['users'];
            $kcalOut[] = round((float) $bucket['kcal_out'], 1);
            $kcalIn[] = round((float) ($dailyFood[$key] ?? 0), 1);
            $menit[] = 0.0;
            $km[] = 0.0;
            $cursor->addDay();
        }

        $daily = [
            'labels' => $labels,
            'sesi' => $sesi,
            'users' => $users,
            'kcal_out' => $kcalOut,
            'kcal_in' => $kcalIn,
            'menit' => $menit,
            'km' => $km,
        ];

        $weekly = [
            'labels' => [],
            'sesi' => [],
            'users' => [],
            'kcal_out' => [],
            'kcal_in' => [],
            'menit' => [],
            'km' => [],
        ];
        $chunkSize = 7;
        $count = count($labels);
        for ($offset = 0; $offset < $count; $offset += $chunkSize) {
            $endIdx = min($offset + $chunkSize, $count) - 1;
            $weekly['labels'][] = $labels[$offset].' – '.$labels[$endIdx];
            $weekly['sesi'][] = (int) array_sum(array_slice($sesi, $offset, $chunkSize));
            $weekly['users'][] = (int) max(array_slice($users, $offset, $chunkSize) ?: [0]);
            $weekly['kcal_out'][] = round((float) array_sum(array_slice($kcalOut, $offset, $chunkSize)), 1);
            $weekly['kcal_in'][] = round((float) array_sum(array_slice($kcalIn, $offset, $chunkSize)), 1);
            $weekly['menit'][] = 0.0;
            $weekly['km'][] = 0.0;
        }

        return ['daily' => $daily, 'weekly' => $weekly];
    }

    /**
     * Top 15 karyawan by kkal olahraga — LIMIT di SQL, tanpa parse teks.
     *
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string,nama:string,report_mode:string}  $filters
     * @return list<array<string, mixed>>
     */
    private function buildLeaderboard(array $filters): array
    {
        $rows = $this->workoutBaseQuery($filters)
            ->select([
                'e.id',
                'e.nama',
                'e.kode_sid',
                'e.site',
                'e.divisi',
            ])
            ->selectRaw('COUNT(w.id) as sesi')
            ->selectRaw('COALESCE(SUM(w.calories_kcal), 0) as kcal_out')
            ->groupBy('e.id', 'e.nama', 'e.kode_sid', 'e.site', 'e.divisi')
            ->orderByDesc('kcal_out')
            ->orderBy('e.nama')
            ->limit(15)
            ->get();

        $leaderboard = [];
        $rank = 1;
        foreach ($rows as $row) {
            $leaderboard[] = [
                'rank' => $rank,
                'id' => (int) $row->id,
                'nama' => $this->displayOrDash($row->nama ?? null),
                'kode_sid' => $this->displayOrDash($row->kode_sid ?? null),
                'site' => $this->siteResolver->resolveOrDash(
                    isset($row->kode_sid) ? (string) $row->kode_sid : null,
                    isset($row->site) ? (string) $row->site : null,
                ),
                'divisi' => $this->displayOrDash($row->divisi ?? null),
                'sesi' => (int) ($row->sesi ?? 0),
                'kcal_out' => (int) round((float) ($row->kcal_out ?? 0)),
            ];
            $rank++;
        }

        return $leaderboard;
    }

    /**
     * @return array<string, mixed>
     */
    private function periodReportDatatable(Request $request, int $draw): array
    {
        $filters = $this->readFilters($request);
        $search = trim((string) $request->input('search.value', ''));
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if ($length < 1) {
            $length = 10;
        }
        if ($length > 100) {
            $length = 100;
        }

        $orderColumnIndex = (int) data_get($request->input('order'), '0.column', 5);
        $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';
        $orderable = [
            0 => 'period_start',
            1 => 'e.nama',
            2 => 'e.site',
            3 => 'e.divisi',
            4 => 'sesi',
            5 => 'kcal_out',
            6 => 'jenis',
        ];
        $orderColumn = $orderable[$orderColumnIndex] ?? 'kcal_out';

        $db = DB::connection(BewellConnectionService::CONNECTION);
        $recordsTotal = (int) $db->query()
            ->fromSub($this->periodGroupedQuery($filters, '', false), 'wa_period')
            ->count();
        $recordsFiltered = (int) $db->query()
            ->fromSub($this->periodGroupedQuery($filters, $search, false), 'wa_period')
            ->count();

        $rows = $this->periodGroupedQuery($filters, $search, true)
            ->orderBy($orderColumn, $orderDir)
            ->orderBy('e.nama')
            ->offset($start)
            ->limit($length)
            ->get();

        $mode = $filters['report_mode'];
        $data = [];
        $rank = $start + 1;
        foreach ($rows as $row) {
            $periodStartRaw = $row->period_start ?? '';
            if ($periodStartRaw instanceof \DateTimeInterface) {
                $periodStart = $periodStartRaw->format('Y-m-d');
            } else {
                $periodStart = substr((string) $periodStartRaw, 0, 10);
            }
            $data[] = [
                'rank' => $rank,
                'id' => (int) $row->id,
                'period' => $periodStart !== ''
                    ? SportEvaluationWorkoutActivityPeriodFormatter::label($mode, $periodStart)
                    : '-',
                'nama' => $this->displayOrDash($row->nama ?? null),
                'kode_sid' => $this->displayOrDash($row->kode_sid ?? null),
                'site' => $this->siteResolver->resolveOrDash(
                    isset($row->kode_sid) ? (string) $row->kode_sid : null,
                    isset($row->site) ? (string) $row->site : null,
                ),
                'divisi' => $this->displayOrDash($row->divisi ?? null),
                'sesi' => (int) ($row->sesi ?? 0),
                'kcal_out' => (int) round((float) ($row->kcal_out ?? 0)),
                'jenis' => $this->displayOrDash($row->jenis ?? null),
            ];
            $rank++;
        }

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string,nama:string,report_mode:string}  $filters
     */
    private function periodGroupedQuery(array $filters, string $search, bool $withJenis): Builder
    {
        $periodSql = SportEvaluationWorkoutActivityPeriodFormatter::periodStartSql($filters['report_mode']);
        $query = $this->workoutBaseQuery($filters)
            ->select([
                'e.id',
                'e.nama',
                'e.kode_sid',
                'e.site',
                'e.divisi',
            ])
            ->selectRaw($periodSql.' as period_start')
            ->selectRaw('COUNT(w.id) as sesi')
            ->selectRaw('COALESCE(SUM(w.calories_kcal), 0) as kcal_out')
            ->groupByRaw($periodSql)
            ->groupBy('e.id', 'e.nama', 'e.kode_sid', 'e.site', 'e.divisi');

        if ($withJenis) {
            $query->selectRaw(
                "SUBSTRING(GROUP_CONCAT(DISTINCT CASE WHEN TRIM(COALESCE(w.activity_type, '')) = '' THEN 'Lainnya' ELSE w.activity_type END SEPARATOR ', '), 1, 120) as jenis"
            );
        }

        return $this->applyUserSearch($query, $search);
    }

    /**
     * @return array<string, mixed>
     */
    private function userDatatable(Request $request, int $draw): array
    {
        $filters = $this->readFilters($request);
        $search = trim((string) $request->input('search.value', ''));
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if ($length < 1) {
            $length = 10;
        }
        if ($length > 100) {
            $length = 100;
        }

        $orderColumnIndex = (int) data_get($request->input('order'), '0.column', 4);
        $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';
        $orderable = [
            0 => 'e.nama',
            1 => 'e.site',
            2 => 'e.nama_perusahaan',
            3 => 'e.divisi',
            4 => 'sesi',
            5 => 'sesi',
            6 => 'sesi',
            7 => 'kcal_out',
            8 => 'kcal_in',
            9 => 'last_workout_at',
        ];
        $orderColumn = $orderable[$orderColumnIndex] ?? 'sesi';

        $db = DB::connection(BewellConnectionService::CONNECTION);
        $recordsTotal = (int) $db->query()
            ->fromSub($this->usersGroupedQuery($filters, ''), 'wa_users')
            ->count();
        $recordsFiltered = (int) $db->query()
            ->fromSub($this->usersGroupedQuery($filters, $search), 'wa_users')
            ->count();

        $rows = $this->usersGroupedQuery($filters, $search)
            ->orderBy($orderColumn, $orderDir)
            ->orderBy('e.nama')
            ->offset($start)
            ->limit($length)
            ->get();

        $periodDays = Carbon::parse($filters['from'])->diffInDays(Carbon::parse($filters['to'])) + 1;
        $weeks = max(1.0, $periodDays / 7);
        $parsedByUser = $this->parsedMetricsForUsers(
            $filters,
            $rows->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all()
        );

        $data = [];
        foreach ($rows as $row) {
            $userId = (int) $row->id;
            $metrics = $parsedByUser[$userId] ?? ['duration_minutes' => 0.0, 'distance_km' => 0.0];
            $lastAt = $row->last_workout_at
                ? Carbon::parse((string) $row->last_workout_at)->format('d M Y H:i')
                : '-';
            $sesi = (int) ($row->sesi ?? 0);
            $kcalOut = round((float) ($row->kcal_out ?? 0), 1);
            $kcalIn = round((float) ($row->kcal_in ?? 0), 1);

            $data[] = [
                'id' => $userId,
                'nama' => $this->displayOrDash($row->nama ?? null),
                'kode_sid' => $this->displayOrDash($row->kode_sid ?? null),
                'site' => $this->siteResolver->resolveOrDash(
                    isset($row->kode_sid) ? (string) $row->kode_sid : null,
                    isset($row->site) ? (string) $row->site : null,
                ),
                'company' => $this->displayOrDash($row->nama_perusahaan ?? null),
                'divisi' => $this->displayOrDash($row->divisi ?? null),
                'sesi' => $sesi,
                'duration_minutes' => $metrics['duration_minutes'],
                'distance_km' => $metrics['distance_km'],
                'kcal_out' => $kcalOut,
                'kcal_in' => $kcalIn,
                'kcal_net' => round($kcalIn - $kcalOut, 1),
                'sessions_per_week' => round($sesi / $weeks, 2),
                'last_workout_at' => $lastAt,
            ];
        }

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     */
    private function usersGroupedQuery(array $filters, string $search): Builder
    {
        $range = $this->datetimeRange($filters);
        $foodSub = DB::connection(BewellConnectionService::CONNECTION)
            ->table('food_analyses')
            ->selectRaw('user_id, SUM(total_calories) as kcal_in')
            ->whereBetween('created_at', [$range['from'], $range['to']])
            ->groupBy('user_id');

        $query = $this->workoutBaseQuery($filters)
            ->leftJoinSub($foodSub, 'food', 'food.user_id', '=', 'e.id')
            ->select([
                'e.id',
                'e.nama',
                'e.kode_sid',
                'e.site',
                'e.nama_perusahaan',
                'e.divisi',
            ])
            ->selectRaw('COUNT(w.id) as sesi')
            ->selectRaw('COALESCE(SUM(w.calories_kcal), 0) as kcal_out')
            ->selectRaw('COALESCE(MAX(food.kcal_in), 0) as kcal_in')
            ->selectRaw('MAX(w.created_at) as last_workout_at')
            ->groupBy('e.id', 'e.nama', 'e.kode_sid', 'e.site', 'e.nama_perusahaan', 'e.divisi');

        return $this->applyUserSearch($query, $search);
    }

    private function applyUserSearch(Builder $query, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }

        $like = '%'.$search.'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('e.nama', 'like', $like)
                ->orWhere('e.kode_sid', 'like', $like)
                ->orWhere('e.nama_perusahaan', 'like', $like)
                ->orWhere('e.divisi', 'like', $like);
        });
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     * @param  list<int>  $userIds
     * @return array<int, array{duration_minutes:float, distance_km:float}>
     */
    private function parsedMetricsForUsers(array $filters, array $userIds): array
    {
        $metrics = [];
        if ($userIds === []) {
            return $metrics;
        }

        $rows = $this->workoutBaseQuery($filters)
            ->whereIn('w.user_id', $userIds)
            ->get([
                'w.user_id',
                'w.activity_type',
                'w.distance',
                'w.workout_time',
                'w.calories_kcal',
                'w.created_at',
            ]);

        foreach ($rows as $row) {
            $parsed = $this->aggregator->parseWorkoutRow((array) $row, '-');
            $userId = (int) $parsed['user_id'];
            if (! isset($metrics[$userId])) {
                $metrics[$userId] = ['duration_minutes' => 0.0, 'distance_km' => 0.0];
            }
            $metrics[$userId]['duration_minutes'] += (float) ($parsed['duration_minutes'] ?? 0);
            $metrics[$userId]['distance_km'] += (float) ($parsed['distance_km'] ?? 0);
        }

        foreach ($metrics as $userId => $row) {
            $metrics[$userId]['duration_minutes'] = round($row['duration_minutes'], 1);
            $metrics[$userId]['distance_km'] = round($row['distance_km'], 2);
        }

        return $metrics;
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     * @return list<array<string, mixed>>
     */
    private function fetchParsedWorkouts(array $filters): array
    {
        $parsed = [];
        $lastId = 0;

        do {
            $rows = $this->workoutBaseQuery($filters)
                ->select($this->workoutSelectColumns())
                ->where('w.id', '>', $lastId)
                ->orderBy('w.id')
                ->limit(self::CHUNK_SIZE)
                ->get();

            foreach ($rows as $row) {
                $lastId = (int) $row->id;
                $arr = (array) $row;
                $site = $this->siteResolver->resolveOrDash(
                    isset($arr['kode_sid']) ? (string) $arr['kode_sid'] : null,
                    isset($arr['site']) ? (string) $arr['site'] : null,
                );
                $parsed[] = $this->aggregator->parseWorkoutRow($arr, $site);
            }
        } while ($rows->count() === self::CHUNK_SIZE);

        return $parsed;
    }

    /**
     * @return list<string>
     */
    private function workoutSelectColumns(): array
    {
        return [
            'w.id as id',
            'w.user_id',
            'w.activity_type',
            'w.calories_kcal',
            'w.active_kilocalories',
            'w.total_kilocalories',
            'w.distance',
            'w.workout_time',
            'w.avg_heart_rate',
            'w.created_at',
            'e.nama',
            'e.kode_sid',
            'e.site',
            'e.nama_perusahaan',
            'e.divisi',
        ];
    }

    private function applyWorkoutSearch(Builder $query, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }

        $like = '%'.$search.'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('e.nama', 'like', $like)
                ->orWhere('e.kode_sid', 'like', $like)
                ->orWhere('e.nama_perusahaan', 'like', $like)
                ->orWhere('w.activity_type', 'like', $like);
        });
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     * @return array{0: array<int, float>, 1: array<string, float>}
     */
    private function fetchFoodCalories(array $filters): array
    {
        $byUser = [];
        foreach ($this->foodBaseQuery($filters)
            ->selectRaw('f.user_id, SUM(f.total_calories) as kcal')
            ->groupBy('f.user_id')
            ->get() as $row) {
            $byUser[(int) $row->user_id] = (float) ($row->kcal ?? 0);
        }

        $byDate = [];
        foreach ($this->foodBaseQuery($filters)
            ->selectRaw('DATE(f.created_at) as d, SUM(f.total_calories) as kcal')
            ->groupBy('d')
            ->get() as $row) {
            $date = (string) $row->d;
            if ($date !== '') {
                $byDate[$date] = (float) ($row->kcal ?? 0);
            }
        }

        return [$byUser, $byDate];
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     * @return list<array<string, mixed>>
     */
    private function fetchRawFoods(array $filters): array
    {
        $rows = [];
        $lastId = 0;

        do {
            $chunk = $this->foodBaseQuery($filters)
                ->select([
                    'f.id as id',
                    'f.user_id',
                    'f.food_name',
                    'f.meal_type',
                    'f.total_calories',
                    'f.created_at',
                    'e.nama',
                    'e.kode_sid',
                    'e.site',
                    'e.nama_perusahaan',
                ])
                ->where('f.id', '>', $lastId)
                ->orderBy('f.id')
                ->limit(self::CHUNK_SIZE)
                ->get();

            foreach ($chunk as $row) {
                $lastId = (int) $row->id;
                $arr = (array) $row;
                $createdAt = (string) ($arr['created_at'] ?? '');
                $rows[] = [
                    'id' => (int) ($arr['id'] ?? 0),
                    'user_id' => (int) ($arr['user_id'] ?? 0),
                    'kode_sid' => $this->displayOrDash($arr['kode_sid'] ?? null),
                    'nama' => $this->displayOrDash($arr['nama'] ?? null),
                    'site' => $this->siteResolver->resolveOrDash(
                        isset($arr['kode_sid']) ? (string) $arr['kode_sid'] : null,
                        isset($arr['site']) ? (string) $arr['site'] : null,
                    ),
                    'company' => $this->displayOrDash($arr['nama_perusahaan'] ?? null),
                    'food_name' => $this->displayOrDash($arr['food_name'] ?? null),
                    'meal_type' => $this->displayOrDash($arr['meal_type'] ?? null),
                    'total_calories' => $arr['total_calories'] !== null ? round((float) $arr['total_calories'], 1) : null,
                    'created_at' => $createdAt !== ''
                        ? Carbon::parse($createdAt)->format('Y-m-d H:i:s')
                        : '',
                ];
            }
        } while ($chunk->count() === self::CHUNK_SIZE);

        return $rows;
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     */
    private function workoutBaseQuery(array $filters): Builder
    {
        $range = $this->datetimeRange($filters);
        $query = $this->activeEmployeesBaseQuery()
            ->join('workout_analyses as w', 'w.user_id', '=', 'e.id')
            ->whereBetween('w.created_at', [$range['from'], $range['to']]);

        $query = $this->applyEmployeeFilters($query, $filters);

        if ($filters['activity_type'] !== '') {
            $query->where('w.activity_type', 'like', '%'.$filters['activity_type'].'%');
        }

        return $query;
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     */
    private function foodBaseQuery(array $filters): Builder
    {
        $range = $this->datetimeRange($filters);

        return $this->applyEmployeeFilters(
            $this->activeEmployeesBaseQuery()
                ->join('food_analyses as f', 'f.user_id', '=', 'e.id')
                ->whereBetween('f.created_at', [$range['from'], $range['to']]),
            $filters
        );
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     * @return array{sites: list<string>, companies: list<string>, divisions: list<string>, activity_types: list<string>}
     */
    private function buildFilterOptions(array $filters): array
    {
        return Cache::remember(
            'evaluasi_well:workout_activity:filters:v2:'.sha1($filters['from'].'|'.$filters['to']),
            self::CACHE_TTL,
            function () use ($filters): array {
                $base = $this->activeEmployeesBaseQuery();
                $fallbackSites = (clone $base)
                    ->whereNotNull('e.site')
                    ->where('e.site', '<>', '')
                    ->distinct()
                    ->orderBy('e.site')
                    ->pluck('e.site')
                    ->map(static fn (mixed $v): string => (string) $v)
                    ->all();
                $sites = $this->siteResolver->mergeFilterSites($fallbackSites);

                $activityTypes = $this->workoutBaseQuery($filters)
                    ->whereNotNull('w.activity_type')
                    ->where('w.activity_type', '<>', '')
                    ->distinct()
                    ->orderBy('w.activity_type')
                    ->limit(80)
                    ->pluck('w.activity_type')
                    ->map(static fn (mixed $v): string => mb_substr(trim((string) $v), 0, 80))
                    ->filter(static fn (string $v): bool => $v !== '')
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'sites' => $sites,
                    'companies' => (clone $base)
                        ->whereNotNull('e.nama_perusahaan')
                        ->where('e.nama_perusahaan', '<>', '')
                        ->distinct()
                        ->orderBy('e.nama_perusahaan')
                        ->pluck('e.nama_perusahaan')
                        ->map(static fn (mixed $v): string => (string) $v)
                        ->all(),
                    'divisions' => (clone $base)
                        ->whereNotNull('e.divisi')
                        ->where('e.divisi', '<>', '')
                        ->distinct()
                        ->orderBy('e.divisi')
                        ->pluck('e.divisi')
                        ->map(static fn (mixed $v): string => (string) $v)
                        ->all(),
                    'activity_types' => $activityTypes,
                ];
            }
        );
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     */
    private function applyEmployeeFilters(Builder $query, array $filters): Builder
    {
        if ($filters['site'] !== '') {
            $this->siteResolver->applySiteFilter($query, $filters['site']);
        }
        if ($filters['company'] !== '') {
            $query->where('e.nama_perusahaan', $filters['company']);
        }
        if ($filters['division'] !== '') {
            $query->where('e.divisi', 'like', '%'.$filters['division'].'%');
        }
        if (($filters['nama'] ?? '') !== '') {
            $query->where('e.nama', 'like', '%'.$filters['nama'].'%');
        }

        return $query;
    }

    private function activeEmployeesBaseQuery(): Builder
    {
        $query = DB::connection(BewellConnectionService::CONNECTION)
            ->table('employee_profiles as e')
            ->where('e.status_karyawan', 'AKTIF');

        return $this->exclusionRules->applyToQuery($query);
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     * @return array{from:string,to:string}
     */
    private function datetimeRange(array $filters): array
    {
        return [
            'from' => Carbon::parse($filters['from'])->startOfDay()->format('Y-m-d H:i:s'),
            'to' => Carbon::parse($filters['to'])->endOfDay()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     * @return array<string, mixed>
     */
    private function emptyDashboard(array $filters, bool $connectionUp): array
    {
        $emptyTrend = $this->emptyTrend();

        return [
            'connectionUp' => $connectionUp,
            'loadError' => null,
            'filters' => $filters,
            'filterOptions' => [
                'sites' => [],
                'companies' => [],
                'divisions' => [],
                'activity_types' => [],
            ],
            'kpi' => [
                'total_sessions' => 0,
                'active_users' => 0,
                'total_minutes' => 0,
                'total_km' => 0.0,
                'kcal_out' => 0,
                'kcal_in' => 0,
                'avg_sessions_per_week' => 0.0,
                'avg_sessions_per_user' => 0.0,
                'period_days' => Carbon::parse($filters['from'])->diffInDays(Carbon::parse($filters['to'])) + 1,
            ],
            'trendDaily' => $emptyTrend,
            'trendWeekly' => $emptyTrend,
            'distribution' => ['labels' => [], 'counts' => []],
            'leaderboard' => [],
            'periodLabel' => $this->periodLabel($filters),
        ];
    }

    /**
     * @return array{labels: list<string>, sesi: list<int>, users: list<int>, kcal_out: list<float>, kcal_in: list<float>, menit: list<float>, km: list<float>}
     */
    private function emptyTrend(): array
    {
        return [
            'labels' => [],
            'sesi' => [],
            'users' => [],
            'kcal_out' => [],
            'kcal_in' => [],
            'menit' => [],
            'km' => [],
        ];
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     */
    private function periodLabel(array $filters): string
    {
        $from = Carbon::parse($filters['from']);
        $to = Carbon::parse($filters['to']);

        return $from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y');
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value))->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function displayOrDash(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : '-';
    }
}
