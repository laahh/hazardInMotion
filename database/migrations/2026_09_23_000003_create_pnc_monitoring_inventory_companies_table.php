<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perusahaan pemilik/penyewa/kontraktor pemegang aset Inventory Tools.
 * TIDAK dieksekusi otomatis — jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_inventory_companies', function (Blueprint $table) {
            $table->id('company_id');
            $table->string('name', 150);
            $table->string('type', 30)->nullable(); // Internal / Rental / Contractor
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_companies');
    }
};
