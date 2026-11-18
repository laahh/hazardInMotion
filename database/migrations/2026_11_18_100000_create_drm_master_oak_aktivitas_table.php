<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kurasi aktivitas OAK → aktivitas HIRA (Peta Risiko PJA, DRM 2.1). Laporan OAK dengan aktivitas_oak (sub aktivitas
 * apa pun) membuat baris HIRA beraktivitas aktivitas_hira BERLAKU untuk laporan itu, di samping label M12. Bila tabel
 * ini berisi, peta-risiko:sync memakainya menggantikan config peta_risiko_drm.oak_aktivitas_hira.
 * Diisi awal: php artisan peta-risiko:master-db --isi-oak. Dikoreksi di /peta-risiko-pja/master.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drm_master_oak_aktivitas', function (Blueprint $t) {
            $t->id();
            $t->string('aktivitas_oak', 300)->comment('nama aktivitas checklist OAK, mis. "Dumping"');
            $t->string('aktivitas_hira', 500)->comment('nama aktivitas HIRA (drm_master_baris.aktivitas)');
            $t->boolean('aktif')->default(true);
            $t->string('catatan', 500)->nullable();
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
            $t->index('aktivitas_oak', 'dmoa_oak_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drm_master_oak_aktivitas');
    }
};
