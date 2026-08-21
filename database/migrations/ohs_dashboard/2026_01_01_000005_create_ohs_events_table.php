<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ohs_events', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->dateTime('timestamp')->index();
            $table->string('event_name');
            $table->text('description');
            $table->string('where');
            $table->text('readiness_update')->nullable();
            $table->dateTime('readiness_updated_at')->nullable();
            $table->string('pic_emp_id')->index();
            $table->string('pic_name');
            $table->string('pic_team')->nullable()->index();
            $table->string('pic_position')->nullable();
            $table->string('pic_site_dedicated')->nullable()->index();
            $table->date('event_date')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohs_events');
    }
};
