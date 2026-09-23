<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "9. Keamanan Penggunaan" — daftar do/don't per jenis alat.
 * TIDAK dieksekusi otomatis — jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_inventory_tool_usage_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_master_id')->constrained('pnc_monitoring_inventory_tool_master', 'tool_master_id', 'fk_inv_usagerule_toolmaster')->cascadeOnDelete();
            $table->enum('rule_type', ['do', 'dont']);
            $table->unsignedInteger('sequence');
            $table->text('description');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_tool_usage_rules');
    }
};
