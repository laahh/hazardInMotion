@extends('dms.layouts.app')

@section('title', $pageTitle ?? 'Dashboard DMS')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">{{ $pageTitle ?? 'Dashboard' }}</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ $breadcrumbParentUrl ?? route('dms.dashboard') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">{{ $breadcrumbCurrent ?? 'DMS' }}</li>
  </ul>
</div>

@if(!empty($showDateFilter) && !empty($filters))
<form method="GET" class="d-flex flex-wrap align-items-end gap-2 mb-24">
  <div>
    <label class="form-label text-sm fw-medium mb-1">Dari Tanggal</label>
    <input type="date" name="start" value="{{ $filters['start'] }}" class="form-control form-control-sm" style="min-width:150px">
  </div>
  <div>
    <label class="form-label text-sm fw-medium mb-1">Sampai Tanggal</label>
    <input type="date" name="end" value="{{ $filters['end'] }}" class="form-control form-control-sm" style="min-width:150px">
  </div>
  <button type="submit" class="btn btn-primary-600 btn-sm radius-8 px-16">
    <iconify-icon icon="solar:filter-bold" class="me-1"></iconify-icon>Terapkan
  </button>
</form>
@endif

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
            <p class="text-sm mb-0">{{ $kpiDeltaLabel ?? 'vs kemarin' }} <span class="{{ $kpi['delta']['class'] }} px-1 rounded-2 fw-medium text-sm">{{ $kpi['delta']['text'] }}</span></p>
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
            <h6 class="mb-2 fw-bold text-lg">{{ $growth['title'] }}</h6>
            <span class="text-sm fw-medium text-secondary-light">{{ $growth['subtitle'] }}</span>
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
            <h6 class="mb-2 fw-bold text-lg">{{ $statistic['title'] }}</h6>
            <span class="text-sm fw-medium text-secondary-light">{{ $statistic['subtitle'] }}</span>
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
              <span class="text-secondary-light text-sm fw-medium">Total</span>
              <h6 class="text-md fw-semibold mb-0">{{ $statistic['total'] }}</h6>
            </div>
          </div>
          <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
            <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
              <iconify-icon icon="solar:shield-check-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="text-secondary-light text-sm fw-medium">Confirmed</span>
              <h6 class="text-md fw-semibold mb-0">{{ $statistic['confirmed'] }}</h6>
            </div>
          </div>
          <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
            <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
              <iconify-icon icon="solar:check-circle-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="text-secondary-light text-sm fw-medium">Dismissed</span>
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
              <h6 class="mb-2 fw-bold text-lg">Kategori Alert</h6>
              <span class="text-sm text-secondary-light">7 hari</span>
            </div>
            <div class="mt-3">
              @forelse($categories as $i => $cat)
              <div class="d-flex align-items-center justify-content-between gap-3 {{ $i < count($categories) - 1 ? 'mb-12' : '' }}">
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
                  <span class="text-secondary-light font-xs fw-semibold">{{ number_format($cat['total']) }}</span>
                </div>
              </div>
              @empty
              <p class="text-secondary-light text-sm mb-0">Belum ada kategori alert.</p>
              @endforelse
            </div>
          </div>
        </div>
      </div>
      <div class="col-xxl-12 col-sm-6">
        <div class="card h-100 radius-8 border-0 overflow-hidden">
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
              <h6 class="mb-2 fw-bold text-lg">Ringkasan Review</h6>
              <span class="text-sm fw-medium text-secondary-light">7 hari</span>
            </div>
            <div class="d-flex flex-wrap align-items-center mt-3">
              <ul class="flex-shrink-0">
                <li class="d-flex align-items-center gap-2 mb-28">
                  <span class="w-12-px h-12-px rounded-circle bg-danger-main"></span>
                  <span class="text-secondary-light text-sm fw-medium">Confirmed: {{ number_format($overview['confirmed']) }}</span>
                </li>
                <li class="d-flex align-items-center gap-2 mb-28">
                  <span class="w-12-px h-12-px rounded-circle bg-success-main"></span>
                  <span class="text-secondary-light text-sm fw-medium">Dismissed: {{ number_format($overview['dismissed']) }}</span>
                </li>
                <li class="d-flex align-items-center gap-2">
                  <span class="w-12-px h-12-px rounded-circle bg-warning-main"></span>
                  <span class="text-secondary-light text-sm fw-medium">Pending: {{ number_format($overview['pending']) }}</span>
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
        <h6 class="mb-2 fw-bold text-lg">Status Review Harian</h6>
        <span class="text-sm fw-medium text-secondary-light">7 hari terakhir</span>
        <ul class="d-flex flex-wrap align-items-center justify-content-center mt-32">
          <li class="d-flex align-items-center gap-2 me-28">
            <span class="w-12-px h-12-px rounded-circle bg-danger-main"></span>
            <span class="text-secondary-light text-sm fw-medium">Confirmed: {{ number_format($weeklyStatus['totals']['confirmed']) }}</span>
          </li>
          <li class="d-flex align-items-center gap-2 me-28">
            <span class="w-12-px h-12-px rounded-circle bg-info-main"></span>
            <span class="text-secondary-light text-sm fw-medium">Pending: {{ number_format($weeklyStatus['totals']['pending']) }}</span>
          </li>
          <li class="d-flex align-items-center gap-2">
            <span class="w-12-px h-12-px rounded-circle bg-success-main"></span>
            <span class="text-secondary-light text-sm fw-medium">Dismissed: {{ number_format($weeklyStatus['totals']['dismissed']) }}</span>
          </li>
        </ul>
        <div class="mt-40">
          <div id="paymentStatusChart" class="margin-16-minus"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4 col-sm-6">
    <div class="card radius-8 border-0 h-100">
      <div class="card-body">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
          <h6 class="mb-2 fw-bold text-lg">Status Site</h6>
          <span class="text-sm text-secondary-light">7 hari</span>
        </div>
      </div>
      <div class="card-body pt-0">
        <div id="siteShareChart"></div>
      </div>
      <div class="card-body p-24 pt-0 max-h-266-px scroll-sm overflow-y-auto">
        @forelse($sites as $i => $site)
        <div class="d-flex align-items-center justify-content-between gap-3 {{ $i < count($sites) - 1 ? 'mb-3 pb-2' : '' }}">
          <div class="d-flex align-items-center w-100">
            <span class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden {{ $site['barClass'] }} text-white d-flex justify-content-center align-items-center fw-semibold">{{ $site['initials'] }}</span>
            <div class="flex-grow-1">
              <h6 class="text-sm mb-0">{{ $site['site'] }}</h6>
              <span class="text-xs text-secondary-light fw-medium">{{ number_format($site['total']) }} alert &middot; {{ number_format($site['confirmed']) }} confirmed</span>
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
        @empty
        <p class="text-secondary-light text-sm mb-0">Belum ada data site.</p>
        @endforelse
      </div>
    </div>
  </div>

  <div class="col-xxl-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
          <h6 class="mb-2 fw-bold text-lg mb-0">Operator Teratas</h6>
          <a href="{{ route('pra-operasi.dms-monitoring') }}" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
            Lihat semua
            <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
          </a>
        </div>
        <div class="mt-32">
          @forelse($topOperators as $i => $op)
          <div class="d-flex align-items-center justify-content-between gap-3 {{ $i < count($topOperators) - 1 ? 'mb-32' : '' }}">
            <div class="d-flex align-items-center">
              <span class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden text-white d-flex justify-content-center align-items-center fw-semibold" style="background: {{ $op['color'] }};">{{ $op['initials'] }}</span>
              <div class="flex-grow-1">
                <h6 class="text-md mb-0">{{ $op['nama'] }}</h6>
                <span class="text-sm text-secondary-light fw-medium">SID: {{ $op['kode_sid'] }}</span>
              </div>
            </div>
            <span class="text-primary-light text-md fw-medium">{{ $op['confirmed'] }}/{{ $op['total'] }}</span>
          </div>
          @empty
          <p class="text-secondary-light text-sm mb-0">Belum ada operator dengan alert.</p>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-6">
    <div class="card h-100">
      <div class="card-header border-bottom bg-base ps-0 py-0 pe-24 d-flex align-items-center justify-content-between">
        <ul class="nav bordered-tab nav-pills mb-0" id="pills-tab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pills-to-do-list-tab" data-bs-toggle="pill" data-bs-target="#pills-to-do-list" type="button" role="tab" aria-controls="pills-to-do-list" aria-selected="true">Semua Alert</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-recent-leads-tab" data-bs-toggle="pill" data-bs-target="#pills-recent-leads" type="button" role="tab" aria-controls="pills-recent-leads" aria-selected="false" tabindex="-1">Confirmed</button>
          </li>
        </ul>
        <a href="{{ route('pra-operasi.dms-monitoring') }}" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
          Lihat semua
          <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
        </a>
      </div>
      <div class="card-body p-24">
        <div class="tab-content" id="pills-tabContent">
          <div class="tab-pane fade show active" id="pills-to-do-list" role="tabpanel" aria-labelledby="pills-to-do-list-tab" tabindex="0">
            @include('dms.partials._dashboard-alert-table', ['rows' => $recentAll])
          </div>
          <div class="tab-pane fade" id="pills-recent-leads" role="tabpanel" aria-labelledby="pills-recent-leads-tab" tabindex="0">
            @include('dms.partials._dashboard-alert-table', ['rows' => $recentConfirmed])
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-6">
    <div class="card h-100">
      <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center justify-content-between">
        <h6 class="text-lg fw-semibold mb-0">Review Terbaru</h6>
        <a href="{{ route('dms.dashboard-static') }}" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
          Realtime
          <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
        </a>
      </div>
      <div class="card-body p-24">
        <div class="table-responsive scroll-sm">
          <table class="table bordered-table mb-0">
            <thead>
              <tr>
                <th scope="col">ID Alert</th>
                <th scope="col">Waktu</th>
                <th scope="col">Status</th>
                <th scope="col">Site</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentReviews as $row)
              <tr>
                <td class="text-sm">{{ \Illuminate\Support\Str::limit($row['id_alert'], 18) }}</td>
                <td>{{ $row['waktu'] }}</td>
                <td><span class="{{ $row['status_class'] }} px-24 py-4 rounded-pill fw-medium text-sm">{{ $row['status_label'] }}</span></td>
                <td>{{ $row['site'] }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="4" class="text-center text-secondary-light">Belum ada review.</td>
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
      series: [overview.confirmed || 0, overview.dismissed || 0, overview.pending || 0],
      colors: ['#EF4A00', '#45B369', '#FF9F29'],
      labels: ['Confirmed', 'Dismissed', 'Pending'],
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
              total: { showAlways: true, show: true, label: 'Review L1' }
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
        { name: 'Confirmed', data: weeklyStatus.confirmed || [] },
        { name: 'Pending', data: weeklyStatus.pending || [] },
        { name: 'Dismissed', data: weeklyStatus.dismissed || [] }
      ],
      colors: ['#EF4A00', '#144bd6', '#45B369'],
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

  var siteEl = document.querySelector('#siteShareChart');
  if (siteEl && typeof ApexCharts !== 'undefined' && sites.length) {
    new ApexCharts(siteEl, {
      series: sites.map(function (s) { return s.total; }),
      labels: sites.map(function (s) { return s.site; }),
      colors: ['#487fff', '#f4941e', '#ff9f29', '#45b369', '#00b8f2', '#8252e9'],
      legend: { show: false },
      chart: { type: 'donut', height: 180, sparkline: { enabled: true } },
      stroke: { width: 0 },
      dataLabels: { enabled: false },
      plotOptions: { pie: { donut: { size: '68%' } } }
    }).render();
  }
})();
</script>
@endsection
