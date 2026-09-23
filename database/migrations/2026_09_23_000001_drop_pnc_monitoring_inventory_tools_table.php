<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Skema flat "pnc_monitoring_inventory_tools" (single table) digantikan oleh skema
 * ternormalisasi tool_master + tool_assets + tabel riwayat (lihat migrasi-migrasi
 * berikutnya). Tabel lama kosong (belum ada data produksi), aman dihapus.
 * TIDAK dieksekusi otomatis — jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pnc_monitoring_inventory_tools');
    }

    public function down(): void
    {
        // Skema lama sengaja tidak direkonstruksi — sudah digantikan skema ternormalisasi baru.
    }
};
