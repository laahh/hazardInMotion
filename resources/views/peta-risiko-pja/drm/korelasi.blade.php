@extends('peta-risiko-pja._layout-drm')

@section('title', 'Peta Korelasi · Peta Risiko PJA')

@section('hero-aksi')
  <a class="pr-back" href="{{ route('peta-risiko-pja.index') }}"><span class="material-symbols-outlined" style="font-size:18px">dashboard</span> Dashboard</a>
  <a class="pr-back" href="{{ route('peta-risiko-pja.master.grup.index') }}"><span class="material-symbols-outlined" style="font-size:18px">account_tree</span> Grup lokasi & aktivitas</a>
  <a class="pr-back" href="{{ route('peta-risiko-pja.master.baris.index') }}"><span class="material-symbols-outlined" style="font-size:18px">table_rows</span> Master per baris</a>
@endsection

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Peta Risiko PJA</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Peta korelasi</span></p>
  <h1 class="pr-title">Peta korelasi baseline</h1>
  <p class="pr-sub">Bagaimana grup lokasi, detail lokasi, PJA, aktivitas HIRA, kelompok aktivitas, bahaya-risiko, dan label SAP saling terhubung. Klik simpul untuk melihat rinciannya, klik dua kali (atau "Jadikan pusat") untuk menelusuri dari simpul itu.</p>
@endsection

@section('content')
<style>
  .kr-wrap { display: grid; grid-template-columns: minmax(0, 330px) minmax(0, 1fr); gap: 16px; align-items: start; }
  .kr-side { display: grid; gap: 14px; }
  .kr-card { background: #fff; border: 1px solid rgba(0, 0, 0, .08); border-radius: 16px; padding: 14px 16px; }
  .kr-card h3 { margin: 0 0 10px; font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #595c5e; }
  .kr-cari { width: 100%; padding: 9px 14px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); background: #f8fafc; font-size: 13.5px; }
  .kr-hasil { display: grid; gap: 2px; margin-top: 8px; max-height: 260px; overflow: auto; }
  .kr-hasil button { display: flex; gap: 8px; align-items: flex-start; text-align: left; padding: 7px 8px; border: 0; border-radius: 10px; background: transparent; cursor: pointer; font-size: 13px; }
  .kr-hasil button:hover, .kr-hasil button:focus-visible { background: #f1f4fb; outline: none; }
  .kr-hasil small { display: block; color: #8a8d90; font-size: 11.5px; }
  .kr-dot { flex: none; width: 10px; height: 10px; margin-top: 4px; border-radius: 50%; background: var(--c); }
  .kr-leg { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 10px; font-size: 12.5px; }
  .kr-leg label { display: flex; gap: 6px; align-items: center; cursor: pointer; }
  .kr-leg input { accent-color: #3952bc; }
  .kr-status { display: flex; flex-wrap: wrap; gap: 6px 12px; margin-top: 10px; font-size: 12px; color: #595c5e; }
  .kr-status i { display: inline-block; width: 12px; height: 12px; border-radius: 50%; border: 3px solid var(--c); background: #fff; vertical-align: -2px; margin-right: 4px; }
  .kr-graf { position: relative; height: 74vh; min-height: 520px; background: #fbfcfe; border: 1px solid rgba(0, 0, 0, .08); border-radius: 16px; overflow: hidden; }
  .kr-graf #kr-net { position: absolute; inset: 0; }
  .kr-atas { position: absolute; top: 10px; left: 12px; right: 12px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; z-index: 2; pointer-events: none; }
  .kr-atas > * { pointer-events: auto; }
  .kr-jejak { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; font-size: 12px; }
  .kr-jejak button { border: 1px solid rgba(171, 173, 175, .45); background: rgba(255, 255, 255, .92); border-radius: 999px; padding: 4px 10px; cursor: pointer; font-size: 12px; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .kr-jejak button.kini { background: #3952bc; color: #fff; border-color: #3952bc; }
  .kr-info-ringkas { margin-left: auto; background: rgba(255, 255, 255, .92); border-radius: 999px; padding: 4px 12px; font-size: 12px; color: #595c5e; border: 1px solid rgba(171, 173, 175, .3); }
  .kr-muat { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(251, 252, 254, .7); font-size: 13.5px; color: #595c5e; z-index: 3; }
  .kr-detail h4 { margin: 0 0 2px; font-size: 15px; line-height: 1.35; }
  .kr-detail .sub { font-size: 12.5px; color: #8a8d90; margin-bottom: 10px; }
  .kr-detail ul { margin: 0; padding: 0; list-style: none; display: grid; gap: 4px; max-height: 300px; overflow: auto; }
  .kr-detail li button { display: flex; gap: 8px; align-items: flex-start; width: 100%; text-align: left; border: 0; background: transparent; padding: 4px 6px; border-radius: 8px; cursor: pointer; font-size: 12.5px; }
  .kr-detail li button:hover { background: #f1f4fb; }
  .kr-aksi { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0; }
  .kr-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; border: 0; background: #3952bc; color: #fff; font-size: 12.5px; font-weight: 600; cursor: pointer; text-decoration: none; }
  .kr-btn.sek { background: #fff; color: #2c2f31; border: 1px solid rgba(171, 173, 175, .45); }
  .kr-tag { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background: #eef1f5; color: #475569; }
  @media (max-width: 960px) { .kr-wrap { grid-template-columns: 1fr; } .kr-graf { height: 62vh; } }
</style>

@if (! $siap)
  <div class="pr-card"><div class="pr-empty">Master database belum siap. Isi tabel master dulu (<code>php artisan peta-risiko:master-db --isi</code>).</div></div>
@else
  <div class="kr-wrap">
    <aside class="kr-side">
      <div class="kr-card">
        <h3>Cari simpul</h3>
        <input type="search" class="kr-cari" id="kr-cari" placeholder="Grup, aktivitas, bahaya, label, PJA, detail lokasi…" aria-label="Cari simpul" autocomplete="off">
        <div class="kr-hasil" id="kr-hasil" role="listbox" aria-label="Hasil pencarian"></div>
      </div>
      <div class="kr-card">
        <h3>Jenis simpul</h3>
        <div class="kr-leg" id="kr-leg"></div>
        <div class="kr-status" title="Status SAP bahaya-risiko di seluruh BC, minggu penuh terakhir (snapshot)">
          <span><i style="--c:#16a34a"></i>Terkontrol</span><span><i style="--c:#d97706"></i>Ada gap</span><span><i style="--c:#9ca3af"></i>Tidak teridentifikasi</span>
        </div>
        <p class="pr-meta" style="margin:8px 0 0" id="kr-minggu"></p>
      </div>
      <div class="kr-card kr-detail" id="kr-detail" aria-live="polite">
        <h3>Detail simpul</h3>
        <p class="pr-meta" style="margin:0">Klik simpul di graf untuk melihat hubungannya.</p>
      </div>
    </aside>

    <section class="kr-graf" aria-label="Graf korelasi">
      <div class="kr-atas"><div class="kr-jejak" id="kr-jejak" aria-label="Jejak penelusuran"></div><span class="kr-info-ringkas" id="kr-ringkas"></span></div>
      <div id="kr-net"></div>
      <div class="kr-muat" id="kr-muat">Memuat graf…</div>
    </section>
  </div>
@endif
@endsection

@section('scripts')
@if ($siap)
<script src="https://cdn.jsdelivr.net/npm/vis-network@9.1.9/standalone/umd/vis-network.min.js"></script>
<script>
(function () {
  const URL_DATA = @json(route('peta-risiko-pja.korelasi.data'));
  const URL_CARI = @json(route('peta-risiko-pja.korelasi.cari'));
  const URL_GRUP = @json(route('peta-risiko-pja.master.grup.index'));
  const URL_UBAH = @json(route('peta-risiko-pja.master.index', ['t' => 'baris']));
  const URL_SELF = @json(route('peta-risiko-pja.korelasi.index'));
  const AWAL = @json($awal);
  const JENIS = {
    grup: ['Grup lokasi', '#2563eb', 26], dl: ['Detail lokasi', '#93c5fd', 11], pja: ['PJA', '#0891b2', 16],
    kelompok: ['Kelompok aktivitas', '#7c3aed', 20], aktivitas: ['Aktivitas HIRA', '#16a34a', 16], bahaya: ['Bahaya-risiko', '#dc2626', 12],
    obs: ['Label Observasi', '#db2777', 10], oak: ['Label OAK', '#0284c7', 10], ket: ['Label Ketidaksesuaian', '#f59e0b', 10],
  };
  const LV = { Significant: '#b91c1c', High: '#ea580c', Medium: '#ca8a04', Low: '#15803d' };
  const STATUS = { K: ['#16a34a', 'Terkontrol'], G: ['#d97706', 'Ada gap'], T: ['#9ca3af', 'Tidak teridentifikasi'] };
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const potong = (s, n) => (s.length > n ? s.slice(0, n - 1) + '…' : s);
  const tampilJenis = Object.fromEntries(Object.keys(JENIS).map((k) => [k, true]));
  let jejak = [], G = null;

  // Legenda + saklar jenis simpul.
  $('kr-leg').innerHTML = Object.entries(JENIS).map(([k, [nama, warna]]) =>
    `<label><input type="checkbox" data-jenis="${k}" checked><span class="kr-dot" style="--c:${warna}"></span>${nama}</label>`).join('');
  $('kr-leg').querySelectorAll('input').forEach((c) => c.addEventListener('change', () => { tampilJenis[c.dataset.jenis] = c.checked; terapkanSaring(); }));

  const nodes = new vis.DataSet(), edges = new vis.DataSet();
  const net = new vis.Network($('kr-net'), { nodes, edges }, {
    physics: { solver: 'forceAtlas2Based', forceAtlas2Based: { gravitationalConstant: -55, springLength: 110, avoidOverlap: 0.4 }, stabilization: { iterations: 220 } },
    interaction: { hover: true, tooltipDelay: 120, hoverConnectedEdges: true, navigationButtons: false, keyboard: false },
    nodes: { shape: 'dot', font: { size: 13, face: 'Plus Jakarta Sans, system-ui, sans-serif', color: '#2c2f31', strokeWidth: 3, strokeColor: '#fbfcfe' } },
    edges: { color: { color: 'rgba(100,116,139,.28)', highlight: '#3952bc', hover: '#3952bc' }, width: 1, smooth: { type: 'continuous' } },
  });

  function simpulVis(n) {
    const [nama, warna, ukuran] = JENIS[n.jenis] || ['?', '#999', 10];
    const isi = n.jenis === 'bahaya' ? (LV[n.kb] || warna) : warna;
    const st = STATUS[n.status];
    return {
      id: n.key, label: potong(n.label, n.pusat ? 48 : 32), title: `${nama}: ${n.label}\n${n.sub}${st ? '\nStatus SAP: ' + st[1] : ''}`,
      color: { background: isi, border: st ? st[0] : isi, highlight: { background: isi, border: '#1e293b' }, hover: { background: isi, border: '#1e293b' } },
      borderWidth: st ? 4 : 1, size: n.pusat ? ukuran + 12 : ukuran, font: n.pusat ? { size: 16, bold: true } : undefined, hidden: !tampilJenis[n.jenis],
    };
  }

  function terapkanSaring() {
    if (!G) return;
    nodes.update(G.node.map((n) => ({ id: n.key, hidden: !tampilJenis[n.jenis] && !n.pusat })));
  }

  async function muat(jenis, id, label) {
    $('kr-muat').hidden = false;
    try {
      const r = await fetch(`${URL_DATA}?${new URLSearchParams({ jenis, id })}`, { headers: { Accept: 'application/json' } });
      const d = await r.json();
      if (!r.ok) throw new Error(d.pesan || `HTTP ${r.status}`);
      G = d;
      nodes.clear(); edges.clear();
      nodes.add(d.node.map(simpulVis));
      edges.add(d.edge.map((e, i) => ({ id: i, from: e.from, to: e.to })));
      const pusat = d.node.find((n) => n.key === d.pusat);
      const i = jejak.findIndex((x) => x.jenis === jenis && x.id === id);
      jejak = i >= 0 ? jejak.slice(0, i + 1) : [...jejak, { jenis, id, label: pusat ? pusat.label : (label || id) }].slice(-8);
      gambarJejak();
      const potongTeks = Object.entries(d.potong || {}).map(([k, n]) => `${n} ${JENIS[k] ? JENIS[k][0].toLowerCase() : k} tidak ditampilkan`).join(' · ');
      $('kr-ringkas').textContent = `${d.node.length} simpul · ${d.edge.length} relasi${potongTeks ? ' · ' + potongTeks : ''}`;
      $('kr-minggu').textContent = d.minggu ? `Status SAP: minggu mulai ${new Date(d.minggu + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}, seluruh BC.` : 'Status SAP belum tersedia (snapshot belum ada).';
      history.replaceState(null, '', `${URL_SELF}?${new URLSearchParams({ jenis, id })}`);
      if (pusat) tampilDetail(pusat.key);
      net.once('stabilizationIterationsDone', () => net.fit({ animation: { duration: 400 } }));
    } catch (e) {
      $('kr-ringkas').textContent = 'Gagal memuat: ' + e.message;
    } finally {
      $('kr-muat').hidden = true;
    }
  }

  function gambarJejak() {
    $('kr-jejak').innerHTML = jejak.map((x, i) => `<button type="button" data-i="${i}" class="${i === jejak.length - 1 ? 'kini' : ''}" title="${esc(x.label)}">${esc(potong(x.label, 28))}</button>`).join('<span aria-hidden="true">›</span>');
    $('kr-jejak').querySelectorAll('button').forEach((b) => b.addEventListener('click', () => { const x = jejak[+b.dataset.i]; muat(x.jenis, x.id, x.label); }));
  }

  function tampilDetail(key) {
    const n = G.node.find((x) => x.key === key);
    if (!n) return;
    const tetangga = G.edge.filter((e) => e.from === key || e.to === key).map((e) => (e.from === key ? e.to : e.from))
      .map((k) => G.node.find((x) => x.key === k)).filter(Boolean);
    const perJenis = {};
    tetangga.forEach((t) => (perJenis[t.jenis] ||= []).push(t));
    const st = STATUS[n.status];
    const tautan = n.jenis === 'grup' ? `<a class="kr-btn sek" href="${URL_GRUP}?gid=${encodeURIComponent(n.id)}">Edit di master grup</a>`
      : n.jenis === 'bahaya' ? `<a class="kr-btn sek" href="${URL_UBAH}&ubah=${encodeURIComponent(n.id)}">Ubah baris HIRA</a>` : '';
    $('kr-detail').innerHTML = `<h3>${esc(JENIS[n.jenis][0])}</h3><h4>${esc(n.label)}</h4><div class="sub">${esc(n.sub)}${st ? ` · <span class="kr-tag" style="background:${st[0]}22;color:${st[0]}">${st[1]}</span>` : ''}</div>
      <div class="kr-aksi">${n.pusat ? '' : `<button type="button" class="kr-btn" id="kr-fokus">Jadikan pusat</button>`}${tautan}</div>
      ${Object.entries(perJenis).map(([j, L]) => `<p class="pr-meta" style="margin:8px 0 4px"><b>${esc(JENIS[j][0])}</b> · ${L.length}</p><ul>${L.map((t) =>
        `<li><button type="button" data-key="${esc(t.key)}"><span class="kr-dot" style="--c:${JENIS[t.jenis][1]}"></span><span>${esc(t.label)}<small style="display:block;color:#8a8d90">${esc(t.sub)}</small></span></button></li>`).join('')}</ul>`).join('')
      || '<p class="pr-meta">Tidak ada simpul terhubung di graf ini.</p>'}`;
    const f = $('kr-fokus');
    if (f) f.addEventListener('click', () => muat(n.jenis, n.id, n.label));
    $('kr-detail').querySelectorAll('li button').forEach((b) => b.addEventListener('click', () => { net.selectNodes([b.dataset.key]); net.focus(b.dataset.key, { scale: 1.1, animation: true }); tampilDetail(b.dataset.key); }));
  }

  net.on('click', (p) => { if (p.nodes.length) tampilDetail(p.nodes[0]); });
  net.on('doubleClick', (p) => {
    if (!p.nodes.length) return;
    const n = G.node.find((x) => x.key === p.nodes[0]);
    if (n && !n.pusat) muat(n.jenis, n.id, n.label);
  });

  // Pencarian (tunda 250 ms).
  let t = 0;
  $('kr-cari').addEventListener('input', (e) => {
    clearTimeout(t);
    const q = e.target.value.trim();
    if (q.length < 2) { $('kr-hasil').innerHTML = ''; return; }
    t = setTimeout(async () => {
      try {
        const r = await fetch(`${URL_CARI}?${new URLSearchParams({ q })}`, { headers: { Accept: 'application/json' } });
        const d = await r.json();
        $('kr-hasil').innerHTML = (d.hasil || []).map((h, i) => `<button type="button" role="option" data-i="${i}"><span class="kr-dot" style="--c:${(JENIS[h.jenis] || ['', '#999'])[1]}"></span><span>${esc(potong(h.label, 70))}<small>${esc(h.sub)}</small></span></button>`).join('')
          || '<p class="pr-meta" style="margin:6px 4px">Tidak ditemukan.</p>';
        $('kr-hasil').querySelectorAll('button').forEach((b) => b.addEventListener('click', () => { const h = d.hasil[+b.dataset.i]; jejak = []; muat(h.jenis, h.id, h.label); }));
      } catch (err) { $('kr-hasil').innerHTML = '<p class="pr-meta" style="margin:6px 4px">Pencarian gagal.</p>'; }
    }, 250);
  });

  if (AWAL) muat(AWAL.jenis, String(AWAL.id));
})();
</script>
@endif
@endsection
