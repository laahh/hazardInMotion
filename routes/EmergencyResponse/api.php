<?php

declare(strict_types=1);

use App\Http\Controllers\EmergencyResponse\Notification\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/notification/unread-summary', [NotificationController::class, 'unreadSummary'])->name('notification.unread-summary');

    // Fase 8+ akan mendaftarkan endpoint polling lain di sini, mis.:
    // Route::get('/incident/active-summary', [Incident\IncidentPollingController::class, 'activeSummary'])->name('incident.active-summary');
});
