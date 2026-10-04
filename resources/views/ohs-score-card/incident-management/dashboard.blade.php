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

  {{-- Catatan sumber data --}}
  <div class="row gy-4">
    <div class="col-12">
      <div class="card radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Catatan Data</h6>
          <span class="text-sm text-secondary-light" data-imi="meta">Sumber: {{ $tabel }}</span>
        </div>
        <div class="card-body p-24">
          <div class="row gy-3">
            <div class="col-xxl-6">
              <div class="border input-form-light radius-8 p-16 h-100">
                <span class="text-md fw-semibold d-block mb-8">Sumber</span>
                <p class="text-sm text-secondary-light mb-0">
                  <code>{{ $tabel }}</code> di OBDS, kolom JSONB <code>rootcause</code> dan
                  <code>tindakan_perbaikan</code>. Materialized view, jadi isinya snapshot — bukan realtime.
                  <b>Insiden yang tidak diinvestigasi tidak ikut dihitung</b>, begitu juga status DELETED
                  dan data uji ("Test"). Membuangnya hampir tidak menyentuh sisi IPLS — dari 1.128 insiden
                  tak terinvestigasi hanya satu yang punya analisis layer — tetapi membuat kategori
                  "Belum dikategorikan" menyusut drastis, karena kategori kecelakaan memang baru diisi
                  saat investigasi berjalan.
                </p>
              </div>
            </div>
            <div class="col-xxl-6">
              <div class="border input-form-light radius-8 p-16 h-100">
                <span class="text-md fw-semibold d-block mb-8">Tanggal &amp; site</span>
                <p class="text-sm text-secondary-light mb-0">
                  Memakai tanggal dan site hasil validasi investigasi; bila kosong, memakai data
                  laporan CCR.
                </p>
              </div>
            </div>
            <div class="col-xxl-6">
              <div class="border input-form-light radius-8 p-16 h-100">
                <span class="text-md fw-semibold d-block mb-8">Penggabungan kategori</span>
                <p class="text-sm text-secondary-light mb-0">
                  Near Miss, Near Miss HIPO dan Non HIPO digabung jadi Near Miss; First Aid,
                  Medical Treatment dan Major Injury jadi Injury; Hazard HIPO digabung dengan
                  Pelanggaran PSPP. "Belum dikategorikan" berarti kategorinya memang belum diisi,
                  kebanyakan pada insiden yang tidak diinvestigasi.
                </p>
              </div>
            </div>
            <div class="col-xxl-6">
              <div class="border input-form-light radius-8 p-16 h-100">
                <span class="text-md fw-semibold d-block mb-8">Label layer &amp; efektivitas perbaikan</span>
                <p class="text-sm text-secondary-light mb-0">
                  Nama pendek tiap layer disusun dari daftar aktivitas di dalamnya, bukan nama resmi
                  sistem. Panel Efektivitas Perbaikan hanya memakai temuan ber-status
                  <i>Root cause</i>, dan pasangannya dihitung per insiden — bukan per temuan —
                  supaya satu insiden dengan banyak root cause dan banyak CAR tidak menggelembungkan
                  alurnya. Insiden yang root cause-nya baru ada di MySQL
                  <code>app_mixer.lpi_insiden</code> belum ikut dihitung.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
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
            + fmt(jumlah(carRows, CAR.n)) + ' tindakan perbaikan';
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
            el('meta').textContent = 'Sumber: ' + json.meta.tabel
                + ' · diambil ' + json.meta.diambil + ' lewat ' + json.meta.koneksi;

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
@endsection
