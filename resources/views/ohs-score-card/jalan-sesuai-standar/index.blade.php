@extends('ohs-score-card.layouts.app')

@section('title', 'Jalan Sesuai Standar')

@php
  /** Label dropdown filter: key = nama kolom di road_summary. */
  $filterLabels = [
      'site' => 'Site',
      'mitra' => 'Mitra',
      'pit' => 'Pit',
      'year' => 'Tahun',
      'week' => 'Minggu',
      'grade_stat' => 'Grade',
      'road_width' => 'Lebar Jalan',
      'supereleva' => 'Superelevasi',
  ];
@endphp

@section('css')
<style>
  .rs-stat {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    background: #fff;
    padding: 16px 18px;
    height: 100%;
  }
  .rs-stat__label {
    font-size: 12px;
    font-weight: 500;
    color: #64748B;
    line-height: 1.25;
  }
  .rs-stat__value {
    font-size: 24px;
    font-weight: 800;
    color: #0F172A;
    line-height: 1.2;
    letter-spacing: -0.01em;
  }
  .rs-stat__meta {
    font-size: 12px;
    color: #64748B;
  }
  .rs-stat__icon {
    width: 42px;
    height: 42px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
  }

  /* Badge status: ACCEPT hijau, REJECT/>12% merah, WARNING kuning, OVERGRADE oranye. */
  .rs-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.5;
    white-space: nowrap;
  }
  .rs-badge--ok     { background: #ECFDF5; color: #166534; border: 1px solid #BBF7D0; }
  .rs-badge--warn   { background: #FEFCE8; color: #854D0E; border: 1px solid #FDE68A; }
  .rs-badge--over   { background: #FFF7ED; color: #9A3412; border: 1px solid #FED7AA; }
  .rs-badge--bad    { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
  .rs-badge--muted  { background: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; }

  /* Kesimpulan dibuat lebih tegas daripada badge status per-parameter. */
  .rs-verdict {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.01em;
    white-space: nowrap;
  }
  .rs-verdict--ok  { background: #16A34A; color: #fff; }
  .rs-verdict--bad { background: #E0484A; color: #fff; }

  #roadSummaryTable { width: 100% !important; }
  #roadSummaryTable th,
  #roadSummaryTable td { vertical-align: middle; white-space: nowrap; }
  #roadSummaryTable thead th { font-weight: 600; }

  #roadSummaryTable_wrapper .dt-layout-row,
  .dt-container:has(#roadSummaryTable) .dt-layout-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0.75rem 0;
  }
  #roadSummaryTable_wrapper .dt-paging,
  .dt-container:has(#roadSummaryTable) .dt-paging {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.375rem;
  }
  #roadSummaryTable_wrapper .dt-paging .dt-paging-button,
  .dt-container:has(#roadSummaryTable) .dt-paging .dt-paging-button {
    width: auto !important;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.625rem !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    line-height: 1 !important;
    border-radius: 6px !important;
  }
  #roadSummaryTable_wrapper .dt-search input,
  .dt-container:has(#roadSummaryTable) .dt-search input {
    margin-left: 0.5rem;
    min-width: 220px;
    width: auto;
    display: inline-block;
  }
  #roadSummaryTable_wrapper .dt-length select,
  .dt-container:has(#roadSummaryTable) .dt-length select {
    margin: 0 0.375rem;
    width: auto;
    display: inline-block;
  }
  #roadSummaryTable_wrapper .dt-length label,
  #roadSummaryTable_wrapper .dt-search label,
  .dt-container:has(#roadSummaryTable) .dt-length label,
  .dt-container:has(#roadSummaryTable) .dt-search label {
    display: inline-flex;
    align-items: center;
    margin-bottom: 0;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-secondary-light);
  }
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

  /* ---- Kartu ringkasan perusahaan ---- */
  .ov-card {
    border: 1px solid #E2E8F0; border-radius: 14px;
    background: #fff; padding: 16px 18px; height: 100%;
  }
  .ov-card__name { font-size: 13px; font-weight: 700; color: #0F172A; }
  .ov-card__pct { font-size: 24px; font-weight: 800; line-height: 1.2; letter-spacing: -0.01em; }
  .ov-card__meta { font-size: 12px; color: #64748B; }
  .ov-card__nilai {
    font-size: 11px; font-weight: 800; padding: 2px 10px;
    border-radius: 999px; color: #fff;
  }

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Jalan Sesuai Standar</h6>
    <div class="text-secondary-light text-sm mt-4">
      Evaluasi per segmen jalan — grade, lebar, dan superelevasi
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
    <li class="fw-medium text-primary-600">Jalan Sesuai Standar</li>
  </ul>
</div>

{{-- Ringkasan kepatuhan; ikut filter yang sedang aktif (di-update dari respons AJAX). --}}
<div class="card radius-8 border mb-24">
  <div class="card-body p-24 pb-0">
    <ul class="nav jss-tabs gap-2" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="jss-tab-overview" data-bs-toggle="tab"
                data-bs-target="#jss-pane-overview" data-tab-key="overview" type="button" role="tab">
          <iconify-icon icon="solar:chart-square-outline"></iconify-icon> Overview Dashboard
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="jss-tab-rawdata" data-bs-toggle="tab"
                data-bs-target="#jss-pane-rawdata" data-tab-key="rawdata" type="button" role="tab">
          <iconify-icon icon="solar:list-outline"></iconify-icon> Raw Data
        </button>
      </li>
    </ul>
  </div>
</div>

<div class="tab-content">
  <div class="tab-pane fade show active" id="jss-pane-overview" role="tabpanel">

    {{-- Filter khusus Overview: terpisah dari filter Raw Data supaya keduanya
         tidak saling mengubah tampilan satu sama lain. --}}
    <div class="card radius-8 border mb-24">
      <div class="card-body p-24">
        <div class="row gy-3 gx-3 align-items-end">
          <div class="col-xxl-3 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-4" for="ov-site">Site</label>
            <select class="form-select form-select-sm radius-8 ov-filter" id="ov-site" data-column="site">
              <option value="">Semua Site</option>
              @foreach ($filterOptions['site'] ?? [] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xxl-3 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-4" for="ov-mitra">Perusahaan</label>
            <select class="form-select form-select-sm radius-8 ov-filter" id="ov-mitra" data-column="mitra">
              <option value="">Semua Perusahaan</option>
              @foreach ($filterOptions['mitra'] ?? [] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xxl-3 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-4" for="ov-month">Bulan</label>
            <select class="form-select form-select-sm radius-8 ov-filter" id="ov-month" data-column="month">
              <option value="">Semua Bulan</option>
              @foreach ($monthOptions as $number => $label)
                <option value="{{ $number }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xxl-3 col-md-4 col-sm-6 d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary radius-8" id="ov-reset">Reset</button>
            <span class="text-sm text-secondary-light" id="ov-status"></span>
          </div>
        </div>
      </div>
    </div>

    {{-- Ringkasan per perusahaan --}}
    <div class="row gy-3 mb-24" id="ov-perusahaan"></div>

    {{-- Matriks site x perusahaan x bulan --}}
    <div class="card radius-8 border mb-24">
      <div class="card-body p-24">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-16">
          <div>
            <h6 class="mb-1 fw-bold text-lg">Capaian per Bulan</h6>
            <span class="text-sm fw-medium text-secondary-light">
              Persentase segmen standar tiap perusahaan di tiap site
            </span>
          </div>
          <div class="d-flex align-items-center flex-wrap gap-3">
            <span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;"><span class="rounded-1" style="width:14px;height:14px;background:#E0484A;"></span>&lt;62%</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;"><span class="rounded-1" style="width:14px;height:14px;background:#F08C2E;"></span>62–78%</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;"><span class="rounded-1" style="width:14px;height:14px;background:#F2C230;"></span>78–90%</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;"><span class="rounded-1" style="width:14px;height:14px;background:#86C96B;"></span>90–98%</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;"><span class="rounded-1" style="width:14px;height:14px;background:#059669;"></span>&ge;98%</span>
          </div>
        </div>
        <div class="ov-matrix-wrap">
          <table class="ov-matrix" id="ov-matrix">
            <thead></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Grafik perbandingan --}}
    <div class="row gy-4">
      <div class="col-xxl-6">
        <div class="card radius-8 border h-100">
          <div class="card-body p-24">
            <h6 class="mb-1 fw-bold text-lg">Perbandingan Bulanan</h6>
            <span class="text-sm fw-medium text-secondary-light">% segmen standar per perusahaan</span>
            <div id="ov-chart-monthly" class="mt-16"></div>
          </div>
        </div>
      </div>
      <div class="col-xxl-6">
        <div class="card radius-8 border h-100">
          <div class="card-body p-24">
            <h6 class="mb-1 fw-bold text-lg">Perbandingan Mingguan</h6>
            <span class="text-sm fw-medium text-secondary-light">% segmen standar per perusahaan</span>
            <div id="ov-chart-weekly" class="mt-16"></div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <div class="tab-pane fade" id="jss-pane-rawdata" role="tabpanel">
      <div class="row gy-3 mb-24">
        <div class="col-xxl col-lg-4 col-sm-6">
          <div class="rs-stat d-flex align-items-center gap-3">
            <span class="rs-stat__icon" style="background:#EFF6FF;color:#2563EB;">
              <iconify-icon icon="solar:ruler-outline"></iconify-icon>
            </span>
            <div class="min-w-0">
              <div class="rs-stat__label">Total Segmen</div>
              <div class="rs-stat__value" id="rs-total">{{ number_format($totalSegments) }}</div>
              <div class="rs-stat__meta" id="rs-total-meta">seluruh data</div>
            </div>
          </div>
        </div>
        <div class="col-xxl col-lg-4 col-sm-6">
          <div class="rs-stat d-flex align-items-center gap-3" style="border-color:#BBF7D0;background:#F7FEF9;">
            <span class="rs-stat__icon" style="background:#16A34A;color:#fff;">
              <iconify-icon icon="solar:check-circle-outline"></iconify-icon>
            </span>
            <div class="min-w-0">
              <div class="rs-stat__label">Standar</div>
              <div class="rs-stat__value" id="rs-standar">–</div>
              <div class="rs-stat__meta" id="rs-standar-meta">&nbsp;</div>
            </div>
          </div>
        </div>
        <div class="col-xxl col-lg-4 col-sm-6">
          <div class="rs-stat d-flex align-items-center gap-3" id="rs-nilai-card">
            <span class="rs-stat__icon" id="rs-nilai-icon" style="background:#F1F5F9;color:#64748B;">
              <iconify-icon icon="solar:medal-star-outline"></iconify-icon>
            </span>
            <div class="min-w-0">
              <div class="rs-stat__label">Nilai</div>
              <div class="rs-stat__value" id="rs-nilai">–</div>
              <div class="rs-stat__meta" id="rs-nilai-meta">dari % standar</div>
            </div>
          </div>
        </div>
        <div class="col-xxl col-lg-4 col-sm-6">
          <div class="rs-stat d-flex align-items-center gap-3">
            <span class="rs-stat__icon" style="background:#ECFDF5;color:#16A34A;">
              <iconify-icon icon="solar:graph-up-outline"></iconify-icon>
            </span>
            <div class="min-w-0">
              <div class="rs-stat__label">Grade ACCEPT</div>
              <div class="rs-stat__value" id="rs-grade">–</div>
              <div class="rs-stat__meta" id="rs-grade-meta">&nbsp;</div>
            </div>
          </div>
        </div>
        <div class="col-xxl col-lg-4 col-sm-6">
          <div class="rs-stat d-flex align-items-center gap-3">
            <span class="rs-stat__icon" style="background:#ECFDF5;color:#16A34A;">
              <iconify-icon icon="solar:arrows-horizontal-outline"></iconify-icon>
            </span>
            <div class="min-w-0">
              <div class="rs-stat__label">Lebar Jalan ACCEPT</div>
              <div class="rs-stat__value" id="rs-width">–</div>
              <div class="rs-stat__meta" id="rs-width-meta">&nbsp;</div>
            </div>
          </div>
        </div>
        <div class="col-xxl col-lg-4 col-sm-6">
          <div class="rs-stat d-flex align-items-center gap-3">
            <span class="rs-stat__icon" style="background:#ECFDF5;color:#16A34A;">
              <iconify-icon icon="solar:slider-horizontal-outline"></iconify-icon>
            </span>
            <div class="min-w-0">
              <div class="rs-stat__label">Superelevasi ACCEPT</div>
              <div class="rs-stat__value" id="rs-super">–</div>
              <div class="rs-stat__meta" id="rs-super-meta">&nbsp;</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card radius-8 border">
        <div class="card-body p-24">

          <div class="row gy-3 gx-3 align-items-end mb-20">
            @foreach ($filterLabels as $column => $label)
              <div class="col-xxl-3 col-md-4 col-sm-6">
                <label class="form-label text-sm fw-medium mb-4" for="rs-filter-{{ $column }}">{{ $label }}</label>
                <select class="form-select form-select-sm radius-8 rs-filter" id="rs-filter-{{ $column }}" data-column="{{ $column }}">
                  <option value="">Semua</option>
                  @foreach ($filterOptions[$column] ?? [] as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                  @endforeach
                </select>
              </div>
            @endforeach

            <div class="col-xxl-3 col-md-4 col-sm-6">
              <label class="form-label text-sm fw-medium mb-4" for="rs-filter-month">Bulan</label>
              <select class="form-select form-select-sm radius-8 rs-filter" id="rs-filter-month" data-column="month">
                <option value="">Semua</option>
                @foreach ($monthOptions as $number => $label)
                  <option value="{{ $number }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-xxl-3 col-md-4 col-sm-6">
              <label class="form-label text-sm fw-medium mb-4" for="rs-filter-kesimpulan">Kesimpulan</label>
              <select class="form-select form-select-sm radius-8 rs-filter" id="rs-filter-kesimpulan" data-column="kesimpulan">
                <option value="">Semua</option>
                <option value="standar">Standar</option>
                <option value="tidak-standar">Tidak Standar</option>
              </select>
            </div>

            <div class="col-xxl-12 d-flex align-items-center gap-2">
              <button type="button" class="btn btn-sm btn-primary-600 radius-8" id="rs-apply">
                <iconify-icon icon="solar:filter-outline" class="icon"></iconify-icon> Terapkan
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary radius-8" id="rs-reset">
                Reset
              </button>

              <div class="ms-auto d-flex align-items-center gap-2">
                <span class="text-sm text-secondary-light" id="rs-export-hint"></span>
                <button type="button" class="btn btn-sm btn-success-600 radius-8" id="rs-export-xlsx">
                  <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Excel
                </button>
                <button type="button" class="btn btn-sm btn-outline-success radius-8" id="rs-export-csv">
                  <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
                </button>
              </div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table bordered-table mb-0" id="roadSummaryTable">
              <thead>
                <tr>
                  <th>Site</th>
                  <th>Pit</th>
                  <th>Mitra</th>
                  <th>Tahun</th>
                  <th>Minggu</th>
                  <th>Bulan</th>
                  <th>Nama Jalan</th>
                  <th>Segmen</th>
                  <th>Grade</th>
                  <th>Lebar Jalan</th>
                  <th>Superelevasi</th>
                  <th>Junction 1</th>
                  <th>Junction S</th>
                  <th>Kesimpulan</th>
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
    var overviewUrl = @json(route('ohs-score-card.jalan-sesuai-standar.overview'));
    var matrixEl = document.querySelector('#ov-matrix');
    if (!matrixEl) {
        return;
    }

    var filterEls = Array.prototype.slice.call(document.querySelectorAll('.ov-filter'));
    var statusEl = document.querySelector('#ov-status');
    var charts = { monthly: null, weekly: null };
    var loaded = false;

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

    // Ambang warna sama dengan konstanta SCORE_BANDS + pewarnaan di controller.
    function tierClass(pct) {
        if (pct >= 98) return 'ov-t5';
        if (pct >= 90) return 'ov-t4';
        if (pct >= 78) return 'ov-t3';
        if (pct >= 62) return 'ov-t2';
        return 'ov-t1';
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

    function renderPerusahaan(list) {
        var host = document.querySelector('#ov-perusahaan');
        if (!list.length) {
            host.innerHTML = '<div class="col-12"><div class="ov-card text-center text-secondary-light">'
                + 'Tidak ada data untuk filter ini.</div></div>';
            return;
        }
        host.innerHTML = list.map(function (p) {
            return '<div class="col-xxl col-lg-3 col-sm-6">'
                + '<div class="ov-card">'
                +   '<div class="d-flex align-items-center justify-content-between gap-2 mb-8">'
                +     '<span class="ov-card__name">' + escapeHtml(p.mitra) + '</span>'
                +     '<span class="ov-card__nilai" style="background:' + nilaiColor(p.nilai) + ';">Nilai ' + p.nilai + '</span>'
                +   '</div>'
                +   '<div class="ov-card__pct" style="color:' + nilaiColor(p.nilai) + ';">' + fmtPct(p.percent) + '</div>'
                +   '<div class="ov-card__meta">' + fmtNum(p.standar) + ' / ' + fmtNum(p.total) + ' segmen</div>'
                + '</div></div>';
        }).join('');
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
                + '<td class="ov-avg" title="' + fmtNum(row.total) + ' segmen · Nilai ' + row.nilai + '">'
                +   fmtPct(row.average) + '</td>';

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
                html += '<td class="ov-cell ' + tierClass(cell.pct) + '"'
                    + ' title="' + escapeHtml(row.site + ' · ' + row.mitra + ' · ' + months[m].label) + ': '
                    + fmtNum(cell.standar) + ' / ' + fmtNum(cell.total) + ' segmen standar">'
                    + Math.round(cell.pct) + '%</td>';
            });

            return html + '</tr>';
        }).join('');
    }

    function renderChart(key, elId, payload, type) {
        if (typeof ApexCharts === 'undefined') { return; }

        var el = document.querySelector(elId);
        if (!el) { return; }

        if (charts[key]) {
            charts[key].destroy();
            charts[key] = null;
        }

        if (!payload.series.length) {
            el.innerHTML = '<p class="text-secondary-light text-sm text-center py-40 mb-0">Tidak ada data.</p>';
            return;
        }
        el.innerHTML = '';

        charts[key] = new ApexCharts(el, {
            series: payload.series,
            chart: { type: type, height: 320, toolbar: { show: false }, zoom: { enabled: false } },
            colors: ['#487FFF', '#45B369', '#F08C2E', '#E0484A', '#8252E9', '#00B8F2'],
            stroke: { curve: 'smooth', width: type === 'line' ? 3 : 0 },
            markers: { size: type === 'line' ? 4 : 0 },
            dataLabels: { enabled: false },
            // connectNulls false: bulan/minggu tanpa data memang harus putus,
            // bukan ditarik lurus seolah ada capaian di antaranya.
            plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
            xaxis: { categories: payload.labels, labels: { style: { fontSize: '11px' } } },
            yaxis: {
                min: 0, max: 100,
                labels: { formatter: function (v) { return Math.round(v) + '%'; } }
            },
            legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
            grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
            tooltip: {
                shared: true,
                y: { formatter: function (v) { return v === null ? 'tidak ada data' : fmtPct(v); } }
            }
        });
        charts[key].render();
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
                renderPerusahaan(json.perusahaan || []);
                renderMatrix(json.months || [], json.matrix || []);
                renderChart('monthly', '#ov-chart-monthly', json.monthly, 'line');
                renderChart('weekly', '#ov-chart-weekly', json.weekly, 'bar');

                var total = (json.matrix || []).reduce(function (a, r) { return a + r.total; }, 0);
                statusEl.textContent = fmtNum(total) + ' segmen · '
                    + (json.matrix || []).length + ' kombinasi site/perusahaan';
                loaded = true;
            })
            .catch(function (err) {
                statusEl.textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Overview Jalan Sesuai Standar: gagal memuat', err);
                }
            });
    }

    filterEls.forEach(function (el) {
        el.addEventListener('change', load);
    });

    document.querySelector('#ov-reset').addEventListener('click', function () {
        filterEls.forEach(function (el) { el.value = ''; });
        load();
    });

    // Tab Overview aktif sejak awal, jadi langsung dimuat. Grafik dibuat ulang
    // ukurannya saat tab dibuka lagi: ApexCharts tidak bisa mengukur lebar
    // elemen yang sedang tersembunyi.
    load();

    var overviewTab = document.querySelector('#jss-tab-overview');
    if (overviewTab) {
        overviewTab.addEventListener('shown.bs.tab', function () {
            if (!loaded) { load(); return; }
            Object.keys(charts).forEach(function (k) {
                if (charts[k]) { charts[k].windowResizeHandler ? charts[k].windowResizeHandler() : charts[k].render(); }
            });
        });
    }
})();
</script>
<script>
(function () {
    var tableEl = document.querySelector('#roadSummaryTable');
    if (!tableEl || typeof DataTable === 'undefined') {
        return;
    }

    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = @json(route('ohs-score-card.jalan-sesuai-standar.data'));
    var filterEls = Array.prototype.slice.call(document.querySelectorAll('.rs-filter'));

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('id-ID');
    }

    // Peta status -> kelas badge. Nilai tak dikenal jatuh ke 'muted'.
    var badgeClass = {
        'ACCEPT': 'rs-badge--ok',
        'WARNING': 'rs-badge--warn',
        'OVERGRADE': 'rs-badge--over',
        'REJECT': 'rs-badge--bad',
        '>12%': 'rs-badge--bad'
    };

    function renderBadge(value) {
        var text = (value === null || value === undefined || value === '') ? '–' : String(value);
        var cls = badgeClass[text] || 'rs-badge--muted';
        return '<span class="rs-badge ' + cls + '">' + escapeHtml(text) + '</span>';
    }

    function plain(value) {
        return escapeHtml(value === null || value === undefined || value === '' ? '–' : value);
    }

    // Junction hanya ikut dinilai bila segmen itu memang titik pertemuan;
    // '-' / kosong berarti tidak berlaku, jadi tidak dihitung.
    function junctionApplies(value) {
        return value !== null && value !== undefined && value !== '' && value !== '-';
    }

    function renderVerdict(isStandar, row) {
        var checked = ['grade_stat', 'road_width', 'supereleva'];
        if (junctionApplies(row.junction_1)) { checked.push('junction_1'); }
        if (junctionApplies(row.junction_s)) { checked.push('junction_s'); }

        var failed = checked.filter(function (key) { return row[key] !== 'ACCEPT'; });

        var tip = isStandar
            ? checked.length + ' parameter dinilai, semuanya ACCEPT'
            : checked.length + ' parameter dinilai, tidak ACCEPT: ' + failed.join(', ');

        return '<span class="rs-verdict ' + (isStandar ? 'rs-verdict--ok' : 'rs-verdict--bad') + '"'
            + ' title="' + escapeHtml(tip) + '">'
            + '<iconify-icon icon="' + (isStandar ? 'mdi:check-circle' : 'mdi:close-circle') + '"></iconify-icon>'
            + (isStandar ? 'STANDAR' : 'TIDAK STANDAR')
            + '</span>';
    }

    function currentFilters() {
        var out = {};
        filterEls.forEach(function (el) {
            var value = el.value;
            if (value) {
                out[el.dataset.column] = value;
            }
        });
        return out;
    }

    // Warna kartu Nilai mengikuti bandnya; nilainya sendiri dihitung di server
    // (satu sumber kebenaran), di sini hanya pewarnaan.
    var nilaiStyle = {
        1: { bg: '#FEF2F2', border: '#FECACA', icon: '#E0484A' },
        2: { bg: '#FFF7ED', border: '#FED7AA', icon: '#F08C2E' },
        3: { bg: '#FEFCE8', border: '#FDE68A', icon: '#CA8A04' },
        4: { bg: '#F7FEF9', border: '#BBF7D0', icon: '#16A34A' }
    };

    function updateNilai(summary) {
        var nilai = Number(summary.nilai || 0);
        var style = nilaiStyle[nilai];
        var card = document.querySelector('#rs-nilai-card');
        var icon = document.querySelector('#rs-nilai-icon');

        document.querySelector('#rs-nilai').textContent = nilai ? String(nilai) : '–';
        document.querySelector('#rs-nilai-meta').textContent = summary.nilai_band || 'dari % standar';

        if (!style) {
            return;
        }
        card.style.background = style.bg;
        card.style.borderColor = style.border;
        icon.style.background = style.icon;
        icon.style.color = '#fff';
    }

    function updateSummary(summary) {
        if (!summary) {
            return;
        }
        document.querySelector('#rs-total').textContent = formatNumber(summary.total);
        document.querySelector('#rs-total-meta').textContent =
            Object.keys(currentFilters()).length ? 'sesuai filter' : 'seluruh data';

        updateNilai(summary);

        [['standar', 'standar_pct', 'standar_ok'],
         ['grade', 'grade_pct', 'grade_ok'],
         ['width', 'width_pct', 'width_ok'],
         ['super', 'super_pct', 'super_ok']].forEach(function (item) {
            var pct = Number(summary[item[1]] || 0);
            document.querySelector('#rs-' + item[0]).textContent =
                pct.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
            document.querySelector('#rs-' + item[0] + '-meta').textContent =
                formatNumber(summary[item[2]]) + ' dari ' + formatNumber(summary.total) + ' segmen';
        });
    }

    var table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        pageLength: 25,
        lengthMenu: [25, 50, 100, 200],
        order: [[0, 'asc']],
        autoWidth: false,
        layout: {
            topStart: 'pageLength',
            topEnd: 'search',
            bottomStart: 'info',
            bottomEnd: 'paging'
        },
        ajax: {
            url: dataUrl,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: function (d) {
                var filters = currentFilters();
                Object.keys(filters).forEach(function (key) {
                    d[key] = filters[key];
                });
            },
            dataSrc: function (json) {
                updateSummary(json.summary);
                lastFilteredCount = json.recordsFiltered;
                refreshExportHint();
                return json.data || [];
            },
            error: function (xhr, error) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Jalan Sesuai Standar: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'site', render: function (d) { return plain(d); } },
            { data: 'pit', render: function (d) { return plain(d); } },
            { data: 'mitra', render: function (d) { return plain(d); } },
            { data: 'year', render: function (d) { return plain(d); } },
            { data: 'week', render: function (d) { return plain(d); } },
            { data: 'bulan', render: function (d) { return plain(d); } },
            { data: 'nama_jalan', render: function (d) { return plain(d); } },
            { data: 'segment', className: 'text-center', render: function (d) { return plain(d); } },
            { data: 'grade_stat', className: 'text-center', render: function (d) { return renderBadge(d); } },
            { data: 'road_width', className: 'text-center', render: function (d) { return renderBadge(d); } },
            { data: 'supereleva', className: 'text-center', render: function (d) { return renderBadge(d); } },
            { data: 'junction_1', render: function (d) { return plain(d); } },
            { data: 'junction_s', render: function (d) { return plain(d); } },
            {
                data: 'is_standar',
                className: 'text-center',
                render: function (d, type, row) {
                    if (type !== 'display') {
                        return d ? 1 : 0;
                    }
                    return renderVerdict(d, row);
                }
            }
        ],
        language: {
            processing: 'Memuat...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ segmen',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(difilter dari _MAX_ total segmen)',
            zeroRecords: 'Tidak ada segmen untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    // ---- Unduhan -----------------------------------------------------------
    var exportUrl = @json(route('ohs-score-card.jalan-sesuai-standar.export'));
    var maxXlsxRows = @json($maxXlsxRows);
    var lastFilteredCount = null;

    function exportHref(format) {
        var params = new URLSearchParams(currentFilters());
        params.set('format', format);

        var search = table.search();
        if (search) {
            params.set('search', search);
        }
        return exportUrl + '?' + params.toString();
    }

    // Beri tahu lebih dulu kalau hasil filter terlalu besar untuk .xlsx,
    // supaya pengguna tidak menunggu lalu baru ditolak.
    function refreshExportHint() {
        var hint = document.querySelector('#rs-export-hint');
        var xlsxBtn = document.querySelector('#rs-export-xlsx');

        if (lastFilteredCount === null) {
            hint.textContent = '';
            return;
        }

        var tooBig = lastFilteredCount > maxXlsxRows;
        xlsxBtn.classList.toggle('disabled', tooBig);
        xlsxBtn.setAttribute('aria-disabled', tooBig ? 'true' : 'false');
        hint.textContent = tooBig
            ? formatNumber(lastFilteredCount) + ' baris — terlalu besar untuk Excel, pakai CSV'
            : 'unduh ' + formatNumber(lastFilteredCount) + ' baris';
    }

    function startDownload(format) {
        if (format === 'xlsx' && lastFilteredCount !== null && lastFilteredCount > maxXlsxRows) {
            window.alert(
                'Hasil filter ' + formatNumber(lastFilteredCount) + ' baris, melebihi batas '
                + formatNumber(maxXlsxRows) + ' baris untuk Excel (.xlsx).\n\n'
                + 'Persempit filter (misalnya pilih satu bulan atau satu site), atau unduh sebagai CSV.'
            );
            return;
        }
        window.location.href = exportHref(format);
    }

    document.querySelector('#rs-export-xlsx').addEventListener('click', function () { startDownload('xlsx'); });
    document.querySelector('#rs-export-csv').addEventListener('click', function () { startDownload('csv'); });

    document.querySelector('#rs-apply').addEventListener('click', function () {
        table.ajax.reload();
    });

    document.querySelector('#rs-reset').addEventListener('click', function () {
        filterEls.forEach(function (el) { el.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    // Ganti dropdown langsung memuat ulang — tombol Terapkan tetap ada
    // untuk yang terbiasa menekannya setelah mengubah beberapa filter.
    filterEls.forEach(function (el) {
        el.addEventListener('change', function () { table.ajax.reload(); });
    });
})();
</script>
@endsection
