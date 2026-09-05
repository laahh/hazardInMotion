<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * plan-OCR.md T0.2 — dibutuhkan agar fitur absen (control-room) tahu user
 * yang login itu personil yang mana. TIDAK dieksekusi otomatis — larangan
 * keras migrasi otomatis (plan-OCR.md rule #6). User menjalankan
 * `php artisan migrate` manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('personnel_source_key', 100)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('personnel_source_key');
        });
    }
};
