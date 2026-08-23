<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_incident_equipment_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('incident_id');
            $table->uuid('response_unit_id')->nullable();
            $table->string('equipmentable_type'); // App\Models\EmergencyResponse\Equipment\EmergencyEquipment atau ...\SafetyDevice\SafetyDevice
            $table->uuid('equipmentable_id');
            $table->unsignedInteger('quantity_used')->default(1);
            $table->string('condition_after')->nullable(); // baik, perlu_perbaikan, rusak, maintenance, tidak_aktif
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('er_incidents')->cascadeOnDelete();
            $table->foreign('response_unit_id')->references('id')->on('er_response_units')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['equipmentable_type', 'equipmentable_id']);
            $table->index('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_incident_equipment_usage');
    }
};
