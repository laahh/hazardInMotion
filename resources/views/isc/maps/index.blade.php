@extends('isc.maps.master')

@section('title', 'Peta Boundary')

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endsection

@section('content')
@php
    $connected = (bool) ($connected ?? false);
@endphp

<div class="isc-hero-copy">
  <div class="isc-alert-chip">ISC · BOUNDARY COMMAND</div>
  <p class="isc-eyebrow">INTEGRATED SPATIAL CONTROL</p>
  <h1>Peta boundary IUPK<br>dan zona Besigma.</h1>
  <p class="isc-lead">Overlay wilayah operasi dari BounderyBC beserta polygon, pelanggaran, dan entri dari database Besigma — tanpa sidebar admin.</p>
</div>

@unless ($connected)
<div class="isc-banner" role="status">
  Koneksi Besigma tidak tersedia. Layer IUPK tetap tampil. Aktifkan tunnel jumphost (<code>127.0.0.1:3307</code>) lalu refresh.
</div>
@endunless

<div class="isc-grid">
  <section class="isc-panel">
    <header class="isc-panel-head">
      <h2>Peta boundary</h2>
      <div class="isc-chip-row">
        <button type="button" id="isc-toggle-iupk" class="isc-chip active">IUPK (BounderyBC)</button>
        <button type="button" id="isc-toggle-besigma" class="isc-chip active">Besigma</button>
      </div>
    </header>
    <div id="isc-map" class="isc-map"></div>
  </section>

  <aside>
    <section class="isc-panel" style="margin-bottom:16px">
      <header><h3>Tabel Besigma</h3></header>
      <table class="isc-table-status">
        <tbody>
        @foreach (($tables ?? []) as $name => $meta)
          <tr>
            <td><code>{{ $name }}</code></td>
            <td style="text-align:right">
              @if ($meta['exists'] ?? false)
                <span class="ok">{{ $meta['row_count'] ?? 0 }}</span>
              @else
                <span class="off">—</span>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </section>
    <section class="isc-panel">
      <header><h3>Legenda IUPK</h3></header>
      <div class="isc-legend">
        <div><span class="legend-dot" style="background:#2563eb"></span> BMO 1</div>
        <div><span class="legend-dot" style="background:#7c3aed"></span> BMO 2</div>
        <div><span class="legend-dot" style="background:#db2777"></span> BMO 3</div>
        <div><span class="legend-dot" style="background:#059669"></span> LMO</div>
        <div><span class="legend-dot" style="background:#d97706"></span> GMO</div>
        <div><span class="legend-dot" style="background:#0891b2"></span> SMO</div>
        <div><span class="legend-dot" style="background:#4b5563"></span> PUNAN</div>
      </div>
    </section>
  </aside>
</div>

<section class="isc-panel" style="margin-top:16px">
  <div class="isc-tabs" role="tablist">
    <button type="button" class="active" data-isc-tab="violations">Violations</button>
    <button type="button" data-isc-tab="entries">Entries</button>
    <button type="button" data-isc-tab="annotations">Annotations</button>
    <button type="button" data-isc-tab="competencies">Competencies</button>
  </div>
  <div class="isc-table-wrap" id="isc-table-violations"></div>
  <div class="isc-table-wrap" id="isc-table-entries" hidden></div>
  <div class="isc-table-wrap" id="isc-table-annotations" hidden></div>
  <div class="isc-table-wrap" id="isc-table-competencies" hidden></div>
</section>
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ $iupkAsset }}"></script>
<script>
(function () {
    var boundariesUrl = @json($boundariesUrl);
    var overlayUrl = @json($overlayUrl);
    var iupkColors = {
        'BMO 1': '#2563eb',
        'BMO 2': '#7c3aed',
        'BMO 3': '#db2777',
        'LMO': '#059669',
        'GMO': '#d97706',
        'SMO': '#0891b2',
        'PUNAN': '#4b5563'
    };

    var map = L.map('isc-map').setView([2.02, 117.35], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    setTimeout(function () { map.invalidateSize(); }, 80);

    function esc(value) {
        return String(value).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    var iupkLayer = L.geoJSON(window.IUPK_BOUNDARY || { type: 'FeatureCollection', features: [] }, {
        style: function (feature) {
            var layer = (feature.properties && feature.properties.Layer) || '';
            return { color: iupkColors[layer] || '#fb923c', weight: 2, fillOpacity: 0.12 };
        },
        onEachFeature: function (feature, layer) {
            var p = feature.properties || {};
            layer.bindPopup('<strong>' + esc(p.Site || p.Layer || 'IUPK') + '</strong><br>Layer: ' + esc(p.Layer || '-'));
        }
    }).addTo(map);

    var besigmaLayer = L.geoJSON({ type: 'FeatureCollection', features: [] }, {
        style: function (feature) {
            var p = feature.properties || {};
            return { color: p.risk_color || p.status_color || '#4ade80', weight: 2, fillOpacity: 0.25 };
        },
        pointToLayer: function (feature, latlng) {
            return L.circleMarker(latlng, { radius: 7, color: '#4ade80', fillOpacity: 0.8 });
        },
        onEachFeature: function (feature, layer) {
            var p = feature.properties || {};
            var title = p.name || p.title || p.code || ('Boundary #' + (p.id || ''));
            var bits = [esc(title)];
            if (p.status_name) bits.push('Status: ' + esc(p.status_name));
            if (p.risk_name) bits.push('Risk: ' + esc(p.risk_name));
            layer.bindPopup(bits.join('<br>'));
        }
    }).addTo(map);

    if (iupkLayer.getLayers().length) {
        map.fitBounds(iupkLayer.getBounds(), { padding: [24, 24] });
    }

    document.getElementById('isc-toggle-iupk').addEventListener('click', function () {
        toggleLayer(this, iupkLayer);
    });
    document.getElementById('isc-toggle-besigma').addEventListener('click', function () {
        toggleLayer(this, besigmaLayer);
    });

    function toggleLayer(btn, layer) {
        if (map.hasLayer(layer)) {
            map.removeLayer(layer);
            btn.classList.remove('active');
        } else {
            layer.addTo(map);
            btn.classList.add('active');
        }
    }

    document.querySelectorAll('[data-isc-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-isc-tab]').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            ['violations', 'entries', 'annotations', 'competencies'].forEach(function (name) {
                document.getElementById('isc-table-' + name).hidden = name !== btn.getAttribute('data-isc-tab');
            });
        });
    });

    function renderTable(elId, rows) {
        var el = document.getElementById(elId);
        if (!rows || !rows.length) {
            el.innerHTML = '<p style="color:#c4b5a5;margin:8px 4px">Tidak ada data.</p>';
            return;
        }
        var keys = Object.keys(rows[0]).slice(0, 8);
        var html = '<table><thead><tr>';
        keys.forEach(function (k) { html += '<th>' + esc(k) + '</th>'; });
        html += '</tr></thead><tbody>';
        rows.forEach(function (row) {
            html += '<tr>';
            keys.forEach(function (k) {
                var v = row[k];
                html += '<td>' + (v === null || v === undefined ? '' : esc(v)) + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table>';
        el.innerHTML = html;
    }

    fetch(boundariesUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (geojson) {
            besigmaLayer.clearLayers();
            if (geojson && geojson.features) {
                besigmaLayer.addData(geojson);
            }
            var group = L.featureGroup();
            if (map.hasLayer(iupkLayer)) group.addLayer(iupkLayer);
            if (besigmaLayer.getLayers().length) group.addLayer(besigmaLayer);
            if (group.getLayers().length) {
                map.fitBounds(group.getBounds(), { padding: [24, 24] });
            }
        })
        .catch(function () {});

    fetch(overlayUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
            var overlay = (payload && payload.overlay) || {};
            renderTable('isc-table-violations', overlay.violations);
            renderTable('isc-table-entries', overlay.entries);
            renderTable('isc-table-annotations', overlay.annotations);
            renderTable('isc-table-competencies', overlay.competencies);
        })
        .catch(function () {
            renderTable('isc-table-violations', []);
            renderTable('isc-table-entries', []);
            renderTable('isc-table-annotations', []);
            renderTable('isc-table-competencies', []);
        });
})();
</script>
@endsection
