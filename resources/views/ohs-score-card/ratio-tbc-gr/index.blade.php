@extends('ohs-score-card.layouts.app')

@section('title', 'Ratio TBC & GR')

@section('css')
<style>

  /* Badge status: ACCEPT hijau, REJECT/>12% merah, WARNING kuning, OVERGRADE oranye. */

  /* Kesimpulan dibuat lebih tegas daripada badge status per-parameter. */


  /* ---- Tab Overview / Raw Data ---- */
  .jss-tabs .nav-link {
    border: 1px solid transparent; border-radius: 10px 10px 0 0;
    font-weight: 600; font-size: 14px; color: #64748B;
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px;
  }
  .jss-tabs .nav-link:hover { color: #2563EB; background: #F8FAFC; }
  .jss-tabs .nav-link.active {
    color: #fff; background: #487FFF; border-color: #487FFF;
    box-shadow: 0 4px 12px rgba(72, 127, 255, 0.25);
  }

  /* Penanda target pada progress bar. Tidak ada padanannya di WowDash,
     jadi ditulis sendiri: satu garis tipis di posisi persentase target. */
  .ov-track { position: relative; overflow: visible; }
  .ov-track__target {
    position: absolute; top: -3px; bottom: -3px; width: 2px;
    background: var(--text-primary-light, #0F172A); opacity: .45;
  }

  /* ---- Matriks capaian bulanan ---- */
  .ov-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .ov-matrix {
    width: 100%; min-width: 900px;
    border-collapse: separate; border-spacing: 3px;
    font-size: 12px;
  }
  .ov-matrix th, .ov-matrix td {
    padding: 7px 9px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .ov-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #475569;
    font-weight: 700; font-size: 11px; border-radius: 8px;
  }
  .ov-matrix thead th.ov-th-last { background: #2E90FA !important; color: #fff !important; }
  /* Sel site di-merge dengan rowspan, jadi diratakan ke tengah secara
     vertikal supaya label berada di tengah blok site-nya. */
  .ov-matrix .ov-site {
    position: sticky; left: 0; z-index: 2;
    background: #EEF1E2; color: #3F4A2E !important;
    font-weight: 800; text-align: center !important;
    vertical-align: middle !important;
    border-radius: 8px; min-width: 76px;
    letter-spacing: 0.02em;
  }
  .ov-matrix .ov-mitra {
    position: sticky; left: 79px; z-index: 2;
    background: #fff; color: #2E6BE6 !important;
    font-weight: 700; text-align: left !important;
    border-radius: 8px; min-width: 92px;
    box-shadow: 1px 0 0 #EEF2F7;
  }
  .ov-matrix thead .ov-site, .ov-matrix thead .ov-mitra { z-index: 4; background: #F8FAFC; }
  .ov-matrix .ov-avg { font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px; }
  .ov-matrix .ov-trend--up { color: #16A34A; font-weight: 800; }
  .ov-matrix .ov-trend--down { color: #DC2626; font-weight: 800; }
  .ov-matrix .ov-cell {
    font-weight: 700; color: #fff; min-width: 58px;
    border-radius: 6px; border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
  }
  .ov-matrix .ov-cell:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(5,150,105,.35);
    position: relative; z-index: 1;
  }
  .ov-matrix .ov-empty { background: #F1F5F9; color: #CBD5E1 !important; border-radius: 6px; }
  .ov-t1 { background: #E0484A; }
  .ov-t2 { background: #F08C2E; }
  .ov-t3 { background: #F2C230; color: #1F2937 !important; }
  .ov-t4 { background: #86C96B; }
  .ov-t5 { background: #059669; }
  /* Mode Nilai: 4 band resmi, warnanya senada dengan kartu perusahaan. */
  .ov-n1 { background: #E0484A; }
  .ov-n2 { background: #F08C2E; }
  .ov-n3 { background: #F2C230; color: #1F2937 !important; }
  .ov-n4 { background: #16A34A; }
  .ov-matrix .ov-cell { font-variant-numeric: tabular-nums; }

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Ratio TBC &amp; GR</h6>
    <div class="text-secondary-light text-sm mt-4">
      Rasio pengawas yang membuat laporan TBC terhadap pengawas yang tercatat RFID
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
    <li class="fw-medium text-primary-600">Ratio TBC &amp; GR</li>
  </ul>
</div>

{{-- Ringkasan kepatuhan; ikut filter yang sedang aktif (di-update dari respons AJAX). --}}
<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="jss-tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="jss-tab-overview"
            data-bs-toggle="pill" data-bs-target="#jss-pane-overview" data-tab-key="overview"
            type="button" role="tab" aria-controls="jss-pane-overview" aria-selected="true">
      Ringkasan
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="jss-tab-rawdata"
            data-bs-toggle="pill" data-bs-target="#jss-pane-rawdata" data-tab-key="rawdata"
            type="button" role="tab" aria-controls="jss-pane-rawdata" aria-selected="false">
      Data Pelaporan
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="jss-pane-overview" role="tabpanel">

    {{-- Filter --}}
    <div class="card radius-8 border mb-24">
      <div class="card-body p-24">
        <div class="row gy-3 gx-3 align-items-end">
          <div class="col-xxl-3 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8" for="ov-site">Site</label>
            <select class="form-select form-select-sm radius-8 ov-filter" id="ov-site" data-column="site">
              <option value="">Semua Site</option>
              @foreach ($filterOptions['site'] ?? [] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xxl-3 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8" for="ov-mitra">Perusahaan</label>
            <select class="form-select form-select-sm radius-8 ov-filter" id="ov-mitra" data-column="mitra">
              <option value="">Semua Perusahaan</option>
              @foreach ($filterOptions['mitra'] ?? [] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xxl-3 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8" for="ov-month">Bulan</label>
            <select class="form-select form-select-sm radius-8 ov-filter" id="ov-month" data-column="month">
              <option value="">Semua Bulan</option>
              @foreach ($monthOptions as $number => $label)
                <option value="{{ $number }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xxl-1 col-md-4 col-sm-6">
            <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100" id="ov-reset">Reset</button>
          </div>
          <div class="col-12">
            <span class="text-sm text-secondary-light" id="ov-status">Memuat ringkasan…</span>
          </div>
        </div>
      </div>
    </div>

    {{-- Kartu ringkasan utama --}}
    <div class="row gy-4 mb-24" id="ov-kpi"></div>

    <div class="row gy-4 mb-24">
      <div class="col-xxl-8">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Pemenuhan per Perusahaan</h6>
          </div>
          <div class="card-body p-24">
            <div class="row gy-3" id="ov-perusahaan"></div>
          </div>
        </div>
      </div>

      <div class="col-xxl-4">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Pemenuhan per Site</h6>
          </div>
          <div class="card-body p-24" id="ov-site-target"></div>
        </div>
      </div>
    </div>

    <div class="row gy-4 mb-24">
      <div class="col-xxl-8">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
            <div>
              <h6 class="text-lg fw-semibold mb-0">Pemenuhan per Bulan</h6>
              <span class="text-sm text-secondary-light" id="ov-matrix-subtitle">
                Persentase pengawas yang melapor TBC, tiap perusahaan di tiap site
              </span>
            </div>
            <ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 flex-nowrap"
                role="tablist">
              <li class="nav-item" role="presentation">
                <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 active ov-switch__btn"
                        data-mode="persen">Persentase</button>
              </li>
              <li class="nav-item" role="presentation">
                <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 ov-switch__btn"
                        data-mode="nilai">Nilai</button>
              </li>
            </ul>
          </div>
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-3 mb-16" id="ov-legend"></div>
            <div class="ov-matrix-wrap">
              <table class="ov-matrix" id="ov-matrix">
                <thead></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xxl-4">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Perlu Perhatian</h6>
          </div>
          <div class="card-body p-24">
            <div class="table-responsive">
              <table class="table bordered-table sm-table mb-0">
                <thead>
                  <tr>
                    <th scope="col">Site &amp; Perusahaan</th>
                    <th scope="col" class="text-center">Nilai</th>
                    <th scope="col" class="text-end">Tidak Sesuai</th>
                  </tr>
                </thead>
                <tbody id="ov-top5"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row gy-4 mb-24">
      <div class="col-xxl-7">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Rasio per Jabatan Fungsional</h6>
            <span class="text-sm text-secondary-light">Jumlah pengawas yang belum melapor, per jenjang jabatan</span>
          </div>
          <div class="card-body p-24">
            <div id="ov-chart-pareto"></div>
          </div>
        </div>
      </div>

      <div class="col-xxl-5">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Belum Melapor per Perusahaan</h6>
            <span class="text-sm text-secondary-light">Diurutkan dari yang terbanyak</span>
          </div>
          <div class="card-body p-24">
            <div id="ov-chart-area"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row gy-4">
      <div class="col-12">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Tren Bulanan</h6>
            <span class="text-sm text-secondary-light">Persentase pengawas yang melapor TBC per perusahaan</span>
          </div>
          <div class="card-body p-24">
            <div id="ov-chart-monthly"></div>
          </div>
        </div>
      </div>

    </div>

  </div>

  <div class="tab-pane fade" id="jss-pane-rawdata" role="tabpanel">

    <div class="card radius-8 border">
      <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
        <div>
          <h6 class="text-lg fw-semibold mb-0">Data Pelaporan</h6>
          <span class="text-sm text-secondary-light">Satu baris per pengawas per bulan, dari detail_lead_ratio_pelapor_tbc</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="text-sm text-secondary-light" id="rs-hint"></span>
          <button type="button" class="btn btn-sm btn-success-600 radius-8" id="rs-export-xlsx">
            <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
          </button>
          <button type="button" class="btn btn-sm btn-outline-success radius-8" id="rs-export-csv">
            <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
          </button>
        </div>
      </div>

      <div class="card-body p-24">
        <div class="row gy-3 gx-3 align-items-end mb-20">
          <div class="col-xxl-3 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8" for="rs-site">Site</label>
            <select class="form-select form-select-sm radius-8 rs-filter" id="rs-site" data-column="site">
              <option value="">Semua Site</option>
              @foreach ($filterOptions['site'] ?? [] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xxl-3 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8" for="rs-mitra">Perusahaan</label>
            <select class="form-select form-select-sm radius-8 rs-filter" id="rs-mitra" data-column="mitra">
              <option value="">Semua Perusahaan</option>
              @foreach ($filterOptions['mitra'] ?? [] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xxl-2 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8" for="rs-jabatan">Jabatan</label>
            <select class="form-select form-select-sm radius-8 rs-filter" id="rs-jabatan" data-column="jabatan">
              <option value="">Semua Jabatan</option>
              @foreach ($filterOptions['jabatan'] ?? [] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xxl-2 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8" for="rs-month">Bulan</label>
            <select class="form-select form-select-sm radius-8 rs-filter" id="rs-month" data-column="month">
              <option value="">Semua Bulan</option>
              @foreach ($monthOptions as $number => $label)
                <option value="{{ $number }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xxl-1 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8" for="rs-status">Status</label>
            <select class="form-select form-select-sm radius-8 rs-filter" id="rs-status" data-column="status">
              <option value="">Semua</option>
              <option value="melapor">Melapor</option>
              <option value="belum">Belum</option>
            </select>
          </div>
          <div class="col-xxl-1 col-md-4 col-sm-6">
            <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100" id="rs-reset">Reset</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table bordered-table sm-table mb-0" id="ratioTable">
            <thead>
              <tr>
                <th>Site</th>
                <th>Perusahaan</th>
                <th>SID</th>
                <th>Nama Pelapor</th>
                <th>Jabatan</th>
                <th>Bulan</th>
                <th>RFID</th>
                <th>Lapor TBC</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

</div>
@endsection

@section('page-scripts')
<script>
// ---- Tab Overview Dashboard -------------------------------------------------
(function () {
    var overviewUrl = @json(route('ohs-score-card.ratio-tbc-gr.overview'));
    var matrixEl = document.querySelector('#ov-matrix');
    if (!matrixEl) {
        return;
    }

    var filterEls = Array.prototype.slice.call(document.querySelectorAll('.ov-filter'));
    var statusEl = document.querySelector('#ov-status');
    var charts = { monthly: null, pareto: null, area: null };
    var loaded = false;

    // 'persen' atau 'nilai'. Payload terakhir disimpan supaya mengganti mode
    // cukup menggambar ulang matriks, tanpa memanggil server lagi.
    var matrixMode = 'persen';
    var lastPayload = null;

    // Warna seri grafik diambil dari palet WowDash yang sudah dipakai
    // dashboard lain di aplikasi ini, bukan palet baru.
    var PALETTE = ['#487FFF', '#45B369', '#FF9F29', '#EF4A00', '#8252E9', '#00B8F2'];

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmtNum(value) {
        return Number(value || 0).toLocaleString('id-ID');
    }

    function fmtPct(value) {
        return Number(value || 0).toLocaleString('id-ID', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        }) + '%';
    }

    // Gradasi warna untuk mode Persentase: 5 tingkat, lebih halus daripada
    // band Nilai sehingga perbedaan antar bulan lebih mudah terlihat.
    function tierClass(pct) {
        if (pct >= 98) return 'ov-t5';
        if (pct >= 90) return 'ov-t4';
        if (pct >= 78) return 'ov-t3';
        if (pct >= 62) return 'ov-t2';
        return 'ov-t1';
    }

    // Mode Nilai memakai 4 band resmi (SCORE_BANDS), bukan gradasi di atas,
    // supaya warna sel tidak pernah bertentangan dengan angka Nilai-nya.
    function nilaiClass(nilai) {
        return { 1: 'ov-n1', 2: 'ov-n2', 3: 'ov-n3', 4: 'ov-n4' }[nilai] || 'ov-empty';
    }

    function cellClass(cell) {
        return matrixMode === 'nilai' ? nilaiClass(cell.nilai) : tierClass(cell.pct);
    }

    function nilaiColor(nilai) {
        return { 1: '#E0484A', 2: '#F08C2E', 3: '#F2C230', 4: '#16A34A' }[nilai] || '#94A3B8';
    }

    function currentFilters() {
        var out = {};
        filterEls.forEach(function (el) {
            if (el.value) { out[el.dataset.column] = el.value; }
        });
        return out;
    }

    // Kelas badge mengikuti sistem warna WowDash (bg-*-focus + text-*-main),
    // bukan warna inline, supaya ikut tema dan konsisten dengan modul lain.
    function nilaiBadgeClass(nilai) {
        return {
            1: 'bg-danger-focus text-danger-main',
            2: 'bg-warning-focus text-warning-main',
            3: 'bg-info-focus text-info-main',
            4: 'bg-success-focus text-success-main'
        }[nilai] || 'bg-neutral-200 text-secondary-light';
    }

    function nilaiBarClass(nilai) {
        return {
            1: 'bg-danger-main', 2: 'bg-warning-main',
            3: 'bg-info-main', 4: 'bg-success-main'
        }[nilai] || 'bg-neutral-400';
    }

    // ---- Kartu ringkasan utama --------------------------------------------
    function renderKpi(k) {
        var cards = [
            {
                grad: 'bg-gradient-end-1', icon: 'solar:ruler-outline', dot: 'bg-primary-600',
                label: 'Pengawas (RFID)', value: fmtNum(k.total),
                foot: k.site_count + ' site, ' + k.mitra_count + ' perusahaan'
            },
            {
                grad: 'bg-gradient-end-2', icon: 'solar:check-circle-outline', dot: 'bg-success-main',
                label: 'Melapor TBC', value: fmtNum(k.standar),
                foot: fmtPct(k.standar_pct) + ' dari total pengawas'
            },
            {
                grad: 'bg-gradient-end-5', icon: 'solar:danger-triangle-outline', dot: 'bg-danger-main',
                label: 'Belum Melapor', value: fmtNum(k.tidak_sesuai),
                foot: fmtPct(k.tidak_sesuai_pct) + ' dari total pengawas'
            },
            {
                grad: 'bg-gradient-end-3', icon: 'solar:medal-star-outline', dot: 'bg-yellow',
                label: 'Rasio Pelaporan', value: fmtPct(k.standar_pct),
                foot: '<span class="' + nilaiBadgeClass(k.nilai) + ' px-1 rounded-2 fw-medium text-sm">Nilai '
                    + k.nilai + '</span> '
                    + (k.memenuhi_target ? 'Memenuhi' : 'Belum memenuhi') + ' target ' + k.target + '%'
            }
        ];

        document.querySelector('#ov-kpi').innerHTML = cards.map(function (c) {
            return '<div class="col-xxl-3 col-sm-6">'
                + '<div class="card p-3 shadow-2 radius-8 border input-form-light h-100 ' + c.grad + '">'
                +   '<div class="card-body p-0">'
                +     '<div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">'
                +       '<div class="d-flex align-items-center gap-2">'
                +         '<span class="mb-0 w-48-px h-48-px ' + c.dot + ' text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">'
                +           '<iconify-icon icon="' + c.icon + '" class="icon"></iconify-icon>'
                +         '</span>'
                +         '<div>'
                +           '<span class="mb-2 fw-medium text-secondary-light text-sm">' + escapeHtml(c.label) + '</span>'
                +           '<h6 class="fw-semibold">' + c.value + '</h6>'
                +         '</div>'
                +       '</div>'
                +     '</div>'
                +     '<p class="text-sm mb-0">' + c.foot + '</p>'
                +   '</div>'
                + '</div></div>';
        }).join('');
    }

    // ---- Capaian per perusahaan -------------------------------------------
    function renderPerusahaan(list) {
        var host = document.querySelector('#ov-perusahaan');
        if (!list.length) {
            host.innerHTML = '<div class="col-12 text-center text-secondary-light py-24">'
                + 'Tidak ada data untuk filter ini.</div>';
            return;
        }
        host.innerHTML = list.map(function (p) {
            return '<div class="col-xxl-4 col-md-6">'
                + '<div class="border input-form-light radius-8 p-16 h-100">'
                +   '<div class="d-flex align-items-center justify-content-between gap-2 mb-12">'
                +     '<span class="text-md fw-semibold">' + escapeHtml(p.mitra) + '</span>'
                +     '<span class="' + nilaiBadgeClass(p.nilai) + ' px-8 py-2 rounded-pill fw-medium text-xs">Nilai '
                +       p.nilai + '</span>'
                +   '</div>'
                +   '<h6 class="mb-8 fw-semibold">' + fmtPct(p.percent) + '</h6>'
                +   '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px mb-8">'
                +     '<div class="progress-bar ' + nilaiBarClass(p.nilai) + ' rounded-pill" role="progressbar"'
                +       ' style="width:' + Math.min(100, p.percent) + '%" aria-valuenow="' + Math.round(p.percent) + '"'
                +       ' aria-valuemin="0" aria-valuemax="100"></div>'
                +   '</div>'
                +   '<span class="text-sm text-secondary-light">' + fmtNum(p.standar) + ' dari ' + fmtNum(p.total) + ' segmen</span>'
                + '</div></div>';
        }).join('');
    }

    // ---- Capaian per site --------------------------------------------------
    function renderSiteTarget(list) {
        var host = document.querySelector('#ov-site-target');
        if (!list.length) {
            host.innerHTML = '<p class="text-secondary-light text-sm text-center py-24 mb-0">Tidak ada data.</p>';
            return;
        }
        host.innerHTML = list.map(function (s, i) {
            return '<div class="' + (i ? 'mt-20' : '') + '">'
                + '<div class="d-flex align-items-center justify-content-between mb-8">'
                +   '<span class="text-sm fw-semibold">' + escapeHtml(s.site) + '</span>'
                +   '<span class="text-sm fw-medium text-secondary-light">' + fmtPct(s.percent) + '</span>'
                + '</div>'
                + '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px ov-track"'
                +   ' title="Target ' + s.target + '%">'
                +   '<div class="progress-bar ' + nilaiBarClass(s.nilai) + ' rounded-pill" role="progressbar"'
                +     ' style="width:' + Math.min(100, s.percent) + '%" aria-valuenow="' + Math.round(s.percent) + '"'
                +     ' aria-valuemin="0" aria-valuemax="100"></div>'
                +   '<span class="ov-track__target" style="left:' + s.target + '%"></span>'
                + '</div>'
                + '<span class="text-xs text-secondary-light">' + fmtNum(s.tidak_sesuai)
                +   ' pengawas belum melapor, target ' + s.target + '%</span>'
                + '</div>';
        }).join('');
    }

    // ---- Perlu perhatian ---------------------------------------------------
    function renderTop5(list) {
        var body = document.querySelector('#ov-top5');
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>';
            return;
        }
        body.innerHTML = list.map(function (t) {
            return '<tr>'
                + '<td>'
                +   '<span class="text-md fw-semibold d-block">' + escapeHtml(t.site) + '</span>'
                +   '<span class="text-sm text-secondary-light">' + escapeHtml(t.mitra) + ', ' + fmtPct(t.percent) + '</span>'
                + '</td>'
                + '<td class="text-center"><span class="' + nilaiBadgeClass(t.nilai)
                +   ' px-8 py-2 rounded-pill fw-medium text-xs">' + t.nilai + '</span></td>'
                + '<td class="text-end fw-medium">' + fmtNum(t.tidak_sesuai) + '</td>'
                + '</tr>';
        }).join('');
    }

    /** Legenda ikut mode: gradasi persentase, atau 4 band Nilai. */
    function renderLegend() {
        var host = document.querySelector('#ov-legend');
        var items = matrixMode === 'nilai'
            ? [
                { color: '#E0484A', label: 'Nilai 1 · <80%' },
                { color: '#F08C2E', label: 'Nilai 2 · 80–90%' },
                { color: '#F2C230', label: 'Nilai 3 · 90–98%' },
                { color: '#16A34A', label: 'Nilai 4 · 98–100%' }
            ]
            : [
                { color: '#E0484A', label: '<62%' },
                { color: '#F08C2E', label: '62–78%' },
                { color: '#F2C230', label: '78–90%' },
                { color: '#86C96B', label: '90–98%' },
                { color: '#059669', label: '≥98%' }
            ];

        host.innerHTML = items.map(function (it) {
            return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                + '<span class="rounded-1" style="width:14px;height:14px;background:' + it.color + ';"></span>'
                + escapeHtml(it.label) + '</span>';
        }).join('');

        document.querySelector('#ov-matrix-subtitle').textContent = matrixMode === 'nilai'
            ? 'Nilai 1–4 dari persentase segmen standar tiap perusahaan di tiap site'
            : 'Persentase pengawas yang melapor TBC, tiap perusahaan di tiap site';
    }

    function renderMatrix(months, rows) {
        var thead = matrixEl.querySelector('thead');
        var tbody = matrixEl.querySelector('tbody');

        var head = '<tr><th class="ov-site">SITE</th><th class="ov-mitra">PERUSAHAAN</th>'
            + '<th>AVG</th><th>TREND</th>';
        months.forEach(function (m, i) {
            head += '<th class="' + (i === months.length - 1 ? 'ov-th-last' : '') + '">' + escapeHtml(m.label) + '</th>';
        });
        thead.innerHTML = head + '</tr>';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="' + (months.length + 4) + '" class="text-center py-24 text-secondary-light">'
                + 'Tidak ada data untuk filter ini.</td></tr>';
            return;
        }

        // Site digabung dengan rowspan: satu sel untuk semua perusahaan di site
        // yang sama. Baris sudah terurut per site dari server, jadi cukup
        // menghitung panjang blok berurutan.
        var siteSpan = {};   // index baris pertama tiap blok -> jumlah baris
        var siteSkip = {};   // index baris yang tidak menulis sel site
        rows.forEach(function (row, i) {
            if (i > 0 && rows[i - 1].site === row.site) {
                siteSkip[i] = true;
                return;
            }
            var n = 1;
            while (i + n < rows.length && rows[i + n].site === row.site) { n++; }
            siteSpan[i] = n;
        });

        tbody.innerHTML = rows.map(function (row, i) {
            var html = '<tr>';

            if (!siteSkip[i]) {
                html += '<td class="ov-site" rowspan="' + siteSpan[i] + '">'
                     + escapeHtml(row.site) + '</td>';
            }

            html += '<td class="ov-mitra">' + escapeHtml(row.mitra) + '</td>'
                + '<td class="ov-avg" title="' + fmtNum(row.total) + ' segmen · '
                +   fmtPct(row.average) + ' · Nilai ' + row.nilai + ' (' + escapeHtml(row.nilai_band) + ')">'
                +   (matrixMode === 'nilai' ? row.nilai : fmtPct(row.average)) + '</td>';

            if (row.trend === 'up') {
                html += '<td class="ov-trend--up" title="Naik dari bulan sebelumnya">&uarr;</td>';
            } else if (row.trend === 'down') {
                html += '<td class="ov-trend--down" title="Turun dari bulan sebelumnya">&darr;</td>';
            } else {
                html += '<td class="text-secondary-light">–</td>';
            }

            // Variabel sengaja dinamai m, bukan i: i di luar sudah dipakai
            // sebagai index baris untuk perhitungan rowspan site.
            row.cells.forEach(function (cell, m) {
                if (cell === null) {
                    html += '<td class="ov-empty" title="' + escapeHtml(months[m].label) + ': tidak ada data">–</td>';
                    return;
                }

                // Tooltip selalu memuat kedua angka, apa pun mode tampilannya,
                // supaya berganti mode tidak menghilangkan informasi.
                var tip = row.site + ' · ' + row.mitra + ' · ' + months[m].label + ': '
                    + fmtNum(cell.standar) + ' / ' + fmtNum(cell.total) + ' segmen standar · '
                    + fmtPct(cell.pct) + ' · Nilai ' + cell.nilai + ' (' + cell.nilai_band + ')';

                html += '<td class="ov-cell ' + cellClass(cell) + '"'
                    + ' title="' + escapeHtml(tip) + '">'
                    + (matrixMode === 'nilai' ? cell.nilai : Math.round(cell.pct) + '%')
                    + '</td>';
            });

            return html + '</tr>';
        }).join('');
    }

    // ---- Grafik ------------------------------------------------------------
    function destroyChart(key) {
        if (charts[key]) {
            charts[key].destroy();
            charts[key] = null;
        }
    }

    function emptyChart(el, text) {
        el.innerHTML = '<p class="text-secondary-light text-sm text-center py-40 mb-0">'
            + escapeHtml(text || 'Tidak ada data.') + '</p>';
    }

    function renderChart(key, elId, payload, type) {
        if (typeof ApexCharts === 'undefined') { return; }

        var el = document.querySelector(elId);
        if (!el) { return; }

        destroyChart(key);

        if (!payload.series.length) { emptyChart(el); return; }
        el.innerHTML = '';

        // "Padat" = banyak titik di sumbu X (mis. 40 minggu). Dipakai untuk
        // menipiskan garis, menyembunyikan marker, dan menjarangkan label.
        var dense = payload.labels.length > 15;

        charts[key] = new ApexCharts(el, {
            series: payload.series,
            chart: { type: type, height: 280, toolbar: { show: false }, zoom: { enabled: false } },
            colors: PALETTE,
            stroke: { curve: 'smooth', width: type === 'line' ? (dense ? 2 : 3) : 0 },
            markers: { size: type === 'line' && !dense ? 4 : 0, hover: { size: 5 } },
            dataLabels: { enabled: false },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
            xaxis: {
                categories: payload.labels,
                labels: {
                    style: { fontSize: '11px' },
                    hideOverlappingLabels: true,
                    rotate: dense ? -45 : 0,
                    rotateAlways: false
                },
                tickAmount: dense ? 12 : undefined
            },
            yaxis: {
                min: 0, max: 100,
                labels: { formatter: function (v) { return Math.round(v) + '%'; } }
            },
            legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
            grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
            tooltip: {
                shared: true,
                // WAJIB eksplisit: untuk tipe 'bar' ApexCharts memasang
                // intersect: true sebagai bawaan, dan kombinasi
                // shared + intersect melempar error sehingga grafiknya
                // gagal dirender sama sekali.
                intersect: false,
                y: { formatter: function (v) { return v === null ? 'tidak ada data' : fmtPct(v); } }
            }
        });
        charts[key].render();
    }

    /** Pareto: batang jumlah + garis persentase kumulatif pada sumbu kedua. */
    function renderPareto(rows) {
        var el = document.querySelector('#ov-chart-pareto');
        if (!el || typeof ApexCharts === 'undefined') { return; }

        destroyChart('pareto');

        if (!rows.length) { emptyChart(el); return; }
        el.innerHTML = '';

        charts.pareto = new ApexCharts(el, {
            series: [
                { name: 'Belum Melapor', type: 'column', data: rows.map(function (r) { return r.jumlah; }) },
                { name: 'Rasio Pelaporan', type: 'line', data: rows.map(function (r) { return r.rasio; }) }
            ],
            chart: { type: 'line', height: 300, toolbar: { show: false }, zoom: { enabled: false } },
            colors: ['#E0484A', '#16A34A'],
            stroke: { width: [0, 3], curve: 'smooth' },
            markers: { size: [0, 4] },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
            dataLabels: {
                enabled: true,
                enabledOnSeries: [0],
                formatter: function (v) { return fmtNum(v); },
                style: { fontSize: '10px', colors: ['#334155'] },
                offsetY: -18
            },
            xaxis: { categories: rows.map(function (r) { return r.label; }), labels: { style: { fontSize: '11px' } } },
            yaxis: [
                { title: { text: 'Pengawas', style: { fontSize: '11px' } },
                  labels: { formatter: function (v) { return fmtNum(Math.round(v)); } } },
                { opposite: true, min: 0, max: 100,
                  title: { text: 'Rasio', style: { fontSize: '11px' } },
                  labels: { formatter: function (v) { return Math.round(v) + '%'; } } }
            ],
            legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
            grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
            tooltip: {
                shared: true, intersect: false,
                y: {
                    formatter: function (v, opts) {
                        return opts.seriesIndex === 1 ? Number(v).toFixed(2) + '%' : fmtNum(v) + ' pengawas';
                    }
                }
            }
        });
        charts.pareto.render();
    }

    /** Donut untuk panel Belum Dilaporkan per Area. */
    function renderDonut(key, elId, labels, values, colors, unit) {
        var el = document.querySelector(elId);
        if (!el || typeof ApexCharts === 'undefined') { return; }

        destroyChart(key);

        var total = values.reduce(function (a, b) { return a + b; }, 0);
        if (!values.length || total === 0) { emptyChart(el); return; }
        el.innerHTML = '';

        charts[key] = new ApexCharts(el, {
            series: values,
            labels: labels,
            colors: colors || PALETTE,
            chart: { type: 'donut', height: 300 },
            dataLabels: { enabled: false },
            stroke: { width: 0 },
            legend: { position: 'bottom', fontSize: '11px', itemMargin: { vertical: 2 } },
            plotOptions: {
                pie: {
                    donut: {
                        size: '62%',
                        labels: {
                            show: true,
                            total: {
                                show: true, showAlways: true, label: unit || 'Total',
                                formatter: function () { return fmtNum(total); }
                            }
                        }
                    }
                }
            },
            tooltip: { y: { formatter: function (v) { return fmtNum(v) + ' (' + (v / total * 100).toFixed(1) + '%)'; } } }
        });
        charts[key].render();
    }

    /** Membungkus renderer agar kegagalan satu panel tidak menjatuhkan sisanya. */
    function safe(label, fn) {
        try {
            fn();
        } catch (err) {
            if (typeof console !== 'undefined' && console.error) {
                console.error('Overview: panel "' + label + '" gagal dirender', err);
            }
        }
    }

    function load() {
        var params = new URLSearchParams(currentFilters());
        statusEl.textContent = 'memuat…';

        fetch(overviewUrl + (params.toString() ? '?' + params.toString() : ''), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.json();
            })
            .then(function (json) {
                lastPayload = json;

                // Tiap panel dibungkus sendiri: satu panel yang gagal tidak
                // boleh membuat seluruh dashboard tampak kosong.
                safe('kpi', function () { renderKpi(json.kpi); });
                safe('perusahaan', function () { renderPerusahaan(json.perusahaan || []); });
                safe('site-target', function () { renderSiteTarget(json.site_vs_target || []); });
                safe('top5', function () { renderTop5(json.top_terendah || []); });
                safe('legend', renderLegend);
                safe('matrix', function () { renderMatrix(json.months || [], json.matrix || []); });
                safe('pareto', function () { renderPareto(json.pareto || []); });
                safe('area', function () {
                    var area = json.per_area || [];
                    renderDonut('area', '#ov-chart-area',
                        area.map(function (a) { return a.area; }),
                        area.map(function (a) { return a.tidak_sesuai; }),
                        null, 'Segmen');
                });
                safe('monthly', function () { renderChart('monthly', '#ov-chart-monthly', json.monthly, 'line'); });

                statusEl.textContent = fmtNum(json.kpi.total) + ' pengawas · '
                    + (json.matrix || []).length + ' kombinasi site/perusahaan';
                loaded = true;
            })
            .catch(function (err) {
                statusEl.textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Overview Ratio TBC & GR: gagal memuat', err);
                }
            });
    }

    filterEls.forEach(function (el) {
        el.addEventListener('change', load);
    });

    // Ganti mode hanya menggambar ulang dari payload terakhir, tidak ada
    // permintaan baru ke server, karena angka Nilai sudah ikut dikirim.
    document.querySelectorAll('.ov-switch__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.mode === matrixMode) { return; }

            matrixMode = btn.dataset.mode;

            document.querySelectorAll('.ov-switch__btn').forEach(function (b) {
                // Kelas aktifnya 'active' (bawaan nav-pills WowDash),
                // bukan kelas buatan sendiri.
                b.classList.toggle('active', b.dataset.mode === matrixMode);
            });

            renderLegend();

            if (lastPayload) {
                renderMatrix(lastPayload.months || [], lastPayload.matrix || []);
            }
        });
    });

    document.querySelector('#ov-reset').addEventListener('click', function () {
        filterEls.forEach(function (el) { el.value = ''; });
        load();
    });

    // Tab Overview aktif sejak awal, jadi langsung dimuat.
    load();

    var overviewTab = document.querySelector('#jss-tab-overview');
    if (overviewTab) {
        overviewTab.addEventListener('shown.bs.tab', function () {
            if (!loaded) { load(); return; }
            // ApexCharts tidak bisa mengukur elemen yang sedang tersembunyi,
            // jadi ukurannya dihitung ulang saat tab kembali tampil.
            Object.keys(charts).forEach(function (k) {
                if (charts[k] && typeof charts[k].windowResizeHandler === 'function') {
                    charts[k].windowResizeHandler();
                }
            });
        });
    }
})();
</script>
<script>
// ---- Tab Data Pelaporan -----------------------------------------------------
(function () {
    var tableEl = document.querySelector('#ratioTable');
    if (!tableEl || typeof DataTable === 'undefined') {
        return;
    }
    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = @json(route('ohs-score-card.ratio-tbc-gr.data'));
    var exportUrl = @json(route('ohs-score-card.ratio-tbc-gr.export'));
    var filterEls = Array.prototype.slice.call(document.querySelectorAll('.rs-filter'));
    var hintEl = document.querySelector('#rs-hint');

    function fmtNum(value) {
        return Number(value || 0).toLocaleString('id-ID');
    }

    function currentFilters() {
        var out = {};
        filterEls.forEach(function (el) {
            if (el.value) { out[el.dataset.column] = el.value; }
        });
        return out;
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    var table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        order: [[0, 'asc']],
        pageLength: 25,
        lengthMenu: [25, 50, 100, 200],
        autoWidth: false,
        layout: {
            topStart: 'pageLength',
            topEnd: 'search',
            bottomStart: 'info',
            bottomEnd: 'paging'
        },
        ajax: {
            url: dataUrl,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            data: function (d) {
                var filters = currentFilters();
                Object.keys(filters).forEach(function (key) { d[key] = filters[key]; });
            },
            dataSrc: function (json) {
                hintEl.textContent = fmtNum(json.recordsFiltered) + ' baris';
                return json.data || [];
            },
            error: function (xhr, error) {
                hintEl.textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Ratio TBC & GR: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'site' },
            { data: 'mitra' },
            { data: 'sid' },
            {
                data: 'nama',
                render: function (d, type, row) {
                    if (type !== 'display') { return d; }
                    var sub = row.jabatan_struktural || '';
                    var off = row.offsite
                        ? ' <span class="bg-neutral-200 text-secondary-light px-8 py-2 rounded-pill text-xs">'
                            + escapeHtml(row.offsite) + '</span>'
                        : '';
                    return '<span class="fw-semibold">' + escapeHtml(d || '-') + '</span>' + off
                        + (sub ? '<span class="d-block text-xs text-secondary-light">'
                            + escapeHtml(sub) + '</span>' : '');
                }
            },
            { data: 'jabatan' },
            { data: 'bulan', orderable: false },
            { data: 'rfid', className: 'text-center' },
            { data: 'tbc', className: 'text-center' },
            {
                data: 'status',
                className: 'text-center',
                orderable: false,
                render: function (d, type) {
                    if (type !== 'display') { return d; }
                    var cls = d === 'Melapor'
                        ? 'bg-success-focus text-success-main'
                        : 'bg-danger-focus text-danger-main';
                    return '<span class="' + cls + ' px-8 py-2 rounded-pill fw-medium text-xs">' + d + '</span>';
                }
            }
        ],
        language: {
            processing: 'Memuat...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(difilter dari _MAX_ total baris)',
            zeroRecords: 'Tidak ada baris untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    filterEls.forEach(function (el) {
        el.addEventListener('change', function () { table.ajax.reload(); });
    });

    document.querySelector('#rs-reset').addEventListener('click', function () {
        filterEls.forEach(function (el) { el.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    function unduh(format) {
        var params = new URLSearchParams(currentFilters());
        var search = table.search();
        if (search) { params.set('search', search); }
        params.set('format', format);
        window.location.href = exportUrl + '?' + params.toString();
    }

    document.querySelector('#rs-export-xlsx').addEventListener('click', function () { unduh('xlsx'); });
    document.querySelector('#rs-export-csv').addEventListener('click', function () { unduh('csv'); });

    // Tabel dibangun saat pane-nya masih tersembunyi, jadi lebar kolom
    // dihitung ulang begitu tab Data pertama kali dibuka.
    var dataTab = document.querySelector('#jss-tab-rawdata');
    if (dataTab) {
        dataTab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        });
    }
})();
</script>
@endsection
