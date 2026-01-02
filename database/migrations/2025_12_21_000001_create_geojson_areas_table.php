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
        Schema::create('geojson_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['area_kerja', 'area_cctv']);
            $table->text('geojson_data'); // JSON data dari file GeoJSON
            $table->string('file_name')->nullable(); // Nama file yang diupload
            $table->integer('week');
            $table->integer('year');
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Index untuk performa query
            $table->index(['type', 'year', 'week']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geojson_areas');
    }
};

