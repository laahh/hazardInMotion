<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_inspections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('inspection_number')->unique(); // mis. INSP-2026-000123
            $table->uuid('inspection_schedule_id')->nullable();
            $table->string('target_type');
            $table->uuid('target_id');
            $table->uuid('checklist_template_id');
            $table->uuid('site_id')->nullable(); // denormalisasi dari target, untuk filter/rekap cepat per lokasi

            $table->unsignedBigInteger('inspector_id')->nullable();
            $table->string('status')->default('draft'); // scheduled, draft, submitted, approved, rejected, follow_up_required, completed, overdue
            $table->string('condition_result')->nullable(); // kondisi hasil observasi: baik, perlu_perbaikan, rusak, maintenance, tidak_aktif
            $table->text('notes')->nullable();
            $table->string('signature_path')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->text('approval_notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('inspection_schedule_id')->references('id')->on('er_inspection_schedules')->nullOnDelete();
            $table->foreign('checklist_template_id')->references('id')->on('er_checklist_templates')->restrictOnDelete();
            $table->foreign('site_id')->references('id')->on('er_sites')->nullOnDelete();
            $table->foreign('inspector_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['target_type', 'target_id']);
            $table->index('status');
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_inspections');
    }
};
