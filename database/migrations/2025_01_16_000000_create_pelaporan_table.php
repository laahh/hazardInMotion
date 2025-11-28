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
        Schema::create('pelaporan', function (Blueprint $table) {
            $table->id();
            $table->string('verification')->nullable();
            $table->string('validator_data1')->nullable();
            $table->string('name_tools_observation')->nullable();
            $table->string('tobe_concerned_hazard_data1')->nullable();
            $table->string('gr_data1')->nullable();
            $table->text('catatan')->nullable();
            $table->string('blindspot_terlapor_bc')->nullable();
            $table->timestamp('hour_of_tanggal_pembuatan')->nullable();
            $table->string('nama')->nullable();
            $table->string('perusahaan_pelapor')->nullable();
            $table->string('nama_pic')->nullable();
            $table->string('perusahaan_pic')->nullable();
            $table->string('nama_site')->nullable();
            $table->string('nama_lokasi')->nullable();
            $table->string('nama_detail_lokasi')->nullable();
            $table->string('gr_related')->nullable();
            $table->string('ketidaksesuaian')->nullable();
            $table->string('subketidaksesuaian')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('keyword')->nullable();
            $table->string('status')->nullable();
            $table->text('url_photo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pelaporan');
    }
};

