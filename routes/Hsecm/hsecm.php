<?php

declare(strict_types=1);

use App\Http\Controllers\Hsecm\HsecmDashboardController;
use App\Http\Controllers\Hsecm\HsecmDatasetController;
use App\Http\Controllers\Hsecm\HsecmGapEvaluasiController;
use App\Http\Controllers\Hsecm\HsecmGapPerulanganController;
use App\Http\Controllers\Hsecm\HsecmPjoActionController;
use App\Http\Controllers\Hsecm\HsecmTasklistManageController;
use App\Http\Controllers\Hsecm\HsecmTasklistPublicController;
use App\Http\Controllers\Hsecm\HsecmWaNotifyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Publik — tanpa login (semua halaman view Daily Monitoring & Intervensi)
|--------------------------------------------------------------------------
*/
Route::prefix('hsecm')
    ->name('hsecm.')
    ->group(function (): void {
        Route::redirect('/', '/hsecm/dashboard')->name('home');
        Route::get('/dashboard', [HsecmDashboardController::class, 'index'])->name('dashboard');
        Route::get('/gap-perulangan', [HsecmGapPerulanganController::class, 'index'])->name('gap-perulangan');
        Route::get('/gap-evaluasi', [HsecmGapEvaluasiController::class, 'index'])->name('gap-evaluasi');
        Route::get('/pjo-action', [HsecmPjoActionController::class, 'index'])->name('pjo-action');
        Route::get('/wa-notify', [HsecmWaNotifyController::class, 'index'])->name('wa-notify.index');
        Route::get('/tasklist', [HsecmTasklistManageController::class, 'index'])->name('tasklist.index');
        Route::get('/tasklist/manage/{id}', [HsecmTasklistManageController::class, 'manage'])
            ->whereNumber('id')
            ->name('tasklist.manage');
        Route::get('/datasets/{dataset}', [HsecmDatasetController::class, 'show'])
            ->where('dataset', 'sap-rfid|coverage-cctv|tbc-blindspot|task-overdue|task-submitted|ikk-work-permit|implementasi-ikk|aggregator|fatigue|sumber-rfid|hazard-rootcause')
            ->name('datasets.show');

        Route::get('/tasklist/open', [HsecmTasklistPublicController::class, 'open'])
            ->name('tasklist.open');
        Route::get('/tasklist/{token}', [HsecmTasklistPublicController::class, 'show'])
            ->where('token', '[A-Za-z0-9]{32,64}')
            ->name('tasklist.show');
        Route::post('/tasklist/{token}/submit', [HsecmTasklistPublicController::class, 'submit'])
            ->middleware('throttle:20,1')
            ->where('token', '[A-Za-z0-9]{32,64}')
            ->name('tasklist.submit');
    });

/*
|--------------------------------------------------------------------------
| Dilindungi auth — aksi tulis (kirim WA/email, CRUD penerima, ACC/tolak)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('hsecm')
    ->name('hsecm.')
    ->group(function (): void {
        Route::post('/wa-notify/recipients', [HsecmWaNotifyController::class, 'storeRecipient'])
            ->name('wa-notify.recipients.store');
        Route::put('/wa-notify/recipients/{id}', [HsecmWaNotifyController::class, 'updateRecipient'])
            ->name('wa-notify.recipients.update');
        Route::delete('/wa-notify/recipients/{id}', [HsecmWaNotifyController::class, 'destroyRecipient'])
            ->name('wa-notify.recipients.destroy');
        Route::post('/wa-notify/{index}/send', [HsecmWaNotifyController::class, 'send'])
            ->whereNumber('index')
            ->name('wa-notify.send');
        Route::post('/wa-notify/{index}/send-email', [HsecmWaNotifyController::class, 'sendEmail'])
            ->whereNumber('index')
            ->name('wa-notify.send-email');
        Route::post('/wa-notify/send-email-bulk', [HsecmWaNotifyController::class, 'sendEmailBulk'])
            ->name('wa-notify.send-email-bulk');
        Route::post('/wa-notify/send-shift-email', [HsecmWaNotifyController::class, 'sendShiftEmail'])
            ->name('wa-notify.send-shift-email');

        Route::post('/tasklist/manage/{id}/approve-bulk', [HsecmTasklistManageController::class, 'approveBulk'])
            ->whereNumber('id')
            ->name('tasklist.approve-bulk');
        Route::post('/tasklist/manage/{id}/reject-bulk', [HsecmTasklistManageController::class, 'rejectBulk'])
            ->whereNumber('id')
            ->name('tasklist.reject-bulk');
        Route::post('/tasklist/items/{itemId}/approve', [HsecmTasklistManageController::class, 'approve'])
            ->whereNumber('itemId')
            ->name('tasklist.items.approve');
        Route::post('/tasklist/items/{itemId}/reject', [HsecmTasklistManageController::class, 'reject'])
            ->whereNumber('itemId')
            ->name('tasklist.items.reject');
    });
