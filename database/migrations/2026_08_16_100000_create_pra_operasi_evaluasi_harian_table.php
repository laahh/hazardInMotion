<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 (Pasca Operasi) — hasil evaluasi harian per operator, "dibekukan" per
 * tanggal supaya tidak dihitung ulang tiap dashboard dibuka, dan bisa ditarik
 * balik sebagai konteks "Evaluasi Kemarin" di dashboard Pra Operasi.
 *
 * Diisi oleh command terjadwal `pra-operasi:evaluate-day` (lihat
 * app/Console/Commands/PraOperasi/EvaluateDayCommand.php), bukan dihitung
 * langsung dari request HTTP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pra_operasi_evaluasi_harian', function (Blueprint $table) {
            $table->id();
            $table->string('kode_sid', 20);
            $table->string('nama', 190)->nullable();
            $table->string('perusahaan', 190)->nullable();
            $table->date('tanggal');
            $table->string('shift', 20)->nullable();
            $table->unsignedSmallInteger('hari_ke')->nullable();

            $table->unsignedTinyInteger('fatigue_score')->nullable();
            $table->string('fatigue_tier', 10)->nullable();
            $table->string('pvt_status', 15)->default('belum');

            $table->unsignedInteger('alert_nyata_count')->default(0);
            $table->unsignedInteger('alert_palsu_count')->default(0);
            $table->unsignedInteger('alert_belum_count')->default(0);

            $table->unsignedInteger('durasi_kerja_menit')->nullable();
            $table->decimal('baseline_zscore', 6, 2)->nullable();

            $table->string('kategori_evaluasi', 20);
            $table->json('alasan')->nullable();

            $table->timestamps();

            $table->unique(['kode_sid', 'tanggal'], 'uq_pra_operasi_evaluasi_sid_tanggal');
            $table->index('tanggal', 'idx_pra_operasi_evaluasi_tanggal');
            $table->index('kategori_evaluasi', 'idx_pra_operasi_evaluasi_kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pra_operasi_evaluasi_harian');
    }
};
