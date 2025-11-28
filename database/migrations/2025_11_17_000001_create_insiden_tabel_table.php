<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('insiden_tabel', function (Blueprint $table) {
            $table->id();
            $table->string('no_kecelakaan');
            $table->string('kode_be_investigasi')->nullable();
            $table->string('status_lpi')->nullable();
            $table->date('target_penyelesaian_lpi')->nullable();
            $table->date('actual_penyelesaian_lpi')->nullable();
            $table->string('ketepatan_waktu_lpi')->nullable();
            $table->date('tanggal')->nullable();
            $table->unsignedTinyInteger('bulan')->nullable();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->unsignedTinyInteger('minggu_ke')->nullable();
            $table->string('hari')->nullable();
            $table->unsignedTinyInteger('jam')->nullable();
            $table->unsignedTinyInteger('menit')->nullable();
            $table->string('shift')->nullable();
            $table->string('perusahaan')->nullable();
            $table->decimal('latitude', 15, 8)->nullable();
            $table->decimal('longitude', 15, 8)->nullable();
            $table->string('departemen')->nullable();
            $table->string('site')->nullable();
            $table->string('lokasi')->nullable();
            $table->string('sublokasi')->nullable();
            $table->string('lokasi_spesifik')->nullable();
            $table->string('lokasi_validasi_hsecm')->nullable();
            $table->string('pja')->nullable();
            $table->string('insiden_dalam_site_mining')->nullable();
            $table->string('kategori')->nullable();
            $table->string('injury_status')->nullable();
            $table->text('kronologis')->nullable();
            $table->string('high_potential')->nullable();
            $table->string('alat_terlibat')->nullable();
            $table->string('nama')->nullable();
            $table->string('jabatan')->nullable();
            $table->unsignedTinyInteger('shift_kerja_ke')->nullable();
            $table->unsignedTinyInteger('hari_kerja_ke')->nullable();
            $table->string('npk')->nullable();
            $table->unsignedTinyInteger('umur')->nullable();
            $table->string('range_umur')->nullable();
            $table->unsignedTinyInteger('masa_kerja_perusahaan_tahun')->nullable();
            $table->unsignedTinyInteger('masa_kerja_perusahaan_bulan')->nullable();
            $table->string('range_masa_kerja_perusahaan')->nullable();
            $table->unsignedTinyInteger('masa_kerja_bc_tahun')->nullable();
            $table->unsignedTinyInteger('masa_kerja_bc_bulan')->nullable();
            $table->string('range_masa_kerja_bc')->nullable();
            $table->string('bagian_luka')->nullable();
            $table->decimal('loss_cost', 20, 2)->nullable();
            $table->string('saksi_langsung')->nullable();
            $table->string('atasan_langsung')->nullable();
            $table->string('jabatan_atasan_langsung')->nullable();
            $table->string('kontak')->nullable();
            $table->text('detail_kontak')->nullable();
            $table->string('sumber_kecelakaan')->nullable();
            $table->string('layer')->nullable();
            $table->string('jenis_item_ipls')->nullable();
            $table->string('detail_layer')->nullable();
            $table->string('klasifikasi_layer')->nullable();
            $table->text('keterangan_layer')->nullable();
            $table->string('id_lokasi_insiden')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insiden_tabel');
    }
};

