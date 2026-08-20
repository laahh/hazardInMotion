@extends('dms.layouts.app')

@section('title', 'Dashboard')

@php
    $kpiMetrics = ['operator_checkin', 'total_alert', 'ratio_per_person', 'units_operating', 'total_alert', 'ratio_per_unit'];
    $kpis = $kpis ?? [];
    foreach ($kpis as $i => $kpi) {
        if (! is_array($kpi)) {
            continue;
        }
        $kpis[$i]['metric'] = $kpi['metric'] ?? ($kpiMetrics[$i] ?? 'total_alert');
        $kpis[$i]['delta'] = $kpi['delta'] ?? ['class' => 'bg-success-focus text-success-main', 'text' => '+0'];
        $kpis[$i]['chart'] = $kpi['chart'] ?? 'kpi-chart-'.$i;
        $kpis[$i]['color'] = $kpi['color'] ?? '#487fff';
        $kpis[$i]['sparkline'] = $kpi['sparkline'] ?? [0, 0, 0, 0, 0, 0, 0, 0, 0];
        $kpis[$i]['gradient'] = $kpi['gradient'] ?? 'bg-gradient-end-1';
        $kpis[$i]['bg'] = $kpi['bg'] ?? 'bg-primary-600';
        $kpis[$i]['icon'] = $kpi['icon'] ?? 'solar:danger-triangle-bold';
        $kpis[$i]['label'] = $kpi['label'] ?? 'KPI';
        $kpis[$i]['value'] = $kpi['value'] ?? '0';
        $kpis[$i]['modal_group'] = $kpi['modal_group'] ?? ($i < 3 ? 'people' : 'unit');
    }
    $kpiDeltaLabel = $kpiDeltaLabel ?? 'this week';
    $campaigns = $campaigns ?? ($categories ?? []);
    $allItems = $recentAll ?? [];
    $bestMatch = $recentConfirmed ?? [];
    $transactions = $recentReviews ?? [];
    $growth = $growth ?? ['title' => 'Alert Last 4 Week', 'subtitle' => 'Weekly Report', 'total' => '0', 'delta' => ['class' => 'bg-success-focus text-success-main', 'text' => '+0'], 'series' => [], 'labels' => []];
    $statistic = $statistic ?? [
        'title' => 'Matriks Site',
        'subtitle' => 'Exposure vs Alert Intensity per Site',
        'mode' => 'quadrant',
        'total' => '0',
        'confirmed' => '0',
        'dismissed' => '0.00',
        'x_median' => 0,
        'y_median' => 0,
        'overall' => ['checkin' => 0, 'alert' => 0, 'ratio' => 0.0],
        'quadrants' => [],
        'points' => [],
        'series' => [],
        'labels' => [],
    ];
    $statistic['mode'] = $statistic['mode'] ?? 'quadrant';
    $statistic['points'] = $statistic['points'] ?? [];
    $statistic['quadrants'] = $statistic['quadrants'] ?? [];
    $statistic['x_median'] = $statistic['x_median'] ?? 0;
    $statistic['y_median'] = $statistic['y_median'] ?? 0;
    $quadrantOrder = ['q2', 'q1', 'q4', 'q3'];
    $controlRoom = $controlRoom ?? [
        'title' => 'Performa Control Room',
        'subtitle' => 'Intervensi alert & lead time real time per perusahaan / site',
        'companies' => [],
        'columns' => [],
        'rows' => [],
    ];
    $controlRoomColumns = $controlRoom['columns'] ?? [];
    $controlRoomRows = $controlRoom['rows'] ?? [];
    $overview = $overview ?? ['confirmed' => 0, 'dismissed' => 0, 'pending' => 0];
    $weeklyStatus = $weeklyStatus ?? ['confirmed' => [], 'pending' => [], 'dismissed' => [], 'labels' => [], 'totals' => ['confirmed' => 0, 'pending' => 0, 'dismissed' => 0]];
    $filters = $filters ?? ['start' => '', 'end' => '', 'site' => '', 'perusahaan' => ''];
    $filterOptions = $filterOptions ?? ['sites' => [], 'companies' => []];
    $lazyWidgets = $lazyWidgets ?? false;
@endphp

@section('css')
<style>
  .dms-quadrant-wrap { position: relative; padding: 0 8px 8px 36px; }
  .dms-quadrant-y-title {
    position: absolute; left: 0; top: 50%; transform: rotate(-90deg) translateX(-50%);
    transform-origin: left center; font-size: 11px; font-weight: 600; color: #6B7280;
    white-space: nowrap;
  }
  .dms-quadrant-x-title {
    text-align: center; font-size: 11px; font-weight: 600; color: #6B7280; margin-top: 8px;
  }
  .dms-quadrant-grid {
    display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr;
    gap: 0; min-height: 360px; border: 1px dashed #D1D5DB; border-radius: 12px; overflow: hidden; position: relative;
  }
  .dms-quadrant-cell {
    padding: 14px 12px 12px; display: flex; flex-direction: column; justify-content: space-between;
    border: 1px dashed #D1D5DB; min-height: 180px;
  }
  .dms-quadrant-cell-head { display: flex; align-items: flex-start; gap: 8px; }
  .dms-quadrant-cell-icon {
    width: 28px; height: 28px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
  }
  .dms-quadrant-cell-title { font-size: 12px; font-weight: 700; line-height: 1.3; margin-bottom: 2px; }
  .dms-quadrant-cell-desc { font-size: 10px; color: #6B7280; line-height: 1.35; }
  .dms-quadrant-sites { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; align-content: flex-end; }
  .dms-quadrant-pill {
    display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 600; background: #fff; border: 1.5px solid currentColor; white-space: nowrap;
  }
  .dms-quadrant-center {
    position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
    width: 54px; height: 54px; border-radius: 50%; background: #111827; color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700;
    z-index: 2; box-shadow: 0 2px 8px rgba(0,0,0,.15);
  }
  .dms-quadrant-axis-hint {
    display: flex; justify-content: space-between; font-size: 10px; color: #9CA3AF; margin-top: 4px; padding: 0 2px;
  }
  @media (max-width: 767px) {
    .dms-quadrant-grid { min-height: 420px; }
    .dms-quadrant-cell { min-height: 210px; }
  }
  .dms-cr-wrap { overflow-x: auto; }
  .dms-cr-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 960px; }
  .dms-cr-table th, .dms-cr-table td { border: 1px solid #E5E7EB; padding: 8px 6px; vertical-align: middle; text-align: center; font-size: 12px; }
  .dms-cr-table thead th { background: #F3F4F6; font-weight: 700; color: #374151; }
  .dms-cr-metric { text-align: left; min-width: 220px; font-weight: 600; color: #111827; background: #fff; }
  .dms-cr-company { background: #EFF6FF; font-size: 11px; font-weight: 700; letter-spacing: .01em; color: #1d4ed8; padding: 6px 4px; }
  .dms-cr-site { background: #F9FAFB; font-size: 11px; font-weight: 600; color: #374151; min-width: 90px; }
  .dms-cr-pct { font-size: 18px; font-weight: 800; line-height: 1.1; margin-bottom: 4px; }
  .dms-cr-frac { font-size: 10px; color: rgba(17, 24, 39, .72); }
  .dms-cr-tone-excellent { background: #16a34a; color: #fff; }
  .dms-cr-tone-good { background: #86efac; color: #14532d; }
  .dms-cr-tone-warn { background: #fde047; color: #713f12; }
  .dms-cr-tone-bad { background: #fdba74; color: #7c2d12; }
  .dms-cr-tone-critical { background: #ef4444; color: #fff; }
  .dms-cr-tone-empty { background: #F3F4F6; color: #6B7280; }

  .dms-overall-modal-dialog {
    max-width: min(92vw, 1400px);
    width: calc(100% - 2rem);
    margin: 1.5rem auto;
  }
  .dms-overall-modal-dialog .modal-content {
    max-height: calc(100vh - 3rem);
    display: flex;
    flex-direction: column;
  }
  .dms-overall-modal-dialog .modal-body {
    flex: 1 1 auto;
    overflow-y: auto;
    min-height: 0;
  }
  .dms-overall-main-chart {
    width: 100%;
    min-height: 280px;
  }
  .dms-overall-mini-chart {
    width: 100%;
    min-height: 220px;
  }
  @media (max-width: 575.98px) {
    .dms-overall-modal-dialog {
      width: calc(100% - 1rem);
      max-width: calc(100% - 1rem);
      margin: 0.75rem auto;
    }
    .dms-overall-modal-dialog .modal-content {
      max-height: calc(100vh - 1.5rem);
    }
  }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Dashboard</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('pra-operasi.dms-monitoring') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">CRM</li>
  </ul>
</div>

@if(session('status'))
<div class="alert alert-success radius-8 mb-24 d-flex align-items-start gap-2">
  <iconify-icon icon="solar:check-circle-bold" class="icon text-xl flex-shrink-0"></iconify-icon>
  <div>{{ session('status') }}</div>
</div>
@endif
@if(session('error'))
<div class="alert alert-warning radius-8 mb-24 d-flex align-items-start gap-2">
  <iconify-icon icon="solar:danger-circle-bold" class="icon text-xl flex-shrink-0"></iconify-icon>
  <div>{{ session('error') }}</div>
</div>
@endif

<div class="card radius-8 border-0 shadow-2 mb-24">
  <div class="card-body p-20">
    <form method="GET" action="{{ route('pra-operasi.dms-monitoring') }}" class="row g-3 align-items-end">
      <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6">
        <label for="filter-start" class="form-label text-sm fw-medium text-secondary-light mb-8">Dari Tanggal</label>
        <input id="filter-start" type="date" name="start" value="{{ $filters['start'] }}" class="form-control radius-8">
      </div>
      <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6">
        <label for="filter-end" class="form-label text-sm fw-medium text-secondary-light mb-8">Sampai Tanggal</label>
        <input id="filter-end" type="date" name="end" value="{{ $filters['end'] }}" class="form-control radius-8">
      </div>
      <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
        <label for="filter-site" class="form-label text-sm fw-medium text-secondary-light mb-8">Site</label>
        <select id="filter-site" name="site" class="form-select radius-8">
          <option value="">Semua Site</option>
          @foreach($filterOptions['sites'] as $siteOption)
          <option value="{{ $siteOption }}" @selected(($filters['site'] ?? '') === $siteOption)>{{ $siteOption }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
        <label for="filter-perusahaan" class="form-label text-sm fw-medium text-secondary-light mb-8">Perusahaan</label>
        <select id="filter-perusahaan" name="perusahaan" class="form-select radius-8">
          <option value="">Semua Perusahaan</option>
          @foreach($filterOptions['companies'] as $companyOption)
          <option value="{{ $companyOption }}" @selected(($filters['perusahaan'] ?? '') === $companyOption)>{{ $companyOption }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-xl-2 col-lg-4 col-md-12">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary-600 radius-8 flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1">
            <iconify-icon icon="solar:filter-bold" class="icon"></iconify-icon>
            Terapkan
          </button>
          <a href="{{ route('pra-operasi.dms-monitoring') }}" class="btn btn-outline-secondary radius-8 d-inline-flex align-items-center justify-content-center px-12" title="Reset filter">
            <iconify-icon icon="solar:restart-bold" class="icon"></iconify-icon>
          </a>
        </div>
      </div>
    </form>
  </div>
</div>

@unless($up)
<div class="alert alert-warning radius-8 mb-24 d-flex align-items-start gap-2">
  <iconify-icon icon="solar:danger-circle-bold" class="icon text-xl flex-shrink-0"></iconify-icon>
  <div>Koneksi ke hse_automation (bcsid.mv_dms_alert) tidak tersedia saat ini. Kartu di bawah menampilkan angka kosong sampai koneksi tersambung.</div>
</div>
@endunless

<div class="row gy-4">
  <div class="col-xxl-8">
    <div class="row gy-4">
      @foreach($kpis as $kpi)
      <div class="col-xxl-4 col-sm-6">
        <div
          class="card p-3 shadow-2 radius-8 border input-form-light h-100 {{ $kpi['gradient'] }} dms-kpi-card cursor-pointer"
          role="button"
          tabindex="0"
          data-kpi-metric="{{ $kpi['metric'] ?? 'total_alert' }}"
          data-kpi-group="{{ $kpi['modal_group'] ?? 'unit' }}"
          data-kpi-label="{{ $kpi['label'] ?? 'KPI' }}"
          aria-label="Lihat detail {{ $kpi['label'] ?? 'KPI' }}"
        >
          <div class="card-body p-0">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
              <div class="d-flex align-items-center gap-2">
                <span class="mb-0 w-48-px h-48-px {{ $kpi['bg'] }} flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
                  <iconify-icon icon="{{ $kpi['icon'] }}" class="icon"></iconify-icon>
                </span>
                <div>
                  <span class="mb-2 fw-medium text-secondary-light text-sm">{{ $kpi['label'] }}</span>
                  <h6 class="fw-semibold">{{ $kpi['value'] }}</h6>
                </div>
              </div>
              <div id="{{ $kpi['chart'] }}" class="remove-tooltip-title rounded-tooltip-value"></div>
            </div>
            <p class="text-sm mb-0">Increase by  <span class="{{ $kpi['delta']['class'] ?? 'bg-success-focus text-success-main' }} px-1 rounded-2 fw-medium text-sm">{{ $kpi['delta']['text'] ?? '+0' }}</span> {{ $kpiDeltaLabel }}</p>
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>

  <div class="col-xxl-4">
    <div class="card h-100 radius-8 border" id="dms-growth-widget">
      <div class="card-body p-24">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
          <div>
            <h6 class="mb-2 fw-bold text-lg">{{ $growth['title'] ?? 'Alert Last 4 Week' }}</h6>
            <span class="text-sm fw-medium text-secondary-light">{{ $growth['subtitle'] ?? 'Weekly Report' }}</span>
          </div>
          <div class="text-end" id="dms-growth-head">
            @if(empty($lazyWidgets))
            <h6 class="mb-2 fw-bold text-lg">{{ $growth['total'] }}</h6>
            <span class="{{ $growth['delta']['class'] }} ps-12 pe-12 pt-2 pb-2 rounded-2 fw-medium text-sm">{{ $growth['delta']['text'] }}</span>
            @else
            <div class="spinner-border spinner-border-sm text-primary-600" role="status"></div>
            @endif
          </div>
        </div>
        <div id="revenue-chart" class="mt-28">
          @if(!empty($lazyWidgets))
          <div class="text-center py-40 text-secondary-light text-sm">Memuat grafik mingguan…</div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-8" id="dms-quadrant-widget">
    @if(empty($lazyWidgets))
    @include('pra-operasi.partials._dms-quadrant-widget', ['quadrantOrder' => $quadrantOrder])
    @else
    <div class="card h-100 radius-8 border-0">
      <div class="card-body p-24 text-center py-60">
        <div class="spinner-border text-primary-600 mb-12" role="status"></div>
        <p class="text-sm text-secondary-light mb-0">Memuat matriks site…</p>
      </div>
    </div>
    @endif
  </div>

  <div class="col-xxl-4">
    <div class="row gy-4">
      <div class="col-xxl-12 col-sm-6">
        <div class="card h-100 radius-8 border-0">
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
              <div>
                <h6 class="mb-2 fw-bold text-lg">Funnel Tindakan Alert</h6>
                <span class="text-sm text-secondary-light">Konversi antar tahap</span>
              </div>
              <span class="text-sm text-secondary-light">{{ $dateLabel }}</span>
            </div>
            <div class="mt-3">
              @forelse($campaigns as $i => $cat)
              <div class="d-flex align-items-center justify-content-between gap-3 {{ $i < count($campaigns) - 1 ? 'mb-12' : '' }}">
                <div class="d-flex align-items-center" style="min-width: 120px;">
                  <span class="text-xxl line-height-1 d-flex align-content-center flex-shrink-0 {{ $cat['textClass'] }}">
                    <iconify-icon icon="{{ $cat['icon'] }}" class="icon"></iconify-icon>
                  </span>
                  <div class="ps-12">
                    <span class="d-block text-primary-light fw-medium text-sm text-truncate" title="{{ $cat['name'] }}">{{ $cat['name'] }}</span>
                    <span class="d-block text-xs text-secondary-light">{{ number_format($cat['total']) }} orang</span>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-2 w-100">
                  <div class="w-100 max-w-66 ms-auto">
                    <div class="progress progress-sm rounded-pill" role="progressbar" aria-valuenow="{{ $cat['pct'] }}" aria-valuemin="0" aria-valuemax="100">
                      <div class="progress-bar {{ $cat['barClass'] }} rounded-pill" style="width: {{ $cat['pct'] }}%;"></div>
                    </div>
                  </div>
                  <div class="text-end" style="min-width: 58px;">
                    <span class="d-block text-secondary-light font-xs fw-semibold">{{ $cat['pct'] }}%</span>
                    <span class="d-block text-xs text-secondary-light">{{ $cat['conversion_label'] ?? 'vs tahap sebelumnya' }}</span>
                  </div>
                </div>
              </div>
              @empty
              <p class="text-secondary-light text-sm mb-0">No data</p>
              @endforelse
            </div>
          </div>
        </div>
      </div>
      <div class="col-xxl-12 col-sm-6">
        <div class="card h-100 radius-8 border-0 overflow-hidden">
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
              <div>
                <h6 class="mb-2 fw-bold text-lg">Status Review Alert</h6>
                <span class="text-sm fw-medium text-secondary-light">Distribusi status review L1</span>
              </div>
              <span class="text-sm fw-medium text-secondary-light">{{ $dateLabel }}</span>
            </div>
            <div class="d-flex flex-wrap align-items-center mt-3">
              <ul class="flex-shrink-0">
                <li class="d-flex align-items-center gap-2 mb-28">
                  <span class="w-12-px h-12-px rounded-circle bg-danger-main"></span>
                  <span class="text-secondary-light text-sm fw-medium">Total Alert: {{ number_format(($overview['confirmed'] ?? 0) + ($overview['dismissed'] ?? 0) + ($overview['pending'] ?? 0)) }}</span>
                </li>
                <li class="d-flex align-items-center gap-2 mb-28">
                  <span class="w-12-px h-12-px rounded-circle bg-warning-main"></span>
                  <span class="text-secondary-light text-sm fw-medium">Belum Review: {{ number_format($overview['pending'] ?? 0) }}</span>
                </li>
                <li class="d-flex align-items-center gap-2">
                  <span class="w-12-px h-12-px rounded-circle bg-primary-600"></span>
                  <span class="text-secondary-light text-sm fw-medium">Confirmed L1: {{ number_format($overview['confirmed'] ?? 0) }}</span>
                </li>
                <li class="d-flex align-items-center gap-2 mt-28">
                  <span class="w-12-px h-12-px rounded-circle bg-success-main"></span>
                  <span class="text-secondary-light text-sm fw-medium">Dismissed L1: {{ number_format($overview['dismissed'] ?? 0) }}</span>
                </li>
              </ul>
              <div id="donutChart" class="flex-grow-1 apexcharts-tooltip-z-none title-style circle-none"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12" id="dms-control-room-widget">
    @if(empty($lazyWidgets))
    @include('pra-operasi.partials._dms-control-room-widget')
    @else
    <div class="card h-100 radius-8 border-0">
      <div class="card-body p-24 text-center py-60">
        <div class="spinner-border text-primary-600 mb-12" role="status"></div>
        <p class="text-sm text-secondary-light mb-0">Memuat performa control room…</p>
      </div>
    </div>
    @endif
  </div>

  <div class="col-xxl-6">
    <div class="card h-100">
      <div class="card-header border-bottom bg-base ps-0 py-0 pe-24 d-flex align-items-center justify-content-between">
        <ul class="nav bordered-tab nav-pills mb-0" id="pills-tab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pills-to-do-list-tab" data-bs-toggle="pill" data-bs-target="#pills-to-do-list" type="button" role="tab" aria-controls="pills-to-do-list" aria-selected="true">All Item</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-recent-leads-tab" data-bs-toggle="pill" data-bs-target="#pills-recent-leads" type="button" role="tab" aria-controls="pills-recent-leads" aria-selected="false" tabindex="-1">Best Match</button>
          </li>
        </ul>
        <a href="javascript:void(0)" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
          View All
          <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
        </a>
      </div>
      <div class="card-body p-24">
        <div class="tab-content" id="pills-tabContent">
          <div class="tab-pane fade show active" id="pills-to-do-list" role="tabpanel" aria-labelledby="pills-to-do-list-tab" tabindex="0">
            @include('pra-operasi.partials._wowdash-task-table', ['rows' => $allItems])
          </div>
          <div class="tab-pane fade" id="pills-recent-leads" role="tabpanel" aria-labelledby="pills-recent-leads-tab" tabindex="0">
            @include('pra-operasi.partials._wowdash-task-table', ['rows' => $bestMatch])
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-6">
    <div class="card h-100">
      <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center justify-content-between">
        <h6 class="text-lg fw-semibold mb-0">Last Transaction</h6>
        <a href="javascript:void(0)" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
          View All
          <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
        </a>
      </div>
      <div class="card-body p-24">
        <div class="table-responsive scroll-sm">
          <table class="table bordered-table mb-0">
            <thead>
              <tr>
                <th scope="col">Transaction ID</th>
                <th scope="col">Date</th>
                <th scope="col">Status</th>
                <th scope="col">Amount</th>
              </tr>
            </thead>
            <tbody>
              @forelse($transactions as $row)
              <tr>
                <td>{{ \Illuminate\Support\Str::limit($row['id_alert'], 16) }}</td>
                <td>{{ $row['waktu'] }}</td>
                <td> <span class="{{ $row['status_class'] }} px-24 py-4 rounded-pill fw-medium text-sm">{{ $row['status_label'] }}</span> </td>
                <td>{{ $row['site'] }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="4" class="text-center text-secondary-light">No data</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

@include('pra-operasi.partials._dms-overall-modal')
@include('pra-operasi.partials._dms-overall-people-modal')
@endsection

@section('scripts')
<script>
(function () {
  var kpis = @json($kpis);
  var growth = @json($growth);
  var statistic = @json($statistic);
  var overview = @json($overview);

  function createSparkline(chartId, chartColor, data) {
    var el = document.querySelector('#' + chartId);
    if (!el || typeof ApexCharts === 'undefined') return;

    new ApexCharts(el, {
      series: [{ name: 'series1', data: data && data.length ? data : [0, 0, 0, 0, 0, 0, 0, 0, 0] }],
      chart: {
        type: 'area',
        width: 80,
        height: 42,
        sparkline: { enabled: true },
        toolbar: { show: false }
      },
      dataLabels: { enabled: false },
      stroke: { curve: 'smooth', width: 2, colors: [chartColor], lineCap: 'round' },
      grid: { show: false },
      fill: {
        type: 'gradient',
        colors: [chartColor],
        gradient: {
          shade: 'light',
          type: 'vertical',
          shadeIntensity: 0.5,
          gradientToColors: [chartColor + '00'],
          inverseColors: false,
          opacityFrom: 0.75,
          opacityTo: 0.3,
          stops: [0, 100]
        }
      },
      markers: { colors: [chartColor], strokeWidth: 2, size: 0, hover: { size: 8 } },
      tooltip: { enabled: false }
    }).render();
  }

  kpis.forEach(function (kpi) {
    createSparkline(kpi.chart, kpi.color, kpi.sparkline);
  });

  function dmsRenderGrowthChart(growthData) {
    var revenueEl = document.querySelector('#revenue-chart');
    if (!revenueEl || typeof ApexCharts === 'undefined') return;
    revenueEl.innerHTML = '';
    new ApexCharts(revenueEl, {
      series: [{ name: 'Alert', data: growthData.series || [] }],
      chart: { type: 'area', width: '100%', height: 162, toolbar: { show: false } },
      dataLabels: { enabled: false },
      stroke: { curve: 'smooth', width: 2, colors: ['#487fff'], lineCap: 'round' },
      grid: {
        show: true, borderColor: 'transparent', strokeDashArray: 0,
        xaxis: { lines: { show: false } }, yaxis: { lines: { show: false } },
        padding: { top: -30, right: 0, bottom: -10, left: 0 }
      },
      fill: {
        type: 'gradient', colors: ['#487fff'],
        gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.5, gradientToColors: ['#487fff00'], inverseColors: false, opacityFrom: 0.6, opacityTo: 0.3, stops: [0, 100] }
      },
      markers: { colors: ['#487fff'], strokeWidth: 3, size: 0, hover: { size: 10 } },
      xaxis: { categories: growthData.labels || [], labels: { show: true, style: { fontSize: '10px', colors: '#6B7280' } } },
      yaxis: { labels: { show: false } },
      tooltip: { y: { formatter: function (v) { return v + ' alert'; } } }
    }).render();
  }

  var dmsLazyWidgets = @json(!empty($lazyWidgets));
  if (!dmsLazyWidgets) {
    dmsRenderGrowthChart(growth);
  }

  var donutEl = document.querySelector('#donutChart');
  if (donutEl && typeof ApexCharts !== 'undefined') {
    var totalAlerts = (overview.confirmed || 0) + (overview.dismissed || 0) + (overview.pending || 0);
    new ApexCharts(donutEl, {
      series: [overview.pending || 0, overview.confirmed || 0, overview.dismissed || 0],
      colors: ['#FF9F29', '#487FFF', '#45B369'],
      labels: ['Belum Review', 'Confirmed L1', 'Dismissed L1'],
      legend: { show: false },
      chart: { type: 'donut', height: 300, sparkline: { enabled: true } },
      stroke: { width: 0 },
      dataLabels: { enabled: false },
      tooltip: {
        y: {
          formatter: function (v) { return v + ' alert'; }
        }
      },
      plotOptions: {
        pie: {
          startAngle: -90,
          endAngle: 90,
          offsetY: 10,
          customScale: 0.8,
          donut: {
            size: '70%',
            labels: {
              show: true,
              name: { show: true, offsetY: 18 },
              value: {
                show: true,
                offsetY: -12,
                formatter: function (val) { return Math.round(Number(val || 0)).toLocaleString('en-US'); }
              },
              total: {
                showAlways: true,
                show: true,
                label: 'Total Alert',
                formatter: function () { return totalAlerts.toLocaleString('en-US'); }
              }
            }
          }
        }
      }
    }).render();
  }

  var dmsOverallFilters = @json($filters);
  var dmsOverallUrl = @json(route('pra-operasi.dms-monitoring.kpi-overall'));
  var dmsOverallUnitDayUrl = @json(route('pra-operasi.dms-monitoring.kpi-overall.unit-day'));
  var dmsOverallUnitAlertsUrl = @json(route('pra-operasi.dms-monitoring.kpi-overall.unit-alerts'));
  var dmsOverallPeopleUrl = @json(route('pra-operasi.dms-monitoring.kpi-overall.people'));
  var dmsOverallPeopleDayUrl = @json(route('pra-operasi.dms-monitoring.kpi-overall.people-day'));
  var dmsOverallPeopleAlertsUrl = @json(route('pra-operasi.dms-monitoring.kpi-overall.people-alerts'));
  var dmsOverallModalEl = document.getElementById('dmsOverallModal');
  var dmsOverallModal = dmsOverallModalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(dmsOverallModalEl) : null;
  var dmsOverallState = { page: 1, status: 'with_alert' };
  var dmsOverallDayState = { day: '', status: 'without_alert', page: 1 };
  var dmsOverallControlChart = null;
  var dmsOverallTopUnitsChart = null;
  var dmsOverallDailyBarChart = null;
  var dmsOverallPeopleModalEl = document.getElementById('dmsOverallPeopleModal');
  var dmsOverallPeopleModal = dmsOverallPeopleModalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(dmsOverallPeopleModalEl) : null;
  var dmsOverallPeopleState = { page: 1, status: 'with_alert' };
  var dmsOverallPeopleDayState = { day: '', status: 'without_alert', page: 1 };
  var dmsOverallPeopleControlChart = null;
  var dmsOverallPeopleTopChart = null;
  var dmsOverallPeopleDailyBarChart = null;

  function dmsOverallSetLoading(show) {
    var loading = document.getElementById('dms-overall-loading');
    if (loading) loading.classList.toggle('d-none', !show);
    if (loading) loading.classList.toggle('d-flex', show);
  }

  function dmsOverallDestroyCharts() {
    if (dmsOverallControlChart) { dmsOverallControlChart.destroy(); dmsOverallControlChart = null; }
    if (dmsOverallTopUnitsChart) { dmsOverallTopUnitsChart.destroy(); dmsOverallTopUnitsChart = null; }
    if (dmsOverallDailyBarChart) { dmsOverallDailyBarChart.destroy(); dmsOverallDailyBarChart = null; }
  }

  function dmsOverallRenderSummary(cards) {
    var wrap = document.getElementById('dms-overall-summary');
    if (!wrap) return;
    wrap.innerHTML = '';
    (cards || []).forEach(function (card) {
      var col = document.createElement('div');
      col.className = 'col-sm-6 col-xl-3';
      col.innerHTML =
        '<div class="border radius-8 p-16 h-100 d-flex align-items-start gap-12">' +
          '<span class="w-40-px h-40-px radius-circle d-flex align-items-center justify-content-center flex-shrink-0" style="background:' + (card.color || '#487fff') + '20;color:' + (card.color || '#487fff') + '">' +
            '<iconify-icon icon="' + (card.icon || 'solar:chart-2-bold') + '" class="icon text-lg"></iconify-icon>' +
          '</span>' +
          '<div class="min-w-0">' +
            '<div class="text-sm text-secondary-light mb-4">' + (card.label || '-') + '</div>' +
            '<div class="fw-bold text-xl">' + (card.value || '0') + '</div>' +
            '<div class="text-xs text-secondary-light mt-4">' + (card.hint || '') + '</div>' +
          '</div>' +
        '</div>';
      wrap.appendChild(col);
    });
  }

  function dmsOverallRenderTopUnits(topUnits) {
    var wrap = document.getElementById('dms-overall-top-units');
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!topUnits || !topUnits.length) {
      wrap.innerHTML = '<div class="col-12 text-sm text-secondary-light">Tidak ada unit dengan alert.</div>';
      return;
    }
    topUnits.forEach(function (row, idx) {
      var col = document.createElement('div');
      col.className = 'col-md-6 col-xl-4';
      col.innerHTML =
        '<div class="border radius-8 p-12 h-100">' +
          '<div class="d-flex align-items-center gap-8 mb-4">' +
            '<span class="badge bg-primary-600 text-white radius-4 px-8 py-4">#' + (idx + 1) + '</span>' +
            '<span class="fw-semibold text-sm text-truncate">' + (row.unit || '-') + '</span>' +
          '</div>' +
          '<div class="text-xs text-secondary-light">' + (row.site || '-') + ' · ' + (row.perusahaan || '-') + '</div>' +
          '<div class="fw-bold text-lg mt-4">' + Number(row.alert_count || 0).toLocaleString('id-ID') + ' <span class="text-sm fw-normal text-secondary-light">alert</span></div>' +
        '</div>';
      wrap.appendChild(col);
    });
  }

  function dmsOverallRenderControlChart(chartData) {
    var el = document.getElementById('dms-overall-control-chart');
    var legend = document.getElementById('dms-overall-control-legend');
    if (!el || typeof ApexCharts === 'undefined') return;

    if (dmsOverallControlChart) { dmsOverallControlChart.destroy(); dmsOverallControlChart = null; }

    if (legend) {
      legend.innerHTML =
        '<span><span class="d-inline-block rounded-circle me-4" style="width:8px;height:8px;background:#487fff"></span> Alert harian</span>' +
        '<span><span class="d-inline-block rounded-circle me-4" style="width:8px;height:8px;background:#45b369"></span> Mean: ' + (chartData.mean || 0) + '</span>' +
        '<span><span class="d-inline-block rounded-circle me-4" style="width:8px;height:8px;background:#ef4a00"></span> UCL: ' + (chartData.ucl || 0) + '</span>' +
        '<span><span class="d-inline-block rounded-circle me-4" style="width:8px;height:8px;background:#8252e9"></span> LCL: ' + (chartData.lcl || 0) + '</span>';
    }

    dmsOverallControlChart = new ApexCharts(el, {
      series: [
        { name: 'Total Alert', type: 'area', data: chartData.series || [] },
        { name: 'Mean', type: 'line', data: chartData.mean_series || [] },
        { name: 'UCL', type: 'line', data: chartData.ucl_series || [] },
        { name: 'LCL', type: 'line', data: chartData.lcl_series || [] },
      ],
      chart: { height: 380, type: 'line', toolbar: { show: false }, zoom: { enabled: false } },
      colors: ['#487fff', '#45b369', '#ef4a00', '#8252e9'],
      stroke: { width: [2, 2, 2, 2], curve: 'smooth', dashArray: [0, 6, 4, 4] },
      fill: {
        type: ['gradient', 'solid', 'solid', 'solid'],
        gradient: { shadeIntensity: 0.4, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 100] },
      },
      dataLabels: { enabled: false },
      xaxis: { categories: chartData.labels || [], labels: { rotate: -45, style: { fontSize: '10px' } } },
      yaxis: { labels: { formatter: function (v) { return Math.round(v); } } },
      legend: { show: false },
      tooltip: { shared: true, intersect: false },
    });
    dmsOverallControlChart.render();
  }

  function dmsOverallRenderTopUnitsChart(chartData) {
    var el = document.getElementById('dms-overall-top-units-chart');
    if (!el || typeof ApexCharts === 'undefined') return;
    if (!chartData || !chartData.series || !chartData.series.length) {
      el.innerHTML = '<p class="text-sm text-secondary-light mb-0">Grafik tren unit teratas tidak tersedia.</p>';
      return;
    }
    el.innerHTML = '';
    if (dmsOverallTopUnitsChart) { dmsOverallTopUnitsChart.destroy(); dmsOverallTopUnitsChart = null; }
    dmsOverallTopUnitsChart = new ApexCharts(el, {
      series: chartData.series,
      chart: { type: 'line', height: 280, toolbar: { show: false }, zoom: { enabled: false } },
      stroke: { curve: 'smooth', width: 2 },
      dataLabels: { enabled: false },
      xaxis: { categories: chartData.labels || [], labels: { rotate: -45, style: { fontSize: '10px' } } },
      legend: { position: 'top', horizontalAlign: 'left', fontSize: '11px' },
      tooltip: { shared: true },
    });
    dmsOverallTopUnitsChart.render();
  }

  function dmsRenderDailyBarChart(el, chartRefName, chartData, color) {
    if (!el || typeof ApexCharts === 'undefined') return null;
    el.innerHTML = '';
    if (!chartData || !chartData.series || !chartData.series.length) {
      el.innerHTML = '<p class="text-sm text-secondary-light mb-0">Tidak ada data harian pada periode ini.</p>';
      return null;
    }
    var chart = new ApexCharts(el, {
      series: [{ name: chartData.name || 'Total', data: chartData.series }],
      chart: { type: 'bar', height: 280, toolbar: { show: false }, zoom: { enabled: false } },
      colors: [color || '#487fff'],
      plotOptions: { bar: { borderRadius: 6, columnWidth: '55%', dataLabels: { position: 'top' } } },
      dataLabels: {
        enabled: true,
        offsetY: -16,
        style: { fontSize: '11px', colors: ['#6B7280'] },
        formatter: function (v) { return Number(v || 0).toLocaleString('id-ID'); }
      },
      xaxis: { categories: chartData.labels || [], labels: { rotate: -45, style: { fontSize: '10px' } } },
      yaxis: { labels: { formatter: function (v) { return Math.round(v); } } },
      tooltip: {
        y: { formatter: function (v) { return Number(v || 0).toLocaleString('id-ID'); } }
      }
    });
    chart.render();
    return chart;
  }

  function dmsOverallRenderDailyBar(chartData) {
    var el = document.getElementById('dms-overall-daily-bar');
    var totalEl = document.getElementById('dms-overall-daily-bar-total');
    if (dmsOverallDailyBarChart) { dmsOverallDailyBarChart.destroy(); dmsOverallDailyBarChart = null; }
    var totals = (chartData && chartData.totals) ? chartData.totals : {};
    if (totalEl) {
      totalEl.textContent = 'Tanpa alert ' + Number(totals.without_alert || 0).toLocaleString('id-ID') + ' / beroperasi ' + Number(totals.units || 0).toLocaleString('id-ID');
    }
    if (!el || typeof ApexCharts === 'undefined') return;
    el.innerHTML = '';
    var series = (chartData && chartData.series) ? chartData.series : [];
    if (!series.length || !series[0] || !series[0].data) {
      el.innerHTML = '<p class="text-sm text-secondary-light mb-0">Tidak ada data harian pada periode ini.</p>';
      return;
    }
    dmsOverallDailyBarChart = new ApexCharts(el, {
      series: series,
      chart: {
        type: 'bar',
        height: 300,
        toolbar: { show: false },
        zoom: { enabled: false },
        events: {
          click: function (event, ctx, config) {
            if (config.dataPointIndex == null || config.dataPointIndex < 0) return;
            var isoDates = (chartData && chartData.iso_dates) ? chartData.iso_dates : [];
            var day = isoDates[config.dataPointIndex] || '';
            if (!day) return;
            var status = 'without_alert';
            if (config.seriesIndex === 1) status = 'with_alert';
            if (config.seriesIndex === 0) status = 'all';
            dmsOverallLoadDay(day, status, 1);
          }
        }
      },
      colors: ['#8252e9', '#f4941e', '#45b369'],
      plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
      dataLabels: { enabled: false },
      xaxis: { categories: (chartData && chartData.labels) ? chartData.labels : [], labels: { rotate: -45, style: { fontSize: '10px' } } },
      yaxis: { labels: { formatter: function (v) { return Math.round(v); } } },
      legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
      tooltip: { y: { formatter: function (v) { return Number(v || 0).toLocaleString('id-ID') + ' unit'; } } }
    });
    dmsOverallDailyBarChart.render();
  }

  function dmsOverallSetDayLoading(show) {
    var loading = document.getElementById('dms-overall-day-loading');
    if (loading) loading.classList.toggle('d-none', !show);
  }

  function dmsOverallResetDayCard() {
    dmsOverallDayState = { day: '', status: 'without_alert', page: 1 };
    var title = document.getElementById('dms-overall-day-title');
    var hint = document.getElementById('dms-overall-day-hint');
    var count = document.getElementById('dms-overall-day-count');
    var body = document.getElementById('dms-overall-day-body');
    var empty = document.getElementById('dms-overall-day-empty');
    var pagination = document.getElementById('dms-overall-day-pagination');
    if (title) title.textContent = 'Detail Unit Harian';
    if (hint) hint.textContent = 'Klik batang hari di chart untuk memuat daftar unit.';
    if (count) count.textContent = '';
    if (body) body.innerHTML = '';
    if (empty) {
      empty.classList.remove('d-none');
      empty.textContent = 'Belum ada hari yang dipilih.';
    }
    if (pagination) {
      pagination.classList.add('d-none');
      pagination.classList.remove('d-flex');
    }
  }

  function dmsOverallRenderDayTable(payload) {
    var body = document.getElementById('dms-overall-day-body');
    var empty = document.getElementById('dms-overall-day-empty');
    var title = document.getElementById('dms-overall-day-title');
    var hint = document.getElementById('dms-overall-day-hint');
    var count = document.getElementById('dms-overall-day-count');
    var pagination = document.getElementById('dms-overall-day-pagination');
    var info = document.getElementById('dms-overall-day-page-info');
    var prev = document.getElementById('dms-overall-day-prev');
    var next = document.getElementById('dms-overall-day-next');
    if (title) title.textContent = payload.label || 'Detail Unit Harian';
    if (hint) hint.textContent = 'Hasil sesuai range filter dan tanggal batang yang diklik.';
    if (!body) return;
    body.innerHTML = '';
    var rows = (payload.table && payload.table.rows) ? payload.table.rows : [];
    if (!rows.length) {
      if (empty) {
        empty.classList.remove('d-none');
        empty.textContent = 'Tidak ada unit untuk filter hari ini.';
      }
    } else {
      if (empty) empty.classList.add('d-none');
      rows.forEach(function (row) {
        var alerts = Number(row.alert_count || 0);
        var hasAlert = !!row.has_alert && alerts > 0;
        var badge = hasAlert
          ? '<span class="badge bg-warning-focus text-warning-main border border-warning-main px-12 py-6">Ada alert</span>'
          : '<span class="badge bg-success-focus text-success-main border border-success-main px-12 py-6">Tidak ada alert</span>';
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td class="fw-medium">' + (row.unit || '-') + '</td>' +
          '<td>' + (row.site || '-') + '</td>' +
          '<td>' + (row.perusahaan || '-') + '</td>' +
          '<td><div class="fw-medium text-sm">' + (row.evidence_source || 'Online DMS') + '</div>' +
            '<div class="text-xs text-secondary-light">' + (row.evidence_at || '-') + '</div></td>' +
          '<td>' + badge + '</td>' +
          '<td class="text-end fw-semibold">' + alerts.toLocaleString('id-ID') + '</td>';
        body.appendChild(tr);
      });
    }
    var paginationData = payload.pagination || {};
    if (count) count.textContent = Number(paginationData.total_rows || 0).toLocaleString('id-ID') + ' unit';
    if (pagination) {
      pagination.classList.remove('d-none');
      pagination.classList.add('d-flex');
    }
    if (info) info.textContent = 'Halaman ' + (paginationData.page || 1) + ' / ' + (paginationData.total_pages || 1);
    if (prev) prev.disabled = (paginationData.page || 1) <= 1;
    if (next) next.disabled = (paginationData.page || 1) >= (paginationData.total_pages || 1);
  }

  function dmsOverallLoadDay(day, status, page) {
    dmsOverallDayState.day = day || dmsOverallDayState.day;
    dmsOverallDayState.status = status || dmsOverallDayState.status || 'without_alert';
    dmsOverallDayState.page = page || 1;
    if (!dmsOverallDayState.day) return;
    dmsOverallSetDayLoading(true);
    var params = new URLSearchParams({
      start: dmsOverallFilters.start || '',
      end: dmsOverallFilters.end || '',
      site: dmsOverallFilters.site || '',
      perusahaan: dmsOverallFilters.perusahaan || '',
      day: dmsOverallDayState.day,
      status: dmsOverallDayState.status,
      page: String(dmsOverallDayState.page)
    });
    fetch(dmsOverallUnitDayUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        dmsOverallSetDayLoading(false);
        if (!result.data.ok) {
          var empty = document.getElementById('dms-overall-day-empty');
          if (empty) {
            empty.classList.remove('d-none');
            empty.textContent = result.data.message || 'Gagal memuat detail hari.';
          }
          return;
        }
        dmsOverallRenderDayTable(result.data);
      })
      .catch(function () {
        dmsOverallSetDayLoading(false);
        var empty = document.getElementById('dms-overall-day-empty');
        if (empty) {
          empty.classList.remove('d-none');
          empty.textContent = 'Gagal memuat detail hari.';
        }
      });
  }

  function dmsOverallRenderTable(table) {
    var body = document.getElementById('dms-overall-table-body');
    var empty = document.getElementById('dms-overall-table-empty');
    if (!body) return;
    body.innerHTML = '';
    var rows = (table && table.rows) ? table.rows : [];
    if (!rows.length) {
      if (empty) empty.classList.remove('d-none');
      return;
    }
    if (empty) empty.classList.add('d-none');

    rows.forEach(function (row) {
      var tr = document.createElement('tr');
      var alerts = Number(row.alert_count || 0);
      var hasAlert = !!row.has_alert && alerts > 0;
      var detailId = 'dms-overall-alerts-' + [row.unit || '-', row.site || '-', row.perusahaan || '-'].join('-').replace(/[^a-zA-Z0-9_-]/g, '_');
      var evidenceSource = row.evidence_source || '-';
      var evidenceAt = row.evidence_at || null;
      var evidenceValue = row.evidence_value !== null && row.evidence_value !== undefined
        ? Number(row.evidence_value)
        : null;
      var evidenceHtml = '<div class="fw-medium text-sm">' + evidenceSource + '</div>' +
        '<div class="text-xs text-secondary-light">' + (evidenceAt || '-') + '</div>' +
        (evidenceSource === 'GPS bergerak' && evidenceValue !== null
          ? '<div class="text-xs text-primary-600 mt-4">Speed max: ' + evidenceValue.toFixed(1) + ' km/h</div>'
          : '');
      var badge = hasAlert
        ? '<span class="badge bg-warning-focus text-warning-main border border-warning-main px-12 py-6">Ada alert</span>'
        : '<span class="badge bg-success-focus text-success-main border border-success-main px-12 py-6">Tidak ada alert</span>';
      var detailButton = hasAlert
        ? '<button type="button" class="btn btn-sm btn-outline-primary dms-overall-toggle" data-target="' + detailId + '">Lihat alert</button>'
        : '<span class="text-xs text-secondary-light">-</span>';
      tr.innerHTML =
        '<td class="fw-medium">' + (row.unit || '-') + '</td>' +
        '<td>' + (row.site || '-') + '</td>' +
        '<td>' + (row.perusahaan || '-') + '</td>' +
        '<td>' + evidenceHtml + '</td>' +
        '<td>' + badge + '</td>' +
        '<td class="text-end fw-semibold">' + alerts.toLocaleString('id-ID') + '</td>' +
        '<td>' + detailButton + '</td>';
      body.appendChild(tr);

      if (hasAlert) {
        var detailTr = document.createElement('tr');
        detailTr.id = detailId;
        detailTr.className = 'd-none';
        detailTr.innerHTML =
          '<td colspan="7" class="bg-neutral-50">' +
            '<div class="p-12">' +
              '<div class="text-xs text-secondary-light mb-8">Jenis alert pada unit ini</div>' +
              '<div class="d-flex flex-wrap gap-2" data-role="alert-list">' +
                '<span class="text-sm text-secondary-light">Klik "Lihat alert" untuk memuat detail.</span>' +
              '</div>' +
            '</div>' +
          '</td>';
        body.appendChild(detailTr);
      }
    });

    body.querySelectorAll('.dms-overall-toggle').forEach(function (button) {
      button.addEventListener('click', function () {
        var targetId = button.getAttribute('data-target');
        var detailRow = targetId ? document.getElementById(targetId) : null;
        if (!detailRow) return;
        var isHidden = detailRow.classList.contains('d-none');
        if (isHidden) {
          detailRow.classList.remove('d-none');
          button.textContent = 'Memuat...';
          button.disabled = true;

          var params = new URLSearchParams({
            start: dmsOverallFilters.start || '',
            end: dmsOverallFilters.end || '',
            site: dmsOverallFilters.site || '',
            perusahaan: dmsOverallFilters.perusahaan || '',
            unit: button.closest('tr').children[0].textContent.trim(),
            unit_site: button.closest('tr').children[1].textContent.trim(),
            unit_perusahaan: button.closest('tr').children[2].textContent.trim()
          });
          var listEl = detailRow.querySelector('[data-role="alert-list"]');

          fetch(dmsOverallUnitAlertsUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
              if (!listEl) return;
              if (!result.ok || !result.data.ok) {
                listEl.innerHTML = '<span class="text-sm text-danger-main">Gagal memuat detail alert.</span>';
                button.textContent = 'Lihat alert';
                return;
              }

              var items = result.data.alerts || [];
              if (!items.length) {
                listEl.innerHTML = '<span class="text-sm text-secondary-light">Tidak ada detail alert.</span>';
              } else {
                listEl.innerHTML = items.map(function (item) {
                  return '<span class="badge bg-primary-50 text-primary-600 border border-primary-100 px-12 py-6">' +
                    (item.name || '-') + ' <span class="fw-semibold">(' + Number(item.total || 0).toLocaleString('id-ID') + ')</span>' +
                  '</span>';
                }).join('');
              }
              button.textContent = 'Sembunyikan alert';
            })
            .catch(function () {
              if (listEl) listEl.innerHTML = '<span class="text-sm text-danger-main">Gagal memuat detail alert.</span>';
              button.textContent = 'Lihat alert';
            })
            .finally(function () {
              button.disabled = false;
            });
        } else {
          detailRow.classList.add('d-none');
          button.textContent = 'Lihat alert';
        }
      });
    });
  }

  function dmsOverallRenderTabs(tabs, activeKey) {
    var wrap = document.getElementById('dms-overall-table-tabs');
    if (!wrap) return;

    (tabs || []).forEach(function (tab) {
      var button = wrap.querySelector('[data-status="' + (tab.key || '') + '"]');
      if (!button) return;
      var isActive = (tab.key || '') === activeKey;
      button.classList.toggle('btn-primary', isActive);
      button.classList.toggle('btn-outline-secondary', !isActive);
      var countEl = button.querySelector('span');
      if (countEl) countEl.textContent = Number(tab.count || 0).toLocaleString('id-ID');
    });
  }

  function dmsOverallRenderPagination(pagination) {
    var wrap = document.getElementById('dms-overall-pagination');
    var info = document.getElementById('dms-overall-page-info');
    var prev = document.getElementById('dms-overall-prev');
    var next = document.getElementById('dms-overall-next');
    var count = document.getElementById('dms-overall-table-count');
    if (!wrap || !pagination) return;

    wrap.classList.remove('d-none');
    if (count) count.textContent = Number(pagination.total_rows || 0).toLocaleString('id-ID') + ' unit';
    if (info) info.textContent = 'Halaman ' + pagination.page + ' / ' + pagination.total_pages;
    if (prev) prev.disabled = pagination.page <= 1;
    if (next) next.disabled = pagination.page >= pagination.total_pages;
  }

  function dmsOverallLoad() {
    dmsOverallSetLoading(true);
    document.getElementById('dms-overall-error').classList.add('d-none');
    document.getElementById('dms-overall-content').classList.add('d-none');

    var params = new URLSearchParams({
      start: dmsOverallFilters.start || '',
      end: dmsOverallFilters.end || '',
      site: dmsOverallFilters.site || '',
      perusahaan: dmsOverallFilters.perusahaan || '',
      page: String(dmsOverallState.page),
      status: dmsOverallState.status || 'with_alert',
    });

    fetch(dmsOverallUrl + '?' + params.toString(), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        dmsOverallSetLoading(false);
        if (!result.data.ok) {
          document.getElementById('dms-overall-error').classList.remove('d-none');
          document.getElementById('dms-overall-error-message').textContent = result.data.message || 'Gagal memuat overview.';
          return;
        }
        var payload = result.data;
        document.getElementById('dmsOverallModalLabel').textContent = payload.label || 'Overview Unit & Alert';
        document.getElementById('dms-overall-subtitle').textContent =
          (payload.period && payload.period.start ? payload.period.start : '') +
          ' s/d ' +
          (payload.period && payload.period.end ? payload.period.end : '');

        dmsOverallRenderSummary(payload.summary || []);
        dmsOverallRenderDailyBar(payload.daily_bar || {});
        dmsOverallRenderTopUnits(payload.top_units || []);
        dmsOverallRenderControlChart(payload.control_chart || {});
        dmsOverallRenderTopUnitsChart(payload.top_units_chart || {});

        dmsOverallRenderTable(payload.table || {});
        dmsOverallRenderTabs(payload.table_tabs || [], payload.table_active || 'with_alert');
        dmsOverallRenderPagination(payload.pagination || null);

        document.getElementById('dms-overall-content').classList.remove('d-none');
      })
      .catch(function () {
        dmsOverallSetLoading(false);
        document.getElementById('dms-overall-error').classList.remove('d-none');
        document.getElementById('dms-overall-error-message').textContent = 'Gagal memuat overview unit & alert.';
      });
  }

  function dmsOverallOpen() {
    dmsOverallState.page = 1;
    dmsOverallState.status = 'with_alert';
    dmsOverallDestroyCharts();
    dmsOverallResetDayCard();
    if (dmsOverallModal) dmsOverallModal.show();
    dmsOverallLoad();
  }

  function dmsOverallPeopleSetLoading(show) {
    var loading = document.getElementById('dms-overall-people-loading');
    if (loading) loading.classList.toggle('d-none', !show);
    if (loading) loading.classList.toggle('d-flex', show);
  }

  function dmsOverallPeopleDestroyCharts() {
    if (dmsOverallPeopleControlChart) { dmsOverallPeopleControlChart.destroy(); dmsOverallPeopleControlChart = null; }
    if (dmsOverallPeopleTopChart) { dmsOverallPeopleTopChart.destroy(); dmsOverallPeopleTopChart = null; }
    if (dmsOverallPeopleDailyBarChart) { dmsOverallPeopleDailyBarChart.destroy(); dmsOverallPeopleDailyBarChart = null; }
  }

  function dmsOverallPeopleRenderSummary(cards) {
    var wrap = document.getElementById('dms-overall-people-summary');
    if (!wrap) return;
    wrap.innerHTML = '';
    (cards || []).forEach(function (card) {
      var col = document.createElement('div');
      col.className = 'col-sm-6 col-xl-3';
      col.innerHTML =
        '<div class="border radius-8 p-16 h-100 d-flex align-items-start gap-12">' +
          '<span class="w-40-px h-40-px radius-circle d-flex align-items-center justify-content-center flex-shrink-0" style="background:' + (card.color || '#487fff') + '20;color:' + (card.color || '#487fff') + '">' +
            '<iconify-icon icon="' + (card.icon || 'solar:chart-2-bold') + '" class="icon text-lg"></iconify-icon>' +
          '</span>' +
          '<div class="min-w-0">' +
            '<div class="text-sm text-secondary-light mb-4">' + (card.label || '-') + '</div>' +
            '<div class="fw-bold text-xl">' + (card.value || '0') + '</div>' +
            '<div class="text-xs text-secondary-light mt-4">' + (card.hint || '') + '</div>' +
          '</div>' +
        '</div>';
      wrap.appendChild(col);
    });
  }

  function dmsOverallPeopleRenderTop(topRows) {
    var wrap = document.getElementById('dms-overall-people-top');
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!topRows || !topRows.length) {
      wrap.innerHTML = '<div class="col-12 text-sm text-secondary-light">Tidak ada orang dengan alert.</div>';
      return;
    }
    topRows.forEach(function (row, idx) {
      var col = document.createElement('div');
      col.className = 'col-md-6 col-xl-4';
      col.innerHTML =
        '<div class="border radius-8 p-12 h-100">' +
          '<div class="d-flex align-items-center gap-8 mb-4">' +
            '<span class="badge bg-primary-600 text-white radius-4 px-8 py-4">#' + (idx + 1) + '</span>' +
            '<span class="fw-semibold text-sm text-truncate">' + (row.unit || '-') + '</span>' +
          '</div>' +
          '<div class="text-xs text-secondary-light">' + (row.site || '-') + ' · ' + (row.perusahaan || '-') + '</div>' +
          '<div class="fw-bold text-lg mt-4">' + Number(row.alert_count || 0).toLocaleString('id-ID') + ' <span class="text-sm fw-normal text-secondary-light">alert</span></div>' +
        '</div>';
      wrap.appendChild(col);
    });
  }

  function dmsOverallPeopleRenderControlChart(chartData) {
    var el = document.getElementById('dms-overall-people-control-chart');
    var legend = document.getElementById('dms-overall-people-control-legend');
    if (!el || typeof ApexCharts === 'undefined') return;
    if (dmsOverallPeopleControlChart) { dmsOverallPeopleControlChart.destroy(); dmsOverallPeopleControlChart = null; }
    if (legend) {
      legend.innerHTML =
        '<span><span class="d-inline-block rounded-circle me-4" style="width:8px;height:8px;background:#487fff"></span> Alert harian</span>' +
        '<span><span class="d-inline-block rounded-circle me-4" style="width:8px;height:8px;background:#45b369"></span> Mean: ' + (chartData.mean || 0) + '</span>' +
        '<span><span class="d-inline-block rounded-circle me-4" style="width:8px;height:8px;background:#ef4a00"></span> UCL: ' + (chartData.ucl || 0) + '</span>' +
        '<span><span class="d-inline-block rounded-circle me-4" style="width:8px;height:8px;background:#8252e9"></span> LCL: ' + (chartData.lcl || 0) + '</span>';
    }
    dmsOverallPeopleControlChart = new ApexCharts(el, {
      series: [
        { name: 'Total Alert', type: 'area', data: chartData.series || [] },
        { name: 'Mean', type: 'line', data: chartData.mean_series || [] },
        { name: 'UCL', type: 'line', data: chartData.ucl_series || [] },
        { name: 'LCL', type: 'line', data: chartData.lcl_series || [] }
      ],
      chart: { height: 380, type: 'line', toolbar: { show: false }, zoom: { enabled: false } },
      colors: ['#487fff', '#45b369', '#ef4a00', '#8252e9'],
      stroke: { width: [2, 2, 2, 2], curve: 'smooth', dashArray: [0, 6, 4, 4] },
      fill: { type: ['gradient', 'solid', 'solid', 'solid'], gradient: { shadeIntensity: 0.4, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 100] } },
      dataLabels: { enabled: false },
      xaxis: { categories: chartData.labels || [], labels: { rotate: -45, style: { fontSize: '10px' } } },
      yaxis: { labels: { formatter: function (v) { return Math.round(v); } } },
      legend: { show: false },
      tooltip: { shared: true, intersect: false }
    });
    dmsOverallPeopleControlChart.render();
  }

  function dmsOverallPeopleRenderTopChart(chartData) {
    var el = document.getElementById('dms-overall-people-top-chart');
    if (!el || typeof ApexCharts === 'undefined') return;
    if (!chartData || !chartData.series || !chartData.series.length) {
      el.innerHTML = '<p class="text-sm text-secondary-light mb-0">Grafik tren orang teratas tidak tersedia.</p>';
      return;
    }
    el.innerHTML = '';
    if (dmsOverallPeopleTopChart) { dmsOverallPeopleTopChart.destroy(); dmsOverallPeopleTopChart = null; }
    dmsOverallPeopleTopChart = new ApexCharts(el, {
      series: chartData.series,
      chart: { type: 'line', height: 280, toolbar: { show: false }, zoom: { enabled: false } },
      stroke: { curve: 'smooth', width: 2 },
      dataLabels: { enabled: false },
      xaxis: { categories: chartData.labels || [], labels: { rotate: -45, style: { fontSize: '10px' } } },
      legend: { position: 'top', horizontalAlign: 'left', fontSize: '11px' },
      tooltip: { shared: true }
    });
    dmsOverallPeopleTopChart.render();
  }

  function dmsOverallPeopleRenderDailyBar(chartData) {
    var el = document.getElementById('dms-overall-people-daily-bar');
    var totalEl = document.getElementById('dms-overall-people-daily-bar-total');
    if (dmsOverallPeopleDailyBarChart) { dmsOverallPeopleDailyBarChart.destroy(); dmsOverallPeopleDailyBarChart = null; }
    var totals = (chartData && chartData.totals) ? chartData.totals : {};
    if (totalEl) {
      totalEl.textContent = 'Tanpa alert ' + Number(totals.without_alert || 0).toLocaleString('id-ID') + ' / checkin ' + Number(totals.checkin || 0).toLocaleString('id-ID');
    }
    if (!el || typeof ApexCharts === 'undefined') return;
    el.innerHTML = '';
    var series = (chartData && chartData.series) ? chartData.series : [];
    if (!series.length) {
      el.innerHTML = '<p class="text-sm text-secondary-light mb-0">Tidak ada data harian pada periode ini.</p>';
      return;
    }
    dmsOverallPeopleDailyBarChart = new ApexCharts(el, {
      series: series,
      chart: {
        type: 'bar',
        height: 300,
        toolbar: { show: false },
        zoom: { enabled: false },
        events: {
          click: function (event, ctx, config) {
            if (config.dataPointIndex == null || config.dataPointIndex < 0) return;
            var isoDates = (chartData && chartData.iso_dates) ? chartData.iso_dates : [];
            var day = isoDates[config.dataPointIndex] || '';
            if (!day) return;
            var status = 'without_alert';
            if (config.seriesIndex === 1) status = 'with_alert';
            if (config.seriesIndex === 0) status = 'all';
            dmsOverallPeopleLoadDay(day, status, 1);
          }
        }
      },
      colors: ['#487fff', '#f4941e', '#45b369'],
      plotOptions: { bar: { borderRadius: 4, columnWidth: '60%', dataLabels: { position: 'top' } } },
      dataLabels: { enabled: false },
      xaxis: { categories: (chartData && chartData.labels) ? chartData.labels : [], labels: { rotate: -45, style: { fontSize: '10px' } } },
      yaxis: { labels: { formatter: function (v) { return Math.round(v); } } },
      legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
      tooltip: { y: { formatter: function (v) { return Number(v || 0).toLocaleString('id-ID') + ' orang'; } } }
    });
    dmsOverallPeopleDailyBarChart.render();
  }

  function dmsOverallPeopleSetDayLoading(show) {
    var loading = document.getElementById('dms-overall-people-day-loading');
    if (loading) loading.classList.toggle('d-none', !show);
  }

  function dmsOverallPeopleResetDayCard() {
    dmsOverallPeopleDayState = { day: '', status: 'without_alert', page: 1 };
    var title = document.getElementById('dms-overall-people-day-title');
    var hint = document.getElementById('dms-overall-people-day-hint');
    var count = document.getElementById('dms-overall-people-day-count');
    var body = document.getElementById('dms-overall-people-day-body');
    var empty = document.getElementById('dms-overall-people-day-empty');
    var pagination = document.getElementById('dms-overall-people-day-pagination');
    if (title) title.textContent = 'Detail Orang Harian';
    if (hint) hint.textContent = 'Klik batang hari di chart untuk memuat daftar orang.';
    if (count) count.textContent = '';
    if (body) body.innerHTML = '';
    if (empty) {
      empty.classList.remove('d-none');
      empty.textContent = 'Belum ada hari yang dipilih.';
    }
    if (pagination) {
      pagination.classList.add('d-none');
      pagination.classList.remove('d-flex');
    }
  }

  function dmsOverallPeopleRenderDayTable(payload) {
    var body = document.getElementById('dms-overall-people-day-body');
    var empty = document.getElementById('dms-overall-people-day-empty');
    var title = document.getElementById('dms-overall-people-day-title');
    var hint = document.getElementById('dms-overall-people-day-hint');
    var count = document.getElementById('dms-overall-people-day-count');
    var pagination = document.getElementById('dms-overall-people-day-pagination');
    var info = document.getElementById('dms-overall-people-day-page-info');
    var prev = document.getElementById('dms-overall-people-day-prev');
    var next = document.getElementById('dms-overall-people-day-next');
    if (title) title.textContent = payload.label || 'Detail Orang Harian';
    if (hint) hint.textContent = 'Hasil sesuai range filter dan tanggal batang yang diklik.';
    if (!body) return;
    body.innerHTML = '';
    var rows = (payload.table && payload.table.rows) ? payload.table.rows : [];
    if (!rows.length) {
      if (empty) {
        empty.classList.remove('d-none');
        empty.textContent = 'Tidak ada orang untuk filter hari ini.';
      }
    } else {
      if (empty) empty.classList.add('d-none');
      rows.forEach(function (row) {
        var alerts = Number(row.alert_count || 0);
        var hasAlert = !!row.has_alert && alerts > 0;
        var badge = hasAlert
          ? '<span class="badge bg-warning-focus text-warning-main border border-warning-main px-12 py-6">Ada alert</span>'
          : '<span class="badge bg-success-focus text-success-main border border-success-main px-12 py-6">Tidak ada alert</span>';
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td class="fw-medium">' + (row.nama || '-') + '</td>' +
          '<td>' + (row.kode_sid || '-') + '</td>' +
          '<td>' + (row.jabatan || '-') + '</td>' +
          '<td>' + (row.perusahaan || '-') + '</td>' +
          '<td>' + (row.site || '-') + '</td>' +
          '<td><div class="fw-medium text-sm">' + (row.evidence_source || 'RFID Check-in') + '</div>' +
            '<div class="text-xs text-secondary-light">' + (row.evidence_at || '-') + '</div>' +
            '<div class="text-xs text-primary-600">' + (row.evidence_note || '') + '</div></td>' +
          '<td>' + badge + '</td>' +
          '<td class="text-end fw-semibold">' + alerts.toLocaleString('id-ID') + '</td>';
        body.appendChild(tr);
      });
    }
    var paginationData = payload.pagination || {};
    if (count) count.textContent = Number(paginationData.total_rows || 0).toLocaleString('id-ID') + ' orang';
    if (pagination) {
      pagination.classList.remove('d-none');
      pagination.classList.add('d-flex');
    }
    if (info) info.textContent = 'Halaman ' + (paginationData.page || 1) + ' / ' + (paginationData.total_pages || 1);
    if (prev) prev.disabled = (paginationData.page || 1) <= 1;
    if (next) next.disabled = (paginationData.page || 1) >= (paginationData.total_pages || 1);
  }

  function dmsOverallPeopleLoadDay(day, status, page) {
    dmsOverallPeopleDayState.day = day || dmsOverallPeopleDayState.day;
    dmsOverallPeopleDayState.status = status || dmsOverallPeopleDayState.status || 'without_alert';
    dmsOverallPeopleDayState.page = page || 1;
    if (!dmsOverallPeopleDayState.day) return;
    dmsOverallPeopleSetDayLoading(true);
    var params = new URLSearchParams({
      start: dmsOverallFilters.start || '',
      end: dmsOverallFilters.end || '',
      site: dmsOverallFilters.site || '',
      perusahaan: dmsOverallFilters.perusahaan || '',
      day: dmsOverallPeopleDayState.day,
      status: dmsOverallPeopleDayState.status,
      page: String(dmsOverallPeopleDayState.page)
    });
    fetch(dmsOverallPeopleDayUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        dmsOverallPeopleSetDayLoading(false);
        if (!result.data.ok) {
          var empty = document.getElementById('dms-overall-people-day-empty');
          if (empty) {
            empty.classList.remove('d-none');
            empty.textContent = result.data.message || 'Gagal memuat detail hari.';
          }
          return;
        }
        dmsOverallPeopleRenderDayTable(result.data);
      })
      .catch(function () {
        dmsOverallPeopleSetDayLoading(false);
        var empty = document.getElementById('dms-overall-people-day-empty');
        if (empty) {
          empty.classList.remove('d-none');
          empty.textContent = 'Gagal memuat detail hari.';
        }
      });
  }

  function dmsOverallPeopleRenderTable(table) {
    var body = document.getElementById('dms-overall-people-table-body');
    var empty = document.getElementById('dms-overall-people-table-empty');
    if (!body) return;
    body.innerHTML = '';
    var rows = (table && table.rows) ? table.rows : [];
    if (!rows.length) {
      if (empty) empty.classList.remove('d-none');
      return;
    }
    if (empty) empty.classList.add('d-none');

    rows.forEach(function (row) {
      var tr = document.createElement('tr');
      var alerts = Number(row.alert_count || 0);
      var hasAlert = !!row.has_alert && alerts > 0;
      var detailId = 'dms-overall-people-alerts-' + (row.kode_sid || '-').replace(/[^a-zA-Z0-9_-]/g, '_');
      var evidenceHtml = '<div class="fw-medium text-sm">' + (row.evidence_source || 'RFID Check-in') + '</div>' +
        '<div class="text-xs text-secondary-light">' + (row.evidence_at || '-') + '</div>' +
        '<div class="text-xs text-primary-600 mt-4">' + (row.evidence_note || '') + '</div>';
      var badge = hasAlert
        ? '<span class="badge bg-warning-focus text-warning-main border border-warning-main px-12 py-6">Ada alert</span>'
        : '<span class="badge bg-success-focus text-success-main border border-success-main px-12 py-6">Tidak ada alert</span>';
      var detailButton = hasAlert
        ? '<button type="button" class="btn btn-sm btn-outline-primary dms-overall-people-toggle" data-target="' + detailId + '" data-sid="' + (row.kode_sid || '-') + '">Lihat alert</button>'
        : '<span class="text-xs text-secondary-light">-</span>';
      tr.innerHTML =
        '<td class="fw-medium">' + (row.nama || '-') + '</td>' +
        '<td>' + (row.kode_sid || '-') + '</td>' +
        '<td>' + (row.jabatan || '-') + '</td>' +
        '<td>' + (row.perusahaan || '-') + '</td>' +
        '<td>' + (row.site || '-') + '</td>' +
        '<td>' + evidenceHtml + '</td>' +
        '<td>' + badge + '</td>' +
        '<td class="text-end fw-semibold">' + alerts.toLocaleString('id-ID') + '</td>' +
        '<td>' + detailButton + '</td>';
      body.appendChild(tr);

      if (hasAlert) {
        var detailTr = document.createElement('tr');
        detailTr.id = detailId;
        detailTr.className = 'd-none';
        detailTr.innerHTML = '<td colspan="9" class="bg-neutral-50"><div class="p-12"><div class="text-xs text-secondary-light mb-8">Jenis alert pada orang ini</div><div class="d-flex flex-wrap gap-2" data-role="alert-list"><span class="text-sm text-secondary-light">Klik "Lihat alert" untuk memuat detail.</span></div></div></td>';
        body.appendChild(detailTr);
      }
    });

    body.querySelectorAll('.dms-overall-people-toggle').forEach(function (button) {
      button.addEventListener('click', function () {
        var targetId = button.getAttribute('data-target');
        var sid = button.getAttribute('data-sid') || '';
        var detailRow = targetId ? document.getElementById(targetId) : null;
        if (!detailRow) return;
        var isHidden = detailRow.classList.contains('d-none');
        if (isHidden) {
          detailRow.classList.remove('d-none');
          button.textContent = 'Memuat...';
          button.disabled = true;
          var listEl = detailRow.querySelector('[data-role="alert-list"]');
          var params = new URLSearchParams({
            start: dmsOverallFilters.start || '',
            end: dmsOverallFilters.end || '',
            site: dmsOverallFilters.site || '',
            perusahaan: dmsOverallFilters.perusahaan || '',
            sid: sid
          });
          fetch(dmsOverallPeopleAlertsUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
              if (!listEl) return;
              if (!result.ok || !result.data.ok) {
                listEl.innerHTML = '<span class="text-sm text-danger-main">Gagal memuat detail alert.</span>';
                button.textContent = 'Lihat alert';
                return;
              }
              var items = result.data.alerts || [];
              listEl.innerHTML = items.length
                ? items.map(function (item) { return '<span class="badge bg-primary-50 text-primary-600 border border-primary-100 px-12 py-6">' + (item.name || '-') + ' <span class="fw-semibold">(' + Number(item.total || 0).toLocaleString('id-ID') + ')</span></span>'; }).join('')
                : '<span class="text-sm text-secondary-light">Tidak ada detail alert.</span>';
              button.textContent = 'Sembunyikan alert';
            })
            .catch(function () {
              if (listEl) listEl.innerHTML = '<span class="text-sm text-danger-main">Gagal memuat detail alert.</span>';
              button.textContent = 'Lihat alert';
            })
            .finally(function () { button.disabled = false; });
        } else {
          detailRow.classList.add('d-none');
          button.textContent = 'Lihat alert';
        }
      });
    });
  }

  function dmsOverallPeopleRenderTabs(tabs, activeKey) {
    var wrap = document.getElementById('dms-overall-people-table-tabs');
    if (!wrap) return;
    (tabs || []).forEach(function (tab) {
      var button = wrap.querySelector('[data-status="' + (tab.key || '') + '"]');
      if (!button) return;
      var isActive = (tab.key || '') === activeKey;
      button.classList.toggle('btn-primary', isActive);
      button.classList.toggle('btn-outline-secondary', !isActive);
      var countEl = button.querySelector('span');
      if (countEl) countEl.textContent = Number(tab.count || 0).toLocaleString('id-ID');
    });
  }

  function dmsOverallPeopleRenderPagination(pagination) {
    var wrap = document.getElementById('dms-overall-people-pagination');
    var info = document.getElementById('dms-overall-people-page-info');
    var prev = document.getElementById('dms-overall-people-prev');
    var next = document.getElementById('dms-overall-people-next');
    var count = document.getElementById('dms-overall-people-table-count');
    if (!wrap || !pagination) return;
    wrap.classList.remove('d-none');
    if (count) count.textContent = Number(pagination.total_rows || 0).toLocaleString('id-ID') + ' orang';
    if (info) info.textContent = 'Halaman ' + pagination.page + ' / ' + pagination.total_pages;
    if (prev) prev.disabled = pagination.page <= 1;
    if (next) next.disabled = pagination.page >= pagination.total_pages;
  }

  function dmsOverallPeopleLoad() {
    dmsOverallPeopleSetLoading(true);
    document.getElementById('dms-overall-people-error').classList.add('d-none');
    document.getElementById('dms-overall-people-content').classList.add('d-none');
    var params = new URLSearchParams({
      start: dmsOverallFilters.start || '',
      end: dmsOverallFilters.end || '',
      site: dmsOverallFilters.site || '',
      perusahaan: dmsOverallFilters.perusahaan || '',
      page: String(dmsOverallPeopleState.page),
      status: dmsOverallPeopleState.status || 'with_alert'
    });
    fetch(dmsOverallPeopleUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        dmsOverallPeopleSetLoading(false);
        if (!result.data.ok) {
          document.getElementById('dms-overall-people-error').classList.remove('d-none');
          document.getElementById('dms-overall-people-error-message').textContent = result.data.message || 'Gagal memuat overview orang.';
          return;
        }
        var payload = result.data;
        document.getElementById('dmsOverallPeopleModalLabel').textContent = payload.label || 'Overview Orang & Alert';
        document.getElementById('dms-overall-people-subtitle').textContent = (payload.period && payload.period.start ? payload.period.start : '') + ' s/d ' + (payload.period && payload.period.end ? payload.period.end : '');
        dmsOverallPeopleRenderSummary(payload.summary || []);
        dmsOverallPeopleRenderDailyBar(payload.daily_bar || {});
        dmsOverallPeopleRenderTop(payload.top_units || []);
        dmsOverallPeopleRenderControlChart(payload.control_chart || {});
        dmsOverallPeopleRenderTopChart(payload.top_units_chart || {});
        dmsOverallPeopleRenderTable(payload.table || {});
        dmsOverallPeopleRenderTabs(payload.table_tabs || [], payload.table_active || 'with_alert');
        dmsOverallPeopleRenderPagination(payload.pagination || null);
        document.getElementById('dms-overall-people-content').classList.remove('d-none');
      })
      .catch(function () {
        dmsOverallPeopleSetLoading(false);
        document.getElementById('dms-overall-people-error').classList.remove('d-none');
        document.getElementById('dms-overall-people-error-message').textContent = 'Gagal memuat overview orang & alert.';
      });
  }

  function dmsOverallPeopleOpen() {
    dmsOverallPeopleState.page = 1;
    dmsOverallPeopleState.status = 'with_alert';
    dmsOverallPeopleDestroyCharts();
    dmsOverallPeopleResetDayCard();
    if (dmsOverallPeopleModal) dmsOverallPeopleModal.show();
    dmsOverallPeopleLoad();
  }

  document.querySelectorAll('.dms-kpi-card').forEach(function (card) {
    card.addEventListener('click', function () {
      if ((card.getAttribute('data-kpi-group') || 'unit') === 'people') {
        dmsOverallPeopleOpen();
      } else {
        dmsOverallOpen();
      }
    });
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if ((card.getAttribute('data-kpi-group') || 'unit') === 'people') {
          dmsOverallPeopleOpen();
        } else {
          dmsOverallOpen();
        }
      }
    });
  });

  var overallPrev = document.getElementById('dms-overall-prev');
  var overallNext = document.getElementById('dms-overall-next');
  if (overallPrev) overallPrev.addEventListener('click', function () {
    if (dmsOverallState.page > 1) { dmsOverallState.page--; dmsOverallLoad(); }
  });
  if (overallNext) overallNext.addEventListener('click', function () {
    dmsOverallState.page++; dmsOverallLoad();
  });

  var overallDayPrev = document.getElementById('dms-overall-day-prev');
  var overallDayNext = document.getElementById('dms-overall-day-next');
  if (overallDayPrev) overallDayPrev.addEventListener('click', function () {
    if (dmsOverallDayState.page > 1) {
      dmsOverallLoadDay(dmsOverallDayState.day, dmsOverallDayState.status, dmsOverallDayState.page - 1);
    }
  });
  if (overallDayNext) overallDayNext.addEventListener('click', function () {
    dmsOverallLoadDay(dmsOverallDayState.day, dmsOverallDayState.status, dmsOverallDayState.page + 1);
  });

  var overallTabWrap = document.getElementById('dms-overall-table-tabs');
  if (overallTabWrap) {
    overallTabWrap.querySelectorAll('[data-status]').forEach(function (button) {
      button.addEventListener('click', function () {
        var nextStatus = button.getAttribute('data-status') || 'with_alert';
        if (nextStatus === dmsOverallState.status) return;
        dmsOverallState.status = nextStatus;
        dmsOverallState.page = 1;
        dmsOverallLoad();
      });
    });
  }

  var overallPeoplePrev = document.getElementById('dms-overall-people-prev');
  var overallPeopleNext = document.getElementById('dms-overall-people-next');
  if (overallPeoplePrev) overallPeoplePrev.addEventListener('click', function () {
    if (dmsOverallPeopleState.page > 1) { dmsOverallPeopleState.page--; dmsOverallPeopleLoad(); }
  });
  if (overallPeopleNext) overallPeopleNext.addEventListener('click', function () {
    dmsOverallPeopleState.page++; dmsOverallPeopleLoad();
  });

  var overallPeopleDayPrev = document.getElementById('dms-overall-people-day-prev');
  var overallPeopleDayNext = document.getElementById('dms-overall-people-day-next');
  if (overallPeopleDayPrev) overallPeopleDayPrev.addEventListener('click', function () {
    if (dmsOverallPeopleDayState.page > 1) {
      dmsOverallPeopleLoadDay(dmsOverallPeopleDayState.day, dmsOverallPeopleDayState.status, dmsOverallPeopleDayState.page - 1);
    }
  });
  if (overallPeopleDayNext) overallPeopleDayNext.addEventListener('click', function () {
    dmsOverallPeopleLoadDay(dmsOverallPeopleDayState.day, dmsOverallPeopleDayState.status, dmsOverallPeopleDayState.page + 1);
  });

  var overallPeopleTabWrap = document.getElementById('dms-overall-people-table-tabs');
  if (overallPeopleTabWrap) {
    overallPeopleTabWrap.querySelectorAll('[data-status]').forEach(function (button) {
      button.addEventListener('click', function () {
        var nextStatus = button.getAttribute('data-status') || 'with_alert';
        if (nextStatus === dmsOverallPeopleState.status) return;
        dmsOverallPeopleState.status = nextStatus;
        dmsOverallPeopleState.page = 1;
        dmsOverallPeopleLoad();
      });
    });
  }

  if (dmsOverallModalEl) {
    dmsOverallModalEl.addEventListener('hidden.bs.modal', function () {
      dmsOverallDestroyCharts();
    });
  }
  if (dmsOverallPeopleModalEl) {
    dmsOverallPeopleModalEl.addEventListener('hidden.bs.modal', function () {
      dmsOverallPeopleDestroyCharts();
    });
  }

  if (dmsLazyWidgets) {
    var dmsWidgetFilters = @json($filters);
    var dmsWidgetQuery = new URLSearchParams({
      start: dmsWidgetFilters.start || '',
      end: dmsWidgetFilters.end || '',
      site: dmsWidgetFilters.site || '',
      perusahaan: dmsWidgetFilters.perusahaan || ''
    }).toString();

    function dmsLoadHtmlWidget(url, targetId) {
      var el = document.getElementById(targetId);
      if (!el) return;
      fetch(url + '?' + dmsWidgetQuery, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
        .then(function (res) { return res.text(); })
        .then(function (html) { el.innerHTML = html; })
        .catch(function () {
          el.innerHTML = '<div class="card border radius-8 p-24 text-center text-sm text-secondary-light">Gagal memuat widget.</div>';
        });
    }

    dmsLoadHtmlWidget(@json(route('pra-operasi.dms-monitoring.widget.quadrant')), 'dms-quadrant-widget');
    dmsLoadHtmlWidget(@json(route('pra-operasi.dms-monitoring.widget.control-room')), 'dms-control-room-widget');

    fetch(@json(route('pra-operasi.dms-monitoring.widget.growth')) + '?' + dmsWidgetQuery, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (res) { return res.json(); })
      .then(function (payload) {
        if (!payload.ok || !payload.growth) return;
        var g = payload.growth;
        var head = document.getElementById('dms-growth-head');
        if (head) {
          head.innerHTML = '<h6 class="mb-2 fw-bold text-lg">' + (g.total || '0') + '</h6>' +
            '<span class="' + ((g.delta && g.delta.class) || 'bg-success-focus text-success-main') + ' ps-12 pe-12 pt-2 pb-2 rounded-2 fw-medium text-sm">' +
            ((g.delta && g.delta.text) || '+0') + '</span>';
        }
        dmsRenderGrowthChart(g);
      })
      .catch(function () {
        var el = document.getElementById('revenue-chart');
        if (el) el.innerHTML = '<div class="text-center py-40 text-secondary-light text-sm">Gagal memuat grafik.</div>';
      });
  }
})();
</script>
@endsection
