<?php

declare(strict_types=1);

use App\Http\Controllers\Isc\IscHomeController;
use App\Http\Controllers\Isc\IscInterventionsController;
use App\Http\Controllers\Isc\IscMapsController;
use App\Http\Controllers\Isc\IscPobController;
use App\Http\Controllers\Isc\IscPostEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('isc')
    ->name('isc.')
    ->group(function (): void {
        Route::get('/', [IscHomeController::class, 'index'])->name('index');
        Route::get('/maps', [IscMapsController::class, 'index'])->name('maps.index');
        Route::get('/maps/boundaries', [IscMapsController::class, 'boundaries'])->name('maps.boundaries');
        Route::get('/maps/overlay', [IscMapsController::class, 'overlay'])->name('maps.overlay');
        Route::get('/maps/pob', [IscPobController::class, 'index'])->name('maps.pob');
        Route::get('/maps/pob/{key}', [IscPobController::class, 'show'])->where('key', '.*')->name('maps.pob.show');

        Route::middleware('isc.role:isc-pic,isc-verifier,admin')->group(function (): void {
            Route::get('/interventions', [IscInterventionsController::class, 'index'])->name('interventions.index');
            Route::get('/interventions/{event}', [IscInterventionsController::class, 'show'])->name('interventions.show');
            Route::post('/interventions', [IscInterventionsController::class, 'store'])->name('interventions.store');
            Route::post('/interventions/{intervention}/evidence', [IscInterventionsController::class, 'storeEvidence'])->name('interventions.evidence');
            Route::post('/interventions/{intervention}/verify', [IscInterventionsController::class, 'verify'])->name('interventions.verify');
            Route::get('/post-event', [IscPostEventController::class, 'index'])->name('post-event.index');
            Route::get('/post-event.json', [IscPostEventController::class, 'json'])->name('post-event.json');
        });
    });
