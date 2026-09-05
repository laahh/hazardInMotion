<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * plan-OCR.md T3.2. TIDAK dieksekusi otomatis — larangan keras migrasi
 * otomatis (plan-OCR.md rule #6). User menjalankan `php artisan migrate` manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_room_schedule_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_plan_id')->constrained('control_room_schedule_plans')->cascadeOnDelete();
            $table->string('field', 100);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason');
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('changed_at');

            $table->index('schedule_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_room_schedule_changes');
    }
};
