<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departemen baseline per baris master (koreksi, 20 Nov 2026): kosong = mengikuti departemen dokumen HIRA asal
 * (obds.mv_hira_master); terisi = departemen yang benar menurut baseline, mis. HIRA tercatat Mining padahal milik
 * Marine. HIRA tidak diubah. Diisi lewat halaman Master per Baris (unggah Excel).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('drm_master_baris', 'departemen')) {
            Schema::table('drm_master_baris', function (Blueprint $t) {
                $t->string('departemen', 150)->nullable()->after('kb');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('drm_master_baris', 'departemen')) {
            Schema::table('drm_master_baris', function (Blueprint $t) {
                $t->dropColumn('departemen');
            });
        }
    }
};
