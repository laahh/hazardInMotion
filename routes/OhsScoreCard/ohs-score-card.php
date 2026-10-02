<?php

declare(strict_types=1);

use App\Http\Controllers\OhsScoreCard\BerecordController;
use App\Http\Controllers\OhsScoreCard\OhsScoreCardDashboardController;
use App\Http\Controllers\OhsScoreCard\PelaksanaanEdukasiController;
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
