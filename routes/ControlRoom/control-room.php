<?php

declare(strict_types=1);

use App\Http\Controllers\ControlRoom\AttendanceController;
use App\Http\Controllers\ControlRoom\DashboardController;
use App\Http\Controllers\ControlRoom\ScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes modul Control Room (plan-OCR.md)
|--------------------------------------------------------------------------
|
| Didaftarkan dari routes/web.php lewat:
|   Route::prefix('control-room')->name('control-room.')->group(base_path('routes/ControlRoom/control-room.php'));
|
| Route Fase 4 (Data SAP / Data Quality) belum didaftarkan di sini — menunggu
| T0.1 (verifikasi mv_inspeksi_hazard dkk), lihat plan-OCR.md 0.5 poin 2.
*/

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('schedule')->name('schedule.')->group(function (): void {
        Route::get('/changes', [ScheduleController::class, 'changes'])->name('changes');
        Route::post('/bulk', [ScheduleController::class, 'storeBulk'])->name('bulk');
        Route::post('/copy', [ScheduleController::class, 'copy'])->name('copy');
        Route::post('/{week}/lock', [ScheduleController::class, 'lock'])->name('lock');
        Route::put('/{schedule}', [ScheduleController::class, 'update'])->name('update');
        Route::delete('/{schedule}', [ScheduleController::class, 'destroy'])->name('destroy');
    });
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');

    Route::prefix('attendance')->name('attendance.')->group(function (): void {
        // GET /check-in HARUS didaftarkan sebelum GET /{attendance} —
        // kalau tidak, "check-in" akan tertangkap sebagai {attendance} id.
        Route::get('/check-in', [AttendanceController::class, 'showCheckIn'])->name('check-in.form');
        Route::post('/check-in', [AttendanceController::class, 'checkIn'])->name('check-in');
        Route::get('/{attendance}', [AttendanceController::class, 'show'])->name('show');
        Route::put('/{attendance}', [AttendanceController::class, 'update'])->name('update');
    });
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
});
