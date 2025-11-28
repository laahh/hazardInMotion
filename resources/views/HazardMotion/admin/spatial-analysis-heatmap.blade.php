@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Spatial Analysis - Heat Map - Beraucoal')

@section('css')
<style>
    .analysis-header {
        margin-bottom: 24px;
    }

    .analysis-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .analysis-subtitle {
        font-size: 14px;
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

    .heatmap-controls {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .heatmap-legend {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-top: 20px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
    }

    .legend-color {
        width: 30px;
        height: 20px;
        border-radius: 4px;
        margin-right: 12px;
    }

    .intensity-high {
        background: linear-gradient(to right, #dc2626, #f59e0b);
    }

    .intensity-medium {
        background: linear-gradient(to right, #f59e0b, #3b82f6);
    }

    .intensity-low {
        background: linear-gradient(to right, #3b82f6, #10b981);
    }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@8.2.0/ol.css">
@endsection

@section('content')
<div class="analysis-header">
    <h1 class="analysis-title">Spatial Analysis - Heat Map</h1>
    <p class="analysis-subtitle">Visualize spatial distribution of violations, alerts, and incidents</p>
</div>

<!-- Controls -->
<div class="heatmap-controls">
    <div class="row">
        <div class="col-md-4">
            <label for="dataTypeFilter" class="form-label">Data Type</label>
            <select id="dataTypeFilter" class="form-select">
                <option value="all">All Types</option>
                <option value="violation">Violations</option>
                <option value="alert">Alerts</option>
                <option value="incident">Incidents</option>
            </select>
        </div>
        <div class="col-md-4">
            <label for="timeRangeFilter" class="form-label">Time Range</label>
            <select id="timeRangeFilter" class="form-select">
                <option value="24h">Last 24 Hours</option>
                <option value="7d">Last 7 Days</option>
                <option value="30d">Last 30 Days</option>
                <option value="all">All Time</option>
            </select>
        </div>
        <div class="col-md-4">
            <label for="intensityFilter" class="form-label">Intensity Threshold</label>
            <select id="intensityFilter" class="form-select">
                <option value="all">All Intensities</option>
                <option value="high">High (70-100)</option>
                <option value="medium">Medium (40-70)</option>
                <option value="low">Low (0-40)</option>
            </select>
        </div>
    </div>
</div>

<!-- Map -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Heat Map Visualization</h5>
    </div>
    <div class="card-body p-0">
        <div id="heatMap" class="map-container"></div>
    </div>
</div>

<!-- Legend -->
<div class="heatmap-legend">
    <h6 class="mb-3">Heat Map Legend</h6>
    <div class="legend-item">
        <div class="legend-color intensity-high"></div>
        <span>High Intensity (70-100)</span>
    </div>
    <div class="legend-item">
        <div class="legend-color intensity-medium"></div>
        <span>Medium Intensity (40-70)</span>
    </div>
    <div class="legend-item">
        <div class="legend-color intensity-low"></div>
        <span>Low Intensity (0-40)</span>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/ol@8.2.0/dist/ol.js"></script>
<script>
    const heatMapData = @json($heatMapData);
    const cctvLocations = @json($cctvLocations);

    // Create map
    const map = new ol.Map({
        target: 'heatMap',
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

    // Create heat map layer
    const heatMapLayer = new ol.layer.Vector({
        source: new ol.source.Vector(),
        style: function(feature) {
            const intensity = feature.get('intensity');
            const type = feature.get('type');
            
            let color = '#ef4444';
            let radius = 15;
            
            if (intensity >= 70) {
                color = '#dc2626';
                radius = 20;
            } else if (intensity >= 40) {
                color = '#f59e0b';
                radius = 15;
            } else {
                color = '#3b82f6';
                radius = 10;
            }
            
            return new ol.style.Style({
                image: new ol.style.Circle({
                    radius: radius,
                    fill: new ol.style.Fill({
                        color: color + '80' // 80 = 50% opacity
                    }),
                    stroke: new ol.style.Stroke({
                        color: color,
                        width: 2
                    })
                })
            });
        }
    });
    map.addLayer(heatMapLayer);

    // Add heat map points
    heatMapData.forEach(function(point) {
        const feature = new ol.Feature({
            geometry: new ol.geom.Point(
                ol.proj.fromLonLat([point.lng, point.lat])
            ),
            intensity: point.intensity,
            type: point.type,
            data: point
        });
        heatMapLayer.getSource().addFeature(feature);
    });

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

    // Popup overlay
    const popupElement = document.createElement('div');
    popupElement.className = 'ol-popup';
    popupElement.id = 'popup';
    
    const popupOverlay = new ol.Overlay({
        element: popupElement,
        autoPan: true
    });
    map.addOverlay(popupOverlay);

    // Click handler
    map.on('singleclick', function(evt) {
        const feature = map.forEachFeatureAtPixel(evt.pixel, function(feature) {
            return feature;
        });

        if (feature && feature.get('intensity')) {
            const data = feature.get('data');
            showHeatMapPopup(evt.coordinate, data);
        } else {
            popupOverlay.setPosition(undefined);
        }
    });

    function showHeatMapPopup(coordinate, data) {
        const content = `
            <div style="min-width: 200px;">
                <h6 style="margin: 0 0 10px 0;">Heat Map Point</h6>
                <p style="margin: 5px 0; font-size: 13px;">
                    <strong>Intensity:</strong> ${data.intensity}/100<br>
                    <strong>Type:</strong> ${data.type}<br>
                    <strong>Location:</strong> ${data.lat}, ${data.lng}
                </p>
            </div>
        `;
        popupElement.innerHTML = content;
        popupOverlay.setPosition(coordinate);
    }

    // Filter functionality
    document.getElementById('dataTypeFilter').addEventListener('change', filterHeatMap);
    document.getElementById('timeRangeFilter').addEventListener('change', filterHeatMap);
    document.getElementById('intensityFilter').addEventListener('change', filterHeatMap);

    function filterHeatMap() {
        const typeFilter = document.getElementById('dataTypeFilter').value;
        const intensityFilter = document.getElementById('intensityFilter').value;
        
        // Reload heat map data
        fetch('{{ route("spatial-analysis.api.heatmap") }}?type=' + typeFilter)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear existing features
                    heatMapLayer.getSource().clear();
                    
                    // Add filtered features
                    data.data.forEach(function(point) {
                        let show = true;
                        if (intensityFilter === 'high' && point.intensity < 70) show = false;
                        if (intensityFilter === 'medium' && (point.intensity < 40 || point.intensity >= 70)) show = false;
                        if (intensityFilter === 'low' && point.intensity >= 40) show = false;
                        
                        if (show) {
                            const feature = new ol.Feature({
                                geometry: new ol.geom.Point(
                                    ol.proj.fromLonLat([point.lng, point.lat])
                                ),
                                intensity: point.intensity,
                                type: point.type,
                                data: point
                            });
                            heatMapLayer.getSource().addFeature(feature);
                        }
                    });
                }
            });
    }
</script>
@endsection

