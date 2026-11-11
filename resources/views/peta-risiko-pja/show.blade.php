@extends('peta-risiko-pja._layout')

@section('title', $pja['nama'].' · Peta Risiko PJA')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">{{ $pja['nama'] }}</h6>
    <p class="text-sm text-secondary-light mb-0 mt-4"><span class="fw-medium">{{ $pja['site'] }}</span> · <span id="pr-periode-label"></span></p>
  </div>
  <ul class="d-flex align-items-center gap-2 mb-0 list-unstyled">
    <li class="fw-medium">
      <a href="{{ route('peta-risiko-pja.index', ['periode' => $periode, 'site' => $pja['site']]) }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <i class="ri-map-pin-2-line text-lg"></i> Peta Risiko PJA
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">{{ $pja['nama'] }}</li>
  </ul>
</div>

<div class="row gy-4 mb-24" id="pr-kpis"></div>

<div class="card radius-8 border-0 mb-24">
  <div class="card-body p-24">
    <div class="d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="mb-0 fw-bold text-lg">Rantai bisnis proses</h6>
        <p class="text-sm text-secondary-light mb-0">Proses yang dilalui lokasi PJA ini. Pilih proses untuk melihat tema bahayanya.</p>
      </div>
      <select class="form-select form-select-sm w-auto bg-base border text-secondary-light" id="pr-periode" aria-label="Periode">
        <option value="wk">Minggu ini</option>
        <option value="ytd">YTD</option>
      </select>
    </div>
    <div id="pr-chain"></div>
  </div>
</div>

<div class="row gy-4">
  <div class="col-xxl-9">
    <div class="card h-100 radius-8 border-0">
      <div class="card-body p-24">
        <div class="d-flex align-items-center flex-wrap gap-3 justify-content-between mb-20">
          <div>
            <h6 class="mb-0 fw-bold text-lg" id="pr-proc-title"></h6>
            <p class="text-sm text-secondary-light mb-0">Tema bahaya HIRA di proses ini dan bukti pelaporannya di SAP.</p>
          </div>
          <div class="d-flex flex-wrap gap-2" id="pr-chips" role="group" aria-label="Filter status"></div>
        </div>
        <div class="table-responsive scroll-sm">
          <table class="table bordered-table mb-0">
            <thead>
              <tr>
                <th scope="col">Tema bahaya</th>
                <th scope="col">Status</th>
                <th scope="col">Residual → efektif</th>
                <th scope="col" class="text-center">Lokasi</th>
                <th scope="col" class="text-center">SAP open</th>
              </tr>
            </thead>
            <tbody id="pr-tema-list"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xxl-3">
    <div class="card h-100 radius-8 border-0">
      <div class="card-body p-24">
        <h6 class="mb-8 fw-bold text-lg">Lokasi di proses ini</h6>
        <p class="text-sm text-secondary-light mb-0" id="pr-proc-desc"></p>
        <div id="pr-loc"></div>
      </div>
    </div>
  </div>
</div>

<div class="text-sm text-secondary-light mt-20">
  <p class="mb-4">Angka = jumlah tema bahaya di semua bisnis proses yang disentuh PJA ini. Status satu tema diambil dari lokasi terburuknya.</p>
  @foreach ($meta['catatan'] as $c)
    <p class="mb-4">{{ $c }}</p>
  @endforeach
  <p class="mb-0">"Tanpa kontrol HIRA" = belum ada temuan SAP dan pengendalian untuk bahaya HIRA terkait tidak ditemukan di data pengendalian (10 bahaya teratas per proses). Cek ulang di BeHIRA sebelum ditindaklanjuti.</p>
</div>

<div class="offcanvas offcanvas-end pr-offcanvas" tabindex="-1" id="pr-detail" aria-labelledby="pr-dr-title">
  <div class="offcanvas-header border-bottom px-24 py-20 align-items-start">
    <div>
      <p class="text-sm text-secondary-light mb-4" id="pr-dr-eyebrow"></p>
      <h6 class="offcanvas-title fw-semibold mb-0" id="pr-dr-title"></h6>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
  </div>
  <div class="offcanvas-body p-24 bg-neutral-50" id="pr-dr-body"></div>
</div>
@endsection

@section('scripts')
@include('peta-risiko-pja._status')
<script>
(function () {
  const META = {{ \Illuminate\Support\Js::from(['periode' => $meta['periode'], 'proses' => $meta['proses'], 'tema' => $meta['tema'], 'sbs' => $meta['sbs'], 'kolom' => $meta['kolom'], 'kolomSap' => $meta['kolomSap']]) }};
  const PJA = @json($pja);
  const STATUS = window.PR_STATUS;
  const { fmt, esc } = window.PR_UTIL;
  const URUT = { gap: 0, tindak: 1, fyi: 2, ok: 3 };
  const PENJELASAN = {
    'gap:open': 'Bahaya ini <b>sudah dilaporkan di SAP</b>, tetapi masih ada temuan yang belum closed. Pastikan tindakan perbaikannya selesai.',
    'gap:berulang': 'Temuan yang sama <b>berulang</b> di lokasi yang sama. Perbaikannya belum menyelesaikan akar masalah.',
    'tindak:tanpa-kontrol': 'Belum pernah dilaporkan, dan <b>pengendalian HIRA untuk bahaya terkait belum terlihat</b>. Pastikan bahaya ini punya pengendalian.',
    'tindak:tanpa-padanan': 'Belum pernah dilaporkan, dan <b>tidak ada bahaya HIRA yang cocok</b>. Cek apakah HIRA sudah mencakup bahaya ini.',
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
  const lv = (l) => `<span class="pr-lv l${l}">${RN[l] || '–'}</span>`;
  const tgl = (s) => s ? new Date(s + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }) : '–';
  const detailDari = (r) => String(r[C.detail] || '').split('|').map((d) => d.trim()).filter(Boolean);
  const offcanvas = new bootstrap.Offcanvas('#pr-detail');

  const state = { periode: @json($periode), proses: null, filter: '' };
  let model = {};

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
    });
    model = proses;
    if (!model[state.proses]) state.proses = Object.values(model).sort((a, b) => b.aksi - a.aksi)[0]?.kode || null;
  }

  function renderKpi() {
    const tot = { gap: 0, tindak: 0, fyi: 0, ok: 0 };
    Object.values(model).forEach((p) => Object.keys(tot).forEach((k) => { tot[k] += p.n[k]; }));
    $('#pr-kpis').innerHTML = Object.keys(tot).map((k) => window.PR_UTIL.kartu(k, tot[k])).join('');
  }

  function renderChain() {
    const html = TAHAP.map(([g, label]) => {
      const list = META.proses.filter((p) => p.g === g && model[p.c]);
      if (!list.length) return '';
      return `<p class="text-sm fw-semibold text-secondary-light text-uppercase mb-8 mt-20">${label}</p>
        <div class="row g-3">${list.map((p) => {
          const m = model[p.c];
          const nLok = Object.values(m.lokasi).reduce((a, k) => a + Object.keys(k).length, 0);
          const on = state.proses === p.c;
          return `<div class="col-xxl-2 col-xl-3 col-md-4 col-sm-6">
            <button type="button" class="pr-proc h-100 bg-base border radius-8 p-16 ${on ? 'active' : ''}" data-proses="${p.c}" aria-pressed="${on}">
              <span class="d-block fw-semibold text-primary-light text-md mb-12">${esc(p.n)}</span>
              ${window.PR_UTIL.stack(m.n)}
              <span class="d-flex justify-content-between align-items-center mt-12 gap-2 flex-wrap">
                <span class="text-sm text-secondary-light">${nLok} lokasi · ${m.list.length} tema</span>
                ${m.aksi ? `<span class="bg-danger-focus text-danger-main px-8 py-2 rounded-pill fw-semibold text-xs">${m.aksi} tindakan</span>` : '<span class="bg-success-focus text-success-main px-8 py-2 rounded-pill fw-semibold text-xs">aman</span>'}
              </span>
            </button>
          </div>`;
        }).join('')}</div>`;
    }).join('');
    $('#pr-chain').innerHTML = html || '<p class="text-secondary-light mb-0 mt-20">Tidak ada data di periode ini.</p>';
  }

  function renderPanel() {
    const p = model[state.proses];
    if (!p) {
      $('#pr-proc-title').textContent = 'Tidak ada proses';
      $('#pr-tema-list').innerHTML = '<tr><td colspan="5" class="text-center text-secondary-light py-24">Tidak ada data di periode ini.</td></tr>';
      $('#pr-chips').innerHTML = '';
      $('#pr-loc').innerHTML = '';
      $('#pr-proc-desc').textContent = '';
      return;
    }
    const info = PROSES[p.kode] || { n: p.kode, d: '' };
    $('#pr-proc-title').textContent = info.n;
    $('#pr-proc-desc').textContent = info.d;

    const chips = [['', 'Semua', p.list.length], ...Object.keys(STATUS).map((k) => [k, STATUS[k].label, p.n[k]])];
    $('#pr-chips').innerHTML = chips.map(([k, l, n]) => {
      const on = state.filter === k;
      return `<button type="button" class="btn btn-sm radius-8 px-12 py-4 text-sm fw-medium ${on ? 'btn-primary-600' : 'border text-secondary-light bg-base'}" data-filter="${k}" aria-pressed="${on}">${l} <span class="fw-semibold ms-1">${n}</span></button>`;
    }).join('');

    const list = p.list.filter((t) => !state.filter || t.status === state.filter);
    $('#pr-tema-list').innerHTML = list.length ? list.map((t) => `
      <tr class="pr-row" data-tema="${t.kode}">
        <td><span class="text-md text-primary-light fw-semibold d-block">${esc(TEMA[t.kode] || t.kode)}</span>
          <span class="text-sm text-secondary-light d-block pr-hz text-truncate">${t.bahaya.size ? esc([...t.bahaya].slice(0, 3).join(' · ')) : 'Tidak ada bahaya HIRA langsung (baseline proses)'}</span></td>
        <td>${window.PR_UTIL.badge(t.status, t.alasan === 'berulang' ? ' · berulang' : '')}</td>
        <td class="text-nowrap">${lv(t.res)} <i class="ri-arrow-right-line text-secondary-light"></i> ${lv(t.eff)}</td>
        <td class="text-center">${t.lok.size}</td>
        <td class="text-center">${fmt(t.open)}</td>
      </tr>`).join('') : '<tr><td colspan="5" class="text-center text-secondary-light py-24">Tidak ada tema dengan status ini.</td></tr>';

    $('#pr-loc').innerHTML = Object.entries(p.lokasi).sort().map(([k, lok]) => `
      <p class="text-xs fw-semibold text-secondary-light text-uppercase mb-4 mt-20">${esc(k)}</p>
      <ul class="list-unstyled mb-0">${Object.entries(lok).sort().map(([l, d]) => `
        <li class="d-flex justify-content-between gap-2 py-8 border-bottom"><span class="text-sm text-primary-light">${esc(l)}</span><span class="text-sm text-secondary-light text-nowrap">${d.size} detail</span></li>`).join('')}</ul>`).join('');
  }

  function seksi(judul, info, isi) {
    return `<div class="card radius-8 border-0 mb-16"><div class="card-body p-0">
      <div class="d-flex justify-content-between align-items-center px-20 py-16 border-bottom"><h6 class="text-md fw-semibold mb-0">${judul}</h6><span class="text-sm text-secondary-light">${info}</span></div>
      ${isi}</div></div>`;
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
      <div class="px-20 py-12 border-bottom">
        <div class="d-flex justify-content-between align-items-start gap-2"><span class="fw-semibold text-primary-light">${esc(r[C.lokasi])}</span>${window.PR_UTIL.badge(r[C.status])}</div>
        <p class="text-sm text-secondary-light mb-0 mt-4">${fmt(r[C.temuan])} temuan periode · ${fmt(r[C.open])} open${r[C.lewat] ? ` · <span class="text-danger-main fw-medium">${r[C.lewat]} lewat janji</span>` : ''}</p>
        <p class="text-sm text-secondary-light mb-0 pr-clamp" title="${esc(detailDari(r).join(', '))}">${detailDari(r).length} detail: ${esc(detailDari(r).join(', '))}</p>
        ${r[C.pjaLain] ? `<p class="text-xs text-secondary-light mb-0 mt-4">Juga di PJA: ${esc(r[C.pjaLain])}</p>` : ''}
      </div>`).join('');

    const set = new Set([...t.bahaya].map((b) => b.toLowerCase()));
    const hira = (PJA.hira?.[p.kode] || []).filter((h) => set.has(h.b.toLowerCase()));
    const perLayer = {};
    hira.forEach((h) => h.c.forEach((c) => {
      const id = String(c.s || '').split('.')[0];
      const m = perLayer[SBS[id] ? id : ''] ??= new Map();
      if (!m.get(c.t) || m.get(c.t).n < c.n) m.set(c.t, c);
    }));
    const hzHtml = hira.map((h) => `<div class="px-20 pt-12"><span class="fw-semibold text-primary-light d-block">${esc(h.b)}</span>
      <span class="text-sm text-secondary-light">Potensi: ${esc(h.p)} · awal ${lv(h.a)} → residual ${lv(h.r)}</span></div>`).join('');
    const ctlHtml = [...META.sbs.map((l) => l.id), ''].filter((l) => perLayer[l]).map((l) => `
      <div class="px-20 pt-16"><span class="bg-primary-50 text-primary-600 px-8 py-2 radius-4 fw-semibold text-xs">${l ? 'Layer ' + l + ' · ' + esc(SBS[l].nama) : 'Tanpa kode layer'}</span></div>
      <ul class="pe-20 pt-8 mb-0" style="padding-left:38px">${[...perLayer[l].values()].sort((a, b) => b.n - a.n).slice(0, 6).map((c) => `<li class="text-sm text-primary-light mb-4" style="list-style:disc">${esc(c.t)} <span class="text-xs text-secondary-light">${esc(c.tp)}${c.s ? ' · ' + esc(c.s) : ''}</span></li>`).join('')}</ul>`).join('');

    const open = (PJA.open || []).filter((o) => o[CS.tema] === kode && o[CS.proses].includes(p.kode))
      .sort((a, b) => b[CS.lewat] - a[CS.lewat] || b[CS.nilai] - a[CS.nilai] || String(b[CS.tanggal]).localeCompare(String(a[CS.tanggal])));
    const openItem = (o) => `
      <div class="px-20 py-12 border-bottom">
        <div class="d-flex justify-content-between align-items-start gap-2"><span class="fw-semibold text-primary-light">${esc(o[CS.sub] || o[CS.ketidaksesuaian])}</span>${o[CS.nilai] ? lv(o[CS.nilai]) : ''}</div>
        <p class="text-sm text-secondary-light mb-0 mt-4">#${esc(o[CS.id])} · ${esc(o[CS.jenis])} · ${tgl(o[CS.tanggal])} · ${esc(o[CS.detail] || o[CS.lokasi])}</p>
        <p class="text-sm text-secondary-light mb-0">${esc(o[CS.status])} · janji ${tgl(o[CS.janji])}${o[CS.lewat] ? ' · <span class="text-danger-main fw-medium">lewat janji</span>' : ''}</p>
      </div>`;
    const BATAS = 15;
    const kosong = (teks) => `<p class="text-sm text-secondary-light px-20 py-16 mb-0">${teks}</p>`;

    $('#pr-dr-body').innerHTML = `
      <div class="${s.soft} radius-8 p-16 mb-16 d-flex gap-12"><i class="${s.icon} ${s.text} text-xl"></i><p class="text-sm text-primary-light mb-0"><b class="${s.text}">${s.label}.</b> ${PENJELASAN[t.status + ':' + t.alasan] || PENJELASAN[t.status + ':'] || ''}</p></div>
      ${seksi('Di lokasi mana', lokasi.length + ' lokasi', lokHtml)}
      ${seksi('Pengendalian HIRA per Layer SBS', hira.length + ' bahaya terkait', hira.length ? hzHtml + ctlHtml + '<div class="pb-16"></div>' : kosong('Pengendalian untuk bahaya terkait belum ditemukan di data HIRA.'))}
      ${seksi('Temuan SAP yang masih open', fmt(open.length), `<div id="pr-open-list">${open.length ? open.slice(0, BATAS).map(openItem).join('') : kosong('Tidak ada temuan open untuk tema ini.')}</div>
        ${open.length > BATAS ? `<button type="button" class="btn btn-link text-primary-600 fw-medium w-100 py-12" id="pr-open-more">Tampilkan semua ${fmt(open.length)} temuan</button>` : ''}`)}`;
    const more = document.getElementById('pr-open-more');
    if (more) {
      more.addEventListener('click', () => {
        document.getElementById('pr-open-list').innerHTML = open.map(openItem).join('');
        more.remove();
      });
    }
    offcanvas.show();
  }

  function render() {
    bangunModel();
    $('#pr-periode').value = state.periode;
    $('#pr-periode-label').textContent = META.periode[state.periode] + (META.periode.status ? ' · temuan open ' + META.periode.status : '');
    renderKpi();
    renderChain();
    renderPanel();
    const u = new URL(location.href);
    u.searchParams.set('periode', state.periode);
    history.replaceState(null, '', u);
  }

  $('#pr-periode').addEventListener('change', (e) => { state.periode = e.target.value; render(); });
  document.addEventListener('click', (e) => {
    const el = (s) => e.target.closest(s);
    if (el('[data-proses]')) { state.proses = el('[data-proses]').dataset.proses; state.filter = ''; renderChain(); renderPanel(); }
    else if (el('[data-filter]')) { state.filter = el('[data-filter]').dataset.filter; renderPanel(); }
    else if (el('tr[data-tema]')) { bukaTema(el('tr[data-tema]').dataset.tema); }
  });
  render();
})();
</script>
@endsection
