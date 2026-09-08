<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Services\ControlRoom\Reference\LocationReader;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Tests\TestCase;

/**
 * isCritical() murni string-matching (config('control-room.critical_area_keywords')),
 * jadi bisa diuji tanpa koneksi DB sama sekali.
 */
final class LocationReaderTest extends TestCase
{
    private LocationReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = new LocationReader(new PembatasanLVOlapQuery());
    }

    public function test_lokasi_mengandung_kata_kritis_terdeteksi_kritis(): void
    {
        $this->assertTrue($this->reader->isCritical('(B7) Area Kritis Blok 7', 'Front Double Pad Loading'));
    }

    public function test_lokasi_mengandung_kata_risk_terdeteksi_kritis(): void
    {
        $this->assertTrue($this->reader->isCritical('Aktivitas Area High Risk', 'IBDA Maintenance Pompa'));
    }

    public function test_detil_lokasi_mengandung_eksplorasi_terdeteksi_kritis(): void
    {
        $this->assertTrue($this->reader->isCritical('LATI', 'Area Jalan Eksplorasi'));
    }

    public function test_detil_lokasi_mengandung_area_pengeboran_terdeteksi_kritis(): void
    {
        $this->assertTrue($this->reader->isCritical('LATI', 'Area Pengeboran'));
    }

    public function test_pencocokan_tidak_case_sensitive(): void
    {
        $this->assertTrue($this->reader->isCritical('AREA KRITIS BLOK 9', 'Apa Saja'));
    }

    public function test_lokasi_biasa_tanpa_keyword_tidak_kritis(): void
    {
        $this->assertFalse($this->reader->isCritical('Workshop', 'Workshop MTN 059'));
    }

    public function test_ho_menyertakan_semua_site_papan_dan_alias_bmo2(): void
    {
        $keys = $this->reader->sourceKeysFor(ControlRoomSiteCode::HeadOffice);

        $this->assertContains('HO', $keys);
        $this->assertContains('BMO 1', $keys);
        $this->assertContains('BMO 2', $keys);
        $this->assertContains('BMO 2 BLOK 7', $keys);
        $this->assertContains('BMO 2 BLOK 8', $keys);
        $this->assertContains('PMO', $keys);
        $this->assertContains('SMO', $keys);
        $this->assertNotContains('MARINE', $keys);
        $this->assertNotContains('EKSPLORASI', $keys);
        $this->assertNotContains('JAKARTA', $keys);
    }

    public function test_bmo1_hanya_source_key_site_itu(): void
    {
        $this->assertSame(['BMO 1'], $this->reader->sourceKeysFor(ControlRoomSiteCode::Bmo1));
    }

    public function test_filter_site_ho_campuran_bmo1_hanya_bmo1(): void
    {
        $rows = [
            ['site' => 'HO', 'lokasi' => 'A', 'detail_lokasi' => 'A1'],
            ['site' => 'BMO 1', 'lokasi' => 'B', 'detail_lokasi' => 'B1'],
            ['site' => 'MARINE', 'lokasi' => 'C', 'detail_lokasi' => 'C1'],
        ];

        $ho = $this->reader->filterBySite($rows, ControlRoomSiteCode::HeadOffice);
        $bmo1 = $this->reader->filterBySite($rows, ControlRoomSiteCode::Bmo1);

        $this->assertSame(['HO', 'BMO 1'], $ho->pluck('site')->all());
        $this->assertSame(['BMO 1'], $bmo1->pluck('site')->all());
    }

    public function test_filter_bmo2_menyertakan_blok_tujuh_dan_delapan(): void
    {
        $rows = [
            ['site' => 'BMO 2', 'lokasi' => 'Pit', 'detail_lokasi' => 'A'],
            ['site' => 'BMO 2 BLOK 7', 'lokasi' => 'Area Kritis', 'detail_lokasi' => 'Front'],
            ['site' => 'BMO 2 BLOK 8', 'lokasi' => 'ROM', 'detail_lokasi' => 'Timur'],
            ['site' => 'BMO 1', 'lokasi' => 'Lain', 'detail_lokasi' => 'X'],
        ];

        $sites = $this->reader->filterBySite($rows, ControlRoomSiteCode::Bmo2)->pluck('site')->all();

        $this->assertSame(['BMO 2', 'BMO 2 BLOK 7', 'BMO 2 BLOK 8'], $sites);
    }
}
