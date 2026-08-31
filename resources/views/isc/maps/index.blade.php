@extends('isc.maps.master')

@section('title', 'Peta')

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endsection

@section('content')
<div id="isc-map" class="isc-map-full"></div>
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ $iupkAsset }}"></script>
<script>
(function () {
    var boundariesUrl = @json($boundariesUrl);
    var iupkColors = {
        'BMO 1': '#2563eb',
        'BMO 2': '#7c3aed',
        'BMO 3': '#db2777',
        'LMO': '#059669',
        'GMO': '#d97706',
        'SMO': '#0891b2',
        'PUNAN': '#4b5563'
    };

    var map = L.map('isc-map', {
        attributionControl: false,
        zoomControl: true
    }).setView([2.02, 117.35], 11);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    function fit() {
        map.invalidateSize();
    }
    setTimeout(fit, 50);
    window.addEventListener('resize', fit);

    var iupkLayer = L.geoJSON(window.IUPK_BOUNDARY || { type: 'FeatureCollection', features: [] }, {
        style: function (feature) {
            var layer = (feature.properties && feature.properties.Layer) || '';
            return { color: iupkColors[layer] || '#fb923c', weight: 2, fillOpacity: 0.12 };
        }
    }).addTo(map);

    var besigmaLayer = L.geoJSON({ type: 'FeatureCollection', features: [] }, {
        style: function (feature) {
            var p = feature.properties || {};
            return { color: p.risk_color || p.status_color || '#4ade80', weight: 2, fillOpacity: 0.25 };
        },
        pointToLayer: function (feature, latlng) {
            return L.circleMarker(latlng, { radius: 7, color: '#4ade80', fillOpacity: 0.8 });
        }
    }).addTo(map);

    if (iupkLayer.getLayers().length) {
        map.fitBounds(iupkLayer.getBounds(), { padding: [8, 8] });
    }

    fetch(boundariesUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (geojson) {
            besigmaLayer.clearLayers();
            if (geojson && geojson.features) {
                besigmaLayer.addData(geojson);
            }
            var group = L.featureGroup();
            group.addLayer(iupkLayer);
            if (besigmaLayer.getLayers().length) {
                group.addLayer(besigmaLayer);
            }
            if (group.getLayers().length) {
                map.fitBounds(group.getBounds(), { padding: [8, 8] });
            }
            fit();
        })
        .catch(function () {});
})();
</script>
@endsection
