@extends('peta-risiko-pja._layout')

@section('title', $pja['nama'].' · Peta Risiko PJA')

@section('hero-aksi')
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <a class="pr-back" href="{{ route('peta-risiko-pja.index', ['periode' => $periode, 'site' => $pja['site']]) }}">← Semua PJA</a>
    <div class="pr-seg" role="group" aria-label="Periode">
      <button type="button" data-periode="wk">Minggu ini</button>
      <button type="button" data-periode="ytd">YTD</button>
    </div>
  </div>
@endsection

@section('hero')
  <p class="pr-kicker">PJA · {{ $pja['site'] }}</p>
  <h1 class="pr-title">{{ $pja['nama'] }}</h1>
  <p class="pr-sub" id="pr-periode"></p>
@endsection

@section('content')
<div class="pr-card">
  <div class="pr-kpis" id="pr-kpis"></div>
  <p class="pr-note" id="pr-ringkas"></p>
</div>

<div class="pr-card">
  <div class="pr-card-head">
    <div>
      <b>Rantai bisnis proses</b>
      <small>Proses yang dilalui lokasi PJA ini. Pilih proses untuk melihat tema bahayanya.</small>
    </div>
  </div>
  <div id="pr-chain"></div>
</div>

<div class="pr-split">
  <div class="pr-card" id="pr-proc"></div>
  <div class="pr-card" id="pr-aside"></div>
</div>

<div class="pr-foot">
  <p>Status satu tema diambil dari lokasi terburuknya. "Tanpa kontrol HIRA" = belum ada temuan SAP dan pengendalian untuk bahaya HIRA terkait tidak ditemukan di data pengendalian (10 bahaya teratas per proses); cek ulang di BeHIRA sebelum ditindaklanjuti.</p>
  @foreach ($meta['catatan'] as $c)
    <p>{{ $c }}</p>
  @endforeach
</div>

<div class="pr-scrim" data-tutup></div>
<aside class="pr-panel" role="dialog" aria-modal="true" aria-labelledby="pr-dr-title" aria-hidden="true">
  <div class="pr-panel-head">
    <p class="pr-kicker" id="pr-dr-eyebrow"></p>
    <h2 id="pr-dr-title"></h2>
    <button type="button" class="pr-x" data-tutup aria-label="Tutup">
      <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true"><path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    </button>
  </div>
  <div class="pr-panel-body" id="pr-dr-body"></div>
</aside>
@endsection

@section('scripts')
<script>
(function () {
  const META = {{ \Illuminate\Support\Js::from(['periode' => $meta['periode'], 'proses' => $meta['proses'], 'tema' => $meta['tema'], 'sbs' => $meta['sbs'], 'kolom' => $meta['kolom'], 'kolomSap' => $meta['kolomSap']]) }};
  const PJA = @json($pja);
  const { STATUS, fmt, esc } = PR;
  const URUT = { gap: 0, tindak: 1, fyi: 2, ok: 3 };
  const PENJELASAN = {
    'gap:open': 'Sudah dilaporkan di SAP, tapi masih ada temuan yang belum closed. Pastikan perbaikannya selesai.',
    'gap:berulang': 'Temuan yang sama muncul lagi di lokasi yang sama. Perbaikan sebelumnya belum menyentuh akar masalah.',
    'tindak:tanpa-kontrol': 'Belum pernah dilaporkan, dan pengendalian HIRA untuk bahaya terkait belum terlihat. Pastikan bahaya ini punya pengendalian.',
    'tindak:tanpa-padanan': 'Belum pernah dilaporkan, dan tidak ada bahaya HIRA yang cocok. Cek apakah HIRA sudah mencakup bahaya ini.',
    'fyi:': 'Belum pernah dilaporkan, tapi pengendalian HIRA sudah ada. Sesuai kesepakatan, bahaya ini tidak perlu dicari-cari; cukup pastikan pengendaliannya berjalan.',
    'ok:': 'Sudah dilaporkan di SAP dan terverifikasi. Tidak ada temuan yang open atau berulang.',
  };
  const TAHAP = [['pit', 'Tambang'], ['plant', 'Pengolahan'], ['marine', 'Pelabuhan'], ['sup', 'Pendukung']];
  const C = Object.fromEntries(META.kolom.map((k, i) => [k, i]));
  const CS = Object.fromEntries(META.kolomSap.map((k, i) => [k, i]));
  const PROSES = Object.fromEntries(META.proses.map((p) => [p.c, p]));
  const TEMA = Object.fromEntries(META.tema.map(([k, n]) => [k, n]));
  const SBS = Object.fromEntries(META.sbs.map((l) => [l.id, l]));
  const RN = ['–', 'Low', 'Medium', 'High', 'Significant'];
  const $ = (s) => document.querySelector(s);
  const lv = (l) => `<span class="pr-lv l${l}">${RN[l] || '–'}</span>`;
  const tgl = (s) => s ? new Date(s + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) : '–';
  const detailDari = (r) => String(r[C.detail] || '').split('|').map((d) => d.trim()).filter(Boolean);

  const state = { periode: @json($periode), proses: null };
  let model = {};
  let pemicu = null;

  function bangunModel() {
    const proses = {};
    (PJA.periode[state.periode]?.rows || []).forEach((r) => {
      const p = proses[r[C.proses]] ??= { kode: r[C.proses], tema: {}, lokasi: {} };
      const t = p.tema[r[C.tema]] ??= { kode: r[C.tema], status: 'ok', alasan: '', rows: [], res: 0, eff: 0, open: 0, bahaya: new Set(), lok: new Set() };
      t.rows.push(r);
      if (URUT[r[C.status]] < URUT[t.status] || (r[C.status] === t.status && r[C.alasan] === 'berulang')) { t.status = r[C.status]; t.alasan = r[C.alasan]; }
      t.res = Math.max(t.res, r[C.residual]);
      t.eff = Math.max(t.eff, r[C.efektif]);
      t.open += r[C.open];
      r[C.bahaya].forEach((b) => t.bahaya.add(b));
      t.lok.add(r[C.lokasi]);
      const set = (p.lokasi[r[C.klaster]] ??= {})[r[C.lokasi]] ??= new Set();
      detailDari(r).forEach((d) => set.add(d));
    });
    Object.values(proses).forEach((p) => {
      p.n = { gap: 0, tindak: 0, fyi: 0, ok: 0 };
      p.list = Object.values(p.tema).sort((a, b) => URUT[a.status] - URUT[b.status] || b.eff - a.eff || b.open - a.open);
      p.list.forEach((t) => p.n[t.status]++);
      p.aksi = p.n.gap + p.n.tindak;
      p.nLok = Object.values(p.lokasi).reduce((a, k) => a + Object.keys(k).length, 0);
    });
    model = proses;
    if (!model[state.proses]) state.proses = Object.values(model).sort((a, b) => b.aksi - a.aksi)[0]?.kode || null;
  }

  function renderKpi() {
    const ps = Object.values(model);
    const tot = { gap: 0, tindak: 0, fyi: 0, ok: 0 };
    ps.forEach((p) => PR.URUT.forEach((k) => { tot[k] += p.n[k]; }));
    $('#pr-kpis').innerHTML = PR.kpis(tot);
    const terberat = [...ps].sort((a, b) => b.aksi - a.aksi)[0];
    $('#pr-ringkas').textContent = ps.length
      ? `${ps.length} bisnis proses, ${fmt(PR.URUT.reduce((a, k) => a + tot[k], 0))} tema bahaya.` + (terberat?.aksi ? ` Paling banyak perlu tindakan di ${PROSES[terberat.kode]?.n || terberat.kode} (${terberat.aksi} tema).` : '')
      : 'Tidak ada data di periode ini.';
  }

  function renderChain() {
    $('#pr-chain').innerHTML = TAHAP.map(([g, label]) => {
      const list = META.proses.filter((p) => p.g === g && model[p.c]);
      if (!list.length) return '';
      return `<div class="pr-stage"><div class="pr-stage-name">${label}</div><div class="pr-procs">${list.map((p) => {
        const m = model[p.c];
        return `<button type="button" class="pr-proc" data-proses="${p.c}" aria-pressed="${state.proses === p.c}">
          <span class="pr-proc-name">${esc(p.n)}</span>
          ${PR.comp(m.n)}
          <span class="pr-proc-foot"><span>${m.nLok} lokasi · ${m.list.length} tema</span>${m.aksi ? `<span class="pr-tag s-gap">${m.aksi} tindakan</span>` : '<span class="pr-tag s-ok">aman</span>'}</span>
        </button>`;
      }).join('')}</div></div>`;
    }).join('') || '<div class="pr-empty">Tidak ada data di periode ini.</div>';
  }

  function renderPanel() {
    const p = model[state.proses];
    if (!p) { $('#pr-proc').innerHTML = '<div class="pr-empty">Tidak ada data di periode ini.</div>'; $('#pr-aside').innerHTML = ''; return; }
    const info = PROSES[p.kode] || { n: p.kode, d: '' };
    const grup = PR.URUT.filter((k) => p.n[k]).map((k) => `
      <tr class="pr-grp s-${k}"><td colspan="4"><i class="pr-dot"></i>${STATUS[k].label}<span>${p.n[k]} tema</span></td></tr>
      ${p.list.filter((t) => t.status === k).map((t) => `
        <tr data-tema="${t.kode}" tabindex="0">
          <td><span class="pr-name">${esc(TEMA[t.kode] || t.kode)}</span>
            <span class="pr-hz">${t.alasan === 'berulang' ? '<em>Berulang</em> · ' : ''}${t.bahaya.size ? esc([...t.bahaya].slice(0, 3).join(' · ')) : 'Tidak ada bahaya HIRA langsung (baseline proses)'}</span></td>
          <td class="hide-sm" style="white-space:nowrap">${lv(t.res)}<span class="pr-arrow">→</span>${lv(t.eff)}</td>
          <td class="r hide-sm">${t.lok.size}</td>
          <td class="r"><span class="pr-big" style="font-size:16px">${fmt(t.open)}</span></td>
        </tr>`).join('')}`).join('');
    $('#pr-proc').innerHTML = `<div class="pr-fade">
      <div class="pr-card-head"><div><b>${esc(info.n)}</b><small>${p.list.length} tema bahaya · ${p.aksi} perlu tindakan</small></div></div>
      <table class="pr-tbl"><thead><tr><th>Tema bahaya</th><th class="hide-sm">Residual → efektif</th><th class="r hide-sm">Lokasi</th><th class="r">SAP open</th></tr></thead>
      <tbody>${grup}</tbody></table></div>`;
    $('#pr-aside').innerHTML = `<div class="pr-fade">
      <div class="pr-card-head"><div><b>Lokasi di proses ini</b><small>${p.nLok} lokasi PJA</small></div></div>
      <div class="pr-card-body"><p class="pr-desc">${esc(info.d)}</p>
      ${Object.entries(p.lokasi).sort().map(([k, lok]) => `<p class="pr-loc-k">${esc(k)}</p>
        <ul class="pr-loc">${Object.entries(lok).sort().map(([l, d]) => `<li><span>${esc(l)}</span><span>${d.size} detail</span></li>`).join('')}</ul>`).join('')}
      </div></div>`;
  }

  function seksi(judul, info, isi) {
    return `<div class="pr-card" style="margin:0"><div class="pr-card-head"><div><b style="font-size:15px">${judul}</b></div><small style="margin:0">${info}</small></div>${isi}</div>`;
  }

  function bukaTema(kode, baris) {
    const p = model[state.proses];
    const t = p?.tema[kode];
    if (!t) return;
    pemicu = baris;
    $('#pr-dr-eyebrow').textContent = (PROSES[p.kode]?.n || p.kode);
    $('#pr-dr-title').textContent = TEMA[kode] || kode;

    const lokasi = [...t.rows].sort((a, b) => URUT[a[C.status]] - URUT[b[C.status]] || b[C.open] - a[C.open]);
    const lokHtml = lokasi.map((r) => {
      const d = detailDari(r);
      return `<div class="pr-entry">
        <div class="pr-entry-top"><strong>${esc(r[C.lokasi])}</strong>${PR.tag(r[C.status])}</div>
        <p>${fmt(r[C.temuan])} temuan di periode ini, ${fmt(r[C.open])} masih open${r[C.lewat] ? `, <b style="color:var(--red)">${r[C.lewat]} lewat janji</b>` : ''}.</p>
        <p class="pr-clamp" title="${esc(d.join(', '))}">${d.length} detail lokasi: ${esc(d.join(', '))}</p>
        ${r[C.pjaLain] ? `<p>Juga area PJA: ${esc(r[C.pjaLain])}</p>` : ''}
      </div>`;
    }).join('');

    const set = new Set([...t.bahaya].map((b) => b.toLowerCase()));
    const hira = (PJA.hira?.[p.kode] || []).filter((h) => set.has(h.b.toLowerCase()));
    const perLayer = {};
    hira.forEach((h) => h.c.forEach((c) => {
      const id = String(c.s || '').split('.')[0];
      const m = perLayer[SBS[id] ? id : ''] ??= new Map();
      if (!m.get(c.t) || m.get(c.t).n < c.n) m.set(c.t, c);
    }));
    const hzHtml = hira.map((h) => `<div class="pr-entry"><div class="pr-entry-top"><strong>${esc(h.b)}</strong><span style="white-space:nowrap">${lv(h.a)}<span class="pr-arrow">→</span>${lv(h.r)}</span></div><p>Potensi: ${esc(h.p)}</p></div>`).join('');
    const ctlHtml = [...META.sbs.map((l) => l.id), ''].filter((l) => perLayer[l]).map((l) => `
      <div class="pr-layer"><h4>${l ? 'Layer ' + l + ' · ' + esc(SBS[l].nama) : 'Tanpa kode layer'}</h4>
        <ul>${[...perLayer[l].values()].sort((a, b) => b.n - a.n).slice(0, 6).map((c) => `<li>${esc(c.t)} <small>${esc(c.tp)}${c.s ? ' · ' + esc(c.s) : ''}</small></li>`).join('')}</ul></div>`).join('');

    const open = (PJA.open || []).filter((o) => o[CS.tema] === kode && o[CS.proses].includes(p.kode))
      .sort((a, b) => b[CS.lewat] - a[CS.lewat] || b[CS.nilai] - a[CS.nilai] || String(b[CS.tanggal]).localeCompare(String(a[CS.tanggal])));
    const openItem = (o) => `<div class="pr-entry">
      <div class="pr-entry-top"><strong>${esc(o[CS.sub] || o[CS.ketidaksesuaian])}</strong>${o[CS.nilai] ? lv(o[CS.nilai]) : ''}</div>
      <p>${esc(o[CS.jenis])} #${esc(o[CS.id])} · ${tgl(o[CS.tanggal])} · ${esc(o[CS.detail] || o[CS.lokasi])}</p>
      <p>${esc(o[CS.status])} · janji selesai ${tgl(o[CS.janji])}${o[CS.lewat] ? ' · <b style="color:var(--red)">lewat janji</b>' : ''}</p>
    </div>`;
    const BATAS = 12;
    const kosong = (teks) => `<div class="pr-empty" style="padding:18px">${teks}</div>`;

    $('#pr-dr-body').innerHTML = `
      <div class="pr-verdict s-${t.status}"><b>${STATUS[t.status].label}${t.alasan === 'berulang' ? ' · berulang' : ''}.</b> ${PENJELASAN[t.status + ':' + t.alasan] || PENJELASAN[t.status + ':'] || ''}</div>
      ${seksi('Lokasi', lokasi.length + ' lokasi', lokHtml)}
      ${seksi('Bahaya & pengendalian HIRA', hira.length + ' bahaya terkait', hira.length ? hzHtml + ctlHtml + '<div style="height:12px"></div>' : kosong('Pengendalian untuk bahaya terkait belum ditemukan di data HIRA.'))}
      ${seksi('Temuan SAP masih open', fmt(open.length) + ' temuan', `<div id="pr-open-list">${open.length ? open.slice(0, BATAS).map(openItem).join('') : kosong('Tidak ada temuan open untuk tema ini.')}</div>
        ${open.length > BATAS ? `<button type="button" class="pr-more" id="pr-open-more">Tampilkan ${fmt(open.length - BATAS)} temuan lainnya</button>` : ''}`)}`;
    const more = document.getElementById('pr-open-more');
    if (more) more.addEventListener('click', () => { document.getElementById('pr-open-list').innerHTML = open.map(openItem).join(''); more.remove(); });

    $('#pr-dr-body').scrollTop = 0;
    document.body.classList.add('is-open');
    $('.pr-panel').setAttribute('aria-hidden', 'false');
    setTimeout(() => $('.pr-x').focus(), 60);
  }

  function tutup() {
    if (!document.body.classList.contains('is-open')) return;
    document.body.classList.remove('is-open');
    $('.pr-panel').setAttribute('aria-hidden', 'true');
    pemicu?.focus();
  }

  function render() {
    bangunModel();
    document.querySelectorAll('[data-periode]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.periode === state.periode));
    $('#pr-periode').textContent = META.periode[state.periode] + (META.periode.status ? ' · temuan open ' + META.periode.status : '');
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
    else if (el('[data-proses]')) {
      state.proses = el('[data-proses]').dataset.proses;
      document.querySelectorAll('[data-proses]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.proses === state.proses));
      renderPanel();
      if (window.matchMedia('(max-width: 1100px)').matches) {
        $('#pr-proc').scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
      }
    }
    else if (el('tr[data-tema]')) { bukaTema(el('tr[data-tema]').dataset.tema, el('tr[data-tema]')); }
    else if (el('[data-tutup]')) { tutup(); }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') tutup();
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches?.('tr[data-tema]')) { e.preventDefault(); bukaTema(e.target.dataset.tema, e.target); }
  });
  render();
})();
</script>
@endsection
