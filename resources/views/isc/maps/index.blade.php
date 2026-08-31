@extends('isc.maps.master')

@section('title', 'Peta Boundary')

@section('content')
<section class="map-hero">
  <div
    id="map"
    role="application"
    aria-label="Peta boundary wilayah operasi Berau"
    data-boundaries-url="{{ $boundariesUrl }}"
    data-overlay-url="{{ $overlayUrl }}"
    data-connected="{{ $connected ? '1' : '0' }}"
  ></div>
  <div class="map-vignette" aria-hidden="true"></div>
  <div id="map-loading" class="map-loading">Memuat peta boundary…</div>

  <header class="topbar">
    <a class="brand" href="{{ route('isc.maps.index') }}" aria-label="Berau Coal OHS">
      <span>BERAU COAL</span><i>·</i><strong>OHS </strong>
    </a>
    <nav class="desktop-nav" aria-label="Navigasi utama">
      <a href="{{ url('/') }}">Beranda</a>
      <a class="active" href="{{ route('isc.maps.index') }}" aria-current="page">Hotspot</a>
    </nav>
    <details class="mobile-menu">
      <summary aria-label="Buka menu">
        <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </summary>
      <nav>
        <a href="{{ url('/') }}">Beranda</a>
        <a class="active" href="{{ route('isc.maps.index') }}">Hotspot</a>
      </nav>
    </details>
  </header>

  <aside class="glass-panel list-card" aria-label="Boundary ISC">
    <div class="chip-row">
      <span class="alert-chip">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.2 21.5 20H2.5L12 3.2Z"/><path d="M12 10v4.2"/><path d="M12 17.2v.6"/></svg>
        SIAGA ISC
      </span>
      <span class="ghost-chip">KAB. BERAU</span>
    </div>
    <p class="eyebrow">Command center · boundary</p>
    <h1>Peta &amp; zona.</h1>
    <p class="lead" id="data-source">Overlay IUPK BounderyBC + polygon Besigma.</p>

    <div class="day-summary" aria-label="Ringkasan layer">
      <article>
        <small>IUPK</small>
        <strong id="sum-iupk">0</strong>
      </article>
      <article>
        <small>Besigma</small>
        <strong id="sum-besigma">0</strong>
      </article>
      <article>
        <small>Violations</small>
        <strong id="sum-violations">0</strong>
      </article>
      <article>
        <small>Entries</small>
        <strong id="sum-entries">0</strong>
      </article>
    </div>

    <div class="scope-tabs" role="group" aria-label="Filter layer">
      <button type="button" class="is-on" data-scope="semua">Semua</button>
      <button type="button" data-scope="iupk">IUPK</button>
      <button type="button" data-scope="besigma">Besigma</button>
    </div>

    <div class="list-meta">
      <strong id="list-count">0</strong>
      <span id="list-label">zona tampil</span>
      <button type="button" id="btn-refresh" class="refresh-btn">Muat Besigma</button>
    </div>
    <div class="hotspot-list" id="zone-list"></div>
  </aside>

  <div class="hud-right">
    <div class="map-toolbar" aria-label="Kontrol peta">
      <div class="layer-switch" role="group" aria-label="Layer peta">
        <button type="button" data-basemap="sat" class="is-on">Satelit</button>
        <button type="button" data-basemap="dark">Gelap</button>
        <button type="button" data-layer="ops" class="is-on">Konsesi</button>
      </div>
      <div class="layer-switch" role="group" aria-label="Layer data">
        <button type="button" data-layer="besigma" class="is-on">Besigma</button>
      </div>
      <div class="zoom-switch" role="group" aria-label="Zoom peta">
        <button type="button" id="zoom-in" aria-label="Perbesar">+</button>
        <button type="button" id="zoom-out" aria-label="Perkecil">−</button>
        <button type="button" id="zoom-fit" aria-label="Pusatkan wilayah operasi">⌖</button>
      </div>
    </div>

    <aside class="glass-panel detail-card-wrap" id="map-detail" aria-label="Status peta">
      <div class="detail-empty" id="detail-empty">
        <span class="pulse"></span>
        <div>
          <small>STATUS LAYER</small>
          <p id="live-status">{{ $connected ? 'Besigma terhubung.' : 'Besigma belum terhubung — IUPK tetap tampil.' }}</p>
        </div>
      </div>
      <article class="detail-card" id="detail-card" hidden></article>
    </aside>
  </div>

  <div class="metric-panel map-legend-bar">
    <article><i class="dot rendah"></i><div><small>Konsesi</small><p>IUPK BounderyBC</p></div></article>
    <article><i class="dot internal"></i><div><small>Besigma</small><p>Polygon database</p></div></article>
    <article><i class="dot tinggi"></i><div><small>Violation</small><p>Pelanggaran zona</p></div></article>
    <article><i class="dot sedang"></i><div><small>Entry</small><p>Masuk boundary</p></div></article>
    <article><i class="dot patroli"></i><div><small>BMO</small><p>Binungan</p></div></article>
    <article><i class="dot kasus"></i><div><small>SMO / GMO</small><p>Sambarata / Gurimbang</p></div></article>
    <article><i class="dot route"></i><div><small>LMO</small><p>Lati</p></div></article>
  </div>
</section>
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ $iupkAsset }}"></script>
<script src="{{ asset('isc-assets/isc-hotspot-map.js') }}"></script>
@endsection
