<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_incident_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('incident_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role_note')->nullable(); // mis. "Investigator", "Coordinator"
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->useCurrent();

            $table->foreign('incident_id')->references('id')->on('er_incidents')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();
            $table->index('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_incident_assignments');
    }
};
