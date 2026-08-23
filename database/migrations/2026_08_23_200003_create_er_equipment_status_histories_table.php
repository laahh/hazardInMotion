<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_equipment_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('trackable_type'); // App\Models\EmergencyResponse\Equipment\EmergencyEquipment atau ...\SafetyDevice
            $table->uuid('trackable_id');
            $table->string('field_changed'); // condition, operational_status
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['trackable_type', 'trackable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_equipment_status_histories');
    }
};
