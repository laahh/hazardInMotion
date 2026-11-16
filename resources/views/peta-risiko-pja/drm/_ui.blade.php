{{-- Gaya + helper bersama halaman Peta Risiko DRM (indeks & detail PJA). --}}
@push('styles')
<style>
  .pr { --k: #1F6FB2; --k-bg: #E3EFFA; --g: #C77800; --g-bg: #FFF2D6; --t: #6B7480; --t-bg: #EEF2F6; --x: #8A6FB8; --x-bg: #F0EBF8; --inc: #C8102E; }
  .st-K { --c: var(--k); --bg: var(--k-bg); } .st-G { --c: var(--g); --bg: var(--g-bg); }
  .st-T { --c: var(--t); --bg: var(--t-bg); } .st-X { --c: var(--x); --bg: var(--x-bg); }
  .st-L { --c: var(--inc); --bg: var(--red-bg); } .st-D { --c: var(--done); --bg: var(--done-bg); }
  .lv-Low { --c: var(--green); --bg: var(--done-bg); } .lv-Medium { --c: #9A7300; --bg: #FFF6D6; }
  .lv-High { --c: #D0640C; --bg: #FFEBDD; } .lv-Significant { --c: var(--red); --bg: var(--red-bg); }

  .dr-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--line-2); margin: 0 0 18px; overflow-x: auto; }
  .dr-tabs button { font: 700 14px/1 "Plus Jakarta Sans", sans-serif; color: var(--mute); padding: 13px 16px; border-bottom: 3px solid transparent; white-space: nowrap; }
  .dr-tabs button[aria-selected="true"] { color: var(--navy); border-bottom-color: var(--navy); }
  .dr-bar { display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: center; padding: 14px 22px; border-bottom: 1px solid var(--line); }
  .dr-bar label { font: 700 13px/1 "Plus Jakarta Sans", sans-serif; color: var(--mute); display: inline-flex; gap: 8px; align-items: center; }
  .dr-bar [hidden] { display: none; }
  .dr-bar select { height: 36px; border: 1px solid var(--line-2); border-radius: 10px; padding: 0 10px; font: inherit; font-size: 15px; background: #fff; max-width: 280px; }

  .dr-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; padding: 18px 22px; }
  .dr-kpi { border-radius: 12px; padding: 12px 14px; background: #fff; border: 1px solid var(--line-2); border-left: 4px solid var(--c, var(--line-2)); }
  .dr-kpi b { display: block; font: 800 26px/1.05 "Plus Jakarta Sans", sans-serif; color: var(--c, var(--ink)); }
  .dr-kpi span { display: block; margin-top: 5px; font-size: 13px; color: var(--mute); line-height: 1.3; }

  .dr-two { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
  .dr-mx { width: 100%; border-collapse: separate; border-spacing: 3px; font-size: 13px; }
  .dr-mx th { background: var(--navy); color: #fff; font: 700 12px/1.2 "Plus Jakarta Sans", sans-serif; padding: 8px 6px; border-radius: 4px; }
  .dr-mx td { text-align: center; padding: 9px 4px; border-radius: 4px; font: 800 15px/1 "Plus Jakarta Sans", sans-serif; }
  .dr-mx .z0 { background: #E2F3E5; color: #16712C; } .dr-mx .z1 { background: #FFF2C2; color: #8A6400; }
  .dr-mx .z2 { background: #FFDDBF; color: #A64B00; } .dr-mx .z3 { background: #FAD0D6; color: #A10C24; }
  .dr-mx h4, .dr-h4 { margin: 0 0 8px; font: 700 14px/1.2 "Plus Jakarta Sans", sans-serif; }

  .dr-comp { display: flex; height: 10px; border-radius: 2px; overflow: hidden; background: var(--track); min-width: 120px; }
  .dr-comp i { display: block; height: 100%; background: var(--c); }
  .dr-comp i + i { box-shadow: inset 1px 0 0 #fff; }
  .dr-tag { display: inline-flex; gap: 4px; align-items: center; padding: 2px 8px; border-radius: 6px; font: 700 12px/1.4 "Plus Jakarta Sans", sans-serif; background: var(--bg); color: var(--c); white-space: nowrap; }
  .dr-num { font: 800 15px/1 "Plus Jakarta Sans", sans-serif; }
  .dr-legend { display: flex; flex-wrap: wrap; gap: 8px 16px; font-size: 13px; color: var(--mute); padding: 12px 22px; }
  .dr-legend i { display: inline-block; width: 12px; height: 12px; border-radius: 2px; background: var(--c); vertical-align: -2px; margin-right: 6px; }
  .dr-scroll { overflow-x: auto; }
  .dr-tbl td, .dr-tbl th { padding: 10px 14px; }
  .dr-tbl td { font-size: 14px; }

  /* Tren mingguan */
  .dr-tren { display: flex; align-items: flex-end; gap: 6px; height: 120px; padding: 6px 0 0; }
  .dr-tren div { flex: 1; display: flex; flex-direction: column-reverse; height: 100%; min-width: 14px; }
  .dr-tren i { display: block; background: var(--c); }
  .dr-tren-x { display: flex; gap: 6px; font-size: 11px; color: var(--mute-2); margin-top: 4px; }
  .dr-tren-x span { flex: 1; text-align: center; min-width: 14px; }

  /* Strip harian */
  .dr-strip-tbl { border-collapse: collapse; font-size: 13px; }
  .dr-strip-tbl td, .dr-strip-tbl th { padding: 4px 10px 4px 0; vertical-align: middle; white-space: nowrap; }
  .dr-strip-tbl th { font: 700 11px/1.2 "Plus Jakarta Sans", sans-serif; color: var(--mute-2); text-align: left; }
  .dr-strip-tbl tr[data-k] { cursor: pointer; }
  .dr-strip-tbl tr[data-k]:hover td { background: #F6FAFE; }
  .dr-strip { display: flex; gap: 1px; height: 22px; align-items: stretch; position: relative; }
  .dr-strip span { width: 6px; background: var(--c); position: relative; }
  .dr-strip span.c-G { --c: var(--g); } .dr-strip span.c-C { --c: #8FC1E8; } .dr-strip span.c-K { --c: #12457A; } .dr-strip span.c- { --c: #DCE2E9; }
  .dr-strip span.inc::after { content: ""; position: absolute; top: -6px; left: 0; width: 6px; height: 5px; border-radius: 1px; background: var(--inc); }
  .dr-strip span.wk { margin-left: 3px; }
  .dr-weeks { display: flex; gap: 1px; font-size: 10px; color: var(--mute-2); }
  .dr-weeks span { width: 6px; overflow: visible; white-space: nowrap; }
  .dr-trunc { max-width: 240px; overflow: hidden; text-overflow: ellipsis; display: inline-block; vertical-align: bottom; }

  .dr-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 20px; margin-top: 20px; align-items: start; }
  .dr-grid > .pr-card { margin-top: 0; }
  .dr-list { list-style: none; margin: 0; padding: 0; }
  .dr-list li { padding: 10px 22px; border-bottom: 1px solid var(--line); font-size: 14px; }
  .dr-list li:last-child { border-bottom: 0; }
  .dr-list b { font-family: "Plus Jakarta Sans", sans-serif; }
  .dr-mute { color: var(--mute); font-size: 13px; }
  .dr-spark { display: inline-flex; gap: 2px; align-items: flex-end; height: 18px; }
  .dr-spark i { width: 5px; background: var(--navy); border-radius: 1px; }
  .dr-spark i.z { background: #DCE2E9; height: 2px !important; }
  .dr-lok td, .dr-lok th { padding: 8px 8px; }
  .dr-lok th { white-space: nowrap; }
  .dr-lok td.r { white-space: nowrap; }
  .dr-lok-head { cursor: pointer; background: #FAFCFE; }
  .dr-lok-head:hover td { background: #F1F6FC; }
  .dr-lok-head:focus-visible { outline: 2px solid var(--green); outline-offset: -2px; }
  .dr-lok-child td { font-size: 13px; background: #fff; }
  .dr-caret { display: inline-block; width: 12px; color: var(--mute); }
  .dr-flag-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
  .dr-flag-form label { display: flex; flex-direction: column; gap: 4px; font: 700 12px/1.2 "Plus Jakarta Sans", sans-serif; color: var(--mute); }
  .dr-flag-form select, .dr-flag-form input, .dr-flag-form textarea { font: inherit; font-size: 14px; color: var(--ink); border: 1px solid var(--line-2); border-radius: 8px; padding: 7px 9px; background: #fff; }
  .dr-sec-num { display: inline-grid; place-items: center; width: 22px; height: 22px; border-radius: 50%; background: var(--navy); color: #fff; font: 700 12px/1 "Plus Jakarta Sans", sans-serif; margin-right: 8px; }

  @media (max-width: 1100px) { .dr-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dr-two, .dr-grid { grid-template-columns: 1fr; } }
</style>
@endpush

<script>
  window.DR = {
    ST: {
      K: { simbol: '✓', label: 'Teridentifikasi & terkontrol', pendek: 'Terkontrol' },
      G: { simbol: '!', label: 'Teridentifikasi – ada gap', pendek: 'Ada gap' },
      T: { simbol: '○', label: 'Tidak teridentifikasi (open risk)', pendek: 'Tidak teridentifikasi' },
      X: { simbol: '?', label: 'Tidak terpetakan ke SAP', pendek: 'Tidak terpetakan' },
    },
    URUT: ['G', 'T', 'K', 'X'],
    fmt: (n) => Number(n || 0).toLocaleString('id-ID'),
    pct: (a, b) => b ? (100 * a / b).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%' : '–',
    esc: (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])),
    tag(k, extra = '') { const s = this.ST[k]; return `<span class="dr-tag st-${k}">${s.simbol} ${s.pendek}${extra}</span>`; },
    comp(n) {
      const t = this.URUT.reduce((a, k) => a + (n[k] || 0), 0) || 1;
      const judul = this.URUT.map((k) => `${this.ST[k].pendek}: ${n[k] || 0}`).join(' · ');
      return `<div class="dr-comp" title="${judul}" role="img" aria-label="${judul}">${this.URUT.map((k) => n[k] ? `<i class="st-${k}" style="width:${(100 * n[k] / t).toFixed(2)}%"></i>` : '').join('')}</div>`;
    },
    // Zona warna per sel (0 hijau … 3 merah); baris = kekerapan (Hampir Pasti → Langka), kolom = konsekuensi.
    ZONA: [[0, 1, 2, 3, 3], [0, 1, 2, 3, 3], [0, 0, 1, 2, 3], [0, 0, 1, 2, 3], [0, 0, 0, 1, 2]],
    zona(i, j) { return this.ZONA[i][j]; },
    matriks(judul, m, meta) {
      const head = meta.konsekuensi.map((k) => `<th>${k}</th>`).join('');
      const rows = meta.kekerapan.map((k, i) => `<tr><th>${k}</th>${m[i].map((v, j) => `<td class="z${this.zona(i, j)}">${this.fmt(v)}</td>`).join('')}</tr>`).join('');
      return `<div><h4 class="dr-h4">${judul}</h4><table class="dr-mx"><thead><tr><th></th>${head}</tr></thead><tbody>${rows}</tbody></table></div>`;
    },
    kpiCards(kpi) {
      const f = kpi.final || {};
      const kartu = [
        [kpi.total, 'Total bahaya-risiko unik (baseline: bisa diidentifikasi SAP)', ''],
        [f.Low, 'Low (final)', 'lv-Low'], [f.Medium, 'Medium (final)', 'lv-Medium'],
        [f.High, 'High (final)', 'lv-High'], [f.Significant, 'Significant (final)', 'lv-Significant'],
        [kpi.K, '✓ Teridentifikasi & terkontrol', 'st-K'], [kpi.G, '! Teridentifikasi – ada gap', 'st-G'],
        [kpi.T, '○ Tidak teridentifikasi (open risk)', 'st-T'],
        [kpi.laten, 'Risiko laten (pernah insiden, kini blind spot SAP)', 'st-L'],
        [kpi.turun, 'Risiko turun kategori (terkontrol)', 'st-D'],
      ];
      return kartu.map(([n, l, c]) => `<div class="dr-kpi ${c}"><b>${this.fmt(n)}</b><span>${l}</span></div>`).join('')
        + (kpi.X ? `<div class="dr-kpi st-X"><b>${this.fmt(kpi.X)}</b><span>? Belum terpetakan ke SAP (bukan berarti aman)</span></div>` : '');
    },
    tren(tren) {
      const minggu = Object.keys(tren);
      const maks = Math.max(1, ...minggu.map((m) => tren[m].total));
      const bar = minggu.map((m) => {
        const t = tren[m];
        const seg = ['K', 'G', 'T', 'X'].map((k) => t[k] ? `<i class="st-${k}" style="height:${(100 * t[k] / maks).toFixed(1)}%"></i>` : '').join('');
        return `<div title="${m}: terkontrol ${t.K || 0}, gap ${t.G || 0}, tidak teridentifikasi ${t.T || 0}">${seg}</div>`;
      }).join('');
      const label = minggu.map((m) => `<span>${m.slice(8, 10)}/${m.slice(5, 7)}</span>`).join('');
      return `<div class="dr-tren">${bar}</div><div class="dr-tren-x">${label}</div>`;
    },
    legendaStatus() {
      return `<div class="dr-legend">${this.URUT.map((k) => `<span><i class="st-${k}"></i>${this.ST[k].simbol} ${this.ST[k].label}</span>`).join('')}</div>`;
    },

    // ---- Beranda PJA (satuan ketat): warna & komposisi terkontrol penuh / sebagian / hanya bermasalah / tanpa laporan.
    BW: { ok: '#1F9A3A', part: '#E3B341', bm: '#D0640C', none: '#F5C7A2' },
    sb4(ok, pt, pb, no, besar = false) {
      const t = ok + pt + pb + no, W = this.BW;
      if (!t) return '<div class="br-sb"></div>';
      const w = (x) => (100 * x / t).toFixed(2) + '%';
      return `<div class="br-sb${besar ? ' big' : ''}" title="${this.fmt(ok)} terkontrol penuh; ${this.fmt(pt)} sebagian; ${this.fmt(pb)} hanya bermasalah; ${this.fmt(no)} tanpa laporan"><i style="width:${w(ok)};background:${W.ok}"></i><i style="width:${w(pt)};background:${W.part}"></i><i style="width:${w(pb)};background:${W.bm}"></i><i style="width:${w(no)};background:${W.none}"></i></div>`;
    },
    /** Nomor minggu DRM 2.1: W1 = 1 Jan s.d. Sabtu pertama, lalu Minggu–Sabtu (W46 2026 = 8–14 Nov). */
    wNo(awal) {
      const d = new Date(awal + 'T00:00:00'), j1 = new Date(d.getFullYear(), 0, 1);
      return Math.floor((Math.round((d - j1) / 864e5) + j1.getDay()) / 7) + 1;
    },
    /** Minggu indeks 0 sudah penuh bila Sabtunya ≤ tanggal data. */
    penuh0(awal0, sampai) {
      const z = new Date(awal0 + 'T00:00:00'); z.setDate(z.getDate() + 6);
      return z <= new Date(sampai + 'T00:00:00');
    },
    /** Default minggu = minggu penuh terakhir. */
    wDefault(mg, sampai) { return mg.length > 1 && !this.penuh0(mg[0], sampai) ? 1 : 0; },
    minggu(awal, sampai, i, awal0) {
      const tgl = (s) => new Date(s + 'T00:00:00');
      const geser = (s, n) => { const d = tgl(s); d.setDate(d.getDate() + n); return d; };
      const w = this.wNo(awal), berjalan = i === 0 && !this.penuh0(awal0 || awal, sampai);
      const pendek = (d) => d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
      return { kode: 'W' + w, label: `W${w} · ${pendek(tgl(awal))} – ${pendek(geser(awal, 6))}${berjalan ? ' (berjalan, s.d. ' + pendek(tgl(sampai)) + ')' : ''}` };
    },
  };
</script>
