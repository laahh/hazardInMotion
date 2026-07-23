<?php

use App\Http\Controllers\SportEvaluation\NutritionEvaluationController;
use App\Http\Controllers\SportEvaluation\SportActivitiesController;
use App\Http\Controllers\SportEvaluation\SportEmployeeController;
use App\Http\Controllers\SportEvaluation\SportEvaluationDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modul Evaluasi Olahraga & Aktivitas (BeWell)
|--------------------------------------------------------------------------
| Read-only ke koneksi bewell_db (SSH tunnel manual). Di-require di dalam
| grup middleware 'auth' pada routes/web.php.
*/

Route::middleware('evaluasi-well.access')
    ->prefix('evaluasi-well')
    ->name('evaluasi-well.')
    ->group(function () {
        Route::get('/', [SportEvaluationDashboardController::class, 'index'])->name('index');
        Route::get('/summary', [SportEvaluationDashboardController::class, 'summary'])->name('summary');
        Route::get('/trend', [SportEvaluationDashboardController::class, 'trend'])->name('trend');
        Route::get('/distribution', [SportEvaluationDashboardController::class, 'distribution'])->name('distribution');
        Route::get('/leaderboard', [SportEvaluationDashboardController::class, 'leaderboard'])->name('leaderboard');

        Route::get('/activities', [SportActivitiesController::class, 'index'])->name('activities.index');
        Route::get('/activities/data', [SportActivitiesController::class, 'data'])->name('activities.data');

        Route::get('/nutrition', [NutritionEvaluationController::class, 'index'])->name('nutrition.index');

        Route::get('/employees/{userId}', [SportEmployeeController::class, 'show'])
            ->whereNumber('userId')
            ->name('employees.show');
    });
