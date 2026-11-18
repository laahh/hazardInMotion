<?php

declare(strict_types=1);

use App\Http\Controllers\PetaRisiko\PetaRisikoRaporPublicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rapor PJA publik — tanpa login
|--------------------------------------------------------------------------
| Cari SID → performa PJA tempat SID ditugaskan (snapshot peta-risiko:sync). Hanya membaca, tanpa penyimpanan.
| Tampilan mengikuti /form/pengajuan-treatment-banned.
*/

Route::prefix('form/rapor-pja')
    ->name('peta-risiko-pja.rapor-publik.')
    ->middleware('throttle:30,1')
    ->group(function (): void {
        Route::get('/', [PetaRisikoRaporPublicController::class, 'show'])->name('form');
        Route::get('/cari', [PetaRisikoRaporPublicController::class, 'cari'])
            ->middleware('throttle:20,1,rapor-pja-cari')
            ->name('cari');
    });
