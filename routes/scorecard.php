<?php

// Semua route Score Card dikelompokkan di sini
Route::middleware(['auth'])
    ->prefix('score-card')
    ->name('score-card.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\ScoreCard\ScoreCardController::class, 'index'])->name('index');
        Route::get('/{parameter}', [\App\Http\Controllers\ScoreCard\ScoreCardController::class, 'show'])->name('show');
    });
