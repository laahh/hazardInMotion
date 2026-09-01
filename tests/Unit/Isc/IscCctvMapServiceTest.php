<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Services\Isc\IscCctvMapService;
use Tests\TestCase;

final class IscCctvMapServiceTest extends TestCase
{
    public function test_map_row_keeps_slim_fields_and_drops_secrets(): void
    {
        $row = (object) [
            'id' => 12,
            'no_cctv' => 'CCTV-BMO-01',
            'nama_cctv' => 'Pit Office',
            'site' => 'BMO',
            'perusahaan' => 'PT Berau Coal',
            'kondisi' => 'Baik',
            'status' => 'Live View',
            'connected' => 'Yes',
            'lokasi_pemasangan' => 'Office pit',
            'latitude' => '2.17500000',
            'longitude' => '117.48000000',
            'link_akses' => 'https://example.test/live',
            'user_name' => 'admin',
            'password' => 'secret',
        ];

        $point = app(IscCctvMapService::class)->mapRow($row);

        $this->assertNotNull($point);
        $this->assertSame('12', $point['id']);
        $this->assertSame('Pit Office', $point['name']);
        $this->assertSame(2.175, $point['lat']);
        $this->assertSame(117.48, $point['lng']);
        $this->assertSame('BMO', $point['site_code']);
        $this->assertTrue($point['ok']);
        $this->assertTrue($point['has_point']);
        $this->assertTrue($point['has_link']);
        $this->assertSame('https://example.test/live', $point['link']);
        $this->assertArrayNotHasKey('password', $point);
        $this->assertArrayNotHasKey('user_name', $point);
    }

    public function test_map_row_rejects_zero_and_unsafe_links(): void
    {
        $service = app(IscCctvMapService::class);

        $empty = $service->mapRow((object) [
            'id' => 1,
            'nama_cctv' => 'Tanpa titik',
            'latitude' => 0,
            'longitude' => 0,
        ]);
        $this->assertNotNull($empty);
        $this->assertFalse($empty['has_point']);
        $this->assertNull($empty['lat']);

        $point = $service->mapRow((object) [
            'id' => 2,
            'nama_cctv' => 'Cam',
            'latitude' => 2.1,
            'longitude' => 117.4,
            'link_akses' => 'javascript:alert(1)',
            'kondisi' => 'Rusak',
        ]);

        $this->assertNotNull($point);
        $this->assertFalse($point['ok']);
        $this->assertFalse($point['has_link']);
        $this->assertNull($point['link']);
    }

    public function test_demo_payload_has_cameras_across_sites(): void
    {
        $data = app(IscCctvMapService::class)->payload(true);

        $this->assertSame('demo', $data['source']);
        $this->assertGreaterThanOrEqual(5, $data['count']);
        $this->assertSame(5, $data['summary']['total']);
        $this->assertSame(3, $data['summary']['on']);
        $this->assertSame(2, $data['summary']['off']);
        $siteCodes = array_column($data['summary']['sites'], 'code');
        $this->assertContains('BMO', $siteCodes);
        $bmo = collect($data['summary']['sites'])->firstWhere('code', 'BMO');
        $this->assertSame(1, $bmo['total']);
        $this->assertSame(1, $bmo['on']);
        $this->assertArrayHasKey('lat', $data['cameras'][0]);
        $this->assertArrayHasKey('lng', $data['cameras'][0]);
        $codes = array_column($data['cameras'], 'site_code');
        $this->assertContains('BMO', $codes);
        $this->assertContains('LMO', $codes);
    }
}
