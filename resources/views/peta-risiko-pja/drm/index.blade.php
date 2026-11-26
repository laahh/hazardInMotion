@extends('peta-risiko-pja._layout-drm')

@section('title', 'Peta Risiko PJA')

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
      <label data-ctl="site">Site <select id="dr-site"><option value="ALL">Semua site</option></select></label>
      <label data-ctl="grup" hidden>Group Lokasi <select id="dr-grup"></select></label>
      <label data-ctl="proses" hidden>Kelompok aktivitas <select id="dr-proses"></select></label>
      <label>Perusahaan <select id="dr-perusahaan"><option value="ALL">Semua perusahaan</option></select></label>
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
  /* Kolom HIRA (asal dokumen) di tabel bahaya detail lokasi */
  .dr-hira { display: inline-flex; flex-direction: column; align-items: flex-start; gap: 1px; max-width: 190px; padding: 4px 8px; border: 1px solid rgba(57, 82, 188, .2);
    border-radius: 8px; background: #f7f8fe; color: #2c2f31; font: inherit; font-size: 12px; text-align: left; cursor: pointer; }
  .dr-hira:hover, .dr-hira:focus-visible { border-color: #3952bc; outline: none; }
  .dr-hira small { max-width: 172px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #595c5e; font-size: 11px; }
  .dr-hira-n { color: #3952bc; font-weight: 700; }
  .dr-hira-kosong { color: #9a5b00; font-weight: 700; }
  .dr-hira-dok { margin-bottom: 14px; }
  .dr-hira-dok-k { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 6px; font-size: 14px; }
  .dr-hira-dok th { text-align: left; color: #595c5e; font-weight: 600; }

  /* PJA yang melaporkan di satu detail lokasi (tab Group Lokasi) */
  .dr-pl-wrap { margin-bottom: 16px; }
  .dr-pl-head { display: flex; flex-wrap: wrap; gap: 6px 16px; align-items: baseline; margin-bottom: 8px; font-size: 13px; }
  .dr-pl-leg { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 12px; color: #595c5e; }
  .dr-pl-leg i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 4px; vertical-align: -1px; background: var(--c); }
  .dr-pl { border: 1px solid rgba(0, 0, 0, .08); border-radius: 12px; background: #fff; margin-bottom: 6px; }
  .dr-pl > summary, .dr-pl-row { display: grid; grid-template-columns: 18px minmax(200px, 2.2fr) 70px 70px minmax(140px, 1.6fr) 110px minmax(120px, 1fr); gap: 4px 12px;
    align-items: center; padding: 10px 12px; font-size: 13px; list-style: none; }
  .dr-pl > summary { cursor: pointer; border-radius: 12px; }
  .dr-pl > summary::-webkit-details-marker { display: none; }
  .dr-pl > summary:hover, .dr-pl > summary:focus-visible { background: #f7f8fe; outline: none; }
  .dr-pl > summary::before { content: '▸'; color: #8a939c; transition: transform .15s; }
  .dr-pl[open] > summary::before { transform: rotate(90deg); }
  .dr-pl-row::before { content: ''; }
  .dr-pl-luar { margin-top: 14px; padding-top: 12px; border-top: 1px dashed rgba(0, 0, 0, .15); }
  .dr-pl-luar > summary { display: flex; flex-wrap: wrap; gap: 6px 16px; align-items: baseline; margin-bottom: 8px; font-size: 13px; cursor: pointer; }
  .dr-pl-luar > summary b { color: #6b4fbb; }
  .dr-pl-row.kepala { padding: 0 12px 4px; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #595c5e; }
  .dr-pl-row.kosong { border: 1px dashed rgba(0, 0, 0, .15); border-radius: 12px; margin-bottom: 6px; color: #595c5e; }
  .dr-pl .r, .dr-pl-row .r { text-align: right; font-variant-numeric: tabular-nums; }
  .dr-pl-tag { display: inline-block; padding: 1px 7px; border-radius: 999px; font-size: 11px; font-weight: 600; margin-left: 4px; background: #eef1fd; color: #3952bc; }
  .dr-pl-tag.milik { background: #e6f4ea; color: #1e7a3c; }
  .dr-pl-bar { display: flex; height: 10px; border-radius: 999px; overflow: hidden; background: #eef0f2; }
  .dr-pl-bar span { background: var(--c); }
  .dr-pl-isi { padding: 4px 12px 12px 42px; }
  .dr-pl-chip { display: inline-flex; gap: 6px; align-items: center; padding: 3px 10px; border-radius: 999px; background: #f5f7f9; font-size: 12px; margin: 0 6px 6px 0; }
  .dr-pl-chip i { width: 8px; height: 8px; border-radius: 2px; background: var(--c); }
  @media (max-width: 900px) {
    .dr-pl > summary, .dr-pl-row { grid-template-columns: 18px minmax(0, 1fr) 60px; }
    .dr-pl > summary > :nth-child(n+3), .dr-pl-row > :nth-child(n+3) { display: none; }
  }

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
  .dr-tm-i .bar .dr-tm-bar { margin: 0; height: 100%; box-shadow: none; }
  /* Komposisi tiga status (Terkontrol / Ada Gap / Open Risk) di kotak treemap & daftar. */
  .dr-tm-bar { display: flex; height: 6px; margin-top: 6px; border-radius: 3px; overflow: hidden; background: rgba(255, 255, 255, .55); box-shadow: 0 0 0 1px rgba(255, 255, 255, .6); }
  .dr-tm-bar i { display: block; height: 100%; }
  .dr-tm-st { display: flex; flex-wrap: wrap; gap: 2px 10px; margin-top: 6px; font-size: 11px; font-weight: 600; }
  .dr-tm-st span { display: inline-flex; align-items: center; gap: 4px; }
  .dr-tm-st i, .dr-tm-leg i { width: 8px; height: 8px; border-radius: 2px; display: inline-block; box-shadow: 0 0 0 1px rgba(255, 255, 255, .7); }
  .dr-tm-st.kecil { grid-column: 2 / span 2; margin-top: 0; color: #595c5e; font-weight: 500; }
  .dr-tm-leg { display: inline-flex; gap: 12px; margin-right: 8px; }
  .dr-tm-leg span { display: inline-flex; align-items: center; gap: 5px; }
  .dr-tm-skala { display: flex; align-items: center; gap: 10px; margin-top: 12px; font-size: 11.5px; color: #595c5e; flex-wrap: wrap; }
  .dr-tm-skala > i { width: 180px; height: 8px; border-radius: 4px; background: linear-gradient(90deg, #fdd66e, #f8a024, #ea6216, #c42d1c, #7a1618); }
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
        <small>Status sama dengan kartu di atas: Teridentifikasi &amp; Terkontrol · Teridentifikasi - Ada Gap · Tidak Teridentifikasi (Open Risk). Luas kotak = bahaya belum terkontrol (Ada Gap + Open Risk); warna = porsi Open Risk. Klik kotak atau daftar untuk memfilter matriks di bawah; klik lagi untuk semua kelompok.</small></div></div>
      <div class="pr-card-body">
        <div class="dr-tm-wrap">
          <div>
            <div id="dr-treemap" class="dr-tm" role="list" aria-label="Treemap kelompok aktivitas"></div>
            <div class="dr-tm-skala" id="dr-tm-skala"></div>
          </div>
          <div class="dr-tm-top" id="dr-tm-top" aria-label="Lima kelompok dengan bahaya Ada Gap dan Open Risk terbanyak"></div>
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

  {{-- Tab Beranda PJA: tampilan tab Semua PJA (kartu status + baris PJA + rincian) untuk satu PJA terpilih.
       Analisis lengkap (tren, heatmap, rincian aktivitas, flag) tetap di halaman /peta-risiko-pja/pja/{id}. --}}
  <div id="dr-panel-bpja" class="br-root" style="margin-top:20px" hidden>
    <div class="pr-card">
      <div class="pr-card-body" style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
        <label class="dr-bpja-pilih" style="display:inline-flex;align-items:center;gap:10px;font-weight:700;font-size:13px">
          <span class="material-symbols-outlined" style="color:var(--primary)">badge</span> PJA
          <select id="dr-bpja-pja" style="min-width:320px;max-width:100%;padding:8px 10px;border-radius:10px;border:1px solid rgba(171,173,175,.45);font-weight:600"></select>
        </label>
        <span class="dr-mute" id="dr-bpja-sub" style="font-size:13px"></span>
        <a class="pr-back" id="dr-bpja-buka" href="#" style="margin-left:auto"><span class="material-symbols-outlined" style="font-size:18px">open_in_new</span> Analisis lengkap PJA</a>
      </div>
    </div>
    <div id="dr-bpja-isi"><div class="pr-card"><div class="pr-empty">Memuat…</div></div></div>
  </div>
@endif
@endsection

@section('scripts')
@include('peta-risiko-pja.drm._ui')
@if ($meta)
@include('peta-risiko-pja.drm._beranda-poin')
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
    w: q.has('w') ? Math.max(0, parseInt(q.get('w'), 10) || 0) : -1, lv: q.get('lv') === 'ALL' ? 'ALL' : 'SH', pri: 'all', urut: 'skor', buka: {}, pbuka: {}, situs: {}, kbuka: {}, dlbuka: {}, pjaB: q.get('pja') || '', kelMx: q.get('kel') || '',
    perusahaan: q.get('perusahaan') || 'ALL' };
  const TAB_W = ['pja', 'periode'];
  let M = null;

  fetch(URL_META, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((m) => { M = m; init(); })
    .catch(() => { $('#dr-kpi4').innerHTML = '<div class="pr-card"><div class="pr-empty">Gagal memuat data.</div></div>'; });

  function init() {
    KEPALA_VEK = kepalaVek(); // M sudah siap di sini — pakai status3 (idk/idg/tid) kalau snapshot sudah punya.
    $('#dr-site').innerHTML += M.sites.map((s) => `<option value="${DR.esc(s)}">${DR.esc(s)}</option>`).join('');
    $('#dr-perusahaan').innerHTML += (M.perusahaan || []).map((p) => `<option value="${DR.esc(p)}">${DR.esc(p)}</option>`).join('');
    if (state.perusahaan !== 'ALL' && !(M.perusahaan || []).includes(state.perusahaan)) state.perusahaan = 'ALL';
    $('#dr-perusahaan').value = state.perusahaan;
    $('#dr-perusahaan').addEventListener('change', (e) => { state.perusahaan = e.target.value; render(); });
    // Grup lokasi tanpa baris baseline sama sekali (mis. grup master dengan 0 baris HIRA) tidak ditampilkan.
    M.grup = grupBerisi();
    $('#dr-site').value = state.site; $('#dr-lv').value = state.lv;
    // Group Lokasi & Kelompok Aktivitas tergantung site terpilih (keputusan user 26 Nov 2026) — isi SETELAH
    // #dr-site di atas punya nilai, supaya daftar yang muncul memang sesuai site yang sedang aktif saat halaman
    // dibuka; kalau site itu tidak punya grup/kelompok tertentu, opsinya tidak muncul sama sekali.
    isiGrup();
    isiProses();
    $('#dr-proses').addEventListener('change', (e) => { state.proses = e.target.value; render(); });
    document.querySelectorAll('.dr-tabs button').forEach((b) => b.addEventListener('click', () => { state.tab = b.dataset.tab; render(); }));
    $('#dr-site').addEventListener('change', (e) => { state.site = e.target.value; isiGrup(); isiProses(); render(); });
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
    const sumber = M.beranda && M.beranda.ringkas && M.beranda.ringkas.ALL && M.beranda.ringkas.ALL.ALL ? [M.beranda.ringkas.ALL.ALL] : ((M.beranda || {}).mgmt || []);
    const ada = new Set();
    sumber.forEach((p) => (p.grp || []).forEach((perLv) => Object.values(perLv || {}).forEach((m) => Object.entries(m || {}).forEach(([g, v]) => {
      if ((v || [])[0] > 0) ada.add(g);
    }))));
    const hasil = M.grup.filter((g) => ada.has(g));
    return hasil.length ? hasil : M.grup;
  }

  /** Baris ringkasan ("p"-shaped, punya .grp/.kel) buat site itu: pakai pita cakupan kalau ada, kalau tidak gabung per-PJA. */
  function sumberSite(site) {
    return site === 'ALL'
      ? (M.beranda && M.beranda.ringkas && M.beranda.ringkas.ALL && M.beranda.ringkas.ALL.ALL ? [M.beranda.ringkas.ALL.ALL] : ((M.beranda || {}).mgmt || []))
      : (M.beranda && M.beranda.ringkas && M.beranda.ringkas[site] && M.beranda.ringkas[site].ALL ? [M.beranda.ringkas[site].ALL]
        : ((M.beranda || {}).mgmt || []).filter((p) => p.site === site));
  }

  /**
   * Group Lokasi (dari M.grup, sudah difilter global non-kosong) yang punya minimal satu baris baseline DI SITE
   * itu (keputusan user 26 Nov 2026: filter Group Lokasi tergantung site terpilih — mis. site tanpa grup
   * tertentu tidak menampilkan grup itu). site === 'ALL' → semua grup yang punya data di mana pun.
   */
  function grupBerisiSite(site) {
    const ada = new Set();
    sumberSite(site).forEach((p) => (p.grp || []).forEach((perLv) => Object.values(perLv || {}).forEach((m) => Object.entries(m || {}).forEach(([g, v]) => {
      if ((v || [])[0] > 0) ada.add(g);
    }))));
    const hasil = M.grup.filter((g) => ada.has(g));
    return hasil.length ? hasil : M.grup;
  }

  /** Isi/perbarui dropdown Group Lokasi sesuai site terpilih, pertahankan pilihan kalau masih tersedia. */
  function isiGrup() {
    const opsi = grupBerisiSite(state.site);
    $('#dr-grup').innerHTML = opsi.map((g) => `<option value="${DR.esc(g)}">${DR.esc(g)}</option>`).join('');
    if (!state.grup || !opsi.includes(state.grup)) state.grup = opsi[0];
    $('#dr-grup').value = state.grup;
  }

  /**
   * Kelompok aktivitas yang punya minimal satu baris baseline DI SITE itu (keputusan user 26 Nov 2026: filter
   * Kelompok Aktivitas tergantung site terpilih — mis. HO cuma menampilkan kelompok yang benar ada di HO).
   * site === 'ALL' → sama dengan tidak difilter (semua kelompok yang punya data di mana pun).
   */
  function prosesBerisi(site) {
    const ada = new Set();
    sumberSite(site).forEach((p) => (p.kel || []).forEach((perLv) => Object.values(perLv || {}).forEach((m) => Object.entries(m || {}).forEach(([k, v]) => {
      if ((v || [])[0] > 0) ada.add(k);
    }))));
    const hasil = M.proses.filter((p) => ada.has(p.kode));
    return hasil.length ? hasil : M.proses;
  }

  /** Isi/perbarui dropdown Kelompok aktivitas sesuai site terpilih, pertahankan pilihan kalau masih tersedia. */
  function isiProses() {
    const opsi = prosesBerisi(state.site);
    $('#dr-proses').innerHTML = opsi.map((p) => `<option value="${DR.esc(p.kode)}">${DR.esc(p.nama)}</option>`).join('');
    if (!state.proses || !opsi.some((p) => p.kode === state.proses)) state.proses = opsi[0].kode;
    $('#dr-proses').value = state.proses;
  }

  function simpanUrl() {
    const p = new URLSearchParams({ tab: state.tab, site: state.site, w: state.w });
    if (state.lv === 'ALL') p.set('lv', 'ALL');
    if (state.tab === 'site' && state.kelMx) p.set('kel', state.kelMx);
    if (state.tab === 'grup') p.set('grup', state.grup);
    if (state.tab === 'proses') p.set('proses', state.proses);
    if (state.tab === 'bpja' && state.pjaB) p.set('pja', state.pjaB);
    if (state.perusahaan !== 'ALL') p.set('perusahaan', state.perusahaan);
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
    // Beranda PJA memakai filter periode & risiko awal yang sama dengan Semua PJA; site ditentukan oleh PJA terpilih.
    document.querySelector('[data-ctl="site"]').hidden = bpja;
    if (bpja) {
      $('#dr-panel-cakupan').hidden = true;
      bukaBeranda();
    } else if (TAB_W.includes(state.tab)) renderW(); else renderCakupan();
    simpanUrl();
  }

  function kosong() { return Array.from({ length: 5 }, () => [0, 0, 0, 0, 0]); }

  // ------------------------------------------------------------ Beranda PJA = tampilan Semua PJA untuk satu PJA
  /** Isi pemilih PJA (dikelompokkan per site) sekali, lalu gambar PJA terpilih untuk minggu & level di bilah filter. */
  function bukaBeranda() {
    const sel = $('#dr-bpja-pja');
    const semuaL = ((M.beranda || {}).mgmt || []).slice().sort((a, b) => a.site.localeCompare(b.site) || a.nama.localeCompare(b.nama));
    if (!semuaL.length) { $('#dr-bpja-isi').innerHTML = '<div class="pr-card"><div class="pr-empty">Snapshot belum memuat data PJA. Jalankan ulang <code>php artisan peta-risiko:sync</code>.</div></div>'; return; }
    const L = semuaL.filter((p) => state.perusahaan === 'ALL' || (p.perusahaan || []).includes(state.perusahaan));
    if (!L.length) { $('#dr-bpja-isi').innerHTML = '<div class="pr-card"><div class="pr-empty">Tidak ada PJA dengan personil dari perusahaan ini.</div></div>'; return; }
    const perSite = {};
    L.forEach((p) => { (perSite[p.site] ||= []).push(p); });
    sel.innerHTML = Object.entries(perSite).map(([site, d]) => `<optgroup label="${DR.esc(site)}">${d.map((p) => `<option value="${DR.esc(p.id)}">${DR.esc(p.nama)}</option>`).join('')}</optgroup>`).join('');
    if (!sel.dataset.wired) {
      sel.addEventListener('change', () => { state.pjaB = sel.value; renderBpja(); simpanUrl(); });
      sel.dataset.wired = '1';
    }
    if (!state.pjaB || !L.some((p) => String(p.id) === String(state.pjaB))) {
      state.pjaB = String((L.find((p) => state.site === 'ALL' || p.site === state.site) || L[0]).id);
    }
    sel.value = state.pjaB;
    renderBpja();
  }

  /** Kartu status (sama dengan tab Semua PJA, klik = daftar baris PJA ini) + baris PJA dengan rincian kelompok & tindak lanjut. */
  function renderBpja() {
    const isi = $('#dr-bpja-isi');
    if (!formatBaru()) { isi.innerHTML = pesanLama(); return; }
    const p = M.beranda.mgmt.find((x) => String(x.id) === String(state.pjaB));
    if (!p) return;
    const NW = M.beranda.minggu.length, wk = wKode(state.w);
    const o = dataPja().find((x) => x.id === String(p.id));
    $('#dr-bpja-buka').href = URL_PJA.replace('__ID__', p.id) + '?w=' + state.w;
    $('#dr-bpja-sub').textContent = `${p.site} · ${p.ndl} detail lokasi · data sampai ${M.sampai.split('-').reverse().join('/')}`;
    const V = vekPja(p, state.w), V0 = state.w + 1 < NW ? vekPja(p, state.w + 1) : null;
    isi.innerHTML = kartu4(V, V0, `${DR.esc(p.nama)} · ${wk}`) + (o ? tabelSatuPja(o, wk) : '') + '<div id="dr-bpja-poin"></div>';
    // Poin Beranda PJA (lokasi & risiko, insiden, personil, identifikasi harian, open risk, temuan): _beranda-poin.
    // perusahaan: supaya tabel detail lokasi/coverage & personil di dalamnya ikut tersaring (keputusan user 26
    // Nov 2026) — bukan cuma daftar pemilih PJA di atas yang sudah tersaring sejak awal.
    BerandaPoin({ el: $('#dr-bpja-poin'), meta: M, id: String(p.id), w: state.w, lv: state.lv, perusahaan: state.perusahaan });
  }

  function tabelSatuPja(o, wk) {
    return `<div class="pr-card" style="margin-top:20px"><div class="pr-card-head"><div><b>${DR.esc(o.nama)} · ${wk}</b><small>Satuan sama dengan tab Semua PJA: satu baris = kelompok aktivitas × bahaya-risiko di PJA ini, tanpa flag</small></div></div>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl" style="min-width:1100px"><thead><tr><th style="min-width:240px">PJA</th><th style="min-width:200px">Komposisi</th>
      <th class="r">Terkontrol penuh</th><th class="r">Gap</th><th class="r">Sebagian</th><th class="r">Tanpa laporan</th>
      <th class="r">Open</th><th class="r">Lewat janji</th><th class="r">Open &gt; 30 hari</th><th class="r">Insiden ${M.beranda.hari} hr</th><th>Prioritas</th></tr></thead>
      <tbody><tr class="br-a open">
        <td><b>${DR.esc(o.nama)}</b><br><span class="dr-mute">${DR.esc(o.site)} · ${o.ndl} detail lokasi · ${DR.fmt(o.personil)} personil</span></td>
        <td>${o.tot ? DR.sb4(o.full, o.part, o.gapo, o.none) + `<span class="dr-mute">${DR.fmt(o.tot)} baris</span>` : '<span class="dr-mute">tanpa baris baseline</span>'}</td>
        <td class="r">${pctI(o.full, o.tot)}</td><td class="r"><b>${pctI(o.tot - o.full, o.tot)}</b></td><td class="r">${DR.fmt(o.part)}</td><td class="r">${DR.fmt(o.none)}</td>
        <td class="r"><b>${DR.fmt(o.op)}</b></td><td class="r">${DR.fmt(o.lewat)}<br><span class="dr-mute">${pctI(o.lewat, o.op)}</span></td>
        <td class="r">${DR.fmt(o.lama30)}<br><span class="dr-mute">${pctI(o.lama30, o.op)}</span></td>
        <td class="r">${o.insiden ? `<b class="br-red">${o.insiden}</b>` : ''}</td>
        <td><span class="br-pill" style="background:${PRI[o.pri][1]}">${PRI[o.pri][0]}</span><br><span class="dr-mute">skor ${o.skor}</span></td></tr>
      ${rincian(o)}</tbody></table></div></div>`;
  }

  // Baris rincian per status satu PJA (modal kartu): disimpan sync di berkas pja/{id}.json → beranda.rincian.
  const URL_PJA_DATA = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.pja.data', ['pja' => '999999999'])) }}.replace('999999999', '__ID__');
  // Berkas pja/{id}.json dimuat sekali per PJA dan dipakai bersama (modal kartu Beranda PJA, bahaya belum tercover per DL).
  const dataPjaPenuh = {};
  const muatDataPja = (id) => (dataPjaPenuh[id] ||= fetch(URL_PJA_DATA.replace('__ID__', encodeURIComponent(id)), { headers: { Accept: 'application/json' } })
    .then((r) => { if (!r.ok) throw new Error(`Gagal memuat data PJA (HTTP ${r.status}).`); return r.json(); })
    .catch((e) => { delete dataPjaPenuh[id]; throw e; }));
  const muatRincianPja = (id) => muatDataPja(id).then((d) => ((d.beranda || {}).rincian || null));
  const namaPjaB = () => ((M.beranda.mgmt.find((x) => String(x.id) === String(state.pjaB)) || {}).nama || 'PJA');
  /** Parameter cakupan untuk laporan SAP / item satu baris: PJA terpilih (tab Beranda PJA) atau site / grup. */
  const cakupanSap = () => (state.tab === 'bpja' ? { site: 'ALL', pja: state.pjaB } : { site: state.site, ...(state.tab === 'grup' ? { grup: state.grup } : {}) });

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
  const adaUnik = () => !!(M.beranda.ringkas && M.beranda.ringkas.ALL && M.beranda.ringkas.ALL.ALL);
  /**
   * Entri ringkasan baseline unik untuk site × perusahaan terpilih. Pecahan per perusahaan HANYA dihitung di
   * cakupan 'ALL' (semua site) — menyilangkan tiap site × tiap perusahaan sempat membuat sync produksi kehabisan
   * memori (lihat DrmSnapshotBuilder::ringkasUnik) — jadi site tertentu + perusahaan tertentu sekaligus jatuh
   * balik ke ringkasan 'ALL' site untuk perusahaan itu (lihat jatuhBalikSite()).
   */
  const ringkasAktif = () => {
    const langsung = (M.beranda.ringkas[state.site] || {})[state.perusahaan];
    if (langsung !== undefined || state.perusahaan === 'ALL') return langsung;
    return (M.beranda.ringkas.ALL || {})[state.perusahaan];
  };
  /** true bila kartu/tabel di atas sebenarnya gabungan SEMUA site (site spesifik + perusahaan tidak disilangkan di snapshot). */
  const jatuhBalikSite = () => state.perusahaan !== 'ALL' && state.site !== 'ALL'
    && (M.beranda.ringkas[state.site] || {})[state.perusahaan] === undefined && (M.beranda.ringkas.ALL || {})[state.perusahaan] !== undefined;
  /**
   * Sumber angka tab Overview / Group Lokasi / Kelompok Aktivitas: ringkasan baseline unik cakupan itu
   * (satu "PJA semu" berisi semua detail lokasi site × perusahaan). Snapshot lama: jumlah baris tingkat PJA.
   */
  const cakupanP = () => (adaUnik() ? [ringkasAktif()].filter(Boolean) : pjaDiSite());

  /** Ikon kartu KPI (gaya template dashboard-peer): kunci kategori → [ikon Material, latar, warna]. */
  const IKON_KPI = { t: ['inventory_2', 'rgba(57,82,188,.1)', '#3952bc'], full: ['verified', '#dcfce7', '#16a34a'], part: ['rule', '#fef3c7', '#b45309'],
    gapo: ['report', '#ffedd5', '#c2410c'], none: ['visibility_off', '#fde4e4', '#b42318'],
    laten: ['history', '#f1e8fa', '#7e3fb3'], turun: ['trending_down', '#dcfce7', '#16a34a'] };
  const ikonKpi = (k) => { const [i, b, c] = IKON_KPI[k] || IKON_KPI.t; return `<span class="tp-ikon" style="--ib:${b};--ic:${c}" aria-hidden="true"><span class="material-symbols-outlined">${i}</span></span>`; };

  /**
   * Label kategori. Ringkasan baseline unik (tab Overview / Group Lokasi / Kelompok Aktivitas) memakai pita cakupan
   * detail lokasi (keputusan user 17 Nov 2026); tab Semua PJA tetap memakai kategori spec per PJA.
   */
  const pitaAktif = () => adaUnik() && !!M.beranda.ringkas.ALL.ALL.pita;
  const LBL_SPEC = { full: 'Terkontrol penuh', part: 'Terkontrol sebagian', gapo: 'Hanya laporan bermasalah', none: 'Tanpa laporan',
    kFull: 'Terkontrol penuh', kPart: 'Sebagian', kGapo: 'Hanya bermasalah', kNone: 'Tanpa laporan' };
  function LBL() {
    if (!pitaAktif()) return LBL_SPEC;
    const [atas, bawah] = M.beranda.ringkas.ALL.ALL.pita.map((x) => Math.round(100 * x));
    return { full: `Terkontrol di ≥${atas}% DL`, part: `Terkontrol di ${bawah}–${atas}% DL`, gapo: `Terkontrol di <${bawah}% DL`, none: 'Tidak terkontrol di DL mana pun',
      kFull: `≥${atas}% DL`, kPart: `${bawah}–${atas}% DL`, kGapo: `<${bawah}% DL`, kNone: '0% DL' };
  }

  /**
   * Kartu KPI: angka (bukan persen) dengan status gabungan antar detail lokasi, gaya dashboard DRM 2.1 lama.
   * Snapshot tanpa kolom status (sebelum 17 Nov 2026) memakai kartu persen lama.
   */
  function kartu4(V, V0, judul) {
    // Snapshot dihitung sebelum kolom status ada: kartu tetap berbentuk angka, isinya "–" sampai sync ulang.
    const ada = M.beranda.kolomMgmt.includes('idk');
    const R = adaUnik() ? ringkasAktif() : null;
    // Selisih jumlah vs minggu lalu; naikBaik = kenaikan berarti membaik (hijau).
    const beda = (k, naikBaik) => {
      if (!ada || !V0) return '';
      const d = V[k] - V0[k];
      if (!d) return 'sama dengan minggu lalu';
      const baik = (d > 0) === naikBaik;
      return `<span style="color:${baik ? 'var(--green-d)' : '#c0262d'}">${d > 0 ? '▲' : '▼'} ${DR.fmt(Math.abs(d))}</span> vs minggu lalu`;
    };
    const klik = (k) => `class="br-kpi br-kpi-klik" data-kat="${k}" role="button" tabindex="0" aria-haspopup="dialog"`;
    const kartu = (warna, ik, k, label, ket, naikBaik) => `<div ${klik(k)} style="--ac:${warna};--kc:${warna}" title="${DR.esc(ket)} · klik untuk melihat daftarnya">${ikonKpi(ik)}
      <small>${label}</small><b>${ada ? DR.fmt(V[k]) : '–'}</b><em>${beda(k, naikBaik) || '&nbsp;'}</em></div>`;
    const catatanSync = ada ? '' : '<p class="dr-mute" style="margin:8px 0 0;font-size:12px">Snapshot ini dihitung sebelum kartu status ditambahkan. Jalankan <code>php artisan peta-risiko:sync</code> untuk mengisi angkanya.</p>';
    return `<div class="br-kpis br-kpis-6">
        <div ${klik('t')} style="--ac:var(--navy);--kc:var(--navy)" title="Klik untuk melihat semua baris baseline">${ikonKpi('t')}<small>Baseline · ${judul}</small><b>${DR.fmt(V.t)}</b><em>${R ? 'kelompok aktivitas × bahaya-risiko unik' : 'baris tingkat PJA'}</em></div>
        ${kartu('#1a7f37', 'full', 'idk', 'Teridentifikasi &amp; Terkontrol', 'Terdeteksi SAP di minimal satu detail lokasi dan tidak bermasalah di detail lokasi mana pun', true)}
        ${kartu('#a07a00', 'part', 'idg', 'Teridentifikasi - Ada Gap', 'Bermasalah di minimal satu detail lokasi: belum closed, berulang, atau OAK Stop', false)}
        ${kartu('#b42318', 'none', 'tid', 'Tidak Teridentifikasi (Open Risk)', 'Tidak ada laporan SAP di semua detail lokasinya', false)}
        ${kartu('#7e3fb3', 'laten', 'laten', 'Risiko Laten (pernah incident, kini blind spot SAP)', 'Tidak teridentifikasi, tetapi terkait insiden 12 bulan terakhir di salah satu detail lokasinya', false)}
        ${kartu('#1a7f37', 'turun', 'turun', 'Resiko Turun', 'Teridentifikasi & terkontrol, dan level risiko residual HIRA lebih rendah dari risiko awal', true)}
      </div>${catatanSync}`;
  }

  // ------------------------------------------------------------ Modal kartu KPI (daftar bahaya per status)
  const URL_RINCIAN = @json(route('peta-risiko-pja.rincian'));
  /** Definisi tiap kartu: judul, warna, keterangan, penyaring baris (kode minggu K/G/T, inc, tr). */
  const KAT_MODAL = {
    t: ['Baseline', '#3952bc', 'Semua kelompok aktivitas × bahaya-risiko unik di cakupan ini.', () => true],
    idk: ['Teridentifikasi & Terkontrol', '#1a7f37', 'Terdeteksi SAP di minimal satu detail lokasi dan tidak bermasalah di detail lokasi mana pun.', (b, c) => c === 'K'],
    idg: ['Teridentifikasi - Ada Gap', '#a07a00', 'Bermasalah di minimal satu detail lokasi: laporan belum closed, berulang (bahaya-risikonya pernah jadi root cause insiden), atau OAK Stop.', (b, c) => c === 'G'],
    tid: ['Tidak Teridentifikasi (Open Risk)', '#b42318', 'Tidak ada laporan SAP di semua detail lokasinya.', (b, c) => c === 'T'],
    laten: ['Risiko Laten', '#7e3fb3', 'Tidak teridentifikasi, tetapi pernah terkait insiden 12 bulan terakhir di salah satu detail lokasinya (blind spot SAP).', (b, c) => c === 'T' && b.inc],
    turun: ['Risiko Turun Kategori', '#1a7f37', 'Teridentifikasi & terkontrol, dan level risiko residual HIRA lebih rendah dari risiko awal.', (b, c) => c === 'K' && b.tr],
  };
  const KODE_MODAL = { K: ['#1a7f37', 'Terkontrol'], G: ['#d9a400', 'Ada gap'], T: ['#d4d7db', 'Tidak teridentifikasi'] };
  let rincianJanji = null;
  const muatRincian = () => (rincianJanji ||= fetch(URL_RINCIAN, { headers: { Accept: 'application/json' } })
    .then((r) => { if (!r.ok) throw new Error(r.status === 404 ? 'Rincian belum tersedia. Jalankan php artisan peta-risiko:sync.' : `Gagal memuat rincian (HTTP ${r.status}).`); return r.json(); })
    .catch((e) => { rincianJanji = null; throw e; }));

  function dialogModal() {
    let d = document.getElementById('dr-modal');
    if (d) return d;
    d = document.createElement('dialog');
    d.id = 'dr-modal';
    d.className = 'dr-modal';
    d.setAttribute('aria-labelledby', 'dr-modal-judul');
    d.innerHTML = `<div class="dr-modal-kepala"><div><h3 id="dr-modal-judul"></h3><p id="dr-modal-ket"></p></div>
        <button type="button" class="dr-modal-tutup" aria-label="Tutup"><span class="material-symbols-outlined">close</span></button></div>
      <div class="dr-modal-alat"><span class="dr-modal-cari"><span class="material-symbols-outlined">search</span><input type="search" id="dr-modal-q" placeholder="Cari kelompok, aktivitas, atau bahaya…" aria-label="Cari"></span>
        <span id="dr-modal-n" class="dr-mute"></span></div>
      <div class="dr-modal-isi" id="dr-modal-isi"></div>`;
    document.body.appendChild(d);
    d.querySelector('.dr-modal-tutup').addEventListener('click', () => d.close());
    // Klik di luar kotak (backdrop) menutup modal.
    d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
    let t = 0;
    d.querySelector('#dr-modal-q').addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => isiModal(), 200); });
    return d;
  }

  let modalBaris = [], modalKat = 't';
  /** "wi:obs:oak:ket:iobs:ioak:iket,..." → { wi: [6 angka] } */
  const uraiVol = (v) => Object.fromEntries((v || '').split(',').filter(Boolean).map((p) => { const x = p.split(':').map(Number); return [x[0], x.slice(1)]; }));
  /** Baris rincian untuk cakupan & filter aktif (tab, site, grup, kelompok, level, minggu) — sama dengan angka kartu. */
  function barisModal(R, kat) {
    const L = R.lingkup[state.site];
    if (!L) return null;
    const lvBoleh = state.lv === 'ALL' ? null : new Set(['Significant', 'High']);
    const daftar = state.tab === 'grup'
      ? L.g.filter((x) => x[0] === state.grup).map(([, kel, hid, lv, inc, tr, kode, gap, vol]) => ({ kel, hid, lv, inc, tr, kode, gap, vol }))
      : L.u.map(([kel, hid, lv, inc, tr, kode, gap, vol]) => ({ kel, hid, lv, inc, tr, kode, gap, vol }));
    const f = KAT_MODAL[kat][3];
    return daftar.filter((b) => (state.tab !== 'proses' || b.kel === state.proses) && (!lvBoleh || lvBoleh.has(b.lv)) && f(b, b.kode[state.w] || 'T'));
  }

  /** Tab Beranda PJA: baris rincian satu PJA (format sama dengan lingkup u). Null bila snapshot belum menyimpannya. */
  function barisPja(rows, kat) {
    if (!rows) return null;
    const lvBoleh = state.lv === 'ALL' ? null : new Set(['Significant', 'High']);
    const f = KAT_MODAL[kat][3];
    return rows.map(([kel, hid, lv, inc, tr, kode, gap, vol]) => ({ kel, hid: String(hid), lv, inc, tr, kode, gap, vol }))
      .filter((b) => (!lvBoleh || lvBoleh.has(b.lv)) && f(b, b.kode[state.w] || 'T'));
  }

  async function bukaModal(kat) {
    const d = dialogModal(), [judul, warna, ket] = KAT_MODAL[kat];
    const lokasi = state.site === 'ALL' ? 'BC' : state.site;
    const cakupan = state.tab === 'bpja' ? namaPjaB() : state.tab === 'grup' ? `${state.grup} · ${lokasi}` : state.tab === 'proses' ? `${(M.proses.find((p) => p.kode === state.proses) || {}).nama || state.proses} · ${lokasi}` : lokasi;
    d.style.setProperty('--mc', warna);
    d.querySelector('#dr-modal-judul').textContent = judul;
    d.querySelector('#dr-modal-ket').textContent = `${cakupan} · ${wKode(state.w)} · ${state.lv === 'ALL' ? 'semua level' : 'Significant + High'}. ${ket}`;
    d.querySelector('#dr-modal-q').value = '';
    d.querySelector('#dr-modal-n').textContent = '';
    modalKat = kat;
    // Baseline = tabel cara tertangkap (lebar); kartu status = aktivitas · bahaya · SAP.
    d.classList.toggle('lebar', kat === 't');
    d.querySelector('#dr-modal-isi').innerHTML = '<div class="pr-empty">Memuat daftar…</div>';
    if (!d.open) d.showModal();
    try {
      const R = await muatRincian();
      d._rincian = R;
      const b = state.tab === 'bpja' ? barisPja(await muatRincianPja(state.pjaB), kat) : barisModal(R, kat);
      if (b === null) { d.querySelector('#dr-modal-isi').innerHTML = '<div class="pr-empty">Rincian untuk cakupan ini belum ada di snapshot. Jalankan ulang <code>php artisan peta-risiko:sync</code>.</div>'; return; }
      modalBaris = b;
      isiModal();
    } catch (e) {
      d.querySelector('#dr-modal-isi').innerHTML = `<div class="pr-empty">${DR.esc(e.message)}</div>`;
    }
  }

  const URL_SAP_BAHAYA = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.bahaya.sap', ['hid' => '__HID__'])) }};
  // Cara laporan mengidentifikasi baris (aturan per jenis laporan): bahaya langsung, atau kelompok aktivitasnya.
  const CARA_SAP = {
    label: ['#1a7f37', 'Hazard/Inspeksi · label → bahaya'],
    obs: ['#7e3fb3', 'OBS · jenis kegiatan → baris (M11)'],
    oak: ['#1d4ed8', 'OAK · aktivitas / sub → baris (M12)'],
  };
  const STATUS_SAP = { K: 'Teridentifikasi - Terkontrol', G: 'Teridentifikasi - Ada Gap', T: 'Tidak Teridentifikasi' };
  const GAP_BIT = { closed: 1, ulang: 2, stop: 4 };

  /**
   * Tabel modal (gaya DRM 2.1 lama): Aktivitas · Sub aktivitas · Bahaya-risiko · Risiko baseline · Status SAP · Risiko akhir ·
   * Gap1–4 · Item sumber (Obs / OAK / Ketidaksesuaian) · Volume, semuanya untuk minggu terpilih. Risiko akhir = residual HIRA
   * bila terkontrol, selain itu tetap risiko baseline. Item = teks aktivitas / label berbeda per detail lokasi (dijumlah antar DL);
   * link item membuka laporan SAP baris itu (dimuat langsung dari ClickHouse).
   */
  /** Baris modal yang sudah diberi teks HIRA, disaring kotak cari, dan diurutkan per kelompok → aktivitas → level. */
  function barisTersaring(d, R) {
    const q = d.querySelector('#dr-modal-q').value.trim().toLowerCase();
    const namaKel = Object.fromEntries([['X', 'Belum terpetakan'], ...M.proses.map((p) => [p.kode, p.nama])]);
    const urutLv = { Significant: 0, High: 1, Medium: 2, Low: 3 };
    const baris = modalBaris.map((b) => ({ ...b, t: R.teks[b.hid] || ['', '', '', '', ''], kn: namaKel[b.kel] || b.kel }))
      .filter((b) => !q || `${b.kn} ${b.t.slice(0, 4).join(' ')}`.toLowerCase().includes(q))
      .sort((a, b) => a.kn.localeCompare(b.kn) || a.t[0].localeCompare(b.t[0]) || a.t[1].localeCompare(b.t[1]) || (urutLv[a.lv] ?? 9) - (urutLv[b.lv] ?? 9) || a.t[2].localeCompare(b.t[2]));
    d.querySelector('#dr-modal-n').textContent = `${DR.fmt(baris.length)} baris${q ? ` (dari ${DR.fmt(modalBaris.length)})` : ''}`;
    if (!baris.length) d.querySelector('#dr-modal-isi').innerHTML = `<div class="pr-empty">${q ? 'Tidak ada yang cocok dengan pencarian.' : 'Tidak ada baris di kategori ini.'}</div>`;
    return baris;
  }

  function isiModal() {
    if (modalKat === 't') isiModalBaseline(); else isiModalStatus();
  }

  /**
   * Modal kartu status (Terkontrol / Ada Gap / Open Risk / Laten / Turun): Aktivitas HIRA · Bahaya-risiko · SAP minggu terpilih —
   * ada atau tidak (jumlah laporan Obs / OAK / Ketidaksesuaian dari snapshot), alasan gap, dan tombol untuk membuka laporan SAP
   * yang menjadi buktinya (dimuat langsung dari ClickHouse).
   */
  function isiModalStatus() {
    const d = dialogModal(), R = d._rincian;
    if (!R) return;
    const baris = barisTersaring(d, R);
    if (!baris.length) return;
    const adaVol = baris.some((b) => b.gap !== undefined);
    // t.length > 11 = snapshot baru (k1/c1/k2/c2/kt ditambahkan setelah index 0-10 lama). Snapshot lama: tampilkan strip.
    const adaDetail = baris.some((b) => b.t.length > 11);
    const stripDetail = '<span class="dr-mute" title="Jalankan ulang php artisan peta-risiko:sync untuk mengisi ini">–</span>';
    const NK = 7;
    const jumlahKel = baris.reduce((a, b) => { a[b.kel] = (a[b.kel] || 0) + 1; return a; }, {});
    let kel = '', html = '';
    baris.forEach((b, i) => {
      if (b.kel !== kel) {
        kel = b.kel;
        html += `<tr class="dr-modal-grp"><td colspan="${NK}">${DR.esc(b.kn)} <span>${DR.fmt(jumlahKel[kel])} bahaya</span></td></tr>`;
      }
      const kode = b.kode[state.w] || 'T';
      const m = parseInt((b.gap || '')[state.w] || '0', 16);
      const [nObs, nOak, nKet] = uraiVol(b.vol)[state.w] || [0, 0, 0];
      const total = nObs + nOak + nKet;
      const alasan = [m & GAP_BIT.closed ? 'Belum closed' : '', m & GAP_BIT.ulang ? 'Berulang' : '', m & GAP_BIT.stop ? 'OAK Stop' : '']
        .filter(Boolean).map((x) => `<span class="dr-tanda merah">${x}</span>`).join('');
      const tanda = [+b.inc > 0 ? `<span class="dr-tanda ungu">insiden 12 bln (${DR.fmt(+b.inc)})</span>` : '', b.tr ? `<span class="dr-tanda hijau">residual ${DR.esc(b.t[4] || '')} lebih rendah</span>` : ''].join('');
      const rinci = [nObs ? `${DR.fmt(nObs)} Obs` : '', nOak ? `${DR.fmt(nOak)} OAK` : '', nKet ? `${DR.fmt(nKet)} Ketidaksesuaian` : ''].filter(Boolean).join(' · ');
      const sap = kode === 'T' ? '<span class="dr-sap-tidak">Tidak ada SAP</span>'
        : adaVol ? `<span class="dr-sap-ada">Ada · ${DR.fmt(total)} laporan</span><small>${rinci}</small>` : '<span class="dr-sap-ada">Ada</span>';
      const residual = !adaDetail ? stripDetail
        : b.t[4] ? `<span class="dr-lv lv-${DR.esc(b.t[4])}">${DR.esc(b.t[4])}</span><small>${DR.esc(b.t[13] || '-')} × ${DR.esc(b.t[14] || '-')}</small>`
          : '<span class="dr-mute">–</span>';
      const kontrol = !adaDetail ? stripDetail
        : (String(b.t[15] || '').split(' | ').filter(Boolean).slice(0, 4).map((x) => '• ' + DR.esc(x)).join('<br>') || '<span class="dr-mute">tidak tercatat di HIRA</span>');
      html += `<tr class="dr-modal-baris" data-i="${i}">
        <td>${DR.esc(b.t[0] || '-')}${b.t[1] && b.t[1] !== b.t[0] ? `<small>${DR.esc(b.t[1])}</small>` : ''}</td>
        <td><b>${DR.esc(b.t[2] || '(tanpa teks bahaya)')}</b>${b.t[3] ? `<small>${DR.esc(b.t[3])}</small>` : ''}${tanda ? `<div>${tanda}</div>` : ''}</td>
        <td><span class="dr-lv lv-${DR.esc(b.lv)}">${DR.esc(b.lv || '-')}</span>${adaDetail ? `<small>${DR.esc(b.t[11] || '-')} × ${DR.esc(b.t[12] || '-')}</small>` : ''}</td>
        <td>${residual}</td>
        <td><span class="dr-st ${kode}">${STATUS_SAP[kode]}</span>${alasan ? `<div>${alasan}</div>` : ''}</td>
        <td>${kontrol}</td>
        <td>${sap}${kode === 'T' ? '' : `<div><button type="button" class="dr-sap-btn" data-i="${i}" aria-expanded="false"><span class="material-symbols-outlined">description</span> Lihat SAP</button></div>`}</td></tr>
        <tr class="dr-modal-sap" data-i="${i}" hidden><td colspan="${NK}"></td></tr>`;
    });
    const isi = d.querySelector('#dr-modal-isi');
    isi.innerHTML = `<table class="pr-tbl dr-tbl dr-modal-tbl dr-st-tbl"><thead><tr><th>Aktivitas HIRA</th><th>Bahaya - Risiko</th><th>Risiko Awal</th><th>Risiko Residual</th>
      <th>Status SAP ${wKode(state.w)}</th><th>Pengendalian yang ada</th><th>SAP ${wKode(state.w)}</th></tr></thead><tbody>${html}</tbody></table>`;
    isi.querySelectorAll('.dr-sap-btn').forEach((btn) => btn.addEventListener('click', () => bukaSapBahaya(baris[+btn.dataset.i], btn)));
  }

  function isiModalBaseline() {
    const d = dialogModal(), R = d._rincian;
    if (!R) return;
    const baris = barisTersaring(d, R);
    if (!baris.length) return;
    const adaVol = baris.some((b) => b.gap !== undefined);
    const NK = 18;
    const strip = '<span class="dr-nol" title="Belum ada di snapshot">–</span>';
    const gapSel = (ya, na, perluData = true) => (perluData && !adaVol ? strip : ya ? '<span class="dr-gap ya">YA - GAP</span>' : na ? '<span class="dr-gap na">N/A</span>' : '<span class="dr-gap">Tidak</span>');
    // Item Sumber = MAPPING master HIRA ↔ SAP (jumlah label yang terhubung ke baris), tetap walau tidak ada laporan.
    const adaMap = baris.some((b) => b.t.length > 5);
    const item = (n, i, sumber) => (!adaMap ? strip : n > 0 ? `<button type="button" class="dr-item" data-i="${i}" data-sumber="${sumber}">${DR.fmt(n)} label</button>` : '<span class="dr-nol" title="Belum ada label di master mapping">0</span>');
    const angka = (n) => (!adaVol ? strip : n > 0 ? DR.fmt(n) : '<span class="dr-nol">0</span>');
    const jumlahKel = baris.reduce((a, b) => { a[b.kel] = (a[b.kel] || 0) + 1; return a; }, {});
    let kel = '', html = '';
    baris.forEach((b, i) => {
      if (b.kel !== kel) {
        kel = b.kel;
        html += `<tr class="dr-modal-grp"><td colspan="${NK}"><span class="dr-grp-tempel">${DR.esc(b.kn)} <span>${DR.fmt(jumlahKel[kel])} bahaya</span></span></td></tr>`;
      }
      const kode = b.kode[state.w] || 'T';
      const m = parseInt((b.gap || '')[state.w] || '0', 16);
      const [nObs, nOak, nKet] = uraiVol(b.vol)[state.w] || [0, 0, 0];
      const [mObs, mOak, mKet] = [+(b.t[5] || 0), +(b.t[6] || 0), +(b.t[7] || 0)];
      const akhir = kode === 'K' && b.t[4] ? b.t[4] : b.lv;
      const tanda = b.tr ? '<span class="dr-tanda hijau">residual lebih rendah</span>' : '';
      // inc = jumlah insiden 12 bulan terkait (snapshot lama: 1 = ya tanpa jumlah).
      const insiden = +b.inc > 0 ? `<span class="dr-gap ya-ungu" title="Insiden 12 bulan terakhir yang root cause-nya terpetakan ke bahaya ini">Ya (${DR.fmt(+b.inc)})</span>` : '<span class="dr-gap">Tidak</span>';
      const T = kode === 'T';
      html += `<tr class="dr-modal-baris" data-i="${i}">
        <td class="akt k1">${DR.esc(b.t[0] || '-')}</td>
        <td class="akt k2">${DR.esc(b.t[1] || '-')}</td>
        <td class="bhy k3"${b.t[3] ? ` title="${DR.esc(b.t[3])}"` : ''}>${DR.esc(b.t[2] || '(tanpa teks bahaya)')}${tanda ? `<div>${tanda}</div>` : ''}</td>
        <td class="c"><span class="dr-lv lv-${DR.esc(b.lv)}">${DR.esc(b.lv || '-')}</span></td>
        <td><span class="dr-st ${kode}">${STATUS_SAP[kode]}</span>${T ? '' : `<div><button type="button" class="dr-sap-btn" data-i="${i}" aria-expanded="false"><span class="material-symbols-outlined">description</span> Lihat SAP</button></div>`}</td>
        <td class="c"><span class="dr-lv lv-${DR.esc(akhir)}">${DR.esc(akhir || '-')}</span></td>
        <td class="c">${gapSel(T, false, false)}</td>
        <td class="c">${gapSel(m & GAP_BIT.closed, T)}</td>
        <td class="c">${gapSel(m & GAP_BIT.ulang, T)}</td>
        <td class="c">${gapSel(m & GAP_BIT.stop, T || nOak === 0)}</td>
        <td class="c">${item(mObs, i, 'obs')}</td>
        <td class="c">${item(mOak, i, 'oak')}</td>
        <td class="c">${item(mKet, i, 'ket')}</td>
        <td class="n">${angka(nObs)}</td>
        <td class="n">${angka(nOak)}</td>
        <td class="n">${angka(nKet)}</td>
        <td class="n"><b>${angka(nObs + nOak + nKet)}</b></td>
        <td class="c">${insiden}</td></tr>
        <tr class="dr-modal-sap" data-i="${i}" hidden><td colspan="${NK}"><div class="dr-bl-sap"></div></td></tr>`;
    });
    const isi = d.querySelector('#dr-modal-isi');
    const wk = wKode(state.w);
    d.querySelector('#dr-modal-n').innerHTML += adaVol ? '' : ' · <b style="color:#b42318">Snapshot belum memuat gap &amp; volume per baris — jalankan <code>php artisan peta-risiko:sync</code></b>';
    isi.innerHTML = `<table class="pr-tbl dr-tbl dr-modal-tbl dr-bl-tbl"><thead><tr>
      <th class="k1">Aktivitas</th><th class="k2">Sub Aktivitas</th><th class="k3">Bahaya - Risiko Terkait</th><th class="c">Risiko Baseline</th><th>Status SAP ${wk}</th><th class="c">Risiko Akhir</th>
      <th class="c">Gap1 Tdk Teridentifikasi</th><th class="c">Gap2 Belum Closed</th><th class="c">Gap3 Berulang</th><th class="c">Gap4 OAK Stop</th>
      <th class="c">Item Sumber Observasi <small>(label master)</small></th><th class="c">Item Sumber OAK <small>(label master)</small></th><th class="c">Item Sumber Ketidaksesuaian <small>(label master)</small></th>
      <th class="n">Vol. Obs</th><th class="n">Vol. OAK</th><th class="n">Vol. Ket</th><th class="n">Vol. Total</th><th class="c">Pernah Incident? <small>(12 bln)</small></th></tr></thead><tbody>${html}</tbody></table>`;
    isi.querySelectorAll('.dr-item').forEach((btn) => btn.addEventListener('click', () => bukaItem(baris[+btn.dataset.i], btn.dataset.sumber)));
    isi.querySelectorAll('.dr-sap-btn').forEach((btn) => btn.addEventListener('click', () => bukaSapBahaya(baris[+btn.dataset.i], btn)));
  }

  /**
   * Modal item sumber: BAGAIMANA SAP tertangkap ke baris HIRA (kunci penangkapnya), bukan isi laporan.
   * Semua lewat label persis di master pemetaan (dokumentasi Risk Matrix 5x5): OBS = jenis kegiatan (M11),
   * OAK = aktivitas / sub aktivitas (M12), Ketidaksesuaian = label ketidaksesuaian / sub Hazard & Inspeksi (M09).
   */
  const SUMBER_ITEM = {
    obs: ['Observasi', 'Jenis kegiatan OBS yang di master pemetaan (M11) dipetakan ke baris HIRA ini'],
    oak: ['OAK', 'Aktivitas / sub aktivitas OAK yang di master pemetaan (M12) dipetakan ke baris HIRA ini ("Risk Intervention Center" disamakan dengan "Langsung"). Gap = Stop Pekerjaan'],
    ket: ['Ketidaksesuaian/Sub Ketidaksesuaian', 'Label ketidaksesuaian / sub ketidaksesuaian Hazard & Inspeksi yang di master pemetaan (M09) dipetakan ke baris HIRA ini'],
  };
  function dialogItem() {
    let d = document.getElementById('dr-item-modal');
    if (d) return d;
    d = document.createElement('dialog');
    d.id = 'dr-item-modal';
    d.className = 'dr-item-modal';
    d.setAttribute('aria-labelledby', 'dr-item-judul');
    d.innerHTML = `<div class="dr-item-kepala"><h3 id="dr-item-judul"></h3>
        <button type="button" class="dr-item-tutup" aria-label="Tutup"><span class="material-symbols-outlined">close</span></button></div>
      <div class="dr-item-isi" id="dr-item-isi"></div>`;
    document.body.appendChild(d);
    d.querySelector('.dr-item-tutup').addEventListener('click', () => d.close());
    d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
    return d;
  }
  async function bukaItem(b, sumber) {
    const [nama, aturan] = SUMBER_ITEM[sumber];
    const d = dialogItem(), isi = d.querySelector('#dr-item-isi');
    d.querySelector('#dr-item-judul').textContent = `Item Sumber - ${nama} ( ${b.t[0] || '-'} / ${b.t[2] || '-'} )`;
    isi.innerHTML = '<div class="dr-mute">Memuat item dari ClickHouse…</div>';
    if (!d.open) d.showModal();
    const qs = new URLSearchParams({ kel: b.kel, w: String(state.w), sumber, ...cakupanSap() });
    try {
      const r = await fetch(`${URL_SAP_BAHAYA.replace('__HID__', encodeURIComponent(b.hid))}?${qs}`, { headers: { Accept: 'application/json' } });
      const data = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(data.pesan || data.message || `HTTP ${r.status}`);
      const periode = `${data.dari.split('-').reverse().join('/')} – ${data.sampai.split('-').reverse().join('/')}`;
      const dilapor = (data.item || []).filter((x) => x.n > 0).length;
      const nLabel = `<p class="dr-item-cara" style="margin-top:4px"><b>${DR.fmt(data.nlabel || 0)} label</b> di master mapping terhubung ke baris ini (baseline tetap, tidak bergantung laporan)${data.nlabel ? '' : ' — belum ada label untuk sumber ini'}.</p>`;
      const daftar = (data.item || []).map((x) => {
        const sub = x.n > 0 ? [`${DR.fmt(x.n)} laporan · ${DR.fmt(x.ndl)} detail lokasi`, x.nts ? `<b style="color:#b91c1c">${DR.fmt(x.nts)} Tidak Sesuai</b>` : '', x.nstop ? `<b style="color:#b91c1c">${DR.fmt(x.nstop)} Stop Pekerjaan</b>` : ''].filter(Boolean).join(' · ') : '<span class="dr-nol">tidak ada laporan minggu ini</span>';
        const butir = (x.butir || []).length ? `<div class="dr-item-contoh">Butir checklist yang menangkap:<br>${x.butir.map((c) => `• ${DR.esc(c)}`).join('<br>')}</div>` : '';
        return `<li><div><span>${DR.esc(x.isi || '-')}</span>${butir}</div><small>${sub}</small></li>`;
      }).join('');
      isi.innerHTML = `<p class="dr-item-cara"><b>Cara tertangkap:</b> ${aturan}.</p>${nLabel}
        <p class="dr-mute" style="margin:0 0 10px;font-size:12px">${wKode(state.w)} · ${periode} · ${DR.fmt(dilapor)} dari ${DR.fmt(data.nlabel || 0)} label dilaporkan di ${DR.fmt(data.ndl)} detail lokasi cakupan</p>
        ${daftar ? `<ul class="dr-item-daftar">${daftar}</ul>` : '<div class="dr-mute">Tidak ada item pada minggu ini.</div>'}`;
    } catch (e) {
      isi.innerHTML = `<div class="dr-mute">Item gagal dimuat: ${DR.esc(e.message)}</div>`;
    }
  }

  /** Buka / tutup daftar SAP satu baris di modal. */
  async function bukaSapBahaya(b, btn) {
    const tr = btn.closest('tbody').querySelector(`tr.dr-modal-sap[data-i="${btn.dataset.i}"]`);
    // Tabel Baseline lebar: isi masuk panel sticky selebar area gulir agar tidak ikut tergeser ke kanan.
    const td = tr.querySelector('.dr-bl-sap') || tr.firstElementChild;
    const buka = tr.hidden;
    tr.hidden = !buka;
    const gulir = td.classList.contains('dr-bl-sap') ? tr.closest('.dr-modal-isi') : null;
    if (buka && gulir) td.style.width = `${Math.max(320, gulir.clientWidth - 48)}px`;
    btn.setAttribute('aria-expanded', String(buka));
    btn.classList.toggle('on', buka);
    if (!buka || td.dataset.dimuat) return;
    td.innerHTML = '<span class="dr-mute">Memuat laporan SAP dari ClickHouse…</span>';
    const qs = new URLSearchParams({ kel: b.kel, w: String(state.w), ...cakupanSap() });
    try {
      const r = await fetch(`${URL_SAP_BAHAYA.replace('__HID__', encodeURIComponent(b.hid))}?${qs}`, { headers: { Accept: 'application/json' } });
      const data = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(data.pesan || data.message || `HTTP ${r.status}`);
      td.innerHTML = isiSapBahaya(data);
      td.dataset.dimuat = '1';
    } catch (e) {
      td.innerHTML = `<span class="dr-mute">Laporan SAP gagal dimuat: ${DR.esc(e.message)}</span>`;
    }
  }

  function isiSapBahaya(data) {
    const R = data.ringkas || {}, L = data.laporan || [];
    const periode = `${data.dari.split('-').reverse().join('/')} – ${data.sampai.split('-').reverse().join('/')}`;
    if (!L.length) return `<span class="dr-mute">Tidak ada laporan SAP yang memetakan ke bahaya ini di ${DR.fmt(data.ndl)} detail lokasi cakupan pada ${periode}.</span>`;
    const ringkas = `<div class="dr-sap-ringkas"><b title="Dihitung per task (nomor tasklist) unik; baris kembar di sumber SAP digabung">${DR.fmt(R.total)} laporan (task unik) · ${periode} · ${DR.fmt(data.ndl)} detail lokasi</b>
      ${Object.entries(CARA_SAP).map(([k, [c, t]]) => R[k] ? `<span style="color:${c}">● ${DR.fmt(R[k])} ${t}</span>` : '').join('')}
      <span>belum closed ${DR.fmt(R.belum_closed)} · berulang ${DR.fmt(R.berulang)} · OAK Stop ${DR.fmt(R.oak_stop)}</span></div>`;
    const baris = L.map((x) => {
      const [c, t] = CARA_SAP[x.cara] || ['#8a8d90', x.cara];
      const bendera = [x.belum_closed ? '<span class="dr-tanda merah">Belum closed</span>' : '', x.berulang ? '<span class="dr-tanda merah">Berulang</span>' : '',
        x.oak_stop ? '<span class="dr-tanda merah">OAK Stop</span>' : '', x.tidak_sesuai ? '<span class="dr-tanda merah">Tidak Sesuai</span>' : ''].join('');
      return `<tr><td style="white-space:nowrap">${DR.esc(x.waktu)}<small>${DR.esc(x.jenis)}${x.task ? ` · task ${DR.esc(x.task)}` : ''}</small></td>
        <td>${DR.esc(x.lokasi)}</td>
        <td><b>${DR.esc(x.isi || '-')}</b>${x.deskripsi ? `<small>${DR.esc(x.deskripsi)}</small>` : ''}</td>
        <td>${DR.esc(x.status || x.hasil_oak || '-')}${bendera ? `<br>${bendera}` : ''}</td>
        <td><span style="color:${c};font-weight:600">● ${t}</span></td>
        <td class="dr-mute">${DR.esc(x.pelapor)}</td></tr>`;
    }).join('');
    return ringkas + `<div class="dr-scroll"><table class="pr-tbl dr-tbl dr-sap-tbl"><thead><tr><th>Waktu</th><th>Detail lokasi</th><th>Isi laporan</th><th>Status</th><th>Cara terpetakan</th><th>Pelapor</th></tr></thead><tbody>${baris}</tbody></table></div>`
      + (data.terpotong ? `<div class="dr-mute" style="font-size:12px;margin-top:6px">Ditampilkan ${DR.fmt(L.length)} laporan terbaru dari ${DR.fmt(R.total)} task.</div>` : '');
  }

  // Klik / Enter / Spasi pada kartu KPI membuka modal (delegasi: kartu dirender ulang tiap filter berubah).
  document.addEventListener('click', (e) => { const k = e.target.closest('.br-kpi-klik'); if (k) bukaModal(k.dataset.kat); });
  document.addEventListener('keydown', (e) => {
    const k = e.target.closest && e.target.closest('.br-kpi-klik');
    if (k && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); bukaModal(k.dataset.kat); }
  });

  function renderCakupan() {
    if (!formatBaru() || !(adaUnik() || M.beranda.mgmt.every((p) => p.kel && p.grp))) { $('#dr-kpi4').innerHTML = pesanLama(); return; }
    const P = cakupanP(), w = state.w, NW = M.beranda.minggu.length, wk = wKode(w);
    // Kombinasi site × perusahaan tanpa detail lokasi sama sekali (lihat DrmSnapshotBuilder::ringkasUnik) — jangan
    // tampilkan kartu/tabel kosong seolah snapshot lama; pesan khusus, sama pola dengan tab Semua PJA / Beranda PJA.
    if (adaUnik() && state.perusahaan !== 'ALL' && !P.length) {
      $('#dr-kpi4').innerHTML = `<div class="pr-card"><div class="pr-empty">Tidak ada detail lokasi dengan personil dari perusahaan ${DR.esc(state.perusahaan)}${state.site === 'ALL' ? '' : ' di ' + DR.esc(state.site)}.</div></div>`;
      $('#dr-card-mx').hidden = true; $('#dr-card-tm').hidden = true; $('#dr-card-grup').hidden = true;
      $('#dr-tren-judul').textContent = ''; $('#dr-tren-sum').innerHTML = ''; $('#dr-tren').innerHTML = ''; $('#dr-legenda').innerHTML = '';
      $('#dr-grup-judul').textContent = 'Per Group Lokasi'; $('#dr-grup-sub').textContent = '';
      $('#dr-kel-judul').textContent = 'Per Kelompok Aktivitas'; $('#dr-kel-sub').textContent = '';
      $('#dr-tbl-grup').innerHTML = ''; $('#dr-tbl-proses').innerHTML = '';
      return;
    }
    const fallbackSite = jatuhBalikSite();
    const lokasi = state.site === 'ALL' || fallbackSite ? 'BC' : DR.esc(state.site);
    const namaKel = (M.proses.find((p) => p.kode === state.proses) || {}).nama || state.proses;
    const judul = state.tab === 'grup' ? `${DR.esc(state.grup)} · ${lokasi}` : state.tab === 'proses' ? `${DR.esc(namaKel)} · ${lokasi}` : lokasi;
    const V = jumlahF(P, (p) => vekTab(p, w)), V0 = w + 1 < NW ? jumlahF(P, (p) => vekTab(p, w + 1)) : null;
    const R = adaUnik() ? ringkasAktif() : null;
    const catatanFallback = fallbackSite
      ? `<p class="dr-mute" style="margin:8px 0 0;font-size:12px">Kartu &amp; tabel di atas gabungan <b>seluruh site</b> untuk perusahaan ${DR.esc(state.perusahaan)} — pecahan per site + perusahaan tidak disimpan saat sync (hemat memori). Tabel detail lokasi di bawah tetap tersaring ke ${DR.esc(state.site)} saja.</p>`
      : '';
    const catatan = catatanFallback || (R
      ? (pitaAktif() ? '' : '<p class="dr-mute" style="margin:8px 0 0;font-size:12px">Snapshot belum memakai pita cakupan, sehingga hampir semua bahaya terbaca "sebagian". Jalankan ulang <code>php artisan peta-risiko:sync</code>.</p>')
      : '<p class="dr-mute" style="margin:8px 0 0;font-size:12px">Snapshot lama: angka masih jumlah baris tingkat PJA. Jalankan ulang <code>php artisan peta-risiko:sync</code> untuk baseline unik.</p>');
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
    // 3 kategori kartu KPI; snapshot lama tanpa kolom status tetap memakai pita cakupan.
    if (M.beranda.kolomMgmt.includes('idk')) {
      $('#dr-tren').innerHTML = trenStatus(P, vekTab);
      $('#dr-legenda').innerHTML = `<div class="dr-legend">${STATUS3.map(([, c, t]) => `<span><i style="--c:${c}"></i>${t}</span>`).join('')}</div>`;
    } else {
      $('#dr-tren').innerHTML = trenPeriode(P, vekTab, LBL());
      const Lc = LBL();
      $('#dr-legenda').innerHTML = `<div class="dr-legend">${[[DR.BW.ok, Lc.kFull], [DR.BW.part, Lc.kPart], [DR.BW.bm, Lc.kGapo], [DR.BW.none, Lc.kNone]].map(([c, t]) => `<span><i style="--c:${c}"></i>${t}</span>`).join('')}</div>`;
    }
    document.querySelectorAll('#dr-tren .br-wc').forEach((c) => c.addEventListener('click', () => { state.w = +c.dataset.w; $('#dr-w').value = state.w; render(); }));

    const diGrup = state.tab === 'grup';
    const namaKelTm = state.tab === 'site' && state.kelMx ? ((M.proses.find((p) => p.kode === state.kelMx) || {}).nama || state.kelMx) : '';
    $('#dr-grup-judul').textContent = diGrup ? `Detail lokasi · ${state.grup}` : namaKelTm ? `Per Group Lokasi · ${namaKelTm}` : 'Per Group Lokasi';
    $('#dr-grup-sub').textContent = diGrup
      ? 'Detail lokasi di grup ini beserta PJA-nya. Satu baris = kelompok aktivitas × bahaya di detail lokasi itu (terkontrol / hanya bermasalah / tanpa laporan). Klik nama PJA untuk membuka Beranda PJA.'
      : (namaKelTm ? `Hanya baris kelompok ${namaKelTm} (dipilih di treemap). ` : '') + 'Satu baris = grup × kelompok aktivitas × bahaya unik; status dari detail lokasi grup itu saja. Satu bahaya bisa berlaku di beberapa grup, jadi jumlah antar grup bisa melebihi baseline. Klik baris untuk melihat grup itu.';
    // Tab Per Kelompok Aktivitas: tabel bawah hanya kelompok yang dipilih di filter atas.
    // Tab Overview: kelompok yang diklik di treemap ikut memfilter tabel bawah (sama dengan matriks).
    const kelTm = state.tab === 'site' ? state.kelMx : '';
    const kelPilih = state.tab === 'proses' ? state.proses : kelTm;
    const diKel = !!kelPilih;
    const namaKelAktif = diKel ? ((M.proses.find((p) => p.kode === kelPilih) || {}).nama || kelPilih) : '';
    $('#dr-kel-judul').textContent = diGrup ? `Kelompok aktivitas · ${state.grup}` : diKel ? `Kelompok aktivitas · ${namaKelAktif}` : 'Per Kelompok Aktivitas';
    $('#dr-kel-sub').textContent = diGrup
      ? 'Kelompok aktivitas yang ada di grup lokasi ini (dari master M05); jumlah barisnya sama dengan baseline grup di kartu atas. Klik baris untuk melihat aktivitas HIRA kelompok itu di grup ini.'
      : kelTm
        ? 'Hanya kelompok yang dipilih di treemap; jumlah barisnya sama dengan angka "dari … bahaya" di kotak treemap. Klik aktivitas untuk melihat bahaya-risikonya. Klik kotak treemap yang sama (atau ✕ di matriks) untuk melepas filter.'
      : diKel
        ? 'Hanya kelompok aktivitas yang dipilih di filter atas; jumlah barisnya sama dengan baseline di kartu atas. Klik baris untuk melihat aktivitas HIRA kelompok ini. Ganti kelompok lewat filter Kelompok aktivitas.'
        : '25 kelompok aktivitas master DRM 2.1 (M02); jumlah barisnya sama dengan baseline di kartu atas. Klik baris untuk melihat aktivitas HIRA di kelompok itu.';
    if (diGrup) {
      tabelDetailLokasi(w);
      tabelCakupan('#dr-tbl-proses', 'Kelompok aktivitas', M.proses.map((k) => [k.kode, k.nama]), (p, i, k) => vekDari(ambilKunci((p.gk || [])[i], `${state.grup}|${k}`)), 'proses');
    } else {
      // Kelompok dari treemap: per grup = baris kelompok itu di grup tersebut (gk "grup|kel").
      tabelCakupan('#dr-tbl-grup', 'Group Lokasi', M.grup.map((g) => [g, g]),
        kelTm ? (p, i, g) => vekDari(ambilKunci((p.gk || [])[i], `${g}|${kelTm}`)) : (p, i, g) => vekGrup(p, i, g), 'grup');
      tabelCakupan('#dr-tbl-proses', 'Kelompok aktivitas', M.proses.filter((k) => !diKel || k.kode === kelPilih).map((k) => [k.kode, k.nama]), (p, i, k) => vekKel(p, i, k), 'proses');
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
      if (!info || info[3] !== state.grup || (state.site !== 'ALL' && info[0] !== state.site)
        || (state.perusahaan !== 'ALL' && !(info[4] || []).includes(state.perusahaan))) return;
      if (!peta.has(d)) {
        // Status per detail lokasi dari ringkasan unik "semua site × semua perusahaan" (sama untuk setiap PJA
        // pemiliknya; dlw hanya disimpan di ALL|ALL, lihat DrmSnapshotBuilder::ringkasUnik). Snapshot lama
        // (sebelum 17 Nov 2026) menyimpan dlw per PJA, bukan di ringkasan ALL: pakai yang tersedia.
        const ra = M.beranda.ringkas && M.beranda.ringkas.ALL && M.beranda.ringkas.ALL.ALL;
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
          + (buka ? `<tr class="br-sub br-buka"><td colspan="8" style="padding:12px 16px 16px 40px">
              <div data-pjalap><span class="dr-mute">Memuat PJA yang melaporkan…</span></div>
              <div data-belum="${DR.esc(d)}" data-pja-id="${DR.esc(pja[0].id)}" style="margin-bottom:16px"><span class="dr-mute">Memuat bahaya-risiko yang belum tercover SAP…</span></div>
              <div data-sap="${DR.esc(d)}" data-pemilik="${DR.esc(JSON.stringify(pja.map((p) => [String(p.id), p.nama])))}"><span class="dr-mute">Memuat laporan SAP…</span></div></td></tr>` : '');
      }).join('')
        || `<tr><td colspan="8" class="pr-empty">Tidak ada detail lokasi ${DR.esc(state.grup)}${state.site === 'ALL' ? '' : ' di ' + DR.esc(state.site)} yang punya baris baseline${M.beranda.dl ? '' : ' (snapshot lama — jalankan ulang peta-risiko:sync)'}.</td></tr>`)
      + (kosongan ? `<tr><td colspan="8" class="dr-mute" style="font-size:12px">${kosongan} detail lokasi lain di grup ini tidak punya baris pada level risiko terpilih.</td></tr>` : '')
      + '</tbody>';
    document.querySelectorAll('#dr-tbl-grup tr[data-dl]').forEach((tr) => tr.addEventListener('click', () => {
      state.dlbuka[tr.dataset.dl] = !state.dlbuka[tr.dataset.dl]; tabelDetailLokasi(state.w);
    }));
    document.querySelectorAll('#dr-tbl-grup a[data-pja]').forEach((a) => a.addEventListener('click', (e) => e.stopPropagation()));
    document.querySelectorAll('#dr-tbl-grup [data-sap]').forEach((td) => muatSap(td.dataset.sap, w, td));
    document.querySelectorAll('#dr-tbl-grup [data-belum]').forEach((el) => muatBelum(el.dataset.belum, el.dataset.pjaId, w, el));
  }

  // ------------------------------------------------------------ Bahaya-risiko belum tercover SAP di satu detail lokasi
  /**
   * Baris baseline (detail lokasi × bahaya, tanpa bahaya umum, level sesuai filter) yang tidak tertangkap laporan SAP
   * pada minggu w — sama dengan angka "Tanpa laporan" di tabel. Juga baris yang tertangkap tetapi bermasalah.
   * Data dari berkas PJA pemilik detail lokasi (bit mingguan kelompok `kd` dan spesifik `sp`, aturan DrmBeranda::status).
   */
  function bahayaDl(D, d, w) {
    const B = Object.fromEntries(M.kolomBahaya.map((k, i) => [k, i]));
    const urai = (s) => { const o = {}; if (s) String(s).split(',').forEach((p) => { const [i, b] = p.split(':'); o[+i] = (o[+i] || 0) | +b; }); return o; };
    const l = (D.lokasi || []).find((x) => String(x.dl) === String(d));
    if (!l) return null;
    const BE = D.beranda || {}, umum = new Set(((BE.umum || {})[l.grp] || []).map(String));
    const bh = Object.fromEntries((D.bahaya || []).map((b) => [String(b[B.hid]), b]));
    const lvBoleh = state.lv === 'ALL' ? null : new Set(['Significant', 'High']);
    const hasil = { n: 0, none: [], bm: [] };
    String((D.pasangan || {})[d] || '').split(',').filter(Boolean).forEach((p) => {
      const hid = p.split(':')[0], b = bh[hid];
      if (!b || umum.has(hid) || (lvBoleh && !lvBoleh.has(b[B.n1]))) return;
      const kel = b[B.proses] || 'X', kd = (BE.kd || {})[d + '|' + kel] || [], sp = (BE.sp || {})[d + '|' + hid] || [];
      const k = urai(kd[0])[w] || 0, s = urai(sp[0])[w] || 0;
      const m = (s & (4 | 8 | 16 | 32)) | (k & 16);
      hasil.n++;
      const baris = { hid: String(hid), kel, an: b[B.aktivitas], sn: b[B.sub], bn: b[B.bahaya], n1: b[B.n1], n2: b[B.n2], m };
      if (m) hasil.bm.push(baris); else if (!((k & 1) || (s & 2))) hasil.none.push(baris);
    });
    return hasil;
  }

  function muatBelum(d, pjaId, w, el) {
    muatDataPja(pjaId).then((D) => {
      if (!el.isConnected) return;
      const H = bahayaDl(D, d, w);
      if (!H) { el.innerHTML = '<span class="dr-mute">Detail lokasi ini tidak ada di berkas PJA-nya.</span>'; return; }
      const namaKel = Object.fromEntries([['X', 'Belum terpetakan'], ...M.proses.map((p) => [p.kode, p.nama])]);
      const urutLv = { Significant: 0, High: 1, Medium: 2, Low: 3 };
      const lvTag = (lv) => `<span class="dr-lv lv-${DR.esc(lv)}">${DR.esc(lv || '-')}</span>`;
      const tabel = (L, alasan) => `<div class="dr-scroll"><table class="pr-tbl dr-tbl" style="min-width:1000px"><thead><tr><th>Risiko awal</th><th>Kelompok aktivitas</th><th style="min-width:220px">Aktivitas HIRA · sub aktivitas</th><th style="min-width:150px">HIRA</th><th style="min-width:220px">Bahaya - risiko</th>${alasan ? '<th>Masalah</th>' : '<th>Residual</th>'}</tr></thead><tbody>${
        L.slice().sort((a, b) => (urutLv[a.n1] ?? 9) - (urutLv[b.n1] ?? 9) || String(namaKel[a.kel]).localeCompare(String(namaKel[b.kel])) || String(a.an).localeCompare(String(b.an)) || String(a.bn).localeCompare(String(b.bn)))
          .map((x) => `<tr><td>${lvTag(x.n1)}</td><td>${DR.esc(namaKel[x.kel] || x.kel)}</td><td>${DR.esc(x.an)}${x.sn && x.sn !== x.an ? `<br><span class="dr-mute">${DR.esc(x.sn)}</span>` : ''}</td><td data-hira="${DR.esc(x.hid)}" data-bahaya="${DR.esc(x.bn)}"><span class="dr-mute">…</span></td><td><b>${DR.esc(x.bn)}</b></td>
            <td>${alasan ? [x.m & 4 && 'belum closed', x.m & 8 && 'berulang', x.m & 16 && 'OAK Stop', x.m & 32 && 'butir OAK tidak sesuai'].filter(Boolean).join(', ') : lvTag(x.n2)}</td></tr>`).join('')}</tbody></table></div>`;
      const lv = state.lv === 'ALL' ? 'semua level' : 'Significant + High';
      el.innerHTML = `<div style="display:flex;flex-wrap:wrap;gap:6px 16px;align-items:baseline;margin-bottom:8px;font-size:13px">
          <b style="color:#b42318">● ${DR.fmt(H.none.length)} bahaya-risiko belum tercover SAP</b>
          <span class="dr-mute">dari ${DR.fmt(H.n)} baris baseline (${pctI(H.none.length, H.n)}) · ${wKode(w)} · ${lv}</span></div>`
        + (H.none.length ? tabel(H.none, false) : '<div class="dr-mute">Semua bahaya-risiko baseline di detail lokasi ini sudah tertangkap laporan SAP minggu ini.</div>')
        + (H.bm.length ? `<details style="margin-top:10px"><summary style="cursor:pointer;font-weight:700;font-size:13px;color:#a07a00">● ${DR.fmt(H.bm.length)} sudah tertangkap SAP tetapi laporannya bermasalah</summary>${tabel(H.bm, true)}</details>` : '')
        + '<p class="dr-mute" style="font-size:12px;margin:8px 0 0">Baseline detail lokasi = semua baris HIRA grup lokasinya (M05), jadi sebagian baris bisa saja aktivitasnya memang tidak ada di lokasi ini. Klik kolom HIRA untuk melihat dokumen asalnya.</p>';
      isiHira(el, [...H.none, ...H.bm].map((x) => x.hid));
    }).catch((e) => { if (el.isConnected) el.innerHTML = `<span class="br-red">${DR.esc(e.message || 'Gagal memuat data PJA.')}</span>`; });
  }

  // ------------------------------------------------------------ Asal HIRA (dokumen id_hira_master) per baris baseline
  const URL_HIRA = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.hira.sumber')) }};
  const hiraCache = {};

  /** Ambil asal HIRA untuk hid yang belum ada di cache (maks 300 per permintaan). */
  async function muatHira(hids) {
    const perlu = [...new Set(hids)].filter((h) => !(h in hiraCache));
    for (let i = 0; i < perlu.length; i += 300) {
      const potong = perlu.slice(i, i + 300);
      const r = await fetch(`${URL_HIRA}?ri=${potong.join(',')}`, { headers: { Accept: 'application/json' } });
      const j = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(j.pesan || `Gagal memuat HIRA (HTTP ${r.status}).`);
      potong.forEach((h) => { hiraCache[h] = (j.baris || {})[h] || []; });
    }
  }

  /** Isi sel [data-hira] di dalam el: chip "id · rev" + departemen singkat; klik → detail dokumen. */
  function isiHira(el, hids) {
    muatHira(hids).then(() => {
      el.querySelectorAll('td[data-hira]').forEach((td) => {
        const L = hiraCache[td.dataset.hira] || [], d = L[0];
        if (!d) { td.innerHTML = '<span class="dr-mute">-</span>'; return; }
        td.innerHTML = `<button type="button" class="dr-hira" aria-haspopup="dialog" title="Lihat dokumen HIRA">
            <span><b>${DR.esc(d.idh)}</b>${d.ada ? ` · rev ${DR.esc(d.rev)}` : ''}${L.length > 1 ? ` <span class="dr-hira-n">+${L.length - 1}</span>` : ''}</span>
            <small>${d.ada ? (d.kosong ? '<span class="dr-hira-kosong">kosong</span> ' : '') + DR.esc(d.dept || '-') : 'tidak ditemukan di HIRA'}</small></button>`;
        td.querySelector('button').addEventListener('click', (e) => { e.stopPropagation(); bukaHira(td.dataset.hira, td.dataset.bahaya || ''); });
      });
    }).catch((e) => {
      el.querySelectorAll('td[data-hira]').forEach((td) => { td.innerHTML = `<span class="br-red" title="${DR.esc(e.message)}">gagal</span>`; });
    });
  }

  function bukaHira(hid, bahaya) {
    const L = hiraCache[hid] || [], d = dialogItem(), isi = d.querySelector('#dr-item-isi');
    d.querySelector('#dr-item-judul').textContent = `Asal HIRA${bahaya ? ' · ' + bahaya : ''}`;
    const baris = (k, v) => (v ? `<tr><th style="width:170px">${k}</th><td>${v}</td></tr>` : '');
    isi.innerHTML = L.map((x) => `<div class="dr-hira-dok">
        <div class="dr-hira-dok-k"><b>HIRA ${DR.esc(x.idh)}</b>${x.ada ? ` · rev ${DR.esc(x.rev)}` : ''}
          ${x.cocok ? '<span class="dr-pl-tag milik">memuat bahaya ini</span>' : x.kosong ? '<span class="dr-pl-tag" style="background:#fff4e5;color:#9a5b00">dokumen kosong</span>' : x.ada ? '<span class="dr-pl-tag">tidak memuat teks bahaya ini</span>' : ''}
          ${x.ada ? (x.aktif ? '<span class="dr-pl-tag">aktif</span>' : '<span class="dr-pl-tag" style="background:#fdecea;color:#b42318">tidak aktif</span>') : ''}</div>
        ${x.kosong ? '<p class="dr-mute" style="margin:0 0 6px">Dokumen ini ada di HIRA (hira_master) tetapi belum berisi bahaya-risiko, site, maupun section, sehingga tidak muncul di mv_hira_master. Nilai risiko baris baseline ini tidak berasal dari dokumen ini.</p>' : ''}
        ${x.ada ? `<table class="pr-tbl dr-tbl"><tbody>
          ${baris('Departemen', DR.esc(x.dept))}${baris('Aktivitas spesifik', DR.esc(x.spesifik))}
          ${baris('Aktivitas · sub', DR.esc([x.aktivitas, x.sub].filter(Boolean).join(' · ')))}${baris('Site', DR.esc((x.site || []).join(', ')))}
          ${baris('Bagian', DR.esc(x.bagian))}${baris('Perusahaan', DR.esc(x.perusahaan))}${baris('Klasifikasi', DR.esc(x.rutin))}${baris('Diperbarui', DR.esc(x.diperbarui))}
        </tbody></table>` : '<p class="dr-mute">ID ini tercatat di master baseline tetapi tidak ditemukan di HIRA (hira_master maupun mv_hira_master).</p>'}</div>`).join('')
      || '<p class="dr-mute">Baris baseline ini tidak punya id_hira_master di master.</p>';
    if (!d.open) d.showModal();
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
    let pemilik = [];
    try { pemilik = JSON.parse(td.dataset.pemilik || '[]'); } catch (e) { pemilik = []; }
    // Urutan rincian: komposisi PJA pelapor → bahaya belum tercover → daftar laporan.
    const kotakPja = td.parentElement && td.parentElement.querySelector('[data-pjalap]');
    const tulis = (data) => {
      if (!td.isConnected) return;
      if (kotakPja) kotakPja.innerHTML = pjaPelapor(data, pemilik);
      td.innerHTML = kotakPja ? isiSap(data) : pjaPelapor(data, pemilik) + isiSap(data);
    };
    if (sapCache[kunci]) { tulis(sapCache[kunci]); return; }
    const qPemilik = pemilik.map(([id]) => id).filter((id) => /^[0-9]+$/.test(id)).join(',');
    fetch(URL_SAP.replace('__DL__', encodeURIComponent(d)) + '?w=' + w + (qPemilik ? '&pemilik=' + qPemilik : ''), { headers: { Accept: 'application/json' } })
      .then((r) => r.json().then((j) => (r.ok ? j : Promise.reject(j))))
      .then((data) => { sapCache[kunci] = data; tulis(data); })
      .catch((e) => {
        if (!td.isConnected) return;
        if (kotakPja) kotakPja.innerHTML = '';
        td.innerHTML = `<span class="br-red">${DR.esc((e && e.pesan) || 'Gagal memuat laporan SAP.')}</span>`;
      });
  }

  // ------------------------------------------------------------ PJA yang melaporkan di satu detail lokasi
  const JENIS_SAP = [['HAZARD', 'Hazard', '#d9822b'], ['INSPEKSI', 'Inspeksi', '#3b7dd8'], ['OBSERVASI', 'Observasi', '#2e9e6b'], ['OBSERVASI AREA KRITIS', 'OAK', '#7a5cc7']];

  function barJenis(jenis, n) {
    return `<div class="dr-pl-bar" role="img" aria-label="${JENIS_SAP.filter(([k]) => jenis[k]).map(([k, t]) => `${t} ${jenis[k]}`).join(', ')}">${
      JENIS_SAP.filter(([k]) => jenis[k]).map(([k, t, c]) => `<span style="--c:${c};width:${(100 * jenis[k] / n).toFixed(1)}%" title="${t} ${jenis[k]}"></span>`).join('')}</div>`;
  }

  const kunciPelapor = (sid, nama) => sid || 'nama:' + String(nama || '').trim().toLowerCase();

  /**
   * Kelompok PJA dari laporan oleh pelapor yang BUKAN personil PJA mapping lokasi ini. Dihitung ulang dari data.laporan
   * (bukan memakai data.pja apa adanya) agar pelapor yang juga anggota PJA mapping tidak muncul dua kali.
   * Keanggotaan sid → PJA diambil dari data.pja (penugasan aktif m_pja_emp, sudah dihitung server).
   */
  function kelompokLuar(data, sidMilik, idTerkait) {
    const anggota = new Map();
    data.pja.forEach((g) => g.pelapor.forEach((o) => {
      const k = kunciPelapor(o.sid, o.nama);
      if (!anggota.has(k)) anggota.set(k, []);
      anggota.get(k).push({ g, kategori: o.kategori });
    }));
    const hasil = new Map();
    (data.laporan || []).forEach((x) => {
      const k = kunciPelapor(x.sid, x.pelapor);
      if (sidMilik.has(k)) return;
      const daftar = (anggota.get(k) || []).filter((a) => !idTerkait.has(a.g.id));
      (daftar.length ? daftar : [{ g: { id: '', nama: 'Bukan personil PJA', tipe: '' }, kategori: '' }]).forEach(({ g, kategori }) => {
        if (!hasil.has(g.id)) hasil.set(g.id, { id: g.id, nama: g.nama, tipe: g.tipe, n: 0, jenis: {}, lolos: 0, belum_closed: 0, oak_stop: 0, orang: new Map() });
        const h = hasil.get(g.id), lolos = x.pemetaan === 'lolos' ? 1 : 0;
        if (!h.orang.has(k)) h.orang.set(k, { sid: x.sid || '', nama: x.pelapor, kategori, n: 0, jenis: {}, lolos: 0 });
        [h, h.orang.get(k)].forEach((o) => { o.n++; o.jenis[x.jenis] = (o.jenis[x.jenis] || 0) + 1; o.lolos += lolos; });
        h.belum_closed += x.belum_closed ? 1 : 0;
        h.oak_stop += x.oak_stop ? 1 : 0;
      });
    });
    return [...hasil.values()]
      .map(({ orang, ...h }) => ({ ...h, pelapor: [...orang.values()].sort((p, q) => q.n - p.n || String(p.nama).localeCompare(String(q.nama))) }))
      .sort((p, q) => (p.id === '') - (q.id === '') || q.n - p.n || String(p.nama).localeCompare(String(q.nama)));
  }

  /** Satu baris PJA (bisa dibuka → komposisi jenis + daftar pelapor). */
  function barisPjaPelapor(g) {
    const tag = (g.tipe ? `<span class="dr-pl-tag">${DR.esc(g.tipe)}</span>` : '')
      + (g.relasi === 'child' ? `<span class="dr-pl-tag milik" title="Child dari ${DR.esc(g.dari)}">child · ${DR.esc(g.dari)}</span>` : '')
      + (g.relasi === 'parent' ? `<span class="dr-pl-tag milik" title="Parent dari ${DR.esc(g.dari)}">parent · ${DR.esc(g.dari)}</span>` : '');
    const masalah = [g.belum_closed && `${DR.fmt(g.belum_closed)} belum closed`, g.oak_stop && `${DR.fmt(g.oak_stop)} OAK Stop`].filter(Boolean).join(' · ') || '<span class="dr-mute">-</span>';
    const isi = `<div class="dr-pl-isi">
        <div>${JENIS_SAP.filter(([k]) => g.jenis[k]).map(([k, t, c]) => `<span class="dr-pl-chip"><i style="--c:${c}"></i>${t} <b>${DR.fmt(g.jenis[k])}</b> <span class="dr-mute">${pctI(g.jenis[k], g.n)}</span></span>`).join('')}</div>
        <div class="dr-scroll"><table class="pr-tbl dr-tbl" style="min-width:640px"><thead><tr><th>Pelapor</th><th>Kategori</th><th class="r">Laporan</th><th style="min-width:160px">Komposisi</th><th class="r">Mengidentifikasi HIRA</th></tr></thead><tbody>${
          g.pelapor.map((o) => `<tr><td><b>${DR.esc(o.nama || '-')}</b>${o.sid ? `<br><span class="dr-mute">${DR.esc(o.sid)}</span>` : ''}</td><td>${DR.esc(o.kategori || '-')}</td>
            <td class="r">${DR.fmt(o.n)}</td><td>${barJenis(o.jenis, o.n)}</td><td class="r">${pctI(o.lolos, o.n)}</td></tr>`).join('')}</tbody></table></div></div>`;
    return `<details class="dr-pl"><summary>
        <span><b>${DR.esc(g.nama)}</b>${tag}</span><span class="r"><b>${DR.fmt(g.n)}</b></span><span class="r">${DR.fmt(g.pelapor.length)}</span>
        <span>${barJenis(g.jenis, g.n)}</span><span class="r">${pctI(g.lolos, g.n)}</span><span>${masalah}</span></summary>${isi}</details>`;
  }

  const KEPALA_PJA_PELAPOR = '<div class="dr-pl-row kepala"><span>PJA pelapor</span><span class="r">Laporan</span><span class="r">Pelapor</span><span>Komposisi jenis</span><span class="r">Identifikasi HIRA</span><span>Bermasalah</span></div>';

  /**
   * Laporan SAP minggu ini per PJA pelapornya, dua bagian:
   *   1. PJA hasil mapping detail lokasi ini (kolom PJA di tabel) — yang tidak melapor tetap tampil 0.
   *   2. PJA lain (dan pelapor non-PJA) yang melaporkan di detail lokasi ini, terpisah dari bagian utama.
   * Tiap PJA bisa dibuka untuk komposisi jenis laporan dan daftar pelapornya.
   */
  function pjaPelapor(data, pemilik) {
    if (!Array.isArray(data.pja)) return '';
    const norm = (t) => String(t || '').trim().toLowerCase();
    const idMilik = new Set(pemilik.map(([id]) => id));
    const namaMilik = new Map(pemilik.map(([, nama]) => [norm(nama), nama]));
    // Parent PJA mapping (dari server, ?pemilik=…): id & nama parent → nama PJA mapping anaknya.
    const indukP = data.induk_pemilik || {};
    const idInduk = new Map(), namaInduk = new Map();
    pemilik.forEach(([id, nama]) => { const x = indukP[id]; if (x && x.id) { idInduk.set(x.id, nama); namaInduk.set(norm(x.nama), nama); } });
    // Relasi tiap PJA pelapor terhadap PJA mapping lokasi ini. Dicocokkan lewat id ATAU nama, karena m_pja punya id
    // ganda bernama sama (mis. "PJA Maintenance BC GMO" 1522 & 2310).
    const relasi = (g) => {
      if (g.id === '') return null;
      if (idMilik.has(g.id)) return { relasi: 'mapping' };
      if (idMilik.has(g.induk) || namaMilik.has(norm(g.induk_nama))) return { relasi: 'child', dari: g.induk_nama || (pemilik.find(([id]) => id === g.induk) || [])[1] || '' };
      if (idInduk.has(g.id) || namaInduk.has(norm(g.nama))) return { relasi: 'parent', dari: idInduk.get(g.id) || namaInduk.get(norm(g.nama)) };
      return null;
    };
    const urutRel = { mapping: 0, child: 1, parent: 2 };
    const G = data.pja.map((g) => { const r = relasi(g); return r ? { ...g, ...r } : null; }).filter(Boolean)
      .sort((p, q) => urutRel[p.relasi] - urutRel[q.relasi] || q.n - p.n || String(p.nama).localeCompare(String(q.nama)));
    const idTerkait = new Set(G.map((g) => g.id));
    const ada = new Set(G.filter((g) => g.relasi === 'mapping').map((g) => g.id));
    const diam = pemilik.filter(([id]) => !ada.has(id));
    const nCp = G.filter((g) => g.relasi !== 'mapping').length;
    const sidMilik = new Set(G.flatMap((g) => g.pelapor.map((o) => kunciPelapor(o.sid, o.nama))));
    const nMilik = (data.laporan || []).filter((x) => sidMilik.has(kunciPelapor(x.sid, x.pelapor))).length;
    const nLuar = (data.laporan || []).length - nMilik;
    const LG = nLuar ? kelompokLuar(data, sidMilik, idTerkait) : [];
    const nPjaLuar = LG.filter((g) => g.id !== '').length;

    const utama = G.map(barisPjaPelapor).join('')
      + diam.map(([, nama]) => `<div class="dr-pl-row kosong"><span><b>${DR.esc(nama)}</b></span><span class="r">0</span><span class="r">0</span><span class="dr-mute" style="grid-column:span 3">Tidak ada laporan di detail lokasi ini minggu ini</span></div>`).join('');
    const bagianLuar = LG.length ? `<details class="dr-pl-luar"${nPjaLuar <= 8 ? ' open' : ''}>
        <summary><b>PJA lain yang melaporkan di lokasi ini · ${DR.fmt(nPjaLuar)} PJA${LG.some((g) => g.id === '') ? ' + pelapor non-PJA' : ''}</b>
          <span class="dr-mute">${DR.fmt(nLuar)} laporan oleh personil PJA yang bukan PJA lokasi ini, bukan child, dan bukan parent-nya</span></summary>
        ${KEPALA_PJA_PELAPOR}${LG.map(barisPjaPelapor).join('')}</details>` : '';

    return `<div class="dr-pl-wrap">
        <div class="dr-pl-head"><b>PJA lokasi ini yang melaporkan · ${DR.fmt(ada.size)} dari ${DR.fmt(pemilik.length)} PJA${nCp ? ` + ${DR.fmt(nCp)} PJA child/parent` : ''}</b>
          <span class="dr-mute">${DR.fmt(nMilik)} laporan oleh personil PJA lokasi ini${nCp ? ' beserta child/parent-nya' : ''}</span>
          <span class="dr-pl-leg">${JENIS_SAP.map(([, t, c]) => `<span><i style="--c:${c}"></i>${t}</span>`).join('')}</span></div>
        ${pemilik.length ? KEPALA_PJA_PELAPOR + utama : ''}
        ${bagianLuar}
        <p class="dr-mute" style="font-size:12px;margin:6px 0 0">PJA pelapor = penugasan aktif pelapor di master PJA (m_pja_emp). Bagian atas = PJA mapping lokasi ini beserta child-nya (m_pja.id_parent = PJA lokasi) dan parent-nya. Bagian "PJA lain" hanya berisi pelapor di luar itu; pelapor yang terdaftar di beberapa PJA dihitung di setiap PJA-nya. Klik baris untuk melihat komposisi dan daftar pelapor.</p></div>`;
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
    $('#dr-matriks').innerHTML = catatan || (DR.matriks(`Baseline HIRA (risiko awal) · ${wk}${akhiran}`, A, M, 'a') + DR.matriks(`Final · ${wk}${akhiran}`, F, M, 'f')
      + (kel && nKel > nBaris ? `<div class="dr-mute" style="grid-column:1/-1;font-size:12px">${DR.fmt(nBaris)} dari ${DR.fmt(nKel)} baris kelompok ini punya nilai risiko di HIRA; sisanya tidak masuk matriks.</div>` : ''));
    const hapus = $('#dr-mx-hapus');
    if (hapus) hapus.addEventListener('click', () => { state.kelMx = ''; render(); });
    document.querySelectorAll('#dr-matriks td[data-mx]').forEach((td) => {
      const buka = () => bukaSelMx(td.dataset.mx, +td.dataset.sel, td.textContent.trim());
      td.addEventListener('click', buka);
      td.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); buka(); } });
    });
  }

  /**
   * Baris rincian (kelompok × bahaya unik di cakupan) yang jatuh di satu sel matriks, aturan sama dengan DrmBeranda:
   * awal = sel risiko awal HIRA; final = sel residual bila kategori minggu itu 1 (terkontrol penuh), selain itu awal.
   * Filter sama dengan matriks: site, level (S+H / semua), kelompok terpilih di treemap.
   * Butuh snapshot baru (teks[8..9] = sel, u[8] = kategori per minggu).
   */
  function barisSelMx(R, jenis, sel) {
    const L = R.lingkup[state.site];
    if (!L) return null;
    const lvBoleh = state.lv === 'ALL' ? null : new Set(['Significant', 'High']);
    const contoh = Object.values(R.teks || {})[0];
    if ((contoh && contoh.length < 10) || (L.u[0] && L.u[0].length < 9)) return 'lama';
    return L.u.filter(([kel, hid, lv]) => (!lvBoleh || lvBoleh.has(lv)) && (!state.kelMx || kel === state.kelMx))
      .map(([kel, hid, lv, inc, tr, kode, gap, vol, kat]) => {
        const t = R.teks[hid] || [], awal = t[8] ?? -1, sisa = t[9] ?? -1;
        const final = (kat || '')[state.w] === '1' && sisa >= 0 ? sisa : awal;
        return { kel, hid: String(hid), lv, inc, st: (kode || '')[state.w] || 'T', t, sel: jenis === 'a' ? awal : final, pakaiSisa: final !== awal };
      })
      .filter((b) => b.sel === sel && b.sel >= 0);
  }

  async function bukaSelMx(jenis, sel, nTampil) {
    const d = dialogItem(), isi = d.querySelector('#dr-item-isi');
    const kek = M.kekerapan[Math.floor(sel / 5)], kon = M.konsekuensi[sel % 5];
    const namaKel = Object.fromEntries([['X', 'Belum terpetakan'], ...M.proses.map((p) => [p.kode, p.nama])]);
    d.querySelector('#dr-item-judul').textContent = `${jenis === 'a' ? 'Baseline HIRA (risiko awal)' : 'Final'} · ${kek} × ${kon} · ${wKode(state.w)}`;
    isi.innerHTML = '<div class="dr-mute">Memuat bahaya-risiko…</div>';
    if (!d.open) d.showModal();
    try {
      const R = await muatRincian();
      const B = barisSelMx(R, jenis, sel);
      if (B === null) { isi.innerHTML = '<p class="dr-mute">Rincian untuk cakupan ini belum ada di snapshot.</p>'; return; }
      if (B === 'lama') { isi.innerHTML = '<p class="dr-mute">Snapshot masih format lama (belum menyimpan sel matriks per baris). Jalankan ulang <code>php artisan peta-risiko:sync</code>, lalu muat ulang halaman.</p>'; return; }
      const urutSt = { T: 0, G: 1, K: 2 };
      B.sort((p, q) => urutSt[p.st] - urutSt[q.st] || String(namaKel[p.kel]).localeCompare(String(namaKel[q.kel])) || String(p.t[0]).localeCompare(String(q.t[0])) || String(p.t[2]).localeCompare(String(q.t[2])));
      const n = { K: 0, G: 0, T: 0 };
      B.forEach((b) => { n[b.st] = (n[b.st] || 0) + 1; });
      isi.innerHTML = `<div style="display:flex;flex-wrap:wrap;gap:6px 16px;margin-bottom:10px;font-size:13px">
          <b>${DR.fmt(B.length)} bahaya-risiko</b>${String(B.length) !== String(nTampil).replace(/\D/g, '') ? `<span class="br-red">angka sel ${DR.esc(nTampil)}</span>` : ''}
          <span style="color:#b42318">● ${DR.fmt(n.T)} tidak teridentifikasi</span><span style="color:#a07a00">● ${DR.fmt(n.G)} ada gap</span><span style="color:#1a7f37">● ${DR.fmt(n.K)} terkontrol</span>
          <span class="dr-mute">${state.site === 'ALL' ? 'Semua site' : DR.esc(state.site)} · ${state.lv === 'ALL' ? 'semua level' : 'Significant + High'}${state.kelMx ? ' · ' + DR.esc(namaKel[state.kelMx] || state.kelMx) : ''}</span></div>
        <div class="dr-scroll"><table class="pr-tbl dr-tbl" style="min-width:900px"><thead><tr><th>Kelompok aktivitas</th><th style="min-width:200px">Aktivitas HIRA · sub</th><th style="min-width:150px">HIRA</th><th style="min-width:220px">Bahaya - risiko</th><th>Risiko</th><th>Status SAP</th></tr></thead><tbody>${
          B.map((b) => `<tr><td>${DR.esc(namaKel[b.kel] || b.kel)}</td>
            <td>${DR.esc(b.t[0] || '-')}${b.t[1] && b.t[1] !== b.t[0] ? `<br><span class="dr-mute">${DR.esc(b.t[1])}</span>` : ''}</td>
            <td data-hira="${DR.esc(b.hid)}" data-bahaya="${DR.esc(b.t[2] || '')}"><span class="dr-mute">…</span></td>
            <td><b>${DR.esc(b.t[2] || '(tanpa teks bahaya)')}</b>${+b.inc > 0 ? `<div><span class="dr-tanda ungu">insiden 12 bln (${DR.fmt(+b.inc)})</span></div>` : ''}</td>
            <td><span class="dr-lv lv-${DR.esc(b.lv)}">${DR.esc(b.lv || '-')}</span>${b.t[4] ? `<br><span class="dr-mute" style="font-size:11px">residual ${DR.esc(b.t[4])}</span>` : ''}${b.pakaiSisa ? '<br><span class="dr-mute" style="font-size:11px">final = residual (terkontrol)</span>' : ''}</td>
            <td><span class="dr-st ${b.st}">${STATUS_SAP[b.st]}</span></td></tr>`).join('')
          || '<tr><td colspan="6" class="pr-empty">Tidak ada baris.</td></tr>'}</tbody></table></div>
        <p class="dr-mute" style="font-size:12px;margin:8px 0 0">${jenis === 'a' ? 'Sel = kekerapan × konsekuensi risiko awal HIRA.' : 'Final = risiko residual HIRA bila bahaya terkontrol di ≥80% detail lokasinya minggu ini; selain itu tetap risiko awal.'} Klik kolom HIRA untuk dokumen asalnya.</p>`;
      isiHira(isi, B.map((b) => b.hid));
    } catch (e) {
      isi.innerHTML = `<p class="br-red">${DR.esc(e.message || 'Gagal memuat rincian.')}</p>`;
    }
  }

  /** Palet berurutan (terang → gelap) untuk nilai 0–1, plus warna teks yang kontras. */
  const PALET_TM = [[253, 214, 110], [248, 160, 36], [234, 98, 22], [196, 45, 28], [122, 22, 24]];
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
   * Treemap kelompok aktivitas, memakai status yang sama dengan kartu KPI (gabungan antar detail lokasi: G > K > T):
   * Teridentifikasi & Terkontrol (idk) · Teridentifikasi - Ada Gap (idg) · Tidak Teridentifikasi / Open Risk (tid).
   * Luas = bahaya belum terkontrol (Ada Gap + Open Risk); warna = porsi Open Risk, diskalakan relatif min–maks antar kotak.
   * Tiap kotak / daftar 5 teratas memuat bar komposisi tiga status. Klik memfilter matriks.
   */
  const ST_TM = [['idk', '#16a34a', 'Terkontrol'], ['idg', '#d9a400', 'Ada Gap'], ['tid', '#dc2626', 'Open Risk']];
  const barTm = (b) => `<span class="dr-tm-bar" aria-hidden="true">${ST_TM.map(([k, c]) => (b[k] ? `<i style="width:${(100 * b[k] / b.t).toFixed(1)}%;background:${c}"></i>` : '')).join('')}</span>`;
  const angkaTm = (b) => ST_TM.map(([k, c, n]) => `<span title="${n}"><i style="background:${c}"></i>${DR.fmt(b[k])}</span>`).join('');
  function renderTreemap(P, w) {
    const el = $('#dr-treemap');
    const semua = M.proses.map((k) => {
      const v = jumlahF(P, (p) => vekKel(p, w, k.kode));
      return { kode: k.kode, nama: k.nama, t: v.idk + v.idg + v.tid, idk: v.idk, idg: v.idg, tid: v.tid, risiko: v.idg + v.tid };
    }).filter((i) => i.t > 0);
    const items = semua.filter((i) => i.risiko > 0).sort((a, b) => b.risiko - a.risiko || b.tid - a.tid).map((i) => ({ ...i, v: i.risiko, open: i.tid / i.t }));
    if (!items.length) { el.innerHTML = '<div class="pr-empty">Semua bahaya teridentifikasi &amp; terkontrol pada minggu ini.</div>'; $('#dr-tm-top').innerHTML = ''; $('#dr-tm-skala').innerHTML = ''; return; }
    const lo = Math.min(...items.map((i) => i.open)), hi = Math.max(...items.map((i) => i.open));
    // Skala PERINGKAT (bukan min–maks): satu kelompok ekstrem tidak membuat yang lain pucat seragam; nilai sama = warna sama.
    const urutOpen = [...new Set(items.map((i) => Math.round(i.open * 1000)))].sort((a, b) => a - b);
    const skala = (x) => (urutOpen.length < 2 ? 0.5 : urutOpen.indexOf(Math.round(x * 1000)) / (urutOpen.length - 1));
    // Kelompok terpilih langsung dibuka di tabel Kelompok Aktivitas (aktivitas & bahayanya kelihatan).
    const pilih = (k) => { state.kelMx = state.kelMx === k ? '' : k; if (state.kelMx) state.kbuka[k] = true; render(); };
    const kls = (k) => `${state.kelMx === k ? ' on' : ''}${state.kelMx && state.kelMx !== k ? ' redup' : ''}`;
    const ket = (b) => `${b.nama}: ${DR.fmt(b.t)} bahaya · Terkontrol ${DR.fmt(b.idk)} · Ada Gap ${DR.fmt(b.idg)} · Open Risk ${DR.fmt(b.tid)} (${DR.pct(b.tid, b.t)})`;

    const W = el.clientWidth || 800, H = el.clientHeight || 420, CELAH = 3;
    el.innerHTML = squarify(items, W, H).map((b) => {
      const x = b.x + CELAH / 2, y = b.y + CELAH / 2, lw = Math.max(0, b.w - CELAH), lh = Math.max(0, b.h - CELAH);
      const besar = lw > 130 && lh > 92, sedang = lw > 78 && lh > 46, kecil = lw > 34 && lh > 22;
      const { bg, fg } = warnaTm(skala(b.open));
      const isi = besar
        ? `<span class="dr-tm-n">${DR.esc(b.nama)}</span><span class="dr-tm-v"><b>${DR.fmt(b.risiko)}</b> belum terkontrol<small>dari ${DR.fmt(b.t)} bahaya</small><span class="dr-tm-st">${angkaTm(b)}</span>${barTm(b)}</span>`
        : sedang ? `<span class="dr-tm-n">${DR.esc(b.nama)}</span><span class="dr-tm-v"><b>${DR.fmt(b.risiko)}</b>${barTm(b)}</span>`
          : kecil ? `<span class="dr-tm-v"><b>${DR.fmt(b.risiko)}</b></span>` : '';
      return `<button type="button" role="listitem" class="dr-tm-k${besar || sedang ? '' : ' kecil'}${kls(b.kode)}" data-kel="${DR.esc(b.kode)}" title="${DR.esc(ket(b))}" aria-label="${DR.esc(ket(b))}" aria-pressed="${state.kelMx === b.kode}"
        style="left:${x.toFixed(1)}px;top:${y.toFixed(1)}px;width:${lw.toFixed(1)}px;height:${lh.toFixed(1)}px;background:${bg};color:${fg}">${isi}</button>`;
    }).join('');

    const total = (k) => semua.reduce((s, b) => s + b[k], 0);
    $('#dr-tm-top').innerHTML = '<h4>5 teratas · Ada Gap + Open Risk</h4>' + items.slice(0, 5).map((b, i) => {
      const { bg, fg } = warnaTm(skala(b.open));
      return `<button type="button" class="dr-tm-i${kls(b.kode)}" data-kel="${DR.esc(b.kode)}" title="${DR.esc(ket(b))}" aria-pressed="${state.kelMx === b.kode}">
        <span class="r" style="background:${bg};color:${fg}">${i + 1}</span><span class="n">${DR.esc(b.nama)}</span><span class="v">${DR.fmt(b.risiko)}</span>
        <span class="bar">${barTm(b)}</span><span class="dr-tm-st kecil">${angkaTm(b)}</span></button>`;
    }).join('') + `<p class="dr-mute" style="margin:2px 0 0;font-size:11.5px">${DR.fmt(items.length)} dari ${DR.fmt(semua.length)} kelompok punya bahaya belum terkontrol · total Terkontrol ${DR.fmt(total('idk'))} · Ada Gap ${DR.fmt(total('idg'))} · Open Risk ${DR.fmt(total('tid'))}.</p>`;

    const legenda = `<span class="dr-tm-leg">${ST_TM.map(([, c, n]) => `<span><i style="background:${c}"></i>${n}</span>`).join('')}</span>`;
    $('#dr-tm-skala').innerHTML = legenda + (hi - lo < 0.005
      ? `<span>Warna kotak: porsi Open Risk semua kelompok ±${DR.pct(lo, 1)}</span>`
      : `<span>Warna kotak = porsi Open Risk</span><span>${DR.pct(lo, 1)}</span><i></i><span>${DR.pct(hi, 1)}</span><span class="dr-mute">(urutan antar kelompok)</span>`);

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
    document.querySelectorAll(`${sel} tr[data-akt]`).forEach((tr) => tr.addEventListener('click', (e) => {
      e.stopPropagation();
      state.abuka[tr.dataset.akt] = !state.abuka[tr.dataset.akt];
      const y = window.pageYOffset; render(); window.scrollTo(0, y);
    }));
    document.querySelectorAll(`${sel} [data-akt-isi]`).forEach((td) => isiBahayaAktivitas(td));
  }

  // ------------------------------------------------------------ Bahaya-risiko satu aktivitas (tab Kelompok Aktivitas / Group Lokasi)
  /**
   * Baris rincian (kelompok × bahaya) aktivitas ini di cakupan aktif: site, grup (tab Group Lokasi), level risiko, minggu.
   * Status sama dengan kartu KPI (K terkontrol, G ada gap, T tidak teridentifikasi) dan jumlah laporan SAP minggu itu.
   */
  async function isiBahayaAktivitas(td) {
    const [kel, aid] = td.dataset.aktIsi.split('|'), nama = td.dataset.aktNama || '';
    // Baris dicocokkan lewat ID aktivitas master (teks[10]); snapshot lama tanpa ID: lewat teks aktivitas HIRA.
    const milikAktivitas = (t) => (t.length > 10 ? String(t[10]) === aid : t[0] === nama);
    try {
      const R = await muatRincian();
      if (!td.isConnected) return;
      const L = R.lingkup[state.site];
      if (!L) { td.innerHTML = '<span class="dr-mute">Rincian untuk cakupan ini belum ada di snapshot.</span>'; return; }
      const lvBoleh = state.lv === 'ALL' ? null : new Set(['Significant', 'High']);
      const sumber = state.tab === 'grup'
        ? L.g.filter((x) => x[0] === state.grup).map(([, k, hid, lv, inc, tr, kode, gap, vol]) => ({ kel: k, hid, lv, inc, tr, kode, gap, vol }))
        : L.u.map(([k, hid, lv, inc, tr, kode, gap, vol]) => ({ kel: k, hid, lv, inc, tr, kode, gap, vol }));
      // Aktivitas dicocokkan lewat kelompok + nama aktivitas di teks rincian.
      const urutSt = { G: 0, T: 1, K: 2 }, urutLv = { Significant: 0, High: 1, Medium: 2, Low: 3 };
      const baris = sumber.filter((b) => b.kel === kel && (!lvBoleh || lvBoleh.has(b.lv)) && milikAktivitas(R.teks[b.hid] || []))
        .map((b) => ({ ...b, t: R.teks[b.hid] || ['', '', '', '', ''], st: b.kode[state.w] || 'T' }))
        .sort((a, b) => urutSt[a.st] - urutSt[b.st] || (urutLv[a.lv] ?? 9) - (urutLv[b.lv] ?? 9) || String(a.t[2]).localeCompare(String(b.t[2])));
      if (!baris.length) { td.innerHTML = '<span class="dr-mute">Tidak ada bahaya-risiko aktivitas ini pada filter terpilih.</span>'; return; }
      const n = { K: 0, G: 0, T: 0 };
      baris.forEach((b) => { n[b.st]++; });
      const wk = wKode(state.w);
      const isi = baris.map((b, i) => {
        const m = parseInt((b.gap || '')[state.w] || '0', 16);
        const [nObs, nOak, nKet] = uraiVol(b.vol)[state.w] || [0, 0, 0];
        const alasan = [m & GAP_BIT.closed && 'Belum closed', m & GAP_BIT.ulang && 'Berulang', m & GAP_BIT.stop && 'OAK Stop'].filter(Boolean)
          .map((x) => `<span class="dr-tanda merah">${x}</span>`).join('');
        const rinci = [nObs && `${DR.fmt(nObs)} Obs`, nOak && `${DR.fmt(nOak)} OAK`, nKet && `${DR.fmt(nKet)} Ketidaksesuaian`].filter(Boolean).join(' · ');
        const sap = b.st === 'T' ? '<span class="dr-sap-tidak">Tidak ada SAP</span>'
          : `<span class="dr-sap-ada">Ada · ${DR.fmt(nObs + nOak + nKet)} laporan</span>${rinci ? `<small>${rinci}</small>` : ''}`;
        return `<tr><td>${DR.esc(b.t[1] || '-')}</td><td data-hira="${DR.esc(b.hid)}" data-bahaya="${DR.esc(b.t[2] || '')}"><span class="dr-mute">…</span></td><td><b>${DR.esc(b.t[2] || '(tanpa teks bahaya)')}</b>${+b.inc > 0 ? `<div><span class="dr-tanda ungu">insiden 12 bln (${DR.fmt(+b.inc)})</span></div>` : ''}</td>
          <td><span class="dr-lv lv-${DR.esc(b.lv)}">${DR.esc(b.lv || '-')}</span></td>
          <td><span class="dr-st ${b.st}">${STATUS_SAP[b.st]}</span>${alasan ? `<div>${alasan}</div>` : ''}</td>
          <td>${sap}${b.st === 'T' ? '' : `<div><button type="button" class="dr-sap-btn" data-i="${i}" aria-expanded="false"><span class="material-symbols-outlined">description</span> Lihat SAP</button></div>`}</td></tr>
          <tr class="dr-modal-sap" data-i="${i}" hidden><td colspan="6"></td></tr>`;
      }).join('');
      td.innerHTML = `<div style="display:flex;flex-wrap:wrap;gap:6px 16px;margin-bottom:8px;font-size:13px"><b>${DR.fmt(baris.length)} bahaya-risiko · ${wk}</b>
          <span style="color:#a07a00">● ${DR.fmt(n.G)} ada gap</span><span style="color:#b42318">● ${DR.fmt(n.T)} tidak teridentifikasi</span><span style="color:#1a7f37">● ${DR.fmt(n.K)} terkontrol</span></div>
        <div class="dr-scroll"><table class="pr-tbl dr-tbl dr-st-tbl" style="min-width:1040px"><thead><tr><th>Sub aktivitas</th><th style="min-width:150px">HIRA</th><th style="min-width:240px">Bahaya - risiko</th><th>Risiko awal</th><th>Status SAP ${wk}</th><th>SAP ${wk}</th></tr></thead><tbody>${isi}</tbody></table></div>`;
      td.querySelectorAll('.dr-sap-btn').forEach((btn) => btn.addEventListener('click', (e) => { e.stopPropagation(); bukaSapBahaya({ kel: baris[+btn.dataset.i].kel, hid: baris[+btn.dataset.i].hid }, btn); }));
      isiHira(td, baris.map((b) => String(b.hid)));
    } catch (e) {
      if (td.isConnected) td.innerHTML = `<span class="br-red">${DR.esc(e.message || 'Gagal memuat rincian.')}</span>`;
    }
  }

  /** Sub-baris aktivitas HIRA satu kelompok; di tab Group Lokasi hanya aktivitas dan baris di grup terpilih. */
  function barisAktivitas(kel, P, w, NW) {
    const diGrup = state.tab === 'grup';
    const vek = (p, i, a) => vekDari(ambilKunci(diGrup ? (p.gka || [])[i] : (p.akt || [])[i], diGrup ? `${state.grup}|${a}` : a));
    const L = Object.entries(M.beranda.akt).filter(([, x]) => x[1] === kel)
      .map(([a, x]) => ({ a, nama: x[0], v: jumlahF(P, (p) => vek(p, w, a)), v0: w + 1 < NW ? jumlahF(P, (p) => vek(p, w + 1, a)) : null }))
      .filter((r) => r.v.t).sort((a, b) => (b.v.t - b.v.full) - (a.v.t - a.v.full) || b.v.t - a.v.t);
    state.abuka ||= {};
    // Aktivitas bisa dibuka: bahaya-risikonya dengan status SAP, jenis gap, dan laporan SAP (dimuat dari rincian.json).
    const isi = L.map(({ a, nama, v, v0 }) => {
      const kunci = `${kel}|${a}`, buka = !!state.abuka[kunci];
      return `<tr class="br-sub br-akt${buka ? ' open' : ''}" data-akt="${DR.esc(kunci)}" style="cursor:pointer"><td style="padding-left:40px"><button class="br-cr" type="button" aria-expanded="${buka}" aria-label="${buka ? 'Tutup' : 'Buka'} bahaya-risiko ${DR.esc(nama || '(tanpa nama aktivitas)')}">▸</button>${DR.esc(nama || '(tanpa nama aktivitas)')}</td>${barisVek(v, v0)}<td class="dr-mute">${gapTxt(v)}</td></tr>`
        + (buka ? `<tr class="br-sub br-buka"><td colspan="8" style="padding:6px 16px 14px 64px" data-akt-isi="${DR.esc(kunci)}" data-akt-nama="${DR.esc(nama)}"><span class="dr-mute">Memuat bahaya-risiko…</span></td></tr>` : '');
    }).join('')
      || `<tr class="br-sub"><td colspan="8" class="dr-mute" style="padding-left:40px">Tidak ada aktivitas pada level risiko terpilih${
        state.perusahaan !== 'ALL' ? ' (rincian per aktivitas tidak dipecah per perusahaan — pilih "Semua perusahaan" untuk melihatnya)'
          : adaUnik() && M.beranda.ringkas.ALL.ALL.akt ? '' : ' (snapshot lama — jalankan ulang peta-risiko:sync)'}.</td></tr>`;
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
  const KOSONG = { t: 0, full: 0, part: 0, gapo: 0, none: 0, g2: 0, g3: 0, g4: 0, cak: 0, idk: 0, idg: 0, tid: 0, turun: 0, laten: 0 };

  /** Vektor KOLOM_MGMT per level → komposisi untuk level risiko terpilih. */
  function vekDari(perLv) {
    const K = M.beranda.kolomMgmt, ix = Object.fromEntries(K.map((k, i) => [k, i])), c = Array(K.length).fill(0);
    lvAmbil(perLv).forEach((lv) => ((perLv || {})[lv] || []).forEach((v, i) => { c[i] += v; }));
    return { t: c[ix.tot], full: c[ix.full], part: c[ix.part], gapo: c[ix.gaponly], none: c[ix.none], g2: c[ix.closed], g3: c[ix.ulang], g4: c[ix.stop],
      ...Object.fromEntries(['cak', 'idk', 'idg', 'tid', 'turun', 'laten'].map((k) => [k, ix[k] === undefined ? 0 : c[ix[k]]])) };
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

  /**
   * Batang komposisi per minggu. Tiap segmen diberi label persen bila cukup tinggi; angka di bawah batang:
   * mode pita = rata-rata cakupan detail lokasi (lebih bergerak daripada porsi pita teratas), mode spec = porsi terkontrol penuh.
   */
  function trenPeriode(P, f = vekPja, L = LBL_SPEC) {
    const W = DR.BW, NW = M.beranda.minggu.length, col = [], pita = L !== LBL_SPEC;
    const seg = (x, n, warna, gelap) => {
      const p = 100 * x / n;
      const teks = p >= 14 ? `<em style="color:${gelap ? '#fff' : '#3d2f00'}">${Math.round(p)}%</em>` : '';
      return `<i style="height:${p.toFixed(1)}%;background:${warna}">${teks}</i>`;
    };
    for (let i = NW - 1; i >= 0; i--) {
      const v = P.reduce((a, p) => tambahVek(a, f(p, i)), KOSONG), n = Math.max(1, v.t);
      const bawah = pita ? `cakupan ${DR.pct(v.cak / 1000, v.t)}` : `penuh ${pctI(v.full, v.t)}`;
      col.push(`<div class="br-wc${i === state.w ? ' sel' : ''}" data-w="${i}" title="${DR.esc(DR.minggu(M.beranda.minggu[i], M.sampai, i, M.beranda.minggu[0]).label)}: ${L.kFull} ${DR.fmt(v.full)}, ${L.kPart} ${DR.fmt(v.part)}, ${L.kGapo} ${DR.fmt(v.gapo)}, ${L.kNone} ${DR.fmt(v.none)}${pita ? ` · rata-rata cakupan ${DR.pct(v.cak / 1000, v.t)} DL` : ''}">
        <div class="stk">${seg(v.none, n, W.none, false)}${seg(v.gapo, n, W.bm, true)}${seg(v.part, n, W.part, false)}${seg(v.full, n, W.ok, true)}</div>
        <span>${wKode(i)}${wBerjalan(i) ? '*' : ''}</span><span class="br-wv">${bawah}</span></div>`);
    }
    const ket = pita
      ? 'Angka di dalam batang = porsi bahaya-risiko per pita cakupan. Angka di bawah = rata-rata porsi detail lokasi yang terkontrol.'
      : 'Angka di bawah batang = porsi baris terkontrol penuh.';
    return `<div class="br-wb">${col.join('')}</div><div class="dr-mute" style="font-size:12px;margin-top:6px">${ket}${wBerjalan(0) ? ' · * minggu berjalan, belum penuh' : ''}</div>`;
  }

  /**
   * Komposisi per minggu dengan 3 kategori kartu KPI (status gabungan antar detail lokasi, G > K > T):
   * Teridentifikasi & Terkontrol (idk), Teridentifikasi - Ada Gap (idg), Tidak Teridentifikasi / Open Risk (tid).
   * idk + idg + tid = jumlah baris, jadi angkanya sama persis dengan kartu di atas.
   */
  const STATUS3 = [['idk', '#1a7f37', 'Teridentifikasi & Terkontrol', true], ['idg', '#d9a400', 'Teridentifikasi - Ada Gap', false], ['tid', '#b42318', 'Tidak Teridentifikasi (Open Risk)', true]];
  function trenStatus(P, f) {
    const NW = M.beranda.minggu.length, col = [];
    const seg = (x, n, warna, gelap) => {
      const p = 100 * x / n;
      const teks = p >= 10 ? `<em style="color:${gelap ? '#fff' : '#3d2f00'}">${Math.round(p)}%</em>` : '';
      return `<i style="height:${p.toFixed(1)}%;background:${warna}">${teks}</i>`;
    };
    for (let i = NW - 1; i >= 0; i--) {
      const v = P.reduce((a, p) => tambahVek(a, f(p, i)), KOSONG), n = Math.max(1, v.t);
      const label = DR.minggu(M.beranda.minggu[i], M.sampai, i, M.beranda.minggu[0]).label;
      const rinci = STATUS3.map(([k, , nama]) => `${nama} ${DR.fmt(v[k])} (${pctI(v[k], v.t)})`).join(', ');
      // Ditumpuk dari bawah: terkontrol, ada gap, tidak teridentifikasi (di atas).
      col.push(`<div class="br-wc${i === state.w ? ' sel' : ''}" data-w="${i}" title="${DR.esc(label)}: ${DR.esc(rinci)} · ${DR.fmt(v.t)} baris">
        <div class="stk">${[...STATUS3].reverse().map(([k, warna, , gelap]) => seg(v[k], n, warna, gelap)).join('')}</div>
        <span>${wKode(i)}${wBerjalan(i) ? '*' : ''}</span><span class="br-wv">terkontrol ${pctI(v.idk, v.t)}</span></div>`);
    }
    return `<div class="br-wb">${col.join('')}</div><div class="dr-mute" style="font-size:12px;margin-top:6px">Angka di dalam batang = porsi bahaya-risiko per status SAP (sama dengan kartu di atas). Angka di bawah = porsi teridentifikasi &amp; terkontrol.${wBerjalan(0) ? ' · * minggu berjalan, belum penuh' : ''}</div>`;
  }

  /** true bila snapshot punya status gabungan (idk/idg/tid) — sama syarat dengan trenStatus() di atas tabel ini. */
  const adaStatus3 = () => !!(M && M.beranda && M.beranda.kolomMgmt && M.beranda.kolomMgmt.includes('idk'));

  /**
   * Kolom status: 3 kategori (Teridentifikasi & Terkontrol / Ada Gap / Tidak Teridentifikasi) — keputusan user
   * 26 Nov 2026, menyamakan dengan kartu KPI & tren di atas tabel ini (sebelumnya 4 kolom pita %DL yang beda
   * istilah). "Komposisi" (bar 4 warna) tetap dipertahankan apa adanya, cuma kolom teks di sebelahnya yang
   * berubah. Snapshot lama tanpa idk/idg/tid tetap jatuh balik ke label pita %DL lama.
   */
  function barisVek(v, v0) {
    const status = `<td>${DR.sb4(v.full, v.part, v.gapo, v.none)}</td><td class="r">${DR.fmt(v.t)}</td>`
      + STATUS3.map(([k]) => `<td class="r">${k === 'idk' ? `<b>${pctI(v.idk, v.t)}</b>${v0 ? '<br>' + delta(v.idk, v.t, v0.idk, v0.t, true) : ''}` : pctI(v[k], v.t)}</td>`).join('');
    const lama = `<td>${DR.sb4(v.full, v.part, v.gapo, v.none)}</td><td class="r">${DR.fmt(v.t)}</td><td class="r"><b>${pctI(v.full, v.t)}</b>${v0 ? '<br>' + delta(v.full, v.t, v0.full, v0.t, true) : ''}</td>
      <td class="r">${pctI(v.part, v.t)}</td><td class="r">${pctI(v.gapo, v.t)}</td><td class="r">${pctI(v.none, v.t)}</td>`;
    return adaStatus3() ? status : lama;
  }
  function kepalaVek(L = LBL_SPEC) {
    const kolom = adaStatus3() ? STATUS3.map(([, , nama]) => `<th class="r">${nama}</th>`).join('')
      : `<th class="r">${L.kFull === 'Terkontrol penuh' ? 'Terkontrol penuh' : 'Terkontrol ' + L.kFull}</th><th class="r">${L.kPart}</th><th class="r">${L.kGapo}</th><th class="r">${L.kNone}</th>`;
    return `<th style="min-width:200px">Komposisi</th><th class="r">Baris</th>${kolom}`;
  }
  // Dipakai tabel "Identifikasi per periode" (disembunyikan sementara) — dihitung ulang di init() setelah M
  // siap, supaya ikut status3 juga begitu ada (sebelumnya dihitung sekali sebelum M termuat, selalu jatuh ke
  // label lama).
  let KEPALA_VEK = kepalaVek();

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
        perusahaan: p.perusahaan || r.perusahaan || [],
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
    const diPerusahaan = state.perusahaan === 'ALL' ? semua : semua.filter((o) => (o.perusahaan || []).includes(state.perusahaan));
    const diSite = diPerusahaan.filter((o) => (state.site === 'ALL' || o.site === state.site));
    const tampil = diSite.filter((o) => (state.pri === 'all' || o.pri === state.pri) && (!state.cari || o.nama.toLowerCase().includes(state.cari)));
    const urut = {
      skor: (a, b) => b.skor - a.skor || (b.tot - b.full) - (a.tot - a.full), gap: (a, b) => b.gr - a.gr, gapn: (a, b) => (b.tot - b.full) - (a.tot - a.full),
      none: (a, b) => b.nr - a.nr, lewat: (a, b) => b.lewat - a.lewat, nama: (a, b) => a.nama.localeCompare(b.nama),
    }[state.urut];
    tampil.sort(urut);
    const B = jumlah(diSite), G = B.t - B.full, W = DR.BW, wk = wKode(state.w);
    const kpi = (ac, kc, judul, angka, isi, k) => `<div class="br-kpi" style="--ac:${ac};--kc:${kc}">${ikonKpi(k)}<small>${judul}</small><b>${angka}</b><em>${isi}</em></div>`;
    // Kosong = kombinasi site + perusahaan terpilih tidak punya PJA sama sekali.
    const kosong = diSite.length === 0;

    // Kartu KPI memakai ringkasan baseline unik yang sama dengan tab Overview (bukan jumlah baris tingkat PJA,
    // yang menghitung satu bahaya berulang di tiap PJA) — sejak ringkasan unik dipecah per perusahaan juga
    // (ringkasAktif/cakupanP), kartu ini otomatis ikut tersaring sama dengan daftar PJA di bawahnya. Site tertentu
    // + perusahaan tertentu sekaligus jatuh balik ke angka gabungan seluruh site (lihat jatuhBalikSite()); daftar
    // PJA di bawah tetap tersaring tepat ke site itu karena pakai atribusi per-PJA, bukan ringkasan unik.
    // Snapshot lama tanpa ringkasan unik: jumlah per PJA.
    const fallbackSitePja = jatuhBalikSite();
    const kartuAtas = (!kosong && adaUnik())
      ? (() => {
        const P = cakupanP(), NW = M.beranda.minggu.length;
        const V = jumlahF(P, (p) => vekPja(p, state.w)), V0 = state.w + 1 < NW ? jumlahF(P, (p) => vekPja(p, state.w + 1)) : null;
        const judulAtas = `${state.site === 'ALL' || fallbackSitePja ? 'BC' : DR.esc(state.site)} · ${diSite.length} PJA · ${wk}`;
        return kartu4(V, V0, judulAtas) + (fallbackSitePja
          ? `<p class="dr-mute" style="margin:8px 0 0;font-size:12px">Kartu di atas gabungan <b>seluruh site</b> untuk perusahaan ${DR.esc(state.perusahaan)} (pecahan per site + perusahaan tidak disimpan saat sync). Daftar PJA di bawah tetap tersaring ke ${DR.esc(state.site)} saja.</p>` : '');
      })()
      : null;

    panel.innerHTML = (kosong ? '' : kartuAtas || `<div class="br-kpis">
        ${kpi('var(--navy)', 'var(--navy)', `Baris baseline · ${state.site === 'ALL' ? 'seluruh BC' : DR.esc(state.site)} · ${wk}`, DR.fmt(B.t), `${diSite.length} PJA · ${state.lv === 'ALL' ? 'semua level' : 'Significant + High'}`, 't')}
        ${kpi(W.ok, 'var(--green-d)', 'Terkontrol penuh', pctI(B.full, B.t), `${DR.fmt(B.full)} baris`, 'full')}
        ${kpi(W.part, '#9A7300', 'Terkontrol sebagian', pctI(B.part, B.t), `${DR.fmt(B.part)} baris · gap total ${pctI(G, B.t)}`, 'part')}
        ${kpi(W.none, W.bm, 'Tanpa laporan', pctI(B.none, B.t), `${DR.fmt(B.none)} baris${B.gapo ? ` · ${DR.fmt(B.gapo)} hanya bermasalah` : ''}`, 'none')}
      </div>`)
      + tabelSite(diPerusahaan, wk) + tabelDaftar(tampil, semua.length, wk);

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
