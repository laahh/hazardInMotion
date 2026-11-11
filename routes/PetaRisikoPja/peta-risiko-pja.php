<?php

use App\Http\Controllers\PetaRisiko\PetaRisikoPjaController;
use Illuminate\Support\Facades\Route;

/*
| Peta Risiko PJA: PJA → lokasi → bisnis proses → bahaya HIRA → bukti SAP.
| Data dari snapshot (php artisan peta-risiko:impor). Di-require di dalam grup 'auth'.
*/
Route::prefix('peta-risiko-pja')
    ->name('peta-risiko-pja.')
    ->controller(PetaRisikoPjaController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{pja}', 'show')->where('pja', '[a-z0-9-]+')->name('show');
    });
