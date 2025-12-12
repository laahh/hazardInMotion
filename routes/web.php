<?php

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


Auth::routes();


// Define a group of routes with 'auth' middleware applied
Route::middleware(['auth'])->group(function () {
    // Define a GET route for the root URL ('/')
    Route::get('/', [HomeController::class, 'index'])->name('index');
    Route::get('/clickhouse-status', [HomeController::class, 'checkClickHouseStatus'])->name('clickhouse.status');
    Route::get('/cctv-company-data', [HomeController::class, 'companyCctvData'])->name('cctv.company-data');
    Route::get('/company-cctv-data', [HomeController::class, 'getCompanyCctvData'])->name('company-cctv-data');
    Route::get('/company-stats', [HomeController::class, 'getCompanyStats'])->name('company-stats');

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

    // CCTV Data CRUD Routes - HARUS sebelum catch-all route
    // Routes khusus harus didefinisikan SEBELUM resource route
    Route::get('cctv-data-import', [CctvDataController::class, 'importForm'])->name('cctv-data.import-form');
    Route::post('cctv-data-import', [CctvDataController::class, 'import'])->name('cctv-data.import');
    Route::get('cctv-data/data', [CctvDataController::class, 'getData'])->name('cctv-data.data');
    Route::get('cctv-data/{id}/scan', [CctvDataController::class, 'scan'])->name('cctv-data.scan');
    Route::get('cctv-data/{id}/qr-code', [CctvDataController::class, 'qrCodeImage'])->name('cctv-data.qr-code');
    Route::get('cctv-data/{id}/qr-code/download', [CctvDataController::class, 'downloadQrCode'])->name('cctv-data.qr-code.download');
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
        Route::get('/api/sites-list', [HazardDetectionController::class, 'getSitesList'])->name('api.sites-list');
        Route::get('/api/check-new-apd-detections', [HazardDetectionController::class, 'checkNewApdDetections'])->name('api.check-new-apd-detections');
        Route::get('/api/tasklist-detail', [HazardDetectionController::class, 'getTasklistDetail'])->name('api.tasklist-detail');
        Route::get('/api/total-cctv-count', [HazardDetectionController::class, 'getTotalCctvCount'])->name('api.total-cctv-count');
        Route::get('/api/tbc-overview', [HazardDetectionController::class, 'getTbcOverview'])->name('api.tbc-overview');
        Route::get('/api/unit-vehicles', [HazardDetectionController::class, 'getUnitVehicles'])->name('api.unit-vehicles');
        Route::get('/api/unit-gps-logs', [HazardDetectionController::class, 'getUnitGpsLogs'])->name('api.unit-gps-logs');
    });


    // Maps Full
    Route::prefix('maps')->name('maps.')->group(function(){
         Route::get('/', [MapBaseController::class, 'index'])->name('map');
         Route::get('/api/filtered-data', [MapBaseController::class, 'getFilteredMapData'])->name('api.filtered-data');
         Route::get('/api/user-gps', [MapBaseController::class, 'getUserGps'])->name('api.user-gps');
         Route::get('/api/unit-vehicles', [MapBaseController::class, 'getUnitVehicles'])->name('api.unit-vehicles');
         Route::post('/api/evaluation-summary', [MapBaseController::class, 'getEvaluationSummary'])->name('api.evaluation-summary');
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

    // Insiden Tabel Routes - HARUS sebelum catch-all route
    Route::prefix('insiden-tabel')->name('insiden-tabel.')->group(function () {
        Route::get('/', [InsidenTabelController::class, 'index'])->name('index');
        Route::get('/create', [InsidenTabelController::class, 'create'])->name('create');
        Route::post('/', [InsidenTabelController::class, 'store'])->name('store');
        Route::get('/{insidenTabel}/edit', [InsidenTabelController::class, 'edit'])->name('edit');
        Route::put('/{insidenTabel}', [InsidenTabelController::class, 'update'])->name('update');
        Route::delete('/{insidenTabel}', [InsidenTabelController::class, 'destroy'])->name('destroy');
        Route::post('/import', [InsidenTabelController::class, 'import'])->name('import');
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

    // Define a GET route with dynamic placeholders for route parameters
    // HARUS di akhir agar tidak menangkap route spesifik di atas
    Route::get('{routeName}/{name?}', [HomeController::class, 'pageView']);
    




});
