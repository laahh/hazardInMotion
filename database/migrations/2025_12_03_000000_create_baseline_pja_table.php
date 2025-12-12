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
        if (Schema::hasTable('baseline_pja')) {
            return;
        }

        Schema::create('baseline_pja', function (Blueprint $table) {
            $table->id();
            $table->string('site')->nullable();
            $table->string('perusahaan')->nullable();
            $table->string('id_lokasi')->nullable();
            $table->string('lokasi')->nullable();
            $table->string('id_pja')->nullable();
            $table->string('pja')->nullable();
            $table->string('tipe_pja')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baseline_pja');
    }
};

