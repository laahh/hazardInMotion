<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ohs_project_issue_sub_tasks', function (Blueprint $table): void {
            $table->string('sub_task_id')->primary();
            $table->string('tracker_id')->index();
            $table->dateTime('timestamp')->index();
            $table->string('sub_task_name');
            $table->string('department')->nullable()->index();
            $table->string('site')->index();
            $table->string('pic_emp_id')->index();
            $table->string('pic_name');
            $table->string('pic_team')->nullable()->index();
            $table->string('pic_position')->nullable();
            $table->string('pic_site_dedicated')->nullable()->index();
            $table->text('description_sub_task');
            $table->date('start_date')->index();
            $table->date('due_date')->index();
            $table->text('success_indicator');
            $table->decimal('current_percent_complete', 5, 2)->default(0);
            $table->text('current_progress_report_weekly');
            $table->text('current_remarks');
            $table->string('status')->index();
            $table->dateTime('last_updated')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohs_project_issue_sub_tasks');
    }
};
