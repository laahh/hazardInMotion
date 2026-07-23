<?php

declare(strict_types=1);

use App\Http\Controllers\Hsecm\HsecmDashboardController;
use App\Http\Controllers\Hsecm\HsecmDatasetController;
use App\Http\Controllers\Hsecm\HsecmPjoActionController;
use App\Http\Controllers\Hsecm\HsecmWaNotifyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('hsecm')
    ->name('hsecm.')
    ->group(function (): void {
        Route::redirect('/', '/hsecm/dashboard')->name('home');
        Route::get('/dashboard', [HsecmDashboardController::class, 'index'])->name('dashboard');
        Route::get('/pjo-action', [HsecmPjoActionController::class, 'index'])->name('pjo-action');
        Route::get('/wa-notify', [HsecmWaNotifyController::class, 'index'])->name('wa-notify.index');
        Route::post('/wa-notify/recipients', [HsecmWaNotifyController::class, 'storeRecipient'])
            ->name('wa-notify.recipients.store');
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
        Route::get('/datasets/{dataset}', [HsecmDatasetController::class, 'show'])
            ->where('dataset', 'sap-rfid|coverage-cctv|tbc-blindspot|task-overdue|task-submitted|ikk-work-permit|aggregator|fatigue|sumber-rfid')
            ->name('datasets.show');
    });
