@extends('peta-risiko-pja._layout')

@section('title', $pja['nama'].' · Peta Risiko PJA')

@section('css')
  @include('peta-risiko-pja._style')
@endsection

@section('content')
<div class="pr" id="pr-show">
  <a class="pr-back" href="{{ route('peta-risiko-pja.index', ['periode' => $periode, 'site' => $pja['site']]) }}">‹ Semua PJA</a>
  <div class="pr-head">
    <div>
      <p class="pr-eyebrow">PJA · {{ $pja['site'] }}</p>
      <h1 class="pr-title">{{ $pja['nama'] }}</h1>
      <p class="pr-sub" id="pr-periode-label"></p>
    </div>
    <div class="pr-tools">
      <div class="pr-seg" role="group" aria-label="Periode">
        <button type="button" data-periode="wk">Minggu ini</button>
        <button type="button" data-periode="ytd">YTD</button>
      </div>
    </div>
  </div>

  <div class="pr-kpis" id="pr-kpis"></div>
  <p class="pr-note">Angka = jumlah tema bahaya di semua bisnis proses yang disentuh PJA ini. Status satu tema diambil dari lokasi terburuknya.</p>

  <div class="pr-card">
    <div class="pr-card-head">
      <div>
        <h2 class="pr-card-title">Rantai bisnis proses</h2>
        <p class="pr-card-sub">Proses yang dilalui lokasi PJA ini. Angka merah = tema yang perlu tindakan.</p>
      </div>
    </div>
    <div class="pr-chain" id="pr-chain"></div>
  </div>

  <div class="pr-panel">
    <div class="pr-card">
      <div class="pr-card-head">
        <div>
          <h2 class="pr-card-title" id="pr-proc-title"></h2>
          <p class="pr-card-sub">Tema bahaya HIRA di proses ini dan bukti pelaporannya di SAP.</p>
        </div>
        <div class="pr-chips" id="pr-chips"></div>
      </div>
      <div class="pr-tema pr-tema-head" aria-hidden="true">
        <span>Status</span><span>Tema bahaya</span><span>Residual → efektif</span><span>Lokasi</span><span>SAP open</span><span></span>
      </div>
      <div id="pr-tema-list"></div>
    </div>
    <div class="pr-card">
      <div class="pr-card-head"><h2 class="pr-card-title">Lokasi di proses ini</h2></div>
      <p class="pr-desc" id="pr-proc-desc"></p>
      <div class="pr-loc" id="pr-loc"></div>
    </div>
  </div>

  <ul class="pr-foot">
    @foreach ($meta['catatan'] as $c)
      <li>{{ $c }}</li>
    @endforeach
    <li>"Tanpa kontrol HIRA" = belum ada temuan SAP dan pengendalian untuk bahaya HIRA terkait tidak ditemukan di data pengendalian (10 bahaya teratas per proses). Perlu dicek ulang di BeHIRA sebelum ditindaklanjuti.</li>
  </ul>

  <div class="pr-drawer-bg" data-tutup></div>
  <aside class="pr-drawer" role="dialog" aria-modal="true" aria-labelledby="pr-dr-title" aria-hidden="true">
    <div class="pr-drawer-head">
      <div>
        <p class="pr-eyebrow" id="pr-dr-eyebrow"></p>
        <h2 id="pr-dr-title"></h2>
      </div>
      <button type="button" class="pr-close" data-tutup aria-label="Tutup">×</button>
    </div>
    <div class="pr-drawer-body" id="pr-dr-body"></div>
  </aside>
</div>
@endsection

@section('scripts')
<script>
(function () {
  const META = {{ \Illuminate\Support\Js::from(['periode' => $meta['periode'], 'proses' => $meta['proses'], 'tema' => $meta['tema'], 'sbs' => $meta['sbs'], 'kolom' => $meta['kolom'], 'kolomSap' => $meta['kolomSap']]) }};
  const PJA = @json($pja);
  const STATUS = {
    gap: { label: 'Ada gap', tag: 'tindak lanjut', desc: 'Dilaporkan, tapi masih open atau berulang' },
    tindak: { label: 'Tanpa kontrol HIRA', tag: 'kendalikan', desc: 'Belum dilaporkan dan pengendalian HIRA belum terlihat' },
    fyi: { label: 'Belum dilaporkan', tag: 'info', desc: 'Pengendalian HIRA sudah ada, tidak perlu dicari-cari' },
    ok: { label: 'Terkontrol', tag: 'aman', desc: 'Dilaporkan dan sudah terverifikasi' },
  };
  const URUT = { gap: 0, tindak: 1, fyi: 2, ok: 3 };
  const PENJELASAN = {
    'gap:open': 'Bahaya ini <b>sudah dilaporkan di SAP</b>, tetapi masih ada temuan yang belum closed. Pastikan tindakan perbaikannya selesai.',
    'gap:berulang': 'Temuan yang sama <b>berulang</b> di lokasi yang sama. Perbaikannya belum menyelesaikan akar masalah.',
    'tindak:tanpa-kontrol': 'Belum pernah dilaporkan, dan <b>pengendalian HIRA untuk bahaya terkait belum terlihat</b>. Pastikan bahaya ini punya pengendalian.',
    'tindak:tanpa-padanan': 'Belum pernah dilaporkan, dan <b>tidak ada bahaya HIRA yang cocok</b>. Perlu dicek apakah HIRA sudah mencakup bahaya ini.',
    'fyi:': 'Belum pernah dilaporkan, tetapi <b>pengendalian HIRA sudah ada</b>. Sesuai kesepakatan, bahaya ini tidak perlu dicari-cari; cukup pastikan pengendaliannya berjalan.',
    'ok:': 'Sudah dilaporkan di SAP dan <b>terverifikasi</b>, tidak ada temuan yang open atau berulang.',
  };
  const TAHAP = [['pit', 'Tambang'], ['plant', 'Pengolahan'], ['marine', 'Pelabuhan & pengapalan'], ['sup', 'Proses pendukung']];
  const C = Object.fromEntries(META.kolom.map((k, i) => [k, i]));
  const CS = Object.fromEntries(META.kolomSap.map((k, i) => [k, i]));
  const PROSES = Object.fromEntries(META.proses.map((p) => [p.c, p]));
  const TEMA = Object.fromEntries(META.tema.map(([k, n]) => [k, n]));
  const SBS = Object.fromEntries(META.sbs.map((l) => [l.id, l]));
  const RN = ['–', 'Low', 'Medium', 'High', 'Significant'];
  const $ = (s) => document.querySelector(s);
  const fmt = (n) => Number(n || 0).toLocaleString('id-ID');
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const lv = (l) => `<span class="pr-lv l${l}">${RN[l] || '–'}</span>`;
  const tgl = (s) => s ? new Date(s + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }) : '–';

  const state = { periode: @json($periode), proses: null, filter: '' };
  let model = {};

  const detailDari = (r) => String(r[C.detail] || '').split('|').map((d) => d.trim()).filter(Boolean);

  function bangunModel() {
    const rows = PJA.periode[state.periode]?.rows || [];
    const proses = {};
    rows.forEach((r) => {
      const p = proses[r[C.proses]] ??= { kode: r[C.proses], tema: {}, lokasi: {} };
      const t = p.tema[r[C.tema]] ??= { kode: r[C.tema], status: 'ok', alasan: '', rows: [], res: 0, eff: 0, open: 0, temuan: 0, bahaya: new Set(), lok: new Set() };
      t.rows.push(r);
      if (URUT[r[C.status]] < URUT[t.status] || (r[C.status] === t.status && r[C.alasan] === 'berulang')) { t.status = r[C.status]; t.alasan = r[C.alasan]; }
      t.res = Math.max(t.res, r[C.residual]);
      t.eff = Math.max(t.eff, r[C.efektif]);
      t.open += r[C.open];
      t.temuan += r[C.temuan];
      r[C.bahaya].forEach((b) => t.bahaya.add(b));
      t.lok.add(r[C.lokasi]);
      const k = p.lokasi[r[C.klaster]] ??= {};
      const set = k[r[C.lokasi]] ??= new Set();
      detailDari(r).forEach((d) => set.add(d));
    });
    Object.values(proses).forEach((p) => {
      p.n = { gap: 0, tindak: 0, fyi: 0, ok: 0 };
      p.list = Object.values(p.tema).sort((a, b) => URUT[a.status] - URUT[b.status] || b.eff - a.eff || b.open - a.open);
      p.list.forEach((t) => p.n[t.status]++);
      p.aksi = p.n.gap + p.n.tindak;
    });
    model = proses;
    if (!model[state.proses]) {
      state.proses = Object.values(model).sort((a, b) => b.aksi - a.aksi)[0]?.kode || null;
    }
  }

  function renderKpi() {
    const tot = { gap: 0, tindak: 0, fyi: 0, ok: 0 };
    Object.values(model).forEach((p) => Object.keys(tot).forEach((k) => { tot[k] += p.n[k]; }));
    $('#pr-kpis').innerHTML = Object.entries(STATUS).map(([k, s]) => `
      <div class="pr-kpi s-${k}" style="cursor:default">
        <div class="pr-kpi-top"><span class="pr-dot"></span>${s.label}<span class="pr-tag">${s.tag}</span></div>
        <div class="pr-kpi-num">${fmt(tot[k])}</div>
        <div class="pr-kpi-desc">${s.desc}</div>
      </div>`).join('');
  }

  function miniBar(n) {
    const t = n.gap + n.tindak + n.fyi + n.ok || 1;
    return `<div class="pr-bar">${Object.keys(STATUS).map((k) => n[k] ? `<i class="s-${k}" style="width:${(100 * n[k] / t).toFixed(1)}%"></i>` : '').join('')}</div>`;
  }

  function renderChain() {
    const html = TAHAP.map(([g, label]) => {
      const list = META.proses.filter((p) => p.g === g && model[p.c]);
      if (!list.length) return '';
      const sep = g === 'sup' ? '' : '<span class="pr-arrow" aria-hidden="true">→</span>';
      return `<div class="pr-stage"><div class="pr-stage-label">${label}</div><div class="pr-flow">${list.map((p) => {
        const m = model[p.c];
        const nLok = Object.values(m.lokasi).reduce((a, k) => a + Object.keys(k).length, 0);
        return `<button type="button" class="pr-proc" data-proses="${p.c}" aria-pressed="${state.proses === p.c}">
          <span class="pr-proc-name">${esc(p.n)}</span>
          ${miniBar(m.n)}
          <span class="pr-proc-meta"><span>${nLok} lokasi · ${m.list.length} tema</span><b class="${m.aksi ? '' : 'nol'}">${m.aksi ? m.aksi + ' tindakan' : 'aman'}</b></span>
        </button>`;
      }).join(sep)}</div></div>`;
    }).join('');
    $('#pr-chain').innerHTML = html || '<div class="pr-empty">Tidak ada data di periode ini.</div>';
  }

  function renderPanel() {
    const p = model[state.proses];
    if (!p) {
      $('#pr-proc-title').textContent = 'Tidak ada proses';
      $('#pr-tema-list').innerHTML = '<div class="pr-empty">Tidak ada data di periode ini.</div>';
      $('#pr-chips').innerHTML = '';
      $('#pr-loc').innerHTML = '';
      $('#pr-proc-desc').textContent = '';
      return;
    }
    const info = PROSES[p.kode] || { n: p.kode, d: '' };
    $('#pr-proc-title').textContent = info.n;
    $('#pr-proc-desc').textContent = info.d;

    const chips = [['', 'Semua', p.list.length], ...Object.keys(STATUS).map((k) => [k, STATUS[k].label, p.n[k]])];
    $('#pr-chips').innerHTML = chips.map(([k, l, n]) => `<button type="button" class="pr-chip ${k ? 's-' + k : ''}" data-filter="${k}" aria-pressed="${state.filter === k}">${k ? '<i class="pr-dot"></i>' : ''}${l} <span>${n}</span></button>`).join('');

    const list = p.list.filter((t) => !state.filter || t.status === state.filter);
    $('#pr-tema-list').innerHTML = list.length ? list.map((t) => `
      <button type="button" class="pr-tema" data-tema="${t.kode}">
        <span class="pr-status s-${t.status}"><i class="pr-dot"></i>${STATUS[t.status].label}${t.alasan === 'berulang' ? ' · berulang' : ''}</span>
        <span style="min-width:0"><span class="pr-tema-name">${esc(TEMA[t.kode] || t.kode)}</span><span class="pr-tema-hz">${t.bahaya.size ? esc([...t.bahaya].slice(0, 3).join(' · ')) : 'Tidak ada bahaya HIRA langsung (baseline proses)'}</span></span>
        <span class="pr-risk">${lv(t.res)} <span aria-hidden="true">→</span> ${lv(t.eff)}</span>
        <span class="pr-num">${t.lok.size}</span>
        <span class="pr-num">${fmt(t.open)}</span>
        <span class="pr-chev" aria-hidden="true">›</span>
      </button>`).join('') : '<div class="pr-empty">Tidak ada tema dengan status ini.</div>';

    $('#pr-loc').innerHTML = Object.entries(p.lokasi).sort().map(([k, lok]) => `
      <div class="pr-loc-k">${esc(k)}</div>
      ${Object.entries(lok).sort().map(([l, d]) => `<div class="pr-loc-i"><span>${esc(l)}</span><span>${d.size} detail</span></div>`).join('')}`).join('');
  }

  function hiraUntuk(proses, bahaya) {
    const daftar = PJA.hira?.[proses] || [];
    const set = new Set([...bahaya].map((b) => b.toLowerCase()));
    return daftar.filter((h) => set.has(h.b.toLowerCase()));
  }

  function layerDari(kode) {
    const id = String(kode || '').split('.')[0];
    return SBS[id] ? id : '';
  }

  function bukaTema(kode) {
    const p = model[state.proses];
    const t = p?.tema[kode];
    if (!t) return;
    const s = STATUS[t.status];
    $('#pr-dr-eyebrow').textContent = (PROSES[p.kode]?.n || p.kode) + ' · ' + META.periode[state.periode];
    $('#pr-dr-title').textContent = TEMA[kode] || kode;

    const lokasi = [...t.rows].sort((a, b) => URUT[a[C.status]] - URUT[b[C.status]] || b[C.open] - a[C.open]);
    const lokHtml = lokasi.map((r) => `
      <div class="pr-li">
        <div class="pr-li-top"><strong>${esc(r[C.lokasi])}</strong><span class="pr-status s-${r[C.status]}"><i class="pr-dot"></i>${STATUS[r[C.status]].label}</span></div>
        <div class="pr-li-sub">${fmt(r[C.temuan])} temuan periode · ${fmt(r[C.open])} open${r[C.lewat] ? ` · <span class="pr-late">${r[C.lewat]} lewat janji</span>` : ''}</div>
        <div class="pr-li-sub pr-clamp" title="${esc(detailDari(r).join(', '))}">${detailDari(r).length} detail: ${esc(detailDari(r).join(', '))}</div>
        ${r[C.pjaLain] ? `<div class="pr-li-sub">Juga di PJA: ${esc(r[C.pjaLain])}</div>` : ''}
      </div>`).join('');

    const hira = hiraUntuk(p.kode, t.bahaya);
    const perLayer = {};
    hira.forEach((h) => h.c.forEach((c) => {
      const l = layerDari(c.s);
      const m = perLayer[l] ??= new Map();
      const prev = m.get(c.t);
      if (!prev || prev.n < c.n) m.set(c.t, c);
    }));
    const urutLayer = [...META.sbs.map((l) => l.id), ''].filter((l) => perLayer[l]);
    const hzHtml = hira.length ? hira.map((h) => `<div class="pr-hz">${esc(h.b)}<small>Potensi: ${esc(h.p)} · risiko awal ${lv(h.a)} → residual ${lv(h.r)}</small></div>`).join('') : '';
    const ctlHtml = urutLayer.map((l) => `
      <div class="pr-layer">${l ? 'Layer ' + l + ' · ' + esc(SBS[l].nama) : 'Tanpa kode layer'}</div>
      ${[...perLayer[l].values()].sort((a, b) => b.n - a.n).slice(0, 6).map((c) => `<div class="pr-ctl"><span>${esc(c.t)} <small>${esc(c.tp)}${c.s ? ' · ' + esc(c.s) : ''}</small></span></div>`).join('')}`).join('');

    const open = (PJA.open || []).filter((o) => o[CS.tema] === kode && o[CS.proses].includes(p.kode))
      .sort((a, b) => b[CS.lewat] - a[CS.lewat] || b[CS.nilai] - a[CS.nilai] || String(b[CS.tanggal]).localeCompare(String(a[CS.tanggal])));
    const openItem = (o) => `
      <div class="pr-li">
        <div class="pr-li-top"><strong>${esc(o[CS.sub] || o[CS.ketidaksesuaian])}</strong>${o[CS.nilai] ? lv(o[CS.nilai]) : ''}</div>
        <div class="pr-li-sub">#${esc(o[CS.id])} · ${esc(o[CS.jenis])} · ${tgl(o[CS.tanggal])} · ${esc(o[CS.detail] || o[CS.lokasi])}</div>
        <div class="pr-li-sub">${esc(o[CS.status])} · janji ${tgl(o[CS.janji])}${o[CS.lewat] ? ' <span class="pr-late">lewat janji</span>' : ''}</div>
      </div>`;
    const BATAS = 15;

    $('#pr-dr-body').innerHTML = `
      <div class="pr-explain s-${t.status}"><b>${s.label}.</b> ${PENJELASAN[t.status + ':' + t.alasan] || PENJELASAN[t.status + ':'] || ''}</div>
      <section class="pr-sec"><h3>Di lokasi mana <span>${lokasi.length} lokasi</span></h3>${lokHtml}</section>
      <section class="pr-sec"><h3>Pengendalian HIRA per Layer SBS <span>${hira.length} bahaya terkait</span></h3>
        ${hira.length ? hzHtml + ctlHtml : '<div class="pr-li pr-li-sub">Pengendalian untuk bahaya terkait belum ditemukan di data HIRA.</div>'}
        <div style="height:8px"></div>
      </section>
      <section class="pr-sec"><h3>Temuan SAP yang masih open <span>${fmt(open.length)}</span></h3>
        <div id="pr-open-list">${open.length ? open.slice(0, BATAS).map(openItem).join('') : '<div class="pr-li pr-li-sub">Tidak ada temuan open untuk tema ini.</div>'}</div>
        ${open.length > BATAS ? `<button type="button" class="pr-more" id="pr-open-more">Tampilkan semua ${fmt(open.length)} temuan</button>` : ''}
      </section>`;
    const more = document.getElementById('pr-open-more');
    if (more) more.addEventListener('click', () => { document.getElementById('pr-open-list').innerHTML = open.map(openItem).join(''); more.remove(); });

    document.body.classList.add('pr-open');
    $('.pr-drawer').setAttribute('aria-hidden', 'false');
    $('.pr-close').focus();
  }

  function tutup() {
    document.body.classList.remove('pr-open');
    $('.pr-drawer').setAttribute('aria-hidden', 'true');
  }

  function render() {
    bangunModel();
    document.querySelectorAll('[data-periode]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.periode === state.periode));
    $('#pr-periode-label').textContent = META.periode[state.periode] + (META.periode.status ? ' · temuan open ' + META.periode.status : '');
    renderKpi();
    renderChain();
    renderPanel();
    const u = new URL(location.href);
    u.searchParams.set('periode', state.periode);
    history.replaceState(null, '', u);
  }

  document.addEventListener('click', (e) => {
    const el = (s) => e.target.closest(s);
    if (el('[data-periode]')) { state.periode = el('[data-periode]').dataset.periode; render(); }
    else if (el('[data-proses]')) { state.proses = el('[data-proses]').dataset.proses; state.filter = ''; renderChain(); renderPanel(); }
    else if (el('[data-filter]')) { state.filter = el('[data-filter]').dataset.filter; renderPanel(); }
    else if (el('[data-tema]')) { bukaTema(el('[data-tema]').dataset.tema); }
    else if (el('[data-tutup]')) { tutup(); }
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') tutup(); });
  render();
})();
</script>
@endsection
