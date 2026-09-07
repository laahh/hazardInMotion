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
        $empty = $this->emptyDashboard($filters);

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            $payload = $this->loadPayload($filters);
            $filterOptions = $this->buildFilterOptions($filters);

            return [
                'connectionUp' => true,
                'filters' => $filters,
                'filterOptions' => $filterOptions,
                'kpi' => $payload['kpi'],
                'trendDaily' => $payload['trendDaily'],
                'trendWeekly' => $payload['trendWeekly'],
                'distribution' => $payload['distribution'],
                'topCompanies' => $payload['topCompanies'],
                'periodLabel' => $this->periodLabel($filters),
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(Request $request): array
    {
        return $this->paginateDataset($request, 'users', [
            0 => 'nama',
            1 => 'kode_sid',
            2 => 'site',
            3 => 'company',
            4 => 'sesi',
            5 => 'duration_minutes',
            6 => 'distance_km',
            7 => 'kcal_out',
            8 => 'kcal_in',
            9 => 'last_workout_at',
        ], 'sesi', [
            'nama', 'kode_sid', 'site', 'company', 'divisi',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rawDatatable(Request $request): array
    {
        return $this->paginateDataset($request, 'rawWorkouts', [
            0 => 'local_datetime',
            1 => 'nama',
            2 => 'activity_type',
            3 => 'duration_minutes',
            4 => 'distance_km',
            5 => 'calories_kcal',
            6 => 'avg_heart_rate',
        ], 'created_at', [
            'nama', 'kode_sid', 'activity_type', 'site', 'company',
        ]);
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
            'rawWorkouts' => $payload['rawWorkouts'],
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
            $from = Carbon::now()->subDays(29)->startOfDay();
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
        ];
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     * @return array<string, mixed>
     */
    public function loadPayload(array $filters): array
    {
        $cacheKey = 'evaluasi_well:workout_activity:v1:'.sha1(json_encode($filters, JSON_THROW_ON_ERROR));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters): array {
            $parsed = $this->fetchParsedWorkouts($filters);
            [$foodByUser, $foodByDate] = $this->fetchFoodCalories($filters);
            $from = Carbon::parse($filters['from']);
            $to = Carbon::parse($filters['to']);
            $periodDays = $from->diffInDays($to) + 1;

            return $this->aggregator->aggregate(
                $parsed,
                $foodByUser,
                $foodByDate,
                $periodDays,
                $filters['from'],
                $filters['to'],
            );
        });
    }

    /**
     * @param  array<int, string>  $orderable
     * @param  list<string>  $searchKeys
     * @return array<string, mixed>
     */
    private function paginateDataset(
        Request $request,
        string $datasetKey,
        array $orderable,
        string $defaultOrder,
        array $searchKeys,
    ): array {
        $draw = (int) $request->input('draw', 1);

        if (! $this->connection->isUp()) {
            return [
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ];
        }

        try {
            $filters = $this->readFilters($request);
            $payload = $this->loadPayload($filters);
            /** @var list<array<string, mixed>> $rows */
            $rows = $payload[$datasetKey] ?? [];
            $recordsTotal = count($rows);

            $search = mb_strtolower(trim((string) $request->input('search.value', '')));
            if ($search !== '') {
                $rows = array_values(array_filter($rows, static function (array $row) use ($search, $searchKeys): bool {
                    foreach ($searchKeys as $key) {
                        if (mb_strpos(mb_strtolower((string) ($row[$key] ?? '')), $search) !== false) {
                            return true;
                        }
                    }

                    return false;
                }));
            }

            $orderColumnIndex = (int) data_get($request->input('order'), '0.column', 0);
            $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'desc')) === 'asc'
                ? 'asc'
                : 'desc';
            $orderKey = $orderable[$orderColumnIndex] ?? $defaultOrder;

            usort($rows, static function (array $a, array $b) use ($orderKey, $orderDir, $defaultOrder): int {
                $left = $a[$orderKey] ?? $a[$defaultOrder] ?? null;
                $right = $b[$orderKey] ?? $b[$defaultOrder] ?? null;
                if (is_numeric($left) && is_numeric($right)) {
                    $cmp = (float) $left <=> (float) $right;
                } else {
                    $cmp = strcmp((string) $left, (string) $right);
                }

                return $orderDir === 'asc' ? $cmp : -$cmp;
            });

            $start = max(0, (int) $request->input('start', 0));
            $length = (int) $request->input('length', 10);
            if ($length < 1) {
                $length = 10;
            }
            if ($length > 100) {
                $length = 100;
            }

            $page = array_slice($rows, $start, $length);

            return [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => count($rows),
                'data' => $page,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal memuat data tren aktivitas.',
            ];
        }
    }

    /**
     * @param  array{from:string,to:string,site:string,company:string,division:string,activity_type:string}  $filters
     * @return list<array<string, mixed>>
     */
    private function fetchParsedWorkouts(array $filters): array
    {
        $parsed = [];

        $this->workoutBaseQuery($filters)
            ->select([
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
            ])
            ->chunkById(self::CHUNK_SIZE, function ($rows) use (&$parsed): void {
                foreach ($rows as $row) {
                    $arr = (array) $row;
                    $site = $this->siteResolver->resolveOrDash(
                        isset($arr['kode_sid']) ? (string) $arr['kode_sid'] : null,
                        isset($arr['site']) ? (string) $arr['site'] : null,
                    );
                    $parsed[] = $this->aggregator->parseWorkoutRow($arr, $site);
                }
            }, 'w.id', 'id');

        return $parsed;
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
        $this->foodBaseQuery($filters)
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
            ->chunkById(self::CHUNK_SIZE, function ($chunk) use (&$rows): void {
                foreach ($chunk as $row) {
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
            }, 'f.id', 'id');

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
            'evaluasi_well:workout_activity:filters:v1:'.sha1($filters['from'].'|'.$filters['to']),
            self::CACHE_TTL,
            function () use ($filters): array {
                $base = $this->activeEmployeesBaseQuery();

                $sitePairs = (clone $base)->get(['e.kode_sid', 'e.site']);
                $resolvedSites = [];
                foreach ($sitePairs as $pair) {
                    $site = $this->siteResolver->resolve(
                        isset($pair->kode_sid) ? (string) $pair->kode_sid : null,
                        isset($pair->site) ? (string) $pair->site : null,
                    );
                    if ($site !== '') {
                        $resolvedSites[$site] = true;
                    }
                }
                $sites = array_keys($resolvedSites);
                sort($sites, SORT_STRING);

                $range = $this->datetimeRange($filters);
                $activityTypes = $this->activeEmployeesBaseQuery()
                    ->join('workout_analyses as w', 'w.user_id', '=', 'e.id')
                    ->whereBetween('w.created_at', [$range['from'], $range['to']])
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
    private function emptyDashboard(array $filters): array
    {
        $emptyTrend = $this->emptyTrend();

        return [
            'connectionUp' => false,
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
            'topCompanies' => ['labels' => [], 'counts' => []],
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
