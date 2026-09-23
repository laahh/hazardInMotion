<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil pemeriksaan per komponen checklist (baris detail dari satu inspection record).
 * TIDAK dieksekusi otomatis — jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_inventory_inspection_record_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('pnc_monitoring_inventory_tool_inspection_records', 'record_id', 'fk_inv_insprecdet_record')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained('pnc_monitoring_inventory_tool_checklist_items', 'id', 'fk_inv_insprecdet_checklist');
            $table->string('result', 20)->nullable(); // OK/Not OK
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_inspection_record_details');
    }
};
