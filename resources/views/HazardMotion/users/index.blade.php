@extends('layouts.MasterMotionHazard')

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

    /* RTSP Stream Section */
    .rtsp-streams-section {
        margin: 24px 0;
    }

    .rtsp-stream-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: 100%;
    }

    .rtsp-stream-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .rtsp-stream-header {
        padding: 12px 16px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .rtsp-stream-title {
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .rtsp-stream-status {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }

    .rtsp-stream-container {
        position: relative;
        width: 100%;
        height: 300px;
        background: #000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .rtsp-stream-container img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .rtsp-stream-loading {
        color: #ffffff;
        font-size: 14px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }

    .rtsp-stream-loading .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-top-color: #ffffff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .rtsp-stream-error {
        color: #ef4444;
        text-align: center;
        padding: 20px;
        font-size: 13px;
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
                      <h2 class="mb-0">Total YTD</h2>
                    </div>
                    <div class="">
                      <p class="dash-lable d-flex align-items-center gap-1 rounded mb-0 bg-danger text-danger bg-opacity-10"><span class="material-icons-outlined fs-6">arrow_downward</span>8.6%</p>
                    </div>
                  </div>
                  <p class="mb-0">10</p>
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
        <div class="col-md-4">
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
        <div class="col-md-4">
            <label for="layerSelect" class="form-label">Layer:</label>
            <select id="layerSelect" class="form-select">
                <option value="0">Loading...</option>
            </select>
        </div>
        <div class="col-md-4">
            <label for="projectionSelect" class="form-label">Projection:</label>
            <select id="projectionSelect" class="form-select">
                <option value="EPSG:3857">Web Mercator (EPSG:3857)</option>
                <option value="EPSG:4326">WGS84 (EPSG:4326)</option>
            </select>
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
        <li>Jika peta tidak muncul, coba pilih layer yang berbeda dari dropdown di atas</li>
        <li>Server mungkin memerlukan parameter layer tertentu - coba mulai dengan "All Layers (Kosong)"</li>
        <li>Jika masih error, kemungkinan ada masalah dengan CORS atau server configuration</li>
        <li>Silakan cek console browser (F12) untuk detail error</li>
    </ul>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/ol@8.2.0/dist/ol.js"></script>
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
                mirrored: cctv.mirrored || '',
                no_cctv: cctv.no_cctv || '',
                id_lokasi: cctv.id_lokasi || '',
                lokasi: cctv.lokasi || '',
                id_detail_lokasi: cctv.id_detail_lokasi || '',
                detail_lokasi: cctv.detail_lokasi || '',
                pja: cctv.pja || ''
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

    // Hover handler untuk CCTV markers - menampilkan popup saat hover
    let hoveredFeature = null;
    map.on('pointermove', function(evt) {
        const pixel = map.getEventPixel(evt.originalEvent);
        const feature = map.forEachFeatureAtPixel(pixel, function(feature) {
            return feature;
        });

        // Change cursor
        const hit = map.hasFeatureAtPixel(pixel);
        map.getTargetElement().style.cursor = hit ? 'pointer' : '';

        if (feature && feature.get('id')) {
            // Jika hover di feature yang sama, tidak perlu update
            if (hoveredFeature === feature) {
                return;
            }
            
            hoveredFeature = feature;
            const coordinate = evt.coordinate;
            const props = feature.getProperties();
            
            let content = `<h3>${props.name || 'CCTV'}</h3>`;
            
            // Hanya tampilkan field yang diminta: Site, Perusahaan, No. CCTV, Lokasi, Detail Lokasi, PJA
            content += `<p><strong>Site:</strong> ${props.site || 'N/A'}</p>`;
            content += `<p><strong>Perusahaan:</strong> ${props.perusahaan || 'N/A'}</p>`;
            content += `<p><strong>No. CCTV:</strong> ${props.no_cctv || 'N/A'}</p>`;
            content += `<p><strong>Lokasi:</strong> ${props.lokasi || 'N/A'}</p>`;
            content += `<p><strong>Detail Lokasi:</strong> ${props.detail_lokasi || 'N/A'}</p>`;
            content += `<p><strong>PJA:</strong> ${props.pja || 'N/A'}</p>`;
            
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
            // Tidak hover di feature, sembunyikan popup
            if (hoveredFeature) {
                hoveredFeature = null;
                popupOverlay.setPosition(undefined);
            }
        }
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
        
        // Pastikan CCTV layer selalu di atas WMS layer
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

    // RTSP Stream Configuration
    const rtspStreams = [
        {
            id: 'simpangzwest',
            name: 'Simpang Z West',
            rtspUrl: 'rtsp://miningeyes:miningeyes@10.1.80.200:8554/simpangzwest',
            containerId: 'stream-simpangzwest',
            statusId: 'status-simpangzwest'
        },
        {
            id: 'simpangjfix',
            name: 'Simpang J Fix',
            rtspUrl: 'rtsp://miningeyes:miningeyes@10.1.80.200:8554/simpangjfix',
            containerId: 'stream-simpangjfix',
            statusId: 'status-simpangjfix'
        },
        {
            id: 'cdtimur01',
            name: 'CD Timur 01',
            rtspUrl: 'rtsp://miningeyes:miningeyes@10.1.80.200:8554/cdtimur01',
            containerId: 'stream-cdtimur01',
            statusId: 'status-cdtimur01'
        }
    ];

    // Function to load RTSP stream
    function loadRTSPStream(streamConfig) {
        const container = document.getElementById(streamConfig.containerId);
        const statusIndicator = document.getElementById(streamConfig.statusId);
        
        if (!container) return;

        const proxyRtspUrl = '{{ route("cctv-proxy-rtsp") }}';
        const params = new URLSearchParams({
            rtsp: streamConfig.rtspUrl,
            transport: 'tcp'
        });

        const img = document.createElement('img');
        img.src = proxyRtspUrl + '?' + params.toString();
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'contain';
        img.style.background = '#000';

        // Handle successful load
        img.onload = function() {
            container.innerHTML = '';
            container.appendChild(img);
            if (statusIndicator) {
                statusIndicator.style.background = '#10b981';
            }
        };

        // Handle error
        img.onerror = function() {
            container.innerHTML = `
                <div class="rtsp-stream-error">
                    <p>❌ Gagal memuat stream</p>
                    <p style="font-size: 11px; color: #999; margin-top: 8px;">Pastikan server memiliki akses ke RTSP stream dan ffmpeg terinstal</p>
                </div>
            `;
            if (statusIndicator) {
                statusIndicator.style.background = '#ef4444';
                statusIndicator.style.animation = 'none';
            }
        };

        // Replace loading with image
        container.innerHTML = '';
        container.appendChild(img);
    }

    // Load all RTSP streams when page loads
    document.addEventListener('DOMContentLoaded', function() {
        rtspStreams.forEach(function(stream) {
            // Delay loading to avoid overwhelming the server
            setTimeout(function() {
                loadRTSPStream(stream);
            }, rtspStreams.indexOf(stream) * 500);
        });
    });
</script>
@endsection

