<?php

declare(strict_types=1);

use App\Http\Controllers\EmergencyResponse\Publik\InspectionPublicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Form publik inspeksi emergency equipment / safety device
|--------------------------------------------------------------------------
| Tanpa login, dipakai inspector lewat HP: scan QR di stiker peralatan atau
| cari UUID/No Registrasi, lalu isi checklist di tempat.
| Approval-nya ada di /emergency-response/inspection (perlu login).
*/

Route::prefix('form/inspeksi-emergency')
    ->name('er-inspection.public.')
    ->middleware('throttle:60,1')
    ->group(function (): void {
        Route::get('/', [InspectionPublicController::class, 'show'])->name('form');
        Route::get('/sukses', [InspectionPublicController::class, 'success'])->name('success');
        Route::get('/lookup', [InspectionPublicController::class, 'lookup'])->name('lookup');
        Route::post('/', [InspectionPublicController::class, 'store'])
            ->middleware('throttle:12,1')
            ->name('store');
    });
