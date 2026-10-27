<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom "Total Jam Kerja" template Master Roster: jam kerja efektif per hari
 * (boleh lebih kecil dari Jam Kerja Akhir − Awal, mis. dipotong istirahat).
 * menit_kerja tetap = rentang Awal–Akhir.
 *
 * HANYA menambah satu kolom nullable — tabel berisi data, tidak ada baris
 * yang diubah atau dihapus. Jadwal lama dibiarkan NULL (artinya total =
 * menit_kerja). down() hanya membuang kolom baru ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dms_roster_master_jadwal') || Schema::hasColumn('dms_roster_master_jadwal', 'menit_total_kerja')) {
            return;
        }

        Schema::table('dms_roster_master_jadwal', function (Blueprint $table) {
            $table->unsignedSmallInteger('menit_total_kerja')->nullable()->after('menit_kerja');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('dms_roster_master_jadwal') && Schema::hasColumn('dms_roster_master_jadwal', 'menit_total_kerja')) {
            Schema::table('dms_roster_master_jadwal', function (Blueprint $table) {
                $table->dropColumn('menit_total_kerja');
            });
        }
    }
};
