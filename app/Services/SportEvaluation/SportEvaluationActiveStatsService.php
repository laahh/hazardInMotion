<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Agregasi statistik user aktif BeWell per dimensi organisasi (read-only).
 *
 * Aktif (luas) = food photo / workout / komunitas / Main Bareng dalam rentang minggu.
 * Evaluasi = volume food_analyses (photo) + workout_analyses dalam minggu yang sama.
 */
final class SportEvaluationActiveStatsService
{
    private const CACHE_TTL = 300;

    private const CHART_TOP_N = 15;

    private const LEADERBOARD_LIMIT = 20;

    private const TREND_WEEKS = 12;

    private const FOOTNOTE = 'User aktif (luas) = food photo / workout / komunitas / Main Bareng minggu terpilih. '
        .'Evaluasi = jumlah upload makanan (photo) + olahraga. '
        .'Breakdown dimensi memakai karyawan status AKTIF (exclude VISITOR); angka KPI kartu bisa berbeda.';

    /** @var array<string, string> */
    private const DIMENSION_COLUMNS = [
        'site' => 'e.site',
        'company' => 'e.nama_perusahaan',
        'jabatan' => 'e.jabatan_fungsional',
    ];

    /** @var array<string, string> */
    private const DIMENSION_LABELS = [
        'site' => 'Site',
        'company' => 'Perusahaan',
        'jabatan' => 'Jabatan',
    ];

    /** @var array<string, string> */
    private const DIMENSION_ICONS = [
        'site' => 'solar:map-point-bold',
        'company' => 'solar:buildings-2-bold',
        'jabatan' => 'solar:case-round-bold',
    ];

    /** @var list<string> */
    private const BAR_CLASSES = [
        'bg-primary-600',
        'bg-orange',
        'bg-yellow',
        'bg-success-main',
        'bg-info-main',
        'bg-indigo',
    ];

    public function __construct(
        private readonly BewellConnectionService $connection,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     dimension: string,
     *     dimension_label: string,
     *     footnote: string,
     *     message: string|null,
     *     week: array{start: string, end: string, label: string, prev_start: string},
     *     week_options: list<array{start: string, label: string}>,
     *     weekly_trend: array{labels: list<string>, active_users: list<int>, week_starts: list<string>},
     *     summary: array{
     *         active_users: int,
     *         food_evals: int,
     *         workout_evals: int,
     *         total_evals: int,
     *         week_increase: int,
     *         kpi_card_total: int,
     *         groups: int
     *     },
     *     overview: list<array{
     *         dimension: string,
     *         label: string,
     *         icon: string,
     *         groups: int,
     *         active_users: int,
     *         food_evals: int,
     *         workout_evals: int,
     *         total_evals: int,
     *         top_name: string,
     *         top_active: int,
     *         top_evals: int
     *     }>,
     *     rows: list<array{
     *         name: string,
     *         active_users: int,
     *         food_evals: int,
     *         workout_evals: int,
     *         total_evals: int,
     *         pct: float,
     *         bar_class: string
     *     }>,
     *     chart: array{
     *         categories: list<string>,
     *         active_users: list<int>,
     *         food_evals: list<int>,
     *         workout_evals: list<int>
     *     },
     *     leaderboard: list<array{
     *         rank: int,
     *         user_id: int,
     *         nama: string,
     *         site: string,
     *         perusahaan: string,
     *         jabatan: string,
     *         food_evals: int,
     *         workout_evals: int,
     *         total_evals: int,
     *         is_active: bool
     *     }>
     * }
     */
    public function getStats(string $dimension, ?string $weekStart = null): array
    {
        $dimension = $this->resolveDimension($dimension);
        $week = $this->resolveWeek($weekStart);
        $empty = $this->emptyPayload($dimension, $week, 'Koneksi BeWell belum tersedia.');

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            $stats = Cache::remember(
                'evaluasi_well:active_stats:v1:'.$dimension.':'.$week['start'],
                self::CACHE_TTL,
                function () use ($dimension, $week): array {
                    return $this->buildStats($dimension, $week);
                }
            );

            $stats['overview'] = $this->getOverview($week);
            $stats['weekly_trend'] = $this->getWeeklyTrend();
            $stats['week_options'] = $this->buildWeekOptions($stats['weekly_trend']);

            return $stats;
        } catch (Throwable $e) {
            report($e);

            return $this->emptyPayload($dimension, $week, 'Gagal memuat statistik user aktif.');
        }
    }

    /**
     * Jumlah user aktif (luas) dalam rentang — single source of truth dengan KPI kartu.
     */
    public function countActiveUsersInRange(string $from, string $to): int
    {
        $db = DB::connection(BewellConnectionService::CONNECTION);

        $row = $db->selectOne(
            'SELECT COUNT(*) AS c FROM ('.$this->activeUsersUnionSql().') AS active_users',
            $this->activeUsersUnionBindings($from, $to)
        );

        return (int) ($row->c ?? 0);
    }

    /**
     * @param  array{start: string, end: string, label: string, prev_start: string}  $week
     * @return list<array{
     *     dimension: string,
     *     label: string,
     *     icon: string,
     *     groups: int,
     *     active_users: int,
     *     food_evals: int,
     *     workout_evals: int,
     *     total_evals: int,
     *     top_name: string,
     *     top_active: int,
     *     top_evals: int
     * }>
     */
    public function getOverview(array $week): array
    {
        if (! $this->connection->isUp()) {
            return $this->emptyOverview();
        }

        try {
            return Cache::remember(
                'evaluasi_well:active_stats:overview:v1:'.$week['start'],
                self::CACHE_TTL,
                function () use ($week): array {
                    $overview = [];

                    foreach (array_keys(self::DIMENSION_COLUMNS) as $dimension) {
                        $stats = Cache::remember(
                            'evaluasi_well:active_stats:v1:'.$dimension.':'.$week['start'],
                            self::CACHE_TTL,
                            function () use ($dimension, $week): array {
                                return $this->buildStats($dimension, $week);
                            }
                        );

                        $summary = $stats['summary'];
                        $top = $stats['rows'][0] ?? null;

                        $overview[] = [
                            'dimension' => $dimension,
                            'label' => self::DIMENSION_LABELS[$dimension],
                            'icon' => self::DIMENSION_ICONS[$dimension],
                            'groups' => (int) ($summary['groups'] ?? count($stats['rows'])),
                            'active_users' => (int) $summary['active_users'],
                            'food_evals' => (int) $summary['food_evals'],
                            'workout_evals' => (int) $summary['workout_evals'],
                            'total_evals' => (int) $summary['total_evals'],
                            'top_name' => $top !== null ? (string) $top['name'] : '-',
                            'top_active' => $top !== null ? (int) $top['active_users'] : 0,
                            'top_evals' => $top !== null ? (int) $top['total_evals'] : 0,
                        ];
                    }

                    return $overview;
                }
            );
        } catch (Throwable $e) {
            report($e);

            return $this->emptyOverview();
        }
    }

    /**
     * @return array{labels: list<string>, active_users: list<int>, week_starts: list<string>}
     */
    public function getWeeklyTrend(): array
    {
        if (! $this->connection->isUp()) {
            return ['labels' => [], 'active_users' => [], 'week_starts' => []];
        }

        try {
            return Cache::remember(
                'evaluasi_well:active_stats:weekly_trend:v1',
                self::CACHE_TTL,
                function (): array {
                    $now = Carbon::now();
                    $labels = [];
                    $activeUsers = [];
                    $weekStarts = [];

                    for ($i = self::TREND_WEEKS - 1; $i >= 0; $i--) {
                        $start = $now->copy()->subWeeks($i)->startOfWeek();
                        $end = $now->copy()->subWeeks($i)->endOfWeek();
                        $count = $this->countActiveUsersInRange(
                            $start->format('Y-m-d H:i:s'),
                            $end->format('Y-m-d H:i:s'),
                        );

                        $labels[] = $start->format('d M');
                        $activeUsers[] = $count;
                        $weekStarts[] = $start->toDateString();
                    }

                    return [
                        'labels' => $labels,
                        'active_users' => $activeUsers,
                        'week_starts' => $weekStarts,
                    ];
                }
            );
        } catch (Throwable $e) {
            report($e);

            return ['labels' => [], 'active_users' => [], 'week_starts' => []];
        }
    }

    /**
     * @param  array{start: string, end: string, label: string, prev_start: string}  $week
     * @return array{
     *     available: bool,
     *     dimension: string,
     *     dimension_label: string,
     *     footnote: string,
     *     message: string|null,
     *     week: array{start: string, end: string, label: string, prev_start: string},
     *     week_options: list<array{start: string, label: string}>,
     *     weekly_trend: array{labels: list<string>, active_users: list<int>, week_starts: list<string>},
     *     summary: array{
     *         active_users: int,
     *         food_evals: int,
     *         workout_evals: int,
     *         total_evals: int,
     *         week_increase: int,
     *         kpi_card_total: int,
     *         groups: int
     *     },
     *     overview: list<array{
     *         dimension: string,
     *         label: string,
     *         icon: string,
     *         groups: int,
     *         active_users: int,
     *         food_evals: int,
     *         workout_evals: int,
     *         total_evals: int,
     *         top_name: string,
     *         top_active: int,
     *         top_evals: int
     *     }>,
     *     rows: list<array{
     *         name: string,
     *         active_users: int,
     *         food_evals: int,
     *         workout_evals: int,
     *         total_evals: int,
     *         pct: float,
     *         bar_class: string
     *     }>,
     *     chart: array{
     *         categories: list<string>,
     *         active_users: list<int>,
     *         food_evals: list<int>,
     *         workout_evals: list<int>
     *     },
     *     leaderboard: list<array{
     *         rank: int,
     *         user_id: int,
     *         nama: string,
     *         site: string,
     *         perusahaan: string,
     *         jabatan: string,
     *         food_evals: int,
     *         workout_evals: int,
     *         total_evals: int,
     *         is_active: bool
     *     }>
     * }
     */
    private function buildStats(string $dimension, array $week): array
    {
        $from = $week['start'].' 00:00:00';
        $to = Carbon::parse($week['end'])->endOfDay()->format('Y-m-d H:i:s');
        $prevFrom = $week['prev_start'].' 00:00:00';
        $prevTo = Carbon::parse($week['prev_start'])->endOfWeek()->endOfDay()->format('Y-m-d H:i:s');

        $kpiCardTotal = $this->countActiveUsersInRange($from, $to);
        $prevKpi = $this->countActiveUsersInRange($prevFrom, $prevTo);
        $weekIncrease = max(0, $kpiCardTotal - $prevKpi);

        $rows = $this->queryDimensionRows($dimension, $from, $to);
        $activeScoped = (int) array_sum(array_column($rows, 'active_users'));
        $foodAll = (int) array_sum(array_column($rows, 'food_evals'));
        $workoutAll = (int) array_sum(array_column($rows, 'workout_evals'));
        $totalEvals = $foodAll + $workoutAll;

        foreach ($rows as $i => $row) {
            $rows[$i]['pct'] = $activeScoped > 0
                ? round($row['active_users'] / $activeScoped * 100, 1)
                : 0.0;
            $rows[$i]['bar_class'] = self::BAR_CLASSES[$i % count(self::BAR_CLASSES)];
        }

        return [
            'available' => true,
            'dimension' => $dimension,
            'dimension_label' => self::DIMENSION_LABELS[$dimension],
            'footnote' => self::FOOTNOTE,
            'message' => null,
            'week' => $week,
            'week_options' => [],
            'weekly_trend' => ['labels' => [], 'active_users' => [], 'week_starts' => []],
            'summary' => [
                'active_users' => $activeScoped,
                'food_evals' => $foodAll,
                'workout_evals' => $workoutAll,
                'total_evals' => $totalEvals,
                'week_increase' => $weekIncrease,
                'kpi_card_total' => $kpiCardTotal,
                'groups' => count($rows),
            ],
            'overview' => [],
            'rows' => $rows,
            'chart' => $this->buildChartPayload($rows),
            'leaderboard' => $this->queryLeaderboard($from, $to),
        ];
    }

    /**
     * @return list<array{
     *     name: string,
     *     active_users: int,
     *     food_evals: int,
     *     workout_evals: int,
     *     total_evals: int,
     *     pct: float,
     *     bar_class: string
     * }>
     */
    private function queryDimensionRows(string $dimension, string $from, string $to): array
    {
        $column = self::DIMENSION_COLUMNS[$dimension];
        $dimExpr = 'COALESCE(NULLIF(TRIM('.$column.'), \'\'), \'Tidak diketahui\')';
        $db = DB::connection(BewellConnectionService::CONNECTION);

        $sql = '
            SELECT
                '.$dimExpr.' AS dim_name,
                COUNT(DISTINCT a.user_id) AS active_users,
                COALESCE(SUM(f.food_cnt), 0) AS food_evals,
                COALESCE(SUM(w.workout_cnt), 0) AS workout_evals
            FROM ('.$this->activeUsersUnionSql().') AS a
            INNER JOIN employee_profiles e ON e.id = a.user_id
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS food_cnt
                FROM food_analyses
                WHERE source_type = ?
                  AND user_id IS NOT NULL
                  AND created_at BETWEEN ? AND ?
                GROUP BY user_id
            ) AS f ON f.user_id = a.user_id
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS workout_cnt
                FROM workout_analyses
                WHERE user_id IS NOT NULL
                  AND created_at BETWEEN ? AND ?
                GROUP BY user_id
            ) AS w ON w.user_id = a.user_id
            WHERE e.status_karyawan = ?
              AND UPPER(TRIM(COALESCE(e.jabatan_fungsional, \'\'))) <> ?
            GROUP BY '.$dimExpr.'
            ORDER BY active_users DESC, food_evals + workout_evals DESC, dim_name ASC
        ';

        $bindings = array_merge(
            $this->activeUsersUnionBindings($from, $to),
            ['photo', $from, $to, $from, $to, 'AKTIF', 'VISITOR']
        );

        $queryRows = $db->select($sql, $bindings);
        $rows = [];

        foreach ($queryRows as $row) {
            $active = (int) ($row->active_users ?? 0);
            $food = (int) ($row->food_evals ?? 0);
            $workout = (int) ($row->workout_evals ?? 0);

            $rows[] = [
                'name' => (string) ($row->dim_name ?? 'Tidak diketahui'),
                'active_users' => $active,
                'food_evals' => $food,
                'workout_evals' => $workout,
                'total_evals' => $food + $workout,
                'pct' => 0.0,
                'bar_class' => self::BAR_CLASSES[0],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{
     *     rank: int,
     *     user_id: int,
     *     nama: string,
     *     site: string,
     *     perusahaan: string,
     *     jabatan: string,
     *     food_evals: int,
     *     workout_evals: int,
     *     total_evals: int,
     *     is_active: bool
     * }>
     */
    private function queryLeaderboard(string $from, string $to): array
    {
        $db = DB::connection(BewellConnectionService::CONNECTION);
        $limit = self::LEADERBOARD_LIMIT;

        $sql = '
            SELECT
                e.id AS user_id,
                COALESCE(NULLIF(TRIM(e.nama), \'\'), \'-\') AS nama,
                COALESCE(NULLIF(TRIM(e.site), \'\'), \'-\') AS site,
                COALESCE(NULLIF(TRIM(e.nama_perusahaan), \'\'), \'-\') AS perusahaan,
                COALESCE(NULLIF(TRIM(e.jabatan_fungsional), \'\'), \'-\') AS jabatan,
                COALESCE(f.food_cnt, 0) AS food_evals,
                COALESCE(w.workout_cnt, 0) AS workout_evals,
                (COALESCE(f.food_cnt, 0) + COALESCE(w.workout_cnt, 0)) AS total_evals,
                CASE WHEN a.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_active
            FROM employee_profiles e
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS food_cnt
                FROM food_analyses
                WHERE source_type = ?
                  AND user_id IS NOT NULL
                  AND created_at BETWEEN ? AND ?
                GROUP BY user_id
            ) AS f ON f.user_id = e.id
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS workout_cnt
                FROM workout_analyses
                WHERE user_id IS NOT NULL
                  AND created_at BETWEEN ? AND ?
                GROUP BY user_id
            ) AS w ON w.user_id = e.id
            LEFT JOIN ('.$this->activeUsersUnionSql().') AS a ON a.user_id = e.id
            WHERE e.status_karyawan = ?
              AND UPPER(TRIM(COALESCE(e.jabatan_fungsional, \'\'))) <> ?
              AND (
                    COALESCE(f.food_cnt, 0) > 0
                 OR COALESCE(w.workout_cnt, 0) > 0
                 OR a.user_id IS NOT NULL
              )
            ORDER BY total_evals DESC, is_active DESC, nama ASC
            LIMIT '.$limit.'
        ';

        $bindings = array_merge(
            ['photo', $from, $to, $from, $to],
            $this->activeUsersUnionBindings($from, $to),
            ['AKTIF', 'VISITOR']
        );

        $queryRows = $db->select($sql, $bindings);
        $leaderboard = [];

        foreach ($queryRows as $i => $row) {
            $food = (int) ($row->food_evals ?? 0);
            $workout = (int) ($row->workout_evals ?? 0);

            $leaderboard[] = [
                'rank' => $i + 1,
                'user_id' => (int) ($row->user_id ?? 0),
                'nama' => (string) ($row->nama ?? '-'),
                'site' => (string) ($row->site ?? '-'),
                'perusahaan' => (string) ($row->perusahaan ?? '-'),
                'jabatan' => (string) ($row->jabatan ?? '-'),
                'food_evals' => $food,
                'workout_evals' => $workout,
                'total_evals' => $food + $workout,
                'is_active' => (int) ($row->is_active ?? 0) === 1,
            ];
        }

        return $leaderboard;
    }

    private function activeUsersUnionSql(): string
    {
        return '
            SELECT user_id FROM food_analyses
                WHERE source_type = ? AND user_id IS NOT NULL
                  AND created_at BETWEEN ? AND ?
            UNION
            SELECT user_id FROM workout_analyses
                WHERE user_id IS NOT NULL
                  AND created_at BETWEEN ? AND ?
            UNION
            SELECT author_user_id AS user_id FROM community_posts
                WHERE author_user_id IS NOT NULL
                  AND created_at BETWEEN ? AND ?
            UNION
            SELECT user_id FROM community_members
                WHERE user_id IS NOT NULL
                  AND joined_at BETWEEN ? AND ?
            UNION
            SELECT user_id FROM community_event_rsvps
                WHERE user_id IS NOT NULL
                  AND created_at BETWEEN ? AND ?
            UNION
            SELECT host_user_id AS user_id FROM open_play_events
                WHERE host_user_id IS NOT NULL
                  AND starts_at BETWEEN ? AND ?
            UNION
            SELECT p.user_id
                FROM open_play_participants p
                INNER JOIN open_play_events e ON e.id = p.event_id
                WHERE p.user_id IS NOT NULL
                  AND e.starts_at BETWEEN ? AND ?
        ';
    }

    /**
     * @return list<string>
     */
    private function activeUsersUnionBindings(string $from, string $to): array
    {
        return [
            'photo', $from, $to,
            $from, $to,
            $from, $to,
            $from, $to,
            $from, $to,
            $from, $to,
            $from, $to,
        ];
    }

    /**
     * @param  list<array{
     *     name: string,
     *     active_users: int,
     *     food_evals: int,
     *     workout_evals: int,
     *     total_evals: int,
     *     pct: float,
     *     bar_class: string
     * }>  $rows
     * @return array{
     *     categories: list<string>,
     *     active_users: list<int>,
     *     food_evals: list<int>,
     *     workout_evals: list<int>
     * }
     */
    private function buildChartPayload(array $rows): array
    {
        $categories = [];
        $activeUsers = [];
        $foodEvals = [];
        $workoutEvals = [];

        $top = array_slice($rows, 0, self::CHART_TOP_N);
        $rest = array_slice($rows, self::CHART_TOP_N);

        foreach ($top as $row) {
            $categories[] = $row['name'];
            $activeUsers[] = $row['active_users'];
            $foodEvals[] = $row['food_evals'];
            $workoutEvals[] = $row['workout_evals'];
        }

        if ($rest !== []) {
            $categories[] = 'Lainnya';
            $activeUsers[] = (int) array_sum(array_column($rest, 'active_users'));
            $foodEvals[] = (int) array_sum(array_column($rest, 'food_evals'));
            $workoutEvals[] = (int) array_sum(array_column($rest, 'workout_evals'));
        }

        return [
            'categories' => $categories,
            'active_users' => $activeUsers,
            'food_evals' => $foodEvals,
            'workout_evals' => $workoutEvals,
        ];
    }

    /**
     * @return array{start: string, end: string, label: string, prev_start: string}
     */
    private function resolveWeek(?string $weekStart): array
    {
        try {
            $start = $weekStart !== null && $weekStart !== ''
                ? Carbon::parse($weekStart)->startOfWeek()
                : Carbon::now()->startOfWeek();
        } catch (Throwable) {
            $start = Carbon::now()->startOfWeek();
        }

        $end = $start->copy()->endOfWeek();
        $prevStart = $start->copy()->subWeek()->startOfWeek();

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'label' => $start->format('d M').' – '.$end->format('d M Y'),
            'prev_start' => $prevStart->toDateString(),
        ];
    }

    /**
     * @param  array{labels: list<string>, active_users: list<int>, week_starts: list<string>}  $trend
     * @return list<array{start: string, label: string}>
     */
    private function buildWeekOptions(array $trend): array
    {
        $options = [];
        $starts = $trend['week_starts'] ?? [];

        foreach ($starts as $startDate) {
            try {
                $start = Carbon::parse($startDate)->startOfWeek();
                $end = $start->copy()->endOfWeek();
                $options[] = [
                    'start' => $start->toDateString(),
                    'label' => $start->format('d M').' – '.$end->format('d M Y'),
                ];
            } catch (Throwable) {
                continue;
            }
        }

        if ($options === []) {
            $week = $this->resolveWeek(null);
            $options[] = [
                'start' => $week['start'],
                'label' => $week['label'],
            ];
        }

        return array_reverse($options);
    }

    private function resolveDimension(string $dimension): string
    {
        $dimension = strtolower(trim($dimension));

        return array_key_exists($dimension, self::DIMENSION_COLUMNS) ? $dimension : 'site';
    }

    /**
     * @return list<array{
     *     dimension: string,
     *     label: string,
     *     icon: string,
     *     groups: int,
     *     active_users: int,
     *     food_evals: int,
     *     workout_evals: int,
     *     total_evals: int,
     *     top_name: string,
     *     top_active: int,
     *     top_evals: int
     * }>
     */
    private function emptyOverview(): array
    {
        $overview = [];

        foreach (self::DIMENSION_LABELS as $dimension => $label) {
            $overview[] = [
                'dimension' => $dimension,
                'label' => $label,
                'icon' => self::DIMENSION_ICONS[$dimension],
                'groups' => 0,
                'active_users' => 0,
                'food_evals' => 0,
                'workout_evals' => 0,
                'total_evals' => 0,
                'top_name' => '-',
                'top_active' => 0,
                'top_evals' => 0,
            ];
        }

        return $overview;
    }

    /**
     * @param  array{start: string, end: string, label: string, prev_start: string}  $week
     * @return array{
     *     available: bool,
     *     dimension: string,
     *     dimension_label: string,
     *     footnote: string,
     *     message: string|null,
     *     week: array{start: string, end: string, label: string, prev_start: string},
     *     week_options: list<array{start: string, label: string}>,
     *     weekly_trend: array{labels: list<string>, active_users: list<int>, week_starts: list<string>},
     *     summary: array{
     *         active_users: int,
     *         food_evals: int,
     *         workout_evals: int,
     *         total_evals: int,
     *         week_increase: int,
     *         kpi_card_total: int,
     *         groups: int
     *     },
     *     overview: list<array{
     *         dimension: string,
     *         label: string,
     *         icon: string,
     *         groups: int,
     *         active_users: int,
     *         food_evals: int,
     *         workout_evals: int,
     *         total_evals: int,
     *         top_name: string,
     *         top_active: int,
     *         top_evals: int
     *     }>,
     *     rows: list<array{
     *         name: string,
     *         active_users: int,
     *         food_evals: int,
     *         workout_evals: int,
     *         total_evals: int,
     *         pct: float,
     *         bar_class: string
     *     }>,
     *     chart: array{
     *         categories: list<string>,
     *         active_users: list<int>,
     *         food_evals: list<int>,
     *         workout_evals: list<int>
     *     },
     *     leaderboard: list<array{
     *         rank: int,
     *         user_id: int,
     *         nama: string,
     *         site: string,
     *         perusahaan: string,
     *         jabatan: string,
     *         food_evals: int,
     *         workout_evals: int,
     *         total_evals: int,
     *         is_active: bool
     *     }>
     * }
     */
    private function emptyPayload(string $dimension, array $week, string $message): array
    {
        return [
            'available' => false,
            'dimension' => $dimension,
            'dimension_label' => self::DIMENSION_LABELS[$dimension] ?? 'Site',
            'footnote' => self::FOOTNOTE,
            'message' => $message,
            'week' => $week,
            'week_options' => [[
                'start' => $week['start'],
                'label' => $week['label'],
            ]],
            'weekly_trend' => ['labels' => [], 'active_users' => [], 'week_starts' => []],
            'summary' => [
                'active_users' => 0,
                'food_evals' => 0,
                'workout_evals' => 0,
                'total_evals' => 0,
                'week_increase' => 0,
                'kpi_card_total' => 0,
                'groups' => 0,
            ],
            'overview' => $this->emptyOverview(),
            'rows' => [],
            'chart' => [
                'categories' => [],
                'active_users' => [],
                'food_evals' => [],
                'workout_evals' => [],
            ],
            'leaderboard' => [],
        ];
    }
}
