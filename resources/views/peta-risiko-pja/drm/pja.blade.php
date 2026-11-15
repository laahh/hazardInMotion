@extends('peta-risiko-pja._layout')

@section('title', $ringkas['nama'].' · Beranda PJA')

@section('hero-aksi')
  <a class="pr-back" href="{{ route('peta-risiko-pja.index', ['tab' => 'pja', 'site' => $ringkas['site']]) }}">← Semua PJA</a>
@endsection

@section('hero')
  <p class="pr-kicker">Beranda PJA · {{ $ringkas['site'] }}</p>
  <h1 class="pr-title">{{ $ringkas['nama'] }}</h1>
  <p class="pr-sub" id="dr-sub">Data sampai {{ \Carbon\Carbon::parse($meta['sampai'])->translatedFormat('d M Y') }} · periode minggu kalender (Minggu–Sabtu)</p>
@endsection

@push('styles')
<style>
  .br-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
  .br-bar select { font: inherit; font-size: 13px; padding: 5px 8px; border: 1px solid var(--line, #dfe3e8); border-radius: 8px; background: #fff; }
  .br-lv { display: inline-flex; gap: 5px; align-items: center; padding: 3px 9px; border: 1px solid var(--line, #dfe3e8); border-radius: 999px; font-size: 13px; cursor: pointer; }
  .br-lv i { width: 10px; height: 10px; border-radius: 2px; display: inline-block; }
  .br-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin: 12px 0; }
  .br-kpi { background: #fff; border: 1px solid var(--line, #e3e6ea); border-radius: 12px; padding: 12px 14px; }
  .br-kpi small { display: block; color: #6b7480; font-size: 12px; }
  .br-kpi b { display: block; font-size: 26px; line-height: 1.3; }
  .br-kpi em { font-style: normal; font-size: 12px; color: #6b7480; }
  .br-sb { display: flex; height: 10px; border-radius: 3px; overflow: hidden; background: #eef1f4; min-width: 110px; }
  .br-sb i { display: block; height: 100%; }
  .br-mb { display: flex; align-items: flex-end; gap: 1px; height: 34px; min-width: 210px; }
  .br-mc { flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; position: relative; }
  .br-mc i { display: block; width: 100%; }
  .br-mc b { position: absolute; top: 0; left: 0; right: 0; height: 4px; border-radius: 2px; background: #ff1a2e; box-shadow: 0 0 5px 1px #ff1a2e; }
  .br-wb { display: flex; align-items: flex-end; gap: 6px; height: 140px; padding-top: 6px; }
  .br-wc { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; cursor: pointer; height: 100%; }
  .br-wc .stk { flex: 1; width: 100%; display: flex; flex-direction: column; justify-content: flex-end; border-radius: 4px; overflow: hidden; background: #f1f3f5; }
  .br-wc.sel .stk { outline: 2px solid #1b5fb5; outline-offset: 1px; }
  .br-wc span { font-size: 11px; color: #6b7480; white-space: nowrap; }
  .br-pill { display: inline-block; padding: 1px 8px; border-radius: 999px; color: #fff; font-size: 12px; font-weight: 600; }
  .br-pb { display: flex; align-items: flex-end; gap: 2px; height: 36px; }
  .br-pb div { width: 8px; height: 100%; display: flex; align-items: flex-end; }
  .br-pb i { display: block; width: 100%; border-radius: 2px 2px 0 0; }
  .br-sub > td { background: #f7f8fa; }
  .br-cr { border: 0; background: none; cursor: pointer; font-size: 13px; padding: 0 4px 0 0; color: inherit; }
  .br-ol { margin: 6px 0 0 18px; padding: 0; } .br-ol li { margin: 6px 0; }
  .br-h { display: block; margin: 14px 0 6px; font-size: 12px; letter-spacing: .02em; color: #6b7480; font-weight: 700; text-transform: uppercase; }
  .br-two { display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 16px; }
  .br-red { color: #c0392b; font-weight: 700; }
</style>
@endpush

@section('content')
  <div id="br-isi"><div class="pr-card"><div class="pr-empty">Memuat Beranda PJA…</div></div></div>

  <details class="pr-card" style="padding:0">
    <summary class="pr-card-head" style="cursor:pointer"><div><b>Lampiran: pengawasan personil PJA & coverage SAP</b><small>Pemenuhan SAP harian (14 hari), mingguan (BeARC), dan laporan per detail lokasi</small></div></summary>
    <div id="dr-personil"><div class="pr-empty">Memuat…</div></div>
  </details>

  <details class="pr-card" style="padding:0">
    <summary class="pr-card-head" style="cursor:pointer"><div><b>Lampiran: temuan Hazard/Inspeksi yang masih open</b><small id="dr-temuan-sub">Di detail lokasi PJA</small></div></summary>
    <div class="dr-scroll"><table class="pr-tbl dr-tbl" id="dr-temuan"></table></div>
  </details>

  <div class="pr-scrim" id="dr-scrim"></div>
  <aside class="pr-panel" id="dr-panel" aria-hidden="true" aria-labelledby="dr-panel-judul">
    <div class="pr-panel-head"><p class="pr-kicker" id="dr-panel-kicker"></p><h2 id="dr-panel-judul"></h2>
      <button type="button" class="pr-x" id="dr-tutup" aria-label="Tutup">✕</button></div>
    <div class="pr-panel-body" id="dr-panel-isi"></div>
  </aside>
@endsection

@section('scripts')
@include('peta-risiko-pja.drm._ui')
<script>
(() => {
  const META = {{ \Illuminate\Support\Js::from(\Illuminate\Support\Arr::only($meta, ['sampai', 'jendela', 'kolomBahaya', 'proses', 'beranda'])) }};
  const BR = META.beranda || null;
  const ID_PJA = {{ \Illuminate\Support\Js::from((string) $ringkas['id']) }};
  const URL_DATA = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.pja.data', ['pja' => $ringkas['id']])) }};
  const URL_PJA = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.pja', ['pja' => '0'])) }}.replace(/0$/, '__ID__');
  const URL_FLAG = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.flag.index', ['pja' => $ringkas['id']])) }};
  const URL_REVIEW = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.flag.review', ['flag' => '__ID__'])) }};
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  const $ = (s) => document.querySelector(s);
  const E = DR.esc, F = DR.fmt;
  const B = Object.fromEntries(META.kolomBahaya.map((k, i) => [k, i]));
  const KEL = { X: 'Belum terpetakan', ...Object.fromEntries(META.proses.map((p) => [p.kode, p.nama])) };
  const LV = ['Significant', 'High', 'Medium', 'Low'];
  const LVC = { Significant: '#b3261e', High: '#d0640c', Medium: '#c28f12', Low: '#2f7d4f' };
  const W = { ok: '#1d9e75', bm: '#d9822b', none: '#f2b99b' };
  const TIER = { t: ['Tinggi', '#b3261e'], s: ['Sedang', '#c28f12'], r: ['Rendah', '#6a8f3c'] };
  const BIT = { DET: 1, SPE: 2, CLOSED: 4, ULANG: 8, STOP: 16, TS: 32, INC: 64 };
  const HARI_INI = new Date().toISOString().slice(0, 10);
  const S = { w: 0, kb: { Significant: 1, High: 1, Medium: 0, Low: 0 }, aopen: {}, gopen: {}, semua: false, mg: false };
  let D = null, R = [], FL = { tersedia: false, flag: [] }, NW = 0, NH = 0;
  const idx = {};

  if (!BR) { $('#br-isi').innerHTML = '<div class="pr-card"><div class="pr-empty">Snapshot belum memuat data Beranda PJA. Jalankan ulang <code>php artisan peta-risiko:sync</code>.</div></div>'; return; }
  NW = BR.minggu.length; NH = BR.hari;

  Promise.all([
    fetch(URL_DATA, { headers: { Accept: 'application/json' } }).then((r) => r.json()),
    fetch(URL_FLAG, { headers: { Accept: 'application/json' } }).then((r) => r.ok ? r.json() : FL).catch(() => FL),
  ]).then(([d, fl]) => { D = d; FL = fl; siapkan(); render(); personil(); temuan(); })
    .catch(() => { $('#br-isi').innerHTML = '<div class="pr-card"><div class="pr-empty">Gagal memuat data PJA.</div></div>'; });

  // ------------------------------------------------------------ utilitas
  const tgl = (s) => new Date(s + 'T00:00:00');
  const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const tambahHari = (s, n) => { const d = tgl(s); d.setDate(d.getDate() + n); return iso(d); };
  const pendek = (s) => tgl(s).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
  const isoMinggu = (s) => { const d = tgl(s); const t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate())); const day = t.getUTCDay() || 7; t.setUTCDate(t.getUTCDate() + 4 - day); const y = new Date(Date.UTC(t.getUTCFullYear(), 0, 1)); return Math.ceil(((t - y) / 864e5 + 1) / 7); };
  const wKode = (i) => 'W' + isoMinggu(tambahHari(BR.minggu[i], 1));
  const wLabel = (i) => `${wKode(i)} · ${pendek(BR.minggu[i])} – ${pendek(tambahHari(BR.minggu[i], 6))}${i === 0 ? ' (berjalan, s.d. ' + pendek(META.sampai) + ')' : ''}`;
  const hariKe = (i) => tambahHari(BR.hari0, i);
  const sejak = (s) => { const n = Math.round((tgl(META.sampai) - tgl(s)) / 864e5); return n <= 0 ? 'hari ini' : n + ' hari lalu'; };
  const terakhir = (s) => !s || s === '1970-01-01' ? '<span class="dr-mute">belum ada</span>' : `${pendek(s)} <span class="dr-mute">(${sejak(s)})</span>`;
  const pct = (a, b) => b ? (100 * a / b).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%' : '–';
  const urai = (s) => { const o = {}; if (s) s.split(',').forEach((p) => { const [i, b] = p.split(':'); o[+i] = (o[+i] || 0) | +b; }); return o; };
  const lvTag = (l) => `<span class="br-pill" style="background:${LVC[l] || '#888'}">${E(l)}</span>`;

  /** Sama dengan DrmBeranda::status() di PHP: ['ok'|'bm'|'none', bit masalah]. */
  function statusBit(k, s) {
    const m = (s & (BIT.CLOSED | BIT.ULANG | BIT.TS)) | (k & BIT.STOP);
    if (m) return ['bm', m];
    return [(k & BIT.DET) || (s & BIT.SPE) ? 'ok' : 'none', 0];
  }
  const baru = () => ({ ok: 0, bm: 0, none: 0, g2: 0, g3: 0, g4: 0, g5: 0, excl: 0, u4: 0 });
  function tambah(c, st, m) {
    c[st]++;
    if (st === 'bm') { if (m & BIT.CLOSED) c.g2++; if (m & BIT.ULANG) c.g3++; if (m & BIT.STOP) c.g4++; if (m & BIT.TS) c.g5++; }
  }
  const nrel = (c) => c.ok + c.bm + c.none;

  // ------------------------------------------------------------ data
  function siapkan() {
    idx.dl = Object.fromEntries(D.lokasi.map((l) => [l.dl, l]));
    const bh = Object.fromEntries(D.bahaya.map((b) => [b[B.hid], b]));
    const BE = D.beranda || { kd: {}, sp: {}, dlast: {}, umum: {}, rc: [] };
    D.beranda = BE;
    const umum = {};
    Object.entries(BE.umum || {}).forEach(([g, l]) => l.forEach((h) => { umum[g + '|' + h] = 1; }));
    const kc = {};
    R = [];
    Object.entries(D.pasangan || {}).forEach(([dl, isi]) => {
      const l = idx.dl[dl];
      if (!l || !isi) return;
      isi.split(',').forEach((x) => {
        const hid = x.split(':')[0], b = bh[hid];
        if (!b) return;
        const kel = b[B.proses] || 'X', kk = dl + '|' + kel;
        const K = kc[kk] || (kc[kk] = ((v) => ({ w: urai(v && v[0]), h: urai(v && v[1]), last: (v && v[2]) || '' }))(BE.kd[kk]));
        const sp = BE.sp[dl + '|' + hid];
        R.push({ dl, hid, kel, grp: l.grp, n1: b[B.n1], bn: b[B.bahaya], umum: !!umum[l.grp + '|' + hid], K, Sw: urai(sp && sp[0]), Sh: urai(sp && sp[1]) });
      });
    });
    indeksFlag();
    if (D.info.mitra.length) $('#dr-sub').textContent += ' · Mitra: ' + D.info.mitra.map((m) => m.nama).join(', ');
  }

  // ------------------------------------------------------------ flag
  function indeksFlag() {
    idx.flag = {};
    (FL.flag || []).forEach((f) => (idx.flag[f.dl + '|' + f.aktivitas] ||= []).push(f));
    idx.nonaktif = {};
  }
  const flagKel = (dl, kel) => idx.flag[dl + '|kel:' + kel] || [];
  /** Flag NONAKTIF disetujui yang periodenya beririsan dengan minggu wi (mulai terbaru menang). */
  function nonaktif(dl, kel, wi) {
    const k = wi + '|' + dl + '|' + kel;
    if (k in idx.nonaktif) return idx.nonaktif[k];
    const a = BR.minggu[wi], z = tambahHari(a, 6);
    const f = flagKel(dl, kel).filter((x) => x.review === 'APPROVED' && x.mulai <= z && (!x.selesai || x.selesai >= a))
      .sort((p, q) => q.mulai.localeCompare(p.mulai) || q.id - p.id)[0];
    return (idx.nonaktif[k] = !!f && f.status === 'NONAKTIF');
  }

  // ------------------------------------------------------------ hitung
  function tidakTerkontrol4(r, w) {
    if (w + 3 >= NW) return false;
    for (let j = w; j <= w + 3; j++) if (statusBit(r.K.w[j] || 0, r.Sw[j] || 0)[0] === 'ok') return false;
    return true;
  }

  /** Semua baris aktif (filter tingkat risiko, tanpa bahaya umum) untuk minggu w. */
  function hitung(w, detail = true) {
    const T = baru(), A = {}, AG = {};
    R.forEach((r) => {
      if (r.umum || !S.kb[r.n1]) return;
      const gk = r.kel + '|' + r.grp;
      const a = A[r.kel] || (A[r.kel] = { c: baru(), gl: new Set() });
      const o = AG[gk] || (AG[gk] = { kel: r.kel, grp: r.grp, dls: new Set(), live: new Set(), rows: [], c: baru() });
      a.gl.add(gk); o.dls.add(r.dl);
      if (nonaktif(r.dl, r.kel, w)) { T.excl++; a.c.excl++; o.c.excl++; return; }
      o.live.add(r.dl); o.rows.push(r);
      const [st, m] = statusBit(r.K.w[w] || 0, r.Sw[w] || 0);
      tambah(T, st, m); tambah(a.c, st, m); tambah(o.c, st, m);
      if (detail && tidakTerkontrol4(r, w)) { T.u4++; a.c.u4++; o.c.u4++; }
    });
    return { T, A, AG };
  }

  /** Bar harian: per hari jumlah baris terkontrol / bermasalah / insiden (tanpa laporan = sisa). */
  function harian(rows, spesifik) {
    const d = Array.from({ length: NH }, () => ({ ok: 0, bm: 0, inc: 0 }));
    rows.forEach((r) => {
      const hari = new Set([...Object.keys(spesifik ? {} : r.K.h), ...Object.keys(r.Sh)]);
      hari.forEach((i) => {
        if (i >= NH) return;
        const s = r.Sh[i] || 0, st = statusBit(spesifik ? 0 : (r.K.h[i] || 0), s)[0];
        if (st === 'ok') d[i].ok++; else if (st === 'bm') d[i].bm++;
        if (s & BIT.INC) d[i].inc++;
      });
    });
    return d;
  }
  function ciri(days) {
    let c7 = 0, st = 0;
    for (let i = NH - 7; i < NH; i++) if (days[i].bm > 0) c7++;
    for (let i = NH - 1; i >= NH - 14 && days[i].bm > 0; i--) st++;
    return { c7, st };
  }
  function tekanan(f, c) {
    const T = BR.tekanan, tot = nrel(c), sh = tot ? c.u4 / tot : 0, rk = { r: 0, s: 1, t: 2 };
    const t1 = sh >= T.u4_tinggi ? 't' : sh >= T.u4_sedang ? 's' : 'r';
    const t2 = f.c7 >= T.hari_tinggi || f.st >= T.beruntun_tinggi ? 't' : f.c7 >= T.hari_sedang ? 's' : 'r';
    return rk[t1] >= rk[t2] ? t1 : t2;
  }

  // ------------------------------------------------------------ gambar
  function sb(ok, bm, none) {
    const t = ok + bm + none;
    if (!t) return '<div class="br-sb"></div>';
    const w = (x) => (100 * x / t).toFixed(2) + '%';
    return `<div class="br-sb" title="${F(ok)} terkontrol; gap: ${F(bm)} laporan bermasalah + ${F(none)} tanpa laporan"><i style="width:${w(ok)};background:${W.ok}"></i><i style="width:${w(bm)};background:${W.bm}"></i><i style="width:${w(none)};background:${W.none}"></i></div>`;
  }
  function batangHarian(days, tot) {
    const mx = Math.max(1, tot), H = (x) => (100 * x / mx).toFixed(1) + '%';
    return `<div class="br-mb">${days.map((e, i) => {
      const none = Math.max(0, tot - e.ok - e.bm);
      return `<div class="br-mc" title="${pendek(hariKe(i))}: ${e.ok} terkontrol; gap ${e.bm + none} (${e.bm} laporan bermasalah + ${none} tanpa laporan hari itu)${e.inc ? ' | INSIDEN' : ''}">${e.inc ? '<b></b>' : ''}<i style="height:${H(none)};background:${W.none}"></i><i style="height:${H(e.bm)};background:${W.bm}"></i><i style="height:${H(e.ok)};background:${W.ok}"></i></div>`;
    }).join('')}</div>`;
  }
  function selTekanan(days, c) {
    const f = ciri(days), t = TIER[tekanan(f, c)], tot = nrel(c), sh = tot ? Math.round(100 * c.u4 / tot) : 0;
    return `<span class="br-pill" style="background:${t[1]}">${t[0]}</span><br><span class="dr-mute" style="font-size:12px">${sh}% 4 minggu tak terkontrol<br>${f.c7}/7 hari laporan bermasalah</span>`;
  }
  function proyeksi(days, tot) {
    tot = Math.max(1, tot);
    let g7 = 0, p7 = 0, g28 = 0;
    days.forEach((e, i) => { const g = 1 - e.ok / tot, p = e.bm / tot; g28 += g; if (i >= NH - 7) { g7 += g; p7 += p; } });
    g7 /= 7; p7 /= 7; g28 /= NH;
    const n = Math.round(100 * g7), pp = Math.round(100 * Math.min(p7, g7)), tr = g7 - g28 > 0.03 ? 'naik' : g28 - g7 > 0.03 ? 'turun' : 'stabil';
    const isi = Array.from({ length: 7 }, (_, i) => `<div title="${pendek(tambahHari(META.sampai, i + 1))}: perkiraan gap ${n}% (bermasalah ${pp}%, tanpa laporan ${n - pp}%)"><i style="height:${Math.max(2, n * 0.34).toFixed(0)}px;background:linear-gradient(to top,${W.bm} ${(n ? 100 * pp / n : 0).toFixed(0)}%,${W.none} 0)"></i></div>`).join('');
    return `<div class="br-pb">${isi}</div><div style="font-size:12px"><b>gap ~${n}%</b> per hari<br><span class="dr-mute">${pp}% bermasalah + ${n - pp}% tanpa laporan<br>rata-rata ${NH} hari ${Math.round(100 * g28)}% (${tr})</span></div>`;
  }
  const legenda = () => `<div class="dr-legend"><span><i style="--c:${W.ok}"></i>Terkontrol (ada laporan SAP, tanpa gap)</span><span><i style="--c:${W.bm}"></i>Gap: laporan bermasalah (belum closed, berulang, OAK Stop, butir tidak sesuai)</span><span><i style="--c:${W.none}"></i>Gap: tanpa laporan SAP</span><span><i style="--c:#ff1a2e"></i>Insiden (root cause terpetakan ke bahaya)</span></div>`;

  // ------------------------------------------------------------ render
  function render() {
    const y = window.pageYOffset;
    idx.nonaktif = {};
    const H = hitung(S.w);
    const T = H.T, n = nrel(T), G = T.bm + T.none;
    $('#br-isi').innerHTML = filterBar() + `<div class="br-kpis">
        <div class="br-kpi"><small>Pembagi · ${E(wLabel(S.w))}</small><b>${F(n)}</b><em>baris baseline (kelompok aktivitas × detail lokasi × bahaya-risiko)${T.excl ? `; ${F(T.excl)} dikecualikan flag` : ''}</em></div>
        <div class="br-kpi"><small>Terkontrol menurut SAP</small><b style="color:${W.ok}">${pct(T.ok, n)}</b><em>${F(T.ok)} baris</em></div>
        <div class="br-kpi"><small>Gap (termasuk tanpa laporan)</small><b style="color:#d9622b">${pct(G, n)}</b><em>${F(G)} baris</em></div>
        <div class="br-kpi"><small>Isi gap: tanpa laporan</small><b>${pct(T.none, G)}</b><em>${F(T.none)} tanpa laporan SAP; ${F(T.bm)} laporan bermasalah (belum closed ${T.g2}, berulang ${T.g3}, OAK stop ${T.g4}, butir tidak sesuai ${T.g5})</em></div>
      </div>`
      + ringkasan(H) + panelRc()
      + `<details class="pr-card" id="br-mg" style="padding:0"${S.mg ? ' open' : ''}><summary class="pr-card-head" style="cursor:pointer"><div><b>Perbandingan antar PJA (untuk manajemen)</b><small>Diurutkan dari persen gap tertinggi · tanpa flag · angka antar PJA tidak boleh dijumlahkan</small></div></summary><div class="pr-card-body" id="br-mg-isi">${S.mg ? tabelMgmt() : ''}</div></details>`
      + `<div class="pr-card"><div class="pr-card-head"><div><b>Tren mingguan</b><small>Setelah filter dan flag; klik batang untuk pindah minggu</small></div></div><div class="pr-card-body">${tren()}</div></div>`
      + hierarki(H) + caraMembaca();
    pasang();
    window.scrollTo(0, y);
  }

  function filterBar() {
    return `<div class="pr-card"><div class="pr-card-body br-bar">
        <label class="dr-mute">Periode <select id="br-w">${BR.minggu.map((_, i) => `<option value="${i}"${i === S.w ? ' selected' : ''}>${E(wLabel(i))}</option>`).join('')}</select></label>
        <button type="button" class="pr-back" id="br-prev" ${S.w >= NW - 1 ? 'disabled' : ''}>‹</button>
        <button type="button" class="pr-back" id="br-next" ${S.w <= 0 ? 'disabled' : ''}>›</button>
        <span class="dr-mute" style="margin-left:8px">Tingkat risiko awal:</span>
        ${LV.map((l) => `<label class="br-lv"><input type="checkbox" class="br-kb" data-k="${l}"${S.kb[l] ? ' checked' : ''}><i style="background:${LVC[l]}"></i>${l}</label>`).join('')}
        <span class="dr-mute">bawaan: Significant dan High</span>
      </div></div>`;
  }

  // ------------------------------------------------- ringkasan & tindak lanjut
  function ringkasan(H) {
    const T = H.T, n = nrel(T), G = T.bm + T.none;
    const groups = Object.values(H.AG);
    const hi = groups.filter((o) => nrel(o.c) && o.c.u4 / nrel(o.c) >= BR.tekanan.u4_tinggi).length;
    const dlAgg = {};
    groups.forEach((o) => o.rows.forEach((r) => {
      const st = statusBit(r.K.w[S.w] || 0, r.Sw[S.w] || 0)[0];
      if (st !== 'bm') return;
      const a = dlAgg[r.dl] || (dlAgg[r.dl] = { n: 0, kel: new Set() }); a.n++; a.kel.add(r.kel);
    }));
    const topDl = Object.entries(dlAgg).sort((a, b) => b[1].n - a[1].n).slice(0, 5);
    const batas = tambahHari(META.sampai, -(BR.lamaHari - 1));
    const lama = D.lokasi.map((l) => ({ l, t: D.beranda.dlast[l.dl] || '' })).filter((x) => !x.t || x.t < batas).sort((a, b) => a.t.localeCompare(b.t));
    const inc = D.insiden.filter((i) => i.tanggal >= BR.hari0 && i.tanggal <= META.sampai);
    const sig = {};
    groups.forEach((o) => o.rows.forEach((r) => {
      if (r.n1 !== 'Significant' || statusBit(r.K.w[S.w] || 0, r.Sw[S.w] || 0)[0] !== 'none') return;
      sig[r.bn] = (sig[r.bn] || 0) + 1;
    }));
    const sigL = Object.entries(sig).sort((a, b) => b[1] - a[1]), nSig = sigL.reduce((s, e) => s + e[1], 0);
    const akt = Object.entries(H.A).filter(([, a]) => nrel(a.c)).sort((x, y) => (y[1].c.bm + y[1].c.none) - (x[1].c.bm + x[1].c.none)).slice(0, 6);
    const tl = [];
    if (hi) tl.push(`<b>Konfirmasi aktivitas:</b> ${hi} dari ${groups.length} kombinasi kelompok aktivitas × grup lokasi punya ≥${Math.round(100 * BR.tekanan.u4_tinggi)}% baris tidak terkontrol 4 minggu berturut-turut. Pastikan aktivitasnya memang berjalan. Bila tidak ada, ajukan flag "nonaktif" di detail lokasinya; bila ada, catat pengawasannya lewat OAK atau OBS.`);
    if (lama.length) tl.push(`<b>Lokasi tanpa laporan SAP ${BR.lamaHari} hari atau lebih:</b> ${lama.length} detail lokasi (terlama: ${lama.slice(0, 5).map((x) => E(x.l.detlok) + ' ' + (x.t ? sejak(x.t) : 'belum pernah dalam 3 bulan')).join('; ')}). Tentukan apakah lokasi masih aktif dan siapa pengawasnya.`);
    if (T.g2) tl.push(`<b>Tutup temuan:</b> ${F(T.g2)} baris punya laporan Hazard/Inspeksi yang belum closed. Tindak lanjuti dan tutup di SAP.`);
    if (T.g3) tl.push(`<b>Laporan berulang:</b> ${F(T.g3)} baris punya ${BR.ambangUlang} laporan serupa atau lebih di lokasi yang sama dalam seminggu. Tinjau akar masalah dan buat perbaikan permanen.`);
    if (T.g4) tl.push(`<b>OAK Stop Pekerjaan:</b> ${F(T.g4)} baris. Verifikasi tindak lanjutnya sebelum pekerjaan dilanjutkan.`);
    if (T.g5) tl.push(`<b>Butir checklist OAK tidak sesuai:</b> ${F(T.g5)} baris. Perbaiki kondisi yang dicatat tidak sesuai.`);
    if (nSig) tl.push(`<b>Bahaya Significant tanpa laporan:</b> ${F(nSig)} baris. Terbanyak: ${sigL.slice(0, 5).map(([t, c]) => E(t) + ' (' + c + ')').join('; ')}. Pastikan pengawasan OAK mencakup bahaya ini.`);
    if (inc.length) tl.push(`<b>Lokasi dengan insiden ${NH} hari terakhir:</b> ${inc.length} kejadian (${inc.slice(0, 5).map((i) => E(i.detlok) + ' ' + pendek(i.tanggal)).join('; ')}). Tinjau bahaya-risiko dan laporan di detail lokasi itu.`);

    return `<div class="pr-card"><div class="pr-card-head"><div><b>Ringkasan gap dan tindak lanjut · ${E(wKode(S.w))}</b><small>Disusun otomatis dari aturan dan data SAP, bukan temuan lapangan; angka mengikuti filter risiko, periode, dan flag</small></div></div><div class="pr-card-body">
      <p style="margin:0 0 10px">Dari <b>${F(n)}</b> baris baseline, <b>${pct(T.ok, n)}</b> terkontrol menurut SAP dan <b>${pct(G, n)}</b> gap. Isi gap: <b>${F(T.none)}</b> baris tanpa laporan SAP dan <b>${F(T.bm)}</b> baris dengan laporan bermasalah.${G && T.none / G > 0.8 ? ' Hampir semua gap adalah tanpa laporan: masalah utamanya cakupan pelaporan, belum tentu kondisi lapangan.' : ''}</p>
      <div class="br-two"><div><span class="br-h">Kelompok aktivitas dengan gap terbanyak</span><table class="pr-tbl dr-tbl"><thead><tr><th>Kelompok aktivitas</th><th class="r">Baris</th><th class="r">Terkontrol</th><th class="r">Tanpa laporan</th><th class="r">Bermasalah</th></tr></thead><tbody>${
        akt.map(([k, a]) => `<tr><td>${E(KEL[k] || k)}</td><td class="r">${F(nrel(a.c))}</td><td class="r">${pct(a.c.ok, nrel(a.c))}</td><td class="r">${F(a.c.none)}</td><td class="r">${F(a.c.bm)}</td></tr>`).join('') || '<tr><td colspan="5" class="pr-empty">Tidak ada baris.</td></tr>'}</tbody></table></div>
        <div><span class="br-h">Detail lokasi dengan laporan bermasalah terbanyak</span>${topDl.length ? `<table class="pr-tbl dr-tbl"><thead><tr><th>Detail lokasi</th><th class="r">Baris bermasalah</th><th>Kelompok</th></tr></thead><tbody>${
          topDl.map(([dl, a]) => { const l = idx.dl[dl] || {}; return `<tr><td>${E(l.lokasi)} · <b>${E(l.detlok)}</b></td><td class="r">${a.n}</td><td class="dr-mute">${[...a.kel].slice(0, 3).map((k) => E(KEL[k] || k)).join(', ')}</td></tr>`; }).join('')}</tbody></table>` : '<div class="dr-mute">Tidak ada laporan bermasalah pada periode ini.</div>'}</div></div>
      <span class="br-h">Tindak lanjut yang perlu dibuat PJA</span>${tl.length ? `<ol class="br-ol">${tl.map((t) => `<li>${t}</li>`).join('')}</ol>` : '<div class="dr-mute">Tidak ada tindak lanjut otomatis untuk periode ini.</div>'}
      ${perhatian(H)}</div></div>`;
  }

  function perhatian(H) {
    const Bm = {}, Z = {}, KO = { Significant: 0, High: 1, Medium: 2, Low: 3 };
    Object.values(H.AG).forEach((o) => o.rows.forEach((r) => {
      const s = r.Sw[S.w] || 0, [st, m] = statusBit(0, s), inc = Object.values(r.Sh).some((b) => b & BIT.INC);
      const key = r.bn + '|' + r.n1;
      if (st === 'bm' || inc) {
        const b = Bm[key] || (Bm[key] = { t: r.bn, kb: r.n1, g2: 0, g3: 0, g5: 0, inc: 0, f: {} });
        if (m & BIT.CLOSED) b.g2++; if (m & BIT.ULANG) b.g3++; if (m & BIT.TS) b.g5++; if (inc) b.inc++;
        b.f[r.kel] = (b.f[r.kel] || 0) + 1;
      }
      if (st === 'none' && (r.n1 === 'Significant' || r.n1 === 'High')) {
        const z = Z[key] || (Z[key] = { t: r.bn, kb: r.n1, n: 0, f: {} }); z.n++; z.f[r.kel] = (z.f[r.kel] || 0) + 1;
      }
    }));
    const kel3 = (f) => Object.entries(f).sort((a, b) => b[1] - a[1]).slice(0, 3).map(([k, v]) => E(KEL[k] || k) + ' (' + v + ')').join('; ');
    const aksi = (b) => [b.g2 && 'tutup temuan di SAP', b.g3 && 'perbaikan permanen', b.g5 && 'perbaiki kondisi butir checklist', b.inc && 'cek padanan root cause insiden'].filter(Boolean).join('; ') || '–';
    const BL = Object.values(Bm).sort((a, b) => KO[a.kb] - KO[b.kb] || (b.g2 + b.g3 + b.g5 + b.inc) - (a.g2 + a.g3 + a.g5 + a.inc));
    const ZL = Object.values(Z).sort((a, b) => KO[a.kb] - KO[b.kb] || b.n - a.n);
    const baris = (b) => `<tr><td>${E(b.t)}</td><td>${lvTag(b.kb)}</td><td class="dr-mute">${kel3(b.f)}</td><td class="dr-mute">${b.g2 ? 'belum closed ' + b.g2 + '<br>' : ''}${b.g3 ? 'berulang ' + b.g3 + '<br>' : ''}${b.g5 ? 'butir tidak sesuai ' + b.g5 + '<br>' : ''}${b.inc ? '<span class="br-red">terkait insiden</span>' : ''}</td><td class="dr-mute">${aksi(b)}</td></tr>`;
    const kepala = '<thead><tr><th style="min-width:220px">Bahaya-risiko</th><th>Risiko awal</th><th style="min-width:200px">Kelompok aktivitas (jumlah baris)</th><th>Jenis gap</th><th>Tindak lanjut</th></tr></thead>';
    return `<span class="br-h">Bahaya-risiko yang masih menjadi perhatian (laporan bermasalah atau terkait insiden; identifikasi spesifik)</span>${
      BL.length ? `<div class="dr-scroll"><table class="pr-tbl dr-tbl">${kepala}<tbody>${BL.slice(0, 10).map(baris).join('')}</tbody></table></div>${BL.length > 10 ? `<details style="margin-top:6px"><summary class="dr-mute">Tampilkan ${BL.length - 10} lainnya</summary><div class="dr-scroll"><table class="pr-tbl dr-tbl">${kepala}<tbody>${BL.slice(10).map(baris).join('')}</tbody></table></div></details>` : ''}` : '<div class="dr-mute">Tidak ada bahaya-risiko dengan laporan bermasalah atau keterkaitan insiden pada periode ini.</div>'}
      <span class="br-h">Bahaya-risiko Significant/High yang belum teridentifikasi spesifik (tanpa label Hazard/Inspeksi atau butir checklist OAK)</span>${
      ZL.length ? `<div class="dr-scroll"><table class="pr-tbl dr-tbl"><thead><tr><th style="min-width:220px">Bahaya-risiko</th><th>Risiko awal</th><th class="r">Jumlah baris</th><th>Kelompok aktivitas terbanyak</th></tr></thead><tbody>${
        ZL.slice(0, 10).map((z) => `<tr><td>${E(z.t)}</td><td>${lvTag(z.kb)}</td><td class="r">${F(z.n)}</td><td class="dr-mute">${kel3(z.f)}</td></tr>`).join('')}</tbody></table></div><div class="dr-mute" style="margin-top:4px">${ZL.length} bahaya-risiko; 10 teratas. Pastikan OAK/OBS memeriksa bahaya ini dan mencatat hasilnya di checklist.</div>` : '<div class="dr-mute">Semua bahaya-risiko Significant/High teridentifikasi spesifik pada periode ini.</div>'}`;
  }

  // ------------------------------------------------------------ panel insiden
  function panelRc() {
    const rc = D.beranda.rc || [];
    const kepala = '<div class="pr-card-head"><div><b>Insiden dan laporan SAP terkait root cause</b><small>Root cause berstatus ROOT CAUSE (Hazard_Related_Incident), insiden tahun berjalan di detail lokasi PJA</small></div></div>';
    if (!rc.length) return `<div class="pr-card">${kepala}<div class="pr-empty">Tidak ada insiden tahun ini dengan root cause terpetakan di detail lokasi PJA ini.</div></div>`;
    const per = {};
    rc.forEach((r) => (per[r.no] ||= []).push(r));
    const nIt = rc.length, nNo = rc.filter((r) => !r.sebelum && !r.bulan).length;
    return `<div class="pr-card">${kepala}<div class="pr-card-body"><p style="margin:0 0 8px"><b>${Object.keys(per).length}</b> insiden punya <b>${nIt}</b> root cause berlabel SAP. Pada <b>${nNo}</b> di antaranya tidak ada laporan Hazard/Inspeksi berlabel sama di detail lokasi itu pada 3 bulan sebelum atau bulan kejadian (penyebab tidak terdeteksi SAP); <b>${nIt - nNo}</b> sudah pernah dilaporkan sebelumnya.</p>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl"><thead><tr><th>Insiden</th><th>Root cause (label SAP)</th><th class="r">Label sama: 3 bln sebelum</th><th class="r">Bulan kejadian</th><th class="r">Sesudah</th><th class="r">Ketidaksesuaian sama: sebelum / sesudah</th><th>Laporan terakhir (label)</th><th class="r">Belum closed</th></tr></thead><tbody>${
        Object.entries(per).map(([no, l]) => l.map((r, i) => `<tr>${i ? '<td></td>' : `<td><b>#${E(no)}</b><br><span class="dr-mute">${E(r.tanggal)} · ${E(r.kategori)}<br>${E(r.detlok)}</span></td>`}
          <td>${E(r.label)}</td><td class="r">${r.sebelum ? '<b>' + r.sebelum + '</b>' : '<span class="br-red">0</span>'}</td><td class="r">${r.bulan}</td><td class="r">${r.sesudah}</td>
          <td class="r">${r.kSebelum} / ${r.kSesudah}</td><td>${r.terakhir ? E(r.terakhir) : '–'}</td><td class="r">${r.belum ? '<b class="br-red">' + r.belum + '</b>' : 0}</td></tr>`).join('')).join('')}</tbody></table></div>
      <div class="dr-mute" style="margin-top:6px">Laporan dicocokkan lewat label ketidaksesuaian + sub yang sama di detail lokasi insiden (HAZARD/INSPEKSI, tanpa DELETED/BUKAN TEMUAN), dihitung per bulan kalender. "3 bln sebelum" = 0 berarti penyebab tidak terdeteksi SAP; "Belum closed" &gt; 0 berarti ada laporan sejenis yang masih terbuka.</div></div></div>`;
  }

  // ------------------------------------------------------------ manajemen
  function tabelMgmt() {
    const K = BR.kolomMgmt, ix = Object.fromEntries(K.map((k, i) => [k, i]));
    const L = BR.mgmt.map((p) => {
      const c = Array(K.length).fill(0);
      Object.entries((p.w || [])[S.w] || {}).forEach(([lv, a]) => { if (S.kb[lv]) a.forEach((v, i) => { c[i] += v; }); });
      const n = c[ix.ok] + c[ix.bm] + c[ix.none];
      return { p, c, n, gap: n ? (c[ix.bm] + c[ix.none]) / n : 0 };
    }).filter((e) => e.n).sort((a, b) => b.gap - a.gap);
    return `<div class="dr-scroll" style="max-height:560px;overflow:auto"><table class="pr-tbl dr-tbl" style="min-width:1000px"><thead><tr><th>PJA</th><th class="r">Detail lokasi</th><th class="r">Baris baseline</th><th>Terkontrol</th><th class="r">Gap</th><th class="r">Tanpa laporan</th><th class="r">Bermasalah</th><th class="r">Belum closed</th><th class="r">Berulang</th><th class="r">OAK stop</th><th class="r">Butir tdk sesuai</th><th class="r">DL tanpa laporan ${BR.lamaHari} hr+</th><th class="r">Insiden ${NH} hr</th></tr></thead><tbody>${
      L.map((e) => `<tr data-pja="${E(e.p.id)}" style="cursor:pointer${e.p.id === ID_PJA ? ';background:#eef4fb' : ''}"><td><b>${E(e.p.nama)}</b><br><span class="dr-mute">${E(e.p.site)}</span></td><td class="r">${e.p.ndl}</td><td class="r">${F(e.n)}</td>
        <td>${sb(e.c[ix.ok], e.c[ix.bm], e.c[ix.none])}<span class="dr-mute">${pct(e.c[ix.ok], e.n)}</span></td><td class="r"><b>${pct(e.c[ix.bm] + e.c[ix.none], e.n)}</b></td>
        <td class="r">${F(e.c[ix.none])}</td><td class="r">${F(e.c[ix.bm])}</td><td class="r">${F(e.c[ix.closed])}</td><td class="r">${F(e.c[ix.ulang])}</td><td class="r">${F(e.c[ix.stop])}</td><td class="r">${F(e.c[ix.tidak_sesuai])}</td>
        <td class="r">${e.p.lama}</td><td class="r">${e.p.insiden || ''}</td></tr>`).join('')}</tbody></table></div>
      <div class="dr-mute" style="margin-top:6px">Periode ${E(wKode(S.w))}, filter tingkat risiko sesuai pilihan di atas, tanpa flag. Klik baris untuk membuka Beranda PJA itu.</div>`;
  }

  // ------------------------------------------------------------ tren
  function tren() {
    const col = [];
    for (let i = NW - 1; i >= 0; i--) {
      const T = hitung(i, false).T, n = Math.max(1, nrel(T));
      col.push(`<div class="br-wc${i === S.w ? ' sel' : ''}" data-w="${i}" title="${E(wLabel(i))}: ${F(T.ok)} terkontrol, gap ${F(T.bm + T.none)} (${F(T.bm)} bermasalah + ${F(T.none)} tanpa laporan); ${pct(T.ok, nrel(T))} terkontrol">
        <div class="stk"><i style="height:${(100 * T.none / n).toFixed(1)}%;background:${W.none}"></i><i style="height:${(100 * T.bm / n).toFixed(1)}%;background:${W.bm}"></i><i style="height:${(100 * T.ok / n).toFixed(1)}%;background:${W.ok}"></i></div>
        <span>${wKode(i)}</span><span>${pct(T.bm + T.none, nrel(T))}</span></div>`);
    }
    return `<div class="br-wb">${col.join('')}</div><div class="dr-mute" style="margin-top:6px">Persen di bawah batang = gap. Kenaikan 2–3 minggu berturut-turut adalah sinyal yang perlu ditindaklanjuti. Minggu berjalan masih sebagian.</div>`;
  }

  // ------------------------------------------------------------ hierarki
  function hierarki(H) {
    const ak = Object.entries(H.A).sort((x, y) => (y[1].c.bm + y[1].c.none) / Math.max(1, nrel(y[1].c)) - (x[1].c.bm + x[1].c.none) / Math.max(1, nrel(x[1].c)) || nrel(y[1].c) - nrel(x[1].c));
    const tampil = S.semua ? ak : ak.filter(([k, a]) => a.c.ok + a.c.bm > 0 || S.aopen[k]);
    const isi = tampil.map(([k, a]) => {
      const n = nrel(a.c), gl = [...a.gl].map((g) => H.AG[g]);
      const hi = gl.filter((o) => tekanan(ciri(harian(o.rows)), o.c) === 't').length;
      const buka = !!S.aopen[k];
      return `<tr class="br-a" data-a="${E(k)}" style="cursor:pointer"><td><button class="br-cr" type="button">${buka ? '▾' : '▸'}</button><b>${E(KEL[k] || k)}</b><br><span class="dr-mute" style="padding-left:18px">${gl.length} grup lokasi · ${F(n)} baris baseline</span></td>
        <td>${sb(a.c.ok, a.c.bm, a.c.none)}</td><td class="r">${pct(a.c.ok, n)}<br><span class="dr-mute">${F(a.c.ok)}</span></td>
        <td class="r"><b>${pct(a.c.bm + a.c.none, n)}</b><br><span class="dr-mute">${F(a.c.bm)} laporan + ${F(a.c.none)} tanpa laporan</span></td><td class="r">${a.c.excl || ''}</td>
        <td>${hi ? `<span class="br-pill" style="background:${TIER.t[1]}">${hi} dari ${gl.length} grup</span>` : '<span class="dr-mute">–</span>'}</td></tr>`
        + (buka ? `<tr class="br-sub"><td colspan="6" style="padding:8px 10px 12px 22px">${tabelGrup(gl)}</td></tr>` : '');
    }).join('');
    const sisa = ak.length - tampil.length;
    return `<div class="pr-card"><div class="pr-card-head"><div><b>Kelompok aktivitas → grup lokasi → detail lokasi → bahaya-risiko · ${E(wKode(S.w))}</b>
        <small>Kelompok aktivitas = proses bisnis HIRA. Bar harian ${NH} hari (${pendek(BR.hari0)} – ${pendek(META.sampai)}): tinggi = jumlah baris baseline. Proyeksi = rata-rata persen gap harian 7 hari terakhir.</small></div></div>
      ${legenda()}<div class="dr-scroll"><table class="pr-tbl dr-tbl" style="min-width:1100px"><thead><tr><th style="min-width:260px">Kelompok aktivitas</th><th style="min-width:160px">Komposisi</th><th class="r">Terkontrol</th><th class="r">Gap</th><th class="r">Dikecualikan</th><th>Grup tekanan gap tinggi</th></tr></thead><tbody>${isi || '<tr><td colspan="6" class="pr-empty">Tidak ada baris untuk filter ini.</td></tr>'}</tbody></table></div>
      ${sisa || S.semua ? `<div class="pr-card-body"><button type="button" class="pr-back" id="br-semua">${S.semua ? 'Sembunyikan kelompok tanpa laporan SAP minggu ini' : `Tampilkan ${sisa} kelompok lain yang tidak ada laporan SAP minggu ini`}</button></div>` : ''}</div>`;
  }

  function tabelGrup(gl) {
    return `<table class="pr-tbl dr-tbl"><thead><tr><th style="min-width:200px">Grup lokasi</th><th style="min-width:140px">Komposisi</th><th class="r">Terkontrol</th><th class="r">Gap laporan</th><th class="r">Tanpa laporan</th><th>Laporan SAP terakhir</th><th>Harian ${NH} hari</th><th>Tekanan gap</th><th>Proyeksi 7 hari</th></tr></thead><tbody>${
      gl.sort((x, y) => (y.c.bm + y.c.none) / Math.max(1, nrel(y.c)) - (x.c.bm + x.c.none) / Math.max(1, nrel(x.c))).map((o) => {
        const key = o.kel + '|' + o.grp, buka = !!S.gopen[key], days = harian(o.rows), n = nrel(o.c);
        const last = [...o.live].map((d) => (o.rows.find((r) => r.dl === d) || { K: { last: '' } }).K.last).filter(Boolean).sort().pop() || '';
        const ninc = days.filter((x) => x.inc).length, off = o.dls.size - o.live.size;
        return `<tr class="br-g" data-g="${E(key)}" style="cursor:pointer"><td><button class="br-cr" type="button">${buka ? '▾' : '▸'}</button><b>${E(o.grp)}</b><br><span class="dr-mute" style="padding-left:18px">${o.dls.size} detail lokasi${off ? ` (${off} nonaktif)` : ''} · ${F(n)} baris</span></td>
          <td>${sb(o.c.ok, o.c.bm, o.c.none)}</td><td class="r">${F(o.c.ok)}</td><td class="r">${F(o.c.bm)}</td><td class="r">${F(o.c.none)}</td><td>${terakhir(last)}</td>
          <td>${batangHarian(days, n)}${ninc ? `<div class="br-red" style="font-size:12px">insiden di ${ninc} hari</div>` : ''}</td><td>${selTekanan(days, o.c)}</td><td>${proyeksi(days, n)}</td></tr>`
          + (buka ? `<tr><td colspan="9" style="padding:6px 8px 12px 22px">${tabelDl(o)}${tabelBahaya(o)}${tabelUmum(o)}</td></tr>` : '');
      }).join('')}</tbody></table>`;
  }

  function tabelDl(o) {
    const rowsDl = {};
    R.forEach((r) => { if (!r.umum && S.kb[r.n1] && r.kel === o.kel && r.grp === o.grp) (rowsDl[r.dl] ||= []).push(r); });
    const daftar = [...o.dls].map((dl) => {
      const rows = rowsDl[dl] || [], off = nonaktif(dl, o.kel, S.w), c = baru();
      if (!off) rows.forEach((r) => { const [st, m] = statusBit(r.K.w[S.w] || 0, r.Sw[S.w] || 0); tambah(c, st, m); if (tidakTerkontrol4(r, S.w)) c.u4++; });
      return { dl, rows, off, c };
    }).sort((a, b) => (b.c.bm + b.c.none) - (a.c.bm + a.c.none) || String((idx.dl[a.dl] || {}).detlok).localeCompare(String((idx.dl[b.dl] || {}).detlok)));
    return `<span class="br-h">Detail lokasi (flag: ada / tidak ada aktivitas kelompok ini — bawaan: ada aktivitas)</span><div class="dr-scroll"><table class="pr-tbl dr-tbl"><thead><tr><th>Detail lokasi</th><th style="min-width:110px">Komposisi</th><th>Laporan SAP kelompok ini</th><th>Terakhir (apa pun)</th><th>Harian ${NH} hari</th><th>Tekanan gap</th><th>Proyeksi 7 hari</th><th>Flag PJA</th></tr></thead><tbody>${
      daftar.map((z) => {
        const l = idx.dl[z.dl] || {}, n = nrel(z.c), days = z.off ? null : harian(z.rows), fl = flagKel(z.dl, o.kel);
        const pending = fl.some((f) => f.review === 'PENDING');
        return `<tr${z.off ? ' style="opacity:.55"' : ''}><td>${E(l.lokasi)} · <b>${E(l.detlok)}</b></td><td>${z.off ? '<span class="dr-mute">dikecualikan</span>' : sb(z.c.ok, z.c.bm, z.c.none)}</td>
          <td>${terakhir((z.rows[0] || { K: {} }).K.last)}</td><td>${terakhir(D.beranda.dlast[z.dl])}</td>
          ${z.off ? '<td colspan="3" class="dr-mute">dikecualikan (flag nonaktif disetujui)</td>' : `<td>${batangHarian(days, n)}</td><td>${selTekanan(days, z.c)}</td><td>${proyeksi(days, n)}</td>`}
          <td><button type="button" class="pr-back" data-flag="${E(z.dl)}" data-kel="${E(o.kel)}">${z.off ? 'Nonaktif' : 'Ada aktivitas'}</button>${pending ? '<br><span class="dr-tag st-X">Menunggu review</span>' : ''}</td></tr>`;
      }).join('')}</tbody></table></div>`;
  }

  function perBahaya(rows) {
    const per = {};
    rows.forEach((r) => {
      const p = per[r.hid] || (per[r.hid] = { r, rows: [], c: baru() });
      p.rows.push(r);
      const [st, m] = statusBit(0, r.Sw[S.w] || 0); tambah(p.c, st, m);
      let u = S.w + 3 < NW; for (let j = S.w; u && j <= S.w + 3; j++) if (statusBit(0, r.Sw[j] || 0)[0] === 'ok') u = false;
      if (u) p.c.u4++;
    });
    return Object.values(per);
  }

  function tabelBahaya(o) {
    const KO = { Significant: 0, High: 1, Medium: 2, Low: 3 };
    const L = perBahaya(o.rows).sort((a, b) => KO[a.r.n1] - KO[b.r.n1] || (b.c.bm + b.c.none) - (a.c.bm + a.c.none));
    return `<span class="br-h">Bahaya-risiko di grup ini — identifikasi spesifik (${E(wKode(S.w))}; status di ${o.live.size} detail lokasi)</span>
      <div class="dr-mute" style="margin-bottom:4px">Status tiap bahaya hanya dari bukti spesifik: label Hazard/Inspeksi dan butir checklist OAK. Deteksi kelompok (OAK/OBS) tidak dipakai di sini, jadi angkanya umumnya lebih rendah daripada baris grup.</div>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl"><thead><tr><th style="min-width:230px">Bahaya-risiko</th><th>Risiko awal</th><th style="min-width:110px">Komposisi detail lokasi</th><th class="r">Terkontrol</th><th class="r">Gap</th><th class="r">Tanpa laporan</th><th>Harian ${NH} hari</th><th>Tekanan gap</th><th>Proyeksi 7 hari</th></tr></thead><tbody>${
      L.slice(0, 200).map((p) => { const days = harian(p.rows, true), n = nrel(p.c);
        return `<tr><td>${E(p.r.bn)}</td><td>${lvTag(p.r.n1)}</td><td>${sb(p.c.ok, p.c.bm, p.c.none)}</td><td class="r">${p.c.ok}</td><td class="r">${p.c.bm}</td><td class="r">${p.c.none}</td>
          <td>${batangHarian(days, n)}</td><td>${selTekanan(days, p.c)}</td><td>${proyeksi(days, n)}</td></tr>`; }).join('')}</tbody></table></div>${L.length > 200 ? `<div class="dr-mute">Menampilkan 200 dari ${L.length} bahaya-risiko.</div>` : ''}`;
  }

  function tabelUmum(o) {
    const rows = R.filter((r) => r.umum && S.kb[r.n1] && r.kel === o.kel && r.grp === o.grp && o.live.has(r.dl));
    if (!rows.length) return '';
    return `<span class="br-h">Bahaya umum (lintas ≥${BR.umumMin} kelompok aktivitas dalam grup lokasi ini; tidak masuk persen kelompok; identifikasi spesifik)</span><div class="dr-scroll"><table class="pr-tbl dr-tbl"><thead><tr><th>Bahaya-risiko</th><th>Risiko awal</th><th>Komposisi detail lokasi</th><th class="r">Terkontrol</th><th class="r">Gap</th><th class="r">Tanpa laporan</th></tr></thead><tbody>${
      perBahaya(rows).map((p) => `<tr><td>${E(p.r.bn)}</td><td>${lvTag(p.r.n1)}</td><td>${sb(p.c.ok, p.c.bm, p.c.none)}</td><td class="r">${p.c.ok}</td><td class="r">${p.c.bm}</td><td class="r">${p.c.none}</td></tr>`).join('')}</tbody></table></div>`;
  }

  function caraMembaca() {
    return `<details class="pr-card" style="padding:0"><summary class="pr-card-head" style="cursor:pointer"><div><b>Cara membaca, metode, dan batasan</b></div></summary><div class="pr-card-body"><ol class="br-ol">
      <li><b>Baseline:</b> satu baris = kelompok aktivitas (proses bisnis HIRA) × detail lokasi × bahaya-risiko. Hanya bahaya yang bisa diidentifikasi lewat SAP. Detail lokasi yang dimiliki dua PJA dihitung di masing-masing, jadi angka antar PJA tidak boleh dijumlahkan.</li>
      <li><b>Periode:</b> minggu kalender Minggu–Sabtu; minggu berjalan masih sebagian. Filter tingkat risiko awal mengubah pembagi.</li>
      <li><b>Sumber identifikasi (digabung):</b> (a) Observasi/OAK yang aktivitasnya terpetakan ke kelompok di detail lokasi itu menandai seluruh bahaya kelompok tersebut; (b) label Hazard/Inspeksi yang cocok ke bahaya — label spesifik (cocok ke ≤2 kelompok di lokasi itu) juga menandai kelompoknya; (c) butir checklist OAK yang cocok ke bahaya. Karena satu laporan OAK/OBS menutup semua baris kelompok, persen terkontrol di tingkat kelompok lebih tinggi daripada di tabel bahaya-risiko (yang hanya memakai bukti spesifik).</li>
      <li><b>Dua status:</b> terkontrol (ada laporan, tanpa masalah) dan gap. Gap = laporan bermasalah (belum closed pada akhir minggu — REJECT dihitung belum closed; berulang ≥${BR.ambangUlang} laporan bahaya sama di lokasi sama dalam seminggu; OAK Stop Pekerjaan; butir checklist tidak sesuai) + tanpa laporan. Terkontrol menurut SAP bukan bukti pengendalian di lapangan.</li>
      <li><b>Bahaya umum:</b> teks bahaya yang sama di ≥${BR.umumMin} kelompok dalam satu grup lokasi ditampilkan terpisah dan tidak masuk pembagi.</li>
      <li><b>Flag:</b> per kelompok aktivitas di satu detail lokasi, dengan alasan, masa berlaku, dan persetujuan orang lain. Flag nonaktif yang disetujui dan beririsan dengan minggu terpilih mengeluarkan barisnya dari pembagi. Bar harian dan proyeksi belum memperhitungkan flag.</li>
      <li><b>Tekanan gap</b> = yang lebih berat dari: (1) porsi baris tidak terkontrol 4 minggu berturut-turut (≥${Math.round(100 * BR.tekanan.u4_tinggi)}% Tinggi, ≥${Math.round(100 * BR.tekanan.u4_sedang)}% Sedang); (2) hari dengan laporan bermasalah dalam 7 hari terakhir (≥${BR.tekanan.hari_tinggi} hari atau ${BR.tekanan.beruntun_tinggi} hari beruntun = Tinggi, ≥${BR.tekanan.hari_sedang} = Sedang). Penanda perhatian, bukan prediksi; belum diuji terhadap insiden.</li>
      <li><b>Proyeksi 7 hari:</b> rata-rata persen gap harian 7 hari terakhir, datar per hari — tingkat gap yang mungkin bertahan, bukan ramalan hari tertentu. Karena laporan SAP tidak terbit tiap hari, persen gap harian lebih tinggi daripada status mingguan.</li>
      <li><b>Tanda merah insiden:</b> hanya pada bahaya yang label root cause insidennya (Hazard_Related_Incident) terpetakan ke bahaya itu di detail lokasi insiden.</li>
      <li>Pemetaan HIRA ↔ SAP berbasis kata kunci (draf) dan belum dikoreksi HSE.</li></ol></div></details>`;
  }

  // ------------------------------------------------------------ event
  function pasang() {
    $('#br-w').addEventListener('change', (e) => { S.w = +e.target.value; render(); });
    $('#br-prev').addEventListener('click', () => { if (S.w < NW - 1) { S.w++; render(); } });
    $('#br-next').addEventListener('click', () => { if (S.w > 0) { S.w--; render(); } });
    document.querySelectorAll('.br-kb').forEach((c) => c.addEventListener('change', () => { S.kb[c.dataset.k] = c.checked ? 1 : 0; render(); }));
    document.querySelectorAll('.br-wc').forEach((c) => c.addEventListener('click', () => { S.w = +c.dataset.w; render(); }));
    document.querySelectorAll('tr.br-a').forEach((t) => t.addEventListener('click', () => { S.aopen[t.dataset.a] = !S.aopen[t.dataset.a]; render(); }));
    document.querySelectorAll('tr.br-g').forEach((t) => t.addEventListener('click', () => { S.gopen[t.dataset.g] = !S.gopen[t.dataset.g]; render(); }));
    document.querySelectorAll('[data-flag]').forEach((b) => b.addEventListener('click', (e) => { e.stopPropagation(); bukaFlag(b.dataset.flag, b.dataset.kel); }));
    document.querySelectorAll('tr[data-pja]').forEach((t) => t.addEventListener('click', () => { if (t.dataset.pja !== ID_PJA) location.href = URL_PJA.replace('__ID__', t.dataset.pja); }));
    const semua = $('#br-semua');
    if (semua) semua.addEventListener('click', () => { S.semua = !S.semua; render(); });
    const mg = $('#br-mg');
    mg.addEventListener('toggle', () => { if (mg.open && !S.mg) { S.mg = true; $('#br-mg-isi').innerHTML = tabelMgmt(); pasangMgmt(); } else if (!mg.open) S.mg = false; });
  }
  function pasangMgmt() {
    document.querySelectorAll('tr[data-pja]').forEach((t) => t.addEventListener('click', () => { if (t.dataset.pja !== ID_PJA) location.href = URL_PJA.replace('__ID__', t.dataset.pja); }));
  }

  // ------------------------------------------------------------ panel flag
  function bukaFlag(dl, kel) {
    const l = idx.dl[dl] || {};
    const riwayat = flagKel(dl, kel);
    $('#dr-panel-kicker').textContent = `${l.lokasi || ''} · ${l.detlok || ''}`;
    $('#dr-panel-judul').textContent = KEL[kel] || kel;
    const status = nonaktif(dl, kel, S.w) ? '<span class="dr-tag st-T">Nonaktif pada minggu ini</span>' : '<span class="dr-mute">Ada aktivitas (bawaan)</span>';
    const isi = !FL.tersedia
      ? '<div class="pr-empty">Fitur flag belum aktif: tabel <code>peta_risiko_flag_aktivitas</code> belum dimigrasi.</div>'
      : `<div class="pr-card-body"><p class="dr-mute" style="margin:0 0 10px">Status: ${status}. Flag nonaktif yang disetujui mengeluarkan semua baris kelompok ini di detail lokasi ini dari pembagi pada minggu yang beririsan.</p>
          <form id="dr-flag-form" class="dr-flag-form">
            <label>Status <select name="status"><option value="NONAKTIF">Tidak ada aktivitas (nonaktif)</option><option value="AKTIF">Ada aktivitas</option></select></label>
            <label>Mulai <input type="date" name="mulai" value="${HARI_INI}" required></label>
            <label>Selesai <input type="date" name="selesai"></label>
            <label style="grid-column:1/-1">Alasan <textarea name="alasan" rows="2" minlength="10" required placeholder="Mis. pekerjaan dihentikan sementara karena …"></textarea></label>
            <div style="grid-column:1/-1;display:flex;gap:10px;align-items:center"><button type="submit" class="pr-back">Ajukan flag</button><span id="dr-flag-msg" class="dr-mute"></span></div>
          </form></div>
        ${riwayat.map((f) => `<div class="pr-entry"><div class="pr-entry-top"><strong>${f.status === 'NONAKTIF' ? 'Nonaktif' : 'Aktif'} · ${E(f.mulai)} s/d ${E(f.selesai || 'seterusnya')}</strong>
            <span class="dr-tag ${f.review === 'APPROVED' ? 'st-K' : f.review === 'REJECTED' ? 'st-L' : 'st-X'}">${f.review === 'APPROVED' ? 'Disetujui' : f.review === 'REJECTED' ? 'Ditolak' : 'Menunggu review'}</span></div>
            <p>${E(f.alasan)}</p><p>Diajukan ${E(f.pengaju || '–')} · ${E(f.dibuat)}${f.reviewer ? ` · direview ${E(f.reviewer)} ${E(f.direview)}` : ''}${f.catatan ? ` — ${E(f.catatan)}` : ''}</p>
            ${f.review === 'PENDING' && Number(f.pengajuId) !== Number(FL.saya) ? `<div style="display:flex;gap:8px;margin-top:6px">
              <button type="button" class="pr-back" data-review="${f.id}" data-k="APPROVED">Setujui</button>
              <button type="button" class="pr-back" data-review="${f.id}" data-k="REJECTED">Tolak</button></div>` : ''}</div>`).join('')}`;
    $('#dr-panel-isi').innerHTML = `<div class="pr-card"><div class="pr-card-head"><div><b>Flag kelompok aktivitas di detail lokasi</b><small>Berlaku setelah disetujui orang lain (bukan pengaju)</small></div></div>${isi}</div>`;
    pasangFlag(dl, kel);
    document.body.classList.add('is-open');
    $('#dr-panel').setAttribute('aria-hidden', 'false');
    $('#dr-tutup').focus();
  }

  async function kirim(url, body) {
    const res = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(body) });
    const j = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(j.errors ? Object.values(j.errors).flat().join(' ') : (j.message || 'Gagal menyimpan.'));
    return j;
  }
  async function muatUlangFlag(dl, kel) {
    FL = await fetch(URL_FLAG, { headers: { Accept: 'application/json' } }).then((x) => x.json());
    indeksFlag(); render(); bukaFlag(dl, kel);
  }
  function pasangFlag(dl, kel) {
    const form = document.getElementById('dr-flag-form');
    if (form) form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form), msg = document.getElementById('dr-flag-msg');
      msg.textContent = 'Menyimpan…';
      try {
        await kirim(URL_FLAG, { dl, aktivitas: 'kel:' + kel, status: fd.get('status'), mulai: fd.get('mulai'), selesai: fd.get('selesai') || null, alasan: fd.get('alasan') });
        await muatUlangFlag(dl, kel);
      } catch (err) { msg.textContent = err.message; msg.style.color = 'var(--red)'; }
    });
    document.querySelectorAll('[data-review]').forEach((b) => b.addEventListener('click', async () => {
      const catatan = b.dataset.k === 'REJECTED' ? prompt('Alasan menolak:') : '';
      if (b.dataset.k === 'REJECTED' && !catatan) return;
      try { await kirim(URL_REVIEW.replace('__ID__', b.dataset.review), { keputusan: b.dataset.k, catatan }); await muatUlangFlag(dl, kel); }
      catch (err) { alert(err.message); }
    }));
  }
  function tutup() { document.body.classList.remove('is-open'); $('#dr-panel').setAttribute('aria-hidden', 'true'); }
  $('#dr-tutup').addEventListener('click', tutup);
  $('#dr-scrim').addEventListener('click', tutup);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') tutup(); });

  // ------------------------------------------------------------ lampiran
  function personil() {
    const akhirBearc = {};
    D.bearc.forEach((b) => { if (!akhirBearc[b.sid] || akhirBearc[b.sid].mulai < b.mulai) akhirBearc[b.sid] = b; });
    const gabung = (rows) => Object.values(rows.reduce((acc, p) => {
      const k = p.pja_id + '|' + p.sid;
      if (!acc[k]) acc[k] = { ...p, kat: new Set() };
      if (p.kategori) acc[k].kat.add(p.kategori.trim());
      return acc;
    }, {})).map((p) => ({ ...p, kategori: [...p.kat].join(', ') }));
    const bc = gabung(D.personil.filter((p) => p.bc)), mk = gabung(D.personil.filter((p) => !p.bc));
    const tabel = (rows) => rows.length ? `<table class="pr-tbl dr-tbl"><thead><tr><th>Personil</th><th>SAP 14 hari</th><th class="r">Total</th><th class="r">%SAP minggu lalu</th><th>BeARC</th></tr></thead><tbody>${
      rows.map((p) => {
        const tot = p.harian.reduce((a, n) => a + Number(n), 0), mx = Math.max(1, ...p.harian.map(Number)), b = akhirBearc[p.sid];
        return `<tr><td><span class="pr-name" style="font-size:14px">${E(p.nama)}</span><span class="pr-meta">${E(p.sid)} · ${E(p.perusahaan)}${p.kategori ? ' · ' + E(p.kategori) : ''}${!p.bc ? ' · ' + E(p.pja) : ''}</span></td>
          <td><span class="dr-spark" title="${p.harian.join(', ')}">${p.harian.map((n) => Number(n) ? `<i style="height:${(100 * n / mx).toFixed(0)}%"></i>` : '<i class="z"></i>').join('')}</span></td>
          <td class="r dr-num">${F(tot)}</td><td class="r">${!b ? '–' : b.persen === null ? 'N/A' : Number(b.persen).toFixed(0) + '%'}</td>
          <td>${!b ? '<span class="dr-mute">tidak ada data</span>' : Number(b.banned_sap) ? `<span class="dr-tag st-L" title="${E(b.status_banned)}">Banned SAP</span>` : `<span class="dr-tag st-K" title="${E(b.status_banned)}">Achieve dihitung</span>`}</td></tr>`;
      }).join('')}</tbody></table>` : '<div class="pr-empty">Tidak ada personil terdaftar.</div>';
    const cov = D.coverage.map((c) => ({ ...c, l: idx.dl[c.dl] })).filter((c) => c.l).sort((a, b) => Number(a.semua) - Number(b.semua));
    const tanpa = cov.filter((c) => !Number(c.semua)).length;
    $('#dr-personil').innerHTML = `
      <div class="pr-card-body" style="padding-bottom:6px"><b>Personil PJA BC (${bc.length})</b></div>${tabel(bc)}
      <div class="pr-card-body" style="padding-bottom:6px"><b>Personil PJA Mitra (${mk.length})</b></div>${tabel(mk)}
      <div class="pr-card-body" style="padding-bottom:6px"><b>Coverage SAP detail lokasi (${META.jendela} hari)</b><div class="dr-mute">${tanpa} dari ${cov.length} detail lokasi tanpa laporan SAP sama sekali</div></div>
      <div class="dr-scroll" style="max-height:340px;overflow-y:auto"><table class="pr-tbl dr-tbl"><thead><tr><th>Detail lokasi</th><th class="r">Laporan (semua)</th><th class="r">Oleh personil PJA</th></tr></thead><tbody>${
        cov.map((c) => `<tr><td>${E(c.l.detlok)}<span class="pr-meta">${E(c.l.lokasi)}</span></td><td class="r">${Number(c.semua) ? F(c.semua) : '<span class="dr-tag st-T">0</span>'}</td>
          <td class="r">${F(c.dedicated)} <span class="dr-mute">${DR.pct(Number(c.dedicated), Number(c.semua))}</span></td></tr>`).join('')}</tbody></table></div>`;
  }
  function temuan() {
    $('#dr-temuan-sub').textContent = `${F(D.temuanTotal)} temuan open di detail lokasi PJA${D.temuanTotal > D.temuan.length ? ` (ditampilkan ${D.temuan.length} terbaru)` : ''}`;
    $('#dr-temuan').innerHTML = `<thead><tr><th>Tanggal</th><th>Detail lokasi</th><th>Ketidaksesuaian</th><th>Deskripsi</th><th>Status</th><th>PIC</th><th>Janji</th></tr></thead><tbody>${
      D.temuan.map((t) => { const l = idx.dl[t.dl] || {}; return `<tr><td>${E(t.tanggal)}<span class="pr-meta">${E(t.jenis)}</span></td>
        <td>${E(l.detlok)}</td><td>${E(t.ket)}<span class="pr-meta">${E(t.subket)}</span></td><td><div class="pr-clamp">${E(t.deskripsi)}</div></td>
        <td><span class="dr-tag st-G">${E(t.status)}</span></td><td>${E(t.pic)}</td><td>${E(t.janji)}</td></tr>`; }).join('')
      || '<tr><td colspan="7" class="pr-empty">Tidak ada temuan open.</td></tr>'}</tbody>`;
  }
})();
</script>
@endsection
