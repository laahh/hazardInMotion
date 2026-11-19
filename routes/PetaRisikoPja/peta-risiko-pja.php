<?php

use App\Http\Controllers\PetaRisiko\PetaRisikoDrmController;
use App\Http\Controllers\PetaRisiko\PetaRisikoFlagController;
use App\Http\Controllers\PetaRisiko\PetaRisikoMasterController;
use App\Http\Controllers\PetaRisiko\PetaRisikoPjaController;
use Illuminate\Support\Facades\Route;

/*
| Peta Risiko PJA (DRM 2.1): HIRA → aktivitas → bahaya-risiko ← SAP, per Site / Group Lokasi / PJA.
| Data dari snapshot `php artisan peta-risiko:sync` (ClickHouse OBDS). Di-require di dalam grup 'auth'.
| Versi lama (impor xlsx) tetap ada di /peta-risiko-pja/arsip.
*/
Route::prefix('peta-risiko-pja')
    ->name('peta-risiko-pja.')
    ->group(function () {
        Route::controller(PetaRisikoDrmController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/data/meta', 'meta')->name('meta');
            Route::get('/data/rincian', 'rincian')->name('rincian');
            Route::get('/unduh/mapping', 'unduhMapping')->name('unduh.mapping');
            Route::get('/prototipe', 'prototipe')->name('prototipe');
            Route::get('/pja/{pja}', 'pja')->where('pja', '[0-9]+')->name('pja');
            Route::get('/pja/{pja}/data', 'pjaData')->where('pja', '[0-9]+')->name('pja.data');
            Route::get('/bahaya/{hid}/sap', 'sapBahaya')->where('hid', '[0-9]+')->middleware('throttle:60,1')->name('bahaya.sap');
            Route::get('/dl/{dl}/sap', 'sapDetailLokasi')->where('dl', '[0-9]+')->middleware('throttle:60,1')->name('dl.sap');
        });

        // Master mapping DRM 2.1 (koreksi baseline → PJA), berlaku pada sync berikutnya.
        // Master per baris (Item Sumber OBS / OAK / Ketidaksesuaian per baris HIRA) — acuan utama, didaftarkan sebelum /{tabel}.
        Route::prefix('master/baris')->name('master.baris.')->controller(\App\Http\Controllers\PetaRisiko\PetaRisikoMasterBarisController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/{ri}/label', 'tambah')->whereNumber('ri')->middleware('throttle:120,1')->name('tambah');
            Route::post('/label/{jenis}/{id}/hapus', 'hapus')->where(['jenis' => 'teks|label|OBS|OAK|KET'])->whereNumber('id')->middleware('throttle:120,1')->name('hapus');
        });

        // Peta korelasi master (graf grup lokasi ↔ DL ↔ PJA ↔ aktivitas ↔ kelompok ↔ bahaya ↔ label SAP).
        Route::prefix('korelasi')->name('korelasi.')->controller(\App\Http\Controllers\PetaRisiko\PetaRisikoKorelasiController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/data', 'data')->middleware('throttle:120,1')->name('data');
            Route::get('/cari', 'cari')->middleware('throttle:120,1')->name('cari');
            Route::get('/simpul', 'simpul')->middleware('throttle:120,1')->name('simpul');
            Route::post('/simpan', 'simpan')->middleware('throttle:60,1')->name('simpan');
        });

        // Master grup lokasi & aktivitas (M05 per aktivitas + kelompok aktivitas M02), didaftarkan sebelum /{tabel}.
        Route::prefix('master/grup')->name('master.grup.')->controller(\App\Http\Controllers\PetaRisiko\PetaRisikoMasterGrupController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/{gid}/tambah', 'tambah')->whereNumber('gid')->middleware('throttle:60,1')->name('tambah');
            Route::post('/{gid}/baris', 'ubahBaris')->whereNumber('gid')->middleware('throttle:60,1')->name('baris');
            Route::post('/aktivitas/{aid}/kelompok', 'kelompok')->whereNumber('aid')->middleware('throttle:60,1')->name('kelompok');
        });

        Route::prefix('master')->name('master.')->controller(PetaRisikoMasterController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/log', 'log')->name('log');
            Route::post('/{tabel}', 'simpan')->where('tabel', '[a-z_]+')->middleware('throttle:60,1')->name('simpan');
            Route::post('/{tabel}/{id}/hapus', 'hapus')->where(['tabel' => '[a-z_]+', 'id' => '[A-Za-z0-9]+'])->middleware('throttle:60,1')->name('hapus');
        });

        Route::controller(PetaRisikoFlagController::class)->group(function () {
            Route::get('/pja/{pja}/flag', 'index')->where('pja', '[0-9]+')->name('flag.index');
            Route::post('/pja/{pja}/flag', 'store')->where('pja', '[0-9]+')->middleware('throttle:30,1')->name('flag.store');
            Route::post('/flag/{flag}/review', 'review')->whereNumber('flag')->middleware('throttle:30,1')->name('flag.review');
        });

        Route::prefix('arsip')->name('arsip.')->controller(PetaRisikoPjaController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{pja}', 'show')->where('pja', '[a-z0-9-]+')->name('show');
        });
    });
