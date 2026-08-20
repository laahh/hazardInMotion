<?php

declare(strict_types=1);

use App\Http\Controllers\DwhRedshift\DwhRedshiftController;
use Illuminate\Support\Facades\Route;

Route::prefix('dwh-redshift')
    ->name('dwh-redshift.')
    ->group(function (): void {
        Route::get('/', [DwhRedshiftController::class, 'index'])->name('index');
        Route::get('/preview', [DwhRedshiftController::class, 'preview'])->name('preview');
    });
