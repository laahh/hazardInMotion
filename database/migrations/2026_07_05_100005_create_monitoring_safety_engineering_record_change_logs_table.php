<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_safety_engineering_record_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('record_id');
            $table->uuid('change_batch');
            $table->string('action', 20);
            $table->string('field_name', 80);
            $table->string('field_label', 120);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('changed_at');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->unsignedSmallInteger('period_year');
            $table->string('review_week', 10);
            $table->timestamps();

            $table->foreign('record_id', 'fk_mse_rcl_record_id')
                ->references('id')
                ->on('monitoring_safety_engineering_records')
                ->cascadeOnDelete();

            $table->foreign('changed_by', 'fk_mse_rcl_changed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['record_id', 'changed_at'], 'idx_mse_rcl_rec_changed');
            $table->index(['record_id', 'review_week'], 'idx_mse_rcl_rec_week');
            $table->index('change_batch', 'idx_mse_rcl_batch');
            $table->index(['review_week', 'period_year'], 'idx_mse_rcl_week_year');
            $table->index(['field_name', 'record_id'], 'idx_mse_rcl_field_rec');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_safety_engineering_record_change_logs');
    }
};
