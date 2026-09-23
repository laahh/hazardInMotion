<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spesifikasi teknis yang sangat bervariasi antar kategori (WLL/SWL, Calibration
 * Interval, Hazard Class, dll) disimpan sebagai EAV di sini per jenis alat —
 * supaya tidak perlu tabel terpisah untuk tiap kategori A-E.
 * TIDAK dieksekusi otomatis — jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_inventory_tool_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_master_id')->constrained('pnc_monitoring_inventory_tool_master', 'tool_master_id', 'fk_inv_attr_toolmaster')->cascadeOnDelete();
            $table->string('attribute_name', 150);
            $table->string('attribute_value', 255)->nullable();

            $table->unique(['tool_master_id', 'attribute_name'], 'pnc_inv_tool_attr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_tool_attributes');
    }
};
