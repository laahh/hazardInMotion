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
        Schema::create('cctv_data_bmo2', function (Blueprint $table) {
            $table->id();
            $table->string('site')->nullable();
            $table->string('perusahaan')->nullable();
            $table->string('kategori')->nullable();
            $table->string('no_cctv')->nullable();
            $table->string('nama_cctv')->nullable();
            $table->string('fungsi_cctv')->nullable();
            $table->string('bentuk_instalasi_cctv')->nullable();
            $table->string('jenis')->nullable();
            $table->string('tipe_cctv')->nullable();
            $table->string('radius_pengawasan')->nullable();
            $table->string('jenis_spesifikasi_zoom')->nullable();
            $table->string('lokasi_pemasangan')->nullable();
            $table->string('control_room')->nullable();
            $table->string('status')->nullable();
            $table->string('kondisi')->nullable();
            $table->decimal('longitude', 10, 8)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->string('coverage_lokasi')->nullable();
            $table->string('coverage_detail_lokasi')->nullable();
            $table->string('kategori_area_tercapture')->nullable();
            $table->string('kategori_aktivitas_tercapture')->nullable();
            $table->text('link_akses')->nullable();
            $table->string('user_name')->nullable();
            $table->string('password')->nullable();
            $table->string('connected')->nullable();
            $table->string('mirrored')->nullable();
            $table->string('fitur_auto_alert')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('verifikasi_by_petugas_ocr')->nullable();
            $table->integer('bulan_update')->nullable();
            $table->integer('tahun_update')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cctv_data');
    }
};

