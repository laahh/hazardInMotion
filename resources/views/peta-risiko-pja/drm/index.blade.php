@extends('peta-risiko-pja._layout')

@section('title', 'Peta Risiko PJA')

@section('hero-aksi')
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    @if ($adaExcel)
      <a class="pr-back" href="{{ route('peta-risiko-pja.unduh.mapping') }}">⬇ Unduh Excel mapping</a>
    @endif
    <a class="pr-back" href="{{ route('peta-risiko-pja.arsip.index') }}">Versi lama (impor)</a>
  </div>
@endsection

@section('hero')
  <p class="pr-kicker">HSE · Peta Risiko PJA · DRM 2.1</p>
  <h1 class="pr-title">Bahaya-risiko HIRA yang sudah teridentifikasi oleh SAP</h1>
  <p class="pr-sub">
    @if ($meta)
      Data sampai {{ \Carbon\Carbon::parse($meta['sampai'])->translatedFormat('d M Y') }} · sinyal SAP {{ $meta['jendela'] }} hari terakhir ·
      dihitung {{ \Carbon\Carbon::parse($meta['dibuat'])->translatedFormat('d M Y H:i') }}
    @else
      Snapshot belum dibuat
    @endif
  </p>
@endsection

@section('content')
@if (! $meta)
  <div class="pr-card"><div class="pr-empty">
    Jalankan <code>php artisan peta-risiko:sync</code> di server yang terhubung ke ClickHouse OBDS, lalu muat ulang halaman ini.
  </div></div>
@else
  <div class="pr-card">
    <div class="pr-card-body" style="padding-bottom:0">
      <nav class="dr-tabs" role="tablist" aria-label="Sudut pandang">
        <button type="button" role="tab" data-tab="site" aria-selected="true">Per Site</button>
        <button type="button" role="tab" data-tab="grup" aria-selected="false">Per Group Lokasi</button>
        <button type="button" role="tab" data-tab="proses" aria-selected="false">Per Proses Bisnis</button>
        <button type="button" role="tab" data-tab="pja" aria-selected="false">Per PJA</button>
      </nav>
    </div>
    <div class="dr-bar">
      <label data-ctl="grup" hidden>Group Lokasi <select id="dr-grup"></select></label>
      <label data-ctl="proses" hidden>Proses bisnis <select id="dr-proses"></select></label>
      <label>Site / scope <select id="dr-site"><option value="ALL">Semua site</option></select></label>
      <label data-ctl="pja" hidden><input type="search" class="pr-search" id="dr-cari" placeholder="Cari nama PJA" aria-label="Cari nama PJA"></label>
    </div>
    <div id="dr-ringkas">
      <div class="dr-kpis" id="dr-kpis"></div>
    </div>
  </div>

  <div id="dr-panel-cakupan">
    <div class="pr-card">
      <div class="pr-card-head"><div><b>Matriks 5x5 — jumlah bahaya-risiko unik</b>
        <small>Grain aktivitas – sub aktivitas – bahaya. Final: terkontrol menurut SAP memakai risiko residual HIRA, selain itu tetap risiko awal.</small></div></div>
      <div class="pr-card-body dr-two" id="dr-matriks"></div>
    </div>

    <div class="pr-card">
      <div class="pr-card-head"><div><b>Tren mingguan</b><small>Status pada akhir tiap minggu (jendela {{ $meta['jendela'] }} hari)</small></div></div>
      <div class="pr-card-body"><div id="dr-tren"></div></div>
      <div id="dr-legenda"></div>
    </div>

    <div class="pr-card">
      <div class="pr-card-head"><div><b>Evaluasi per Group Lokasi</b><small id="dr-grup-sub">Klik baris untuk melihat Group Lokasi itu</small></div></div>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl" id="dr-tbl-grup"></table></div>
    </div>

    <div class="pr-card">
      <div class="pr-card-head"><div><b>Evaluasi per Proses Bisnis</b><small>11 proses inti + 6 pendukung. Klik baris untuk melihat proses itu.</small></div></div>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl" id="dr-tbl-proses"></table></div>
    </div>
  </div>

  <div id="dr-panel-pja" hidden>
    <div class="pr-card">
      <div class="pr-card-head"><div><b>Daftar PJA</b><small>PJA BC pemilik area; angka bahaya-risiko unik di detail lokasi PJA. Klik untuk detail.</small></div></div>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl" id="dr-tbl-pja"></table></div>
    </div>
  </div>

  <div class="pr-foot">
    <p>Pemetaan HIRA ↔ SAP berbasis kata kunci (kamus 29 tema, Master A/B DRM 2.1) dan belum diuji silang pakar. "Terkontrol" = terkontrol menurut SAP, bukan bukti efektivitas lapangan.</p>
    <p>"Tidak teridentifikasi" bukan berarti aman. Satu detail lokasi bisa dimiliki beberapa PJA: jangan menjumlahkan angka antar PJA.</p>
  </div>
@endif
@endsection

@section('scripts')
@include('peta-risiko-pja.drm._ui')
@if ($meta)
<script>
(() => {
  const URL_META = @json(route('peta-risiko-pja.meta'));
  const URL_PJA = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.pja', ['pja' => '__ID__'])) }};
  const $ = (s) => document.querySelector(s);
  const q = new URLSearchParams(location.search);
  const state = { tab: q.get('tab') || 'site', site: q.get('site') || 'ALL', grup: q.get('grup') || '', proses: q.get('proses') || '', cari: '' };
  let M = null;

  fetch(URL_META, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((m) => { M = m; init(); })
    .catch(() => { $('#dr-kpis').innerHTML = '<div class="pr-empty">Gagal memuat data.</div>'; });

  function init() {
    $('#dr-site').innerHTML += M.sites.map((s) => `<option value="${DR.esc(s)}">${DR.esc(s)}</option>`).join('');
    $('#dr-grup').innerHTML = M.grup.map((g) => `<option value="${DR.esc(g)}">${DR.esc(g)}</option>`).join('');
    if (!state.grup) state.grup = M.grup[0];
    $('#dr-proses').innerHTML = M.proses.map((p) => `<option value="${DR.esc(p.kode)}">${DR.esc(p.nama)}${p.jenis === 'inti' ? '' : ' (pendukung)'}</option>`).join('');
    if (!state.proses) state.proses = M.proses[0].kode;
    $('#dr-proses').value = state.proses;
    $('#dr-proses').addEventListener('change', (e) => { state.proses = e.target.value; render(); });
    $('#dr-site').value = state.site; $('#dr-grup').value = state.grup;
    $('#dr-legenda').innerHTML = DR.legendaStatus();
    document.querySelectorAll('.dr-tabs button').forEach((b) => b.addEventListener('click', () => { state.tab = b.dataset.tab; render(); }));
    $('#dr-site').addEventListener('change', (e) => { state.site = e.target.value; render(); });
    $('#dr-grup').addEventListener('change', (e) => { state.grup = e.target.value; render(); });
    $('#dr-cari').addEventListener('input', (e) => { state.cari = e.target.value.toLowerCase(); renderPja(); });
    render();
  }

  function simpanUrl() {
    const p = new URLSearchParams({ tab: state.tab, site: state.site });
    if (state.tab === 'grup') p.set('grup', state.grup);
    if (state.tab === 'proses') p.set('proses', state.proses);
    history.replaceState(null, '', '?' + p.toString());
  }

  function render() {
    document.querySelectorAll('.dr-tabs button').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.tab === state.tab)));
    document.querySelector('[data-ctl="grup"]').hidden = state.tab !== 'grup';
    document.querySelector('[data-ctl="pja"]').hidden = state.tab !== 'pja';
    document.querySelector('[data-ctl="proses"]').hidden = state.tab !== 'proses';
    $('#dr-panel-cakupan').hidden = state.tab === 'pja';
    $('#dr-panel-pja').hidden = state.tab !== 'pja';
    const kunci = state.tab === 'grup' ? `GRUP|${state.grup}|${state.site}`
      : state.tab === 'proses' ? `PROSES|${state.proses}|${state.site}` : `SITE|${state.site}`;
    const c = M.cakupan[kunci] || { kpi: { total: 0, final: {} }, tren: {}, mAwal: kosong(), mFinal: kosong() };
    $('#dr-kpis').innerHTML = DR.kpiCards(c.kpi);
    if (state.tab === 'pja') { renderPja(); simpanUrl(); return; }
    const akhir = M.sampai.split('-').reverse().join('/');
    $('#dr-matriks').innerHTML = DR.matriks('Baseline (BeHIRA, risiko awal)', c.mAwal, M) + DR.matriks(`Final (per ${akhir})`, c.mFinal, M);
    $('#dr-tren').innerHTML = DR.tren(c.tren);
    renderGrup();
    renderProses();
    simpanUrl();
  }

  function kosong() { return Array.from({ length: 5 }, () => [0, 0, 0, 0, 0]); }

  function renderGrup() {
    const dlGrup = M.grupDl[state.site] || {};
    const rows = M.grup.map((g) => ({ g, c: (M.cakupan[`GRUP|${g}|${state.site}`] || {}).kpi, dl: dlGrup[g] || 0 }))
      .filter((r) => r.c && r.c.total).sort((a, b) => b.c.total - a.c.total);
    $('#dr-tbl-grup').innerHTML = `<thead><tr><th>Group Lokasi</th><th class="r">Detail lokasi aktif</th><th class="r">Baseline</th><th>Komposisi</th>
      <th class="r">✓ Terkontrol</th><th class="r">! Ada gap</th><th class="r">○ Tidak teridentifikasi</th><th class="r">% teridentifikasi</th>
      <th class="r hide-sm">Low</th><th class="r hide-sm">Medium</th><th class="r hide-sm">High</th><th class="r hide-sm">Significant</th></tr></thead><tbody>`
      + rows.map(({ g, c, dl }) => {
        const f = c.final || {};
        const aktif = state.tab === 'grup' && g === state.grup;
        return `<tr data-grup="${DR.esc(g)}" style="cursor:pointer${aktif ? ';background:#F5F8FD' : ''}"${aktif ? ' aria-current="true"' : ''}>
          <td><span class="pr-name">${DR.esc(g)}</span></td><td class="r">${DR.fmt(dl)}</td><td class="r dr-num">${DR.fmt(c.total)}</td>
          <td>${DR.comp(c)}</td><td class="r">${DR.fmt(c.K)}</td><td class="r">${c.G ? DR.tag('G', ' ' + DR.fmt(c.G)) : 0}</td>
          <td class="r">${c.T ? DR.tag('T', ' ' + DR.fmt(c.T)) : 0}</td><td class="r">${DR.pct(c.K + c.G, c.total)}</td>
          <td class="r hide-sm">${DR.fmt(f.Low)}</td><td class="r hide-sm">${DR.fmt(f.Medium)}</td><td class="r hide-sm">${DR.fmt(f.High)}</td>
          <td class="r hide-sm" style="color:var(--red);font-weight:700">${DR.fmt(f.Significant)}</td></tr>`;
      }).join('') + '</tbody>';
    document.querySelectorAll('#dr-tbl-grup tr[data-grup]').forEach((tr) => tr.addEventListener('click', () => {
      state.tab = 'grup'; state.grup = tr.dataset.grup; $('#dr-grup').value = state.grup; render(); window.scrollTo({ top: 0, behavior: 'smooth' });
    }));
  }

  function renderProses() {
    const rows = M.proses.map((p) => ({ p, c: (M.cakupan[`PROSES|${p.kode}|${state.site}`] || {}).kpi })).filter((r) => r.c && r.c.total);
    $('#dr-tbl-proses').innerHTML = `<thead><tr><th>Proses bisnis</th><th>Jenis</th><th class="r">Baseline</th><th>Komposisi</th>
      <th class="r">✓ Terkontrol</th><th class="r">! Ada gap</th><th class="r">○ Tidak teridentifikasi</th><th class="r">% teridentifikasi</th>
      <th class="r hide-sm">Laten</th><th class="r hide-sm">Significant (final)</th></tr></thead><tbody>`
      + rows.map(({ p, c }) => {
        const aktif = state.tab === 'proses' && p.kode === state.proses;
        return `<tr data-proses="${DR.esc(p.kode)}" style="cursor:pointer${aktif ? ';background:#F5F8FD' : ''}">
          <td><span class="pr-name">${DR.esc(p.nama)}</span></td><td class="dr-mute">${p.jenis === 'inti' ? 'Inti' : 'Pendukung'}</td>
          <td class="r dr-num">${DR.fmt(c.total)}</td><td>${DR.comp(c)}</td><td class="r">${DR.fmt(c.K)}</td>
          <td class="r">${c.G ? DR.tag('G', ' ' + DR.fmt(c.G)) : 0}</td><td class="r">${c.T ? DR.tag('T', ' ' + DR.fmt(c.T)) : 0}</td>
          <td class="r">${DR.pct(c.K + c.G, c.total)}</td><td class="r hide-sm">${DR.fmt(c.laten)}</td>
          <td class="r hide-sm" style="color:var(--red);font-weight:700">${DR.fmt((c.final || {}).Significant)}</td></tr>`;
      }).join('') + '</tbody>';
    document.querySelectorAll('#dr-tbl-proses tr[data-proses]').forEach((tr) => tr.addEventListener('click', () => {
      state.tab = 'proses'; state.proses = tr.dataset.proses; $('#dr-proses').value = state.proses; render(); window.scrollTo({ top: 0, behavior: 'smooth' });
    }));
  }

  function renderPja() {
    const rows = M.pja.filter((p) => (state.site === 'ALL' || p.site === state.site) && (!state.cari || p.nama.toLowerCase().includes(state.cari)))
      .sort((a, b) => (b.kpi.G + b.kpi.T) - (a.kpi.G + a.kpi.T));
    $('#dr-tbl-pja').innerHTML = `<thead><tr><th>PJA</th><th class="r">Mitra</th><th class="r">Detail lokasi</th><th class="r">Aktivitas</th>
      <th class="r">Bahaya-risiko</th><th>Komposisi</th><th class="r">! Gap</th><th class="r">○ Tidak teridentifikasi</th>
      <th class="r hide-sm">Significant (final)</th><th class="r hide-sm">Temuan open</th><th class="r hide-sm">Insiden 12 bln</th></tr></thead><tbody>`
      + (rows.length ? rows.map((p) => `<tr data-href="${URL_PJA.replace('__ID__', p.id)}">
          <td><span class="pr-name">${DR.esc(p.nama)}</span><span class="pr-meta">${DR.esc(p.site)} · ${DR.fmt(p.personil)} personil</span></td>
          <td class="r">${p.mitra}</td><td class="r">${DR.fmt(p.dl)}</td><td class="r">${DR.fmt(p.aktivitas)}</td>
          <td class="r dr-num">${DR.fmt(p.kpi.total)}</td><td>${DR.comp(p.kpi)}</td>
          <td class="r">${DR.fmt(p.kpi.G)}</td><td class="r">${DR.fmt(p.kpi.T)}</td>
          <td class="r hide-sm">${DR.fmt(p.sig)}</td><td class="r hide-sm">${DR.fmt(p.temuan)}</td>
          <td class="r hide-sm">${p.insiden ? `<b style="color:var(--inc)">${p.insiden}</b>` : 0}</td></tr>`).join('')
        : '<tr><td colspan="11" class="pr-empty">Tidak ada PJA yang cocok.</td></tr>') + '</tbody>';
    document.querySelectorAll('#dr-tbl-pja tr[data-href]').forEach((tr) => tr.addEventListener('click', () => { location.href = tr.dataset.href; }));
  }
})();
</script>
@endif
@endsection
