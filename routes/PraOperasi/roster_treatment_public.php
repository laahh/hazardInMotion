<?php

declare(strict_types=1);

use App\Http\Controllers\PraOperasi\RosterTreatmentPublicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Form publik pengajuan bukti treatment — sid_roster_banned_master
|--------------------------------------------------------------------------
| Tanpa login, dipakai karyawan lewat HP untuk upload bukti treatment atas
| pelanggaran yang tercatat di master roster banned. Approval-nya ada di
| /pra-operasi/roster-banned/treatment (perlu login).
*/

Route::prefix('form/pengajuan-treatment-banned')
    ->name('roster-treatment.public.')
    ->middleware('throttle:30,1')
    ->group(function (): void {
        Route::get('/', [RosterTreatmentPublicController::class, 'show'])->name('form');
        Route::get('/sukses', [RosterTreatmentPublicController::class, 'success'])->name('success');
        Route::get('/lookup', [RosterTreatmentPublicController::class, 'lookup'])->name('lookup');
        Route::post('/', [RosterTreatmentPublicController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('store');
    });
