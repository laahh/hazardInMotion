<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar aktivitas/sub aktivitas pekerjaan OAK yang terkait IKK (Izin Kerja Khusus) — Peta Risiko PJA, DRM 2.1.
 * Baris baseline (drm_master_baris) ditandai IKK kalau salah satu Item Sumber OAK-nya (aktivitas / sub aktivitas,
 * sama format dengan M11/M12) cocok (DrmMaster::normTeks) dengan salah satu baris di sini. Kolom
 * `cek_luar_workshop`: khusus kategori "Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang" (keputusan user
 * 25 Nov 2026) — baris baseline yang teks aktivitas/sub/bahaya-risikonya sendiri menyebut "workshop" atau "pit
 * stop" TIDAK ditandai IKK meski Item Sumber OAK-nya cocok (pekerjaan perbaikan di workshop/pit stop dianggap
 * bukan risiko lapangan IKK). Diisi awal: php artisan peta-risiko:master-db --isi-ikk. Dikoreksi di
 * /peta-risiko-pja/master (tab "Aktivitas IKK").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drm_master_ikk', function (Blueprint $t) {
            $t->id();
            $t->string('aktivitas', 255)->comment('aktivitas pekerjaan OAK, mis. "Bekerja dengan Panas"');
            $t->string('sub_aktivitas', 255)->comment('sub aktivitas pekerjaan OAK, mis. "Pemotongan"');
            $t->boolean('cek_luar_workshop')->default(false)
                ->comment('true = baris baseline yang teksnya sendiri menyebut workshop/pit stop dikecualikan dari IKK');
            $t->boolean('aktif')->default(true);
            $t->string('catatan', 500)->nullable();
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
            $t->index(['aktivitas', 'sub_aktivitas'], 'dmik_aktivitas_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drm_master_ikk');
    }
};
