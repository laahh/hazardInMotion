@extends('ohs-score-card.layouts.app')

@section('title', 'Incident Management & IPLS')

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
<style>
  /*
    Seluruh gaya halaman ini discope ke .imi-wrap.

    Rancangannya datang dari halaman mandiri yang punya :root dan body sendiri;
    kalau dipakai apa adanya, latar dan fontnya akan menimpa seluruh cangkang
    admin. Karena itu variabelnya didefinisikan di .imi-wrap, bukan :root, dan
    tidak ada satu pun selector di sini yang menyentuh body atau html.
  */
  .imi-wrap {
    --surface: #fcfcfb; --surface-2: #eef1ee; --line: #dde2dd;
    --ink: #141a17; --ink-2: #4b5550; --ink-3: #77817b;
    --accent: #0f6b5c;
    --s1: #2a78d6; --s2: #eb6834; --s3: #1baf7a; --s4: #eda100;
    --s5: #e87ba4; --s6: #008300; --s7: #4a3aa7;
    --neutral: #9aa39e;
    --good: #0ca30c; --warning: #fab219; --serious: #ec835a; --critical: #d03b3b;
    --f-display: "Barlow Semi Condensed", "Arial Narrow", system-ui, sans-serif;
    --f-mono: "IBM Plex Mono", ui-monospace, Menlo, monospace;

    display: flex; flex-direction: column; gap: 20px;
    color: var(--ink);
  }

  /* Tema gelap mengikuti atribut yang dipakai cangkang admin maupun preferensi
     sistem; keduanya dicakup supaya warnanya tidak pernah tertinggal. */
  @media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) .imi-wrap {
      --surface: #1a1f1c; --surface-2: #222824; --line: #2f3732;
      --ink: #f1f4f2; --ink-2: #c0c8c3; --ink-3: #8d9791;
      --accent: #4cc2a8;
      --s1: #3987e5; --s2: #d95926; --s3: #199e70; --s4: #c98500;
      --s5: #d55181; --s6: #008300; --s7: #9085e9;
      --neutral: #6c7570;
    }
  }
  :root[data-theme="dark"] .imi-wrap {
    --surface: #1a1f1c; --surface-2: #222824; --line: #2f3732;
    --ink: #f1f4f2; --ink-2: #c0c8c3; --ink-3: #8d9791;
    --accent: #4cc2a8;
    --s1: #3987e5; --s2: #d95926; --s3: #199e70; --s4: #c98500;
    --s5: #d55181; --s6: #008300; --s7: #9085e9;
    --neutral: #6c7570;
  }

  .imi-wrap * { box-sizing: border-box; }

  .imi-top {
    display: flex; flex-wrap: wrap; align-items: flex-end;
    justify-content: space-between; gap: 16px;
    border-bottom: 2px solid var(--ink); padding-bottom: 14px;
  }
  .imi-eyebrow {
    font-family: var(--f-mono); font-size: 11.5px; letter-spacing: .08em;
    text-transform: uppercase; color: var(--ink-3);
  }
  .imi-wrap h1 {
    font-family: var(--f-display); font-weight: 700;
    font-size: clamp(26px, 4vw, 38px); line-height: 1.05;
    margin: 4px 0 0; letter-spacing: -.01em;
  }
  .imi-wrap h1 span { color: var(--accent); }
  .imi-scope { color: var(--ink-2); font-size: 13.5px; margin-top: 6px; max-width: 68ch; }

  .imi-filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
  .imi-fgroup { display: flex; flex-direction: column; gap: 4px; }
  .imi-fgroup .imi-lbl {
    font-family: var(--f-mono); font-size: 11px; letter-spacing: .06em;
    text-transform: uppercase; color: var(--ink-3);
  }
  .imi-seg {
    display: inline-flex; border: 1px solid var(--line); border-radius: 8px;
    background: var(--surface); padding: 2px;
  }
  .imi-seg button {
    font-weight: 500; font-size: 13.5px; color: var(--ink-2);
    background: transparent; border: 0; border-radius: 6px; padding: 6px 12px; cursor: pointer;
  }
  .imi-seg button[aria-pressed="true"] { background: var(--ink); color: var(--surface); }
  .imi-seg button:focus-visible, .imi-wrap select:focus-visible {
    outline: 2px solid var(--accent); outline-offset: 2px;
  }
  .imi-wrap select {
    font-weight: 500; font-size: 13.5px; color: var(--ink); background: var(--surface);
    border: 1px solid var(--line); border-radius: 8px; padding: 7px 30px 7px 10px; min-width: 150px;
  }

  .imi-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
  .imi-kpi {
    background: var(--surface); border: 1px solid var(--line); border-radius: 10px;
    padding: 14px 16px; display: flex; flex-direction: column; gap: 2px; min-width: 0;
  }
  .imi-kpi .k-l { font-size: 13px; color: var(--ink-2); }
  .imi-kpi .k-v {
    font-family: var(--f-display); font-weight: 600; font-size: 32px; line-height: 1.1;
    font-variant-numeric: tabular-nums;
  }
  .imi-kpi .k-s { font-family: var(--f-mono); font-size: 12px; color: var(--ink-3); }
  .imi-meter {
    height: 4px; border-radius: 2px; background: var(--surface-2);
    margin-top: 8px; overflow: hidden;
  }
  .imi-meter i { display: block; height: 100%; background: var(--accent); border-radius: 2px; }
  .imi-kpi.alert { border-left: 4px solid var(--critical); }
  .imi-pill {
    display: inline-flex; align-items: center; gap: 5px; font-family: var(--f-mono);
    font-size: 11.5px; padding: 1px 8px; border-radius: 99px;
    background: var(--surface-2); color: var(--ink-2); width: fit-content;
  }
  .imi-pill::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--critical); }

  .imi-g2 { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: 16px; }
  .imi-g2b { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; }
  .imi-panel {
    background: var(--surface); border: 1px solid var(--line); border-radius: 10px;
    padding: 16px 18px; min-width: 0; display: flex; flex-direction: column; gap: 10px;
  }
  .imi-ph { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 10px; }
  .imi-ph h2 {
    font-family: var(--f-display); font-weight: 600; font-size: 20px;
    line-height: 1.2; margin: 0; color: var(--ink);
  }
  .imi-ph p { margin: 2px 0 0; color: var(--ink-2); font-size: 13.5px; max-width: 72ch; }
  .imi-tag {
    font-family: var(--f-mono); font-size: 11px; letter-spacing: .06em;
    text-transform: uppercase; color: var(--accent); margin-bottom: 2px;
  }
  .imi-chart { width: 100%; height: 320px; }
  .imi-scroll { overflow-x: auto; margin-inline: -4px; padding-inline: 4px; }
  .imi-scroll .imi-chart { min-width: 760px; }
  .imi-sk-a { height: 440px; } .imi-sk-b { height: 620px; } .imi-sk-c { height: 380px; min-width: 520px; }

  .imi-read { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
  .imi-read > div {
    border-top: 2px solid var(--accent); padding-top: 8px;
    font-size: 14px; color: var(--ink-2); min-width: 0;
  }
  .imi-read b {
    display: block; font-family: var(--f-display); font-weight: 600;
    font-size: 21px; color: var(--ink); font-variant-numeric: tabular-nums;
  }
  .imi-legend { display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: 12.5px; color: var(--ink-2); }
  .imi-legend span { display: inline-flex; align-items: center; gap: 6px; }
  .imi-legend i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }

  .imi-wrap table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
  .imi-wrap th {
    text-align: left; font-family: var(--f-mono); font-size: 11px; letter-spacing: .06em;
    text-transform: uppercase; color: var(--ink-3); font-weight: 500;
    padding: 6px 8px; border-bottom: 1px solid var(--line);
  }
  .imi-wrap td { padding: 7px 8px; border-bottom: 1px solid var(--line); vertical-align: middle; color: var(--ink); }
  .imi-wrap td.num, .imi-wrap th.num {
    text-align: right; font-variant-numeric: tabular-nums;
    font-family: var(--f-mono); font-size: 12.5px;
  }
  .imi-lchip {
    display: inline-block; font-family: var(--f-mono); font-size: 11px;
    padding: 1px 6px; border-radius: 4px; color: #fff; margin-right: 6px;
  }
  .imi-bar { height: 8px; border-radius: 0 4px 4px 0; background: var(--accent); min-width: 2px; }

  .imi-notes {
    font-size: 13px; color: var(--ink-2); border-top: 1px solid var(--line);
    padding-top: 14px; display: grid; gap: 6px; max-width: 100ch;
  }
  .imi-notes b { color: var(--ink); }
  .imi-notes code {
    font-family: var(--f-mono); font-size: 12px;
    background: var(--surface-2); padding: 1px 5px; border-radius: 4px; color: var(--ink-2);
  }
  .imi-empty { color: var(--ink-3); font-size: 14px; padding: 30px 0; text-align: center; }
  .imi-gagal {
    background: var(--surface); border: 1px solid var(--critical);
    border-left-width: 4px; border-radius: 10px; padding: 16px 18px; color: var(--ink);
  }
  .imi-gagal h2 { font-family: var(--f-display); font-size: 19px; margin: 0 0 6px; }
  .imi-gagal p { margin: 0 0 6px; color: var(--ink-2); font-size: 13.5px; }
  .imi-gagal code { font-family: var(--f-mono); font-size: 12px; color: var(--ink-3); word-break: break-all; }

  @media (max-width: 1000px) {
    .imi-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .imi-g2, .imi-g2b { grid-template-columns: minmax(0, 1fr); }
  }
  @media (max-width: 620px) {
    .imi-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .imi-read { grid-template-columns: minmax(0, 1fr); }
    .imi-kpi .k-v { font-size: 27px; }
  }
  @media (prefers-reduced-motion: reduce) {
    .imi-wrap * { transition: none !important; animation: none !important; }
  }
</style>
@endsection

@section('content')
<div class="imi-wrap" data-url="{{ route('ohs-score-card.incident-management.dashboard.data') }}">

  <header class="imi-top">
    <div>
      <div class="imi-eyebrow">OBDS · beInvestigasi · {{ $tabel }}</div>
      <h1>Incident Management <span>&amp; IPLS</span></h1>
      <div class="imi-scope" data-imi="scope">Memuat data dari OBDS…</div>
    </div>
    <div class="imi-filters">
      <div class="imi-fgroup">
        <span class="imi-lbl" id="imi-lbl-tahun">Tahun</span>
        <div class="imi-seg" role="group" aria-labelledby="imi-lbl-tahun" data-imi="seg-tahun"></div>
      </div>
      <div class="imi-fgroup">
        <label class="imi-lbl" for="imi-site">Site</label>
        <select id="imi-site" data-imi="site"><option value="all">Semua site</option></select>
      </div>
    </div>
  </header>

  <div data-imi="gagal" hidden></div>

  <div data-imi="isi" hidden>

    <section class="imi-kpis" data-imi="kpis" aria-label="Ringkasan"></section>

    <div class="imi-g2" style="margin-top:20px">
      <section class="imi-panel">
        <div class="imi-ph"><div>
          <div class="imi-tag">Tren</div>
          <h2>Insiden per bulan</h2>
          <p>Ditumpuk per status investigasi. Insiden "Tidak investigasi" umumnya Illness, Fire Case kecil, dan Spill.</p>
        </div></div>
        <div class="imi-legend" data-imi="lg-tren"></div>
        <div class="imi-chart" data-imi="ch-tren"></div>
      </section>
      <section class="imi-panel">
        <div class="imi-ph"><div>
          <div class="imi-tag">Sebaran</div>
          <h2>Insiden per site</h2>
          <p>Bagian gelap adalah insiden yang sudah punya analisis IPLS.</p>
        </div></div>
        <div class="imi-legend" data-imi="lg-site"></div>
        <div class="imi-chart" data-imi="ch-site"></div>
      </section>
    </div>

    <section class="imi-panel" style="margin-top:20px">
      <div class="imi-ph"><div>
        <div class="imi-tag">Sankey 1 · Alur insiden</div>
        <h2>Jenis insiden → Kategori kecelakaan → Status investigasi</h2>
        <p>Lebar alur = jumlah insiden. Arahkan kursor ke alur untuk melihat angkanya.</p>
      </div></div>
      <div class="imi-scroll"><div class="imi-chart imi-sk-a" data-imi="sk-a"></div></div>
    </section>

    <section class="imi-panel" style="margin-top:20px">
      <div class="imi-ph">
        <div>
          <div class="imi-tag">Sankey 2 · Korelasi IPLS</div>
          <h2>Kategori kecelakaan → Layer IPLS → Aktivitas penyebab</h2>
          <p>Lebar alur = jumlah temuan layer di analisis investigasi. Satu insiden biasanya punya
             beberapa temuan lintas layer. Aktivitas di luar 14 teratas digabung jadi "Lainnya" per layer.</p>
        </div>
        <div class="imi-fgroup">
          <span class="imi-lbl" id="imi-lbl-st">Status layer</span>
          <div class="imi-seg" role="group" aria-labelledby="imi-lbl-st" data-imi="seg-status"></div>
        </div>
      </div>
      <div class="imi-legend" data-imi="lg-layer"></div>
      <div class="imi-scroll"><div class="imi-chart imi-sk-b" data-imi="sk-b"></div></div>
      <div class="imi-read" data-imi="read-b"></div>
    </section>

    <div class="imi-g2b" style="margin-top:20px">
      <section class="imi-panel">
        <div class="imi-ph"><div>
          <div class="imi-tag">Sankey 3 · Efektivitas perbaikan</div>
          <h2>Layer root cause → Layer CAR</h2>
          <p>Jumlah insiden yang root cause-nya ada di layer kiri dan punya tindakan perbaikan di
             layer kanan. Satu insiden bisa muncul di beberapa alur.</p>
        </div></div>
        <div class="imi-scroll"><div class="imi-chart imi-sk-c" data-imi="sk-c"></div></div>
        <div class="imi-read" data-imi="read-c" style="grid-template-columns:repeat(2,minmax(0,1fr))"></div>
      </section>
      <section class="imi-panel">
        <div class="imi-ph"><div>
          <div class="imi-tag">Peta panas</div>
          <h2>Site × Layer IPLS</h2>
          <p>Jumlah temuan per site dan layer, mengikuti pilihan status layer di Sankey 2.</p>
        </div></div>
        <div class="imi-scroll"><div class="imi-chart" data-imi="ch-heat" style="height:380px;min-width:480px"></div></div>
      </section>
    </div>

    <div class="imi-g2b" style="margin-top:20px">
      <section class="imi-panel">
        <div class="imi-ph"><div>
          <div class="imi-tag">Peringkat</div>
          <h2>Aktivitas penyebab teratas</h2>
          <p>Mengikuti pilihan status layer di Sankey 2.</p>
        </div></div>
        <div class="imi-scroll">
          <table>
            <thead><tr><th>Aktivitas</th><th class="num">Temuan</th><th style="width:34%">Porsi</th></tr></thead>
            <tbody data-imi="tbl-akt"></tbody>
          </table>
        </div>
      </section>
      <section class="imi-panel">
        <div class="imi-ph"><div>
          <div class="imi-tag">Tindak lanjut</div>
          <h2>Status CAR per layer perbaikan</h2>
          <p>Tindakan perbaikan dari hasil investigasi. Overdue = masih Open/Pending dan sudah lewat target.</p>
        </div></div>
        <div class="imi-legend" data-imi="lg-car"></div>
        <div class="imi-chart" data-imi="ch-car" style="height:300px"></div>
      </section>
    </div>

  </div>

  <footer class="imi-notes">
    <div><b>Sumber:</b> <code>{{ $tabel }}</code> (OBDS), kolom JSONB <code>rootcause</code> dan
      <code>tindakan_perbaikan</code>. Materialized view, jadi isinya snapshot, bukan realtime.
      Tidak termasuk status DELETED dan data uji ("Test"). <span data-imi="meta"></span></div>
    <div><b>Tanggal &amp; site:</b> memakai tanggal/site hasil validasi investigasi; bila kosong,
      memakai data laporan CCR.</div>
    <div><b>Kategori:</b> Near Miss, Near Miss HIPO dan Non HIPO digabung jadi Near Miss;
      First Aid, Medical Treatment dan Major Injury jadi Injury; Hazard HIPO dan Pelanggaran PSPP
      digabung. "Belum dikategorikan" = kategori kecelakaan belum diisi (kebanyakan insiden yang
      tidak diinvestigasi).</div>
    <div><b>Label layer:</b> nama pendek tiap layer (Sistem &amp; Kebijakan, dst.) disusun dari
      daftar aktivitas di dalamnya, bukan nama resmi sistem. Insiden yang root cause-nya baru ada
      di MySQL <code>app_mixer.lpi_insiden</code> (belum sinkron ke beInvestigasi) belum ikut dihitung.</div>
    <div><b>Sankey 3</b> hanya memakai temuan ber-status <i>Root cause</i>, dan pasangannya dihitung
      per insiden — bukan per temuan — supaya satu insiden dengan banyak root cause dan banyak CAR
      tidak menggelembungkan alurnya.</div>
  </footer>

</div>
@endsection

@section('page-scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.5.0/echarts.min.js"></script>
<script>
// ---- Dashboard Incident Management & IPLS -----------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.imi-wrap');
    if (!root) { return; }

    var el = function (n) { return root.querySelector('[data-imi="' + n + '"]'); };
    var fmt = function (n) { return Number(n || 0).toLocaleString('id-ID'); };
    var pct = function (a, b) { return b ? Math.round(a / b * 100) : 0; };
    var esc = function (v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };

    var BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    var payload = null;
    var state = { tahun: 'all', site: 'all', status: 'all' };

    // Posisi filter diingat supaya berpindah halaman lalu kembali tidak
    // mengulang memilih tahun dan site yang sama.
    try {
        var simpan = JSON.parse(window.localStorage.getItem('imi-filter') || '{}');
        if (simpan && typeof simpan === 'object') { Object.assign(state, simpan); }
    } catch (err) { /* localStorage bisa diblokir; filter default saja */ }

    function ingat() {
        try { window.localStorage.setItem('imi-filter', JSON.stringify(state)); }
        catch (err) { /* menyimpan posisi filter tidak pantas menggagalkan halaman */ }
    }

    // ---- Warna ------------------------------------------------------------
    function v(nama) { return getComputedStyle(root).getPropertyValue(nama).trim(); }

    function palet() {
        return {
            ink: v('--ink'), ink2: v('--ink-2'), ink3: v('--ink-3'),
            line: v('--line'), surface: v('--surface'), accent: v('--accent'),
            neutral: v('--neutral'), good: v('--good'), warning: v('--warning'),
            serious: v('--serious'), critical: v('--critical'),
            kat: [v('--s1'), v('--s2'), v('--s3'), v('--s4'), v('--s5'), v('--s6'), v('--s7')],
            layer: [v('--neutral'), v('--s1'), v('--s2'), v('--s3'), v('--s4'), v('--s5')],
            mono: v('--f-mono'), font: v('--f-display')
        };
    }

    // Warna dipetakan dari LABEL, bukan indeks: urutan dimensi datang dari
    // server dan bisa bergeser kalau ada nilai baru di sumber.
    function warnaStatus(label, c) {
        return ({
            'Investigasi': c.kat[0],
            'Tidak investigasi': c.neutral,
            'Insiden baru': c.kat[3]
        })[label] || c.kat[6];
    }

    function warnaKategori(label, c) {
        return ({
            'Pelanggaran Golden Rules': c.kat[1],
            'Near Miss': c.kat[0],
            'Property Damage': c.kat[2],
            'Injury': c.critical,
            'Fire Case': c.kat[3],
            'Hazard HIPO / PSPP': c.kat[4],
            'Belum dikategorikan': c.neutral
        })[label] || c.kat[6];
    }

    function warnaCar(label, c) {
        return ({
            'Closed': c.good, 'Closed overdue': c.serious, 'Open': c.warning,
            'Pending approval': c.kat[0], 'Reject': c.critical, 'Lainnya': c.neutral
        })[label] || c.neutral;
    }

    // ---- ECharts ----------------------------------------------------------
    var charts = {};

    function ch(nama) {
        if (typeof echarts === 'undefined') { return null; }
        if (!charts[nama]) {
            charts[nama] = echarts.init(el(nama), null, { renderer: 'svg' });
        }
        return charts[nama];
    }

    window.addEventListener('resize', function () {
        Object.keys(charts).forEach(function (k) { charts[k].resize(); });
    });

    function tip(c) {
        return {
            backgroundColor: c.surface, borderColor: c.line,
            textStyle: { color: c.ink, fontSize: 13 },
            extraCssText: 'box-shadow:0 4px 14px rgba(0,0,0,.12);border-radius:8px'
        };
    }

    function legenda(nama, items) {
        el(nama).innerHTML = items.map(function (it) {
            return '<span><i style="background:' + it[1] + '"></i>' + esc(it[0]) + '</span>';
        }).join('');
    }

    // ---- Penyaringan ------------------------------------------------------
    // Indeks kolom tiap kumpulan; dinamai supaya pembacaan baris tidak berupa
    // deretan angka tanpa arti.
    var D1 = { ym: 0, site: 1, jenis: 2, kat: 3, status: 4, n: 5, ipls: 6 };
    var D2 = { tahun: 0, site: 1, kat: 2, akt: 3, status: 4, n: 5 };
    var CAR = { tahun: 0, site: 1, layer: 2, status: 3, overdue: 4, n: 5 };
    var RC = { tahun: 0, site: 1, rc: 2, car: 3, n: 4 };

    function cocokTahun(nilai) {
        return state.tahun === 'all' || String(nilai) === String(state.tahun);
    }

    function cocokSite(idx) {
        return state.site === 'all' || String(idx) === String(state.site);
    }

    function saringD1() {
        return payload.d1.filter(function (r) {
            return cocokTahun(r[D1.ym].slice(0, 4)) && cocokSite(r[D1.site]);
        });
    }

    function saringD2(pakaiStatus) {
        return payload.d2.filter(function (r) {
            return cocokTahun(r[D2.tahun]) && cocokSite(r[D2.site])
                && (!pakaiStatus || state.status === 'all' || String(r[D2.status]) === String(state.status));
        });
    }

    function saringCar() {
        return payload.car.filter(function (r) {
            return cocokTahun(r[CAR.tahun]) && cocokSite(r[CAR.site]);
        });
    }

    function saringRc() {
        return payload.rc.filter(function (r) {
            return cocokTahun(r[RC.tahun]) && cocokSite(r[RC.site]);
        });
    }

    function jumlah(rows, kolom) {
        return rows.reduce(function (s, r) { return s + r[kolom]; }, 0);
    }

    // ---- Panel ------------------------------------------------------------
    function renderKpi(c, d1, carRows) {
        var dim = payload.dim;
        var iTidak = dim.status.indexOf('Tidak investigasi');
        var iInv = dim.status.indexOf('Investigasi');

        var total = jumlah(d1, D1.n);
        var inv = jumlah(d1.filter(function (r) { return r[D1.status] === iInv; }), D1.n);
        var tidak = jumlah(d1.filter(function (r) { return r[D1.status] === iTidak; }), D1.n);
        var ipls = jumlah(d1, D1.ipls);

        var carTot = jumlah(carRows, CAR.n);
        var iClosed = dim.status_car.indexOf('Closed');
        var iClosedOd = dim.status_car.indexOf('Closed overdue');
        var iOpen = dim.status_car.indexOf('Open');
        var iPending = dim.status_car.indexOf('Pending approval');

        var carClosed = jumlah(carRows.filter(function (r) {
            return r[CAR.status] === iClosed || r[CAR.status] === iClosedOd;
        }), CAR.n);
        var carJalan = jumlah(carRows.filter(function (r) {
            return r[CAR.status] === iOpen || r[CAR.status] === iPending;
        }), CAR.n);
        var od = jumlah(carRows.filter(function (r) { return r[CAR.overdue] === 1; }), CAR.n);

        var kartu = [
            ['Total insiden', fmt(total), fmt(tidak) + ' tidak diinvestigasi', null, false],
            ['Diinvestigasi', fmt(inv), pct(inv, total) + '% dari total', pct(inv, total), false],
            ['Punya analisis IPLS', fmt(ipls), pct(ipls, inv) + '% dari yang diinvestigasi', pct(ipls, inv), false],
            ['Tindakan perbaikan (CAR)', fmt(carTot),
                pct(carClosed, carTot) + '% sudah closed · ' + fmt(carJalan) + ' masih berjalan',
                pct(carClosed, carTot), false],
            ['CAR lewat target', fmt(od),
                od ? 'Open/Pending melewati target' : 'Tidak ada yang lewat target', null, od > 0]
        ];

        el('kpis').innerHTML = kartu.map(function (k) {
            return '<div class="imi-kpi' + (k[4] ? ' alert' : '') + '">'
                + '<div class="k-l">' + esc(k[0]) + '</div>'
                + '<div class="k-v">' + k[1] + '</div>'
                + '<div class="k-s">' + esc(k[2]) + '</div>'
                + (k[3] !== null ? '<div class="imi-meter"><i style="width:' + k[3] + '%"></i></div>' : '')
                + (k[4] ? '<span class="imi-pill">Perlu ditindaklanjuti</span>' : '')
                + '</div>';
        }).join('');
    }

    function renderTren(c, d1) {
        var dim = payload.dim;
        var bulanAda = {};
        d1.forEach(function (r) { bulanAda[r[D1.ym]] = true; });
        var bulan = Object.keys(bulanAda).sort();

        var urut = ['Investigasi', 'Tidak investigasi', 'Insiden baru'].filter(function (s) {
            return dim.status.indexOf(s) !== -1;
        });

        legenda('lg-tren', urut.map(function (s) { return [s, warnaStatus(s, c)]; }));

        var g = ch('ch-tren');
        if (!g) { return; }

        g.setOption({
            animation: false,
            grid: { left: 40, right: 8, top: 10, bottom: 28 },
            tooltip: Object.assign(tip(c), {
                trigger: 'axis',
                axisPointer: { type: 'shadow', shadowStyle: { color: 'rgba(127,127,127,.08)' } }
            }),
            xAxis: {
                type: 'category',
                data: bulan.map(function (ym) {
                    return BULAN[+ym.slice(4) - 1] + (state.tahun === 'all' ? ' ' + ym.slice(2, 4) : '');
                }),
                axisLine: { lineStyle: { color: c.line } }, axisTick: { show: false },
                axisLabel: { color: c.ink3, fontSize: 11, hideOverlap: true }
            },
            yAxis: {
                type: 'value', splitLine: { lineStyle: { color: c.line, type: 'dashed' } },
                axisLabel: { color: c.ink3, fontSize: 11 }
            },
            series: urut.map(function (s, i) {
                var idx = dim.status.indexOf(s);
                return {
                    name: s, type: 'bar', stack: 'a', barMaxWidth: 26,
                    itemStyle: {
                        color: warnaStatus(s, c), borderColor: c.surface, borderWidth: 1,
                        borderRadius: i === urut.length - 1 ? [4, 4, 0, 0] : 0
                    },
                    data: bulan.map(function (ym) {
                        return jumlah(d1.filter(function (r) {
                            return r[D1.ym] === ym && r[D1.status] === idx;
                        }), D1.n);
                    })
                };
            })
        }, true);
    }

    function renderSite(c, d1) {
        var per = {};
        d1.forEach(function (r) {
            var k = r[D1.site];
            if (!per[k]) { per[k] = { n: 0, r: 0 }; }
            per[k].n += r[D1.n];
            per[k].r += r[D1.ipls];
        });

        var baris = Object.keys(per).map(function (k) {
            return { nama: payload.dim.site[k], n: per[k].n, r: per[k].r };
        }).sort(function (a, b) { return a.n - b.n; });

        legenda('lg-site', [['Dengan analisis IPLS', c.accent], ['Belum ada analisis', c.neutral]]);

        var g = ch('ch-site');
        if (!g) { return; }

        g.setOption({
            animation: false,
            grid: { left: 88, right: 40, top: 6, bottom: 20 },
            tooltip: Object.assign(tip(c), {
                trigger: 'axis',
                axisPointer: { type: 'shadow', shadowStyle: { color: 'rgba(127,127,127,.08)' } }
            }),
            xAxis: {
                type: 'value', splitLine: { lineStyle: { color: c.line, type: 'dashed' } },
                axisLabel: { color: c.ink3, fontSize: 11 }
            },
            yAxis: {
                type: 'category', data: baris.map(function (x) { return x.nama; }),
                axisLine: { show: false }, axisTick: { show: false },
                axisLabel: { color: c.ink2, fontSize: 12 }
            },
            series: [
                {
                    name: 'Dengan analisis IPLS', type: 'bar', stack: 'a', barMaxWidth: 18,
                    itemStyle: { color: c.accent, borderColor: c.surface, borderWidth: 1 },
                    data: baris.map(function (x) { return x.r; })
                },
                {
                    name: 'Belum ada analisis', type: 'bar', stack: 'a', barMaxWidth: 18,
                    itemStyle: { color: c.neutral, borderColor: c.surface, borderWidth: 1, borderRadius: [0, 4, 4, 0] },
                    data: baris.map(function (x) { return x.n - x.r; }),
                    label: {
                        show: true, position: 'right', color: c.ink2,
                        fontFamily: c.mono, fontSize: 11,
                        formatter: function (p) { return fmt(baris[p.dataIndex].n); }
                    }
                }
            ]
        }, true);
    }

    /** Sankey generik. Nama simpul berprefiks "x:" supaya dua kolom boleh berlabel sama. */
    function sankey(nama, tautan, warnaSimpul, c, opt) {
        var g = ch(nama);
        if (!g) { return; }

        var links = Object.keys(tautan).filter(function (k) { return tautan[k] > 0; }).map(function (k) {
            var p = k.split('\u0001');
            return { source: p[0], target: p[1], value: tautan[k] };
        });

        if (!links.length) {
            g.clear();
            return;
        }

        var nama2 = {};
        links.forEach(function (l) { nama2[l.source] = true; nama2[l.target] = true; });

        var keluar = {}, masuk = {};
        links.forEach(function (l) {
            keluar[l.source] = (keluar[l.source] || 0) + l.value;
            masuk[l.target] = (masuk[l.target] || 0) + l.value;
        });
        var nilai = function (n) { return Math.max(keluar[n] || 0, masuk[n] || 0); };
        var polos = function (n) { return n.slice(2); };

        g.setOption({
            animation: false,
            tooltip: Object.assign(tip(c), {
                trigger: 'item',
                formatter: function (p) {
                    return p.dataType === 'edge'
                        ? polos(p.data.source) + ' → ' + polos(p.data.target)
                            + '<br><b>' + fmt(p.data.value) + '</b> ' + opt.unit
                        : polos(p.name) + '<br><b>' + fmt(nilai(p.name)) + '</b> ' + opt.unit;
                }
            }),
            series: [{
                type: 'sankey', left: 4, right: opt.tingkat === 2 ? 150 : 230, top: 8, bottom: 8,
                nodeWidth: 12, nodeGap: 10, draggable: false, layoutIterations: 64,
                emphasis: { focus: 'adjacency' },
                data: Object.keys(nama2).map(function (n) {
                    return { name: n, itemStyle: { color: warnaSimpul(n), borderColor: c.surface, borderWidth: 1 } };
                }),
                links: links,
                lineStyle: { color: 'source', opacity: 0.32, curveness: 0.5 },
                label: {
                    color: c.ink, fontSize: 12,
                    formatter: function (p) { return polos(p.name) + '  {n|' + fmt(nilai(p.name)) + '}'; },
                    rich: { n: { fontFamily: c.mono, fontSize: 11, color: c.ink3 } }
                }
            }]
        }, true);
    }

    function tambah(obj, a, b, n) {
        var k = a + '\u0001' + b;
        obj[k] = (obj[k] || 0) + n;
    }

    function renderSankeyA(c, d1) {
        var dim = payload.dim;
        var tautan = {};

        d1.forEach(function (r) {
            tambah(tautan, 'j:' + dim.jenis[r[D1.jenis]], 'k:' + dim.kategori[r[D1.kat]], r[D1.n]);
            tambah(tautan, 'k:' + dim.kategori[r[D1.kat]], 't:' + dim.status[r[D1.status]], r[D1.n]);
        });

        sankey('sk-a', tautan, function (n) {
            var jenisNode = n.slice(0, 1), label = n.slice(2);
            if (jenisNode === 'k') { return warnaKategori(label, c); }
            if (jenisNode === 't') { return warnaStatus(label, c); }
            return c.ink3;
        }, c, { tingkat: 3, unit: 'insiden' });
    }

    function renderSankeyB(c, d2) {
        var dim = payload.dim;
        var host = el('read-b');

        var total = jumlah(d2, D2.n);

        if (!total) {
            var g = ch('sk-b');
            if (g) { g.clear(); }
            host.innerHTML = '<div class="imi-empty" style="grid-column:1/-1">'
                + 'Belum ada temuan layer untuk filter ini.</div>';
            return;
        }

        // 14 aktivitas teratas tampil sendiri; sisanya dikumpulkan jadi satu
        // simpul "Lainnya" PER LAYER, bukan satu simpul global, supaya alurnya
        // tetap pulang ke layer yang benar.
        var perAkt = {};
        d2.forEach(function (r) { perAkt[r[D2.akt]] = (perAkt[r[D2.akt]] || 0) + r[D2.n]; });

        var top = Object.keys(perAkt).sort(function (a, b) { return perAkt[b] - perAkt[a]; })
            .slice(0, 14).reduce(function (s, k) { s[k] = true; return s; }, {});

        var tautan = {};
        d2.forEach(function (r) {
            var a = dim.aktivitas[r[D2.akt]];
            var namaLayer = dim.layer[a.layer] || 'Tanpa layer';
            var simpul = top[r[D2.akt]] ? 'a:' + a.nama : 'a:Lainnya · L' + a.layer;
            tambah(tautan, 'k:' + dim.kategori[r[D2.kat]], 'l:' + namaLayer, r[D2.n]);
            tambah(tautan, 'l:' + namaLayer, simpul, r[D2.n]);
        });

        var layerDariNama = {};
        dim.aktivitas.forEach(function (a) { layerDariNama[a.nama] = a.layer; });

        legenda('lg-layer', [1, 2, 3, 4, 5].map(function (i) { return [dim.layer[i], c.layer[i]]; }));

        sankey('sk-b', tautan, function (n) {
            var p = n.slice(0, 1), label = n.slice(2);
            if (p === 'k') { return warnaKategori(label, c); }
            if (p === 'l') {
                for (var i = 1; i <= 5; i++) { if (dim.layer[i] === label) { return c.layer[i]; } }
                return c.neutral;
            }
            var m = label.match(/· L(\d)$/);
            return c.layer[m ? +m[1] : (layerDariNama[label] || 0)];
        }, c, { tingkat: 3, unit: 'temuan' });

        var perLayer = [0, 0, 0, 0, 0, 0];
        d2.forEach(function (r) { perLayer[dim.aktivitas[r[D2.akt]].layer] += r[D2.n]; });

        var puncak = perLayer.indexOf(Math.max.apply(null, perLayer));
        var atas = Object.keys(perAkt).sort(function (a, b) { return perAkt[b] - perAkt[a]; })[0];
        var sistem = pct(perLayer[1] + perLayer[2], total);

        host.innerHTML =
            '<div><b>' + pct(perLayer[puncak], total) + '% di ' + esc(dim.layer_pendek[puncak]) + '</b>'
            + 'Layer dengan temuan terbanyak (' + fmt(perLayer[puncak]) + ' dari ' + fmt(total) + ' temuan).</div>'
            + '<div><b>' + fmt(perAkt[atas]) + '× ' + esc(dim.aktivitas[atas].nama) + '</b>'
            + 'Aktivitas penyebab paling sering, di ' + esc(dim.layer_pendek[dim.aktivitas[atas].layer]) + '.</div>'
            + '<div><b>' + sistem + '% di Layer 1–2</b>'
            + 'Porsi temuan pada sistem, kebijakan dan perencanaan. Makin kecil porsinya, makin sering '
            + 'investigasi berhenti di level pelaksana.</div>';
    }

    function renderSankeyC(c, rcRows) {
        var dim = payload.dim;
        var host = el('read-c');
        var total = jumlah(rcRows, RC.n);

        if (!total) {
            var g = ch('sk-c');
            if (g) { g.clear(); }
            host.innerHTML = '<div class="imi-empty" style="grid-column:1/-1">'
                + 'Belum ada pasangan root cause &amp; CAR untuk filter ini.</div>';
            return;
        }

        var tautan = {};
        rcRows.forEach(function (r) {
            tambah(tautan, 'r:' + dim.layer_pendek[r[RC.rc]], 'c:' + dim.layer_pendek[r[RC.car]], r[RC.n]);
        });

        sankey('sk-c', tautan, function (n) {
            var label = n.slice(2);
            for (var i = 0; i <= 5; i++) { if (dim.layer_pendek[i] === label) { return c.layer[i]; } }
            return c.neutral;
        }, c, { tingkat: 2, unit: 'insiden' });

        var sejajar = jumlah(rcRows.filter(function (r) { return r[RC.rc] === r[RC.car]; }), RC.n);
        var ke12 = jumlah(rcRows.filter(function (r) { return r[RC.car] === 1 || r[RC.car] === 2; }), RC.n);

        host.innerHTML =
            '<div><b>' + pct(sejajar, total) + '% alur sejajar</b>'
            + 'CAR berada di layer yang sama dengan root cause-nya.</div>'
            + '<div><b>' + pct(ke12, total) + '% ke Layer 1–2</b>'
            + 'Alur perbaikan yang menyasar sistem dan perencanaan.</div>';
    }

    function renderHeat(c, d2) {
        var dim = payload.dim;
        var siteIdx = state.site === 'all'
            ? dim.site.map(function (_, i) { return i; })
            : [+state.site];

        var data = [];
        var maks = 0;

        siteIdx.forEach(function (si, yi) {
            for (var L = 1; L <= 5; L++) {
                var n = jumlah(d2.filter(function (r) {
                    return r[D2.site] === si && dim.aktivitas[r[D2.akt]].layer === L;
                }), D2.n);
                maks = Math.max(maks, n);
                data.push([L - 1, yi, n]);
            }
        });

        var gelap = document.documentElement.getAttribute('data-theme') === 'dark'
            || (document.documentElement.getAttribute('data-theme') !== 'light'
                && window.matchMedia('(prefers-color-scheme: dark)').matches);

        var g = ch('ch-heat');
        if (!g) { return; }

        g.setOption({
            animation: false,
            grid: { left: 88, right: 10, top: 10, bottom: 54 },
            tooltip: Object.assign(tip(c), {
                formatter: function (p) {
                    return esc(dim.site[siteIdx[p.value[1]]]) + ' · ' + esc(dim.layer[p.value[0] + 1])
                        + '<br><b>' + fmt(p.value[2]) + '</b> temuan';
                }
            }),
            xAxis: {
                type: 'category',
                data: [1, 2, 3, 4, 5].map(function (i) { return dim.layer_pendek[i]; }),
                axisLine: { show: false }, axisTick: { show: false },
                axisLabel: { color: c.ink2, fontSize: 11, interval: 0 }
            },
            yAxis: {
                type: 'category', data: siteIdx.map(function (i) { return dim.site[i]; }), inverse: true,
                axisLine: { show: false }, axisTick: { show: false },
                axisLabel: { color: c.ink2, fontSize: 12 }
            },
            visualMap: {
                min: 0, max: Math.max(maks, 1), show: true, orient: 'horizontal',
                left: 'center', bottom: 0, itemWidth: 10, itemHeight: 140, calculable: false,
                text: ['banyak', 'sedikit'], textStyle: { color: c.ink3, fontSize: 11 },
                inRange: {
                    color: gelap
                        ? ['#222824', '#184f95', '#3987e5', '#86b6ef']
                        : ['#eef3fa', '#9ec5f4', '#3987e5', '#104281']
                }
            },
            series: [{
                type: 'heatmap',
                data: data.map(function (d) {
                    return { value: d, label: { color: d[2] > maks * 0.45 ? '#ffffff' : c.ink } };
                }),
                itemStyle: { borderColor: c.surface, borderWidth: 2, borderRadius: 3 },
                label: {
                    show: true, fontFamily: c.mono, fontSize: 11,
                    formatter: function (p) { return p.value[2] ? p.value[2] : ''; }
                },
                emphasis: { itemStyle: { borderColor: c.ink, borderWidth: 1 } }
            }]
        }, true);
    }

    function renderTabel(c, d2) {
        var dim = payload.dim;
        var per = {};
        d2.forEach(function (r) { per[r[D2.akt]] = (per[r[D2.akt]] || 0) + r[D2.n]; });

        var baris = Object.keys(per).sort(function (a, b) { return per[b] - per[a]; }).slice(0, 10);
        var maks = baris.length ? per[baris[0]] : 1;

        el('tbl-akt').innerHTML = baris.length
            ? baris.map(function (k) {
                var a = dim.aktivitas[k];
                return '<tr>'
                    + '<td><span class="imi-lchip" style="background:' + c.layer[a.layer] + '">L' + a.layer + '</span>'
                    + esc(a.nama) + '</td>'
                    + '<td class="num">' + fmt(per[k]) + '</td>'
                    + '<td><div class="imi-bar" style="width:' + (per[k] / maks * 100) + '%;background:'
                    + c.layer[a.layer] + '"></div></td>'
                    + '</tr>';
            }).join('')
            : '<tr><td colspan="3" class="imi-empty">Belum ada temuan untuk filter ini.</td></tr>';
    }

    function renderCar(c, carRows) {
        var dim = payload.dim;
        var urut = ['Closed', 'Closed overdue', 'Pending approval', 'Open', 'Reject', 'Lainnya']
            .filter(function (s) {
                var i = dim.status_car.indexOf(s);
                return i !== -1 && jumlah(carRows.filter(function (r) { return r[CAR.status] === i; }), CAR.n) > 0;
            });

        legenda('lg-car', urut.map(function (s) { return [s, warnaCar(s, c)]; }));

        var layers = [1, 2, 3, 4, 5, 0];
        var g = ch('ch-car');
        if (!g) { return; }

        g.setOption({
            animation: false,
            grid: { left: 112, right: 46, top: 6, bottom: 20 },
            tooltip: Object.assign(tip(c), {
                trigger: 'axis',
                axisPointer: { type: 'shadow', shadowStyle: { color: 'rgba(127,127,127,.08)' } }
            }),
            xAxis: {
                type: 'value', splitLine: { lineStyle: { color: c.line, type: 'dashed' } },
                axisLabel: { color: c.ink3, fontSize: 11 }
            },
            yAxis: {
                type: 'category', inverse: true,
                data: layers.map(function (l) { return dim.layer_pendek[l]; }),
                axisLine: { show: false }, axisTick: { show: false },
                axisLabel: { color: c.ink2, fontSize: 12 }
            },
            series: urut.map(function (s, ix) {
                var i = dim.status_car.indexOf(s);
                return {
                    name: s, type: 'bar', stack: 'c', barMaxWidth: 18,
                    itemStyle: {
                        color: warnaCar(s, c), borderColor: c.surface, borderWidth: 1,
                        borderRadius: ix === urut.length - 1 ? [0, 4, 4, 0] : 0
                    },
                    data: layers.map(function (l) {
                        return jumlah(carRows.filter(function (r) {
                            return r[CAR.layer] === l && r[CAR.status] === i;
                        }), CAR.n);
                    }),
                    label: ix === urut.length - 1 ? {
                        show: true, position: 'right', color: c.ink2,
                        fontFamily: c.mono, fontSize: 11,
                        formatter: function (p) {
                            return fmt(jumlah(carRows.filter(function (r) {
                                return r[CAR.layer] === layers[p.dataIndex];
                            }), CAR.n));
                        }
                    } : undefined
                };
            })
        }, true);
    }

    // ---- Perakitan --------------------------------------------------------
    function aman(nama, fn) {
        try { fn(); }
        catch (err) {
            if (typeof console !== 'undefined' && console.error) {
                console.error('Incident Management & IPLS: panel "' + nama + '" gagal dirender', err);
            }
        }
    }

    function render() {
        if (!payload) { return; }

        var c = palet();
        var d1 = saringD1();
        var d2Status = saringD2(true);
        var carRows = saringCar();
        var rcRows = saringRc();

        var namaSite = state.site === 'all' ? 'semua site' : payload.dim.site[+state.site];
        var namaTahun = state.tahun === 'all'
            ? payload.tahun[0] + '–' + payload.tahun[payload.tahun.length - 1]
            : state.tahun;

        el('scope').textContent = 'Insiden ' + namaTahun + ', ' + namaSite
            + '. Snapshot materialized view OBDS, beserta analisis 5 layer IPLS dan tindakan perbaikan (CAR).';

        aman('kpi', function () { renderKpi(c, d1, carRows); });
        aman('tren', function () { renderTren(c, d1); });
        aman('site', function () { renderSite(c, d1); });
        aman('sankey-a', function () { renderSankeyA(c, d1); });
        aman('sankey-b', function () { renderSankeyB(c, d2Status); });
        aman('sankey-c', function () { renderSankeyC(c, rcRows); });
        aman('heatmap', function () { renderHeat(c, d2Status); });
        aman('tabel', function () { renderTabel(c, d2Status); });
        aman('car', function () { renderCar(c, carRows); });
    }

    function bangunFilter() {
        var dim = payload.dim;

        var seg = el('seg-tahun');
        seg.innerHTML = '<button type="button" data-v="all">Semua</button>'
            + payload.tahun.map(function (t) {
                return '<button type="button" data-v="' + t + '">' + t + '</button>';
            }).join('');

        var segS = el('seg-status');
        segS.innerHTML = ['Root cause', 'Non-conformity', 'Improvement']
            .filter(function (s) { return dim.status_layer.indexOf(s) !== -1; })
            .map(function (s) {
                return '<button type="button" data-v="' + dim.status_layer.indexOf(s) + '">' + esc(s) + '</button>';
            }).join('') + '<button type="button" data-v="all">Semua</button>';

        var sel = el('site');
        sel.innerHTML = '<option value="all">Semua site</option>'
            + dim.site.map(function (s, i) {
                return '<option value="' + i + '">' + esc(s) + '</option>';
            }).join('');

        // Nilai tersimpan bisa menunjuk tahun atau site yang sudah tidak ada
        // lagi di sumber; kalau tidak diperiksa, halaman tampil kosong tanpa
        // sebab yang terlihat.
        if (state.tahun !== 'all' && payload.tahun.indexOf(+state.tahun) === -1) { state.tahun = 'all'; }
        if (state.site !== 'all' && !dim.site[+state.site]) { state.site = 'all'; }
        if (state.status !== 'all' && !dim.status_layer[+state.status]) { state.status = 'all'; }

        sel.value = state.site;
        sinkron();

        seg.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) { return; }
            state.tahun = b.dataset.v;
            ingat(); sinkron(); render();
        });

        segS.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) { return; }
            state.status = b.dataset.v;
            ingat(); sinkron(); render();
        });

        sel.addEventListener('change', function () {
            state.site = sel.value;
            ingat(); render();
        });
    }

    function sinkron() {
        el('seg-tahun').querySelectorAll('button').forEach(function (b) {
            b.setAttribute('aria-pressed', String(b.dataset.v) === String(state.tahun) ? 'true' : 'false');
        });
        el('seg-status').querySelectorAll('button').forEach(function (b) {
            b.setAttribute('aria-pressed', String(b.dataset.v) === String(state.status) ? 'true' : 'false');
        });
    }

    function tampilkanGagal(pesan, detail) {
        el('scope').textContent = 'Data tidak bisa dimuat.';
        var box = el('gagal');
        box.hidden = false;
        box.innerHTML = '<div class="imi-gagal"><h2>Data tidak bisa dimuat</h2>'
            + '<p>' + esc(pesan) + '</p>'
            + (detail ? '<code>' + esc(detail) + '</code>' : '') + '</div>';
    }

    fetch(root.dataset.url, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function (res) {
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
            return res.json();
        })
        .then(function (json) {
            if (!json.ok) {
                tampilkanGagal(json.pesan || 'Sumber data tidak merespons.', json.detail);
                return;
            }

            payload = json;
            el('isi').hidden = false;
            el('meta').textContent = 'Diambil ' + json.meta.diambil + ' lewat ' + json.meta.koneksi + '.';

            if (typeof echarts === 'undefined') {
                tampilkanGagal('Pustaka grafik (ECharts) gagal dimuat dari CDN, '
                    + 'jadi grafiknya tidak bisa digambar. Angka di kartu ringkasan tetap benar.');
            }

            bangunFilter();
            render();
        })
        .catch(function (err) {
            tampilkanGagal('Permintaan ke server gagal.', err && err.message);
        });

    // Grafik ECharts tidak ikut berubah saat tema berganti, jadi dibuang dan
    // digambar ulang dengan palet yang baru.
    var ulang = function () {
        Object.keys(charts).forEach(function (k) { charts[k].dispose(); delete charts[k]; });
        render();
    };
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', ulang);
    new MutationObserver(ulang).observe(document.documentElement, {
        attributes: true, attributeFilter: ['data-theme']
    });
})();
</script>
@endsection
