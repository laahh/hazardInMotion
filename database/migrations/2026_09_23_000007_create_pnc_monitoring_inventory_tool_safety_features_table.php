<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "3. Pengaman alat" per jenis alat (mis. "Trigger Lock").
 * TIDAK dieksekusi otomatis — jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_inventory_tool_safety_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_master_id')->constrained('pnc_monitoring_inventory_tool_master', 'tool_master_id', 'fk_inv_safety_toolmaster')->cascadeOnDelete();
            $table->string('feature_name', 150);
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_tool_safety_features');
    }
};
