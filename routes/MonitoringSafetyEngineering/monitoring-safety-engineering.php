<?php

declare(strict_types=1);

use App\Http\Controllers\MonitoringSafetyEngineering\MonitoringSafetyEngineeringCompanyOverviewController;
use App\Http\Controllers\MonitoringSafetyEngineering\MonitoringSafetyEngineeringDashboardController;
use App\Http\Controllers\MonitoringSafetyEngineering\MonitoringSafetyEngineeringEffectivenessController;
use App\Http\Controllers\MonitoringSafetyEngineering\MonitoringSafetyEngineeringOutsideCommitmentController;
use App\Http\Controllers\MonitoringSafetyEngineering\MonitoringSafetyEngineeringPmrEvaluationController;
use App\Http\Controllers\MonitoringSafetyEngineering\MonitoringSafetyEngineeringRecordUpdateController;
use App\Http\Controllers\MonitoringSafetyEngineering\MonitoringSafetyEngineeringUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('monitoring-safety-engineering')
    ->name('monitoring-safety-engineering.')
    ->group(function (): void {
        Route::redirect('/', '/monitoring-safety-engineering/dashboard')->name('home');

        Route::get('/dashboard', [MonitoringSafetyEngineeringDashboardController::class, 'index'])->name('dashboard');
        Route::get('/luar-komitmen', [MonitoringSafetyEngineeringOutsideCommitmentController::class, 'index'])->name('outside-commitment');
        Route::get('/evaluasi-pmr', [MonitoringSafetyEngineeringPmrEvaluationController::class, 'index'])->name('pmr-evaluation');
        Route::get('/overall-perusahaan', [MonitoringSafetyEngineeringCompanyOverviewController::class, 'index'])->name('company-overview');
        Route::get('/evaluasi-efektivitas', [MonitoringSafetyEngineeringEffectivenessController::class, 'index'])->name('effectiveness');

        Route::prefix('upload')->name('upload.')->group(function (): void {
            Route::get('/', [MonitoringSafetyEngineeringUploadController::class, 'index'])->name('index');
            Route::get('/template', [MonitoringSafetyEngineeringUploadController::class, 'downloadTemplate'])->name('template');
            Route::post('/import', [MonitoringSafetyEngineeringUploadController::class, 'import'])->name('import');
        });

        Route::prefix('data-update')->name('data-update.')->group(function (): void {
            Route::get('/', [MonitoringSafetyEngineeringRecordUpdateController::class, 'index'])->name('index');
            Route::get('/records', [MonitoringSafetyEngineeringRecordUpdateController::class, 'records'])->name('records');
            Route::get('/records/{recordId}/history', [MonitoringSafetyEngineeringRecordUpdateController::class, 'history'])
                ->name('history')
                ->whereNumber('recordId');
            Route::post('/records', [MonitoringSafetyEngineeringRecordUpdateController::class, 'save'])->name('save');
        });
    });
