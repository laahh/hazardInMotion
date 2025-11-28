@extends('layouts.MasterMotionHazard')

@section('title', 'MiningEyes 2.0 - Hazard Motion')
@section('css')
<style>
    html, body {
        margin: 0;
        padding: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
    }

    /* Hide default topbar and footer */
    .top-header {
        display: none !important;
    }
    
    footer {
        display: none !important;
    }

    .main-wrapper {
        margin: 0 !important;
        padding: 0 !important;
        height: 100vh !important;
        width: 100vw !important;
        overflow: hidden !important;
    }

    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        height: 100vh !important;
        width: 100vw !important;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* Top Bar */
    .top-bar {
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        border-bottom: 2px solid #1e40af;
        padding: 0 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 1000;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06);
        flex-shrink: 0;
        height: 70px;
        width: 100%;
    }

    .top-bar-title {
        font-size: 24px;
        font-weight: 700;
        color: #ffffff;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        letter-spacing: 0.5px;
    }

    .top-bar-title::before {
        content: "⚡";
        font-size: 28px;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
    }

    .top-bar-stats {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .stat-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        padding: 8px 16px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        min-width: 80px;
        transition: all 0.3s ease;
    }

    .stat-item:has(select) {
        padding: 6px 12px;
    }

    .stat-item:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    .stat-item select {
        background: rgba(255, 255, 255, 0.95) !important;
        border: 1px solid rgba(255, 255, 255, 0.3) !important;
        color: #1e3a8a !important;
        font-weight: 600 !important;
        border-radius: 6px !important;
        padding: 8px 36px 8px 12px !important;
        font-size: 13px !important;
        cursor: pointer;
        transition: all 0.2s ease;
        min-width: 120px;
        width: 100%;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%231e3a8a' d='M6 9L1 4h10z'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 10px center !important;
        background-size: 12px !important;
    }

    .stat-item select:hover {
        background: #ffffff !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .stat-item select:focus {
        outline: none;
        border-color: #ffffff !important;
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
    }

    .stat-item span:not(.stat-badge) {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.9);
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .stat-item strong {
        color: #ffffff;
        font-weight: 700;
        font-size: 18px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .stat-badge {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 700;
        background: #ffffff;
        color: #dc2626;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .stat-badge.alert {
        background: #fee2e2;
        color: #dc2626;
        border-color: #fecaca;
    }

    .stat-weather {
        display: flex;
        align-items: center;
        gap: 6px;
        color: rgba(255, 255, 255, 0.9);
        font-size: 12px;
        font-weight: 600;
    }

    .stat-weather::before {
        content: "☁️";
        font-size: 16px;
    }

    /* Main Layout */
    .main-layout {
        display: flex;
        flex: 1;
        overflow: hidden;
        height: calc(100vh - 70px);
        min-height: 0;
        width: 100%;
    }

    /* Left Sidebar */
    .left-sidebar {
        width: 300px;
        min-width: 300px;
        background: #fff;
        border-right: 1px solid #e5e7eb;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 20px;
        flex-shrink: 0;
        height: 100%;
    }

    .sidebar-section {
        background: #f9fafb;
        border-radius: 8px;
        padding: 16px;
    }

    .sidebar-section h3 {
        font-size: 16px;
        font-weight: 600;
        color: #111827;
        margin: 0 0 12px 0;
        padding-bottom: 8px;
        border-bottom: 2px solid #e5e7eb;
    }

    .hazard-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .hazard-item {
        padding: 10px;
        margin-bottom: 8px;
        background: #fff;
        border-radius: 6px;
        border-left: 3px solid #dc2626;
        font-size: 13px;
    }

    .hazard-item-id {
        font-weight: 600;
        color: #111827;
    }

    .hazard-item-name {
        color: #6b7280;
        margin-top: 4px;
    }

    .hazard-item-distance {
        color: #dc2626;
        font-weight: 600;
        margin-top: 4px;
    }

    .equipment-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .equipment-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e5e7eb;
        font-size: 14px;
    }

    .equipment-item:last-child {
        border-bottom: none;
    }

    .equipment-type {
        color: #374151;
    }

    .equipment-count {
        font-weight: 600;
        color: #111827;
    }

    /* Map Container */
    .map-wrapper {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #f3f4f6;
        min-width: 0;
        min-height: 0;
        width: 100%;
    }

    .map-controls {
        padding: 12px 16px;
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        flex-shrink: 0;
        width: 100%;
    }

    .map-controls .form-label {
        font-size: 12px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 4px;
    }

    .map-controls .form-select {
        font-size: 13px;
        padding: 6px 12px;
    }

    .map-container {
        flex: 1;
        width: 100%;
        height: 100%;
        position: relative;
        overflow: hidden;
        min-height: 0;
    }
    
    #map {
        width: 100%;
        height: 100%;
    }

    /* Right Sidebar */
    .right-sidebar {
        width: 320px;
        min-width: 320px;
        background: #fff;
        border-left: 1px solid #e5e7eb;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 20px;
        flex-shrink: 0;
        height: 100%;
    }

    .search-section {
        margin-bottom: 8px;
    }

    .search-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
    }

    .search-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .cctv-section {
        background: #f9fafb;
        border-radius: 8px;
        padding: 16px;
    }

    .cctv-section h3 {
        font-size: 16px;
        font-weight: 600;
        color: #111827;
        margin: 0 0 12px 0;
    }

    .cctv-info {
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 12px;
    }

    .cctv-stream-placeholder {
        width: 100%;
        height: 200px;
        background: #1f2937;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        font-size: 12px;
        margin-bottom: 12px;
    }

    .cctv-controls {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }

    .cctv-control-btn {
        padding: 8px;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        cursor: pointer;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .cctv-control-btn:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
    }

    .cctv-control-btn.reset {
        grid-column: span 3;
        font-size: 14px;
        padding: 10px;
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
<!-- Top Bar -->
<div class="top-bar">
    <h1 class="top-bar-title">MiningEyes 2.0 Berau Coal</h1>
    <div class="top-bar-stats">
        <div class="stat-item">
            <select class="form-select form-select-sm" id="pitSelect">
                <option value="PIT-KGU">PIT-KGU</option>
                <option value="PIT-KGU-Timur">PIT-KGU Timur</option>
                <option value="PIT-Lainnya">PIT Lainnya</option>
            </select>
        </div>
        <div class="stat-item">
            <span>Manusia</span>
            <strong id="statManusia">{{ $stats['manusia'] ?? 17 }}</strong>
        </div>
        <div class="stat-item">
            <span>Alat</span>
            <strong id="statAlat">{{ $stats['alat'] ?? 31 }}</strong>
        </div>
        <div class="stat-item">
            <span>Alert</span>
            <span class="stat-badge alert" id="statAlert">{{ $stats['alert'] ?? 5 }}</span>
        </div>
        <div class="stat-item">
            <div class="stat-weather">
                <span id="statWeather">{{ $stats['weather'] ?? 'Berawan' }}</span>
            </div>
            <strong id="statTemp">{{ $stats['temperature'] ?? '29.4°C' }}</strong>
        </div>
    </div>
</div>

<!-- Main Layout -->
<div class="main-layout">
    <!-- Left Sidebar -->
    <div class="left-sidebar">
        <!-- Notifikasi Bahaya -->
        <div class="sidebar-section">
            <h3>Notifikasi Bahaya</h3>
            <ul class="hazard-list" id="hazardList">
                @forelse($hazardNotifications ?? [] as $hazard)
                <li class="hazard-item">
                    <div class="hazard-item-id">{{ $hazard['id'] ?? '' }}</div>
                    <div class="hazard-item-name">{{ $hazard['name'] ?? '' }}</div>
                    <div class="hazard-item-distance">{{ $hazard['distance'] ?? '' }}</div>
                </li>
                @empty
                <li class="hazard-item">
                    <div class="hazard-item-id">Tidak ada notifikasi</div>
                </li>
                @endforelse
            </ul>
        </div>

        <!-- Equipment & Manpower -->
        <div class="sidebar-section">
            <h3>Equipment & Manpower</h3>
            <ul class="equipment-list" id="equipmentList">
                @forelse($equipmentManpower ?? [] as $item)
                <li class="equipment-item">
                    <span class="equipment-type">{{ $item['type'] ?? '' }}</span>
                    <span class="equipment-count">{{ $item['count'] ?? 0 }}</span>
                </li>
                @empty
                <li class="equipment-item">
                    <span class="equipment-type">Crane Truck</span>
                    <span class="equipment-count">0</span>
                </li>
                <li class="equipment-item">
                    <span class="equipment-type">Dozer</span>
                    <span class="equipment-count">0</span>
                </li>
                <li class="equipment-item">
                    <span class="equipment-type">Excavator</span>
                    <span class="equipment-count">0</span>
                </li>
                <li class="equipment-item">
                    <span class="equipment-type">Grader</span>
                    <span class="equipment-count">0</span>
                </li>
                <li class="equipment-item">
                    <span class="equipment-type">HD</span>
                    <span class="equipment-count">0</span>
                </li>
                @endforelse
            </ul>
        </div>
    </div>

    <!-- Map Wrapper -->
    <div class="map-wrapper">
        <div class="map-controls">
            <div style="flex: 1; min-width: 200px;">
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
            <div style="flex: 1; min-width: 200px;">
                <label for="layerSelect" class="form-label">Layer:</label>
                <select id="layerSelect" class="form-select">
                    <option value="0">Loading...</option>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label for="projectionSelect" class="form-label">Projection:</label>
                <select id="projectionSelect" class="form-select">
                    <option value="EPSG:3857">Web Mercator (EPSG:3857)</option>
                    <option value="EPSG:4326">WGS84 (EPSG:4326)</option>
                </select>
            </div>
        </div>
        <div id="map" class="map-container"></div>
        <div id="popup" class="ol-popup">
            <a href="#" id="popup-closer" class="ol-popup-closer"></a>
            <div id="popup-content" class="cctv-popup-content"></div>
        </div>
    </div>

    <!-- Right Sidebar -->
    <div class="right-sidebar">
        <!-- Search Section -->
        <div class="search-section">
            <input type="text" class="search-input" id="searchInput" placeholder="Cari ID / Nama (mis. DT-102 / Andi)">
        </div>

        <!-- CCTV Section -->
        <div class="cctv-section">
            <h3>CCTV</h3>
            <div class="cctv-info">
                <div id="cctvName">MTC20-2</div>
                <div id="cctvTarget" style="color: #9ca3af; font-size: 12px; margin-top: 4px;">Target: -</div>
            </div>
            <div class="cctv-stream-placeholder" id="cctvStreamPlaceholder">
                CCTV Stream akan muncul di sini
            </div>
            <div class="cctv-controls">
                <button class="cctv-control-btn" title="Up">↑</button>
                <button class="cctv-control-btn" title="Zoom In">+</button>
                <button class="cctv-control-btn" title="Right">→</button>
                <button class="cctv-control-btn" title="Left">←</button>
                <button class="cctv-control-btn" title="Zoom Out">-</button>
                <button class="cctv-control-btn" title="Down">↓</button>
                <button class="cctv-control-btn reset" title="Reset">Reset</button>
            </div>
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
    
    // Update CCTV info when clicking on map
    let selectedCCTV = null;

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

    // Function to load CCTV stream to right sidebar
    function loadCCTVStreamToSidebar(cctv) {
        const placeholder = document.getElementById('cctvStreamPlaceholder');
        if (!placeholder) return;
        
        const preferredMode = (cctv.streamingMode || '').toLowerCase();
        const rtspUrl = buildRtspUrl(cctv);
        const hasPortal = !!(cctv.externalUrl || cctv.link_akses);
        const hasInternalIp = cctv.ip && (cctv.brand === 'HIKVision' || cctv.brand === 'Ezviz');
        
        // Use proxy for internal IP cameras
        if (preferredMode === 'proxy' || hasInternalIp) {
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
            
            placeholder.innerHTML = `
                <img id="cctvSidebarStream" 
                     src="${proxyUrl}?${params.toString()}" 
                     style="width: 100%; height: 100%; object-fit: contain; background-color: #000; border-radius: 6px;">
            `;
            
            const sidebarImg = document.getElementById('cctvSidebarStream');
            if (sidebarImg) {
                if (typeof sidebarRefreshInterval !== 'undefined' && sidebarRefreshInterval) {
                    clearInterval(sidebarRefreshInterval);
                }
                sidebarRefreshInterval = setInterval(function() {
                    const timestamp = new Date().getTime();
                    const refreshParams = new URLSearchParams({
                        ip: cctv.ip,
                        port: port,
                        channel: channel,
                        username: username,
                        password: password,
                        t: timestamp
                    });
                    sidebarImg.src = `${proxyUrl}?${refreshParams.toString()}`;
                }, 1000);
            }
        } else {
            placeholder.innerHTML = 'Stream tidak tersedia untuk CCTV ini';
        }
    }
    
    let sidebarRefreshInterval = null;

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

    // Click handler untuk CCTV markers
    map.on('singleclick', function(evt) {
        const feature = map.forEachFeatureAtPixel(evt.pixel, function(feature) {
            return feature;
        });

        if (feature && feature.get('id')) {
            // Feature adalah CCTV marker
            const coordinate = evt.coordinate;
            const props = feature.getProperties();
            
            // Update right sidebar CCTV info
            selectedCCTV = props;
            document.getElementById('cctvName').textContent = props.name || 'CCTV';
            document.getElementById('cctvTarget').textContent = `Target: ${props.name || '-'}`;
            
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
            
            // Auto-load CCTV stream in right sidebar
            if (props.id) {
                const cctv = cctvLocations.find(c => c.id === props.id);
                if (cctv) {
                    loadCCTVStreamToSidebar(cctv);
                }
            }
        } else {
            // Klik di area kosong, tutup popup
            popupOverlay.setPosition(undefined);
            selectedCCTV = null;
            document.getElementById('cctvName').textContent = 'MTC20-2';
            document.getElementById('cctvTarget').textContent = 'Target: -';
            document.getElementById('cctvStreamPlaceholder').innerHTML = 'CCTV Stream akan muncul di sini';
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

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        if (searchTerm.length < 2) {
            // Reset map view if search is cleared
            return;
        }
        
        // Search in CCTV locations
        const found = cctvLocations.find(c => {
            const name = (c.name || '').toLowerCase();
            const id = (c.id || '').toLowerCase();
            return name.includes(searchTerm) || id.includes(searchTerm);
        });
        
        if (found && found.location) {
            const view = map.getView();
            view.setCenter(ol.proj.fromLonLat(found.location));
            view.setZoom(18);
            
            // Trigger click on the feature to show popup
            setTimeout(() => {
                const pixel = map.getPixelFromCoordinate(ol.proj.fromLonLat(found.location));
                const evt = {
                    coordinate: ol.proj.fromLonLat(found.location),
                    pixel: pixel
                };
                map.dispatchEvent({
                    type: 'singleclick',
                    coordinate: evt.coordinate,
                    pixel: evt.pixel
                });
            }, 300);
        }
    });
</script>
@endsection

