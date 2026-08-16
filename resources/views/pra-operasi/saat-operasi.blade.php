@extends('dms.layouts.app')

@section('title', 'Saat Operasi - Live Monitoring')

@section('css')
<style>
  .so-live-dot { display:inline-block; width:8px; height:8px; background:#22c55e; border-radius:50%; margin-right:6px; animation: soPulse 2s ease-in-out infinite; vertical-align:middle; }
  @keyframes soPulse { 0%,100% { opacity:1; } 50% { opacity:.4; } }
  .so-warn-banner{background:var(--warning-100,#fff3e0);border:1px solid var(--warning-200,#ffe0b2);color:var(--warning-600,#b45309);
    border-radius:10px;padding:12px 16px;font-size:13px;display:flex;gap:10px;align-items:flex-start;}
  .so-redflag-panel{ border:1px dashed var(--danger-main); background:rgba(239,74,0,0.05); border-radius:10px; padding:16px; margin-bottom:24px; }
  .so-card { border-radius:10px; border:1px solid var(--neutral-200,#e5e7eb); padding:14px 16px; height:100%; border-left-width:4px; border-left-style:solid; background:#fff; }
  .so-card.st-fit { border-left-color:#45B369; }
  .so-card.st-fit_pantau { border-left-color:#45B369; }
  .so-card.st-perlu_perhatian { border-left-color:#FF9F29; }
  .so-card.st-tarik { border-left-color:#EF4A00; background:rgba(239,74,0,0.03); }
  .so-badge { font-size:11px; font-weight:600; padding:4px 10px; border-radius:999px; display:inline-block; }
  .so-badge.st-fit, .so-badge.st-fit_pantau { background:#e8f8ee; color:#0f7a3d; }
  .so-badge.st-perlu_perhatian { background:#fff3e0; color:#b45309; }
  .so-badge.st-tarik { background:#fde8e0; color:#b91c1c; }
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
      <div class="card-body">
        <div class="row gy-3" id="soCardGrid"></div>
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
@endsection

@section('page-scripts')
<script>
(function(){
  var dataUrl = @json(route('pra-operasi.saat-operasi.data'));
  var date = @json(request()->query('date'));
  var POLL_MS = 20000;

  var statusMeta = {
    fit: { label: 'Fit', cls: 'st-fit' },
    fit_pantau: { label: 'Fit (Pantau)', cls: 'st-fit_pantau' },
    perlu_perhatian: { label: 'Perlu Perhatian', cls: 'st-perlu_perhatian' },
    tarik: { label: 'Tarik dari Unit', cls: 'st-tarik' }
  };
  var tierLabel = { hijau: 'Hijau', kuning: 'Kuning', merah: 'Merah' };

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

  function operatorCard(op) {
    var meta = statusMeta[op.status] || statusMeta.perlu_perhatian;
    var tier = op.fatigue_tier ? tierLabel[op.fatigue_tier] : 'Belum Tes';
    var needsAction = op.status === 'tarik' || op.status === 'perlu_perhatian';
    var actionHtml = '';
    if (needsAction) {
      if (op.sudah_ditindaklanjuti) {
        actionHtml = '<div class="mt-8 text-xs text-success-600 d-flex align-items-center gap-1">' +
          '<iconify-icon icon="solar:check-circle-bold"></iconify-icon>Ditindaklanjuti' +
          (op.catatan_tindak_lanjut && op.catatan_tindak_lanjut.ditandai_pada ? ' (' + escapeHtml(op.catatan_tindak_lanjut.ditandai_pada) + ')' : '') +
          '</div>';
      } else {
        actionHtml = '<button type="button" class="btn btn-outline-danger btn-sm w-100 mt-8 so-tl-btn" data-sid="' + escapeHtml(op.kode_sid) + '" data-nama="' + escapeHtml(op.nama) + '">Tandai Ditindaklanjuti</button>';
      }
    }
    return '<div class="col-xxl-3 col-md-4 col-sm-6">' +
      '<div class="so-card ' + meta.cls + '">' +
        '<div class="d-flex align-items-start justify-content-between mb-8">' +
          '<div><span class="fw-semibold d-block">' + escapeHtml(op.nama) + '</span><span class="text-xs text-secondary-light">' + escapeHtml(op.kode_sid) + '</span></div>' +
          '<span class="so-badge ' + meta.cls + '">' + meta.label + '</span>' +
        '</div>' +
        '<div class="text-xs text-secondary-light mb-8">' + escapeHtml(op.perusahaan) + '</div>' +
        '<div class="d-flex justify-content-between text-sm mb-4"><span class="text-secondary-light">Fatigue Test Pagi</span><span class="fw-medium">' + tier + (op.fatigue_score !== null ? ' (' + op.fatigue_score + '/10)' : '') + '</span></div>' +
        '<div class="d-flex justify-content-between text-sm"><span class="text-secondary-light">Alert Hari Ini</span><span class="fw-medium">' +
          '<span class="text-danger-600">' + op.alert_nyata + '</span> nyata &middot; <span class="text-warning-600">' + op.alert_belum + '</span> belum diperiksa</span></div>' +
        actionHtml +
      '</div>' +
    '</div>';
  }

  function renderRedFlags(redFlags) {
    var wrap = document.getElementById('soRedFlagWrap');
    if (!redFlags || !redFlags.length) { wrap.innerHTML = ''; return; }
    wrap.innerHTML = '<div class="so-redflag-panel">' +
      '<div class="d-flex align-items-center gap-2 mb-12"><iconify-icon icon="solar:flag-bold" class="text-danger-600 text-xl"></iconify-icon>' +
      '<h6 class="mb-0 text-danger-600">Red Flag Proses &mdash; Fatigue Test Merah/Belum Tapi Tetap Beroperasi</h6></div>' +
      '<p class="text-sm text-secondary-light mb-12">Operator ini seharusnya sudah dicegat di Pra Operasi pagi tadi &mdash; ini indikasi kegagalan kontrol, bukan cuma risiko rutin.</p>' +
      '<div class="row gy-3">' + redFlags.map(operatorCard).join('') + '</div>' +
    '</div>';
  }

  var alertStatusMeta2 = {
    nyata: { label: 'Nyata', cls: 'bg-danger-focus text-danger-main' },
    palsu: { label: 'Palsu', cls: 'bg-neutral-200 text-neutral-600' },
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

    var nonRedFlagCards = (payload.cards || []).filter(function(c){ return !c.is_red_flag; });
    document.getElementById('soCardCount').textContent = (payload.cards || []).length + ' operator';
    var grid = document.getElementById('soCardGrid');
    if (!nonRedFlagCards.length) {
      grid.innerHTML = '<div class="col-12 text-center text-secondary-light py-40">Tidak ada operator lain yang sedang beroperasi saat ini.</div>';
    } else {
      grid.innerHTML = nonRedFlagCards.map(operatorCard).join('');
    }
  }

  function fetchData() {
    var url = dataUrl + (date ? ('?date=' + encodeURIComponent(date)) : '');
    fetch(url).then(function(r){ return r.json(); }).then(render).catch(function(){});
  }

  // Modal "Tandai Ditindaklanjuti" — event delegation karena kartu dirender ulang tiap poll.
  var tlModalEl = document.getElementById('soTindakLanjutModal');
  var tlModal = (typeof bootstrap !== 'undefined') ? new bootstrap.Modal(tlModalEl) : null;
  var tlUrl = @json(route('pra-operasi.saat-operasi.tindak-lanjut'));
  var csrfToken = document.querySelector('meta[name="csrf-token"]');
  csrfToken = csrfToken ? csrfToken.getAttribute('content') : '';

  document.addEventListener('click', function(e){
    var btn = e.target.closest('.so-tl-btn');
    if (!btn || !tlModal) return;
    document.getElementById('soTlSid').textContent = btn.getAttribute('data-sid');
    document.getElementById('soTlNama').textContent = btn.getAttribute('data-nama');
    document.getElementById('soTlCatatan').value = '';
    tlModal.show();
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
