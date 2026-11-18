<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asal label OBS/OAK per baris di master (drm_master_teks): kosong = workbook M11/M12, "kurasi aktivitas OAK" =
 * hasil penurunan kurasi (peta-risiko:master-db --turunkan-oak), "manual" = ditambah di editor per baris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drm_master_teks', function (Blueprint $t) {
            $t->string('sumber', 100)->nullable()->after('ri');
        });
    }

    public function down(): void
    {
        Schema::table('drm_master_teks', function (Blueprint $t) {
            $t->dropColumn('sumber');
        });
    }
};
