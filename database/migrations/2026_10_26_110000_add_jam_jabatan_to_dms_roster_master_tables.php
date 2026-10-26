<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom tambahan Master Roster untuk format "ROSTER KERJA KARYAWAN":
 * Departemen / Site per upload, serta Jabatan dan jam efektif per hari
 * (jam check-in, check-out, menit kerja) per jadwal.
 *
 * 2026_10_26_100000 sudah dijalankan dalam versi tanpa kolom ini; file itu
 * kini juga memuatnya, jadi setiap kolom dicek dulu supaya migrasi ini aman di
 * database yang membuat tabelnya dari versi mana pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dms_roster_master_upload', 'departemen_site')) {
            Schema::table('dms_roster_master_upload', function (Blueprint $table) {
                $table->string('departemen_site', 255)->nullable()->after('periode_selesai');
            });
        }

        Schema::table('dms_roster_master_jadwal', function (Blueprint $table) {
            if (! Schema::hasColumn('dms_roster_master_jadwal', 'jabatan')) {
                $table->string('jabatan', 150)->nullable()->after('perusahaan');
            }
            if (! Schema::hasColumn('dms_roster_master_jadwal', 'jam_masuk')) {
                $table->time('jam_masuk')->nullable()->after('kode');
            }
            if (! Schema::hasColumn('dms_roster_master_jadwal', 'jam_keluar')) {
                $table->time('jam_keluar')->nullable()->after('jam_masuk');
            }
            if (! Schema::hasColumn('dms_roster_master_jadwal', 'menit_kerja')) {
                $table->unsignedSmallInteger('menit_kerja')->nullable()->after('jam_keluar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dms_roster_master_jadwal', function (Blueprint $table) {
            foreach (['menit_kerja', 'jam_keluar', 'jam_masuk', 'jabatan'] as $kolom) {
                if (Schema::hasColumn('dms_roster_master_jadwal', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });

        if (Schema::hasColumn('dms_roster_master_upload', 'departemen_site')) {
            Schema::table('dms_roster_master_upload', function (Blueprint $table) {
                $table->dropColumn('departemen_site');
            });
        }
    }
};
