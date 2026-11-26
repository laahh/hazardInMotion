<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master baru: PIC Perusahaan per lokasi (keputusan user 26 Nov 2026) — gap yang belum ada di baseline DRM 2.1
 * (baseline cuma sampai PJA, belum ada "siapa perusahaan penanggung jawabnya"). PIC di sini KHUSUS dari
 * personil PJA Child (Mitra), BUKAN personil PJA induk sendiri — beda dengan drm_master_lokasi_pja.perusahaan
 * yang menggabung induk+anak. Satu lokasi bisa dimiliki beberapa PJA (61% kasus di data nyata); PIC-nya
 * digabung semua PJA pemilik lokasi itu (tidak dipisah per PJA, tidak difilter/diprioritaskan).
 * Detail lokasi mewarisi lewat site+lokasi, sama seperti mewarisi PJA dari drm_master_lokasi_pja.
 * Diisi oleh DrmMasterDb::picPerusahaanPerLokasi() lewat `php artisan peta-risiko:master-db --isi` /
 * backfill satu kali untuk data yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drm_master_lokasi_pic', function (Blueprint $t) {
            $t->id();
            $t->string('site', 60);
            $t->string('lokasi', 200);
            $t->text('pic_perusahaan')->nullable()->comment('Gabungan nama perusahaan PJA Child dari semua PJA pemilik lokasi ini, dipisah baris baru');
            $t->boolean('aktif')->default(true);
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
            $t->unique(['site', 'lokasi'], 'dmlpc_site_lok_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drm_master_lokasi_pic');
    }
};
