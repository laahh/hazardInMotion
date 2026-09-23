<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog JENIS alat (satu baris per "Nama Alat (Standard Name)", mis. "Air Duster Gun") —
 * dokumentasi fungsi/safety/checklist cukup ditulis sekali per jenis alat di sini,
 * dipakai bersama oleh banyak unit fisik di pnc_monitoring_inventory_tool_assets.
 * TIDAK dieksekusi otomatis — jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_inventory_tool_master', function (Blueprint $table) {
            $table->id('tool_master_id');
            $table->foreignId('category_id')->constrained('pnc_monitoring_inventory_categories', 'category_id', 'fk_inv_toolmaster_category');
            $table->string('standard_name', 200);
            $table->string('sub_category', 100)->nullable();
            $table->text('main_function')->nullable();
            $table->string('criticality', 20)->nullable(); // Critical / Major / Minor
            $table->string('risk_class', 20)->nullable(); // Low / Medium / High
            $table->boolean('is_regulated')->default(false);
            $table->text('image_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_tool_master');
    }
};
