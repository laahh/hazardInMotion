<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Dashboard Mitra Kerja — sama dengan dashboard utama, terscope site + perusahaan.
 */
final class SportEvaluationMitraDashboardController extends SportEvaluationDashboardController
{
    public function __construct(
        BewellConnectionService $connection,
        SportEvaluationInstallStatsService $installStatsService,
        SportEvaluationActiveStatsService $activeStatsService,
        SportEvaluationWellnessMetricsService $wellnessMetricsService,
        SportEvaluationKaryawanWellSiteResolver $siteResolver,
        SportEvaluationDivisiGroupResolver $divisiGroupResolver,
        SportEvaluationMitraAssignmentService $mitraAssignmentService,
        SportEvaluationCompanyAliasResolver $companyAliasResolver,
        SportEvaluationEmployeeExclusionRules $exclusionRules,
        SportEvaluationAccessService $accessService,
        private readonly SportEvaluationMitraAssignmentService $assignmentService,
    ) {
        parent::__construct(
            $connection,
            $installStatsService,
            $activeStatsService,
            $wellnessMetricsService,
            $siteResolver,
            $divisiGroupResolver,
            $mitraAssignmentService,
            $companyAliasResolver,
            $exclusionRules,
            $accessService,
        );
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $isManager = $this->accessService->isMitraManager($user);
        $scope = $this->accessService->resolveMitraScope($user, $request);
        $options = $this->assignmentService->filterOptions();

        if ($scope === null) {
            return view('evaluasi-well.dashboard', [
                'mitraMode' => true,
                'mitraNeedsPicker' => true,
                'mitraIsManager' => $isManager,
                'mitraScope' => ['site' => '', 'perusahaan' => '', 'pairs' => [], 'companies' => []],
                'mitraScopeLabel' => null,
                'siteOptions' => $options['sites'],
                'companyOptions' => $options['companies'],
                'ajaxRoutes' => $this->ajaxRoutes(),
                // Empty card defaults so blade partials tidak error
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
                'adoptionTrendRangeLabel' => '24 Aug 2026 – 17 Sep 2026',
                'activityPatternSeries' => [],
                'activityPatternCategories' => [],
                'activityPatternPeakDayLabel' => '02 Sep 2026',
                'activityPatternPeakDayCount' => 2041,
                'activityPatternAvgDaily' => 1159,
                'activityPatternWeekdayRatio' => 1.8,
                'activityPatternPeakHourLabel' => '08:00 – 10:00',
                'activityPatternInsight' => 'Aktivitas tertinggi biasanya terjadi pada hari kerja, dengan puncak di awal September. Manfaatkan momentum ini untuk program engagement.',
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
                'weeklyActivityLabels' => [],
                'weeklyMakananSeries' => [],
                'weeklyOlahragaSeries' => [],
                'weeklySosialSeries' => [],
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
                'wellnessCharts' => [
                    'total_employees' => 0,
                    'top_sports' => [],
                    'duration_buckets' => [],
                    'frequency_buckets' => [],
                    'calorie_buckets' => [],
                    'macro_attainment' => [],
                ],
            ]);
        }

        $data = $this->buildIndexData($scope);

        return view('evaluasi-well.dashboard', array_merge($data, [
            'mitraMode' => true,
            'mitraNeedsPicker' => false,
            'mitraIsManager' => $isManager,
            'mitraScope' => $scope,
            'mitraScopeLabel' => $this->assignmentService->scopeLabel($scope),
            'siteOptions' => $options['sites'],
            'companyOptions' => $options['companies'],
            'ajaxRoutes' => $this->ajaxRoutes($scope),
            'lockMitraFilters' => ! $isManager || $this->accessService->scopeFor($user) !== [],
        ]));
    }

    public function installStats(Request $request): JsonResponse
    {
        $scope = $this->requireScopeOrEmpty($request);
        if ($scope === null) {
            return response()->json(['available' => false, 'message' => 'Scope mitra belum dipilih.']);
        }

        $request->merge($this->assignmentService->toFilterPayload($scope));

        return parent::installStats($request);
    }

    public function installStatsExport(Request $request): JsonResponse
    {
        $scope = $this->requireScopeOrEmpty($request);
        if ($scope === null) {
            return response()->json(['message' => 'Scope mitra belum dipilih.'], 422);
        }

        $this->applyForcedIndexFilters($scope);
        $request->merge($this->assignmentService->toFilterPayload($scope));

        return parent::installStatsExport($request);
    }

    public function activeStats(Request $request): JsonResponse
    {
        $scope = $this->requireScopeOrEmpty($request);
        if ($scope === null) {
            return response()->json(['available' => false, 'message' => 'Scope mitra belum dipilih.']);
        }

        $dimension = is_string($request->input('dimension'))
            ? $request->input('dimension')
            : 'site';
        $weekStart = is_string($request->input('week_start'))
            ? $request->input('week_start')
            : null;

        try {
            return response()->json(
                app(SportEvaluationActiveStatsService::class)->getStats($dimension, $weekStart, $scope)
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'available' => false,
                'message' => 'Gagal memuat statistik user aktif.',
            ]);
        }
    }

    public function activeStatsExport(Request $request): JsonResponse
    {
        $scope = $this->requireScopeOrEmpty($request);
        if ($scope === null) {
            return response()->json(['message' => 'Scope mitra belum dipilih.'], 422);
        }

        $this->applyForcedIndexFilters($scope);
        $request->merge($this->assignmentService->toFilterPayload($scope));

        return parent::activeStatsExport($request);
    }

    public function wellnessMetricsKpi(Request $request): JsonResponse
    {
        $scope = $this->requireScopeOrEmpty($request);
        if ($scope === null) {
            return response()->json(['available' => false, 'message' => 'Scope mitra belum dipilih.']);
        }

        $this->applyForcedIndexFilters($scope);
        $request->merge($this->assignmentService->toFilterPayload($scope));

        return parent::wellnessMetricsKpi($request);
    }

    public function wellnessMetricsData(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 1);
        $scope = $this->requireScopeOrEmpty($request);
        if ($scope === null) {
            return response()->json([
                'draw' => $draw,
                'data' => [],
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
            ]);
        }

        $this->applyForcedIndexFilters($scope);
        $request->merge($this->assignmentService->toFilterPayload($scope));

        return parent::wellnessMetricsData($request);
    }

    public function wellnessMetricsExport(Request $request): JsonResponse
    {
        $scope = $this->requireScopeOrEmpty($request);
        if ($scope === null) {
            return response()->json(['message' => 'Scope mitra belum dipilih.'], 422);
        }

        $this->applyForcedIndexFilters($scope);
        $request->merge($this->assignmentService->toFilterPayload($scope));

        return parent::wellnessMetricsExport($request);
    }

    public function notInstalledData(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 1);
        $scope = $this->requireScopeOrEmpty($request);
        if ($scope === null) {
            return response()->json([
                'draw' => $draw,
                'data' => [],
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
            ]);
        }

        $this->applyForcedIndexFilters($scope);
        $request->merge($this->assignmentService->toFilterPayload($scope));

        return parent::notInstalledData($request);
    }

    public function notInstalledExport(Request $request): JsonResponse
    {
        $scope = $this->requireScopeOrEmpty($request);
        if ($scope === null) {
            return response()->json(['message' => 'Scope mitra belum dipilih.'], 422);
        }

        $this->applyForcedIndexFilters($scope);
        $request->merge($this->assignmentService->toFilterPayload($scope));

        return parent::notInstalledExport($request);
    }

    public function topUsersLeaderboard(Request $request): JsonResponse
    {
        $scope = $this->requireScopeOrEmpty($request);
        if ($scope === null) {
            return response()->json(['data' => []]);
        }

        $this->applyForcedIndexFilters($scope);
        $request->merge($this->assignmentService->toFilterPayload($scope));

        return parent::topUsersLeaderboard($request);
    }

    /**
     * @return array{
     *     site: string,
     *     perusahaan: string,
     *     pairs: list<array{site: string, perusahaan: string}>,
     *     companies: list<array{perusahaan: string, sites: list<string>}>
     * }|null
     */
    private function requireScopeOrEmpty(Request $request): ?array
    {
        return $this->accessService->resolveMitraScope($request->user(), $request);
    }

    /**
     * @param  array{site?:string,perusahaan?:string}|null  $scope
     * @return array{
     *     notInstalledData:string,
     *     notInstalledExport:string,
     *     installStats:string,
     *     installStatsExport:string,
     *     activeStats:string,
     *     activeStatsExport:string,
     *     index:string
     * }
     */
    private function ajaxRoutes(?array $scope = null): array
    {
        // Base URL tanpa query — scope dikirim dari JS agar tidak bentrok `?` ganda.
        return [
            'notInstalledData' => route('evaluasi-well.mitra.not-installed.data'),
            'notInstalledExport' => route('evaluasi-well.mitra.not-installed.export'),
            'installStats' => route('evaluasi-well.mitra.install-stats'),
            'installStatsExport' => route('evaluasi-well.mitra.install-stats.export'),
            'activeStats' => route('evaluasi-well.mitra.active-stats'),
            'activeStatsExport' => route('evaluasi-well.mitra.active-stats.export'),
            'wellnessMetricsKpi' => route('evaluasi-well.mitra.wellness-metrics.kpi'),
            'wellnessMetricsData' => route('evaluasi-well.mitra.wellness-metrics.data'),
            'wellnessMetricsExport' => route('evaluasi-well.mitra.wellness-metrics.export'),
            'index' => route('evaluasi-well.mitra.index'),
        ];
    }
}
