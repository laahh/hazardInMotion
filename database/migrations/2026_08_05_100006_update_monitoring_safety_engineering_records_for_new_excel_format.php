<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan monitoring_safety_engineering_records dengan format Excel baru:
 * - deteksi_deviasi disimpan sebagai label teks (dulu unsignedInteger, tidak sesuai isi datanya).
 * - Kolom baru: efektivitas_rekayasa serta 4 kolom pelacakan risiko signifikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('monitoring_safety_engineering_records', 'deteksi_deviasi')) {
            Schema::table('monitoring_safety_engineering_records', function (Blueprint $table): void {
                $table->dropColumn('deteksi_deviasi');
            });
        }

        Schema::table('monitoring_safety_engineering_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('monitoring_safety_engineering_records', 'deteksi_deviasi')) {
                $table->string('deteksi_deviasi', 50)->nullable()->after('replikasi_aktual');
            }

            if (! Schema::hasColumn('monitoring_safety_engineering_records', 'efektivitas_rekayasa')) {
                $table->string('efektivitas_rekayasa', 80)->nullable()->after('terkait_insiden');
            }

            if (! Schema::hasColumn('monitoring_safety_engineering_records', 'total_risiko_signifikan')) {
                $table->unsignedInteger('total_risiko_signifikan')->nullable()->after('pengendalian_peningkatan_efektivitas');
            }

            if (! Schema::hasColumn('monitoring_safety_engineering_records', 'link_list_risiko_signifikan')) {
                $table->string('link_list_risiko_signifikan', 500)->nullable()->after('total_risiko_signifikan');
            }

            if (! Schema::hasColumn('monitoring_safety_engineering_records', 'jumlah_risiko_signifikan_tercover_rekayasa')) {
                $table->unsignedInteger('jumlah_risiko_signifikan_tercover_rekayasa')->nullable()->after('link_list_risiko_signifikan');
            }

            if (! Schema::hasColumn('monitoring_safety_engineering_records', 'link_risiko_signifikan_tercover_rekayasa')) {
                $table->string('link_risiko_signifikan_tercover_rekayasa', 500)->nullable()->after('jumlah_risiko_signifikan_tercover_rekayasa');
            }
        });

        Schema::table('monitoring_safety_engineering_records', function (Blueprint $table): void {
            $table->index('deteksi_deviasi', 'idx_mse_rec_deteksi');
            $table->index('efektivitas_rekayasa', 'idx_mse_rec_efektivitas');
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_safety_engineering_records', function (Blueprint $table): void {
            $table->dropIndex('idx_mse_rec_deteksi');
            $table->dropIndex('idx_mse_rec_efektivitas');

            $table->dropColumn([
                'efektivitas_rekayasa',
                'total_risiko_signifikan',
                'link_list_risiko_signifikan',
                'jumlah_risiko_signifikan_tercover_rekayasa',
                'link_risiko_signifikan_tercover_rekayasa',
            ]);

            $table->dropColumn('deteksi_deviasi');
        });

        Schema::table('monitoring_safety_engineering_records', function (Blueprint $table): void {
            $table->unsignedInteger('deteksi_deviasi')->nullable()->after('replikasi_aktual');
        });
    }
};
