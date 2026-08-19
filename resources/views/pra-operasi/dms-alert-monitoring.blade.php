@extends('dms.layouts.app')

@section('title', 'Dashboard')

@php
    $kpiList = $kpis ?? [];
    $kpiDeltaLabel = $kpiDeltaLabel ?? 'this week';
    $categories = $categories ?? [];
    $sites = $sites ?? [];
    $topOperators = $topOperators ?? [];
    $campaignIcons = [
        ['icon' => 'majesticons:mail', 'textClass' => 'text-orange', 'barClass' => 'bg-orange'],
        ['icon' => 'eva:globe-2-fill', 'textClass' => 'text-success-main', 'barClass' => 'bg-success-main'],
        ['icon' => 'fa6-brands:square-facebook', 'textClass' => 'text-info-main', 'barClass' => 'bg-info-main'],
        ['icon' => 'fluent:location-off-20-filled', 'textClass' => 'text-indigo', 'barClass' => 'bg-indigo'],
    ];
    $campaignFallbacks = ['Email', 'Website', 'Facebook', 'Email'];
    $campaigns = [];
    foreach ($campaignIcons as $i => $style) {
        $row = $categories[$i] ?? ['name' => $campaignFallbacks[$i], 'pct' => 0, 'total' => 0];
        $campaigns[] = $style + $row;
    }
    $flagFiles = ['flag1.png', 'flag2.png', 'flag3.png', 'flag4.png'];
    $countryBars = ['bg-primary-600', 'bg-orange', 'bg-yellow', 'bg-success-main'];
    $countryFallbacks = ['USA', 'Japan', 'France', 'Germany'];
    $countryRows = [];
    for ($i = 0; $i < 4; $i++) {
        $site = $sites[$i] ?? ['site' => $countryFallbacks[$i], 'total' => 0, 'pct' => 0];
        $countryRows[] = $site + ['flag' => $flagFiles[$i], 'barClass' => $countryBars[$i]];
    }
    $userFiles = ['user1.png', 'user2.png', 'user3.png', 'user4.png', 'user5.png', 'user1.png'];
    $performers = [];
    for ($i = 0; $i < 6; $i++) {
        $op = $topOperators[$i] ?? ['nama' => '-', 'kode_sid' => '-', 'confirmed' => 0, 'total' => 0];
        $performers[] = $op + ['photo' => $userFiles[$i]];
    }
    $allItems = $recentAll ?? [];
    $bestMatch = $recentConfirmed ?? [];
    $transactions = $recentReviews ?? [];
    $growth = $growth ?? ['series' => [], 'labels' => []];
    $statistic = $statistic ?? ['series' => [], 'labels' => []];
    $overview = $overview ?? ['confirmed' => 0, 'dismissed' => 0, 'pending' => 0];
    $weeklyStatus = $weeklyStatus ?? ['confirmed' => [], 'pending' => [], 'dismissed' => [], 'labels' => []];
@endphp

@section('css')
<link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/jquery-jvectormap-2.0.5.css') }}">
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

<div class="row gy-4">
  <div class="col-xxl-8">
    <div class="row gy-4">

      <div class="col-xxl-4 col-sm-6">
        <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-1">
          <div class="card-body p-0">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
              <div class="d-flex align-items-center gap-2">
                <span class="mb-0 w-48-px h-48-px bg-primary-600 flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
                  <iconify-icon icon="mingcute:user-follow-fill" class="icon"></iconify-icon>
                </span>
                <div>
                  <span class="mb-2 fw-medium text-secondary-light text-sm">New Users</span>
                  <h6 class="fw-semibold">{{ $kpiList[0]['value'] ?? '0' }}</h6>
                </div>
              </div>
              <div id="new-user-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
            </div>
            <p class="text-sm mb-0">Increase by  <span class="{{ $kpiList[0]['delta']['class'] ?? 'bg-success-focus text-success-main' }} px-1 rounded-2 fw-medium text-sm">{{ $kpiList[0]['delta']['text'] ?? '+0' }}</span> this week</p>
          </div>
        </div>
      </div>

      <div class="col-xxl-4 col-sm-6">
        <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-2">
          <div class="card-body p-0">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
              <div class="d-flex align-items-center gap-2">
                <span class="mb-0 w-48-px h-48-px bg-success-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6">
                  <iconify-icon icon="mingcute:user-follow-fill" class="icon"></iconify-icon>
                </span>
                <div>
                  <span class="mb-2 fw-medium text-secondary-light text-sm">Active Users</span>
                  <h6 class="fw-semibold">{{ $kpiList[1]['value'] ?? '0' }}</h6>
                </div>
              </div>
              <div id="active-user-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
            </div>
            <p class="text-sm mb-0">Increase by  <span class="{{ $kpiList[1]['delta']['class'] ?? 'bg-success-focus text-success-main' }} px-1 rounded-2 fw-medium text-sm">{{ $kpiList[1]['delta']['text'] ?? '+0' }}</span> this week</p>
          </div>
        </div>
      </div>

      <div class="col-xxl-4 col-sm-6">
        <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-3">
          <div class="card-body p-0">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
              <div class="d-flex align-items-center gap-2">
                <span class="mb-0 w-48-px h-48-px bg-yellow text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">
                  <iconify-icon icon="iconamoon:discount-fill" class="icon"></iconify-icon>
                </span>
                <div>
                  <span class="mb-2 fw-medium text-secondary-light text-sm">Total Sales</span>
                  <h6 class="fw-semibold">{{ $kpiList[2]['value'] ?? '0' }}</h6>
                </div>
              </div>
              <div id="total-sales-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
            </div>
            <p class="text-sm mb-0">Increase by  <span class="{{ $kpiList[2]['delta']['class'] ?? 'bg-danger-focus text-danger-main' }} px-1 rounded-2 fw-medium text-sm">{{ $kpiList[2]['delta']['text'] ?? '+0' }}</span> this week</p>
          </div>
        </div>
      </div>

      <div class="col-xxl-4 col-sm-6">
        <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-4">
          <div class="card-body p-0">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
              <div class="d-flex align-items-center gap-2">
                <span class="mb-0 w-48-px h-48-px bg-purple text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">
                  <iconify-icon icon="mdi:message-text" class="icon"></iconify-icon>
                </span>
                <div>
                  <span class="mb-2 fw-medium text-secondary-light text-sm">Conversion</span>
                  <h6 class="fw-semibold">{{ $kpiList[3]['value'] ?? '0%' }}</h6>
                </div>
              </div>
              <div id="conversion-user-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
            </div>
            <p class="text-sm mb-0">Increase by  <span class="{{ $kpiList[3]['delta']['class'] ?? 'bg-success-focus text-success-main' }} px-1 rounded-2 fw-medium text-sm">{{ $kpiList[3]['delta']['text'] ?? '+0' }}</span> this week</p>
          </div>
        </div>
      </div>

      <div class="col-xxl-4 col-sm-6">
        <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-5">
          <div class="card-body p-0">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
              <div class="d-flex align-items-center gap-2">
                <span class="mb-0 w-48-px h-48-px bg-pink text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">
                  <iconify-icon icon="mdi:leads" class="icon"></iconify-icon>
                </span>
                <div>
                  <span class="mb-2 fw-medium text-secondary-light text-sm">Leads</span>
                  <h6 class="fw-semibold">{{ $kpiList[4]['value'] ?? '0' }}</h6>
                </div>
              </div>
              <div id="leads-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
            </div>
            <p class="text-sm mb-0">Increase by  <span class="{{ $kpiList[4]['delta']['class'] ?? 'bg-success-focus text-success-main' }} px-1 rounded-2 fw-medium text-sm">{{ $kpiList[4]['delta']['text'] ?? '+0' }}</span> this week</p>
          </div>
        </div>
      </div>

      <div class="col-xxl-4 col-sm-6">
        <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-6">
          <div class="card-body p-0">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
              <div class="d-flex align-items-center gap-2">
                <span class="mb-0 w-48-px h-48-px bg-cyan text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">
                  <iconify-icon icon="streamline:bag-dollar-solid" class="icon"></iconify-icon>
                </span>
                <div>
                  <span class="mb-2 fw-medium text-secondary-light text-sm">Total Profit</span>
                  <h6 class="fw-semibold">{{ $kpiList[5]['value'] ?? '0' }}</h6>
                </div>
              </div>
              <div id="total-profit-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
            </div>
            <p class="text-sm mb-0">Increase by  <span class="{{ $kpiList[5]['delta']['class'] ?? 'bg-success-focus text-success-main' }} px-1 rounded-2 fw-medium text-sm">{{ $kpiList[5]['delta']['text'] ?? '+0' }}</span> this week</p>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="col-xxl-4">
    <div class="card h-100 radius-8 border">
      <div class="card-body p-24">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
          <div>
            <h6 class="mb-2 fw-bold text-lg">Revenue Growth</h6>
            <span class="text-sm fw-medium text-secondary-light">Weekly Report</span>
          </div>
          <div class="text-end">
            <h6 class="mb-2 fw-bold text-lg">{{ $growth['total'] ?? '0' }}</h6>
            <span class="{{ $growth['delta']['class'] ?? 'bg-success-focus text-success-main' }} ps-12 pe-12 pt-2 pb-2 rounded-2 fw-medium text-sm">{{ $growth['delta']['text'] ?? '+0' }}</span>
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
            <h6 class="mb-2 fw-bold text-lg">Earning Statistic</h6>
            <span class="text-sm fw-medium text-secondary-light">Yearly earning overview</span>
          </div>
          <div class="">
            <select class="form-select form-select-sm w-auto bg-base border text-secondary-light">
              <option>Yearly</option>
              <option>Monthly</option>
              <option>Weekly</option>
              <option>Today</option>
            </select>
          </div>
        </div>

        <div class="mt-20 d-flex justify-content-center flex-wrap gap-3">
          <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
            <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
              <iconify-icon icon="fluent:cart-16-filled" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="text-secondary-light text-sm fw-medium">Sales</span>
              <h6 class="text-md fw-semibold mb-0">{{ $statistic['total'] ?? '0' }}</h6>
            </div>
          </div>
          <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
            <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
              <iconify-icon icon="uis:chart" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="text-secondary-light text-sm fw-medium">Income</span>
              <h6 class="text-md fw-semibold mb-0">{{ $statistic['confirmed'] ?? '0' }}</h6>
            </div>
          </div>
          <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
            <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
              <iconify-icon icon="ph:arrow-fat-up-fill" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="text-secondary-light text-sm fw-medium">Profit</span>
              <h6 class="text-md fw-semibold mb-0">{{ $statistic['dismissed'] ?? '0' }}</h6>
            </div>
          </div>
        </div>

        <div id="barChart" class="barChart"></div>
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
              <div class="">
                <select class="form-select form-select-sm w-auto bg-base border text-secondary-light">
                  <option>Yearly</option>
                  <option>Monthly</option>
                  <option>Weekly</option>
                  <option>Today</option>
                </select>
              </div>
            </div>

            <div class="mt-3">
              @foreach($campaigns as $i => $cat)
              <div class="d-flex align-items-center justify-content-between gap-3 {{ $i < 3 ? 'mb-12' : '' }}">
                <div class="d-flex align-items-center">
                  <span class="text-xxl line-height-1 d-flex align-content-center flex-shrink-0 {{ $cat['textClass'] }}">
                    <iconify-icon icon="{{ $cat['icon'] }}" class="icon"></iconify-icon>
                  </span>
                  <span class="text-primary-light fw-medium text-sm ps-12">{{ $cat['name'] }}</span>
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
              @endforeach
            </div>
          </div>
        </div>
      </div>
      <div class="col-xxl-12 col-sm-6">
        <div class="card h-100 radius-8 border-0 overflow-hidden">
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
              <h6 class="mb-2 fw-bold text-lg">Customer Overview</h6>
              <div class="">
                <select class="form-select form-select-sm w-auto bg-base border text-secondary-light">
                  <option>Yearly</option>
                  <option>Monthly</option>
                  <option>Weekly</option>
                  <option>Today</option>
                </select>
              </div>
            </div>

            <div class="d-flex flex-wrap align-items-center mt-3">
              <ul class="flex-shrink-0">
                <li class="d-flex align-items-center gap-2 mb-28">
                  <span class="w-12-px h-12-px rounded-circle bg-success-main"></span>
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
          <div class="">
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
        <div class="">
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
@endsection

@section('scripts')
<script src="{{ asset('evaluasi-well-assets/js/lib/jquery-ui.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/jquery-jvectormap-2.0.5.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/jquery-jvectormap-world-mill-en.js') }}"></script>
<script>
(function () {
  var kpis = @json($kpiList);
  var growth = @json($growth);
  var statistic = @json($statistic);
  var overview = @json($overview);
  var weeklyStatus = @json($weeklyStatus);

  function createChart(chartId, chartColor, data) {
    var el = document.querySelector('#' + chartId);
    if (!el || typeof ApexCharts === 'undefined') return;
    var currentYear = new Date().getFullYear();
    new ApexCharts(el, {
      series: [{ name: 'series1', data: data && data.length ? data : [35, 45, 38, 41, 36, 43, 37, 55, 40] }],
      chart: { type: 'area', width: 80, height: 42, sparkline: { enabled: true }, toolbar: { show: false }, padding: { left: 0, right: 0, top: 0, bottom: 0 } },
      dataLabels: { enabled: false },
      stroke: { curve: 'smooth', width: 2, colors: [chartColor], lineCap: 'round' },
      grid: {
        show: true, borderColor: 'transparent', strokeDashArray: 0, position: 'back',
        xaxis: { lines: { show: false } }, yaxis: { lines: { show: false } },
        padding: { top: -3, right: 0, bottom: 0, left: 0 }
      },
      fill: {
        type: 'gradient', colors: [chartColor],
        gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.5, gradientToColors: [chartColor + '00'], inverseColors: false, opacityFrom: .75, opacityTo: 0.3, stops: [0, 100] }
      },
      markers: { colors: [chartColor], strokeWidth: 2, size: 0, hover: { size: 8 } },
      xaxis: { labels: { show: false }, categories: ['Jan ' + currentYear, 'Feb ' + currentYear, 'Mar ' + currentYear, 'Apr ' + currentYear, 'May ' + currentYear, 'Jun ' + currentYear, 'Jul ' + currentYear, 'Aug ' + currentYear, 'Sep ' + currentYear, 'Oct ' + currentYear, 'Nov ' + currentYear, 'Dec ' + currentYear], tooltip: { enabled: false } },
      yaxis: { labels: { show: false } }
    }).render();
  }

  createChart('new-user-chart', '#487fff', (kpis[0] && kpis[0].sparkline) || []);
  createChart('active-user-chart', '#45b369', (kpis[1] && kpis[1].sparkline) || []);
  createChart('total-sales-chart', '#f4941e', (kpis[2] && kpis[2].sparkline) || []);
  createChart('conversion-user-chart', '#8252e9', (kpis[3] && kpis[3].sparkline) || []);
  createChart('leads-chart', '#de3ace', (kpis[4] && kpis[4].sparkline) || []);
  createChart('total-profit-chart', '#00b8f2', (kpis[5] && kpis[5].sparkline) || []);

  var revenueEl = document.querySelector('#revenue-chart');
  if (revenueEl && typeof ApexCharts !== 'undefined') {
    new ApexCharts(revenueEl, {
      series: [{ name: 'This Day', data: growth.series && growth.series.length ? growth.series : [4, 18, 13, 40, 30, 50, 30, 60, 40, 75, 45, 90] }],
      chart: { type: 'area', width: '100%', height: 162, toolbar: { show: false }, padding: { left: 0, right: 0, top: 0, bottom: 0 } },
      dataLabels: { enabled: false },
      stroke: { curve: 'smooth', width: 2, colors: ['#487fff'], lineCap: 'round' },
      grid: {
        show: true, borderColor: 'red', strokeDashArray: 0, position: 'back',
        xaxis: { lines: { show: false } }, yaxis: { lines: { show: false } },
        padding: { top: -30, right: 0, bottom: -10, left: 0 }
      },
      fill: {
        type: 'gradient', colors: ['#487fff'],
        gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.5, gradientToColors: ['#487fff00'], inverseColors: false, opacityFrom: .6, opacityTo: 0.3, stops: [0, 100] }
      },
      markers: { colors: ['#487fff'], strokeWidth: 3, size: 0, hover: { size: 10 } },
      xaxis: { categories: growth.labels && growth.labels.length ? growth.labels : ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], labels: { show: false } },
      yaxis: { labels: { show: false } }
    }).render();
  }

  var barEl = document.querySelector('#barChart');
  if (barEl && typeof ApexCharts !== 'undefined') {
    var labels = statistic.labels && statistic.labels.length ? statistic.labels : ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var series = statistic.series && statistic.series.length ? statistic.series : [85000,70000,40000,50000,60000,50000,40000,50000,40000,60000,30000,50000];
    var barData = labels.map(function (label, i) { return { x: label, y: series[i] || 0 }; });
    new ApexCharts(barEl, {
      series: [{ name: 'Sales', data: barData }],
      chart: { type: 'bar', height: 310, toolbar: { show: false } },
      plotOptions: { bar: { borderRadius: 4, horizontal: false, columnWidth: '23%', endingShape: 'rounded' } },
      dataLabels: { enabled: false },
      fill: {
        type: 'gradient', colors: ['#487FFF'],
        gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.5, gradientToColors: ['#487FFF'], inverseColors: false, opacityFrom: 1, opacityTo: 1, stops: [0, 100] }
      },
      grid: { show: true, borderColor: '#D1D5DB', strokeDashArray: 4, position: 'back' },
      xaxis: { type: 'category', categories: labels }
    }).render();
  }

  var donutEl = document.querySelector('#donutChart');
  if (donutEl && typeof ApexCharts !== 'undefined') {
    var total = (overview.confirmed || 0) + (overview.dismissed || 0) + (overview.pending || 0);
    new ApexCharts(donutEl, {
      series: [total || 0, overview.pending || 0, overview.confirmed || 0],
      colors: ['#45B369', '#FF9F29', '#487FFF'],
      labels: ['Active', 'New', 'Total'],
      legend: { show: false },
      chart: { type: 'donut', height: 300, sparkline: { enabled: true }, margin: { top: -100, right: -100, bottom: -100, left: -100 }, padding: { top: -100, right: -100, bottom: -100, left: -100 } },
      stroke: { width: 0 },
      dataLabels: { enabled: false },
      plotOptions: {
        pie: {
          startAngle: -90, endAngle: 90, offsetY: 10, customScale: 0.8,
          donut: { size: '70%', labels: { show: true, total: { showAlways: true, show: true, label: 'Customer Report' } } }
        }
      }
    }).render();
  }

  var statusEl = document.querySelector('#paymentStatusChart');
  if (statusEl && typeof ApexCharts !== 'undefined') {
    new ApexCharts(statusEl, {
      series: [
        { name: 'Net Profit', data: weeklyStatus.confirmed && weeklyStatus.confirmed.length ? weeklyStatus.confirmed : [44, 100, 40, 56, 30, 58, 50] },
        { name: 'Revenue', data: weeklyStatus.pending && weeklyStatus.pending.length ? weeklyStatus.pending : [90, 140, 80, 125, 70, 140, 110] },
        { name: 'Free Cash', data: weeklyStatus.dismissed && weeklyStatus.dismissed.length ? weeklyStatus.dismissed : [60, 120, 60, 90, 50, 95, 90] }
      ],
      colors: ['#45B369', '#144bd6', '#FF9F29'],
      legend: { show: false },
      chart: { type: 'bar', height: 350, toolbar: { show: false } },
      grid: { show: true, borderColor: '#D1D5DB', strokeDashArray: 4, position: 'back' },
      plotOptions: { bar: { borderRadius: 4, columnWidth: 8 } },
      dataLabels: { enabled: false },
      states: { hover: { filter: { type: 'none' } } },
      stroke: { show: true, width: 0, colors: ['transparent'] },
      xaxis: { categories: weeklyStatus.labels && weeklyStatus.labels.length ? weeklyStatus.labels : ['Mon', 'Tues', 'Wed', 'Thurs', 'Fri', 'Sat', 'Sun'] },
      fill: { opacity: 1, width: 18 }
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
})();
</script>
@endsection
