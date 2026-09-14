<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data Commissioning / SPIP untuk PNC Monitoring. TIDAK dieksekusi otomatis —
 * jalankan `php artisan migrate` manual setelah dikonfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_monitoring_commissionings', function (Blueprint $table) {
            $table->id();
            $table->string('site', 100)->nullable();
            $table->string('no_register_spip', 100);
            $table->string('detail_jenis_spip', 150)->nullable();
            $table->string('keterangan_sko', 255)->nullable();
            $table->string('nama_pengawas_teknis', 150)->nullable();
            $table->date('permohonan_dokumen_1')->nullable();
            $table->unsignedTinyInteger('week')->nullable();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->string('pemilik_spip', 150)->nullable();
            $table->string('pengelola_spip', 150)->nullable();
            $table->unsignedInteger('temuan_komisioning')->default(0);
            $table->string('status_komisioning', 100)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('alasan_reject', 255)->nullable();
            $table->string('status', 100)->nullable();
            $table->decimal('performance_sko', 8, 4)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique('no_register_spip', 'pnc_monitoring_commissionings_register_unique');
            $table->index(['tahun', 'week'], 'idx_pnc_comm_tahun_week');
            $table->index('site', 'idx_pnc_comm_site');
            $table->index('pemilik_spip', 'idx_pnc_comm_pemilik');
            $table->index('detail_jenis_spip', 'idx_pnc_comm_detail_jenis');
            $table->index('nama_pengawas_teknis', 'idx_pnc_comm_pengawas');
            $table->index('status', 'idx_pnc_comm_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnc_monitoring_commissionings');
    }
};
