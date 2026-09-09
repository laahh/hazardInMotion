<?php

declare(strict_types=1);

use App\Http\Controllers\ControlRoom\AttendanceController;
use App\Http\Controllers\ControlRoom\ControlRoomQrCodeController;
use App\Http\Controllers\ControlRoom\ControlRoomSapController;
use App\Http\Controllers\ControlRoom\ControlRoomTutorialController;
use App\Http\Controllers\ControlRoom\DashboardController;
use App\Http\Controllers\ControlRoom\DataQualityController;
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
| Data SAP = tabel laporan MV SAP personil jaga minggu terpilih (read-only).
*/

Route::prefix('attendance')->name('attendance.')->middleware('throttle:30,1')->group(function (): void {
    Route::get('/form', [AttendanceController::class, 'showForm'])->name('form');
    Route::post('/form', [AttendanceController::class, 'storeForm'])->middleware('throttle:6,1')->name('form.store');
    Route::get('/personnel', [AttendanceController::class, 'lookupPersonnel'])->name('personnel');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/sap-detail', [DashboardController::class, 'sapDetail'])->name('dashboard.sap-detail');
    Route::get('/dashboard/sap-photos', [DashboardController::class, 'sapPhotos'])->name('dashboard.sap-photos');

    Route::prefix('schedule')->name('schedule.')->group(function (): void {
        Route::get('/excel-template', [ScheduleController::class, 'downloadExcelTemplate'])->name('excel-template');
        Route::post('/excel-import', [ScheduleController::class, 'importExcel'])->name('excel-import');
        Route::get('/changes', [ScheduleController::class, 'changes'])->name('changes');
        Route::get('/events', [ScheduleController::class, 'events'])->name('events');
        Route::post('/bulk', [ScheduleController::class, 'storeBulk'])->name('bulk');
        Route::post('/copy', [ScheduleController::class, 'copy'])->name('copy');
        Route::post('/destroy-week', [ScheduleController::class, 'destroyWeek'])->name('destroy-week');
        Route::post('/lock', [ScheduleController::class, 'lock'])->name('lock');
        Route::get('/{schedule}/changes', [ScheduleController::class, 'planChanges'])->whereNumber('schedule')->name('plan-changes');
        Route::put('/{schedule}', [ScheduleController::class, 'update'])->whereNumber('schedule')->name('update');
        Route::delete('/{schedule}', [ScheduleController::class, 'destroy'])->whereNumber('schedule')->name('destroy');
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
    Route::get('/data-quality', [DataQualityController::class, 'index'])->name('data-quality.index');
    Route::get('/sap', [ControlRoomSapController::class, 'index'])->name('sap.index');
    Route::get('/qr-code', [ControlRoomQrCodeController::class, 'index'])->name('qr-code.index');
    Route::get('/tutorial/embed', [ControlRoomTutorialController::class, 'embed'])->name('tutorial.embed');
    Route::get('/tutorial', [ControlRoomTutorialController::class, 'index'])->name('tutorial.index');
});
