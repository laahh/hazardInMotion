<?php

use App\Http\Controllers\SportEvaluation\HealthNutritionRiskController;
use App\Http\Controllers\SportEvaluation\NutritionEvaluationController;
use App\Http\Controllers\SportEvaluation\SportActivitiesController;
use App\Http\Controllers\SportEvaluation\SportEmployeeController;
use App\Http\Controllers\SportEvaluation\SportEvaluationDashboardController;
use App\Http\Controllers\SportEvaluation\SportEvaluationWeeklyUploadController;
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

        Route::get('/not-installed/data', [SportEvaluationDashboardController::class, 'notInstalledData'])
            ->name('not-installed.data');
        Route::get('/not-installed/export', [SportEvaluationDashboardController::class, 'notInstalledExport'])
            ->name('not-installed.export');

        Route::get('/activities', [SportActivitiesController::class, 'index'])->name('activities.index');
        Route::get('/activities/data', [SportActivitiesController::class, 'data'])->name('activities.data');

        Route::get('/nutrition', [NutritionEvaluationController::class, 'index'])->name('nutrition.index');

        Route::get('/health-nutrition', [HealthNutritionRiskController::class, 'index'])
            ->name('health-nutrition.index');
        Route::get('/health-nutrition/data', [HealthNutritionRiskController::class, 'data'])
            ->name('health-nutrition.data');
        Route::get('/health-nutrition/export', [HealthNutritionRiskController::class, 'export'])
            ->name('health-nutrition.export');

        Route::get('/weekly-uploads', [SportEvaluationWeeklyUploadController::class, 'index'])
            ->name('weekly-uploads.index');
        Route::get('/weekly-uploads/data', [SportEvaluationWeeklyUploadController::class, 'data'])
            ->name('weekly-uploads.data');
        Route::get('/weekly-uploads/export', [SportEvaluationWeeklyUploadController::class, 'export'])
            ->name('weekly-uploads.export');

        Route::get('/employees/{userId}', [SportEmployeeController::class, 'show'])
            ->whereNumber('userId')
            ->name('employees.show');
    });
