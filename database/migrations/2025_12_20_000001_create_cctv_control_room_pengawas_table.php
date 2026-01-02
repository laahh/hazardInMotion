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
        Schema::create('cctv_control_room_pengawas', function (Blueprint $table) {
            $table->id();
            $table->string('control_room')->nullable();
            $table->string('nama_pengawas')->nullable();
            $table->string('email_pengawas')->nullable();
            $table->string('no_hp_pengawas')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            
            // Index untuk performa query
            $table->index('control_room');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cctv_control_room_pengawas');
    }
};

