@extends('layouts.master')

@section('title', 'WMS Map - Beraucoal')
@section('css')
<style>
    .map-container {
        width: 100%;
        height: calc(100vh - 200px);
        min-height: 600px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .map-header {
        margin-bottom: 24px;
    }

    .map-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .map-subtitle {
        font-size: 14px;
        color: #6b7280;
    }

    .map-controls {
        margin-bottom: 16px;
        padding: 16px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
    }

    .map-info {
        margin-top: 16px;
        padding: 12px;
        background: #f3f4f6;
        border-radius: 6px;
        font-size: 13px;
        color: #6b7280;
    }

    /* Popup style untuk CCTV info */
    .ol-popup {
        position: absolute;
        background-color: white;
        box-shadow: 0 1px 4px rgba(0,0,0,0.2);
        padding: 15px;
        border-radius: 10px;
        border: 1px solid #cccccc;
        bottom: 12px;
        left: -50px;
        min-width: 200px;
    }

    .ol-popup:after, .ol-popup:before {
        top: 100%;
        border: solid transparent;
        content: " ";
        height: 0;
        width: 0;
        position: absolute;
        pointer-events: none;
    }

    .ol-popup:after {
        border-top-color: white;
        border-width: 10px;
        left: 48px;
        margin-left: -10px;
    }

    .ol-popup:before {
        border-top-color: #cccccc;
        border-width: 11px;
        left: 48px;
        margin-left: -11px;
    }

    .ol-popup-closer {
        text-decoration: none;
        position: absolute;
        top: 2px;
        right: 8px;
        color: #333;
        font-size: 18px;
        font-weight: bold;
    }

    .ol-popup-closer:hover {
        color: #000;
    }

    .cctv-popup-content {
        margin: 0;
    }

    .cctv-popup-content h3 {
        margin: 0 0 10px 0;
        color: #333;
        font-size: 16px;
    }

    .cctv-popup-content p {
        margin: 5px 0;
        font-size: 13px;
        color: #666;
    }

    .cctv-popup-content button {
        margin-top: 10px;
        padding: 8px 16px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
    }

    .cctv-popup-content button:hover {
        background-color: #0056b3;
    }

    /* Modal untuk CCTV Stream */
    .cctv-modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.8);
    }

    .cctv-modal-content {
        position: relative;
        background-color: #fff;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 90%;
        max-width: 1200px;
        border-radius: 8px;
    }

    .cctv-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .cctv-modal-header h3 {
        margin: 0;
    }

    .cctv-modal-close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .cctv-modal-close:hover {
        color: #000;
    }

    .cctv-stream-container {
        width: 100%;
        height: 600px;
        background-color: #000;
        position: relative;
    }

    .cctv-stream-container iframe {
        width: 100%;
        height: 100%;
        border: none;
    }

    .cctv-portal-info {
        color: #333;
        font-size: 14px;
        line-height: 1.5;
    }

    .cctv-portal-info p {
        margin: 8px 0;
    }

    .cctv-portal-info a {
        color: #007bff;
        text-decoration: underline;
    }

    .cctv-portal-info a:hover {
        text-decoration: none;
    }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@8.2.0/ol.css">
@endsection

@section('content')
<div class="map-header">
    <h1 class="map-title">WMS Map - Beraucoal</h1>
    <p class="map-subtitle">Peta dari WMS Server - Pilih server dari dropdown di bawah</p>
</div>

 <div class="row">
          <div class="col-12 col-xl-4 d-flex">
             <div class="card rounded-4 w-100">
               <div class="card-body">
                 <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="">
                      <h2 class="mb-0">$9,568</h2>
                    </div>
                    <div class="">
                      <p class="dash-lable d-flex align-items-center gap-1 rounded mb-0 bg-danger text-danger bg-opacity-10"><span class="material-icons-outlined fs-6">arrow_downward</span>8.6%</p>
                    </div>
                  </div>
                  <p class="mb-0">Average Weekly Sales</p>
                   <div id="chart1"></div>
               </div>
             </div>
          </div>
          <div class="col-12 col-xl-8 d-flex">
            <div class="card rounded-4 w-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-around flex-wrap gap-4 p-4">
                  <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <a href="javascript:;" class="mb-2 wh-48 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">camera</i>
                    </a>
                    <h3 class="mb-0">120</h3>
                    <p class="mb-0">CCTV</p>
                  </div>
                  <div class="vr"></div>
                  <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <a href="javascript:;" class="mb-2 wh-48 bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center">
                    <i class="material-icons-outlined">report_problem</i>
                    </a>
                    <h3 class="mb-0">$96,147</h3>
                    <p class="mb-0">Income</p>
                  </div>
                  <div class="vr"></div>
                  <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <a href="javascript:;" class="mb-2 wh-48 bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center">
                     <i class="material-icons-outlined">report_problem</i>
                    </a>
                    <h3 class="mb-0">846</h3>
                    <p class="mb-0">Notifications</p>
                  </div>
                  <div class="vr"></div>
                  
                  <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <a href="javascript:;" class="mb-2 wh-48 bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">payment</i>
                    </a>
                    <h3 class="mb-0">$84,472</h3>
                    <p class="mb-0">Payment</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

<div class="map-controls">
    <div class="row">
        <div class="col-md-3">
            <label for="wmsServerSelect" class="form-label">WMS Server:</label>
            <select id="wmsServerSelect" class="form-select">
                <option value="smo">SMO Block B1</option>
                <option value="smoA">SMO Block A</option>
                <option value="smoBEastWest">SMO Block B East-West</option>
                <option value="bmo">BMO Block 1-4</option>
                <option value="bmo56">BMO Block 5-6</option>
                <option value="bmo7">BMO Block 7</option>
                <option value="bmo8">BMO Block 8</option>
                <option value="bmo9">BMO Block 9</option>
                <option value="bmo10">BMO Block 10</option>
                <option value="bmoParapatan">BMO Block Parapatan</option>
                <option value="gurimbang">Gurimbang</option>
                <option value="khdtk">KHDTK</option>
                <option value="punan">Punan</option>
                <option value="lati">Lati</option>
            </select>
        </div>
        <!-- <div class="col-md-3">
            <label for="layerSelect" class="form-label">Layer:</label>
            <select id="layerSelect" class="form-select">
                <option value="0">Loading...</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="projectionSelect" class="form-label">Projection:</label>
            <select id="projectionSelect" class="form-select">
                <option value="EPSG:3857">Web Mercator (EPSG:3857)</option>
                <option value="EPSG:4326">WGS84 (EPSG:4326)</option>
            </select>
        </div> -->
        <div class="col-md-3">
            <label class="form-label">GeoJSON Layers:</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="showAreaCctv" checked>
                <label class="form-check-label" for="showAreaCctv">
                    Area CCTV BMO1 FAD
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="showAreaKerja" checked>
                <label class="form-check-label" for="showAreaKerja">
                    Area Kerja BMO1 FAD
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="showAreaKerjaBmo2Pama" checked>
                <label class="form-check-label" for="showAreaKerjaBmo2Pama">
                    Area Kerja BMO2 PAMA
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="showAreaCctvBmo2Pama" checked>
                <label class="form-check-label" for="showAreaCctvBmo2Pama">
                    Area CCTV BMO2 PAMA
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="showDifferenceBmo2Pama" checked>
                <label class="form-check-label" for="showDifferenceBmo2Pama">
                    Difference BMO2 PAMA
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="showSymmetricalDifferenceBmo2Pama" checked>
                <label class="form-check-label" for="showSymmetricalDifferenceBmo2Pama">
                    Symmetrical Difference BMO2 PAMA
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="showIntersectionBmo2Pama" checked>
                <label class="form-check-label" for="showIntersectionBmo2Pama">
                    Intersection BMO2 PAMA
                </label>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div id="map" class="map-container"></div>
        <div id="popup" class="ol-popup">
            <a href="#" id="popup-closer" class="ol-popup-closer"></a>
            <div id="popup-content" class="cctv-popup-content"></div>
        </div>
    </div>
</div>

<!-- Modal untuk CCTV Stream -->
<div id="cctvModal" class="cctv-modal">
    <div class="cctv-modal-content">
        <div class="cctv-modal-header">
            <h3 id="cctvModalTitle">CCTV Live Stream</h3>
            <span class="cctv-modal-close" id="cctvModalClose">&times;</span>
        </div>
        <div class="cctv-stream-container" id="cctvStreamContainer">
            <iframe id="cctvStreamFrame" src="" allowfullscreen></iframe>
        </div>
    </div>
</div>
    </div>
</div>

<div class="map-info">
    <strong>Info:</strong> 
    <ul style="margin: 8px 0; padding-left: 20px;">
        <li>Klik pada icon CCTV (ikon kamera merah) untuk melihat informasi detail</li>
        <li>Data CCTV diambil dari database <strong>cctv_data_bmo2</strong> - hanya menampilkan CCTV yang memiliki koordinat (longitude & latitude)</li>
        <li>Total CCTV yang ditampilkan: <strong id="cctvCount">{{ count($cctvLocations ?? []) }}</strong> unit</li>
        <li><strong>Layer BMO2 PAMA:</strong> Gunakan checkbox di bagian "GeoJSON Layers" untuk menampilkan/menyembunyikan layer-layer berikut:
            <ul style="margin: 4px 0; padding-left: 20px;">
                <li>Area Kerja BMO2 PAMA - Menampilkan area kerja dengan warna berbeda berdasarkan tipe (Pit: merah, Hauling: orange, Infra Tambang: biru)</li>
                <li>Area CCTV BMO2 PAMA - Menampilkan area coverage CCTV dengan warna ungu</li>
                <li>Difference BMO2 PAMA - Menampilkan area yang berbeda antara Area Kerja dan Area CCTV (warna merah)</li>
                <li>Symmetrical Difference BMO2 PAMA - Menampilkan area symmetrical difference (warna orange)</li>
                <li>Intersection BMO2 PAMA - Menampilkan area yang overlap antara Area Kerja dan Area CCTV (warna hijau)</li>
            </ul>
        </li>
        <li>Klik pada polygon GeoJSON untuk melihat informasi detail area</li>
        <li>Jika peta tidak muncul, coba pilih layer yang berbeda dari dropdown di atas</li>
        <li>Server mungkin memerlukan parameter layer tertentu - coba mulai dengan "All Layers (Kosong)"</li>
        <li>Jika masih error, kemungkinan ada masalah dengan CORS atau server configuration</li>
        <li>Silakan cek console browser (F12) untuk detail error dan status loading layer</li>
    </ul>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/ol@8.2.0/dist/ol.js"></script>
<script src="https://cdn.jsdelivr.net/npm/proj4@2.9.0/dist/proj4.js"></script>
<!-- Load Area Kerja data dari file terpisah -->
<script src="{{ asset('js/area-kerja-bmo1-fad.js') }}"></script>
<!-- Load BMO2 PAMA GeoJSON data -->
<script src="{{ asset('js/area-kerja-bmo2-pama.js') }}"></script>
<script src="{{ asset('js/area-cctv-bmo2-pama.js') }}"></script>
<script src="{{ asset('js/difference_bmo2-pama.js') }}"></script>
<script src="{{ asset('js/symmetrical_difference_bmo2-pama.js') }}"></script>
<script src="{{ asset('js/intersection_bmo2-pama.js') }}"></script>
<script>
    // Function to build RTSP URL from CCTV data
    function buildRtspUrl(cctv) {
        if (cctv.rtspUrl) {
            return cctv.rtspUrl;
        }
        if (cctv.rtspHost && cctv.rtspPort && cctv.rtspChannel && cctv.rtspUsername && cctv.rtspPassword) {
            const user = encodeURIComponent(cctv.rtspUsername);
            const pass = encodeURIComponent(cctv.rtspPassword);
            const host = cctv.rtspHost;
            const port = cctv.rtspPort;
            const channel = cctv.rtspChannel;
            return `rtsp://${user}:${pass}@${host}:${port}/Streaming/Channels/${channel}`;
        }
        return '';
    }

    // WMS Server Configuration
    const wmsServers = {
        smo: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_SMO_BLOCK_B1_2510/MapServer/WMSServer',
            name: 'SMO Block B1',
            bbox: [117.402228, 2.150819, 117.505579, 2.221687],
            center: [117.4539035, 2.186253]
        },
        smoA: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_SMO_BLOCK_A/MapServer/WMSServer',
            name: 'SMO Block A',
            bbox: [117.378740, 2.154163, 117.409737, 2.199252],
            center: [117.3942385, 2.1767075]
        },
        smoBEastWest: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_SMO_BLOCK_B_EAST_WEST/MapServer/WMSServer',
            name: 'SMO Block B East-West',
            bbox: [117.333284, 2.166645, 117.420828, 2.333354],
            center: [117.377056, 2.2499995]
        },
        bmo: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_BMO_BLOCK_1_4/MapServer/WMSServer',
            name: 'BMO Block 1-4',
            bbox: [117.437891, 2.026662, 117.483348, 2.122948],
            center: [117.4606195, 2.074805]
        },
        bmo56: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_BMO_BLOCK_5_6/MapServer/WMSServer',
            name: 'BMO Block 5-6',
            bbox: [117.405839, 1.971650, 117.475021, 2.095264],
            center: [117.44043, 2.033457]
        },
        bmo7: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_BMO_BLOCK_7/MapServer/WMSServer',
            name: 'BMO Block 7',
            bbox: [117.312358, 1.941393, 117.426208, 2.036692],
            center: [117.369283, 1.9890425]
        },
        bmo8: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_BMO_BLOCK_8/MapServer/WMSServer',
            name: 'BMO Block 8',
            bbox: [117.143050, 1.873312, 117.353350, 2.000030],
            center: [117.2482, 1.936671]
        },
        bmo9: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_BMO_BLOCK_9/MapServer/WMSServer',
            name: 'BMO Block 9',
            bbox: [117.129991, 1.936764, 117.183360, 2.043340],
            center: [117.1566755, 1.990052]
        },
        bmo10: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_BMO_BLOCK_10/MapServer/WMSServer',
            name: 'BMO Block 10',
            bbox: [117.166646, 2.033321, 117.241680, 2.132808],
            center: [117.204163, 2.0830645]
        },
        bmoParapatan: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_BMO_BLOCK_PARAPATAN/MapServer/WMSServer',
            name: 'BMO Block Parapatan',
            bbox: [117.431663, 2.090234, 117.483621, 2.146128],
            center: [117.457642, 2.118181]
        },
        gurimbang: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_GURIMBANG/MapServer/WMSServer',
            name: 'Gurimbang',
            bbox: [117.483325, 2.091636, 117.625039, 2.212991],
            center: [117.554182, 2.1523135]
        },
        khdtk: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_KHDTK/MapServer/WMSServer',
            name: 'KHDTK',
            bbox: [117.175827, 1.900871, 117.241266, 1.948915],
            center: [117.2085465, 1.924893]
        },
        punan: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_PUNAN/MapServer/WMSServer',
            name: 'Punan',
            bbox: [117.204989, 2.166649, 117.333347, 2.248363],
            center: [117.269168, 2.207506]
        },
        lati: {
            url: 'https://sgi.beraucoal.co.id/server/services/Basemap_Layer_LATI_2510/MapServer/WMSServer',
            name: 'Lati',
            bbox: [117.509916, 2.189945, 117.638408, 2.418367],
            center: [117.574162, 2.304156]
        }
    };
    
    // Current WMS server
    let currentWmsServer = 'smo';
    let wmsUrl = wmsServers[currentWmsServer].url;
    
    // Default layer name - akan diupdate setelah GetCapabilities
    let currentLayer = '';
    let wmsLayer = null;
    let cctvLayer = null;
    let popupOverlay = null;
    
    // Function to create WMS layer - UPDATE BOUNDING BOX
    function createWMSLayer(layerName = '', serverKey = currentWmsServer) {
        const server = wmsServers[serverKey];
        const params = {
            'LAYERS': layerName || '0',
            'VERSION': '1.1.1',
            'FORMAT': 'image/png',
            'TRANSPARENT': true,
            'TILED': true  // Penting untuk TileWMS
        };
        
        // UPDATE BOUNDING BOX sesuai server yang dipilih
        return new ol.layer.Tile({
            source: new ol.source.TileWMS({
                url: server.url,
                params: params,
                serverType: 'mapserver',
                crossOrigin: 'anonymous',
                tileGrid: new ol.tilegrid.TileGrid({
                    extent: ol.proj.transformExtent(
                        server.bbox, // Bounding box dari server yang dipilih
                        'EPSG:4326',
                        'EPSG:3857'
                    ),
                    resolutions: [
                        156543.03392804097,
                        78271.51696402048,
                        39135.75848201024,
                        19567.87924100512,
                        9783.93962050256,
                        4891.96981025128,
                        2445.98490512564,
                        1222.99245256282,
                        611.49622628141,
                        305.748113140705,
                        152.8740565703525,
                        76.43702828517625,
                        38.21851414258813,
                        19.109257071294063,
                        9.554628535647032,
                        4.777314267823516,
                        2.388657133911758,
                        1.194328566955879,
                        0.5971642834779395
                    ],
                    tileSize: [256, 256]
                })
            }),
            zIndex: 1,  // Z-index rendah agar di bawah CCTV layer
            opacity: 0.85  // Sedikit transparan agar CCTV icon lebih terlihat
        });
    }

    // Create Google Satellite tile source
    const googleSatelliteSource = new ol.source.XYZ({
        url: 'http://mt0.google.com/vt/lyrs=s&hl=en&x={x}&y={y}&z={z}',
        attributions: '© Google',
        maxZoom: 20
    });

    // Data CCTV - diambil dari database cctv_data_bmo2
    const cctvLocations = @json($cctvLocations ?? []);

    // Create CCTV icon style
    function createCCTVStyle() {
        return new ol.style.Style({
            image: new ol.style.Circle({
                radius: 8,
                fill: new ol.style.Fill({
                    color: '#FF4444'
                }),
                stroke: new ol.style.Stroke({
                    color: '#FFFFFF',
                    width: 2
                })
            })
        });
    }

    // Create CCTV features
    function createCCTVFeatures() {
        const features = [];
        cctvLocations.forEach(function(cctv) {
            const feature = new ol.Feature({
                geometry: new ol.geom.Point(ol.proj.fromLonLat(cctv.location)),
                name: cctv.name,
                id: cctv.id,
                status: cctv.status,
                description: cctv.description,
                type: cctv.type,
                brand: cctv.brand || '',
                model: cctv.model || '',
                viewType: cctv.viewType || '',
                area: cctv.area || '',
                areaType: cctv.areaType || '',
                activity: cctv.activity || '',
                ip: cctv.ip || '',
                username: cctv.user_name || cctv.username || '',
                password: cctv.password || '',
                port: cctv.port || 80,
                channel: cctv.channel || 101,
                controlRoom: cctv.controlRoom || '',
                liveView: cctv.liveView || '',
                streamingMode: cctv.streamingMode || '',
                externalUrl: cctv.link_akses || cctv.externalUrl || '',
                portalUsername: cctv.user_name || cctv.portalUsername || '',
                portalPassword: cctv.password || cctv.portalPassword || '',
                portalVerification: cctv.portalVerification || '',
                rtspHost: cctv.rtspHost || '',
                rtspPort: cctv.rtspPort || '',
                rtspChannel: cctv.rtspChannel || '',
                rtspUsername: cctv.user_name || cctv.rtspUsername || '',
                rtspPassword: cctv.password || cctv.rtspPassword || '',
                rtspUrl: cctv.rtspUrl || '',
                rtspTransport: cctv.rtspTransport || 'tcp',
                site: cctv.site || '',
                perusahaan: cctv.perusahaan || '',
                kategori: cctv.kategori || '',
                radius_pengawasan: cctv.radius_pengawasan || '',
                keterangan: cctv.keterangan || '',
                connected: cctv.connected || '',
                mirrored: cctv.mirrored || ''
            });
            feature.setStyle(createCCTVStyle());
            features.push(feature);
        });
        return features;
    }

    // Function to get HikVision stream URL
    function getHikVisionStreamUrl(cctv) {
        if (!cctv.ip) return null;
        
        // HikVision web interface untuk live view
        // Format: http://ip:port/Streaming/channels/channel/picture
        // Atau menggunakan RTSP melalui proxy/transcoder
        const port = cctv.port || 80;
        const channel = cctv.channel || 101;
        
        // Option 1: Menggunakan HikVision web interface (jika tersedia)
        // return `http://${cctv.ip}:${port}/ISAPI/Streaming/channels/${channel}01/picture`;
        
        // Option 2: Menggunakan snapshot URL (refresh setiap detik)
        // return `http://${cctv.username}:${cctv.password}@${cctv.ip}:${port}/Streaming/channels/${channel}01/picture`;
        
        // Option 3: Menggunakan RTSP stream (perlu proxy server atau transcoder)
        // RTSP URL: rtsp://username:password@ip:port/Streaming/Channels/channel
        // Untuk browser, RTSP tidak langsung didukung, perlu menggunakan:
        // - WebRTC gateway
        // - HLS/MPEG-DASH transcoder
        // - atau menggunakan HikVision web plugin
        
        // Untuk sementara, gunakan web interface dengan authentication
        // Note: Browser security mungkin memblokir basic auth di URL
        // Solusi: gunakan proxy server atau iframe dengan authentication header
        return `http://${cctv.ip}:${port}/ISAPI/Streaming/channels/${channel}01/picture`;
    }

    // Function to open CCTV stream modal
    function openCCTVStream(cctv) {
        const modal = document.getElementById('cctvModal');
        const modalTitle = document.getElementById('cctvModalTitle');
        const streamContainer = document.getElementById('cctvStreamContainer');
        
        modalTitle.textContent = `${cctv.name} - Live View`;
        
        // Reset container & interval
        streamContainer.innerHTML = '';
        if (typeof cctvRefreshInterval !== 'undefined' && cctvRefreshInterval) {
            clearInterval(cctvRefreshInterval);
            cctvRefreshInterval = null;
        }
        
        const preferredMode = (cctv.streamingMode || '').toLowerCase();
        const rtspUrl = buildRtspUrl(cctv);
        const hasPortal = !!(cctv.externalUrl || cctv.link_akses);
        const hasInternalIp = cctv.ip && (cctv.brand === 'HIKVision' || cctv.brand === 'Ezviz');

        // External portal (Hik-Connect atau link akses lainnya)
        const linkAkses = cctv.externalUrl || cctv.link_akses;
        if ((preferredMode === 'externalportal' || (hasPortal && !hasInternalIp && !rtspUrl)) && hasPortal) {
            streamContainer.innerHTML = `
                <div class="cctv-portal-info">
                    <p>Live view kamera ini tersedia melalui link akses eksternal.</p>
                    <p><strong>Link Akses:</strong> <a href="${linkAkses}" target="_blank" rel="noopener">Buka Link</a></p>
                    ${cctv.portalUsername || cctv.user_name ? `<p><strong>Username:</strong> ${cctv.portalUsername || cctv.user_name}</p>` : ''}
                    ${cctv.portalPassword || cctv.password ? `<p><strong>Password:</strong> ${cctv.portalPassword || cctv.password}</p>` : ''}
                    ${cctv.portalVerification ? `<p><strong>Verification Code:</strong> ${cctv.portalVerification}</p>` : ''}
                    <p style="font-size: 12px; color: #666; margin-top: 12px;">Klik tautan di atas untuk membuka link pada tab baru, lalu masuk menggunakan kredensial yang disediakan.</p>
                </div>
            `;
        }
        // Internal network HikVision/Ezviz camera via proxy
        else if (preferredMode === 'proxy' || hasInternalIp) {
            const port = cctv.port || 80;
            const channel = cctv.channel || 101;
            const proxyUrl = '{{ route("cctv-proxy-snapshot") }}';
            const username = cctv.username || cctv.user_name || '';
            const password = cctv.password || '';
            const params = new URLSearchParams({
                ip: cctv.ip,
                port: port,
                channel: channel,
                username: username,
                password: password
            });
            
            streamContainer.innerHTML = `
                <img id="cctvSnapshot" 
                     src="${proxyUrl}?${params.toString()}" 
                     style="width: 100%; height: 100%; object-fit: contain; background-color: #000;"
                     onerror="handleCCTVError(this);">
                <div id="cctvError" style="display: none; color: white; text-align: center; padding-top: 50%;">
                    <p>Gagal memuat stream CCTV</p>
                    <p style="font-size: 12px; color: #999;">Periksa koneksi network atau konfigurasi CCTV</p>
                </div>
            `;
            
            const snapshotImg = document.getElementById('cctvSnapshot');
            if (snapshotImg) {
                cctvRefreshInterval = setInterval(function() {
                    const timestamp = new Date().getTime();
                    const refreshParams = new URLSearchParams({
                        ip: cctv.ip,
                        port: port,
                        channel: channel,
                        username: username,
                        password: password,
                        t: timestamp
                    });
                    snapshotImg.src = `${proxyUrl}?${refreshParams.toString()}`;
                }, 1000);
            }
        } else {
            if (rtspUrl) {
                const proxyRtspUrl = '{{ route("cctv-proxy-rtsp") }}';
                const transport = cctv.rtspTransport || 'tcp';
                const params = new URLSearchParams({
                    rtsp: rtspUrl,
                    transport: transport
                });
                streamContainer.innerHTML = `
                    <img id="cctvRtspStream"
                         src="${proxyRtspUrl}?${params.toString()}"
                         style="width: 100%; height: 100%; object-fit: contain; background-color: #000;"
                         onerror="handleCCTVError(this);">
                    <div id="cctvError" style="display: none; color: white; text-align: center; padding-top: 50%;">
                        <p>Gagal memuat stream CCTV</p>
                        <p style="font-size: 12px; color: #999;">Pastikan server memiliki akses ke stream RTSP dan ffmpeg terinstal.</p>
                        ${hasPortal ? `<p style="font-size: 12px;"><a href="${linkAkses}" target="_blank" rel="noopener">Buka Link Akses sebagai alternatif</a></p>` : ''}
                    </div>
                `;
            } else if (hasPortal) {
                streamContainer.innerHTML = `
                    <div class="cctv-portal-info">
                        <p>Stream RTSP tidak tersedia. Gunakan link akses berikut:</p>
                        <p><strong>Link Akses:</strong> <a href="${linkAkses}" target="_blank" rel="noopener">Buka Link</a></p>
                        ${cctv.portalUsername || cctv.user_name ? `<p><strong>Username:</strong> ${cctv.portalUsername || cctv.user_name}</p>` : ''}
                        ${cctv.portalPassword || cctv.password ? `<p><strong>Password:</strong> ${cctv.portalPassword || cctv.password}</p>` : ''}
                        ${cctv.portalVerification ? `<p><strong>Verification Code:</strong> ${cctv.portalVerification}</p>` : ''}
                    </div>
                `;
            } else {
                streamContainer.innerHTML = '<p style="color: white; text-align: center; padding-top: 50%;">Stream tidak tersedia untuk CCTV ini</p>';
            }
        }
        
        modal.style.display = 'block';
    }

    // Function to handle CCTV error (global untuk bisa dipanggil dari inline onerror)
    window.handleCCTVError = function(img) {
        if (img) {
            img.style.display = 'none';
        }
        const errorDiv = document.getElementById('cctvError');
        if (errorDiv) {
            errorDiv.style.display = 'block';
        }
    };

    // Variable untuk menyimpan interval ID
    let cctvRefreshInterval = null;

    // Close modal
    document.getElementById('cctvModalClose').onclick = function() {
        closeCCTVModal();
    };

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('cctvModal');
        if (event.target === modal) {
            closeCCTVModal();
        }
    };

    // Function to close CCTV modal and cleanup
    function closeCCTVModal() {
        document.getElementById('cctvModal').style.display = 'none';
        const streamFrame = document.getElementById('cctvStreamFrame');
        if (streamFrame) {
            streamFrame.src = '';
        }
        const streamContainer = document.getElementById('cctvStreamContainer');
        if (streamContainer) {
            streamContainer.innerHTML = '';
        }
        const errorDiv = document.getElementById('cctvError');
        if (errorDiv) {
            errorDiv.style.display = 'none';
        }
        
        // Clear interval jika ada
        if (cctvRefreshInterval) {
            clearInterval(cctvRefreshInterval);
            cctvRefreshInterval = null;
        }
    }

    // Global function to open CCTV stream modal (called from button onclick)
    window.openCCTVStreamModal = function(cctvId) {
        // Find CCTV by ID dari data yang sudah di-load
        const cctv = cctvLocations.find(c => c.id === cctvId);
        if (cctv) {
            // Tambahkan field yang mungkin diperlukan untuk streaming
            if (!cctv.externalUrl && cctv.link_akses) {
                cctv.externalUrl = cctv.link_akses;
            }
            if (!cctv.username && cctv.user_name) {
                cctv.username = cctv.user_name;
            }
            if (!cctv.portalUsername && cctv.user_name) {
                cctv.portalUsername = cctv.user_name;
            }
            if (!cctv.portalPassword && cctv.password) {
                cctv.portalPassword = cctv.password;
            }
            openCCTVStream(cctv);
        } else {
            alert('CCTV tidak ditemukan');
        }
    };

    // Create CCTV layer dengan zIndex tinggi agar selalu di atas
    cctvLayer = new ol.layer.Vector({
        source: new ol.source.Vector({
            features: createCCTVFeatures()
        }),
        name: 'CCTV',
        zIndex: 1000  // Z-index tinggi agar selalu di atas layer lain
    });

    // GeoJSON Data - Area CCTV BMO1 FAD
    const areaCctvGeoJson = {
        "type": "FeatureCollection",
        "name": "area_cctv_bmo1_fad",
        "crs": { "type": "name", "properties": { "name": "urn:ogc:def:crs:EPSG::32650" } },
        "features": [
            { "type": "Feature", "properties": { "fid": 1, "nomor_cctv": null, "nama_cctv": "", "site": "BMO 1", "perusahaan_cctv": "", "luasan": 17443.77436 }, "geometry": { "type": "MultiPolygon", "coordinates": [ [ [ [ 547541.999576447531581, 224118.253150632604957 ], [ 547542.556905532255769, 224119.006576988846064 ], [ 547584.190882381983101, 224111.202332006767392 ], [ 547581.911137556657195, 224101.228448387235403 ], [ 547583.45099375769496, 224075.820821069180965 ], [ 547598.849555770866573, 224057.342546651139855 ], [ 547619.637614487670362, 224046.563553243875504 ], [ 547601.338750809431076, 224013.998232029378414 ], [ 547584.788522525690496, 223978.614985363557935 ], [ 547567.667596720159054, 223935.812670839950442 ], [ 547555.112251126207411, 223917.550349975004792 ], [ 547459.235066596418619, 223978.614985363557935 ], [ 547483.204362728632987, 224019.705207303166389 ], [ 547492.906220688484609, 224047.098688593134284 ], [ 547511.739239076152444, 224077.345657521858811 ], [ 547541.999576447531581, 224118.253150632604957 ] ] ] ] } },
            { "type": "Feature", "properties": { "fid": 2, "nomor_cctv": "BMO-FAD-0013", "nama_cctv": "WF - Waterfill_FIXED", "site": "BMO 1", "perusahaan_cctv": "PT Fajar Anugerah Dinamika", "luasan": 1294.73319 }, "geometry": { "type": "MultiPolygon", "coordinates": [ [ [ [ 548719.192801279015839, 224781.326092138886452 ], [ 548754.708456729538739, 224762.523686315864325 ], [ 548738.706056691706181, 224730.963397352024913 ], [ 548729.037064061500132, 224714.244097599759698 ], [ 548722.188693737611175, 224728.51153577119112 ], [ 548733.031946749426425, 224744.49106652662158 ], [ 548709.6333481464535, 224763.324084918946028 ], [ 548713.057533306069672, 224775.308732982724905 ], [ 548719.192801279015839, 224781.326092138886452 ] ] ] ] } },
            { "type": "Feature", "properties": { "fid": 3, "nomor_cctv": "BMO-FAD-0010", "nama_cctv": "TH - Park & Refuling QSV1N_FIXED", "site": "BMO 1", "perusahaan_cctv": "PT Fajar Anugerah Dinamika", "luasan": 11897.13491 }, "geometry": { "type": "MultiPolygon", "coordinates": [ [ [ [ 548813.205936715006828, 225528.533819653093815 ], [ 548800.559248474426568, 225688.633713340386748 ], [ 548813.770399169065058, 225683.725333452224731 ], [ 548869.980739209800959, 225646.251773428171873 ], [ 548895.587671896442771, 225605.030857395380735 ], [ 548915.476710196584463, 225530.949664697051048 ], [ 548813.205936715006828, 225528.533819653093815 ] ] ] ] } },
            { "type": "Feature", "properties": { "fid": 4, "nomor_cctv": null, "nama_cctv": "", "site": "BMO 1", "perusahaan_cctv": "", "luasan": 93515.76791 }, "geometry": { "type": "MultiPolygon", "coordinates": [ [ [ [ 548530.771612764336169, 225404.227156180888414 ], [ 548935.709593309089541, 225388.604978576302528 ], [ 548941.586735266260803, 225365.586172575131059 ], [ 548933.850431541912258, 225337.542071575298905 ], [ 548932.883393575437367, 225294.992401087656617 ], [ 548909.540111012756824, 225237.778473233804107 ], [ 548931.561848987825215, 225224.73113040626049 ], [ 548930.74674591049552, 225188.866595042869449 ], [ 548930.74674591049552, 225175.009842744097114 ], [ 548957.645147433504462, 225138.330204302445054 ], [ 548993.509682795964181, 225104.910978171974421 ], [ 549010.626847402192652, 225085.348504334688187 ], [ 548999.215404332615435, 225071.491752035915852 ], [ 548982.643234949558973, 225069.330164728686213 ], [ 548964.691265925765038, 225072.007595617324114 ], [ 548964.981075122021139, 225072.306855114176869 ], [ 548967.426384350284934, 225083.718298183754086 ], [ 548954.384735126979649, 225094.314638175070286 ], [ 548889.398134211078286, 225115.097643744200468 ], [ 548843.179887775331736, 225092.102515356615186 ], [ 548843.089004157111049, 225091.648097269237041 ], [ 548821.585045392625034, 225089.670755255967379 ], [ 548793.91331772133708, 225091.251996837556362 ], [ 548761.497865304350853, 225100.739446325227618 ], [ 548746.4760702829808, 225113.389378972351551 ], [ 548698.860205897130072, 225125.760757103562355 ], [ 548691.123902169987559, 225165.409313697367907 ], [ 548670.816104894503951, 225191.519338764250278 ], [ 548641.804965926334262, 225249.541616706177592 ], [ 548611.826788992621005, 225266.948300087824464 ], [ 548571.21119443513453, 225300.794628884643316 ], [ 548554.771549019962549, 225330.772805821150541 ], [ 548530.771612764336169, 225404.227156180888414 ] ] ] ] } },
            { "type": "Feature", "properties": { "fid": 5, "nomor_cctv": "BMO-FAD-0004", "nama_cctv": "CS - Changeshift_FIX", "site": "BMO 1", "perusahaan_cctv": "PT Fajar Anugerah Dinamika", "luasan": 6024.64514 }, "geometry": { "type": "MultiPolygon", "coordinates": [ [ [ [ 548456.60759520996362, 224625.544543173164129 ], [ 548452.984857780858874, 224617.436511786654592 ], [ 548437.601775553077459, 224623.245608666911721 ], [ 548438.69033688493073, 224627.302973635494709 ], [ 548456.600585641339421, 224650.970088062807918 ], [ 548463.636754794977605, 224663.763122888281941 ], [ 548473.572137106209993, 224696.575489696115255 ], [ 548478.949224486015737, 224720.100246978923678 ], [ 548458.785146815702319, 224729.510149890556931 ], [ 548423.834078853018582, 224739.592188727110624 ], [ 548405.686408950947225, 224755.723450860008597 ], [ 548377.456700213253498, 224768.494033385068178 ], [ 548372.079612834379077, 224779.248208144679666 ], [ 548394.260098271071911, 224787.31383921392262 ], [ 548432.571845844388008, 224787.31383921392262 ], [ 548466.594927465543151, 224777.90319961681962 ], [ 548464.854426493868232, 224770.357125589624047 ], [ 548462.526509827934206, 224762.209417257457972 ], [ 548501.501003347337246, 224750.247564261779189 ], [ 548490.375535165891051, 224710.690344063565135 ], [ 548476.932816717773676, 224662.96869358047843 ], [ 548462.817962350323796, 224639.443936295807362 ], [ 548456.60759520996362, 224625.544543173164129 ] ] ] ] } },
            { "type": "Feature", "properties": { "fid": 6, "nomor_cctv": "BMO-FAD-0005", "nama_cctv": "MC - Workshop Track_FIX", "site": "BMO 1", "perusahaan_cctv": "PT Fajar Anugerah Dinamika", "luasan": 6024.64514 }, "geometry": { "type": "MultiPolygon", "coordinates": [ [ [ [ 548456.60759520996362, 224625.544543173164129 ], [ 548452.984857780858874, 224617.436511786654592 ], [ 548437.601775553077459, 224623.245608666911721 ], [ 548438.69033688493073, 224627.302973635494709 ], [ 548456.600585641339421, 224650.970088062807918 ], [ 548463.636754794977605, 224663.763122888281941 ], [ 548473.572137106209993, 224696.575489696115255 ], [ 548478.949224486015737, 224720.100246978923678 ], [ 548458.785146815702319, 224729.510149890556931 ], [ 548423.834078853018582, 224739.592188727110624 ], [ 548405.686408950947225, 224755.723450860008597 ], [ 548377.456700213253498, 224768.494033385068178 ], [ 548372.079612834379077, 224779.248208144679666 ], [ 548394.260098271071911, 224787.31383921392262 ], [ 548432.571845844388008, 224787.31383921392262 ], [ 548466.594927465543151, 224777.90319961681962 ], [ 548464.854426493868232, 224770.357125589624047 ], [ 548462.526509827934206, 224762.209417257457972 ], [ 548501.501003347337246, 224750.247564261779189 ], [ 548490.375535165891051, 224710.690344063565135 ], [ 548476.932816717773676, 224662.96869358047843 ], [ 548462.817962350323796, 224639.443936295807362 ], [ 548456.60759520996362, 224625.544543173164129 ] ] ] ] } },
            { "type": "Feature", "properties": { "fid": 7, "nomor_cctv": "BMO-FAD-0018", "nama_cctv": "RM - ROM_FIXED", "site": "BMO 1", "perusahaan_cctv": "PT Fajar Anugerah Dinamika", "luasan": 10550.61771 }, "geometry": { "type": "MultiPolygon", "coordinates": [ [ [ [ 548244.368874384090304, 224451.402935268357396 ], [ 548257.429136047139764, 224436.561728835105896 ], [ 548264.242407965473831, 224428.819374380633235 ], [ 548272.648619291372597, 224420.938551265746355 ], [ 548280.004054200835526, 224405.17690503038466 ], [ 548281.580218825489283, 224394.669140877202153 ], [ 548278.59992943610996, 224390.364278424531221 ], [ 548276.85172495432198, 224387.839094169437885 ], [ 548259.513914095237851, 224376.280553599819541 ], [ 548238.321505830623209, 224366.058333151042461 ], [ 548214.855916429311037, 224354.739637082442641 ], [ 548173.90186948236078, 224339.260942172259092 ], [ 548139.648284747265279, 224412.20005402341485 ], [ 548141.10100014321506, 224413.512184057384729 ], [ 548166.240385618060827, 224426.361203299835324 ], [ 548185.793240987695754, 224439.768875550478697 ], [ 548197.524954207241535, 224454.852506838738918 ], [ 548205.924316897988319, 224462.444219682365656 ], [ 548217.482857469469309, 224464.545772511512041 ], [ 548229.041398041881621, 224460.868055060505867 ], [ 548241.125326821580529, 224455.088784769177437 ], [ 548244.368874384090304, 224451.402935268357396 ] ] ] ] } },
            { "type": "Feature", "properties": { "fid": 8, "nomor_cctv": "BMO-FAD-0006", "nama_cctv": "MC - PitStop_FIXED", "site": "BMO 1", "perusahaan_cctv": "PT Fajar Anugerah Dinamika", "luasan": 11398.11966 }, "geometry": { "type": "MultiPolygon", "coordinates": [ [ [ [ 547659.544980997219682, 224156.416223544627428 ], [ 547663.593101009726524, 224166.062807409092784 ], [ 547678.806039416231215, 224202.3149159476161 ], [ 547691.828147984109819, 224207.523759376257658 ], [ 547780.362285177223384, 224074.159293439239264 ], [ 547774.462786012329161, 224066.995615882799029 ], [ 547763.422987821511924, 224061.475716790184379 ], [ 547760.464237730950117, 224059.996341746300459 ], [ 547755.332655547186732, 224059.857650335878134 ], [ 547738.883142463862896, 224059.41306889988482 ], [ 547718.46859288495034, 224065.245797351002693 ], [ 547689.304950628429651, 224068.162161571905017 ], [ 547671.219694809988141, 224065.306594867259264 ], [ 547656.058398456312716, 224062.912705967202783 ], [ 547656.113918285816908, 224063.145889250561595 ], [ 547658.974762681871653, 224075.161435713991523 ], [ 547653.142034230753779, 224091.493075378239155 ], [ 547649.059124315157533, 224110.741079267114401 ], [ 547651.392215695232153, 224136.988357298076153 ], [ 547659.544980997219682, 224156.416223544627428 ] ] ] ] } },
            { "type": "Feature", "properties": { "fid": 9, "nomor_cctv": "BMO-FAD-0007", "nama_cctv": "FA - Park DT WS_FIXED", "site": "BMO 1", "perusahaan_cctv": "PT Fajar Anugerah Dinamika", "luasan": 2990.76596 }, "geometry": { "type": "MultiPolygon", "coordinates": [ [ [ [ 547622.521731841377914, 223903.36397216655314 ], [ 547653.567147768102586, 223943.775044301524758 ], [ 547688.698218177072704, 223989.504314720630646 ], [ 547705.498407969251275, 223974.624514361843467 ], [ 547721.31988822389394, 223960.866705445572734 ], [ 547699.307393955998123, 223935.414758948609233 ], [ 547686.237475485540926, 223949.860458312556148 ], [ 547676.607009243220091, 223936.790539842098951 ], [ 547634.64569204673171, 223890.013989523053169 ], [ 547621.038358439691365, 223901.433100644499063 ], [ 547622.521731841377914, 223903.36397216655314 ] ] ] ] } },
            { "type": "Feature", "properties": { "fid": 10, "nomor_cctv": "BMO-FAD-0008", "nama_cctv": "MC - WS tyre_FIXED", "site": "BMO 1", "perusahaan_cctv": "PT Fajar Anugerah Dinamika", "luasan": 2990.76596 }, "geometry": { "type": "MultiPolygon", "coordinates": [ [ [ [ 547622.521731841377914, 223903.36397216655314 ], [ 547653.567147768102586, 223943.775044301524758 ], [ 547688.698218177072704, 223989.504314720630646 ], [ 547705.498407969251275, 223974.624514361843467 ], [ 547721.31988822389394, 223960.866705445572734 ], [ 547699.307393955998123, 223935.414758948609233 ], [ 547686.237475485540926, 223949.860458312556148 ], [ 547676.607009243220091, 223936.790539842098951 ], [ 547634.64569204673171, 223890.013989523053169 ], [ 547621.038358439691365, 223901.433100644499063 ], [ 547622.521731841377914, 223903.36397216655314 ] ] ] ] } },
            { "type": "Feature", "properties": { "fid": 11, "nomor_cctv": "BMO-FAD-0019", "nama_cctv": "WH - Warehouse_FIXED", "site": "BMO 1", "perusahaan_cctv": "PT Fajar Anugerah Dinamika", "luasan": 2990.76596 }, "geometry": { "type": "MultiPolygon", "coordinates": [ [ [ [ 547622.521731841377914, 223903.36397216655314 ], [ 547653.567147768102586, 223943.775044301524758 ], [ 547688.698218177072704, 223989.504314720630646 ], [ 547705.498407969251275, 223974.624514361843467 ], [ 547721.31988822389394, 223960.866705445572734 ], [ 547699.307393955998123, 223935.414758948609233 ], [ 547686.237475485540926, 223949.860458312556148 ], [ 547676.607009243220091, 223936.790539842098951 ], [ 547634.64569204673171, 223890.013989523053169 ], [ 547621.038358439691365, 223901.433100644499063 ], [ 547622.521731841377914, 223903.36397216655314 ] ] ] ] } }
        ]
    };

    // Function to transform coordinates from EPSG:32650 (UTM Zone 50N) to EPSG:3857
    // EPSG:32650 perlu di-transform ke WGS84 (EPSG:4326) dulu, baru ke EPSG:3857
    function transformCoordinates(coords, fromProj, toProj) {
        if (Array.isArray(coords[0])) {
            return coords.map(coord => transformCoordinates(coord, fromProj, toProj));
        } else {
            try {
                // Try direct transformation first
                return ol.proj.transform(coords, fromProj, toProj);
            } catch (e) {
                // If direct transformation fails, try via WGS84
                if (fromProj === 'EPSG:32650') {
                    // UTM Zone 50N to WGS84
                    const lon = coords[0] / 111320.0; // Approximate conversion
                    const lat = coords[1] / 110540.0; // Approximate conversion
                    const wgs84 = [lon, lat];
                    // WGS84 to EPSG:3857
                    return ol.proj.transform(wgs84, 'EPSG:4326', 'EPSG:3857');
                }
                return coords;
            }
        }
    }

    // Function to transform GeoJSON geometry from EPSG:32650 to EPSG:3857
    // UTM Zone 50N (EPSG:32650) to WGS84 (EPSG:4326) to EPSG:3857
    function transformGeoJsonGeometry(geometry) {
        if (geometry.type === 'MultiPolygon') {
            return {
                type: geometry.type,
                coordinates: geometry.coordinates.map(polygon =>
                    polygon.map(ring =>
                        ring.map(coord => {
                            // Use proj4 if available, otherwise use manual calculation
                            if (typeof proj4 !== 'undefined') {
                                try {
                                    // Define EPSG:32650 projection
                                    proj4.defs('EPSG:32650', '+proj=utm +zone=50 +datum=WGS84 +units=m +no_defs');
                                    // Transform from EPSG:32650 to EPSG:4326
                                    const wgs84 = proj4('EPSG:32650', 'EPSG:4326', coord);
                                    // Transform from EPSG:4326 to EPSG:3857
                                    return ol.proj.transform([wgs84[0], wgs84[1]], 'EPSG:4326', 'EPSG:3857');
                                } catch (e) {
                                    console.warn('proj4 transformation failed, using manual calculation:', e);
                                }
                            }
                            
                            // Manual UTM to WGS84 conversion (simplified but more accurate)
                            const x = coord[0]; // Easting
                            const y = coord[1]; // Northing
                            
                            // UTM Zone 50N parameters
                            const k0 = 0.9996;
                            const a = 6378137.0; // WGS84 semi-major axis
                            const e2 = 0.00669438; // WGS84 first eccentricity squared
                            const e1 = (1 - Math.sqrt(1 - e2)) / (1 + Math.sqrt(1 - e2));
                            const n = (a - 6356752.314245) / (a + 6356752.314245);
                            const A = (a / (1 + n)) * (1 + (n * n) / 4 + (n * n * n * n) / 64);
                            
                            const x0 = 500000;
                            const y0 = 0;
                            const lon0 = 117 * Math.PI / 180; // central meridian in radians
                            
                            const x1 = x - x0;
                            const y1 = y - y0;
                            const M = y1 / k0;
                            
                            const mu = M / (A * (1 - e2 / 4 - 3 * e2 * e2 / 64 - 5 * e2 * e2 * e2 / 256));
                            
                            const J1 = (3 * e1 / 2 - 27 * e1 * e1 * e1 / 32);
                            const J2 = (21 * e1 * e1 / 16 - 55 * e1 * e1 * e1 * e1 / 32);
                            const J3 = (151 * e1 * e1 * e1 / 96);
                            const J4 = (1097 * e1 * e1 * e1 * e1 / 512);
                            
                            const fp = mu + J1 * Math.sin(2 * mu) + J2 * Math.sin(4 * mu) + J3 * Math.sin(6 * mu) + J4 * Math.sin(8 * mu);
                            
                            const e_2 = e2 / (1 - e2);
                            const C1 = e_2 * Math.cos(fp) * Math.cos(fp);
                            const T1 = Math.tan(fp) * Math.tan(fp);
                            const N1 = a / Math.sqrt(1 - e2 * Math.sin(fp) * Math.sin(fp));
                            const R1 = a * (1 - e2) / Math.pow(1 - e2 * Math.sin(fp) * Math.sin(fp), 1.5);
                            const D = x1 / (N1 * k0);
                            
                            const Q1 = N1 * Math.tan(fp) / R1;
                            const Q2 = D * D / 2;
                            const Q3 = (5 + 3 * T1 + 10 * C1 - 4 * C1 * C1 - 9 * e_2) * D * D * D * D / 24;
                            const Q4 = (61 + 90 * T1 + 298 * C1 + 45 * T1 * T1 - 252 * e_2 - 3 * C1 * C1) * D * D * D * D * D * D / 720;
                            const lat = fp - Q1 * (Q2 - Q3 + Q4);
                            
                            const Q5 = D;
                            const Q6 = (1 + 2 * T1 + C1) * D * D * D / 6;
                            const Q7 = (5 - 2 * C1 + 28 * T1 - 3 * C1 * C1 + 8 * e_2 + 24 * T1 * T1) * D * D * D * D * D / 120;
                            const lon = lon0 + (Q5 - Q6 + Q7) / Math.cos(fp);
                            
                            const latDeg = lat * 180 / Math.PI;
                            const lonDeg = lon * 180 / Math.PI;
                            
                            return ol.proj.transform([lonDeg, latDeg], 'EPSG:4326', 'EPSG:3857');
                        })
                    )
                )
            };
        }
        return geometry;
    }

    // Create Area CCTV layer
    let areaCctvLayer = new ol.layer.Vector({
        source: new ol.source.Vector({
            features: new ol.format.GeoJSON().readFeatures(
                {
                    type: 'FeatureCollection',
                    features: areaCctvGeoJson.features.map(feature => ({
                        ...feature,
                        geometry: transformGeoJsonGeometry(feature.geometry)
                    }))
                },
                {
                    dataProjection: 'EPSG:3857',
                    featureProjection: 'EPSG:3857'
                }
            )
        }),
        style: function(feature) {
            const props = feature.getProperties();
            return new ol.style.Style({
                fill: new ol.style.Fill({
                    color: 'rgba(59, 130, 246, 0.3)' // Blue with transparency
                }),
                stroke: new ol.style.Stroke({
                    color: '#3b82f6', // Blue
                    width: 2
                })
            });
        },
        name: 'Area CCTV',
        zIndex: 500,
        visible: true
    });

    // GeoJSON Data - Area Kerja BMO1 FAD (simplified - full data akan ditambahkan)
    // Note: Karena data sangat besar, kita akan load secara dinamis
    const areaKerjaGeoJson = {
        "type": "FeatureCollection",
        "name": "area_kerja_bmo1_fad",
        "crs": { "type": "name", "properties": { "name": "urn:ogc:def:crs:EPSG::32650" } },
        "features": [] // Will be populated with actual data
    };

    // Create Area Kerja layer
    areaKerjaLayer = new ol.layer.Vector({
        source: new ol.source.Vector(),
        style: function(feature) {
            const props = feature.getProperties();
            const areaKerja = props.area_kerja || '';
            
            // Different colors based on area_kerja type
            let fillColor = 'rgba(16, 185, 129, 0.3)'; // Green default
            let strokeColor = '#10b981';
            
            if (areaKerja === 'Pit') {
                fillColor = 'rgba(239, 68, 68, 0.3)'; // Red
                strokeColor = '#ef4444';
            } else if (areaKerja === 'Hauling') {
                fillColor = 'rgba(245, 158, 11, 0.3)'; // Orange
                strokeColor = '#f59e0b';
            } else if (areaKerja === 'Infra Tambang') {
                fillColor = 'rgba(59, 130, 246, 0.3)'; // Blue
                strokeColor = '#3b82f6';
            }
            
            return new ol.style.Style({
                fill: new ol.style.Fill({
                    color: fillColor
                }),
                stroke: new ol.style.Stroke({
                    color: strokeColor,
                    width: 2
                })
            });
        },
        name: 'Area Kerja',
        zIndex: 400,
        visible: true
    });

    // Function to load Area Kerja GeoJSON data
    function loadAreaKerjaData(geoJsonData) {
        if (!geoJsonData || !geoJsonData.features || geoJsonData.features.length === 0) {
            console.warn('Area Kerja GeoJSON data is empty or not provided');
            return;
        }

        // Transform and add features to layer
        const transformedFeatures = geoJsonData.features.map(feature => {
            const transformedGeometry = transformGeoJsonGeometry(feature.geometry);
            const geoJsonFeature = {
                type: 'Feature',
                properties: feature.properties,
                geometry: transformedGeometry
            };
            return new ol.format.GeoJSON().readFeature(geoJsonFeature, {
                dataProjection: 'EPSG:3857',
                featureProjection: 'EPSG:3857'
            });
        });

        areaKerjaLayer.getSource().addFeatures(transformedFeatures);
        console.log(`Loaded ${transformedFeatures.length} Area Kerja features`);
        
        // Fit map to show all features
        if (transformedFeatures.length > 0) {
            const extent = areaKerjaLayer.getSource().getExtent();
            if (extent && extent[0] !== Infinity) {
                map.getView().fit(extent, {
                    padding: [50, 50, 50, 50],
                    maxZoom: 18
                });
                console.log('Map fitted to Area Kerja extent:', extent);
            }
        }
    }

    // Load Area Kerja data will be called after map is created

    // Function to create layer from GeoJSON data (for CRS84/EPSG:4326 data)
    function createLayerFromGeoJson(geoJsonData, layerName, styleFunction, zIndex = 300) {
        if (!geoJsonData) {
            console.warn(`${layerName}: GeoJSON data is null or undefined`);
            return new ol.layer.Vector({
                source: new ol.source.Vector(),
                name: layerName,
                zIndex: zIndex,
                visible: true
            });
        }

        if (!geoJsonData.features || geoJsonData.features.length === 0) {
            console.warn(`${layerName}: GeoJSON data has no features (features: ${geoJsonData.features ? geoJsonData.features.length : 'null'})`);
            return new ol.layer.Vector({
                source: new ol.source.Vector(),
                name: layerName,
                zIndex: zIndex,
                visible: true
            });
        }

        try {
            console.log(`${layerName}: Parsing ${geoJsonData.features.length} features...`);
            const features = new ol.format.GeoJSON().readFeatures(geoJsonData, {
                dataProjection: 'EPSG:4326',
                featureProjection: 'EPSG:3857'
            });

            console.log(`${layerName}: Successfully parsed ${features.length} features`);

            return new ol.layer.Vector({
                source: new ol.source.Vector({
                    features: features
                }),
                style: styleFunction,
                name: layerName,
                zIndex: zIndex,
                visible: true
            });
        } catch (error) {
            console.error(`${layerName}: Error parsing GeoJSON:`, error);
            console.error('GeoJSON data sample:', JSON.stringify(geoJsonData).substring(0, 200));
            return new ol.layer.Vector({
                source: new ol.source.Vector(),
                name: layerName,
                zIndex: zIndex,
                visible: true
            });
        }
    }

    // Create BMO2 PAMA layers
    let areaKerjaBmo2PamaLayer = null;
    let areaCctvBmo2PamaLayer = null;
    let differenceBmo2PamaLayer = null;
    let symmetricalDifferenceBmo2PamaLayer = null;
    let intersectionBmo2PamaLayer = null;

    // Style functions for different layers
    function getAreaKerjaStyle(feature) {
        const props = feature.getProperties();
        const areaKerja = props.area_kerja || '';
        
        let fillColor = 'rgba(16, 185, 129, 0.3)'; // Green default
        let strokeColor = '#10b981';
        
        if (areaKerja === 'Pit') {
            fillColor = 'rgba(239, 68, 68, 0.3)'; // Red
            strokeColor = '#ef4444';
        } else if (areaKerja === 'Hauling') {
            fillColor = 'rgba(245, 158, 11, 0.3)'; // Orange
            strokeColor = '#f59e0b';
        } else if (areaKerja === 'Infra Tambang') {
            fillColor = 'rgba(59, 130, 246, 0.3)'; // Blue
            strokeColor = '#3b82f6';
        }
        
        return new ol.style.Style({
            fill: new ol.style.Fill({
                color: fillColor
            }),
            stroke: new ol.style.Stroke({
                color: strokeColor,
                width: 2
            })
        });
    }

    function getAreaCctvStyle(feature) {
        return new ol.style.Style({
            fill: new ol.style.Fill({
                color: 'rgba(139, 92, 246, 0.3)' // Purple with transparency
            }),
            stroke: new ol.style.Stroke({
                color: '#8b5cf6', // Purple
                width: 2
            })
        });
    }

    function getDifferenceStyle(feature) {
        return new ol.style.Style({
            fill: new ol.style.Fill({
                color: 'rgba(239, 68, 68, 0.4)' // Red with transparency
            }),
            stroke: new ol.style.Stroke({
                color: '#ef4444', // Red
                width: 2
            })
        });
    }

    function getSymmetricalDifferenceStyle(feature) {
        return new ol.style.Style({
            fill: new ol.style.Fill({
                color: 'rgba(245, 158, 11, 0.4)' // Orange with transparency
            }),
            stroke: new ol.style.Stroke({
                color: '#f59e0b', // Orange
                width: 2
            })
        });
    }

    function getIntersectionStyle(feature) {
        return new ol.style.Style({
            fill: new ol.style.Fill({
                color: 'rgba(34, 197, 94, 0.4)' // Green with transparency
            }),
            stroke: new ol.style.Stroke({
                color: '#22c55e', // Green
                width: 2
            })
        });
    }

    // Create popup overlay
    const popupElement = document.getElementById('popup');
    popupOverlay = new ol.Overlay({
        element: popupElement,
        autoPan: {
            animation: {
                duration: 250
            }
        }
    });

    // Create map dengan Google Satellite sebagai base layer
    // UPDATE CENTER sesuai bounding box baru
    const map = new ol.Map({
        target: 'map',
        layers: [
            // Base layer - Google Satellite
            new ol.layer.Tile({
                source: googleSatelliteSource,
                opacity: 1.0
            })
            // CCTV Layer akan ditambahkan setelah WMS layer agar selalu di atas
        ],
        overlays: [popupOverlay],
        view: new ol.View({
            center: ol.proj.fromLonLat(wmsServers[currentWmsServer].center), // Center dari server yang dipilih
            zoom: 15  // Zoom lebih dekat karena area kecil
        }),
        controls: [
            new ol.control.Zoom(),
            new ol.control.ScaleLine(),
            new ol.control.MousePosition({
                coordinateFormat: function(coordinate) {
                    if (coordinate) {
                        return coordinate[0].toFixed(4) + ', ' + coordinate[1].toFixed(4);
                    }
                    return '';
                },
                projection: 'EPSG:4326'
            })
        ]
    });

    // Popup closer
    const popupCloser = document.getElementById('popup-closer');
    popupCloser.onclick = function() {
        popupOverlay.setPosition(undefined);
        popupCloser.blur();
        return false;
    };

    // Add GeoJSON layers to map
    map.addLayer(areaCctvLayer);
    map.addLayer(areaKerjaLayer);
    
    // Log layer info
    console.log('Area CCTV layer added, features:', areaCctvLayer.getSource().getFeatures().length);
    console.log('Area Kerja layer added, features:', areaKerjaLayer.getSource().getFeatures().length);

    // Load Area Kerja data after map is created
    // Priority: Use data from external file if available, otherwise use inline data
    setTimeout(function() {
        if (typeof window.areaKerjaGeoJsonData !== 'undefined' && window.areaKerjaGeoJsonData.features && window.areaKerjaGeoJsonData.features.length > 0) {
            console.log('Loading Area Kerja data from external file:', window.areaKerjaGeoJsonData.features.length, 'features');
            loadAreaKerjaData(window.areaKerjaGeoJsonData);
        } else {
            console.warn('Area Kerja data not found in external file, using empty data');
            console.warn('window.areaKerjaGeoJsonData:', typeof window.areaKerjaGeoJsonData);
            // You can add inline data here if needed
        }
    }, 100);

    // Load BMO2 PAMA layers after map is created
    setTimeout(function() {
        console.log('Loading BMO2 PAMA layers...');
        console.log('Checking available variables:');
        console.log('- areaKerjaGeoJsonDataPama:', typeof window.areaKerjaGeoJsonDataPama);
        console.log('- areaCctvGeoJsonDataBmo2Pama:', typeof window.areaCctvGeoJsonDataBmo2Pama);
        console.log('- difference_bmo2_pama:', typeof window.difference_bmo2_pama);
        console.log('- symmetrical_difference_bmo1_fad:', typeof window.symmetrical_difference_bmo1_fad);
        console.log('- intersection_bmo1_fad:', typeof window.intersection_bmo1_fad);

        // Area Kerja BMO2 PAMA
        if (typeof window.areaKerjaGeoJsonDataPama !== 'undefined' && window.areaKerjaGeoJsonDataPama) {
            try {
                areaKerjaBmo2PamaLayer = createLayerFromGeoJson(
                    window.areaKerjaGeoJsonDataPama,
                    'Area Kerja BMO2 PAMA',
                    getAreaKerjaStyle,
                    410
                );
                map.addLayer(areaKerjaBmo2PamaLayer);
                areaKerjaBmo2PamaLayer.setVisible(true); // Ensure visible
                console.log('✓ Area Kerja BMO2 PAMA layer added, features:', areaKerjaBmo2PamaLayer.getSource().getFeatures().length);
            } catch (error) {
                console.error('Error creating Area Kerja BMO2 PAMA layer:', error);
            }
        } else {
            console.warn('✗ areaKerjaGeoJsonDataPama not found or undefined');
        }

        // Area CCTV BMO2 PAMA
        if (typeof window.areaCctvGeoJsonDataBmo2Pama !== 'undefined' && window.areaCctvGeoJsonDataBmo2Pama) {
            try {
                areaCctvBmo2PamaLayer = createLayerFromGeoJson(
                    window.areaCctvGeoJsonDataBmo2Pama,
                    'Area CCTV BMO2 PAMA',
                    getAreaCctvStyle,
                    510
                );
                map.addLayer(areaCctvBmo2PamaLayer);
                areaCctvBmo2PamaLayer.setVisible(true); // Ensure visible
                console.log('✓ Area CCTV BMO2 PAMA layer added, features:', areaCctvBmo2PamaLayer.getSource().getFeatures().length);
            } catch (error) {
                console.error('Error creating Area CCTV BMO2 PAMA layer:', error);
            }
        } else {
            console.warn('✗ areaCctvGeoJsonDataBmo2Pama not found or undefined');
        }

        // Difference BMO2 PAMA
        if (typeof window.difference_bmo2_pama !== 'undefined' && window.difference_bmo2_pama) {
            try {
                differenceBmo2PamaLayer = createLayerFromGeoJson(
                    window.difference_bmo2_pama,
                    'Difference BMO2 PAMA',
                    getDifferenceStyle,
                    350
                );
                map.addLayer(differenceBmo2PamaLayer);
                differenceBmo2PamaLayer.setVisible(true); // Ensure visible
                console.log('✓ Difference BMO2 PAMA layer added, features:', differenceBmo2PamaLayer.getSource().getFeatures().length);
            } catch (error) {
                console.error('Error creating Difference BMO2 PAMA layer:', error);
            }
        } else {
            console.warn('✗ difference_bmo2_pama not found or undefined');
        }

        // Symmetrical Difference BMO2 PAMA
        if (typeof window.symmetrical_difference_bmo1_fad !== 'undefined' && window.symmetrical_difference_bmo1_fad) {
            try {
                // Note: Variable name is wrong in file, but data is for BMO2 PAMA
                symmetricalDifferenceBmo2PamaLayer = createLayerFromGeoJson(
                    window.symmetrical_difference_bmo1_fad,
                    'Symmetrical Difference BMO2 PAMA',
                    getSymmetricalDifferenceStyle,
                    360
                );
                map.addLayer(symmetricalDifferenceBmo2PamaLayer);
                symmetricalDifferenceBmo2PamaLayer.setVisible(true); // Ensure visible
                console.log('✓ Symmetrical Difference BMO2 PAMA layer added, features:', symmetricalDifferenceBmo2PamaLayer.getSource().getFeatures().length);
            } catch (error) {
                console.error('Error creating Symmetrical Difference BMO2 PAMA layer:', error);
            }
        } else {
            console.warn('✗ symmetrical_difference_bmo1_fad not found or undefined');
        }

        // Intersection BMO2 PAMA
        if (typeof window.intersection_bmo1_fad !== 'undefined' && window.intersection_bmo1_fad) {
            try {
                // Note: Variable name is wrong in file, but data is for BMO2 PAMA
                intersectionBmo2PamaLayer = createLayerFromGeoJson(
                    window.intersection_bmo1_fad,
                    'Intersection BMO2 PAMA',
                    getIntersectionStyle,
                    370
                );
                map.addLayer(intersectionBmo2PamaLayer);
                intersectionBmo2PamaLayer.setVisible(true); // Ensure visible
                console.log('✓ Intersection BMO2 PAMA layer added, features:', intersectionBmo2PamaLayer.getSource().getFeatures().length);
            } catch (error) {
                console.error('Error creating Intersection BMO2 PAMA layer:', error);
            }
        } else {
            console.warn('✗ intersection_bmo1_fad not found or undefined');
        }
        
        console.log('Finished loading BMO2 PAMA layers');
        
        // Fit map to show all BMO2 PAMA layers if any are loaded
        const allBmo2Layers = [
            areaKerjaBmo2PamaLayer,
            areaCctvBmo2PamaLayer,
            differenceBmo2PamaLayer,
            symmetricalDifferenceBmo2PamaLayer,
            intersectionBmo2PamaLayer
        ].filter(layer => layer !== null);
        
        if (allBmo2Layers.length > 0) {
            // Collect all extents from loaded layers
            const extents = allBmo2Layers
                .map(layer => {
                    const source = layer.getSource();
                    if (source && source.getFeatures().length > 0) {
                        return source.getExtent();
                    }
                    return null;
                })
                .filter(extent => extent && extent[0] !== Infinity);
            
            if (extents.length > 0) {
                // Calculate combined extent
                let combinedExtent = extents[0];
                for (let i = 1; i < extents.length; i++) {
                    combinedExtent = ol.extent.extend(combinedExtent, extents[i]);
                }
                
                // Fit map to combined extent
                map.getView().fit(combinedExtent, {
                    padding: [50, 50, 50, 50],
                    maxZoom: 18,
                    duration: 1000
                });
                console.log('Map fitted to BMO2 PAMA layers extent');
            }
        }
    }, 500);

    // Toggle GeoJSON layers visibility
    document.getElementById('showAreaCctv').addEventListener('change', function(e) {
        areaCctvLayer.setVisible(e.target.checked);
    });

    document.getElementById('showAreaKerja').addEventListener('change', function(e) {
        areaKerjaLayer.setVisible(e.target.checked);
    });

    document.getElementById('showAreaKerjaBmo2Pama').addEventListener('change', function(e) {
        if (areaKerjaBmo2PamaLayer) {
            areaKerjaBmo2PamaLayer.setVisible(e.target.checked);
        }
    });

    document.getElementById('showAreaCctvBmo2Pama').addEventListener('change', function(e) {
        if (areaCctvBmo2PamaLayer) {
            areaCctvBmo2PamaLayer.setVisible(e.target.checked);
        }
    });

    document.getElementById('showDifferenceBmo2Pama').addEventListener('change', function(e) {
        if (differenceBmo2PamaLayer) {
            differenceBmo2PamaLayer.setVisible(e.target.checked);
        }
    });

    document.getElementById('showSymmetricalDifferenceBmo2Pama').addEventListener('change', function(e) {
        if (symmetricalDifferenceBmo2PamaLayer) {
            symmetricalDifferenceBmo2PamaLayer.setVisible(e.target.checked);
        }
    });

    document.getElementById('showIntersectionBmo2Pama').addEventListener('change', function(e) {
        if (intersectionBmo2PamaLayer) {
            intersectionBmo2PamaLayer.setVisible(e.target.checked);
        }
    });

    // Click handler untuk CCTV markers dan GeoJSON polygons
    map.on('singleclick', function(evt) {
        const feature = map.forEachFeatureAtPixel(evt.pixel, function(feature) {
            return feature;
        });

        if (feature) {
            const coordinate = evt.coordinate;
            const props = feature.getProperties();
            
            // Check if it's a GeoJSON polygon (Area CCTV or Area Kerja)
            if (props.nomor_cctv !== undefined || props.id_lokasi !== undefined) {
                // It's a GeoJSON polygon
                let content = '';
                
                if (props.nomor_cctv !== undefined) {
                    // Area CCTV
                    content = `
                        <h3>Area CCTV</h3>
                        <p><strong>Nomor CCTV:</strong> ${props.nomor_cctv || 'N/A'}</p>
                        <p><strong>Nama CCTV:</strong> ${props.nama_cctv || 'N/A'}</p>
                        <p><strong>Site:</strong> ${props.site || 'N/A'}</p>
                        <p><strong>Perusahaan:</strong> ${props.perusahaan_cctv || 'N/A'}</p>
                        <p><strong>Luasan:</strong> ${props.luasan ? props.luasan.toLocaleString('id-ID', {maximumFractionDigits: 2}) : 'N/A'} m²</p>
                    `;
                } else if (props.id_lokasi !== undefined) {
                    // Area Kerja
                    content = `
                        <h3>Area Kerja</h3>
                        <p><strong>Lokasi:</strong> ${props.lokasi || 'N/A'}</p>
                        <p><strong>ID Lokasi:</strong> ${props.id_lokasi || 'N/A'}</p>
                        <p><strong>Site:</strong> ${props.site || 'N/A'}</p>
                        <p><strong>Perusahaan:</strong> ${props.perusahaan || 'N/A'}</p>
                        <p><strong>Area Kerja:</strong> ${props.area_kerja || 'N/A'}</p>
                        <p><strong>Luasan:</strong> ${props.luasan ? props.luasan.toLocaleString('id-ID', {maximumFractionDigits: 2}) : 'N/A'} m²</p>
                        <p><strong>Upload Data:</strong> ${props.upload_data || 'N/A'}</p>
                    `;
                }
                
                document.getElementById('popup-content').innerHTML = content;
                popupOverlay.setPosition(coordinate);
                return;
            }
            
            // Feature adalah CCTV marker
            if (feature.get('id')) {
            let content = `
                <h3>${props.name}</h3>
                <p><strong>ID:</strong> ${props.id}</p>
                <p><strong>Status:</strong> <span style="color: ${props.status === 'Aktif' || props.status === 'Baik' ? 'green' : 'red'}">${props.status}</span></p>
                <p><strong>Tipe:</strong> ${props.type || 'N/A'}</p>
                <p><strong>Deskripsi:</strong> ${props.description || 'N/A'}</p>
            `;
            
            // Tambahkan informasi tambahan jika tersedia
            if (props.site) {
                content += `<p><strong>Site:</strong> ${props.site}</p>`;
            }
            if (props.perusahaan) {
                content += `<p><strong>Perusahaan:</strong> ${props.perusahaan}</p>`;
            }
            if (props.kategori) {
                content += `<p><strong>Kategori:</strong> ${props.kategori}</p>`;
            }
            if (props.brand) {
                content += `<p><strong>Brand:</strong> ${props.brand}</p>`;
            }
            if (props.model) {
                content += `<p><strong>Model:</strong> ${props.model}</p>`;
            }
            if (props.viewType) {
                content += `<p><strong>Fungsi CCTV:</strong> ${props.viewType}</p>`;
            }
            if (props.area) {
                content += `<p><strong>Coverage Lokasi:</strong> ${props.area}</p>`;
            }
            if (props.areaType) {
                content += `<p><strong>Kategori Area:</strong> ${props.areaType}</p>`;
            }
            if (props.activity) {
                content += `<p><strong>Kategori Aktivitas:</strong> ${props.activity}</p>`;
            }
            if (props.controlRoom) {
                content += `<p><strong>Control Room:</strong> ${props.controlRoom}</p>`;
            }
            if (props.radius_pengawasan) {
                content += `<p><strong>Radius Pengawasan:</strong> ${props.radius_pengawasan}</p>`;
            }
            if (props.externalUrl || props.link_akses) {
                const url = props.externalUrl || props.link_akses;
                content += `<p><strong>Link Akses:</strong> <a href="${url}" target="_blank" rel="noopener">Buka Link</a></p>`;
            }
            if (props.username || props.user_name) {
                content += `<p><strong>Username:</strong> ${props.username || props.user_name}</p>`;
            }
            if (props.connected) {
                content += `<p><strong>Connected:</strong> ${props.connected}</p>`;
            }
            if (props.mirrored) {
                content += `<p><strong>Mirrored:</strong> ${props.mirrored}</p>`;
            }
            if (props.keterangan) {
                content += `<p><strong>Keterangan:</strong> ${props.keterangan}</p>`;
            }
            
            content += `<p><strong>Koordinat:</strong> ${ol.proj.toLonLat(coordinate)[0].toFixed(6)}, ${ol.proj.toLonLat(coordinate)[1].toFixed(6)}</p>`;
            
            // Tambahkan tombol Live View jika CCTV memiliki link akses atau IP
            if (props.externalUrl || props.link_akses) {
                content += `<button onclick="openCCTVStreamModal('${props.id}')">🔗 Buka Link Akses</button>`;
            }
            if (props.ip && (props.brand === 'HIKVision' || props.brand === 'Ezviz')) {
                content += `<button onclick="openCCTVStreamModal('${props.id}')">📹 Live View</button>`;
            }
            if (props.rtspUrl || (props.rtspHost && props.rtspChannel)) {
                content += `<button onclick="openCCTVStreamModal('${props.id}')">🎥 RTSP Stream</button>`;
            }
            
            document.getElementById('popup-content').innerHTML = content;
            popupOverlay.setPosition(coordinate);
        } else {
            // Klik di area kosong, tutup popup
            popupOverlay.setPosition(undefined);
        }
    });

    // Change cursor saat hover di atas marker
    map.on('pointermove', function(evt) {
        const pixel = map.getEventPixel(evt.originalEvent);
        const hit = map.hasFeatureAtPixel(pixel);
        map.getTargetElement().style.cursor = hit ? 'pointer' : '';
    });
    
    // Function to add WMS layer to map
    function addWMSLayerToMap(layerName = '', serverKey = currentWmsServer) {
        // Remove existing WMS layer if any
        if (wmsLayer) {
            map.removeLayer(wmsLayer);
            wmsLayer = null;
        }
        
        // Create and add new WMS layer
        wmsLayer = createWMSLayer(layerName, serverKey);
        map.addLayer(wmsLayer);
        currentLayer = layerName;
        
        // Update map center berdasarkan server yang dipilih
        const server = wmsServers[serverKey];
        const view = map.getView();
        view.setCenter(ol.proj.fromLonLat(server.center));
        view.setZoom(15);
        
        // Pastikan CCTV layer selalu di atas WMS layer dan GeoJSON layers
        // Gunakan getLayers() untuk mengatur urutan layer
        const layers = map.getLayers();
        if (cctvLayer) {
            // Jika CCTV layer sudah ada, pastikan berada di posisi teratas
            if (layers.getArray().includes(cctvLayer)) {
                // Hapus dari posisi lama
                layers.remove(cctvLayer);
            }
            // Tambahkan di posisi teratas (setelah semua layer)
            layers.push(cctvLayer);
        }
        // Ensure GeoJSON layers are above WMS but below CCTV
        if (areaCctvLayer && layers.getArray().includes(areaCctvLayer)) {
            layers.remove(areaCctvLayer);
            layers.push(areaCctvLayer);
        }
        if (areaKerjaLayer && layers.getArray().includes(areaKerjaLayer)) {
            layers.remove(areaKerjaLayer);
            layers.push(areaKerjaLayer);
        }
        // Ensure BMO2 PAMA layers are above WMS but below CCTV
        if (areaKerjaBmo2PamaLayer && layers.getArray().includes(areaKerjaBmo2PamaLayer)) {
            layers.remove(areaKerjaBmo2PamaLayer);
            layers.push(areaKerjaBmo2PamaLayer);
        }
        if (areaCctvBmo2PamaLayer && layers.getArray().includes(areaCctvBmo2PamaLayer)) {
            layers.remove(areaCctvBmo2PamaLayer);
            layers.push(areaCctvBmo2PamaLayer);
        }
        if (differenceBmo2PamaLayer && layers.getArray().includes(differenceBmo2PamaLayer)) {
            layers.remove(differenceBmo2PamaLayer);
            layers.push(differenceBmo2PamaLayer);
        }
        if (symmetricalDifferenceBmo2PamaLayer && layers.getArray().includes(symmetricalDifferenceBmo2PamaLayer)) {
            layers.remove(symmetricalDifferenceBmo2PamaLayer);
            layers.push(symmetricalDifferenceBmo2PamaLayer);
        }
        if (intersectionBmo2PamaLayer && layers.getArray().includes(intersectionBmo2PamaLayer)) {
            layers.remove(intersectionBmo2PamaLayer);
            layers.push(intersectionBmo2PamaLayer);
        }
        
        // Setup error handling
        setupErrorHandling();
    }
    
    // Tambahkan CCTV layer langsung setelah map dibuat dan pastikan di posisi teratas
    setTimeout(function() {
        const layers = map.getLayers();
        if (cctvLayer) {
            if (layers.getArray().includes(cctvLayer)) {
                layers.remove(cctvLayer);
            }
            // Pastikan CCTV layer selalu di posisi teratas
            layers.push(cctvLayer);
        }
    }, 100);
    
    // Pastikan CCTV layer selalu di atas saat map selesai render
    map.on('rendercomplete', function() {
        const layers = map.getLayers();
        if (cctvLayer && layers.getArray().includes(cctvLayer)) {
            // Pindahkan CCTV layer ke posisi teratas
            layers.remove(cctvLayer);
            layers.push(cctvLayer);
        }
    });

    // Update layer when layer select changes
    document.getElementById('layerSelect').addEventListener('change', function(e) {
        const selectedLayer = e.target.value;
        addWMSLayerToMap(selectedLayer);
    });

    // Update WMS server when server select changes
    document.getElementById('wmsServerSelect').addEventListener('change', function(e) {
        currentWmsServer = e.target.value;
        wmsUrl = wmsServers[currentWmsServer].url;
        
        // Reload layers dari server yang baru
        loadWMSLayers();
    });

    // Update projection when projection select changes
    document.getElementById('projectionSelect').addEventListener('change', function(e) {
        const projection = e.target.value;
        const view = map.getView();
        
        // UPDATE CENTER sesuai server yang dipilih
        const server = wmsServers[currentWmsServer];
        const newCenter = server.center;
        
        if (projection === 'EPSG:4326') {
            view.setProjection('EPSG:4326');
            view.setCenter(newCenter);
            view.setZoom(15);
        } else {
            view.setProjection('EPSG:3857');
            view.setCenter(ol.proj.fromLonLat(newCenter));
            view.setZoom(15);
        }
    });

    // Try to get capabilities to list available layers
    async function loadWMSLayers(serverKey = currentWmsServer) {
        const layerSelect = document.getElementById('layerSelect');
        layerSelect.innerHTML = '<option value="">Loading...</option>';
        
        const server = wmsServers[serverKey];
        const serverUrl = server.url;
        
        try {
            const capabilitiesUrl = serverUrl + '?SERVICE=WMS&VERSION=1.1.1&REQUEST=GetCapabilities';
            const response = await fetch(capabilitiesUrl);
            
            if (!response.ok) {
                throw new Error('Failed to fetch capabilities');
            }
            
            const text = await response.text();
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(text, 'text/xml');
            
            // Check for parsing errors
            const parseError = xmlDoc.querySelector('parsererror');
            if (parseError) {
                throw new Error('XML parsing error');
            }
            
            // Check for service exception
            const serviceException = xmlDoc.querySelector('ServiceException');
            if (serviceException) {
                throw new Error('Service Exception: ' + serviceException.textContent);
            }
            
            // Parse layer names - untuk format XML ini, layer ada di <Layer><Name>
            const layers = xmlDoc.querySelectorAll('Layer > Name');
            layerSelect.innerHTML = '';
            
            if (layers.length > 0) {
                layers.forEach((layer) => {
                    const layerName = layer.textContent.trim();
                    const layerElement = layer.closest('Layer');
                    const titleElement = layerElement.querySelector('Title');
                    const layerTitle = titleElement ? titleElement.textContent.trim() : layerName;
                    
                    if (layerName) {
                        const option = document.createElement('option');
                        option.value = layerName;
                        option.textContent = layerTitle || `Layer: ${layerName}`;
                        layerSelect.appendChild(option);
                    }
                });
                
                // Auto-select first layer (layer "0")
                if (layers.length > 0) {
                    const firstLayerName = layers[0].textContent.trim();
                    layerSelect.value = firstLayerName;
                    addWMSLayerToMap(firstLayerName, serverKey);
                    
                    // Update map center berdasarkan bounding box dari XML atau server config
                    const layerElement = layers[0].closest('Layer');
                    const bbox = layerElement.querySelector('LatLonBoundingBox');
                    if (bbox) {
                        const minx = parseFloat(bbox.getAttribute('minx'));
                        const miny = parseFloat(bbox.getAttribute('miny'));
                        const maxx = parseFloat(bbox.getAttribute('maxx'));
                        const maxy = parseFloat(bbox.getAttribute('maxy'));
                        
                        // Calculate center
                        const centerLon = (minx + maxx) / 2;
                        const centerLat = (miny + maxy) / 2;
                        
                        // Update map view
                        const view = map.getView();
                        view.setCenter(ol.proj.fromLonLat([centerLon, centerLat]));
                        view.setZoom(15); // Zoom lebih dekat karena area kecil
                    }
                }
            } else {
                // Fallback jika tidak ada layer ditemukan
                const server = wmsServers[serverKey];
                layerSelect.innerHTML = `<option value="0">${server.name} - Layer 0</option>`;
                addWMSLayerToMap('0', serverKey);
            }
        } catch (error) {
            console.warn('Could not load WMS capabilities:', error);
            console.info('Using default layer "0"');
            
            // Fallback ke layer 0 dengan bounding box dari server config
            const server = wmsServers[serverKey];
            layerSelect.innerHTML = `<option value="0">${server.name} - Layer 0</option>`;
            layerSelect.value = '0';
            addWMSLayerToMap('0', serverKey);
        }
    }

    // Load layers on page load
    loadWMSLayers();

    // Handle WMS errors
    function setupErrorHandling() {
        if (wmsLayer) {
            wmsLayer.getSource().on('tileloaderror', function(event) {
                console.error('Tile load error:', event);
                console.info('Trying alternative layer names or checking server configuration might help.');
                console.info('Server URL:', wmsUrl);
                console.info('Current layer:', currentLayer || '(kosong)');
                console.info('Coba pilih layer lain dari dropdown atau hubungi administrator server untuk informasi layer yang tersedia.');
            });
        }
    }

    // Display map info
    map.on('moveend', function() {
        const view = map.getView();
        const center = view.getCenter();
        // Convert to lon/lat if using EPSG:3857
        if (view.getProjection().getCode() === 'EPSG:3857') {
            const lonLat = ol.proj.toLonLat(center);
            console.log('Map center (lon/lat):', lonLat, 'Zoom:', view.getZoom());
        } else {
            console.log('Map center:', center, 'Zoom:', view.getZoom());
        }
    });
</script>
@endsection

