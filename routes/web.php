<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\HseValidationController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\WmsProxyController;
use App\Http\Controllers\CctvProxyController;
use App\Http\Controllers\CctvDataController;
use App\Http\Controllers\HazardMotion\PublicHazardMotionController;
use App\Http\Controllers\HazardMotion\HazardDetectionController;
use App\Http\Controllers\HazardMotion\RealtimeAlertController;
use App\Http\Controllers\HazardMotion\GeofencingController;
use App\Http\Controllers\HazardMotion\SpatialAnalysisController;
use App\Http\Controllers\HazardMotion\ReportingController;
use App\Http\Controllers\HazardMotion\CctvManagementController;
use App\Http\Controllers\HazardMotion\LiveStreamingController;
use App\Http\Controllers\HazardMotion\CctvEvaluationController;
use App\Http\Controllers\CarRegisterController;
use App\Http\Controllers\GrTableController;
use App\Http\Controllers\InsidenTabelController;
use App\Http\Controllers\HazardValidationController;
use App\Http\Controllers\BaselinePjaController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\HazardMotion\MapBaseController;
use App\Http\Controllers\HazardMotion\fullMapsController;
use App\Http\Controllers\HazardMotion\CctvP2hController;
use App\Http\Controllers\ScoreCard\ScoreCardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DailyOperationPlanController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\CctvAlertsDashboardController;
use App\Http\Controllers\InsidenCcrController;
use App\Http\Controllers\InsidenLpiController;
use App\Http\Controllers\CctvP2hChecklistController;
use App\Http\Controllers\EvaluasiUnitTabelController;
use App\Http\Controllers\FuelingEvaluasiController;
use App\Http\Controllers\AlignmentController;
use App\Http\Controllers\BecomlineController;
use App\Http\Controllers\PilotProjectValidationController;
use App\Http\Controllers\PilotProjectValidationProjectController;
use App\Http\Controllers\PilotProjectValidationRoadmapPeriodController;
use App\Http\Controllers\PilotProjectValidationTimelineTaskController;
use App\Http\Controllers\PilotProjectValidationGateController;
use App\Http\Controllers\PilotProjectValidationMetricController;
use App\Http\Controllers\PilotProjectValidationHistorySnapshotController;
use App\Http\Controllers\PeerPressureEdukasiController;
use App\Http\Controllers\PeerPressureEdukasiDataController;
use App\Http\Controllers\PeerPressureSbsKelompokController;
use App\Http\Controllers\PeerPressureSpeakUpFatigueController;
use App\Http\Controllers\PeerPressureValidasiTbcController;
use App\Http\Controllers\PeerPressureBerecordTableController;
use App\Http\Controllers\UnitMtdController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


require __DIR__ . '/sid_meeting.php';

Auth::routes();

// Form publik Auto Banned — tanpa login
require __DIR__.'/AutoBanned/auto_banned_public.php';

// Route khusus screenshot dashboard (tanpa middleware auth, pakai token)
// URL: /dopmikk/dopm/dashboard/screenshot?token=SECRET
Route::get('/dopmikk/dopm/dashboard/screenshot', function (\Illuminate\Http\Request $request) {
    $token = config('dashboard_screenshot.token');
    if ($token === '' || $token !== $request->query('token')) {
        abort(404);
    }
    $userId = config('dashboard_screenshot.user_id', 1);
    $user = \App\Models\User::find($userId);
    if (!$user) {
        abort(404);
    }
    \Illuminate\Support\Facades\Auth::login($user);
    return app(\App\Http\Controllers\DOPMIKK\DOPMController::class)->dashboard($request);
})->name('dopm.dashboard.screenshot');

// Define a group of routes with 'auth' middleware applied
Route::middleware(['auth'])->group(function () {
    // Define a GET route for the root URL ('/')
    Route::get('/', [HomeController::class, 'index'])->name('index');
    
    // Dashboard Routes
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::get('/data', [DashboardController::class, 'getData'])->name('data');
    });

    // CCTV Alerts Dashboard Routes
    Route::prefix('cctv-alerts-dashboard')->name('cctv-alerts-dashboard.')->group(function () {
        Route::get('/', [CctvAlertsDashboardController::class, 'index'])->name('index');
        Route::get('/chart-data', [CctvAlertsDashboardController::class, 'getChartData'])->name('chart-data');
        Route::get('/data', [CctvAlertsDashboardController::class, 'getDataTableData'])->name('data');
        Route::get('/sites', [CctvAlertsDashboardController::class, 'getSites'])->name('sites');
        Route::get('/export', [CctvAlertsDashboardController::class, 'exportExcel'])->name('export');
    });
    Route::get('/full-maps', [fullMapsController::class, 'index'])->name('fullmaps');
    Route::get('/full-maps/api/search-cctv', [fullMapsController::class, 'searchCctv'])->name('full-maps.api.search-cctv');
    Route::get('/full-maps/api/cctv-by-coverage', [fullMapsController::class, 'getCctvByCoverageLocation'])->name('full-maps.api.cctv-by-coverage');
    Route::get('/full-maps/api/sap-data', [fullMapsController::class, 'getSapDataApi'])->name('full-maps.api.sap-data');
    Route::post('/full-maps/api/generate-recommendations', [fullMapsController::class, 'generateControlRoomRecommendations'])->name('full-maps.api.generate-recommendations');
    Route::post('/full-maps/api/intervensi-area-kerja', [fullMapsController::class, 'storeIntervensiAreaKerja'])->name('full-maps.api.intervensi-area-kerja');
    Route::post('/full-maps/api/supervisory-alert-log', [fullMapsController::class, 'storeSupervisoryAlertLog'])->name('full-maps.api.supervisory-alert-log');
    Route::get('/full-maps/api/supervisory-alert-log-list', [fullMapsController::class, 'getSupervisoryAlertLogList'])->name('full-maps.api.supervisory-alert-log-list');
    Route::get('/full-maps/api/cctv-for-area-kerja', [fullMapsController::class, 'getCctvForAreaKerja'])->name('full-maps.api.cctv-for-area-kerja');
    Route::get('/full-maps/api/daily-operation-plans', [fullMapsController::class, 'getDailyOperationPlansWithPolygons'])->name('full-maps.api.daily-operation-plans');
    Route::get('/full-maps/api/dopm-ikk-today', [fullMapsController::class, 'getDopmIkkToday'])->name('full-maps.api.dopm-ikk-today');
    Route::get('/full-maps/api/ikk-work-permit-today', [fullMapsController::class, 'getIkkWorkPermitToday'])->name('full-maps.api.ikk-work-permit-today');
    Route::get('/full-maps/api/ikk-for-controlroom-sidebar', [fullMapsController::class, 'getIkkForControlroomSidebar'])->name('full-maps.api.ikk-for-controlroom-sidebar');
    Route::get('/full-maps/api/ikk-modal-data', [fullMapsController::class, 'getIkkModalData'])->name('full-maps.api.ikk-modal-data');
    Route::get('/full-maps/api/location-sap-counts', [fullMapsController::class, 'getLocationSapCounts'])->name('full-maps.api.location-sap-counts');
    Route::get('/full-maps/api/latest-cctv-alert', [fullMapsController::class, 'getLatestCctvAlert'])->name('full-maps.api.latest-cctv-alert');
    Route::get('/full-maps/api/cctv-alerts-with-units', [fullMapsController::class, 'getCctvAlertsWithUnits'])->name('full-maps.api.cctv-alerts-with-units');
    Route::get('/full-maps/api/photo-gallery', [fullMapsController::class, 'getPhotoGallery'])->name('full-maps.api.photo-gallery');
    Route::get('/full-maps/api/unit-vehicles', [fullMapsController::class, 'getUnitVehicles'])->name('full-maps.api.unit-vehicles');
    Route::get('/full-maps/api/nitip-units', [fullMapsController::class, 'getNitipUnits'])->name('full-maps.api.nitip-units');
    Route::get('/full-maps/api/nitip-unit-gps-logs', [fullMapsController::class, 'getNitipUnitGpsLogs'])->name('full-maps.api.nitip-unit-gps-logs');
    Route::get('/full-maps/export-evaluasi-unit-excel', [fullMapsController::class, 'exportEvaluasiUnitExcel'])->name('full-maps.export-evaluasi-unit-excel');
    Route::get('/images/unit.png', function () {
        return response()->file(resource_path('images/unit.png'));
    })->name('images.unit');
    Route::get('/images/lv.png', function () {
        return response()->file(resource_path('images/lv.png'));
    })->name('images.lv');
    Route::get('/images/LARGE_TRUCK.png', function () {
        return response()->file(resource_path('images/LARGE_TRUCK.png'));
    })->name('images.large-truck');
    Route::get('/clickhouse-status', [HomeController::class, 'checkClickHouseStatus'])->name('clickhouse.status');
    Route::get('/cctv-company-data', [HomeController::class, 'companyCctvData'])->name('cctv.company-data');
    Route::get('/company-cctv-data', [HomeController::class, 'getCompanyCctvData'])->name('company-cctv-data');
    Route::get('/company-stats', [HomeController::class, 'getCompanyStats'])->name('company-stats');

    // Fueling Evaluasi
    Route::get('/fueling-evaluasi', [FuelingEvaluasiController::class, 'index'])->name('fueling-evaluasi.index');
    Route::get('/fueling-evaluasi/dashboard', [FuelingEvaluasiController::class, 'dashboard'])->name('fueling-evaluasi.dashboard');
    Route::get('/fueling-evaluasi/tableau', [FuelingEvaluasiController::class, 'tableau'])->name('fueling-evaluasi.tableau');
    Route::get('/fueling-evaluasi/tabel', [EvaluasiUnitTabelController::class, 'index'])->name('fueling-evaluasi.tabel');
    Route::get('/fueling-evaluasi/per-hari', [EvaluasiUnitTabelController::class, 'perHari'])->name('fueling-evaluasi.per-hari');
    Route::get('/fueling-evaluasi/per-hari/data', [EvaluasiUnitTabelController::class, 'perHariData'])->name('fueling-evaluasi.per-hari.data');
    Route::get('/fueling-evaluasi/per-hari/all-data', [EvaluasiUnitTabelController::class, 'perHariAllData'])->name('fueling-evaluasi.per-hari.all-data');
    Route::get('/fueling-evaluasi/per-hari/dashboard-stats', [EvaluasiUnitTabelController::class, 'perHariDashboardStats'])->name('fueling-evaluasi.per-hari.dashboard-stats');
    Route::get('/fueling-evaluasi/per-hari/export-excel', [EvaluasiUnitTabelController::class, 'exportPerHariExcel'])->name('fueling-evaluasi.per-hari.export-excel');

    // Alignment (Peer Pressure Program Evaluation — static page)
    Route::get('/alignment', [AlignmentController::class, 'index'])->name('alignment.index');

    // Pilot Project Validation (Gate Go / No Go — portfolio + project CRUD)
    Route::prefix('pilot-project-validation')->name('pilot-project-validation.')->group(function () {
        Route::get('/', [PilotProjectValidationController::class, 'index'])->name('index');
        Route::get('/portfolio', [PilotProjectValidationController::class, 'portfolio'])->name('portfolio.show');
        Route::post('/portfolio', [PilotProjectValidationController::class, 'storePortfolio'])->name('portfolio.store');
        Route::get('/portfolio/import-excel', function () {
            return redirect()
                ->route('pilot-project-validation.index')
                ->with('warning', 'Endpoint impor Excel hanya menerima metode POST. Silakan upload dari halaman Pilot Project Validation.');
        })->name('portfolio.import-excel.get');
        Route::post('/portfolio/import-excel', [PilotProjectValidationController::class, 'importExcel'])->name('portfolio.import-excel');
        Route::get('/template-excel', [PilotProjectValidationController::class, 'downloadTemplate'])->name('template-excel');
        Route::get('/project-pdf/{key}', [PilotProjectValidationController::class, 'projectPdf'])
            ->where('key', 'arcas|mea|mgc|dozer|minepump')
            ->name('project-pdf.show');
        Route::resource('projects', PilotProjectValidationProjectController::class)->except(['show']);
        Route::resource('roadmap-periods', PilotProjectValidationRoadmapPeriodController::class)->except(['show']);
        Route::get('/roadmap-periods/template-excel', [PilotProjectValidationRoadmapPeriodController::class, 'downloadTemplateExcel'])->name('roadmap-periods.template-excel');
        Route::post('/roadmap-periods/import-excel', [PilotProjectValidationRoadmapPeriodController::class, 'importExcel'])->name('roadmap-periods.import-excel');
        Route::resource('timeline-tasks', PilotProjectValidationTimelineTaskController::class)->except(['show']);
        Route::get('/timeline-tasks/template-excel', [PilotProjectValidationTimelineTaskController::class, 'downloadTemplateExcel'])->name('timeline-tasks.template-excel');
        Route::post('/timeline-tasks/import-excel', [PilotProjectValidationTimelineTaskController::class, 'importExcel'])->name('timeline-tasks.import-excel');
        Route::resource('gates', PilotProjectValidationGateController::class)->except(['show']);
        Route::get('/gates/template-excel', [PilotProjectValidationGateController::class, 'downloadTemplateExcel'])->name('gates.template-excel');
        Route::post('/gates/import-excel', [PilotProjectValidationGateController::class, 'importExcel'])->name('gates.import-excel');
        Route::resource('metrics', PilotProjectValidationMetricController::class)->except(['show']);
        Route::get('/metrics/template-excel', [PilotProjectValidationMetricController::class, 'downloadTemplateExcel'])->name('metrics.template-excel');
        Route::post('/metrics/import-excel', [PilotProjectValidationMetricController::class, 'importExcel'])->name('metrics.import-excel');
        Route::resource('history-snapshots', PilotProjectValidationHistorySnapshotController::class)->except(['show']);
    });

    // Becomline (CRUD + Import Excel)
    Route::get('/becomline', [BecomlineController::class, 'index'])->name('becomline.index');
    Route::get('/becomline/data', [BecomlineController::class, 'data'])->name('becomline.data');
    Route::get('/becomline/stats', [BecomlineController::class, 'stats'])->name('becomline.stats');
    Route::get('/becomline/create', [BecomlineController::class, 'create'])->name('becomline.create');
    Route::post('/becomline', [BecomlineController::class, 'store'])->name('becomline.store');
    Route::get('/becomline/{id}/edit', [BecomlineController::class, 'edit'])->name('becomline.edit');
    Route::put('/becomline/{id}', [BecomlineController::class, 'update'])->name('becomline.update');
    Route::delete('/becomline/{id}', [BecomlineController::class, 'destroy'])->name('becomline.destroy');
    Route::get('/becomline/import/form', [BecomlineController::class, 'importForm'])->name('becomline.import-form');
    Route::post('/becomline/import', [BecomlineController::class, 'import'])->name('becomline.import');
    Route::get('/becomline/import/template', [BecomlineController::class, 'downloadTemplate'])->name('becomline.download-template');

    // Peer Pressure — kejadian edukasi + import Excel
    Route::get('/peer-pressure-edukasi/berecord', [PeerPressureBerecordTableController::class, 'index'])->name('peer-pressure-edukasi.berecord.index');

    Route::get('/peer-pressure-edukasi/data', [PeerPressureEdukasiDataController::class, 'index'])->name('peer-pressure-edukasi.data.index');
    Route::post('/peer-pressure-edukasi/data/import', [PeerPressureEdukasiDataController::class, 'import'])->name('peer-pressure-edukasi.data.import');
    Route::get('/peer-pressure-edukasi/data/template', [PeerPressureEdukasiDataController::class, 'downloadTemplate'])->name('peer-pressure-edukasi.data.template');
    Route::get('/peer-pressure-edukasi/data/create', [PeerPressureEdukasiDataController::class, 'create'])->name('peer-pressure-edukasi.data.create');
    Route::post('/peer-pressure-edukasi/data', [PeerPressureEdukasiDataController::class, 'store'])->name('peer-pressure-edukasi.data.store');
    Route::get('/peer-pressure-edukasi/data/{id}/edit', [PeerPressureEdukasiDataController::class, 'edit'])->whereNumber('id')->name('peer-pressure-edukasi.data.edit');
    Route::put('/peer-pressure-edukasi/data/{id}', [PeerPressureEdukasiDataController::class, 'update'])->whereNumber('id')->name('peer-pressure-edukasi.data.update');
    Route::delete('/peer-pressure-edukasi/data/{id}', [PeerPressureEdukasiDataController::class, 'destroy'])->whereNumber('id')->name('peer-pressure-edukasi.data.destroy');

    // Peer Pressure — grup SBS (kelompok + anggota)
    Route::get('/peer-pressure-edukasi/sbs', [PeerPressureSbsKelompokController::class, 'index'])->name('peer-pressure-edukasi.sbs.index');
    Route::post('/peer-pressure-edukasi/sbs/import', [PeerPressureSbsKelompokController::class, 'import'])->name('peer-pressure-edukasi.sbs.import');
    Route::get('/peer-pressure-edukasi/sbs/template', [PeerPressureSbsKelompokController::class, 'downloadTemplate'])->name('peer-pressure-edukasi.sbs.template');
    Route::get('/peer-pressure-edukasi/sbs/create', [PeerPressureSbsKelompokController::class, 'create'])->name('peer-pressure-edukasi.sbs.create');
    Route::post('/peer-pressure-edukasi/sbs', [PeerPressureSbsKelompokController::class, 'store'])->name('peer-pressure-edukasi.sbs.store');
    Route::get('/peer-pressure-edukasi/sbs/{id}/edit', [PeerPressureSbsKelompokController::class, 'edit'])->whereNumber('id')->name('peer-pressure-edukasi.sbs.edit');
    Route::put('/peer-pressure-edukasi/sbs/{id}', [PeerPressureSbsKelompokController::class, 'update'])->whereNumber('id')->name('peer-pressure-edukasi.sbs.update');
    Route::delete('/peer-pressure-edukasi/sbs/{id}', [PeerPressureSbsKelompokController::class, 'destroy'])->whereNumber('id')->name('peer-pressure-edukasi.sbs.destroy');

    // Peer Pressure — Speak Up Fatigue
    Route::get('/peer-pressure-edukasi/speak-up-fatigue', [PeerPressureSpeakUpFatigueController::class, 'index'])->name('peer-pressure-edukasi.speak-up-fatigue.index');
    Route::post('/peer-pressure-edukasi/speak-up-fatigue/import', [PeerPressureSpeakUpFatigueController::class, 'import'])->name('peer-pressure-edukasi.speak-up-fatigue.import');
    Route::get('/peer-pressure-edukasi/speak-up-fatigue/template', [PeerPressureSpeakUpFatigueController::class, 'downloadTemplate'])->name('peer-pressure-edukasi.speak-up-fatigue.template');
    Route::get('/peer-pressure-edukasi/speak-up-fatigue/create', [PeerPressureSpeakUpFatigueController::class, 'create'])->name('peer-pressure-edukasi.speak-up-fatigue.create');
    Route::post('/peer-pressure-edukasi/speak-up-fatigue', [PeerPressureSpeakUpFatigueController::class, 'store'])->name('peer-pressure-edukasi.speak-up-fatigue.store');
    Route::get('/peer-pressure-edukasi/speak-up-fatigue/{id}/edit', [PeerPressureSpeakUpFatigueController::class, 'edit'])->whereNumber('id')->name('peer-pressure-edukasi.speak-up-fatigue.edit');
    Route::put('/peer-pressure-edukasi/speak-up-fatigue/{id}', [PeerPressureSpeakUpFatigueController::class, 'update'])->whereNumber('id')->name('peer-pressure-edukasi.speak-up-fatigue.update');
    Route::delete('/peer-pressure-edukasi/speak-up-fatigue/{id}', [PeerPressureSpeakUpFatigueController::class, 'destroy'])->whereNumber('id')->name('peer-pressure-edukasi.speak-up-fatigue.destroy');

    // Peer Pressure — Validasi TBC
    Route::get('/peer-pressure-edukasi/validasi-tbc', [PeerPressureValidasiTbcController::class, 'index'])->name('peer-pressure-edukasi.validasi-tbc.index');
    Route::post('/peer-pressure-edukasi/validasi-tbc/import', [PeerPressureValidasiTbcController::class, 'import'])->name('peer-pressure-edukasi.validasi-tbc.import');
    Route::get('/peer-pressure-edukasi/validasi-tbc/template', [PeerPressureValidasiTbcController::class, 'downloadTemplate'])->name('peer-pressure-edukasi.validasi-tbc.template');
    Route::get('/peer-pressure-edukasi/validasi-tbc/create', [PeerPressureValidasiTbcController::class, 'create'])->name('peer-pressure-edukasi.validasi-tbc.create');
    Route::post('/peer-pressure-edukasi/validasi-tbc', [PeerPressureValidasiTbcController::class, 'store'])->name('peer-pressure-edukasi.validasi-tbc.store');
    Route::get('/peer-pressure-edukasi/validasi-tbc/{id}/edit', [PeerPressureValidasiTbcController::class, 'edit'])->whereNumber('id')->name('peer-pressure-edukasi.validasi-tbc.edit');
    Route::put('/peer-pressure-edukasi/validasi-tbc/{id}', [PeerPressureValidasiTbcController::class, 'update'])->whereNumber('id')->name('peer-pressure-edukasi.validasi-tbc.update');
    Route::delete('/peer-pressure-edukasi/validasi-tbc/{id}', [PeerPressureValidasiTbcController::class, 'destroy'])->whereNumber('id')->name('peer-pressure-edukasi.validasi-tbc.destroy');

    Route::get('/peer-pressure-edukasi', [PeerPressureEdukasiController::class, 'index'])->name('peer-pressure-edukasi.index');
    Route::get('/peer-pressure-edukasi/dashboard', [PeerPressureEdukasiController::class, 'dashboard'])->name('peer-pressure-edukasi.dashboard');
    Route::get('/peer-pressure-edukasi/dashboard-performance', [PeerPressureEdukasiController::class, 'dashboard'])->name('peer-pressure-edukasi.dashboard-performance');
    Route::get('/peer-pressure-edukasi/tematic', [PeerPressureEdukasiController::class, 'dashboard'])->name('peer-pressure-edukasi.tematic');
    Route::get('/peer-pressure-edukasi/dashboard-peer', [PeerPressureEdukasiController::class, 'dashboard'])->name('peer-pressure-edukasi.dashboard-peer');
    Route::get('/peer-pressure-edukasi/dashboard-risk-score', function () {
        return view('peer-pressure-edukasi.RiskScoreSite');
    })->name('peer-pressure-edukasi.dashboard-risk-score');
    Route::get('/peer-pressure-edukasi/tools-analisis', function () {
        return view('peer-pressure-edukasi.tools-analisis');
    })->name('peer-pressure-edukasi.tools-analisis');
    Route::get('/peer-pressure-edukasi/dashboard/weekly-trend', [PeerPressureEdukasiController::class, 'weeklyTrendData'])->name('peer-pressure-edukasi.dashboard.weekly-trend');
    Route::get('/peer-pressure-edukasi/dashboard/gap-matrix', [PeerPressureEdukasiController::class, 'gapMatrixData'])->name('peer-pressure-edukasi.dashboard.gap-matrix');
    Route::get('/peer-pressure-edukasi/dashboard/perusahaan-pelaksanaan-heatmap', [PeerPressureEdukasiController::class, 'perusahaanPelaksanaanHeatmapData'])->name('peer-pressure-edukasi.dashboard.perusahaan-pelaksanaan-heatmap');
    Route::get('/peer-pressure-edukasi/dashboard/pelaksanaan-baseline-detail', [PeerPressureEdukasiController::class, 'pelaksanaanBaselineDetailData'])->name('peer-pressure-edukasi.dashboard.pelaksanaan-baseline-detail');
    Route::get('/peer-pressure-edukasi/dashboard/highlight-issue-recommendation', [PeerPressureEdukasiController::class, 'dashboardHighlightIssueRecommendation'])->name('peer-pressure-edukasi.dashboard.highlight-issue-recommendation');
    Route::get('/peer-pressure-edukasi/dashboard/compliance-breakdown', [PeerPressureEdukasiController::class, 'complianceBreakdownData'])->name('peer-pressure-edukasi.dashboard.compliance-breakdown');
    Route::get('/peer-pressure-edukasi/dashboard/pelaksanaan-selesai', [PeerPressureEdukasiController::class, 'pelaksanaanSelesaiData'])->name('peer-pressure-edukasi.dashboard.pelaksanaan-selesai');
    Route::get('/peer-pressure-edukasi/dashboard/pelaksanaan-belum', [PeerPressureEdukasiController::class, 'pelaksanaanBelumData'])->name('peer-pressure-edukasi.dashboard.pelaksanaan-belum');
    Route::get('/peer-pressure-edukasi/dashboard/deviation-modal-detail', [PeerPressureEdukasiController::class, 'deviationModalDetailData'])->name('peer-pressure-edukasi.dashboard.deviation-modal-detail');
    Route::get('/peer-pressure-edukasi/dashboard/tbc-high-risk-cards', [PeerPressureEdukasiController::class, 'tbcHighRiskCards'])->name('peer-pressure-edukasi.dashboard.tbc-high-risk-cards');
    Route::get('/peer-pressure-edukasi/dashboard/tbc-aaj-car/search', [PeerPressureEdukasiController::class, 'tbcAajCarSearch'])->name('peer-pressure-edukasi.dashboard.tbc-aaj-car.search');
    Route::get('/peer-pressure-edukasi/dashboard/tbc-aaj-car', [PeerPressureEdukasiController::class, 'tbcAajCarShow'])->name('peer-pressure-edukasi.dashboard.tbc-aaj-car');
    Route::get('/peer-pressure-edukasi/dashboard/pelanggar-profiling', [PeerPressureEdukasiController::class, 'pelanggarProfilingDetail'])->name('peer-pressure-edukasi.dashboard.pelanggar-profiling');
    Route::get('/peer-pressure-edukasi/kejadian/{id}', [PeerPressureEdukasiController::class, 'kejadianDetail'])
        ->whereNumber('id')
        ->name('peer-pressure-edukasi.kejadian.detail');
    Route::post('/peer-pressure-edukasi/import', [PeerPressureEdukasiController::class, 'import'])->name('peer-pressure-edukasi.import');
    Route::get('/peer-pressure-edukasi/template', [PeerPressureEdukasiController::class, 'downloadTemplate'])->name('peer-pressure-edukasi.download-template');


    // Unit MTD (CRUD + Import Excel) - Site, Perusahaan, Kategori, No Unit, MTD, AVG per Day
    Route::get('/unit-mtd', [UnitMtdController::class, 'index'])->name('unit-mtd.index');
    Route::get('/unit-mtd/data', [UnitMtdController::class, 'data'])->name('unit-mtd.data');
    Route::get('/unit-mtd/create', [UnitMtdController::class, 'create'])->name('unit-mtd.create');
    Route::post('/unit-mtd', [UnitMtdController::class, 'store'])->name('unit-mtd.store');
    Route::get('/unit-mtd/{id}/edit', [UnitMtdController::class, 'edit'])->name('unit-mtd.edit');
    Route::put('/unit-mtd/{id}', [UnitMtdController::class, 'update'])->name('unit-mtd.update');
    Route::delete('/unit-mtd/{id}', [UnitMtdController::class, 'destroy'])->name('unit-mtd.destroy');
    Route::get('/unit-mtd/import/form', [UnitMtdController::class, 'importForm'])->name('unit-mtd.import-form');
    Route::post('/unit-mtd/import', [UnitMtdController::class, 'import'])->name('unit-mtd.import');
    Route::get('/unit-mtd/import/template', [UnitMtdController::class, 'downloadTemplate'])->name('unit-mtd.download-template');

    // Chatbot Routes
    Route::prefix('chatbot')->name('chatbot.')->group(function () {
        Route::get('/', [ChatController::class, 'index'])->name('index');
        Route::post('/send', [ChatController::class, 'sendMessage'])->name('send');
    });

    // Database Viewer Routes
    Route::prefix('database')->name('database.')->group(function () {
        Route::get('/', [DatabaseController::class, 'index'])->name('index');
        Route::get('/table/{schema}/{tableName}', [DatabaseController::class, 'showTable'])->name('table');
    });

    // HSE Validation Routes - HARUS sebelum catch-all route dan lebih spesifik
    Route::prefix('hse-validation')->name('hse-validation.')->group(function () {
        Route::get('/', [HseValidationController::class, 'index'])->name('index');
        Route::post('/process', [HseValidationController::class, 'process'])->name('process');
        Route::get('/loading/{processId}', [HseValidationController::class, 'loading'])->name('loading');
        Route::get('/progress/{processId}', [HseValidationController::class, 'getProgress'])->name('progress');
        Route::post('/process-async/{processId}', [HseValidationController::class, 'processAsync'])->name('process-async');
        Route::get('/image-proxy', [HseValidationController::class, 'imageProxy'])->name('image-proxy');
        Route::get('/results/{processId}', [HseValidationController::class, 'results'])->name('results.with-id'); // Route dengan processId
        Route::get('/results', [HseValidationController::class, 'results'])->name('results'); // Route tanpa processId
        Route::get('/download', [HseValidationController::class, 'download'])->name('download');
    });

    // HSE AI Validation Display Routes - HARUS sebelum catch-all route
    Route::prefix('hse-ai-validation')->name('hse-ai-validation.')->group(function () {
        Route::get('/', [App\Http\Controllers\HseAiValidationDisplayController::class, 'index'])->name('index');
        Route::get('/data/{site}', [App\Http\Controllers\HseAiValidationDisplayController::class, 'getSiteData'])->name('data');
        Route::put('/update/{id}', [App\Http\Controllers\HseAiValidationDisplayController::class, 'update'])->name('update');
    });

    // Report Weekly Routes - HARUS sebelum catch-all route
    Route::prefix('report-weekly')->name('report-weekly.')->group(function () {
        Route::get('/', [App\Http\Controllers\ReportWeeklyController::class, 'index'])->name('index');
        Route::get('/{id}', [App\Http\Controllers\ReportWeeklyController::class, 'show'])->name('show');
    });

    // Daily Report Routes - HARUS sebelum catch-all route
    Route::prefix('daily-report')->name('daily-report.')->group(function () {
        Route::get('/', [DailyReportController::class, 'index'])->name('index');
        Route::post('/generate', [DailyReportController::class, 'generate'])->name('generate');
    });

    // WMS Map Route - HARUS sebelum catch-all route
    Route::get('map-wms', [CctvDataController::class, 'mapWms'])->name('map-wms');

    // WMS Proxy Route - untuk mengatasi CORS
    Route::get('wms-proxy', [WmsProxyController::class, 'proxy'])->name('wms-proxy');

    // CCTV Proxy Route - untuk streaming CCTV
    Route::get('cctv-proxy/snapshot', [CctvProxyController::class, 'snapshot'])->name('cctv-proxy-snapshot');
    Route::get('cctv-proxy/rtsp', [CctvProxyController::class, 'rtspStream'])->name('cctv-proxy-rtsp');
    Route::get('cctv-proxy/rtsp-snapshot', [CctvProxyController::class, 'rtspSnapshot'])->name('cctv-proxy-rtsp-snapshot');
    Route::get('cctv-proxy/rtsp-hls', [CctvProxyController::class, 'rtspHls'])->name('cctv-proxy-rtsp-hls');
    
    // DMS Video Stream Proxy - untuk bypass CORS
    Route::get('dms-proxy/video-stream', [CctvProxyController::class, 'videoStream'])->name('dms-proxy-video-stream');

    // CCTV Data CRUD Routes - HARUS sebelum catch-all route
    // Routes khusus harus didefinisikan SEBELUM resource route
    Route::get('cctv-data-import', [CctvDataController::class, 'importForm'])->name('cctv-data.import-form');
    Route::post('cctv-data-import', [CctvDataController::class, 'import'])->name('cctv-data.import');
    Route::get('cctv-data-import/download-template', [CctvDataController::class, 'downloadTemplate'])->name('cctv-data.download-template');
    Route::get('cctv-data-coverage-import', [CctvDataController::class, 'importCoverageForm'])->name('cctv-data.import-coverage-form');
    Route::post('cctv-data-coverage-import', [CctvDataController::class, 'importCoverage'])->name('cctv-data.import-coverage');
    Route::get('cctv-data-coverage-import/data', [CctvDataController::class, 'getCoverageData'])->name('cctv-data.coverage.data');
    Route::get('cctv-data-coverage-import/export', [CctvDataController::class, 'exportCoverage'])->name('cctv-data.coverage.export');
    Route::get('cctv-data-coverage-import/download-template', [CctvDataController::class, 'downloadTemplateCoverage'])->name('cctv-data.download-template-coverage');
    Route::get('cctv-data-coverage-import/{id}', [CctvDataController::class, 'getCoverageDetail'])->name('cctv-data.coverage.detail');
    Route::put('cctv-data-coverage-import/{id}', [CctvDataController::class, 'updateCoverage'])->name('cctv-data.coverage.update');
    Route::delete('cctv-data-coverage-import/{id}', [CctvDataController::class, 'deleteCoverage'])->name('cctv-data.coverage.delete');
    Route::get('cctv-data-control-room', [CctvDataController::class, 'indexControlRoom'])->name('cctv-data.control-room.index');
    Route::get('cctv-data-control-room/data', [CctvDataController::class, 'getControlRoomData'])->name('cctv-data.control-room.data');
    Route::get('cctv-data-control-room/export', [CctvDataController::class, 'exportControlRoomPengawas'])->name('cctv-data.control-room.export');
    Route::post('cctv-data-control-room/pengawas', [CctvDataController::class, 'storePengawasControlRoom'])->name('cctv-data.control-room.pengawas.store');
    Route::get('cctv-data-control-room/pengawas/{controlRoom}', [CctvDataController::class, 'getPengawasControlRoom'])->name('cctv-data.control-room.pengawas.get');
    Route::get('cctv-data-control-room/users', [CctvDataController::class, 'getUsersFromClickHouse'])->name('cctv-data.control-room.users.get');
    Route::get('cctv-data-control-room/cctv', [CctvDataController::class, 'getCctvByControlRoom'])->name('cctv-data.control-room.cctv.get');
    Route::post('cctv-data-control-room/intervensi', [CctvDataController::class, 'storeIntervensiControlRoom'])->name('cctv-data.control-room.intervensi.store');
    Route::get('cctv-data-control-room/intervensi', [CctvDataController::class, 'indexIntervensiControlRoom'])->name('cctv-data.intervensi-control-room.index');
    Route::get('cctv-data-control-room/intervensi/export', [CctvDataController::class, 'exportIntervensiControlRoomExcel'])->name('cctv-data.intervensi-control-room.export');
    Route::get('cctv-data-control-room/intervensi/export-done', [CctvDataController::class, 'exportDoneIntervensiControlRoomExcel'])->name('cctv-data.intervensi-control-room.export-done');
    Route::get('cctv-data-control-room/intervensi/data', [CctvDataController::class, 'getIntervensiControlRoomData'])->name('cctv-data.intervensi-control-room.data');
    Route::get('cctv-data-control-room/intervensi/done/data', [CctvDataController::class, 'getDoneIntervensiControlRoomData'])->name('cctv-data.intervensi-control-room.done.data');
    Route::get('cctv-data-control-room/intervensi/{id}/detail', [CctvDataController::class, 'getIntervensiDetail'])->name('cctv-data.intervensi-control-room.detail');
    Route::get('cctv-data-control-room/intervensi/{id}/done/detail', [CctvDataController::class, 'getDoneIntervensiDetail'])->name('cctv-data.intervensi-control-room.done.detail');
    Route::put('cctv-data-control-room/intervensi/{id}/status', [CctvDataController::class, 'updateIntervensiStatus'])->name('cctv-data.intervensi-control-room.status.update');
    Route::put('cctv-data-control-room/intervensi/{id}/status-done', [CctvDataController::class, 'updateIntervensiStatusDone'])->name('cctv-data.intervensi-control-room.status-done.update');
    Route::delete('cctv-data-control-room/pengawas/{id}', [CctvDataController::class, 'deletePengawasControlRoom'])->name('cctv-data.control-room.pengawas.delete');
    // Intervensi Area Kerja Routes
    Route::get('intervensi-area-kerja', [\App\Http\Controllers\IntervensiAreaKerjaController::class, 'index'])->name('intervensi-area-kerja.index');
    Route::get('intervensi-area-kerja/data', [\App\Http\Controllers\IntervensiAreaKerjaController::class, 'getData'])->name('intervensi-area-kerja.data');
    Route::get('intervensi-area-kerja/done/data', [\App\Http\Controllers\IntervensiAreaKerjaController::class, 'getDoneData'])->name('intervensi-area-kerja.done.data');
    Route::get('intervensi-area-kerja/{id}/detail', [\App\Http\Controllers\IntervensiAreaKerjaController::class, 'getDetail'])->name('intervensi-area-kerja.detail');
    Route::get('intervensi-area-kerja/{id}/done/detail', [\App\Http\Controllers\IntervensiAreaKerjaController::class, 'getDoneDetail'])->name('intervensi-area-kerja.done.detail');
    Route::post('intervensi-area-kerja/{id}/status', [\App\Http\Controllers\IntervensiAreaKerjaController::class, 'updateStatus'])->name('intervensi-area-kerja.status.update');
    Route::get('supervisory-alert-log', [\App\Http\Controllers\SupervisoryAlertLogController::class, 'index'])->name('supervisory-alert-log.index');
    Route::get('supervisory-alert-log/export', [\App\Http\Controllers\SupervisoryAlertLogController::class, 'exportExcel'])->name('supervisory-alert-log.export');
    Route::get('supervisory-alert-log/data', [\App\Http\Controllers\SupervisoryAlertLogController::class, 'getData'])->name('supervisory-alert-log.data');
    Route::get('supervisory-alert-log/data-mobility', [\App\Http\Controllers\SupervisoryAlertLogController::class, 'getDataMobility'])->name('supervisory-alert-log.data-mobility');
    Route::get('supervisory-alert-log/data-critical-area', [\App\Http\Controllers\SupervisoryAlertLogController::class, 'getDataCriticalArea'])->name('supervisory-alert-log.data-critical-area');
    Route::get('supervisory-alert-log/{id}/detail', [\App\Http\Controllers\SupervisoryAlertLogController::class, 'getDetail'])->name('supervisory-alert-log.detail');
    Route::get('cctv-data-pja-cctv-import', [CctvDataController::class, 'importPjaCctvForm'])->name('cctv-data.import-pja-cctv-form');
    Route::post('cctv-data-pja-cctv-import', [CctvDataController::class, 'importPjaCctv'])->name('cctv-data.import-pja-cctv');
    Route::get('cctv-data-pja-cctv-dedicated-import', [CctvDataController::class, 'importPjaCctvDedicatedForm'])->name('cctv-data.import-pja-cctv-dedicated-form');
    Route::post('cctv-data-pja-cctv-dedicated-import', [CctvDataController::class, 'importPjaCctvDedicated'])->name('cctv-data.import-pja-cctv-dedicated');
    Route::get('cctv-data-pja-cctv-dedicated', [CctvDataController::class, 'indexPjaCctvDedicated'])->name('cctv-data.pja-cctv-dedicated.index');
    Route::get('cctv-data-pja-cctv-dedicated/data', [CctvDataController::class, 'getPjaCctvDedicatedData'])->name('cctv-data.pja-cctv-dedicated.data');
    Route::get('cctv-data-pja-cctv-dedicated/export', [CctvDataController::class, 'exportPjaCctvDedicated'])->name('cctv-data.pja-cctv-dedicated.export');
    Route::get('cctv-data-unmapped-cctv', [CctvDataController::class, 'indexUnmappedCctv'])->name('cctv-data.unmapped-cctv.index');
    Route::get('cctv-data-unmapped-cctv/data', [CctvDataController::class, 'getUnmappedCctvData'])->name('cctv-data.unmapped-cctv.data');
    Route::get('cctv-data-unmapped-cctv/export', [CctvDataController::class, 'exportUnmappedCctv'])->name('cctv-data.unmapped-cctv.export');
    Route::get('cctv-data-download-template-mapping-pja', [CctvDataController::class, 'downloadTemplateMappingPja'])->name('cctv-data.download-template-mapping-pja');
    Route::get('cctv-data-import-mapping-pja', [CctvDataController::class, 'importMappingPjaForm'])->name('cctv-data.import-mapping-pja-form');
    Route::post('cctv-data-import-mapping-pja', [CctvDataController::class, 'importMappingPja'])->name('cctv-data.import-mapping-pja');
    Route::post('cctv-data-unmapped-cctv/import-mapping', [CctvDataController::class, 'importMapping'])->name('cctv-data.unmapped-cctv.import-mapping');
    Route::get('cctv-data/data', [CctvDataController::class, 'getData'])->name('cctv-data.data');
    Route::get('cctv-data/{id}/scan', [CctvDataController::class, 'scan'])->name('cctv-data.scan');
    Route::get('cctv-data/{id}/qr-code', [CctvDataController::class, 'qrCodeImage'])->name('cctv-data.qr-code');
    Route::get('cctv-data/{id}/qr-code/download', [CctvDataController::class, 'downloadQrCode'])->name('cctv-data.qr-code.download');
    Route::get('cctv-data/{id}/details', [CctvDataController::class, 'getCctvDetails'])->name('cctv-data.details');
    Route::get('cctv-data/hazard-status', [CctvDataController::class, 'getCctvHazardStatus'])->name('cctv-data.hazard-status');
    Route::get('cctv-data/export', [CctvDataController::class, 'exportCctvData'])->name('cctv-data.export');
    // Resource routes dengan parameter eksplisit
    Route::get('cctv-data', [CctvDataController::class, 'index'])->name('cctv-data.index');
    Route::get('cctv-data/create', [CctvDataController::class, 'create'])->name('cctv-data.create');
    Route::post('cctv-data', [CctvDataController::class, 'store'])->name('cctv-data.store');
    Route::get('cctv-data/{id}', [CctvDataController::class, 'show'])->name('cctv-data.show');
    Route::get('cctv-data/{id}/edit', [CctvDataController::class, 'edit'])->name('cctv-data.edit');
    Route::put('cctv-data/{id}', [CctvDataController::class, 'update'])->name('cctv-data.update');
    Route::delete('cctv-data/{id}', [CctvDataController::class, 'destroy'])->name('cctv-data.destroy');

    // Hazard Motion Routes - HARUS sebelum catch-all route
    Route::prefix('hazard-motion')->name('hazard-motion.')->group(function () {
        Route::get('/', [PublicHazardMotionController::class, 'index'])->name('index');
    });

    // Hazard Detection Routes - HARUS sebelum catch-all route
    Route::prefix('hazard-detection')->name('hazard-detection.')->group(function () {
        Route::get('/', [HazardDetectionController::class, 'index'])->name('index');
        Route::get('/fullscreen-map', [HazardDetectionController::class, 'fullscreenMap'])->name('fullscreen-map');
        Route::get('/api/detections', [HazardDetectionController::class, 'getDetections'])->name('api.detections');
        Route::get('/api/cctv', [HazardDetectionController::class, 'getCctvByName'])->name('api.cctv');
        Route::get('/api/incidents-by-cctv', [HazardDetectionController::class, 'getIncidentsByCctv'])->name('api.incidents-by-cctv');
        Route::get('/api/pja-by-cctv', [HazardDetectionController::class, 'getPjaByCctv'])->name('api.pja-by-cctv');
        Route::get('/api/photos', [HazardDetectionController::class, 'getPhotosFromPhotoCar'])->name('api.photos');
        Route::get('/api/company-stats', [HazardDetectionController::class, 'getCompanyStats'])->name('api.company-stats');
        Route::get('/api/company-cctv-data', [HazardDetectionController::class, 'getCompanyCctvData'])->name('api.company-cctv-data');
        Route::get('/api/company-overview', [HazardDetectionController::class, 'getCompanyOverview'])->name('api.company-overview');
        Route::get('/api/cctv-chart-stats', [HazardDetectionController::class, 'getCctvChartStats'])->name('api.cctv-chart-stats');
        Route::get('/api/control-room-overview', [MapBaseController::class, 'getControlRoomOverview'])->name('api.control-room-overview');
        Route::get('/api/sites-list', [HazardDetectionController::class, 'getSitesList'])->name('api.sites-list');
        Route::get('/api/check-new-apd-detections', [HazardDetectionController::class, 'checkNewApdDetections'])->name('api.check-new-apd-detections');
        Route::get('/api/tasklist-detail', [HazardDetectionController::class, 'getTasklistDetail'])->name('api.tasklist-detail');
        Route::get('/api/total-cctv-count', [HazardDetectionController::class, 'getTotalCctvCount'])->name('api.total-cctv-count');
        Route::get('/api/tbc-overview', [HazardDetectionController::class, 'getTbcOverview'])->name('api.tbc-overview');
        Route::get('/api/unit-vehicles', [HazardDetectionController::class, 'getUnitVehicles'])->name('api.unit-vehicles');
        
        // P2H Checklist Routes
        Route::get('/p2h/{controlRoom}', [CctvP2hController::class, 'create'])->name('p2h.create');
        Route::post('/p2h', [CctvP2hController::class, 'store'])->name('p2h.store');
        Route::get('/p2h/{controlRoom}/history', [CctvP2hController::class, 'history'])->name('p2h.history');
        Route::get('/api/p2h/status', [CctvP2hController::class, 'getStatus'])->name('api.p2h.status');
        Route::get('/p2h-evaluation', [CctvP2hController::class, 'evaluation'])->name('p2h.evaluation');
        Route::get('/api/unit-gps-logs', [HazardDetectionController::class, 'getUnitGpsLogs'])->name('api.unit-gps-logs');
    });


    // Maps Full
    Route::prefix('maps')->name('maps.')->group(function(){
         Route::get('/', [MapBaseController::class, 'index'])->name('map');
         Route::get('/api/filtered-data', [MapBaseController::class, 'getFilteredMapData'])->name('api.filtered-data');
         Route::get('/api/user-gps', [MapBaseController::class, 'getUserGps'])->name('api.user-gps');
         Route::get('/api/employee-location', [MapBaseController::class, 'getEmployeeLocation'])->name('api.employee-location');
         Route::get('/api/work-areas', [MapBaseController::class, 'getWorkAreas'])->name('api.work-areas');
         Route::post('/api/check-work-area', [MapBaseController::class, 'checkGpsInWorkArea'])->name('api.check-work-area');
         Route::get('/api/gps-user-location-details', [MapBaseController::class, 'getGpsUserLocationDetails'])->name('api.gps-user-location-details');
         Route::get('/api/user-gps-history', [MapBaseController::class, 'getUserGpsHistory'])->name('api.user-gps-history');
         Route::get('/api/unit-vehicles', [MapBaseController::class, 'getUnitVehicles'])->name('api.unit-vehicles');
         Route::get('/api/pja-data', [MapBaseController::class, 'getPjaData'])->name('api.pja-data');
         Route::get('/api/kesiapan-orang-data', [MapBaseController::class, 'getKesiapanOrangData'])->name('api.kesiapan-orang-data');
         Route::post('/api/intervensi-kesiapan-orang', [MapBaseController::class, 'storeIntervensiKesiapanOrang'])->name('api.intervensi-kesiapan-orang');
         Route::get('/api/area-kerja-data', [MapBaseController::class, 'getAreaKerjaData'])->name('api.area-kerja-data');
         Route::get('/api/area-kerja-sidebar-data', [MapBaseController::class, 'getAreaKerjaSidebarData'])->name('api.area-kerja-sidebar-data');
         Route::get('/api/auto-alert-sidebar-data', [MapBaseController::class, 'getAutoAlertSidebarData'])->name('api.auto-alert-sidebar-data');
         Route::post('/api/evaluation-summary', [MapBaseController::class, 'getEvaluationSummary'])->name('api.evaluation-summary');
         Route::post('/api/send-telegram', [MapBaseController::class, 'sendTelegramNotification'])->name('api.send-telegram');
    });

    // Real-time Alerts Routes - HARUS sebelum catch-all route
    Route::prefix('realtime-alerts')->name('realtime-alerts.')->group(function () {
        Route::get('/', [RealtimeAlertController::class, 'index'])->name('index');
        Route::get('/history', [RealtimeAlertController::class, 'history'])->name('history');
        Route::get('/settings', [RealtimeAlertController::class, 'settings'])->name('settings');
        Route::post('/settings', [RealtimeAlertController::class, 'saveSettings'])->name('settings.save');
        Route::get('/api/alerts', [RealtimeAlertController::class, 'getAlerts'])->name('api.alerts');
        Route::post('/acknowledge/{alertId}', [RealtimeAlertController::class, 'acknowledge'])->name('acknowledge');
    });

    // Geofencing Routes - HARUS sebelum catch-all route
    Route::prefix('geofencing')->name('geofencing.')->group(function () {
        Route::get('/', [GeofencingController::class, 'index'])->name('index');
        Route::get('/rules', [GeofencingController::class, 'rules'])->name('rules');
        Route::get('/monitoring', [GeofencingController::class, 'monitoring'])->name('monitoring');
        Route::get('/api/zones', [GeofencingController::class, 'getZones'])->name('api.zones');
        Route::post('/zones', [GeofencingController::class, 'saveZone'])->name('zones.save');
        Route::delete('/zones/{zoneId}', [GeofencingController::class, 'deleteZone'])->name('zones.delete');
        
        // WMS Link Routes
        Route::post('/wms', [GeofencingController::class, 'storeWmsLink'])->name('wms.store');
        Route::get('/wms/{id}', [GeofencingController::class, 'getWmsLink'])->name('wms.get');
        Route::put('/wms/{id}', [GeofencingController::class, 'updateWmsLink'])->name('wms.update');
        Route::delete('/wms/{id}', [GeofencingController::class, 'deleteWmsLink'])->name('wms.delete');
        
        // GeoJSON Area Routes
        Route::post('/geojson', [GeofencingController::class, 'storeGeojsonArea'])->name('geojson.store');
        Route::get('/geojson/{id}', [GeofencingController::class, 'getGeojsonArea'])->name('geojson.get');
        Route::put('/geojson/{id}', [GeofencingController::class, 'updateGeojsonArea'])->name('geojson.update');
        Route::delete('/geojson/{id}', [GeofencingController::class, 'deleteGeojsonArea'])->name('geojson.delete');
    });

    // Spatial Analysis Routes - HARUS sebelum catch-all route
    Route::prefix('spatial-analysis')->name('spatial-analysis.')->group(function () {
        Route::get('/heatmap', [SpatialAnalysisController::class, 'heatMap'])->name('heatmap');
        Route::get('/zone', [SpatialAnalysisController::class, 'zoneAnalysis'])->name('zone');
        Route::get('/movement', [SpatialAnalysisController::class, 'movementPatterns'])->name('movement');
        Route::get('/risk', [SpatialAnalysisController::class, 'riskAssessment'])->name('risk');
        Route::get('/api/heatmap', [SpatialAnalysisController::class, 'getHeatMapData'])->name('api.heatmap');
    });

    // Reporting & Analytics Routes - HARUS sebelum catch-all route
    Route::prefix('reporting')->name('reporting.')->group(function () {
        Route::get('/dashboard', [ReportingController::class, 'dashboard'])->name('dashboard');
        Route::get('/operational', [ReportingController::class, 'operational'])->name('operational');
        Route::get('/safety', [ReportingController::class, 'safety'])->name('safety');
        Route::get('/custom', [ReportingController::class, 'custom'])->name('custom');
        Route::post('/generate', [ReportingController::class, 'generate'])->name('generate');
        Route::get('/download/{reportId}', [ReportingController::class, 'download'])->name('download');
    });

    // CCTV Evaluation Routes - HARUS sebelum catch-all route
    Route::prefix('cctv-evaluation')->name('cctv-evaluation.')->group(function () {
        Route::get('/', [CctvEvaluationController::class, 'index'])->name('index');
    });

    // CCTV Management Routes - HARUS sebelum catch-all route
    Route::prefix('cctv-management')->name('cctv-management.')->group(function () {
        Route::get('/status', [CctvManagementController::class, 'status'])->name('status');
    });

    // Live Streaming Routes - HARUS sebelum catch-all route
    Route::prefix('live-streaming')->name('live-streaming.')->group(function () {
        Route::get('/active', [LiveStreamingController::class, 'activeStreams'])->name('active');
        Route::get('/archive', [LiveStreamingController::class, 'streamArchive'])->name('archive');
        Route::get('/api/active', [LiveStreamingController::class, 'getActiveStreams'])->name('api.active');
        Route::post('/start', [LiveStreamingController::class, 'startStream'])->name('start');
        Route::post('/stop/{streamId}', [LiveStreamingController::class, 'stopStream'])->name('stop');
    });

    // Car Register Routes - HARUS sebelum catch-all route
    Route::prefix('car-register')->name('car-register.')->group(function () {
        Route::get('/', [CarRegisterController::class, 'index'])->name('index');
    });

    // GR Table Routes - HARUS sebelum catch-all route
    Route::prefix('gr-table')->name('gr-table.')->group(function () {
        Route::get('/', [GrTableController::class, 'index'])->name('index');
        Route::post('/', [GrTableController::class, 'store'])->name('store');
        Route::post('/import', [GrTableController::class, 'import'])->name('import');
    });

    // Daily Operation Plan (DOP) Routes - HARUS sebelum catch-all route
    Route::prefix('daily-operation-plan')->name('daily-operation-plan.')->group(function () {
        Route::get('/', [DailyOperationPlanController::class, 'index'])->name('index');
        Route::get('/create', [DailyOperationPlanController::class, 'create'])->name('create');
        Route::post('/', [DailyOperationPlanController::class, 'store'])->name('store');
        Route::get('/template', [DailyOperationPlanController::class, 'downloadTemplate'])->name('template');
        Route::get('/export', [DailyOperationPlanController::class, 'exportExcel'])->name('export');
        Route::post('/import', [DailyOperationPlanController::class, 'import'])->name('import');
        Route::patch('/{id}/toggle-status', [DailyOperationPlanController::class, 'toggleStatus'])->name('toggle-status');
        Route::get('/{id}', [DailyOperationPlanController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [DailyOperationPlanController::class, 'edit'])->name('edit');
        Route::put('/{id}', [DailyOperationPlanController::class, 'update'])->name('update');
        Route::delete('/{id}', [DailyOperationPlanController::class, 'destroy'])->name('destroy');
    });

    // CCTV P2H Checklist Routes
    Route::prefix('cctv-p2h-checklist')->name('cctv-p2h-checklist.')->group(function () {
        Route::get('/', [CctvP2hChecklistController::class, 'index'])->name('index');
        Route::get('/export', [CctvP2hChecklistController::class, 'exportExcel'])->name('export');
        Route::get('/{id}', [CctvP2hChecklistController::class, 'show'])->name('show');
        Route::delete('/{id}', [CctvP2hChecklistController::class, 'destroy'])->name('destroy');
    });

    // Insiden Tabel Routes - HARUS sebelum catch-all route
    Route::prefix('insiden-tabel')->name('insiden-tabel.')->group(function () {
        Route::get('/', [InsidenTabelController::class, 'index'])->name('index');
        Route::get('/data', [InsidenTabelController::class, 'data'])->name('data');
        Route::get('/template', [InsidenTabelController::class, 'downloadTemplate'])->name('template');
        Route::post('/update-group-meta', [InsidenTabelController::class, 'updateGroupMeta'])->name('update-group-meta');
        Route::get('/create', [InsidenTabelController::class, 'create'])->name('create');
        Route::post('/', [InsidenTabelController::class, 'store'])->name('store');
        Route::get('/{insidenTabel}/edit', [InsidenTabelController::class, 'edit'])->name('edit');
        Route::put('/{insidenTabel}', [InsidenTabelController::class, 'update'])->name('update');
        Route::delete('/{insidenTabel}', [InsidenTabelController::class, 'destroy'])->name('destroy');
        Route::post('/destroy-group', [InsidenTabelController::class, 'destroyGroup'])->name('destroy-group');
        Route::post('/import', [InsidenTabelController::class, 'import'])->name('import');
    });

    // Insiden CCR Routes - HARUS sebelum catch-all route
    Route::prefix('insiden-ccr')->name('insiden-ccr.')->group(function () {
        Route::get('/', [InsidenCcrController::class, 'index'])->name('index');
        Route::get('/create', [InsidenCcrController::class, 'create'])->name('create');
        Route::post('/', [InsidenCcrController::class, 'store'])->name('store');
        Route::get('/template', [InsidenCcrController::class, 'downloadTemplate'])->name('template');
        Route::get('/{insidenCcr}/edit', [InsidenCcrController::class, 'edit'])->name('edit');
        Route::put('/{insidenCcr}', [InsidenCcrController::class, 'update'])->name('update');
        Route::delete('/{insidenCcr}', [InsidenCcrController::class, 'destroy'])->name('destroy');
        Route::post('/import', [InsidenCcrController::class, 'import'])->name('import');
    });

    // Insiden LPI Routes - HARUS sebelum catch-all route
    Route::prefix('insiden-lpi')->name('insiden-lpi.')->group(function () {
        Route::get('/', [InsidenLpiController::class, 'index'])->name('index');
        Route::get('/create', [InsidenLpiController::class, 'create'])->name('create');
        Route::post('/', [InsidenLpiController::class, 'store'])->name('store');
        Route::get('/template', [InsidenLpiController::class, 'downloadTemplate'])->name('template');
        Route::get('/ccr-data/{insidenCcr}', [InsidenLpiController::class, 'getCcrData'])->name('ccr-data');
        Route::get('/{insidenLpi}/edit', [InsidenLpiController::class, 'edit'])->name('edit');
        Route::put('/{insidenLpi}', [InsidenLpiController::class, 'update'])->name('update');
        Route::delete('/{insidenLpi}', [InsidenLpiController::class, 'destroy'])->name('destroy');
        Route::post('/import', [InsidenLpiController::class, 'import'])->name('import');
    });

    // DOPM$IKK Routes - DOPM, IPK-IKK, OKK
    Route::prefix('dopmikk')->name('dopmikk.')->group(function () {
        Route::get('api/ikk-modal-data', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'getDetailModalData'])->name('api.ikk-modal-data');
        Route::get('api/ikk-context-alert-log', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'getIkkContextForAlertLog'])->name('api.ikk-context-alert-log');
        Route::post('api/alert-log-intervensi', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'storeAlertLogIntervensi'])->name('api.alert-log-intervensi');
        Route::post('api/update-intervensi-pic', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'updateIntervensiPic'])->name('api.update-intervensi-pic');
        Route::get('api/layer1-users', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'getLayer1Users'])->name('api.layer1-users');
        Route::get('api/layers234-users', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'getLayers234Users'])->name('api.layers234-users');
        Route::get('api/search-users', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'searchUsers'])->name('api.search-users');
        Route::post('api/assign-pic', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'assignPic'])->name('api.assign-pic');
        Route::post('api/close-issue', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'closeIssue'])->name('api.close-issue');
        Route::get('api/get-closure', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'getClosure'])->name('api.get-closure');
        // DOPM
        Route::prefix('dopm')->name('dopm.')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'dashboard'])->name('dashboard');
            // Dashboard Weekly: week di path (RESTful). Query opsional: site, date (hari dalam minggu untuk detail harian).
            Route::get('/dashboard-weekly/export-ikk-excel', function (Request $request) {
                $week = $request->query('week', now()->format('o-\WW'));
                if (! preg_match('/^\d{4}-W\d{2}$/', (string) $week)) {
                    $week = now()->format('o-\WW');
                }
                $to = route('dopmikk.dopm.dashboard-weekly.export-ikk-excel', ['week' => $week]);
                if ($request->filled('site')) {
                    $to .= '?'.http_build_query(['site' => $request->query('site')]);
                }

                return redirect()->to($to, 301);
            });
            Route::get('/dashboard-weekly/{week}/export-ikk-excel', [\App\Http\Controllers\DOPMIKK\DOPMWeeklyController::class, 'exportIkkExcel'])
                ->where('week', '^\d{4}-W\d{2}$')
                ->name('dashboard-weekly.export-ikk-excel');
            Route::get('/dashboard-weekly/{week}/export-joined-oak-excel', [\App\Http\Controllers\DOPMIKK\DOPMWeeklyController::class, 'exportWeeklyJoinedWithOakExcel'])
                ->where('week', '^\d{4}-W\d{2}$')
                ->name('dashboard-weekly.export-joined-oak-excel');
            Route::get('/dashboard-weekly/export-joined-oak-excel-all', [\App\Http\Controllers\DOPMIKK\DOPMWeeklyController::class, 'exportAllJoinedWithOakExcel'])
                ->name('dashboard-weekly.export-joined-oak-excel-all');
            Route::get('/dashboard-weekly/api/compliance-by-month', [\App\Http\Controllers\DOPMIKK\DOPMWeeklyController::class, 'getComplianceByMonth'])->name('dashboard-weekly.api.compliance-by-month');
            Route::get('/dashboard-weekly/api/ikk-daily-details', [\App\Http\Controllers\DOPMIKK\DOPMWeeklyController::class, 'getIkkDailyDetails'])->name('dashboard-weekly.api.ikk-daily-details');
            Route::post('/dashboard-weekly/import-excel', [\App\Http\Controllers\DOPMIKK\DOPMWeeklyController::class, 'importWeeklyExcel'])->name('dashboard-weekly.import-excel');
            Route::get('/dashboard-weekly/{week}', [\App\Http\Controllers\DOPMIKK\DOPMWeeklyController::class, 'dashboard'])
                ->where('week', '^\d{4}-W\d{2}$')
                ->name('dashboard-weekly');
            Route::get('/dashboard-weekly', function (Request $request) {
                $week = $request->query('week', now()->format('o-\WW'));
                if (! preg_match('/^\d{4}-W\d{2}$/', (string) $week)) {
                    $week = now()->format('o-\WW');
                }
                $to = route('dopmikk.dopm.dashboard-weekly', ['week' => $week]);
                $query = [];
                if ($request->filled('site')) {
                    $query['site'] = $request->query('site');
                }
                if ($request->filled('date')) {
                    $d = $request->query('date');
                    if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                        $query['date'] = $d;
                    }
                }
                if ($query !== []) {
                    $to .= '?'.http_build_query($query);
                }

                return redirect()->to($to, 301);
            })->name('dashboard-weekly.home');
            Route::get('/alert-log', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'alertLog'])->name('alert-log');
            Route::get('/issue-closure', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'issueClosure'])->name('issue-closure');
            Route::get('/', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'create'])->name('create');
            Route::get('/download-template', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'downloadTemplate'])->name('download-template');
            Route::post('/', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'store'])->name('store');
            Route::post('/import', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'import'])->name('import');
            Route::get('/{dopm}/edit', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'edit'])->name('edit');
            Route::put('/{dopm}', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'update'])->name('update');
            Route::delete('/{dopm}', [\App\Http\Controllers\DOPMIKK\DOPMController::class, 'destroy'])->name('destroy');
        });
        // IPK-IKK
        Route::prefix('ipk-ikk')->name('ipk-ikk.')->group(function () {
            Route::get('/', [\App\Http\Controllers\DOPMIKK\IPKIKKController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\DOPMIKK\IPKIKKController::class, 'create'])->name('create');
            Route::get('/download-template', [\App\Http\Controllers\DOPMIKK\IPKIKKController::class, 'downloadTemplate'])->name('download-template');
            Route::post('/', [\App\Http\Controllers\DOPMIKK\IPKIKKController::class, 'store'])->name('store');
            Route::post('/import', [\App\Http\Controllers\DOPMIKK\IPKIKKController::class, 'import'])->name('import');
            Route::get('/{ipkIkk}/edit', [\App\Http\Controllers\DOPMIKK\IPKIKKController::class, 'edit'])->name('edit');
            Route::put('/{ipkIkk}', [\App\Http\Controllers\DOPMIKK\IPKIKKController::class, 'update'])->name('update');
            Route::delete('/{ipkIkk}', [\App\Http\Controllers\DOPMIKK\IPKIKKController::class, 'destroy'])->name('destroy');
        });
        // OKK
        Route::prefix('okk')->name('okk.')->group(function () {
            Route::get('/', [\App\Http\Controllers\DOPMIKK\OKKController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\DOPMIKK\OKKController::class, 'create'])->name('create');
            Route::get('/download-template', [\App\Http\Controllers\DOPMIKK\OKKController::class, 'downloadTemplate'])->name('download-template');
            Route::post('/', [\App\Http\Controllers\DOPMIKK\OKKController::class, 'store'])->name('store');
            Route::post('/import', [\App\Http\Controllers\DOPMIKK\OKKController::class, 'import'])->name('import');
            Route::get('/{okk}/edit', [\App\Http\Controllers\DOPMIKK\OKKController::class, 'edit'])->name('edit');
            Route::put('/{okk}', [\App\Http\Controllers\DOPMIKK\OKKController::class, 'update'])->name('update');
            Route::delete('/{okk}', [\App\Http\Controllers\DOPMIKK\OKKController::class, 'destroy'])->name('destroy');
        });
    });

    // Hazard Validation Routes - HARUS sebelum catch-all route
    Route::prefix('hazard-validation')->name('hazard-validation.')->group(function () {
        Route::get('/', [HazardValidationController::class, 'index'])->name('index');
        Route::post('/', [HazardValidationController::class, 'store'])->name('store');
        Route::get('/{hazardValidation}/edit', [HazardValidationController::class, 'edit'])->name('edit');
        Route::put('/{hazardValidation}', [HazardValidationController::class, 'update'])->name('update');
        Route::delete('/{hazardValidation}', [HazardValidationController::class, 'destroy'])->name('destroy');
        Route::post('/import', [HazardValidationController::class, 'import'])->name('import');
    });

    // Baseline PJA Routes - HARUS sebelum catch-all route
    Route::prefix('baseline-pja')->name('baseline-pja.')->group(function () {
        Route::get('/', [BaselinePjaController::class, 'index'])->name('index');
        Route::post('/', [BaselinePjaController::class, 'store'])->name('store');
        Route::get('/{baselinePja}/edit', [BaselinePjaController::class, 'edit'])->name('edit');
        Route::put('/{baselinePja}', [BaselinePjaController::class, 'update'])->name('update');
        Route::delete('/{baselinePja}', [BaselinePjaController::class, 'destroy'])->name('destroy');
        Route::post('/import', [BaselinePjaController::class, 'import'])->name('import');
    });

    // DMS (Driver Monitoring System) Routes - HARUS sebelum catch-all route
    Route::prefix('dms')->name('dms.')->group(function () {
        Route::get('/', function () {
            return view('dms.index');
        })->name('index');
        Route::get('/detection', [\App\Http\Controllers\DMS\DetectionController::class, 'index'])->name('detection');
        Route::get('/dashboard', [\App\Http\Controllers\DMS\DMSDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard-static', function () {
            return view('dms.dashboard-static');
        })->name('dashboard-static');
    });

    // Route modul VALIDASI TBC & Score Card
    require __DIR__ . '/validasi_tbc.php';
    require __DIR__ . '/scorecard.php';

    // Route modul Pembatasan LV
    require __DIR__ . '/PembatasanLV/pembatasan_lv.php';

    // Route modul Auto Banned
    require __DIR__ . '/AutoBanned/auto_banned.php';

    // Role & Permission Management Routes
    Route::prefix('role-permission')->name('role-permission.')->group(function () {
        Route::get('/', [RolePermissionController::class, 'index'])->name('index');
        
        // Role routes
        Route::post('/role', [RolePermissionController::class, 'storeRole'])->name('role.store');
        Route::put('/role/{id}', [RolePermissionController::class, 'updateRole'])->name('role.update');
        Route::delete('/role/{id}', [RolePermissionController::class, 'deleteRole'])->name('role.delete');
        Route::get('/role/{id}', [RolePermissionController::class, 'getRole'])->name('role.get');
        Route::post('/role/{id}/permissions', [RolePermissionController::class, 'assignPermissionsToRole'])->name('role.permissions.assign');
        
        // Permission routes
        Route::get('/permissions', [RolePermissionController::class, 'getPermissions'])->name('permissions.list');
        Route::post('/permission', [RolePermissionController::class, 'storePermission'])->name('permission.store');
        Route::put('/permission/{id}', [RolePermissionController::class, 'updatePermission'])->name('permission.update');
        Route::delete('/permission/{id}', [RolePermissionController::class, 'deletePermission'])->name('permission.delete');
        Route::get('/permission/{id}', [RolePermissionController::class, 'getPermission'])->name('permission.get');
        
        // User routes
        Route::get('/roles', [RolePermissionController::class, 'getRoles'])->name('roles.list');
        Route::get('/user/{id}', [RolePermissionController::class, 'getUser'])->name('user.get');
        Route::post('/user/{id}/roles', [RolePermissionController::class, 'assignRolesToUser'])->name('user.roles.assign');
    });

    // Master User Management (buat akun manual + import Excel)
    Route::prefix('user-management')->name('user-management.')->group(function () {
        Route::get('/', [App\Http\Controllers\UserManagementController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\UserManagementController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\UserManagementController::class, 'store'])->name('store');
        Route::get('/import', [App\Http\Controllers\UserManagementController::class, 'importForm'])->name('import-form');
        Route::post('/import', [App\Http\Controllers\UserManagementController::class, 'import'])->name('import');
        Route::get('/template', [App\Http\Controllers\UserManagementController::class, 'downloadTemplate'])->name('download-template');
        Route::get('/{id}/edit', [App\Http\Controllers\UserManagementController::class, 'edit'])->name('edit');
        Route::put('/{id}', [App\Http\Controllers\UserManagementController::class, 'update'])->name('update');
        Route::delete('/{id}', [App\Http\Controllers\UserManagementController::class, 'destroy'])->name('destroy');
    });

    // Sistem Roster Routes
    Route::prefix('sistem-roster')->name('sistem-roster.')->group(function () {
        // Dashboard
        Route::get('/dashboard', [\App\Http\Controllers\SistemRoster\DashboardController::class, 'index'])->name('dashboard.index');
        Route::get('/dashboard/coverage-all', [\App\Http\Controllers\SistemRoster\DashboardController::class, 'coverageAll'])->name('dashboard.coverage-all');
        Route::get('/dashboard/coverage-dop', [\App\Http\Controllers\SistemRoster\DashboardController::class, 'coverageDop'])->name('dashboard.coverage-dop');
        Route::get('/dashboard/coverage-ikk', [\App\Http\Controllers\SistemRoster\DashboardController::class, 'coverageIkk'])->name('dashboard.coverage-ikk');
        Route::get('/dashboard/sap-detail', [\App\Http\Controllers\SistemRoster\DashboardController::class, 'sapDetail'])->name('dashboard.sap-detail');
        Route::get('/dashboard/oak-detail', [\App\Http\Controllers\SistemRoster\DashboardController::class, 'oakDetail'])->name('dashboard.oak-detail');
        Route::get('/dashboard/observasi-detail', [\App\Http\Controllers\SistemRoster\DashboardController::class, 'observasiDetail'])->name('dashboard.observasi-detail');
        Route::get('/dashboard/heatmap-day-detail', [\App\Http\Controllers\SistemRoster\DashboardController::class, 'heatmapDayDetail'])->name('dashboard.heatmap-day-detail');

        // DOP Routes
        Route::prefix('dop')->name('dop.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SistemRoster\DOPController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\SistemRoster\DOPController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\SistemRoster\DOPController::class, 'store'])->name('store');
            Route::get('/template', [\App\Http\Controllers\SistemRoster\DOPController::class, 'downloadTemplate'])->name('template');
            Route::post('/import', [\App\Http\Controllers\SistemRoster\DOPController::class, 'import'])->name('import');
            Route::patch('/{id}/toggle-status', [\App\Http\Controllers\SistemRoster\DOPController::class, 'toggleStatus'])->name('toggle-status');
            Route::get('/{id}', [\App\Http\Controllers\SistemRoster\DOPController::class, 'show'])->name('show');
            Route::get('/{id}/edit', [\App\Http\Controllers\SistemRoster\DOPController::class, 'edit'])->name('edit');
            Route::put('/{id}', [\App\Http\Controllers\SistemRoster\DOPController::class, 'update'])->name('update');
            Route::delete('/{id}', [\App\Http\Controllers\SistemRoster\DOPController::class, 'destroy'])->name('destroy');
        });

        // IKK Routes (ClickHouse)
        Route::prefix('ikk')->name('ikk.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SistemRoster\IKKController::class, 'index'])->name('index');
            Route::get('/{id}', [\App\Http\Controllers\SistemRoster\IKKController::class, 'show'])->name('show');
        });

        // Master Aktivitas Routes
        Route::prefix('master-aktivitas')->name('master-aktivitas.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SistemRoster\MasterAktivitasController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\SistemRoster\MasterAktivitasController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\SistemRoster\MasterAktivitasController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [\App\Http\Controllers\SistemRoster\MasterAktivitasController::class, 'edit'])->name('edit');
            Route::put('/{id}', [\App\Http\Controllers\SistemRoster\MasterAktivitasController::class, 'update'])->name('update');
            Route::delete('/{id}', [\App\Http\Controllers\SistemRoster\MasterAktivitasController::class, 'destroy'])->name('destroy');
        });

        // Lokasi Non Kritis Routes
        Route::prefix('lokasi-non-kritis')->name('lokasi-non-kritis.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SistemRoster\LokasiNonKritisController::class, 'index'])->name('index');
            Route::get('/data', [\App\Http\Controllers\SistemRoster\LokasiNonKritisController::class, 'data'])->name('data');
            Route::post('/generate', [\App\Http\Controllers\SistemRoster\LokasiNonKritisController::class, 'generate'])->name('generate');
        });

        // Tasklist Routes (Intelligence Hub)
        Route::prefix('tasklist')->name('tasklist.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SistemRoster\TasklistController::class, 'index'])->name('index');
        });

        // Master Roster (mapping roster per tabel/site, tampilan mingguan)
        Route::get('/master-roster', [\App\Http\Controllers\SistemRoster\MasterRosterController::class, 'index'])->name('master-roster.index');

        // Planning Routes
        Route::prefix('planning')->name('planning.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'index'])->name('index');
            Route::post('/generate', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'generate'])->name('generate');
            Route::post('/save-roster', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'saveRosterToPlanning'])->name('save-roster');
            Route::post('/exclude-roster-location', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'excludeRosterLocation'])->name('exclude-roster-location');
            Route::post('/reset-roster-exclusions', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'resetRosterExclusions'])->name('reset-roster-exclusions');
            Route::get('/job-status', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'jobStatus'])->name('job-status');
            Route::get('/users', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'getUsers'])->name('users');
            Route::get('/ikk-list', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'getIkkListForPlanning'])->name('ikk-list');
            Route::post('/store-ikk-manual', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'storeIkkManual'])->name('store-ikk-manual');
            Route::get('/{id}/karyawans', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'getKaryawans'])->name('karyawans');
            Route::get('/{id}/wa-message', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'waMessageContent'])->name('wa-message');
            Route::post('/{id}/assign-karyawan', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'assignKaryawan'])->name('assign-karyawan');
            Route::put('/{id}', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'update'])->name('update');
            Route::delete('/{id}', [\App\Http\Controllers\SistemRoster\PlanningController::class, 'destroy'])->name('destroy');
        });
    });



    require __DIR__ . '/HiraImprovement/hira.php';
    require __DIR__ . '/FatigueManagement/fatigue-management.php';
    require __DIR__ . '/DopSafety/dop-safety.php';
    require __DIR__ . '/MonitoringSafetyEngineering/monitoring-safety-engineering.php';
    require __DIR__ . '/Hsecm/hsecm.php';
    require __DIR__ . '/EvaluasiWell/evaluasi-well.php';


    // Define a GET route with dynamic placeholders for route parameters
    // HARUS di akhir agar tidak menangkap route spesifik di atas
    Route::get('{routeName}/{name?}', [HomeController::class, 'pageView'])
        ->where('routeName', '^(?!sid-meeting$|attendance$).+');
});
