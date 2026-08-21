<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ohs_leave_requests', function (Blueprint $table): void {
            $table->string('request_id')->primary();
            $table->dateTime('timestamp')->index();
            $table->string('emp_id')->index();
            $table->string('emp_name');
            $table->string('team')->nullable();
            $table->string('position')->nullable();
            $table->string('site_dedicated')->nullable();
            $table->string('leave_type')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('note')->nullable();
            $table->string('backup_emp_id')->index();
            $table->string('backup_emp_name');
            $table->string('backup_team')->nullable();
            $table->string('backup_position')->nullable();
            $table->string('backup_site_dedicated')->nullable();

            $table->index(['emp_id', 'start_date', 'end_date'], 'idx_ohs_leave_emp_range');
            $table->index(['start_date', 'end_date'], 'idx_ohs_leave_dates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohs_leave_requests');
    }
};
