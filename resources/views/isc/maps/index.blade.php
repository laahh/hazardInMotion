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
    data-pob-url="{{ $pobUrl }}"
    data-pob-export-url="{{ $pobExportUrl }}"
    data-post-event-url="{{ $postEventUrl }}"
    data-post-event-trail-url="{{ $postEventTrailUrl }}"
    data-cctv-url="{{ $cctvUrl }}"
    data-maps-interventions-url="{{ $mapsInterventionsUrl }}"
    data-maps-hazard-reports-url="{{ $mapsHazardReportsUrl }}"
    data-maps-hazard-reports-list-url="{{ $mapsHazardReportsListUrl }}"
    data-maps-hazard-reports-historical-url="{{ $mapsHazardReportsHistoricalUrl }}"
    data-maps-hazard-employees-url="{{ $mapsHazardEmployeesUrl }}"
    data-maps-hazard-sysuser-url="{{ $mapsHazardSysUserUrl }}"
    data-maps-hazard-lokasi-url="{{ $mapsHazardLokasiUrl }}"
    data-maps-hazard-detail-lokasi-url="{{ $mapsHazardDetailLokasiUrl }}"
    data-maps-hazard-pja-bc-url="{{ $mapsHazardPjaBcUrl }}"
    data-maps-hazard-pja-mitra-url="{{ $mapsHazardPjaMitraUrl }}"
    data-interventions-url="{{ $interventionsUrl }}"
    data-connected="{{ $connected ? '1' : '0' }}"
    data-wms-url="{{ $wmsUrl }}"
    data-wms-layer="{{ $wmsLayer }}"
    data-wmts-proxy-url="{{ $wmtsProxyUrl }}"
    data-wmts-attribution="Drone Imagery &copy; SGI"
  ></div>
  <div id="map-loading" class="gm-loading">Memuat peta…</div>

  <aside class="gm-rail" aria-label="Menu peta">
    <button type="button" class="gm-rail-btn" id="gm-menu-btn" data-rail="menu" aria-label="Menu peta">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      <span>Menu</span>
    </button>
    <button type="button" class="gm-rail-btn is-on" id="gm-home-btn" data-rail="home" aria-label="Beranda peta">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10.5 12 4l8 6.5V20H4z"/><path d="M9 20v-6h6v6"/></svg>
      <span>Beranda</span>
    </button>
    <button type="button" class="gm-rail-btn" id="gm-postevent-btn" data-rail="postevent">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V7"/><path d="M4 19h16"/><path d="M8 15l3-4 3 3 4-6"/></svg>
      <span>Post-event</span>
    </button>
    <button type="button" class="gm-rail-btn" id="gm-cctv-btn" data-rail="cctv">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h10v10H4z"/><path d="m14 11 6-3v8l-6-3z"/></svg>
      <span>CCTV</span>
    </button>
    <button type="button" class="gm-rail-btn" id="gm-interventions-btn" data-rail="interventions">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18M4 8h16M7 21h10"/></svg>
      <span>Intervensi</span>
    </button>
    <button type="button" class="gm-rail-btn" id="gm-historical-btn" data-rail="historical">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v5l4 2"/></svg>
      <span>Historical</span>
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
        placeholder="Cari zona, site, CCTV, atau boundary"
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

    <div class="gm-hud" id="gm-hud" aria-label="Kartu peta">
      <div class="gm-hud-view is-on is-anim" data-view="home" id="gm-view-home">

      <article class="gm-hud-card is-checkin">
        <div class="gm-hud-card-top">
          <span class="gm-hud-ico checkin" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4z"/><path d="M4 20c1.5-3.2 4.2-5 8-5s6.5 1.8 8 5"/><circle cx="18" cy="7" r="3.2"/><path d="m16.7 7 .9.9 1.7-1.8"/></svg>
          </span>
          <div class="gm-hud-head">
            <p class="gm-hud-kicker">Check-in RFID</p>
            <p class="gm-hud-value"><b id="hud-checkin-total">–</b> <small>orang onsite</small></p>
          </div>
          <svg class="gm-hud-spark" viewBox="0 0 88 32" fill="none" aria-hidden="true">
            <path d="M2 24 C12 24 14 8 24 12 C34 16 36 6 46 10 C56 14 58 20 68 14 C78 8 80 18 86 16" />
          </svg>
        </div>
        <div class="gm-hud-sites" id="hud-site-filters" role="group" aria-label="Filter site">
          <button type="button" class="gm-hud-site is-on" data-hud-site="">Semua</button>
          <button type="button" class="gm-hud-site" data-hud-site="BMO">BMO <b id="hud-site-BMO">0</b></button>
          <button type="button" class="gm-hud-site" data-hud-site="LMO">LMO <b id="hud-site-LMO">0</b></button>
          <button type="button" class="gm-hud-site" data-hud-site="GMO">GMO <b id="hud-site-GMO">0</b></button>
          <button type="button" class="gm-hud-site" data-hud-site="SMO">SMO <b id="hud-site-SMO">0</b></button>
          <button type="button" class="gm-hud-site" data-hud-site="PUNAN">PUNAN <b id="hud-site-PUNAN">0</b></button>
        </div>
        <p class="gm-hud-foot">Per site hari ini <button type="button" class="gm-hud-link" data-roster="checkin">Lihat daftar</button></p>
      </article>

      <article class="gm-hud-card is-safety" id="gm-safety-card" role="button" tabindex="0" aria-label="Buka daftar personel terlacak">
        <div class="gm-hud-card-top">
          <span class="gm-hud-ico safety" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 3 5 6v6c0 4.5 3 7.6 7 9 4-1.4 7-4.5 7-9V6z"/><path d="m9.2 12 2 2 4-4"/></svg>
          </span>
          <div class="gm-hud-head">
            <p class="gm-hud-kicker">Personel terlacak Besigma</p>
            <p class="gm-hud-value"><b id="hud-pob-in">–</b> <small>dalam boundary</small></p>
          </div>
          <svg class="gm-hud-spark" viewBox="0 0 88 32" fill="none" aria-hidden="true">
            <path d="M2 20 C10 20 14 10 22 12 C30 14 34 24 44 18 C54 12 58 8 68 12 C78 16 82 22 86 18" />
          </svg>
        </div>
        <p class="gm-hud-hint">GPS Besigma hari ini <span class="gm-hud-pill"><b id="hud-traced">–</b> orang</span></p>
        <div class="gm-hud-split is-presence">
          <button type="button" class="gm-hud-metric is-tag" data-roster="in">
            <span>Dalam konsesi</span>
            <strong id="hud-pob-in-metric">–</strong>
            <small>GPS di dalam boundary IUPK</small>
          </button>
          <button type="button" class="gm-hud-metric is-ever" data-roster="out">
            <span>Di luar konsesi</span>
            <strong id="hud-pob-out-metric">–</strong>
            <small>GPS di luar boundary IUPK</small>
          </button>
        </div>
        <div class="gm-hud-split">
          <button type="button" class="gm-hud-metric is-safe" data-roster="safe">
            <span>Safe</span>
            <strong id="hud-safe">–</strong>
            <small>Di IUPK, di luar zona bahaya</small>
          </button>
          <button type="button" class="gm-hud-metric is-unsafe" data-roster="unsafe">
            <span>Unsafe</span>
            <strong id="hud-unsafe">–</strong>
            <small>Masuk boundary berbahaya</small>
          </button>
        </div>
        <p class="gm-hud-hint is-violations">Pelanggaran aktif <span class="gm-hud-pill is-alert"><b id="hud-violation-total">–</b></span></p>
        <div class="gm-hud-violations" role="group" aria-label="Pelanggaran Besigma">
          <button type="button" class="gm-hud-violation is-danger" data-roster="kind" data-kind="employee_danger">
            <span>Bahaya karyawan</span>
            <strong id="hud-kind-employee_danger">–</strong>
            <small>Masuk batas bahaya</small>
          </button>
          {{-- Card Kompetensi dihapus per permintaan user. --}}
          {{-- <button type="button" class="gm-hud-violation is-unit" data-roster="kind" data-kind="unit_danger">
            <span>Bahaya unit</span>
            <strong id="hud-kind-unit_danger">–</strong>
            <small>Unit di zona bahaya</small>
          </button> --}}
        </div>
        <p class="gm-hud-foot">Tidak diketahui <b id="hud-pob-unknown">–</b> <button type="button" class="gm-hud-link" data-roster="unknown">Lihat daftar</button> · <button type="button" class="gm-hud-link" data-roster="in">Lihat di boundary</button></p>
      </article>

      <article class="gm-hud-card is-rfid" id="gm-rfid-card" role="button" tabindex="0" aria-label="Buka daftar Besigma dan RFID">
        <div class="gm-hud-card-top">
          <span class="gm-hud-ico rfid" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M5 12a7 7 0 0 1 7-7"/><path d="M8 12a4 4 0 0 1 4-4"/><circle cx="12" cy="12" r="1.4"/><path d="M19 12a7 7 0 0 1-7 7"/><path d="M16 12a4 4 0 0 1-4 4"/></svg>
          </span>
          <div class="gm-hud-head">
            <p class="gm-hud-kicker">Besigma × RFID</p>
            <p class="gm-hud-value"><b id="hud-both">–</b> <small>keduanya cocok</small></p>
          </div>
          <svg class="gm-hud-spark" viewBox="0 0 88 32" fill="none" aria-hidden="true">
            <path d="M2 18 C12 18 16 8 26 11 C36 14 38 22 48 16 C58 10 62 6 72 12 C80 16 82 20 86 14" />
          </svg>
        </div>
        <div class="gm-hud-split is-3">
          <button type="button" class="gm-hud-metric is-ever" data-roster="current">
            <span>GPS aktif</span>
            <strong id="hud-current">–</strong>
            <small>GPS aktif hari ini</small>
          </button>
          <button type="button" class="gm-hud-metric is-tag" data-roster="checkin">
            <span>Check-in RFID</span>
            <strong id="hud-rfid">–</strong>
            <small>Sudah tap onsite</small>
          </button>
          <button type="button" class="gm-hud-metric is-live" data-roster="both">
            <span>Keduanya cocok</span>
            <strong id="hud-both-metric">–</strong>
            <small>RFID + Besigma</small>
          </button>
        </div>
        <div class="gm-hud-split">
          <button type="button" class="gm-hud-metric is-gap" data-roster="both">
            <span>Aktif dari Install</span>
            <strong id="hud-install-ratio">–</strong>
            <small>Pakai Besigma &amp; check-in, dari total install</small>
          </button>
          <button type="button" class="gm-hud-metric is-miss" data-roster="gap_rb">
            <span>RFID tanpa GPS</span>
            <strong id="hud-gap-rb">–</strong>
            <small>Sudah tap, tidak di Besigma</small>
          </button>
        </div>
        <p class="gm-hud-foot">Klik angka untuk daftar · Excel di panel daftar</p>
      </article>
      </div>

      <div class="gm-hud-view" data-view="menu" id="gm-view-menu" hidden>
        <article class="gm-hud-card is-menu">
          <div class="gm-hud-card-top">
            <span class="gm-hud-ico menu" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
            </span>
            <div class="gm-hud-head">
              <p class="gm-hud-kicker">Boundary peta</p>
              <p class="gm-hud-value"><b id="hud-zone-count">–</b> <small>zona tampil</small></p>
            </div>
            <svg class="gm-hud-spark" viewBox="0 0 88 32" fill="none" aria-hidden="true">
              <path d="M2 18 C12 18 16 10 26 12 C36 14 40 24 50 18 C60 12 66 8 76 14 C82 18 84 20 86 16" />
            </svg>
          </div>
          <p class="gm-hud-hint">IUPK, Besigma, dan personel di peta</p>
          <p class="gm-hud-foot">Katalog zona <button type="button" class="gm-hud-link" id="gm-menu-open-list">Lihat daftar</button></p>
        </article>
        <article class="gm-hud-card is-sites">
          <div class="gm-hud-card-top">
            <span class="gm-hud-ico sites" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.2"/></svg>
            </span>
            <div class="gm-hud-head">
              <p class="gm-hud-kicker">Site operasi</p>
              <p class="gm-hud-value"><b>5</b> <small>wilayah</small></p>
            </div>
            <svg class="gm-hud-spark" viewBox="0 0 88 32" fill="none" aria-hidden="true">
              <path d="M2 22 C14 22 16 8 28 12 C40 16 42 24 54 16 C66 8 70 14 86 12" />
            </svg>
          </div>
          <div class="gm-hud-sites" role="group" aria-label="Loncat ke site">
            <button type="button" class="gm-hud-site" data-jump="BMO">BMO</button>
            <button type="button" class="gm-hud-site" data-jump="LMO">LMO</button>
            <button type="button" class="gm-hud-site" data-jump="GMO">GMO</button>
            <button type="button" class="gm-hud-site" data-jump="SMO">SMO</button>
            <button type="button" class="gm-hud-site" data-jump="PUNAN">PUNAN</button>
          </div>
          <p class="gm-hud-foot">Klik site untuk memusatkan peta</p>
        </article>
      </div>

      <div class="gm-hud-view" data-view="postevent" id="gm-view-postevent" hidden>
        <article class="gm-hud-card is-postevent">
          <div class="gm-hud-card-top">
            <span class="gm-hud-ico postevent" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M4 19V7"/><path d="M4 19h16"/><path d="M8 15l3-4 3 3 4-6"/></svg>
            </span>
            <div class="gm-hud-head">
              <p class="gm-hud-kicker">Post-event</p>
              <p class="gm-hud-value"><b id="hud-postevent-headline">–</b> <small>orang</small></p>
            </div>
            <svg class="gm-hud-spark" viewBox="0 0 88 32" fill="none" aria-hidden="true">
              <path d="M2 22 C12 22 16 10 26 14 C36 18 40 8 50 12 C60 16 68 24 86 12" />
            </svg>
          </div>
          <label class="gm-hud-hint" for="hud-postevent-date">Tanggal jejak
            <input type="date" id="hud-postevent-date" class="gm-hud-date">
          </label>
          <div class="gm-hud-sites" id="hud-postevent-kinds" role="group" aria-label="Filter jejak">
            <button type="button" class="gm-hud-site is-on" data-postevent-kind="">Semua <b id="hud-postevent-count">0</b></button>
            <button type="button" class="gm-hud-site" data-postevent-kind="person">Orang <b id="hud-postevent-people">0</b></button>
            {{-- Unit di-hide dulu — lihat dokumentasi di JS paintPostEventRoster() yang memfilter entity "unit" dari daftar. --}}
            {{-- <button type="button" class="gm-hud-site" data-postevent-kind="unit">Unit <b id="hud-postevent-units">0</b></button> --}}
          </div>
          <p class="gm-hud-foot">Cari nama atau SID, lalu klik kartu untuk melihat jalur GPS.</p>
        </article>
        <div id="gm-postevent-cards" class="gm-hud-place-stack"></div>
      </div>

      <div class="gm-hud-view" data-view="cctv" id="gm-view-cctv" hidden>
        <article class="gm-hud-card is-cctv">
          <div class="gm-hud-card-top">
            <span class="gm-hud-ico cctv" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M4 8h10v10H4z"/><path d="m14 11 6-3v8l-6-3z"/></svg>
            </span>
            <div class="gm-hud-head">
              <p class="gm-hud-kicker">CCTV</p>
              <p class="gm-hud-value"><b id="hud-cctv-total">–</b> <small>kamera</small></p>
            </div>
            <svg class="gm-hud-spark" viewBox="0 0 88 32" fill="none" aria-hidden="true">
              <path d="M2 20 C14 20 18 10 28 12 C38 14 42 22 52 16 C62 10 70 8 86 14" />
            </svg>
          </div>
          <div class="gm-hud-split">
            <button type="button" class="gm-hud-metric is-safe" data-cctv-status="on">
              <small>On</small><b id="hud-cctv-on">0</b>
            </button>
            <button type="button" class="gm-hud-metric is-unsafe" data-cctv-status="off">
              <small>Off</small><b id="hud-cctv-off">0</b>
            </button>
          </div>
          <div class="gm-hud-sites" id="hud-cctv-sites" role="group" aria-label="Filter site CCTV">
            <button type="button" class="gm-hud-site is-on" data-cctv-site="">Semua <b id="hud-cctv-all">0</b></button>
            <button type="button" class="gm-hud-site" data-cctv-site="BMO">BMO <b id="hud-cctv-BMO">0</b></button>
            <button type="button" class="gm-hud-site" data-cctv-site="LMO">LMO <b id="hud-cctv-LMO">0</b></button>
            <button type="button" class="gm-hud-site" data-cctv-site="GMO">GMO <b id="hud-cctv-GMO">0</b></button>
            <button type="button" class="gm-hud-site" data-cctv-site="SMO">SMO <b id="hud-cctv-SMO">0</b></button>
            <button type="button" class="gm-hud-site" data-cctv-site="PUNAN">PUN <b id="hud-cctv-PUNAN">0</b></button>
          </div>
          <p class="gm-hud-foot">Klik kartu atau titik untuk melihat lokasi. Live hanya dibuka saat diminta.</p>
        </article>
        <div id="gm-cctv-cards" class="gm-hud-place-stack"></div>
      </div>

      <div class="gm-hud-view" data-view="interventions" id="gm-view-interventions" hidden>
        <article class="gm-hud-card is-interventions">
          <div class="gm-hud-card-top">
            <span class="gm-hud-ico interventions" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M12 3v18M4 8h16M7 21h10"/></svg>
            </span>
            <div class="gm-hud-head">
              <p class="gm-hud-kicker">Intervensi</p>
              <p class="gm-hud-value"><b id="hud-iv-total">–</b> <small>task terbuka</small></p>
            </div>
            <svg class="gm-hud-spark" viewBox="0 0 88 32" fill="none" aria-hidden="true">
              <path d="M2 18 C12 22 18 8 28 12 C38 16 44 24 54 14 C64 6 74 16 86 10" />
            </svg>
            <button type="button" class="gm-hud-sound-toggle" id="gm-iv-sound-toggle" aria-pressed="true" title="Bunyi alarm pelanggaran baru: aktif">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 5V4L8 9H4z"/><path d="M16.5 8.5a5 5 0 0 1 0 7"/><path d="M19 6a8 8 0 0 1 0 12"/></svg>
            </button>
          </div>
          <div class="gm-hud-split">
            <button type="button" class="gm-hud-metric is-unsafe" data-iv-status="open">
              <small>Open</small><b id="hud-iv-open">0</b>
            </button>
            <button type="button" class="gm-hud-metric is-tag" data-iv-status="in_progress">
              <small>On progress</small><b id="hud-iv-progress">0</b>
            </button>
          </div>
          <div class="gm-hud-sites" id="hud-iv-filters" role="group" aria-label="Filter task">
            <button type="button" class="gm-hud-site is-on" data-iv-entity="">Semua <b id="hud-iv-all">0</b></button>
            <button type="button" class="gm-hud-site" data-iv-entity="person">Orang <b id="hud-iv-people">0</b></button>
            {{-- Unit di-hide dulu — lihat dokumentasi di JS loadInterventions() yang memfilter entity "unit" dari daftar. --}}
            {{-- <button type="button" class="gm-hud-site" data-iv-entity="unit">Unit <b id="hud-iv-units">0</b></button> --}}
          </div>
          <div class="gm-hud-sites" id="hud-iv-kinds" role="group" aria-label="Jenis pelanggaran">
            <button type="button" class="gm-hud-site" data-iv-kind="employee_danger">Bahaya <b id="hud-iv-kind-employee_danger">0</b></button>
            <button type="button" class="gm-hud-site" data-iv-kind="employee_competence">Kompetensi <b id="hud-iv-kind-employee_competence">0</b></button>
            {{-- <button type="button" class="gm-hud-site" data-iv-kind="unit_danger">Unit <b id="hud-iv-kind-unit_danger">0</b></button> --}}
          </div>
          <div class="gm-hud-sites" id="hud-iv-sites" role="group" aria-label="Filter site">
            <button type="button" class="gm-hud-site is-on" data-iv-site="">Semua site</button>
            <button type="button" class="gm-hud-site" data-iv-site="BMO">BMO <b id="hud-iv-BMO">0</b></button>
            <button type="button" class="gm-hud-site" data-iv-site="LMO">LMO <b id="hud-iv-LMO">0</b></button>
            <button type="button" class="gm-hud-site" data-iv-site="GMO">GMO <b id="hud-iv-GMO">0</b></button>
            <button type="button" class="gm-hud-site" data-iv-site="SMO">SMO <b id="hud-iv-SMO">0</b></button>
            <button type="button" class="gm-hud-site" data-iv-site="PUNAN">PUN <b id="hud-iv-PUNAN">0</b></button>
          </div>
          <p class="gm-hud-foot">Detail &amp; bukti menampilkan jejak GPS di peta (seperti Post-event). Laporan Hazard untuk kirim form.</p>
        </article>
        <div id="gm-iv-cards" class="gm-hud-place-stack"></div>

        <article class="gm-hud-card is-interventions">
          <div class="gm-hud-card-top">
            <span class="gm-hud-ico interventions" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
            </span>
            <div class="gm-hud-head">
              <p class="gm-hud-kicker">Laporan Hazard Ter-intervensi</p>
              <p class="gm-hud-value"><b id="hud-hr-total">–</b> <small>laporan submit</small></p>
            </div>
          </div>
          <p class="gm-hud-foot">Laporan hazard yang sudah dikirim dan sudah punya intervensi, beserta status terkini.</p>
        </article>
        <div id="gm-hr-cards" class="gm-hud-place-stack"></div>
      </div>

      <div class="gm-hud-view" data-view="historical" id="gm-view-historical" hidden>
        <article class="gm-hud-card is-historical">
          <div class="gm-hud-card-top">
            <span class="gm-hud-ico historical" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v5l4 2"/></svg>
            </span>
            <div class="gm-hud-head">
              <p class="gm-hud-kicker">Historical</p>
              <p class="gm-hud-value"><b id="hud-hist-total">–</b> <small>laporan hazard</small></p>
            </div>
            <svg class="gm-hud-spark" viewBox="0 0 88 32" fill="none" aria-hidden="true">
              <path d="M2 18 C12 22 18 8 28 12 C38 16 44 24 54 14 C64 6 74 16 86 10" />
            </svg>
          </div>
          <div class="gm-hud-sites" id="hud-hist-status" role="group" aria-label="Filter status laporan">
            <button type="button" class="gm-hud-site is-on" data-hist-status="">Semua <b id="hud-hist-all">0</b></button>
            <button type="button" class="gm-hud-site" data-hist-status="submitted">Submit <b id="hud-hist-submitted">0</b></button>
            <button type="button" class="gm-hud-site" data-hist-status="open">Open <b id="hud-hist-open">0</b></button>
            <button type="button" class="gm-hud-site" data-hist-status="in_progress">On progress <b id="hud-hist-in_progress">0</b></button>
            <button type="button" class="gm-hud-site" data-hist-status="closed">Selesai <b id="hud-hist-closed">0</b></button>
            <button type="button" class="gm-hud-site" data-hist-status="verified">Terverifikasi <b id="hud-hist-verified">0</b></button>
          </div>
          <p class="gm-hud-foot">Riwayat seluruh laporan hazard yang pernah masuk, beserta status intervensi/event terkini.</p>
        </article>
        <div id="gm-hist-cards" class="gm-hud-place-stack"></div>
      </div>
    </div>

    <aside class="gm-panel is-closed" id="gm-panel" aria-label="Hasil peta">
      <div class="gm-results" id="gm-results">
        <div class="gm-results-head">
          <button type="button" class="gm-panel-back" id="gm-panel-back" aria-label="Kembali ke beranda">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5 8 12l7 7"/></svg>
            <span>Kembali</span>
          </button>
          <div>
            <p class="gm-kicker">Boundary Besigma</p>
            <p id="gm-status">{{ $connected ? 'Besigma terhubung' : 'IUPK tampil · Besigma belum terhubung' }}</p>
          </div>
          <div class="gm-results-tools">
            <button type="button" class="gm-export-btn" id="gm-roster-export" hidden>Unduh Excel</button>
            <strong id="gm-count">0</strong>
          </div>
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
            <button type="button" class="gm-action" id="gm-act-intervene">
              <span class="gm-action-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18M4 8h16M7 21h10"/></svg>
              </span>
              Buka intervensi
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
    <button type="button" class="gm-pill" data-scope="people">
      <span class="gm-dot people"></span>
      Personel
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
      <span class="gm-layers-thumb" id="gm-layers-thumb" data-kind="sgi"></span>
      <span>Layers</span>
    </button>
    <div class="gm-layers-pop" id="gm-layers-pop" hidden>
      <p>Jenis peta</p>
      <div class="gm-layer-cards">
        <button type="button" class="is-on" data-basemap="sgi"><i data-kind="sgi"></i>Drone + Satelit</button>
        <button type="button" data-basemap="map"><i data-kind="map"></i>Peta</button>
        <button type="button" data-basemap="dark"><i data-kind="dark"></i>Gelap</button>
      </div>
      <p>Overlay</p>
      <label><input type="checkbox" data-layer="ops" checked> Konsesi IUPK</label>
      <label><input type="checkbox" data-layer="besigma" checked> Besigma</label>
      <label><input type="checkbox" data-layer="people" checked> Personel GPS</label>
      <label><input type="checkbox" data-layer="hazard" checked> Zona berbahaya</label>
      <label><input type="checkbox" data-layer="cctv"> CCTV</label>
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

  <div class="gm-hazard-modal" id="gm-hazard-modal" hidden>
    <div class="gm-hazard-backdrop" data-hazard-close></div>
    <div class="gm-hazard-dialog" role="dialog" aria-modal="true" aria-labelledby="gm-hazard-title">
      <header class="gm-hazard-head">
        <h2 id="gm-hazard-title">Laporan Hazard</h2>
        <button type="button" class="gm-hazard-x" data-hazard-close aria-label="Tutup">×</button>
      </header>
      <form id="gm-hazard-form" class="gm-hazard-body" enctype="multipart/form-data">
        <input type="hidden" name="event_id" id="gm-hazard-event-id" value="">

        <section class="gm-hazard-sec">
          <h3>Akses</h3>
          <p class="gm-hazard-hint">Input SID pelapor</p>
          <label>SID Pelapor
            <input type="text" name="sid_pelapor" id="gm-hazard-pelapor-sid" maxlength="64" required placeholder="Kode SID">
          </label>
          <input type="hidden" name="username" id="gm-hazard-username" value="">
          <input type="hidden" name="password" id="gm-hazard-password" value="">
        </section>

        <section class="gm-hazard-sec">
          <h3>Pelapor</h3>
          <p class="gm-hazard-hint">Terisi otomatis dari SID pada bagian Akses.</p>
          <input type="hidden" name="npk_pelapor" id="gm-hazard-pelapor-npk" value="">
          <div class="gm-hazard-grid">
            <label>Nama<input type="text" name="nama_pelapor" id="gm-hazard-pelapor-nama" maxlength="255" readonly></label>
            <label>Jabatan<input type="text" name="jabatan_pelapor" id="gm-hazard-pelapor-jabatan" maxlength="255" readonly></label>
          </div>
        </section>

        <section class="gm-hazard-sec">
          <h3>Penanggung Jawab</h3>
          <label>Perusahaan
            <select name="perusahaan" id="gm-hazard-perusahaan">
              <option value="">Pilih perusahaan</option>
              <option value="PT Berau Coal Energy">PT Berau Coal Energy</option>
              <option value="Mitra Kerja">Mitra Kerja</option>
            </select>
          </label>
          <div class="gm-hazard-grid">
            <label>SID PIC
              <input type="text" name="pic_sid" id="gm-hazard-pic-sid" maxlength="64" placeholder="Kode SID">
            </label>
            <label>NPK PIC<input type="text" name="pic_npk" id="gm-hazard-pic-npk" maxlength="64" readonly></label>
          </div>
          <div class="gm-hazard-grid">
            <label>Nama PIC<input type="text" name="pic_nama" id="gm-hazard-pic-nama" maxlength="255" readonly></label>
            <label>Jabatan PIC<input type="text" name="pic_jabatan" id="gm-hazard-pic-jabatan" maxlength="255" readonly></label>
          </div>
          <button type="button" class="gm-hazard-link" data-hazard-lookup="pic">Cari PIC (Nama / NIK / SID)</button>
        </section>

        <section class="gm-hazard-sec">
          <h3>Lokasi</h3>
          <label>Tools Pengamatan
            <select name="tools_pengamatan">
              <option value="Post Event - BeSigma">Post Event - BeSigma</option>
              <option value="Real Time">Real Time</option>
              <option value="Pengawasan Langsung">Pengawasan Langsung</option>
            </select>
          </label>
          <div class="gm-hazard-grid">
            <label>Site
              <select name="site" id="gm-hazard-site">
                <option value="">Pilih site</option>
                <option value="BMO 1">BMO 1</option>
                <option value="BMO 2">BMO 2</option>
                <option value="BMO 3">BMO 3</option>
                <option value="SMO">SMO</option>
                <option value="GMO">GMO</option>
                <option value="LMO">LMO</option>
                <option value="HO">HO</option>
                <option value="EXPLORASI">EXPLORASI</option>
                <option value="MARINE">MARINE</option>
              </select>
            </label>
            <label>Lokasi
              <div class="gm-hazard-combo" data-gm-hazard-combo data-url-attr="data-maps-hazard-lokasi-url" data-site-param="1" data-clear-targets="#gm-hazard-detail-lokasi,#gm-hazard-pja-bc,#gm-hazard-pja-mitra">
                <input type="text" name="lokasi" id="gm-hazard-lokasi" maxlength="255" autocomplete="off" placeholder="Cari lokasi…">
                <ul class="gm-hazard-combo-list" hidden></ul>
              </div>
            </label>
          </div>
          <label>Detail Lokasi
            <div class="gm-hazard-combo" data-gm-hazard-combo data-url-attr="data-maps-hazard-detail-lokasi-url" data-site-param="1" data-lokasi-param="1">
              <input type="text" name="detail_lokasi" id="gm-hazard-detail-lokasi" maxlength="255" autocomplete="off" placeholder="Pilih lokasi dulu, lalu cari detail…">
              <ul class="gm-hazard-combo-list" hidden></ul>
            </div>
          </label>
          <label>Keterangan Lokasi<textarea name="keterangan_lokasi" rows="2" maxlength="2000"></textarea></label>
        </section>

        <section class="gm-hazard-sec">
          <h3>PJA</h3>
          <p class="gm-hazard-hint">Dari bcbeats.wan_vw_relasi_lokasi_pja (terfilter lokasi bila dipilih).</p>
          <div class="gm-hazard-grid">
            <label>Area PJA BC
              <div class="gm-hazard-combo" data-gm-hazard-combo data-url-attr="data-maps-hazard-pja-bc-url" data-site-param="1" data-lokasi-param="1">
                <input type="text" name="area_pja_bc" id="gm-hazard-pja-bc" maxlength="255" autocomplete="off" placeholder="Cari Area PJA BC…">
                <ul class="gm-hazard-combo-list" hidden></ul>
              </div>
            </label>
            <label>Area PJA Mitra Kerja
              <div class="gm-hazard-combo" data-gm-hazard-combo data-url-attr="data-maps-hazard-pja-mitra-url" data-site-param="1" data-lokasi-param="1">
                <input type="text" name="area_pja_mitra" id="gm-hazard-pja-mitra" maxlength="255" autocomplete="off" placeholder="Cari Area PJA Mitra…">
                <ul class="gm-hazard-combo-list" hidden></ul>
              </div>
            </label>
          </div>
        </section>

        <section class="gm-hazard-sec">
          <h3>Temuan</h3>
          <label>Unggah Foto
            <input type="file" name="foto" id="gm-hazard-foto" accept="image/jpeg,image/png,image/webp">
            <span class="gm-hazard-hint" id="gm-hazard-foto-status"></span>
            <img id="gm-hazard-foto-preview" class="gm-hazard-foto-preview" alt="Preview foto jejak GPS" hidden>
          </label>
          {{-- <label class="gm-hazard-check">
            <input type="checkbox" name="is_observasi_area_kritis" value="1">
            Apakah laporan berkaitan dengan Observasi Area Kritis?
          </label> --}}
          <div class="gm-hazard-grid">
            <label>Ketidaksesuaian<input type="text" name="ketidaksesuaian" id="gm-hazard-ketidaksesuaian" maxlength="255"></label>
            <label>Sub Ketidaksesuaian<input type="text" name="sub_ketidaksesuaian" id="gm-hazard-sub-ketidaksesuaian" maxlength="255"></label>
          </div>
          <label>Quick Action
            <select name="quick_action" id="gm-hazard-quick-action">
              <option value="">Pilih quick action</option>
              <option value="Atur kecepatan dan jarak">Atur kecepatan dan jarak</option>
              <option value="Coaching">Coaching</option>
              <option value="Fatigue Test">Fatigue Test</option>
              <option value="Komunikasi 2 arah">Komunikasi 2 arah</option>
              <option value="Parkir Fatigue">Parkir Fatigue</option>
              <option value="Pekerjaan dilanjutkan setelah perbaikan langsung.">Pekerjaan dilanjutkan setelah perbaikan langsung.</option>
              <option value="STOP Pekerjaan">STOP Pekerjaan</option>
              <option value="STOP pekerjaan sampai temuan diperbaiki">STOP pekerjaan sampai temuan diperbaiki</option>
              <option value="Tidak diperlukan intervensi">Tidak diperlukan intervensi</option>
            </select>
          </label>
          <label>Deskripsi Temuan<textarea name="deskripsi_temuan" id="gm-hazard-deskripsi" rows="5" maxlength="5000" placeholder="Terisi otomatis dari pelanggaran BeSigma"></textarea></label>
        </section>

        <p class="gm-hazard-msg" id="gm-hazard-msg" hidden></p>
        <footer class="gm-hazard-foot">
          <button type="reset" class="gm-hazard-reset">Reset</button>
          <button type="submit" class="gm-hazard-submit">Kirim Laporan Hazard</button>
        </footer>
      </form>
    </div>
  </div>

  <div class="gm-hazard-picker" id="gm-hazard-picker" hidden>
    <div class="gm-hazard-backdrop" data-picker-close></div>
    <div class="gm-hazard-picker-dialog" role="dialog" aria-modal="true" aria-label="Pilih Karyawan">
      <header class="gm-hazard-head">
        <h2>Pilih Karyawan</h2>
        <button type="button" class="gm-hazard-x" data-picker-close aria-label="Tutup">×</button>
      </header>
      <div class="gm-hazard-picker-body">
        <div class="gm-hazard-picker-search">
          <input type="search" id="gm-hazard-picker-q" placeholder="Nama / NPK / Kode SID">
          <button type="button" id="gm-hazard-picker-go">Cari</button>
        </div>
        <div class="gm-hazard-picker-table-wrap">
          <table>
            <thead>
              <tr><th>Kode SID</th><th>NPK</th><th>Nama</th><th></th></tr>
            </thead>
            <tbody id="gm-hazard-picker-rows">
              <tr><td colspan="4">Ketik minimal 2 karakter lalu Cari.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="{{ $iupkAsset }}"></script>
<script src="{{ asset('isc-assets/isc-hotspot-map.js') }}?v={{ filemtime(public_path('isc-assets/isc-hotspot-map.js')) }}"></script>
@endsection
