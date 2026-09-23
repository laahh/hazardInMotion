<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat peminjaman (checkout/return) per unit aset.
 * TIDAK dieksekusi otomatis — jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_inventory_tool_checkout_records', function (Blueprint $table) {
            $table->id('record_id');
            $table->foreignId('asset_id')->constrained('pnc_monitoring_inventory_tool_assets', 'asset_id', 'fk_inv_checkoutrec_asset')->cascadeOnDelete();
            $table->foreignId('borrower_user_id')->nullable()->constrained('users', 'id', 'fk_inv_checkoutrec_borrower');
            $table->date('checkout_date');
            $table->date('expected_return_date')->nullable();
            $table->date('actual_return_date')->nullable();
            $table->string('condition_out', 20)->nullable();
            $table->string('condition_in', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_tool_checkout_records');
    }
};
