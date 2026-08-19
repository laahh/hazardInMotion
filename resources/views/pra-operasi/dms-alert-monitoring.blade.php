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
    }
    $kpiDeltaLabel = $kpiDeltaLabel ?? 'this week';
    $campaigns = $campaigns ?? ($categories ?? []);
    $flagFiles = ['flag1.png', 'flag2.png', 'flag3.png', 'flag4.png'];
    $countryBars = ['bg-primary-600', 'bg-orange', 'bg-yellow', 'bg-success-main'];
    $countryRows = [];
    $sites = $sites ?? [];
    for ($i = 0; $i < 4; $i++) {
        $site = $sites[$i] ?? ['site' => '-', 'total' => 0, 'pct' => 0, 'confirmed' => 0];
        $countryRows[] = $site + ['flag' => $flagFiles[$i], 'barClass' => $site['barClass'] ?? $countryBars[$i]];
    }
    $userFiles = ['user1.png', 'user2.png', 'user3.png', 'user4.png', 'user5.png', 'user1.png'];
    $performers = [];
    $topOperators = $topOperators ?? [];
    for ($i = 0; $i < 6; $i++) {
        $op = $topOperators[$i] ?? ['nama' => '-', 'kode_sid' => '-', 'confirmed' => 0, 'total' => 0];
        $performers[] = $op + ['photo' => $userFiles[$i]];
    }
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
    $overview = $overview ?? ['confirmed' => 0, 'dismissed' => 0, 'pending' => 0];
    $weeklyStatus = $weeklyStatus ?? ['confirmed' => [], 'pending' => [], 'dismissed' => [], 'labels' => [], 'totals' => ['confirmed' => 0, 'pending' => 0, 'dismissed' => 0]];
    $filters = $filters ?? ['start' => '', 'end' => '', 'site' => '', 'perusahaan' => ''];
    $filterOptions = $filterOptions ?? ['sites' => [], 'companies' => []];
@endphp

@section('css')
<link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/jquery-jvectormap-2.0.5.css') }}">
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
    <div class="card h-100 radius-8 border">
      <div class="card-body p-24">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
          <div>
            <h6 class="mb-2 fw-bold text-lg">{{ $growth['title'] ?? 'Alert Last 4 Week' }}</h6>
            <span class="text-sm fw-medium text-secondary-light">{{ $growth['subtitle'] ?? 'Weekly Report' }}</span>
          </div>
          <div class="text-end">
            <h6 class="mb-2 fw-bold text-lg">{{ $growth['total'] }}</h6>
            <span class="{{ $growth['delta']['class'] }} ps-12 pe-12 pt-2 pb-2 rounded-2 fw-medium text-sm">{{ $growth['delta']['text'] }}</span>
          </div>
        </div>
        <div id="revenue-chart" class="mt-28"></div>
      </div>
    </div>
  </div>

  <div class="col-xxl-8">
    <div class="card h-100 radius-8 border-0">
      <div class="card-body p-24">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
          <div>
            <h6 class="mb-2 fw-bold text-lg">{{ $statistic['title'] ?? 'Matriks Site' }}</h6>
            <span class="text-sm fw-medium text-secondary-light">{{ $statistic['subtitle'] ?? 'Check-in vs Alert / Orang' }}</span>
          </div>
          <div>
            <span class="form-select form-select-sm w-auto bg-base border text-secondary-light d-inline-block pe-none">{{ $dateLabel }}</span>
          </div>
        </div>

        <div class="mt-20 d-flex justify-content-center flex-wrap gap-3">
          <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
            <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
              <iconify-icon icon="mingcute:user-follow-fill" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="text-secondary-light text-sm fw-medium">Total Check-in</span>
              <h6 class="text-md fw-semibold mb-0">{{ $statistic['confirmed'] }}</h6>
            </div>
          </div>
          <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
            <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
              <iconify-icon icon="solar:danger-triangle-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="text-secondary-light text-sm fw-medium">Total Alert</span>
              <h6 class="text-md fw-semibold mb-0">{{ $statistic['total'] }}</h6>
            </div>
          </div>
          <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
            <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
              <iconify-icon icon="solar:chart-2-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="text-secondary-light text-sm fw-medium">Alert / Orang</span>
              <h6 class="text-md fw-semibold mb-0">{{ $statistic['dismissed'] }}</h6>
            </div>
          </div>
        </div>

        <div class="mt-12 text-xs text-secondary-light text-center">
          Median check-in: <strong>{{ number_format((float) ($statistic['x_median'] ?? 0), 0) }}</strong>
          &nbsp;·&nbsp;
          Median alert/orang: <strong>{{ number_format((float) ($statistic['y_median'] ?? 0), 2) }}</strong>
        </div>

        <div class="dms-quadrant-wrap mt-16">
          <div class="dms-quadrant-y-title">Alert Intensity – Rasio Alert / Orang</div>
          <div class="dms-quadrant-axis-hint mb-4"><span>Tinggi</span></div>
          <div class="dms-quadrant-grid">
            @foreach($quadrantOrder as $qKey)
              @php
                $q = $statistic['quadrants'][$qKey] ?? [
                  'label' => $qKey,
                  'description' => '',
                  'bg' => '#f9fafb',
                  'border' => '#9CA3AF',
                  'text' => '#374151',
                  'icon' => 'solar:map-point-bold',
                  'sites' => [],
                ];
              @endphp
              <div class="dms-quadrant-cell" style="background: {{ $q['bg'] }};">
                <div>
                  <div class="dms-quadrant-cell-head">
                    <span class="dms-quadrant-cell-icon" style="background: {{ $q['bg'] }}; color: {{ $q['text'] }}; border: 1.5px solid {{ $q['border'] }};">
                      <iconify-icon icon="{{ $q['icon'] }}" class="icon"></iconify-icon>
                    </span>
                    <div>
                      <div class="dms-quadrant-cell-title" style="color: {{ $q['text'] }};">{{ $q['label'] }}</div>
                      <div class="dms-quadrant-cell-desc">{{ $q['description'] }}</div>
                    </div>
                  </div>
                </div>
                <div class="dms-quadrant-sites">
                  @forelse($q['sites'] ?? [] as $siteRow)
                    <span
                      class="dms-quadrant-pill"
                      style="color: {{ $q['text'] }};"
                      title="Check-in: {{ number_format($siteRow['checkin_count'] ?? 0) }} | Alert: {{ number_format($siteRow['alert_count'] ?? 0) }} | Rasio: {{ number_format((float) ($siteRow['ratio'] ?? 0), 2) }} | WoW: {{ ($siteRow['wow'] ?? 0) >= 0 ? '+' : '' }}{{ number_format((float) ($siteRow['wow'] ?? 0), 2) }}"
                    >{{ $siteRow['site'] ?? '-' }} {{ $siteRow['arrow'] ?? '' }}</span>
                  @empty
                    <span class="text-xs text-secondary-light">—</span>
                  @endforelse
                </div>
              </div>
            @endforeach
            <div class="dms-quadrant-center" title="Overall: {{ $statistic['confirmed'] }} check-in, {{ $statistic['total'] }} alert, {{ $statistic['dismissed'] }} alert/orang">Overall</div>
          </div>
          <div class="dms-quadrant-axis-hint"><span>Rendah</span><span>Tinggi</span></div>
          <div class="dms-quadrant-x-title">Exposure – Total Orang Check-in</div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4">
    <div class="row gy-4">
      <div class="col-xxl-12 col-sm-6">
        <div class="card h-100 radius-8 border-0">
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
              <h6 class="mb-2 fw-bold text-lg">Campaigns</h6>
              <span class="text-sm text-secondary-light">Weekly</span>
            </div>
            <div class="mt-3">
              @forelse($campaigns as $i => $cat)
              <div class="d-flex align-items-center justify-content-between gap-3 {{ $i < count($campaigns) - 1 ? 'mb-12' : '' }}">
                <div class="d-flex align-items-center" style="min-width: 120px;">
                  <span class="text-xxl line-height-1 d-flex align-content-center flex-shrink-0 {{ $cat['textClass'] }}">
                    <iconify-icon icon="{{ $cat['icon'] }}" class="icon"></iconify-icon>
                  </span>
                  <span class="text-primary-light fw-medium text-sm ps-12 text-truncate" title="{{ $cat['name'] }}">{{ $cat['name'] }}</span>
                </div>
                <div class="d-flex align-items-center gap-2 w-100">
                  <div class="w-100 max-w-66 ms-auto">
                    <div class="progress progress-sm rounded-pill" role="progressbar" aria-valuenow="{{ $cat['pct'] }}" aria-valuemin="0" aria-valuemax="100">
                      <div class="progress-bar {{ $cat['barClass'] }} rounded-pill" style="width: {{ $cat['pct'] }}%;"></div>
                    </div>
                  </div>
                  <span class="text-secondary-light font-xs fw-semibold">{{ $cat['pct'] }}%</span>
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
              <h6 class="mb-2 fw-bold text-lg">Customer Overview</h6>
              <span class="text-sm fw-medium text-secondary-light">Weekly</span>
            </div>
            <div class="d-flex flex-wrap align-items-center mt-3">
              <ul class="flex-shrink-0">
                <li class="d-flex align-items-center gap-2 mb-28">
                  <span class="w-12-px h-12-px rounded-circle bg-danger-main"></span>
                  <span class="text-secondary-light text-sm fw-medium">Total: {{ number_format(($overview['confirmed'] ?? 0) + ($overview['dismissed'] ?? 0) + ($overview['pending'] ?? 0)) }}</span>
                </li>
                <li class="d-flex align-items-center gap-2 mb-28">
                  <span class="w-12-px h-12-px rounded-circle bg-warning-main"></span>
                  <span class="text-secondary-light text-sm fw-medium">New: {{ number_format($overview['pending'] ?? 0) }}</span>
                </li>
                <li class="d-flex align-items-center gap-2">
                  <span class="w-12-px h-12-px rounded-circle bg-primary-600"></span>
                  <span class="text-secondary-light text-sm fw-medium">Active: {{ number_format($overview['confirmed'] ?? 0) }}</span>
                </li>
              </ul>
              <div id="donutChart" class="flex-grow-1 apexcharts-tooltip-z-none title-style circle-none"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4 col-sm-6">
    <div class="card h-100 radius-8 border-0">
      <div class="card-body p-24">
        <h6 class="mb-2 fw-bold text-lg">Client Payment Status</h6>
        <span class="text-sm fw-medium text-secondary-light">Weekly Report</span>
        <ul class="d-flex flex-wrap align-items-center justify-content-center mt-32">
          <li class="d-flex align-items-center gap-2 me-28">
            <span class="w-12-px h-12-px rounded-circle bg-success-main"></span>
            <span class="text-secondary-light text-sm fw-medium">Paid: {{ number_format($weeklyStatus['totals']['confirmed'] ?? 0) }}</span>
          </li>
          <li class="d-flex align-items-center gap-2 me-28">
            <span class="w-12-px h-12-px rounded-circle bg-info-main"></span>
            <span class="text-secondary-light text-sm fw-medium">Pending: {{ number_format($weeklyStatus['totals']['pending'] ?? 0) }}</span>
          </li>
          <li class="d-flex align-items-center gap-2">
            <span class="w-12-px h-12-px rounded-circle bg-warning-main"></span>
            <span class="text-secondary-light text-sm fw-medium">Overdue: {{ number_format($weeklyStatus['totals']['dismissed'] ?? 0) }}</span>
          </li>
        </ul>
        <div class="mt-40">
          <div id="paymentStatusChart" class="margin-16-minus"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4 col-sm-6">
    <div class="card radius-8 border-0">
      <div class="card-body">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
          <h6 class="mb-2 fw-bold text-lg">Countries Status</h6>
          <div>
            <select class="form-select form-select-sm w-auto bg-base border text-secondary-light">
              <option>Yearly</option>
              <option>Monthly</option>
              <option>Weekly</option>
              <option>Today</option>
            </select>
          </div>
        </div>
      </div>

      <div id="world-map"></div>

      <div class="card-body p-24 max-h-266-px scroll-sm overflow-y-auto">
        @foreach($countryRows as $i => $site)
        <div class="d-flex align-items-center justify-content-between gap-3 {{ $i < 3 ? 'mb-3 pb-2' : '' }}">
          <div class="d-flex align-items-center w-100">
            <img src="{{ asset('evaluasi-well-assets/images/flags/'.$site['flag']) }}" alt="" class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden">
            <div class="flex-grow-1">
              <h6 class="text-sm mb-0">{{ $site['site'] }}</h6>
              <span class="text-xs text-secondary-light fw-medium">{{ number_format($site['total']) }} Users</span>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2 w-100">
            <div class="w-100 max-w-66 ms-auto">
              <div class="progress progress-sm rounded-pill" role="progressbar" aria-valuenow="{{ $site['pct'] }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar {{ $site['barClass'] }} rounded-pill" style="width: {{ $site['pct'] }}%;"></div>
              </div>
            </div>
            <span class="text-secondary-light font-xs fw-semibold">{{ $site['pct'] }}%</span>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="col-xxl-4">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
          <h6 class="mb-2 fw-bold text-lg mb-0">Top Performer</h6>
          <a href="javascript:void(0)" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
            View All
            <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
          </a>
        </div>

        <div class="mt-32">
          @foreach($performers as $i => $op)
          <div class="d-flex align-items-center justify-content-between gap-3 {{ $i < 5 ? 'mb-32' : '' }}">
            <div class="d-flex align-items-center">
              <img src="{{ asset('evaluasi-well-assets/images/users/'.$op['photo']) }}" alt="" class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden">
              <div class="flex-grow-1">
                <h6 class="text-md mb-0">{{ $op['nama'] }}</h6>
                <span class="text-sm text-secondary-light fw-medium">Agent ID: {{ $op['kode_sid'] }}</span>
              </div>
            </div>
            <span class="text-primary-light text-md fw-medium">{{ $op['confirmed'] }}/{{ $op['total'] }}</span>
          </div>
          @endforeach
        </div>
      </div>
    </div>
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

@include('pra-operasi.partials._dms-kpi-detail-modal')
@endsection

@section('scripts')
<script src="{{ asset('evaluasi-well-assets/js/lib/jquery-ui.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/jquery-jvectormap-2.0.5.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/jquery-jvectormap-world-mill-en.js') }}"></script>
<script>
(function () {
  var kpis = @json($kpis);
  var growth = @json($growth);
  var statistic = @json($statistic);
  var overview = @json($overview);
  var weeklyStatus = @json($weeklyStatus);
  var sites = @json($sites);

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

  var revenueEl = document.querySelector('#revenue-chart');
  if (revenueEl && typeof ApexCharts !== 'undefined') {
    new ApexCharts(revenueEl, {
      series: [{ name: 'Alert', data: growth.series || [] }],
      chart: { type: 'area', width: '100%', height: 162, toolbar: { show: false } },
      dataLabels: { enabled: false },
      stroke: { curve: 'smooth', width: 2, colors: ['#487fff'], lineCap: 'round' },
      grid: {
        show: true,
        borderColor: 'transparent',
        strokeDashArray: 0,
        xaxis: { lines: { show: false } },
        yaxis: { lines: { show: false } },
        padding: { top: -30, right: 0, bottom: -10, left: 0 }
      },
      fill: {
        type: 'gradient',
        colors: ['#487fff'],
        gradient: {
          shade: 'light',
          type: 'vertical',
          shadeIntensity: 0.5,
          gradientToColors: ['#487fff00'],
          inverseColors: false,
          opacityFrom: 0.6,
          opacityTo: 0.3,
          stops: [0, 100]
        }
      },
      markers: { colors: ['#487fff'], strokeWidth: 3, size: 0, hover: { size: 10 } },
      xaxis: { categories: growth.labels || [], labels: { show: true, style: { fontSize: '10px', colors: '#6B7280' } } },
      yaxis: { labels: { show: false } },
      tooltip: { y: { formatter: function (v) { return v + ' alert'; } } }
    }).render();
  }

  var donutEl = document.querySelector('#donutChart');
  if (donutEl && typeof ApexCharts !== 'undefined') {
    new ApexCharts(donutEl, {
      series: [((overview.confirmed || 0) + (overview.dismissed || 0) + (overview.pending || 0)), overview.pending || 0, overview.confirmed || 0],
      colors: ['#45B369', '#FF9F29', '#487FFF'],
      labels: ['Active', 'New', 'Total'],
      legend: { show: false },
      chart: { type: 'donut', height: 300, sparkline: { enabled: true } },
      stroke: { width: 0 },
      dataLabels: { enabled: false },
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
              total: { showAlways: true, show: true, label: 'Customer Report' }
            }
          }
        }
      }
    }).render();
  }

  var statusEl = document.querySelector('#paymentStatusChart');
  if (statusEl && typeof ApexCharts !== 'undefined') {
    new ApexCharts(statusEl, {
      series: [
        { name: 'Net Profit', data: weeklyStatus.confirmed || [] },
        { name: 'Revenue', data: weeklyStatus.pending || [] },
        { name: 'Free Cash', data: weeklyStatus.dismissed || [] }
      ],
      colors: ['#45B369', '#144bd6', '#FF9F29'],
      legend: { show: false },
      chart: { type: 'bar', height: 350, toolbar: { show: false } },
      grid: { show: true, borderColor: '#D1D5DB', strokeDashArray: 4, position: 'back' },
      plotOptions: { bar: { borderRadius: 4, columnWidth: 8 } },
      dataLabels: { enabled: false },
      states: { hover: { filter: { type: 'none' } } },
      stroke: { show: true, width: 0, colors: ['transparent'] },
      xaxis: { categories: weeklyStatus.labels || [] },
      fill: { opacity: 1 }
    }).render();
  }

  if (window.jQuery && jQuery.fn.vectorMap && document.getElementById('world-map')) {
    jQuery('#world-map').vectorMap({
      map: 'world_mill_en',
      backgroundColor: 'transparent',
      borderColor: '#fff',
      borderOpacity: 0.25,
      borderWidth: 0,
      color: '#000000',
      regionStyle: { initial: { fill: '#D1D5DB' } },
      markerStyle: { initial: { r: 5, fill: '#fff', 'fill-opacity': 1, stroke: '#000', 'stroke-width': 1, 'stroke-opacity': 0.4 } },
      markers: [
        { latLng: [35.8617, 104.1954], name: 'China : 250' },
        { latLng: [25.2744, 133.7751], name: 'Australia : 250' },
        { latLng: [36.77, -119.41], name: 'USA : 82%' },
        { latLng: [55.37, -3.41], name: 'UK : 250' },
        { latLng: [25.20, 55.27], name: 'UAE : 250' }
      ],
      series: { regions: [{ values: { US: '#487FFF', SA: '#487FFF', AU: '#487FFF', CN: '#487FFF', GB: '#487FFF', ID: '#487FFF' }, attribute: 'fill' }] },
      hoverOpacity: null,
      normalizeFunction: 'linear',
      zoomOnScroll: false,
      scaleColors: ['#000000', '#000000'],
      selectedColor: '#000000',
      selectedRegions: [],
      enableZoom: false,
      hoverColor: '#fff'
    });
  }

  var dmsKpiFilters = @json($filters);
  var dmsKpiDetailUrl = @json(route('pra-operasi.dms-monitoring.kpi-detail', ['metric' => '__METRIC__']));
  var dmsKpiModalEl = document.getElementById('dmsKpiDetailModal');
  var dmsKpiModal = dmsKpiModalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(dmsKpiModalEl) : null;
  var dmsKpiState = { metric: '', level: 'sites', parentSite: '', parentCompany: '', page: 1 };

  function dmsKpiDetailEndpoint(metric) {
    return dmsKpiDetailUrl.replace('__METRIC__', encodeURIComponent(metric));
  }

  function dmsKpiSetLoading(show) {
    var loading = document.getElementById('dms-kpi-detail-loading');
    if (loading) loading.classList.toggle('d-none', !show);
    if (loading) loading.classList.toggle('d-flex', show);
  }

  function dmsKpiRenderBreadcrumb(crumbs) {
    var ol = document.querySelector('#dms-kpi-detail-breadcrumb ol');
    if (!ol) return;
    ol.innerHTML = '';
    (crumbs || []).forEach(function (crumb, idx) {
      var li = document.createElement('li');
      li.className = 'breadcrumb-item' + (idx === crumbs.length - 1 ? ' active' : '');
      if (idx === crumbs.length - 1) {
        li.textContent = crumb.label;
        li.setAttribute('aria-current', 'page');
      } else {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-link btn-sm p-0 text-decoration-none';
        btn.textContent = crumb.label;
        btn.addEventListener('click', function () {
          dmsKpiState.level = crumb.level || 'sites';
          dmsKpiState.parentSite = crumb.parent_site || '';
          dmsKpiState.parentCompany = crumb.parent_company || '';
          dmsKpiState.page = 1;
          dmsKpiLoadDetail();
        });
        li.appendChild(btn);
      }
      ol.appendChild(li);
    });
  }

  function dmsKpiRenderSummary(summary, metric) {
    var wrap = document.getElementById('dms-kpi-detail-summary');
    var footnote = document.getElementById('dms-kpi-detail-footnote');
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!summary || !summary.length) {
      wrap.classList.add('d-none');
      if (footnote) footnote.classList.add('d-none');
      return;
    }
    wrap.classList.remove('d-none');
    if (footnote) footnote.classList.toggle('d-none', metric !== 'units_operating');
    summary.forEach(function (item) {
      var col = document.createElement('div');
      col.className = 'col-md-4';
      col.innerHTML = '<div class="border radius-8 p-16 h-100"><div class="text-sm text-secondary-light mb-4">' + item.label + '</div><div class="fw-bold text-lg">' + item.value + '</div><div class="text-xs text-secondary-light mt-4">' + (item.hint || '') + '</div></div>';
      wrap.appendChild(col);
    });
  }

  function dmsKpiMetaText(meta) {
    if (!meta) return '';
    var parts = [];
    if (meta.alert_count != null) parts.push('Alert: ' + meta.alert_count);
    if (meta.checkin_count != null) parts.push('Check-in: ' + meta.checkin_count);
    if (meta.unit_count != null) parts.push('Unit: ' + meta.unit_count);
    return parts.join(' · ');
  }

  function dmsKpiRenderTable(payload) {
    var head = document.getElementById('dms-kpi-detail-head');
    var body = document.getElementById('dms-kpi-detail-body');
    var empty = document.getElementById('dms-kpi-detail-empty');
    if (!head || !body) return;

    head.innerHTML = '';
    body.innerHTML = '';
    (payload.columns || []).forEach(function (col) {
      var th = document.createElement('th');
      th.scope = 'col';
      th.textContent = col.label;
      head.appendChild(th);
    });

    var rows = payload.rows || [];
    if (!rows.length) {
      if (empty) empty.classList.remove('d-none');
      return;
    }
    if (empty) empty.classList.add('d-none');

    rows.forEach(function (row) {
      var tr = document.createElement('tr');
      if (payload.drillable && row.drill) {
        tr.className = 'cursor-pointer';
        tr.addEventListener('click', function () {
          dmsKpiState.level = row.drill.level;
          dmsKpiState.parentSite = row.drill.parent_site || '';
          dmsKpiState.parentCompany = row.drill.parent_company || '';
          dmsKpiState.page = 1;
          dmsKpiLoadDetail();
        });
      }

      if (payload.level === 'sites' || payload.level === 'companies') {
        var tdLabel = document.createElement('td');
        tdLabel.innerHTML = '<div class="fw-medium">' + (row.label || '-') + '</div><div class="text-xs text-secondary-light">' + dmsKpiMetaText(row.meta) + '</div>';
        tr.appendChild(tdLabel);
        var tdValue = document.createElement('td');
        tdValue.className = 'text-end fw-semibold';
        tdValue.textContent = row.value != null ? row.value : '-';
        tr.appendChild(tdValue);
      } else {
        (payload.columns || []).forEach(function (col) {
          var td = document.createElement('td');
          td.textContent = row[col.key] != null ? row[col.key] : '-';
          tr.appendChild(td);
        });
      }

      body.appendChild(tr);
    });
  }

  function dmsKpiRenderPagination(pagination) {
    var wrap = document.getElementById('dms-kpi-detail-pagination');
    var info = document.getElementById('dms-kpi-detail-page-info');
    var prev = document.getElementById('dms-kpi-detail-prev');
    var next = document.getElementById('dms-kpi-detail-next');
    if (!wrap) return;

    if (!pagination) {
      wrap.classList.add('d-none');
      return;
    }

    wrap.classList.remove('d-none');
    if (info) info.textContent = 'Halaman ' + pagination.page + ' / ' + pagination.total_pages + ' (' + pagination.total_rows + ' baris)';
    if (prev) prev.disabled = pagination.page <= 1;
    if (next) next.disabled = pagination.page >= pagination.total_pages;
  }

  function dmsKpiLoadDetail() {
    if (!dmsKpiState.metric) return;
    dmsKpiSetLoading(true);
    document.getElementById('dms-kpi-detail-error').classList.add('d-none');

    var params = new URLSearchParams({
      start: dmsKpiFilters.start || '',
      end: dmsKpiFilters.end || '',
      site: dmsKpiFilters.site || '',
      perusahaan: dmsKpiFilters.perusahaan || '',
      level: dmsKpiState.level,
      page: String(dmsKpiState.page)
    });
    if (dmsKpiState.parentSite) params.set('parent_site', dmsKpiState.parentSite);
    if (dmsKpiState.parentCompany) params.set('parent_company', dmsKpiState.parentCompany);

    fetch(dmsKpiDetailEndpoint(dmsKpiState.metric) + '?' + params.toString(), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        dmsKpiSetLoading(false);
        if (!result.data.ok) {
          document.getElementById('dms-kpi-detail-error').classList.remove('d-none');
          document.getElementById('dms-kpi-detail-error-message').textContent = result.data.message || 'Gagal memuat detail.';
          return;
        }
        var payload = result.data;
        document.getElementById('dmsKpiDetailModalLabel').textContent = payload.label || 'Detail KPI';
        document.getElementById('dms-kpi-detail-subtitle').textContent = (dmsKpiFilters.start || '') + ' s/d ' + (dmsKpiFilters.end || '');
        document.getElementById('dms-kpi-detail-total').textContent = payload.total || '0';
        document.getElementById('dms-kpi-detail-hint').textContent = payload.level === 'rows'
          ? 'Klik baris site/perusahaan di level atas untuk drill-down.'
          : (payload.drillable ? 'Klik baris untuk lihat breakdown berikutnya.' : '');
        dmsKpiRenderBreadcrumb(payload.breadcrumb || []);
        dmsKpiRenderSummary(payload.summary || [], payload.metric || '');
        dmsKpiRenderTable(payload);
        dmsKpiRenderPagination(payload.pagination || null);
      })
      .catch(function () {
        dmsKpiSetLoading(false);
        document.getElementById('dms-kpi-detail-error').classList.remove('d-none');
        document.getElementById('dms-kpi-detail-error-message').textContent = 'Gagal memuat detail KPI.';
      });
  }

  function dmsKpiOpenDetail(metric, label) {
    dmsKpiState.metric = metric;
    dmsKpiState.level = 'sites';
    dmsKpiState.parentSite = '';
    dmsKpiState.parentCompany = '';
    dmsKpiState.page = 1;
    document.getElementById('dmsKpiDetailModalLabel').textContent = label || 'Detail KPI';
    if (dmsKpiModal) dmsKpiModal.show();
    dmsKpiLoadDetail();
  }

  document.querySelectorAll('.dms-kpi-card').forEach(function (card) {
    card.addEventListener('click', function () {
      dmsKpiOpenDetail(card.getAttribute('data-kpi-metric'), card.getAttribute('data-kpi-label'));
    });
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        dmsKpiOpenDetail(card.getAttribute('data-kpi-metric'), card.getAttribute('data-kpi-label'));
      }
    });
  });

  var prevBtn = document.getElementById('dms-kpi-detail-prev');
  var nextBtn = document.getElementById('dms-kpi-detail-next');
  if (prevBtn) prevBtn.addEventListener('click', function () {
    if (dmsKpiState.page > 1) { dmsKpiState.page--; dmsKpiLoadDetail(); }
  });
  if (nextBtn) nextBtn.addEventListener('click', function () {
    dmsKpiState.page++; dmsKpiLoadDetail();
  });
})();
</script>
@endsection
