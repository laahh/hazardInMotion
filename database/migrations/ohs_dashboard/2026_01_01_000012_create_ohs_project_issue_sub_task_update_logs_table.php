<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ohs_project_issue_sub_task_update_logs', function (Blueprint $table): void {
            $table->string('update_id')->primary();
            $table->dateTime('timestamp')->index();
            $table->string('tracker_id')->index();
            $table->string('sub_task_id')->index();
            $table->decimal('percent_complete', 5, 2);
            $table->text('progress_report_weekly');
            $table->text('remarks');
            $table->string('status')->index();
            $table->string('updated_by_emp_id')->index();
            $table->string('updated_by_name');
            $table->string('updated_by_team')->nullable();
            $table->string('updated_by_position')->nullable();
            $table->string('updated_by_site_dedicated')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohs_project_issue_sub_task_update_logs');
    }
};
