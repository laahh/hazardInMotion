<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ohs_event_attendances', function (Blueprint $table): void {
            $table->string('attendance_id')->primary();
            $table->dateTime('timestamp')->index();
            $table->string('event_id')->index();
            $table->string('emp_id')->index();
            $table->string('emp_name');
            $table->string('team')->nullable();
            $table->string('position')->nullable();
            $table->string('site_dedicated')->nullable();
            $table->dateTime('check_in_at')->index();

            $table->unique(['event_id', 'emp_id'], 'uk_ohs_event_attendance_emp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohs_event_attendances');
    }
};
