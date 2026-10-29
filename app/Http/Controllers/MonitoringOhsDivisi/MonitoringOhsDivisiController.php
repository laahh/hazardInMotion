<?php

declare(strict_types=1);

namespace App\Http\Controllers\MonitoringOhsDivisi;

use App\Http\Controllers\Controller;
use App\Services\Isc\IscBasemapProxyService;
use App\Services\Isc\IscBoundaryMapService;
use App\Services\MonitoringOhsDivisi\OhsDivisiPobService;
use App\Services\MonitoringOhsDivisi\OhsDivisiStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Monitoring OHS Divisi — salinan awal halaman peta /isc/maps
 * (IscMapsController::index). Data POB lewat pob() di bawah: sama dengan ISC,
 * tetapi Check-in RFID hanya karyawan departemen OHS Divisi
 * (config/monitoring_ohs_divisi.php). Data peta lain (boundary, overlay,
 * CCTV, hazard, intervensi) masih memakai endpoint ISC yang sudah ada.
 */
final class MonitoringOhsDivisiController extends Controller
{
    public function __construct(
        private readonly IscBoundaryMapService $boundaries,
        private readonly OhsDivisiPobService $pob,
        private readonly OhsDivisiStatusService $status,
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
            'pobUrl' => route('monitoring-ohs-divisi.pob', ['source' => $sumber]),
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
            'statusUrl' => route('monitoring-ohs-divisi.status'),
        ]);
    }

    /**
     * Status RFID terkini karyawan departemen OHS Divisi (onsite / sudah
     * check-out / belum check-in) beserta lama di site.
     */
    public function status(): JsonResponse
    {
        return response()->json(['success' => true] + $this->status->status());
    }

    /**
     * Snapshot POB (format sama dengan IscPobController::index), Check-in RFID
     * hanya departemen OHS Divisi.
     */
    public function pob(Request $request): JsonResponse
    {
        $demo = $request->query('source', 'demo') !== 'live';
        $data = $this->pob->snapshot($request->boolean('fresh'), $demo);

        return response()->json([
            'success' => true,
            'source' => $data['source'] ?? ($demo ? 'demo' : 'live'),
            'generated_at' => $data['generated_at'],
            'stale_gps_seconds' => $data['stale_gps_seconds'],
            'besigma_connected' => $data['besigma_connected'],
            'besigma_error' => $data['besigma_error'],
            'rfid_available' => $data['rfid_available'],
            'summary' => $data['summary'],
            'sites' => $data['sites'] ?? [],
            'checkins' => $data['checkins'] ?? [],
            'reconcile' => $data['reconcile'],
            'people' => $data['people'],
            'hazard_features' => $data['hazard_features'] ?? [],
            'departemen' => $data['departemen'],
            'departemen_terbaca' => $data['departemen_terbaca'],
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
