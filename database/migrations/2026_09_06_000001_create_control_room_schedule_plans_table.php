<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * plan-OCR.md T3.1. TIDAK dieksekusi otomatis — larangan keras migrasi
 * otomatis (plan-OCR.md rule #6). User menjalankan `php artisan migrate` manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_room_schedule_plans', function (Blueprint $table) {
            $table->id();
            $table->string('site_code', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('week_number');
            $table->date('date');
            $table->string('shift_code', 10);
            $table->string('personnel_source_key', 100);
            $table->string('personnel_name_snapshot', 150);
            $table->enum('status', ['draft', 'locked'])->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(
                ['site_code', 'date', 'shift_code', 'personnel_source_key'],
                'control_room_schedule_plans_unique_slot'
            );
            $table->index(['site_code', 'year', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_room_schedule_plans');
    }
};
