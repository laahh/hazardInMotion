<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterAksesPerusahaan as Akses;
use PHPUnit\Framework\TestCase;

/**
 * Aturan cakupan Assign Role Master Roster: upload terlihat bila SALAH SATU
 * perusahaannya di-assign, boleh di-review bila SEMUA-nya di-assign.
 */
class DmsRosterAksesPerusahaanTest extends TestCase
{
    public function test_tanpa_batasan_melihat_dan_mereview_semua(): void
    {
        $this->assertTrue(Akses::terlihat(null, ['PT Pamapersada Nusantara']));
        $this->assertTrue(Akses::mencakup(null, ['PT Pamapersada Nusantara', 'PT Lain']));
        $this->assertTrue(Akses::mencakup(null, []));
    }

    public function test_user_hanya_melihat_perusahaan_yang_di_assign(): void
    {
        $cakupan = ['PT Pamapersada Nusantara'];

        $this->assertTrue(Akses::terlihat($cakupan, ['PT Pamapersada Nusantara']));
        $this->assertTrue(Akses::mencakup($cakupan, ['PT Pamapersada Nusantara']));
        $this->assertFalse(Akses::terlihat($cakupan, ['PT Bukit Makmur Mandiri Utama']));
        $this->assertFalse(Akses::mencakup($cakupan, ['PT Bukit Makmur Mandiri Utama']));
    }

    public function test_upload_campuran_terlihat_tapi_tidak_boleh_direview(): void
    {
        $cakupan = ['PT Pamapersada Nusantara'];
        $upload = ['PT Bukit Makmur Mandiri Utama', 'PT Pamapersada Nusantara'];

        $this->assertTrue(Akses::terlihat($cakupan, $upload));
        $this->assertFalse(Akses::mencakup($cakupan, $upload));
        $this->assertTrue(Akses::mencakup([...$cakupan, 'PT Bukit Makmur Mandiri Utama'], $upload));
    }

    public function test_tanpa_assign_tidak_melihat_apa_pun(): void
    {
        $this->assertFalse(Akses::terlihat([], ['PT Pamapersada Nusantara']));
        $this->assertFalse(Akses::mencakup([], ['PT Pamapersada Nusantara']));
        // Upload tanpa jadwal tidak boleh lolos hanya karena tidak ada perusahaan yang ditolak.
        $this->assertFalse(Akses::mencakup(['PT Pamapersada Nusantara'], []));
    }

    public function test_pencocokan_tidak_membedakan_huruf_dan_spasi(): void
    {
        $this->assertTrue(Akses::mencakup(['PT Pamapersada Nusantara'], ['  PT PAMAPERSADA NUSANTARA ']));
    }

    public function test_pencocokan_tidak_membedakan_spasi_di_tengah_dan_titik_awalan(): void
    {
        $this->assertTrue(Akses::mencakup(['PT Pamapersada Nusantara'], ['PT Pama Persada Nusantara']));
        $this->assertTrue(Akses::mencakup(['PT Terusan Raya'], ['PT. Terusan Raya']));
        $this->assertFalse(Akses::mencakup(['PT Pamapersada Nusantara'], ['PT Pama Persada']));
    }

    public function test_singkatan_upload_cocok_dengan_nama_lengkap_yang_di_assign(): void
    {
        $cakupan = ['PT Puncak Makmur Jaya'];

        $this->assertTrue(Akses::terlihat($cakupan, ['PT PMJ']));
        $this->assertTrue(Akses::mencakup($cakupan, ['PT PMJ']));
        $this->assertTrue(Akses::mencakup($cakupan, ['PMJ']));
        $this->assertFalse(Akses::mencakup($cakupan, ['PT BMJ']));
        // Kebalikannya: assign singkatan, upload nama lengkap.
        $this->assertTrue(Akses::mencakup(['PT PMJ'], ['PT Puncak Makmur Jaya']));
    }

    public function test_dua_nama_lengkap_berinisial_sama_tidak_tertukar(): void
    {
        $this->assertFalse(Akses::terlihat(['PT Puncak Makmur Jaya'], ['PT Prima Mitra Jaya']));
    }

    public function test_rapikan_membuang_kosong_dan_duplikat(): void
    {
        $this->assertSame(
            ['PT Bukit Makmur Mandiri Utama', 'PT Pamapersada Nusantara'],
            Akses::rapikan(['PT Pamapersada Nusantara', ' ', 'pt pamapersada nusantara', ' PT Bukit Makmur Mandiri Utama ']),
        );
    }
}
