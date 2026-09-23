<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "8. Checklist pemeriksaan" per jenis alat (komponen yang diperiksa + kriterianya).
 * TIDAK dieksekusi otomatis — jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_inventory_tool_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_master_id')->constrained('pnc_monitoring_inventory_tool_master', 'tool_master_id', 'fk_inv_checklist_toolmaster')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('komponen_diperiksa', 200);
            $table->text('kriteria_pemeriksaan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_tool_checklist_items');
    }
};
