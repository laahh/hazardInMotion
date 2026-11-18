@extends('peta-risiko-pja._layout-drm')

@section('title', 'Alur Baseline · Peta Risiko PJA')

@section('hero-aksi')
  <a class="pr-back" href="{{ route('peta-risiko-pja.index') }}"><span class="material-symbols-outlined" style="font-size:18px">dashboard</span> Dashboard</a>
  <a class="pr-back" href="{{ route('peta-risiko-pja.master.grup.index') }}"><span class="material-symbols-outlined" style="font-size:18px">account_tree</span> Grup lokasi & aktivitas</a>
  <a class="pr-back" href="{{ route('peta-risiko-pja.master.baris.index') }}"><span class="material-symbols-outlined" style="font-size:18px">table_rows</span> Master per baris</a>
@endsection

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Peta Risiko PJA</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Alur baseline</span></p>
  <h1 class="pr-title">Alur baseline: dari aktivitas sampai PJA</h1>
  <p class="pr-sub">Baca dari kiri ke kanan: aktivitas HIRA dipecah menjadi sub aktivitas dan bahaya-risiko, ditangkap SAP lewat label, berlaku di grup lokasi, diwarisi detail lokasi, lalu menjadi tanggung jawab PJA. Arahkan kursor ke kotak untuk menyorot jalurnya; klik untuk rincian, klik dua kali untuk menjadikannya fokus.</p>
@endsection

@section('content')
<style>
  .al-alat { display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: center; margin-bottom: 14px; }
  .al-cari-wrap { position: relative; flex: 1 1 320px; max-width: 520px; }
  .al-cari { width: 100%; padding: 10px 16px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); background: #fff; font-size: 14px; }
  .al-hasil { position: absolute; z-index: 20; top: calc(100% + 6px); left: 0; right: 0; background: #fff; border: 1px solid rgba(0, 0, 0, .1); border-radius: 14px; box-shadow: 0 16px 40px rgba(15, 23, 42, .14); max-height: 360px; overflow: auto; padding: 6px; }
  .al-hasil button { display: flex; gap: 10px; align-items: flex-start; width: 100%; text-align: left; padding: 8px 10px; border: 0; border-radius: 10px; background: transparent; cursor: pointer; font-size: 13.5px; }
  .al-hasil button:hover, .al-hasil button:focus-visible { background: #f1f4fb; outline: none; }
  .al-hasil small { display: block; color: #8a8d90; font-size: 11.5px; }
  .al-dot { flex: none; width: 10px; height: 10px; margin-top: 4px; border-radius: 3px; background: var(--c); }
  .al-kol { display: flex; flex-wrap: wrap; gap: 6px; }
  .al-kol label { display: inline-flex; gap: 6px; align-items: center; padding: 5px 10px; border-radius: 999px; background: #fff; border: 1px solid rgba(171, 173, 175, .4); font-size: 12.5px; cursor: pointer; }
  .al-kol input { accent-color: #3952bc; }
  .al-jejak { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; margin-bottom: 10px; font-size: 12.5px; color: #8a8d90; }
  .al-jejak button { border: 1px solid rgba(171, 173, 175, .45); background: #fff; border-radius: 999px; padding: 4px 11px; cursor: pointer; font-size: 12.5px; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .al-jejak button.kini { background: #3952bc; color: #fff; border-color: #3952bc; }
  .al-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 16px; align-items: start; }
  .al-kanvas { position: relative; background: #fff; border: 1px solid rgba(0, 0, 0, .08); border-radius: 16px; overflow: auto; max-height: 78vh; }
  .al-kanvas svg { display: block; }
  .al-muat { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(255, 255, 255, .75); font-size: 13.5px; color: #595c5e; }
  .al-kepala { font: 800 11px "Plus Jakarta Sans", system-ui, sans-serif; letter-spacing: .07em; text-transform: uppercase; fill: #595c5e; }
  .al-kepala-sub { font: 600 11px "Plus Jakarta Sans", system-ui, sans-serif; fill: #9aa0a6; }
  .al-n rect.kotak { fill: #fff; stroke: rgba(15, 23, 42, .14); stroke-width: 1; transition: opacity .15s; }
  .al-n .t1 { font: 600 12.5px "Plus Jakarta Sans", system-ui, sans-serif; fill: #1f2937; }
  .al-n .t2 { font: 500 10.5px "Plus Jakarta Sans", system-ui, sans-serif; fill: #8a8d90; }
  .al-n { cursor: pointer; transition: opacity .15s; }
  .al-n.pusat rect.kotak { stroke: #1e293b; stroke-width: 2.2; }
  .al-n:focus-visible rect.kotak { stroke: #3952bc; stroke-width: 2.5; }
  .al-n:focus { outline: none; }
  .al-e { fill: none; stroke: rgba(100, 116, 139, .32); stroke-width: 1.2; transition: opacity .15s, stroke .15s; }
  svg.sorot .al-n:not(.on) { opacity: .16; }
  svg.sorot .al-e:not(.on) { opacity: .06; }
  svg.sorot .al-e.on { stroke: #3952bc; stroke-width: 2; }
  .al-detail { background: #fff; border: 1px solid rgba(0, 0, 0, .08); border-radius: 16px; padding: 16px; position: sticky; top: 90px; }
  .al-detail h3 { margin: 0 0 6px; font-size: 11px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: #595c5e; }
  .al-detail h4 { margin: 0 0 4px; font-size: 15px; line-height: 1.35; }
  .al-detail .sub { font-size: 12.5px; color: #8a8d90; margin-bottom: 10px; }
  .al-detail ul { margin: 0 0 8px; padding: 0; list-style: none; display: grid; gap: 2px; max-height: 220px; overflow: auto; }
  .al-detail li button { display: flex; gap: 8px; align-items: flex-start; width: 100%; text-align: left; border: 0; background: transparent; padding: 5px 6px; border-radius: 8px; cursor: pointer; font-size: 12.5px; }
  .al-detail li button:hover { background: #f1f4fb; }
  .al-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; border: 0; background: #3952bc; color: #fff; font-size: 12.5px; font-weight: 600; cursor: pointer; text-decoration: none; }
  .al-btn.sek { background: #fff; color: #2c2f31; border: 1px solid rgba(171, 173, 175, .45); }
  .al-aksi { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 12px; }
  .al-leg { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(171, 173, 175, .25); font-size: 12px; color: #595c5e; }
  .al-leg i { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: var(--c); margin-right: 5px; vertical-align: -1px; }
  .al-leg b { display: block; width: 100%; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #8a8d90; }
  @media (max-width: 1100px) { .al-grid { grid-template-columns: 1fr; } .al-detail { position: static; } }
  @media (prefers-reduced-motion: reduce) { .al-n, .al-e, .al-n rect.kotak { transition: none; } }
</style>

@if (! $siap)
  <div class="pr-card"><div class="pr-empty">Master database belum siap. Isi tabel master dulu (<code>php artisan peta-risiko:master-db --isi</code>).</div></div>
@else
  <div class="al-alat">
    <div class="al-cari-wrap">
      <input type="search" class="al-cari" id="al-cari" placeholder="Mulai dari aktivitas, bahaya, label SAP, grup lokasi, detail lokasi, atau PJA…" aria-label="Cari titik awal alur" autocomplete="off">
      <div class="al-hasil" id="al-hasil" hidden role="listbox" aria-label="Hasil pencarian"></div>
    </div>
    <div class="al-kol" id="al-kol" aria-label="Kolom yang ditampilkan"></div>
  </div>
  <div class="al-jejak" id="al-jejak" aria-label="Jejak penelusuran"></div>

  <div class="al-grid">
    <section class="al-kanvas" id="al-kanvas" aria-label="Alur baseline">
      <svg id="al-svg" role="img" aria-label="Diagram alur baseline"></svg>
      <div class="al-muat" id="al-muat">Memuat alur…</div>
    </section>
    <aside class="al-detail" id="al-detail" aria-live="polite">
      <h3>Rincian</h3>
      <p class="pr-meta" style="margin:0">Klik sebuah kotak untuk melihat dari mana asalnya dan ke mana mengarahnya.</p>
    </aside>
  </div>
@endif
@endsection

@section('scripts')
@if ($siap)
<script>
(function () {
  const URL_DATA = @json(route('peta-risiko-pja.korelasi.data'));
  const URL_CARI = @json(route('peta-risiko-pja.korelasi.cari'));
  const URL_GRUP = @json(route('peta-risiko-pja.master.grup.index'));
  const URL_UBAH = @json(route('peta-risiko-pja.master.index', ['t' => 'baris']));
  const URL_SELF = @json(route('peta-risiko-pja.korelasi.index'));
  const AWAL = @json($awal);
  const NS = 'http://www.w3.org/2000/svg';
  // Kolom alur dan warnanya (warna = penanda jenis, bukan status).
  const KOLOM = {
    aktivitas: ['Aktivitas HIRA', '#16a34a'], sub: ['Sub aktivitas', '#65a30d'], bahaya: ['Bahaya-risiko', '#dc2626'],
    label: ['Label SAP', '#db2777'], grup: ['Grup lokasi', '#2563eb'], dl: ['Detail lokasi', '#60a5fa'], pja: ['PJA', '#0891b2'],
  };
  const JENIS_KOLOM = { aktivitas: 'aktivitas', sub: 'sub', bahaya: 'bahaya', obs: 'label', oak: 'label', ket: 'label', grup: 'grup', dl: 'dl', pja: 'pja', kelompok: 'aktivitas' };
  const WARNA_LABEL = { obs: '#db2777', oak: '#0284c7', ket: '#f59e0b' };
  const NAMA_JENIS = { aktivitas: 'Aktivitas HIRA', sub: 'Sub aktivitas', bahaya: 'Bahaya-risiko', obs: 'Label Observasi', oak: 'Label OAK', ket: 'Label Ketidaksesuaian', grup: 'Grup lokasi', dl: 'Detail lokasi', pja: 'PJA', kelompok: 'Kelompok aktivitas' };
  const LV = { Significant: '#b91c1c', High: '#ea580c', Medium: '#ca8a04', Low: '#15803d' };
  const STATUS = { K: ['#16a34a', 'Terkontrol'], G: ['#d97706', 'Ada gap'], T: ['#9ca3af', 'Tidak teridentifikasi'] };
  const FOKUS = ['aktivitas', 'bahaya', 'obs', 'oak', 'ket', 'grup', 'dl', 'pja', 'kelompok'];
  const H = 42, GAP = 10, ATAS = 58, LEBAR_MIN = 210;
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const potong = (s, n) => (String(s).length > n ? String(s).slice(0, n - 1) + '…' : String(s));
  const warnaSimpul = (n) => (WARNA_LABEL[n.jenis] || (KOLOM[n.kolomNama] || ['', '#64748b'])[1]);
  const tampilKolom = Object.fromEntries(Object.keys(KOLOM).map((k) => [k, true]));
  let D = null, jejak = [], posisi = {};

  $('al-kol').innerHTML = Object.entries(KOLOM).map(([k, [nama, warna]]) =>
    `<label><input type="checkbox" data-kol="${k}" checked><span class="al-dot" style="--c:${warna}"></span>${nama}</label>`).join('');
  $('al-kol').querySelectorAll('input').forEach((c) => c.addEventListener('change', () => { tampilKolom[c.dataset.kol] = c.checked; gambar(); }));

  // ------------------------------------------------------------ tata letak
  /** Urutan simpul per kolom: kolom pertama urut abjad, berikutnya rata-rata posisi tetangga (barycenter), dua sapuan. */
  function tataLetak(kolom, nodes, edges) {
    const per = kolom.map(() => []);
    nodes.forEach((n) => per[n.ci].push(n));
    per.forEach((L) => L.sort((a, b) => String(a.label).localeCompare(String(b.label))));
    const idx = {};
    const catat = () => per.forEach((L) => L.forEach((n, i) => { idx[n.key] = i; }));
    catat();
    const masuk = {}, keluar = {};
    edges.forEach((e) => { (masuk[e.to] ||= []).push(e.from); (keluar[e.from] ||= []).push(e.to); });
    const rata = (L) => (L && L.length ? L.reduce((s, k) => s + (idx[k] ?? 0), 0) / L.length : null);
    for (let sapu = 0; sapu < 2; sapu++) {
      for (let c = 1; c < per.length; c++) {
        per[c].forEach((n) => { n._b = rata(masuk[n.key]) ?? rata(keluar[n.key]) ?? idx[n.key]; });
        per[c].sort((a, b) => a._b - b._b || String(a.label).localeCompare(String(b.label)));
        catat();
      }
      for (let c = per.length - 2; c >= 0; c--) {
        per[c].forEach((n) => { n._b = rata(keluar[n.key]) ?? rata(masuk[n.key]) ?? idx[n.key]; });
        per[c].sort((a, b) => a._b - b._b || String(a.label).localeCompare(String(b.label)));
        catat();
      }
    }
    return per;
  }

  function el(tag, attr, parent) {
    const e = document.createElementNS(NS, tag);
    Object.entries(attr || {}).forEach(([k, v]) => e.setAttribute(k, v));
    if (parent) parent.appendChild(e);
    return e;
  }

  // ------------------------------------------------------------ gambar
  function gambar() {
    if (!D) return;
    const kolom = D.kolom.filter((k) => tampilKolom[k] && D.node.some((n) => n.kolomNama === k));
    const ci = Object.fromEntries(kolom.map((k, i) => [k, i]));
    const nodes = D.node.filter((n) => n.kolomNama in ci).map((n) => ({ ...n, ci: ci[n.kolomNama] }));
    const ada = new Set(nodes.map((n) => n.key));
    // Kolom tersembunyi: sambungkan langsung kiri ↔ kanan melewati simpul yang disembunyikan.
    const edges = jembatan(D.edge, ada);
    const per = tataLetak(kolom, nodes, edges);
    const lebarKanvas = $('al-kanvas').clientWidth || 1000;
    const W = Math.max(LEBAR_MIN, Math.floor((lebarKanvas - 20) / Math.max(1, kolom.length)));
    const BW = W - 36, tinggi = ATAS + Math.max(1, ...per.map((L) => L.length)) * (H + GAP) + 20;
    const svg = $('al-svg');
    svg.innerHTML = '';
    svg.setAttribute('width', String(W * kolom.length + 20));
    svg.setAttribute('height', String(tinggi));
    svg.classList.remove('sorot');
    posisi = {};
    per.forEach((L, c) => L.forEach((n, i) => { posisi[n.key] = { x: 18 + c * W, y: ATAS + i * (H + GAP), n }; }));

    // Judul kolom.
    kolom.forEach((k, c) => {
      const x = 18 + c * W;
      el('rect', { x, y: 12, width: BW, height: 4, rx: 2, fill: KOLOM[k][1] }, svg);
      const t = el('text', { x, y: 34, class: 'al-kepala' }, svg);
      t.textContent = KOLOM[k][0];
      const s = el('text', { x, y: 48, class: 'al-kepala-sub' }, svg);
      const lebih = (D.potong || {})[k === 'label' ? 'label' : k] || 0;
      s.textContent = `${per[c].length}${lebih ? ' ditampilkan · +' + lebih + ' lainnya' : ''}`;
    });

    // Garis dulu (di bawah kotak).
    const gGaris = el('g', {}, svg);
    edges.forEach((e) => {
      const a = posisi[e.from], b = posisi[e.to];
      if (!a || !b) return;
      const x1 = a.x + BW, y1 = a.y + H / 2, x2 = b.x, y2 = b.y + H / 2, dx = Math.max(40, (x2 - x1) / 2);
      el('path', { d: `M${x1},${y1} C${x1 + dx},${y1} ${x2 - dx},${y2} ${x2},${y2}`, class: 'al-e', 'data-from': e.from, 'data-to': e.to }, gGaris);
    });

    const maksHuruf = Math.max(14, Math.floor((BW - 26) / 6.6));
    Object.values(posisi).forEach(({ x, y, n }) => {
      const g = el('g', { class: 'al-n' + (n.pusat ? ' pusat' : ''), transform: `translate(${x},${y})`, tabindex: '0', role: 'button', 'data-key': n.key,
        'aria-label': `${NAMA_JENIS[n.jenis] || n.jenis}: ${n.label}` }, svg);
      el('title', {}, g).textContent = `${NAMA_JENIS[n.jenis] || n.jenis}: ${n.label}\n${n.sub}${STATUS[n.status] ? '\nStatus SAP: ' + STATUS[n.status][1] : ''}`;
      el('rect', { class: 'kotak', width: BW, height: H, rx: 9 }, g);
      el('rect', { width: 5, height: H, rx: 2.5, fill: n.jenis === 'bahaya' ? (LV[n.kb] || '#dc2626') : warnaSimpul(n) }, g);
      const t1 = el('text', { x: 14, y: 17, class: 't1' }, g);
      t1.textContent = potong(n.label, maksHuruf);
      const t2 = el('text', { x: 14, y: 32, class: 't2' }, g);
      t2.textContent = potong(n.sub, maksHuruf + 6);
      if (STATUS[n.status]) el('circle', { cx: BW - 11, cy: H / 2, r: 5.5, fill: STATUS[n.status][0], stroke: '#fff', 'stroke-width': 1.5 }, g);
      g.addEventListener('mouseenter', () => sorot(n.key, edges));
      g.addEventListener('mouseleave', () => svg.classList.remove('sorot'));
      g.addEventListener('focus', () => sorot(n.key, edges));
      g.addEventListener('blur', () => svg.classList.remove('sorot'));
      g.addEventListener('click', () => detail(n.key, edges));
      g.addEventListener('dblclick', () => fokus(n));
      g.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); fokus(n); } else if (ev.key === ' ') { ev.preventDefault(); detail(n.key, edges); } });
    });
  }

  /** Relasi setelah kolom disembunyikan: simpul tersembunyi dilewati (A → tersembunyi → B menjadi A → B). */
  function jembatan(edges, ada) {
    const keluar = {};
    edges.forEach((e) => (keluar[e.from] ||= []).push(e.to));
    const hasil = new Map();
    const ikut = (asal, k, lihat) => (keluar[k] || []).forEach((t) => {
      if (lihat.has(t)) return;
      lihat.add(t);
      if (ada.has(t)) hasil.set(asal + '|' + t, { from: asal, to: t }); else ikut(asal, t, lihat);
    });
    [...ada].forEach((k) => ikut(k, k, new Set()));
    return [...hasil.values()];
  }

  /** Sorot seluruh jalur: ke kiri (asal) dan ke kanan (tujuan) dari simpul. */
  function sorot(key, edges) {
    const masuk = {}, keluar = {};
    edges.forEach((e) => { (masuk[e.to] ||= []).push(e); (keluar[e.from] ||= []).push(e); });
    const on = new Set([key]), eOn = new Set();
    const jalan = (k, arah) => (arah[k] || []).forEach((e) => {
      const t = arah === masuk ? e.from : e.to;
      eOn.add(e.from + '|' + e.to);
      if (!on.has(t)) { on.add(t); jalan(t, arah); }
    });
    jalan(key, masuk);
    jalan(key, keluar);
    const svg = $('al-svg');
    svg.classList.add('sorot');
    svg.querySelectorAll('.al-n').forEach((g) => g.classList.toggle('on', on.has(g.dataset.key)));
    svg.querySelectorAll('.al-e').forEach((p) => p.classList.toggle('on', eOn.has(p.dataset.from + '|' + p.dataset.to)));
  }

  function detail(key, edges) {
    const p = posisi[key];
    if (!p) return;
    const n = p.n;
    const daftar = (arah) => {
      const L = edges.filter((e) => (arah === 'kiri' ? e.to === key : e.from === key)).map((e) => posisi[arah === 'kiri' ? e.from : e.to]).filter(Boolean).map((x) => x.n);
      return L.length ? `<ul>${L.map((t) => `<li><button type="button" data-key="${esc(t.key)}"><span class="al-dot" style="--c:${warnaSimpul(t)}"></span><span>${esc(t.label)}<small style="display:block;color:#8a8d90">${esc(NAMA_JENIS[t.jenis] || t.jenis)} · ${esc(t.sub)}</small></span></button></li>`).join('')}</ul>` : '<p class="pr-meta" style="margin:0 0 8px">—</p>';
    };
    const st = STATUS[n.status];
    const tautan = n.jenis === 'grup' ? `<a class="al-btn sek" href="${URL_GRUP}?gid=${encodeURIComponent(n.id)}">Edit di master grup</a>`
      : n.jenis === 'bahaya' ? `<a class="al-btn sek" href="${URL_UBAH}&ubah=${encodeURIComponent(n.id)}">Ubah baris HIRA</a>` : '';
    $('al-detail').innerHTML = `<h3>${esc(NAMA_JENIS[n.jenis] || n.jenis)}</h3><h4>${esc(n.label)}</h4>
      <div class="sub">${esc(n.sub)}${st ? ` · <b style="color:${st[0]}">${st[1]}</b>` : ''}</div>
      <div class="al-aksi">${!n.pusat && FOKUS.includes(n.jenis) && n.jenis !== 'sub' ? '<button type="button" class="al-btn" id="al-fokus">Jadikan fokus</button>' : ''}${tautan}</div>
      <p class="pr-meta" style="margin:0 0 4px"><b>Berasal dari</b></p>${daftar('kiri')}
      <p class="pr-meta" style="margin:8px 0 4px"><b>Mengarah ke</b></p>${daftar('kanan')}
      <div class="al-leg"><b>Bahaya-risiko</b>${Object.entries(LV).map(([l, c]) => `<span><i style="--c:${c};border-radius:2px"></i>${l}</span>`).join('')}
        ${Object.values(STATUS).map(([c, t]) => `<span><i style="--c:${c}"></i>${t}</span>`).join('')}</div>`;
    const f = $('al-fokus');
    if (f) f.addEventListener('click', () => fokus(n));
    $('al-detail').querySelectorAll('li button').forEach((b) => b.addEventListener('click', () => {
      const t = posisi[b.dataset.key];
      if (t) { $('al-kanvas').scrollTo({ top: Math.max(0, t.y - 120), left: Math.max(0, t.x - 120), behavior: 'smooth' }); sorot(b.dataset.key, edges); detail(b.dataset.key, edges); }
    }));
  }

  function fokus(n) {
    if (n.pusat || !FOKUS.includes(n.jenis) || n.jenis === 'sub') return;
    muat(n.jenis, n.id, n.label);
  }

  async function muat(jenis, id, label) {
    $('al-muat').hidden = false;
    try {
      const r = await fetch(`${URL_DATA}?${new URLSearchParams({ jenis, id })}`, { headers: { Accept: 'application/json' } });
      const d = await r.json();
      if (!r.ok) throw new Error(d.pesan || `HTTP ${r.status}`);
      d.node.forEach((n) => { n.kolomNama = d.kolom[n.kolom]; });
      D = d;
      const pusat = d.node.find((n) => n.key === d.pusat);
      const i = jejak.findIndex((x) => x.jenis === jenis && x.id === id);
      jejak = i >= 0 ? jejak.slice(0, i + 1) : [...jejak, { jenis, id, label: pusat ? pusat.label : (label || id) }].slice(-8);
      gambarJejak();
      gambar();
      history.replaceState(null, '', `${URL_SELF}?${new URLSearchParams({ jenis, id })}`);
      if (pusat) detail(pusat.key, jembatan(D.edge, new Set(Object.keys(posisi))));
      $('al-kanvas').scrollTo({ top: 0, left: 0 });
    } catch (e) {
      $('al-detail').innerHTML = `<h3>Rincian</h3><p class="br-red" style="margin:0">Alur gagal dimuat: ${esc(e.message)}</p>`;
    } finally {
      $('al-muat').hidden = true;
    }
  }

  function gambarJejak() {
    $('al-jejak').innerHTML = (jejak.length ? '<span>Penelusuran:</span>' : '') + jejak.map((x, i) => `<button type="button" data-i="${i}" class="${i === jejak.length - 1 ? 'kini' : ''}" title="${esc(x.label)}">${esc(potong(x.label, 30))}</button>`).join('<span aria-hidden="true">›</span>');
    $('al-jejak').querySelectorAll('button').forEach((b) => b.addEventListener('click', () => { const x = jejak[+b.dataset.i]; muat(x.jenis, x.id, x.label); }));
  }

  // Pencarian titik awal.
  let t = 0;
  const hasil = $('al-hasil');
  $('al-cari').addEventListener('input', (e) => {
    clearTimeout(t);
    const q = e.target.value.trim();
    if (q.length < 2) { hasil.hidden = true; return; }
    t = setTimeout(async () => {
      try {
        const r = await fetch(`${URL_CARI}?${new URLSearchParams({ q })}`, { headers: { Accept: 'application/json' } });
        const d = await r.json();
        hasil.innerHTML = (d.hasil || []).map((h, i) => `<button type="button" role="option" data-i="${i}"><span class="al-dot" style="--c:${WARNA_LABEL[h.jenis] || (KOLOM[JENIS_KOLOM[h.jenis]] || ['', '#999'])[1]}"></span><span>${esc(potong(h.label, 80))}<small>${esc(NAMA_JENIS[h.jenis] || h.sub)} · ${esc(h.sub)}</small></span></button>`).join('')
          || '<p class="pr-meta" style="margin:8px 10px">Tidak ditemukan.</p>';
        hasil.hidden = false;
        hasil.querySelectorAll('button').forEach((b) => b.addEventListener('click', () => { const h = d.hasil[+b.dataset.i]; hasil.hidden = true; jejak = []; muat(h.jenis, h.id, h.label); }));
      } catch (err) { hasil.innerHTML = '<p class="pr-meta" style="margin:8px 10px">Pencarian gagal.</p>'; hasil.hidden = false; }
    }, 250);
  });
  document.addEventListener('click', (e) => { if (!e.target.closest('.al-cari-wrap')) hasil.hidden = true; });
  let ubah = 0;
  window.addEventListener('resize', () => { clearTimeout(ubah); ubah = setTimeout(gambar, 200); });

  if (AWAL) muat(AWAL.jenis, String(AWAL.id));
})();
</script>
@endif
@endsection
