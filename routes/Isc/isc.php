<?php

declare(strict_types=1);

use App\Http\Controllers\Isc\IscCctvMapController;
use App\Http\Controllers\Isc\IscHomeController;
use App\Http\Controllers\Isc\IscInterventionsController;
use App\Http\Controllers\Isc\IscMapsController;
use App\Http\Controllers\Isc\IscMapsInterventionController;
use App\Http\Controllers\Isc\IscPobController;
use App\Http\Controllers\Isc\IscPostEventController;
use App\Http\Controllers\Isc\IscPostEventTrackController;
use Illuminate\Support\Facades\Route;

Route::prefix('isc')
    ->name('isc.')
    ->group(function (): void {
        Route::get('/', [IscHomeController::class, 'index'])->name('index');
        Route::get('/maps', [IscMapsController::class, 'index'])->name('maps.index');
        Route::get('/maps/boundaries', [IscMapsController::class, 'boundaries'])->name('maps.boundaries');
        Route::get('/maps/overlay', [IscMapsController::class, 'overlay'])->name('maps.overlay');
        Route::get('/maps/wms', [IscMapsController::class, 'wms'])->name('maps.wms');
        Route::get('/maps/wmts/{z}/{x}/{y}', [IscMapsController::class, 'wmts'])
            ->whereNumber(['z', 'x', 'y'])
            ->name('maps.wmts');
        Route::get('/maps/pob', [IscPobController::class, 'index'])->name('maps.pob');
        Route::get('/maps/pob/export', [IscPobController::class, 'export'])->name('maps.pob.export');
        Route::get('/maps/pob/{key}', [IscPobController::class, 'show'])->where('key', '.*')->name('maps.pob.show');
        Route::get('/maps/post-event', [IscPostEventTrackController::class, 'index'])->name('maps.post-event');
        Route::get('/maps/post-event/trail', [IscPostEventTrackController::class, 'trail'])->name('maps.post-event.trail');
        Route::get('/maps/cctv', [IscCctvMapController::class, 'index'])->name('maps.cctv');
        Route::get('/maps/interventions', [IscMapsInterventionController::class, 'index'])->name('maps.interventions');

        Route::middleware('isc.role:isc-pic,isc-verifier,admin')->group(function (): void {
            Route::get('/interventions', [IscInterventionsController::class, 'index'])->name('interventions.index');
            Route::get('/interventions/{event}', [IscInterventionsController::class, 'show'])->name('interventions.show');
            Route::post('/interventions', [IscInterventionsController::class, 'store'])->name('interventions.store');
            Route::post('/maps/interventions', [IscMapsInterventionController::class, 'store'])->name('maps.interventions.store');
            Route::post('/interventions/{intervention}/evidence', [IscInterventionsController::class, 'storeEvidence'])->name('interventions.evidence');
            Route::post('/interventions/{intervention}/verify', [IscInterventionsController::class, 'verify'])->name('interventions.verify');
            Route::get('/post-event', [IscPostEventController::class, 'index'])->name('post-event.index');
            Route::get('/post-event.json', [IscPostEventController::class, 'json'])->name('post-event.json');
        });
    });
