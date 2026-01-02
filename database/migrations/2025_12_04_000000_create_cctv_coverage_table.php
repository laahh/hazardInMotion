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
        Schema::create('cctv_coverage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_cctv');
            $table->string('coverage_lokasi')->nullable();
            $table->string('coverage_detail_lokasi')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('id_cctv')->references('id')->on('cctv_data_bmo2')->onDelete('cascade');
            
            // Index untuk performa query
            $table->index('id_cctv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cctv_coverage');
    }
};

