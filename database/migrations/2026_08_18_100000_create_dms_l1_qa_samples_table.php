<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sampel QA untuk mengukur false negative L1 — alert yang sudah di-dismiss
 * petugas L1 (l1_context_status = false, dianggap bukan pelanggaran nyata)
 * diambil sampelnya secara acak (ukuran sampel dihitung pakai rumus Slovin)
 * untuk diaudit ulang manual oleh supervisor. Tidak ada data ground-truth
 * soal ini di hse_automation sama sekali — tabel ini yang membangunnya.
 * Data lokal aplikasi, bukan dari hse_automation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dms_l1_qa_samples', function (Blueprint $table) {
            $table->id();
            $table->string('id_alert', 64);
            $table->string('kode_sid', 20)->nullable();
            $table->string('nama_pelanggaran', 100)->nullable();
            $table->string('unit', 60)->nullable();
            $table->string('site', 40)->nullable();
            $table->timestamp('waktu_deteksi')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('margin_of_error', 4, 3)->default(0.05);
            $table->string('verdict', 20)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('audited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('audited_at')->nullable();
            $table->timestamps();

            $table->unique(['id_alert', 'period_start', 'period_end'], 'uq_dms_l1_qa_samples_alert_period');
            $table->index(['period_start', 'period_end'], 'idx_dms_l1_qa_samples_period');
            $table->index('verdict', 'idx_dms_l1_qa_samples_verdict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dms_l1_qa_samples');
    }
};
