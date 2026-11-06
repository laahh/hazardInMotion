<?php

declare(strict_types=1);

use App\Http\Controllers\PraOperasi\PengawasUnbanPublicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Form publik pengajuan unbanned pengawas — validasi_status_banned_pengawas
|--------------------------------------------------------------------------
| Tanpa login, tampilan mengikuti /form/pengajuan-treatment. Hasilnya masuk ke
| pengawas_unban_requests.
*/

Route::prefix('form/pengajuan-treatment-banned')
    ->name('roster-treatment.public.')
    ->middleware('throttle:30,1')
    ->group(function (): void {
        Route::get('/', [PengawasUnbanPublicController::class, 'show'])->name('form');
        Route::get('/sukses', [PengawasUnbanPublicController::class, 'success'])->name('success');
        Route::get('/lookup-sid', [PengawasUnbanPublicController::class, 'lookupSid'])->name('lookup-sid');
        Route::post('/', [PengawasUnbanPublicController::class, 'store'])
            // Prefix wajib: tanpa itu counter-nya sama dengan throttle:30,1 grup,
            // jadi buka form + Cari SID beberapa kali sudah menghabiskan jatah submit.
            ->middleware('throttle:6,1,roster-treatment-submit')
            ->name('store');
    });
