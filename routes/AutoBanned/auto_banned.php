<?php

declare(strict_types=1);

use App\Http\Controllers\AutoBanned\AutoBannedController;
use App\Http\Controllers\AutoBanned\AutoBannedHsctEmailController;
use App\Http\Controllers\AutoBanned\AutoBannedInputasiController;
use App\Http\Controllers\AutoBanned\AutoBannedMasterDataController;
use App\Http\Controllers\AutoBanned\AutoBannedMasterSodController;
use App\Http\Controllers\AutoBanned\AutoBannedSodVerificationController;
use App\Http\Controllers\AutoBanned\AutoBannedTableauFlowHistoryController;
use App\Http\Controllers\AutoBanned\AutoBannedTreatmentController;
use App\Http\Controllers\AutoBanned\AutoBannedUnbanMonitoringController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('auto-banned')
    ->name('auto-banned.')
    ->group(function (): void {
        Route::get('/', [AutoBannedController::class, 'index'])->name('index');
        Route::get('/banned-monitoring', [AutoBannedController::class, 'bannedMonitoring'])->name('banned-monitoring.index');
        Route::post('/banned-monitoring/send-email', [AutoBannedController::class, 'sendDailyBannedEmail'])->name('banned-monitoring.send-email');
        Route::get('/unban-monitoring', [AutoBannedUnbanMonitoringController::class, 'index'])->name('unban-monitoring.index');
        Route::get('/hsct-email', [AutoBannedHsctEmailController::class, 'index'])->name('hsct-email.index');
        Route::get('/tableau-flow-history', [AutoBannedTableauFlowHistoryController::class, 'index'])->name('tableau-flow-history.index');
        Route::post('/hsct-email/initial', [AutoBannedHsctEmailController::class, 'sendInitial'])->name('hsct-email.initial');
        Route::post('/hsct-email/reminder', [AutoBannedHsctEmailController::class, 'sendReminder'])->name('hsct-email.reminder');
        Route::post('/hsct-campaign-items/{item}/confirm', [AutoBannedHsctEmailController::class, 'confirmItem'])->name('hsct-campaign-items.confirm');
        Route::post('/snapshots/{snapshot}/hsct-sent', [AutoBannedController::class, 'markHsctSent'])->name('snapshots.hsct-sent');
        Route::post('/snapshots/{snapshot}/hsct-confirmed', [AutoBannedController::class, 'markHsctConfirmed'])->name('snapshots.hsct-confirmed');
        Route::get('/inputasi', [AutoBannedInputasiController::class, 'index'])->name('inputasi.index');
        Route::get('/treatment-evidence/lookup-sid', [AutoBannedTreatmentController::class, 'lookupSid'])->name('treatment-evidence.lookup-sid');
        Route::post('/treatment-evidence', [AutoBannedTreatmentController::class, 'store'])->name('treatment-evidence.store');
        Route::get('/unban-requests/{unbanRequest}/evidence', [AutoBannedTreatmentController::class, 'downloadEvidence'])->name('unban-requests.evidence');
        Route::get('/sod-verification', [AutoBannedSodVerificationController::class, 'index'])->name('sod-verification.index');
        Route::post('/unban-requests/{unbanRequest}/review', [AutoBannedSodVerificationController::class, 'review'])->name('unban-requests.review');
        Route::get('/master-data', [AutoBannedMasterDataController::class, 'index'])->name('master-data.index');

        Route::get('/master-sod', [AutoBannedMasterSodController::class, 'index'])->name('master-sod.index');
        Route::prefix('master-sod')
            ->name('master-sod.')
            ->group(function (): void {
                Route::get('/data', [AutoBannedMasterSodController::class, 'data'])->name('data');
                Route::post('/', [AutoBannedMasterSodController::class, 'store'])->name('store');
                Route::get('/{masterSod}', [AutoBannedMasterSodController::class, 'show'])->name('show');
                Route::put('/{masterSod}', [AutoBannedMasterSodController::class, 'update'])->name('update');
                Route::delete('/{masterSod}', [AutoBannedMasterSodController::class, 'destroy'])->name('destroy');
            });
    });
