<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pola roster terkompilasi — satu baris per karyawan per tahun.
 *
 * Inilah tabel yang dibaca dashboard. Satu tahun kerja seorang karyawan
 * dipadatkan jadi SATU string (1 karakter = 1 hari: P/M/o), sehingga halaman
 * cukup membaca ±8 ribu baris, bukan 1,2 juta baris fakta harian. Tanpa lapis
 * ini, setiap pemuatan dashboard harus meng-agregasi ulang tabel fakta.
 *
 * Karakter ke-0 SELALU 'hari_pertama'. Kolom itu disimpan eksplisit (tidak
 * diasumsikan 1 Januari) supaya tidak ada aturan tersembunyi antara penulis
 * dan pembaca pola.
 *
 * Normalisasi o -> c (Cuti) TIDAK disimpan di sini; itu dikerjakan rule
 * engine saat evaluasi, supaya ambang 'off_ke_cuti' bisa diubah di config
 * tanpa perlu kompilasi ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dms_roster_pola', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('karyawan_id');
            $table->unsignedSmallInteger('tahun');

            // Maksimum 366 (tahun kabisat).
            $table->string('pola', 366);

            $table->date('hari_pertama');
            $table->date('hari_terakhir');

            // Jumlah hari yang benar-benar punya scan — dipakai memeriksa
            // kelengkapan data tanpa memindai tabel fakta.
            $table->unsignedSmallInteger('hari_ada_scan')->default(0);

            $table->timestamp('dikompilasi_pada')->nullable();

            $table->unique(['karyawan_id', 'tahun'], 'uq_drp_karyawan_tahun');
            $table->index('tahun', 'idx_drp_tahun');

            $table->foreign('karyawan_id', 'fk_drp_karyawan')
                ->references('id')->on('dms_roster_karyawan')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dms_roster_pola');
    }
};
