<?php

use App\Http\Controllers\SidMeetingAttendanceController;
use App\Http\Controllers\SidMeetingController;
use Illuminate\Support\Facades\Route;

Route::get('/attendance/{qrToken}/lookup', [SidMeetingAttendanceController::class, 'lookup'])
    ->middleware('throttle:60,1')
    ->name('sid-meeting.attendance.lookup');
Route::get('/attendance/{qrToken}/photo-proxy', [SidMeetingAttendanceController::class, 'photoProxy'])
    ->middleware('throttle:60,1')
    ->name('sid-meeting.attendance.photo-proxy');
Route::get('/attendance/{qrToken}', [SidMeetingAttendanceController::class, 'form'])->name('sid-meeting.attendance.form');
Route::post('/attendance/{qrToken}', [SidMeetingAttendanceController::class, 'submit'])->name('sid-meeting.attendance.submit');

Route::middleware(['auth'])->prefix('sid-meeting')->name('sid-meeting.')->group(function (): void {
    Route::get('/api/bootstrap', [SidMeetingController::class, 'apiBootstrap'])->name('api.bootstrap');
    Route::get('/api/form-options', [SidMeetingController::class, 'apiFormOptions'])->name('api.form-options');
    Route::get('/api/stats', [SidMeetingController::class, 'apiStats'])->name('api.stats');
    Route::get('/api/events/data', [SidMeetingController::class, 'apiEventsData'])->name('api.events.data');
    Route::get('/api/events/list', [SidMeetingController::class, 'apiEventsList'])->name('api.events.list');
    Route::get('/api/events/{event}', [SidMeetingController::class, 'apiEventDetail'])->name('api.events.show');
    Route::get('/api/companies/options', [SidMeetingController::class, 'apiCompaniesOptions'])->name('api.companies.options');
    Route::get('/api/companies/data', [SidMeetingController::class, 'apiCompaniesData'])->name('api.companies.data');
    Route::get('/api/reports/filters', [SidMeetingController::class, 'apiReportFilters'])->name('api.reports.filters');
    Route::get('/api/reports/attendance-data', [SidMeetingController::class, 'apiReportAttendanceData'])->name('api.reports.attendance-data');
    Route::get('/api/reports/minutes-data', [SidMeetingController::class, 'apiReportMinutesData'])->name('api.reports.minutes-data');
    Route::get('/api/minutes-management/list', [SidMeetingController::class, 'apiMinutesManagementList'])->name('api.minutes-management.list');
    Route::get('/api/minutes-management/events/{event}', [SidMeetingController::class, 'apiMinutesManagementDetail'])->name('api.minutes-management.detail');
    Route::get('/api/employees/by-sid/{kodeSid}', [SidMeetingController::class, 'apiEmployeeBySid'])->name('api.employees.by-sid');
    Route::post('/api/minutes-management/issues/{issue}/status', [SidMeetingController::class, 'apiUpdateMinuteIssueStatus'])->name('api.minutes-management.issue.status');
    Route::post('/api/minutes-management/issues/{issue}/close', [SidMeetingController::class, 'apiCloseMinuteIssue'])->name('api.minutes-management.issue.close');
    Route::post('/api/companies/{company}/sites', [SidMeetingController::class, 'apiToggleCompanySite'])->name('api.companies.sites.toggle');
    Route::post('/api/events', [SidMeetingController::class, 'apiStoreEvent'])->name('api.events.store');
    Route::post('/api/companies', [SidMeetingController::class, 'apiStoreCompany'])->name('api.companies.store');
    Route::post('/api/meeting-types', [SidMeetingController::class, 'apiStoreMeetingType'])->name('api.meeting-types.store');
    Route::delete('/api/meeting-types/{meetingType}', [SidMeetingController::class, 'apiDestroyMeetingType'])->name('api.meeting-types.destroy');
    Route::post('/api/attendance', [SidMeetingController::class, 'apiStoreAttendance'])->name('api.attendance.store');
    Route::post('/api/events/{event}/minutes', [SidMeetingController::class, 'apiSaveMinutes'])->name('api.events.minutes.store');
    Route::post('/api/sync', [SidMeetingController::class, 'apiSync'])->name('api.sync');

    Route::get('/', [SidMeetingController::class, 'dashboard'])->name('dashboard');

    Route::get('/sites', [SidMeetingController::class, 'dashboard'])->name('sites.index');
    Route::post('/sites', [SidMeetingController::class, 'storeSite'])->name('sites.store');
    Route::put('/sites/{site}', [SidMeetingController::class, 'updateSite'])->name('sites.update');
    Route::delete('/sites/{site}', [SidMeetingController::class, 'destroySite'])->name('sites.destroy');

    Route::get('/meeting-types', [SidMeetingController::class, 'dashboard'])->name('meeting-types.index');
    Route::post('/meeting-types', [SidMeetingController::class, 'storeMeetingType'])->name('meeting-types.store');
    Route::put('/meeting-types/{meetingType}', [SidMeetingController::class, 'updateMeetingType'])->name('meeting-types.update');
    Route::delete('/meeting-types/{meetingType}', [SidMeetingController::class, 'destroyMeetingType'])->name('meeting-types.destroy');

    Route::get('/companies', [SidMeetingController::class, 'dashboard'])->name('companies.index');
    Route::post('/companies', [SidMeetingController::class, 'storeCompany'])->name('companies.store');
    Route::put('/companies/{company}', [SidMeetingController::class, 'updateCompany'])->name('companies.update');
    Route::delete('/companies/{company}', [SidMeetingController::class, 'destroyCompany'])->name('companies.destroy');

    Route::get('/employees', [SidMeetingController::class, 'dashboard'])->name('employees.index');
    Route::post('/employees', [SidMeetingController::class, 'storeEmployee'])->name('employees.store');
    Route::put('/employees/{employee}', [SidMeetingController::class, 'updateEmployee'])->name('employees.update');
    Route::delete('/employees/{employee}', [SidMeetingController::class, 'destroyEmployee'])->name('employees.destroy');

    Route::get('/events', [SidMeetingController::class, 'dashboard'])->name('events.index');
    Route::post('/events', [SidMeetingController::class, 'storeEvent'])->name('events.store');
    Route::get('/events/{event}', [SidMeetingController::class, 'showEvent'])->name('events.show');
    Route::post('/events/{event}/close', [SidMeetingController::class, 'closeEvent'])->name('events.close');
    Route::post('/events/{event}/manual-attendance', [SidMeetingController::class, 'manualAttendance'])->name('events.manual-attendance');
    Route::post('/events/{event}/minutes', [SidMeetingController::class, 'saveMinutes'])->name('events.minutes.save');
    Route::post('/events/{event}/issues', [SidMeetingController::class, 'storeIssue'])->name('events.issues.store');
    Route::delete('/issues/{issue}', [SidMeetingController::class, 'deleteIssue'])->name('issues.destroy');

    Route::get('/reports', [SidMeetingController::class, 'dashboard'])->name('reports.index');
    Route::get('/reports/export-attendance', [SidMeetingController::class, 'exportAttendanceCsv'])->name('reports.export-attendance');
    Route::get('/reports/export-minutes', [SidMeetingController::class, 'exportMinutesCsv'])->name('reports.export-minutes');

    Route::get('/performance', [SidMeetingController::class, 'dashboard'])->name('performance.index');
    Route::get('/semantic', [SidMeetingController::class, 'dashboard'])->name('semantic.index');
    Route::get('/semantic/export', [SidMeetingController::class, 'exportSimilarityCsv'])->name('semantic.export');
});
