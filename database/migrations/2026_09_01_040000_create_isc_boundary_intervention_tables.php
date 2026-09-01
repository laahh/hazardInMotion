<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isc_detection_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('stale_gps_seconds')->default(900);
            $table->string('notify_channel', 32)->default('telegram');
            $table->timestamps();
        });

        Schema::create('isc_boundary_events', function (Blueprint $table): void {
            $table->id();
            $table->string('person_key', 128)->index();
            $table->string('sid', 64)->nullable()->index();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('iupk_site', 64)->nullable();
            $table->string('hazard_boundary_id', 64)->nullable()->index();
            $table->string('hazard_name')->nullable();
            $table->timestamp('entered_at')->index();
            $table->timestamp('exited_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status', 24)->default('open')->index();
            $table->string('rule_code', 64)->nullable();
            $table->timestamps();
            $table->index(['status', 'person_key'], 'idx_isc_events_status_person');
            $table->index(['sid', 'entered_at'], 'idx_isc_events_sid_entered');
        });

        Schema::create('isc_interventions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('isc_boundary_events')->cascadeOnDelete();
            $table->foreignId('pic_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 64);
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('submitted')->index();
            $table->timestamps();
            $table->index(['event_id', 'status'], 'idx_isc_interventions_event_status');
        });

        Schema::create('isc_intervention_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('intervention_id')->constrained('isc_interventions')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index('intervention_id');
        });

        Schema::create('isc_intervention_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('intervention_id')->constrained('isc_interventions')->cascadeOnDelete();
            $table->foreignId('verifier_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('result', 24);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('intervention_id');
            $table->index('verifier_user_id');
        });

        DB::table('isc_detection_rules')->insert([
            'code' => 'hazard-entry',
            'name' => 'Masuk zona berbahaya',
            'is_active' => true,
            'stale_gps_seconds' => 900,
            'notify_channel' => 'telegram',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('isc_intervention_verifications');
        Schema::dropIfExists('isc_intervention_evidences');
        Schema::dropIfExists('isc_interventions');
        Schema::dropIfExists('isc_boundary_events');
        Schema::dropIfExists('isc_detection_rules');
    }
};
