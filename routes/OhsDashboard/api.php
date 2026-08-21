<?php

declare(strict_types=1);

use App\Http\Controllers\OhsDashboard\Api\AdminController;
use App\Http\Controllers\OhsDashboard\Api\CalendarController;
use App\Http\Controllers\OhsDashboard\Api\DashboardController;
use App\Http\Controllers\OhsDashboard\Api\EmployeeController;
use App\Http\Controllers\OhsDashboard\Api\EventController;
use App\Http\Controllers\OhsDashboard\Api\InitController;
use App\Http\Controllers\OhsDashboard\Api\LeaveController;
use App\Http\Controllers\OhsDashboard\Api\TrackerController;
use Illuminate\Support\Facades\Route;

Route::get('/init', InitController::class)->name('init');
Route::get('/employees/search', [EmployeeController::class, 'search'])->name('employees.search');

Route::post('/dashboard/overview', [DashboardController::class, 'overview'])->name('dashboard.overview');

Route::get('/leave/history', [LeaveController::class, 'history'])->name('leave.history');
Route::post('/leave/check-overlap', [LeaveController::class, 'checkOverlap'])->name('leave.check-overlap');
Route::post('/leave/create', [LeaveController::class, 'create'])->name('leave.create');

Route::post('/calendar/range', [CalendarController::class, 'range'])->name('calendar.range');

Route::post('/events/create', [EventController::class, 'create'])->name('events.create');
Route::post('/events/update', [EventController::class, 'update'])->name('events.update');
Route::post('/events/readiness', [EventController::class, 'readiness'])->name('events.readiness');
Route::post('/events/maker-data', [EventController::class, 'makerData'])->name('events.maker-data');
Route::get('/events/checkin-info', [EventController::class, 'checkinInfo'])->name('events.checkin-info');
Route::post('/events/checkin', [EventController::class, 'checkin'])->name('events.checkin');
Route::get('/events/attendance', [EventController::class, 'attendance'])->name('events.attendance');
Route::get('/events/minutes', [EventController::class, 'minutes'])->name('events.minutes');
Route::post('/events/minutes', [EventController::class, 'saveMinutes'])->name('events.minutes.save');
Route::post('/events/action-items/add', [EventController::class, 'addActionItem'])->name('events.action-items.add');
Route::post('/events/action-items/status', [EventController::class, 'updateActionItemStatus'])->name('events.action-items.status');

Route::post('/tracker/create', [TrackerController::class, 'create'])->name('tracker.create');
Route::post('/tracker/update-details', [TrackerController::class, 'updateDetails'])->name('tracker.update-details');
Route::post('/tracker/data', [TrackerController::class, 'data'])->name('tracker.data');
Route::post('/tracker/update-subtask', [TrackerController::class, 'updateSubTask'])->name('tracker.update-subtask');
Route::post('/tracker/update', [TrackerController::class, 'update'])->name('tracker.update');
Route::get('/tracker/log', [TrackerController::class, 'log'])->name('tracker.log');
Route::get('/tracker/subtask-log', [TrackerController::class, 'subtaskLog'])->name('tracker.subtask-log');

Route::get('/admin/email-settings', [AdminController::class, 'emailSettings'])->name('admin.email-settings');
Route::post('/admin/email-settings', [AdminController::class, 'saveEmailSettings'])->name('admin.email-settings.save');
Route::post('/admin/email-send', [AdminController::class, 'emailSend'])->name('admin.email-send');
Route::post('/admin/email-test', [AdminController::class, 'emailTest'])->name('admin.email-test');
Route::post('/admin/overdue-reminder-send', [AdminController::class, 'overdueReminderSend'])->name('admin.overdue-reminder-send');
Route::post('/admin/hse-sync-now', [AdminController::class, 'hseSyncNow'])->name('admin.hse-sync-now');
Route::post('/admin/install-cron', [AdminController::class, 'installCron'])->name('admin.install-cron');
Route::post('/admin/remove-cron', [AdminController::class, 'removeCron'])->name('admin.remove-cron');
