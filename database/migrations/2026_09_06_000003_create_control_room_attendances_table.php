<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * plan-OCR.md T3.3. TIDAK dieksekusi otomatis — larangan keras migrasi
 * otomatis (plan-OCR.md rule #6). User menjalankan `php artisan migrate` manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_room_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_plan_id')->nullable()->constrained('control_room_schedule_plans')->nullOnDelete();
            $table->string('site_code', 20);
            $table->date('date');
            $table->string('shift_code', 10);
            $table->string('personnel_source_key', 100);
            $table->string('personnel_name_snapshot', 150);
            $table->enum('status', ['hadir_sesuai_jadwal', 'hadir_menggantikan', 'tidak_hadir']);
            $table->string('replacing_source_key', 100)->nullable();
            $table->text('absence_reason')->nullable();
            $table->timestamp('checked_in_at');
            $table->foreignId('corrected_by')->nullable()->constrained('users');
            $table->text('correction_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['site_code', 'date', 'shift_code', 'personnel_source_key'],
                'control_room_attendances_unique_slot'
            );
            $table->index(['site_code', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_room_attendances');
    }
};
