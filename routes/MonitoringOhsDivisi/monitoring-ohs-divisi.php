<?php

declare(strict_types=1);

use App\Http\Controllers\MonitoringOhsDivisi\MonitoringOhsDivisiController;
use Illuminate\Support\Facades\Route;

// Monitoring OHS Divisi — awalnya salinan halaman peta /isc/maps.
Route::prefix('monitoring-ohs-divisi')
    ->name('monitoring-ohs-divisi.')
    ->group(function (): void {
        Route::get('/', [MonitoringOhsDivisiController::class, 'index'])->name('index');
    });
