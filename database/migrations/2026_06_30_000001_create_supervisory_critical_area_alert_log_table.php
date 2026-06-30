<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alert Critical Area: DOP area kritis tanpa observasi, dengan status intervensi.
     */
    public function up(): void
    {
        Schema::create('supervisory_critical_area_alert_log', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->comment('Tanggal alert');
            $table->foreignId('dop_id')->constrained('daily_operation_plans')->cascadeOnDelete();
            $table->boolean('has_observasi')->default(false)->comment('Apakah sudah ada observasi');
            $table->string('status_intervensi', 30)->default('belum_di_intervensi')->comment('sudah_di_intervensi | belum_di_intervensi');
            $table->string('temuan', 255)->default('Belum ada observasi');
            $table->json('dop_snapshot')->nullable()->comment('Snapshot data DOP saat alert dibuat');
            $table->timestamps();

            $table->unique(['tanggal', 'dop_id'], 'idx_supervisory_ca_alert_tanggal_dop');
            $table->index('tanggal');
            $table->index('has_observasi');
            $table->index('status_intervensi');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisory_critical_area_alert_log');
    }
};
