<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_equipment_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('documentable_type'); // App\Models\EmergencyResponse\Equipment\EmergencyEquipment atau ...\SafetyDevice
            $table->uuid('documentable_id');
            $table->string('type')->default('document'); // photo, document
            $table->string('original_name');
            $table->string('file_path');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();

            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['documentable_type', 'documentable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_equipment_documents');
    }
};
