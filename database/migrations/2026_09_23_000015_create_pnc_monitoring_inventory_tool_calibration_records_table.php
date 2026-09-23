<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat kalibrasi per unit aset.
 * TIDAK dieksekusi otomatis — jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_inventory_tool_calibration_records', function (Blueprint $table) {
            $table->id('record_id');
            $table->foreignId('asset_id')->constrained('pnc_monitoring_inventory_tool_assets', 'asset_id', 'fk_inv_calibrec_asset')->cascadeOnDelete();
            $table->date('calibration_date');
            $table->string('certificate_no', 100)->nullable();
            $table->string('standard_method', 150)->nullable();
            $table->string('result', 20)->nullable();
            $table->date('next_due_date')->nullable();
            $table->text('certificate_file_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_tool_calibration_records');
    }
};
