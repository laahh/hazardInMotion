<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan Hazard dari /isc/maps (intervensi).
 * TIDAK dijalankan otomatis — jalankan `php artisan migrate` manual setelah konfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isc_hazard_reports', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('event_id')->nullable()->constrained('isc_boundary_events')->nullOnDelete();
            $table->foreignId('intervention_id')->nullable()->constrained('isc_interventions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Kredensial input form
            $table->string('username', 100)->nullable()->index();
            $table->string('password')->nullable();

            // Penanggung jawab (sama pola dengan pelapor: SID + NPK + Nama)
            $table->string('perusahaan', 255)->nullable();
            $table->string('pic_sid', 64)->nullable()->index();
            $table->string('pic_npk', 64)->nullable();
            $table->string('pic_nama', 255)->nullable();
            $table->string('pic_jabatan', 255)->nullable();

            // Lokasi
            $table->string('tools_pengamatan', 100)->nullable()->index();
            $table->string('site', 64)->nullable()->index();
            $table->string('lokasi', 255)->nullable();
            $table->string('detail_lokasi', 255)->nullable();
            $table->text('keterangan_lokasi')->nullable();

            // Pelapor — input SID; NPK/Nama/Jabatan dari lookup
            $table->string('sid_pelapor', 64)->index();
            $table->string('npk_pelapor', 64)->nullable();
            $table->string('nama_pelapor', 255)->nullable();
            $table->string('jabatan_pelapor', 255)->nullable();

            // PJA
            $table->string('area_pja_bc', 255)->nullable();
            $table->string('area_pja_mitra', 255)->nullable();

            // Temuan
            $table->string('foto_path')->nullable();
            $table->boolean('is_observasi_area_kritis')->default(false)->index();
            $table->string('ketidaksesuaian', 255)->nullable()->index();
            $table->string('sub_ketidaksesuaian', 255)->nullable();
            $table->string('quick_action', 255)->nullable();
            $table->text('deskripsi_temuan')->nullable();

            $table->string('status', 24)->default('submitted')->index();
            $table->timestamps();

            $table->index(['site', 'status'], 'idx_isc_hazard_reports_site_status');
            $table->index(['sid_pelapor', 'created_at'], 'idx_isc_hazard_reports_sid_created');
            $table->index(['pic_sid', 'created_at'], 'idx_isc_hazard_reports_pic_sid_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isc_hazard_reports');
    }
};
