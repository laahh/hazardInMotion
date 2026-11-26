<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ganti sumber & granularitas drm_master_lokasi_pic (keputusan user 27 Nov 2026, ditemukan lewat filter
 * Site=BMO 1 x Perusahaan=PT Bukit Makmur Mandiri Utama di Alur Baseline yang masih menampilkan Detail Lokasi
 * "(B PMO) Warehouse KDC" — PIC resminya KDC, bukan BUMA sama sekali). Kolom `pic_perusahaan` SEBELUMNYA dihitung
 * dari personil PJA Child (sama sumber dengan drm_master_lokasi_pja.perusahaan) dan cuma per (site, lokasi) —
 * SEKARANG diisi dari PIC_Detail_Lokasi ClickHouse (obds.BMO_1/BMO_2/BMO_3/GMO/HOTE/LMO/MARINE/SMO, sumber asli
 * Google Sheet "Coverage Area All Site") per (site, lokasi, detail) — resmi, bukan perkiraan dari siapa yang
 * kebetulan tercatat bekerja di situ. Kolom `detail` ditambah & kunci unik diganti supaya satu lokasi fisik bisa
 * punya PIC berbeda per detlok (satu PJA/lokasi bisa menaungi banyak subkontraktor berbeda di detlok berbeda).
 * Diisi oleh DrmMasterDb::picDetlokPerLokasi() lewat `php artisan peta-risiko:master-db --isi` / backfill satu
 * kali untuk data yang sudah ada — data LAMA (berbasis personil) dibuang total, bukan digabung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drm_master_lokasi_pic', function (Blueprint $t) {
            $t->dropUnique('dmlpc_site_lok_uniq');
            $t->string('detail', 300)->default('')->after('lokasi');
        });
        Schema::table('drm_master_lokasi_pic', function (Blueprint $t) {
            $t->unique(['site', 'lokasi', 'detail'], 'dmlpc_site_lok_detail_uniq');
        });
    }

    public function down(): void
    {
        Schema::table('drm_master_lokasi_pic', function (Blueprint $t) {
            $t->dropUnique('dmlpc_site_lok_detail_uniq');
            $t->dropColumn('detail');
        });
        Schema::table('drm_master_lokasi_pic', function (Blueprint $t) {
            $t->unique(['site', 'lokasi'], 'dmlpc_site_lok_uniq');
        });
    }
};
