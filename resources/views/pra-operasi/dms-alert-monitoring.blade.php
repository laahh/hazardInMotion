@extends('dms.layouts.app')

@section('title', 'Dashboard')

@php
    $kpis = $kpis ?? [];
    foreach ($kpis as $i => $kpi) {
        if (! is_array($kpi)) {
            continue;
        }
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
    $growth = $growth ?? ['title' => 'Revenue Growth', 'subtitle' => 'Weekly Report', 'total' => '0', 'delta' => ['class' => 'bg-success-focus text-success-main', 'text' => '+0'], 'series' => [], 'labels' => []];
    $statistic = $statistic ?? ['title' => 'Earning Statistic', 'subtitle' => 'Yearly earning overview', 'total' => '0', 'confirmed' => '0', 'dismissed' => '0', 'series' => [], 'labels' => []];
    $overview = $overview ?? ['confirmed' => 0, 'dismissed' => 0, 'pending' => 0];
    $weeklyStatus = $weeklyStatus ?? ['confirmed' => [], 'pending' => [], 'dismissed' => [], 'labels' => [], 'totals' => ['confirmed' => 0, 'pending' => 0, 'dismissed' => 0]];
    $filters = $filters ?? ['start' => '', 'end' => '', 'site' => '', 'perusahaan' => ''];
    $filterOptions = $filterOptions ?? ['sites' => [], 'companies' => []];
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
        <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 {{ $kpi['gradient'] }}">
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
            <h6 class="mb-2 fw-bold text-lg">Revenue Growth</h6>
            <span class="text-sm fw-medium text-secondary-light">Weekly Report</span>
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
            <h6 class="mb-2 fw-bold text-lg">Earning Statistic</h6>
            <span class="text-sm fw-medium text-secondary-light">Yearly earning overview</span>
          </div>
          <div>
            <span class="form-select form-select-sm w-auto bg-base border text-secondary-light d-inline-block pe-none">{{ $dateLabel }}</span>
          </div>
        </div>

        <div class="mt-20 d-flex justify-content-center flex-wrap gap-3">
          <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
            <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
              <iconify-icon icon="solar:danger-triangle-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="text-secondary-light text-sm fw-medium">Sales</span>
              <h6 class="text-md fw-semibold mb-0">{{ $statistic['total'] }}</h6>
            </div>
          </div>
          <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
            <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
              <iconify-icon icon="solar:shield-check-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="text-secondary-light text-sm fw-medium">Income</span>
              <h6 class="text-md fw-semibold mb-0">{{ $statistic['confirmed'] }}</h6>
            </div>
          </div>
          <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
            <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
              <iconify-icon icon="solar:check-circle-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="text-secondary-light text-sm fw-medium">Profit</span>
              <h6 class="text-md fw-semibold mb-0">{{ $statistic['dismissed'] }}</h6>
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
      xaxis: { categories: growth.labels || [], labels: { show: false } },
      yaxis: { labels: { show: false } },
      tooltip: { y: { formatter: function (v) { return v + ' alert'; } } }
    }).render();
  }

  var barEl = document.querySelector('#barChart');
  if (barEl && typeof ApexCharts !== 'undefined') {
    var barData = (statistic.labels || []).map(function (label, i) {
      return { x: label, y: (statistic.series || [])[i] || 0 };
    });
    new ApexCharts(barEl, {
      series: [{ name: 'Alert', data: barData }],
      chart: { type: 'bar', height: 310, toolbar: { show: false } },
      plotOptions: { bar: { borderRadius: 4, horizontal: false, columnWidth: '23%' } },
      dataLabels: { enabled: false },
      fill: {
        type: 'gradient',
        colors: ['#487FFF'],
        gradient: {
          shade: 'light',
          type: 'vertical',
          shadeIntensity: 0.5,
          gradientToColors: ['#487FFF'],
          inverseColors: false,
          opacityFrom: 1,
          opacityTo: 1,
          stops: [0, 100]
        }
      },
      grid: { show: true, borderColor: '#D1D5DB', strokeDashArray: 4, position: 'back' },
      xaxis: { type: 'category', categories: statistic.labels || [] },
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
})();
</script>
@endsection
