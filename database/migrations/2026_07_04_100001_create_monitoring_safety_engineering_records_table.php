<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_safety_engineering_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('row_no')->default(0);

            $table->string('site', 50);
            $table->string('perusahaan', 100);
            $table->string('aktivitas', 255);
            $table->string('sumber_rekayasa', 80);
            $table->string('pelaksana_rekayasa', 50);
            $table->text('pengendalian_rekayasa');
            $table->date('tanggal_ideation')->nullable();

            $table->date('kajian_teknis_due_date')->nullable();
            $table->string('kajian_teknis_status', 20)->default('not_yet');

            $table->date('pengadaan_due_date')->nullable();
            $table->string('pengadaan_status', 20)->default('not_yet');

            $table->date('uji_coba_due_date')->nullable();
            $table->string('uji_coba_status', 20)->default('not_yet');

            $table->date('standardisasi_due_date')->nullable();
            $table->string('standardisasi_status', 20)->default('not_yet');

            $table->date('replikasi_due_date')->nullable();
            $table->unsignedInteger('replikasi_total_populasi')->default(0);
            $table->string('replikasi_satuan', 50)->default('');
            $table->unsignedInteger('replikasi_target_komitmen')->default(0);
            $table->string('replikasi_diusulkan_pjo', 255)->nullable();
            $table->string('replikasi_ditinjau', 255)->nullable();
            $table->string('replikasi_disetujui', 255)->nullable();
            $table->unsignedInteger('replikasi_aktual')->default(0);

            $table->unsignedInteger('deteksi_deviasi')->nullable();
            $table->string('intervensi_deviasi', 30)->nullable();
            $table->unsignedTinyInteger('prediksi_penurunan_tangga_risiko')->nullable();
            $table->boolean('terkait_hazard')->default(false);
            $table->boolean('terkait_insiden')->default(false);

            $table->text('brief_analysis_challenge')->nullable();
            $table->text('next_to_do')->nullable();
            $table->boolean('potensi_peningkatan_efektivitas')->default(false);
            $table->text('pengendalian_peningkatan_efektivitas')->nullable();

            $table->unsignedSmallInteger('period_year')->default(2026);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('row_no', 'idx_mse_rec_row_no');
            $table->index('site', 'idx_mse_rec_site');
            $table->index('perusahaan', 'idx_mse_rec_perusahaan');
            $table->index('aktivitas', 'idx_mse_rec_aktivitas');
            $table->index('sumber_rekayasa', 'idx_mse_rec_sumber');
            $table->index('pelaksana_rekayasa', 'idx_mse_rec_pelaksana');
            $table->index('tanggal_ideation', 'idx_mse_rec_ideation');
            $table->index('kajian_teknis_due_date', 'idx_mse_rec_kt_due');
            $table->index('kajian_teknis_status', 'idx_mse_rec_kt_stat');
            $table->index('pengadaan_due_date', 'idx_mse_rec_peng_due');
            $table->index('pengadaan_status', 'idx_mse_rec_peng_stat');
            $table->index('uji_coba_due_date', 'idx_mse_rec_uc_due');
            $table->index('uji_coba_status', 'idx_mse_rec_uc_stat');
            $table->index('standardisasi_due_date', 'idx_mse_rec_std_due');
            $table->index('standardisasi_status', 'idx_mse_rec_std_stat');
            $table->index('replikasi_due_date', 'idx_mse_rec_rep_due');
            $table->index('intervensi_deviasi', 'idx_mse_rec_intervensi');
            $table->index('terkait_hazard', 'idx_mse_rec_hazard');
            $table->index('terkait_insiden', 'idx_mse_rec_insiden');
            $table->index('potensi_peningkatan_efektivitas', 'idx_mse_rec_potensi');
            $table->index('period_year', 'idx_mse_rec_year');
            $table->index('sort_order', 'idx_mse_rec_sort');
            $table->index('deleted_at', 'idx_mse_rec_deleted_at');
            $table->index(['site', 'perusahaan', 'period_year'], 'idx_mse_rec_site_per_year');
            $table->index(['sumber_rekayasa', 'period_year'], 'idx_mse_rec_sumber_year');
            $table->index(['aktivitas', 'period_year'], 'idx_mse_rec_aktivitas_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_safety_engineering_records');
    }
};
