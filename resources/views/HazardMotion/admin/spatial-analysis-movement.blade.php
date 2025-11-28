@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Spatial Analysis - Movement Patterns - Beraucoal')

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

    .movement-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
        background: white;
    }

    .movement-card:hover {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .map-container {
        width: 100%;
        height: 400px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        margin-top: 16px;
    }

    .entity-type-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .entity-personnel {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .entity-equipment {
        background-color: #fef3c7;
        color: #92400e;
    }

    .movement-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-top: 16px;
    }

    .info-item {
        padding: 12px;
        background: #f9fafb;
        border-radius: 6px;
    }

    .info-label {
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 4px;
    }

    .info-value {
        font-size: 16px;
        font-weight: 600;
        color: #111827;
    }

    .zones-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }

    .zone-badge {
        display: inline-block;
        padding: 4px 12px;
        background-color: #eff6ff;
        color: #1e40af;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@8.2.0/ol.css">
@endsection

@section('content')
<div class="analysis-header">
    <h1 class="analysis-title">Spatial Analysis - Movement Patterns</h1>
    <p class="analysis-subtitle">Track and analyze movement patterns of personnel and equipment</p>
</div>

@foreach($movementPatterns as $pattern)
<div class="movement-card">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h5 class="mb-1">{{ $pattern['entity_name'] }}</h5>
            <div class="d-flex align-items-center gap-2">
                <span class="entity-type-badge entity-{{ $pattern['entity_type'] }}">
                    {{ $pattern['entity_type'] }}
                </span>
                <span class="text-muted" style="font-size: 13px;">ID: {{ $pattern['entity_id'] }}</span>
            </div>
        </div>
    </div>

    <div class="movement-info">
        <div class="info-item">
            <div class="info-label">Total Distance</div>
            <div class="info-value">{{ $pattern['total_distance'] }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Duration</div>
            <div class="info-value">{{ $pattern['duration'] }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Zones Visited</div>
            <div class="info-value">{{ count($pattern['zones_visited']) }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Path Points</div>
            <div class="info-value">{{ count($pattern['movement_path']) }}</div>
        </div>
    </div>

    <div class="mt-3">
        <strong>Zones Visited:</strong>
        <div class="zones-list">
            @foreach($pattern['zones_visited'] as $zone)
            <span class="zone-badge">{{ $zone }}</span>
            @endforeach
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h6 class="mb-0">Movement Path</h6>
        </div>
        <div class="card-body p-0">
            <div id="movementMap{{ $pattern['entity_id'] }}" class="map-container"></div>
        </div>
    </div>
</div>
@endforeach
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/ol@8.2.0/dist/ol.js"></script>
<script>
    const movementPatterns = @json($movementPatterns);

    movementPatterns.forEach(function(pattern) {
        const mapId = 'movementMap' + pattern.entity_id.replace(/[^a-zA-Z0-9]/g, '');
        const mapElement = document.getElementById(mapId);
        
        if (mapElement) {
            const map = new ol.Map({
                target: mapId,
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
                    center: ol.proj.fromLonLat([pattern.movement_path[0].lng, pattern.movement_path[0].lat]),
                    zoom: 14
                })
            });

            // Create path line
            const pathCoordinates = pattern.movement_path.map(function(point) {
                return ol.proj.fromLonLat([point.lng, point.lat]);
            });

            const pathFeature = new ol.Feature({
                geometry: new ol.geom.LineString(pathCoordinates)
            });

            const pathLayer = new ol.layer.Vector({
                source: new ol.source.Vector({
                    features: [pathFeature]
                }),
                style: new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: '#3b82f6',
                        width: 3
                    })
                })
            });
            map.addLayer(pathLayer);

            // Add start point
            const startPoint = new ol.Feature({
                geometry: new ol.geom.Point(pathCoordinates[0])
            });
            startPoint.setStyle(new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 8,
                    fill: new ol.style.Fill({ color: '#10b981' }),
                    stroke: new ol.style.Stroke({
                        color: '#ffffff',
                        width: 2
                    })
                })
            }));

            // Add end point
            const endPoint = new ol.Feature({
                geometry: new ol.geom.Point(pathCoordinates[pathCoordinates.length - 1])
            });
            endPoint.setStyle(new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 8,
                    fill: new ol.style.Fill({ color: '#ef4444' }),
                    stroke: new ol.style.Stroke({
                        color: '#ffffff',
                        width: 2
                    })
                })
            }));

            // Add intermediate points
            const pointsLayer = new ol.layer.Vector({
                source: new ol.source.Vector({
                    features: [startPoint, endPoint]
                })
            });
            map.addLayer(pointsLayer);

            // Fit view to path
            const extent = pathFeature.getGeometry().getExtent();
            map.getView().fit(extent, {
                padding: [50, 50, 50, 50],
                maxZoom: 16
            });
        }
    });
</script>
@endsection

