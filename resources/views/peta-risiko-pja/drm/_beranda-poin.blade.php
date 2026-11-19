{{--
  Poin Beranda PJA (tab "Beranda PJA" di halaman indeks), di bawah kartu status & baris PJA:
    1–5 Lokasi & risiko (detail lokasi, aktivitas & bahaya HIRA, level awal / residual / menurut SAP, pengendalian)
    6   Insiden & root cause       a–c Personil BC / Mitra & pemenuhan SAP, coverage SAP detail lokasi
    7   Identifikasi harian per kelompok aktivitas × detail lokasi + proyeksi
    8–9 Ringkasan & daftar open risk per kelompok aktivitas × detail lokasi, dengan flag status aktivitas
    10  Temuan yang masih menjadi gap
  Data: berkas snapshot pja/{id}.json (sama dengan halaman PJA) + flag aktivitas. Status baris = DrmBeranda::status.
  Pemakaian: BerandaPoin({ el, meta, id, w, lv }) — dipanggil ulang setiap PJA / minggu / level berubah.
--}}
<style>
  .bp-strip { display: flex; gap: 2px; align-items: flex-end; height: 30px; min-width: 300px; }
  .bp-strip i { position: relative; flex: 1; height: 100%; border-radius: 2px; background: var(--c); }
  .bp-strip i.inc::before { content: ""; position: absolute; top: -5px; left: 50%; width: 5px; height: 5px; margin-left: -2.5px; border-radius: 50%; background: #E8112D; box-shadow: 0 0 4px #E8112D; }
  .bp-strip i.sep { margin-left: 4px; }
  .bp-hari { display: flex; gap: 2px; min-width: 300px; font: 600 10.5px/1 "Plus Jakarta Sans", sans-serif; color: var(--mute); }
  .bp-hari span { flex: 1; white-space: nowrap; overflow: visible; }
  .bp-lg { display: flex; flex-wrap: wrap; gap: 6px 16px; font-size: 12.5px; color: var(--mute); margin: 0 0 12px; }
  .bp-lg i { display: inline-block; width: 11px; height: 11px; border-radius: 3px; vertical-align: -1px; margin-right: 5px; background: var(--c); }
  .bp-sum { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
  .bp-sum div { border: 1px solid var(--line); border-top: 4px solid var(--c); border-radius: 12px; padding: 10px 14px; background: #fff; }
  .bp-sum small { display: block; font: 700 12px/1.3 "Plus Jakarta Sans", sans-serif; color: var(--mute); }
  .bp-sum b { display: block; margin-top: 4px; font: 800 24px/1 "Plus Jakarta Sans", sans-serif; font-variant-numeric: tabular-nums; color: var(--ink); }
  .bp-sum em { font-style: normal; font-size: 12px; color: var(--mute); }
  .bp-chip { display: inline-block; padding: 2px 8px; border-radius: 999px; font: 700 11.5px/1.4 "Plus Jakarta Sans", sans-serif; white-space: nowrap; background: var(--b, #eef1f5); color: var(--c, #475569); }
  .bp-akt > summary { display: flex; gap: 12px; align-items: center; padding: 12px 16px; cursor: pointer; list-style: none; border-top: 1px solid var(--line); }
  .bp-akt > summary::-webkit-details-marker { display: none; }
  .bp-akt > summary::before { content: "▸"; color: var(--navy); transition: transform .25s var(--ease); }
  .bp-akt[open] > summary::before { transform: rotate(90deg); }
  .bp-akt > summary:hover { background: var(--sky); }
  .bp-akt .bp-isi { padding: 4px 16px 14px 34px; background: #f8fafc; }
  .bp-kt { font-size: 12.5px; color: #475569; line-height: 1.45; max-width: 460px; }
  .bp-flag { display: inline-flex; gap: 4px; align-items: center; padding: 5px 10px; border: 1px solid var(--line-2); border-radius: 999px; background: #fff; font: 700 12px/1 "Plus Jakarta Sans", sans-serif; color: var(--navy); cursor: pointer; }
  .bp-flag:hover { border-color: var(--navy); background: var(--sky); }
  .bp-flag.off { color: #8a5300; border-color: #f2c76b; background: #fff7e6; }
  .bp-tot td { background: #eef3fa; font-weight: 800; }
  /* Baris nonaktif diredupkan kecuali kolom status (kolom ke-3) supaya tombolnya tetap jelas. */
  .bp-off td:not(:nth-child(3)) { opacity: .5; }
  /* Tabel poin: isi baris rata atas supaya sel pendek tidak terdorong ke tengah oleh sel panjang. */
  #bp-isi .pr-tbl td { vertical-align: top; }
  .bp-dialog { width: min(640px, calc(100vw - 32px)); border: 0; border-radius: 18px; padding: 0; box-shadow: 0 30px 80px rgba(15, 23, 42, .35); }
  .bp-dialog::backdrop { background: rgba(15, 23, 42, .45); }
  .bp-dialog header { display: flex; gap: 12px; align-items: flex-start; padding: 18px 20px 12px; border-bottom: 1px solid var(--line); }
  .bp-dialog header h3 { margin: 0; font: 800 17px/1.3 "Plus Jakarta Sans", sans-serif; }
  .bp-dialog header p { margin: 4px 0 0; font-size: 13px; color: var(--mute); }
  .bp-dialog header button { margin-left: auto; border: 0; background: transparent; cursor: pointer; color: var(--mute); }
  .bp-dialog .bp-dlg-isi { padding: 16px 20px 20px; max-height: 70vh; overflow: auto; }
  .bp-form { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 14px; }
  .bp-form label { display: grid; gap: 4px; font: 700 12px/1.2 "Plus Jakarta Sans", sans-serif; color: var(--mute); }
  .bp-form input, .bp-form select, .bp-form textarea { font: inherit; font-size: 14px; padding: 8px 10px; border: 1px solid var(--line-2); border-radius: 10px; }
  .bp-riwayat { margin-top: 14px; display: grid; gap: 8px; }
  .bp-riwayat div { border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px; font-size: 13px; }
  @media (max-width: 640px) { .bp-form { grid-template-columns: 1fr; } }
</style>
<script>
window.BerandaPoin = (function () {
  const URL_DATA = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.pja.data', ['pja' => '999999999'])) }}.replace('999999999', '__ID__');
  const URL_FLAG = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.flag.index', ['pja' => '999999999'])) }}.replace('999999999', '__ID__');
  const URL_REVIEW = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.flag.review', ['flag' => '999999999'])) }}.replace('999999999', '__ID__');
  const BIT = { DET: 1, SPE: 2, CLOSED: 4, ULANG: 8, STOP: 16, TS: 32, INC: 64 };
  const LV = ['Significant', 'High', 'Medium', 'Low'];
  const LVC = { Significant: '#b3261e', High: '#d0640c', Medium: '#c28f12', Low: '#2f7d4f' };
  const TABS = [['profil', 'Lokasi & risiko', 'map'], ['insiden', 'Insiden', 'emergency'], ['personil', 'Personil & coverage', 'groups'],
    ['harian', 'Identifikasi harian', 'calendar_view_week'], ['open', 'Open risk', 'report'], ['temuan', 'Temuan gap', 'assignment_late']];
  const S = { tab: 'profil', semuaHarian: false, buka: {} };
  try { const t = sessionStorage.getItem('bp-tab'); if (TABS.some(([k]) => k === t)) S.tab = t; } catch (e) { /* sesi tidak tersedia */ }
  const muat = {}, siap = {};
  let C = null; // konteks render terakhir
  const AKT = {}; // kunci aktivitas → bahaya-risikonya (diisi profil, dipakai saat aktivitas dibuka)
  const LABEL = { full: ['Terkontrol', '#dcfce7', '#15803d'], part: ['Sebagian', '#fef3c7', '#92650a'], gaponly: ['Ada gap', '#ffedd5', '#c2410c'], none: ['Tidak teridentifikasi', '#fde4e4', '#b42318'] };

  const E = (s) => DR.esc(s), F = (n) => DR.fmt(n);
  const pct = (a, b) => (b ? (100 * a / b).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%' : '–');
  const tgl = (s) => new Date(s + 'T00:00:00');
  const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const geser = (s, n) => { const d = tgl(s); d.setDate(d.getDate() + n); return iso(d); };
  const pendek = (s) => (s ? tgl(s).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) : '–');
  const urai = (s) => { const o = {}; if (s) String(s).split(',').forEach((p) => { const [i, b] = p.split(':'); o[+i] = (o[+i] || 0) | +b; }); return o; };
  const lvTag = (l) => `<span class="br-pill" style="background:${LVC[l] || '#8a939c'}">${E(l || '–')}</span>`;
  /** Sama dengan DrmBeranda::status: ['ok'|'bm'|'none', bit masalah]. */
  function statusBit(k, s) {
    const m = (s & (BIT.CLOSED | BIT.ULANG | BIT.STOP | BIT.TS)) | (k & BIT.STOP);
    if (m) return ['bm', m];
    return [(k & BIT.DET) || (s & BIT.SPE) ? 'ok' : 'none', 0];
  }
  const alasan = (m) => [m & BIT.CLOSED && 'belum closed', m & BIT.ULANG && 'berulang', m & BIT.STOP && 'OAK Stop', m & BIT.TS && 'butir OAK tidak sesuai'].filter(Boolean);
  const terakhir = (s) => (!s || s === '1970-01-01' ? '<span class="br-red">belum pernah</span>' : `${pendek(s)} <span class="dr-mute">(${Math.max(0, Math.round((tgl(C.M.sampai) - tgl(s)) / 864e5))} hari lalu)</span>`);

  // ------------------------------------------------------------ data
  function ambil(id) {
    return (muat[id] ||= Promise.all([
      fetch(URL_DATA.replace('__ID__', id), { headers: { Accept: 'application/json' } }).then((r) => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); }),
      fetch(URL_FLAG.replace('__ID__', id), { headers: { Accept: 'application/json' } }).then((r) => (r.ok ? r.json() : { tersedia: false, flag: [] })).catch(() => ({ tersedia: false, flag: [] })),
    ]).then(([D, FL]) => (siap[id] = siapkan(D, FL))).catch((e) => { delete muat[id]; throw e; }));
  }

  /** Baris rincian (detail lokasi × bahaya) dari pasangan baseline + bit mingguan / harian, seperti komponen Beranda. */
  function siapkan(D, FL) {
    const kol = C.M.kolomBahaya, B = Object.fromEntries(kol.map((k, i) => [k, i]));
    const bh = {};
    D.bahaya.forEach((b) => { bh[String(b[B.hid])] = { hid: String(b[B.hid]), a: b[B.a], an: b[B.aktivitas], sn: b[B.sub], bn: b[B.bahaya], p: b[B.potensi], n1: b[B.n1], k1: b[B.k1], c1: b[B.c1], n2: b[B.n2], k2: b[B.k2], c2: b[B.c2], kt: b[B.kontrol], kel: b[B.proses] || 'X' }; });
    const BE = D.beranda || {}, dl = Object.fromEntries(D.lokasi.map((l) => [String(l.dl), l]));
    const umum = {};
    Object.entries(BE.umum || {}).forEach(([g, l]) => l.forEach((h) => { umum[g + '|' + h] = 1; }));
    const inc = {};
    (D.sorot || []).forEach((r) => { if (+r.n_inc > 0) inc[r.dl + '|' + r.hid] = +r.n_inc; });
    const kc = {}, R = [];
    Object.entries(D.pasangan || {}).forEach(([d, isi]) => {
      const l = dl[d];
      if (!l || !isi) return;
      isi.split(',').forEach((x) => {
        const hid = x.split(':')[0], b = bh[hid];
        if (!b) return;
        const kk = d + '|' + b.kel, v = (BE.kd || {})[kk];
        const K = kc[kk] || (kc[kk] = { w: urai(v && v[0]), h: urai(v && v[1]), last: (v && v[2]) || '' });
        const sp = (BE.sp || {})[d + '|' + hid];
        R.push({ dl: d, hid, kel: b.kel, grp: l.grp, b, umum: !!umum[l.grp + '|' + hid], K, Sw: urai(sp && sp[0]), Sh: urai(sp && sp[1]), inc: inc[d + '|' + hid] || 0 });
      });
    });
    const flag = {};
    (FL.flag || []).forEach((f) => (flag[f.dl + '|' + f.aktivitas] ||= []).push(f));
    return { D, FL, R, bh, dl, flag, BE };
  }

  const flagKel = (X, d, kel) => X.flag[d + '|kel:' + kel] || [];
  function nonaktif(X, d, kel, wi) {
    const a = C.M.beranda.minggu[wi], z = geser(a, 6);
    const f = flagKel(X, d, kel).filter((x) => x.review === 'APPROVED' && x.mulai <= z && (!x.selesai || x.selesai >= a))
      .sort((p, q) => q.mulai.localeCompare(p.mulai) || q.id - p.id)[0];
    return !!f && f.status === 'NONAKTIF';
  }
  const lvOk = (n1) => C.lv === 'ALL' || n1 === 'Significant' || n1 === 'High';
  /** Baris aktif untuk perhitungan: bukan bahaya umum, level sesuai filter. */
  const aktif = (X) => X.R.filter((r) => !r.umum && lvOk(r.b.n1));

  /** Status gabungan satu bahaya (kelompok × bahaya) di semua detail lokasi PJA pada minggu w: full / part / gaponly / none. */
  function statusBahaya(X, w) {
    const U = {};
    aktif(X).forEach((r) => {
      if (nonaktif(X, r.dl, r.kel, w)) return;
      const k = r.kel + '|' + r.hid, u = U[k] || (U[k] = { n: 0, ok: 0, bm: 0, m: 0, inc: 0 });
      const [st, m] = statusBit(r.K.w[w] || 0, r.Sw[w] || 0);
      u.n++; u.inc += r.inc;
      if (st === 'ok') u.ok++; else if (st === 'bm') { u.bm++; u.m |= m; }
    });
    Object.values(U).forEach((u) => { u.cat = u.ok === u.n ? 'full' : u.ok > 0 ? 'part' : u.bm > 0 ? 'gaponly' : 'none'; });
    return U;
  }

  // ------------------------------------------------------------ render
  function render() {
    const { el } = C;
    el.innerHTML = `<div class="pr-card" style="margin-top:20px;padding:0;overflow:hidden"><div class="br-tabs" role="tablist" aria-label="Poin Beranda PJA">${TABS.map(([k, l, ik]) =>
      `<button type="button" role="tab" data-bp="${k}" aria-selected="${S.tab === k}"><span class="material-symbols-outlined">${ik}</span>${l}</button>`).join('')}</div>
      <div id="bp-isi"${C.ganti ? ' class="br-ganti"' : ''}><div class="pr-empty">Memuat data PJA…</div></div></div>`;
    C.ganti = false;
    el.querySelectorAll('[data-bp]').forEach((b) => b.addEventListener('click', () => {
      S.tab = b.dataset.bp;
      C.ganti = true;
      try { sessionStorage.setItem('bp-tab', S.tab); } catch (e) { /* sesi tidak tersedia */ }
      render();
    }));
    const id = C.id;
    const gambar = (X) => {
      const isi = document.getElementById('bp-isi');
      if (C.id !== id || !isi) return;
      isi.innerHTML = { profil, insiden, personil, harian, open, temuan }[S.tab](X);
      pasang(X, isi);
    };
    // Data PJA yang sudah dimuat digambar langsung (tanpa kedip "Memuat…" saat filter / baris berubah).
    if (siap[id]) { gambar(siap[id]); return; }
    ambil(id).then(gambar).catch((e) => {
      const isi = document.getElementById('bp-isi');
      if (isi) isi.innerHTML = `<div class="pr-empty">Data PJA gagal dimuat (${E(e.message)}).</div>`;
    });
  }

  const kartu = (judul, sub, isi) => `<div class="pr-card-head" style="border-top:1px solid var(--line)"><div><b>${judul}</b>${sub ? `<small>${sub}</small>` : ''}</div></div>${isi}`;
  const tabel = (kepala, baris, kosong, minW = 0) => `<div class="dr-scroll"><table class="pr-tbl dr-tbl"${minW ? ` style="min-width:${minW}px"` : ''}><thead><tr>${kepala}</tr></thead><tbody>${baris || `<tr><td colspan="20" class="pr-empty">${kosong}</td></tr>`}</tbody></table></div>`;
  const wKode = () => DR.minggu(C.M.beranda.minggu[C.w], C.M.sampai, C.w, C.M.beranda.minggu[0]).kode;
  const namaKel = (k) => ((C.M.proses.find((p) => p.kode === k) || {}).nama || (k === 'X' ? 'Belum terpetakan' : k));

  // ------------------------------------------------ 1–5 lokasi, aktivitas, bahaya, level risiko, pengendalian
  function profil(X) {
    const rows = aktif(X), U = statusBahaya(X, C.w);
    const perDl = {};
    rows.forEach((r) => { const o = perDl[r.dl] || (perDl[r.dl] = { kel: new Set(), h: new Set() }); o.kel.add(r.kel); o.h.add(r.hid); });
    const dl = X.D.lokasi.slice().sort((a, b) => (a.lokasi + a.detlok).localeCompare(b.lokasi + b.detlok)).map((l) => {
      const o = perDl[l.dl] || { kel: new Set(), h: new Set() };
      return `<tr><td>${E(l.lokasi)} · <b>${E(l.detlok)}</b></td><td>${E(l.grp)}</td><td class="r">${F(o.kel.size)}</td><td class="r">${F(o.h.size)}</td><td>${terakhir((X.BE.dlast || {})[l.dl])}</td></tr>`;
    }).join('');

    // Aktivitas HIRA → bahaya unik di PJA (dari baris aktif), status menurut SAP minggu terpilih.
    const perAkt = {};
    rows.forEach((r) => {
      const a = perAkt[r.b.an] || (perAkt[r.b.an] = { kel: r.kel, h: {} });
      a.h[r.hid] = r.b;
    });
    C.U = U;
    const akt = Object.entries(perAkt).sort((x, y) => x[0].localeCompare(y[0])).map(([an, a]) => {
      const hs = Object.values(a.h);
      const n = { full: 0, part: 0, gaponly: 0, none: 0 };
      hs.forEach((b) => { n[(U[b.kel + '|' + b.hid] || { cat: 'none' }).cat]++; });
      const kunci = 'akt:' + an;
      AKT[kunci] = hs;
      return `<details class="bp-akt" data-k="${E(kunci)}"${S.buka[kunci] ? ' open' : ''}><summary><div style="flex:1;min-width:0"><b>${E(an || 'Aktivitas tanpa nama')}</b><br><span class="dr-mute">${E(namaKel(a.kel))} · ${hs.length} bahaya-risiko</span></div>
        ${DR.sb4(n.full, n.part, n.gaponly, n.none)}<span class="dr-mute" style="white-space:nowrap">${pct(n.full, hs.length)} terkontrol</span></summary>
        <div class="bp-isi">${S.buka[kunci] ? isiAkt(hs, U) : ''}</div></details>`;
    }).join('');

    return kartu('1. Lokasi – detail lokasi terkait', `${X.D.lokasi.length} detail lokasi milik PJA ini`,
      tabel('<th>Lokasi · detail lokasi</th><th>Grup lokasi</th><th class="r">Kelompok aktivitas</th><th class="r">Bahaya-risiko</th><th>Laporan SAP terakhir</th>', dl, 'Tidak ada detail lokasi.'))
      + kartu('2–5. Aktivitas, bahaya-risiko, level risiko, dan pengendalian (HIRA)', `${wKode()} · ${C.lv === 'ALL' ? 'semua level' : 'Significant + High'} · klik aktivitas untuk melihat bahaya-risikonya`,
        akt || '<div class="pr-empty">Tidak ada baris baseline pada filter ini.</div>');
  }

  function isiAkt(hs, U) {
    const urut = { Significant: 0, High: 1, Medium: 2, Low: 3 };
    return tabel('<th style="min-width:220px">Bahaya-risiko</th><th>Sub aktivitas</th><th>Risiko awal</th><th>Risiko residual</th><th>Risiko menurut identifikasi SAP</th><th style="min-width:260px">Pengendalian yang ada</th>',
      hs.slice().sort((a, b) => (urut[a.n1] ?? 9) - (urut[b.n1] ?? 9) || String(a.bn).localeCompare(String(b.bn))).map((b) => {
        const u = U[b.kel + '|' + b.hid] || { cat: 'none', m: 0 };
        const [t, bg, c] = LABEL[u.cat];
        const akhir = u.cat === 'full' && b.n2 ? b.n2 : b.n1;
        const kt = String(b.kt || '').split(' | ').filter(Boolean);
        return `<tr><td><b>${E(b.bn)}</b>${b.p ? `<br><span class="dr-mute">${E(b.p)}</span>` : ''}</td><td class="dr-mute">${E(b.sn)}</td>
          <td>${lvTag(b.n1)}<br><span class="dr-mute">${E(b.k1)} × ${E(b.c1)}</span></td>
          <td>${lvTag(b.n2)}<br><span class="dr-mute">${E(b.k2)} × ${E(b.c2)}</span></td>
          <td>${lvTag(akhir)} <span class="bp-chip" style="--b:${bg};--c:${c}">${t}</span>${u.m ? `<br><span class="dr-mute">${alasan(u.m).join(', ')}</span>` : ''}</td>
          <td><div class="bp-kt">${kt.length ? kt.slice(0, 4).map((x) => '• ' + E(x)).join('<br>') + (kt.length > 4 ? `<br><span class="dr-mute">+${kt.length - 4} lainnya</span>` : '') : '<span class="dr-mute">tidak tercatat di HIRA</span>'}</div></td></tr>`;
      }).join(''), 'Tidak ada bahaya.', 980);
  }

  // ------------------------------------------------ 6 insiden & root cause
  function insiden(X) {
    const rc = {};
    (X.BE.rc || []).forEach((r) => (rc[r.no] ||= []).push(r));
    const baris = (X.D.insiden || []).map((i) => {
      const r = rc[i.no] || [];
      return `<tr><td style="white-space:nowrap"><b>#${E(i.no)}</b><br><span class="dr-mute">${pendek(i.tanggal)} ${String(i.tanggal).slice(0, 4)}</span></td>
        <td>${E(i.kategori)}</td><td>${E(i.lokasi)} · <b>${E(i.detlok)}</b></td>
        <td><div class="pr-clamp" title="${E(i.kronologi)}">${E(i.kronologi || '–')}</div>${i.potensi ? `<span class="dr-mute">Potensi: ${E(i.potensi)}</span>` : ''}</td>
        <td>${r.length ? r.map((x) => `<div style="margin-bottom:4px">${E(x.label)} <span class="dr-mute">· sebelum ${x.sebelum ? F(x.sebelum) : '<b class="br-red">0</b>'} · belum closed ${F(x.belum)}</span></div>`).join('') : '<span class="dr-mute">belum terpetakan</span>'}</td></tr>`;
    }).join('');
    return kartu('6. Insiden dan root cause di lokasi PJA', `${(X.D.insiden || []).length} insiden · root cause SAP = label ketidaksesuaian yang terpetakan, "sebelum" = laporan label sama 3 bulan sebelum insiden`,
      tabel('<th>Insiden</th><th>Kategori</th><th>Detail lokasi</th><th style="min-width:320px">Kronologi</th><th style="min-width:260px">Root cause (label SAP)</th>', baris, 'Tidak ada insiden di detail lokasi PJA ini.', 960));
  }

  // ------------------------------------------------ a–c personil & coverage
  /**
   * a–c: performa SAP per personil PJA (BC & Mitra) dan coverage SAP per lokasi – detail lokasi.
   * Coverage dipisah: oleh tim PJA (personil PJA ini + PJA Mitra anaknya) dan dari luar PJA (semua − tim PJA).
   */
  function personil(X) {
    const D = X.D, akhir = {};
    (D.bearc || []).forEach((b) => { if (!akhir[b.sid] || akhir[b.sid].mulai < b.mulai) akhir[b.sid] = b; });
    const gabung = (rows) => Object.values(rows.reduce((acc, p) => {
      const k = p.pja_id + '|' + p.sid;
      if (!acc[k]) acc[k] = { ...p, kat: new Set() };
      if (p.kategori) acc[k].kat.add(String(p.kategori).trim());
      return acc;
    }, {})).map(performa).sort((x, y) => y.tot - x.tot || String(x.nama).localeCompare(String(y.nama)));
    const bc = gabung(D.personil.filter((p) => p.bc)), mk = gabung(D.personil.filter((p) => !p.bc));
    const cov = D.coverage.map((c) => ({ ...c, l: X.dl[String(c.dl)], s: Number(c.semua), d: Number(c.dedicated) })).filter((c) => c.l);
    const sCov = cov.reduce((a, c) => a + c.s, 0), dCov = cov.reduce((a, c) => a + c.d, 0);
    const semua = [...bc, ...mk];
    const ringkas = `<div class="pr-card-body"><div class="bp-sum">${[
      ['#3952bc', 'Personil PJA', F(semua.length), `${bc.length} BC · ${mk.length} Mitra`],
      ['#1a7f37', 'Aktif lapor 7 hari terakhir', F(semua.filter((p) => p.tot7 > 0).length), `${semua.filter((p) => !p.tot).length} personil tanpa laporan 14 hari`],
      ['#0891b2', 'Laporan SAP personil 14 hari', F(semua.reduce((a, p) => a + p.tot, 0)), `rata-rata ${(semua.reduce((a, p) => a + p.tot, 0) / Math.max(1, semua.length) / 14).toFixed(1)} per orang per hari`],
      ['#a07a00', `Coverage oleh tim PJA (${C.M.jendela} hari)`, pct(dCov, sCov), `${F(dCov)} dari ${F(sCov)} laporan di detail lokasi PJA`],
      ['#b42318', 'Detail lokasi tanpa laporan', F(cov.filter((c) => !c.s).length), `dari ${cov.length} detail lokasi`],
    ].map(([c, j, n, k]) => `<div style="--c:${c}"><small>${j}</small><b>${n}</b><em>${k}</em></div>`).join('')}</div></div>`;
    const ket = (rows) => `${F(rows.reduce((a, p) => a + p.tot, 0))} laporan SAP 14 hari · ${rows.filter((p) => !p.tot).length} personil tanpa laporan · diurutkan dari laporan terbanyak`;
    return ringkas
      + kartu(`a. Performa SAP personil PJA BC (${bc.length})`, ket(bc), tabelPersonil(bc, akhir))
      + kartu(`b. Performa SAP personil PJA Mitra (${mk.length})`, ket(mk), tabelPersonil(mk, akhir))
      + kartu(`c. Lokasi – detail lokasi & coverage SAP (${C.M.jendela} hari)`, 'Tim PJA = personil PJA ini dan PJA Mitra-nya · luar PJA = laporan orang lain di lokasi yang sama', tabelCoverage(X, cov));
  }

  /** Angka performa satu personil dari jumlah laporan harian 14 hari (indeks 0 = 14 hari lalu). */
  function performa(p) {
    const h = (p.harian || []).map(Number), tot = h.reduce((a, n) => a + n, 0);
    const tot7 = h.slice(-7).reduce((a, n) => a + n, 0), sebelum7 = h.slice(0, -7).reduce((a, n) => a + n, 0);
    return { ...p, h, tot, tot7, sebelum7, aktif: h.filter((n) => n > 0).length };
  }

  function tabelPersonil(rows, akhir) {
    return tabel('<th>Personil</th><th>Peran</th><th>SAP 14 hari</th><th class="r">Total</th><th class="r">Hari aktif</th><th class="r">Rata-rata / hari</th><th>Tren 7 hari</th><th class="r">%SAP minggu lalu</th><th>BeARC</th>',
      rows.map((p) => {
        const mx = Math.max(1, ...p.h), b = akhir[p.sid], beda = p.tot7 - p.sebelum7;
        const tren = !p.tot ? '<span class="br-red">tanpa laporan</span>'
          : `<span style="color:${beda > 0 ? '#1a7f37' : beda < 0 ? '#b42318' : 'var(--mute)'}">${beda > 0 ? '▲' : beda < 0 ? '▼' : '='} ${F(p.tot7)}</span> <span class="dr-mute">vs ${F(p.sebelum7)}</span>`;
        return `<tr><td><b>${E(p.nama)}</b><br><span class="dr-mute">${E(p.sid)} · ${E(p.perusahaan)}${!p.bc ? ' · ' + E(p.pja) : ''}</span></td><td class="dr-mute">${E([...p.kat].join(', ') || '–')}</td>
          <td><span class="dr-spark" title="${E(p.h.join(', '))}">${p.h.map((n) => (n ? `<i style="height:${(100 * n / mx).toFixed(0)}%"></i>` : '<i class="z"></i>')).join('')}</span></td>
          <td class="r"><b>${F(p.tot)}</b></td><td class="r">${p.aktif}<span class="dr-mute">/14</span></td><td class="r">${(p.tot / 14).toFixed(1)}</td><td>${tren}</td>
          <td class="r">${!b ? '–' : b.persen === null ? 'N/A' : Number(b.persen).toFixed(0) + '%'}</td>
          <td>${!b ? '<span class="dr-mute">tidak ada data</span>' : Number(b.banned_sap) ? '<span class="dr-tag st-L">Banned SAP</span>' : '<span class="dr-tag st-K">Achieve dihitung</span>'}</td></tr>`;
      }).join(''), 'Tidak ada personil terdaftar.', 1040);
  }

  /** Coverage per lokasi (subtotal) → detail lokasi: total, oleh tim PJA, dari luar PJA, porsi, laporan terakhir. */
  function tabelCoverage(X, cov) {
    const perLok = {};
    cov.forEach((c) => (perLok[c.l.lokasi || '–'] ||= []).push(c));
    const batang = (d, s) => (s ? `<div class="br-sb" title="Tim PJA ${pct(d, s)} · luar PJA ${pct(s - d, s)}"><i style="width:${(100 * d / s).toFixed(1)}%;background:#1a7f37"></i><i style="width:${(100 * (s - d) / s).toFixed(1)}%;background:#93c5fd"></i></div>` : '<span class="dr-mute">–</span>');
    const isi = Object.keys(perLok).sort().map((lok) => {
      const L = perLok[lok].sort((a, b) => String(a.l.detlok).localeCompare(String(b.l.detlok)));
      const s = L.reduce((a, c) => a + c.s, 0), d = L.reduce((a, c) => a + c.d, 0);
      return `<tr class="bp-tot"><td><b>${E(lok)}</b> <span class="dr-mute" style="font-weight:400">· ${L.length} detail lokasi</span></td><td></td><td class="r">${F(s)}</td><td class="r">${F(d)}</td><td class="r">${F(s - d)}</td><td>${batang(d, s)}</td><td class="r">${pct(d, s)}</td><td></td></tr>`
        + L.map((c) => `<tr><td style="padding-left:28px">${E(c.l.detlok)}</td><td class="dr-mute">${E(c.l.grp)}</td>
          <td class="r">${c.s ? F(c.s) : '<span class="dr-tag st-T">0</span>'}</td><td class="r">${F(c.d)}</td><td class="r">${F(c.s - c.d)}</td>
          <td>${batang(c.d, c.s)}</td><td class="r">${pct(c.d, c.s)}</td><td>${terakhir((X.BE.dlast || {})[c.dl])}</td></tr>`).join('');
    }).join('');
    return `<div class="pr-card-body" style="padding-bottom:0"><div class="bp-lg"><span><i style="--c:#1a7f37"></i>oleh tim PJA</span><span><i style="--c:#93c5fd"></i>dari luar PJA</span></div></div>`
      + tabel('<th style="min-width:240px">Lokasi · detail lokasi</th><th>Grup lokasi</th><th class="r">Laporan (semua)</th><th class="r">Oleh tim PJA</th><th class="r">Dari luar PJA</th><th style="min-width:150px">Porsi</th><th class="r">% tim PJA</th><th>Laporan SAP terakhir</th>',
        isi, 'Tidak ada data coverage.', 1080);
  }

  // ------------------------------------------------ 7 identifikasi harian + proyeksi
  const EW = { alpha: 0.3, hari: 7 };
  /** EWMA % gap harian (hari dengan baris aktif); prakiraan rata-rata 7 hari ke depan = nilai EWMA terakhir. */
  function proyeksiHarian(gap) {
    const y = gap.filter((v) => v !== null);
    if (y.length < 7) return null;
    let s = y[0];
    for (let i = 1; i < y.length; i++) s = EW.alpha * y[i] + (1 - EW.alpha) * s;
    const akhir7 = y.slice(-7).reduce((a, b) => a + b, 0) / 7;
    return { f: s, arah: s - akhir7 > 0.03 ? 'naik' : akhir7 - s > 0.03 ? 'turun' : 'stabil' };
  }
  /** Tombol status aktivitas (flag) satu kelompok aktivitas × detail lokasi; dipakai tab Identifikasi harian & Open risk. */
  function tombolFlag(X, dl, kel, off) {
    const pending = flagKel(X, dl, kel).some((f) => f.review === 'PENDING');
    return `<button type="button" class="bp-flag${off ? ' off' : ''}" data-flag="${E(dl)}" data-kel="${E(kel)}" title="Ubah status aktivitas (berlaku setelah disetujui orang lain)"><span class="material-symbols-outlined" style="font-size:15px">${off ? 'pause_circle' : 'play_circle'}</span>${off ? 'Nonaktif' : 'Ada aktivitas'}</button>${pending ? '<br><span class="dr-tag st-X">Menunggu review</span>' : ''}`;
  }

  function harian(X) {
    const NH = C.M.beranda.hari, h0 = C.M.beranda.hari0, T = C.M.beranda.tekanan;
    const grup = {};
    // Kombinasi bertanda nonaktif tetap ditampilkan (redup, di bawah) supaya statusnya bisa diubah kembali.
    aktif(X).forEach((r) => { (grup[r.kel + '|' + r.dl] ||= { kel: r.kel, dl: r.dl, off: nonaktif(X, r.dl, r.kel, C.w), rows: [] }).rows.push(r); });
    const L = Object.values(grup).map((g) => {
      const d = Array.from({ length: NH }, () => ({ ok: 0, bm: 0, inc: 0 }));
      g.rows.forEach((r) => {
        for (let i = 0; i < NH; i++) {
          const s = r.Sh[i] || 0, st = statusBit(r.K.h[i] || 0, s)[0];
          if (st === 'ok') d[i].ok++; else if (st === 'bm') d[i].bm++;
          if (s & BIT.INC) d[i].inc++;
        }
      });
      const n = g.rows.length, gap = d.map((x) => 1 - x.ok / n);
      let c7 = 0, beruntun = 0;
      for (let i = NH - 7; i < NH; i++) if (d[i].bm > 0 || d[i].ok === 0) c7++;
      for (let i = NH - 1; i >= 0 && (d[i].bm > 0 || d[i].ok === 0); i--) beruntun++;
      const ok7 = d.slice(-7).reduce((a, x) => a + x.ok, 0) / (7 * n);
      const inc = d.reduce((a, x) => a + (x.inc ? 1 : 0), 0) + (g.rows.some((r) => r.inc) ? 1 : 0);
      // Kecenderungan insiden: hari bergap dalam 7 hari terakhir / beruntun (ambang tekanan Beranda), dinaikkan bila ada riwayat insiden.
      let tk = c7 >= T.hari_tinggi || beruntun >= T.beruntun_tinggi ? 2 : c7 >= T.hari_sedang ? 1 : 0;
      if (inc && tk < 2) tk++;
      return { ...g, d, n, ok7, pj: proyeksiHarian(gap), tk, c7, inc };
    }).sort((a, b) => Number(a.off) - Number(b.off) || b.tk - a.tk || a.ok7 - b.ok7 || b.n - a.n);
    const nOff = L.filter((g) => g.off).length;
    const tampil = S.semuaHarian ? L : L.slice(0, 40);
    const warna = (x, n) => (x.bm > 0 ? DR.BW.bm : x.ok === 0 ? '#e5e9ef' : `hsl(214, 70%, ${(78 - 38 * (x.ok / n)).toFixed(0)}%)`);
    const kepalaHari = Array.from({ length: NH }, (_, i) => { const t = geser(h0, i); return `<span>${tgl(t).getDay() === 0 ? pendek(t) : ''}</span>`; }).join('');
    const TK = [['Rendah', '#dcfce7', '#15803d'], ['Sedang', '#fef3c7', '#92650a'], ['Tinggi', '#fde4e4', '#b42318']];
    const baris = tampil.map((g) => {
      const l = X.dl[g.dl] || {};
      const pj = g.pj ? `<b>~${Math.round(100 * (1 - g.pj.f))}%</b> terkontrol<br><span class="dr-mute">gap ${g.pj.arah}</span>` : '<span class="dr-mute">data kurang</span>';
      const kecenderungan = g.off ? '<span class="dr-mute">dikecualikan (nonaktif)</span>'
        : `<span class="bp-chip" style="--b:${TK[g.tk][1]};--c:${TK[g.tk][2]}">${TK[g.tk][0]}</span>${g.inc ? '<br><span class="br-red" style="font-size:12px">ada riwayat insiden</span>' : ''}`;
      return `<tr${g.off ? ' class="bp-off"' : ''}><td><b>${E(namaKel(g.kel))}</b><br><span class="dr-mute">${g.n} bahaya</span></td><td>${E(l.lokasi)} · <b>${E(l.detlok)}</b></td>
        <td>${tombolFlag(X, g.dl, g.kel, g.off)}</td>
        <td><div class="bp-strip">${g.d.map((x, i) => `<i class="${x.inc ? 'inc' : ''}${i && tgl(geser(h0, i)).getDay() === 0 ? ' sep' : ''}" style="--c:${warna(x, g.n)}" title="${pendek(geser(h0, i))}: ${x.ok} terkontrol, ${x.bm} bermasalah, ${Math.max(0, g.n - x.ok - x.bm)} tanpa laporan${x.inc ? ' · INSIDEN' : ''}"></i>`).join('')}</div></td>
        <td class="r"><b>${pct(g.ok7, 1)}</b><br><span class="dr-mute">${g.c7} hari bergap</span></td><td>${g.off ? '<span class="dr-mute">–</span>' : pj}</td>
        <td>${kecenderungan}</td></tr>`;
    }).join('');
    return kartu('7. Identifikasi risiko per kelompok aktivitas – detail lokasi', `${pendek(h0)} – ${pendek(C.M.sampai)} · satu kotak = satu hari · titik merah = insiden · proyeksi = EWMA % terkontrol harian untuk 7 hari ke depan · klik "Ada aktivitas" bila aktivitas tidak berjalan di lokasi itu${nOff ? ` · ${nOff} kombinasi nonaktif` : ''}`,
      `<div class="pr-card-body" style="padding-bottom:0"><div class="bp-lg"><span><i style="--c:hsl(214,70%,40%)"></i>semua bahaya terkontrol</span><span><i style="--c:hsl(214,70%,70%)"></i>sebagian terkontrol</span><span><i style="--c:${DR.BW.bm}"></i>ada laporan bermasalah</span><span><i style="--c:#e5e9ef"></i>tanpa laporan</span><span>Kecenderungan insiden naik bila gap terjadi hampir setiap hari (≥${T.hari_tinggi} dari 7 hari atau ${T.beruntun_tinggi} hari beruntun) atau pernah ada insiden.</span></div></div>`
      + tabel(`<th style="min-width:180px">Kelompok aktivitas</th><th style="min-width:200px">Lokasi · detail lokasi</th><th>Status aktivitas</th><th><div class="bp-hari">${kepalaHari}</div></th><th class="r">Terkontrol 7 hari</th><th>Proyeksi 7 hari</th><th>Kecenderungan insiden</th>`, baris, 'Tidak ada baris aktif pada filter ini.', 1200)
      + (L.length > 40 ? `<div class="pr-card-body"><button type="button" class="pr-back" id="bp-semua-harian">${S.semuaHarian ? 'Tampilkan 40 teratas' : `Tampilkan semua ${L.length} baris`}</button></div>` : ''));
  }

  // ------------------------------------------------ 8–9 open risk
  function bahayaOpen(X) {
    const per = {};
    aktif(X).forEach((r) => {
      const k = r.kel + '|' + r.dl, off = nonaktif(X, r.dl, r.kel, C.w);
      const o = per[k] || (per[k] = { kel: r.kel, dl: r.dl, off, last: r.K.last, g1: 0, g2: 0, g3: 0, g4: 0, ts: 0, inc: 0, n: 0, list: [] });
      o.n++;
      if (off) return;
      const [st, m] = statusBit(r.K.w[C.w] || 0, r.Sw[C.w] || 0);
      if (r.inc) o.inc++;
      if (st === 'ok') return;
      if (st === 'none') o.g1++;
      if (m & BIT.CLOSED) o.g2++; if (m & BIT.ULANG) o.g3++; if (m & BIT.STOP) o.g4++; if (m & BIT.TS) o.ts++;
      o.list.push({ r, st, m });
    });
    return Object.values(per);
  }
  function open(X) {
    const L = bahayaOpen(X), aktifL = L.filter((o) => !o.off);
    const jml = (k) => aktifL.reduce((s, o) => s + o[k], 0);
    const tid = aktifL.reduce((s, o) => s + o.list.filter((x) => x.st === 'none' && x.r.inc).length, 0);
    const kartuSum = [['#b42318', 'Tidak teridentifikasi (Gap1)', jml('g1'), 'tanpa laporan SAP sama sekali'], ['#c2410c', 'Belum closed (Gap2)', jml('g2'), 'temuan belum CLOSED/DONE'],
      ['#a07a00', 'Berulang / sistemik (Gap3)', jml('g3'), '≥' + C.M.beranda.ambangUlang + ' laporan label sama per minggu'], ['#7f1d1d', 'OAK Stop pekerjaan (Gap4)', jml('g4'), 'hasil OAK Stop'],
      ['#1d4ed8', 'Butir OAK tidak sesuai', jml('ts'), 'checklist OAK Tidak Sesuai'], ['#7e3fb3', 'Open risk pernah insiden', tid, 'tidak teridentifikasi & terkait insiden 12 bln']];
    const sum = `<div class="pr-card-body"><div class="bp-sum">${kartuSum.map(([c, j, n, k]) => `<div style="--c:${c}"><small>${j}</small><b>${F(n)}</b><em>${k}</em></div>`).join('')}</div>
      <p class="dr-mute" style="margin:10px 0 0;font-size:12.5px">Satuan: baris kelompok aktivitas × detail lokasi × bahaya-risiko, ${wKode()}, ${C.lv === 'ALL' ? 'semua level' : 'Significant + High'}. ${L.length - aktifL.length ? `${L.length - aktifL.length} kombinasi dikecualikan karena flag nonaktif disetujui.` : ''}</p></div>`;

    const daftar = L.filter((o) => o.off || o.g1 + o.g2 + o.g3 + o.g4 + o.ts > 0)
      .sort((a, b) => Number(a.off) - Number(b.off) || (b.g1 + b.g2 + b.g3 + b.g4) - (a.g1 + a.g2 + a.g3 + a.g4) || b.inc - a.inc);
    const tot = `<tr class="bp-tot"><td colspan="4">Total ${daftar.filter((o) => !o.off).length} kombinasi dengan open risk</td><td class="r">${F(jml('g1'))}</td><td class="r">${F(jml('g2'))}</td><td class="r">${F(jml('g3'))}</td><td class="r">${F(jml('g4'))}</td></tr>`;
    const baris = daftar.map((o) => {
      const l = X.dl[o.dl] || {}, k = 'open:' + o.kel + '|' + o.dl, buka = !!S.buka[k];
      const utama = `<tr class="br-a${buka ? ' open' : ''}" data-buka="${E(k)}" style="cursor:pointer${o.off ? ';opacity:.6' : ''}">
        <td><button class="br-cr" type="button" aria-expanded="${buka}" aria-label="Lihat bahaya-risiko">▸</button><b>${E(namaKel(o.kel))}</b></td>
        <td>${E(l.lokasi)} · <b>${E(l.detlok)}</b></td><td>${terakhir(o.last || (X.BE.dlast || {})[o.dl])}</td>
        <td>${o.inc ? `<span class="bp-chip" style="--b:#f1e8fa;--c:#7e3fb3">Ya (${o.inc} bahaya)</span>` : '<span class="dr-mute">Tidak</span>'}</td>
        <td class="r">${o.off ? '–' : F(o.g1)}</td><td class="r">${o.off ? '–' : F(o.g2)}</td><td class="r">${o.off ? '–' : F(o.g3)}</td><td class="r">${o.off ? '–' : F(o.g4)}</td></tr>`;
      if (!buka) return utama;
      const urut = { Significant: 0, High: 1, Medium: 2, Low: 3 };
      const isi = o.list.slice().sort((a, b) => (urut[a.r.b.n1] ?? 9) - (urut[b.r.b.n1] ?? 9)).map((x) => `<tr><td>${E(x.r.b.bn)}<br><span class="dr-mute">${E(x.r.b.an)}${x.r.b.sn && x.r.b.sn !== x.r.b.an ? ' · ' + E(x.r.b.sn) : ''}</span></td><td>${lvTag(x.r.b.n1)}</td>
        <td>${x.st === 'none' ? '<span class="bp-chip" style="--b:#fde4e4;--c:#b42318">Tidak teridentifikasi</span>' : alasan(x.m).map((a) => `<span class="bp-chip" style="--b:#ffedd5;--c:#c2410c">${a}</span>`).join(' ')}</td>
        <td>${x.r.inc ? `<span class="br-red">${x.r.inc} insiden</span>` : '<span class="dr-mute">–</span>'}</td></tr>`).join('');
      return utama + `<tr class="br-sub br-buka"><td colspan="8" style="padding:8px 12px 14px 34px">${o.off ? '<span class="dr-mute">Dikecualikan dari perhitungan karena flag nonaktif disetujui.</span>' : tabel('<th>Bahaya-risiko spesifik</th><th>Risiko awal</th><th>Jenis gap</th><th>Insiden 12 bln</th>', isi, 'Tidak ada.')}</td></tr>`;
    }).join('');
    return kartu(`8. Ringkasan open risk · ${wKode()}`, 'Open = tidak teridentifikasi SAP atau masih ada laporan bermasalah', sum)
      + kartu('9. Open risk per kelompok aktivitas & detail lokasi', 'Klik baris untuk bahaya-risiko spesifiknya · status aktivitas diubah di tab Identifikasi harian',
        tabel('<th style="min-width:200px">Kelompok aktivitas</th><th style="min-width:200px">Lokasi · detail lokasi</th><th>Terakhir dilaporkan</th><th>Terkait insiden?</th><th class="r">Tidak teridentifikasi<br>(Gap1)</th><th class="r">Belum closed<br>(Gap2)</th><th class="r">Berulang<br>(Gap3)</th><th class="r">OAK Stop<br>(Gap4)</th>',
          daftar.length ? tot + baris : '', 'Tidak ada open risk pada filter ini.', 1150));
  }

  // ------------------------------------------------ 10 temuan gap
  function temuan(X) {
    const hari = C.M.sampai;
    const baris = (X.D.temuan || []).map((t) => {
      const l = X.dl[String(t.dl)] || {}, lewat = t.janji && t.janji < hari;
      return `<tr><td style="white-space:nowrap">${pendek(t.tanggal)}<br><span class="dr-mute">${E(t.jenis)}</span></td><td>${E(l.lokasi)} · <b>${E(l.detlok)}</b></td>
        <td><b>${E(t.ket)}</b><br><span class="dr-mute">${E(t.subket)}</span></td><td><div class="pr-clamp" title="${E(t.deskripsi)}">${E(t.deskripsi)}</div>${(t.aktivitas || []).length ? `<span class="dr-mute">Aktivitas: ${E(t.aktivitas.slice(0, 3).join(', '))}</span>` : ''}</td>
        <td><span class="dr-tag st-G">${E(t.status)}</span></td><td>${E(t.pic || '–')}</td>
        <td>${t.janji ? (lewat ? `<span class="br-red">${pendek(t.janji)} · lewat</span>` : pendek(t.janji)) : '<span class="dr-mute">–</span>'}</td></tr>`;
    }).join('');
    const total = X.D.temuanTotal || 0, n = (X.D.temuan || []).length;
    const lewat = (X.D.temuan || []).filter((t) => t.janji && t.janji < hari).length;
    return kartu('10. Detail temuan yang masih menjadi gap', `${F(total)} temuan Hazard/Inspeksi open (Gap2) · ${F(lewat)} lewat tanggal janji${total > n ? ` · ditampilkan ${F(n)} terbaru` : ''}`,
      tabel('<th>Tanggal</th><th>Detail lokasi</th><th style="min-width:220px">Ketidaksesuaian</th><th style="min-width:280px">Deskripsi</th><th>Status</th><th>PIC</th><th>Janji</th>', baris, 'Tidak ada temuan open.', 1100));
  }

  // ------------------------------------------------------------ event & flag
  function pasang(X, isi) {
    isi.querySelectorAll('details.bp-akt').forEach((d) => d.addEventListener('toggle', () => {
      S.buka[d.dataset.k] = d.open;
      const box = d.querySelector('.bp-isi');
      if (d.open && !box.innerHTML.trim()) box.innerHTML = isiAkt(AKT[d.dataset.k] || [], C.U || {});
    }));
    isi.querySelectorAll('tr[data-buka]').forEach((tr) => tr.addEventListener('click', () => { S.buka[tr.dataset.buka] = !S.buka[tr.dataset.buka]; render(); }));
    isi.querySelectorAll('[data-flag]').forEach((b) => b.addEventListener('click', (e) => { e.stopPropagation(); bukaFlag(X, b.dataset.flag, b.dataset.kel); }));
    const semua = isi.querySelector('#bp-semua-harian');
    if (semua) semua.addEventListener('click', () => { S.semuaHarian = !S.semuaHarian; render(); });
  }

  function dialog() {
    let d = document.getElementById('bp-flag-dlg');
    if (d) return d;
    d = document.createElement('dialog');
    d.id = 'bp-flag-dlg';
    d.className = 'bp-dialog';
    d.setAttribute('aria-labelledby', 'bp-flag-judul');
    d.innerHTML = '<header><div><h3 id="bp-flag-judul"></h3><p id="bp-flag-sub"></p></div><button type="button" aria-label="Tutup"><span class="material-symbols-outlined">close</span></button></header><div class="bp-dlg-isi" id="bp-flag-isi"></div>';
    document.body.appendChild(d);
    d.querySelector('header button').addEventListener('click', () => d.close());
    d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
    return d;
  }

  function bukaFlag(X, dl, kel) {
    const d = dialog(), l = X.dl[dl] || {}, riwayat = flagKel(X, dl, kel), hariIni = iso(new Date());
    d.querySelector('#bp-flag-judul').textContent = `Status aktivitas: ${namaKel(kel)}`;
    d.querySelector('#bp-flag-sub').textContent = `${l.lokasi || ''} · ${l.detlok || ''} · saat ini ${nonaktif(X, dl, kel, C.w) ? 'nonaktif (flag disetujui)' : 'ada aktivitas'}`;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const isi = !X.FL.tersedia ? '<div class="pr-empty">Fitur flag belum aktif: tabel peta_risiko_flag_aktivitas belum dimigrasi.</div>'
      : `<form class="bp-form" id="bp-flag-form">
          <label>Status<select name="status"><option value="NONAKTIF">Tidak ada aktivitas (nonaktif)</option><option value="AKTIF">Ada aktivitas</option></select></label>
          <label>Mulai<input type="date" name="mulai" value="${hariIni}" required></label>
          <label>Selesai<input type="date" name="selesai"></label><span></span>
          <label style="grid-column:1/-1">Alasan<textarea name="alasan" rows="2" minlength="10" required placeholder="Mis. pekerjaan dihentikan sementara karena …"></textarea></label>
          <div style="grid-column:1/-1;display:flex;gap:10px;align-items:center"><button type="submit" class="pr-back">Ajukan perubahan status</button><span id="bp-flag-msg" class="dr-mute"></span></div>
        </form>
        <p class="dr-mute" style="font-size:12.5px;margin:10px 0 0">Flag berlaku setelah disetujui orang lain (bukan pengaju). Flag nonaktif mengeluarkan kombinasi ini dari perhitungan minggu yang beririsan.</p>
        <div class="bp-riwayat">${riwayat.map((f) => `<div><b>${f.status === 'NONAKTIF' ? 'Nonaktif' : 'Aktif'} · ${E(f.mulai)} s/d ${E(f.selesai || 'seterusnya')}</b>
          <span class="dr-tag ${f.review === 'APPROVED' ? 'st-K' : f.review === 'REJECTED' ? 'st-L' : 'st-X'}" style="float:right">${f.review === 'APPROVED' ? 'Disetujui' : f.review === 'REJECTED' ? 'Ditolak' : 'Menunggu review'}</span>
          <p style="margin:6px 0 2px">${E(f.alasan)}</p><span class="dr-mute">Diajukan ${E(f.pengaju || '–')} · ${E(f.dibuat)}${f.reviewer ? ` · direview ${E(f.reviewer)}` : ''}${f.catatan ? ` — ${E(f.catatan)}` : ''}</span>
          ${f.review === 'PENDING' && Number(f.pengajuId) !== Number(X.FL.saya) ? `<div style="display:flex;gap:8px;margin-top:8px"><button type="button" class="pr-back" data-review="${f.id}" data-k="APPROVED">Setujui</button>
            <input type="text" placeholder="Alasan bila menolak" data-catatan="${f.id}" style="flex:1;padding:6px 10px;border:1px solid var(--line-2);border-radius:10px"><button type="button" class="pr-back" data-review="${f.id}" data-k="REJECTED">Tolak</button></div>` : ''}</div>`).join('')}</div>`;
    d.querySelector('#bp-flag-isi').innerHTML = isi;
    const kirim = async (url, body) => {
      const r = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) });
      const j = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(j.errors ? Object.values(j.errors).flat().join(' ') : (j.message || 'Gagal menyimpan.'));
    };
    const muatUlang = async () => { delete muat[C.id]; delete siap[C.id]; const Y = await ambil(C.id); render(); bukaFlag(Y, dl, kel); };
    const form = d.querySelector('#bp-flag-form');
    if (form) form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form), msg = d.querySelector('#bp-flag-msg');
      msg.textContent = 'Menyimpan…';
      msg.style.color = '';
      try {
        await kirim(URL_FLAG.replace('__ID__', C.id), { dl, aktivitas: 'kel:' + kel, status: fd.get('status'), mulai: fd.get('mulai'), selesai: fd.get('selesai') || null, alasan: fd.get('alasan') });
        await muatUlang();
      } catch (err) { msg.textContent = err.message; msg.style.color = 'var(--red)'; }
    });
    d.querySelectorAll('[data-review]').forEach((b) => b.addEventListener('click', async () => {
      const catatan = b.dataset.k === 'REJECTED' ? (d.querySelector(`[data-catatan="${b.dataset.review}"]`).value || '').trim() : '';
      if (b.dataset.k === 'REJECTED' && !catatan) { d.querySelector(`[data-catatan="${b.dataset.review}"]`).focus(); return; }
      try { await kirim(URL_REVIEW.replace('__ID__', b.dataset.review), { keputusan: b.dataset.k, catatan }); await muatUlang(); }
      catch (err) { b.insertAdjacentHTML('afterend', `<span class="br-red" style="font-size:12px">${E(err.message)}</span>`); }
    }));
    if (!d.open) d.showModal();
  }

  return function (cfg) {
    C = { el: cfg.el, M: cfg.meta, id: String(cfg.id), w: cfg.w, lv: cfg.lv };
    render();
  };
})();
</script>
