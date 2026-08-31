@extends('isc.maps.master')

@section('title', 'Peta Boundary')

@section('content')
<section class="gm-shell is-panel-closed">
  <div
    id="map"
    role="application"
    aria-label="Peta boundary wilayah operasi Berau"
    data-boundaries-url="{{ $boundariesUrl }}"
    data-overlay-url="{{ $overlayUrl }}"
    data-connected="{{ $connected ? '1' : '0' }}"
  ></div>
  <div id="map-loading" class="gm-loading">Memuat peta…</div>

  <aside class="gm-rail" aria-label="Menu peta">
    <button type="button" class="gm-rail-btn" id="gm-rail-toggle" aria-label="Buka panel" aria-expanded="false">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      <span>Menu</span>
    </button>
    <a class="gm-rail-btn" href="{{ url('/') }}">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10.5 12 4l8 6.5V20H4z"/><path d="M9 20v-6h6v6"/></svg>
      <span>Beranda</span>
    </a>
    <button type="button" class="gm-rail-btn" id="gm-saved-btn">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
      <span>Disimpan</span>
    </button>
    <button type="button" class="gm-rail-btn" id="gm-recents-btn">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>
      <span>Terbaru</span>
    </button>
    <span class="gm-rail-gap"></span>
    <button type="button" class="gm-rail-thumb bmo" data-jump="BMO" title="Binungan">BMO</button>
    <button type="button" class="gm-rail-thumb lmo" data-jump="LMO" title="Lati">LMO</button>
    <button type="button" class="gm-rail-thumb gmo" data-jump="GMO" title="Gurimbang">GMO</button>
    <button type="button" class="gm-rail-thumb smo" data-jump="SMO" title="Sambarata">SMO</button>
    <button type="button" class="gm-rail-thumb punan" data-jump="PUNAN" title="Punan">PUN</button>
  </aside>

  <div class="gm-left">
    <form class="gm-search" id="gm-search-form" role="search" autocomplete="off">
      <button type="submit" class="gm-icon-btn" aria-label="Cari">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
      </button>
      <input
        id="gm-search-input"
        type="search"
        placeholder="Cari zona, site, atau boundary"
        aria-label="Cari peta"
      >
      <button type="button" class="gm-icon-btn" id="gm-search-clear" hidden aria-label="Hapus pencarian">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
      <span class="gm-search-split" aria-hidden="true"></span>
      <button type="button" class="gm-dir-btn" id="zoom-fit" title="Pusatkan wilayah operasi" aria-label="Pusatkan wilayah operasi">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.71 11.29l-9-9a1 1 0 0 0-1.41 0l-9 9a1 1 0 0 0 0 1.41l9 9a1 1 0 0 0 1.41 0l9-9a1 1 0 0 0 0-1.41ZM14 14.5V12h-4v3H8v-4a1 1 0 0 1 1-1h5V7.5l3.5 3.5-3.5 3.5Z"/></svg>
      </button>
    </form>

    <aside class="gm-panel is-closed" id="gm-panel" aria-label="Hasil peta">
      <div class="gm-results" id="gm-results">
        <div class="gm-results-head">
          <div>
            <p class="gm-kicker">Wilayah operasi</p>
            <p id="gm-status">{{ $connected ? 'Besigma terhubung' : 'IUPK tampil · Besigma belum terhubung' }}</p>
          </div>
          <strong id="gm-count">0</strong>
        </div>
        <div class="gm-results-list" id="zone-list"></div>
      </div>

      <article class="gm-place" id="gm-place" hidden>
        <button type="button" class="gm-place-back" id="gm-place-back" aria-label="Kembali ke daftar">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5 8 12l7 7"/></svg>
        </button>
        <div class="gm-place-hero" id="gm-place-hero" data-site="IUPK">IUPK</div>
        <div class="gm-place-body">
          <h1 id="gm-place-title">Zona</h1>
          <p class="gm-place-sub" id="gm-place-sub">Konsesi</p>
          <div class="gm-place-tabs" role="tablist">
            <button type="button" class="is-on" data-tab="overview">Ringkasan</button>
            <button type="button" data-tab="about">Data</button>
          </div>
          <div class="gm-actions" aria-label="Aksi lokasi">
            <button type="button" class="gm-action" id="gm-act-zoom">
              <span class="gm-action-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.2"/></svg>
              </span>
              Zoom
            </button>
            <button type="button" class="gm-action" id="gm-act-save">
              <span class="gm-action-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
              </span>
              <span id="gm-save-label">Simpan</span>
            </button>
            <button type="button" class="gm-action" id="gm-act-nearby">
              <span class="gm-action-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 4v2M12 18v2M4 12h2M18 12h2"/></svg>
              </span>
              Sekitar
            </button>
            <button type="button" class="gm-action" id="gm-act-share">
              <span class="gm-action-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="M8.2 11.1 15.8 6.4M8.2 12.9l7.6 5.7"/></svg>
              </span>
              Bagikan
            </button>
          </div>
          <ul class="gm-facts" id="gm-place-facts"></ul>
          <pre class="gm-place-data" id="gm-place-data" hidden></pre>
        </div>
      </article>
    </aside>
  </div>

  <nav class="gm-pills" aria-label="Filter peta">
    <button type="button" class="gm-pill is-on" data-scope="semua">Semua</button>
    <button type="button" class="gm-pill" data-scope="iupk">
      <span class="gm-dot iupk"></span>
      Konsesi
    </button>
    <button type="button" class="gm-pill" data-scope="besigma">
      <span class="gm-dot besigma"></span>
      Besigma
    </button>
    <button type="button" class="gm-pill" data-jump="BMO"><span class="gm-dot bmo"></span>Binungan</button>
    <button type="button" class="gm-pill" data-jump="LMO"><span class="gm-dot lmo"></span>Lati</button>
    <button type="button" class="gm-pill" data-jump="GMO"><span class="gm-dot gmo"></span>Gurimbang</button>
    <button type="button" class="gm-pill" data-jump="SMO"><span class="gm-dot smo"></span>Sambarata</button>
    <button type="button" class="gm-pill" data-jump="PUNAN"><span class="gm-dot punan"></span>Punan</button>
    <button type="button" class="gm-pill gm-pill-refresh" id="btn-refresh" title="Muat ulang Besigma">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.2-5.5"/><path d="M20 5v5h-5"/></svg>
      Muat
    </button>
  </nav>

  <div class="gm-layers">
    <button type="button" class="gm-layers-btn" id="gm-layers-btn" aria-expanded="false" aria-controls="gm-layers-pop">
      <span class="gm-layers-thumb" id="gm-layers-thumb" data-kind="sat"></span>
      <span>Layers</span>
    </button>
    <div class="gm-layers-pop" id="gm-layers-pop" hidden>
      <p>Jenis peta</p>
      <div class="gm-layer-cards">
        <button type="button" class="is-on" data-basemap="sat"><i data-kind="sat"></i>Satelit</button>
        <button type="button" data-basemap="map"><i data-kind="map"></i>Peta</button>
        <button type="button" data-basemap="dark"><i data-kind="dark"></i>Gelap</button>
      </div>
      <p>Overlay</p>
      <label><input type="checkbox" data-layer="ops" checked> Konsesi IUPK</label>
      <label><input type="checkbox" data-layer="besigma" checked> Besigma</label>
      <label><input type="checkbox" id="gm-toggle-labels" checked> Label site</label>
    </div>
  </div>

  <div class="gm-zoom" role="group" aria-label="Zoom peta">
    <button type="button" id="zoom-in" aria-label="Perbesar">+</button>
    <button type="button" id="zoom-out" aria-label="Perkecil">−</button>
  </div>
  <button type="button" class="gm-locate" id="zoom-home" aria-label="Lihat semua konsesi">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v2.2M12 17.8V20M4 12h2.2M17.8 12H20"/><circle cx="12" cy="12" r="3.2"/></svg>
  </button>

  <div class="gm-toast" id="gm-toast" hidden></div>
</section>
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ $iupkAsset }}"></script>
<script src="{{ asset('isc-assets/isc-hotspot-map.js') }}"></script>
@endsection
