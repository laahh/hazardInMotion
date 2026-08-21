<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ohs_event_minutes', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->dateTime('timestamp');
            $table->text('summary')->nullable();
            $table->dateTime('updated_at')->index();
            $table->string('updated_by_emp_id')->nullable()->index();
            $table->string('updated_by_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohs_event_minutes');
    }
};
