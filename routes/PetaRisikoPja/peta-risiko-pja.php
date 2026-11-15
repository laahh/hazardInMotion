<?php

use App\Http\Controllers\PetaRisiko\PetaRisikoDrmController;
use App\Http\Controllers\PetaRisiko\PetaRisikoFlagController;
use App\Http\Controllers\PetaRisiko\PetaRisikoPjaController;
use Illuminate\Support\Facades\Route;

/*
| Peta Risiko PJA (DRM 2.1): HIRA → aktivitas → bahaya-risiko ← SAP, per Site / Group Lokasi / PJA.
| Data dari snapshot `php artisan peta-risiko:sync` (ClickHouse OBDS). Di-require di dalam grup 'auth'.
| Versi lama (impor xlsx) tetap ada di /peta-risiko-pja/arsip.
*/
Route::prefix('peta-risiko-pja')
    ->name('peta-risiko-pja.')
    ->group(function () {
        Route::controller(PetaRisikoDrmController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/data/meta', 'meta')->name('meta');
            Route::get('/pja/{pja}', 'pja')->where('pja', '[0-9]+')->name('pja');
            Route::get('/pja/{pja}/data', 'pjaData')->where('pja', '[0-9]+')->name('pja.data');
        });

        Route::controller(PetaRisikoFlagController::class)->group(function () {
            Route::get('/pja/{pja}/flag', 'index')->where('pja', '[0-9]+')->name('flag.index');
            Route::post('/pja/{pja}/flag', 'store')->where('pja', '[0-9]+')->middleware('throttle:30,1')->name('flag.store');
            Route::post('/flag/{flag}/review', 'review')->whereNumber('flag')->middleware('throttle:30,1')->name('flag.review');
        });

        Route::prefix('arsip')->name('arsip.')->controller(PetaRisikoPjaController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{pja}', 'show')->where('pja', '[a-z0-9-]+')->name('show');
        });
    });
