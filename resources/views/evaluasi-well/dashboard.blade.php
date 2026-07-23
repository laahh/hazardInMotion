@extends('evaluasi-well.layouts.app')

@section('title', 'Dashboard')

@section('page-scripts')
<script src="{{ asset('evaluasi-well-assets/js/homeTwoChart.js') }}"></script>
<script>
(function () {
    var el = document.querySelector('#revenue-chart');
    if (!el || typeof ApexCharts === 'undefined') {
        return;
    }

    var labels = @json($activeTrendLabels ?? []);
    var series = @json($activeTrendSeries ?? []);
    var chartColor = '#487fff';

    if (!labels.length) {
        labels = ['W1','W2','W3','W4','W5','W6','W7','W8','W9','W10','W11','W12'];
        series = [0,0,0,0,0,0,0,0,0,0,0,0];
    }

    new ApexCharts(el, {
        series: [{ name: 'User Aktif', data: series }],
        chart: {
            type: 'area',
            width: '100%',
            height: 162,
            toolbar: { show: false },
            padding: { left: 0, right: 0, top: 0, bottom: 0 }
        },
        dataLabels: { enabled: false },
        stroke: {
            curve: 'smooth',
            width: 2,
            colors: [chartColor],
            lineCap: 'round'
        },
        grid: {
            show: true,
            borderColor: 'transparent',
            strokeDashArray: 0,
            position: 'back',
            xaxis: { lines: { show: false } },
            yaxis: { lines: { show: false } },
            padding: { top: -30, right: 0, bottom: -10, left: 0 }
        },
        fill: {
            type: 'gradient',
            colors: [chartColor],
            gradient: {
                shade: 'light',
                type: 'vertical',
                shadeIntensity: 0.5,
                gradientToColors: [chartColor + '00'],
                inverseColors: false,
                opacityFrom: 0.6,
                opacityTo: 0.3,
                stops: [0, 100]
            }
        },
        markers: {
            colors: [chartColor],
            strokeWidth: 3,
            size: 0,
            hover: { size: 10 }
        },
        xaxis: {
            categories: labels,
            labels: {
                show: true,
                style: { fontSize: '11px' },
                rotate: -45,
                hideOverlappingLabels: true
            },
            tooltip: { enabled: false }
        },
        yaxis: { labels: { show: false } },
        tooltip: {
            y: {
                formatter: function (val) {
                    return val + ' user';
                }
            }
        }
    }).render();
})();
</script>
<script>
(function () {
    var el = document.querySelector('#barChart');
    if (!el || typeof ApexCharts === 'undefined') {
        return;
    }

    var labels = @json($adoptionChartLabels ?? []);
    var series = @json($adoptionChartSeries ?? []);

    new ApexCharts(el, {
        series: [{
            name: 'Login Sukses',
            data: labels.map(function (label, i) {
                return { x: label, y: series[i] || 0 };
            })
        }],
        chart: {
            type: 'bar',
            height: 310,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                borderRadius: 4,
                horizontal: false,
                columnWidth: '23%',
                endingShape: 'rounded'
            }
        },
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
        grid: {
            show: true,
            borderColor: '#D1D5DB',
            strokeDashArray: 4,
            position: 'back'
        },
        xaxis: {
            type: 'category',
            categories: labels
        },
        yaxis: {
            labels: {
                formatter: function (value) {
                    if (value >= 1000) {
                        return (value / 1000).toFixed(0) + 'k';
                    }
                    return value;
                }
            }
        },
        tooltip: {
            y: {
                formatter: function (value) {
                    return value + ' login';
                }
            }
        }
    }).render();
})();
</script>
<script>
(function () {
    var el = document.querySelector('#donutChart');
    if (!el || typeof ApexCharts === 'undefined') {
        return;
    }

    var series = @json($compositionSeries ?? []);
    var labels = @json($compositionLabels ?? []);

    if (!series.length) {
        series = [0, 0, 0];
    }
    if (!labels.length) {
        labels = ['Olahraga', 'Nutrisi', 'Sosial'];
    }

    new ApexCharts(el, {
        series: series,
        colors: ['#45B369', '#FF9F29', '#487FFF'],
        labels: labels,
        legend: { show: false },
        chart: {
            type: 'donut',
            height: 300,
            sparkline: { enabled: true },
            margin: { top: -100, right: -100, bottom: -100, left: -100 },
            padding: { top: -100, right: -100, bottom: -100, left: -100 }
        },
        stroke: { width: 0 },
        dataLabels: { enabled: false },
        responsive: [{
            breakpoint: 480,
            options: {
                chart: { width: 200 },
                legend: { position: 'bottom' }
            }
        }],
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
                        total: {
                            showAlways: true,
                            show: true,
                            label: 'Laporan Aktivitas',
                            formatter: function () {
                                return '';
                            }
                        }
                    }
                }
            }
        },
        tooltip: {
            y: {
                formatter: function (value) {
                    return value + ' user';
                }
            }
        }
    }).render();
})();
</script>
<script>
(function () {
    var el = document.querySelector('#paymentStatusChart');
    if (!el || typeof ApexCharts === 'undefined') {
        return;
    }

    var labels = @json($weeklyActivityLabels ?? []);
    var makanan = @json($weeklyMakananSeries ?? []);
    var olahraga = @json($weeklyOlahragaSeries ?? []);
    var sosial = @json($weeklySosialSeries ?? []);

    if (!labels.length) {
        labels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    }
    if (!makanan.length) {
        makanan = [0, 0, 0, 0, 0, 0, 0];
    }
    if (!olahraga.length) {
        olahraga = [0, 0, 0, 0, 0, 0, 0];
    }
    if (!sosial.length) {
        sosial = [0, 0, 0, 0, 0, 0, 0];
    }

    new ApexCharts(el, {
        series: [
            { name: 'Makanan', data: makanan },
            { name: 'Olahraga', data: olahraga },
            { name: 'Sosial', data: sosial }
        ],
        colors: ['#45B369', '#144bd6', '#FF9F29'],
        legend: { show: false },
        chart: {
            type: 'bar',
            height: 350,
            toolbar: { show: false }
        },
        grid: {
            show: true,
            borderColor: '#D1D5DB',
            strokeDashArray: 4,
            position: 'back'
        },
        plotOptions: {
            bar: {
                borderRadius: 4,
                columnWidth: 8
            }
        },
        dataLabels: { enabled: false },
        states: {
            hover: {
                filter: { type: 'none' }
            }
        },
        stroke: {
            show: true,
            width: 0,
            colors: ['transparent']
        },
        xaxis: {
            categories: labels
        },
        yaxis: {
            labels: {
                formatter: function (value) {
                    return Math.round(value);
                }
            }
        },
        fill: {
            opacity: 1
        },
        tooltip: {
            y: {
                formatter: function (value) {
                    return value + ' aktivitas';
                }
            }
        }
    }).render();
})();
</script>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Dashboard</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('evaluasi-well.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Evaluasi Olahraga</li>
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
                        <span class="mb-2 fw-medium text-secondary-light text-sm">Total User Install</span>
                        <h6 class="fw-semibold">{{ number_format($newUsersTotal ?? 0) }}</h6>
                      </div>
                    </div>
                  
                    <div id="new-user-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
                </div>
                <p class="text-sm mb-0">Increase by  <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm">+{{ number_format($newUsersWeekIncrease ?? 0) }}</span> this week</p>
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
                        <span class="mb-2 fw-medium text-secondary-light text-sm">Total User Aktif</span>
                        <h6 class="fw-semibold">{{ number_format($activeUsersTotal ?? 0) }}</h6>
                      </div>
                    </div>
                  
                    <div id="active-user-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
                </div>
                <p class="text-sm mb-0">Increase by  <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm">+{{ number_format($activeUsersWeekIncrease ?? 0) }}</span> this week</p>
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
                        <span class="mb-2 fw-medium text-secondary-light text-sm">Total Strava Connect</span>
                        <h6 class="fw-semibold">50 orang</h6>
                      </div>
                    </div>
                  
                    <div id="total-sales-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
                </div>
                <p class="text-sm mb-0">Increase by  <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm">+{{ number_format($totalStravaConnectWeekIncrease ?? 0) }}</span> this week</p>
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
                        <span class="mb-2 fw-medium text-secondary-light text-sm">Total Komunitas</span>
                        <h6 class="fw-semibold">{{ number_format($totalKomunitas ?? 0) }}</h6>
                      </div>
                    </div>
                  
                    <div id="conversion-user-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
                </div>
                <p class="text-sm mb-0">Increase by  <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm">+{{ number_format($totalKomunitasWeekIncrease ?? 0) }}</span> this week</p>
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
                        <span class="mb-2 fw-medium text-secondary-light text-sm">Total Main Bareng</span>
                        <h6 class="fw-semibold">{{ number_format($totalMainBareng ?? 0) }}</h6>
                      </div>
                    </div>
                  
                    <div id="leads-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
                </div>
                <p class="text-sm mb-0">Increase by  <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm">+{{ number_format($totalMainBarengWeekIncrease ?? 0) }}</span> this week</p>
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
                        <span class="mb-2 fw-medium text-secondary-light text-sm">Total Goal Aktif</span>
                        <h6 class="fw-semibold">{{ number_format($totalGoalAktif ?? 0) }}</h6>
                      </div>
                    </div>
                  
                    <div id="total-profit-chart" class="remove-tooltip-title rounded-tooltip-value"></div>
                </div>
                <p class="text-sm mb-0">Increase by  <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm">+{{ number_format($totalGoalAktifWeekIncrease ?? 0) }}</span> this week</p>
              </div>
            </div>
          </div>

        </div>
      </div>
      <!-- Pertumbuhan User Aktif start -->
      <div class="col-xxl-4">
        <div class="card h-100 radius-8 border">
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
              <div>
                <h6 class="mb-2 fw-bold text-lg">Pertumbuhan User Aktif</h6>
                <span class="text-sm fw-medium text-secondary-light">Weekly Report</span>
              </div>
              <div class="text-end">
                <h6 class="mb-2 fw-bold text-lg">{{ number_format($activeTrendThisWeek ?? 0) }}</h6>
                <span class="bg-success-focus ps-12 pe-12 pt-2 pb-2 rounded-2 fw-medium text-success-main text-sm">+{{ number_format($activeTrendWeekIncrease ?? 0) }}</span>
              </div>
            </div>
            <div id="revenue-chart" class="mt-28"></div>
          </div>
        </div>
      </div>
      <!-- Pertumbuhan User Aktif End -->

      <!-- Tren Login & Adopsi start -->
      <div class="col-xxl-8">
        <div class="card h-100 radius-8 border-0">
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
              <div>
                <h6 class="mb-2 fw-bold text-lg">Tren Login &amp; Adopsi</h6>
                <span class="text-sm fw-medium text-secondary-light">Ringkasan login sukses per bulan</span>
              </div>
              <div class="">
                <span class="form-select form-select-sm w-auto bg-base border text-secondary-light d-inline-block pe-none">{{ date('Y') }}</span>
              </div>
            </div>

            <div class="mt-20 d-flex justify-content-center flex-wrap gap-3">

              <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
                <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
                  <iconify-icon icon="solar:download-minimalistic-bold" class="icon"></iconify-icon>
                </span>
                <div>
                  <span class="text-secondary-light text-sm fw-medium">Install</span>
                  <h6 class="text-md fw-semibold mb-0">{{ number_format($adoptionInstall ?? 0) }}</h6>
                </div>
              </div>

              <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
                <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
                  <iconify-icon icon="solar:login-3-bold" class="icon"></iconify-icon>
                </span>
                <div>
                  <span class="text-secondary-light text-sm fw-medium">Login Sukses</span>
                  <h6 class="text-md fw-semibold mb-0">{{ number_format($adoptionLoginSuccess ?? 0) }}</h6>
                </div>
              </div>

              <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
                <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
                  <iconify-icon icon="solar:user-check-bold" class="icon"></iconify-icon>
                </span>
                <div>
                  <span class="text-secondary-light text-sm fw-medium">Aktif</span>
                  <h6 class="text-md fw-semibold mb-0">{{ number_format($adoptionAktif ?? 0) }}</h6>
                </div>
              </div>
            </div>
            
            <div id="barChart" class="barChart"></div>
          </div>
        </div>
      </div>
      <!-- Tren Login & Adopsi End -->

      <!-- Campaign Static start -->
      <div class="col-xxl-4">
        <div class="row gy-4">
          <div class="col-xxl-12 col-sm-6">
            <div class="card h-100 radius-8 border-0">
              <div class="card-body p-24">
                <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between mb-20">
                  <h6 class="mb-0 fw-bold text-lg">Top Komunitas</h6>
                  <span class="text-sm text-secondary-light">Berdasarkan member</span>
                </div>
                
                <div class="mt-3">
                  @forelse(($topKomunitas ?? []) as $i => $komunitas)
                  <div class="d-flex align-items-center gap-3 {{ $i < count($topKomunitas) - 1 ? 'mb-16' : '' }}">
                    <div class="d-flex align-items-center gap-2 flex-shrink-0" style="width: 148px;">
                      <span class="w-32-px h-32-px rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0 {{ $komunitas['barClass'] }} text-white">
                        <iconify-icon icon="{{ $komunitas['icon'] }}" class="text-md"></iconify-icon>
                      </span>
                      <span class="text-primary-light fw-medium text-sm text-truncate min-w-0" style="max-width: 104px;" title="{{ $komunitas['name'] }}">{{ $komunitas['name'] }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                      <div class="progress progress-sm rounded-pill w-100" role="progressbar" aria-label="Top komunitas" aria-valuenow="{{ $komunitas['pct'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar {{ $komunitas['barClass'] }} rounded-pill" style="width: {{ $komunitas['pct'] }}%;"></div>
                      </div>
                      <span class="text-secondary-light font-xs fw-semibold flex-shrink-0 text-end" style="min-width: 52px;">{{ number_format($komunitas['members']) }}</span>
                    </div>
                  </div>
                  @empty
                  <p class="text-secondary-light text-sm mb-0">Belum ada data komunitas.</p>
                  @endforelse

                </div>

              </div>
            </div>
          </div>
          <div class="col-xxl-12 col-sm-6">
            <div class="card h-100 radius-8 border-0 overflow-hidden">
              <div class="card-body p-24">
                <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
                  <h6 class="mb-2 fw-bold text-lg">Komposisi Aktivitas</h6>
                  <span class="text-sm fw-medium text-secondary-light">Tahun {{ date('Y') }}</span>
                </div>

                <div class="d-flex flex-wrap align-items-center mt-3">
                  <ul class="flex-shrink-0">
                    <li class="d-flex align-items-center gap-2 mb-28">
                      <span class="w-12-px h-12-px rounded-circle bg-success-main"></span>
                      <span class="text-secondary-light text-sm fw-medium">Olahraga: {{ number_format($compositionOlahraga ?? 0) }}</span>
                    </li>
                    <li class="d-flex align-items-center gap-2 mb-28">
                      <span class="w-12-px h-12-px rounded-circle bg-warning-main"></span>
                      <span class="text-secondary-light text-sm fw-medium">Nutrisi: {{ number_format($compositionNutrisi ?? 0) }}</span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                      <span class="w-12-px h-12-px rounded-circle bg-primary-600"></span>
                      <span class="text-secondary-light text-sm fw-medium">Sosial: {{ number_format($compositionSosial ?? 0) }}</span>
                    </li>
                  </ul>
                  <div id="donutChart" class="flex-grow-1 apexcharts-tooltip-z-none title-style circle-none"></div>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>  
      <!-- Campaign Static End -->

      <!-- Aktivitas Harian Start -->
      <div class="col-xxl-4 col-sm-6">
        <div class="card h-100 radius-8 border-0">
          <div class="card-body p-24">
              <h6 class="mb-2 fw-bold text-lg">Aktivitas Harian</h6>
              <span class="text-sm fw-medium text-secondary-light">Minggu ini (Sen–Min)</span>

              <ul class="d-flex flex-wrap align-items-center justify-content-center mt-32">
                <li class="d-flex align-items-center gap-2 me-28">
                  <span class="w-12-px h-12-px rounded-circle bg-success-main"></span>
                  <span class="text-secondary-light text-sm fw-medium">Makanan: {{ number_format($weeklyMakananTotal ?? 0) }}</span>
                </li>
                <li class="d-flex align-items-center gap-2 me-28">
                  <span class="w-12-px h-12-px rounded-circle bg-info-main"></span>
                  <span class="text-secondary-light text-sm fw-medium">Olahraga: {{ number_format($weeklyOlahragaTotal ?? 0) }}</span>
                </li>
                <li class="d-flex align-items-center gap-2">
                  <span class="w-12-px h-12-px rounded-circle bg-warning-main"></span>
                  <span class="text-secondary-light text-sm fw-medium">Sosial: {{ number_format($weeklySosialTotal ?? 0) }}</span>
                </li>
              </ul>
              <div class="mt-40">
                <div id="paymentStatusChart" class="margin-16-minus"></div>
              </div>
          </div>
        </div>
      </div>
      <!-- Aktivitas Harian End -->

      <!-- Site Status Start -->
      <div class="col-xxl-4 col-sm-6">
        <div class="card radius-8 border-0 h-100">

          <div class="card-body">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
              <h6 class="mb-2 fw-bold text-lg mb-0">Distribusi per Site</h6>
              <span class="text-sm fw-medium text-secondary-light">{{ number_format($siteTotalEmployees ?? 0) }} karyawan</span>
            </div>
          </div>

          <div class="card-body p-24 pt-0 max-h-350-px scroll-sm overflow-y-auto">
            <div class="">
              @forelse (($siteRows ?? []) as $site)
              <div class="d-flex align-items-center justify-content-between gap-3 {{ !$loop->last ? 'mb-3 pb-2' : '' }}">
                <div class="d-flex align-items-center w-100 min-w-0">
                  <div class="flex-grow-1 min-w-0">
                    <h6 class="text-sm mb-0 text-truncate">{{ $site['name'] }}</h6>
                    <span class="text-xs text-secondary-light fw-medium">{{ number_format($site['total']) }} karyawan</span>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-2 w-100">
                  <div class="w-100 max-w-66 ms-auto">
                    <div class="progress progress-sm rounded-pill" role="progressbar" aria-valuenow="{{ $site['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                      <div class="progress-bar {{ $site['barClass'] }} rounded-pill" style="width: {{ min(100, $site['percent']) }}%;"></div>
                    </div>
                  </div>
                  <span class="text-secondary-light font-xs fw-semibold flex-shrink-0" style="min-width: 42px; text-align: right;">{{ $site['percent'] }}%</span>
                </div>
              </div>
              @empty
              <p class="text-secondary-light text-sm mb-0">Belum ada data site karyawan.</p>
              @endforelse
            </div>
          </div>
        </div>
      </div>
      <!-- Site Status End -->

      <!-- Top User Start -->
      <div class="col-xxl-4">
        <div class="card">

          <div class="card-body">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
              <h6 class="mb-2 fw-bold text-lg mb-0">Top User</h6>
              <a href="{{ route('evaluasi-well.leaderboard') }}" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
                Lihat Semua
                <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
              </a>
            </div>

            <div class="mt-32">
              @forelse (($topUsers ?? []) as $i => $user)
              <div class="d-flex align-items-center justify-content-between gap-3 {{ !$loop->last ? 'mb-32' : '' }}">
                <div class="d-flex align-items-center min-w-0">
                  <img src="{{ $user['avatar'] }}" alt="" class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden" style="object-fit: cover;" onerror="this.src='{{ asset('evaluasi-well-assets/images/users/user1.png') }}'">
                  <div class="flex-grow-1 min-w-0">
                    <h6 class="text-md mb-0 text-truncate">
                      <a href="{{ route('evaluasi-well.employees.show', $user['id']) }}" class="text-primary-light hover-text-primary">
                        {{ $user['nama'] }}
                      </a>
                    </h6>
                    <span class="text-sm text-secondary-light fw-medium text-truncate d-block" title="Makanan {{ $user['food_cnt'] }} · Olahraga {{ $user['workout_cnt'] }} · Komunitas {{ $user['community_cnt'] }} · Main Bareng {{ $user['open_play_cnt'] }}">
                      {{ $user['food_cnt'] }} makan · {{ $user['workout_cnt'] }} olahraga · {{ $user['community_cnt'] + $user['open_play_cnt'] }} sosial
                    </span>
                  </div>
                </div>
                <span class="text-primary-light text-md fw-medium flex-shrink-0">{{ number_format($user['total_cnt']) }}</span>
              </div>
              @empty
              <p class="text-secondary-light text-sm mb-0">Belum ada data aktivitas user.</p>
              @endforelse
            </div>

          </div>
        </div>
      </div>
      <!-- Top User End -->


      <!-- Aktivitas & Login Terbaru Start -->
      <div class="col-xxl-6">
        <div class="card h-100">
          <div class="card-header border-bottom bg-base ps-0 py-0 pe-24 d-flex align-items-center justify-content-between">
              <ul class="nav bordered-tab nav-pills mb-0" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pills-to-do-list-tab" data-bs-toggle="pill" data-bs-target="#pills-to-do-list" type="button" role="tab" aria-controls="pills-to-do-list" aria-selected="true">Semua</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-recent-food-tab" data-bs-toggle="pill" data-bs-target="#pills-recent-food" type="button" role="tab" aria-controls="pills-recent-food" aria-selected="false" tabindex="-1">Makanan</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-recent-workout-tab" data-bs-toggle="pill" data-bs-target="#pills-recent-workout" type="button" role="tab" aria-controls="pills-recent-workout" aria-selected="false" tabindex="-1">Olahraga</button>
                </li>
              </ul>
              <a href="{{ route('evaluasi-well.activities.index') }}" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
                Lihat Semua
                <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
              </a>
            </div>
            <div class="card-body p-24">
              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="pills-to-do-list" role="tabpanel" aria-labelledby="pills-to-do-list-tab" tabindex="0">
                  @include('evaluasi-well.partials._recent-activities-table', ['items' => $recentAllActivities ?? []])
                </div>
                <div class="tab-pane fade" id="pills-recent-food" role="tabpanel" aria-labelledby="pills-recent-food-tab" tabindex="0">
                  @include('evaluasi-well.partials._recent-activities-table', ['items' => $recentFoodActivities ?? []])
                </div>
                <div class="tab-pane fade" id="pills-recent-workout" role="tabpanel" aria-labelledby="pills-recent-workout-tab" tabindex="0">
                  @include('evaluasi-well.partials._recent-activities-table', ['items' => $recentWorkoutActivities ?? []])
                </div>
              </div>
          </div>
        </div>
      </div>

      <div class="col-xxl-6">
        <div class="card h-100">
          <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center justify-content-between">
            <h6 class="text-lg fw-semibold mb-0">Login Terbaru</h6>
            <a href="{{ route('evaluasi-well.index') }}" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
              Lihat Semua
              <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
            </a>
          </div>
          <div class="card-body p-24">
            <div class="table-responsive scroll-sm">
              <table class="table bordered-table mb-0">
                <thead>
                  <tr>
                    <th scope="col">Karyawan</th>
                    <th scope="col">Waktu</th>
                    <th scope="col">Status</th>
                    <th scope="col">Platform</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse (($recentLogins ?? []) as $login)
                  <tr>
                    <td>
                      @if ($login['user_id'])
                        <a href="{{ route('evaluasi-well.employees.show', $login['user_id']) }}" class="text-primary-light hover-text-primary">
                          {{ $login['user_name'] }}
                        </a>
                        <span class="text-sm d-block fw-normal text-secondary-light">{{ $login['kode_sid'] }}</span>
                      @else
                        <span class="text-primary-light">{{ $login['user_name'] }}</span>
                        <span class="text-sm d-block fw-normal text-secondary-light">{{ $login['kode_sid'] }}</span>
                      @endif
                    </td>
                    <td>{{ $login['at'] }}</td>
                    <td>
                      <span class="{{ $login['status_class'] }} px-24 py-4 rounded-pill fw-medium text-sm">{{ $login['status'] }}</span>
                    </td>
                    <td>{{ strtoupper($login['platform']) }}</td>
                  </tr>
                  @empty
                  <tr>
                    <td colspan="4" class="text-secondary-light text-sm">Belum ada data login.</td>
                  </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <!-- Aktivitas & Login Terbaru End -->
    </div>
@endsection
