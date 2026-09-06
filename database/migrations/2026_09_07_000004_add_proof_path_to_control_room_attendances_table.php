<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom path file bukti absensi. TIDAK dieksekusi otomatis —
 * jalankan `php artisan migrate` manual setelah review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('control_room_attendances', function (Blueprint $table): void {
            $table->string('proof_path', 255)->nullable()->after('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('control_room_attendances', function (Blueprint $table): void {
            $table->dropColumn('proof_path');
        });
    }
};
