@extends('dms.layouts.app')

@section('title', 'Saat Operasi - Live Monitoring')

@section('css')
<style>
  .so-live-dot { display:inline-block; width:8px; height:8px; background:#22c55e; border-radius:50%; margin-right:6px; animation: soPulse 2s ease-in-out infinite; vertical-align:middle; }
  @keyframes soPulse { 0%,100% { opacity:1; } 50% { opacity:.4; } }
  .so-warn-banner{background:var(--warning-100,#fff3e0);border:1px solid var(--warning-200,#ffe0b2);color:var(--warning-600,#b45309);
    border-radius:10px;padding:12px 16px;font-size:13px;display:flex;gap:10px;align-items:flex-start;}
  .so-redflag-panel{ border:1px dashed var(--danger-main); background:rgba(239,74,0,0.05); border-radius:10px; padding:16px; margin-bottom:24px; }
  .so-badge { font-size:11px; font-weight:600; padding:4px 10px; border-radius:999px; display:inline-block; white-space:nowrap; }
  .so-badge.st-fit, .so-badge.st-fit_pantau { background:#e8f8ee; color:#0f7a3d; }
  .so-badge.st-perlu_perhatian { background:#fff3e0; color:#b45309; }
  .so-badge.st-tarik { background:#fde8e0; color:#b91c1c; }
  .so-row { cursor:pointer; border-left:4px solid transparent; }
  .so-row:hover { background:var(--neutral-50,#f9fafb); }
  .so-row.st-tarik { border-left-color:#EF4A00; background:rgba(239,74,0,0.03); }
  .so-row.st-perlu_perhatian { border-left-color:#FF9F29; }
  .so-row.st-fit, .so-row.st-fit_pantau { border-left-color:#45B369; }
  .so-row.is-redflag { background:rgba(124,58,237,0.05); }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Saat Operasi &mdash; Live Monitoring</h6>
    <div class="text-secondary-light text-sm mt-4">
      <span class="so-live-dot"></span>Fase 2: status kelayakan dinamis selama shift &middot; {{ $dateLabel }}
      &middot; diperbarui <span id="soLastUpdated">{{ $lastUpdated }}</span>
    </div>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('pra-operasi.dashboard') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:clipboard-check-outline" class="icon text-lg"></iconify-icon>
        Pra Operasi
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Saat Operasi</li>
  </ul>
</div>

<div id="soWarnBanner" class="so-warn-banner mb-24 {{ $up ? 'd-none' : '' }}">
  <iconify-icon icon="solar:danger-circle-bold" class="icon text-lg flex-shrink-0"></iconify-icon>
  <div>Koneksi checkin/alert (hse_automation) tidak tersedia saat ini.</div>
</div>

<div class="row gy-4 mb-24" id="soKpiRow">
  {{-- diisi/diupdate oleh JS renderKpi() --}}
</div>

<div id="soRedFlagWrap"></div>

<div class="row gy-4">
  <div class="col-xxl-8">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="text-lg mb-0">Operator Sedang Beroperasi</h6>
        <span class="text-secondary-light text-sm" id="soCardCount">0 operator</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive scroll-sm" style="max-height:640px;overflow-y:auto">
          <table class="table bordered-table mb-0">
            <thead>
              <tr>
                <th>Status</th><th>Operator</th><th>Checkin</th><th>Shift</th>
                <th>Fatigue Test</th><th>PVT</th><th>Alert Hari Ini</th><th>Tindak Lanjut</th>
              </tr>
            </thead>
            <tbody id="soTableBody"></tbody>
          </table>
        </div>
        <p class="text-secondary-light text-xs px-16 py-12 mb-0">Klik baris untuk melihat riwayat pengecekan Fatigue Test, PVT, dan alert operator tersebut.</p>
      </div>
    </div>
  </div>
  <div class="col-xxl-4">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent">
        <h6 class="text-lg mb-0">Feed Alert Live</h6>
        <span class="text-secondary-light text-sm">Kronologis, terbaru di atas</span>
      </div>
      <div class="card-body p-0">
        <div id="soAlertFeed" style="max-height:640px;overflow-y:auto"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="soTindakLanjutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title mb-0">Tandai Tindak Lanjut</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <p class="text-sm text-secondary-light mb-12">Operator: <b id="soTlNama">-</b> (<span id="soTlSid">-</span>)</p>
        <label class="form-label text-sm fw-medium">Catatan tindakan</label>
        <textarea id="soTlCatatan" class="form-control" rows="3" placeholder="mis. Ditarik dari unit, diistirahatkan 30 menit"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary-600 btn-sm" id="soTlSubmit">Simpan</button>
      </div>
    </div>
  </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="soOperatorDrawer" style="width:480px">
  <div class="offcanvas-header border-bottom">
    <div>
      <span class="text-secondary-light text-sm mono" id="soDrawerSid">-</span>
      <h6 class="mb-0 mt-2" id="soDrawerName">-</h6>
      <div class="d-flex flex-wrap gap-2 mt-8">
        <div id="soDrawerStatusBadge"></div>
        <span id="soDrawerRosterBadge" class="bg-neutral-100 text-secondary-light px-10 py-2 rounded-8 text-xs fw-medium"></span>
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
  </div>
  <div class="offcanvas-body">
    <div id="soDrawerLoading" class="text-center text-secondary-light py-40">
      <div class="spinner-border spinner-border-sm text-primary-600 mb-8" role="status"></div>
      <div class="text-sm">Memuat profil operator...</div>
    </div>
    <div id="soDrawerContent" class="d-none">

      <div id="soDrawerIllnessBanner" class="d-none mb-24"></div>

      <div class="mb-24">
        <h6 class="text-sm fw-semibold text-secondary-light text-uppercase mb-8">Tren Fatigue Test Personal (30 Hari)</h6>
        <div id="soDrawerTrendChart"></div>
        <p id="soDrawerBaselineNote" class="text-xs text-secondary-light mt-8 mb-0"></p>
      </div>

      <div class="mb-24">
        <h6 class="text-sm fw-semibold text-secondary-light text-uppercase mb-8">Riwayat PVT (30 Hari)</h6>
        <div id="soDrawerPvtList" class="d-flex flex-column gap-2" style="max-height:200px;overflow-y:auto"></div>
      </div>

      <div>
        <div class="d-flex align-items-center justify-content-between mb-8">
          <h6 class="text-sm fw-semibold text-secondary-light text-uppercase mb-0">Riwayat Alert DMS (30 Hari)</h6>
          <span id="soDrawerAlertSummary" class="text-xs text-secondary-light"></span>
        </div>
        <div id="soDrawerAlertList" class="d-flex flex-column gap-2" style="max-height:320px;overflow-y:auto"></div>
      </div>

    </div>
  </div>
</div>
@endsection

@section('page-scripts')
<script>
(function(){
  var dataUrl = @json(route('pra-operasi.saat-operasi.data'));
  var profileUrlBase = @json(url('/pra-operasi/operator'));
  var date = @json(request()->query('date'));
  var POLL_MS = 20000;
  var latestCardsBySid = {};

  var statusMeta = {
    fit: { label: 'Fit', cls: 'st-fit' },
    fit_pantau: { label: 'Fit (Pantau)', cls: 'st-fit_pantau' },
    perlu_perhatian: { label: 'Perlu Perhatian', cls: 'st-perlu_perhatian' },
    tarik: { label: 'Tarik dari Unit', cls: 'st-tarik' }
  };
  var tierLabel = { hijau: 'Hijau', kuning: 'Kuning', merah: 'Merah' };
  var pvtStatusLabel = { lulus: 'Lulus', tidak_lulus: 'Tidak Lulus', belum: 'Belum Tes' };

  function escapeHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function renderKpi(kpi) {
    var tiles = [
      ['Sedang Beroperasi', kpi.beroperasi, 'var(--primary-600)', 'solar:steering-wheel-bold'],
      ['Fit', kpi.fit, '#45B369', 'solar:shield-check-bold'],
      ['Perlu Perhatian', kpi.perlu_perhatian, '#FF9F29', 'solar:shield-warning-bold'],
      ['Tarik dari Unit', kpi.tarik, '#EF4A00', 'solar:danger-triangle-bold'],
      ['Red Flag Proses', kpi.red_flag, '#7C3AED', 'solar:flag-bold']
    ];
    document.getElementById('soKpiRow').innerHTML = tiles.map(function(t){
      return '<div class="col-xxl col-sm-6"><div class="card p-3 shadow-2 radius-8 border input-form-light h-100"><div class="card-body p-0">' +
        '<div class="d-flex align-items-center gap-2 mb-8"><span class="w-48-px h-48-px flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle" style="background:' + t[2] + '">' +
        '<iconify-icon icon="' + t[3] + '" class="icon text-xl"></iconify-icon></span>' +
        '<div><span class="mb-2 fw-medium text-secondary-light text-sm d-block">' + t[0] + '</span><h6 class="fw-semibold mb-0">' + t[1] + '</h6></div></div>' +
        '</div></div></div>';
    }).join('');
  }

  function operatorRow(op) {
    var meta = statusMeta[op.status] || statusMeta.perlu_perhatian;
    var tier = op.fatigue_tier ? tierLabel[op.fatigue_tier] : 'Belum Tes';
    var needsAction = op.status === 'tarik' || op.status === 'perlu_perhatian';
    var actionHtml = '-';
    if (needsAction) {
      if (op.sudah_ditindaklanjuti) {
        actionHtml = '<span class="text-xs text-success-600 d-flex align-items-center gap-1">' +
          '<iconify-icon icon="solar:check-circle-bold"></iconify-icon>Ditindaklanjuti' +
          (op.catatan_tindak_lanjut && op.catatan_tindak_lanjut.ditandai_pada ? ' (' + escapeHtml(op.catatan_tindak_lanjut.ditandai_pada) + ')' : '') +
          '</span>';
      } else {
        actionHtml = '<button type="button" class="btn btn-outline-danger btn-sm so-tl-btn" data-sid="' + escapeHtml(op.kode_sid) + '" data-nama="' + escapeHtml(op.nama) + '">Tandai</button>';
      }
    }
    var pvtLabel = pvtStatusLabel[op.pvt_status] || 'Belum Tes';
    return '<tr class="so-row ' + meta.cls + (op.is_red_flag ? ' is-redflag' : '') + '" data-sid="' + escapeHtml(op.kode_sid) + '" tabindex="0">' +
      '<td><span class="so-badge ' + meta.cls + '">' + meta.label + '</span>' + (op.is_red_flag ? ' <iconify-icon icon="solar:flag-bold" class="text-danger-600" title="Red Flag Proses"></iconify-icon>' : '') + '</td>' +
      '<td><span class="fw-semibold d-block">' + escapeHtml(op.nama) + '</span><span class="text-xs text-secondary-light">' + escapeHtml(op.kode_sid) + ' &middot; ' + escapeHtml(op.perusahaan) + '</span></td>' +
      '<td class="text-sm">' + escapeHtml(op.checked_in_at ? op.checked_in_at.slice(11,16) : '-') + '</td>' +
      '<td class="text-sm">' + (op.shift_label ? escapeHtml(op.shift_label) : '-') +
        (op.shift_source === 'pattern' ? ' <iconify-icon icon="solar:clock-circle-linear" class="text-secondary-light" title="Dari pola jam checkin, roster DMS tidak tersedia"></iconify-icon>' : '') + '</td>' +
      '<td class="text-sm">' + tier + (op.fatigue_score !== null ? ' (' + op.fatigue_score + '/10)' : '') + '</td>' +
      '<td class="text-sm">' + pvtLabel + '</td>' +
      '<td class="text-sm"><span class="text-danger-600">' + op.alert_nyata + '</span> true alert &middot; <span class="text-warning-600">' + op.alert_belum + '</span> belum</td>' +
      '<td onclick="event.stopPropagation()">' + actionHtml + '</td>' +
    '</tr>';
  }

  function renderRedFlags(redFlags) {
    var wrap = document.getElementById('soRedFlagWrap');
    if (!redFlags || !redFlags.length) { wrap.innerHTML = ''; return; }
    wrap.innerHTML = '<div class="so-redflag-panel">' +
      '<div class="d-flex align-items-center gap-2 mb-4"><iconify-icon icon="solar:flag-bold" class="text-danger-600 text-xl"></iconify-icon>' +
      '<h6 class="mb-0 text-danger-600">Red Flag Proses &mdash; Fatigue Test Merah/Belum Tapi Tetap Beroperasi (' + redFlags.length + ' orang)</h6></div>' +
      '<p class="text-sm text-secondary-light mb-0">Operator ini seharusnya sudah dicegat di Pra Operasi pagi tadi &mdash; ini indikasi kegagalan kontrol, bukan cuma risiko rutin. Lihat baris bertanda ungu di tabel.</p>' +
    '</div>';
  }

  var alertStatusMeta2 = {
    nyata: { label: 'True Alert', cls: 'bg-danger-focus text-danger-main' },
    palsu: { label: 'False Alert', cls: 'bg-neutral-200 text-neutral-600' },
    belum: { label: 'Belum Diperiksa', cls: 'bg-warning-focus text-warning-main' }
  };

  function renderAlertFeed(feed) {
    var el = document.getElementById('soAlertFeed');
    if (!feed || !feed.length) {
      el.innerHTML = '<div class="text-secondary-light text-sm text-center py-40">Belum ada alert hari ini.</div>';
      return;
    }
    el.innerHTML = feed.map(function(a){
      var meta = alertStatusMeta2[a.status] || alertStatusMeta2.belum;
      return '<div class="d-flex align-items-center justify-content-between px-16 py-10 border-bottom">' +
        '<div><span class="fw-medium text-sm d-block">' + escapeHtml(a.nama) + '</span>' +
        '<span class="text-xs text-secondary-light">' + escapeHtml(a.name) + ' &middot; ' + escapeHtml(a.waktu) + '</span></div>' +
        '<span class="' + meta.cls + ' px-8 py-2 rounded-pill text-xs fw-medium flex-shrink-0">' + meta.label + '</span>' +
      '</div>';
    }).join('');
  }

  function render(payload) {
    if (payload.date) { date = payload.date; }
    document.getElementById('soWarnBanner').classList.toggle('d-none', !!payload.up);
    document.getElementById('soLastUpdated').textContent = payload.lastUpdated;
    renderKpi(payload.kpi || { beroperasi:0, fit:0, perlu_perhatian:0, tarik:0, red_flag:0 });
    renderRedFlags(payload.redFlags);
    renderAlertFeed(payload.alertFeed);

    var cards = payload.cards || [];
    latestCardsBySid = {};
    cards.forEach(function(c){ latestCardsBySid[c.kode_sid.toUpperCase()] = c; });

    document.getElementById('soCardCount').textContent = cards.length + ' operator';
    var body = document.getElementById('soTableBody');
    if (!cards.length) {
      body.innerHTML = '<tr><td colspan="8" class="text-center text-secondary-light py-40">Tidak ada operator yang sedang beroperasi saat ini.</td></tr>';
    } else {
      body.innerHTML = cards.map(operatorRow).join('');
    }
  }

  function fetchData() {
    var url = dataUrl + (date ? ('?date=' + encodeURIComponent(date)) : '');
    fetch(url).then(function(r){ return r.json(); }).then(render).catch(function(){});
  }

  // Modal "Tandai Ditindaklanjuti" — event delegation karena baris dirender ulang tiap poll.
  var tlModalEl = document.getElementById('soTindakLanjutModal');
  var tlModal = (typeof bootstrap !== 'undefined') ? new bootstrap.Modal(tlModalEl) : null;
  var tlUrl = @json(route('pra-operasi.saat-operasi.tindak-lanjut'));
  var csrfToken = document.querySelector('meta[name="csrf-token"]');
  csrfToken = csrfToken ? csrfToken.getAttribute('content') : '';

  // Drawer detail operator — riwayat Fatigue Test, PVT, dan alert.
  var drawerEl = document.getElementById('soOperatorDrawer');
  var drawer = (drawerEl && typeof bootstrap !== 'undefined') ? new bootstrap.Offcanvas(drawerEl) : null;
  var currentChart = null;
  var pvtStatusMeta = {
    lulus: { label: 'Lulus', cls: 'bg-success-focus text-success-main' },
    tidak_lulus: { label: 'Tidak Lulus', cls: 'bg-danger-focus text-danger-main' },
    belum: { label: 'Belum Tes', cls: 'bg-neutral-200 text-neutral-600' }
  };
  var alertStatusMeta = {
    nyata: { label: 'True Alert', cls: 'bg-success-focus text-success-main' },
    palsu: { label: 'False Alert', cls: 'bg-neutral-200 text-neutral-600' },
    belum: { label: 'Belum Diperiksa', cls: 'bg-warning-focus text-warning-main' }
  };

  function openDrawer(sid) {
    if (!drawer) return;
    var op = latestCardsBySid[sid.toUpperCase()] || {};
    var meta = statusMeta[op.status] || statusMeta.perlu_perhatian;
    document.getElementById('soDrawerSid').textContent = sid;
    document.getElementById('soDrawerName').textContent = op.nama || '-';
    document.getElementById('soDrawerStatusBadge').innerHTML =
      '<span class="so-badge ' + meta.cls + '">' + meta.label + '</span>';
    document.getElementById('soDrawerRosterBadge').textContent = '';

    document.getElementById('soDrawerLoading').classList.remove('d-none');
    document.getElementById('soDrawerContent').classList.add('d-none');
    drawer.show();

    fetch(profileUrlBase + '/' + encodeURIComponent(sid) + '?date=' + encodeURIComponent(date))
      .then(function(r){ return r.json(); })
      .then(renderProfile)
      .catch(function(){
        document.getElementById('soDrawerLoading').innerHTML = '<div class="text-danger-600 text-sm">Gagal memuat profil operator.</div>';
      });
  }

  function renderProfile(profile) {
    document.getElementById('soDrawerLoading').classList.add('d-none');
    document.getElementById('soDrawerContent').classList.remove('d-none');

    var roster = profile.roster || {};
    if (roster.hari_ke !== null && roster.hari_ke !== undefined) {
      document.getElementById('soDrawerRosterBadge').textContent = 'Hari ke-' + roster.hari_ke + (roster.shift ? ' · Shift ' + roster.shift : '');
    }

    var illnessEl = document.getElementById('soDrawerIllnessBanner');
    if (profile.criticalIllness && profile.criticalIllness.has_critical_illness) {
      var followed = profile.criticalIllness.followed_up;
      illnessEl.classList.remove('d-none');
      illnessEl.innerHTML =
        '<div class="' + (followed ? 'bg-success-100 text-success-600 border-success-100' : 'bg-danger-100 text-danger-600 border-danger-100') + ' border px-16 py-13 rounded-8 d-flex gap-2 align-items-start text-sm">' +
          '<iconify-icon icon="solar:heart-pulse-bold" class="icon text-lg flex-shrink-0 mt-2"></iconify-icon>' +
          '<span>Riwayat penyakit kritis terkonfirmasi <b>' + escapeHtml(profile.criticalIllness.confirmed_date || '-') + '</b>. ' +
          (followed ? 'Sudah ada Fatigue Test follow-up sejak tanggal itu.' : '<b>Belum ada Fatigue Test follow-up</b> sejak tanggal itu.') +
          '</span></div>';
    } else {
      illnessEl.classList.add('d-none');
      illnessEl.innerHTML = '';
    }

    // Grafik tren Fatigue Test personal + pita baseline
    var history = profile.fatigueHistory || [];
    var baseline = profile.baseline;
    var chartEl = document.getElementById('soDrawerTrendChart');
    var noteEl = document.getElementById('soDrawerBaselineNote');
    if (currentChart) { currentChart.destroy(); currentChart = null; }

    if (history.length === 0) {
      chartEl.innerHTML = '<div class="text-secondary-light text-sm text-center py-24">Belum ada riwayat Fatigue Test pada 30 hari terakhir.</div>';
      noteEl.textContent = '';
    } else if (typeof ApexCharts !== 'undefined') {
      var categories = history.map(function(h){ return h.date.slice(5); });
      var scores = history.map(function(h){ return h.score; });
      var annotations = { yaxis: [] };
      if (baseline) {
        annotations.yaxis.push({
          y: baseline.mean, borderColor: '#9CA3AF', strokeDashArray: 4,
          label: { text: 'Baseline ' + baseline.mean, style: { background: '#9CA3AF', color: '#fff', fontSize: '10px' } }
        });
        noteEl.textContent = 'Baseline dihitung dari ' + baseline.n + ' tes sebelumnya: rata-rata ' + baseline.mean + ', deviasi ' + baseline.std + '.';
      } else {
        noteEl.textContent = 'Riwayat belum cukup (minimal 5 tes) untuk menghitung baseline personal.';
      }

      currentChart = new ApexCharts(chartEl, {
        chart: { height: 200, type: 'line', toolbar: { show:false } },
        series: [{ name: 'Skor Kesiapan', data: scores }],
        colors: ['#487FFF'],
        stroke: { curve: 'smooth', width: 2.5 },
        markers: { size: 3 },
        xaxis: { categories: categories, labels: { style: { fontSize: '10px' } } },
        yaxis: { min: 0, max: 10 },
        annotations: annotations,
        grid: { borderColor: '#E5E7EB', strokeDashArray: 4 },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: function(v){ return v + '/10'; } } }
      });
      currentChart.render();
    }

    // Riwayat PVT
    var pvtListEl = document.getElementById('soDrawerPvtList');
    if (!profile.pvtHistory || profile.pvtHistory.length === 0) {
      pvtListEl.innerHTML = '<div class="text-secondary-light text-sm text-center py-16">Tidak ada riwayat PVT pada 30 hari terakhir.</div>';
    } else {
      pvtListEl.innerHTML = profile.pvtHistory.map(function(p){
        var meta = pvtStatusMeta[p.status] || pvtStatusMeta.belum;
        return '<div class="d-flex align-items-center justify-content-between border rounded-8 px-12 py-8">' +
          '<div><div class="text-sm fw-medium">' + escapeHtml(p.tested_at) + '</div>' +
          (p.mean_rt_ms !== null ? '<div class="text-xs text-secondary-light">Mean RT ' + p.mean_rt_ms + 'ms &middot; Lapses ' + (p.lapses ?? '-') + '</div>' : '') + '</div>' +
          '<span class="' + meta.cls + ' px-10 py-4 rounded-pill fw-medium text-xs">' + meta.label + '</span>' +
          '</div>';
      }).join('');
    }

    // Riwayat alert
    var listEl = document.getElementById('soDrawerAlertList');
    var summaryEl = document.getElementById('soDrawerAlertSummary');
    var s = profile.alertSummary || { nyata:0, palsu:0, belum:0, total:0 };
    summaryEl.textContent = s.total + ' alert · ' + s.nyata + ' true alert · ' + s.belum + ' belum diperiksa';

    if (!profile.alertTimeline || profile.alertTimeline.length === 0) {
      listEl.innerHTML = '<div class="text-secondary-light text-sm text-center py-16">Tidak ada alert fatigue pada 30 hari terakhir.</div>';
    } else {
      listEl.innerHTML = profile.alertTimeline.map(function(a){
        var meta = alertStatusMeta[a.status] || alertStatusMeta.belum;
        return '<div class="d-flex align-items-center justify-content-between border rounded-8 px-12 py-8">' +
          '<div><div class="text-sm fw-medium">' + escapeHtml(a.name) + '</div><div class="text-xs text-secondary-light">' + escapeHtml(a.date) + '</div></div>' +
          '<span class="' + meta.cls + ' px-10 py-4 rounded-pill fw-medium text-xs">' + meta.label + '</span>' +
          '</div>';
      }).join('');
    }
  }

  document.addEventListener('click', function(e){
    var tlBtn = e.target.closest('.so-tl-btn');
    if (tlBtn && tlModal) {
      document.getElementById('soTlSid').textContent = tlBtn.getAttribute('data-sid');
      document.getElementById('soTlNama').textContent = tlBtn.getAttribute('data-nama');
      document.getElementById('soTlCatatan').value = '';
      tlModal.show();
      return;
    }

    var row = e.target.closest('.so-row');
    if (row) {
      openDrawer(row.getAttribute('data-sid'));
    }
  });

  document.getElementById('soTableBody').addEventListener('keydown', function(e){
    var row = e.target.closest('.so-row');
    if (row && (e.key === 'Enter' || e.key === ' ')) {
      e.preventDefault();
      openDrawer(row.getAttribute('data-sid'));
    }
  });

  var tlSubmitBtn = document.getElementById('soTlSubmit');
  if (tlSubmitBtn) {
    tlSubmitBtn.addEventListener('click', function(){
      var sid = document.getElementById('soTlSid').textContent;
      var catatan = document.getElementById('soTlCatatan').value;
      tlSubmitBtn.disabled = true;
      fetch(tlUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ kode_sid: sid, date: date || new Date().toISOString().slice(0,10), catatan: catatan })
      }).then(function(r){ return r.json(); }).then(function(){
        tlModal.hide();
        fetchData();
      }).catch(function(){
        alert('Gagal menyimpan catatan. Coba lagi.');
      }).finally(function(){
        tlSubmitBtn.disabled = false;
      });
    });
  }

  fetchData();
  setInterval(fetchData, POLL_MS);
})();
</script>
@endsection
