<?php

declare(strict_types=1);

use App\Http\Controllers\AutoBanned\AutoBannedPublicTreatmentController;
use App\Http\Controllers\AutoBanned\AutoBannedPipelineMonitoringController;
use Illuminate\Support\Facades\Route;

Route::prefix('auto-banned')
    ->name('auto-banned.')
    ->middleware('throttle:60,1')
    ->group(function (): void {
        Route::get('/pipeline-monitoring', [AutoBannedPipelineMonitoringController::class, 'index'])
            ->name('pipeline-monitoring.index');
    });

Route::prefix('form/pengajuan-treatment')
    ->name('auto-banned.public.treatment.')
    ->middleware('throttle:30,1')
    ->group(function (): void {
        Route::get('/', [AutoBannedPublicTreatmentController::class, 'show'])->name('form');
        Route::get('/sukses', [AutoBannedPublicTreatmentController::class, 'success'])->name('success');
        Route::get('/lookup-sid', [AutoBannedPublicTreatmentController::class, 'lookupSid'])->name('lookup-sid');
        Route::post('/', [AutoBannedPublicTreatmentController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('store');
    });
