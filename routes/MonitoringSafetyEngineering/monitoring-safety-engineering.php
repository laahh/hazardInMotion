<?php

declare(strict_types=1);

use App\Http\Controllers\MonitoringSafetyEngineering\MonitoringSafetyEngineeringDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('monitoring-safety-engineering')
    ->name('monitoring-safety-engineering.')
    ->group(function (): void {
        Route::redirect('/', '/monitoring-safety-engineering/dashboard')->name('home');
        Route::get('/dashboard', [MonitoringSafetyEngineeringDashboardController::class, 'index'])->name('dashboard');
    });
