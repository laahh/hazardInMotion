<?php

declare(strict_types=1);

use App\Http\Controllers\OhsScoreCard\OhsScoreCardDashboardController;
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
    });
