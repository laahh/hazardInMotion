<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_incident_victims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('incident_id');
            $table->string('name')->nullable(); // bisa belum diketahui saat laporan awal
            $table->string('condition')->default('luka_ringan'); // selamat, luka_ringan, luka_berat, meninggal
            $table->text('details')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('er_incidents')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_incident_victims');
    }
};
