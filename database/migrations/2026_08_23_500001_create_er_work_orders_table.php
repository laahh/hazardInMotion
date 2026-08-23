<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_work_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('work_order_number')->unique(); // mis. WO-2026-000123

            $table->string('equipmentable_type')->nullable();
            $table->uuid('equipmentable_id')->nullable();
            $table->uuid('site_id')->nullable(); // denormalisasi dari equipment, untuk filter cepat

            $table->string('work_type'); // corrective, preventive, inspection_followup, calibration, vehicle_service, certification_renewal, emergency_repair
            $table->string('source')->default('manual'); // manual, inspection, incident, alert
            $table->uuid('source_inspection_finding_id')->nullable();
            $table->uuid('source_incident_id')->nullable();

            $table->text('description');
            $table->uuid('priority_level_id')->nullable();
            $table->unsignedBigInteger('assigned_technician_id')->nullable();
            $table->uuid('vendor_id')->nullable();

            $table->timestamp('requested_at');
            $table->date('target_start_at')->nullable();
            $table->date('target_end_at')->nullable();
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_end_at')->nullable();

            $table->decimal('estimated_cost', 14, 2)->nullable();
            $table->decimal('actual_cost', 14, 2)->nullable();

            $table->string('status')->default('requested'); // requested, approved, assigned, in_progress, on_hold, completed, verified, closed

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->text('result_notes')->nullable();
            $table->string('technician_signature_path')->nullable();
            $table->string('verifier_signature_path')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('priority_level_id')->references('id')->on('er_priority_levels')->nullOnDelete();
            $table->foreign('site_id')->references('id')->on('er_sites')->nullOnDelete();
            $table->foreign('assigned_technician_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('vendor_id')->references('id')->on('er_vendors')->nullOnDelete();
            $table->foreign('source_inspection_finding_id')->references('id')->on('er_inspection_findings')->nullOnDelete();
            $table->foreign('source_incident_id')->references('id')->on('er_incidents')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['equipmentable_type', 'equipmentable_id']);
            $table->index('status');
            $table->index('target_end_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_work_orders');
    }
};
