@extends('peta-risiko-pja._layout-drm')

@section('title', 'Peta Risiko PJA')

@section('hero-aksi')
  @if ($adaExcel)
    <a class="pr-back" href="{{ route('peta-risiko-pja.unduh.mapping') }}"><span class="material-symbols-outlined" style="font-size:18px">download</span> Unduh Excel mapping</a>
  @endif
  <a class="pr-back" href="{{ route('peta-risiko-pja.master.index') }}"><span class="material-symbols-outlined" style="font-size:18px">tune</span> Master mapping</a>
  <a class="pr-back" href="{{ route('peta-risiko-pja.arsip.index') }}"><span class="material-symbols-outlined" style="font-size:18px">history</span> Versi lama</a>
@endsection

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Peta Risiko PJA</span></p>
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

@if ($meta)
  @section('nav')
    {{-- Tombol tab di bilah atas (konsep template dashboard-peer); JS memakai .dr-tabs button[data-tab]. --}}
    <nav class="dr-tabs tp-nav" role="tablist" aria-label="Sudut pandang">
      <button type="button" role="tab" data-tab="site" aria-selected="true">Overview</button>
      <button type="button" role="tab" data-tab="grup" aria-selected="false">Per Group Lokasi</button>
      <button type="button" role="tab" data-tab="proses" aria-selected="false">Per Kelompok Aktivitas</button>
      {{-- Tab "Identifikasi per periode" disembunyikan sementara (permintaan user); kodenya tetap ada. --}}
      <button type="button" role="tab" data-tab="periode" aria-selected="false" hidden>Identifikasi per periode</button>
      <button type="button" role="tab" data-tab="pja" aria-selected="false">Semua PJA</button>
      <button type="button" role="tab" data-tab="bpja" aria-selected="false">Beranda PJA</button>
    </nav>
  @endsection

  @section('header-aksi')
    <div class="dr-bar">
      <label data-ctl="grup" hidden>Group Lokasi <select id="dr-grup"></select></label>
      <label data-ctl="proses" hidden>Kelompok aktivitas <select id="dr-proses"></select></label>
      <label>Site <select id="dr-site"><option value="ALL">Semua site</option></select></label>
      <label><span class="material-symbols-outlined" style="font-size:18px;color:var(--primary)">calendar_month</span> Periode <select id="dr-w"></select></label>
      <label>Risiko awal <select id="dr-lv"><option value="SH">Significant + High</option><option value="ALL">Semua level</option></select></label>
      <label data-ctl="pja" hidden>Prioritas <select id="dr-pri"><option value="all">Semua</option><option value="T">Tinggi</option><option value="S">Sedang</option><option value="R">Rendah</option><option value="K">Volume kecil</option></select></label>
      <label data-ctl="pja" hidden>Urutkan <select id="dr-urut"><option value="skor">Skor prioritas</option><option value="gap">% gap</option><option value="gapn">Jumlah baris gap</option><option value="none">% tanpa laporan</option><option value="lewat">Lewat janji</option><option value="nama">Nama</option></select></label>
      <label data-ctl="pja" hidden><input type="search" class="pr-search" id="dr-cari" placeholder="Cari nama PJA" aria-label="Cari nama PJA"></label>
    </div>
  @endsection
@endif

@include('peta-risiko-pja.drm._beranda-style')

@section('content')
<style>
  /* Treemap kelompok aktivitas */
  .dr-tm-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 20px; align-items: stretch; }
  .dr-tm { position: relative; height: 420px; border-radius: 14px; overflow: hidden; background: #f5f7f9; }
  .dr-tm-k { position: absolute; box-sizing: border-box; border: 0; border-radius: 10px; padding: 10px 12px; cursor: pointer; overflow: hidden;
    display: flex; flex-direction: column; justify-content: space-between; text-align: left; font-family: inherit;
    box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .06); transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease; }
  .dr-tm-k:hover, .dr-tm-k:focus-visible { transform: scale(1.015); z-index: 2; box-shadow: 0 10px 24px -8px rgba(0, 0, 0, .35), inset 0 0 0 2px rgba(255, 255, 255, .55); outline: none; }
  .dr-tm-k.on { z-index: 3; box-shadow: 0 0 0 3px #fff, 0 0 0 6px #3952bc, 0 12px 28px -8px rgba(0, 0, 0, .35); }
  .dr-tm-k.redup { opacity: .35; }
  .dr-tm-n { font-size: 12.5px; font-weight: 600; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .dr-tm-v { line-height: 1.1; }
  .dr-tm-v b { font-size: 22px; font-weight: 800; letter-spacing: -.02em; }
  .dr-tm-v small { display: block; margin-top: 2px; font-size: 11px; font-weight: 500; opacity: .85; }
  .dr-tm-k.kecil { padding: 6px 8px; justify-content: center; }
  .dr-tm-k.kecil .dr-tm-v b { font-size: 14px; }
  .dr-tm-top { display: flex; flex-direction: column; gap: 10px; }
  .dr-tm-top h4 { margin: 0 0 4px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #595c5e; }
  .dr-tm-i { display: grid; grid-template-columns: 26px minmax(0, 1fr) auto; gap: 4px 10px; align-items: center; padding: 10px 12px; border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, .07); background: #fff; cursor: pointer; text-align: left; font-family: inherit; transition: border-color .15s, background .15s; }
  .dr-tm-i:hover, .dr-tm-i:focus-visible { border-color: #3952bc; background: #f7f8fe; outline: none; }
  .dr-tm-i.on { border-color: #3952bc; background: #eef1fd; }
  .dr-tm-i.redup { opacity: .5; }
  .dr-tm-i .r { grid-row: span 2; display: grid; place-items: center; width: 26px; height: 26px; border-radius: 8px; font-size: 12px; font-weight: 800; }
  .dr-tm-i .n { font-size: 12.5px; font-weight: 600; color: #2c2f31; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .dr-tm-i .v { font-size: 13px; font-weight: 800; color: #2c2f31; }
  .dr-tm-i .bar { grid-column: 2 / span 2; height: 6px; border-radius: 3px; background: #eef1f3; overflow: hidden; }
  .dr-tm-i .bar i { display: block; height: 100%; border-radius: 3px; }
  .dr-tm-skala { display: flex; align-items: center; gap: 10px; margin-top: 12px; font-size: 11.5px; color: #595c5e; flex-wrap: wrap; }
  .dr-tm-skala i { width: 180px; height: 8px; border-radius: 4px; background: linear-gradient(90deg, #fff1dc, #fbbf24, #ea580c, #7c2d12); }
  @media (max-width: 1100px) { .dr-tm-wrap { grid-template-columns: 1fr; } }
  @media (max-width: 640px) { .dr-tm { height: 320px; } }
</style>
@if (! $meta)
  <div class="pr-card"><div class="pr-empty">
    Jalankan <code>php artisan peta-risiko:sync</code> di server yang terhubung ke ClickHouse OBDS, lalu muat ulang halaman ini.
  </div></div>
@else
  <div id="dr-ringkas" hidden><div id="dr-kpis"></div></div>

  <div id="dr-panel-cakupan" class="br-root" style="margin-top:20px">
    <div id="dr-kpi4"></div>

    <div class="pr-card" id="dr-card-tm">
      <div class="pr-card-head"><div><b>Kelompok aktivitas dengan risiko terbesar</b>
        <small>Luas kotak = jumlah bahaya-risiko yang belum terkontrol di ≥80% detail lokasinya. Warna = porsi bahaya yang tidak terkontrol di detail lokasi mana pun. Klik kotak atau daftar untuk memfilter matriks di bawah; klik lagi untuk semua kelompok.</small></div></div>
      <div class="pr-card-body">
        <div class="dr-tm-wrap">
          <div>
            <div id="dr-treemap" class="dr-tm" role="list" aria-label="Treemap kelompok aktivitas"></div>
            <div class="dr-tm-skala" id="dr-tm-skala"></div>
          </div>
          <div class="dr-tm-top" id="dr-tm-top" aria-label="Lima kelompok dengan baris belum terkontrol terbanyak"></div>
        </div>
      </div>
    </div>

    <div class="pr-card" id="dr-card-mx">
      <div class="pr-card-head"><div><b>Matriks 5x5</b> <span id="dr-mx-filter"></span>
        <small>Satu baris = kelompok aktivitas × bahaya-risiko unik di cakupan ini. Final: bahaya yang terkontrol di ≥80% detail lokasinya memakai risiko residual HIRA, selain itu tetap risiko awal. Baris tanpa nilai risiko di HIRA tidak masuk matriks.</small></div></div>
      <div class="pr-card-body dr-two" id="dr-matriks"></div>
    </div>

    <div class="pr-card">
      <div class="br-rhead"><b class="t" id="dr-tren-judul">Komposisi per minggu</b><span class="br-sum" id="dr-tren-sum"></span></div>
      <div class="pr-card-body"><div id="dr-tren"></div><div id="dr-legenda"></div></div>
    </div>

    <div class="pr-card" id="dr-card-grup">
      <div class="pr-card-head"><div><b id="dr-grup-judul">Per Group Lokasi</b><small id="dr-grup-sub">Satu baris = grup × kelompok aktivitas × bahaya unik; status dari detail lokasi grup itu saja. Satu bahaya bisa berlaku di beberapa grup, jadi jumlah antar grup bisa melebihi baseline. Klik baris untuk melihat grup itu.</small></div></div>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl" id="dr-tbl-grup"></table></div>
    </div>

    <div class="pr-card">
      <div class="pr-card-head"><div><b id="dr-kel-judul">Per Kelompok Aktivitas</b><small id="dr-kel-sub">25 kelompok aktivitas master DRM 2.1 (M02); jumlah barisnya sama dengan baseline di kartu atas. Klik baris untuk melihat aktivitas HIRA di kelompok itu.</small></div></div>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl" id="dr-tbl-proses"></table></div>
    </div>
  </div>

  <div id="dr-panel-periode" class="br-root" style="margin-top:20px" hidden></div>
  <div id="dr-panel-pja" class="br-root" style="margin-top:20px" hidden></div>

  {{-- Tab Beranda PJA: Beranda PJA hidup (data snapshot sync), dirender langsung tanpa iframe. --}}
  <div id="dr-panel-bpja" class="br-root" style="margin-top:20px" hidden>
    <div class="pr-card" style="margin-bottom:18px">
      <div class="pr-card-body" style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
        <label class="dr-bpja-pilih" style="display:inline-flex;align-items:center;gap:10px;font-weight:700;font-size:13px">
          <span class="material-symbols-outlined" style="color:var(--primary)">badge</span> PJA
          <select id="dr-bpja-pja" style="min-width:320px;padding:8px 10px;border-radius:10px;border:1px solid rgba(171,173,175,.45);font-weight:600"></select>
        </label>
        <a class="pr-back" id="dr-bpja-buka" href="#" style="margin-left:auto"><span class="material-symbols-outlined" style="font-size:18px">open_in_new</span> Buka halaman PJA</a>
      </div>
    </div>
    @include('peta-risiko-pja.drm._beranda-pja-isi', ['denganSub' => true])
  </div>

  <div class="pr-foot">
    <p>Baseline dan pemetaan HIRA ↔ SAP mengikuti master mapping DRM 2.1 (M01–M16, unduh lewat "Unduh Excel mapping"). Semua tab memakai status mingguan (Minggu–Sabtu), bahaya umum dikeluarkan. "Terkontrol" = terkontrol menurut catatan SAP, bukan bukti kondisi lapangan.</p>
    <p>Tanpa laporan bukan berarti aman. Overview, Group Lokasi, dan Kelompok Aktivitas memakai baseline unik (kelompok aktivitas × bahaya, tidak dipetakan ke PJA); Identifikasi per periode dan Semua PJA menjumlahkan baris tingkat PJA (satu detail lokasi bisa dimiliki 2 PJA).</p>
  </div>
@endif
@endsection

@section('scripts')
@include('peta-risiko-pja.drm._ui')
@if ($meta)
@include('peta-risiko-pja.drm._beranda-pja-script')
@endif
@if ($meta)
<script>
(() => {
  const URL_META = @json(route('peta-risiko-pja.meta'));
  const URL_PJA = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.pja', ['pja' => '__ID__'])) }};
  const $ = (s) => document.querySelector(s);
  const q = new URLSearchParams(location.search);
  // Tab "periode" sedang disembunyikan: tautan lama ?tab=periode diarahkan ke Overview.
  const state = { tab: ['periode'].includes(q.get('tab')) ? 'site' : (q.get('tab') || 'site'), site: q.get('site') || 'ALL', grup: q.get('grup') || '', proses: q.get('proses') || '', cari: '',
    w: q.has('w') ? Math.max(0, parseInt(q.get('w'), 10) || 0) : -1, lv: q.get('lv') === 'ALL' ? 'ALL' : 'SH', pri: 'all', urut: 'skor', buka: {}, pbuka: {}, situs: {}, kbuka: {}, dlbuka: {}, pjaB: q.get('pja') || '', kelMx: q.get('kel') || '' };
  const TAB_W = ['pja', 'periode'];
  let M = null;

  fetch(URL_META, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((m) => { M = m; init(); })
    .catch(() => { $('#dr-kpi4').innerHTML = '<div class="pr-card"><div class="pr-empty">Gagal memuat data.</div></div>'; });

  function init() {
    $('#dr-site').innerHTML += M.sites.map((s) => `<option value="${DR.esc(s)}">${DR.esc(s)}</option>`).join('');
    // Grup lokasi tanpa baris baseline sama sekali (mis. grup master dengan 0 baris HIRA) tidak ditampilkan.
    M.grup = grupBerisi();
    $('#dr-grup').innerHTML = M.grup.map((g) => `<option value="${DR.esc(g)}">${DR.esc(g)}</option>`).join('');
    if (!state.grup || !M.grup.includes(state.grup)) state.grup = M.grup[0];
    $('#dr-proses').innerHTML = M.proses.map((p) => `<option value="${DR.esc(p.kode)}">${DR.esc(p.nama)}</option>`).join('');
    if (!state.proses) state.proses = M.proses[0].kode;
    $('#dr-proses').value = state.proses;
    $('#dr-proses').addEventListener('change', (e) => { state.proses = e.target.value; render(); });
    $('#dr-site').value = state.site; $('#dr-grup').value = state.grup; $('#dr-lv').value = state.lv;
    document.querySelectorAll('.dr-tabs button').forEach((b) => b.addEventListener('click', () => { state.tab = b.dataset.tab; render(); }));
    $('#dr-site').addEventListener('change', (e) => { state.site = e.target.value; render(); });
    $('#dr-grup').addEventListener('change', (e) => { state.grup = e.target.value; render(); });
    $('#dr-lv').addEventListener('change', (e) => { state.lv = e.target.value; render(); });
    $('#dr-cari').addEventListener('input', (e) => { state.cari = e.target.value.toLowerCase(); renderPja(); });
    const mg = (M.beranda || {}).minggu || [];
    if (state.w < 0 || state.w >= mg.length) state.w = DR.wDefault(mg, M.sampai);
    $('#dr-w').innerHTML = mg.map((a, i) => `<option value="${i}"${i === state.w ? ' selected' : ''}>${DR.esc(DR.minggu(a, M.sampai, i, mg[0]).label)}</option>`).join('');
    $('#dr-w').addEventListener('change', (e) => { state.w = +e.target.value; render(); });
    $('#dr-pri').addEventListener('change', (e) => { state.pri = e.target.value; renderPja(); });
    $('#dr-urut').addEventListener('change', (e) => { state.urut = e.target.value; renderPja(); });
    render();
  }

  /** Grup lokasi yang punya minimal satu baris baseline di minggu dan level mana pun (semua site). */
  function grupBerisi() {
    const sumber = M.beranda && M.beranda.ringkas && M.beranda.ringkas.ALL ? [M.beranda.ringkas.ALL] : ((M.beranda || {}).mgmt || []);
    const ada = new Set();
    sumber.forEach((p) => (p.grp || []).forEach((perLv) => Object.values(perLv || {}).forEach((m) => Object.entries(m || {}).forEach(([g, v]) => {
      if ((v || [])[0] > 0) ada.add(g);
    }))));
    const hasil = M.grup.filter((g) => ada.has(g));
    return hasil.length ? hasil : M.grup;
  }

  function simpanUrl() {
    const p = new URLSearchParams({ tab: state.tab, site: state.site, w: state.w });
    if (state.lv === 'ALL') p.set('lv', 'ALL');
    if (state.tab === 'site' && state.kelMx) p.set('kel', state.kelMx);
    if (state.tab === 'grup') p.set('grup', state.grup);
    if (state.tab === 'proses') p.set('proses', state.proses);
    if (state.tab === 'bpja' && state.pjaB) p.set('pja', state.pjaB);
    history.replaceState(null, '', '?' + p.toString());
  }

  function render() {
    document.querySelectorAll('.dr-tabs button').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.tab === state.tab)));
    document.querySelector('[data-ctl="grup"]').hidden = state.tab !== 'grup';
    document.querySelectorAll('[data-ctl="pja"]').forEach((e) => { e.hidden = state.tab !== 'pja'; });
    document.querySelector('[data-ctl="proses"]').hidden = state.tab !== 'proses';
    $('#dr-panel-cakupan').hidden = TAB_W.includes(state.tab);
    $('#dr-panel-pja').hidden = state.tab !== 'pja';
    $('#dr-panel-periode').hidden = state.tab !== 'periode';
    $('#dr-ringkas').hidden = true;
    const bpja = state.tab === 'bpja';
    $('#dr-panel-bpja').hidden = !bpja;
    // Beranda PJA punya filter minggu & tingkat risiko sendiri. Pakai style.display: CSS .dr-bar (display:flex)
    // mengalahkan atribut hidden.
    $('.dr-bar').style.display = bpja ? 'none' : '';
    if (bpja) {
      $('#dr-panel-cakupan').hidden = true;
      bukaBeranda();
    } else if (TAB_W.includes(state.tab)) renderW(); else renderCakupan();
    simpanUrl();
  }

  function kosong() { return Array.from({ length: 5 }, () => [0, 0, 0, 0, 0]); }

  // ------------------------------------------------------------ Beranda PJA (komponen bersama _beranda-pja-*)
  let berandaDimuat = '';
  /** Isi pemilih PJA (dikelompokkan per site) sekali, lalu muat Beranda PJA terpilih bila belum dimuat. */
  function bukaBeranda() {
    const sel = $('#dr-bpja-pja');
    const L = ((M.beranda || {}).mgmt || []).slice().sort((a, b) => a.site.localeCompare(b.site) || a.nama.localeCompare(b.nama));
    if (!L.length) { $('#br-isi').innerHTML = '<div class="pr-card"><div class="pr-empty">Snapshot belum memuat data PJA. Jalankan ulang <code>php artisan peta-risiko:sync</code>.</div></div>'; return; }
    if (!sel.options.length) {
      const perSite = {};
      L.forEach((p) => { (perSite[p.site] ||= []).push(p); });
      sel.innerHTML = Object.entries(perSite).map(([site, d]) => `<optgroup label="${DR.esc(site)}">${d.map((p) => `<option value="${DR.esc(p.id)}">${DR.esc(p.nama)}</option>`).join('')}</optgroup>`).join('');
      sel.addEventListener('change', () => { state.pjaB = sel.value; muatBeranda(state.w); simpanUrl(); });
    }
    if (!state.pjaB || !L.some((p) => String(p.id) === String(state.pjaB))) {
      state.pjaB = String((L.find((p) => state.site === 'ALL' || p.site === state.site) || L[0]).id);
    }
    sel.value = state.pjaB;
    if (berandaDimuat !== state.pjaB) muatBeranda(state.w);
  }

  function muatBeranda(w) {
    const p = M.beranda.mgmt.find((x) => String(x.id) === String(state.pjaB));
    if (!p) return;
    berandaDimuat = String(p.id);
    $('#dr-bpja-buka').href = URL_PJA.replace('__ID__', p.id) + '?w=' + w;
    $('#dr-sub').textContent = `${p.nama} · ${p.site} · data sampai ${M.sampai.split('-').reverse().join('/')} · periode minggu kalender (Minggu–Sabtu)`;
    $('#br-isi').innerHTML = '<div class="pr-card"><div class="pr-empty">Memuat Beranda PJA…</div></div>';
    BerandaPJA({
      meta: M, id: String(p.id), w,
      // Memilih PJA lain di tabel antar PJA: ganti isi tab tanpa memuat ulang halaman.
      pindahPja: (id, wk) => { state.pjaB = String(id); $('#dr-bpja-pja').value = state.pjaB; muatBeranda(wk); simpanUrl(); window.scrollTo({ top: 0, behavior: 'smooth' }); },
    });
  }

  // ------------------------------------------------------------ Per Site / Group Lokasi / Kelompok Aktivitas (satuan ketat)
  /** Peta {level: peta kunci → vektor} menjadi {level: vektor} untuk satu kunci (grup / kelompok). */
  const ambilKunci = (perLv, kunci) => Object.fromEntries(Object.entries(perLv || {}).map(([lv, m]) => [lv, (m || {})[kunci] || []]));
  const vekGrup = (p, w, g) => vekDari(ambilKunci((p.grp || [])[w], g));
  const vekKel = (p, w, k) => vekDari(ambilKunci((p.kel || [])[w], k));
  /** Vektor satu PJA untuk cakupan tab yang aktif. */
  const vekTab = (p, w) => (state.tab === 'grup' ? vekGrup(p, w, state.grup) : state.tab === 'proses' ? vekKel(p, w, state.proses) : vekPja(p, w));
  const jumlahF = (P, f) => P.reduce((a, p) => tambahVek(a, f(p)), KOSONG);
  const pjaDiSite = () => M.beranda.mgmt.filter((p) => state.site === 'ALL' || p.site === state.site);
  /** Snapshot punya ringkasan baseline unik (kelompok aktivitas × bahaya, tidak dipetakan ke PJA)? */
  const adaUnik = () => !!(M.beranda.ringkas && M.beranda.ringkas.ALL);
  /**
   * Sumber angka tab Overview / Group Lokasi / Kelompok Aktivitas: ringkasan baseline unik cakupan itu
   * (satu "PJA semu" berisi semua detail lokasi site). Snapshot lama: jumlah baris tingkat PJA.
   */
  const cakupanP = () => (adaUnik() ? [M.beranda.ringkas[state.site]].filter(Boolean) : pjaDiSite());

  /** Ikon kartu KPI (gaya template dashboard-peer): kunci kategori → [ikon Material, latar, warna]. */
  const IKON_KPI = { t: ['inventory_2', 'rgba(57,82,188,.1)', '#3952bc'], full: ['verified', '#dcfce7', '#16a34a'], part: ['rule', '#fef3c7', '#b45309'],
    gapo: ['report', '#ffedd5', '#c2410c'], none: ['visibility_off', '#fde7d4', '#c2410c'] };
  const ikonKpi = (k) => { const [i, b, c] = IKON_KPI[k] || IKON_KPI.t; return `<span class="tp-ikon" style="--ib:${b};--ic:${c}" aria-hidden="true"><span class="material-symbols-outlined">${i}</span></span>`; };

  /**
   * Label kategori. Ringkasan baseline unik (tab Overview / Group Lokasi / Kelompok Aktivitas) memakai pita cakupan
   * detail lokasi (keputusan user 17 Nov 2026); tab Semua PJA tetap memakai kategori spec per PJA.
   */
  const pitaAktif = () => adaUnik() && !!(M.beranda.ringkas.ALL || {}).pita;
  const LBL_SPEC = { full: 'Terkontrol penuh', part: 'Terkontrol sebagian', gapo: 'Hanya laporan bermasalah', none: 'Tanpa laporan',
    kFull: 'Terkontrol penuh', kPart: 'Sebagian', kGapo: 'Hanya bermasalah', kNone: 'Tanpa laporan' };
  function LBL() {
    if (!pitaAktif()) return LBL_SPEC;
    const [atas, bawah] = M.beranda.ringkas.ALL.pita.map((x) => Math.round(100 * x));
    return { full: `Terkontrol di ≥${atas}% DL`, part: `Terkontrol di ${bawah}–${atas}% DL`, gapo: `Terkontrol di <${bawah}% DL`, none: 'Tidak terkontrol di DL mana pun',
      kFull: `≥${atas}% DL`, kPart: `${bawah}–${atas}% DL`, kGapo: `<${bawah}% DL`, kNone: '0% DL' };
  }

  function kartu4(V, V0, judul) {
    const W = DR.BW, R = adaUnik() ? M.beranda.ringkas[state.site] || {} : null, L = LBL();
    const cakupan = pitaAktif() && V.t ? ` · rata-rata cakupan ${DR.pct(V.cak / 1000, V.t)} DL` : '';
    const kartu = (ac, kc, j, a, naikBaik) => `<div class="br-kpi" style="--ac:${ac};--kc:${kc}">${ikonKpi(a)}<small>${j}</small><b>${pctI(V[a], V.t)}</b><em>${DR.fmt(V[a])} baris${V0 ? ' · ' + delta(V[a], V.t, V0[a], V0.t, naikBaik) + ' vs minggu lalu' : ''}</em></div>`;
    const satuan = R ? 'kelompok aktivitas × bahaya-risiko unik (tidak dipetakan ke PJA / detail lokasi)' : 'jumlah baris tingkat PJA';
    return `<div class="br-kpis br-kpis-5">
        <div class="br-kpi" style="--ac:var(--navy);--kc:var(--navy)" title="Satuan: ${satuan}">${ikonKpi('t')}<small>Baseline · ${judul}</small><b>${DR.fmt(V.t)}</b><em>${R ? 'kelompok aktivitas × bahaya-risiko unik' : 'baris tingkat PJA'}${cakupan} · ${state.lv === 'ALL' ? 'semua level' : 'Significant + High'} · ${gapTxt(V)}</em></div>
        ${kartu(W.ok, 'var(--green-d)', L.full, 'full', true)}
        ${kartu(W.part, '#9A7300', L.part, 'part', false)}
        ${kartu(W.bm, W.bm, L.gapo, 'gapo', false)}
        ${kartu(W.none, W.bm, L.none, 'none', false)}
      </div>`;
  }

  function renderCakupan() {
    if (!formatBaru() || !(adaUnik() || M.beranda.mgmt.every((p) => p.kel && p.grp))) { $('#dr-kpi4').innerHTML = pesanLama(); return; }
    const P = cakupanP(), w = state.w, NW = M.beranda.minggu.length, wk = wKode(w);
    const lokasi = state.site === 'ALL' ? 'BC' : DR.esc(state.site);
    const namaKel = (M.proses.find((p) => p.kode === state.proses) || {}).nama || state.proses;
    const judul = state.tab === 'grup' ? `${DR.esc(state.grup)} · ${lokasi}` : state.tab === 'proses' ? `${DR.esc(namaKel)} · ${lokasi}` : lokasi;
    const V = jumlahF(P, (p) => vekTab(p, w)), V0 = w + 1 < NW ? jumlahF(P, (p) => vekTab(p, w + 1)) : null;
    const R = adaUnik() ? M.beranda.ringkas[state.site] || {} : null;
    const catatan = R
      ? '' // keterangan satuan baseline dihapus atas permintaan user
      : '<p class="dr-mute" style="margin:8px 0 0;font-size:12px">Snapshot lama: angka masih jumlah baris tingkat PJA. Jalankan ulang <code>php artisan peta-risiko:sync</code> untuk baseline unik.</p>';
    $('#dr-kpi4').innerHTML = kartu4(V, V0, `${judul} · ${wk}`) + catatan;

    $('#dr-card-mx').hidden = state.tab !== 'site';
    $('#dr-card-tm').hidden = state.tab !== 'site';
    // Tab Kelompok Aktivitas tidak menampilkan tabel per grup lokasi.
    $('#dr-card-grup').hidden = state.tab === 'proses';
    if (state.tab === 'site') {
      renderTreemap(P, w);
      renderMatriks(P, w, wk);
    }

    $('#dr-tren-judul').innerHTML = `Komposisi per minggu · ${judul}`;
    $('#dr-tren-sum').innerHTML = `<span>${DR.fmt(V.t)} baris</span><span>${gapTxt(V)}</span>`;
    $('#dr-tren').innerHTML = trenPeriode(P, vekTab, LBL());
    const Lc = LBL();
    $('#dr-legenda').innerHTML = `<div class="dr-legend">${[[DR.BW.ok, Lc.kFull], [DR.BW.part, Lc.kPart], [DR.BW.bm, Lc.kGapo], [DR.BW.none, Lc.kNone]].map(([c, t]) => `<span><i style="--c:${c}"></i>${t}</span>`).join('')}</div>`;
    document.querySelectorAll('#dr-tren .br-wc').forEach((c) => c.addEventListener('click', () => { state.w = +c.dataset.w; $('#dr-w').value = state.w; render(); }));

    const diGrup = state.tab === 'grup';
    $('#dr-grup-judul').textContent = diGrup ? `Detail lokasi · ${state.grup}` : 'Per Group Lokasi';
    $('#dr-grup-sub').textContent = diGrup
      ? 'Detail lokasi di grup ini beserta PJA-nya. Satu baris = kelompok aktivitas × bahaya di detail lokasi itu (terkontrol / hanya bermasalah / tanpa laporan). Klik nama PJA untuk membuka Beranda PJA.'
      : 'Satu baris = grup × kelompok aktivitas × bahaya unik; status dari detail lokasi grup itu saja. Satu bahaya bisa berlaku di beberapa grup, jadi jumlah antar grup bisa melebihi baseline. Klik baris untuk melihat grup itu.';
    $('#dr-kel-judul').textContent = diGrup ? `Kelompok aktivitas · ${state.grup}` : 'Per Kelompok Aktivitas';
    $('#dr-kel-sub').textContent = diGrup
      ? 'Kelompok aktivitas yang ada di grup lokasi ini (dari master M05); jumlah barisnya sama dengan baseline grup di kartu atas. Klik baris untuk melihat aktivitas HIRA kelompok itu di grup ini.'
      : '25 kelompok aktivitas master DRM 2.1 (M02); jumlah barisnya sama dengan baseline di kartu atas. Klik baris untuk melihat aktivitas HIRA di kelompok itu.';
    if (diGrup) {
      tabelDetailLokasi(w);
      tabelCakupan('#dr-tbl-proses', 'Kelompok aktivitas', M.proses.map((k) => [k.kode, k.nama]), (p, i, k) => vekDari(ambilKunci((p.gk || [])[i], `${state.grup}|${k}`)), 'proses');
    } else {
      tabelCakupan('#dr-tbl-grup', 'Group Lokasi', M.grup.map((g) => [g, g]), (p, i, g) => vekGrup(p, i, g), 'grup');
      tabelCakupan('#dr-tbl-proses', 'Kelompok aktivitas', M.proses.map((k) => [k.kode, k.nama]), (p, i, k) => vekKel(p, i, k), 'proses');
    }
  }

  /**
   * Tab Group Lokasi: satu baris per detail lokasi di grup terpilih (site sesuai filter, menurut site detail lokasi).
   * Detail lokasi milik 2 PJA dihitung sekali; semua PJA pemiliknya ditampilkan.
   */
  function tabelDetailLokasi(w) {
    const NW = M.beranda.minggu.length, kamus = M.beranda.dl || {}, peta = new Map();
    M.beranda.mgmt.forEach((p) => (p.dls || []).forEach((d) => {
      const info = kamus[d];
      if (!info || info[3] !== state.grup || (state.site !== 'ALL' && info[0] !== state.site)) return;
      if (!peta.has(d)) {
        // Status per detail lokasi dari ringkasan unik "semua site" (sama untuk setiap PJA pemiliknya).
        // Snapshot lama (sebelum 17 Nov 2026) menyimpan dlw per PJA, bukan di ringkasan ALL: pakai yang tersedia.
        const ra = M.beranda.ringkas && M.beranda.ringkas.ALL;
        const sumber = ra && ra.dlw ? ra : p;
        peta.set(d, { d, info, pja: [], v: vekDari(ambilKunci((sumber.dlw || [])[w], d)), v0: w + 1 < NW ? vekDari(ambilKunci((sumber.dlw || [])[w + 1], d)) : null });
      }
      peta.get(d).pja.push(p);
    }));
    const L = [...peta.values()].filter((r) => r.v.t).sort((a, b) => (b.v.t - b.v.full) - (a.v.t - a.v.full) || b.v.t - a.v.t);
    const kosongan = [...peta.values()].length - L.length;
    $('#dr-tbl-grup').innerHTML = `<thead><tr><th style="min-width:240px">Detail lokasi</th><th style="min-width:200px">PJA</th><th style="min-width:180px">Komposisi</th>
        <th class="r">Baris</th><th class="r">Terkontrol</th><th class="r">Hanya bermasalah</th><th class="r">Tanpa laporan</th><th>Laporan bermasalah</th></tr></thead><tbody>`
      + (L.map(({ d, info, pja, v, v0 }) => {
        const buka = !!state.dlbuka[d];
        return `<tr class="br-a${buka ? ' open' : ''}" data-dl="${DR.esc(d)}" style="cursor:pointer">
          <td><button class="br-cr" type="button" aria-expanded="${buka}" aria-label="${buka ? 'Tutup' : 'Buka'} laporan SAP ${DR.esc(info[2])}">▸</button><span class="pr-name">${DR.esc(info[2])}</span><br><span class="dr-mute" style="padding-left:24px">${DR.esc(info[1])} · ${DR.esc(info[0])}</span></td>
          <td>${pja.map((p) => `<a data-pja href="${URL_PJA.replace('__ID__', p.id)}?w=${w}" style="display:block">${DR.esc(p.nama)}</a>`).join('')}</td>
          <td>${DR.sb4(v.full, 0, v.gapo, v.none)}</td><td class="r">${DR.fmt(v.t)}</td>
          <td class="r"><b>${pctI(v.full, v.t)}</b>${v0 ? '<br>' + delta(v.full, v.t, v0.full, v0.t, true) : ''}</td>
          <td class="r">${pctI(v.gapo, v.t)}</td><td class="r">${pctI(v.none, v.t)}</td><td class="dr-mute">${gapTxt(v)}</td></tr>`
          + (buka ? `<tr class="br-sub br-buka"><td colspan="8" style="padding:12px 16px 16px 40px" data-sap="${DR.esc(d)}"><span class="dr-mute">Memuat laporan SAP…</span></td></tr>` : '');
      }).join('')
        || `<tr><td colspan="8" class="pr-empty">Tidak ada detail lokasi ${DR.esc(state.grup)}${state.site === 'ALL' ? '' : ' di ' + DR.esc(state.site)} yang punya baris baseline${M.beranda.dl ? '' : ' (snapshot lama — jalankan ulang peta-risiko:sync)'}.</td></tr>`)
      + (kosongan ? `<tr><td colspan="8" class="dr-mute" style="font-size:12px">${kosongan} detail lokasi lain di grup ini tidak punya baris pada level risiko terpilih.</td></tr>` : '')
      + '</tbody>';
    document.querySelectorAll('#dr-tbl-grup tr[data-dl]').forEach((tr) => tr.addEventListener('click', () => {
      state.dlbuka[tr.dataset.dl] = !state.dlbuka[tr.dataset.dl]; tabelDetailLokasi(state.w);
    }));
    document.querySelectorAll('#dr-tbl-grup a[data-pja]').forEach((a) => a.addEventListener('click', (e) => e.stopPropagation()));
    document.querySelectorAll('#dr-tbl-grup [data-sap]').forEach((td) => muatSap(td.dataset.sap, w, td));
  }

  // ------------------------------------------------------------ Laporan SAP satu detail lokasi (dimuat saat baris dibuka)
  const URL_SAP = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.dl.sap', ['dl' => '__DL__'])) }};
  const sapCache = {};
  const PEMETAAN = {
    lolos: ['var(--green-d)', 'Mengidentifikasi baris HIRA'],
    luar_grup: ['#9A7300', 'Terpetakan, tapi barisnya bukan milik grup ini'],
    tak_terpetakan: ['#8A939C', 'Tidak terpetakan di master'],
  };

  function muatSap(d, w, td) {
    const kunci = `${d}|${w}`;
    const tulis = (data) => { if (td.isConnected) td.innerHTML = isiSap(data); };
    if (sapCache[kunci]) { tulis(sapCache[kunci]); return; }
    fetch(URL_SAP.replace('__DL__', encodeURIComponent(d)) + '?w=' + w, { headers: { Accept: 'application/json' } })
      .then((r) => r.json().then((j) => (r.ok ? j : Promise.reject(j))))
      .then((data) => { sapCache[kunci] = data; tulis(data); })
      .catch((e) => { if (td.isConnected) td.innerHTML = `<span class="br-red">${DR.esc((e && e.pesan) || 'Gagal memuat laporan SAP.')}</span>`; });
  }

  function isiSap(data) {
    const R = data.ringkas || {}, L = data.laporan || [];
    const periode = `${data.dari.split('-').reverse().join('/')} – ${data.sampai.split('-').reverse().join('/')}`;
    if (!L.length) return `<span class="dr-mute">Tidak ada laporan SAP (Hazard, Inspeksi, Observasi, OAK) di detail lokasi ini pada ${periode}.</span>`;
    const ringkas = `<div style="display:flex;flex-wrap:wrap;gap:6px 16px;margin-bottom:10px;font-size:13px">
        <b>${DR.fmt(R.total)} laporan · ${periode}</b>
        <span style="color:${PEMETAAN.lolos[0]}">● ${DR.fmt(R.lolos)} mengidentifikasi baris HIRA</span>
        <span style="color:${PEMETAAN.luar_grup[0]}">● ${DR.fmt(R.luar_grup)} terpetakan di luar grup</span>
        <span style="color:${PEMETAAN.tak_terpetakan[0]}">● ${DR.fmt(R.tak_terpetakan)} tidak terpetakan</span>
        <span>belum closed ${DR.fmt(R.belum_closed)} · OAK Stop ${DR.fmt(R.oak_stop)}</span></div>`;
    const baris = L.map((x) => {
      const [warna, ket] = PEMETAAN[x.pemetaan] || ['#8A939C', x.pemetaan];
      const bendera = [x.belum_closed ? '<span class="br-pill" style="background:var(--red)">Belum closed</span>' : '',
        x.oak_stop ? '<span class="br-pill" style="background:var(--red)">OAK Stop</span>' : ''].join(' ');
      return `<tr><td style="white-space:nowrap">${DR.esc(x.waktu)}<br><span class="dr-mute">${DR.esc(x.jenis)}</span></td>
        <td><b>${DR.esc(x.isi || '-')}</b>${x.deskripsi ? `<br><span class="dr-mute">${DR.esc(x.deskripsi)}</span>` : ''}</td>
        <td>${DR.esc(x.status || x.hasil_oak || '-')}${x.janji ? `<br><span class="dr-mute">janji ${DR.esc(x.janji)}</span>` : ''}<br>${bendera}</td>
        <td><span style="color:${warna};font-weight:600">● ${ket}</span>${x.n_lolos ? `<br><span class="dr-mute">${x.bahaya.map(DR.esc).join('<br>')}${x.n_lolos > x.bahaya.length ? `<br>+${x.n_lolos - x.bahaya.length} baris lain` : ''}</span>` : x.n_kandidat ? `<br><span class="dr-mute">${x.n_kandidat} kandidat baris di grup lain</span>` : ''}</td>
        <td class="dr-mute">${DR.esc(x.pelapor)}</td></tr>`;
    }).join('');
    return ringkas + `<div class="dr-scroll"><table class="pr-tbl dr-tbl" style="min-width:900px"><thead><tr><th>Waktu</th><th>Isi laporan</th><th>Status</th><th style="min-width:240px">Pemetaan master</th><th>Pelapor</th></tr></thead><tbody>${baris}</tbody></table></div>`
      + (data.terpotong ? '<div class="dr-mute" style="font-size:12px;margin-top:6px">Ditampilkan 500 laporan terbaru.</div>' : '')
      + '<div class="dr-mute" style="font-size:12px;margin-top:6px">Pemetaan: label ketidaksesuaian / subketidaksesuaian (M09) atau teks aktivitas OBS/OAK (M11/M12) → baris HIRA; dihitung hanya bila baris itu milik grup lokasi detail lokasi ini. Bukti tema checklist/teks OBS (M16) tidak ditampilkan di sini.</div>';
  }

  /** Matriks 5x5 Baseline HIRA (risiko awal) dan Final; bila satu kelompok dipilih di treemap, hanya kelompok itu. */
  function renderMatriks(P, w, wk) {
    const A = kosong(), F = kosong(), kel = state.kelMx;
    const isi = (x) => {
      Object.entries(x.a || {}).forEach(([s, n]) => { A[Math.floor(s / 5)][s % 5] += n; });
      Object.entries(x.f || {}).forEach(([s, n]) => { F[Math.floor(s / 5)][s % 5] += n; });
    };
    // Snapshot sebelum 17 Nov 2026 menyimpan mx[minggu][level] = {a, f} tanpa pecahan per kelompok.
    let lama = false;
    P.forEach((p) => {
      const m = (p.mx || [])[w] || {};
      lvAmbil(m).forEach((lv) => {
        const x = m[lv] || {};
        if ('a' in x || 'f' in x) { lama = true; if (!kel) isi(x); return; }
        Object.entries(x).forEach(([k, v]) => { if (!kel || k === kel) isi(v); });
      });
    });
    const nama = kel ? (M.proses.find((p) => p.kode === kel) || {}).nama || kel : '';
    const akhiran = kel ? ` · ${DR.esc(nama)}` : '';
    $('#dr-mx-filter').innerHTML = kel ? `<button type="button" class="dr-chip" id="dr-mx-hapus" aria-label="Hapus filter kelompok">${DR.esc(nama)} ✕</button>` : '';
    const nBaris = A.flat().reduce((s, n) => s + n, 0);
    const nKel = kel ? jumlahF(P, (p) => vekKel(p, w, kel)).t : 0;
    const catatan = lama && kel
      ? `<div class="pr-empty" style="grid-column:1/-1">Snapshot masih format lama (matriks belum dipecah per kelompok aktivitas). Jalankan ulang <code>php artisan peta-risiko:sync</code> agar filter kelompok di matriks bekerja.</div>`
      : kel && !nBaris && nKel
        ? `<div class="pr-empty" style="grid-column:1/-1">${DR.fmt(nKel)} baris ${DR.esc(nama)} tidak punya nilai kekerapan/konsekuensi di HIRA (ID HIRA v31 tidak ada di mv_hira_master), jadi tidak bisa digambar di matriks.</div>`
        : '';
    $('#dr-matriks').innerHTML = catatan || (DR.matriks(`Baseline HIRA (risiko awal) · ${wk}${akhiran}`, A, M) + DR.matriks(`Final · ${wk}${akhiran}`, F, M)
      + (kel && nKel > nBaris ? `<div class="dr-mute" style="grid-column:1/-1;font-size:12px">${DR.fmt(nBaris)} dari ${DR.fmt(nKel)} baris kelompok ini punya nilai risiko di HIRA; sisanya tidak masuk matriks.</div>` : ''));
    const hapus = $('#dr-mx-hapus');
    if (hapus) hapus.addEventListener('click', () => { state.kelMx = ''; render(); });
  }

  /** Palet berurutan (terang → gelap) untuk nilai 0–1, plus warna teks yang kontras. */
  const PALET_TM = [[255, 241, 220], [251, 191, 36], [234, 88, 12], [124, 45, 18]];
  function warnaTm(r) {
    const t = Math.max(0, Math.min(1, r)) * (PALET_TM.length - 1), i = Math.min(PALET_TM.length - 2, Math.floor(t)), f = t - i;
    const c = PALET_TM[i].map((v, j) => Math.round(v + (PALET_TM[i + 1][j] - v) * f));
    const terang = (0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2]) > 165;
    return { bg: `rgb(${c.join(',')})`, fg: terang ? '#3b1d0a' : '#fff' };
  }

  /** Tata letak treemap squarified (Bruls dkk.): item urut menurun, nilai v → kotak {x, y, w, h} dalam lebar × tinggi. */
  function squarify(items, W, H) {
    const total = items.reduce((s, i) => s + i.v, 0);
    if (!total) return [];
    let rest = items.map((i) => ({ ...i, a: i.v * W * H / total })), x = 0, y = 0, w = W, h = H;
    const out = [];
    const terburuk = (row, sisi) => {
      const s = row.reduce((a, b) => a + b.a, 0), mx = Math.max(...row.map((b) => b.a)), mn = Math.min(...row.map((b) => b.a));
      return Math.max(sisi * sisi * mx / (s * s), s * s / (sisi * sisi * mn));
    };
    while (rest.length) {
      const sisi = Math.min(w, h);
      let n = 1;
      while (n < rest.length && terburuk(rest.slice(0, n + 1), sisi) <= terburuk(rest.slice(0, n), sisi)) n++;
      const row = rest.slice(0, n), s = row.reduce((a, b) => a + b.a, 0);
      if (w >= h) {
        const cw = s / h; let cy = y;
        row.forEach((b) => { out.push({ ...b, x, y: cy, w: cw, h: b.a / cw }); cy += b.a / cw; });
        x += cw; w -= cw;
      } else {
        const rh = s / w; let cx = x;
        row.forEach((b) => { out.push({ ...b, x: cx, y, w: b.a / rh, h: rh }); cx += b.a / rh; });
        y += rh; h -= rh;
      }
      rest = rest.slice(n);
    }
    return out;
  }

  /**
   * Treemap kelompok aktivitas: luas = baris belum terkontrol penuh; warna = porsi baris tanpa kontrol sama sekali
   * (tanpa laporan + hanya bermasalah), diskalakan relatif min–maks antar kotak supaya perbedaannya terlihat.
   * Klik kotak / daftar 5 teratas memfilter matriks.
   */
  function renderTreemap(P, w) {
    const el = $('#dr-treemap');
    const items = M.proses.map((k) => {
      const v = jumlahF(P, (p) => vekKel(p, w, k.kode));
      // Pita: "buta" = tidak terkontrol di DL mana pun (pita 0%). Spec: tanpa laporan + hanya bermasalah.
      const buta = pitaAktif() ? v.none : v.none + v.gapo;
      return { kode: k.kode, nama: k.nama, t: v.t, gap: v.t - v.full, full: v.full, part: v.part, gapo: v.gapo, none: v.none, nb: buta, buta: v.t ? buta / v.t : 0 };
    }).filter((i) => i.gap > 0).sort((a, b) => b.gap - a.gap).map((i) => ({ ...i, v: i.gap }));
    if (!items.length) { el.innerHTML = '<div class="pr-empty">Tidak ada baris belum terkontrol penuh pada minggu ini.</div>'; $('#dr-tm-top').innerHTML = ''; $('#dr-tm-skala').innerHTML = ''; return; }
    const lo = Math.min(...items.map((i) => i.buta)), hi = Math.max(...items.map((i) => i.buta));
    const skala = (x) => (hi - lo < 0.005 ? 0.55 : 0.12 + 0.88 * (x - lo) / (hi - lo));
    const pilih = (k) => { state.kelMx = state.kelMx === k ? '' : k; render(); };
    const kls = (k) => `${state.kelMx === k ? ' on' : ''}${state.kelMx && state.kelMx !== k ? ' redup' : ''}`;
    const L = LBL(), belum = pitaAktif() ? `belum terkontrol di ${L.kFull}` : 'belum terkontrol penuh';
    const ket = (b) => `${b.nama}: ${DR.fmt(b.gap)} dari ${DR.fmt(b.t)} baris ${belum} (${DR.pct(b.gap, b.t)}); ${L.kPart} ${DR.fmt(b.part)}, ${L.kGapo} ${DR.fmt(b.gapo)}, ${L.kNone} ${DR.fmt(b.none)}; ${DR.pct(b.nb, b.t)} tidak terkontrol sama sekali`;

    const W = el.clientWidth || 800, H = el.clientHeight || 420, CELAH = 3;
    el.innerHTML = squarify(items, W, H).map((b) => {
      const x = b.x + CELAH / 2, y = b.y + CELAH / 2, lw = Math.max(0, b.w - CELAH), lh = Math.max(0, b.h - CELAH);
      const besar = lw > 130 && lh > 78, sedang = lw > 78 && lh > 46, kecil = lw > 34 && lh > 22;
      const { bg, fg } = warnaTm(skala(b.buta));
      const isi = besar
        ? `<span class="dr-tm-n">${DR.esc(b.nama)}</span><span class="dr-tm-v"><b>${DR.fmt(b.gap)}</b> baris<small>${DR.pct(b.gap, b.t)} ${pitaAktif() ? 'di bawah ' + L.kFull : 'gap'} · ${DR.pct(b.nb, b.t)} tanpa kontrol</small></span>`
        : sedang ? `<span class="dr-tm-n">${DR.esc(b.nama)}</span><span class="dr-tm-v"><b>${DR.fmt(b.gap)}</b></span>`
          : kecil ? `<span class="dr-tm-v"><b>${DR.fmt(b.gap)}</b></span>` : '';
      return `<button type="button" role="listitem" class="dr-tm-k${besar || sedang ? '' : ' kecil'}${kls(b.kode)}" data-kel="${DR.esc(b.kode)}" title="${DR.esc(ket(b))}" aria-label="${DR.esc(ket(b))}" aria-pressed="${state.kelMx === b.kode}"
        style="left:${x.toFixed(1)}px;top:${y.toFixed(1)}px;width:${lw.toFixed(1)}px;height:${lh.toFixed(1)}px;background:${bg};color:${fg}">${isi}</button>`;
    }).join('');

    const maks = items[0].gap;
    $('#dr-tm-top').innerHTML = `<h4>5 teratas · baris ${pitaAktif() ? 'di bawah ' + L.kFull : 'belum terkontrol penuh'}</h4>` + items.slice(0, 5).map((b, i) => {
      const { bg, fg } = warnaTm(skala(b.buta));
      return `<button type="button" class="dr-tm-i${kls(b.kode)}" data-kel="${DR.esc(b.kode)}" title="${DR.esc(ket(b))}" aria-pressed="${state.kelMx === b.kode}">
        <span class="r" style="background:${bg};color:${fg}">${i + 1}</span><span class="n">${DR.esc(b.nama)}</span><span class="v">${DR.fmt(b.gap)}</span>
        <span class="bar"><i style="width:${(100 * b.gap / maks).toFixed(1)}%;background:${bg}"></i></span></button>`;
    }).join('') + `<p class="dr-mute" style="margin:2px 0 0;font-size:11.5px">${DR.fmt(items.length)} kelompok punya baris belum terkontrol · total ${DR.fmt(items.reduce((s, b) => s + b.gap, 0))} baris.</p>`;

    $('#dr-tm-skala').innerHTML = hi - lo < 0.005
      ? `<span>Tanpa kontrol sama sekali: semua kelompok ±${DR.pct(lo, 1)}</span>`
      : `<span>Tanpa kontrol sama sekali</span><span>${DR.pct(lo, 1)}</span><i></i><span>${DR.pct(hi, 1)}</span><span class="dr-mute">(skala relatif antar kelompok)</span>`;

    document.querySelectorAll('#dr-treemap [data-kel], #dr-tm-top [data-kel]').forEach((t) => t.addEventListener('click', () => pilih(t.dataset.kel)));
  }

  // Treemap dihitung dari lebar wadah: gambar ulang saat ukuran layar berubah.
  let tundaUkur = 0;
  window.addEventListener('resize', () => { clearTimeout(tundaUkur); tundaUkur = setTimeout(() => { if (M && state.tab === 'site') renderTreemap(cakupanP(), state.w); }, 150); });

  /** Tabel per grup / per kelompok: komposisi satuan ketat minggu terpilih + perubahan vs minggu lalu. */
  function tabelCakupan(sel, judulKolom, daftar, f, tab) {
    const P = cakupanP(), w = state.w, NW = M.beranda.minggu.length;
    const baris = daftar.map(([kunci, nama]) => ({ kunci, nama, v: jumlahF(P, (p) => f(p, w, kunci)), v0: w + 1 < NW ? jumlahF(P, (p) => f(p, w + 1, kunci)) : null }))
      .filter((r) => r.v.t).sort((a, b) => b.v.t - a.v.t);
    const aktif = (k) => state.tab === tab && (tab === 'grup' ? state.grup : state.proses) === k;
    // Baris kelompok bisa dibuka: menampilkan aktivitas HIRA (M03) kelompok itu, di grup terpilih bila tab Group Lokasi.
    const bisaBuka = tab === 'proses' && !!M.beranda.akt;
    $(sel).innerHTML = `<thead><tr><th>${judulKolom}</th>${kepalaVek(LBL())}<th>Laporan bermasalah</th></tr></thead><tbody>`
      + (baris.map(({ kunci, nama, v, v0 }) => {
        const buka = bisaBuka && !!state.kbuka[kunci];
        const kepala = `<tr data-kunci="${DR.esc(kunci)}" class="br-a${aktif(kunci) || buka ? ' open' : ''}" style="cursor:pointer">
          <td>${bisaBuka ? `<button class="br-cr" type="button" aria-expanded="${buka}" aria-label="${buka ? 'Tutup' : 'Buka'} aktivitas ${DR.esc(nama)}">▸</button>` : ''}<span class="pr-name">${DR.esc(nama)}</span></td>${barisVek(v, v0)}<td class="dr-mute">${gapTxt(v)}</td></tr>`;
        return kepala + (buka ? barisAktivitas(kunci, P, w, NW) : '');
      }).join('')
        || '<tr><td colspan="8" class="pr-empty">Tidak ada baris.</td></tr>') + '</tbody>';
    document.querySelectorAll(`${sel} tr[data-kunci]`).forEach((tr) => tr.addEventListener('click', () => {
      if (bisaBuka) { state.kbuka[tr.dataset.kunci] = !state.kbuka[tr.dataset.kunci]; render(); return; }
      state.tab = tab;
      if (tab === 'grup') { state.grup = tr.dataset.kunci; $('#dr-grup').value = state.grup; } else { state.proses = tr.dataset.kunci; $('#dr-proses').value = state.proses; }
      render(); window.scrollTo({ top: 0, behavior: 'smooth' });
    }));
    document.querySelectorAll(`${sel} [data-kekel]`).forEach((a) => a.addEventListener('click', (e) => {
      e.preventDefault(); e.stopPropagation();
      state.tab = 'proses'; state.proses = a.dataset.kekel; $('#dr-proses').value = state.proses; render(); window.scrollTo({ top: 0, behavior: 'smooth' });
    }));
  }

  /** Sub-baris aktivitas HIRA satu kelompok; di tab Group Lokasi hanya aktivitas dan baris di grup terpilih. */
  function barisAktivitas(kel, P, w, NW) {
    const diGrup = state.tab === 'grup';
    const vek = (p, i, a) => vekDari(ambilKunci(diGrup ? (p.gka || [])[i] : (p.akt || [])[i], diGrup ? `${state.grup}|${a}` : a));
    const L = Object.entries(M.beranda.akt).filter(([, x]) => x[1] === kel)
      .map(([a, x]) => ({ nama: x[0], v: jumlahF(P, (p) => vek(p, w, a)), v0: w + 1 < NW ? jumlahF(P, (p) => vek(p, w + 1, a)) : null }))
      .filter((r) => r.v.t).sort((a, b) => (b.v.t - b.v.full) - (a.v.t - a.v.full) || b.v.t - a.v.t);
    const isi = L.map(({ nama, v, v0 }) => `<tr class="br-sub"><td style="padding-left:40px">${DR.esc(nama)}</td>${barisVek(v, v0)}<td class="dr-mute">${gapTxt(v)}</td></tr>`).join('')
      || `<tr class="br-sub"><td colspan="8" class="dr-mute" style="padding-left:40px">Tidak ada aktivitas pada level risiko terpilih${adaUnik() && M.beranda.ringkas.ALL.akt ? '' : ' (snapshot lama — jalankan ulang peta-risiko:sync)'}.</td></tr>`;
    const tautan = diGrup || state.tab !== 'proses' || state.proses !== kel
      ? `<tr class="br-sub"><td colspan="8" style="padding-left:40px"><a href="#" data-kekel="${DR.esc(kel)}">Lihat kelompok ini di semua grup ›</a></td></tr>` : '';
    return isi + tautan;
  }

  // ------------------------------------------------------------ Per PJA (konsep Beranda PJA, satuan ketat)
  /** Level risiko awal yang dihitung: Significant + High (bawaan spec) atau semua level yang ada di data. */
  const lvAmbil = (perLv) => (state.lv === 'ALL' ? Object.keys(perLv || {}) : ['Significant', 'High']);
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
  const KOSONG = { t: 0, full: 0, part: 0, gapo: 0, none: 0, g2: 0, g3: 0, g4: 0, cak: 0 };

  /** Vektor KOLOM_MGMT per level → komposisi untuk level risiko terpilih. */
  function vekDari(perLv) {
    const K = M.beranda.kolomMgmt, ix = Object.fromEntries(K.map((k, i) => [k, i])), c = Array(K.length).fill(0);
    lvAmbil(perLv).forEach((lv) => ((perLv || {})[lv] || []).forEach((v, i) => { c[i] += v; }));
    return { t: c[ix.tot], full: c[ix.full], part: c[ix.part], gapo: c[ix.gaponly], none: c[ix.none], g2: c[ix.closed], g3: c[ix.ulang], g4: c[ix.stop], cak: ix.cak === undefined ? 0 : c[ix.cak] };
  }
  /** Komposisi satu PJA pada minggu w. */
  const vekPja = (p, w) => vekDari((p.w || [])[w]);
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

  function trenPeriode(P, f = vekPja, L = LBL_SPEC) {
    const W = DR.BW, NW = M.beranda.minggu.length, col = [];
    for (let i = NW - 1; i >= 0; i--) {
      const v = P.reduce((a, p) => tambahVek(a, f(p, i)), KOSONG), n = Math.max(1, v.t), H = (x) => (100 * x / n).toFixed(1) + '%';
      col.push(`<div class="br-wc${i === state.w ? ' sel' : ''}" data-w="${i}" title="${DR.esc(DR.minggu(M.beranda.minggu[i], M.sampai, i, M.beranda.minggu[0]).label)}: ${L.kFull} ${DR.fmt(v.full)}, ${L.kPart} ${DR.fmt(v.part)}, ${L.kGapo} ${DR.fmt(v.gapo)}, ${L.kNone} ${DR.fmt(v.none)}">
        <div class="stk"><i style="height:${H(v.none)};background:${W.none}"></i><i style="height:${H(v.gapo)};background:${W.bm}"></i><i style="height:${H(v.part)};background:${W.part}"></i><i style="height:${H(v.full)};background:${W.ok}"></i></div>
        <span>${wKode(i)}${wBerjalan(i) ? '*' : ''}</span><span>${pctI(v.full, v.t)}</span></div>`);
    }
    return `<div class="br-wb">${col.join('')}</div>${wBerjalan(0) ? '<div class="dr-mute" style="font-size:12px">* minggu berjalan, belum penuh</div>' : ''}`;
  }

  function barisVek(v, v0) {
    return `<td>${DR.sb4(v.full, v.part, v.gapo, v.none)}</td><td class="r">${DR.fmt(v.t)}</td><td class="r"><b>${pctI(v.full, v.t)}</b>${v0 ? '<br>' + delta(v.full, v.t, v0.full, v0.t, true) : ''}</td>
      <td class="r">${pctI(v.part, v.t)}</td><td class="r">${pctI(v.gapo, v.t)}</td><td class="r">${pctI(v.none, v.t)}</td>`;
  }
  const kepalaVek = (L = LBL_SPEC) => `<th style="min-width:200px">Komposisi</th><th class="r">Baris</th><th class="r">${L.kFull === 'Terkontrol penuh' ? 'Terkontrol penuh' : 'Terkontrol ' + L.kFull}</th><th class="r">${L.kPart}</th><th class="r">${L.kGapo}</th><th class="r">${L.kNone}</th>`;
  const KEPALA_VEK = kepalaVek();

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
    const isi = (obj, f) => lvAmbil(obj).forEach((lv) => Object.entries((obj || {})[lv] || {}).forEach(([k, v]) => f(kel[k] ||= { t: 0, g: 0, g0: 0 }, v)));
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
      lvAmbil(((p.w || [])[state.w]) || {}).forEach((lv) => {
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
    const kpi = (ac, kc, judul, angka, isi, k) => `<div class="br-kpi" style="--ac:${ac};--kc:${kc}">${ikonKpi(k)}<small>${judul}</small><b>${angka}</b><em>${isi}</em></div>`;

    panel.innerHTML = `<div class="br-kpis">
        ${kpi('var(--navy)', 'var(--navy)', `Baris baseline · ${state.site === 'ALL' ? 'seluruh BC' : DR.esc(state.site)} · ${wk}`, DR.fmt(B.t), `${diSite.length} PJA · ${state.lv === 'ALL' ? 'semua level' : 'Significant + High'}`, 't')}
        ${kpi(W.ok, 'var(--green-d)', 'Terkontrol penuh', pctI(B.full, B.t), `${DR.fmt(B.full)} baris`, 'full')}
        ${kpi(W.part, '#9A7300', 'Terkontrol sebagian', pctI(B.part, B.t), `${DR.fmt(B.part)} baris · gap total ${pctI(G, B.t)}`, 'part')}
        ${kpi(W.none, W.bm, 'Tanpa laporan', pctI(B.none, B.t), `${DR.fmt(B.none)} baris${B.gapo ? ` · ${DR.fmt(B.gapo)} hanya bermasalah` : ''}`, 'none')}
      </div>`
      + tabelSite(semua, wk) + tabelDaftar(tampil, semua.length, wk);

    panel.querySelectorAll('tr[data-site]').forEach((tr) => tr.addEventListener('click', () => {
      state.site = state.site === tr.dataset.site ? 'ALL' : tr.dataset.site; $('#dr-site').value = state.site; render();
    }));
    panel.querySelectorAll('tr.br-a[data-id]').forEach((tr) => tr.addEventListener('click', () => { state.buka[tr.dataset.id] = !state.buka[tr.dataset.id]; state.baru = tr.dataset.id; renderPja(); state.baru = ''; }));
    panel.querySelectorAll('tr[data-situs]').forEach((tr) => tr.addEventListener('click', () => { state.situs[tr.dataset.situs] = !siteTerbuka(tr.dataset.situs); renderPja(); }));
    panel.querySelectorAll('[data-semua]').forEach((b) => b.addEventListener('click', () => {
      M.sites.forEach((s) => { state.situs[s] = b.dataset.semua === '1'; }); renderPja();
    }));
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

  /** Baris judul site di tabel Semua PJA: subtotal satuan ketat + temuan open dari PJA site itu. */
  function judulSite(site, L) {
    const v = jumlah(L), op = L.reduce((s, o) => s + o.op, 0), lewat = L.reduce((s, o) => s + o.lewat, 0);
    const lama30 = L.reduce((s, o) => s + o.lama30, 0), ins = L.reduce((s, o) => s + o.insiden, 0), tinggi = L.filter((o) => o.pri === 'T').length;
    const buka = siteTerbuka(site);
    return `<tr class="br-a${buka ? ' open' : ''}" data-situs="${DR.esc(site)}" style="background:#EEF3FA;font-weight:700;cursor:pointer"><td><button class="br-cr" type="button" aria-expanded="${buka}" aria-label="${buka ? 'Tutup' : 'Buka'} daftar PJA ${DR.esc(site)}">▸</button>${DR.esc(site)}<br><span class="dr-mute" style="font-weight:400;padding-left:24px">${L.length} PJA${tinggi ? ` · ${tinggi} prioritas tinggi` : ''}</span></td>
      <td>${v.t ? DR.sb4(v.full, v.part, v.gapo, v.none) + `<span class="dr-mute" style="font-weight:400">${DR.fmt(v.t)} baris</span>` : ''}</td>
      <td class="r">${pctI(v.full, v.t)}</td><td class="r">${pctI(v.t - v.full, v.t)}</td><td class="r">${DR.fmt(v.part)}</td><td class="r">${DR.fmt(v.none)}</td>
      <td class="r">${DR.fmt(op)}</td><td class="r">${DR.fmt(lewat)}<br><span class="dr-mute" style="font-weight:400">${pctI(lewat, op)}</span></td>
      <td class="r">${DR.fmt(lama30)}<br><span class="dr-mute" style="font-weight:400">${pctI(lama30, op)}</span></td><td class="r">${ins || ''}</td><td></td></tr>`;
  }

  /**
   * Site di tabel Semua PJA tertutup secara bawaan; terbuka bila diklik, bila satu site dipilih di filter,
   * atau saat mencari nama / memfilter prioritas (supaya hasilnya langsung terlihat).
   */
  function siteTerbuka(site) {
    if (site in state.situs) return state.situs[site];
    return state.site !== 'ALL' || !!state.cari || state.pri !== 'all';
  }

  function tabelDaftar(L, total, wk) {
    // Dikelompokkan per site (urutan daftar site); di dalam site tetap mengikuti pilihan "Urutkan".
    const perSite = new Map([...M.sites, ...L.map((o) => o.site)].map((s) => [s, []]));
    L.forEach((o) => perSite.get(o.site).push(o));
    const rows = [...perSite].filter(([, d]) => d.length).map(([site, d]) => judulSite(site, d) + (!siteTerbuka(site) ? '' : d.map((o) => {
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
    }).join(''))).join('');
    return `<div class="pr-card"><div class="pr-card-head"><div><b>Semua PJA per site · ${wk}</b><small>${L.length} dari ${total} PJA · klik site untuk membuka daftar PJA-nya, klik PJA untuk rincian · subtotal site = jumlah baris tingkat PJA</small></div>
        <div style="display:flex;gap:8px"><button type="button" class="pr-back" data-semua="1">Buka semua</button><button type="button" class="pr-back" data-semua="0">Tutup semua</button></div></div>
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
