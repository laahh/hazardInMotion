<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use App\Services\SportEvaluation\BewellConnectionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Dashboard EvaluasiWell — desain template index-2.html.
 */
class SportEvaluationDashboardController extends Controller
{
    public function __construct(
        private readonly BewellConnectionService $connection,
    ) {}

    public function index(): View
    {
        return view('evaluasi-well.dashboard', array_merge(
            $this->newUsersCardData(),
            $this->activeUsersCardData(),
            $this->stravaConnectCardData(),
            $this->engagementCardsData(),
            $this->topKomunitasData(),
            $this->activeUsersWeeklyTrendData(),
            $this->loginAdoptionData(),
            $this->activityCompositionData(),
            $this->topUsersData(),
            $this->siteDistributionData(),
            $this->weeklyActivityData(),
            $this->recentFeedData(),
        ));
    }

    public function summary(): View
    {
        return $this->index();
    }

    public function trend(): View
    {
        return $this->index();
    }

    public function distribution(): View
    {
        return $this->index();
    }

    public function leaderboard(): View
    {
        return $this->index();
    }

    /**
     * Total user install = distinct user_id di login_audit (event login_success).
     *
     * @return array{newUsersTotal:int, newUsersWeekIncrease:int}
     */
    private function newUsersCardData(): array
    {
        $newUsersTotal = 0;
        $newUsersWeekIncrease = 0;

        if (! $this->connection->isUp()) {
            return compact('newUsersTotal', 'newUsersWeekIncrease');
        }

        try {
            $db = DB::connection(BewellConnectionService::CONNECTION);

            $newUsersTotal = (int) $db->table('login_audit')
                ->where('event', 'login_success')
                ->whereNotNull('user_id')
                ->distinct()
                ->count('user_id');

            $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d H:i:s');

            $row = $db->selectOne(
                'SELECT COUNT(*) AS c FROM (
                    SELECT user_id
                    FROM login_audit
                    WHERE event = ?
                      AND user_id IS NOT NULL
                    GROUP BY user_id
                    HAVING MIN(created_at) >= ?
                ) AS first_login_week',
                ['login_success', $weekStart]
            );
            $newUsersWeekIncrease = (int) ($row->c ?? 0);
        } catch (Throwable $e) {
            report($e);
        }

        return compact('newUsersTotal', 'newUsersWeekIncrease');
    }

    /**
     * Active users minggu ini: minimal salah satu dari
     * - upload foto makan (food_analyses.source_type = photo)
     * - workout_analyses
     * - aktivitas komunitas (post / join / RSVP)
     * - Main Bareng (host / participant open_play)
     *
     * @return array{activeUsersTotal:int, activeUsersWeekIncrease:int}
     */
    private function activeUsersCardData(): array
    {
        $activeUsersTotal = 0;
        $activeUsersWeekIncrease = 0;

        if (! $this->connection->isUp()) {
            return compact('activeUsersTotal', 'activeUsersWeekIncrease');
        }

        try {
            $now = Carbon::now();
            $thisWeekStart = $now->copy()->startOfWeek();
            $thisWeekEnd = $now->copy()->endOfWeek();
            $lastWeekStart = $now->copy()->subWeek()->startOfWeek();
            $lastWeekEnd = $now->copy()->subWeek()->endOfWeek();

            $thisWeek = $this->countActiveUsersInRange(
                $thisWeekStart->format('Y-m-d H:i:s'),
                $thisWeekEnd->format('Y-m-d H:i:s'),
            );
            $lastWeek = $this->countActiveUsersInRange(
                $lastWeekStart->format('Y-m-d H:i:s'),
                $lastWeekEnd->format('Y-m-d H:i:s'),
            );

            $activeUsersTotal = $thisWeek;
            $activeUsersWeekIncrease = max(0, $thisWeek - $lastWeek);
        } catch (Throwable $e) {
            report($e);
        }

        return compact('activeUsersTotal', 'activeUsersWeekIncrease');
    }

    /**
     * Total Strava Connect = jumlah user di strava_connections.
     *
     * @return array{totalStravaConnect:int, totalStravaConnectWeekIncrease:int}
     */
    private function stravaConnectCardData(): array
    {
        $totalStravaConnect = 0;
        $totalStravaConnectWeekIncrease = 0;

        if (! $this->connection->isUp()) {
            return compact('totalStravaConnect', 'totalStravaConnectWeekIncrease');
        }

        try {
            $db = DB::connection(BewellConnectionService::CONNECTION);
            $now = Carbon::now();
            $weekStart = $now->copy()->startOfWeek()->format('Y-m-d H:i:s');
            $weekEnd = $now->copy()->endOfWeek()->format('Y-m-d H:i:s');
            $lastWeekStart = $now->copy()->subWeek()->startOfWeek()->format('Y-m-d H:i:s');
            $lastWeekEnd = $now->copy()->subWeek()->endOfWeek()->format('Y-m-d H:i:s');

            $totalStravaConnect = (int) $db->table('strava_connections')->count();

            $thisWeek = (int) $db->table('strava_connections')
                ->whereBetween('connected_at', [$weekStart, $weekEnd])
                ->count();
            $lastWeek = (int) $db->table('strava_connections')
                ->whereBetween('connected_at', [$lastWeekStart, $lastWeekEnd])
                ->count();

            $totalStravaConnectWeekIncrease = max(0, $thisWeek - $lastWeek);
        } catch (Throwable $e) {
            report($e);
        }

        return compact('totalStravaConnect', 'totalStravaConnectWeekIncrease');
    }

    private function countActiveUsersInRange(string $from, string $to): int
    {
        $db = DB::connection(BewellConnectionService::CONNECTION);

        $row = $db->selectOne(
            'SELECT COUNT(*) AS c FROM (
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
            ) AS active_users',
            [
                'photo', $from, $to,
                $from, $to,
                $from, $to,
                $from, $to,
                $from, $to,
                $from, $to,
                $from, $to,
            ]
        );

        return (int) ($row->c ?? 0);
    }

    /**
     * Kartu: Total Komunitas, Total Main Bareng, Total Goal Aktif.
     *
     * @return array{
     *     totalKomunitas:int,
     *     totalKomunitasWeekIncrease:int,
     *     totalMainBareng:int,
     *     totalMainBarengWeekIncrease:int,
     *     totalGoalAktif:int,
     *     totalGoalAktifWeekIncrease:int
     * }
     */
    private function engagementCardsData(): array
    {
        $totalKomunitas = 0;
        $totalKomunitasWeekIncrease = 0;
        $totalMainBareng = 0;
        $totalMainBarengWeekIncrease = 0;
        $totalGoalAktif = 0;
        $totalGoalAktifWeekIncrease = 0;

        if (! $this->connection->isUp()) {
            return compact(
                'totalKomunitas',
                'totalKomunitasWeekIncrease',
                'totalMainBareng',
                'totalMainBarengWeekIncrease',
                'totalGoalAktif',
                'totalGoalAktifWeekIncrease',
            );
        }

        try {
            $db = DB::connection(BewellConnectionService::CONNECTION);
            $now = Carbon::now();
            $weekStart = $now->copy()->startOfWeek()->format('Y-m-d H:i:s');
            $weekEnd = $now->copy()->endOfWeek()->format('Y-m-d H:i:s');
            $lastWeekStart = $now->copy()->subWeek()->startOfWeek()->format('Y-m-d H:i:s');
            $lastWeekEnd = $now->copy()->subWeek()->endOfWeek()->format('Y-m-d H:i:s');

            $totalKomunitas = (int) $db->table('communities')->count();
            $komunitasThisWeek = (int) $db->table('communities')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();
            $komunitasLastWeek = (int) $db->table('communities')
                ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
                ->count();
            $totalKomunitasWeekIncrease = max(0, $komunitasThisWeek - $komunitasLastWeek);

            $totalMainBareng = (int) $db->table('open_play_events')->count();
            $mainBarengThisWeek = (int) $db->table('open_play_events')
                ->whereBetween('starts_at', [$weekStart, $weekEnd])
                ->count();
            $mainBarengLastWeek = (int) $db->table('open_play_events')
                ->whereBetween('starts_at', [$lastWeekStart, $lastWeekEnd])
                ->count();
            $totalMainBarengWeekIncrease = max(0, $mainBarengThisWeek - $mainBarengLastWeek);

            $totalGoalAktif = (int) $db->table('user_goals')
                ->where('status', 'active')
                ->count();
            $goalThisWeek = (int) $db->table('user_goals')
                ->where('status', 'active')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();
            $goalLastWeek = (int) $db->table('user_goals')
                ->where('status', 'active')
                ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
                ->count();
            $totalGoalAktifWeekIncrease = max(0, $goalThisWeek - $goalLastWeek);
        } catch (Throwable $e) {
            report($e);
        }

        return compact(
            'totalKomunitas',
            'totalKomunitasWeekIncrease',
            'totalMainBareng',
            'totalMainBarengWeekIncrease',
            'totalGoalAktif',
            'totalGoalAktifWeekIncrease',
        );
    }

    /**
     * Top komunitas berdasarkan jumlah member terbanyak.
     *
     * @return array{topKomunitas:array<int,array{name:string,members:int,pct:int,icon:string,iconColor:string,barClass:string}>}
     */
    private function topKomunitasData(): array
    {
        $styles = [
            ['icon' => 'solar:users-group-rounded-bold', 'iconColor' => 'text-orange', 'barClass' => 'bg-orange'],
            ['icon' => 'solar:users-group-rounded-bold', 'iconColor' => 'text-success-main', 'barClass' => 'bg-success-main'],
            ['icon' => 'solar:users-group-rounded-bold', 'iconColor' => 'text-info-main', 'barClass' => 'bg-info-main'],
            ['icon' => 'solar:users-group-rounded-bold', 'iconColor' => 'text-indigo', 'barClass' => 'bg-indigo'],
        ];

        $topKomunitas = [];

        if (! $this->connection->isUp()) {
            return compact('topKomunitas');
        }

        try {
            $db = DB::connection(BewellConnectionService::CONNECTION);

            $rows = $db->table('communities as c')
                ->leftJoin('community_members as m', 'm.community_id', '=', 'c.id')
                ->selectRaw('c.id, c.name, COALESCE(COUNT(m.user_id), 0) as members')
                ->groupBy('c.id', 'c.name')
                ->orderByDesc('members')
                ->limit(4)
                ->get();

            $max = (int) ($rows->max('members') ?: 0);

            foreach ($rows->values() as $i => $row) {
                $members = (int) $row->members;
                $style = $styles[$i] ?? $styles[0];
                $topKomunitas[] = [
                    'name' => (string) $row->name,
                    'members' => $members,
                    'pct' => $max > 0 ? (int) round($members / $max * 100) : 0,
                    'icon' => $style['icon'],
                    'iconColor' => $style['iconColor'],
                    'barClass' => $style['barClass'],
                ];
            }
        } catch (Throwable $e) {
            report($e);
        }

        return compact('topKomunitas');
    }

    /**
     * Tren user aktif 12 minggu terakhir (sama definisi aktif dengan kartu Active Users).
     *
     * @return array{
     *     activeTrendLabels:array<int,string>,
     *     activeTrendSeries:array<int,int>,
     *     activeTrendThisWeek:int,
     *     activeTrendWeekIncrease:int
     * }
     */
    private function activeUsersWeeklyTrendData(): array
    {
        $activeTrendLabels = [];
        $activeTrendSeries = [];
        $activeTrendThisWeek = 0;
        $activeTrendWeekIncrease = 0;

        if (! $this->connection->isUp()) {
            return compact(
                'activeTrendLabels',
                'activeTrendSeries',
                'activeTrendThisWeek',
                'activeTrendWeekIncrease',
            );
        }

        try {
            $now = Carbon::now();
            $cached = \Illuminate\Support\Facades\Cache::remember(
                'evaluasi_well:active_users_weekly_trend_v1',
                300,
                function () use ($now): array {
                    $labels = [];
                    $series = [];

                    for ($i = 11; $i >= 0; $i--) {
                        $start = $now->copy()->subWeeks($i)->startOfWeek();
                        $end = $now->copy()->subWeeks($i)->endOfWeek();
                        $count = $this->countActiveUsersInRange(
                            $start->format('Y-m-d H:i:s'),
                            $end->format('Y-m-d H:i:s'),
                        );

                        $labels[] = $start->format('d M');
                        $series[] = $count;
                    }

                    return compact('labels', 'series');
                }
            );

            $activeTrendLabels = $cached['labels'];
            $activeTrendSeries = $cached['series'];
            $activeTrendThisWeek = (int) ($activeTrendSeries[11] ?? 0);
            $prevWeek = (int) ($activeTrendSeries[10] ?? 0);
            $activeTrendWeekIncrease = max(0, $activeTrendThisWeek - $prevWeek);
        } catch (Throwable $e) {
            report($e);
        }

        return compact(
            'activeTrendLabels',
            'activeTrendSeries',
            'activeTrendThisWeek',
            'activeTrendWeekIncrease',
        );
    }

    /**
     * Tren Login & Adopsi: Install / Login sukses / Aktif + chart login bulanan.
     *
     * @return array{
     *     adoptionInstall:int,
     *     adoptionLoginSuccess:int,
     *     adoptionAktif:int,
     *     adoptionChartLabels:array<int,string>,
     *     adoptionChartSeries:array<int,int>
     * }
     */
    private function loginAdoptionData(): array
    {
        $adoptionInstall = 0;
        $adoptionLoginSuccess = 0;
        $adoptionAktif = 0;
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $adoptionChartLabels = $months;
        $adoptionChartSeries = array_fill(0, 12, 0);

        if (! $this->connection->isUp()) {
            return compact(
                'adoptionInstall',
                'adoptionLoginSuccess',
                'adoptionAktif',
                'adoptionChartLabels',
                'adoptionChartSeries',
            );
        }

        try {
            $db = DB::connection(BewellConnectionService::CONNECTION);
            $yearStart = Carbon::now()->startOfYear()->format('Y-m-d H:i:s');
            $yearEnd = Carbon::now()->endOfYear()->format('Y-m-d H:i:s');
            $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d H:i:s');
            $weekEnd = Carbon::now()->endOfWeek()->format('Y-m-d H:i:s');

            $adoptionInstall = (int) $db->table('login_audit')
                ->where('event', 'login_success')
                ->whereNotNull('user_id')
                ->distinct()
                ->count('user_id');

            $adoptionLoginSuccess = (int) $db->table('login_audit')
                ->where('event', 'login_success')
                ->whereBetween('created_at', [$yearStart, $yearEnd])
                ->count();

            $adoptionAktif = $this->countActiveUsersInRange($weekStart, $weekEnd);

            $rows = $db->table('login_audit')
                ->selectRaw('MONTH(created_at) as m, COUNT(*) as total')
                ->where('event', 'login_success')
                ->whereBetween('created_at', [$yearStart, $yearEnd])
                ->groupByRaw('MONTH(created_at)')
                ->get();

            foreach ($rows as $row) {
                $idx = ((int) $row->m) - 1;
                if ($idx >= 0 && $idx < 12) {
                    $adoptionChartSeries[$idx] = (int) $row->total;
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return compact(
            'adoptionInstall',
            'adoptionLoginSuccess',
            'adoptionAktif',
            'adoptionChartLabels',
            'adoptionChartSeries',
        );
    }

    /**
     * Komposisi aktivitas: distinct user Olahraga / Nutrisi / Sosial (tahun berjalan).
     *
     * @return array{
     *     compositionOlahraga:int,
     *     compositionNutrisi:int,
     *     compositionSosial:int,
     *     compositionSeries:array<int,int>,
     *     compositionLabels:array<int,string>
     * }
     */
    private function activityCompositionData(): array
    {
        $compositionOlahraga = 0;
        $compositionNutrisi = 0;
        $compositionSosial = 0;
        $compositionLabels = ['Olahraga', 'Nutrisi', 'Sosial'];
        $compositionSeries = [0, 0, 0];

        if (! $this->connection->isUp()) {
            return compact(
                'compositionOlahraga',
                'compositionNutrisi',
                'compositionSosial',
                'compositionSeries',
                'compositionLabels',
            );
        }

        try {
            $db = DB::connection(BewellConnectionService::CONNECTION);
            $yearStart = Carbon::now()->startOfYear()->format('Y-m-d H:i:s');
            $yearEnd = Carbon::now()->endOfYear()->format('Y-m-d H:i:s');

            $compositionOlahraga = (int) $db->table('workout_analyses')
                ->whereNotNull('user_id')
                ->whereBetween('created_at', [$yearStart, $yearEnd])
                ->distinct()
                ->count('user_id');

            $compositionNutrisi = (int) $db->table('food_analyses')
                ->where('source_type', 'photo')
                ->whereNotNull('user_id')
                ->whereBetween('created_at', [$yearStart, $yearEnd])
                ->distinct()
                ->count('user_id');

            $sosialRow = $db->selectOne(
                'SELECT COUNT(*) AS c FROM (
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
                ) AS sosial_users',
                [
                    $yearStart, $yearEnd,
                    $yearStart, $yearEnd,
                    $yearStart, $yearEnd,
                    $yearStart, $yearEnd,
                    $yearStart, $yearEnd,
                ]
            );
            $compositionSosial = (int) ($sosialRow->c ?? 0);

            $compositionSeries = [
                $compositionOlahraga,
                $compositionNutrisi,
                $compositionSosial,
            ];
        } catch (Throwable $e) {
            report($e);
        }

        return compact(
            'compositionOlahraga',
            'compositionNutrisi',
            'compositionSosial',
            'compositionSeries',
            'compositionLabels',
        );
    }

    /**
     * Top User: ranking frekuensi makanan + olahraga + komunitas + main bareng (tahun berjalan).
     *
     * @return array{topUsers:array<int,array<string,mixed>>}
     */
    private function topUsersData(): array
    {
        $topUsers = [];

        if (! $this->connection->isUp()) {
            return compact('topUsers');
        }

        try {
            $cached = Cache::remember('evaluasi_well:top_users_year', 300, function (): array {
                $db = DB::connection(BewellConnectionService::CONNECTION);
                $yearStart = Carbon::now()->startOfYear()->format('Y-m-d H:i:s');
                $yearEnd = Carbon::now()->endOfYear()->format('Y-m-d H:i:s');

                $rows = $db->select(
                    'SELECT
                        e.id,
                        e.nama,
                        e.kode_sid,
                        e.divisi,
                        e.avatar_url,
                        e.foto,
                        s.food_cnt,
                        s.workout_cnt,
                        s.community_cnt,
                        s.open_play_cnt,
                        s.total_cnt
                    FROM (
                        SELECT
                            user_id,
                            SUM(food_cnt) AS food_cnt,
                            SUM(workout_cnt) AS workout_cnt,
                            SUM(community_cnt) AS community_cnt,
                            SUM(open_play_cnt) AS open_play_cnt,
                            SUM(food_cnt + workout_cnt + community_cnt + open_play_cnt) AS total_cnt
                        FROM (
                            SELECT user_id, COUNT(*) AS food_cnt, 0 AS workout_cnt, 0 AS community_cnt, 0 AS open_play_cnt
                            FROM food_analyses
                            WHERE source_type = ?
                              AND user_id IS NOT NULL
                              AND created_at BETWEEN ? AND ?
                            GROUP BY user_id

                            UNION ALL

                            SELECT user_id, 0 AS food_cnt, COUNT(*) AS workout_cnt, 0 AS community_cnt, 0 AS open_play_cnt
                            FROM workout_analyses
                            WHERE user_id IS NOT NULL
                              AND created_at BETWEEN ? AND ?
                            GROUP BY user_id

                            UNION ALL

                            SELECT user_id, 0 AS food_cnt, 0 AS workout_cnt, COUNT(*) AS community_cnt, 0 AS open_play_cnt
                            FROM (
                                SELECT author_user_id AS user_id FROM community_posts
                                    WHERE author_user_id IS NOT NULL
                                      AND created_at BETWEEN ? AND ?
                                UNION ALL
                                SELECT user_id FROM community_event_rsvps
                                    WHERE user_id IS NOT NULL
                                      AND created_at BETWEEN ? AND ?
                            ) community_acts
                            GROUP BY user_id

                            UNION ALL

                            SELECT user_id, 0 AS food_cnt, 0 AS workout_cnt, 0 AS community_cnt, COUNT(*) AS open_play_cnt
                            FROM (
                                SELECT host_user_id AS user_id FROM open_play_events
                                    WHERE host_user_id IS NOT NULL
                                      AND starts_at BETWEEN ? AND ?
                                UNION ALL
                                SELECT p.user_id
                                    FROM open_play_participants p
                                    INNER JOIN open_play_events e2 ON e2.id = p.event_id
                                    WHERE p.user_id IS NOT NULL
                                      AND e2.starts_at BETWEEN ? AND ?
                            ) open_play_acts
                            GROUP BY user_id
                        ) parts
                        GROUP BY user_id
                    ) s
                    INNER JOIN employee_profiles e ON e.id = s.user_id
                    ORDER BY s.total_cnt DESC, e.nama ASC
                    LIMIT 6',
                    [
                        'photo', $yearStart, $yearEnd,
                        $yearStart, $yearEnd,
                        $yearStart, $yearEnd,
                        $yearStart, $yearEnd,
                        $yearStart, $yearEnd,
                        $yearStart, $yearEnd,
                    ]
                );

                $placeholders = [
                    'evaluasi-well-assets/images/users/user1.png',
                    'evaluasi-well-assets/images/users/user2.png',
                    'evaluasi-well-assets/images/users/user3.png',
                    'evaluasi-well-assets/images/users/user4.png',
                    'evaluasi-well-assets/images/users/user5.png',
                ];

                $result = [];
                foreach ($rows as $i => $row) {
                    $avatar = trim((string) ($row->avatar_url ?: $row->foto ?: ''));
                    if ($avatar === '') {
                        $avatar = asset($placeholders[$i % count($placeholders)]);
                    }

                    $result[] = [
                        'id' => (int) $row->id,
                        'nama' => (string) ($row->nama ?: 'User #'.$row->id),
                        'kode_sid' => (string) ($row->kode_sid ?: '-'),
                        'divisi' => (string) ($row->divisi ?: '-'),
                        'avatar' => $avatar,
                        'food_cnt' => (int) $row->food_cnt,
                        'workout_cnt' => (int) $row->workout_cnt,
                        'community_cnt' => (int) $row->community_cnt,
                        'open_play_cnt' => (int) $row->open_play_cnt,
                        'total_cnt' => (int) $row->total_cnt,
                    ];
                }

                return $result;
            });

            $topUsers = $cached;
        } catch (Throwable $e) {
            report($e);
        }

        return compact('topUsers');
    }

    /**
     * Distribusi karyawan per site (jumlah + persen dari total).
     *
     * @return array{siteRows:array<int,array<string,mixed>>, siteTotalEmployees:int}
     */
    private function siteDistributionData(): array
    {
        $siteRows = [];
        $siteTotalEmployees = 0;

        if (! $this->connection->isUp()) {
            return compact('siteRows', 'siteTotalEmployees');
        }

        try {
            $db = DB::connection(BewellConnectionService::CONNECTION);

            $siteTotalEmployees = (int) $db->table('employee_profiles')->count();

            $rows = $db->table('employee_profiles')
                ->selectRaw("COALESCE(NULLIF(TRIM(site), ''), 'Tidak diketahui') AS site_name, COUNT(*) AS total")
                ->groupByRaw("COALESCE(NULLIF(TRIM(site), ''), 'Tidak diketahui')")
                ->orderByDesc('total')
                ->orderBy('site_name')
                ->get();

            $barClasses = ['bg-primary-600', 'bg-orange', 'bg-yellow', 'bg-success-main', 'bg-info-main', 'bg-indigo'];

            foreach ($rows as $i => $row) {
                $total = (int) $row->total;
                $pct = $siteTotalEmployees > 0
                    ? round($total / $siteTotalEmployees * 100, 1)
                    : 0.0;

                $siteRows[] = [
                    'name' => (string) $row->site_name,
                    'total' => $total,
                    'percent' => $pct,
                    'barClass' => $barClasses[$i % count($barClasses)],
                ];
            }
        } catch (Throwable $e) {
            report($e);
        }

        return compact('siteRows', 'siteTotalEmployees');
    }

    /**
     * Aktivitas harian minggu ini: Makanan / Olahraga / Sosial per hari (Sen–Min).
     *
     * @return array{
     *     weeklyMakananTotal:int,
     *     weeklyOlahragaTotal:int,
     *     weeklySosialTotal:int,
     *     weeklyActivityLabels:array<int,string>,
     *     weeklyMakananSeries:array<int,int>,
     *     weeklyOlahragaSeries:array<int,int>,
     *     weeklySosialSeries:array<int,int>
     * }
     */
    private function weeklyActivityData(): array
    {
        $weeklyActivityLabels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
        $weeklyMakananSeries = array_fill(0, 7, 0);
        $weeklyOlahragaSeries = array_fill(0, 7, 0);
        $weeklySosialSeries = array_fill(0, 7, 0);
        $weeklyMakananTotal = 0;
        $weeklyOlahragaTotal = 0;
        $weeklySosialTotal = 0;

        if (! $this->connection->isUp()) {
            return compact(
                'weeklyMakananTotal',
                'weeklyOlahragaTotal',
                'weeklySosialTotal',
                'weeklyActivityLabels',
                'weeklyMakananSeries',
                'weeklyOlahragaSeries',
                'weeklySosialSeries',
            );
        }

        try {
            $cached = Cache::remember('evaluasi_well:weekly_activity', 300, function (): array {
                $db = DB::connection(BewellConnectionService::CONNECTION);
                $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d H:i:s');
                $weekEnd = Carbon::now()->endOfWeek()->format('Y-m-d H:i:s');

                $fillByWeekday = static function (array $rows): array {
                    $series = array_fill(0, 7, 0);
                    foreach ($rows as $row) {
                        $idx = (int) $row->d;
                        if ($idx >= 0 && $idx < 7) {
                            $series[$idx] = (int) $row->total;
                        }
                    }

                    return $series;
                };

                $makananRows = $db->select(
                    'SELECT WEEKDAY(created_at) AS d, COUNT(*) AS total
                     FROM food_analyses
                     WHERE source_type = ?
                       AND created_at BETWEEN ? AND ?
                     GROUP BY WEEKDAY(created_at)',
                    ['photo', $weekStart, $weekEnd]
                );

                $olahragaRows = $db->select(
                    'SELECT WEEKDAY(created_at) AS d, COUNT(*) AS total
                     FROM workout_analyses
                     WHERE created_at BETWEEN ? AND ?
                     GROUP BY WEEKDAY(created_at)',
                    [$weekStart, $weekEnd]
                );

                $sosialRows = $db->select(
                    'SELECT WEEKDAY(act_at) AS d, COUNT(*) AS total
                     FROM (
                        SELECT created_at AS act_at FROM community_posts
                            WHERE created_at BETWEEN ? AND ?
                        UNION ALL
                        SELECT created_at AS act_at FROM community_event_rsvps
                            WHERE created_at BETWEEN ? AND ?
                        UNION ALL
                        SELECT starts_at AS act_at FROM open_play_events
                            WHERE starts_at BETWEEN ? AND ?
                        UNION ALL
                        SELECT e.starts_at AS act_at
                            FROM open_play_participants p
                            INNER JOIN open_play_events e ON e.id = p.event_id
                            WHERE e.starts_at BETWEEN ? AND ?
                     ) sosial_acts
                     GROUP BY WEEKDAY(act_at)',
                    [
                        $weekStart, $weekEnd,
                        $weekStart, $weekEnd,
                        $weekStart, $weekEnd,
                        $weekStart, $weekEnd,
                    ]
                );

                $makanan = $fillByWeekday($makananRows);
                $olahraga = $fillByWeekday($olahragaRows);
                $sosial = $fillByWeekday($sosialRows);

                return [
                    'makanan' => $makanan,
                    'olahraga' => $olahraga,
                    'sosial' => $sosial,
                    'makananTotal' => array_sum($makanan),
                    'olahragaTotal' => array_sum($olahraga),
                    'sosialTotal' => array_sum($sosial),
                ];
            });

            $weeklyMakananSeries = $cached['makanan'];
            $weeklyOlahragaSeries = $cached['olahraga'];
            $weeklySosialSeries = $cached['sosial'];
            $weeklyMakananTotal = (int) $cached['makananTotal'];
            $weeklyOlahragaTotal = (int) $cached['olahragaTotal'];
            $weeklySosialTotal = (int) $cached['sosialTotal'];
        } catch (Throwable $e) {
            report($e);
        }

        return compact(
            'weeklyMakananTotal',
            'weeklyOlahragaTotal',
            'weeklySosialTotal',
            'weeklyActivityLabels',
            'weeklyMakananSeries',
            'weeklyOlahragaSeries',
            'weeklySosialSeries',
        );
    }

    /**
     * Feed bawah dashboard: Aktivitas Terbaru (tab) + Login Terbaru.
     *
     * @return array{
     *     recentAllActivities:array<int,array<string,mixed>>,
     *     recentFoodActivities:array<int,array<string,mixed>>,
     *     recentWorkoutActivities:array<int,array<string,mixed>>,
     *     recentLogins:array<int,array<string,mixed>>
     * }
     */
    private function recentFeedData(): array
    {
        $recentAllActivities = [];
        $recentFoodActivities = [];
        $recentWorkoutActivities = [];
        $recentLogins = [];

        if (! $this->connection->isUp()) {
            return compact(
                'recentAllActivities',
                'recentFoodActivities',
                'recentWorkoutActivities',
                'recentLogins',
            );
        }

        try {
            $cached = Cache::remember('evaluasi_well:recent_feed', 120, function (): array {
                $db = DB::connection(BewellConnectionService::CONNECTION);

                $foodRows = $db->table('food_analyses as f')
                    ->join('employee_profiles as e', 'e.id', '=', 'f.user_id')
                    ->where('f.source_type', 'photo')
                    ->orderByDesc('f.created_at')
                    ->limit(8)
                    ->get([
                        'f.id',
                        'f.food_name',
                        'f.total_calories',
                        'f.created_at',
                        'e.id as user_id',
                        'e.nama',
                        'e.kode_sid',
                    ]);

                $workoutRows = $db->table('workout_analyses as w')
                    ->join('employee_profiles as e', 'e.id', '=', 'w.user_id')
                    ->orderByDesc('w.created_at')
                    ->limit(8)
                    ->get([
                        'w.id',
                        'w.activity_type',
                        'w.calories_kcal',
                        'w.created_at',
                        'e.id as user_id',
                        'e.nama',
                        'e.kode_sid',
                    ]);

                $mapFood = static function ($row): array {
                    $calories = $row->total_calories !== null
                        ? number_format((float) $row->total_calories, 0).' kkal'
                        : 'Foto makanan';

                    return [
                        'id' => (int) $row->id,
                        'title' => (string) ($row->food_name !== '' ? $row->food_name : 'Upload makanan'),
                        'subtitle' => $calories,
                        'user_id' => (int) $row->user_id,
                        'user_name' => (string) ($row->nama ?: 'User #'.$row->user_id),
                        'at' => Carbon::parse($row->created_at)->format('d M Y H:i'),
                        'at_raw' => (string) $row->created_at,
                        'type' => 'Makanan',
                        'badge_class' => 'bg-warning-focus text-warning-main',
                    ];
                };

                $mapWorkout = static function ($row): array {
                    $calories = $row->calories_kcal !== null
                        ? number_format((float) $row->calories_kcal, 0).' kkal'
                        : 'Workout';

                    return [
                        'id' => (int) $row->id,
                        'title' => (string) ($row->activity_type !== '' ? $row->activity_type : 'Olahraga'),
                        'subtitle' => $calories,
                        'user_id' => (int) $row->user_id,
                        'user_name' => (string) ($row->nama ?: 'User #'.$row->user_id),
                        'at' => Carbon::parse($row->created_at)->format('d M Y H:i'),
                        'at_raw' => (string) $row->created_at,
                        'type' => 'Olahraga',
                        'badge_class' => 'bg-success-focus text-success-main',
                    ];
                };

                $food = $foodRows->map($mapFood)->all();
                $workout = $workoutRows->map($mapWorkout)->all();

                $all = collect(array_merge($food, $workout))
                    ->sortByDesc('at_raw')
                    ->take(8)
                    ->values()
                    ->map(static function (array $item): array {
                        unset($item['at_raw']);

                        return $item;
                    })
                    ->all();

                $food = array_map(static function (array $item): array {
                    unset($item['at_raw']);

                    return $item;
                }, $food);

                $workout = array_map(static function (array $item): array {
                    unset($item['at_raw']);

                    return $item;
                }, $workout);

                $loginRows = $db->table('login_audit as a')
                    ->leftJoin('employee_profiles as e', 'e.id', '=', 'a.user_id')
                    ->orderByDesc('a.created_at')
                    ->limit(8)
                    ->get([
                        'a.id',
                        'a.user_id',
                        'a.kode_sid',
                        'a.event',
                        'a.platform',
                        'a.created_at',
                        'e.nama',
                    ]);

                $logins = [];
                foreach ($loginRows as $row) {
                    $isSuccess = $row->event === 'login_success';
                    $name = (string) ($row->nama ?: '');
                    if ($name === '') {
                        $name = (string) ($row->kode_sid ?: 'SID tidak dikenal');
                    }

                    $logins[] = [
                        'id' => (int) $row->id,
                        'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                        'user_name' => $name,
                        'kode_sid' => (string) ($row->kode_sid ?: '-'),
                        'at' => Carbon::parse($row->created_at)->format('d M Y H:i'),
                        'status' => $isSuccess ? 'Sukses' : 'Gagal',
                        'status_class' => $isSuccess
                            ? 'bg-success-focus text-success-main'
                            : 'bg-danger-focus text-danger-main',
                        'platform' => (string) ($row->platform ?: '-'),
                    ];
                }

                return [
                    'all' => $all,
                    'food' => $food,
                    'workout' => $workout,
                    'logins' => $logins,
                ];
            });

            $recentAllActivities = $cached['all'];
            $recentFoodActivities = $cached['food'];
            $recentWorkoutActivities = $cached['workout'];
            $recentLogins = $cached['logins'];
        } catch (Throwable $e) {
            report($e);
        }

        return compact(
            'recentAllActivities',
            'recentFoodActivities',
            'recentWorkoutActivities',
            'recentLogins',
        );
    }
}
