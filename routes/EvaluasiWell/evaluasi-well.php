<?php

use App\Http\Controllers\SportEvaluation\HealthNutritionRiskController;
use App\Http\Controllers\SportEvaluation\NutritionEvaluationController;
use App\Http\Controllers\SportEvaluation\SportActivitiesController;
use App\Http\Controllers\SportEvaluation\SportEmployeeController;
use App\Http\Controllers\SportEvaluation\SportEvaluationDashboardController;
use App\Http\Controllers\SportEvaluation\SportEvaluationEmployeeProfileController;
use App\Http\Controllers\SportEvaluation\SportEvaluationMitraAssignmentController;
use App\Http\Controllers\SportEvaluation\SportEvaluationMitraDashboardController;
use App\Http\Controllers\SportEvaluation\SportEvaluationPvtDashboardController;
use App\Http\Controllers\SportEvaluation\SportEvaluationWeeklyUploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modul Evaluasi Olahraga & Aktivitas (BeWell)
|--------------------------------------------------------------------------
| Akses bewell_db lewat SSH tunnel manual. Metrik/dashboard tetap read-only;
| manajemen employee_profiles (create/update) diizinkan dari Admin.
| Di-require di dalam grup middleware 'auth' pada routes/web.php.
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

        Route::get('/install-stats', [SportEvaluationDashboardController::class, 'installStats'])
            ->name('install-stats');
        Route::get('/install-stats/export', [SportEvaluationDashboardController::class, 'installStatsExport'])
            ->name('install-stats.export');

        Route::get('/active-stats', [SportEvaluationDashboardController::class, 'activeStats'])
            ->name('active-stats');

        Route::get('/mitra', [SportEvaluationMitraDashboardController::class, 'index'])
            ->name('mitra.index');
        Route::get('/mitra/install-stats', [SportEvaluationMitraDashboardController::class, 'installStats'])
            ->name('mitra.install-stats');
        Route::get('/mitra/install-stats/export', [SportEvaluationMitraDashboardController::class, 'installStatsExport'])
            ->name('mitra.install-stats.export');
        Route::get('/mitra/active-stats', [SportEvaluationMitraDashboardController::class, 'activeStats'])
            ->name('mitra.active-stats');
        Route::get('/mitra/not-installed/data', [SportEvaluationMitraDashboardController::class, 'notInstalledData'])
            ->name('mitra.not-installed.data');
        Route::get('/mitra/not-installed/export', [SportEvaluationMitraDashboardController::class, 'notInstalledExport'])
            ->name('mitra.not-installed.export');

        Route::get('/mitra-assignments', [SportEvaluationMitraAssignmentController::class, 'index'])
            ->name('mitra-assignments.index');
        Route::get('/mitra-assignments/create', [SportEvaluationMitraAssignmentController::class, 'create'])
            ->name('mitra-assignments.create');
        Route::post('/mitra-assignments', [SportEvaluationMitraAssignmentController::class, 'store'])
            ->name('mitra-assignments.store');
        Route::get('/mitra-assignments/{id}/edit', [SportEvaluationMitraAssignmentController::class, 'edit'])
            ->whereNumber('id')
            ->name('mitra-assignments.edit');
        Route::put('/mitra-assignments/{id}', [SportEvaluationMitraAssignmentController::class, 'update'])
            ->whereNumber('id')
            ->name('mitra-assignments.update');
        Route::delete('/mitra-assignments/{id}', [SportEvaluationMitraAssignmentController::class, 'destroy'])
            ->whereNumber('id')
            ->name('mitra-assignments.destroy');

        Route::get('/activities', [SportActivitiesController::class, 'index'])->name('activities.index');
        Route::get('/activities/data', [SportActivitiesController::class, 'data'])->name('activities.data');

        Route::get('/nutrition', [NutritionEvaluationController::class, 'index'])->name('nutrition.index');

        Route::get('/health-nutrition', [HealthNutritionRiskController::class, 'index'])
            ->name('health-nutrition.index');
        Route::get('/health-nutrition/data', [HealthNutritionRiskController::class, 'data'])
            ->name('health-nutrition.data');
        Route::get('/health-nutrition/export', [HealthNutritionRiskController::class, 'export'])
            ->name('health-nutrition.export');

        Route::get('/pvt', [SportEvaluationPvtDashboardController::class, 'index'])
            ->name('pvt.index');
        Route::get('/pvt/data', [SportEvaluationPvtDashboardController::class, 'data'])
            ->name('pvt.data');
        Route::get('/pvt/export', [SportEvaluationPvtDashboardController::class, 'export'])
            ->name('pvt.export');

        Route::get('/weekly-uploads', [SportEvaluationWeeklyUploadController::class, 'index'])
            ->name('weekly-uploads.index');
        Route::get('/weekly-uploads/data', [SportEvaluationWeeklyUploadController::class, 'data'])
            ->name('weekly-uploads.data');
        Route::get('/weekly-uploads/export', [SportEvaluationWeeklyUploadController::class, 'export'])
            ->name('weekly-uploads.export');

        Route::get('/users', [SportEvaluationEmployeeProfileController::class, 'index'])
            ->name('users.index');
        Route::get('/users/create', [SportEvaluationEmployeeProfileController::class, 'create'])
            ->name('users.create');
        Route::post('/users', [SportEvaluationEmployeeProfileController::class, 'store'])
            ->name('users.store');
        Route::get('/users/import', [SportEvaluationEmployeeProfileController::class, 'importForm'])
            ->name('users.import-form');
        Route::get('/users/import/template', [SportEvaluationEmployeeProfileController::class, 'downloadTemplate'])
            ->name('users.import-template');
        Route::post('/users/import', [SportEvaluationEmployeeProfileController::class, 'import'])
            ->name('users.import');
        Route::post('/users/sync-hse', [SportEvaluationEmployeeProfileController::class, 'syncFromHse'])
            ->name('users.sync-hse');
        Route::get('/users/{id}/edit', [SportEvaluationEmployeeProfileController::class, 'edit'])
            ->whereNumber('id')
            ->name('users.edit');
        Route::put('/users/{id}', [SportEvaluationEmployeeProfileController::class, 'update'])
            ->whereNumber('id')
            ->name('users.update');

        Route::get('/employees/{userId}', [SportEmployeeController::class, 'show'])
            ->whereNumber('userId')
            ->name('employees.show');
    });
