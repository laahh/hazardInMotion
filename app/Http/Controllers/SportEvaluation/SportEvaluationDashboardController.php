<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use App\Services\SportEvaluation\BewellConnectionService;
use App\Services\SportEvaluation\SportEvaluationAccessService;
use App\Services\SportEvaluation\SportEvaluationActiveStatsService;
use App\Services\SportEvaluation\SportEvaluationCompanyAliasResolver;
use App\Services\SportEvaluation\SportEvaluationDivisiGroupResolver;
use App\Services\SportEvaluation\SportEvaluationEmployeeExclusionRules;
use App\Services\SportEvaluation\SportEvaluationInstallStatsService;
use App\Services\SportEvaluation\SportEvaluationKaryawanWellSiteResolver;
use App\Services\SportEvaluation\SportEvaluationMitraAssignmentService;
use App\Services\SportEvaluation\SportEvaluationWellnessMetricsService;
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
    /**
     * Filter index opsional (dipakai dashboard Mitra Kerja).
     *
     * @var array{
     *     site: string,
     *     perusahaan: string,
     *     pairs: list<array{site: string, perusahaan: string}>,
     *     companies: list<array{perusahaan: string, sites: list<string>}>
     * }
     */
    protected array $indexFilters = [
        'site' => '',
        'perusahaan' => '',
        'pairs' => [],
        'companies' => [],
    ];

    public function __construct(
        private readonly BewellConnectionService $connection,
        private readonly SportEvaluationInstallStatsService $installStatsService,
        private readonly SportEvaluationActiveStatsService $activeStatsService,
        private readonly SportEvaluationWellnessMetricsService $wellnessMetricsService,
        private readonly SportEvaluationKaryawanWellSiteResolver $siteResolver,
        private readonly SportEvaluationDivisiGroupResolver $divisiGroupResolver,
        private readonly SportEvaluationMitraAssignmentService $mitraAssignmentService,
        private readonly SportEvaluationCompanyAliasResolver $companyAliasResolver,
        private readonly SportEvaluationEmployeeExclusionRules $exclusionRules,
        protected readonly SportEvaluationAccessService $accessService,
    ) {}

    public function index(Request $request): View
    {
        return view('evaluasi-well.dashboard', $this->buildIndexData(
            $this->dashboardFiltersFromRequest($request)
        ));
    }

    /**
     * Data SSR dashboard utama (global atau scoped mitra).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildIndexData(array $filters = []): array
    {
        $divisionGroup = trim((string) ($filters['division_group'] ?? $filters['division'] ?? $filters['divisi'] ?? ''));
        $this->indexFilters = array_merge(
            $this->mitraAssignmentService->normalizeScope($filters),
            [
                'division_group' => $divisionGroup,
                'division' => $divisionGroup,
                'divisi' => $divisionGroup,
            ]
        );

        $filterOptions = $this->dashboardFilterOptions();

        // Fail-fast: satu cek koneksi. Saat tunnel down, jangan N× SELECT 1 / query berat.
        if (! $this->connection->isUp()) {
            return array_merge(
                $this->emptyDashboardPayload(),
                $this->wellnessMetricsService->getDashboardPayload($this->indexFilters),
                [
                    'mitraMode' => false,
                    'mitraScope' => $this->indexFilters,
                    'bewellConnectionUp' => false,
                    'dashboardFilters' => $this->currentDashboardFilters(),
                    'dashboardFilterOptions' => $filterOptions,
                ]
            );
        }

        $data = array_merge(
            $this->newUsersCardData(),
            $this->activeUsersCardData(),
            $this->totalKaryawanCardData(),
            $this->engagementCardsData(),
            $this->topKomunitasData(),
            $this->activeUsersWeeklyTrendData(),
            $this->loginAdoptionData(),
            $this->activityCompositionData(),
            $this->topUsersData(),
            $this->siteDistributionData(),
            $this->weeklyActivityData(),
            $this->notInstalledFilterData(),
            $this->wellnessMetricsService->getDashboardPayload($this->indexFilters),
            [
                'mitraMode' => false,
                'mitraScope' => $this->indexFilters,
                'bewellConnectionUp' => true,
                'dashboardFilters' => $this->currentDashboardFilters(),
                'dashboardFilterOptions' => $filterOptions,
            ],
        );

        $totalKaryawan = (int) ($data['totalKaryawan'] ?? 0);
        $newUsersTotal = (int) ($data['newUsersTotal'] ?? 0);
        $data['newUsersInstallPercent'] = $totalKaryawan > 0
            ? round(($newUsersTotal / $totalKaryawan) * 100, 1)
            : 0.0;

        return $data;
    }

    /**
     * @return array{site: string, perusahaan: string, division_group: string}
     */
    private function currentDashboardFilters(): array
    {
        return [
            'site' => (string) ($this->indexFilters['site'] ?? ''),
            'perusahaan' => (string) ($this->indexFilters['perusahaan'] ?? ''),
            'division_group' => (string) ($this->indexFilters['division_group'] ?? ''),
        ];
    }

    /**
     * @return array{sites: list<string>, companies: list<string>, division_groups: list<string>}
     */
    private function dashboardFilterOptions(): array
    {
        $options = $this->installStatsService->filterOptions();

        return [
            'sites' => $options['sites'] ?? [],
            'companies' => $options['companies'] ?? [],
            'division_groups' => $options['division_groups'] ?? $this->divisiGroupResolver->groupLabels(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardFiltersFromRequest(Request $request): array
    {
        $site = trim((string) $request->input('site', ''));
        $perusahaan = trim((string) $request->input('perusahaan', $request->input('company', '')));
        $division = trim((string) $request->input('division_group', $request->input('division', $request->input('divisi', ''))));

        return [
            'site' => mb_substr($site, 0, 180),
            'perusahaan' => mb_substr($perusahaan, 0, 180),
            'company' => mb_substr($perusahaan, 0, 180),
            'division_group' => mb_substr($division, 0, 180),
            'division' => mb_substr($division, 0, 180),
            'divisi' => mb_substr($division, 0, 180),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboardPayload(): array
    {
        return [
            'newUsersTotal' => 0,
            'newUsersWeekIncrease' => 0,
            'newUsersWeekIncreasePercent' => 0.0,
            'newUsersInstallPercent' => 0.0,
            'activeUsersTotal' => 0,
            'activeUsersWeekIncrease' => 0,
            'activeUsersWeekIncreasePercent' => 0.0,
            'totalKaryawan' => 0,
            'totalKaryawanWeekIncrease' => 0,
            'totalKaryawanWeekIncreasePercent' => 0.0,
            'totalKomunitas' => 0,
            'totalKomunitasWeekIncrease' => 0,
            'totalKomunitasWeekIncreasePercent' => 0.0,
            'totalMainBareng' => 0,
            'totalMainBarengWeekIncrease' => 0,
            'totalMainBarengWeekIncreasePercent' => 0.0,
            'totalGoalAktif' => 0,
            'totalGoalAktifWeekIncrease' => 0,
            'totalGoalAktifWeekIncreasePercent' => 0.0,
            'topKomunitas' => [],
            'activeTrendLabels' => [],
            'activeTrendSeries' => [],
            'activeTrendUserCounts' => [],
            'activeTrendThisWeek' => 0,
            'activeTrendThisWeekPercent' => 0.0,
            'activeTrendWeekIncrease' => 0,
            'adoptionInstall' => 0,
            'adoptionLoginSuccess' => 0,
            'adoptionAktif' => 0,
            'adoptionNewInstallsPeriod' => 0,
            'adoptionAvgDailyUsage' => 0,
            'adoptionChartLabels' => [],
            'adoptionChartSeries' => [],
            'adoptionTrendLabels' => [],
            'adoptionTrendNewInstalls' => [],
            'adoptionTrendActiveUsers' => [],
            'adoptionTrendDates' => [],
            'adoptionTrendRangeLabel' => '',
            'activityPatternSeries' => [],
            'activityPatternCategories' => [],
            'activityPatternPeakDayLabel' => '–',
            'activityPatternPeakDayCount' => 0,
            'activityPatternAvgDaily' => 0,
            'activityPatternWeekdayRatio' => 0.0,
            'activityPatternPeakHourLabel' => '–',
            'activityPatternInsight' => 'Belum ada cukup data untuk insight pola aktivitas.',
            'compositionOlahraga' => 0,
            'compositionNutrisi' => 0,
            'compositionSosial' => 0,
            'compositionSeries' => [0, 0, 0],
            'compositionLabels' => ['Olahraga', 'Nutrisi', 'Sosial'],
            'topUsers' => [],
            'siteRows' => [],
            'siteTotalEmployees' => 0,
            'weeklyMakananTotal' => 0,
            'weeklyOlahragaTotal' => 0,
            'weeklySosialTotal' => 0,
            'weeklyActivityLabels' => ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
            'weeklyMakananSeries' => array_fill(0, 7, 0),
            'weeklyOlahragaSeries' => array_fill(0, 7, 0),
            'weeklySosialSeries' => array_fill(0, 7, 0),
            'notInstalledTotal' => 0,
            'notInstalledSites' => [],
            'notInstalledCompanies' => [],
            'notInstalledDivisions' => [],
            'notInstalledDepartements' => [],
            'notInstalledJabatanFungsionals' => [],
            'notInstalledWeekLabel' => '',
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
            'wellnessWeek' => ['start' => '', 'end' => '', 'label' => '', 'prev_start' => ''],
            'wellnessWeekOptions' => [],
            'wellnessSites' => [],
            'wellnessCompanies' => [],
        ];
    }

    /**
     * Detail statistik install per dimensi (site / divisi / perusahaan / departemen / jabatan).
     */
    public function installStats(Request $request): JsonResponse
    {
        $this->ensureScopedIndexFilters($request);

        $dimension = is_string($request->input('dimension'))
            ? $request->input('dimension')
            : 'site';

        $filters = $this->installStatsFiltersFromRequest($request);

        try {
            return response()->json($this->installStatsService->getStats($dimension, $filters));
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'available' => false,
                'dimension' => 'site',
                'dimension_label' => 'Site',
                'footnote' => 'Filter global mempengaruhi seluruh ringkasan. Divisi digabung per grup sejenis.',
                'message' => 'Gagal memuat statistik install.',
                'summary' => [
                    'total' => 0,
                    'installed' => 0,
                    'not_installed' => 0,
                    'adoption_pct' => 0,
                    'kpi_card_total' => 0,
                    'groups' => 0,
                ],
                'overview' => [],
                'rows' => [],
                'chart' => [
                    'categories' => [],
                    'installed' => [],
                    'not_installed' => [],
                ],
                'filters' => $filters,
                'filter_options' => [
                    'sites' => [],
                    'division_groups' => [],
                    'companies' => [],
                    'departements' => [],
                    'jabatans' => [],
                ],
            ]);
        }
    }

    /**
     * Export Excel modal Detail Total User Install: ringkasan dimensi + daftar karyawan.
     */
    public function installStatsExport(Request $request): JsonResponse
    {
        $this->ensureScopedIndexFilters($request);

        if (! $this->connection->isUp()) {
            return response()->json(['message' => 'Koneksi BeWell tidak tersedia.'], 503);
        }

        try {
            $dimension = is_string($request->input('dimension'))
                ? $request->input('dimension')
                : 'site';

            $filters = $this->installStatsFiltersFromRequest($request);

            $stats = $this->installStatsService->getStats($dimension, $filters);
            $dimensionLabel = (string) ($stats['dimension_label'] ?? 'Site');
            $summary = is_array($stats['summary'] ?? null) ? $stats['summary'] : [];
            $rows = is_array($stats['rows'] ?? null) ? $stats['rows'] : [];

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
                $dimensionLabel,
                'Total',
                'Sudah Install',
                'Belum Install',
                'Adoption (%)',
            ]);
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Ringkasan');

            $rowNum = 2;
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $sheet->fromArray([
                    (string) ($row['name'] ?? '-'),
                    (int) ($row['total'] ?? 0),
                    (int) ($row['installed'] ?? 0),
                    (int) ($row['not_installed'] ?? 0),
                    (float) ($row['pct'] ?? 0),
                ], null, 'A'.$rowNum);
                $rowNum++;
            }

            $sheet->fromArray([
                'Total',
                (int) ($summary['total'] ?? 0),
                (int) ($summary['installed'] ?? 0),
                (int) ($summary['not_installed'] ?? 0),
                (float) ($summary['adoption_pct'] ?? 0),
            ], null, 'A'.$rowNum);

            $employeeFilters = $this->readNotInstalledFilters($request);
            if ($employeeFilters['jabatan_fungsional'] === '' && $filters['jabatan'] !== '') {
                $employeeFilters['jabatan_fungsional'] = $filters['jabatan'];
            }
            if ($employeeFilters['division_group'] === '' && $filters['division_group'] !== '') {
                $employeeFilters['division_group'] = $filters['division_group'];
            }
            if ($employeeFilters['company'] === '' && $filters['company'] !== '') {
                $employeeFilters['company'] = $filters['company'];
            }
            if ($employeeFilters['site'] === '' && $filters['site'] !== '') {
                $employeeFilters['site'] = $filters['site'];
            }
            if ($employeeFilters['departement'] === '' && $filters['departement'] !== '') {
                $employeeFilters['departement'] = $filters['departement'];
            }
            if ($employeeFilters['install'] === '' && $filters['install'] !== '') {
                $employeeFilters['install'] = $filters['install'];
            }

            $search = trim((string) $request->query('search', ''));
            $week = $this->currentWeekRange();
            $employees = $this->appendEmployeeStatusSelects(
                $this->applyNotInstalledFilters(
                    $this->activeEmployeesBaseQuery(),
                    $employeeFilters,
                    $search,
                    $week['start'],
                    $week['end'],
                ),
                $week['start'],
                $week['end'],
            )
                ->orderBy('e.nama')
                ->get();

            $employeeSheet = SpreadsheetExporter::addSheetWithHeaders($spreadsheet, 'Karyawan', [
                'No',
                'Nama',
                'Kode SID',
                'Site',
                'Perusahaan',
                'Departemen',
                'Divisi',
                'Jabatan Fungsional',
                'Install',
            ]);

            $empRow = 2;
            foreach ($employees as $index => $employee) {
                $employeeSheet->fromArray([
                    $index + 1,
                    (string) ($employee->nama ?: '-'),
                    (string) ($employee->kode_sid ?: '-'),
                    $this->siteResolver->resolveOrDash(
                        isset($employee->kode_sid) ? (string) $employee->kode_sid : null,
                        isset($employee->site) ? (string) $employee->site : null,
                    ),
                    (string) (trim((string) ($employee->nama_perusahaan ?? '')) !== '' ? $employee->nama_perusahaan : '-'),
                    (string) (trim((string) ($employee->departement ?? '')) !== '' ? $employee->departement : '-'),
                    (string) (trim((string) ($employee->divisi ?? '')) !== '' ? $employee->divisi : '-'),
                    (string) (trim((string) ($employee->jabatan_fungsional ?? '')) !== '' ? $employee->jabatan_fungsional : '-'),
                    (int) ($employee->is_installed ?? 0) === 1 ? 'Sudah' : 'Belum',
                ], null, 'A'.$empRow);
                $empRow++;
            }

            $spreadsheet->setActiveSheetIndex(0);

            SpreadsheetExporter::download(
                $spreadsheet,
                'evaluasi_well_detail_install_'.date('Y-m-d_His').'.xlsx'
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal mengekspor data install.'], 500);
        }
    }

    /**
     * Detail statistik user aktif per dimensi (site / perusahaan / jabatan) + leaderboard.
     */
    public function activeStats(Request $request): JsonResponse
    {
        $this->ensureScopedIndexFilters($request);

        $dimension = is_string($request->input('dimension'))
            ? $request->input('dimension')
            : 'site';

        $weekStart = is_string($request->input('week_start'))
            ? $request->input('week_start')
            : null;

        try {
            return response()->json($this->activeStatsService->getStats($dimension, $weekStart, $this->indexFilters));
        } catch (Throwable $e) {
            report($e);

            $fallbackWeek = $this->activeStatsService->resolveWeekRange(null);

            return response()->json([
                'available' => false,
                'dimension' => 'site',
                'dimension_label' => 'Site',
                'footnote' => 'User aktif (luas) = food photo / workout / komunitas / Main Bareng minggu terpilih (Minggu–Sabtu). Evaluasi = food + workout.',
                'message' => 'Gagal memuat statistik user aktif.',
                'week' => $fallbackWeek,
                'week_options' => [],
                'weekly_trend' => [
                    'labels' => [],
                    'active_users' => [],
                    'week_starts' => [],
                ],
                'summary' => [
                    'active_users' => 0,
                    'food_evals' => 0,
                    'workout_evals' => 0,
                    'total_evals' => 0,
                    'week_increase' => 0,
                    'kpi_card_total' => 0,
                    'groups' => 0,
                ],
                'overview' => [],
                'rows' => [],
                'chart' => [
                    'categories' => [],
                    'active_users' => [],
                    'food_evals' => [],
                    'workout_evals' => [],
                ],
                'leaderboard' => [],
            ]);
        }
    }

    /**
     * Export Excel daftar user aktif (luas) minggu terpilih (Minggu–Sabtu).
     */
    public function activeStatsExport(Request $request): JsonResponse
    {
        $this->ensureScopedIndexFilters($request);

        if (! $this->connection->isUp()) {
            return response()->json(['message' => 'Koneksi BeWell tidak tersedia.'], 503);
        }

        $weekStart = is_string($request->input('week_start'))
            ? $request->input('week_start')
            : null;

        try {
            $payload = $this->activeStatsService->getActiveUsersForExport(
                $weekStart,
                $this->indexFilters,
            );
            $week = $payload['week'];
            $rows = $payload['rows'];

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
                'Nama',
                'Site',
                'Perusahaan',
                'Jabatan',
                'Food',
                'Workout',
                'Eval',
                'Tanggal Aktif',
            ]);
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('User Aktif');

            $rowNum = 2;
            foreach ($rows as $row) {
                $sheet->fromArray([
                    $row['nama'],
                    $row['site'],
                    $row['perusahaan'],
                    $row['jabatan'],
                    $row['food_evals'],
                    $row['workout_evals'],
                    $row['total_evals'],
                    $row['tanggal_aktif'],
                ], null, 'A'.$rowNum);
                $rowNum++;
            }

            SpreadsheetExporter::download(
                $spreadsheet,
                'evaluasi_well_user_aktif_'.$week['start'].'_'.$week['end'].'_'.date('Ymd_His').'.xlsx'
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal mengekspor data user aktif.'], 500);
        }
    }

    /**
     * KPI JSON metrik wellness (saat ganti minggu di dashboard).
     */
    public function wellnessMetricsKpi(Request $request): JsonResponse
    {
        $this->ensureScopedIndexFilters($request);

        $weekStart = is_string($request->input('week_start'))
            ? $request->input('week_start')
            : null;
        $site = trim((string) $request->input('site', ''));
        $company = trim((string) $request->input('company', $request->input('perusahaan', '')));

        try {
            return response()->json(
                $this->wellnessMetricsService->getKpiPayload($this->indexFilters, $weekStart, $site, $company)
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['available' => false, 'message' => 'Gagal memuat KPI wellness.'], 500);
        }
    }

    /**
     * DataTables server-side metrik wellness per karyawan.
     */
    public function wellnessMetricsData(Request $request): JsonResponse
    {
        $this->ensureScopedIndexFilters($request);
        $draw = (int) $request->input('draw', 1);

        $weekStart = is_string($request->input('week_start'))
            ? $request->input('week_start')
            : null;
        $site = trim((string) $request->input('site', ''));
        $company = trim((string) $request->input('company', $request->input('perusahaan', '')));
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
        $orderDir = (string) data_get($request->input('order'), '0.dir', 'asc');

        return response()->json(
            $this->wellnessMetricsService->datatable(
                $draw,
                $start,
                $length,
                $search,
                $orderColumnIndex,
                $orderDir,
                $this->indexFilters,
                $weekStart,
                $site,
                $company,
            )
        );
    }

    /**
     * Export Excel metrik wellness per karyawan.
     */
    public function wellnessMetricsExport(Request $request): JsonResponse
    {
        $this->ensureScopedIndexFilters($request);

        if (! $this->connection->isUp()) {
            return response()->json(['message' => 'Koneksi BeWell tidak tersedia.'], 503);
        }

        $weekStart = is_string($request->input('week_start'))
            ? $request->input('week_start')
            : null;
        $site = trim((string) $request->input('site', ''));
        $company = trim((string) $request->input('company', $request->input('perusahaan', '')));
        $search = trim((string) $request->query('search', ''));

        try {
            $payload = $this->wellnessMetricsService->exportRows(
                $this->indexFilters,
                $weekStart,
                $site,
                $company,
                $search,
            );
            $week = $payload['week'];
            $rows = $payload['rows'];

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
                'Nama',
                'Site',
                'Perusahaan',
                'Jabatan',
                'Durasi (menit)',
                'Avg HR',
                'Intensitas',
                'Frekuensi',
                'Kalori Out',
                'Kalori In',
                'Protein (g)',
                'Karbo (g)',
                'Lemak (g)',
            ]);
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Metrik Wellness');

            $rowNum = 2;
            foreach ($rows as $row) {
                $sheet->fromArray([
                    $row['nama'],
                    $row['site'],
                    $row['perusahaan'],
                    $row['jabatan'],
                    $row['durasi_minutes'],
                    $row['avg_hr'] ?? '-',
                    $row['intensitas'],
                    $row['frekuensi'],
                    $row['kalori_out'],
                    $row['kalori_in'],
                    $row['protein_g'],
                    $row['carbs_g'],
                    $row['fats_g'],
                ], null, 'A'.$rowNum);
                $rowNum++;
            }

            SpreadsheetExporter::download(
                $spreadsheet,
                'evaluasi_well_metrik_wellness_'.$week['start'].'_'.$week['end'].'_'.date('Ymd_His').'.xlsx'
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal mengekspor metrik wellness.'], 500);
        }
    }

    /**
     * DataTables server-side: karyawan AKTIF + status install & user aktif minggu ini.
     */
    public function notInstalledData(Request $request): JsonResponse
    {
        $this->ensureScopedIndexFilters($request);
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
                2 => 'e.departement',
                3 => 'e.divisi',
                4 => 'is_installed',
                5 => 'is_weekly_active',
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
                    'site' => $this->siteResolver->resolveOrDash(
                        isset($row->kode_sid) ? (string) $row->kode_sid : null,
                        isset($row->site) ? (string) $row->site : null,
                    ),
                    'company' => (string) (trim((string) ($row->nama_perusahaan ?? '')) !== '' ? $row->nama_perusahaan : '-'),
                    'departement' => (string) (trim((string) ($row->departement ?? '')) !== '' ? $row->departement : '-'),
                    'divisi' => (string) (trim((string) ($row->divisi ?? '')) !== '' ? $row->divisi : '-'),
                    'jabatan' => (string) (trim((string) ($row->jabatan_fungsional ?? '')) !== '' ? $row->jabatan_fungsional : '-'),
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
            ]);
        }
    }

    /**
     * Export Excel karyawan AKTIF (mengikuti filter install / user aktif / site / dll).
     */
    public function notInstalledExport(Request $request): JsonResponse
    {
        $this->ensureScopedIndexFilters($request);
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
                'Departemen',
                'Divisi',
                'Jabatan Fungsional',
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
                    $this->siteResolver->resolveOrDash(
                        isset($row->kode_sid) ? (string) $row->kode_sid : null,
                        isset($row->site) ? (string) $row->site : null,
                    ),
                    (string) (trim((string) ($row->nama_perusahaan ?? '')) !== '' ? $row->nama_perusahaan : '-'),
                    (string) (trim((string) ($row->departement ?? '')) !== '' ? $row->departement : '-'),
                    (string) (trim((string) ($row->divisi ?? '')) !== '' ? $row->divisi : '-'),
                    (string) (trim((string) ($row->jabatan_fungsional ?? '')) !== '' ? $row->jabatan_fungsional : '-'),
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
     * @return array{newUsersTotal:int, newUsersWeekIncrease:int, newUsersWeekIncreasePercent:float}
     */
    private function newUsersCardData(): array
    {
        $newUsersTotal = 0;
        $newUsersWeekIncrease = 0;
        $newUsersWeekIncreasePercent = 0.0;

        if (! $this->connection->isUp()) {
            return compact('newUsersTotal', 'newUsersWeekIncrease', 'newUsersWeekIncreasePercent');
        }

        try {
            $cached = Cache::remember(
                'evaluasi_well:new_users_card_v2:'.$this->scopeCacheKey(),
                300,
                function (): array {
                    $db = DB::connection(BewellConnectionService::CONNECTION);
                    [$inSql, $inBindings] = $this->scopedUserIdSql('install_signals.user_id');

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
                        'SELECT COUNT(DISTINCT user_id) AS c FROM ('.$installSignalsSql.') AS install_signals WHERE 1 = 1'.$inSql,
                        array_merge(['login_success'], $inBindings)
                    );
                    $total = (int) ($row->c ?? 0);

                    $week = $this->activeStatsService->resolveWeekRange(null);
                    $weekStart = $week['start'].' 00:00:00';

                    $row = $db->selectOne(
                        'SELECT COUNT(*) AS c FROM (
                            SELECT user_id
                            FROM ('.$installSignalsSql.') AS install_signals
                            WHERE 1 = 1'.$inSql.'
                            GROUP BY user_id
                            HAVING MIN(created_at) >= ?
                        ) AS first_install_week',
                        array_merge(['login_success'], $inBindings, [$weekStart])
                    );

                    $increase = (int) ($row->c ?? 0);

                    return [
                        'newUsersTotal' => $total,
                        'newUsersWeekIncrease' => $increase,
                        'newUsersWeekIncreasePercent' => $this->weekIncreasePercent($increase, $total),
                    ];
                }
            );

            $newUsersTotal = (int) $cached['newUsersTotal'];
            $newUsersWeekIncrease = (int) $cached['newUsersWeekIncrease'];
            $newUsersWeekIncreasePercent = (float) $cached['newUsersWeekIncreasePercent'];
        } catch (Throwable $e) {
            report($e);
        }

        return compact('newUsersTotal', 'newUsersWeekIncrease', 'newUsersWeekIncreasePercent');
    }

    /**
     * Active users minggu ini (Minggu–Sabtu): minimal salah satu dari
     * - upload foto makan (food_analyses.source_type = photo)
     * - workout_analyses
     * - aktivitas komunitas (post / join / RSVP)
     * - Main Bareng (host / participant open_play)
     *
     * Increase = user aktif minggu ini yang belum aktif minggu sebelumnya.
     *
     * @return array{activeUsersTotal:int, activeUsersWeekIncrease:int, activeUsersWeekIncreasePercent:float}
     */
    private function activeUsersCardData(): array
    {
        $activeUsersTotal = 0;
        $activeUsersWeekIncrease = 0;
        $activeUsersWeekIncreasePercent = 0.0;

        if (! $this->connection->isUp()) {
            return compact('activeUsersTotal', 'activeUsersWeekIncrease', 'activeUsersWeekIncreasePercent');
        }

        try {
            $cached = Cache::remember(
                'evaluasi_well:active_users_card_v3:'.$this->scopeCacheKey(),
                300,
                function (): array {
                    $thisWeek = $this->activeStatsService->resolveWeekRange(null);
                    $lastWeek = $this->activeStatsService->resolveWeekRange($thisWeek['prev_start']);

                    $from = $thisWeek['start'].' 00:00:00';
                    $to = Carbon::parse($thisWeek['end'])->endOfDay()->format('Y-m-d H:i:s');
                    $prevFrom = $lastWeek['start'].' 00:00:00';
                    $prevTo = Carbon::parse($lastWeek['end'])->endOfDay()->format('Y-m-d H:i:s');

                    $total = $this->activeStatsService->countActiveUsersInRange(
                        $from,
                        $to,
                        $this->indexFilters,
                    );
                    $increase = $this->activeStatsService->countNewlyActiveUsersVsPreviousWeek(
                        $from,
                        $to,
                        $prevFrom,
                        $prevTo,
                        $this->indexFilters,
                    );

                    return [
                        'activeUsersTotal' => $total,
                        'activeUsersWeekIncrease' => $increase,
                        'activeUsersWeekIncreasePercent' => $this->weekIncreasePercent($increase, $total),
                    ];
                }
            );

            $activeUsersTotal = (int) $cached['activeUsersTotal'];
            $activeUsersWeekIncrease = (int) $cached['activeUsersWeekIncrease'];
            $activeUsersWeekIncreasePercent = (float) $cached['activeUsersWeekIncreasePercent'];
        } catch (Throwable $e) {
            report($e);
        }

        return compact('activeUsersTotal', 'activeUsersWeekIncrease', 'activeUsersWeekIncreasePercent');
    }

    /**
     * Total Karyawan = karyawan status AKTIF setelah exclusion rules
     * (VISITOR, Yayasan Dharma Bakti, Berau intern/poltek/kampus merdeka/prakerin, dll).
     *
     * @return array{totalKaryawan:int, totalKaryawanWeekIncrease:int, totalKaryawanWeekIncreasePercent:float}
     */
    private function totalKaryawanCardData(): array
    {
        $totalKaryawan = 0;
        $totalKaryawanWeekIncrease = 0;
        $totalKaryawanWeekIncreasePercent = 0.0;

        if (! $this->connection->isUp()) {
            return compact('totalKaryawan', 'totalKaryawanWeekIncrease', 'totalKaryawanWeekIncreasePercent');
        }

        try {
            $totalKaryawan = (int) $this->activeEmployeesBaseQuery()->count('e.id');

            $week = $this->activeStatsService->resolveWeekRange(null);
            $from = $week['start'].' 00:00:00';
            $to = Carbon::parse($week['end'])->endOfDay()->format('Y-m-d H:i:s');
            $prevFrom = $week['prev_start'].' 00:00:00';
            $prevTo = Carbon::parse($week['prev_start'])
                ->startOfWeek(Carbon::SUNDAY)
                ->endOfWeek(Carbon::SATURDAY)
                ->endOfDay()
                ->format('Y-m-d H:i:s');

            $thisWeek = (int) $this->activeEmployeesBaseQuery()
                ->whereBetween('e.created_at', [$from, $to])
                ->count('e.id');
            $lastWeek = (int) $this->activeEmployeesBaseQuery()
                ->whereBetween('e.created_at', [$prevFrom, $prevTo])
                ->count('e.id');

            $totalKaryawanWeekIncrease = max(0, $thisWeek - $lastWeek);
            $totalKaryawanWeekIncreasePercent = $this->weekIncreasePercent(
                $totalKaryawanWeekIncrease,
                $totalKaryawan,
            );
        } catch (Throwable $e) {
            report($e);
        }

        return compact('totalKaryawan', 'totalKaryawanWeekIncrease', 'totalKaryawanWeekIncreasePercent');
    }

    /**
     * Kartu: Total Komunitas, Total Main Bareng, Total Goal Aktif.
     *
     * @return array{
     *     totalKomunitas:int,
     *     totalKomunitasWeekIncrease:int,
     *     totalKomunitasWeekIncreasePercent:float,
     *     totalMainBareng:int,
     *     totalMainBarengWeekIncrease:int,
     *     totalMainBarengWeekIncreasePercent:float,
     *     totalGoalAktif:int,
     *     totalGoalAktifWeekIncrease:int,
     *     totalGoalAktifWeekIncreasePercent:float
     * }
     */
    private function engagementCardsData(): array
    {
        $totalKomunitas = 0;
        $totalKomunitasWeekIncrease = 0;
        $totalKomunitasWeekIncreasePercent = 0.0;
        $totalMainBareng = 0;
        $totalMainBarengWeekIncrease = 0;
        $totalMainBarengWeekIncreasePercent = 0.0;
        $totalGoalAktif = 0;
        $totalGoalAktifWeekIncrease = 0;
        $totalGoalAktifWeekIncreasePercent = 0.0;

        if (! $this->connection->isUp()) {
            return compact(
                'totalKomunitas',
                'totalKomunitasWeekIncrease',
                'totalKomunitasWeekIncreasePercent',
                'totalMainBareng',
                'totalMainBarengWeekIncrease',
                'totalMainBarengWeekIncreasePercent',
                'totalGoalAktif',
                'totalGoalAktifWeekIncrease',
                'totalGoalAktifWeekIncreasePercent',
            );
        }

        try {
            $db = DB::connection(BewellConnectionService::CONNECTION);
            $week = $this->activeStatsService->resolveWeekRange(null);
            $lastWeek = $this->activeStatsService->resolveWeekRange($week['prev_start']);
            $weekStart = $week['start'].' 00:00:00';
            $weekEnd = Carbon::parse($week['end'])->endOfDay()->format('Y-m-d H:i:s');
            $lastWeekStart = $lastWeek['start'].' 00:00:00';
            $lastWeekEnd = Carbon::parse($lastWeek['end'])->endOfDay()->format('Y-m-d H:i:s');

            $totalKomunitas = (int) $db->table('communities')->count();
            $komunitasThisWeek = (int) $db->table('communities')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();
            $komunitasLastWeek = (int) $db->table('communities')
                ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
                ->count();
            $totalKomunitasWeekIncrease = max(0, $komunitasThisWeek - $komunitasLastWeek);
            $totalKomunitasWeekIncreasePercent = $this->weekIncreasePercent(
                $totalKomunitasWeekIncrease,
                $totalKomunitas,
            );

            $totalMainBareng = (int) $db->table('open_play_events')->count();
            $mainBarengThisWeek = (int) $db->table('open_play_events')
                ->whereBetween('starts_at', [$weekStart, $weekEnd])
                ->count();
            $mainBarengLastWeek = (int) $db->table('open_play_events')
                ->whereBetween('starts_at', [$lastWeekStart, $lastWeekEnd])
                ->count();
            $totalMainBarengWeekIncrease = max(0, $mainBarengThisWeek - $mainBarengLastWeek);
            $totalMainBarengWeekIncreasePercent = $this->weekIncreasePercent(
                $totalMainBarengWeekIncrease,
                $totalMainBareng,
            );

            $totalGoalAktif = (int) $this->applyScopedUserIds(
                $db->table('user_goals')->where('status', 'active'),
                'user_id'
            )->count();
            $goalThisWeek = (int) $this->applyScopedUserIds(
                $db->table('user_goals')
                    ->where('status', 'active')
                    ->whereBetween('created_at', [$weekStart, $weekEnd]),
                'user_id'
            )->count();
            $goalLastWeek = (int) $this->applyScopedUserIds(
                $db->table('user_goals')
                    ->where('status', 'active')
                    ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd]),
                'user_id'
            )->count();
            $totalGoalAktifWeekIncrease = max(0, $goalThisWeek - $goalLastWeek);
            $totalGoalAktifWeekIncreasePercent = $this->weekIncreasePercent(
                $totalGoalAktifWeekIncrease,
                $totalGoalAktif,
            );
        } catch (Throwable $e) {
            report($e);
        }

        return compact(
            'totalKomunitas',
            'totalKomunitasWeekIncrease',
            'totalKomunitasWeekIncreasePercent',
            'totalMainBareng',
            'totalMainBarengWeekIncrease',
            'totalMainBarengWeekIncreasePercent',
            'totalGoalAktif',
            'totalGoalAktifWeekIncrease',
            'totalGoalAktifWeekIncreasePercent',
        );
    }

    /**
     * Persentase kenaikan minggu ini vs baseline (total sebelum kenaikan).
     */
    private function weekIncreasePercent(int $increase, int $total): float
    {
        $increase = max(0, $increase);
        $baseline = max(0, $total - $increase);

        if ($baseline <= 0) {
            return $increase > 0 ? 100.0 : 0.0;
        }

        return round(($increase / $baseline) * 100, 1);
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
                ->whereRaw('UPPER(TRIM(c.name)) <> ?', ['RUNNING SUNDAY MORNING'])
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
     * Tren partisipasi user aktif 12 minggu terakhir (Minggu–Sabtu).
     * Series chart = % aktif / total karyawan; tooltip memakai jumlah user absolut.
     *
     * @return array{
     *     activeTrendLabels:array<int,string>,
     *     activeTrendSeries:array<int,float>,
     *     activeTrendUserCounts:array<int,int>,
     *     activeTrendThisWeek:int,
     *     activeTrendThisWeekPercent:float,
     *     activeTrendWeekIncrease:int
     * }
     */
    private function activeUsersWeeklyTrendData(): array
    {
        $activeTrendLabels = [];
        $activeTrendSeries = [];
        $activeTrendUserCounts = [];
        $activeTrendThisWeek = 0;
        $activeTrendThisWeekPercent = 0.0;
        $activeTrendWeekIncrease = 0;

        if (! $this->connection->isUp()) {
            return compact(
                'activeTrendLabels',
                'activeTrendSeries',
                'activeTrendUserCounts',
                'activeTrendThisWeek',
                'activeTrendThisWeekPercent',
                'activeTrendWeekIncrease',
            );
        }

        try {
            $trend = $this->activeStatsService->getWeeklyTrend($this->indexFilters);
            $activeTrendLabels = $trend['labels'] ?? [];
            $activeTrendUserCounts = array_map(
                static fn (mixed $value): int => (int) $value,
                $trend['active_users'] ?? [],
            );

            $totalKaryawan = (int) $this->activeEmployeesBaseQuery()->count('e.id');
            $activeTrendSeries = [];
            foreach ($activeTrendUserCounts as $activeCount) {
                $activeTrendSeries[] = $totalKaryawan > 0
                    ? round(($activeCount / $totalKaryawan) * 100, 1)
                    : 0.0;
            }

            $count = count($activeTrendUserCounts);
            $activeTrendThisWeek = $count > 0 ? $activeTrendUserCounts[$count - 1] : 0;
            $prevWeek = $count > 1 ? $activeTrendUserCounts[$count - 2] : 0;
            $activeTrendWeekIncrease = max(0, $activeTrendThisWeek - $prevWeek);
            $activeTrendThisWeekPercent = $count > 0
                ? (float) $activeTrendSeries[$count - 1]
                : 0.0;
        } catch (Throwable $e) {
            report($e);
        }

        return compact(
            'activeTrendLabels',
            'activeTrendSeries',
            'activeTrendUserCounts',
            'activeTrendThisWeek',
            'activeTrendThisWeekPercent',
            'activeTrendWeekIncrease',
        );
    }

    /**
     * Pola Aktivitas Penggunaan Aktif: heatmap kalender dari data pertama sampai hari ini.
     *
     * @return array<string, mixed>
     */
    private function loginAdoptionData(): array
    {
        $adoptionInstall = 0;
        $adoptionLoginSuccess = 0;
        $adoptionAktif = 0;
        $adoptionNewInstallsPeriod = 0;
        $adoptionAvgDailyUsage = 0;
        $adoptionChartLabels = [];
        $adoptionChartSeries = [];
        $adoptionTrendLabels = [];
        $adoptionTrendNewInstalls = [];
        $adoptionTrendActiveUsers = [];
        $adoptionTrendDates = [];
        $adoptionTrendRangeLabel = '';
        $activityPatternSeries = [];
        $activityPatternCategories = [];
        $activityPatternPeakDayLabel = '–';
        $activityPatternPeakDayCount = 0;
        $activityPatternAvgDaily = 0;
        $activityPatternWeekdayRatio = 0.0;
        $activityPatternPeakHourLabel = '–';
        $activityPatternInsight = 'Belum ada cukup data untuk insight pola aktivitas.';

        $empty = compact(
            'adoptionInstall',
            'adoptionLoginSuccess',
            'adoptionAktif',
            'adoptionNewInstallsPeriod',
            'adoptionAvgDailyUsage',
            'adoptionChartLabels',
            'adoptionChartSeries',
            'adoptionTrendLabels',
            'adoptionTrendNewInstalls',
            'adoptionTrendActiveUsers',
            'adoptionTrendDates',
            'adoptionTrendRangeLabel',
            'activityPatternSeries',
            'activityPatternCategories',
            'activityPatternPeakDayLabel',
            'activityPatternPeakDayCount',
            'activityPatternAvgDaily',
            'activityPatternWeekdayRatio',
            'activityPatternPeakHourLabel',
            'activityPatternInsight',
        );

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            $db = DB::connection(BewellConnectionService::CONNECTION);
            $yearStart = Carbon::now()->startOfYear()->format('Y-m-d H:i:s');
            $yearEnd = Carbon::now()->endOfYear()->format('Y-m-d H:i:s');
            $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d H:i:s');
            $weekEnd = Carbon::now()->endOfWeek()->format('Y-m-d H:i:s');

            $adoptionInstall = (int) $this->applyScopedUserIds(
                $db->table('login_audit')
                    ->where('event', 'login_success')
                    ->whereNotNull('user_id'),
                'user_id'
            )->distinct()->count('user_id');

            $adoptionLoginSuccess = (int) $this->applyScopedUserIds(
                $db->table('login_audit')
                    ->where('event', 'login_success')
                    ->whereBetween('created_at', [$yearStart, $yearEnd]),
                'user_id'
            )->count();

            $adoptionAktif = $this->activeStatsService->countActiveUsersInRange(
                $weekStart,
                $weekEnd,
                $this->indexFilters,
            );

            $trend = $this->installStatsService->getDailyTrend($this->installStatsFiltersFromIndex());
            $adoptionTrendLabels = $trend['labels'] ?? [];
            $adoptionTrendNewInstalls = $trend['new_installs'] ?? [];
            $adoptionTrendActiveUsers = $trend['active_users'] ?? [];
            $adoptionTrendDates = $trend['dates'] ?? [];
            $adoptionTrendRangeLabel = (string) ($trend['range_label'] ?? '');

            $adoptionNewInstallsPeriod = array_sum($adoptionTrendNewInstalls);
            $days = count($adoptionTrendActiveUsers);
            $adoptionAvgDailyUsage = $days > 0
                ? (int) round(array_sum($adoptionTrendActiveUsers) / $days)
                : 0;

            $adoptionChartLabels = $adoptionTrendLabels;
            $adoptionChartSeries = $adoptionTrendActiveUsers;

            $activityTrend = $this->installStatsService->getActivityPatternDailyTrend($this->installStatsFiltersFromIndex());
            $activityDates = $activityTrend['dates'] ?? [];
            $activityLabels = $activityTrend['labels'] ?? [];
            $activityUsers = $activityTrend['active_users'] ?? [];
            $activityRangeLabel = (string) ($activityTrend['range_label'] ?? '');

            if ($activityDates !== [] && $activityUsers !== []) {
                $built = $this->buildActivityPatternPayload(
                    $activityDates,
                    $activityLabels,
                    $activityUsers,
                    $activityRangeLabel,
                );
                $activityPatternSeries = $built['series'];
                $activityPatternCategories = $built['categories'];
                $activityPatternPeakDayLabel = $built['peak_day_label'];
                $activityPatternPeakDayCount = $built['peak_day_count'];
                $activityPatternAvgDaily = $built['avg_daily'];
                $activityPatternWeekdayRatio = $built['weekday_ratio'];
                $activityPatternInsight = $built['insight'];
                $adoptionTrendRangeLabel = $activityRangeLabel !== ''
                    ? $activityRangeLabel
                    : $adoptionTrendRangeLabel;

                $from = Carbon::parse((string) $activityDates[0])->startOfDay()->format('Y-m-d H:i:s');
                $to = Carbon::parse((string) $activityDates[array_key_last($activityDates)])->endOfDay()->format('Y-m-d H:i:s');
                $activityPatternPeakHourLabel = $this->resolvePeakHourLabel($from, $to);
            }
        } catch (Throwable $e) {
            report($e);
        }

        return compact(
            'adoptionInstall',
            'adoptionLoginSuccess',
            'adoptionAktif',
            'adoptionNewInstallsPeriod',
            'adoptionAvgDailyUsage',
            'adoptionChartLabels',
            'adoptionChartSeries',
            'adoptionTrendLabels',
            'adoptionTrendNewInstalls',
            'adoptionTrendActiveUsers',
            'adoptionTrendDates',
            'adoptionTrendRangeLabel',
            'activityPatternSeries',
            'activityPatternCategories',
            'activityPatternPeakDayLabel',
            'activityPatternPeakDayCount',
            'activityPatternAvgDaily',
            'activityPatternWeekdayRatio',
            'activityPatternPeakHourLabel',
            'activityPatternInsight',
        );
    }

    /**
     * Dummy pola aktivitas: awal tahun sampai hari ini.
     * Format calendar heatmap (GitHub-style): kolom = minggu, baris = Senin–Minggu.
     *
     * @return array<string, mixed>
     */
    private function dummyActivityPatternPayload(): array
    {
        $start = Carbon::now()->startOfYear()->startOfDay();
        $end = Carbon::now()->startOfDay();
        $totalDays = max(1, (int) $start->diffInDays($end) + 1);

        $dailyProfile = [];
        $cursor = $start->copy();
        $dayIndex = 0;

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            $dow = (int) $cursor->dayOfWeek;
            $progress = $dayIndex / $totalDays;

            // Pertumbuhan gradual sepanjang tahun.
            $base = (int) round(160 + ($progress * 1250) + (sin($dayIndex / 9) * 70));

            if ($dow === Carbon::SATURDAY) {
                $value = (int) round($base * 0.38);
            } elseif ($dow === Carbon::SUNDAY) {
                $value = (int) round($base * 0.28);
            } else {
                $boost = match ($dow) {
                    Carbon::TUESDAY, Carbon::WEDNESDAY => 1.18,
                    Carbon::MONDAY, Carbon::THURSDAY => 1.08,
                    default => 1.0,
                };
                $value = (int) round($base * $boost);
            }

            $dailyProfile[$key] = max(25, $value);
            $cursor->addDay();
            $dayIndex++;
        }

        // Pertahankan puncak mock yang sudah dikenal.
        $peakKey = '2026-09-02';
        if ($start->lte(Carbon::parse($peakKey)) && $end->gte(Carbon::parse($peakKey))) {
            $dailyProfile[$peakKey] = 2041;
        }

        $dates = array_keys($dailyProfile);
        $labels = array_map(
            static fn (string $d): string => Carbon::parse($d)->format('d M'),
            $dates
        );
        $values = array_values($dailyProfile);
        $rangeLabel = $start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y');

        $built = $this->buildActivityPatternPayload($dates, $labels, $values, $rangeLabel);

        return [
            'adoptionTrendRangeLabel' => $rangeLabel,
            'activityPatternSeries' => $built['series'],
            'activityPatternCategories' => $built['categories'],
            'activityPatternPeakDayLabel' => $built['peak_day_label'],
            'activityPatternPeakDayCount' => $built['peak_day_count'],
            'activityPatternAvgDaily' => $built['avg_daily'],
            'activityPatternWeekdayRatio' => $built['weekday_ratio'],
            'activityPatternPeakHourLabel' => '08:00 – 10:00',
            'activityPatternInsight' => $built['insight'],
        ];
    }

    /**
     * Calendar heatmap: kolom = minggu (Senin awal), baris = Senin–Minggu,
     * setiap tanggal hanya satu sel.
     *
     * @param  array<string, int>  $dailyByDate  map Y-m-d => jumlah user aktif
     * @return array{
     *     series: list<array{name: string, data: list<array<string, mixed>>}>,
     *     categories: list<string>
     * }
     */
    private function buildCalendarHeatmapSeries(array $dailyByDate): array
    {
        if ($dailyByDate === []) {
            return ['series' => [], 'categories' => []];
        }

        ksort($dailyByDate);
        $dateKeys = array_keys($dailyByDate);
        $start = Carbon::parse($dateKeys[0])->startOfDay();
        $end = Carbon::parse($dateKeys[array_key_last($dateKeys)])->startOfDay();

        $weekdayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        $dowToName = [
            Carbon::MONDAY => 'Senin',
            Carbon::TUESDAY => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY => 'Kamis',
            Carbon::FRIDAY => 'Jumat',
            Carbon::SATURDAY => 'Sabtu',
            Carbon::SUNDAY => 'Minggu',
        ];

        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $weekLabels = [];
        $seriesData = [];
        foreach ($weekdayNames as $name) {
            $seriesData[$name] = [];
        }

        $cursor = $gridStart->copy();
        $weekIndex = -1;
        while ($cursor->lte($gridEnd)) {
            if ((int) $cursor->dayOfWeek === Carbon::MONDAY) {
                $weekIndex++;
                $weekLabels[] = $cursor->translatedFormat('d M');
                foreach ($weekdayNames as $name) {
                    $seriesData[$name][$weekIndex] = [
                        'x' => $weekLabels[$weekIndex],
                        'y' => null,
                        'date' => null,
                        'date_label' => null,
                        'empty' => true,
                    ];
                }
            }

            $rowName = $dowToName[(int) $cursor->dayOfWeek] ?? 'Senin';
            $key = $cursor->format('Y-m-d');
            $inRange = $cursor->betweenIncluded($start, $end);

            if ($inRange) {
                $seriesData[$rowName][$weekIndex] = [
                    'x' => $weekLabels[$weekIndex],
                    'y' => (int) ($dailyByDate[$key] ?? 0),
                    'date' => $key,
                    'date_label' => $cursor->translatedFormat('d M Y'),
                    'empty' => false,
                ];
            }

            $cursor->addDay();
        }

        $series = [];
        foreach ($weekdayNames as $name) {
            $series[] = [
                'name' => $name,
                'data' => array_values($seriesData[$name]),
            ];
        }

        return [
            'series' => $series,
            'categories' => $weekLabels,
        ];
    }

    /**
     * @param  list<string>  $dates
     * @param  list<string>  $labels
     * @param  list<int>  $activeUsers
     * @return array{
     *     series: list<array{name: string, data: list<array<string, mixed>>}>,
     *     categories: list<string>,
     *     peak_day_label: string,
     *     peak_day_count: int,
     *     avg_daily: int,
     *     weekday_ratio: float,
     *     insight: string
     * }
     */
    private function buildActivityPatternPayload(
        array $dates,
        array $labels,
        array $activeUsers,
        string $rangeLabel,
    ): array {
        $dailyByDate = [];
        $peakCount = -1;
        $peakLabel = '–';
        $weekdaySum = 0.0;
        $weekdayDays = 0;
        $weekendSum = 0.0;
        $weekendDays = 0;
        $totalSum = 0;
        $totalDays = 0;

        $count = min(count($dates), count($activeUsers));
        for ($i = 0; $i < $count; $i++) {
            $date = (string) $dates[$i];
            $value = (int) $activeUsers[$i];
            $carbon = Carbon::parse($date);
            $key = $carbon->format('Y-m-d');
            $dailyByDate[$key] = $value;

            $totalSum += $value;
            $totalDays++;
            if ($value > $peakCount) {
                $peakCount = $value;
                $peakLabel = $carbon->translatedFormat('d M Y');
            }

            $dow = (int) $carbon->dayOfWeek;
            if ($dow >= Carbon::MONDAY && $dow <= Carbon::FRIDAY) {
                $weekdaySum += $value;
                $weekdayDays++;
            } else {
                $weekendSum += $value;
                $weekendDays++;
            }
        }

        $heatmap = $this->buildCalendarHeatmapSeries($dailyByDate);

        $avgDaily = $totalDays > 0 ? (int) round($totalSum / $totalDays) : 0;
        $weekdayAvg = $weekdayDays > 0 ? $weekdaySum / $weekdayDays : 0.0;
        $weekendAvg = $weekendDays > 0 ? $weekendSum / $weekendDays : 0.0;
        $ratio = $weekendAvg > 0
            ? round($weekdayAvg / $weekendAvg, 1)
            : ($weekdayAvg > 0 ? 99.0 : 0.0);

        $insight = 'Belum ada cukup data untuk insight pola aktivitas.';
        if ($totalDays > 0 && $peakCount >= 0) {
            if ($weekdayAvg >= $weekendAvg && $weekdayAvg > 0) {
                $insight = 'Aktivitas tertinggi biasanya terjadi pada hari kerja'
                    .($peakLabel !== '–' ? ', dengan puncak di '.$peakLabel : '')
                    .'. Manfaatkan momentum ini untuk program engagement.';
            } elseif ($weekendAvg > $weekdayAvg) {
                $insight = 'Aktivitas lebih tinggi di akhir pekan'
                    .($peakLabel !== '–' ? ', puncak pada '.$peakLabel : '')
                    .'. Pertimbangkan konten engagement khusus weekend.';
            } else {
                $insight = 'Pola aktivitas relatif merata sepanjang minggu'
                    .($rangeLabel !== '' ? ' ('.$rangeLabel.')' : '')
                    .'.';
            }
        }

        return [
            'series' => $heatmap['series'],
            'categories' => $heatmap['categories'],
            'peak_day_label' => $peakLabel,
            'peak_day_count' => max(0, $peakCount),
            'avg_daily' => $avgDaily,
            'weekday_ratio' => $ratio,
            'insight' => $insight,
        ];
    }

    private function resolvePeakHourLabel(string $from, string $to): string
    {
        try {
            $db = DB::connection(BewellConnectionService::CONNECTION);
            [$inSql, $inBindings] = $this->scopedUserIdSql('s.user_id');

            $signalsSql = '
                SELECT user_id, created_at FROM login_audit
                    WHERE event = ? AND user_id IS NOT NULL
                      AND created_at BETWEEN ? AND ?
                UNION ALL
                SELECT user_id, created_at FROM food_analyses
                    WHERE user_id IS NOT NULL
                      AND created_at BETWEEN ? AND ?
                UNION ALL
                SELECT user_id, created_at FROM workout_analyses
                    WHERE user_id IS NOT NULL
                      AND created_at BETWEEN ? AND ?
            ';

            $rows = $db->select(
                'SELECT HOUR(s.created_at) AS h, COUNT(*) AS c
                 FROM ('.$signalsSql.') AS s
                 WHERE 1 = 1'.$inSql.'
                 GROUP BY HOUR(s.created_at)',
                array_merge(
                    ['login_success', $from, $to, $from, $to, $from, $to],
                    $inBindings
                )
            );

            $hours = array_fill(0, 24, 0);
            foreach ($rows as $row) {
                $hUtc = (int) ($row->h ?? -1);
                if ($hUtc < 0 || $hUtc > 23) {
                    continue;
                }
                // created_at tersimpan UTC → konversi ke WITA (UTC+8).
                $hWita = ($hUtc + 8) % 24;
                $hours[$hWita] += (int) ($row->c ?? 0);
            }

            $bestStart = 0;
            $bestSum = -1;
            for ($h = 0; $h <= 22; $h++) {
                $sum = $hours[$h] + $hours[$h + 1];
                if ($sum > $bestSum) {
                    $bestSum = $sum;
                    $bestStart = $h;
                }
            }

            if ($bestSum <= 0) {
                return '–';
            }

            return sprintf('%02d:00 – %02d:00', $bestStart, $bestStart + 2);
        } catch (Throwable $e) {
            report($e);

            return '–';
        }
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

            $compositionOlahraga = (int) $this->applyScopedUserIds(
                $db->table('workout_analyses')
                    ->whereNotNull('user_id')
                    ->whereBetween('created_at', [$yearStart, $yearEnd]),
                'user_id'
            )->distinct()->count('user_id');

            $compositionNutrisi = (int) $this->applyScopedUserIds(
                $db->table('food_analyses')
                    ->where('source_type', 'photo')
                    ->whereNotNull('user_id')
                    ->whereBetween('created_at', [$yearStart, $yearEnd]),
                'user_id'
            )->distinct()->count('user_id');

            [$inSql, $inBindings] = $this->scopedUserIdSql('sosial_users.user_id');
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
                ) AS sosial_users WHERE 1 = 1'.$inSql,
                array_merge(
                    [
                        $yearStart, $yearEnd,
                        $yearStart, $yearEnd,
                        $yearStart, $yearEnd,
                        $yearStart, $yearEnd,
                        $yearStart, $yearEnd,
                    ],
                    $inBindings
                )
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
            $cached = Cache::remember('evaluasi_well:top_users_year:'.$this->scopeCacheKey(), 300, function (): array {
                $db = DB::connection(BewellConnectionService::CONNECTION);
                $yearStart = Carbon::now()->startOfYear()->format('Y-m-d H:i:s');
                $yearEnd = Carbon::now()->endOfYear()->format('Y-m-d H:i:s');
                [$inSql, $inBindings] = $this->scopedUserIdSql('e.id');

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
                    WHERE 1 = 1'.$inSql.'
                    ORDER BY s.total_cnt DESC, e.nama ASC
                    LIMIT 6',
                    array_merge(
                        [
                            'photo', $yearStart, $yearEnd,
                            $yearStart, $yearEnd,
                            $yearStart, $yearEnd,
                            $yearStart, $yearEnd,
                            $yearStart, $yearEnd,
                            $yearStart, $yearEnd,
                        ],
                        $inBindings
                    )
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
     * Populasi sama dengan Status Install: status AKTIF + exclusion rules
     * (Yayasan Dharma Bakti, Berau intern/poltek/kampus merdeka/prakerin, dll).
     * Site memakai site_dedicated karyawan_well (fallback employee_profiles.site).
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
            $employees = $this->activeEmployeesBaseQuery()
                ->get([
                    'e.kode_sid',
                    'e.site',
                    'e.nama',
                    'e.nama_perusahaan',
                    'e.departement',
                    'e.jabatan_fungsional',
                ]);

            $counts = [];

            foreach ($employees as $employee) {
                $rawSite = isset($employee->site) ? (string) $employee->site : null;
                $resolvedSite = $this->siteResolver->resolve(
                    isset($employee->kode_sid) ? (string) $employee->kode_sid : null,
                    $rawSite,
                );

                if ($this->exclusionRules->isExcludedRow([
                    'jabatan_fungsional' => isset($employee->jabatan_fungsional)
                        ? (string) $employee->jabatan_fungsional
                        : null,
                    'site' => $rawSite,
                    'nama' => isset($employee->nama) ? (string) $employee->nama : null,
                    'company' => isset($employee->nama_perusahaan)
                        ? (string) $employee->nama_perusahaan
                        : null,
                    'departement' => isset($employee->departement)
                        ? (string) $employee->departement
                        : null,
                ])) {
                    continue;
                }

                // Exclude juga jika site_dedicated (data asli) Jakarta/Poltek.
                if ($this->exclusionRules->isExcludedSite($resolvedSite)) {
                    continue;
                }

                $siteName = $resolvedSite !== '' ? $resolvedSite : 'Tidak diketahui';
                $counts[$siteName] = ($counts[$siteName] ?? 0) + 1;
            }

            $siteTotalEmployees = (int) array_sum($counts);
            arsort($counts);
            $barClasses = ['bg-primary-600', 'bg-orange', 'bg-yellow', 'bg-success-main', 'bg-info-main', 'bg-indigo'];
            $i = 0;

            foreach ($counts as $name => $total) {
                $pct = $siteTotalEmployees > 0
                    ? round($total / $siteTotalEmployees * 100, 1)
                    : 0.0;

                $siteRows[] = [
                    'name' => (string) $name,
                    'total' => $total,
                    'percent' => $pct,
                    'barClass' => $barClasses[$i % count($barClasses)],
                ];
                $i++;
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
            $cached = Cache::remember('evaluasi_well:weekly_activity:'.$this->scopeCacheKey(), 300, function (): array {
                $db = DB::connection(BewellConnectionService::CONNECTION);
                $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d H:i:s');
                $weekEnd = Carbon::now()->endOfWeek()->format('Y-m-d H:i:s');
                [$foodInSql, $foodInBindings] = $this->scopedUserIdSql('user_id');
                [$workoutInSql, $workoutInBindings] = $this->scopedUserIdSql('user_id');

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
                       '.$foodInSql.'
                     GROUP BY WEEKDAY(created_at)',
                    array_merge(['photo', $weekStart, $weekEnd], $foodInBindings)
                );

                $olahragaRows = $db->select(
                    'SELECT WEEKDAY(created_at) AS d, COUNT(*) AS total
                     FROM workout_analyses
                     WHERE created_at BETWEEN ? AND ?
                       '.$workoutInSql.'
                     GROUP BY WEEKDAY(created_at)',
                    array_merge([$weekStart, $weekEnd], $workoutInBindings)
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
     *     notInstalledDepartements:array<int,string>,
     *     notInstalledJabatanFungsionals:array<int,string>,
     *     notInstalledWeekLabel:string
     * }
     */
    private function notInstalledFilterData(): array
    {
        $notInstalledTotal = 0;
        $notInstalledSites = [];
        $notInstalledCompanies = [];
        $notInstalledDivisions = [];
        $notInstalledDepartements = [];
        $notInstalledJabatanFungsionals = [];
        $week = $this->currentWeekRange();
        $notInstalledWeekLabel = $week['label'];

        if (! $this->connection->isUp()) {
            return compact(
                'notInstalledTotal',
                'notInstalledSites',
                'notInstalledCompanies',
                'notInstalledDivisions',
                'notInstalledDepartements',
                'notInstalledJabatanFungsionals',
                'notInstalledWeekLabel',
            );
        }

        try {
            $cached = Cache::remember('evaluasi_well:active_employees_filters_v9:'.$this->scopeCacheKey(), 120, function (): array {
                $base = $this->activeEmployeesBaseQuery();

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
                    'total' => (int) $belumInstall->count(),
                    'sites' => $sites,
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
                    'departements' => (clone $base)
                        ->whereNotNull('e.departement')
                        ->where('e.departement', '<>', '')
                        ->distinct()
                        ->orderBy('e.departement')
                        ->pluck('e.departement')
                        ->map(static fn (mixed $departement): string => (string) $departement)
                        ->all(),
                    'jabatan_fungsionals' => (clone $base)
                        ->whereNotNull('e.jabatan_fungsional')
                        ->where('e.jabatan_fungsional', '<>', '')
                        ->distinct()
                        ->orderBy('e.jabatan_fungsional')
                        ->pluck('e.jabatan_fungsional')
                        ->map(static fn (mixed $jabatan): string => (string) $jabatan)
                        ->all(),
                ];
            });

            $notInstalledTotal = (int) $cached['total'];
            $notInstalledSites = $cached['sites'];
            $notInstalledCompanies = $cached['companies'];
            $notInstalledDivisions = $cached['divisions'];
            $notInstalledDepartements = $cached['departements'];
            $notInstalledJabatanFungsionals = $cached['jabatan_fungsionals'];
        } catch (Throwable $e) {
            report($e);
        }

        return compact(
            'notInstalledTotal',
            'notInstalledSites',
            'notInstalledCompanies',
            'notInstalledDivisions',
            'notInstalledDepartements',
            'notInstalledJabatanFungsionals',
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
     * Karyawan status AKTIF, exclude VISITOR/Presiden Direktur/Direktur tanpa
     * site (atau site HO), site Jakarta & Poltek, perusahaan Politeknik Sinarmas /
     * Sinarmas Maritim / Fusi, dan nama dummy.
     */
    private function activeEmployeesBaseQuery(): Builder
    {
        $query = DB::connection(BewellConnectionService::CONNECTION)
            ->table('employee_profiles as e')
            ->where('e.status_karyawan', 'AKTIF');

        $this->exclusionRules->applyToQuery($query);
        $this->applyScopedUserIds($query, 'e.id');

        return $query;
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
                'e.departement',
                'e.divisi',
                'e.jabatan_fungsional',
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
     * @return array{site:string,company:string,division:string,division_group:string,departement:string,jabatan_fungsional:string,install:string,user_aktif:string}
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

        $jabatanFungsional = $readFilter($request->input('jabatan_fungsional'));
        if (mb_strtoupper($jabatanFungsional) === 'VISITOR') {
            $jabatanFungsional = '';
        }

        return [
            'site' => $readFilter($request->input('site')),
            'company' => $readFilter($request->input('company')),
            'division' => $readFilter($request->input('division')),
            'division_group' => $readFilter($request->input('division_group')),
            'departement' => $readFilter($request->input('departement')),
            'jabatan_fungsional' => $jabatanFungsional,
            'install' => $install,
            'user_aktif' => $userAktif,
        ];
    }

    /**
     * @param  array{site:string,company:string,division:string,division_group?:string,departement:string,jabatan_fungsional:string,install:string,user_aktif:string}  $filters
     */
    private function applyNotInstalledFilters(
        Builder $query,
        array $filters,
        string $search = '',
        string $weekStart = '',
        string $weekEnd = '',
    ): Builder {
        $hasForcedScope = $this->mitraAssignmentService->hasScope($this->indexFilters);

        // Jangan double-apply site/perusahaan: indexFilters sudah membatasi via ID.
        if ($filters['site'] !== '' && ! $hasForcedScope) {
            $this->siteResolver->applySiteFilter($query, $filters['site']);
        }
        if ($filters['company'] !== '' && ! $hasForcedScope) {
            $names = $this->companyAliasResolver->matchingRawNames($filters['company']);
            if ($names === []) {
                $names = [$filters['company']];
            }
            $normalized = array_map(
                static fn (string $name): string => mb_strtoupper(trim($name)),
                $names
            );
            $placeholders = implode(',', array_fill(0, count($normalized), '?'));
            $query->whereRaw(
                'UPPER(TRIM(COALESCE(e.nama_perusahaan, \'\'))) IN ('.$placeholders.')',
                $normalized
            );
        }
        if (($filters['division_group'] ?? '') !== '') {
            $aliases = $this->divisiGroupResolver->aliasesForGroup($filters['division_group']);
            if ($aliases !== []) {
                $normalized = array_map(
                    static fn (string $alias): string => mb_strtoupper(trim($alias)),
                    $aliases
                );
                $placeholders = implode(',', array_fill(0, count($normalized), '?'));
                $query->whereRaw(
                    'UPPER(TRIM(COALESCE(e.divisi, \'\'))) IN ('.$placeholders.')',
                    $normalized
                );
            } else {
                // Grup belum termapping: cocokkan exact ke label / nilai resolve
                $query->whereRaw(
                    'UPPER(TRIM(COALESCE(e.divisi, \'\'))) = ?',
                    [mb_strtoupper($filters['division_group'])]
                );
            }
        } elseif (($filters['division'] ?? '') !== '') {
            $query->where('e.divisi', 'like', '%'.$filters['division'].'%');
        }
        if ($filters['departement'] !== '') {
            $query->where('e.departement', 'like', '%'.$filters['departement'].'%');
        }
        if ($filters['jabatan_fungsional'] !== '') {
            $query->where('e.jabatan_fungsional', $filters['jabatan_fungsional']);
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
            $query->where(function (Builder $inner) use ($like, $search): void {
                $inner->where('e.nama', 'like', $like)
                    ->orWhere('e.kode_sid', 'like', $like)
                    ->orWhere('e.nama_perusahaan', 'like', $like)
                    ->orWhere('e.departement', 'like', $like)
                    ->orWhere('e.divisi', 'like', $like)
                    ->orWhere('e.jabatan_fungsional', 'like', $like);
                $this->siteResolver->orWhereSiteMatchesSearch($inner, $like, $search);
            });
        }

        return $query;
    }

    /**
     * Terapkan scope Mitra Kerja (jika ada assignment) ATAU filter global dashboard
     * (site/perusahaan/divisi dari tombol Filter) ke $this->indexFilters, dipakai
     * oleh seluruh endpoint AJAX (install stats, active stats, wellness metrics,
     * status install) supaya "Filter" di header dashboard memengaruhi semua konten.
     * Dikirim lewat parameter khusus (scope_site/scope_perusahaan/scope_division)
     * agar tidak bentrok dengan filter lokal tiap modal/tabel (site/company/division_group).
     */
    protected function ensureScopedIndexFilters(Request $request): void
    {
        $this->ensureMitraAssignmentScope($request);

        if ($this->hasIndexScope()) {
            return;
        }

        $site = trim((string) $request->input('scope_site', ''));
        $perusahaan = trim((string) $request->input('scope_perusahaan', ''));
        $division = trim((string) $request->input('scope_division', ''));

        if ($site === '' && $perusahaan === '' && $division === '') {
            return;
        }

        $this->applyForcedIndexFilters([
            'site' => $site,
            'perusahaan' => $perusahaan,
            'division_group' => $division,
        ]);
    }

    /**
     * User Mitra Kerja: kunci query ke assignment meskipun AJAX mengenai
     * endpoint dashboard global (bukan /mitra/...). Manager tidak di-kunci.
     */
    protected function ensureMitraAssignmentScope(Request $request): void
    {
        if ($this->hasIndexScope()) {
            return;
        }

        $user = $request->user();
        if ($user === null || $this->accessService->isMitraManager($user)) {
            return;
        }

        $scope = $this->mitraAssignmentService->scopeFromAssignment(
            $this->mitraAssignmentService->findActiveForUser((int) $user->id)
        );
        if ($scope === null) {
            return;
        }

        $this->applyForcedIndexFilters($scope);
        $request->merge($this->mitraAssignmentService->toFilterPayload($scope));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyForcedIndexFilters(array $filters): void
    {
        $divisionGroup = trim((string) ($filters['division_group'] ?? $filters['division'] ?? $filters['divisi'] ?? ''));
        $this->indexFilters = array_merge(
            $this->mitraAssignmentService->normalizeScope($filters),
            [
                'division_group' => $divisionGroup,
                'division' => $divisionGroup,
                'divisi' => $divisionGroup,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function installStatsFiltersFromIndex(): array
    {
        return $this->installStatsService->normalizeFilters([
            'site' => $this->indexFilters['site'] ?? '',
            'company' => $this->indexFilters['perusahaan'] ?? '',
            'perusahaan' => $this->indexFilters['perusahaan'] ?? '',
            'division_group' => $this->indexFilters['division_group'] ?? '',
            'companies' => $this->indexFilters['companies'] ?? [],
            'pairs' => $this->indexFilters['pairs'] ?? [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function installStatsFiltersFromRequest(Request $request): array
    {
        // Filter lokal modal (jika diisi) menang; kalau kosong, jatuh ke filter
        // global dashboard (indexFilters) supaya tombol Filter tetap berlaku.
        $site = trim((string) $request->input('site', ''));
        if ($site === '') {
            $site = (string) ($this->indexFilters['site'] ?? '');
        }

        $company = trim((string) $request->input('company', $request->input('perusahaan', '')));
        if ($company === '') {
            $company = (string) ($this->indexFilters['perusahaan'] ?? '');
        }

        $divisionGroup = trim((string) $request->input('division_group', $request->input('division', '')));
        if ($divisionGroup === '') {
            $divisionGroup = (string) ($this->indexFilters['division_group'] ?? '');
        }

        return $this->installStatsService->normalizeFilters([
            'site' => $site,
            'division_group' => $divisionGroup,
            'jabatan' => $request->input('jabatan', $request->input('jabatan_fungsional')),
            'company' => $company,
            'perusahaan' => $company,
            'departement' => $request->input('departement'),
            'install' => $request->input('install'),
            'companies' => $request->input('companies'),
            'pairs' => $request->input('pairs'),
        ]);
    }

    private function hasIndexScope(): bool
    {
        return $this->mitraAssignmentService->hasScope($this->indexFilters);
    }

    private function scopeCacheKey(): string
    {
        return $this->mitraAssignmentService->cacheKeySuffix($this->indexFilters);
    }

    /**
     * @return array{0:string,1:list<int>}
     */
    private function scopedUserIdSql(string $column): array
    {
        $ids = $this->mitraAssignmentService->scopedEmployeeIds($this->indexFilters);
        if ($ids === null) {
            return ['', []];
        }
        if ($ids === []) {
            return [' AND 1 = 0', []];
        }

        $parts = [];
        $bindings = [];
        foreach (array_chunk($ids, 800) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $parts[] = $column.' IN ('.$placeholders.')';
            foreach ($chunk as $id) {
                $bindings[] = $id;
            }
        }

        return [' AND ('.implode(' OR ', $parts).')', $bindings];
    }

    private function applyScopedUserIds(Builder $query, string $column): Builder
    {
        $ids = $this->mitraAssignmentService->scopedEmployeeIds($this->indexFilters);
        if ($ids === null) {
            return $query;
        }
        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $outer) use ($column, $ids): void {
            $first = true;
            foreach (array_chunk($ids, 800) as $chunk) {
                if ($first) {
                    $outer->whereIn($column, $chunk);
                    $first = false;
                } else {
                    $outer->orWhereIn($column, $chunk);
                }
            }
        });
    }
}
