<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('incident_number')->unique(); // mis. INC-2026-000123

            $table->timestamp('occurred_at');
            $table->timestamp('reported_at');
            $table->uuid('incident_type_id')->nullable();
            $table->uuid('severity_level_id')->nullable();
            $table->uuid('priority_level_id')->nullable();

            $table->uuid('site_id')->nullable();
            $table->text('location_detail')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->text('description');
            $table->unsignedInteger('victim_count')->default(0);
            $table->text('potential_hazards')->nullable();
            $table->text('assistance_needed')->nullable();

            $table->unsignedBigInteger('reported_by')->nullable();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_phone')->nullable();
            $table->string('reporter_department')->nullable();

            $table->string('status')->default('open'); // open, in_progress, resolved, closed

            // Response time tracking
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('handling_started_at')->nullable();
            $table->timestamp('contained_at')->nullable();
            $table->timestamp('handling_completed_at')->nullable();

            $table->boolean('is_possible_duplicate')->default(false);
            $table->uuid('possible_duplicate_of')->nullable();
            $table->boolean('is_escalated')->default(false);

            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('incident_type_id')->references('id')->on('er_incident_types')->nullOnDelete();
            $table->foreign('severity_level_id')->references('id')->on('er_severity_levels')->nullOnDelete();
            $table->foreign('priority_level_id')->references('id')->on('er_priority_levels')->nullOnDelete();
            $table->foreign('site_id')->references('id')->on('er_sites')->nullOnDelete();
            $table->foreign('reported_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('possible_duplicate_of')->references('id')->on('er_incidents')->nullOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index('status');
            $table->index('reported_at');
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_incidents');
    }
};
