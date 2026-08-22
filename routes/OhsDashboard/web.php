<?php

declare(strict_types=1);

use App\Http\Controllers\OhsDashboard\CheckinController;
use App\Http\Controllers\OhsDashboard\PortalController;
use Illuminate\Support\Facades\Route;

Route::get('/checkin', [CheckinController::class, 'index'])->name('checkin');

Route::middleware('auth')->group(function (): void {
    Route::get('/', [PortalController::class, 'index'])->name('overview');
    Route::get('/leave', [PortalController::class, 'leave'])->name('leave');
    Route::get('/events', [PortalController::class, 'events'])->name('events');
    Route::get('/tracker', [PortalController::class, 'tracker'])->name('tracker');
    Route::get('/admin', [PortalController::class, 'admin'])->name('admin');
});
