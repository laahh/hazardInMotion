<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 (Inspeksi) sengaja membuat kolom er_inspection_findings.work_order_id
 * TANPA foreign key karena tabel er_work_orders belum ada. Sekarang tabelnya
 * sudah dibuat (lihat 2026_08_23_500001_create_er_work_orders_table.php),
 * jadi constraint-nya ditambahkan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('er_inspection_findings', function (Blueprint $table) {
            $table->foreign('work_order_id')->references('id')->on('er_work_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('er_inspection_findings', function (Blueprint $table) {
            $table->dropForeign(['work_order_id']);
        });
    }
};
