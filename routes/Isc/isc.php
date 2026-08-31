<?php

declare(strict_types=1);

use App\Http\Controllers\Isc\IscMapsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modul ISC — peta boundary IUPK + Besigma
|--------------------------------------------------------------------------
| Prefix URL /isc/...  Data Besigma read-only lewat koneksi besigma_db.
*/

Route::prefix('isc')
    ->name('isc.')
    ->group(function (): void {
        Route::get('/', static fn () => redirect()->route('isc.maps.index'))->name('index');
        Route::get('/maps', [IscMapsController::class, 'index'])->name('maps.index');
        Route::get('/maps/boundaries', [IscMapsController::class, 'boundaries'])->name('maps.boundaries');
        Route::get('/maps/overlay', [IscMapsController::class, 'overlay'])->name('maps.overlay');
    });
