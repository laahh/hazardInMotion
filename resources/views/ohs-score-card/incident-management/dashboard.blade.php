@extends('ohs-score-card.layouts.app')

@section('title', 'Incident Management & IPLS')

@section('css')
<style>
  /*
    Halaman ini memakai komponen WowDash apa adanya (card, nav-pills, progress,
    bordered-table, badge bg-*-focus). Yang ditulis di sini hanya yang memang
    tidak disediakan tema: tinggi kanvas grafik, pembungkus gulir untuk Sankey
    yang lebar, dan chip warna layer.

    Semuanya discope ke .imd-page karena style.css dipakai bareng modul lain.
  */

  .imd-page .imd-chart { width: 100%; height: 320px; }

  /* Sankey butuh lebar minimum supaya labelnya tidak saling tindih; di layar
     sempit dibiarkan menggulir mendatar, bukan diperas sampai tidak terbaca. */
  .imd-page .imd-scroll { overflow-x: auto; margin-inline: -4px; padding-inline: 4px; }
  .imd-page .imd-scroll .imd-chart { min-width: 760px; }
  .imd-page .imd-sankey-a { height: 440px; }
  .imd-page .imd-sankey-b { height: 620px; }
  .imd-page .imd-sankey-c { height: 380px; min-width: 520px; }
  .imd-page .imd-heat { height: 380px; min-width: 480px; }

  /* Penanda layer di tabel peringkat. Warnanya bermakna (satu warna per layer)
     sehingga tidak bisa diwakili kelas badge tema yang jumlahnya tetap. */
  .imd-page .imd-chip {
    display: inline-block; min-width: 26px; text-align: center;
    font-size: 11px; font-weight: 600; color: #fff;
    padding: 1px 6px; border-radius: 4px; margin-inline-end: 6px;
  }

  .imd-page .imd-bar { height: 8px; border-radius: 0 4px 4px 0; min-width: 2px; }

  /* Nama aktivitas panjang-panjang; dipotong supaya baris tabel tetap satu
     baris, teks penuhnya tetap terbaca lewat title saat hover. */
  .imd-page .imd-akt {
    display: inline-block; max-width: 320px; vertical-align: bottom;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
</style>
@endsection

@section('content')
<div class="imd-page" data-url="{{ route('ohs-score-card.incident-management.dashboard.data') }}">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
    <div>
      <h6 class="fw-semibold mb-0">Incident Management &amp; IPLS</h6>
      <div class="text-secondary-light text-sm mt-4" data-imi="scope">
        Memuat insiden yang diinvestigasi beserta analisis 5 layer IPLS dari OBDS…
      </div>
    </div>
    <ul class="d-flex align-items-center gap-2">
      <li class="fw-medium">
        <a href="{{ route('ohs-score-card.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
          <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
          Dashboard
        </a>
      </li>
      <li class="text-secondary-light">/</li>
      <li class="fw-medium text-primary-600">Incident Management</li>
    </ul>
  </div>

  {{-- Tiga tab. Pemindahannya ditangani JS sendiri, bukan data-bs-toggle,
       karena tab kedua dan ketiga memuat datanya sendiri saat pertama dibuka
       dan grafik ECharts perlu diukur ulang setelah panelnya terlihat. --}}
  <ul class="nav nav-pills pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex flex-wrap mb-24"
      role="tablist" data-imi="tabbar">
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link px-24 py-10 text-md text-center radius-8 active"
              data-tab="ringkasan">Ringkasan</button>
    </li>
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link px-24 py-10 text-md text-center radius-8"
              data-tab="deep">Deep Dive Insiden</button>
    </li>
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link px-24 py-10 text-md text-center radius-8"
              data-tab="leading">Leading Indicator</button>
    </li>
  </ul>

  <div data-pane="ringkasan">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-6 col-md-6">
          <span class="form-label text-sm fw-medium mb-8 d-block" id="imd-tahun-lbl">Tahun</span>
          <ul class="nav nav-pills pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex flex-wrap"
              role="tablist" aria-labelledby="imd-tahun-lbl" data-imi="seg-tahun"></ul>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-8">
          <label class="form-label text-sm fw-medium mb-8" for="imd-site">Site</label>
          <select class="form-select form-select-sm radius-8" id="imd-site" data-imi="site">
            <option value="all">Semua site</option>
          </select>
        </div>
        <div class="col-xxl-2 col-md-2 col-sm-4">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-imi="reset">Reset</button>
        </div>
        <div class="col-12">
          <span class="text-sm text-secondary-light" data-imi="status">Memuat ringkasan…</span>
        </div>
      </div>
    </div>
  </div>

  {{-- Pesan gagal; disembunyikan selama data berhasil dimuat --}}
  <div class="row gy-4 mb-24 d-none" data-imi="gagal-wrap">
    <div class="col-12">
      <div class="alert alert-danger bg-danger-focus border-danger-main text-danger-main
                  radius-8 px-20 py-16 mb-0 d-flex align-items-start gap-3">
        <iconify-icon icon="solar:danger-triangle-outline" class="icon text-xxl flex-shrink-0"></iconify-icon>
        <div>
          <h6 class="text-md fw-semibold mb-4 text-danger-main">Data tidak bisa dimuat</h6>
          <p class="text-sm mb-4" data-imi="gagal-pesan"></p>
          <code class="text-xs text-secondary-light" data-imi="gagal-detail"></code>
        </div>
      </div>
    </div>
  </div>

  <div data-imi="isi" class="d-none">

    {{-- Kartu ringkasan utama --}}
    <div class="row gy-4 mb-24" data-imi="kpi"></div>

    <div class="row gy-4 mb-24">
      <div class="col-xxl-8">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Insiden per Bulan</h6>
            <span class="text-sm text-secondary-light">
              Hanya insiden yang diinvestigasi; yang tidak diinvestigasi tidak ikut dihitung di seluruh halaman ini
            </span>
          </div>
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-imi="lg-tren"></div>
            <div class="imd-chart" data-imi="ch-tren"></div>
          </div>
        </div>
      </div>

      <div class="col-xxl-4">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Insiden per Site</h6>
            <span class="text-sm text-secondary-light">Bagian berwarna adalah yang sudah punya analisis IPLS</span>
          </div>
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-imi="lg-site"></div>
            <div class="imd-chart" data-imi="ch-site"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row gy-4 mb-24">
      <div class="col-12">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Alur Insiden</h6>
            <span class="text-sm text-secondary-light">
              Jenis insiden → kategori kecelakaan → status investigasi; lebar alur = jumlah insiden
            </span>
          </div>
          <div class="card-body p-24">
            <div class="imd-scroll"><div class="imd-chart imd-sankey-a" data-imi="sk-a"></div></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row gy-4 mb-24">
      <div class="col-12">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
            <div>
              <h6 class="text-lg fw-semibold mb-0">Korelasi IPLS</h6>
              <span class="text-sm text-secondary-light">
                Kategori kecelakaan → layer IPLS → aktivitas penyebab. Satu insiden biasanya punya
                beberapa temuan lintas layer; aktivitas di luar 14 teratas digabung jadi "Lainnya" per layer
              </span>
            </div>
            {{-- style-three SENGAJA TIDAK DIPAKAI di sini. Kelas itu menyetel
                 `display:grid; grid-template-columns:1fr 1fr 1fr`, jadi sakelar
                 berisi empat tombol akan pecah jadi 3 + 1 baris dan kotaknya
                 melar mengikuti kolom, bukan mengikuti isi. flex-shrink-0
                 menjaga agar header yang padat tidak memerasnya. --}}
            <div class="flex-shrink-0">
              <span class="form-label text-sm fw-medium mb-8 d-block" id="imd-status-lbl">Status layer</span>
              <ul class="nav nav-pills pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex flex-nowrap"
                  role="tablist" aria-labelledby="imd-status-lbl" data-imi="seg-status"></ul>
            </div>
          </div>
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-imi="lg-layer"></div>
            <div class="imd-scroll"><div class="imd-chart imd-sankey-b" data-imi="sk-b"></div></div>
            <div class="row gy-3 mt-8" data-imi="read-b"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row gy-4 mb-24">
      <div class="col-xxl-6">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Efektivitas Perbaikan</h6>
            <span class="text-sm text-secondary-light">
              Layer root cause → layer CAR, dihitung per insiden
            </span>
          </div>
          <div class="card-body p-24">
            <div class="imd-scroll"><div class="imd-chart imd-sankey-c" data-imi="sk-c"></div></div>
            <div class="row gy-3 mt-8" data-imi="read-c"></div>
          </div>
        </div>
      </div>

      <div class="col-xxl-6">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Site × Layer IPLS</h6>
            <span class="text-sm text-secondary-light">
              Jumlah temuan per site dan layer, mengikuti pilihan status layer di Korelasi IPLS
            </span>
          </div>
          <div class="card-body p-24">
            <div class="imd-scroll"><div class="imd-chart imd-heat" data-imi="ch-heat"></div></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row gy-4 mb-24">
      <div class="col-xxl-6">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Aktivitas Penyebab Teratas</h6>
            <span class="text-sm text-secondary-light">Mengikuti pilihan status layer di Korelasi IPLS</span>
          </div>
          <div class="card-body p-24">
            <div class="table-responsive">
              <table class="table bordered-table sm-table mb-0">
                <thead>
                  <tr>
                    <th scope="col">Aktivitas</th>
                    <th scope="col" class="text-end">Temuan</th>
                    <th scope="col" style="width:32%">Porsi</th>
                  </tr>
                </thead>
                <tbody data-imi="tbl-akt"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xxl-6">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Status CAR per Layer Perbaikan</h6>
            <span class="text-sm text-secondary-light">
              Overdue = masih Open/Pending dan sudah lewat target
            </span>
          </div>
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-imi="lg-car"></div>
            <div class="imd-chart" data-imi="ch-car" style="height:300px"></div>
          </div>
        </div>
      </div>
    </div>

  </div>

  </div>{{-- /pane ringkasan --}}

  {{-- ================= Deep Dive Insiden ================= --}}
  <div data-pane="deep" class="d-none">

    <div class="card radius-8 border mb-24">
      <div class="card-body p-24">
        <div class="row gy-3 gx-3 align-items-end">
          <div class="col-xxl-2 col-md-3 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8" for="dd-site">Site</label>
            <select class="form-select form-select-sm radius-8" id="dd-site" data-imi="dd-site">
              <option value="all">Semua site</option>
            </select>
          </div>
          <div class="col-xxl-6 col-md-5">
            <label class="form-label text-sm fw-medium mb-8" for="dd-insiden">
              Insiden yang diinvestigasi<span data-imi="dd-rentang"></span>
            </label>
            <select class="form-select form-select-sm radius-8" id="dd-insiden" data-imi="dd-insiden">
              <option value="">Memuat daftar insiden…</option>
            </select>
          </div>
          <div class="col-xxl-3 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8" for="dd-id">Atau ID investigasi</label>
            <div class="d-flex gap-2">
              <input type="text" inputmode="numeric" class="form-control form-control-sm radius-8"
                     id="dd-id" placeholder="mis. 2254" data-imi="dd-id">
              <button type="button" class="btn btn-sm btn-primary-600 radius-8 flex-shrink-0"
                      data-imi="dd-buka">Buka</button>
            </div>
          </div>
          <div class="col-12">
            <span class="text-sm text-secondary-light">
              Ditarik langsung dari OBDS. Sinyal SAP baru tersedia sejak 1 Januari 2026, jadi jendela
              90 hari baru penuh untuk insiden sejak April 2026.
            </span>
          </div>
        </div>
      </div>
    </div>

    <div data-imi="dd-body"></div>
  </div>

  {{-- ================= Leading Indicator ================= --}}
  <div data-pane="leading" class="d-none">

    <div class="card radius-8 border mb-24">
      <div class="card-body p-24">
        <div class="row gy-3 gx-3 align-items-end">
          <div class="col-xxl-3 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8" for="ld-site">Site</label>
            <select class="form-select form-select-sm radius-8" id="ld-site" data-imi="ld-site">
              <option value="all">Semua site</option>
            </select>
          </div>
          <div class="col-xxl-4 col-md-5 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8" for="ld-indikator">Indikator</label>
            <select class="form-select form-select-sm radius-8" id="ld-indikator" data-imi="ld-indikator"></select>
          </div>
          <div class="col-12">
            <span class="text-sm text-secondary-light">
              Mingguan, 2026. Indikator leading dibandingkan dengan jumlah insiden di site yang sama;
              minggu terakhir yang datanya belum lengkap tidak dihitung.
            </span>
          </div>
        </div>
      </div>
    </div>

    <div data-imi="ld-body"></div>
  </div>

</div>
@endsection

@section('page-scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.5.0/echarts.min.js"></script>
<script>
// ---- Dashboard Incident Management & IPLS -----------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.imd-page');
    if (!root) { return; }

    // Palet WowDash yang sudah dipakai dashboard lain di aplikasi ini, bukan
    // palet baru; warna grid dan sumbu pun mengikuti halaman OHS lainnya.
    var PALETTE = ['#487FFF', '#45B369', '#FF9F29', '#EF4A00', '#8252E9', '#00B8F2', '#E0484A'];
    var WARNA_LAYER = ['#94A3B8', '#487FFF', '#FF9F29', '#45B369', '#8252E9', '#00B8F2'];
    var GRID = '#EEF2F7';
    var INK = '#1F2937';
    var INK2 = '#475569';
    var INK3 = '#94A3B8';
    var NETRAL = '#94A3B8';

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
        var simpan = JSON.parse(window.localStorage.getItem('imd-filter') || '{}');
        if (simpan && typeof simpan === 'object') { Object.assign(state, simpan); }
    } catch (err) { /* localStorage bisa diblokir; filter default saja */ }

    function ingat() {
        try { window.localStorage.setItem('imd-filter', JSON.stringify(state)); }
        catch (err) { /* menyimpan posisi filter tidak pantas menggagalkan halaman */ }
    }

    // ---- Warna per label ---------------------------------------------------
    // Dipetakan dari LABEL, bukan indeks: urutan dimensi datang dari server dan
    // bisa bergeser kalau ada nilai baru di sumber.
    function warnaStatus(label) {
        return ({
            'Investigasi': PALETTE[0],
            'Tidak investigasi': NETRAL,
            'Insiden baru': PALETTE[2]
        })[label] || PALETTE[4];
    }

    function warnaKategori(label) {
        return ({
            'Pelanggaran Golden Rules': PALETTE[2],
            'Near Miss': PALETTE[0],
            'Property Damage': PALETTE[1],
            'Injury': PALETTE[6],
            'Fire Case': PALETTE[3],
            'Hazard HIPO / PSPP': PALETTE[4],
            'Belum dikategorikan': NETRAL
        })[label] || PALETTE[5];
    }

    function warnaCar(label) {
        return ({
            'Closed': PALETTE[1],
            'Closed overdue': PALETTE[3],
            'Open': PALETTE[2],
            'Pending approval': PALETTE[0],
            'Reject': PALETTE[6],
            'Lainnya': NETRAL
        })[label] || NETRAL;
    }

    // ---- ECharts -----------------------------------------------------------
    var charts = {};

    function ch(nama) {
        if (typeof echarts === 'undefined') { return null; }
        if (!charts[nama]) { charts[nama] = echarts.init(el(nama), null, { renderer: 'svg' }); }
        return charts[nama];
    }

    window.addEventListener('resize', function () {
        Object.keys(charts).forEach(function (k) { charts[k].resize(); });
    });

    function tip(extra) {
        return Object.assign({
            backgroundColor: '#fff', borderColor: GRID,
            textStyle: { color: INK, fontSize: 13 },
            extraCssText: 'box-shadow:0 4px 14px rgba(15,23,42,.12);border-radius:8px'
        }, extra || {});
    }

    function sumbuBayangan() {
        return { type: 'shadow', shadowStyle: { color: 'rgba(100,116,139,.08)' } };
    }

    function legenda(nama, items) {
        el(nama).innerHTML = items.map(function (it) {
            return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                + '<span class="rounded-1" style="width:14px;height:14px;background:' + it[1] + ';"></span>'
                + esc(it[0]) + '</span>';
        }).join('');
    }

    // ---- Penyaringan -------------------------------------------------------
    // Indeks kolom tiap kumpulan; dinamai supaya pembacaan baris tidak berupa
    // deretan angka tanpa arti.
    var D1 = { ym: 0, site: 1, jenis: 2, kat: 3, status: 4, n: 5, ipls: 6 };
    var D2 = { tahun: 0, site: 1, kat: 2, akt: 3, status: 4, n: 5 };
    var CAR = { tahun: 0, site: 1, layer: 2, status: 3, overdue: 4, n: 5 };
    var RC = { tahun: 0, site: 1, rc: 2, car: 3, n: 4 };

    function cocokTahun(v) { return state.tahun === 'all' || String(v) === String(state.tahun); }
    function cocokSite(i) { return state.site === 'all' || String(i) === String(state.site); }

    function saringD1() {
        return payload.d1.filter(function (r) {
            return cocokTahun(r[D1.ym].slice(0, 4)) && cocokSite(r[D1.site]);
        });
    }

    /**
     * pakaiStatus=false dipakai kartu ringkasan: cacah temuan di sana harus
     * mengikuti filter tahun & site saja, tidak ikut sakelar status layer yang
     * memang milik panel Korelasi IPLS.
     */
    function saringD2(pakaiStatus) {
        return payload.d2.filter(function (r) {
            return cocokTahun(r[D2.tahun]) && cocokSite(r[D2.site])
                && (!pakaiStatus || state.status === 'all'
                    || String(r[D2.status]) === String(state.status));
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

    // ---- Kartu ringkasan utama ---------------------------------------------
    function renderKpi(d1, d2Semua, carRows) {
        var dim = payload.dim;
        var iBaru = dim.status.indexOf('Insiden baru');

        var total = jumlah(d1, D1.n);
        // Insiden yang baru masuk dan belum ditriase; sisanya sudah diinvestigasi.
        var baru = iBaru === -1
            ? 0
            : jumlah(d1.filter(function (r) { return r[D1.status] === iBaru; }), D1.n);
        var ipls = jumlah(d1, D1.ipls);
        var temuan = jumlah(d2Semua, D2.n);

        var iClosed = dim.status_car.indexOf('Closed');
        var iClosedOd = dim.status_car.indexOf('Closed overdue');
        var iOpen = dim.status_car.indexOf('Open');
        var iPending = dim.status_car.indexOf('Pending approval');

        var carTot = jumlah(carRows, CAR.n);
        var carClosed = jumlah(carRows.filter(function (r) {
            return r[CAR.status] === iClosed || r[CAR.status] === iClosedOd;
        }), CAR.n);
        var carJalan = jumlah(carRows.filter(function (r) {
            return r[CAR.status] === iOpen || r[CAR.status] === iPending;
        }), CAR.n);
        var od = jumlah(carRows.filter(function (r) { return r[CAR.overdue] === 1; }), CAR.n);

        var kartu = [
            {
                grad: 'bg-gradient-end-1', icon: 'solar:danger-triangle-outline', dot: 'bg-primary-600',
                label: 'Insiden Diinvestigasi', value: fmt(total),
                foot: baru
                    ? fmt(baru) + ' di antaranya masih berstatus insiden baru'
                    : 'Insiden yang tidak diinvestigasi tidak ikut dihitung'
            },
            {
                grad: 'bg-gradient-end-2', icon: 'solar:clipboard-check-outline', dot: 'bg-success-main',
                label: 'Punya Analisis IPLS', value: fmt(ipls),
                foot: pct(ipls, total) + '% dari insiden di samping',
                bar: pct(ipls, total), barKelas: 'bg-success-main'
            },
            {
                grad: 'bg-gradient-end-6', icon: 'solar:layers-minimalistic-outline', dot: 'bg-info-main',
                label: 'Temuan Layer IPLS', value: fmt(temuan),
                foot: ipls
                    ? 'Rata-rata ' + (temuan / ipls).toLocaleString('id-ID', { maximumFractionDigits: 1 })
                        + ' temuan per insiden yang dianalisis'
                    : 'Belum ada temuan'
            },
            {
                grad: 'bg-gradient-end-3', icon: 'solar:wrench-outline', dot: 'bg-yellow',
                label: 'Tindakan Perbaikan', value: fmt(carTot),
                foot: pct(carClosed, carTot) + '% sudah closed · ' + fmt(carJalan) + ' masih berjalan',
                bar: pct(carClosed, carTot), barKelas: 'bg-warning-main'
            },
            {
                grad: 'bg-gradient-end-5', icon: 'solar:alarm-outline', dot: 'bg-danger-main',
                label: 'CAR Lewat Target', value: fmt(od),
                foot: od
                    ? '<span class="bg-danger-focus text-danger-main px-1 rounded-2 fw-medium text-sm">'
                        + 'Perlu ditindaklanjuti</span> Open/Pending melewati target'
                    : 'Tidak ada yang lewat target'
            }
        ];

        el('kpi').innerHTML = kartu.map(function (c) {
            return '<div class="col-xxl col-md-4 col-sm-6">'
                + '<div class="card p-3 shadow-2 radius-8 border input-form-light h-100 ' + c.grad + '">'
                +   '<div class="card-body p-0">'
                +     '<div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">'
                +       '<div class="d-flex align-items-center gap-2">'
                +         '<span class="mb-0 w-48-px h-48-px ' + c.dot + ' text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">'
                +           '<iconify-icon icon="' + c.icon + '" class="icon"></iconify-icon>'
                +         '</span>'
                +         '<div>'
                +           '<span class="mb-2 fw-medium text-secondary-light text-sm">' + esc(c.label) + '</span>'
                +           '<h6 class="fw-semibold">' + c.value + '</h6>'
                +         '</div>'
                +       '</div>'
                +     '</div>'
                +     (c.bar !== undefined
                        ? '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px mb-8">'
                            + '<div class="progress-bar ' + c.barKelas + ' rounded-pill" role="progressbar"'
                            + ' style="width:' + Math.min(100, c.bar) + '%" aria-valuenow="' + c.bar + '"'
                            + ' aria-valuemin="0" aria-valuemax="100"></div></div>'
                        : '')
                +     '<p class="text-sm mb-0">' + c.foot + '</p>'
                +   '</div>'
                + '</div></div>';
        }).join('');
    }

    // ---- Tren --------------------------------------------------------------
    function renderTren(d1) {
        var dim = payload.dim;
        var ada = {};
        d1.forEach(function (r) { ada[r[D1.ym]] = true; });
        var bulan = Object.keys(ada).sort();

        var urut = ['Investigasi', 'Tidak investigasi', 'Insiden baru'].filter(function (s) {
            return dim.status.indexOf(s) !== -1;
        });

        legenda('lg-tren', urut.map(function (s) { return [s, warnaStatus(s)]; }));

        var g = ch('ch-tren');
        if (!g) { return; }

        g.setOption({
            animation: false,
            grid: { left: 40, right: 8, top: 10, bottom: 28 },
            tooltip: tip({ trigger: 'axis', axisPointer: sumbuBayangan() }),
            xAxis: {
                type: 'category',
                data: bulan.map(function (ym) {
                    return BULAN[+ym.slice(4) - 1] + (state.tahun === 'all' ? ' ' + ym.slice(2, 4) : '');
                }),
                axisLine: { lineStyle: { color: GRID } }, axisTick: { show: false },
                axisLabel: { color: INK3, fontSize: 11, hideOverlap: true }
            },
            yAxis: {
                type: 'value', splitLine: { lineStyle: { color: GRID, type: 'dashed' } },
                axisLabel: { color: INK3, fontSize: 11 }
            },
            series: urut.map(function (s, i) {
                var idx = dim.status.indexOf(s);
                return {
                    name: s, type: 'bar', stack: 'a', barMaxWidth: 26,
                    itemStyle: {
                        color: warnaStatus(s), borderColor: '#fff', borderWidth: 1,
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

    // ---- Insiden per site --------------------------------------------------
    function renderSite(d1) {
        var per = {};
        d1.forEach(function (r) {
            if (!per[r[D1.site]]) { per[r[D1.site]] = { n: 0, r: 0 }; }
            per[r[D1.site]].n += r[D1.n];
            per[r[D1.site]].r += r[D1.ipls];
        });

        var baris = Object.keys(per).map(function (k) {
            return { nama: payload.dim.site[k], n: per[k].n, r: per[k].r };
        }).sort(function (a, b) { return a.n - b.n; });

        legenda('lg-site', [['Dengan analisis IPLS', PALETTE[0]], ['Belum ada analisis', NETRAL]]);

        var g = ch('ch-site');
        if (!g) { return; }

        g.setOption({
            animation: false,
            grid: { left: 88, right: 40, top: 6, bottom: 20 },
            tooltip: tip({ trigger: 'axis', axisPointer: sumbuBayangan() }),
            xAxis: {
                type: 'value', splitLine: { lineStyle: { color: GRID, type: 'dashed' } },
                axisLabel: { color: INK3, fontSize: 11 }
            },
            yAxis: {
                type: 'category', data: baris.map(function (x) { return x.nama; }),
                axisLine: { show: false }, axisTick: { show: false },
                axisLabel: { color: INK2, fontSize: 12 }
            },
            series: [
                {
                    name: 'Dengan analisis IPLS', type: 'bar', stack: 'a', barMaxWidth: 18,
                    itemStyle: { color: PALETTE[0], borderColor: '#fff', borderWidth: 1 },
                    data: baris.map(function (x) { return x.r; })
                },
                {
                    name: 'Belum ada analisis', type: 'bar', stack: 'a', barMaxWidth: 18,
                    itemStyle: { color: NETRAL, borderColor: '#fff', borderWidth: 1, borderRadius: [0, 4, 4, 0] },
                    data: baris.map(function (x) { return x.n - x.r; }),
                    label: {
                        show: true, position: 'right', color: INK2, fontSize: 11,
                        formatter: function (p) { return fmt(baris[p.dataIndex].n); }
                    }
                }
            ]
        }, true);
    }

    // ---- Sankey ------------------------------------------------------------
    function tambah(obj, a, b, n) {
        var k = a + '\u0001' + b;
        obj[k] = (obj[k] || 0) + n;
    }

    /** Sankey generik. Nama simpul berprefiks "x:" supaya dua kolom boleh berlabel sama. */
    function sankey(nama, tautan, warnaSimpul, opt) {
        var g = ch(nama);
        if (!g) { return; }

        var links = Object.keys(tautan).filter(function (k) { return tautan[k] > 0; }).map(function (k) {
            var p = k.split('\u0001');
            return { source: p[0], target: p[1], value: tautan[k] };
        });

        if (!links.length) { g.clear(); return; }

        var simpul = {};
        links.forEach(function (l) { simpul[l.source] = true; simpul[l.target] = true; });

        var keluar = {}, masuk = {};
        links.forEach(function (l) {
            keluar[l.source] = (keluar[l.source] || 0) + l.value;
            masuk[l.target] = (masuk[l.target] || 0) + l.value;
        });

        var nilai = function (n) { return Math.max(keluar[n] || 0, masuk[n] || 0); };
        var polos = function (n) { return n.slice(2); };

        g.setOption({
            animation: false,
            tooltip: tip({
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
                data: Object.keys(simpul).map(function (n) {
                    return { name: n, itemStyle: { color: warnaSimpul(n), borderColor: '#fff', borderWidth: 1 } };
                }),
                links: links,
                lineStyle: { color: 'source', opacity: 0.32, curveness: 0.5 },
                label: {
                    color: INK, fontSize: 12,
                    formatter: function (p) { return polos(p.name) + '  {n|' + fmt(nilai(p.name)) + '}'; },
                    rich: { n: { fontSize: 11, color: INK3 } }
                }
            }]
        }, true);
    }

    function renderSankeyA(d1) {
        var dim = payload.dim;
        var tautan = {};

        d1.forEach(function (r) {
            tambah(tautan, 'j:' + dim.jenis[r[D1.jenis]], 'k:' + dim.kategori[r[D1.kat]], r[D1.n]);
            tambah(tautan, 'k:' + dim.kategori[r[D1.kat]], 't:' + dim.status[r[D1.status]], r[D1.n]);
        });

        sankey('sk-a', tautan, function (n) {
            var p = n.slice(0, 1), label = n.slice(2);
            if (p === 'k') { return warnaKategori(label); }
            if (p === 't') { return warnaStatus(label); }
            return INK3;
        }, { tingkat: 3, unit: 'insiden' });
    }

    /** Kartu kecil di bawah Sankey, memakai kotak WowDash yang sama dengan panel lain. */
    function kartuBaca(items) {
        return items.map(function (it) {
            return '<div class="col-xxl-4 col-md-6">'
                + '<div class="border input-form-light radius-8 p-16 h-100">'
                +   '<h6 class="mb-8 fw-semibold">' + it[0] + '</h6>'
                +   '<span class="text-sm text-secondary-light">' + it[1] + '</span>'
                + '</div></div>';
        }).join('');
    }

    function renderSankeyB(d2) {
        var dim = payload.dim;
        var host = el('read-b');
        var total = jumlah(d2, D2.n);

        if (!total) {
            var kosong = ch('sk-b');
            if (kosong) { kosong.clear(); }
            host.innerHTML = '<div class="col-12 text-center text-secondary-light py-24">'
                + 'Belum ada temuan layer untuk filter ini.</div>';
            return;
        }

        // 14 aktivitas teratas tampil sendiri; sisanya dikumpulkan jadi satu
        // simpul "Lainnya" PER LAYER, bukan satu simpul global, supaya alurnya
        // tetap pulang ke layer yang benar.
        var perAkt = {};
        d2.forEach(function (r) { perAkt[r[D2.akt]] = (perAkt[r[D2.akt]] || 0) + r[D2.n]; });

        var urutAkt = Object.keys(perAkt).sort(function (a, b) { return perAkt[b] - perAkt[a]; });
        var top = urutAkt.slice(0, 14).reduce(function (s, k) { s[k] = true; return s; }, {});

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

        legenda('lg-layer', [1, 2, 3, 4, 5].map(function (i) { return [dim.layer[i], WARNA_LAYER[i]]; }));

        sankey('sk-b', tautan, function (n) {
            var p = n.slice(0, 1), label = n.slice(2);
            if (p === 'k') { return warnaKategori(label); }
            if (p === 'l') {
                for (var i = 1; i <= 5; i++) { if (dim.layer[i] === label) { return WARNA_LAYER[i]; } }
                return NETRAL;
            }
            var m = label.match(/· L(\d)$/);
            return WARNA_LAYER[m ? +m[1] : (layerDariNama[label] || 0)];
        }, { tingkat: 3, unit: 'temuan' });

        var perLayer = [0, 0, 0, 0, 0, 0];
        d2.forEach(function (r) { perLayer[dim.aktivitas[r[D2.akt]].layer] += r[D2.n]; });

        var puncak = perLayer.indexOf(Math.max.apply(null, perLayer));
        var atas = urutAkt[0];
        var sistem = pct(perLayer[1] + perLayer[2], total);

        host.innerHTML = kartuBaca([
            [pct(perLayer[puncak], total) + '% di ' + esc(dim.layer_pendek[puncak]),
                'Layer dengan temuan terbanyak, ' + fmt(perLayer[puncak]) + ' dari ' + fmt(total) + ' temuan.'],
            [fmt(perAkt[atas]) + '× ' + esc(dim.aktivitas[atas].nama),
                'Aktivitas penyebab paling sering, di ' + esc(dim.layer_pendek[dim.aktivitas[atas].layer]) + '.'],
            [sistem + '% di Layer 1–2',
                'Porsi temuan pada sistem, kebijakan dan perencanaan. Makin kecil porsinya, makin sering '
                + 'investigasi berhenti di level pelaksana.']
        ]);
    }

    function renderSankeyC(rcRows) {
        var dim = payload.dim;
        var host = el('read-c');
        var total = jumlah(rcRows, RC.n);

        if (!total) {
            var kosong = ch('sk-c');
            if (kosong) { kosong.clear(); }
            host.innerHTML = '<div class="col-12 text-center text-secondary-light py-24">'
                + 'Belum ada pasangan root cause &amp; CAR untuk filter ini.</div>';
            return;
        }

        var tautan = {};
        rcRows.forEach(function (r) {
            tambah(tautan, 'r:' + dim.layer_pendek[r[RC.rc]], 'c:' + dim.layer_pendek[r[RC.car]], r[RC.n]);
        });

        sankey('sk-c', tautan, function (n) {
            var label = n.slice(2);
            for (var i = 0; i <= 5; i++) { if (dim.layer_pendek[i] === label) { return WARNA_LAYER[i]; } }
            return NETRAL;
        }, { tingkat: 2, unit: 'insiden' });

        var sejajar = jumlah(rcRows.filter(function (r) { return r[RC.rc] === r[RC.car]; }), RC.n);
        var ke12 = jumlah(rcRows.filter(function (r) { return r[RC.car] === 1 || r[RC.car] === 2; }), RC.n);

        host.innerHTML = [
            ['col-sm-6', pct(sejajar, total) + '% alur sejajar',
                'CAR berada di layer yang sama dengan root cause-nya.'],
            ['col-sm-6', pct(ke12, total) + '% ke Layer 1–2',
                'Alur perbaikan yang menyasar sistem dan perencanaan.']
        ].map(function (it) {
            return '<div class="' + it[0] + '">'
                + '<div class="border input-form-light radius-8 p-16 h-100">'
                +   '<h6 class="mb-8 fw-semibold">' + it[1] + '</h6>'
                +   '<span class="text-sm text-secondary-light">' + it[2] + '</span>'
                + '</div></div>';
        }).join('');
    }

    // ---- Peta panas --------------------------------------------------------
    function renderHeat(d2) {
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

        var g = ch('ch-heat');
        if (!g) { return; }

        g.setOption({
            animation: false,
            grid: { left: 88, right: 10, top: 10, bottom: 54 },
            tooltip: tip({
                formatter: function (p) {
                    return esc(dim.site[siteIdx[p.value[1]]]) + ' · ' + esc(dim.layer[p.value[0] + 1])
                        + '<br><b>' + fmt(p.value[2]) + '</b> temuan';
                }
            }),
            xAxis: {
                type: 'category',
                data: [1, 2, 3, 4, 5].map(function (i) { return dim.layer_pendek[i]; }),
                axisLine: { show: false }, axisTick: { show: false },
                axisLabel: { color: INK2, fontSize: 11, interval: 0 }
            },
            yAxis: {
                type: 'category', data: siteIdx.map(function (i) { return dim.site[i]; }), inverse: true,
                axisLine: { show: false }, axisTick: { show: false },
                axisLabel: { color: INK2, fontSize: 12 }
            },
            visualMap: {
                min: 0, max: Math.max(maks, 1), show: true, orient: 'horizontal',
                left: 'center', bottom: 0, itemWidth: 10, itemHeight: 140, calculable: false,
                text: ['banyak', 'sedikit'], textStyle: { color: INK3, fontSize: 11 },
                inRange: { color: ['#EEF4FF', '#A8C5FF', '#487FFF', '#1A48B5'] }
            },
            series: [{
                type: 'heatmap',
                data: data.map(function (d) {
                    return { value: d, label: { color: d[2] > maks * 0.45 ? '#fff' : INK } };
                }),
                itemStyle: { borderColor: '#fff', borderWidth: 2, borderRadius: 3 },
                label: {
                    show: true, fontSize: 11,
                    formatter: function (p) { return p.value[2] ? p.value[2] : ''; }
                },
                emphasis: { itemStyle: { borderColor: INK, borderWidth: 1 } }
            }]
        }, true);
    }

    // ---- Tabel aktivitas ---------------------------------------------------
    function renderTabel(d2) {
        var dim = payload.dim;
        var per = {};
        d2.forEach(function (r) { per[r[D2.akt]] = (per[r[D2.akt]] || 0) + r[D2.n]; });

        var baris = Object.keys(per).sort(function (a, b) { return per[b] - per[a]; }).slice(0, 10);
        var maks = baris.length ? per[baris[0]] : 1;

        el('tbl-akt').innerHTML = baris.length
            ? baris.map(function (k) {
                var a = dim.aktivitas[k];
                return '<tr>'
                    + '<td><span class="imd-chip" style="background:' + WARNA_LAYER[a.layer] + '">L'
                    +   a.layer + '</span>'
                    +   '<span class="imd-akt text-sm" title="' + esc(a.nama) + '">' + esc(a.nama) + '</span></td>'
                    + '<td class="text-end fw-semibold">' + fmt(per[k]) + '</td>'
                    + '<td><div class="imd-bar" style="width:' + (per[k] / maks * 100) + '%;background:'
                    +   WARNA_LAYER[a.layer] + '"></div></td>'
                    + '</tr>';
            }).join('')
            : '<tr><td colspan="3" class="text-center text-secondary-light py-24">'
                + 'Belum ada temuan untuk filter ini.</td></tr>';
    }

    // ---- Status CAR --------------------------------------------------------
    function renderCar(carRows) {
        var dim = payload.dim;
        var urut = ['Closed', 'Closed overdue', 'Pending approval', 'Open', 'Reject', 'Lainnya']
            .filter(function (s) {
                var i = dim.status_car.indexOf(s);
                return i !== -1 && jumlah(carRows.filter(function (r) {
                    return r[CAR.status] === i;
                }), CAR.n) > 0;
            });

        legenda('lg-car', urut.map(function (s) { return [s, warnaCar(s)]; }));

        var layers = [1, 2, 3, 4, 5, 0];
        var g = ch('ch-car');
        if (!g) { return; }

        g.setOption({
            animation: false,
            grid: { left: 112, right: 46, top: 6, bottom: 20 },
            tooltip: tip({ trigger: 'axis', axisPointer: sumbuBayangan() }),
            xAxis: {
                type: 'value', splitLine: { lineStyle: { color: GRID, type: 'dashed' } },
                axisLabel: { color: INK3, fontSize: 11 }
            },
            yAxis: {
                type: 'category', inverse: true,
                data: layers.map(function (l) { return dim.layer_pendek[l]; }),
                axisLine: { show: false }, axisTick: { show: false },
                axisLabel: { color: INK2, fontSize: 12 }
            },
            series: urut.map(function (s, ix) {
                var i = dim.status_car.indexOf(s);
                return {
                    name: s, type: 'bar', stack: 'c', barMaxWidth: 18,
                    itemStyle: {
                        color: warnaCar(s), borderColor: '#fff', borderWidth: 1,
                        borderRadius: ix === urut.length - 1 ? [0, 4, 4, 0] : 0
                    },
                    data: layers.map(function (l) {
                        return jumlah(carRows.filter(function (r) {
                            return r[CAR.layer] === l && r[CAR.status] === i;
                        }), CAR.n);
                    }),
                    label: ix === urut.length - 1 ? {
                        show: true, position: 'right', color: INK2, fontSize: 11,
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

    // ---- Perakitan ---------------------------------------------------------
    /** Membungkus renderer agar kegagalan satu panel tidak menjatuhkan sisanya. */
    function aman(nama, fn) {
        try { fn(); }
        catch (err) {
            if (typeof console !== 'undefined' && console.error) {
                console.error('Incident Management: panel "' + nama + '" gagal dirender', err);
            }
        }
    }

    function render() {
        if (!payload) { return; }

        var d1 = saringD1();
        var d2 = saringD2(true);
        var d2Semua = saringD2(false);
        var carRows = saringCar();
        var rcRows = saringRc();

        var namaSite = state.site === 'all' ? 'semua site' : payload.dim.site[+state.site];
        var namaTahun = state.tahun === 'all'
            ? payload.tahun[0] + '–' + payload.tahun[payload.tahun.length - 1]
            : state.tahun;

        el('scope').textContent = 'Insiden yang diinvestigasi ' + namaTahun + ', ' + namaSite
            + ', beserta analisis 5 layer IPLS dan tindakan perbaikan (CAR)';

        aman('kpi', function () { renderKpi(d1, d2Semua, carRows); });
        aman('tren', function () { renderTren(d1); });
        aman('site', function () { renderSite(d1); });
        aman('sankey-a', function () { renderSankeyA(d1); });
        aman('sankey-b', function () { renderSankeyB(d2); });
        aman('sankey-c', function () { renderSankeyC(rcRows); });
        aman('heatmap', function () { renderHeat(d2); });
        aman('tabel', function () { renderTabel(d2); });
        aman('car', function () { renderCar(carRows); });

        el('status').textContent = fmt(jumlah(d1, D1.n)) + ' insiden diinvestigasi · '
            + fmt(jumlah(d1, D1.ipls)) + ' punya analisis IPLS · '
            + fmt(jumlah(d2Semua, D2.n)) + ' temuan layer · '
            + fmt(jumlah(carRows, CAR.n)) + ' tindakan perbaikan'
            + (payload.meta ? ' · data per ' + payload.meta.diambil : '');
    }

    // ---- Filter ------------------------------------------------------------
    function pil(nilai, teks, aktif) {
        return '<li class="nav-item" role="presentation">'
            + '<button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8'
            + (aktif ? ' active' : '') + '" data-v="' + nilai + '">' + esc(teks) + '</button></li>';
    }

    function sinkron() {
        el('seg-tahun').querySelectorAll('button').forEach(function (b) {
            b.classList.toggle('active', String(b.dataset.v) === String(state.tahun));
        });
        el('seg-status').querySelectorAll('button').forEach(function (b) {
            b.classList.toggle('active', String(b.dataset.v) === String(state.status));
        });
    }

    function bangunFilter() {
        var dim = payload.dim;

        // Nilai tersimpan bisa menunjuk tahun atau site yang sudah tidak ada
        // lagi di sumber; kalau tidak diperiksa, halaman tampil kosong tanpa
        // sebab yang terlihat.
        if (state.tahun !== 'all' && payload.tahun.indexOf(+state.tahun) === -1) { state.tahun = 'all'; }
        if (state.site !== 'all' && !dim.site[+state.site]) { state.site = 'all'; }
        if (state.status !== 'all' && !dim.status_layer[+state.status]) { state.status = 'all'; }

        el('seg-tahun').innerHTML = pil('all', 'Semua', state.tahun === 'all')
            + payload.tahun.map(function (t) {
                return pil(t, t, String(t) === String(state.tahun));
            }).join('');

        el('seg-status').innerHTML = pil('all', 'Semua', state.status === 'all')
            + ['Root cause', 'Non-conformity', 'Improvement']
                .filter(function (s) { return dim.status_layer.indexOf(s) !== -1; })
                .map(function (s) {
                    var i = dim.status_layer.indexOf(s);
                    return pil(i, s, String(i) === String(state.status));
                }).join('');

        var sel = el('site');
        sel.innerHTML = '<option value="all">Semua site</option>'
            + dim.site.map(function (s, i) {
                return '<option value="' + i + '">' + esc(s) + '</option>';
            }).join('');
        sel.value = state.site;

        el('seg-tahun').addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) { return; }
            state.tahun = b.dataset.v;
            ingat(); sinkron(); render();
        });

        el('seg-status').addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) { return; }
            state.status = b.dataset.v;
            ingat(); sinkron(); render();
        });

        sel.addEventListener('change', function () {
            state.site = sel.value;
            ingat(); render();
        });

        el('reset').addEventListener('click', function () {
            state = { tahun: 'all', site: 'all', status: 'all' };
            sel.value = 'all';
            ingat(); sinkron(); render();
        });
    }

    function tampilkanGagal(pesan, detail) {
        el('scope').textContent = 'Data tidak bisa dimuat.';
        el('status').textContent = 'gagal memuat';
        el('gagal-wrap').classList.remove('d-none');
        el('gagal-pesan').textContent = pesan;
        el('gagal-detail').textContent = detail || '';
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
            el('isi').classList.remove('d-none');

            if (typeof echarts === 'undefined') {
                tampilkanGagal('Pustaka grafik (ECharts) gagal dimuat dari CDN, jadi grafiknya tidak '
                    + 'bisa digambar. Angka di kartu ringkasan dan tabel tetap benar.');
            }

            bangunFilter();
            render();
        })
        .catch(function (err) {
            tampilkanGagal('Permintaan ke server gagal.', err && err.message);
        });
})();
</script>
<script>
// ---- Tab Deep Dive & Leading Indicator --------------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.imd-page');
    if (!root) { return; }

    var PALETTE = ['#487FFF', '#45B369', '#FF9F29', '#EF4A00', '#8252E9', '#00B8F2', '#E0484A'];
    var WARNA_LAYER = ['#94A3B8', '#487FFF', '#FF9F29', '#45B369', '#8252E9', '#00B8F2'];
    var GRID = '#EEF2F7', INK = '#1F2937', INK2 = '#475569', INK3 = '#94A3B8', NETRAL = '#94A3B8';

    var NAMA_LAYER = {
        1: 'Sistem & Kebijakan', 2: 'Perencanaan & Program', 3: 'Pelaksanaan Lapangan',
        4: 'Kontrol Teknologi', 5: 'Pengaman Fisik & Darurat'
    };
    var SHORT_LAYER = ['Tanpa layer', 'L1 Sistem', 'L2 Perencanaan', 'L3 Pelaksanaan', 'L4 Teknologi', 'L5 Pengaman'];
    var BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    var el = function (n) { return root.querySelector('[data-imi="' + n + '"]'); };
    var fmt = function (n) { return Number(n || 0).toLocaleString('id-ID'); };
    var pct = function (a, b) { return b ? Math.round(a / b * 100) : 0; };
    var esc = function (v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };
    /** Nomor layer dari teks "Layer 3"; 0 kalau tidak terbaca. */
    var noLayer = function (v) { var m = String(v || '').match(/(\d)/); return m ? +m[1] : 0; };
    /** Awalan penomoran sumber ("10. Pengawasan…") dibuang, tidak berurutan di tampilan. */
    var bersihAkt = function (v) { return String(v || '').replace(/^\s*\d+\.\s*/, '').replace(/\s+/g, ' ').trim(); };

    var charts = {};

    function gambar(id, opsi) {
        var node = el(id);
        if (!node || typeof echarts === 'undefined') { return; }
        if (charts[id]) { charts[id].dispose(); }
        charts[id] = echarts.init(node, null, { renderer: 'svg' });
        charts[id].setOption(opsi, true);
    }

    window.addEventListener('resize', function () {
        Object.keys(charts).forEach(function (k) { if (charts[k]) { charts[k].resize(); } });
    });

    function tip(extra) {
        return Object.assign({
            backgroundColor: '#fff', borderColor: GRID,
            textStyle: { color: INK, fontSize: 13 },
            extraCssText: 'box-shadow:0 4px 14px rgba(15,23,42,.12);border-radius:8px'
        }, extra || {});
    }

    function memuat(teks) {
        return '<div class="card radius-8 border"><div class="card-body p-24 text-center text-secondary-light">'
            + '<div class="spinner-border spinner-border-sm text-primary-600 me-2" role="status"></div>'
            + esc(teks) + '</div></div>';
    }

    function kotakPesan(judul, isi, bahaya) {
        var warna = bahaya ? 'danger' : 'info';
        return '<div class="alert alert-' + warna + ' bg-' + warna + '-focus border-' + warna + '-main text-'
            + warna + '-main radius-8 px-20 py-16 mb-24 d-flex align-items-start gap-3">'
            + '<iconify-icon icon="solar:info-circle-outline" class="icon text-xxl flex-shrink-0"></iconify-icon>'
            + '<div><h6 class="text-md fw-semibold mb-4 text-' + warna + '-main">' + esc(judul) + '</h6>'
            + '<p class="text-sm mb-0">' + isi + '</p></div></div>';
    }

    function panel(judul, sub, isi, kelas) {
        return '<div class="card radius-8 border ' + (kelas || 'mb-24') + '">'
            + '<div class="card-header border-bottom bg-base py-16 px-24">'
            +   '<h6 class="text-lg fw-semibold mb-0">' + esc(judul) + '</h6>'
            +   (sub ? '<span class="text-sm text-secondary-light">' + sub + '</span>' : '')
            + '</div><div class="card-body p-24">' + isi + '</div></div>';
    }

    function ambil(url) {
        return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.json();
            });
    }

    // =====================================================================
    // Perpindahan tab
    // =====================================================================
    var tabAktif = 'ringkasan';

    function bukaTab(nama) {
        tabAktif = nama;

        root.querySelectorAll('[data-imi="tabbar"] button').forEach(function (b) {
            b.classList.toggle('active', b.dataset.tab === nama);
        });
        root.querySelectorAll('[data-pane]').forEach(function (p) {
            p.classList.toggle('d-none', p.dataset.pane !== nama);
        });

        try { window.localStorage.setItem('imd-tab', nama); } catch (err) { /* diabaikan */ }

        // ECharts tidak bisa mengukur elemen tersembunyi, jadi ukurannya
        // dihitung ulang begitu panelnya tampil.
        window.requestAnimationFrame(function () {
            Object.keys(charts).forEach(function (k) { if (charts[k]) { charts[k].resize(); } });
            window.dispatchEvent(new Event('resize'));
        });

        if (nama === 'deep') { mulaiDeepDive(); }
        if (nama === 'leading') { mulaiLeading(); }
    }

    el('tabbar').addEventListener('click', function (e) {
        var b = e.target.closest('button[data-tab]');
        if (b) { bukaTab(b.dataset.tab); }
    });

    // =====================================================================
    // Deep Dive
    // =====================================================================
    var ddSiap = false, ddDaftar = null, ddAktif = null;

    function mulaiDeepDive() {
        if (ddSiap) { return; }
        ddSiap = true;

        el('dd-body').innerHTML = memuat('Memuat daftar insiden…');

        ambil(@json(route('ohs-score-card.incident-management.deep-dive.daftar')))
            .then(function (j) {
                if (!j.ok) {
                    el('dd-body').innerHTML = kotakPesan('Daftar insiden tidak bisa dimuat', esc(j.pesan), true);
                    return;
                }

                ddDaftar = j.insiden || [];

                var site = [];
                ddDaftar.forEach(function (r) { if (site.indexOf(r.site) === -1) { site.push(r.site); } });
                site.sort();

                el('dd-site').innerHTML = '<option value="all">Semua site</option>'
                    + site.map(function (s) { return '<option value="' + esc(s) + '">' + esc(s) + '</option>'; }).join('');

                // Rentangnya diambil dari data yang kembali, bukan dari tahun
                // berjalan: daftarnya dibatasi konstanta di controller, dan dua
                // sumber tanggal yang berbeda pasti melenceng begitu tahun ganti.
                if (ddDaftar.length) {
                    var tgl = ddDaftar.map(function (r) { return r.tanggal; }).sort();
                    el('dd-rentang').textContent = ' · ' + tgl[0] + ' s.d. ' + tgl[tgl.length - 1];
                }

                isiPilihanInsiden();

                el('dd-body').innerHTML = kotakPesan('Pilih insiden untuk memulai deep dive',
                    'Halaman ini menyusun rekonstruksi kejadian, status barrier per layer IPLS, sinyal 90 hari '
                    + 'sebelum kejadian di lokasi yang sama, riwayat orang yang terlibat, serta tindak lanjut '
                    + 'dan pengulangan root cause.');

                var awal = (ddDaftar.filter(function (r) { return r.temuan >= 6; })[0] || ddDaftar[0]);
                if (awal) {
                    el('dd-insiden').value = String(awal.id);
                    bukaInsiden(awal.id);
                }
            })
            .catch(function (err) {
                el('dd-body').innerHTML = kotakPesan('Permintaan ke server gagal', esc(err && err.message), true);
            });

        el('dd-site').addEventListener('change', isiPilihanInsiden);
        el('dd-insiden').addEventListener('change', function () {
            if (el('dd-insiden').value) { bukaInsiden(el('dd-insiden').value); }
        });

        var buka = function () {
            var v = String(el('dd-id').value || '').replace(/\D/g, '');
            if (v) { bukaInsiden(v); }
        };
        el('dd-buka').addEventListener('click', buka);
        el('dd-id').addEventListener('keydown', function (e) { if (e.key === 'Enter') { buka(); } });
    }

    function isiPilihanInsiden() {
        var s = el('dd-site').value;
        var sebelum = el('dd-insiden').value;
        var baris = ddDaftar.filter(function (r) { return s === 'all' || r.site === s; });

        el('dd-insiden').innerHTML = baris.length
            ? baris.map(function (r) {
                return '<option value="' + r.id + '">' + esc(r.tanggal) + ' · ' + esc(r.site) + ' · '
                    + esc(r.lokasi) + ' · ' + esc(r.kategori)
                    + (r.temuan ? '' : ' · belum ada analisis') + ' (#' + r.id + ')</option>';
            }).join('')
            : '<option value="">Tidak ada insiden untuk site ini</option>';

        if (baris.some(function (r) { return String(r.id) === sebelum; })) {
            el('dd-insiden').value = sebelum;
        }
    }

    function bukaInsiden(id) {
        id = String(id).replace(/\D/g, '');
        if (!id) { return; }
        ddAktif = id;

        var pilih = el('dd-insiden');
        if (Array.prototype.some.call(pilih.options, function (o) { return o.value === id; })) {
            pilih.value = id;
        }

        el('dd-body').innerHTML = memuat('Memuat deep dive insiden #' + id + '… '
            + 'Sinyal 90 hari memindai jutaan baris, jadi bagian ini butuh beberapa detik.');

        ambil(@json(url('/ohs-score-card/incident-management/deep-dive')) + '/' + id)
            .then(function (j) {
                if (ddAktif !== id) { return; }

                if (!j.ok) {
                    el('dd-body').innerHTML = kotakPesan('Deep dive tidak bisa dimuat', esc(j.pesan), true);
                    return;
                }

                el('dd-body').innerHTML =
                    bagianHeader(j) + bagianBarrier(j) + bagianSinyal(j)
                    + bagianPekerja(j) + bagianTindakLanjut(j);

                gambarSinyal(j.sinyal || {});
                pasangTautanInsiden();
            })
            .catch(function (err) {
                if (ddAktif !== id) { return; }
                el('dd-body').innerHTML = kotakPesan('Permintaan ke server gagal', esc(err && err.message), true);
            });
    }

    function pasangTautanInsiden() {
        el('dd-body').querySelectorAll('a[data-insiden]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                bukaInsiden(a.dataset.insiden);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    }

    // ---- 0. Header ------------------------------------------------------
    function bagianHeader(j) {
        var h = j.header || {};
        var fakta = [
            ['Waktu kejadian', (h.waktu || '-') + ' WITA'],
            ['Site · lokasi', (h.site || '-') + ' · ' + (h.lokasi || '-')],
            ['Detil lokasi', h.detil || '-'],
            ['Potensi', h.potensi || '-'],
            ['PJA BC', h.pja || '-'],
            ['PJA mitra', h.pja_mitra || '-'],
            ['Perusahaan', h.perusahaan || '-'],
            ['Biaya kerugian', j.biaya ? 'Rp ' + fmt(j.biaya) : '-']
        ];

        var isi = '<div class="row gy-3">'
            + fakta.map(function (f) {
                return '<div class="col-xxl-3 col-md-4 col-sm-6">'
                    + '<span class="text-xs text-secondary-light d-block text-uppercase">' + esc(f[0]) + '</span>'
                    + '<span class="text-md fw-medium">' + esc(f[1]) + '</span></div>';
            }).join('')
            + '</div>'
            + (h.kronologi
                ? '<details class="mt-20"><summary class="text-md fw-semibold">Kronologi</summary>'
                    + '<p class="text-sm text-secondary-light mt-12 mb-0" style="white-space:pre-line">'
                    + esc(h.kronologi) + '</p></details>'
                : '');

        var sub = esc(h.kategori || 'Belum dikategorikan') + ' · ' + esc(h.jenis || '-')
            + ' · status ' + esc(h.status || '-') + (h.lpi ? ' · LPI ' + esc(h.lpi) : '');

        return panel('Insiden #' + esc(h.id), sub, isi);
    }

    // ---- 1. Barrier IPLS -------------------------------------------------
    function temuanPerLayer(j) {
        var per = { 1: [], 2: [], 3: [], 4: [], 5: [] };
        (j.temuan || []).forEach(function (t) {
            var n = noLayer(t.layer);
            if (per[n]) { per[n].push(t); }
        });
        return per;
    }

    function keadaanLayer(items) {
        if (items.some(function (r) { return r.status === 'ROOT CAUSE'; })) { return 'gagal'; }
        if (items.some(function (r) { return r.status === 'NON CONFIRMITY'; })) { return 'lemah'; }
        return 'aman';
    }

    function bagianBarrier(j) {
        if (!(j.temuan || []).length) {
            return panel('Barrier IPLS', 'Layer mana yang jebol',
                '<div class="text-center text-secondary-light py-24">Belum ada analisis layer untuk insiden ini. '
                + 'Root cause-nya mungkin masih di MySQL <code>app_mixer.lpi_insiden</code>, '
                + 'atau investigasinya belum sampai tahap analisis.</div>');
        }

        var per = temuanPerLayer(j);
        var urut = { 'ROOT CAUSE': 0, 'NON CONFIRMITY': 1, 'IMPROVEMENT': 2 };

        var isi = '<div class="row gy-3">' + [1, 2, 3, 4, 5].map(function (n) {
            var items = per[n].slice().sort(function (a, b) {
                return (urut[a.status] === undefined ? 3 : urut[a.status])
                     - (urut[b.status] === undefined ? 3 : urut[b.status]);
            });
            var st = keadaanLayer(items);
            var lencana = st === 'gagal'
                ? '<span class="bg-danger-focus text-danger-main px-8 py-2 rounded-pill fw-medium text-xs">Gagal</span>'
                : st === 'lemah'
                    ? '<span class="bg-warning-focus text-warning-main px-8 py-2 rounded-pill fw-medium text-xs">Lemah</span>'
                    : '<span class="bg-neutral-200 text-secondary-light px-8 py-2 rounded-pill fw-medium text-xs">Tidak ada temuan</span>';

            return '<div class="col-xxl col-md-4 col-sm-6">'
                + '<div class="border input-form-light radius-8 p-16 h-100" style="border-top:3px solid '
                +   (st === 'gagal' ? PALETTE[6] : st === 'lemah' ? PALETTE[2] : GRID) + '">'
                +   '<span class="text-xs text-secondary-light d-block">LAYER ' + n + '</span>'
                +   '<h6 class="text-md fw-semibold mb-8">' + esc(NAMA_LAYER[n]) + '</h6>'
                +   lencana
                +   items.map(function (r) {
                        var kelas = r.status === 'ROOT CAUSE' ? 'bg-danger-focus text-danger-main'
                            : r.status === 'NON CONFIRMITY' ? 'bg-warning-focus text-warning-main'
                            : 'bg-info-focus text-info-main';
                        var label = r.status === 'ROOT CAUSE' ? 'Root cause'
                            : r.status === 'NON CONFIRMITY' ? 'Non-conformity' : 'Improvement';
                        return '<div class="border-top mt-12 pt-12">'
                            + '<span class="' + kelas + ' px-8 py-2 rounded-pill fw-medium text-xs">' + label + '</span>'
                            + '<span class="text-sm fw-semibold d-block mt-8">' + esc(bersihAkt(r.aktivitas)) + '</span>'
                            + '<span class="text-xs text-secondary-light d-block">' + esc(r.klasifikasi || '') + '</span>'
                            + (r.keterangan ? '<span class="text-xs text-secondary-light d-block mt-4">'
                                + esc(r.keterangan) + '</span>' : '')
                            + '</div>';
                    }).join('')
                + '</div></div>';
        }).join('') + '</div>';

        return panel('Barrier IPLS', 'Gagal = ada root cause di layer itu; lemah = ada ketidaksesuaian '
            + 'tapi bukan root cause', isi);
    }

    // ---- 2. Sinyal sebelum kejadian -------------------------------------
    function deretMinggu(arr, kunci) {
        var out = new Array(13).fill(0);
        (arr || []).forEach(function (r) {
            if (r.w >= 0 && r.w < 13) { out[r.w] = r[kunci || 'n'] || 0; }
        });
        return out;
    }

    function bagianSinyal(j) {
        var s = j.sinyal || {};
        var h = j.header || {};

        if (!s.lokasi) {
            return panel('Sinyal 90 Hari Sebelum Kejadian', null,
                '<div class="text-center text-secondary-light py-24">Data sinyal tidak tersedia.</div>');
        }

        var hz = deretMinggu(s.hazard, 'lokasi');
        var oak = deretMinggu(s.oak), co = deretMinggu(s.coaching), ob = deretMinggu(s.observasi);
        var lok = s.lokasi || {}, site = s.site || {};

        // Empat minggu terakhir dibandingkan sembilan minggu sebelumnya:
        // yang dicari perubahan irama, bukan angka mutlaknya.
        var baru = 0, lama = 0;
        for (var i = 0; i < 4; i++) { baru += hz[i] + oak[i] + co[i] + ob[i]; }
        for (var k = 4; k < 13; k++) { lama += hz[k] + oak[k] + co[k] + ob[k]; }
        baru /= 4; lama /= 9;
        var delta = lama ? Math.round((baru - lama) / lama * 100) : null;

        var umum = /by DMS|^-?$/i.test(String(h.lokasi || ''));

        var ubin = [
            ['Laporan SAP di lokasi, 4 minggu terakhir', fmt(Math.round(baru)) + '/mgg',
                delta === null ? 'tidak ada pembanding' : (delta >= 0 ? '+' : '') + delta + '% vs 9 minggu sebelumnya', false],
            ['Temuan di lokasi terbuka >7 hari saat kejadian', fmt(lok.menggantung || 0),
                fmt(lok.terbuka || 0) + ' terbuka · ' + fmt(lok.n || 0) + ' temuan dalam 90 hari', (lok.menggantung || 0) > 0],
            ['Temuan di lokasi sudah lewat target', fmt(lok.lewat_target || 0),
                'Se-site ' + fmt(site.lewat_target || 0) + ' dari ' + fmt(site.n || 0) + ' temuan', (lok.lewat_target || 0) > 0],
            ['Temuan di detil lokasi yang sama', fmt(lok.detil || 0), 'dalam 90 hari sebelum kejadian', false]
        ];

        var isi =
            (umum ? kotakPesan('Lokasi kejadian tidak spesifik ("' + esc(h.lokasi) + '")',
                'Sinyal lokasi tidak bisa dibaca dengan andal; angka di bawah memakai label lokasi apa adanya.', true) : '')
            + (s.terpotong ? kotakPesan('Jendela 90 hari terpotong',
                'Data SAP baru ada sejak ' + esc(s.awal_sap) + ', sedangkan jendela insiden ini mulai '
                + esc(s.mulai_jendela) + '. Minggu sebelum itu tercatat nol karena datanya belum ada, '
                + 'bukan karena sepi.') : '')
            + '<div class="row gy-3 mb-24">' + ubin.map(function (u) {
                return '<div class="col-xxl-3 col-md-6">'
                    + '<div class="border input-form-light radius-8 p-16 h-100'
                    +   (u[3] ? ' border-danger-main' : '') + '">'
                    +   '<span class="text-sm text-secondary-light d-block">' + esc(u[0]) + '</span>'
                    +   '<h6 class="fw-semibold mt-8 mb-4">' + u[1] + '</h6>'
                    +   '<span class="text-xs text-secondary-light">' + esc(u[2]) + '</span>'
                    + '</div></div>';
            }).join('') + '</div>'
            + '<div class="imd-chart" data-imi="dd-chart" style="height:400px"></div>'
            + '<div class="row gy-4 mt-8">'
            +   '<div class="col-xxl-5"><h6 class="text-md fw-semibold mb-12">Tema temuan terbanyak di lokasi</h6>'
            +     '<div class="table-responsive"><table class="table bordered-table sm-table mb-0"><tbody>'
            +     ((s.tema || []).map(function (t) {
                      return '<tr><td class="text-end" style="width:64px">' + fmt(t.n) + '×</td><td>'
                          + esc(t.k) + '</td></tr>';
                  }).join('') || '<tr><td class="text-center text-secondary-light py-16">Tidak ada temuan.</td></tr>')
            +     '</tbody></table></div></div>'
            +   '<div class="col-xxl-7"><h6 class="text-md fw-semibold mb-12">Masih terbuka >7 hari saat kejadian</h6>'
            +     '<div class="table-responsive"><table class="table bordered-table sm-table mb-0"><tbody>'
            +     ((s.menggantung || []).map(function (r) {
                      return '<tr><td style="width:96px"><span class="text-xs text-secondary-light">'
                          + esc(r.d) + '</span></td><td><span class="text-sm d-block">' + esc(r.deskripsi || '')
                          + '</span><span class="text-xs text-secondary-light">' + esc(r.detil || '') + ' · '
                          + esc(r.k || '') + ' · target ' + esc(r.target || '-')
                          + (r.lewat ? ' · <span class="text-danger-main fw-medium">lewat target</span>' : '')
                          + '</span></td></tr>';
                  }).join('') || '<tr><td class="text-center text-secondary-light py-16">'
                      + 'Tidak ada. Semua temuan lama sudah ditutup sebelum kejadian.</td></tr>')
            +     '</tbody></table></div></div>'
            + '</div>';

        return panel('Sinyal 90 Hari Sebelum Kejadian',
            'Aktivitas SAP dan temuan hazard di site dan lokasi kejadian; minggu dihitung mundur dari waktu kejadian',
            isi);
    }

    function gambarSinyal(s) {
        if (!el('dd-chart')) { return; }

        var deret = [
            ['Hazard & inspeksi di lokasi', deretMinggu(s.hazard, 'lokasi'), PALETTE[0]],
            ['OAK di lokasi', deretMinggu(s.oak), PALETTE[1]],
            ['Coaching di lokasi', deretMinggu(s.coaching), PALETTE[2]],
            ['Observasi di lokasi', deretMinggu(s.observasi), PALETTE[4]]
        ];

        var label = [];
        for (var w = 12; w >= 0; w--) { label.push(w === 0 ? '0–7 hr' : w + '–' + (w + 1) + ' mgg'); }
        var balik = function (a) { return a.slice().reverse(); };
        var n = deret.length;

        gambar('dd-chart', {
            animation: false,
            tooltip: tip({ trigger: 'axis', axisPointer: { type: 'line', lineStyle: { color: INK3 } } }),
            axisPointer: { link: [{ xAxisIndex: 'all' }] },
            title: deret.map(function (d, i) {
                return { text: d[0], left: 0, top: i * 95, textStyle: { fontSize: 12, fontWeight: 500, color: INK2 } };
            }),
            grid: deret.map(function (d, i) { return { left: 46, right: 12, top: i * 95 + 24, height: 58 }; }),
            xAxis: deret.map(function (d, i) {
                return {
                    type: 'category', gridIndex: i, data: label, axisTick: { show: false },
                    axisLine: { lineStyle: { color: GRID } },
                    axisLabel: { show: i === n - 1, color: INK3, fontSize: 10.5, interval: 1 }
                };
            }),
            yAxis: deret.map(function (d, i) {
                return {
                    type: 'value', gridIndex: i, splitNumber: 2, minInterval: 1,
                    splitLine: { lineStyle: { color: GRID, type: 'dashed' } },
                    axisLabel: { color: INK3, fontSize: 10 }
                };
            }),
            series: deret.map(function (d, i) {
                return {
                    name: d[0], type: 'bar', xAxisIndex: i, yAxisIndex: i, data: balik(d[1]),
                    barMaxWidth: 22, itemStyle: { color: d[2], borderRadius: [3, 3, 0, 0] }
                };
            })
        });
    }

    // ---- 3. Orang terlibat ----------------------------------------------
    function bagianPekerja(j) {
        var P = j.pekerja || [];

        if (!P.length) {
            return panel('Riwayat Orang Terlibat', null,
                '<div class="text-center text-secondary-light py-24">'
                + 'Belum ada pekerja terlibat yang tercatat di beInvestigasi untuk insiden ini.</div>');
        }

        var baris = P.map(function (p) {
            var pelaku = p.peran === 'Korban/Pelaku';
            var br = p.berecord || [], dms = p.dms || [], inc = p.insiden || [];
            var sorot = function (ada) { return ada ? ' class="bg-danger-focus"' : ''; };

            return '<tr>'
                + '<td><span class="text-sm fw-semibold d-block">' + esc(p.nama || '-') + '</span>'
                +   '<span class="text-xs text-secondary-light">' + esc(p.jabatan || '') + ' · '
                +   esc(p.perusahaan || '') + '</span></td>'
                + '<td>' + (pelaku
                    ? '<span class="bg-danger-focus text-danger-main px-8 py-2 rounded-pill fw-medium text-xs">'
                        + esc(p.peran) + '</span>'
                    : '<span class="bg-neutral-200 text-secondary-light px-8 py-2 rounded-pill fw-medium text-xs">'
                        + esc(p.peran || '-') + '</span>') + '</td>'
                + '<td class="text-end"' + (pelaku && !p.coaching ? ' style="background:#FEF2F2"' : '') + '>'
                +   fmt(p.coaching) + '</td>'
                + '<td class="text-end">' + fmt(p.observasi) + '</td>'
                + '<td class="text-end">' + fmt(p.sap) + '</td>'
                + '<td' + sorot(br.length) + '>' + (br.length
                    ? br.slice(0, 3).map(function (b) {
                        return '<span class="text-xs d-block">' + esc(b.tanggal) + ' · ' + esc(b.kategori || '') + '</span>';
                      }).join('') + (br.length > 3
                        ? '<span class="text-xs text-secondary-light">+' + (br.length - 3) + ' lainnya</span>' : '')
                    : '<span class="text-xs text-secondary-light">tidak ada</span>') + '</td>'
                + '<td' + sorot(dms.length) + '>' + (dms.length
                    ? dms.map(function (d) {
                        return '<span class="text-xs d-block">' + fmt(d.n) + '× ' + esc(d.pelanggaran) + '</span>';
                      }).join('')
                    : '<span class="text-xs text-secondary-light">tidak ada</span>') + '</td>'
                + '<td' + sorot(inc.length) + '>' + (inc.length
                    ? inc.slice(0, 3).map(function (x) {
                        return '<span class="text-xs d-block"><a href="javascript:void(0)" data-insiden="' + x.id
                            + '" class="text-primary-600">#' + x.id + '</a> ' + esc(x.tanggal) + '</span>';
                      }).join('')
                    : '<span class="text-xs text-secondary-light">tidak ada</span>') + '</td>'
                + '<td>' + (p.mcu === 'berlaku'
                    ? '<span class="bg-success-focus text-success-main px-8 py-2 rounded-pill fw-medium text-xs">Berlaku</span>'
                    : p.mcu === 'kadaluarsa'
                        ? '<span class="bg-danger-focus text-danger-main px-8 py-2 rounded-pill fw-medium text-xs">Kadaluarsa</span>'
                        : '<span class="bg-neutral-200 text-secondary-light px-8 py-2 rounded-pill fw-medium text-xs">Tidak ada data</span>')
                + '</td></tr>';
        }).join('');

        var isi = '<div class="table-responsive"><table class="table bordered-table sm-table mb-0">'
            + '<thead><tr><th>Pekerja</th><th>Peran</th><th class="text-end">Coaching</th>'
            + '<th class="text-end">Diobservasi</th><th class="text-end">Laporan SAP</th>'
            + '<th>beRecord</th><th>Pelanggaran DMS</th><th>Insiden sebelumnya</th><th>MCU</th></tr></thead>'
            + '<tbody>' + baris + '</tbody></table></div>'
            + '<p class="text-xs text-secondary-light mt-12 mb-0">Coaching, observasi, SAP dan DMS dihitung pada '
            + '90 hari sebelum kejadian; beRecord dan insiden sebelumnya dihitung seluruh riwayat. '
            + 'Sel bertanda warna menandai riwayat yang layak ditelusuri. Status MCU hanya menunjukkan masa '
            + 'berlaku, bukan hasil pemeriksaan.</p>';

        return panel('Riwayat Orang Terlibat', 'Jendela 90 hari sebelum kejadian', isi);
    }

    // ---- 4. Tindak lanjut & pengulangan ----------------------------------
    function bagianTindakLanjut(j) {
        var per = temuanPerLayer(j);
        var car = j.car || [];
        var hariIni = new Date().toISOString().slice(0, 10);

        var baris = [1, 2, 3, 4, 5].map(function (n) {
            var items = per[n];
            var cs = car.filter(function (x) { return noLayer(x.layer) === n; });
            var jalan = cs.filter(function (x) { return x.status === 'OPEN' || x.status === 'PENDING APPROVAL'; });

            return {
                n: n,
                rc: items.filter(function (r) { return r.status === 'ROOT CAUSE'; }).length,
                nc: items.filter(function (r) { return r.status === 'NON CONFIRMITY'; }).length,
                car: cs.length,
                selesai: cs.filter(function (x) { return x.status === 'CLOSED' || x.status === 'CLOSED OVERDUE'; }).length,
                jalan: jalan.length,
                lewat: jalan.filter(function (x) { return x.target && x.target < hariIni; }).length,
                telat: cs.filter(function (x) { return x.status === 'CLOSED OVERDUE'; }).length
            };
        });

        var nilai = function (r) {
            var perlu = r.rc + r.nc > 0;
            if (!perlu && !r.car) { return ['bg-neutral-200 text-secondary-light', '–']; }
            if (perlu && !r.car) { return ['bg-danger-focus text-danger-main', 'Tidak ada CAR']; }
            if (r.lewat) { return ['bg-danger-focus text-danger-main', 'CAR lewat target']; }
            if (r.telat) { return ['bg-warning-focus text-warning-main', 'Selesai terlambat']; }
            if (r.jalan) { return ['bg-warning-focus text-warning-main', 'Berjalan']; }
            if (perlu) { return ['bg-success-focus text-success-main', 'Tertangani']; }
            return ['bg-info-focus text-info-main', 'CAR tambahan'];
        };

        var tabelCar = '<div class="table-responsive"><table class="table bordered-table sm-table mb-0">'
            + '<thead><tr><th>Layer</th><th class="text-end">Root cause</th><th class="text-end">Non-conformity</th>'
            + '<th class="text-end">CAR</th><th class="text-end">Selesai</th><th class="text-end">Berjalan</th>'
            + '<th class="text-end">Lewat target</th><th>Penilaian</th></tr></thead><tbody>'
            + baris.map(function (r) {
                var v = nilai(r);
                return '<tr><td><span class="imd-chip" style="background:' + WARNA_LAYER[r.n] + '">L' + r.n
                    + '</span><span class="text-sm">' + esc(NAMA_LAYER[r.n]) + '</span></td>'
                    + '<td class="text-end">' + r.rc + '</td><td class="text-end">' + r.nc + '</td>'
                    + '<td class="text-end">' + r.car + '</td><td class="text-end">' + r.selesai + '</td>'
                    + '<td class="text-end">' + r.jalan + '</td><td class="text-end">' + r.lewat + '</td>'
                    + '<td><span class="' + v[0] + ' px-8 py-2 rounded-pill fw-medium text-xs">' + v[1] + '</span></td></tr>';
            }).join('')
            + '</tbody></table></div>';

        var rek = j.rekurensi || [];
        var tabelRek = rek.length
            ? '<div class="table-responsive"><table class="table bordered-table sm-table mb-0">'
                + '<thead><tr><th>Root cause</th><th class="text-end">PJA sama, sebelum</th>'
                + '<th class="text-end">PJA sama, sesudah</th><th class="text-end">Site sama, sebelum</th>'
                + '<th>Insiden terkait di PJA yang sama</th></tr></thead><tbody>'
                + rek.map(function (r) {
                    var n = noLayer(r.layer);
                    return '<tr><td><span class="imd-chip" style="background:' + WARNA_LAYER[n] + '">L' + n
                        + '</span><span class="text-sm">' + esc(bersihAkt(r.aktivitas)) + '</span></td>'
                        + '<td class="text-end' + (r.pja_sebelum ? ' fw-semibold text-danger-main' : '') + '">'
                        +   fmt(r.pja_sebelum) + '</td>'
                        + '<td class="text-end' + (r.pja_sesudah ? ' fw-semibold text-danger-main' : '') + '">'
                        +   fmt(r.pja_sesudah) + '</td>'
                        + '<td class="text-end">' + fmt(r.site_sebelum) + '</td>'
                        + '<td class="text-xs">' + ((r.daftar || []).map(function (x) {
                              return '<a href="javascript:void(0)" data-insiden="' + x.id
                                  + '" class="text-primary-600">#' + x.id + '</a> ' + esc(x.d);
                          }).join(' · ') || '-') + '</td></tr>';
                }).join('')
                + '</tbody></table></div>'
                + '<p class="text-xs text-secondary-light mt-12 mb-0">"Sebelum" berarti root cause yang sama sudah '
                + 'pernah muncul, jadi perbaikan sebelumnya belum efektif. "Sesudah" berarti muncul lagi setelah '
                + 'insiden ini.</p>'
            : '<div class="text-center text-secondary-light py-24">Belum ada temuan ber-status Root cause '
                + 'untuk dibandingkan.</div>';

        return panel('Tindak Lanjut per Layer',
            'Apakah CAR menyasar layer yang gagal, dan selesai tepat waktu', tabelCar)
            + panel('Pengulangan Root Cause',
                'Apakah penyebab yang sama pernah muncul di PJA atau site yang sama', tabelRek);
    }

    // =====================================================================
    // Leading Indicator
    // =====================================================================
    var INDIKATOR = [
        { k: 'hazard', label: 'Laporan hazard & inspeksi', f: function (r) { return r.hazard; }, aktivitas: true },
        { k: 'pelapor', label: 'Pelapor hazard aktif', f: function (r) { return r.pelapor; }, aktivitas: true },
        { k: 'per_pelapor', label: 'Laporan per pelapor', aktivitas: true, desimal: 1,
          f: function (r) { return r.pelapor ? r.hazard / r.pelapor : null; } },
        { k: 'tepat', label: '% temuan selesai tepat waktu', persen: true, desimal: 1,
          f: function (r) { return r.jatuh_tempo ? r.tepat_waktu / r.jatuh_tempo * 100 : null; } },
        { k: 'lewat', label: 'Temuan minggu itu yang kini lewat target', f: function (r) { return r.lewat_target; } },
        { k: 'oak', label: 'OAK', f: function (r) { return r.oak; }, aktivitas: true },
        { k: 'coaching', label: 'Coaching', f: function (r) { return r.coaching; }, aktivitas: true },
        { k: 'observasi', label: 'Observasi lapangan', f: function (r) { return r.observasi; }, aktivitas: true },
        { k: 'dms', label: 'Pelanggaran DMS dilaporkan', f: function (r) { return r.dms; } }
    ];

    var ldSiap = false, ldData = null;
    var K = { site: 0, minggu: 1, hazard: 2, jatuh_tempo: 3, tepat_waktu: 4, lewat_target: 5,
              pelapor: 6, oak: 7, coaching: 8, observasi: 9, dms: 10, insiden: 11, berkonsekuensi: 12 };

    function keObjek(b) {
        var o = {};
        Object.keys(K).forEach(function (n) { o[n] = b[K[n]]; });
        return o;
    }

    function mulaiLeading() {
        if (ldSiap) { return; }
        ldSiap = true;

        el('ld-indikator').innerHTML = INDIKATOR.map(function (x) {
            return '<option value="' + x.k + '">' + esc(x.label) + '</option>';
        }).join('');

        el('ld-body').innerHTML = memuat('Memuat indikator mingguan dari OBDS… '
            + 'Agregasinya memindai jutaan baris, jadi butuh beberapa detik.');

        ambil(@json(route('ohs-score-card.incident-management.leading-indicator')))
            .then(function (j) {
                if (!j.ok) {
                    el('ld-body').innerHTML = kotakPesan('Indikator tidak bisa dimuat', esc(j.pesan), true);
                    return;
                }

                ldData = j;

                var adaIsi = {};
                j.baris.forEach(function (b) { if (b[K.hazard] > 0) { adaIsi[b[K.site]] = true; } });

                el('ld-site').innerHTML = '<option value="all">Semua site</option>'
                    + Object.keys(adaIsi).map(function (i) {
                        return '<option value="' + i + '">' + esc(j.site[i]) + '</option>';
                    }).join('');

                gambarLeading();
            })
            .catch(function (err) {
                el('ld-body').innerHTML = kotakPesan('Permintaan ke server gagal', esc(err && err.message), true);
            });

        el('ld-site').addEventListener('change', gambarLeading);
        el('ld-indikator').addEventListener('change', gambarLeading);
    }

    /** Korelasi Pearson; null bila sampelnya terlalu sedikit untuk dipercaya. */
    function pearson(xs, ys) {
        var n = xs.length;
        if (n < 8) { return null; }
        var mx = 0, my = 0, i;
        for (i = 0; i < n; i++) { mx += xs[i]; my += ys[i]; }
        mx /= n; my /= n;
        var sxy = 0, sx = 0, sy = 0;
        for (i = 0; i < n; i++) {
            var a = xs[i] - mx, b = ys[i] - my;
            sxy += a * b; sx += a * a; sy += b * b;
        }
        return sx && sy ? sxy / Math.sqrt(sx * sy) : null;
    }

    /** Dinormalkan per site supaya site besar tidak mendominasi korelasinya. */
    function baku(arr) {
        var isi = arr.filter(function (x) { return x !== null; });
        if (isi.length < 4) { return null; }
        var m = isi.reduce(function (s, x) { return s + x; }, 0) / isi.length;
        var sd = Math.sqrt(isi.reduce(function (s, x) { return s + (x - m) * (x - m); }, 0) / isi.length);
        if (!sd) { return null; }
        return arr.map(function (x) { return x === null ? null : (x - m) / sd; });
    }

    function korelasiLag(ind, siteIdx, minggu) {
        var hasil = [];

        for (var lag = 0; lag <= 8; lag++) {
            var X = [], Y = [];

            siteIdx.forEach(function (si) {
                var deret = minggu.map(function (w) {
                    var b = ldData.baris.find(function (r) { return r[K.site] === si && r[K.minggu] === w; });
                    return b ? keObjek(b) : { hazard: 0, jatuh_tempo: 0, tepat_waktu: 0, lewat_target: 0,
                                              pelapor: 0, oak: 0, coaching: 0, observasi: 0, dms: 0, insiden: 0 };
                });
                var x = baku(deret.map(function (r) {
                    var v = ind.f(r);
                    return (v === null || v === undefined || isNaN(v)) ? null : v;
                }));
                var y = baku(deret.map(function (r) { return r.insiden; }));
                if (!x || !y) { return; }

                for (var t = lag; t < minggu.length; t++) {
                    if (x[t - lag] === null || y[t] === null) { continue; }
                    X.push(x[t - lag]); Y.push(y[t]);
                }
            });

            hasil.push({ lag: lag, r: pearson(X, Y), n: X.length });
        }

        return hasil;
    }

    function gambarLeading() {
        if (!ldData) { return; }

        var site = el('ld-site').value;
        var ind = INDIKATOR.filter(function (x) { return x.k === el('ld-indikator').value; })[0] || INDIKATOR[0];
        var batas = ldData.batas_lengkap;
        var minggu = ldData.minggu.filter(function (w) { return !batas || w <= batas; });
        var semuaSite = [];
        ldData.baris.forEach(function (b) {
            if (b[K.hazard] > 0 && semuaSite.indexOf(b[K.site]) === -1) { semuaSite.push(b[K.site]); }
        });
        var siteIdx = site === 'all' ? semuaSite : [+site];

        // Agregat per minggu untuk site yang dipilih
        var agg = minggu.map(function (w) {
            var o = { minggu: w };
            Object.keys(K).forEach(function (n) { if (n !== 'site' && n !== 'minggu') { o[n] = 0; } });
            ldData.baris.forEach(function (b) {
                if (b[K.minggu] !== w || siteIdx.indexOf(b[K.site]) === -1) { return; }
                Object.keys(K).forEach(function (n) {
                    if (n !== 'site' && n !== 'minggu') { o[n] += b[K[n]]; }
                });
            });
            return o;
        });

        var lag = korelasiLag(ind, siteIdx, minggu);
        var totInsiden = agg.reduce(function (s, r) { return s + r.insiden; }, 0);

        // Kesehatan tiap indikator, selalu dihitung untuk SELURUH site supaya
        // penilaiannya tidak berubah-ubah mengikuti pilihan site.
        var sehat = INDIKATOR.map(function (x) {
            var lc = korelasiLag(x, semuaSite, minggu).filter(function (r) { return r.lag >= 1 && r.r !== null; });
            var terbaik = lc.reduce(function (b, r) {
                return (!b || Math.abs(r.r) > Math.abs(b.r)) ? r : b;
            }, null);

            var nilai = minggu.map(function (w) {
                var o = {};
                Object.keys(K).forEach(function (n) { if (n !== 'site' && n !== 'minggu') { o[n] = 0; } });
                ldData.baris.forEach(function (b) {
                    if (b[K.minggu] !== w) { return; }
                    Object.keys(K).forEach(function (n) {
                        if (n !== 'site' && n !== 'minggu') { o[n] += b[K[n]]; }
                    });
                });
                return x.f(o);
            }).filter(function (v) { return v !== null && !isNaN(v); });

            var rata = nilai.reduce(function (a, v) { return a + v; }, 0) / (nilai.length || 1);
            var sd = Math.sqrt(nilai.reduce(function (a, v) { return a + (v - rata) * (v - rata); }, 0) / (nilai.length || 1));
            var cv = rata ? sd / rata : 0;
            var pita = terbaik ? 2 / Math.sqrt(terbaik.n) : 1;

            var verdikt, kelas;
            if (x.persen && rata >= 97) {
                verdikt = 'Jenuh: hampir selalu mendekati 100%, tidak membedakan minggu berisiko';
                kelas = 'bg-warning-focus text-warning-main';
            } else if (cv < 0.03) {
                verdikt = 'Datar: hampir tidak berubah antar minggu';
                kelas = 'bg-warning-focus text-warning-main';
            } else if (terbaik && Math.abs(terbaik.r) >= pita) {
                verdikt = 'Ada sinyal: nilainya ' + (terbaik.r < 0 ? 'lebih rendah' : 'lebih tinggi') + ' '
                    + terbaik.lag + ' minggu sebelum minggu yang insidennya banyak';
                kelas = (terbaik.r < 0 && x.aktivitas) ? 'bg-success-focus text-success-main' : 'bg-info-focus text-info-main';
            } else {
                verdikt = 'Belum terlihat hubungan dengan insiden';
                kelas = 'bg-neutral-200 text-secondary-light';
            }

            return { x: x, terbaik: terbaik, rata: rata, verdikt: verdikt, kelas: kelas };
        });

        el('ld-body').innerHTML =
            panel('Tren Mingguan · ' + esc(site === 'all' ? 'semua site' : ldData.site[+site]),
                esc(minggu.length + ' minggu (' + (minggu[0] || '') + ' s.d. ' + (batas || '') + '), '
                    + fmt(totInsiden) + ' insiden. Panel atas insiden (lagging), panel bawah indikator (leading).'),
                '<div class="imd-chart" data-imi="ld-chart" style="height:400px"></div>')
            + '<div class="row gy-4 mb-24"><div class="col-xxl-6">'
            +   panel('Uji Lead-Lag', 'Korelasi antara nilai indikator <i>k</i> minggu sebelumnya dan jumlah '
                    + 'insiden minggu ini, dinormalkan per site. Batang yang melewati garis putus-putus '
                    + 'cukup kuat untuk ditindaklanjuti.',
                    '<div class="imd-chart" data-imi="ld-lag" style="height:300px"></div>'
                    + '<p class="text-xs text-secondary-light mt-12 mb-0">Korelasi bukan sebab-akibat. Nilai '
                    + 'positif pada indikator aktivitas sering berarti site yang sibuk punya lebih banyak '
                    + 'laporan sekaligus lebih banyak insiden. Konfirmasi lewat deep dive per insiden.</p>',
                    'h-100')
            + '</div><div class="col-xxl-6">'
            +   panel('Kesehatan Indikator · semua site', 'Mana yang layak dipakai sebagai leading indicator',
                    '<div class="table-responsive"><table class="table bordered-table sm-table mb-0">'
                    + '<thead><tr><th>Indikator</th><th class="text-end">Rata-rata/mgg</th>'
                    + '<th class="text-end">Lag terkuat</th><th>Penilaian</th></tr></thead><tbody>'
                    + sehat.map(function (h) {
                        return '<tr style="cursor:pointer" data-ind="' + h.x.k + '">'
                            + '<td><span class="text-sm fw-semibold">' + esc(h.x.label) + '</span></td>'
                            + '<td class="text-end">' + (h.x.persen ? h.rata.toFixed(1) + '%'
                                : h.x.desimal ? h.rata.toFixed(1) : fmt(Math.round(h.rata))) + '</td>'
                            + '<td class="text-end text-xs">' + (h.terbaik
                                ? h.terbaik.lag + ' mgg · r ' + h.terbaik.r.toFixed(2) : '-') + '</td>'
                            + '<td><span class="' + h.kelas + ' px-8 py-2 rounded-pill fw-medium text-xs" '
                            +   'style="white-space:normal">' + esc(h.verdikt) + '</span></td></tr>';
                    }).join('')
                    + '</tbody></table></div>'
                    + '<p class="text-xs text-secondary-light mt-12 mb-0">Klik baris untuk menampilkan '
                    + 'indikator itu di grafik.</p>', 'h-100')
            + '</div></div>'
            + panel('Catatan Leading Indicator', null,
                '<div class="row gy-3">'
                + [['Sumber', 'mv_inspeksi_hazard, mv_oak, mv_coaching, mv_observasi (bcbeats), '
                    + 'mv_dms_violation_report (bcsid) dan mv_investigasi. Semuanya materialized view, '
                    + 'jadi minggu terakhir bisa belum lengkap dan sudah dipotong otomatis.'],
                   ['Belum termasuk', 'Jam kerja atau jumlah pekerja sebagai pembagi, sehingga angkanya masih '
                    + 'jumlah absolut, bukan rate. Site yang besar otomatis terlihat lebih tinggi.'],
                   ['Cara membaca', '"% tepat waktu" dihitung dari temuan yang sudah jatuh tempo; '
                    + '"lewat target" dihitung terhadap hari ini, jadi minggu-minggu lama cenderung lebih kecil.']]
                    .map(function (c) {
                        return '<div class="col-xxl-4"><div class="border input-form-light radius-8 p-16 h-100">'
                            + '<span class="text-md fw-semibold d-block mb-8">' + esc(c[0]) + '</span>'
                            + '<p class="text-sm text-secondary-light mb-0">' + esc(c[1]) + '</p></div></div>';
                    }).join('')
                + '</div>', 'mb-0');

        el('ld-body').querySelectorAll('tr[data-ind]').forEach(function (tr) {
            tr.addEventListener('click', function () {
                el('ld-indikator').value = tr.dataset.ind;
                gambarLeading();
            });
        });

        var label = minggu.map(function (w) {
            var d = new Date(w + 'T00:00:00');
            return d.getDate() + ' ' + BULAN[d.getMonth()];
        });
        var nilaiInd = agg.map(function (r) {
            var v = ind.f(r);
            return (v === null || isNaN(v)) ? null : v;
        });

        gambar('ld-chart', {
            animation: false,
            tooltip: tip({
                trigger: 'axis', axisPointer: { type: 'line', lineStyle: { color: INK3 } },
                valueFormatter: function (v) {
                    return (v === null || v === undefined) ? '-' : (ind.desimal ? (+v).toFixed(ind.desimal) : fmt(Math.round(v)));
                }
            }),
            axisPointer: { link: [{ xAxisIndex: 'all' }] },
            legend: { data: ['Insiden', 'Berkonsekuensi'], top: 0, right: 0, itemWidth: 10, itemHeight: 10,
                      textStyle: { color: INK2, fontSize: 11 } },
            title: [
                { text: 'Insiden (lagging)', left: 0, top: 0, textStyle: { fontSize: 12, fontWeight: 500, color: INK2 } },
                { text: ind.label + ' (leading)', left: 0, top: '42%', textStyle: { fontSize: 12, fontWeight: 500, color: INK2 } }
            ],
            grid: [{ left: 48, right: 12, top: 24, height: '26%' }, { left: 48, right: 12, top: '50%', bottom: 30 }],
            xAxis: [0, 1].map(function (i) {
                return {
                    type: 'category', gridIndex: i, data: label, axisTick: { show: false },
                    axisLine: { lineStyle: { color: GRID } },
                    axisLabel: { show: i === 1, color: INK3, fontSize: 10.5, hideOverlap: true }
                };
            }),
            yAxis: [0, 1].map(function (i) {
                return {
                    type: 'value', gridIndex: i, splitNumber: i ? 4 : 2, minInterval: i ? 0 : 1,
                    scale: i === 1 && !!ind.persen,
                    splitLine: { lineStyle: { color: GRID, type: 'dashed' } },
                    axisLabel: { color: INK3, fontSize: 10, formatter: function (v) { return ind.persen && i ? v + '%' : v; } }
                };
            }),
            series: [
                { name: 'Insiden', type: 'bar', xAxisIndex: 0, yAxisIndex: 0, barMaxWidth: 14,
                  data: agg.map(function (r) { return r.insiden; }),
                  itemStyle: { color: PALETTE[6], borderRadius: [3, 3, 0, 0] } },
                { name: 'Berkonsekuensi', type: 'bar', xAxisIndex: 0, yAxisIndex: 0, barMaxWidth: 14, barGap: '-100%',
                  data: agg.map(function (r) { return r.berkonsekuensi; }),
                  itemStyle: { color: INK, opacity: 0.55, borderRadius: [3, 3, 0, 0] } },
                { name: ind.label, type: 'line', xAxisIndex: 1, yAxisIndex: 1, data: nilaiInd,
                  showSymbol: false, connectNulls: false,
                  lineStyle: { width: 2, color: PALETTE[0] },
                  areaStyle: { color: PALETTE[0], opacity: 0.08 } }
            ]
        });

        var pertama = lag.filter(function (r) { return r.n; })[0];
        var pita = pertama ? 2 / Math.sqrt(pertama.n) : null;

        gambar('ld-lag', {
            animation: false,
            grid: { left: 44, right: 12, top: 14, bottom: 42 },
            tooltip: tip({
                trigger: 'axis', axisPointer: { type: 'shadow', shadowStyle: { color: 'rgba(100,116,139,.08)' } },
                formatter: function (p) {
                    var r = lag[p[0].dataIndex];
                    return r.lag + ' minggu sebelumnya<br>r = <b>' + (r.r === null ? '-' : r.r.toFixed(3))
                        + '</b> · n = ' + r.n;
                }
            }),
            xAxis: {
                type: 'category', data: lag.map(function (r) { return r.lag === 0 ? 'minggu sama' : r.lag + ' mgg'; }),
                name: 'jarak waktu', nameLocation: 'middle', nameGap: 28,
                nameTextStyle: { color: INK3, fontSize: 11 },
                axisTick: { show: false }, axisLine: { lineStyle: { color: GRID } },
                axisLabel: { color: INK3, fontSize: 10.5, interval: 0 }
            },
            yAxis: {
                type: 'value',
                min: function (v) { return Math.min(-0.3, Math.floor(v.min * 10) / 10); },
                max: function (v) { return Math.max(0.3, Math.ceil(v.max * 10) / 10); },
                splitLine: { lineStyle: { color: GRID, type: 'dashed' } },
                axisLabel: { color: INK3, fontSize: 10 }
            },
            series: [{
                type: 'bar', barMaxWidth: 26,
                data: lag.map(function (r) {
                    var kuat = pita && r.r !== null && Math.abs(r.r) >= pita;
                    return {
                        value: r.r === null ? null : +r.r.toFixed(3),
                        itemStyle: {
                            color: r.r === null ? NETRAL : (kuat ? (r.r < 0 ? PALETTE[1] : PALETTE[3]) : NETRAL),
                            borderRadius: (r.r || 0) >= 0 ? [3, 3, 0, 0] : [0, 0, 3, 3]
                        }
                    };
                }),
                markLine: pita ? {
                    symbol: 'none', silent: true, label: { show: false },
                    lineStyle: { color: INK3, type: 'dashed' },
                    data: [{ yAxis: pita }, { yAxis: -pita }]
                } : undefined
            }]
        });
    }

    // Tab terakhir diingat; kalau yang tersimpan sudah tidak ada, kembali ke Ringkasan.
    var simpan = 'ringkasan';
    try { simpan = window.localStorage.getItem('imd-tab') || 'ringkasan'; } catch (err) { /* diabaikan */ }
    if (['ringkasan', 'deep', 'leading'].indexOf(simpan) === -1) { simpan = 'ringkasan'; }
    if (simpan !== 'ringkasan') { bukaTab(simpan); }
})();
</script>
@endsection
