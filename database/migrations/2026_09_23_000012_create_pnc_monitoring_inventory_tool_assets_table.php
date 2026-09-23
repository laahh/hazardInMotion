<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNIT FISIK alat — bisa banyak unit per jenis alat (tool_master), masing-masing
 * punya Asset ID/Serial/lokasi/status sendiri.
 * TIDAK dieksekusi otomatis — jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_inventory_tool_assets', function (Blueprint $table) {
            $table->id('asset_id');
            $table->foreignId('tool_master_id')->constrained('pnc_monitoring_inventory_tool_master', 'tool_master_id', 'fk_inv_asset_toolmaster');
            $table->string('inventory_id', 50)->nullable()->unique(); // Asset ID / barcode / QR
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->unsignedSmallInteger('year_made')->nullable();
            $table->string('power_source', 50)->nullable();
            $table->string('status_availability', 30)->default('Available'); // Available/Checked-out/In Repair/Quarantine/Scrapped
            $table->string('location_detail', 150)->nullable();
            $table->string('condition', 20)->nullable(); // Good/Fair/Poor/Damaged
            $table->string('owner_type', 20)->nullable(); // Company/Rental/Contractor
            $table->foreignId('owner_company_id')->nullable()->constrained('pnc_monitoring_inventory_companies', 'company_id', 'fk_inv_asset_company');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users', 'id', 'fk_inv_asset_user');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status_availability', 'idx_pnc_inv_assets_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_tool_assets');
    }
};
