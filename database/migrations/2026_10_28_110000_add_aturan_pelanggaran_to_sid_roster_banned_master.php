<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembeda aturan pada Master Roster Banned:
 *   aturan_pelanggaran  ONSITE_70   = periode kerja > 70 hari on-site (banned 14 hari)
 *                       BERUNTUN_13 = bekerja > 13 hari berturut-turut (banned 1 hari off)
 *                       NULL        = input manual / import (aturan tidak diketahui)
 *   lama_banned_hari    14 / 1 (NULL untuk input manual)
 *
 * Tabel berisi data: HANYA menambah dua kolom nullable, lalu mengisi kolom
 * BARU itu untuk baris buatan sistem RFID (created_by "Sistem (RFID
 * otomatis)") yang sampai 28 Okt 2026 seluruhnya berasal dari aturan > 70 hari
 * (auto-banned beruntun belum pernah dinyalakan). Tidak ada baris dihapus dan
 * tidak ada kolom lama yang diubah. down() hanya membuang dua kolom baru.
 */
return new class extends Migration
{
    private const TABEL = 'sid_roster_banned_master';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABEL)) {
            return;
        }

        Schema::table(self::TABEL, function (Blueprint $table) {
            if (! Schema::hasColumn(self::TABEL, 'aturan_pelanggaran')) {
                $table->string('aturan_pelanggaran', 20)->nullable()->after('alasan_pelanggaran');
            }
            if (! Schema::hasColumn(self::TABEL, 'lama_banned_hari')) {
                $table->unsignedSmallInteger('lama_banned_hari')->nullable()->after('aturan_pelanggaran');
            }
        });

        DB::table(self::TABEL)
            ->whereNull('aturan_pelanggaran')
            ->where('created_by', 'Sistem (RFID otomatis)')
            ->where('alasan_pelanggaran', 'BAN SID - Kepatuhan Roster Karyawan')
            ->update(['aturan_pelanggaran' => 'ONSITE_70', 'lama_banned_hari' => 14]);
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABEL)) {
            return;
        }

        $buang = array_values(array_filter(
            ['aturan_pelanggaran', 'lama_banned_hari'],
            static fn (string $k): bool => Schema::hasColumn(self::TABEL, $k),
        ));
        if ($buang !== []) {
            Schema::table(self::TABEL, function (Blueprint $table) use ($buang) {
                $table->dropColumn($buang);
            });
        }
    }
};
