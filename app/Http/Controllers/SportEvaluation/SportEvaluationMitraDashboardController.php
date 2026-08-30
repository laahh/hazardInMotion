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
                'mitraScope' => ['site' => '', 'perusahaan' => ''],
                'mitraScopeLabel' => null,
                'siteOptions' => $options['sites'],
                'companyOptions' => $options['companies'],
                'ajaxRoutes' => $this->ajaxRoutes(),
                // Empty card defaults so blade partials tidak error
                'newUsersTotal' => 0,
                'newUsersWeekIncrease' => 0,
                'activeUsersTotal' => 0,
                'activeUsersWeekIncrease' => 0,
                'totalStravaConnect' => 0,
                'totalStravaConnectWeekIncrease' => 0,
                'totalKomunitas' => 0,
                'totalKomunitasWeekIncrease' => 0,
                'totalMainBareng' => 0,
                'totalMainBarengWeekIncrease' => 0,
                'totalGoalAktif' => 0,
                'totalGoalAktifWeekIncrease' => 0,
                'topKomunitas' => [],
                'activeTrendLabels' => [],
                'activeTrendSeries' => [],
                'activeTrendThisWeek' => 0,
                'activeTrendWeekIncrease' => 0,
                'adoptionInstall' => 0,
                'adoptionLoginSuccess' => 0,
                'adoptionAktif' => 0,
                'adoptionChartLabels' => [],
                'adoptionChartSeries' => [],
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
            ]);
        }

        $data = $this->buildIndexData($scope);

        return view('evaluasi-well.dashboard', array_merge($data, [
            'mitraMode' => true,
            'mitraNeedsPicker' => false,
            'mitraIsManager' => $isManager,
            'mitraScope' => $scope,
            'mitraScopeLabel' => $scope['perusahaan'].' · '.$scope['site'],
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

        $request->merge([
            'site' => $scope['site'],
            'company' => $scope['perusahaan'],
        ]);

        return parent::installStats($request);
    }

    public function installStatsExport(Request $request): JsonResponse
    {
        $scope = $this->requireScopeOrEmpty($request);
        if ($scope === null) {
            return response()->json(['message' => 'Scope mitra belum dipilih.'], 422);
        }

        $this->applyForcedIndexFilters($scope);
        $request->merge([
            'site' => $scope['site'],
            'company' => $scope['perusahaan'],
        ]);

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
        $request->merge([
            'site' => $scope['site'],
            'company' => $scope['perusahaan'],
        ]);

        return parent::notInstalledData($request);
    }

    public function notInstalledExport(Request $request): JsonResponse
    {
        $scope = $this->requireScopeOrEmpty($request);
        if ($scope === null) {
            return response()->json(['message' => 'Scope mitra belum dipilih.'], 422);
        }

        $this->applyForcedIndexFilters($scope);
        $request->merge([
            'site' => $scope['site'],
            'company' => $scope['perusahaan'],
        ]);

        return parent::notInstalledExport($request);
    }

    /**
     * @return array{site:string,perusahaan:string}|null
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
            'index' => route('evaluasi-well.mitra.index'),
        ];
    }
}
