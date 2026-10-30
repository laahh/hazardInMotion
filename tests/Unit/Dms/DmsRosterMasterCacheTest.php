<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterMasterCache as Ringkas;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Agregat halaman Master Roster diturunkan di PHP dari ringkasan latar
 * (query A per upload/ejaan/site, B per ejaan/site/SID).
 */
class DmsRosterMasterCacheTest extends TestCase
{
    private const KUNCI = 'dms_roster:master_ringkas:v1';

    private mixed $simpan = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simpan = Cache::get(self::KUNCI);
        Cache::forever(self::KUNCI, [
            'versi' => 'uji', 'dihitung' => 'uji',
            'a' => [
                [1, 'PT Pama Persada Nusantara', 'BMO 2', '2026-10-01', '2026-10-31'],
                [2, 'PT Pamapersada Nusantara', 'GMO', '2026-11-01', '2026-11-30'],
                [3, 'PT PMJ', 'SMO', '2026-10-05', '2026-10-20'],
                [4, 'PT Ditolak', 'LMO', '2026-10-01', '2026-10-02'],
                [5, '', '', '2026-09-01', '2026-09-02'],
            ],
            'b' => [
                ['PT Pama Persada Nusantara', 'BMO 2', 'A1', true, false],
                ['PT Pama Persada Nusantara', 'BMO 2', 'A2', true, false],
                ['PT Pamapersada Nusantara', 'GMO', 'A1', false, true],
                ['PT PMJ', 'SMO', 'B1', false, true],
                ['', '', 'C1', false, true],
            ],
            'upload' => [
                1 => ['status' => 'APPROVED', 'dibuat' => '2026-10-01 08:00:00'],
                2 => ['status' => 'PENDING', 'dibuat' => '2026-10-25 08:00:00'],
                3 => ['status' => 'PENDING', 'dibuat' => '2026-10-03 08:00:00'],
                4 => ['status' => 'REJECTED', 'dibuat' => '2026-10-02 08:00:00'],
                5 => ['status' => 'PENDING', 'dibuat' => '2026-09-01 08:00:00'],
            ],
        ]);
        Ringkas::lupakan();
    }

    protected function tearDown(): void
    {
        $this->simpan === null ? Cache::forget(self::KUNCI) : Cache::forever(self::KUNCI, $this->simpan);
        Ringkas::lupakan();
        parent::tearDown();
    }

    public function test_perusahaan_terupload_menggabungkan_ejaan_lewat_peta(): void
    {
        $pt = array_column(Ringkas::perusahaanTerupload([
            'PT Pama Persada Nusantara' => 'PT Pamapersada Nusantara',
        ]), null, 'perusahaan');

        $pama = $pt['PT Pamapersada Nusantara'];
        $this->assertSame(['PT Pama Persada Nusantara', 'PT Pamapersada Nusantara'], $pama['ejaan']);
        $this->assertSame(2, $pama['karyawan']);
        $this->assertSame(2, $pama['upload']);
        $this->assertSame(1, $pama['disetujui']);
        $this->assertSame(1, $pama['menunggu']);
        $this->assertSame(['2026-10-01', '2026-11-30'], [$pama['dari'], $pama['sampai']]);
        $this->assertSame('2026-10-25 08:00:00', $pama['terakhir']);
        $this->assertSame(['BMO 2', 'GMO'], $pama['site']);
        // Upload ditolak tidak muncul; jadwal tanpa perusahaan tetap tampil.
        $this->assertArrayNotHasKey('PT Ditolak', $pt);
        $this->assertSame(1, $pt['(tanpa perusahaan)']['karyawan']);
    }

    public function test_upload_per_site_dan_turunan_lainnya(): void
    {
        $site = [];
        foreach (Ringkas::uploadPerSite() as $r) {
            $site[$r['perusahaan'].'|'.$r['site']] = $r;
        }

        $this->assertSame(['perusahaan' => 'PT Pama Persada Nusantara', 'site' => 'BMO 2', 'disetujui' => true, 'menunggu' => false, 'karyawan' => 2], $site['PT Pama Persada Nusantara|BMO 2']);
        $this->assertTrue($site['PT PMJ|SMO']['menunggu']);
        $this->assertSame(['A1' => true, 'A2' => true], Ringkas::sidRoster(['PT Pama Persada Nusantara', 'PT Pamapersada Nusantara']));
        $this->assertSame(['PT Pama Persada Nusantara'], Ringkas::ptPerUpload()[1]);
        $this->assertContains('PT Ditolak', Ringkas::ejaan());
        $this->assertNotContains('', Ringkas::ejaan());
        $this->assertCount(4, Ringkas::pasangan());
    }
}
