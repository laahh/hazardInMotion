<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('alertable_type'); // EmergencyEquipment, SafetyDevice, MaintenanceSchedule, InspectionSchedule, WorkOrder, Incident
            $table->uuid('alertable_id');
            $table->string('alert_type'); // apar_expiring, certificate_expiring, calibration_due, inspection_overdue, maintenance_overdue, wo_overdue, escalation
            $table->date('due_date')->nullable();
            $table->integer('threshold_days')->nullable(); // 90,60,30,14,7,0, negatif = overdue (hari terlewat)
            $table->string('status')->default('pending'); // pending, sent, dismissed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['alertable_type', 'alertable_id']);
            $table->unique(['alertable_type', 'alertable_id', 'alert_type', 'threshold_days'], 'er_alerts_unique_threshold');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_alerts');
    }
};
