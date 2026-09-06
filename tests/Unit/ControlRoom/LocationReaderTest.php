<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

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
}
