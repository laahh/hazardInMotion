<?php

declare(strict_types=1);

use App\Http\Controllers\Besigma\BesigmaConnectionTestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modul Besigma — tes koneksi jumphost MySQL
|--------------------------------------------------------------------------
| Akses besigma_db lewat SSH tunnel manual (setup-ssh-tunnel-besigma.bat, port 3307).
| Di-require di dalam grup middleware 'auth' pada routes/web.php.
*/

Route::prefix('besigma')
    ->name('besigma.')
    ->group(function (): void {
        Route::get('/connection-test', [BesigmaConnectionTestController::class, 'index'])
            ->name('connection-test');
    });
