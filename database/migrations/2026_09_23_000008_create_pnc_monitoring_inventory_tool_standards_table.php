<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "4. Standarisasi" per jenis alat (mis. "N/A", "ASME B30.9").
 * TIDAK dieksekusi otomatis — jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_inventory_tool_standards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_master_id')->constrained('pnc_monitoring_inventory_tool_master', 'tool_master_id', 'fk_inv_std_toolmaster')->cascadeOnDelete();
            $table->string('standard_name', 150);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_tool_standards');
    }
};
