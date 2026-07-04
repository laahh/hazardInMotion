<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('monitoring_safety_engineering_records', 'kajian_teknis_status_changed_at')) {
            Schema::table('monitoring_safety_engineering_records', function (Blueprint $table): void {
                $table->timestamp('kajian_teknis_status_changed_at')->nullable()->after('kajian_teknis_status');
                $table->string('kajian_teknis_status_compliance', 20)->nullable()->after('kajian_teknis_status_changed_at');
                $table->timestamp('pengadaan_status_changed_at')->nullable()->after('pengadaan_status');
                $table->string('pengadaan_status_compliance', 20)->nullable()->after('pengadaan_status_changed_at');
                $table->timestamp('uji_coba_status_changed_at')->nullable()->after('uji_coba_status');
                $table->string('uji_coba_status_compliance', 20)->nullable()->after('uji_coba_status_changed_at');
                $table->timestamp('standardisasi_status_changed_at')->nullable()->after('standardisasi_status');
                $table->string('standardisasi_status_compliance', 20)->nullable()->after('standardisasi_status_changed_at');

                $table->index('kajian_teknis_status_compliance', 'idx_mse_rec_kt_compliance');
                $table->index('pengadaan_status_compliance', 'idx_mse_rec_peng_compliance');
                $table->index('uji_coba_status_compliance', 'idx_mse_rec_uc_compliance');
                $table->index('standardisasi_status_compliance', 'idx_mse_rec_std_compliance');
            });
        }

        if (Schema::hasTable('monitoring_safety_engineering_phase_status_logs')) {
            Schema::dropIfExists('monitoring_safety_engineering_phase_status_logs');
        }

        Schema::create('monitoring_safety_engineering_phase_status_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('record_id');
            $table->string('phase', 30);
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->date('due_date')->nullable();
            $table->timestamp('changed_at');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('compliance', 20);
            $table->unsignedSmallInteger('period_year');
            $table->string('review_week', 10);
            $table->timestamps();

            $table->foreign('record_id', 'fk_mse_psl_record_id')
                ->references('id')
                ->on('monitoring_safety_engineering_records')
                ->cascadeOnDelete();

            $table->foreign('changed_by', 'fk_mse_psl_changed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['record_id', 'phase'], 'idx_mse_psl_rec_phase');
            $table->index('changed_at', 'idx_mse_psl_changed_at');
            $table->index(['review_week', 'period_year'], 'idx_mse_psl_week_year');
            $table->index(['changed_by', 'review_week'], 'idx_mse_psl_user_week');
            $table->index(['compliance', 'phase'], 'idx_mse_psl_compliance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_safety_engineering_phase_status_logs');

        if (Schema::hasColumn('monitoring_safety_engineering_records', 'kajian_teknis_status_changed_at')) {
            Schema::table('monitoring_safety_engineering_records', function (Blueprint $table): void {
                $table->dropIndex('idx_mse_rec_kt_compliance');
                $table->dropIndex('idx_mse_rec_peng_compliance');
                $table->dropIndex('idx_mse_rec_uc_compliance');
                $table->dropIndex('idx_mse_rec_std_compliance');

                $table->dropColumn([
                    'kajian_teknis_status_changed_at',
                    'kajian_teknis_status_compliance',
                    'pengadaan_status_changed_at',
                    'pengadaan_status_compliance',
                    'uji_coba_status_changed_at',
                    'uji_coba_status_compliance',
                    'standardisasi_status_changed_at',
                    'standardisasi_status_compliance',
                ]);
            });
        }
    }
};
