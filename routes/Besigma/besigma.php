<?php

declare(strict_types=1);

use App\Http\Controllers\Besigma\BesigmaConnectionTestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modul Besigma — tes koneksi jumphost MySQL
|--------------------------------------------------------------------------
| Akses Postgres database `besigma` langsung ke RDS (PG_HOST:PG_PORT), sama pola RFID.
| Connection Laravel: besigma_db (pgsql). Tidak mengubah PG_SSH_DATABASE=hse_automation.
| Di-require di dalam grup middleware 'auth' pada routes/web.php.
*/

Route::prefix('besigma')
    ->name('besigma.')
    ->group(function (): void {
        Route::get('/connection-test', [BesigmaConnectionTestController::class, 'index'])
            ->name('connection-test');
        Route::get('/connection-test.json', [BesigmaConnectionTestController::class, 'index'])
            ->name('connection-test.json');
        Route::get('/connection-test.txt', [BesigmaConnectionTestController::class, 'index'])
            ->name('connection-test.text');
    });
