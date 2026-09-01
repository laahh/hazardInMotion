<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Services\Isc\IscBasemapProxyService;
use Illuminate\Http\Request;
use Tests\TestCase;

final class IscBasemapProxyServiceTest extends TestCase
{
    public function test_wms_query_uses_allowed_layer_and_web_mercator(): void
    {
        $service = new IscBasemapProxyService();
        $query = $service->wmsQuery(Request::create('/isc/maps/wms', 'GET', [
            'service' => 'WMS',
            'request' => 'GetMap',
            'layers' => 'basemap:basemap_allsite',
            'bbox' => '1,2,3,4',
            'width' => 256,
            'height' => 256,
            'srs' => 'EPSG:3857',
        ]));

        $this->assertSame('WMS', $query['SERVICE']);
        $this->assertSame('GetMap', $query['REQUEST']);
        $this->assertSame('basemap:basemap_allsite', $query['LAYERS']);
        $this->assertSame('1,2,3,4', $query['BBOX']);
        $this->assertSame('EPSG:3857', $query['SRS']);
        $this->assertSame('image/png', $query['FORMAT']);
    }

    public function test_unknown_layer_is_replaced_with_allsite(): void
    {
        $service = new IscBasemapProxyService();
        $query = $service->wmsQuery(Request::create('/isc/maps/wms', 'GET', [
            'LAYERS' => 'evil:layer',
            'BBOX' => '1,2,3,4',
        ]));

        $this->assertSame(IscBasemapProxyService::WMS_LAYER, $query['LAYERS']);
    }
}
