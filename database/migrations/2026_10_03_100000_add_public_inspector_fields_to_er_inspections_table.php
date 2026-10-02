<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitas inspector untuk inspeksi yang masuk lewat form publik
 * /form/inspeksi-emergency (tanpa login), jadi `inspector_id` kosong dan
 * nama/NIK/SID/nomor WA diisi manual oleh pengisi form.
 *
 * Panjang `inspector_sid` mengikuti kolom `sid` di sid_roster_banned_master
 * (varchar 20, mis. "P8GTB") supaya nilainya bisa dicocokkan antar modul.
 *
 * Inspeksi internal tetap memakai `inspector_id`; kolom ini dibiarkan kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('er_inspections', function (Blueprint $table) {
            $table->string('inspector_name')->nullable()->after('inspector_id');
            $table->string('inspector_nik')->nullable()->after('inspector_name');
            $table->string('inspector_sid', 20)->nullable()->after('inspector_nik');
            $table->string('inspector_phone')->nullable()->after('inspector_sid');

            $table->index('inspector_nik', 'er_insp_inspector_nik_index');
            $table->index('inspector_sid', 'er_insp_inspector_sid_index');
        });
    }

    public function down(): void
    {
        Schema::table('er_inspections', function (Blueprint $table) {
            $table->dropIndex('er_insp_inspector_nik_index');
            $table->dropIndex('er_insp_inspector_sid_index');
            $table->dropColumn(['inspector_name', 'inspector_nik', 'inspector_sid', 'inspector_phone']);
        });
    }
};
