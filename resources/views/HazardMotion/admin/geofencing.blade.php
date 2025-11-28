@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Geofencing - Zone Management - Beraucoal')

@section('css')
<style>
    .geofencing-header {
        margin-bottom: 24px;
    }

    .geofencing-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .geofencing-subtitle {
        font-size: 14px;
        color: #6b7280;
    }

    .stats-card {
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        color: white;
    }

    .stats-card.total {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .stats-card.active {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .stats-card.restricted {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }

    .stats-card.area {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .stats-number {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .stats-label {
        font-size: 14px;
        opacity: 0.9;
    }

    .zone-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        transition: all 0.3s ease;
        background: white;
    }

    .zone-card:hover {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .zone-type-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .zone-type-restricted {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .zone-type-storage {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .zone-type-safety {
        background-color: #d1fae5;
        color: #065f46;
    }

    .zone-type-exclusion {
        background-color: #fef3c7;
        color: #92400e;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-active {
        background-color: #d1fae5;
        color: #065f46;
    }

    .status-inactive {
        background-color: #f3f4f6;
        color: #6b7280;
    }

    .map-container {
        width: 100%;
        height: 600px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .zone-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }

    .btn-action {
        padding: 6px 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-edit {
        background-color: #3b82f6;
        color: white;
    }

    .btn-edit:hover {
        background-color: #2563eb;
    }

    .btn-delete {
        background-color: #ef4444;
        color: white;
    }

    .btn-delete:hover {
        background-color: #dc2626;
    }

    .btn-view {
        background-color: #10b981;
        color: white;
    }

    .btn-view:hover {
        background-color: #059669;
    }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@8.2.0/ol.css">
@endsection

@section('content')
<div class="geofencing-header">
    <h1 class="geofencing-title">Geofencing - Zone Management</h1>
    <p class="geofencing-subtitle">Create and manage geofence zones for operational area monitoring</p>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-md-3">
        <div class="stats-card total">
            <div class="stats-number">{{ $stats['total_zones'] }}</div>
            <div class="stats-label">Total Zones</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card active">
            <div class="stats-number">{{ $stats['active_zones'] }}</div>
            <div class="stats-label">Active Zones</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card restricted">
            <div class="stats-number">{{ $stats['restricted_zones'] }}</div>
            <div class="stats-label">Restricted Zones</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card area">
            <div class="stats-number">{{ $stats['total_area'] }}</div>
            <div class="stats-label">Total Area</div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="row">
    <!-- Zones List -->
    <div class="col-md-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Geofence Zones</h5>
                <button class="btn btn-sm btn-primary" onclick="openCreateZoneModal()">
                    <i class="material-icons-outlined">add</i> Create Zone
                </button>
            </div>
            <div class="card-body">
                <div id="zonesList">
                    @foreach($geofenceZones as $zone)
                    <div class="zone-card" 
                         data-zone-id="{{ $zone['id'] }}"
                         data-zone-type="{{ $zone['type'] }}"
                         data-zone-status="{{ $zone['status'] }}"
                         onclick="selectZone('{{ $zone['id'] }}')">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $zone['name'] }}</h6>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="zone-type-badge zone-type-{{ $zone['type'] }}">
                                        {{ $zone['type'] }}
                                    </span>
                                    <span class="status-badge status-{{ $zone['status'] }}">
                                        {{ $zone['status'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p class="text-muted mb-2" style="font-size: 13px;">{{ $zone['description'] }}</p>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <small class="text-muted">Area: <strong>{{ $zone['area'] }}</strong></small>
                            </div>
                            <div>
                                <small class="text-muted">Updated: {{ $zone['updated_at'] }}</small>
                            </div>
                        </div>
                        <div class="zone-actions">
                            <button class="btn-action btn-view" onclick="viewZoneOnMap('{{ $zone['id'] }}'); event.stopPropagation();">
                                <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">map</i>
                                View on Map
                            </button>
                            <button class="btn-action btn-edit" onclick="editZone('{{ $zone['id'] }}'); event.stopPropagation();">
                                <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">edit</i>
                                Edit
                            </button>
                            <button class="btn-action btn-delete" onclick="deleteZone('{{ $zone['id'] }}'); event.stopPropagation();">
                                <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">delete</i>
                                Delete
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Map -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Zone Map</h5>
            </div>
            <div class="card-body p-0">
                <div id="geofenceMap" class="map-container"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/ol@8.2.0/dist/ol.js"></script>
<script>
    // Geofence zones data
    const geofenceZones = @json($geofenceZones);
    const cctvLocations = @json($cctvLocations);

    // Create map
    const map = new ol.Map({
        target: 'geofenceMap',
        layers: [
            new ol.layer.Tile({
                source: new ol.source.XYZ({
                    url: 'http://mt0.google.com/vt/lyrs=s&hl=en&x={x}&y={y}&z={z}',
                    attributions: '© Google',
                    maxZoom: 20
                })
            })
        ],
        view: new ol.View({
            center: ol.proj.fromLonLat([117.4539035, -2.186253]),
            zoom: 13
        })
    });

    // Create vector layer for zones
    const zonesLayer = new ol.layer.Vector({
        source: new ol.source.Vector(),
        style: function(feature) {
            const zoneType = feature.get('zoneType');
            const status = feature.get('status');
            
            let fillColor = 'rgba(239, 68, 68, 0.3)'; // default red
            let strokeColor = '#ef4444';
            
            if (zoneType === 'restricted') {
                fillColor = 'rgba(220, 38, 38, 0.3)';
                strokeColor = '#dc2626';
            } else if (zoneType === 'storage') {
                fillColor = 'rgba(59, 130, 246, 0.3)';
                strokeColor = '#3b82f6';
            } else if (zoneType === 'safety') {
                fillColor = 'rgba(16, 185, 129, 0.3)';
                strokeColor = '#10b981';
            } else if (zoneType === 'exclusion') {
                fillColor = 'rgba(245, 158, 11, 0.3)';
                strokeColor = '#f59e0b';
            }
            
            if (status === 'inactive') {
                fillColor = 'rgba(107, 114, 128, 0.2)';
                strokeColor = '#6b7280';
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
    });
    map.addLayer(zonesLayer);

    // Add CCTV markers
    const cctvLayer = new ol.layer.Vector({
        source: new ol.source.Vector(),
        style: new ol.style.Style({
            image: new ol.style.Circle({
                radius: 6,
                fill: new ol.style.Fill({ color: '#3b82f6' }),
                stroke: new ol.style.Stroke({
                    color: '#ffffff',
                    width: 2
                })
            })
        })
    });
    map.addLayer(cctvLayer);

    cctvLocations.forEach(function(cctv) {
        const feature = new ol.Feature({
            geometry: new ol.geom.Point(
                ol.proj.fromLonLat(cctv.location)
            ),
            name: cctv.name,
            type: 'cctv'
        });
        cctvLayer.getSource().addFeature(feature);
    });

    // Add zone polygons to map
    geofenceZones.forEach(function(zone) {
        const coordinates = zone.coordinates.map(function(coord) {
            return ol.proj.fromLonLat([coord.lng, coord.lat]);
        });
        
        const polygon = new ol.geom.Polygon([coordinates]);
        const feature = new ol.Feature({
            geometry: polygon,
            zoneId: zone.id,
            zoneName: zone.name,
            zoneType: zone.type,
            status: zone.status,
            data: zone
        });
        zonesLayer.getSource().addFeature(feature);
    });

    // Popup overlay
    const popupElement = document.createElement('div');
    popupElement.className = 'ol-popup';
    popupElement.id = 'popup';
    
    const popupOverlay = new ol.Overlay({
        element: popupElement,
        autoPan: true,
        autoPanAnimation: {
            duration: 250
        }
    });
    map.addOverlay(popupOverlay);

    // Click handler
    map.on('singleclick', function(evt) {
        const feature = map.forEachFeatureAtPixel(evt.pixel, function(feature) {
            return feature;
        });

        if (feature && feature.get('zoneId')) {
            const zone = feature.get('data');
            showZonePopup(evt.coordinate, zone);
        } else {
            popupOverlay.setPosition(undefined);
        }
    });

    function showZonePopup(coordinate, zone) {
        const content = `
            <div style="min-width: 200px;">
                <h6 style="margin: 0 0 10px 0;">${zone.name}</h6>
                <p style="margin: 5px 0; font-size: 13px;">${zone.description}</p>
                <p style="margin: 5px 0; font-size: 12px; color: #666;">
                    <strong>Type:</strong> ${zone.type}<br>
                    <strong>Status:</strong> ${zone.status}<br>
                    <strong>Area:</strong> ${zone.area}
                </p>
            </div>
        `;
        popupElement.innerHTML = content;
        popupOverlay.setPosition(coordinate);
    }

    function selectZone(zoneId) {
        // Highlight selected zone
        const zoneCard = document.querySelector(`[data-zone-id="${zoneId}"]`);
        document.querySelectorAll('.zone-card').forEach(card => {
            card.classList.remove('border-primary');
        });
        zoneCard.classList.add('border-primary');
        
        // Center map on zone
        const zone = geofenceZones.find(z => z.id === zoneId);
        if (zone) {
            map.getView().setCenter(ol.proj.fromLonLat([zone.center.lng, zone.center.lat]));
            map.getView().setZoom(15);
        }
    }

    function viewZoneOnMap(zoneId) {
        selectZone(zoneId);
    }

    function editZone(zoneId) {
        alert('Edit zone: ' + zoneId);
        // Open edit modal
    }

    function deleteZone(zoneId) {
        if (confirm('Are you sure you want to delete this zone?')) {
            fetch(`{{ route('geofencing.zones.delete', '') }}/${zoneId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Zone deleted successfully!');
                    location.reload();
                } else {
                    alert('Failed to delete zone');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting zone');
            });
        }
    }

    function openCreateZoneModal() {
        alert('Open create zone modal');
        // Open modal to create new zone
    }
</script>
@endsection

