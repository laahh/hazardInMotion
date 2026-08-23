<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_maintenance_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('target_type'); // App\Models\EmergencyResponse\Equipment\EmergencyEquipment atau ...\SafetyDevice\SafetyDevice
            $table->uuid('target_id');
            $table->uuid('maintenance_type_id');
            $table->unsignedInteger('frequency_days');
            $table->date('next_due_date');
            $table->unsignedBigInteger('assigned_technician_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('maintenance_type_id')->references('id')->on('er_maintenance_types')->cascadeOnDelete();
            $table->foreign('assigned_technician_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['target_type', 'target_id']);
            $table->index('next_due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_maintenance_schedules');
    }
};
