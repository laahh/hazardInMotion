@extends('peta-risiko-pja._layout-drm')

@section('title', $ringkas['nama'].' · Beranda PJA')

@section('hero-aksi')
  <a class="pr-back" href="{{ route('peta-risiko-pja.index', ['tab' => 'pja', 'site' => $ringkas['site']]) }}"><span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Semua PJA</a>
@endsection

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Peta Risiko PJA</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Beranda PJA · {{ $ringkas['site'] }}</span></p>
  <h1 class="pr-title">{{ $ringkas['nama'] }}</h1>
  <p class="pr-sub" id="dr-sub">Data sampai {{ \Carbon\Carbon::parse($meta['sampai'])->translatedFormat('d M Y') }} · periode minggu kalender (Minggu–Sabtu)</p>
@endsection

@include('peta-risiko-pja.drm._beranda-style')

@section('content')
  <div id="br-isi" class="br-root"><div class="pr-card"><div class="pr-empty">Memuat Beranda PJA…</div></div></div>

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
  const W = DR.BW;
  const TIER = { t: ['Tinggi', '#b3261e'], s: ['Sedang', '#c28f12'], r: ['Rendah', '#6a8f3c'] };
  const BIT = { DET: 1, SPE: 2, CLOSED: 4, ULANG: 8, STOP: 16, TS: 32, INC: 64 };
  const HARI_INI = new Date().toISOString().slice(0, 10);
  const S = { w: 0, kb: { Significant: 1, High: 1, Medium: 0, Low: 0 }, aopen: {}, gopen: {}, semua: false, mg: false };
  let D = null, R = [], FL = { tersedia: false, flag: [] }, NW = 0, NH = 0;
  const idx = {};

  if (!BR) { $('#br-isi').innerHTML = '<div class="pr-card"><div class="pr-empty">Snapshot belum memuat data Beranda PJA. Jalankan ulang <code>php artisan peta-risiko:sync</code>.</div></div>'; return; }
  NW = BR.minggu.length; NH = BR.hari;
  const wUrl = parseInt(new URLSearchParams(location.search).get('w'), 10);
  S.w = wUrl >= 0 && wUrl < NW ? wUrl : DR.wDefault(BR.minggu, META.sampai);

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
  const wKode = (i) => 'W' + DR.wNo(BR.minggu[i]);
  const berjalan = (i) => i === 0 && !DR.penuh0(BR.minggu[0], META.sampai);
  const wLabel = (i) => `${wKode(i)} · ${pendek(BR.minggu[i])} – ${pendek(tambahHari(BR.minggu[i], 6))}${berjalan(i) ? ' (berjalan, s.d. ' + pendek(META.sampai) + ')' : ''}`;
  const hariKe = (i) => tambahHari(BR.hari0, i);
  const sejak = (s) => { const n = Math.round((tgl(META.sampai) - tgl(s)) / 864e5); return n <= 0 ? 'hari ini' : n + ' hari lalu'; };
  const terakhir = (s) => !s || s === '1970-01-01' ? '<span class="dr-mute">belum ada</span>' : `${pendek(s)} <span class="dr-mute">(${sejak(s)})</span>`;
  const pct = (a, b) => b ? (100 * a / b).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%' : '–';
  const urai = (s) => { const o = {}; if (s) s.split(',').forEach((p) => { const [i, b] = p.split(':'); o[+i] = (o[+i] || 0) | +b; }); return o; };
  const lvTag = (l) => `<span class="br-pill" style="background:${LVC[l] || '#888'}">${E(l)}</span>`;

  /** Sama dengan DrmBeranda::status() di PHP: ['ok'|'bm'|'none', bit masalah]. Gap = belum closed | berulang | OAK Stop. */
  function statusBit(k, s) {
    const m = (s & (BIT.CLOSED | BIT.ULANG)) | (k & BIT.STOP);
    if (m) return ['bm', m];
    return [(k & BIT.DET) || (s & BIT.SPE) ? 'ok' : 'none', 0];
  }
  const baru = () => ({ ok: 0, bm: 0, none: 0, g2: 0, g3: 0, g4: 0, excl: 0, u4: 0 });
  function tambah(c, st, m) {
    c[st]++;
    if (st === 'bm') { if (m & BIT.CLOSED) c.g2++; if (m & BIT.ULANG) c.g3++; if (m & BIT.STOP) c.g4++; }
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

  /**
   * Satuan KETAT untuk kartu, ringkasan, dan peta panas: satu baris = kelompok aktivitas × bahaya-risiko,
   * dihitung sekali walau ada di beberapa detail lokasi. Terkontrol penuh bila semua detail lokasi aktifnya
   * terkontrol; sebagian bila sebagian; hanya bermasalah bila tak ada yang terkontrol tapi ada laporan
   * bermasalah; sisanya tanpa laporan. Sama dengan DrmBeranda::ringkasMgmt() di PHP.
   */
  function ketat(w, pakaiFlag = true) {
    const Rk = {}, X = {};
    R.forEach((r) => {
      if (r.umum || !S.kb[r.n1]) return;
      const key = r.kel + '|' + r.hid;
      if (pakaiFlag && nonaktif(r.dl, r.kel, w)) { X[key] = 1; return; }
      const u = Rk[key] || (Rk[key] = { kel: r.kel, bn: r.bn, n1: r.n1, n: 0, ok: 0, bm: 0, m: 0 });
      const [st, m] = statusBit(r.K.w[w] || 0, r.Sw[w] || 0);
      u.n++;
      if (st === 'ok') u.ok++; else if (st === 'bm') { u.bm++; u.m |= m; }
    });
    const T = { tot: 0, full: 0, part: 0, gaponly: 0, none: 0, g2: 0, g3: 0, g4: 0, excl: 0 }, A = {}, list = Object.values(Rk);
    list.forEach((u) => {
      u.cat = u.ok === u.n ? 'full' : u.ok > 0 ? 'part' : u.bm > 0 ? 'gaponly' : 'none';
      T.tot++; T[u.cat]++;
      if (u.m & BIT.CLOSED) T.g2++; if (u.m & BIT.ULANG) T.g3++; if (u.m & BIT.STOP) T.g4++;
      const a = A[u.kel] || (A[u.kel] = { tot: 0, full: 0, part: 0, gaponly: 0, none: 0 });
      a.tot++; a[u.cat]++;
    });
    T.excl = Object.keys(X).filter((k) => !Rk[k]).length;
    return { T, A, list };
  }
  const gapKetat = (t) => t.part + t.gaponly + t.none;

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
  const sb4 = (ok, pt, pb, no) => DR.sb4(ok, pt, pb, no);
  function batangHarian(days, tot) {
    const mx = Math.max(1, tot), H = (x) => (100 * x / mx).toFixed(1) + '%';
    return `<div class="br-mb">${days.map((e, i) => {
      const none = Math.max(0, tot - e.ok - e.bm);
      return `<div class="br-mc" title="${pendek(hariKe(i))}: ${e.ok} terkontrol; gap ${e.bm + none} (${e.bm} laporan bermasalah + ${none} tanpa laporan hari itu)${e.inc ? ' | INSIDEN' : ''}">${e.inc ? '<b></b>' : ''}<i style="height:${H(none)};background:${W.none}"></i><i style="height:${H(e.bm)};background:${W.bm}"></i><i style="height:${H(e.ok)};background:${W.ok}"></i></div>`;
    }).join('')}</div>`;
  }
  function selTekanan(days, c) {
    const f = ciri(days), t = TIER[tekanan(f, c)], tot = nrel(c), sh = tot ? Math.round(100 * c.u4 / tot) : 0;
    return `<span class="br-pill" style="background:${t[1]}" title="${sh}% tidak terkontrol 4 minggu berturut-turut; laporan bermasalah ${f.c7} dari 7 hari terakhir">${t[0]}</span>`;
  }
  const EW = { alpha: 0.3, minTitik: 5, minResidu: 8, rentang: [-0.111, 0.073], lebarMin: 0.02, ambang: 0.03 };
  const kuantil = (a, q) => { const b = [...a].sort((x, y) => x - y), k = (b.length - 1) * q, lo = Math.floor(k); return b[lo] + (b[Math.ceil(k)] - b[lo]) * (k - lo); };
  /** EWMA atas % gap mingguan: s0 = y0; residu = y − s; s = 0,3·y + 0,7·s. Prakiraan = s akhir. */
  function ewma(y) {
    let s = y[0];
    const res = [];
    for (let i = 1; i < y.length; i++) { res.push(y[i] - s); s = EW.alpha * y[i] + (1 - EW.alpha) * s; }
    const [a, b] = res.length >= EW.minResidu ? [kuantil(res, 0.1), kuantil(res, 0.9)] : EW.rentang;
    const jepit = (x) => Math.min(1, Math.max(0, x));
    return { f: s, lo: jepit(s + Math.min(a, -EW.lebarMin)), hi: jepit(s + Math.max(b, EW.lebarMin)) };
  }
  /** Seri % gap per minggu penuh (terlama → minggu terpilih) untuk baris aktif. */
  function seriGap(rows, spesifik) {
    const y = [], akhir = Math.max(S.w, berjalan(0) ? 1 : 0);
    if (!rows.length) return y;
    for (let i = NW - 1; i >= akhir; i--) {
      let ok = 0;
      rows.forEach((r) => { if (statusBit(spesifik ? 0 : (r.K.w[i] || 0), r.Sw[i] || 0)[0] === 'ok') ok++; });
      y.push(1 - ok / rows.length);
    }
    return y;
  }
  function proyeksi(rows, spesifik = false) {
    const y = seriGap(rows, spesifik);
    if (y.length < EW.minTitik) return '<span class="dr-mute">data kurang</span>';
    const e = ewma(y), akhir = y[y.length - 1], P = (x) => Math.round(100 * x);
    const arah = e.f - akhir > EW.ambang ? 'naik' : akhir - e.f > EW.ambang ? 'turun' : 'stabil';
    const isi = y.map((v, i) => `<div title="${wKode(NW - 1 - i)}: gap ${P(v)}%"><i style="height:${Math.max(2, v * 34).toFixed(0)}px;background:${W.none}"></i></div>`).join('')
      + `<div title="Prakiraan minggu depan: ${P(e.f)}% (80%: ${P(e.lo)}–${P(e.hi)}%)"><i style="height:${Math.max(2, e.f * 34).toFixed(0)}px;background:${W.bm}"></i></div>`;
    return `<div class="br-pj" title="EWMA % gap mingguan: prakiraan ${P(e.f)}% (rentang 80% ${P(e.lo)}–${P(e.hi)}%), ${arah} dibanding minggu terakhir ${P(akhir)}%"><div class="br-pb">${isi}</div><b>~${P(e.f)}%<br><span class="dr-mute" style="font-weight:600;font-size:11px;white-space:nowrap">${P(e.lo)}–${P(e.hi)}% · ${arah}</span></b></div>`;
  }
  const legenda = () => `<div class="dr-legend"><span><i style="--c:${W.ok}"></i>Terkontrol</span><span><i style="--c:${W.bm}"></i>Laporan bermasalah</span><span><i style="--c:${W.none}"></i>Tanpa laporan</span><span><i style="--c:#ff1a2e"></i>Insiden</span></div>`;

  // ------------------------------------------------------------ render
  function render() {
    const y = window.pageYOffset;
    idx.nonaktif = {};
    const H = hitung(S.w), U = ketat(S.w), UT = U.T, U0 = ketat(S.w, false).T, GU = gapKetat(UT);
    const kpi = (ac, kc, judul, angka, isi) => `<div class="br-kpi" style="--ac:${ac};--kc:${kc}"><small>${judul}</small><b>${angka}</b><em>${isi}</em></div>`;
    const isi = $('#br-isi');
    isi.classList.toggle('br-awal', !S.sudah);
    S.sudah = true;
    isi.innerHTML = filterBar(UT, GU)
      + `<div class="br-kpis">
        ${kpi('var(--navy)', 'var(--navy)', 'Baris baseline unik · ' + E(wKode(S.w)), F(UT.tot), `kelompok aktivitas × bahaya-risiko${UT.excl ? ` · ${F(UT.excl)} dikecualikan flag` : ''}`)}
        ${kpi(W.ok, 'var(--green-d)', 'Terkontrol penuh menurut SAP', pct(UT.full, UT.tot), `${F(UT.full)} baris${UT.excl ? ` · tanpa flag ${pct(U0.full, U0.tot)}` : ''}`)}
        ${kpi(W.part, '#9A7300', 'Terkontrol sebagian', pct(UT.part, UT.tot), `${F(UT.part)} baris · gap total ${pct(GU, UT.tot)}`)}
        ${kpi(W.none, W.bm, 'Tanpa laporan', pct(UT.none, UT.tot), `${F(UT.none)} baris${UT.gaponly ? ` · ${F(UT.gaponly)} hanya bermasalah` : ''}`)}
      </div>`
      + perluAksi(U) + petaPanas() + ringkasan(H, U) + panelRc()
      + `<details class="pr-card" id="br-mg"${S.mg ? ' open' : ''}><summary class="pr-card-head" style="cursor:pointer"><div><b>Perbandingan antar PJA (untuk manajemen)</b><small>Tanpa flag · klik baris untuk membuka PJA</small></div></summary><div class="pr-card-body" id="br-mg-isi">${S.mg ? tabelMgmt() : ''}</div></details>`
      + `<div class="pr-card"><div class="pr-card-head"><div><b>Tren mingguan</b><small>Klik batang untuk pindah minggu</small></div></div><div class="pr-card-body">${tren()}</div></div>`
      + hierarki(H) + caraMembaca();
    S.baru = '';
    pasang();
    window.scrollTo(0, y);
  }

  function filterBar(UT, GU) {
    return `<div class="br-bar-wrap"><div class="br-bar">
        <span class="lbl">Periode</span>
        <select id="br-w" aria-label="Pilih minggu">${BR.minggu.map((_, i) => `<option value="${i}"${i === S.w ? ' selected' : ''}>${E(wLabel(i))}</option>`).join('')}</select>
        <span class="br-nav"><button type="button" id="br-prev" aria-label="Minggu sebelumnya" ${S.w >= NW - 1 ? 'disabled' : ''}>‹</button><button type="button" id="br-next" aria-label="Minggu berikutnya" ${S.w <= 0 ? 'disabled' : ''}>›</button></span>
        <span class="br-sep"></span>
        <span class="lbl">Risiko awal</span>
        ${LV.map((l) => `<button type="button" class="br-lv br-kb" data-k="${l}" aria-pressed="${S.kb[l] ? 'true' : 'false'}" style="--c:${LVC[l]}"><i></i>${l}</button>`).join('')}
        <span class="br-sum"><span>Terkontrol penuh <b style="--c:var(--green-d)">${pct(UT.full, UT.tot)}</b></span><span>Gap <b style="--c:${W.bm}">${pct(GU, UT.tot)}</b></span></span>
      </div></div>`;
  }

  // ------------------------------------------------- perlu aksi & peta panas
  const insiden28 = () => D.insiden.filter((i) => i.tanggal >= BR.hari0 && i.tanggal <= META.sampai).sort((a, b) => b.tanggal.localeCompare(a.tanggal));
  function kartuAksi(warna, judul, besar, isi, ke, teksKe) {
    return `<div class="br-ac" style="--ac:${warna}"><small>${judul}</small><span class="big">${besar}</span><div class="dr-mute">${isi}</div>${ke ? `<a data-go="${ke}">${teksKe} ›</a>` : ''}</div>`;
  }
  function perluAksi(U) {
    const best = Object.entries(U.A).filter(([, a]) => a.tot).sort((x, y) => y[1].none - x[1].none || y[1].tot - x[1].tot)[0];
    const fl = U.list.filter((u) => u.m), flS = fl.filter((u) => u.n1 === 'Significant'), top = flS[0] || fl[0];
    const inc = insiden28(), hijau = W.ok, kecil = (t) => ` <span>${t}</span>`;
    const c1 = best && best[1].none
      ? kartuAksi(W.bm, 'Kelompok dengan tanpa laporan terbanyak', F(best[1].none) + kecil('dari ' + F(best[1].tot) + ' baris'), E(KEL[best[0]] || best[0]), 'br-kel', 'Lihat kelompok aktivitas')
      : kartuAksi(hijau, 'Kelompok dengan tanpa laporan terbanyak', '0', 'Semua kelompok aktivitas punya laporan.');
    const c2 = kartuAksi(fl.length ? 'var(--red)' : hijau, 'Baris dengan laporan bermasalah', F(fl.length) + (flS.length ? kecil(flS.length + ' Significant') : ''),
      top ? E(top.bn) + ' · ' + E(KEL[top.kel] || top.kel) : 'Tidak ada.', fl.length ? 'br-perh' : '', 'Lihat bahaya-risiko');
    const c3 = kartuAksi(inc.length ? '#E8112D' : hijau, `Insiden ${NH} hari terakhir`, String(inc.length),
      inc.length ? E(inc[0].detlok) + ' · ' + pendek(inc[0].tanggal) : 'Tidak ada insiden pada detail lokasi PJA ini.', inc.length ? 'br-rc' : '', 'Lihat root cause');
    return `<div><span class="br-h">Perlu aksi</span><div class="br-act">${c1}${c2}${c3}</div></div>`;
  }

  function petaPanas() {
    const A = {};
    for (let i = NW - 1; i >= 0; i--) {
      Object.entries(ketat(i).A).forEach(([k, a]) => { (A[k] ||= {})[i] = a; });
    }
    const v = (a) => (a && a.tot ? a.full / a.tot : null);
    const L = Object.entries(A).sort((x, y) => (v(x[1][S.w]) ?? 2) - (v(y[1][S.w]) ?? 2) || ((y[1][S.w] || {}).tot || 0) - ((x[1][S.w] || {}).tot || 0));
    const wk = Array.from({ length: NW }, (_, j) => NW - 1 - j);
    const kepala = `<div class="pr-card-head"><div><b>Heatmap: % terkontrol penuh per kelompok aktivitas × minggu</b><small>0%<span class="br-hlg"></span>100%</small></div></div>`;
    if (!L.length) return `<div class="pr-card">${kepala}<div class="pr-empty">Tidak ada baris baseline pada filter ini.</div></div>`;
    return `<div class="pr-card">${kepala}<div class="pr-card-body dr-scroll"><table class="br-hm"><thead><tr><th style="text-align:left;min-width:220px">Kelompok aktivitas</th>${wk.map((i) => `<th${i === S.w ? ' style="color:#1d2b4f"' : ''}>${wKode(i)}${berjalan(i) ? '*' : ''}</th>`).join('')}</tr></thead><tbody>${
      L.map(([k, per]) => `<tr><td><b>${E(KEL[k] || k)}</b> <span class="dr-mute">${F((per[S.w] || {}).tot || 0)} baris</span></td>${wk.map((i) => {
        const a = per[i], x = v(a);
        if (x === null) return `<td class="c${i === S.w ? ' sel' : ''}" data-w="${i}" style="background:#f1f3f5;color:#9aa3ad">–</td>`;
        return `<td class="c${i === S.w ? ' sel' : ''}" data-w="${i}" style="background:hsl(${(8 + 120 * x).toFixed(0)},55%,${(86 - 26 * x).toFixed(0)}%);color:${x >= 0.75 ? '#fff' : '#222'}" title="${E(KEL[k] || k)} · ${E(wLabel(i))}: ${a.full} dari ${a.tot} baris terkontrol penuh">${Math.round(100 * x)}</td>`;
      }).join('')}</tr>`).join('')}</tbody></table></div></div>`;
  }

  // ------------------------------------------------- ringkasan & tindak lanjut
  function ringkasan(H, U) {
    const UT = U.T;
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
    const inc = insiden28();
    const sig = {};
    U.list.forEach((u) => { if (u.cat === 'none' && u.n1 === 'Significant') sig[u.bn] = (sig[u.bn] || 0) + 1; });
    const sigL = Object.entries(sig).sort((a, b) => b[1] - a[1]), nSig = sigL.reduce((s, e) => s + e[1], 0);
    const akt = Object.entries(U.A).filter(([, a]) => a.tot).sort((x, y) => (y[1].tot - y[1].full) - (x[1].tot - x[1].full) || y[1].tot - x[1].tot).slice(0, 6);
    const tl = [], C = { none: W.bm, part: W.part, lama: 'var(--mute-2)', bm: 'var(--red)', inc: '#E8112D' };
    if (UT.none) tl.push([C.none, `<b>Tanpa laporan:</b> ${F(UT.none)} baris. Konfirmasi aktivitasnya; bila tidak berjalan, ajukan flag.`]);
    if (UT.part) tl.push([C.part, `<b>Terkontrol sebagian:</b> ${F(UT.part)} baris. Lengkapi pengawasan di detail lokasi yang kosong.`]);
    if (hi) tl.push([C.none, `<b>Konfirmasi aktivitas:</b> ${hi} dari ${groups.length} kelompok × grup tidak terkontrol 4 minggu berturut-turut.`]);
    if (lama.length) tl.push([C.lama, `<b>Tanpa laporan SAP ≥${BR.lamaHari} hari:</b> ${lama.length} detail lokasi${lama.length ? ` (${lama.slice(0, 3).map((x) => E(x.l.detlok)).join(', ')}${lama.length > 3 ? ', …' : ''})` : ''}.`]);
    if (UT.g2) tl.push([C.bm, `<b>Tutup temuan:</b> ${F(UT.g2)} baris belum closed.`]);
    if (UT.g3) tl.push([C.bm, `<b>Laporan berulang:</b> ${F(UT.g3)} baris. Buat perbaikan permanen.`]);
    if (UT.g4) tl.push([C.bm, `<b>OAK Stop Pekerjaan:</b> ${F(UT.g4)} baris. Verifikasi sebelum lanjut.`]);
    if (nSig) tl.push([C.bm, `<b>Significant tanpa laporan:</b> ${F(nSig)} baris (${sigL.slice(0, 3).map(([t]) => E(t)).join(', ')}${sigL.length > 3 ? ', …' : ''}).`]);
    if (inc.length) tl.push([C.inc, `<b>Insiden ${NH} hari:</b> ${inc.length} kejadian (${inc.slice(0, 3).map((i) => E(i.detlok) + ' ' + pendek(i.tanggal)).join(', ')}).`]);

    return `<div class="pr-card"><div class="br-rhead"><b class="t">Ringkasan gap dan tindak lanjut · ${E(wKode(S.w))}</b>
      ${sb4(UT.full, UT.part, UT.gaponly, UT.none).replace('class="br-sb"', 'class="br-sb big"')}
      <div class="br-chips">${[[W.ok, 'terkontrol penuh', UT.full], [W.part, 'sebagian', UT.part], ...(UT.gaponly ? [[W.bm, 'hanya bermasalah', UT.gaponly]] : []), [W.none, 'tanpa laporan', UT.none]]
        .map(([c, l, x]) => `<span><i style="background:${c}"></i><b>${pct(x, UT.tot)}</b> ${l} <span class="dr-mute">(${F(x)})</span></span>`).join('')}</div></div><div class="pr-card-body">
      <div class="br-two"><div><span class="br-h">Kelompok aktivitas dengan gap terbanyak</span><table class="pr-tbl dr-tbl"><thead><tr><th>Kelompok aktivitas</th><th style="min-width:110px">Komposisi</th><th class="r">Baris</th><th class="r">Terkontrol penuh</th><th class="r">Sebagian</th><th class="r">Tanpa laporan</th></tr></thead><tbody>${
        akt.map(([k, a]) => `<tr><td>${E(KEL[k] || k)}</td><td>${sb4(a.full, a.part, a.gaponly, a.none)}</td><td class="r">${F(a.tot)}</td><td class="r"><b>${pct(a.full, a.tot)}</b></td><td class="r">${F(a.part)}</td><td class="r">${F(a.none)}</td></tr>`).join('') || '<tr><td colspan="6" class="pr-empty">Tidak ada baris.</td></tr>'}</tbody></table></div>
        <div><span class="br-h">Detail lokasi dengan laporan bermasalah terbanyak</span>${topDl.length ? `<table class="pr-tbl dr-tbl"><thead><tr><th>Detail lokasi</th><th class="r">Baris bermasalah</th><th>Kelompok</th></tr></thead><tbody>${
          topDl.map(([dl, a]) => { const l = idx.dl[dl] || {}; return `<tr><td>${E(l.lokasi)} · <b>${E(l.detlok)}</b></td><td class="r">${a.n}</td><td class="dr-mute">${[...a.kel].slice(0, 3).map((k) => E(KEL[k] || k)).join(', ')}</td></tr>`; }).join('')}</tbody></table>` : '<div class="dr-mute">Tidak ada laporan bermasalah pada periode ini.</div>'}</div></div>
      <span class="br-h">Tindak lanjut yang perlu dibuat PJA</span>${tl.length ? `<div class="br-tis">${tl.map(([c, t], i) => `<div class="br-ti" style="--c:${c}"><span class="br-tn">${i + 1}</span><div>${t}</div></div>`).join('')}</div>` : '<div class="dr-mute">Tidak ada tindak lanjut otomatis untuk periode ini.</div>'}
      <div id="br-perh">${perhatian(H)}</div>
      </div></div>`;
  }

  function perhatian(H) {
    const Bm = {}, Z = {}, KO = { Significant: 0, High: 1, Medium: 2, Low: 3 };
    Object.values(H.AG).forEach((o) => o.rows.forEach((r) => {
      const s = r.Sw[S.w] || 0, [st, m] = statusBit(0, s), inc = Object.values(r.Sh).some((b) => b & BIT.INC);
      const key = r.bn + '|' + r.n1;
      if (st === 'bm' || inc) {
        const b = Bm[key] || (Bm[key] = { t: r.bn, kb: r.n1, g2: 0, g3: 0, inc: 0, f: {} });
        if (m & BIT.CLOSED) b.g2++; if (m & BIT.ULANG) b.g3++; if (inc) b.inc++;
        b.f[r.kel] = (b.f[r.kel] || 0) + 1;
      }
      if (st === 'none' && (r.n1 === 'Significant' || r.n1 === 'High')) {
        const z = Z[key] || (Z[key] = { t: r.bn, kb: r.n1, n: 0, f: {} }); z.n++; z.f[r.kel] = (z.f[r.kel] || 0) + 1;
      }
    }));
    const kel3 = (f) => Object.entries(f).sort((a, b) => b[1] - a[1]).slice(0, 3).map(([k, v]) => E(KEL[k] || k) + ' (' + v + ')').join('; ');
    const aksi = (b) => [b.g2 && 'tutup temuan di SAP', b.g3 && 'perbaikan permanen', b.inc && 'cek padanan root cause insiden'].filter(Boolean).join('; ') || '–';
    const BL = Object.values(Bm).sort((a, b) => KO[a.kb] - KO[b.kb] || (b.g2 + b.g3 + b.inc) - (a.g2 + a.g3 + a.inc));
    const ZL = Object.values(Z).sort((a, b) => KO[a.kb] - KO[b.kb] || b.n - a.n);
    const baris = (b) => `<tr><td>${E(b.t)}</td><td>${lvTag(b.kb)}</td><td class="dr-mute">${kel3(b.f)}</td><td class="dr-mute">${b.g2 ? 'belum closed ' + b.g2 + '<br>' : ''}${b.g3 ? 'berulang ' + b.g3 + '<br>' : ''}${b.inc ? '<span class="br-red">terkait insiden</span>' : ''}</td><td class="dr-mute">${aksi(b)}</td></tr>`;
    const kepala = '<thead><tr><th style="min-width:220px">Bahaya-risiko</th><th>Risiko awal</th><th style="min-width:200px">Kelompok aktivitas (jumlah baris)</th><th>Jenis gap</th><th>Tindak lanjut</th></tr></thead>';
    return `<span class="br-h">Bahaya-risiko perlu perhatian</span>${
      BL.length ? `<div class="dr-scroll"><table class="pr-tbl dr-tbl">${kepala}<tbody>${BL.slice(0, 10).map(baris).join('')}</tbody></table></div>${BL.length > 10 ? `<details style="margin-top:6px"><summary class="dr-mute">Tampilkan ${BL.length - 10} lainnya</summary><div class="dr-scroll"><table class="pr-tbl dr-tbl">${kepala}<tbody>${BL.slice(10).map(baris).join('')}</tbody></table></div></details>` : ''}` : '<div class="dr-mute">Tidak ada bahaya-risiko dengan laporan bermasalah atau keterkaitan insiden pada periode ini.</div>'}
      <span class="br-h">Significant/High belum teridentifikasi spesifik</span>${
      ZL.length ? `<div class="dr-scroll"><table class="pr-tbl dr-tbl"><thead><tr><th style="min-width:220px">Bahaya-risiko</th><th>Risiko awal</th><th class="r">Jumlah baris</th><th>Kelompok aktivitas terbanyak</th></tr></thead><tbody>${
        ZL.slice(0, 10).map((z) => `<tr><td>${E(z.t)}</td><td>${lvTag(z.kb)}</td><td class="r">${F(z.n)}</td><td class="dr-mute">${kel3(z.f)}</td></tr>`).join('')}</tbody></table></div><div class="dr-mute" style="margin-top:4px">10 teratas dari ${ZL.length}</div>` : '<div class="dr-mute">Semua bahaya-risiko Significant/High teridentifikasi spesifik pada periode ini.</div>'}`;
  }

  // ------------------------------------------------------------ panel insiden
  function panelRc() {
    const rc = D.beranda.rc || [];
    const kepala = '<div class="pr-card-head"><div><b>Insiden dan laporan SAP terkait root cause</b><small>Insiden tahun berjalan di detail lokasi PJA</small></div></div>';
    if (!rc.length) return `<div class="pr-card" id="br-rc">${kepala}<div class="pr-empty">Tidak ada insiden tahun ini dengan root cause terpetakan di detail lokasi PJA ini.</div></div>`;
    const per = {};
    rc.forEach((r) => (per[r.no] ||= []).push(r));
    const nIt = rc.length, nNo = rc.filter((r) => !r.sebelum && !r.bulan).length;
    return `<div class="pr-card" id="br-rc">${kepala}<div class="pr-card-body"><p style="margin:0 0 8px"><b>${Object.keys(per).length}</b> insiden · <b>${nIt}</b> root cause · <b class="br-red">${nNo}</b> tidak terdeteksi SAP sebelumnya</p>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl"><thead><tr><th>Insiden</th><th>Root cause (label SAP)</th><th class="r">Label sama: 3 bln sebelum</th><th class="r">Bulan kejadian</th><th class="r">Sesudah</th><th class="r">Ketidaksesuaian sama: sebelum / sesudah</th><th>Laporan terakhir (label)</th><th class="r">Belum closed</th></tr></thead><tbody>${
        Object.entries(per).map(([no, l]) => l.map((r, i) => `<tr>${i ? '<td></td>' : `<td><b>#${E(no)}</b><br><span class="dr-mute">${E(r.tanggal)} · ${E(r.kategori)}<br>${E(r.detlok)}</span></td>`}
          <td>${E(r.label)}</td><td class="r">${r.sebelum ? '<b>' + r.sebelum + '</b>' : '<span class="br-red">0</span>'}</td><td class="r">${r.bulan}</td><td class="r">${r.sesudah}</td>
          <td class="r">${r.kSebelum} / ${r.kSesudah}</td><td>${r.terakhir ? E(r.terakhir) : '–'}</td><td class="r">${r.belum ? '<b class="br-red">' + r.belum + '</b>' : 0}</td></tr>`).join('')).join('')}</tbody></table></div>
      </div></div>`;
  }

  // ------------------------------------------------------------ manajemen
  function tabelMgmt() {
    const K = BR.kolomMgmt, ix = Object.fromEntries(K.map((k, i) => [k, i]));
    if (!('tot' in ix)) return '<div class="pr-empty">Snapshot masih format lama. Jalankan ulang <code>php artisan peta-risiko:sync</code>.</div>';
    const jumlah = (obj, f) => LV.forEach((lv) => { if (S.kb[lv]) Object.entries((obj || {})[lv] || {}).forEach(([k, v]) => f(k, v)); });
    const L = BR.mgmt.map((p) => {
      const c = Array(K.length).fill(0), kel = {}, kg = {};
      LV.forEach((lv) => { if (S.kb[lv]) (((p.w || [])[S.w] || {})[lv] || []).forEach((v, i) => { c[i] += v; }); });
      jumlah(p.kelTot, (k, v) => { (kel[k] ||= { t: 0, g: 0 }).t += v; });
      jumlah((p.kelGap || [])[S.w], (k, v) => { (kel[k] ||= { t: 0, g: 0 }).g += v; });
      jumlah(p.kgTot, (k, v) => { (kg[k] ||= { t: 0, u: 0 }).t += v; });
      jumlah((p.u4 || [])[S.w], (k, v) => { (kg[k] ||= { t: 0, u: 0 }).u += v; });
      const kgL = Object.values(kg).filter((x) => x.t);
      const top = Object.entries(kel).filter(([, x]) => x.t).sort((a, b) => b[1].g / b[1].t - a[1].g / a[1].t || b[1].t - a[1].t).slice(0, 2).map(([k]) => KEL[k] || k);
      const tot = c[ix.tot];
      return { p, c, tot, gap: tot ? (tot - c[ix.full]) / tot : 0, hi: kgL.filter((x) => x.u / x.t >= BR.tekanan.u4_tinggi).length, all: kgL.length, top };
    }).filter((e) => e.tot).sort((a, b) => b.gap - a.gap);
    const r = (x) => `<td class="r">${F(x)}</td>`;
    return `      <div class="dr-scroll" style="max-height:560px;overflow:auto"><table class="pr-tbl dr-tbl" style="min-width:1250px"><thead><tr><th>PJA</th><th class="r">Detail lokasi</th><th class="r">Baris (kelompok × bahaya)</th><th style="min-width:150px">Terkontrol penuh</th><th class="r">Gap</th><th class="r">Tanpa laporan</th><th class="r">Sebagian</th><th class="r">Hanya bermasalah</th><th class="r">Belum closed</th><th class="r">Berulang</th><th class="r">OAK Stop</th><th class="r">Kelompok × grup tekanan tinggi</th><th class="r">DL tanpa laporan ${BR.lamaHari} hr+</th><th class="r">Insiden ${NH} hr</th><th style="min-width:200px">Kelompok gap tertinggi</th></tr></thead><tbody>${
      L.map((e) => `<tr data-pja="${E(e.p.id)}" style="cursor:pointer${e.p.id === ID_PJA ? ';background:#eef4fb' : ''}"><td><b>${E(e.p.nama)}</b><br><span class="dr-mute">${E(e.p.site)}</span></td><td class="r">${e.p.ndl}</td>${r(e.tot)}
        <td>${sb4(e.c[ix.full], e.c[ix.part], e.c[ix.gaponly], e.c[ix.none])}<span class="dr-mute">${pct(e.c[ix.full], e.tot)}</span></td><td class="r"><b>${pct(e.tot - e.c[ix.full], e.tot)}</b></td>
        ${r(e.c[ix.none])}${r(e.c[ix.part])}${r(e.c[ix.gaponly])}${r(e.c[ix.closed])}${r(e.c[ix.ulang])}${r(e.c[ix.stop])}
        <td class="r">${e.hi} dari ${e.all}</td><td class="r">${e.p.lama}</td><td class="r">${e.p.insiden || ''}</td><td class="dr-mute">${E(e.top.join(', '))}</td></tr>`).join('')}</tbody></table></div>`;
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
    return `<div class="br-wb">${col.join('')}</div>`;
  }

  // ------------------------------------------------------------ hierarki
  function hierarki(H) {
    const ak = Object.entries(H.A).sort((x, y) => (y[1].c.bm + y[1].c.none) / Math.max(1, nrel(y[1].c)) - (x[1].c.bm + x[1].c.none) / Math.max(1, nrel(x[1].c)) || nrel(y[1].c) - nrel(x[1].c));
    const tampil = S.semua ? ak : ak.filter(([k, a]) => a.c.ok + a.c.bm > 0 || S.aopen[k]);
    const isi = tampil.map(([k, a]) => {
      const n = nrel(a.c), gl = [...a.gl].map((g) => H.AG[g]);
      const hi = gl.filter((o) => tekanan(ciri(harian(o.rows)), o.c) === 't').length;
      const buka = !!S.aopen[k];
      return `<tr class="br-a${buka ? ' open' : ''}" data-a="${E(k)}" style="cursor:pointer"><td><button class="br-cr" type="button" aria-expanded="${buka}" aria-label="Buka kelompok">▸</button><b>${E(KEL[k] || k)}</b><br><span class="dr-mute" style="padding-left:18px">${gl.length} grup lokasi · ${F(n)} baris baseline</span></td>
        <td>${sb(a.c.ok, a.c.bm, a.c.none)}</td><td class="r">${pct(a.c.ok, n)}<br><span class="dr-mute">${F(a.c.ok)}</span></td>
        <td class="r"><b>${pct(a.c.bm + a.c.none, n)}</b></td><td class="r">${a.c.excl || ''}</td>
        <td>${hi ? `<span class="br-pill" style="background:${TIER.t[1]}">${hi} dari ${gl.length} grup</span>` : '<span class="dr-mute">–</span>'}</td></tr>`
        + (buka ? `<tr class="br-sub${S.baru === 'a:' + k ? ' br-buka' : ''}"><td colspan="6" style="padding:8px 10px 12px 22px">${tabelGrup(gl)}</td></tr>` : '');
    }).join('');
    const sisa = ak.length - tampil.length;
    return `<div class="pr-card" id="br-kel"><div class="pr-card-head"><div><b>Kelompok aktivitas → grup lokasi → detail lokasi → bahaya-risiko · ${E(wKode(S.w))}</b>
        <small>Bar harian ${pendek(BR.hari0)} – ${pendek(META.sampai)}</small></div></div>
      ${legenda()}<div class="dr-scroll"><table class="pr-tbl dr-tbl" style="min-width:1100px"><thead><tr><th style="min-width:260px">Kelompok aktivitas</th><th style="min-width:160px">Komposisi</th><th class="r">Terkontrol</th><th class="r">Gap</th><th class="r">Dikecualikan</th><th>Grup tekanan gap tinggi</th></tr></thead><tbody>${isi || '<tr><td colspan="6" class="pr-empty">Tidak ada baris untuk filter ini.</td></tr>'}</tbody></table></div>
      ${sisa || S.semua ? `<div class="pr-card-body"><button type="button" class="pr-back" id="br-semua">${S.semua ? 'Sembunyikan kelompok tanpa laporan SAP minggu ini' : `Tampilkan ${sisa} kelompok lain yang tidak ada laporan SAP minggu ini`}</button></div>` : ''}</div>`;
  }

  function tabelGrup(gl) {
    return `<table class="pr-tbl dr-tbl"><thead><tr><th style="min-width:200px">Grup lokasi</th><th style="min-width:140px">Komposisi</th><th class="r">Terkontrol</th><th class="r">Gap laporan</th><th class="r">Tanpa laporan</th><th>Laporan SAP terakhir</th><th>Harian ${NH} hari</th><th>Tekanan gap</th><th>Proyeksi minggu depan</th></tr></thead><tbody>${
      gl.sort((x, y) => (y.c.bm + y.c.none) / Math.max(1, nrel(y.c)) - (x.c.bm + x.c.none) / Math.max(1, nrel(x.c))).map((o) => {
        const key = o.kel + '|' + o.grp, buka = !!S.gopen[key], days = harian(o.rows), n = nrel(o.c);
        const last = [...o.live].map((d) => (o.rows.find((r) => r.dl === d) || { K: { last: '' } }).K.last).filter(Boolean).sort().pop() || '';
        const ninc = days.filter((x) => x.inc).length, off = o.dls.size - o.live.size;
        return `<tr class="br-g${buka ? ' open' : ''}" data-g="${E(key)}" style="cursor:pointer"><td><button class="br-cr" type="button" aria-expanded="${buka}" aria-label="Buka grup lokasi">▸</button><b>${E(o.grp)}</b><br><span class="dr-mute" style="padding-left:18px">${o.dls.size} detail lokasi${off ? ` (${off} nonaktif)` : ''} · ${F(n)} baris</span></td>
          <td>${sb(o.c.ok, o.c.bm, o.c.none)}</td><td class="r">${F(o.c.ok)}</td><td class="r">${F(o.c.bm)}</td><td class="r">${F(o.c.none)}</td><td>${terakhir(last)}</td>
          <td>${batangHarian(days, n)}${ninc ? `<div class="br-red" style="font-size:12px">insiden di ${ninc} hari</div>` : ''}</td><td>${selTekanan(days, o.c)}</td><td>${proyeksi(o.rows)}</td></tr>`
          + (buka ? `<tr class="br-sub${S.baru === 'g:' + key ? ' br-buka' : ''}"><td colspan="9" style="padding:6px 8px 12px 22px">${tabelDl(o)}${tabelBahaya(o)}${tabelUmum(o)}</td></tr>` : '');
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
    return `<span class="br-h">Detail lokasi</span><div class="dr-scroll"><table class="pr-tbl dr-tbl"><thead><tr><th>Detail lokasi</th><th style="min-width:110px">Komposisi</th><th>Laporan SAP kelompok ini</th><th>Terakhir (apa pun)</th><th>Harian ${NH} hari</th><th>Tekanan gap</th><th>Proyeksi minggu depan</th><th>Flag PJA</th></tr></thead><tbody>${
      daftar.map((z) => {
        const l = idx.dl[z.dl] || {}, n = nrel(z.c), days = z.off ? null : harian(z.rows), fl = flagKel(z.dl, o.kel);
        const pending = fl.some((f) => f.review === 'PENDING');
        return `<tr${z.off ? ' style="opacity:.55"' : ''}><td>${E(l.lokasi)} · <b>${E(l.detlok)}</b></td><td>${z.off ? '<span class="dr-mute">dikecualikan</span>' : sb(z.c.ok, z.c.bm, z.c.none)}</td>
          <td>${terakhir((z.rows[0] || { K: {} }).K.last)}</td><td>${terakhir(D.beranda.dlast[z.dl])}</td>
          ${z.off ? '<td colspan="3" class="dr-mute">dikecualikan</td>' : `<td>${batangHarian(days, n)}</td><td>${selTekanan(days, z.c)}</td><td>${proyeksi(z.rows)}</td>`}
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
    return `<span class="br-h">Bahaya-risiko · identifikasi spesifik</span>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl"><thead><tr><th style="min-width:230px">Bahaya-risiko</th><th>Risiko awal</th><th style="min-width:110px">Komposisi detail lokasi</th><th class="r">Terkontrol</th><th class="r">Gap</th><th class="r">Tanpa laporan</th><th>Harian ${NH} hari</th><th>Tekanan gap</th><th>Proyeksi minggu depan</th></tr></thead><tbody>${
      L.slice(0, 200).map((p) => { const days = harian(p.rows, true), n = nrel(p.c);
        return `<tr><td>${E(p.r.bn)}</td><td>${lvTag(p.r.n1)}</td><td>${sb(p.c.ok, p.c.bm, p.c.none)}</td><td class="r">${p.c.ok}</td><td class="r">${p.c.bm}</td><td class="r">${p.c.none}</td>
          <td>${batangHarian(days, n)}</td><td>${selTekanan(days, p.c)}</td><td>${proyeksi(p.rows, true)}</td></tr>`; }).join('')}</tbody></table></div>${L.length > 200 ? `<div class="dr-mute">Menampilkan 200 dari ${L.length} bahaya-risiko.</div>` : ''}`;
  }

  function tabelUmum(o) {
    const rows = R.filter((r) => r.umum && S.kb[r.n1] && r.kel === o.kel && r.grp === o.grp && o.live.has(r.dl));
    if (!rows.length) return '';
    return `<span class="br-h">Bahaya umum</span><div class="dr-scroll"><table class="pr-tbl dr-tbl"><thead><tr><th>Bahaya-risiko</th><th>Risiko awal</th><th>Komposisi detail lokasi</th><th class="r">Terkontrol</th><th class="r">Gap</th><th class="r">Tanpa laporan</th></tr></thead><tbody>${
      perBahaya(rows).map((p) => `<tr><td>${E(p.r.bn)}</td><td>${lvTag(p.r.n1)}</td><td>${sb(p.c.ok, p.c.bm, p.c.none)}</td><td class="r">${p.c.ok}</td><td class="r">${p.c.bm}</td><td class="r">${p.c.none}</td></tr>`).join('')}</tbody></table></div>`;
  }

  function caraMembaca() {
    return `<details class="pr-card" style="padding:0"><summary class="pr-card-head" style="cursor:pointer"><div><b>Cara membaca, metode, dan batasan</b></div></summary><div class="pr-card-body"><ol class="br-ol">
      <li><b>Satuan kartu, ringkasan, peta panas, dan tabel antar PJA (versi ketat):</b> satu baris = kelompok aktivitas × bahaya-risiko, dihitung sekali walau muncul di beberapa detail lokasi. <b>Terkontrol penuh</b> bila semua detail lokasi aktifnya terkontrol; <b>sebagian</b> bila ada yang terkontrol dan ada yang belum; <b>hanya bermasalah</b> bila tidak ada yang terkontrol tetapi ada laporan bermasalah; <b>tanpa laporan</b> bila tidak ada laporan SAP di lokasi mana pun. Gap = semua selain terkontrol penuh.</li>
      <li><b>Baseline rincian (tabel kelompok → grup → detail lokasi, tren mingguan):</b> satu baris = kelompok aktivitas (M02) × detail lokasi × bahaya-risiko. Baseline = master DRM 2.1: detail lokasi (M06) mewarisi semua baris HIRA v31 grup lokasinya (M05), termasuk yang tidak punya jalur identifikasi SAP (otomatis tanpa laporan = gap). Laporan SAP hanya dihitung bila barisnya ada di grup lokasi detail lokasi laporan. Detail lokasi yang dimiliki dua PJA dihitung di masing-masing, jadi angka antar PJA tidak boleh dijumlahkan.</li>
      <li><b>Periode:</b> minggu Minggu–Sabtu; W1 = 1 Jan s.d. Sabtu pertama (W46 2026 = 8–14 Nov). Default = minggu penuh terakhir. Filter tingkat risiko awal mengubah pembagi.</li>
      <li><b>Sumber identifikasi (digabung):</b> (a) Observasi/OAK yang aktivitasnya terpetakan ke kelompok di detail lokasi itu menandai seluruh bahaya kelompok tersebut; (b) label Hazard/Inspeksi yang cocok ke bahaya — label spesifik (cocok ke ≤2 kelompok di lokasi itu) juga menandai kelompoknya; (c) butir checklist OAK yang cocok ke bahaya. Karena satu laporan OAK/OBS menutup semua baris kelompok, persen terkontrol di tingkat kelompok lebih tinggi daripada di tabel bahaya-risiko (yang hanya memakai bukti spesifik).</li>
      <li><b>Dua status:</b> terkontrol (ada laporan, tanpa masalah) dan gap. Gap = laporan bermasalah (status belum CLOSED/DONE — REJECT dihitung terbuka; ≥${BR.ambangUlang} laporan label ketidaksesuaian sama di detail lokasi yang sama dalam seminggu; OAK Stop Pekerjaan) + tanpa laporan. BUKAN TEMUAN dan DELETED dibuang. Terkontrol menurut catatan SAP, bukan bukti kondisi lapangan.</li>
      <li><b>Bahaya umum:</b> teks bahaya yang sama di ≥${BR.umumMin} kelompok dalam satu grup lokasi ditampilkan terpisah dan tidak masuk pembagi.</li>
      <li><b>Flag:</b> per kelompok aktivitas di satu detail lokasi, dengan alasan, masa berlaku, dan persetujuan orang lain. Flag nonaktif yang disetujui dan beririsan dengan minggu terpilih mengeluarkan barisnya dari pembagi. Bar harian dan proyeksi belum memperhitungkan flag.</li>
      <li><b>Tekanan gap</b> = yang lebih berat dari: (1) porsi baris tidak terkontrol 4 minggu berturut-turut (≥${Math.round(100 * BR.tekanan.u4_tinggi)}% Tinggi, ≥${Math.round(100 * BR.tekanan.u4_sedang)}% Sedang); (2) hari dengan laporan bermasalah dalam 7 hari terakhir (≥${BR.tekanan.hari_tinggi} hari atau ${BR.tekanan.beruntun_tinggi} hari beruntun = Tinggi, ≥${BR.tekanan.hari_sedang} = Sedang). Penanda perhatian, bukan prediksi; belum diuji terhadap insiden.</li>
      <li><b>Proyeksi minggu depan:</b> EWMA (α 0,3) atas % gap mingguan sampai minggu terpilih, butuh ≥5 minggu. Rentang 80% dari kuantil 10%/90% residu satu langkah (≥8 residu; selain itu −11,1/+7,3 poin), minimal ±2 poin. Naik/turun bila selisih dengan minggu terakhir >3 poin.</li>
      <li><b>Tanda merah insiden:</b> hanya pada bahaya yang label root cause insidennya (Hazard_Related_Incident) terpetakan ke bahaya itu di detail lokasi insiden.</li>
      <li>Pemetaan HIRA ↔ SAP berbasis kata kunci (draf) dan belum dikoreksi HSE.</li></ol></div></details>`;
  }

  // ------------------------------------------------------------ event
  function pasang() {
    $('#br-w').addEventListener('change', (e) => { S.w = +e.target.value; render(); });
    $('#br-prev').addEventListener('click', () => { if (S.w < NW - 1) { S.w++; render(); } });
    $('#br-next').addEventListener('click', () => { if (S.w > 0) { S.w--; render(); } });
    document.querySelectorAll('.br-kb').forEach((c) => c.addEventListener('click', () => { S.kb[c.dataset.k] = S.kb[c.dataset.k] ? 0 : 1; render(); }));
    document.querySelectorAll('.br-wc, .br-hm td[data-w]').forEach((c) => c.addEventListener('click', () => { S.w = +c.dataset.w; render(); }));
    document.querySelectorAll('[data-go]').forEach((a) => a.addEventListener('click', () => { const t = $('#' + a.dataset.go); if (t) t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }));
    document.querySelectorAll('tr.br-a').forEach((t) => t.addEventListener('click', () => { S.aopen[t.dataset.a] = !S.aopen[t.dataset.a]; S.baru = 'a:' + t.dataset.a; render(); }));
    document.querySelectorAll('tr.br-g').forEach((t) => t.addEventListener('click', () => { S.gopen[t.dataset.g] = !S.gopen[t.dataset.g]; S.baru = 'g:' + t.dataset.g; render(); }));
    document.querySelectorAll('[data-flag]').forEach((b) => b.addEventListener('click', (e) => { e.stopPropagation(); bukaFlag(b.dataset.flag, b.dataset.kel); }));
    document.querySelectorAll('tr[data-pja]').forEach((t) => t.addEventListener('click', () => { if (t.dataset.pja !== ID_PJA) location.href = URL_PJA.replace('__ID__', t.dataset.pja) + '?w=' + S.w; }));
    const semua = $('#br-semua');
    if (semua) semua.addEventListener('click', () => { S.semua = !S.semua; render(); });
    const mg = $('#br-mg');
    mg.addEventListener('toggle', () => { if (mg.open && !S.mg) { S.mg = true; $('#br-mg-isi').innerHTML = tabelMgmt(); pasangMgmt(); } else if (!mg.open) S.mg = false; });
  }
  function pasangMgmt() {
    document.querySelectorAll('tr[data-pja]').forEach((t) => t.addEventListener('click', () => { if (t.dataset.pja !== ID_PJA) location.href = URL_PJA.replace('__ID__', t.dataset.pja) + '?w=' + S.w; }));
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
      : `<div class="pr-card-body"><p class="dr-mute" style="margin:0 0 10px">Status: ${status}</p>
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
