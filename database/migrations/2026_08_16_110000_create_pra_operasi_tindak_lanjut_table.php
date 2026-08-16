<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 (Saat Operasi) — catatan tindak lanjut supervisor terhadap status
 * Fit-to-Continue seorang operator pada tanggal tertentu (mis. "sudah ditarik
 * dari unit & diistirahatkan"). Data lokal aplikasi, bukan dari hse_automation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pra_operasi_tindak_lanjut', function (Blueprint $table) {
            $table->id();
            $table->string('kode_sid', 20);
            $table->date('tanggal');
            $table->string('status_saat_ditandai', 20)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kode_sid', 'tanggal'], 'idx_pra_operasi_tindak_lanjut_sid_tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pra_operasi_tindak_lanjut');
    }
};
