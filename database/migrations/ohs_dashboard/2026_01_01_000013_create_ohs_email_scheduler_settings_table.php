<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ohs_email_scheduler_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false)->index();
            $table->string('frequency')->default('SELECTED_DAYS');
            $table->string('schedule_days')->default('MON,TUE,WED,THU,FRI');
            $table->unsignedTinyInteger('send_hour')->default(7);
            $table->unsignedTinyInteger('send_minute')->default(0);
            $table->text('recipients');
            $table->text('cc');
            $table->text('bcc');
            $table->string('portal_url')->default('');
            $table->string('overview_team')->default('All Teams');
            $table->string('overview_site')->default('All Sites');
            $table->boolean('include_leave_summary')->default(true);
            $table->boolean('include_tracker_summary')->default(true);
            $table->boolean('include_leaderboard')->default(true);
            $table->string('subject_prefix')->default('[OHS Portal]');
            $table->string('event_reminder_days')->default('0,1,3,7');
            $table->unsignedSmallInteger('include_previous_days')->default(7);
            $table->string('last_scheduled_key')->default('');
            $table->dateTime('last_run_at')->nullable();
            $table->string('last_run_status')->default('Belum pernah dijalankan.');
            $table->unsignedInteger('last_email_count')->default(0);
            $table->dateTime('updated_at')->nullable();
            $table->string('updated_by')->default('');
            $table->string('overdue_reminder_last_key')->default('');
            $table->dateTime('overdue_reminder_last_run_at')->nullable();
            $table->unsignedInteger('overdue_reminder_last_count')->default(0);
            $table->string('hse_sync_last_key')->default('');
            $table->dateTime('hse_sync_last_run_at')->nullable();
            $table->unsignedInteger('hse_sync_last_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohs_email_scheduler_settings');
    }
};
