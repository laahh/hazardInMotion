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

  /* Hanya sel berisi angka yang bisa dibuka rinciannya. */
  .ov-matrix .ov-cell--klik { cursor: pointer; }
  .ov-matrix .ov-cell--klik:focus-visible {
    outline: 2px solid #0F172A; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Tabel panjang di dalam modal digulir sendiri, bukan ikut badan modal. */
  .ov-modal-scroll { max-height: 42vh; overflow: auto; }
  .ov-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }

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
    <h6 class="fw-semibold mb-0">Jalan Sesuai Standar</h6>
    <div class="text-secondary-light text-sm mt-4">
      Evaluasi per segmen jalan: grade, lebar, dan superelevasi
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
      Data Segmen
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
            <label class="form-label text-sm fw-medium mb-8" for="ov-pit">Area / Pit</label>
            <select class="form-select form-select-sm radius-8 ov-filter" id="ov-pit" data-column="pit">
              <option value="">Semua Area</option>
              @foreach ($filterOptions['pit'] ?? [] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xxl-2 col-md-4 col-sm-6">
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
            <h6 class="text-lg fw-semibold mb-0">Capaian per Perusahaan</h6>
          </div>
          <div class="card-body p-24">
            <div class="row gy-3" id="ov-perusahaan"></div>
          </div>
        </div>
      </div>

      <div class="col-xxl-4">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Capaian per Site</h6>
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
              <h6 class="text-lg fw-semibold mb-0">Capaian per Bulan</h6>
              <span class="text-sm text-secondary-light" id="ov-matrix-subtitle">
                Persentase segmen standar tiap perusahaan di tiap site
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
              {{-- URL rincian ditempel di tabelnya: di situ pula klik sel
                   didelegasikan, jadi keduanya tidak bisa terpisah. --}}
              <table class="ov-matrix" id="ov-matrix"
                     data-detail-url="{{ route('ohs-score-card.jalan-sesuai-standar.detail-bulan') }}">
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
            <h6 class="text-lg fw-semibold mb-0">Jenis Ketidaksesuaian</h6>
            <span class="text-sm text-secondary-light">Satu segmen bisa gagal di lebih dari satu jenis cek</span>
          </div>
          <div class="card-body p-24">
            <div id="ov-chart-pareto"></div>
          </div>
        </div>
      </div>

      <div class="col-xxl-5">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Sebaran per Area</h6>
            <span class="text-sm text-secondary-light">Delapan area terbanyak, sisanya digabung</span>
          </div>
          <div class="card-body p-24">
            <div id="ov-chart-area"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row gy-4">
      <div class="col-xxl-6">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Tren Bulanan</h6>
            <span class="text-sm text-secondary-light">Persentase segmen standar per perusahaan</span>
          </div>
          <div class="card-body p-24">
            <div id="ov-chart-monthly"></div>
          </div>
        </div>
      </div>

      <div class="col-xxl-6">
        <div class="card h-100 radius-8 border">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <h6 class="text-lg fw-semibold mb-0">Tren Mingguan</h6>
            <span class="text-sm text-secondary-light">Persentase segmen standar per perusahaan</span>
          </div>
          <div class="card-body p-24">
            <div id="ov-chart-weekly"></div>
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

{{-- Modal rincian satu sel matriks bulanan.
     Diletakkan di luar tab pane supaya tidak ikut tersembunyi saat tab
     "Data Segmen" aktif. --}}
<div class="modal fade" id="ov-detail-modal" tabindex="-1" aria-labelledby="ov-detail-judul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content radius-12">
      <div class="modal-header border-bottom py-16 px-24">
        <div>
          <h6 class="modal-title text-lg fw-semibold mb-0" id="ov-detail-judul">Rincian Bulan</h6>
          <span class="text-sm text-secondary-light" data-ovm="subjudul"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24"><div data-ovm="isi"></div></div>
      <div class="modal-footer border-top py-12 px-24">
        <span class="text-sm text-secondary-light me-auto" data-ovm="kaki"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary radius-8" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-scripts')
<script>
// ---- Modal rincian satu sel matriks bulanan ---------------------------------
var ovModalDetail = (function () {
    'use strict';

    var el = document.getElementById('ov-detail-modal');
    if (!el) { return null; }

    var bagian = function (n) { return el.querySelector('[data-ovm="' + n + '"]'); };
    var permintaan = 0;

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function num(v) { return Number(v || 0).toLocaleString('id-ID'); }

    function pct(v) {
        return Number(v || 0).toLocaleString('id-ID', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        }) + '%';
    }

    function ubin(label, nilai, catatan, kelas) {
        return '<div class="col-md-6 col-xxl-3">'
            + '<div class="border input-form-light radius-8 p-16 h-100">'
            +   '<span class="text-sm text-secondary-light d-block">' + esc(label) + '</span>'
            +   '<h6 class="fw-semibold mt-8 mb-4 ' + (kelas || '') + '">' + nilai + '</h6>'
            +   '<span class="text-xs text-secondary-light">' + esc(catatan) + '</span>'
            + '</div></div>';
    }

    /** Daftar berbatang: label, jumlah, lalu panjang batang relatif terbesar. */
    function daftarBatang(baris, kunci, warna) {
        var isi = (baris || []).filter(function (b) { return b.jumlah > 0; });

        if (!isi.length) {
            return '<div class="text-center text-secondary-light py-16">Tidak ada.</div>';
        }

        var maks = isi[0].jumlah || 1;

        return '<div class="table-responsive"><table class="table bordered-table sm-table mb-0"><tbody>'
            + isi.map(function (b) {
                return '<tr><td class="text-sm">' + esc(b[kunci]) + '</td>'
                    + '<td class="text-end fw-semibold" style="width:72px">' + num(b.jumlah) + '</td>'
                    + '<td style="width:38%"><div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
                    + '<div class="progress-bar ' + warna + ' rounded-pill" role="progressbar"'
                    + ' style="width:' + (b.jumlah / maks * 100) + '%" aria-valuenow="' + b.jumlah + '"'
                    + ' aria-valuemin="0" aria-valuemax="' + maks + '"></div></div></td></tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function tabelPit(baris) {
        if (!baris || !baris.length) {
            return '<div class="text-center text-secondary-light py-16">Tidak ada.</div>';
        }

        return '<div class="table-responsive ov-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>Pit</th><th class="text-end">Segmen</th>'
            +   '<th class="text-end">Tidak sesuai</th><th class="text-end">Capaian</th>'
            + '</tr></thead><tbody>'
            + baris.map(function (b) {
                return '<tr><td><span class="text-sm d-block">' + esc(b.pit) + '</span>'
                    +   '<span class="text-xs text-secondary-light">' + num(b.ruas) + ' ruas</span></td>'
                    + '<td class="text-end text-sm">' + num(b.total) + '</td>'
                    + '<td class="text-end text-sm fw-semibold">' + num(b.tidak_sesuai) + '</td>'
                    + '<td class="text-end text-sm">' + pct(b.percent) + '</td></tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function tabelMinggu(baris, lintasTahun) {
        return '<div class="table-responsive ov-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>Minggu</th><th class="text-end">Segmen</th>'
            +   '<th class="text-end">Standar</th><th class="text-end">Capaian</th>'
            + '</tr></thead><tbody>'
            + baris.map(function (b) {
                var label = 'W' + b.minggu + (lintasTahun ? " '" + String(b.tahun).slice(2) : '');
                return '<tr><td class="text-sm">' + esc(label) + '</td>'
                    + '<td class="text-end text-sm">' + num(b.total) + '</td>'
                    + '<td class="text-end text-sm">' + num(b.standar) + '</td>'
                    + '<td class="text-end text-sm fw-semibold">' + pct(b.percent) + '</td></tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    /** Nilai cek: yang gagal ditandai merah supaya terbaca sekilas. */
    function cek(nilai, gagal) {
        return gagal
            ? '<span class="text-danger-main fw-semibold">' + esc(nilai) + '</span>'
            : '<span class="text-secondary-light">' + esc(nilai) + '</span>';
    }

    function tabelSegmen(baris) {
        return '<div class="table-responsive ov-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0" data-ovm="tabel"><thead><tr>'
            +   '<th>Pit &amp; ruas</th><th>Segmen</th><th>Minggu</th>'
            +   '<th>Grade</th><th>Lebar</th><th>Superelevasi</th><th>Junction</th>'
            + '</tr></thead><tbody>'
            + baris.map(function (b) {
                var junction = [];
                if (b.junction_1 !== '-') { junction.push('1: ' + b.junction_1); }
                if (b.junction_s !== '-') { junction.push('S: ' + b.junction_s); }

                return '<tr data-cari="'
                    + esc((b.pit + ' ' + b.nama_jalan + ' ' + b.segment + ' ' + b.grade_stat + ' '
                           + b.road_width + ' ' + b.supereleva + ' ' + b.junction_1 + ' '
                           + b.junction_s).toLowerCase()) + '">'
                    + '<td><span class="text-sm d-block">' + esc(b.nama_jalan) + '</span>'
                    +   '<span class="text-xs text-secondary-light">' + esc(b.pit) + '</span></td>'
                    + '<td class="text-sm">' + num(b.segment) + '</td>'
                    + '<td class="text-xs text-secondary-light">W' + b.minggu + '</td>'
                    + '<td class="text-xs">' + cek(b.grade_stat, b.grade_stat !== 'ACCEPT') + '</td>'
                    + '<td class="text-xs">' + cek(b.road_width, b.road_width !== 'ACCEPT') + '</td>'
                    + '<td class="text-xs">' + cek(b.supereleva, b.supereleva !== 'ACCEPT') + '</td>'
                    + '<td class="text-xs">'
                    +   (junction.length
                            ? cek(junction.join(' · '),
                                  b.junction_1 === 'REJECT' || b.junction_s === 'REJECT')
                            : '<span class="text-secondary-light">–</span>')
                    + '</td></tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function render(j, koordinat) {
        var r = j.ringkas;
        var minggu = j.per_minggu || [];
        var tahun = minggu.map(function (b) { return b.tahun; });
        var lintasTahun = tahun.some(function (t) { return t !== tahun[0]; });
        var daftarMinggu = minggu.map(function (b) {
            return 'W' + b.minggu + (lintasTahun ? " '" + String(b.tahun).slice(2) : '');
        }).join(', ');

        var terburuk = (j.per_jenis || []).filter(function (b) { return b.jumlah > 0; })[0] || null;

        // Yang diklik bisa persentase atau Nilai, tergantung mode matriks.
        // Kartu pertama menyebut angka yang persis dia klik, catatannya memuat
        // keduanya supaya tidak ada informasi yang hilang.
        var isi = '<div class="row gy-3 mb-20">'
            + ubin('Capaian sel',
                   koordinat.ukuran === 'nilai' ? 'Nilai ' + r.nilai : pct(r.percent),
                   num(r.standar) + ' dari ' + num(r.total) + ' segmen memenuhi standar · '
                   + (koordinat.ukuran === 'nilai' ? pct(r.percent) : 'Nilai ' + r.nilai)
                   + ' (' + r.nilai_band + ')')
            + ubin('Segmen tidak sesuai', num(r.tidak_sesuai),
                   pct(r.tidak_sesuai_percent) + ' dari segmen di sel ini')
            + ubin('Penyebab terbanyak',
                   terburuk ? esc(terburuk.label) : '–',
                   terburuk
                       ? num(terburuk.jumlah) + ' segmen gagal cek ini'
                       : 'Tidak ada cek yang gagal di sel ini')
            + ubin('Cakupan', num(r.pit) + ' pit · ' + num(r.ruas) + ' ruas',
                   num(r.minggu) + ' minggu: ' + daftarMinggu)
            + '</div>';

        // Tabel sumbernya berbutir MINGGU, bukan bulan. Tanpa keterangan ini
        // pembaca tidak punya cara tahu minggu mana yang masuk hitungan sel —
        // dan 8 dari 40 minggu di data ini membelah dua bulan.
        isi += '<div class="alert bg-info-focus text-info-main border-info-main'
            + ' radius-8 px-20 py-12 mb-20 text-sm mt-0">'
            + '<strong>Bulan diturunkan dari nomor minggu.</strong> Satu minggu dimiliki bulan'
            + ' tempat hari Kamisnya jatuh (ISO 8601), jadi sel ' + esc(koordinat.bulan)
            + ' ini tersusun dari ' + num(r.minggu) + ' minggu: ' + esc(daftarMinggu) + '.'
            + '</div>';

        isi += '<div class="row gy-4 mb-20">'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">Jenis ketidaksesuaian</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Satu segmen bisa gagal lebih dari satu cek, jadi jumlah batang ini wajar'
            +       ' melebihi ' + num(r.tidak_sesuai) + ' segmen tidak sesuai</span>'
            +     daftarBatang(j.per_jenis, 'label', 'bg-danger-main')
            +   '</div>'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">Per pit</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Pit penyumbang segmen tidak sesuai terbanyak di atas</span>'
            +     tabelPit(j.per_pit)
            +   '</div>'
            + '</div>';

        isi += '<div class="row gy-4 mb-20"><div class="col-12">'
            +   '<h6 class="text-md fw-semibold mb-4">Per minggu</h6>'
            +   '<span class="text-xs text-secondary-light d-block mb-12">'
            +     'Penyusun sel ini; penjumlahannya persis angka di matriks</span>'
            +   tabelMinggu(minggu, lintasTahun)
            + '</div></div>';

        if (!j.baris.length) {
            isi += '<div class="text-center text-secondary-light py-24">'
                + 'Seluruh ' + num(r.total) + ' segmen di sel ini memenuhi standar.'
                + '</div>';
            bagian('isi').innerHTML = isi;
            bagian('kaki').textContent = num(r.total) + ' segmen terevaluasi · tidak ada yang gagal.';
            return;
        }

        isi += '<h6 class="text-md fw-semibold mb-4">Contoh segmen tidak sesuai</h6>'
            + '<span class="text-xs text-secondary-light d-block mb-12">'
            +   'Diurutkan dari yang paling banyak gagal ceknya</span>'
            + '<div class="row gy-2 gx-2 align-items-end mb-12">'
            +   '<div class="col-sm-8"><input type="text" class="form-control form-control-sm radius-8"'
            +     ' placeholder="Cari pit, ruas, segmen, status cek…" data-ovm="cari"></div>'
            +   '<div class="col-sm-4 text-sm-end"><span class="text-sm text-secondary-light"'
            +     ' data-ovm="hitung"></span></div>'
            + '</div>'
            + tabelSegmen(j.baris);

        bagian('isi').innerHTML = isi;
        bagian('kaki').textContent = j.terpotong
            ? 'Menampilkan ' + num(j.batas) + ' segmen terburuk dari ' + num(r.tidak_sesuai)
              + ' yang tidak sesuai. Unduh tab Data Segmen untuk daftar lengkapnya.'
            : num(r.tidak_sesuai) + ' segmen tidak sesuai di sel ini, semuanya terdaftar.';

        pasangPencarian();
    }

    /** Pencarian dikerjakan di baris yang sudah ada, tanpa ke server lagi. */
    function pasangPencarian() {
        var cari = bagian('cari');
        var hitung = bagian('hitung');
        var semua = Array.prototype.slice.call(el.querySelectorAll('[data-ovm="tabel"] tbody tr'));

        function terapkan() {
            var teks = (cari.value || '').trim().toLowerCase();
            var tampil = 0;

            semua.forEach(function (tr) {
                var cocok = !teks || tr.dataset.cari.indexOf(teks) !== -1;
                tr.classList.toggle('d-none', !cocok);
                if (cocok) { tampil++; }
            });

            hitung.textContent = tampil === semua.length
                ? semua.length + ' segmen'
                : tampil + ' dari ' + semua.length + ' segmen';
        }

        cari.addEventListener('input', terapkan);
        terapkan();
    }

    function buka(url, koordinat) {
        // Nomor permintaan menjaga agar jawaban yang datang terlambat untuk sel
        // yang sudah tidak dibuka lagi tidak menimpa isi modal.
        var ini = ++permintaan;

        el.querySelector('#ov-detail-judul').textContent =
            'Rincian ' + koordinat.bulan + ' · ' + koordinat.site;

        var sub = koordinat.mitra || 'Seluruh perusahaan di site ini';
        if (koordinat.pit) { sub += ' · pit ' + koordinat.pit; }
        bagian('subjudul').textContent = sub;

        bagian('kaki').textContent = '';
        bagian('isi').innerHTML = '<div class="text-center text-secondary-light py-40">'
            + '<div class="spinner-border spinner-border-sm text-primary-600 me-2" role="status"></div>'
            + 'Memuat rincian…</div>';

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }

        // pit dan year ikut dikirim: keduanya membentuk sel di matriks, jadi
        // tanpa itu modal akan memecah sel yang lebih besar daripada yang diklik.
        var q = new URLSearchParams({
            site: koordinat.site,
            mitra: koordinat.mitra || '',
            pit: koordinat.pit || '',
            year: koordinat.year || '',
            month: koordinat.month
        });

        fetch(url + '?' + q.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.json();
            })
            .then(function (j) {
                if (ini !== permintaan) { return; }
                if (!j.ok) {
                    bagian('isi').innerHTML = '<div class="alert alert-danger bg-danger-focus'
                        + ' border-danger-main text-danger-main radius-8 px-20 py-12 mb-0">'
                        + esc(j.pesan || 'Rincian tidak bisa dimuat.') + '</div>';
                    return;
                }
                render(j, koordinat);
            })
            .catch(function (err) {
                if (ini !== permintaan) { return; }
                bagian('isi').innerHTML = '<div class="alert alert-danger bg-danger-focus'
                    + ' border-danger-main text-danger-main radius-8 px-20 py-12 mb-0">'
                    + 'Permintaan ke server gagal. ' + esc(err && err.message) + '</div>';
            });
    }

    return { buka: buka };
})();
</script>
<script>
// ---- Tab Overview Dashboard -------------------------------------------------
(function () {
    var overviewUrl = @json(route('ohs-score-card.jalan-sesuai-standar.overview'));
    var matrixEl = document.querySelector('#ov-matrix');
    if (!matrixEl) {
        return;
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap ganti
    // filter atau mode, dan pendengar per sel akan ikut hilang. URL rincian
    // menempel di tabel yang sama, jadi keduanya tidak bisa terpisah.
    if (ovModalDetail && matrixEl.dataset.detailUrl) {
        var bukaSel = function (td) {
            ovModalDetail.buka(matrixEl.dataset.detailUrl, {
                site: td.dataset.site,
                mitra: td.dataset.mitra,
                month: td.dataset.month,
                bulan: td.dataset.bulan,
                ukuran: td.dataset.ukuran,
                nilai: td.dataset.nilai
            });
        };

        matrixEl.addEventListener('click', function (e) {
            var td = e.target.closest('.ov-cell--klik');
            if (td && matrixEl.contains(td)) { bukaSel(td); }
        });

        matrixEl.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') { return; }
            var td = e.target.closest('.ov-cell--klik');
            if (!td || !matrixEl.contains(td)) { return; }
            e.preventDefault();
            bukaSel(td);
        });
    }

    var filterEls = Array.prototype.slice.call(document.querySelectorAll('.ov-filter'));
    var statusEl = document.querySelector('#ov-status');
    var charts = { monthly: null, weekly: null, pareto: null, area: null };
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
                label: 'Total Segmen', value: fmtNum(k.total),
                foot: k.site_count + ' site, ' + k.mitra_count + ' perusahaan'
            },
            {
                grad: 'bg-gradient-end-2', icon: 'solar:check-circle-outline', dot: 'bg-success-main',
                label: 'Sesuai Standar', value: fmtNum(k.standar),
                foot: fmtPct(k.standar_pct) + ' dari total segmen'
            },
            {
                grad: 'bg-gradient-end-5', icon: 'solar:danger-triangle-outline', dot: 'bg-danger-main',
                label: 'Tidak Sesuai', value: fmtNum(k.tidak_sesuai),
                foot: fmtPct(k.tidak_sesuai_pct) + ' dari total segmen'
            },
            {
                grad: 'bg-gradient-end-3', icon: 'solar:medal-star-outline', dot: 'bg-yellow',
                label: 'Capaian Keseluruhan', value: fmtPct(k.standar_pct),
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
                +   ' segmen tidak sesuai, target ' + s.target + '%</span>'
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
            : 'Persentase segmen standar tiap perusahaan di tiap site';
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

                // Koordinat sel dibawa di atribut, bukan ditebak dari posisi
                // DOM: matriks digambar ulang tiap ganti filter/mode dan
                // urutan barisnya ikut berubah.
                html += '<td class="ov-cell ov-cell--klik ' + cellClass(cell) + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-site="' + escapeHtml(row.site) + '"'
                    + ' data-mitra="' + escapeHtml(row.mitra) + '"'
                    + ' data-month="' + months[m].number + '"'
                    + ' data-bulan="' + escapeHtml(months[m].label) + '"'
                    + ' data-ukuran="' + matrixMode + '"'
                    + ' data-nilai="' + escapeHtml(matrixMode === 'nilai'
                        ? 'Nilai ' + cell.nilai : fmtPct(cell.pct)) + '"'
                    + ' title="' + escapeHtml(tip + ' · klik untuk rincian') + '">'
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
                { name: 'Jumlah Segmen', type: 'column', data: rows.map(function (r) { return r.jumlah; }) },
                { name: 'Kumulatif', type: 'line', data: rows.map(function (r) { return r.kumulatif; }) }
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
                { title: { text: 'Jumlah Segmen', style: { fontSize: '11px' } },
                  labels: { formatter: function (v) { return fmtNum(Math.round(v)); } } },
                { opposite: true, min: 0, max: 100,
                  title: { text: 'Kumulatif', style: { fontSize: '11px' } },
                  labels: { formatter: function (v) { return Math.round(v) + '%'; } } }
            ],
            legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
            grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
            tooltip: {
                shared: true, intersect: false,
                y: {
                    formatter: function (v, opts) {
                        return opts.seriesIndex === 1 ? Number(v).toFixed(1) + '%' : fmtNum(v) + ' segmen';
                    }
                }
            }
        });
        charts.pareto.render();
    }

    /** Donut untuk panel Sebaran per Area. */
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
                safe('weekly', function () { renderChart('weekly', '#ov-chart-weekly', json.weekly, 'line'); });

                statusEl.textContent = fmtNum(json.kpi.total) + ' segmen · '
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
            ? formatNumber(lastFilteredCount) + ' baris, terlalu besar untuk Excel. Pakai CSV.'
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

    // Tabel ini dibangun saat pane-nya masih tersembunyi (tab Ringkasan yang
    // aktif lebih dulu), sehingga DataTables tidak bisa mengukur lebar kolom.
    // Ukurannya dihitung ulang begitu tab Data Segmen pertama kali dibuka.
    var rawTab = document.querySelector('#jss-tab-rawdata');
    if (rawTab) {
        rawTab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        });
    }

    document.querySelector('#rs-apply').addEventListener('click', function () {
        table.ajax.reload();
    });

    document.querySelector('#rs-reset').addEventListener('click', function () {
        filterEls.forEach(function (el) { el.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    // Ganti dropdown langsung memuat ulang; tombol Terapkan tetap ada
    // untuk yang terbiasa menekannya setelah mengubah beberapa filter.
    filterEls.forEach(function (el) {
        el.addEventListener('change', function () { table.ajax.reload(); });
    });
})();
</script>
@endsection
