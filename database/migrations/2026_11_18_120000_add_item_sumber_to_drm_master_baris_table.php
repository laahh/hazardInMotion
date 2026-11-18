<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master HIRA ↔ SAP dalam SATU tabel (keputusan user 18 Nov 2026): drm_master_baris = Aktivitas · Sub Aktivitas ·
 * Bahaya-Risiko Terkait + tiga kolom label yang bisa diubah sendiri (satu label per baris teks):
 *   item_sumber_observasi       : jenis kegiatan OBS
 *   item_sumber_oak             : "aktivitas OAK / sub aktivitas"
 *   item_sumber_ketidaksesuaian : "ketidaksesuaian / sub ketidaksesuaian"
 * Bila kolom ini sudah diisi (php artisan peta-risiko:master-db --isi-label-baris), sync membaca label dari sini,
 * bukan dari drm_master_teks / drm_master_label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drm_master_baris', function (Blueprint $t) {
            $t->text('item_sumber_observasi')->nullable()->after('bahaya');
            $t->text('item_sumber_oak')->nullable()->after('item_sumber_observasi');
            $t->text('item_sumber_ketidaksesuaian')->nullable()->after('item_sumber_oak');
        });
    }

    public function down(): void
    {
        Schema::table('drm_master_baris', function (Blueprint $t) {
            $t->dropColumn(['item_sumber_observasi', 'item_sumber_oak', 'item_sumber_ketidaksesuaian']);
        });
    }
};
