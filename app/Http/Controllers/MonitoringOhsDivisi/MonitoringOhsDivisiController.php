<?php

declare(strict_types=1);

namespace App\Http\Controllers\MonitoringOhsDivisi;

use App\Http\Controllers\Controller;
use App\Services\Isc\IscBasemapProxyService;
use App\Services\Isc\IscBoundaryMapService;
use Illuminate\View\View;

/**
 * Monitoring OHS Divisi — salinan awal halaman peta /isc/maps
 * (IscMapsController::index). Data peta (boundary, overlay, POB, CCTV,
 * hazard, intervensi) masih memakai endpoint ISC yang sudah ada; ganti di
 * sini bila halaman ini perlu sumber data sendiri.
 */
final class MonitoringOhsDivisiController extends Controller
{
    public function __construct(
        private readonly IscBoundaryMapService $boundaries,
    ) {}

    public function index(): View
    {
        // Samakan bootstrap dengan /besigma/connection-test (fresh probe path).
        $this->boundaries->prepareLikeConnectionTest();
        $status = $this->boundaries->tableStatus();
        $sumber = $status['connected'] ? 'live' : 'demo';

        return view('monitoring-ohs-divisi.index', [
            'connected' => $status['connected'],
            'tables' => $status['tables'],
            'iupkAsset' => asset('isc-assets/BounderyBC.js'),
            'boundariesUrl' => route('isc.maps.boundaries'),
            'overlayUrl' => route('isc.maps.overlay'),
            'pobUrl' => route('isc.maps.pob', ['source' => $sumber]),
            'pobExportUrl' => route('isc.maps.pob.export', ['source' => $sumber]),
            'postEventUrl' => route('isc.maps.post-event', ['source' => $sumber]),
            'postEventTrailUrl' => route('isc.maps.post-event.trail', ['source' => $sumber]),
            'cctvUrl' => route('isc.maps.cctv'),
            'mapsInterventionsUrl' => route('isc.maps.interventions', ['source' => $sumber]),
            'mapsHazardReportsUrl' => route('isc.maps.hazard-reports.store'),
            'mapsHazardReportsListUrl' => route('isc.maps.hazard-reports.list'),
            'mapsHazardReportsHistoricalUrl' => route('isc.maps.hazard-reports.historical'),
            'mapsHazardEmployeesUrl' => route('isc.maps.hazard-employees'),
            'mapsHazardSysUserUrl' => route('isc.maps.hazard-sysuser'),
            'mapsHazardLokasiUrl' => route('isc.maps.hazard-lokasi'),
            'mapsHazardDetailLokasiUrl' => route('isc.maps.hazard-detail-lokasi'),
            'mapsHazardPjaBcUrl' => route('isc.maps.hazard-pja-bc'),
            'mapsHazardPjaMitraUrl' => route('isc.maps.hazard-pja-mitra'),
            'interventionsUrl' => route('isc.interventions.index'),
            'wmsUrl' => '',
            'wmsLayer' => IscBasemapProxyService::WMS_LAYER,
            'wmtsProxyUrl' => $this->wmtsProxyUrl(),
        ]);
    }

    /**
     * Pakai /wms-proxy yang sudah ada di production (sama dengan IscMapsController).
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
}
