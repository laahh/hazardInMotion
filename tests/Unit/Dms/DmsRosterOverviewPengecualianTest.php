<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterMasterCache as Ringkas;
use App\Services\Dms\Roster\DmsRosterOverviewPengecualian as Kecuali;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Overview Roster tidak menghitung PT Berau Coal, PT Berau Coal Energy,
 * PT Unggul Jaya Berkah, dan site MARINE (config overview_dikecualikan).
 */
class DmsRosterOverviewPengecualianTest extends TestCase
{
    private const KUNCI = 'dms_roster:master_ringkas:v1';

    private mixed $simpan = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dms_roster.master.overview_dikecualikan' => [
            'perusahaan' => ['PT Berau Coal', 'PT Berau Coal Energy', 'PT Unggul Jaya Berkah'],
            'site' => ['MARINE'],
        ]]);
        $this->simpan = Cache::get(self::KUNCI);
    }

    protected function tearDown(): void
    {
        $this->simpan === null ? Cache::forget(self::KUNCI) : Cache::forever(self::KUNCI, $this->simpan);
        Ringkas::lupakan();
        parent::tearDown();
    }

    public function test_nama_dicocokkan_tanpa_awalan_dan_tidak_menyapu_nama_mirip(): void
    {
        $this->assertTrue(Kecuali::perusahaan('PT. Berau Coal'));
        $this->assertTrue(Kecuali::perusahaan('pt berau coal energy'));
        $this->assertTrue(Kecuali::perusahaan('PT Unggul Jaya Berkah'));
        $this->assertFalse(Kecuali::perusahaan('Politeknik Sinar Mas Berau Coal'));
        $this->assertFalse(Kecuali::perusahaan('PT Prima Unggul Persada'));
        $this->assertTrue(Kecuali::site('Marine'));
        $this->assertFalse(Kecuali::site('-'));
        $this->assertFalse(Kecuali::site('BMO 1'));
    }

    public function test_karyawan_aktif_upload_dan_sid_disaring(): void
    {
        $aktif = Kecuali::saringAktif([
            ['nama' => 'PT Berau Coal', 'jumlah' => 100, 'site' => [['nama' => 'HO', 'jumlah' => 100]]],
            ['nama' => 'PT Asian Bulk Logistics', 'jumlah' => 613, 'site' => [['nama' => 'MARINE', 'jumlah' => 613]]],
            ['nama' => 'PT PMJ', 'jumlah' => 15, 'site' => [['nama' => 'MARINE', 'jumlah' => 5], ['nama' => 'SMO', 'jumlah' => 10]]],
        ]);
        $this->assertSame([['nama' => 'PT PMJ', 'jumlah' => 10, 'site' => [['nama' => 'SMO', 'jumlah' => 10]]]], $aktif);

        $upload = Kecuali::saringUpload([
            ['perusahaan' => 'PT Unggul Jaya Berkah', 'site' => 'BMO 1'],
            ['perusahaan' => 'PT PMJ', 'site' => 'Marine'],
            ['perusahaan' => 'PT PMJ', 'site' => 'SMO'],
        ]);
        $this->assertSame([['perusahaan' => 'PT PMJ', 'site' => 'SMO']], $upload);

        $this->assertNull(Kecuali::saringSid(null));
        $this->assertSame(['PT PMJ' => ['SMO' => ['B1']]], Kecuali::saringSid([
            'PT Berau Coal Energy' => ['HO' => ['A1']],
            'PT PMJ' => ['MARINE' => ['M1'], 'SMO' => ['B1']],
        ]));
    }

    public function test_perusahaan_terupload_overview_tanpa_yang_dikecualikan(): void
    {
        Cache::forever(self::KUNCI, [
            'versi' => 'uji', 'dihitung' => 'uji',
            'a' => [
                [1, 'PT Berau Coal', 'HO', '2026-10-01', '2026-10-31'],
                [2, 'PT PMJ', 'SMO', '2026-10-05', '2026-10-20'],
                [2, 'PT PMJ', 'MARINE', '2026-09-01', '2026-11-30'],
                [3, 'PT Kapal', 'MARINE', '2026-10-01', '2026-10-31'],
            ],
            'b' => [
                ['PT Berau Coal', 'HO', 'A1', true, false],
                ['PT PMJ', 'SMO', 'B1', false, true],
                ['PT PMJ', 'MARINE', 'B2', false, true],
                ['PT Kapal', 'MARINE', 'K1', true, false],
            ],
            'upload' => [
                1 => ['status' => 'APPROVED', 'dibuat' => '2026-10-01 08:00:00'],
                2 => ['status' => 'PENDING', 'dibuat' => '2026-10-03 08:00:00'],
                3 => ['status' => 'APPROVED', 'dibuat' => '2026-10-03 08:00:00'],
            ],
        ]);
        Ringkas::lupakan();

        $semua = array_column(Ringkas::perusahaanTerupload([]), null, 'perusahaan');
        $this->assertSame(['PT Berau Coal', 'PT Kapal', 'PT PMJ'], array_keys($semua));

        $pt = array_column(Ringkas::perusahaanTerupload([], true), null, 'perusahaan');
        $this->assertSame(['PT PMJ'], array_keys($pt));
        $this->assertSame(1, $pt['PT PMJ']['karyawan']);
        $this->assertSame(['SMO'], $pt['PT PMJ']['site']);
        $this->assertSame(['2026-10-05', '2026-10-20'], [$pt['PT PMJ']['dari'], $pt['PT PMJ']['sampai']]);
    }
}
