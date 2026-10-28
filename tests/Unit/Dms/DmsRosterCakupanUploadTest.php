<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterCakupanUpload as Cakupan;
use Tests\TestCase;

/**
 * Perbandingan perusahaan & site karyawan aktif terhadap roster yang sudah
 * di-upload: sudah (disetujui) / menunggu / belum.
 */
class DmsRosterCakupanUploadTest extends TestCase
{
    private const AKTIF = [
        ['nama' => 'PT Pamapersada Nusantara', 'jumlah' => 100, 'site' => [
            ['nama' => '-', 'jumlah' => 5], ['nama' => 'BMO 2', 'jumlah' => 60], ['nama' => 'GMO', 'jumlah' => 35],
        ]],
        ['nama' => 'PT Puncak Makmur Jaya', 'jumlah' => 40, 'site' => [['nama' => 'SMO', 'jumlah' => 40]]],
        ['nama' => 'PT Trakindo Utama', 'jumlah' => 30, 'site' => [['nama' => 'LMO', 'jumlah' => 30]]],
        ['nama' => 'CV Damayanti', 'jumlah' => 15, 'site' => [['nama' => '-', 'jumlah' => 15]]],
    ];

    public function test_status_perusahaan_dan_site(): void
    {
        $hasil = Cakupan::bandingkan(self::AKTIF, [
            // Ejaan beda spasi tetap cocok; site beda spasi juga.
            $this->up('PT Pama Persada Nusantara', 'bmo2', true, false, 58),
            $this->up('PT Pama Persada Nusantara', 'GMO', false, true, 30),
            // Singkatan cocok ke nama lengkap.
            $this->up('PT PMJ', 'SMO', false, true, 39),
        ]);
        $pt = array_column($hasil['perusahaan'], null, 'nama');

        $this->assertSame('sudah', $pt['PT Pamapersada Nusantara']['status']);
        $this->assertSame(['PT Pama Persada Nusantara'], $pt['PT Pamapersada Nusantara']['alias']);
        $this->assertSame(
            [['nama' => 'BMO 2', 'karyawan' => 60, 'status' => 'sudah'], ['nama' => 'GMO', 'karyawan' => 35, 'status' => 'menunggu']],
            $pt['PT Pamapersada Nusantara']['site'],
        );
        $this->assertSame(1, $pt['PT Pamapersada Nusantara']['site_sudah']);
        $this->assertSame(88, $pt['PT Pamapersada Nusantara']['karyawan_roster']);
        $this->assertSame('menunggu', $pt['PT Puncak Makmur Jaya']['status']);
        $this->assertSame('belum', $pt['PT Trakindo Utama']['status']);
        // PT tanpa site: tidak menyumbang site.
        $this->assertSame([], $pt['CV Damayanti']['site']);
    }

    public function test_ringkasan_perusahaan_site_dan_karyawan(): void
    {
        $r = Cakupan::bandingkan(self::AKTIF, [
            $this->up('PT Pamapersada Nusantara', 'BMO 2', true, false, 58),
            $this->up('PT PMJ', 'SMO', false, true, 39),
        ])['ringkas'];

        $this->assertSame(['belum' => 2, 'menunggu' => 1, 'sudah' => 1, 'total' => 4], $r['pt']);
        // Site: BMO 2 sudah, SMO menunggu, GMO & LMO belum.
        $this->assertSame(['belum' => 2, 'menunggu' => 1, 'sudah' => 1, 'total' => 4], $r['site']);
        $this->assertSame(100, $r['karyawan']['sudah']);
        $this->assertSame(185, $r['karyawan']['total']);
    }

    public function test_site_di_roster_yang_tidak_ada_di_data_aktif_dan_nama_tidak_cocok(): void
    {
        $hasil = Cakupan::bandingkan(self::AKTIF, [
            $this->up('PT Trakindo Utama', 'BMO 1', true, false, 3),
            $this->up('PT Tidak Dikenal', 'HO', true, false, 2),
        ]);
        $pt = array_column($hasil['perusahaan'], null, 'nama');

        // PT sudah, tapi site aktifnya (LMO) belum ada roster.
        $this->assertSame('sudah', $pt['PT Trakindo Utama']['status']);
        $this->assertSame('belum', $pt['PT Trakindo Utama']['site'][0]['status']);
        $this->assertSame(['BMO 1'], $pt['PT Trakindo Utama']['site_lain']);
        $this->assertSame(['PT Tidak Dikenal'], $hasil['tidak_cocok']);
    }

    public function test_ejaan_persis_didahulukan_dari_singkatan(): void
    {
        $aktif = [
            ['nama' => 'PT SMB', 'jumlah' => 5, 'site' => []],
            ['nama' => 'PT Sumber Mitra Binungan', 'jumlah' => 9, 'site' => []],
        ];
        $pt = array_column(Cakupan::bandingkan($aktif, [$this->up('PT SMB', '', true, false, 5)])['perusahaan'], null, 'nama');

        $this->assertSame('sudah', $pt['PT SMB']['status']);
        $this->assertSame('belum', $pt['PT Sumber Mitra Binungan']['status']);
    }

    /**
     * @return array{perusahaan:string,site:string,disetujui:bool,menunggu:bool,karyawan:int}
     */
    private function up(string $pt, string $site, bool $ok, bool $tunggu, int $n): array
    {
        return ['perusahaan' => $pt, 'site' => $site, 'disetujui' => $ok, 'menunggu' => $tunggu, 'karyawan' => $n];
    }
}
