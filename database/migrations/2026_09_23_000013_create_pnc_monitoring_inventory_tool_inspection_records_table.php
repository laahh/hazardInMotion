<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat inspeksi per unit aset (header). Detail per komponen checklist ada di
 * pnc_monitoring_inventory_inspection_record_details.
 * TIDAK dieksekusi otomatis — jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_inventory_tool_inspection_records', function (Blueprint $table) {
            $table->id('record_id');
            $table->foreignId('asset_id')->constrained('pnc_monitoring_inventory_tool_assets', 'asset_id', 'fk_inv_insprec_asset')->cascadeOnDelete();
            $table->date('inspection_date');
            $table->foreignId('inspector_user_id')->nullable()->constrained('users', 'id', 'fk_inv_insprec_inspector');
            $table->string('overall_result', 20)->nullable(); // Pass/Fail/Conditional
            $table->date('next_due_date')->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_tool_inspection_records');
    }
};
