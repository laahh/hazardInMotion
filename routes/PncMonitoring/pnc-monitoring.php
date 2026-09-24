<?php

declare(strict_types=1);

use App\Http\Controllers\PncMonitoring\PncMonitoringCommissioningController;
use App\Http\Controllers\PncMonitoring\PncMonitoringIkkDashboardController;
use App\Http\Controllers\PncMonitoring\PncMonitoringIkkRecordController;
use App\Http\Controllers\PncMonitoring\PncMonitoringInventoryCategoryController;
use App\Http\Controllers\PncMonitoring\PncMonitoringInventoryCompanyController;
use App\Http\Controllers\PncMonitoring\PncMonitoringInventoryDashboardController;
use App\Http\Controllers\PncMonitoring\PncMonitoringInventoryInspectionAppController;
use App\Http\Controllers\PncMonitoring\PncMonitoringInventoryToolAssetController;
use App\Http\Controllers\PncMonitoring\PncMonitoringInventoryToolMasterController;
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

    Route::get('/dashboard/inventory', [PncMonitoringInventoryDashboardController::class, 'index'])->name('dashboard.inventory');
    Route::get('/dashboard/inventory/data', [PncMonitoringInventoryDashboardController::class, 'data'])->name('dashboard.inventory.data');

    Route::prefix('ikk-records')->name('ikk-records.')->group(function (): void {
        Route::get('/excel-template', [PncMonitoringIkkRecordController::class, 'excelTemplate'])->name('excel-template');
        Route::post('/excel-import', [PncMonitoringIkkRecordController::class, 'excelImport'])->name('excel-import');
        Route::get('/export', [PncMonitoringIkkRecordController::class, 'export'])->name('export');
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

    Route::prefix('inventory-categories')->name('inventory-categories.')->group(function (): void {
        Route::get('/', [PncMonitoringInventoryCategoryController::class, 'index'])->name('index');
        Route::get('/create', [PncMonitoringInventoryCategoryController::class, 'create'])->name('create');
        Route::post('/', [PncMonitoringInventoryCategoryController::class, 'store'])->name('store');
        Route::get('/{inventoryCategory}/edit', [PncMonitoringInventoryCategoryController::class, 'edit'])->whereNumber('inventoryCategory')->name('edit');
        Route::put('/{inventoryCategory}', [PncMonitoringInventoryCategoryController::class, 'update'])->whereNumber('inventoryCategory')->name('update');
        Route::delete('/{inventoryCategory}', [PncMonitoringInventoryCategoryController::class, 'destroy'])->whereNumber('inventoryCategory')->name('destroy');
    });

    Route::prefix('inventory-companies')->name('inventory-companies.')->group(function (): void {
        Route::get('/', [PncMonitoringInventoryCompanyController::class, 'index'])->name('index');
        Route::get('/create', [PncMonitoringInventoryCompanyController::class, 'create'])->name('create');
        Route::post('/', [PncMonitoringInventoryCompanyController::class, 'store'])->name('store');
        Route::get('/{inventoryCompany}/edit', [PncMonitoringInventoryCompanyController::class, 'edit'])->whereNumber('inventoryCompany')->name('edit');
        Route::put('/{inventoryCompany}', [PncMonitoringInventoryCompanyController::class, 'update'])->whereNumber('inventoryCompany')->name('update');
        Route::delete('/{inventoryCompany}', [PncMonitoringInventoryCompanyController::class, 'destroy'])->whereNumber('inventoryCompany')->name('destroy');
    });

    Route::prefix('inventory-tool-master')->name('inventory-tool-master.')->group(function (): void {
        Route::get('/excel-template', [PncMonitoringInventoryToolMasterController::class, 'excelTemplate'])->name('excel-template');
        Route::post('/excel-import', [PncMonitoringInventoryToolMasterController::class, 'excelImport'])->name('excel-import');
        Route::get('/export', [PncMonitoringInventoryToolMasterController::class, 'export'])->name('export');
        Route::get('/', [PncMonitoringInventoryToolMasterController::class, 'index'])->name('index');
        Route::get('/create', [PncMonitoringInventoryToolMasterController::class, 'create'])->name('create');
        Route::post('/', [PncMonitoringInventoryToolMasterController::class, 'store'])->name('store');
        Route::get('/{inventoryToolMaster}/edit', [PncMonitoringInventoryToolMasterController::class, 'edit'])->whereNumber('inventoryToolMaster')->name('edit');
        Route::put('/{inventoryToolMaster}', [PncMonitoringInventoryToolMasterController::class, 'update'])->whereNumber('inventoryToolMaster')->name('update');
        Route::delete('/{inventoryToolMaster}', [PncMonitoringInventoryToolMasterController::class, 'destroy'])->whereNumber('inventoryToolMaster')->name('destroy');
        Route::get('/{inventoryToolMaster}/checklist-export', [PncMonitoringInventoryToolMasterController::class, 'checklistExport'])->whereNumber('inventoryToolMaster')->name('checklist-export');
        Route::post('/{inventoryToolMaster}/checklist-import', [PncMonitoringInventoryToolMasterController::class, 'checklistImport'])->whereNumber('inventoryToolMaster')->name('checklist-import');
        Route::get('/{inventoryToolMaster}/detail-export/{section}', [PncMonitoringInventoryToolMasterController::class, 'detailExport'])->whereNumber('inventoryToolMaster')->name('detail-export');
        Route::post('/{inventoryToolMaster}/detail-import/{section}', [PncMonitoringInventoryToolMasterController::class, 'detailImport'])->whereNumber('inventoryToolMaster')->name('detail-import');
        Route::post('/{inventoryToolMaster}/image', [PncMonitoringInventoryToolMasterController::class, 'updateImage'])->whereNumber('inventoryToolMaster')->name('image.update');
        Route::delete('/{inventoryToolMaster}/image', [PncMonitoringInventoryToolMasterController::class, 'destroyImage'])->whereNumber('inventoryToolMaster')->name('image.destroy');
    });

    Route::prefix('inventory-inspection')->name('inventory-inspection.')->group(function (): void {
        Route::get('/', [PncMonitoringInventoryInspectionAppController::class, 'home'])->name('home');
        Route::get('/scan', [PncMonitoringInventoryInspectionAppController::class, 'scan'])->name('scan');
        Route::get('/history', [PncMonitoringInventoryInspectionAppController::class, 'history'])->name('history');
        Route::get('/tools/{toolMaster}', [PncMonitoringInventoryInspectionAppController::class, 'showTool'])->whereNumber('toolMaster')->name('tools.show');
        Route::get('/tools/{toolMaster}/inspect', [PncMonitoringInventoryInspectionAppController::class, 'inspect'])->whereNumber('toolMaster')->name('tools.inspect');
    });

    Route::prefix('inventory-tool-assets')->name('inventory-tool-assets.')->group(function (): void {
        Route::get('/excel-template', [PncMonitoringInventoryToolAssetController::class, 'excelTemplate'])->name('excel-template');
        Route::post('/excel-import', [PncMonitoringInventoryToolAssetController::class, 'excelImport'])->name('excel-import');
        Route::get('/export', [PncMonitoringInventoryToolAssetController::class, 'export'])->name('export');
        Route::get('/', [PncMonitoringInventoryToolAssetController::class, 'index'])->name('index');
        Route::get('/create', [PncMonitoringInventoryToolAssetController::class, 'create'])->name('create');
        Route::post('/', [PncMonitoringInventoryToolAssetController::class, 'store'])->name('store');
        Route::get('/{inventoryToolAsset}/edit', [PncMonitoringInventoryToolAssetController::class, 'edit'])->whereNumber('inventoryToolAsset')->name('edit');
        Route::put('/{inventoryToolAsset}', [PncMonitoringInventoryToolAssetController::class, 'update'])->whereNumber('inventoryToolAsset')->name('update');
        Route::delete('/{inventoryToolAsset}', [PncMonitoringInventoryToolAssetController::class, 'destroy'])->whereNumber('inventoryToolAsset')->name('destroy');
    });
});
