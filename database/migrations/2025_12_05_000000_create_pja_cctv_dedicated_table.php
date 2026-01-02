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
        Schema::create('pja_cctv_dedicated', function (Blueprint $table) {
            $table->id();
            $table->string('no')->nullable();
            $table->string('pja')->nullable();
            $table->string('cctv_dedicated')->nullable();
            $table->timestamps();
            
            // Index untuk performa query
            $table->index('no');
            $table->index('pja');
            $table->index('cctv_dedicated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pja_cctv_dedicated');
    }
};

