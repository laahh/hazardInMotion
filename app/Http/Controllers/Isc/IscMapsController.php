<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Http\Controllers\Controller;
use App\Services\Isc\IscBasemapProxyService;
use App\Services\Isc\IscBoundaryMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Peta ISC: boundary IUPK (BounderyBC.js) + polygon/event dari Besigma.
 */
final class IscMapsController extends Controller
{
    public function __construct(
        private readonly IscBoundaryMapService $boundaries,
        private readonly IscBasemapProxyService $basemap,
    ) {}

    public function index(): View
    {
        $status = $this->boundaries->tableStatus();

        return view('isc.maps.index', [
            'connected' => $status['connected'],
            'tables' => $status['tables'],
            'iupkAsset' => asset('isc-assets/BounderyBC.js'),
            'boundariesUrl' => route('isc.maps.boundaries'),
            'overlayUrl' => route('isc.maps.overlay'),
            'pobUrl' => route('isc.maps.pob', ['source' => $status['connected'] ? 'live' : 'demo']),
            'postEventUrl' => route('isc.maps.post-event', ['source' => $status['connected'] ? 'live' : 'demo']),
            'postEventTrailUrl' => route('isc.maps.post-event.trail', ['source' => $status['connected'] ? 'live' : 'demo']),
            'cctvUrl' => route('isc.maps.cctv'),
            'mapsInterventionsUrl' => route('isc.maps.interventions', ['source' => $status['connected'] ? 'live' : 'demo']),
            'interventionsUrl' => route('isc.interventions.index'),
            'wmsUrl' => '',
            'wmsLayer' => IscBasemapProxyService::WMS_LAYER,
            'wmtsProxyUrl' => $this->wmtsProxyUrl(),
        ]);
    }

    /**
     * Pakai /wms-proxy yang sudah ada di production, bukan /isc/maps/wms
     * (route baru sering 404 karena route:cache lama).
     */
    private function wmtsProxyUrl(): string
    {
        $base = route('wms-proxy', [
            'url' => 'http://'.IscBasemapProxyService::GEOSERVER_HOST.':'.IscBasemapProxyService::GEOSERVER_PORT.'/geoserver/gwc/service/wmts',
            'layer' => 'geonode:basemap_allsite',
            'tilematrixset' => 'EPSG:900913',
            'Service' => 'WMTS',
            'Request' => 'GetTile',
            'Version' => '1.0.0',
            'Format' => 'image/png',
        ], false);

        return $base.'&TileMatrix=EPSG:900913:{z}&TileCol={x}&TileRow={y}';
    }

    public function wms(Request $request): Response
    {
        return $this->basemap->wms($request);
    }

    public function wmts(int $z, int $x, int $y): Response
    {
        return $this->basemap->wmts($z, $x, $y);
    }

    public function boundaries(): JsonResponse
    {
        return response()->json($this->boundaries->boundariesGeoJson());
    }

    public function overlay(): JsonResponse
    {
        $status = $this->boundaries->tableStatus();

        return response()->json([
            'connected' => $status['connected'],
            'tables' => $status['tables'],
            'overlay' => $this->boundaries->overlayData(),
        ]);
    }
}
