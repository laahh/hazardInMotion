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
    data-post-event-url="{{ $postEventUrl }}"
    data-post-event-trail-url="{{ $postEventTrailUrl }}"
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
    <button type="button" class="gm-rail-btn" id="gm-saved-btn" data-rail="saved">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
      <span>Disimpan</span>
    </button>
    <button type="button" class="gm-rail-btn" id="gm-recents-btn" data-rail="recents">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>
      <span>Terbaru</span>
    </button>
    <button type="button" class="gm-rail-btn" id="gm-postevent-btn" data-rail="postevent">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V7"/><path d="M4 19h16"/><path d="M8 15l3-4 3 3 4-6"/></svg>
      <span>Post-event</span>
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

      <article class="gm-hud-card is-safety">
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
        <p class="gm-hud-hint">Pelanggaran aktif <span class="gm-hud-pill is-alert"><b id="hud-violation-total">–</b></span></p>
        <div class="gm-hud-violations" role="group" aria-label="Pelanggaran Besigma">
          <button type="button" class="gm-hud-violation is-danger" data-roster="kind" data-kind="employee_danger">
            <span>Bahaya karyawan</span>
            <strong id="hud-kind-employee_danger">–</strong>
            <small>Masuk batas bahaya</small>
          </button>
          <button type="button" class="gm-hud-violation is-competence" data-roster="kind" data-kind="employee_competence">
            <span>Kompetensi</span>
            <strong id="hud-kind-employee_competence">–</strong>
            <small>Batas kompetensi</small>
          </button>
          <button type="button" class="gm-hud-violation is-unit" data-roster="kind" data-kind="unit_danger">
            <span>Bahaya unit</span>
            <strong id="hud-kind-unit_danger">–</strong>
            <small>Unit di zona bahaya</small>
          </button>
        </div>
        <p class="gm-hud-foot">Di luar <b id="hud-pob-out">–</b> · Tidak diketahui <b id="hud-pob-unknown">–</b> <button type="button" class="gm-hud-link" data-roster="in">Lihat di boundary</button></p>
      </article>

      <article class="gm-hud-card is-rfid">
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
          <div class="gm-hud-metric is-ever">
            <span>Pernah tercatat</span>
            <strong id="hud-ever">–</strong>
            <small>SID di Besigma</small>
          </div>
          <div class="gm-hud-metric is-live">
            <span>GPS aktif</span>
            <strong id="hud-current">–</strong>
            <small>Besigma hari ini</small>
          </div>
          <div class="gm-hud-metric is-tag">
            <span>Check-in RFID</span>
            <strong id="hud-rfid">–</strong>
            <small>Onsite hari ini</small>
          </div>
        </div>
        <div class="gm-hud-split">
          <div class="gm-hud-metric is-gap">
            <span>Besigma tanpa RFID</span>
            <strong id="hud-gap-br">–</strong>
            <small>Ada di Besigma, tidak check-in</small>
          </div>
          <div class="gm-hud-metric is-miss">
            <span>RFID tanpa Besigma</span>
            <strong id="hud-gap-rb">–</strong>
            <small>Check-in, tidak di Besigma</small>
          </div>
        </div>
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

      <div class="gm-hud-view" data-view="saved" id="gm-view-saved" hidden>
        <article class="gm-hud-card is-saved">
          <div class="gm-hud-card-top">
            <span class="gm-hud-ico saved" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
            </span>
            <div class="gm-hud-head">
              <p class="gm-hud-kicker">Lokasi disimpan</p>
              <p class="gm-hud-value"><b id="hud-saved-count">0</b> <small>tempat</small></p>
            </div>
            <svg class="gm-hud-spark" viewBox="0 0 88 32" fill="none" aria-hidden="true">
              <path d="M2 20 C12 20 18 8 28 12 C38 16 42 24 54 16 C66 8 74 14 86 10" />
            </svg>
          </div>
          <p class="gm-hud-foot">Boundary atau zona yang Anda tandai</p>
        </article>
        <div id="gm-saved-cards" class="gm-hud-place-stack"></div>
      </div>

      <div class="gm-hud-view" data-view="recents" id="gm-view-recents" hidden>
        <article class="gm-hud-card is-recent">
          <div class="gm-hud-card-top">
            <span class="gm-hud-ico recent" aria-hidden="true">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>
            </span>
            <div class="gm-hud-head">
              <p class="gm-hud-kicker">Dilihat baru-baru ini</p>
              <p class="gm-hud-value"><b id="hud-recent-count">0</b> <small>tempat</small></p>
            </div>
            <svg class="gm-hud-spark" viewBox="0 0 88 32" fill="none" aria-hidden="true">
              <path d="M2 16 C10 16 14 24 24 18 C34 12 40 6 50 12 C60 18 68 22 86 14" />
            </svg>
          </div>
          <p class="gm-hud-foot">Zona yang terakhir dibuka di peta</p>
        </article>
        <div id="gm-recent-cards" class="gm-hud-place-stack"></div>
      </div>

      <div class="gm-hud-view" data-view="postevent" id="gm-view-postevent" hidden>
        <article class="gm-hud-card is-postevent">
          <div class="gm-hud-card-top">
            <span class="gm-hud-ico postevent" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M4 19V7"/><path d="M4 19h16"/><path d="M8 15l3-4 3 3 4-6"/></svg>
            </span>
            <div class="gm-hud-head">
              <p class="gm-hud-kicker">Post-event</p>
              <p class="gm-hud-value"><b id="hud-postevent-count">–</b> <small>orang &amp; unit</small></p>
            </div>
            <svg class="gm-hud-spark" viewBox="0 0 88 32" fill="none" aria-hidden="true">
              <path d="M2 22 C12 22 16 10 26 14 C36 18 40 8 50 12 C60 16 68 24 86 12" />
            </svg>
          </div>
          <label class="gm-hud-hint" for="hud-postevent-date">Tanggal jejak
            <input type="date" id="hud-postevent-date" class="gm-hud-date">
          </label>
          <p class="gm-hud-foot">Cari nama, SID, atau nopol, lalu klik kartu untuk melihat jalur GPS.</p>
        </article>
        <div id="gm-postevent-cards" class="gm-hud-place-stack"></div>
      </div>
    </div>

    <aside class="gm-panel is-closed" id="gm-panel" aria-label="Hasil peta">
      <div class="gm-results" id="gm-results">
        <div class="gm-results-head">
          <div>
            <p class="gm-kicker">Boundary Besigma</p>
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
<script src="{{ asset('isc-assets/isc-hotspot-map.js') }}?v={{ filemtime(public_path('isc-assets/isc-hotspot-map.js')) }}"></script>
@endsection
