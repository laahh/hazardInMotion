<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Ringkasan upload makanan/olahraga per minggu (BeWell read-only).
 */
final class SportEvaluationWeeklyUploadService
{
    private const WEEK_HISTORY = 12;

    private const CACHE_TTL = 120;

    public function __construct(
        private readonly BewellConnectionService $connection,
        private readonly SportEvaluationKaryawanWellSiteResolver $siteResolver,
    ) {}

    /**
     * @return array{
     *     connectionUp: bool,
     *     filters: array{week:string,site:string,company:string,division:string,upload_type:string},
     *     weekOptions: list<array{key:string,label:string,start:string,end:string}>,
     *     weekLabel: string,
     *     kpi: array{uploaders:int,food_uploaders:int,workout_uploaders:int,food_entries:int,workout_entries:int},
     *     trendLabels: list<string>,
     *     trendUploaders: list<int>,
     *     trendFood: list<int>,
     *     trendWorkout: list<int>,
     *     filterOptions: array{sites:list<string>,companies:list<string>,divisions:list<string>}
     * }
     */
    public function dashboard(Request $request): array
    {
        $weekOptions = $this->buildWeekOptions();
        $filters = $this->readFilters($request, $weekOptions);
        $selectedWeek = $this->resolveWeek($filters['week'], $weekOptions);

        $empty = [
            'connectionUp' => false,
            'filters' => $filters,
            'weekOptions' => $weekOptions,
            'weekLabel' => $selectedWeek['label'],
            'kpi' => [
                'uploaders' => 0,
                'food_uploaders' => 0,
                'workout_uploaders' => 0,
                'food_entries' => 0,
                'workout_entries' => 0,
            ],
            'trendLabels' => array_map(static fn (array $w): string => $w['label'], $weekOptions),
            'trendUploaders' => array_fill(0, count($weekOptions), 0),
            'trendFood' => array_fill(0, count($weekOptions), 0),
            'trendWorkout' => array_fill(0, count($weekOptions), 0),
            'filterOptions' => [
                'sites' => [],
                'companies' => [],
                'divisions' => [],
            ],
        ];

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            $cacheKey = 'evaluasi_well:weekly_uploads:dash:v2:'.sha1(json_encode([
                $filters,
                $selectedWeek['key'],
            ], JSON_THROW_ON_ERROR));

            $cached = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters, $selectedWeek, $weekOptions): array {
                $kpi = $this->buildWeekKpi($selectedWeek['start'], $selectedWeek['end'], $filters);
                $trend = $this->buildTrend($weekOptions, $filters);
                $filterOptions = $this->buildFilterOptions();

                return compact('kpi', 'trend', 'filterOptions');
            });

            return [
                'connectionUp' => true,
                'filters' => $filters,
                'weekOptions' => $weekOptions,
                'weekLabel' => $selectedWeek['label'],
                'kpi' => $cached['kpi'],
                'trendLabels' => $cached['trend']['labels'],
                'trendUploaders' => $cached['trend']['uploaders'],
                'trendFood' => $cached['trend']['food'],
                'trendWorkout' => $cached['trend']['workout'],
                'filterOptions' => $cached['filterOptions'],
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * DataTables server-side daftar karyawan yang upload di minggu terpilih.
     *
     * @return array<string, mixed>
     */
    public function datatable(Request $request): array
    {
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
            $weekOptions = $this->buildWeekOptions();
            $filters = $this->readFilters($request, $weekOptions);
            $week = $this->resolveWeek($filters['week'], $weekOptions);
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
                0 => 'e.nama',
                1 => 'e.nama_perusahaan',
                2 => 'e.departement',
                3 => 'e.divisi',
                4 => 'food_count',
                5 => 'workout_count',
                6 => 'total_count',
                7 => 'last_upload_at',
            ];
            $orderColumn = $orderable[$orderColumnIndex] ?? 'total_count';

            $base = $this->uploadersBaseQuery($week['start'], $week['end'], $filters);
            $recordsTotal = (int) (clone $base)->count();

            $filtered = $this->applySearch(clone $base, $search);
            $recordsFiltered = (int) (clone $filtered)->count();

            $rows = (clone $filtered)
                ->orderBy($orderColumn, $orderDir)
                ->orderBy('e.nama')
                ->offset($start)
                ->limit($length)
                ->get();

            $data = [];
            foreach ($rows as $row) {
                $foodCount = (int) ($row->food_count ?? 0);
                $workoutCount = (int) ($row->workout_count ?? 0);
                $lastAt = $row->last_upload_at
                    ? Carbon::parse((string) $row->last_upload_at)->format('d M Y H:i')
                    : '-';

                $data[] = [
                    'id' => (int) $row->id,
                    'nama' => (string) ($row->nama ?: 'User #'.$row->id),
                    'kode_sid' => (string) ($row->kode_sid ?: '-'),
                    'site' => $this->siteResolver->resolveOrDash(
                        isset($row->kode_sid) ? (string) $row->kode_sid : null,
                        isset($row->site) ? (string) $row->site : null,
                    ),
                    'company' => $this->displayOrDash($row->nama_perusahaan ?? null),
                    'departement' => $this->displayOrDash($row->departement ?? null),
                    'divisi' => $this->displayOrDash($row->divisi ?? null),
                    'food_count' => $foodCount,
                    'workout_count' => $workoutCount,
                    'total_count' => $foodCount + $workoutCount,
                    'last_upload_at' => $lastAt,
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

            return [
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal memuat data upload mingguan.',
            ];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(Request $request): array
    {
        if (! $this->connection->isUp()) {
            return [];
        }

        $weekOptions = $this->buildWeekOptions();
        $filters = $this->readFilters($request, $weekOptions);
        $week = $this->resolveWeek($filters['week'], $weekOptions);
        $search = trim((string) $request->query('search', ''));

        $rows = $this->applySearch(
            $this->uploadersBaseQuery($week['start'], $week['end'], $filters),
            $search,
        )
            ->orderByDesc('total_count')
            ->orderBy('e.nama')
            ->get();

        $export = [];
        foreach ($rows as $row) {
            $foodCount = (int) ($row->food_count ?? 0);
            $workoutCount = (int) ($row->workout_count ?? 0);
            $export[] = [
                'nama' => (string) ($row->nama ?: '-'),
                'kode_sid' => (string) ($row->kode_sid ?: '-'),
                'site' => $this->siteResolver->resolveOrDash(
                    isset($row->kode_sid) ? (string) $row->kode_sid : null,
                    isset($row->site) ? (string) $row->site : null,
                ),
                'company' => $this->displayOrDash($row->nama_perusahaan ?? null),
                'departement' => $this->displayOrDash($row->departement ?? null),
                'divisi' => $this->displayOrDash($row->divisi ?? null),
                'food_count' => $foodCount,
                'workout_count' => $workoutCount,
                'total_count' => $foodCount + $workoutCount,
                'last_upload_at' => $row->last_upload_at
                    ? Carbon::parse((string) $row->last_upload_at)->format('d M Y H:i')
                    : '-',
            ];
        }

        return $export;
    }

    /**
     * @param  list<array{key:string,label:string,start:string,end:string}>  $weekOptions
     * @return array{week:string,site:string,company:string,division:string,upload_type:string}
     */
    public function readFilters(Request $request, array $weekOptions = []): array
    {
        $weekOptions = $weekOptions !== [] ? $weekOptions : $this->buildWeekOptions();
        $read = static fn (mixed $value): string => is_string($value)
            ? mb_substr(trim($value), 0, 150)
            : '';

        $week = $read($request->input('week', $request->query('week', '')));
        $validKeys = array_column($weekOptions, 'key');
        if ($week === '' || ! in_array($week, $validKeys, true)) {
            $week = $weekOptions[0]['key'] ?? Carbon::now()->format('o-\WW');
        }

        $uploadType = strtolower($read($request->input('upload_type', $request->query('upload_type', ''))));
        if (! in_array($uploadType, ['food', 'workout', 'both'], true)) {
            $uploadType = '';
        }

        return [
            'week' => $week,
            'site' => $read($request->input('site', $request->query('site', ''))),
            'company' => $read($request->input('company', $request->query('company', ''))),
            'division' => $read($request->input('division', $request->query('division', ''))),
            'upload_type' => $uploadType,
        ];
    }

    /**
     * @return list<array{key:string,label:string,start:string,end:string}>
     */
    private function buildWeekOptions(): array
    {
        $options = [];
        $cursor = Carbon::now()->startOfWeek(Carbon::MONDAY);

        for ($i = 0; $i < self::WEEK_HISTORY; $i++) {
            $start = $cursor->copy()->subWeeks($i)->startOfWeek(Carbon::MONDAY);
            $end = $start->copy()->endOfWeek(Carbon::SUNDAY);
            $options[] = [
                'key' => $start->format('o-\WW'),
                'label' => $start->translatedFormat('d M').' – '.$end->translatedFormat('d M Y'),
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
            ];
        }

        return $options;
    }

    /**
     * @param  list<array{key:string,label:string,start:string,end:string}>  $weekOptions
     * @return array{key:string,label:string,start:string,end:string}
     */
    private function resolveWeek(string $weekKey, array $weekOptions): array
    {
        foreach ($weekOptions as $option) {
            if ($option['key'] === $weekKey) {
                return $option;
            }
        }

        return $weekOptions[0];
    }

    /**
     * @param  array{week:string,site:string,company:string,division:string,upload_type:string}  $filters
     * @return array{uploaders:int,food_uploaders:int,workout_uploaders:int,food_entries:int,workout_entries:int}
     */
    private function buildWeekKpi(string $weekStart, string $weekEnd, array $filters): array
    {
        $foodUsers = $this->scopedEmployeeIdsWithFood($weekStart, $weekEnd, $filters);
        $workoutUsers = $this->scopedEmployeeIdsWithWorkout($weekStart, $weekEnd, $filters);

        $uploaders = match ($filters['upload_type']) {
            'food' => $foodUsers,
            'workout' => $workoutUsers,
            'both' => $foodUsers->intersect($workoutUsers)->values(),
            default => $foodUsers->merge($workoutUsers)->unique()->values(),
        };

        return [
            'uploaders' => $uploaders->count(),
            'food_uploaders' => $foodUsers->count(),
            'workout_uploaders' => $workoutUsers->count(),
            'food_entries' => $this->countFoodEntries($weekStart, $weekEnd, $filters),
            'workout_entries' => $this->countWorkoutEntries($weekStart, $weekEnd, $filters),
        ];
    }

    /**
     * @param  list<array{key:string,label:string,start:string,end:string}>  $weekOptions
     * @param  array{week:string,site:string,company:string,division:string,upload_type:string}  $filters
     * @return array{labels:list<string>,uploaders:list<int>,food:list<int>,workout:list<int>}
     */
    private function buildTrend(array $weekOptions, array $filters): array
    {
        // Chart menampilkan kronologis lama → baru.
        $chronological = array_reverse($weekOptions);
        $labels = [];
        $uploaders = [];
        $food = [];
        $workout = [];

        foreach ($chronological as $week) {
            $kpi = $this->buildWeekKpi($week['start'], $week['end'], $filters);
            $labels[] = $week['label'];
            $uploaders[] = $kpi['uploaders'];
            $food[] = $kpi['food_uploaders'];
            $workout[] = $kpi['workout_uploaders'];
        }

        return compact('labels', 'uploaders', 'food', 'workout');
    }

    /**
     * @return array{sites:list<string>,companies:list<string>,divisions:list<string>}
     */
    private function buildFilterOptions(): array
    {
        return Cache::remember('evaluasi_well:weekly_uploads:filters_v2', 300, function (): array {
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
            ];
        });
    }

    /**
     * @param  array{week:string,site:string,company:string,division:string,upload_type:string}  $filters
     */
    private function uploadersBaseQuery(string $weekStart, string $weekEnd, array $filters): Builder
    {
        $db = DB::connection(BewellConnectionService::CONNECTION);

        $foodSub = $db->table('food_analyses')
            ->selectRaw('user_id, COUNT(*) AS food_count, MAX(created_at) AS food_last_at')
            ->where('source_type', 'photo')
            ->whereNotNull('user_id')
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->groupBy('user_id');

        $workoutSub = $db->table('workout_analyses')
            ->selectRaw('user_id, COUNT(*) AS workout_count, MAX(created_at) AS workout_last_at')
            ->whereNotNull('user_id')
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->groupBy('user_id');

        $query = $this->activeEmployeesBaseQuery()
            ->leftJoinSub($foodSub, 'f', 'f.user_id', '=', 'e.id')
            ->leftJoinSub($workoutSub, 'w', 'w.user_id', '=', 'e.id')
            ->select([
                'e.id',
                'e.nama',
                'e.kode_sid',
                'e.site',
                'e.nama_perusahaan',
                'e.departement',
                'e.divisi',
            ])
            ->selectRaw('COALESCE(f.food_count, 0) AS food_count')
            ->selectRaw('COALESCE(w.workout_count, 0) AS workout_count')
            ->selectRaw('(COALESCE(f.food_count, 0) + COALESCE(w.workout_count, 0)) AS total_count')
            ->selectRaw(
                'CASE
                    WHEN f.food_last_at IS NULL THEN w.workout_last_at
                    WHEN w.workout_last_at IS NULL THEN f.food_last_at
                    ELSE GREATEST(f.food_last_at, w.workout_last_at)
                END AS last_upload_at'
            );

        $query = $this->applyEmployeeFilters($query, $filters);

        return match ($filters['upload_type']) {
            'food' => $query->where('f.food_count', '>', 0),
            'workout' => $query->where('w.workout_count', '>', 0),
            'both' => $query->where('f.food_count', '>', 0)->where('w.workout_count', '>', 0),
            default => $query->where(function (Builder $outer): void {
                $outer->where('f.food_count', '>', 0)
                    ->orWhere('w.workout_count', '>', 0);
            }),
        };
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }

        $like = '%'.$search.'%';

        return $query->where(function (Builder $inner) use ($like, $search): void {
            $inner->where('e.nama', 'like', $like)
                ->orWhere('e.kode_sid', 'like', $like)
                ->orWhere('e.nama_perusahaan', 'like', $like)
                ->orWhere('e.departement', 'like', $like)
                ->orWhere('e.divisi', 'like', $like);
            $this->siteResolver->orWhereSiteMatchesSearch($inner, $like, $search);
        });
    }

    /**
     * @param  array{week:string,site:string,company:string,division:string,upload_type:string}  $filters
     * @return Collection<int, int>
     */
    private function scopedEmployeeIdsWithFood(string $weekStart, string $weekEnd, array $filters): Collection
    {
        $query = $this->activeEmployeesBaseQuery()
            ->join('food_analyses as f', 'f.user_id', '=', 'e.id')
            ->where('f.source_type', 'photo')
            ->whereBetween('f.created_at', [$weekStart, $weekEnd]);

        $query = $this->applyEmployeeFilters($query, $filters);

        return $query->distinct()->pluck('e.id')->map(static fn ($id): int => (int) $id);
    }

    /**
     * @param  array{week:string,site:string,company:string,division:string,upload_type:string}  $filters
     * @return Collection<int, int>
     */
    private function scopedEmployeeIdsWithWorkout(string $weekStart, string $weekEnd, array $filters): Collection
    {
        $query = $this->activeEmployeesBaseQuery()
            ->join('workout_analyses as w', 'w.user_id', '=', 'e.id')
            ->whereBetween('w.created_at', [$weekStart, $weekEnd]);

        $query = $this->applyEmployeeFilters($query, $filters);

        return $query->distinct()->pluck('e.id')->map(static fn ($id): int => (int) $id);
    }

    /**
     * @param  array{week:string,site:string,company:string,division:string,upload_type:string}  $filters
     */
    private function countFoodEntries(string $weekStart, string $weekEnd, array $filters): int
    {
        $query = $this->activeEmployeesBaseQuery()
            ->join('food_analyses as f', 'f.user_id', '=', 'e.id')
            ->where('f.source_type', 'photo')
            ->whereBetween('f.created_at', [$weekStart, $weekEnd]);

        return (int) $this->applyEmployeeFilters($query, $filters)->count('f.id');
    }

    /**
     * @param  array{week:string,site:string,company:string,division:string,upload_type:string}  $filters
     */
    private function countWorkoutEntries(string $weekStart, string $weekEnd, array $filters): int
    {
        $query = $this->activeEmployeesBaseQuery()
            ->join('workout_analyses as w', 'w.user_id', '=', 'e.id')
            ->whereBetween('w.created_at', [$weekStart, $weekEnd]);

        return (int) $this->applyEmployeeFilters($query, $filters)->count('w.id');
    }

    /**
     * @param  array{week:string,site:string,company:string,division:string,upload_type:string}  $filters
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
        return DB::connection(BewellConnectionService::CONNECTION)
            ->table('employee_profiles as e')
            ->where('e.status_karyawan', 'AKTIF')
            ->whereRaw('UPPER(TRIM(COALESCE(e.jabatan_fungsional, \'\'))) <> ?', ['VISITOR']);
    }

    private function displayOrDash(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : '-';
    }
}
