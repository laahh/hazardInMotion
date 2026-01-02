<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ValidasiTbc\ValidasiTbcController;

// Semua route Validasi TBC dikelompokkan di sini
Route::middleware(['auth'])
    ->prefix('validasi-tbc')
    ->name('validasi-tbc.')
    ->group(function () {
        Route::get('/', [ValidasiTbcController::class, 'index'])->name('index');
        Route::post('/simpan', [ValidasiTbcController::class, 'store'])->name('store');
    });


