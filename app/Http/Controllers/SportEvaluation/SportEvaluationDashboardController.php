<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use App\Services\SportEvaluation\BewellConnectionService;
use App\Support\SpreadsheetExporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function index(Request $request): View
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
            $this->notInstalledFilterData(),
        ));
    }

    /**
     * DataTables server-side: karyawan AKTIF + status install & user aktif minggu ini.
     */
    public function notInstalledData(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 1);

        if (! $this->connection->isUp()) {
            return response()->json([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        try {
            $filters = $this->readNotInstalledFilters($request);
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
            $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'asc')) === 'desc'
                ? 'desc'
                : 'asc';

            $week = $this->currentWeekRange();
            $orderableColumns = [
                0 => 'e.nama',
                1 => 'e.nama_perusahaan',
                2 => 'e.divisi',
                3 => 'is_installed',
                4 => 'is_weekly_active',
            ];
            $orderColumn = $orderableColumns[$orderColumnIndex] ?? 'e.nama';

            $recordsTotal = (int) $this->activeEmployeesBaseQuery()->count('e.id');

            $filteredQuery = $this->applyNotInstalledFilters(
                $this->activeEmployeesBaseQuery(),
                $filters,
                $search,
                $week['start'],
                $week['end'],
            );
            $recordsFiltered = (int) (clone $filteredQuery)->count('e.id');

            $rows = $this->appendEmployeeStatusSelects(clone $filteredQuery, $week['start'], $week['end'])
                ->orderBy($orderColumn, $orderDir)
                ->orderBy('e.nama')
                ->offset($start)
                ->limit($length)
                ->get();

            $data = [];
            foreach ($rows as $row) {
                $isInstalled = (int) ($row->is_installed ?? 0) === 1;
                $isWeeklyActive = (int) ($row->is_weekly_active ?? 0) === 1;

                $data[] = [
                    'id' => (int) $row->id,
                    'nama' => (string) ($row->nama ?: 'User #'.$row->id),
                    'kode_sid' => (string) ($row->kode_sid ?: '-'),
                    'site' => (string) (trim((string) ($row->site ?? '')) !== '' ? $row->site : '-'),
                    'company' => (string) (trim((string) ($row->nama_perusahaan ?? '')) !== '' ? $row->nama_perusahaan : '-'),
                    'divisi' => (string) (trim((string) ($row->divisi ?? '')) !== '' ? $row->divisi : '-'),
                    'install' => $isInstalled ? 'Sudah' : 'Belum',
                    'install_class' => $isInstalled
                        ? 'bg-success-focus text-success-main'
                        : 'bg-warning-focus text-warning-main',
                    'user_aktif' => $isWeeklyActive ? 'Ya' : 'Tidak',
                    'user_aktif_class' => $isWeeklyActive
                        ? 'bg-success-focus text-success-main'
                        : 'bg-neutral-200 text-secondary-light',
                ];
            }

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal memuat data karyawan.',
            ], 500);
        }
    }

    /**
     * Export Excel karyawan AKTIF (mengikuti filter install / user aktif / site / dll).
     */
    public function notInstalledExport(Request $request): JsonResponse
    {
        if (! $this->connection->isUp()) {
            return response()->json(['message' => 'Koneksi BeWell tidak tersedia.'], 503);
        }

        try {
            $filters = $this->readNotInstalledFilters($request);
            $search = trim((string) $request->query('search', ''));
            $week = $this->currentWeekRange();

            $rows = $this->appendEmployeeStatusSelects(
                $this->applyNotInstalledFilters(
                    $this->activeEmployeesBaseQuery(),
                    $filters,
                    $search,
                    $week['start'],
                    $week['end'],
                ),
                $week['start'],
                $week['end'],
            )
                ->orderBy('e.nama')
                ->get();

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
                'No',
                'Nama',
                'Kode SID',
                'Site',
                'Perusahaan',
                'Divisi',
                'Install',
                'User Aktif',
            ]);
            $sheet = $spreadsheet->getActiveSheet();

            $rowNum = 2;
            foreach ($rows as $index => $row) {
                $sheet->fromArray([
                    $index + 1,
                    (string) ($row->nama ?: '-'),
                    (string) ($row->kode_sid ?: '-'),
                    (string) (trim((string) ($row->site ?? '')) !== '' ? $row->site : '-'),
                    (string) (trim((string) ($row->nama_perusahaan ?? '')) !== '' ? $row->nama_perusahaan : '-'),
                    (string) (trim((string) ($row->divisi ?? '')) !== '' ? $row->divisi : '-'),
                    (int) ($row->is_installed ?? 0) === 1 ? 'Sudah' : 'Belum',
                    (int) ($row->is_weekly_active ?? 0) === 1 ? 'Ya' : 'Tidak',
                ], null, 'A'.$rowNum);
                $rowNum++;
            }

            SpreadsheetExporter::download(
                $spreadsheet,
                'evaluasi_well_status_install_'.date('Y-m-d_His').'.xlsx'
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal mengekspor data.'], 500);
        }
    }

    public function summary(Request $request): View
    {
        return $this->index($request);
    }

    public function trend(Request $request): View
    {
        return $this->index($request);
    }

    public function distribution(Request $request): View
    {
        return $this->index($request);
    }

    public function leaderboard(Request $request): View
    {
        return $this->index($request);
    }

    /**
     * Total user install = distinct user yang pernah login_success
     * ATAU punya aktivitas (food/workout) di tanggal berapa pun.
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

            // Install = pernah login_success ATAU punya aktivitas apa pun
            // (upload makanan/olahraga tidak mungkin tanpa aplikasi terpasang).
            $installSignalsSql = '
                SELECT user_id, created_at FROM login_audit
                    WHERE event = ? AND user_id IS NOT NULL
                UNION ALL
                SELECT user_id, created_at FROM food_analyses
                    WHERE user_id IS NOT NULL
                UNION ALL
                SELECT user_id, created_at FROM workout_analyses
                    WHERE user_id IS NOT NULL
            ';

            $row = $db->selectOne(
                'SELECT COUNT(DISTINCT user_id) AS c FROM ('.$installSignalsSql.') AS install_signals',
                ['login_success']
            );
            $newUsersTotal = (int) ($row->c ?? 0);

            $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d H:i:s');

            $row = $db->selectOne(
                'SELECT COUNT(*) AS c FROM (
                    SELECT user_id
                    FROM ('.$installSignalsSql.') AS install_signals
                    GROUP BY user_id
                    HAVING MIN(created_at) >= ?
                ) AS first_install_week',
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
     * Distribusi karyawan AKTIF per site (jumlah + persen dari total).
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

            $siteTotalEmployees = (int) $db->table('employee_profiles')
                ->where('status_karyawan', 'AKTIF')
                ->count();

            $rows = $db->table('employee_profiles')
                ->where('status_karyawan', 'AKTIF')
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
     * Opsi filter + total untuk kartu Status Install di dashboard.
     *
     * @return array{
     *     notInstalledTotal:int,
     *     notInstalledSites:array<int,string>,
     *     notInstalledCompanies:array<int,string>,
     *     notInstalledDivisions:array<int,string>,
     *     notInstalledWeekLabel:string
     * }
     */
    private function notInstalledFilterData(): array
    {
        $notInstalledTotal = 0;
        $notInstalledSites = [];
        $notInstalledCompanies = [];
        $notInstalledDivisions = [];
        $week = $this->currentWeekRange();
        $notInstalledWeekLabel = $week['label'];

        if (! $this->connection->isUp()) {
            return compact(
                'notInstalledTotal',
                'notInstalledSites',
                'notInstalledCompanies',
                'notInstalledDivisions',
                'notInstalledWeekLabel',
            );
        }

        try {
            $cached = Cache::remember('evaluasi_well:active_employees_filters_v3', 120, function (): array {
                $base = DB::connection(BewellConnectionService::CONNECTION)
                    ->table('employee_profiles as e')
                    ->where('e.status_karyawan', 'AKTIF');

                $belumInstall = (clone $base)
                    ->whereNotExists(function ($query): void {
                        $query->selectRaw('1')
                            ->from('login_audit as a')
                            ->whereColumn('a.user_id', 'e.id')
                            ->where('a.event', 'login_success');
                    })
                    ->whereNotExists(function ($query): void {
                        $query->selectRaw('1')
                            ->from('food_analyses as f')
                            ->whereColumn('f.user_id', 'e.id');
                    })
                    ->whereNotExists(function ($query): void {
                        $query->selectRaw('1')
                            ->from('workout_analyses as w')
                            ->whereColumn('w.user_id', 'e.id');
                    });

                return [
                    'total' => (int) $belumInstall->count(),
                    'sites' => (clone $base)
                        ->whereNotNull('e.site')
                        ->where('e.site', '<>', '')
                        ->distinct()
                        ->orderBy('e.site')
                        ->pluck('e.site')
                        ->map(static fn (mixed $site): string => (string) $site)
                        ->all(),
                    'companies' => (clone $base)
                        ->whereNotNull('e.nama_perusahaan')
                        ->where('e.nama_perusahaan', '<>', '')
                        ->distinct()
                        ->orderBy('e.nama_perusahaan')
                        ->pluck('e.nama_perusahaan')
                        ->map(static fn (mixed $company): string => (string) $company)
                        ->all(),
                    'divisions' => (clone $base)
                        ->whereNotNull('e.divisi')
                        ->where('e.divisi', '<>', '')
                        ->distinct()
                        ->orderBy('e.divisi')
                        ->pluck('e.divisi')
                        ->map(static fn (mixed $division): string => (string) $division)
                        ->all(),
                ];
            });

            $notInstalledTotal = (int) $cached['total'];
            $notInstalledSites = $cached['sites'];
            $notInstalledCompanies = $cached['companies'];
            $notInstalledDivisions = $cached['divisions'];
        } catch (Throwable $e) {
            report($e);
        }

        return compact(
            'notInstalledTotal',
            'notInstalledSites',
            'notInstalledCompanies',
            'notInstalledDivisions',
            'notInstalledWeekLabel',
        );
    }

    /**
     * @return array{start:string,end:string,label:string}
     */
    private function currentWeekRange(): array
    {
        $start = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $end = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'label' => $start->translatedFormat('d M').' – '.$end->translatedFormat('d M Y'),
        ];
    }

    /**
     * Karyawan status AKTIF.
     */
    private function activeEmployeesBaseQuery(): Builder
    {
        return DB::connection(BewellConnectionService::CONNECTION)
            ->table('employee_profiles as e')
            ->where('e.status_karyawan', 'AKTIF');
    }

    /**
     * Tambah kolom kalkulasi install & user aktif minggu ini.
     */
    private function appendEmployeeStatusSelects(Builder $query, string $weekStart, string $weekEnd): Builder
    {
        return $query
            ->select([
                'e.id',
                'e.nama',
                'e.kode_sid',
                'e.site',
                'e.nama_perusahaan',
                'e.divisi',
            ])
            ->selectRaw(
                'CASE WHEN EXISTS (
                    SELECT 1 FROM login_audit a
                    WHERE a.user_id = e.id AND a.event = ?
                ) OR EXISTS (
                    SELECT 1 FROM food_analyses f2
                    WHERE f2.user_id = e.id
                ) OR EXISTS (
                    SELECT 1 FROM workout_analyses w2
                    WHERE w2.user_id = e.id
                ) THEN 1 ELSE 0 END AS is_installed',
                ['login_success']
            )
            ->selectRaw(
                'CASE WHEN EXISTS (
                    SELECT 1 FROM food_analyses f
                    WHERE f.user_id = e.id
                      AND f.source_type = ?
                      AND f.created_at BETWEEN ? AND ?
                ) OR EXISTS (
                    SELECT 1 FROM workout_analyses w
                    WHERE w.user_id = e.id
                      AND w.created_at BETWEEN ? AND ?
                ) THEN 1 ELSE 0 END AS is_weekly_active',
                ['photo', $weekStart, $weekEnd, $weekStart, $weekEnd]
            );
    }

    /**
     * @return array{site:string,company:string,division:string,install:string,user_aktif:string}
     */
    private function readNotInstalledFilters(Request $request): array
    {
        $readFilter = static fn (mixed $value): string => is_string($value)
            ? mb_substr(trim($value), 0, 150)
            : '';

        $install = strtolower($readFilter($request->input('install')));
        if (! in_array($install, ['sudah', 'belum'], true)) {
            $install = '';
        }

        $userAktif = strtolower($readFilter($request->input('user_aktif')));
        if (! in_array($userAktif, ['ya', 'tidak'], true)) {
            $userAktif = '';
        }

        return [
            'site' => $readFilter($request->input('site')),
            'company' => $readFilter($request->input('company')),
            'division' => $readFilter($request->input('division')),
            'install' => $install,
            'user_aktif' => $userAktif,
        ];
    }

    /**
     * @param  array{site:string,company:string,division:string,install:string,user_aktif:string}  $filters
     */
    private function applyNotInstalledFilters(
        Builder $query,
        array $filters,
        string $search = '',
        string $weekStart = '',
        string $weekEnd = '',
    ): Builder {
        if ($filters['site'] !== '') {
            $query->where('e.site', $filters['site']);
        }
        if ($filters['company'] !== '') {
            $query->where('e.nama_perusahaan', $filters['company']);
        }
        if ($filters['division'] !== '') {
            $query->where('e.divisi', 'like', '%'.$filters['division'].'%');
        }

        // Sudah install = pernah login_success ATAU punya aktivitas apa pun
        // (upload makanan/olahraga tidak mungkin tanpa aplikasi terpasang).
        if ($filters['install'] === 'sudah') {
            $query->where(function (Builder $outer): void {
                $outer->whereExists(function ($inner): void {
                    $inner->selectRaw('1')
                        ->from('login_audit as a')
                        ->whereColumn('a.user_id', 'e.id')
                        ->where('a.event', 'login_success');
                })->orWhereExists(function ($inner): void {
                    $inner->selectRaw('1')
                        ->from('food_analyses as f')
                        ->whereColumn('f.user_id', 'e.id');
                })->orWhereExists(function ($inner): void {
                    $inner->selectRaw('1')
                        ->from('workout_analyses as w')
                        ->whereColumn('w.user_id', 'e.id');
                });
            });
        } elseif ($filters['install'] === 'belum') {
            $query->whereNotExists(function ($inner): void {
                $inner->selectRaw('1')
                    ->from('login_audit as a')
                    ->whereColumn('a.user_id', 'e.id')
                    ->where('a.event', 'login_success');
            })->whereNotExists(function ($inner): void {
                $inner->selectRaw('1')
                    ->from('food_analyses as f')
                    ->whereColumn('f.user_id', 'e.id');
            })->whereNotExists(function ($inner): void {
                $inner->selectRaw('1')
                    ->from('workout_analyses as w')
                    ->whereColumn('w.user_id', 'e.id');
            });
        }

        if ($weekStart !== '' && $weekEnd !== '') {
            if ($filters['user_aktif'] === 'ya') {
                $query->where(function (Builder $outer) use ($weekStart, $weekEnd): void {
                    $outer->whereExists(function ($inner) use ($weekStart, $weekEnd): void {
                        $inner->selectRaw('1')
                            ->from('food_analyses as f')
                            ->whereColumn('f.user_id', 'e.id')
                            ->where('f.source_type', 'photo')
                            ->whereBetween('f.created_at', [$weekStart, $weekEnd]);
                    })->orWhereExists(function ($inner) use ($weekStart, $weekEnd): void {
                        $inner->selectRaw('1')
                            ->from('workout_analyses as w')
                            ->whereColumn('w.user_id', 'e.id')
                            ->whereBetween('w.created_at', [$weekStart, $weekEnd]);
                    });
                });
            } elseif ($filters['user_aktif'] === 'tidak') {
                $query->whereNotExists(function ($inner) use ($weekStart, $weekEnd): void {
                    $inner->selectRaw('1')
                        ->from('food_analyses as f')
                        ->whereColumn('f.user_id', 'e.id')
                        ->where('f.source_type', 'photo')
                        ->whereBetween('f.created_at', [$weekStart, $weekEnd]);
                })->whereNotExists(function ($inner) use ($weekStart, $weekEnd): void {
                    $inner->selectRaw('1')
                        ->from('workout_analyses as w')
                        ->whereColumn('w.user_id', 'e.id')
                        ->whereBetween('w.created_at', [$weekStart, $weekEnd]);
                });
            }
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('e.nama', 'like', $like)
                    ->orWhere('e.kode_sid', 'like', $like)
                    ->orWhere('e.site', 'like', $like)
                    ->orWhere('e.nama_perusahaan', 'like', $like)
                    ->orWhere('e.divisi', 'like', $like);
            });
        }

        return $query;
    }
}
