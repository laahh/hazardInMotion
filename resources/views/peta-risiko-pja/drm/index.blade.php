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

@include('peta-risiko-pja.drm._beranda-style')

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
        <button type="button" role="tab" data-tab="periode" aria-selected="false">Identifikasi per periode</button>
        <button type="button" role="tab" data-tab="pja" aria-selected="false">Semua PJA</button>
      </nav>
    </div>
    <div class="dr-bar">
      <label data-ctl="grup" hidden>Group Lokasi <select id="dr-grup"></select></label>
      <label data-ctl="proses" hidden>Proses bisnis <select id="dr-proses"></select></label>
      <label>Site / scope <select id="dr-site"><option value="ALL">Semua site</option></select></label>
      <label data-ctl="w" hidden>Periode <select id="dr-w"></select></label>
      <label data-ctl="pja" hidden>Prioritas <select id="dr-pri"><option value="all">Semua</option><option value="T">Tinggi</option><option value="S">Sedang</option><option value="R">Rendah</option><option value="K">Volume kecil</option></select></label>
      <label data-ctl="pja" hidden>Urutkan <select id="dr-urut"><option value="skor">Skor prioritas</option><option value="gap">% gap</option><option value="gapn">Jumlah baris gap</option><option value="none">% tanpa laporan</option><option value="lewat">Lewat janji</option><option value="nama">Nama</option></select></label>
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

  <div id="dr-panel-periode" class="br-root" style="margin-top:20px" hidden></div>
  <div id="dr-panel-pja" class="br-root" style="margin-top:20px" hidden></div>

  <div class="pr-foot">
    <p>Pemetaan HIRA ↔ SAP berbasis kata kunci (kamus 29 tema, Master A/B/C/D DRM 2.1: label Hazard/Inspeksi → bahaya, Observasi/OAK → aktivitas, butir checklist OAK → bahaya) dan belum diuji silang pakar. "Terkontrol" = terkontrol menurut catatan SAP, bukan bukti kondisi lapangan.</p>
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
  const state = { tab: q.get('tab') || 'site', site: q.get('site') || 'ALL', grup: q.get('grup') || '', proses: q.get('proses') || '', cari: '',
    w: q.has('w') ? Math.max(0, parseInt(q.get('w'), 10) || 0) : -1, pri: 'all', urut: 'skor', buka: {}, pbuka: {} };
  const TAB_W = ['pja', 'periode'];
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
    const mg = (M.beranda || {}).minggu || [];
    if (state.w < 0 || state.w >= mg.length) state.w = DR.wDefault(mg, M.sampai);
    $('#dr-w').innerHTML = mg.map((a, i) => `<option value="${i}"${i === state.w ? ' selected' : ''}>${DR.esc(DR.minggu(a, M.sampai, i, mg[0]).label)}</option>`).join('');
    $('#dr-w').addEventListener('change', (e) => { state.w = +e.target.value; renderW(); simpanUrl(); });
    $('#dr-pri').addEventListener('change', (e) => { state.pri = e.target.value; renderPja(); });
    $('#dr-urut').addEventListener('change', (e) => { state.urut = e.target.value; renderPja(); });
    render();
  }

  function simpanUrl() {
    const p = new URLSearchParams({ tab: state.tab, site: state.site });
    if (state.tab === 'grup') p.set('grup', state.grup);
    if (state.tab === 'proses') p.set('proses', state.proses);
    if (TAB_W.includes(state.tab)) p.set('w', state.w);
    history.replaceState(null, '', '?' + p.toString());
  }

  function render() {
    document.querySelectorAll('.dr-tabs button').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.tab === state.tab)));
    document.querySelector('[data-ctl="grup"]').hidden = state.tab !== 'grup';
    document.querySelectorAll('[data-ctl="pja"]').forEach((e) => { e.hidden = state.tab !== 'pja'; });
    document.querySelector('[data-ctl="w"]').hidden = !TAB_W.includes(state.tab);
    document.querySelector('[data-ctl="proses"]').hidden = state.tab !== 'proses';
    $('#dr-panel-cakupan').hidden = TAB_W.includes(state.tab);
    $('#dr-panel-pja').hidden = state.tab !== 'pja';
    $('#dr-panel-periode').hidden = state.tab !== 'periode';
    $('#dr-ringkas').hidden = TAB_W.includes(state.tab);
    const kunci = state.tab === 'grup' ? `GRUP|${state.grup}|${state.site}`
      : state.tab === 'proses' ? `PROSES|${state.proses}|${state.site}` : `SITE|${state.site}`;
    const c = M.cakupan[kunci] || { kpi: { total: 0, final: {} }, tren: {}, mAwal: kosong(), mFinal: kosong() };
    $('#dr-kpis').innerHTML = DR.kpiCards(c.kpi);
    if (TAB_W.includes(state.tab)) { renderW(); simpanUrl(); return; }
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

  // ------------------------------------------------------------ Per PJA (konsep Beranda PJA, satuan ketat)
  const LV_PJA = ['Significant', 'High'];
  const PRI = { T: ['Tinggi', 'var(--red)'], S: ['Sedang', '#C28F12'], R: ['Rendah', '#1F9A3A'], K: ['Volume kecil', '#8A939C'] };
  const TEMUAN_MIN = 20;
  const pctI = (a, b) => (b ? DR.pct(a, b) : '–');

  // Snapshot dari kode lama: ringkasan PJA belum satuan ketat (tanpa kolom "tot") atau tanpa statistik temuan.
  const formatBaru = () => M.beranda && M.beranda.mgmt && (M.beranda.kolomMgmt || []).includes('tot') && M.pja.every((p) => Array.isArray(p.tm));
  const pesanLama = () => '<div class="pr-card"><div class="pr-empty">Snapshot masih format lama (dihitung ' + DR.esc(String(M.dibuat || '').slice(0, 16).replace('T', ' ')) + '). Jalankan ulang <code>php artisan peta-risiko:sync</code> agar tab ini terisi.</div></div>';
  const wKode = (i) => DR.minggu(M.beranda.minggu[i], M.sampai, i, M.beranda.minggu[0]).kode;
  const wBerjalan = (i) => i === 0 && !DR.penuh0(M.beranda.minggu[0], M.sampai);
  function renderW() { if (state.tab === 'periode') renderPeriode(); else renderPja(); }

  // ------------------------------------------------------------ Identifikasi per periode (satuan ketat per minggu)
  const KOSONG = { t: 0, full: 0, part: 0, gapo: 0, none: 0, g2: 0, g3: 0, g4: 0 };

  /** Komposisi satu PJA pada minggu w (Significant + High). */
  function vekPja(p, w) {
    const K = M.beranda.kolomMgmt, ix = Object.fromEntries(K.map((k, i) => [k, i])), c = Array(K.length).fill(0);
    LV_PJA.forEach((lv) => (((p.w || [])[w] || {})[lv] || []).forEach((v, i) => { c[i] += v; }));
    return { t: c[ix.tot], full: c[ix.full], part: c[ix.part], gapo: c[ix.gaponly], none: c[ix.none], g2: c[ix.closed], g3: c[ix.ulang], g4: c[ix.stop] };
  }
  const tambahVek = (a, b) => Object.fromEntries(Object.keys(KOSONG).map((k) => [k, a[k] + b[k]]));
  const sumVek = (L, w) => L.reduce((a, p) => tambahVek(a, vekPja(p, w)), KOSONG);
  const gapTxt = (v) => `belum closed ${DR.fmt(v.g2)}, berulang ${DR.fmt(v.g3)}, OAK Stop ${DR.fmt(v.g4)}`;

  /** Perubahan proporsi a/ta terhadap minggu lalu b/tb, dalam poin persen. */
  function delta(a, ta, b, tb, naikBaik) {
    if (!ta || !tb) return '';
    const d = 100 * (a / ta - b / tb);
    if (Math.abs(d) < 0.05) return '<span class="dr-mute">tetap</span>';
    const baik = naikBaik ? d > 0 : d < 0;
    return `<span style="color:${baik ? 'var(--green-d)' : 'var(--red)'}">${d > 0 ? '▲' : '▼'} ${Math.abs(d).toLocaleString('id-ID', { maximumFractionDigits: 1 })} poin</span>`;
  }

  function renderPeriode() {
    const panel = $('#dr-panel-periode');
    if (!formatBaru()) { panel.innerHTML = pesanLama(); return; }
    const P = M.beranda.mgmt.filter((p) => state.site === 'ALL' || p.site === state.site), NW = M.beranda.minggu.length, w = state.w;
    const V = sumVek(P, w), V0 = w + 1 < NW ? sumVek(P, w + 1) : null, W = DR.BW, wk = wKode(w);
    const kartu = (ac, kc, judul, a, naikBaik) => `<div class="br-kpi" style="--ac:${ac};--kc:${kc}"><small>${judul}</small><b>${pctI(V[a], V.t)}</b><em>${DR.fmt(V[a])} baris${V0 ? ' · ' + delta(V[a], V.t, V0[a], V0.t, naikBaik) + ' vs minggu lalu' : ''}</em></div>`;
    const lingkup = state.site === 'ALL' ? 'BC' : DR.esc(state.site);
    panel.innerHTML = `<div class="br-kpis">
        ${kartu(W.ok, 'var(--green-d)', `Terkontrol penuh · ${lingkup} · ${wk}`, 'full', true)}
        ${kartu(W.part, '#9A7300', 'Terkontrol sebagian', 'part', false)}
        ${kartu(W.bm, W.bm, 'Hanya laporan bermasalah', 'gapo', false)}
        ${kartu(W.none, W.bm, 'Tanpa laporan', 'none', false)}
      </div>
      <div class="pr-card"><div class="br-rhead"><b class="t">Komposisi per minggu · ${lingkup}</b><span class="br-sum"><span>${DR.fmt(V.t)} baris</span><span>${gapTxt(V)}</span></span></div>
        <div class="pr-card-body">${trenPeriode(P)}<div class="dr-legend">${[[W.ok, 'Terkontrol penuh'], [W.part, 'Sebagian'], [W.bm, 'Hanya bermasalah'], [W.none, 'Tanpa laporan']].map(([c, t]) => `<span><i style="--c:${c}"></i>${t}</span>`).join('')}</div></div></div>
      ${tabelSitePeriode(w)}${tabelKelPeriode(P, w)}${tabelPjaPeriode(P, w)}
      <p class="dr-mute" style="margin-top:8px">Baris site/BC = jumlah baris tingkat PJA (satu detail lokasi bisa dimiliki 2 PJA), bukan baris unik. Terkontrol menurut catatan SAP, bukan bukti kondisi lapangan.</p>`;
    panel.querySelectorAll('.br-wc').forEach((c) => c.addEventListener('click', () => { state.w = +c.dataset.w; $('#dr-w').value = state.w; renderPeriode(); simpanUrl(); }));
    panel.querySelectorAll('tr[data-site]').forEach((tr) => tr.addEventListener('click', () => {
      state.site = state.site === tr.dataset.site ? 'ALL' : tr.dataset.site; $('#dr-site').value = state.site; render();
    }));
    panel.querySelectorAll('tr.br-a[data-pid]').forEach((tr) => tr.addEventListener('click', () => { state.pbuka[tr.dataset.pid] = !state.pbuka[tr.dataset.pid]; renderPeriode(); }));
    panel.querySelectorAll('[data-buka]').forEach((a) => a.addEventListener('click', (e) => e.stopPropagation()));
  }

  function trenPeriode(P) {
    const W = DR.BW, NW = M.beranda.minggu.length, col = [];
    for (let i = NW - 1; i >= 0; i--) {
      const v = sumVek(P, i), n = Math.max(1, v.t), H = (x) => (100 * x / n).toFixed(1) + '%';
      col.push(`<div class="br-wc${i === state.w ? ' sel' : ''}" data-w="${i}" title="${DR.esc(DR.minggu(M.beranda.minggu[i], M.sampai, i, M.beranda.minggu[0]).label)}: penuh ${DR.fmt(v.full)}, sebagian ${DR.fmt(v.part)}, hanya bermasalah ${DR.fmt(v.gapo)}, tanpa laporan ${DR.fmt(v.none)}">
        <div class="stk"><i style="height:${H(v.none)};background:${W.none}"></i><i style="height:${H(v.gapo)};background:${W.bm}"></i><i style="height:${H(v.part)};background:${W.part}"></i><i style="height:${H(v.full)};background:${W.ok}"></i></div>
        <span>${wKode(i)}${wBerjalan(i) ? '*' : ''}</span><span>${pctI(v.full, v.t)}</span></div>`);
    }
    return `<div class="br-wb">${col.join('')}</div>${wBerjalan(0) ? '<div class="dr-mute" style="font-size:12px">* minggu berjalan, belum penuh</div>' : ''}`;
  }

  function barisVek(v, v0) {
    return `<td>${DR.sb4(v.full, v.part, v.gapo, v.none)}</td><td class="r">${DR.fmt(v.t)}</td><td class="r"><b>${pctI(v.full, v.t)}</b>${v0 ? '<br>' + delta(v.full, v.t, v0.full, v0.t, true) : ''}</td>
      <td class="r">${pctI(v.part, v.t)}</td><td class="r">${pctI(v.gapo, v.t)}</td><td class="r">${pctI(v.none, v.t)}</td>`;
  }
  const KEPALA_VEK = '<th style="min-width:200px">Komposisi</th><th class="r">Baris</th><th class="r">Terkontrol penuh</th><th class="r">Sebagian</th><th class="r">Hanya bermasalah</th><th class="r">Tanpa laporan</th>';

  function tabelSitePeriode(w) {
    const NW = M.beranda.minggu.length, semua = M.beranda.mgmt;
    const baris = [...M.sites.map((s) => [s, semua.filter((p) => p.site === s)]), ['BC', semua]].map(([s, L]) => {
      const v = sumVek(L, w);
      if (!v.t) return '';
      const atr = s === 'BC' ? ' style="font-weight:700;background:#F5F8FD"' : ` data-site="${DR.esc(s)}" class="br-a${state.site === s ? ' open' : ''}" style="cursor:pointer"`;
      return `<tr${atr}><td>${DR.esc(s)}</td><td class="r">${L.filter((p) => vekPja(p, w).t).length}</td>
        ${barisVek(v, w + 1 < NW ? sumVek(L, w + 1) : null)}<td class="dr-mute">${gapTxt(v)}</td></tr>`;
    }).join('');
    return `<div class="pr-card"><div class="pr-card-head"><div><b>Site dan BC · ${wKode(w)}</b><small>Klik site untuk memfilter</small></div></div>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl"><thead><tr><th>Site</th><th class="r">PJA</th>${KEPALA_VEK}<th>Laporan bermasalah</th></tr></thead><tbody>${baris}</tbody></table></div></div>`;
  }

  function tabelKelPeriode(P, w) {
    const kelNama = Object.fromEntries([['X', 'Belum terpetakan'], ...M.proses.map((p) => [p.kode, p.nama])]), kel = {};
    const isi = (obj, f) => LV_PJA.forEach((lv) => Object.entries((obj || {})[lv] || {}).forEach(([k, v]) => f(kel[k] ||= { t: 0, g: 0, g0: 0 }, v)));
    P.forEach((p) => {
      isi(p.kelTot, (x, v) => { x.t += v; });
      isi((p.kelGap || [])[w], (x, v) => { x.g += v; });
      isi((p.kelGap || [])[w + 1], (x, v) => { x.g0 += v; });
    });
    const adaLalu = M.beranda.minggu.length > w + 1;
    const L = Object.entries(kel).filter(([, x]) => x.t).sort((a, b) => b[1].g / b[1].t - a[1].g / a[1].t || b[1].t - a[1].t);
    const baris = L.map(([k, x]) => `<tr><td>${DR.esc(kelNama[k] || k)}</td><td><div class="br-sb"><i style="width:${(100 * (x.t - x.g) / x.t).toFixed(1)}%;background:${DR.BW.ok}"></i><i style="width:${(100 * x.g / x.t).toFixed(1)}%;background:${DR.BW.bm}"></i></div></td>
      <td class="r">${DR.fmt(x.t)}</td><td class="r"><b>${pctI(x.t - x.g, x.t)}</b>${adaLalu ? '<br>' + delta(x.t - x.g, x.t, x.t - x.g0, x.t, true) : ''}</td><td class="r">${DR.fmt(x.g)}</td></tr>`).join('');
    return `<div class="pr-card"><div class="pr-card-head"><div><b>Kelompok aktivitas · ${wKode(w)}</b><small>Urut % gap tertinggi</small></div></div>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl"><thead><tr><th>Kelompok aktivitas</th><th style="min-width:160px">Terkontrol penuh / gap</th><th class="r">Baris</th><th class="r">Terkontrol penuh</th><th class="r">Gap</th></tr></thead>
      <tbody>${baris || '<tr><td colspan="5" class="pr-empty">Tidak ada baris.</td></tr>'}</tbody></table></div></div>`;
  }

  function tabelPjaPeriode(P, w) {
    const NW = M.beranda.minggu.length;
    const L = P.map((p) => ({ p, v: vekPja(p, w), v0: w + 1 < NW ? vekPja(p, w + 1) : null })).filter((o) => o.v.t)
      .sort((a, b) => (b.v.t - b.v.full) / b.v.t - (a.v.t - a.v.full) / a.v.t);
    const baris = L.map(({ p, v, v0 }) => {
      const id = String(p.id), buka = !!state.pbuka[id];
      const utama = `<tr class="br-a${buka ? ' open' : ''}" data-pid="${DR.esc(id)}" style="cursor:pointer"><td><button class="br-cr" type="button" aria-expanded="${buka}" aria-label="Buka tren PJA">▸</button><b>${DR.esc(p.nama)}</b><br><span class="dr-mute" style="padding-left:24px">${DR.esc(p.site)}</span></td>${barisVek(v, v0)}</tr>`;
      if (!buka) return utama;
      return utama + `<tr class="br-sub br-buka"><td colspan="7" style="padding:12px 16px 16px 26px">${trenPeriode([p])}
        <div class="dr-mute" style="margin:8px 0">${gapTxt(v)}</div><a class="pr-back" data-buka href="${URL_PJA.replace('__ID__', id)}?w=${w}">Buka di Beranda PJA ›</a></td></tr>`;
    }).join('');
    return `<div class="pr-card"><div class="pr-card-head"><div><b>PJA · ${wKode(w)}</b><small>${L.length} PJA · urut % tidak terkontrol penuh · klik baris untuk tren</small></div></div>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl" style="min-width:900px"><thead><tr><th style="min-width:260px">PJA</th>${KEPALA_VEK}</tr></thead>
      <tbody>${baris || '<tr><td colspan="7" class="pr-empty">Tidak ada PJA.</td></tr>'}</tbody></table></div></div>`;
  }

  /** Satu baris per PJA untuk minggu terpilih (risiko awal Significant + High), digabung dengan temuan open. */
  function dataPja() {
    const K = M.beranda.kolomMgmt, ix = Object.fromEntries(K.map((k, i) => [k, i]));
    const ring = Object.fromEntries(M.pja.map((p) => [String(p.id), p]));
    const kelNama = Object.fromEntries([['X', 'Belum terpetakan'], ...M.proses.map((p) => [p.kode, p.nama])]);
    const L = M.beranda.mgmt.map((p) => {
      const c = Array(K.length).fill(0), kel = {};
      LV_PJA.forEach((lv) => {
        (((p.w || [])[state.w] || {})[lv] || []).forEach((v, i) => { c[i] += v; });
        Object.entries((p.kelTot || {})[lv] || {}).forEach(([k, v]) => { (kel[k] ||= { t: 0, g: 0 }).t += v; });
        Object.entries((((p.kelGap || [])[state.w]) || {})[lv] || {}).forEach(([k, v]) => { (kel[k] ||= { t: 0, g: 0 }).g += v; });
      });
      const r = ring[String(p.id)] || {}, tm = r.tm || [0, 0, 0, 0], tot = c[ix.tot];
      return {
        id: String(p.id), nama: p.nama, site: p.site, ndl: p.ndl, personil: r.personil || 0, lama: p.lama, insiden: p.insiden,
        tot, full: c[ix.full], part: c[ix.part], gapo: c[ix.gaponly], none: c[ix.none], g2: c[ix.closed], g3: c[ix.ulang], g4: c[ix.stop],
        gr: tot ? (tot - c[ix.full]) / tot : 0, nr: tot ? c[ix.none] / tot : 0,
        op: tm[0], lewat: tm[1], lama30: tm[2], esk: tm[3],
        lr: tm[0] ? tm[1] / tm[0] : 0, l30r: tm[0] ? tm[2] / tm[0] : 0, er: tm[0] ? tm[3] / tm[0] : 0,
        kel: Object.entries(kel).filter(([, x]) => x.t).map(([k, x]) => ({ nama: kelNama[k] || k, t: x.t, g: x.g })).sort((a, b) => b.g - a.g || b.t - a.t),
      };
    });
    skor(L);
    return L;
  }

  /** Skor prioritas seperti prototipe: kuartil antar PJA (yang punya ≥20 temuan open) untuk % gap, % tanpa laporan, % lewat janji, % open lama. */
  function skor(L) {
    const besar = L.filter((o) => o.op >= TEMUAN_MIN);
    const kuartil = (f) => {
      const a = besar.map(f).sort((x, y) => x - y);
      const q = (p) => { if (!a.length) return Infinity; const i = (a.length - 1) * p, lo = Math.floor(i); return a[lo] + (a[Math.ceil(i)] - a[lo]) * (i - lo); };
      return [q(0.5), q(0.75)];
    };
    const Q = { gr: kuartil((o) => o.gr), nr: kuartil((o) => o.nr), lr: kuartil((o) => o.lr), l30r: kuartil((o) => o.l30r) };
    const nilai = (v, q) => (v >= q[1] ? 2 : v >= q[0] ? 1 : 0);
    L.forEach((o) => {
      o.skor = nilai(o.gr, Q.gr) + nilai(o.nr, Q.nr) + nilai(o.lr, Q.lr) + nilai(o.l30r, Q.l30r) + (o.er >= 0.2 ? 1 : 0);
      o.pri = o.op < TEMUAN_MIN && o.tot - o.full < 50 ? 'K' : o.skor >= 6 ? 'T' : o.skor >= 3 ? 'S' : 'R';
      o.atasGap = o.gr >= Q.gr[1];
      o.atasLewat = o.lewat > 0 && o.lr >= Q.lr[0];
      o.atasLama = o.lama30 > 0 && o.l30r >= Q.l30r[0];
    });
  }

  function saranPja(o) {
    const r = [];
    if (o.none) r.push(['var(--amber)', `<b>Tanpa laporan:</b> ${DR.fmt(o.none)} baris. Konfirmasi aktivitasnya; bila berjalan, jadwalkan OAK/OBS.`]);
    if (o.part) r.push([DR.BW.part, `<b>Terkontrol sebagian:</b> ${DR.fmt(o.part)} baris. Lengkapi pengawasan di detail lokasi yang kosong.`]);
    if (o.gapo) r.push(['var(--red)', `<b>Hanya bermasalah:</b> ${DR.fmt(o.gapo)} baris (belum closed ${o.g2}, berulang ${o.g3}, OAK Stop ${o.g4}).`]);
    if (o.atasGap) r.push(['var(--red)', `<b>% gap ${DR.pct(o.tot - o.full, o.tot)}</b> termasuk kuartil tertinggi antar PJA.`]);
    if (o.atasLewat) r.push(['var(--red)', `<b>Lewat janji:</b> ${DR.fmt(o.lewat)} temuan. Percepat penutupan.`]);
    if (o.atasLama) r.push(['#8A939C', `<b>Open > 30 hari:</b> ${DR.fmt(o.lama30)} temuan. Selesaikan atau eskalasi.`]);
    if (o.er >= 0.2) r.push(['var(--red)', `<b>Eskalasi ≥3:</b> ${DR.fmt(o.esk)} temuan. Perlu keputusan manajemen.`]);
    if (o.lama) r.push(['#8A939C', `<b>Tanpa laporan SAP ≥${M.beranda.lamaHari} hari:</b> ${o.lama} detail lokasi.`]);
    return r.length ? r : [[DR.BW.ok, 'Tidak ada sinyal gap kritis.']];
  }

  const jumlah = (L) => L.reduce((s, o) => ({ t: s.t + o.tot, full: s.full + o.full, part: s.part + o.part, gapo: s.gapo + o.gapo, none: s.none + o.none }), { t: 0, full: 0, part: 0, gapo: 0, none: 0 });

  function renderPja() {
    const panel = $('#dr-panel-pja');
    if (!formatBaru()) { panel.innerHTML = pesanLama(); return; }
    const semua = dataPja();
    const diSite = semua.filter((o) => state.site === 'ALL' || o.site === state.site);
    const tampil = diSite.filter((o) => (state.pri === 'all' || o.pri === state.pri) && (!state.cari || o.nama.toLowerCase().includes(state.cari)));
    const urut = {
      skor: (a, b) => b.skor - a.skor || (b.tot - b.full) - (a.tot - a.full), gap: (a, b) => b.gr - a.gr, gapn: (a, b) => (b.tot - b.full) - (a.tot - a.full),
      none: (a, b) => b.nr - a.nr, lewat: (a, b) => b.lewat - a.lewat, nama: (a, b) => a.nama.localeCompare(b.nama),
    }[state.urut];
    tampil.sort(urut);
    const B = jumlah(diSite), G = B.t - B.full, W = DR.BW, wk = wKode(state.w);
    const kpi = (ac, kc, judul, angka, isi) => `<div class="br-kpi" style="--ac:${ac};--kc:${kc}"><small>${judul}</small><b>${angka}</b><em>${isi}</em></div>`;

    panel.innerHTML = `<div class="br-kpis">
        ${kpi('var(--navy)', 'var(--navy)', `Baris baseline · ${state.site === 'ALL' ? 'seluruh BC' : DR.esc(state.site)} · ${wk}`, DR.fmt(B.t), `${diSite.length} PJA · Significant + High`)}
        ${kpi(W.ok, 'var(--green-d)', 'Terkontrol penuh', pctI(B.full, B.t), `${DR.fmt(B.full)} baris`)}
        ${kpi(W.part, '#9A7300', 'Terkontrol sebagian', pctI(B.part, B.t), `${DR.fmt(B.part)} baris · gap total ${pctI(G, B.t)}`)}
        ${kpi(W.none, W.bm, 'Tanpa laporan', pctI(B.none, B.t), `${DR.fmt(B.none)} baris${B.gapo ? ` · ${DR.fmt(B.gapo)} hanya bermasalah` : ''}`)}
      </div>`
      + tabelSite(semua, wk) + tabelDaftar(tampil, semua.length, wk);

    panel.querySelectorAll('tr[data-site]').forEach((tr) => tr.addEventListener('click', () => {
      state.site = state.site === tr.dataset.site ? 'ALL' : tr.dataset.site; $('#dr-site').value = state.site; render();
    }));
    panel.querySelectorAll('tr.br-a').forEach((tr) => tr.addEventListener('click', () => { state.buka[tr.dataset.id] = !state.buka[tr.dataset.id]; state.baru = tr.dataset.id; renderPja(); state.baru = ''; }));
    panel.querySelectorAll('[data-buka]').forEach((a) => a.addEventListener('click', (e) => e.stopPropagation()));
  }

  function tabelSite(semua, wk) {
    const baris = M.sites.map((s) => {
      const L = semua.filter((o) => o.site === s), v = jumlah(L);
      if (!v.t) return '';
      const pilih = state.site === s;
      return `<tr data-site="${DR.esc(s)}" class="br-a${pilih ? ' open' : ''}" style="cursor:pointer"><td><b>${DR.esc(s)}</b></td><td class="r">${L.length}</td>
        <td class="r">${L.filter((o) => o.pri === 'T').length || ''}</td><td>${DR.sb4(v.full, v.part, v.gapo, v.none)}</td>
        <td class="r">${pctI(v.full, v.t)}</td><td class="r"><b>${pctI(v.t - v.full, v.t)}</b></td><td class="r">${DR.fmt(v.part)}</td><td class="r">${DR.fmt(v.none)}</td></tr>`;
    }).join('');
    return `<div class="pr-card"><div class="pr-card-head"><div><b>Ringkasan per site · ${wk}</b><small>Klik site untuk memfilter</small></div></div>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl"><thead><tr><th>Site</th><th class="r">PJA</th><th class="r">Prioritas tinggi</th><th style="min-width:220px">Komposisi</th>
      <th class="r">Terkontrol penuh</th><th class="r">Gap</th><th class="r">Sebagian</th><th class="r">Tanpa laporan</th></tr></thead><tbody>${baris}</tbody></table></div></div>`;
  }

  function tabelDaftar(L, total, wk) {
    const rows = L.map((o) => {
      const buka = !!state.buka[o.id];
      const utama = `<tr class="br-a${buka ? ' open' : ''}" data-id="${o.id}" style="cursor:pointer">
        <td><button class="br-cr" type="button" aria-expanded="${buka}" aria-label="Buka rincian PJA">▸</button><b>${DR.esc(o.nama)}</b><br><span class="dr-mute" style="padding-left:24px">${DR.esc(o.site)} · ${o.ndl} detail lokasi · ${DR.fmt(o.personil)} personil</span></td>
        <td>${o.tot ? DR.sb4(o.full, o.part, o.gapo, o.none) + `<span class="dr-mute">${DR.fmt(o.tot)} baris</span>` : '<span class="dr-mute">tanpa baris baseline</span>'}</td>
        <td class="r">${pctI(o.full, o.tot)}</td><td class="r"><b>${pctI(o.tot - o.full, o.tot)}</b></td><td class="r">${DR.fmt(o.part)}</td><td class="r">${DR.fmt(o.none)}</td>
        <td class="r"><b>${DR.fmt(o.op)}</b></td><td class="r">${DR.fmt(o.lewat)}<br><span class="dr-mute">${pctI(o.lewat, o.op)}</span></td>
        <td class="r">${DR.fmt(o.lama30)}<br><span class="dr-mute">${pctI(o.lama30, o.op)}</span></td>
        <td class="r">${o.insiden ? `<b class="br-red">${o.insiden}</b>` : ''}</td>
        <td><span class="br-pill" style="background:${PRI[o.pri][1]}">${PRI[o.pri][0]}</span><br><span class="dr-mute">skor ${o.skor}</span></td></tr>`;
      return utama + (buka ? rincian(o) : '');
    }).join('');
    return `<div class="pr-card"><div class="pr-card-head"><div><b>Semua PJA · ${wk}</b><small>${L.length} dari ${total} PJA · klik baris untuk rincian</small></div></div>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl" style="min-width:1200px"><thead><tr><th style="min-width:260px">PJA</th><th style="min-width:200px">Komposisi</th>
      <th class="r">Terkontrol penuh</th><th class="r">Gap</th><th class="r">Sebagian</th><th class="r">Tanpa laporan</th>
      <th class="r">Open</th><th class="r">Lewat janji</th><th class="r">Open &gt; 30 hari</th><th class="r">Insiden ${M.beranda.hari} hr</th><th>Prioritas</th></tr></thead>
      <tbody>${rows || '<tr><td colspan="11" class="pr-empty">Tidak ada PJA yang cocok.</td></tr>'}</tbody></table></div></div>`;
  }

  function rincian(o) {
    const kel = o.kel.slice(0, 12).map((k) => `<tr><td>${DR.esc(k.nama)}</td><td class="r">${DR.fmt(k.t)}</td><td class="r">${DR.fmt(k.g)}</td><td class="r"><b>${pctI(k.t - k.g, k.t)}</b></td></tr>`).join('');
    const saran = saranPja(o).map(([c, t], i) => `<div class="br-ti" style="--c:${c}"><span class="br-tn">${i + 1}</span><div>${t}</div></div>`).join('');
    return `<tr class="br-sub${state.baru === o.id ? ' br-buka' : ''}"><td colspan="11" style="padding:12px 16px 16px 26px"><div class="br-two">
        <div><span class="br-h">Kelompok aktivitas</span><table class="pr-tbl dr-tbl"><thead><tr><th>Kelompok</th><th class="r">Baris</th><th class="r">Gap</th><th class="r">Terkontrol penuh</th></tr></thead>
          <tbody>${kel || '<tr><td colspan="4" class="pr-empty">Tidak ada baris.</td></tr>'}</tbody></table></div>
        <div><span class="br-h">Tindak lanjut</span><div class="br-tis" style="grid-template-columns:1fr">${saran}</div>
          <a class="pr-back" style="margin-top:12px" data-buka href="${URL_PJA.replace('__ID__', o.id)}?w=${state.w}">Buka di Beranda PJA ›</a></div>
      </div></td></tr>`;
  }
})();
</script>
@endif
@endsection
