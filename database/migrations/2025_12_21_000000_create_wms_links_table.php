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
        Schema::create('wms_links', function (Blueprint $table) {
            $table->id();
            $table->string('location_name');
            $table->text('wms_link');
            $table->integer('week');
            $table->integer('year');
            $table->timestamps();
            
            // Index untuk performa query
            $table->index(['year', 'week']);
            $table->index('location_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wms_links');
    }
};

