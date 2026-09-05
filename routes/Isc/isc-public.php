<?php

declare(strict_types=1);

use App\Http\Controllers\Isc\IscMoiController;
use Illuminate\Support\Facades\Route;

Route::prefix('isc')
    ->name('isc.')
    ->group(function (): void {
        Route::get('/moi/{page?}', [IscMoiController::class, 'index'])
            ->where('page', '[1-6]')
            ->name('moi');
    });
