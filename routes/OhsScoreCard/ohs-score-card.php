<?php

declare(strict_types=1);

use App\Http\Controllers\OhsScoreCard\OhsScoreCardDashboardController;
use App\Http\Controllers\OhsScoreCard\RoadSummaryController;
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
    });
