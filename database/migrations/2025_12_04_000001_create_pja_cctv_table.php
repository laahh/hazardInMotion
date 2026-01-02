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
        Schema::create('pja_cctv', function (Blueprint $table) {
            $table->id();
            $table->string('id_pja')->nullable();
            $table->unsignedBigInteger('id_cctv')->nullable();
            $table->timestamps();

            // Foreign key constraint untuk CCTV
            $table->foreign('id_cctv')->references('id')->on('cctv_data_bmo2')->onDelete('cascade');
            
            // Index untuk performa query
            $table->index('id_pja');
            $table->index('id_cctv');
            
            // Unique constraint untuk mencegah duplikasi
            $table->unique(['id_pja', 'id_cctv'], 'unique_pja_cctv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pja_cctv');
    }
};

