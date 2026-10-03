<?php

declare(strict_types=1);

use App\Http\Controllers\OhsScoreCard\BerecordController;
use App\Http\Controllers\OhsScoreCard\BlindspotTbcController;
use App\Http\Controllers\OhsScoreCard\KinerjaControlRoomDmsController;
use App\Http\Controllers\OhsScoreCard\OhsScoreCardDashboardController;
use App\Http\Controllers\OhsScoreCard\PelaksanaanEdukasiController;
use App\Http\Controllers\OhsScoreCard\RatioTbcGrController;
use App\Http\Controllers\OhsScoreCard\RoadSummaryController;
use App\Http\Controllers\OhsScoreCard\SpeakUpFatigueController;
use App\Http\Controllers\OhsScoreCard\ValidasiTbcTabController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modul OHS Score Card
|--------------------------------------------------------------------------
| Dashboard OHS Score Card. Datanya berasal dari bewell_db (sama dengan modul
| Evaluasi Olahraga & Aktivitas), jadi gate aksesnya ikut 'evaluasi-well.access'.
| Di-require di dalam grup middleware 'auth' pada routes/web.php.
*/

Route::middleware('evaluasi-well.access')
    ->prefix('ohs-score-card')
    ->name('ohs-score-card.')
    ->group(function (): void {
        Route::get('/', [OhsScoreCardDashboardController::class, 'index'])->name('index');

        // Parameter SGI — "Jalan sesuai standar" (app_mixer.road_summary)
        Route::get('/jalan-sesuai-standar', [RoadSummaryController::class, 'index'])
            ->name('jalan-sesuai-standar.index');
        Route::get('/jalan-sesuai-standar/data', [RoadSummaryController::class, 'data'])
            ->name('jalan-sesuai-standar.data');
        Route::get('/jalan-sesuai-standar/export', [RoadSummaryController::class, 'export'])
            ->name('jalan-sesuai-standar.export');
        Route::get('/jalan-sesuai-standar/overview', [RoadSummaryController::class, 'overview'])
            ->name('jalan-sesuai-standar.overview');

        // Parameter SOD "Ratio TBC & GR" — minecon & subcon
        Route::get('/ratio-tbc-gr', [RatioTbcGrController::class, 'index'])
            ->name('ratio-tbc-gr.index');
        // {dataset}: minecon (lead_ratio_pelapor_tbc) atau subcon
        // (lead_subcont_ratio_pelapor_tbc); nama lain ditolak di sini.
        Route::get('/ratio-tbc-gr/{dataset}/overview', [RatioTbcGrController::class, 'overview'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('ratio-tbc-gr.overview');
        Route::get('/ratio-tbc-gr/{dataset}/data', [RatioTbcGrController::class, 'data'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('ratio-tbc-gr.data');
        Route::get('/ratio-tbc-gr/{dataset}/export', [RatioTbcGrController::class, 'export'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('ratio-tbc-gr.export');

        // Parameter SOD "Blindspot TBC" — minecon & subcon
        Route::get('/blindspot-tbc', [BlindspotTbcController::class, 'index'])
            ->name('blindspot-tbc.index');
        Route::get('/blindspot-tbc/{dataset}/overview', [BlindspotTbcController::class, 'overview'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('blindspot-tbc.overview');
        Route::get('/blindspot-tbc/{dataset}/data', [BlindspotTbcController::class, 'data'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('blindspot-tbc.data');
        Route::get('/blindspot-tbc/{dataset}/export', [BlindspotTbcController::class, 'export'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('blindspot-tbc.export');

        // Parameter SOD "Kinerja Pengawasan Control Room DMS"
        Route::get('/kinerja-control-room-dms', [KinerjaControlRoomDmsController::class, 'index'])
            ->name('kinerja-control-room-dms.index');
        Route::get('/kinerja-control-room-dms/overview', [KinerjaControlRoomDmsController::class, 'overview'])
            ->name('kinerja-control-room-dms.overview');
        Route::get('/kinerja-control-room-dms/data', [KinerjaControlRoomDmsController::class, 'data'])
            ->name('kinerja-control-room-dms.data');
        Route::get('/kinerja-control-room-dms/export', [KinerjaControlRoomDmsController::class, 'export'])
            ->name('kinerja-control-room-dms.export');

        // Parameter HSECT — "Peer Pressure" (hse_automation: bcsid.mv_berecord)
        Route::get('/peer-pressure', [BerecordController::class, 'index'])
            ->name('peer-pressure.index');
        Route::get('/peer-pressure/data', [BerecordController::class, 'data'])
            ->name('peer-pressure.data');
        Route::get('/peer-pressure/export', [BerecordController::class, 'export'])
            ->name('peer-pressure.export');

        // Tab Speak Up (app_mixer.speak_up_fatigue)
        Route::get('/peer-pressure/speak-up/data', [SpeakUpFatigueController::class, 'data'])
            ->name('peer-pressure.speak-up.data');
        Route::get('/peer-pressure/speak-up/export', [SpeakUpFatigueController::class, 'export'])
            ->name('peer-pressure.speak-up.export');

        // Tab Blindspot TBC (app_mixer.validasi_tbc)
        Route::get('/peer-pressure/blindspot-tbc/data', [ValidasiTbcTabController::class, 'data'])
            ->name('peer-pressure.blindspot-tbc.data');
        Route::get('/peer-pressure/blindspot-tbc/export', [ValidasiTbcTabController::class, 'export'])
            ->name('peer-pressure.blindspot-tbc.export');

        // Tab Pelaksanaan Peer Pressure (kejadian + peserta edukasi)
        Route::get('/peer-pressure/pelaksanaan/data', [PelaksanaanEdukasiController::class, 'data'])
            ->name('peer-pressure.pelaksanaan.data');
        Route::get('/peer-pressure/pelaksanaan/export', [PelaksanaanEdukasiController::class, 'export'])
            ->name('peer-pressure.pelaksanaan.export');
    });
