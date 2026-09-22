<?php

declare(strict_types=1);

use App\Http\Controllers\PncMonitoring\PncMonitoringCommissioningController;
use App\Http\Controllers\PncMonitoring\PncMonitoringIkkDashboardController;
use App\Http\Controllers\PncMonitoring\PncMonitoringIkkRecordController;
use App\Http\Controllers\PncMonitoring\PncMonitoringMainDashboardController;
use App\Http\Controllers\PncMonitoring\PncMonitoringPengawasDashboardController;
use App\Http\Controllers\PncMonitoring\PncMonitoringPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes modul PNC Monitoring System (Fase 1)
|--------------------------------------------------------------------------
|
| Didaftarkan dari routes/web.php:
|   Route::prefix('pnc-monitoring')->name('pnc-monitoring.')
|       ->group(base_path('routes/PncMonitoring/pnc-monitoring.php'));
|
*/

Route::middleware('auth')->group(function (): void {
    Route::get('/', [PncMonitoringPortalController::class, 'index'])->name('index');

    Route::get('/dashboard', [PncMonitoringMainDashboardController::class, 'index'])->name('dashboard.main');
    Route::get('/dashboard/data', [PncMonitoringMainDashboardController::class, 'data'])->name('dashboard.main.data');

    Route::get('/dashboard/ikk', [PncMonitoringIkkDashboardController::class, 'index'])->name('dashboard.ikk');
    Route::get('/dashboard/ikk/data', [PncMonitoringIkkDashboardController::class, 'data'])->name('dashboard.ikk.data');

    Route::get('/dashboard/pengawas', [PncMonitoringPengawasDashboardController::class, 'index'])->name('dashboard.pengawas');
    Route::get('/dashboard/pengawas/data', [PncMonitoringPengawasDashboardController::class, 'data'])->name('dashboard.pengawas.data');

    Route::prefix('ikk-records')->name('ikk-records.')->group(function (): void {
        Route::get('/excel-template', [PncMonitoringIkkRecordController::class, 'excelTemplate'])->name('excel-template');
        Route::post('/excel-import', [PncMonitoringIkkRecordController::class, 'excelImport'])->name('excel-import');
        Route::get('/', [PncMonitoringIkkRecordController::class, 'index'])->name('index');
        Route::get('/create', [PncMonitoringIkkRecordController::class, 'create'])->name('create');
        Route::post('/', [PncMonitoringIkkRecordController::class, 'store'])->name('store');
        Route::get('/{ikkRecord}/edit', [PncMonitoringIkkRecordController::class, 'edit'])->whereNumber('ikkRecord')->name('edit');
        Route::put('/{ikkRecord}', [PncMonitoringIkkRecordController::class, 'update'])->whereNumber('ikkRecord')->name('update');
        Route::delete('/{ikkRecord}', [PncMonitoringIkkRecordController::class, 'destroy'])->whereNumber('ikkRecord')->name('destroy');
    });

    Route::prefix('commissionings')->name('commissionings.')->group(function (): void {
        Route::get('/excel-template', [PncMonitoringCommissioningController::class, 'excelTemplate'])->name('excel-template');
        Route::post('/excel-import', [PncMonitoringCommissioningController::class, 'excelImport'])->name('excel-import');
        Route::get('/', [PncMonitoringCommissioningController::class, 'index'])->name('index');
        Route::get('/create', [PncMonitoringCommissioningController::class, 'create'])->name('create');
        Route::post('/', [PncMonitoringCommissioningController::class, 'store'])->name('store');
        Route::get('/{commissioning}/edit', [PncMonitoringCommissioningController::class, 'edit'])->whereNumber('commissioning')->name('edit');
        Route::put('/{commissioning}', [PncMonitoringCommissioningController::class, 'update'])->whereNumber('commissioning')->name('update');
        Route::delete('/{commissioning}', [PncMonitoringCommissioningController::class, 'destroy'])->whereNumber('commissioning')->name('destroy');
    });
});
