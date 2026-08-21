<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ohs_project_issue_trackers', function (Blueprint $table): void {
            $table->string('tracker_id')->primary();
            $table->dateTime('timestamp')->index();
            $table->string('tracker_type')->index();
            $table->string('project_issue_name');
            $table->string('department')->index();
            $table->string('site')->index();
            $table->string('project_leader_emp_id')->index();
            $table->string('project_leader_name');
            $table->string('project_leader_team')->nullable();
            $table->string('project_leader_position')->nullable();
            $table->string('project_leader_site_dedicated')->nullable();
            $table->text('description_project');
            $table->text('background_project');
            $table->text('impact_project');
            $table->date('start_date')->index();
            $table->date('due_date')->index();
            $table->text('success_indicator');
            $table->decimal('current_percent_complete', 5, 2)->default(0);
            $table->text('current_progress_report_weekly')->nullable();
            $table->text('current_remarks')->nullable();
            $table->string('status')->index();
            $table->dateTime('last_updated')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohs_project_issue_trackers');
    }
};
