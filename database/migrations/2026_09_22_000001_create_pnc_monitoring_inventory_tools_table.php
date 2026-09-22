<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Data Inventory Tools untuk PNC Monitoring. TIDAK dieksekusi otomatis —
 * jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_inventory_tools', function (Blueprint $table) {
            $table->id();
            $table->string('upsert_key', 191);
            $table->string('category', 50);
            $table->string('asset_id', 100)->nullable();
            $table->string('nama_alat', 191);
            $table->string('sub_kategori', 150)->nullable();
            $table->string('brand', 150)->nullable();
            $table->string('model', 150)->nullable();
            $table->string('serial_number', 150)->nullable();
            $table->string('tahun_pembuatan', 20)->nullable();
            $table->string('power_source', 50)->nullable();
            $table->string('kapasitas_rating', 191)->nullable();
            $table->string('site', 100)->nullable();
            $table->string('lokasi_detail', 150)->nullable();
            $table->string('status_ketersediaan', 50)->nullable();
            $table->string('condition', 20)->nullable();
            $table->string('pic', 150)->nullable();
            $table->unsignedInteger('qty_on_hand')->default(1);
            $table->boolean('calibration_required')->nullable();
            $table->date('last_calibration_date')->nullable();
            $table->date('calibration_due_date')->nullable();
            $table->boolean('inspection_required')->nullable();
            $table->date('last_inspection_date')->nullable();
            $table->date('next_inspection_due')->nullable();
            $table->string('inspection_result', 30)->nullable();
            $table->boolean('pm_required')->nullable();
            $table->date('last_pm_date')->nullable();
            $table->date('next_pm_due')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique('upsert_key', 'pnc_monitoring_inventory_tools_upsert_key_unique');
            $table->index('category', 'idx_pnc_inventory_category');
            $table->index('site', 'idx_pnc_inventory_site');
            $table->index('status_ketersediaan', 'idx_pnc_inventory_status');
            $table->index('condition', 'idx_pnc_inventory_condition');
            $table->index('calibration_due_date', 'idx_pnc_inventory_calibration_due');
            $table->index('next_inspection_due', 'idx_pnc_inventory_inspection_due');
            $table->index('next_pm_due', 'idx_pnc_inventory_pm_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_tools');
    }
};
