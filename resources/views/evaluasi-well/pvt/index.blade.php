@extends('evaluasi-well.layouts.app')

@section('title', 'Evaluasi PVT')

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
  .pvt-kpi-card { cursor: pointer; }
  .pvt-kpi-card.is-active {
    outline: 2px solid #487fff;
    outline-offset: -2px;
  }
  .pvt-breakdown-row { cursor: pointer; }
  .pvt-breakdown-row:hover { background: rgba(72, 127, 255, 0.06); }
  .pvt-breakdown-row.is-selected {
    background: rgba(72, 127, 255, 0.10);
    outline: 1px solid #487fff;
  }
  .pvt-breakdown-list {
    max-height: 420px;
    overflow-y: auto;
  }
  .pvt-breakdown-list .pvt-breakdown-row + .pvt-breakdown-row {
    margin-top: 8px;
  }
  .pvt-stack-bar {
    display: flex;
    width: 100%;
    height: 10px;
    border-radius: 999px;
    overflow: hidden;
    background: #eef2f7;
  }
  .pvt-stack-seg { display: block; height: 100%; }
  .pvt-stack-seg.is-lulus { background: #56A353; }
  .pvt-stack-seg.is-tidak { background: #F4A444; }
  .pvt-stack-seg.is-belum { background: #D1D5DB; }
  .pvt-stack-seg.is-empty { background: #E5E7EB; }
  .pvt-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
  }
  .pvt-dot.is-lulus { background: #56A353; }
  .pvt-dot.is-tidak { background: #F4A444; }
  .pvt-dot.is-belum { background: #D1D5DB; }
  .pvt-mini-stat {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #4b5563;
  }

  .dt-container:has(#pvtOperatorsTable) .dt-layout-row,
  #pvtOperatorsTable_wrapper .dt-layout-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0.75rem 0;
  }
  .dt-container:has(#pvtOperatorsTable) .dt-paging .dt-paging-button,
  #pvtOperatorsTable_wrapper .dt-paging .dt-paging-button {
    width: auto !important;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.625rem !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    border-radius: 6px !important;
  }
  .pvt-operators-datatable,
  #pvtOperatorsTable {
    width: 100% !important;
  }
  #pvtOperatorsTable th,
  #pvtOperatorsTable td {
    vertical-align: middle;
    white-space: nowrap;
  }
  #pvtOperatorsTable td.pvt-col-nama,
  #pvtOperatorsTable td.pvt-col-company,
  #pvtOperatorsTable td.pvt-col-result {
    white-space: normal;
    min-width: 140px;
  }
  .select2-container .select2-selection--single {
    height: 31px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 2px 8px;
  }
  .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 25px;
    color: #111827;
    font-size: 0.875rem;
  }
  .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 29px;
  }
  .select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #6b7280;
  }
  #pvtCheckinChart {
    min-height: 320px;
  }
</style>
@endsection

@section('page-scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    var employeeShowBase = @json(url('/evaluasi-well/employees'));
    var dataUrl = @json(route('evaluasi-well.pvt.data'));
    var exportUrl = @json(route('evaluasi-well.pvt.export'));
    var indexUrl = @json(route('evaluasi-well.pvt.index'));

    var dateEl = document.querySelector('#pvt-date');
    var siteEl = document.querySelector('#pvt-site');
    var companyEl = document.querySelector('#pvt-company');
    var statusEl = document.querySelector('#pvt-status');
    var applyBtn = document.querySelector('#pvt-apply-btn');
    var resetBtn = document.querySelector('#pvt-reset-btn');
    var exportBtn = document.querySelector('#pvt-export-btn');
    var totalBadge = document.querySelector('#pvt-total-badge');
    var tableEl = document.querySelector('#pvtOperatorsTable');

    if (window.jQuery) {
        window.jQuery('.js-pvt-searchable').each(function () {
            var $el = window.jQuery(this);
            $el.select2({
                width: '100%',
                placeholder: $el.data('placeholder') || 'Cari…',
                allowClear: true,
                language: {
                    noResults: function () { return 'Tidak ditemukan'; },
                    searching: function () { return 'Mencari…'; }
                }
            });
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function currentFilters() {
        return {
            date: dateEl ? dateEl.value : '',
            site: siteEl ? siteEl.value : '',
            company: companyEl ? companyEl.value : '',
            pvt_status: statusEl ? statusEl.value : ''
        };
    }

    function filterQuery(extra) {
        var filters = currentFilters();
        var params = new URLSearchParams();
        Object.keys(filters).forEach(function (key) {
            if (filters[key]) params.set(key, filters[key]);
        });
        if (extra) {
            Object.keys(extra).forEach(function (key) {
                if (extra[key]) params.set(key, extra[key]);
                else params.delete(key);
            });
        }
        return params.toString();
    }

    function reloadWithFilters(extra) {
        var query = filterQuery(extra || null);
        window.location.href = indexUrl + (query ? ('?' + query) : '');
    }

    function updateExportHref() {
        if (!exportBtn) return;
        var params = new URLSearchParams(filterQuery());
        if (table && typeof table.search === 'function') {
            var search = table.search();
            if (search) params.set('search', search);
        }
        var query = params.toString();
        exportBtn.href = exportUrl + (query ? ('?' + query) : '');
    }

    function badgeHtml(status, label) {
        var cls = 'bg-neutral-200 text-secondary-light';
        if (status === 'lulus') cls = 'bg-success-100 text-success-600';
        if (status === 'tidak_lulus') cls = 'bg-danger-100 text-danger-600';
        if (status === 'belum') cls = 'bg-warning-100 text-warning-600';
        return '<span class="' + cls + ' px-12 py-4 rounded-pill fw-medium text-sm">' + escapeHtml(label) + '</span>';
    }

    var chartPayload = @json($checkinChart ?? null);
    if (!chartPayload || typeof chartPayload !== 'object') {
        chartPayload = { categories: [], dates: [], checkin: [], lulus: [], tidak_lulus: [], belum: [], pct_sudah: [] };
    }
    var chartEl = document.querySelector('#pvtCheckinChart');
    var chartEmptyEl = document.querySelector('#pvtCheckinChartEmpty');
    if (chartEl && typeof ApexCharts !== 'undefined') {
        var categories = chartPayload.categories || [];
        var dates = chartPayload.dates || [];
        var checkin = chartPayload.checkin || [];
        var lulus = chartPayload.lulus || [];
        var tidakLulus = chartPayload.tidak_lulus || [];
        var belum = chartPayload.belum || [];
        var pctSudah = chartPayload.pct_sudah || [];
        var hasChartData = categories.length > 0 && checkin.some(function (n) { return Number(n) > 0; });

        if (chartEmptyEl) {
            chartEmptyEl.classList.toggle('d-none', hasChartData);
        }
        chartEl.classList.toggle('d-none', !hasChartData);

        if (hasChartData) {
            new ApexCharts(chartEl, {
                series: [
                    { name: 'Lulus PVT', data: lulus },
                    { name: 'Tidak lulus', data: tidakLulus },
                    { name: 'Belum tes', data: belum }
                ],
                colors: ['#56A353', '#F4A444', '#D1D5DB'],
                chart: {
                    type: 'bar',
                    height: 320,
                    stacked: true,
                    toolbar: { show: false },
                    fontFamily: 'inherit',
                    events: {
                        dataPointSelection: function (event, ctx, config) {
                            var picked = dates[config.dataPointIndex] || '';
                            if (picked) {
                                reloadWithFilters({ date: picked });
                            }
                        }
                    }
                },
                plotOptions: {
                    bar: {
                        columnWidth: '88%',
                        borderRadius: 2,
                        dataLabels: {
                            total: {
                                enabled: true,
                                formatter: function (val) {
                                    return Number(val).toLocaleString('id-ID');
                                },
                                style: { fontSize: '11px', fontWeight: 700, color: '#111827' }
                            }
                        }
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val, opts) {
                        if (opts.seriesIndex !== 0) return '';
                        var total = Number(checkin[opts.dataPointIndex] || 0);
                        var pct = Number(pctSudah[opts.dataPointIndex] || 0);
                        if (total < 1 || pct < 12) return '';
                        return pct.toFixed(2) + '%';
                    },
                    style: { fontSize: '11px', fontWeight: 700, colors: ['#ffffff'] }
                },
                grid: { borderColor: '#E5E7EB', strokeDashArray: 4, padding: { top: 16 } },
                xaxis: {
                    categories: categories,
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: {
                        formatter: function (value) {
                            return Number(value).toLocaleString('id-ID');
                        }
                    }
                },
                legend: { position: 'top', horizontalAlign: 'left' },
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: {
                        formatter: function (val, opts) {
                            var total = Number(checkin[opts.dataPointIndex] || 0);
                            var pct = total > 0 ? (Number(val) / total * 100).toFixed(1) : '0.0';
                            return Number(val).toLocaleString('id-ID') + ' orang (' + pct + '%)';
                        }
                    }
                }
            }).render();
        }
    }

    var table = null;
    if (tableEl && typeof DataTable !== 'undefined') {
    table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, 'asc']],
        autoWidth: false,
        scrollX: true,
        layout: {
            topStart: 'pageLength',
            topEnd: 'search',
            bottomStart: 'info',
            bottomEnd: 'paging'
        },
        ajax: {
            url: dataUrl,
            data: function (d) {
                var filters = currentFilters();
                d.date = filters.date;
                d.site = filters.site;
                d.company = filters.company;
                d.pvt_status = filters.pvt_status;
            }
        },
        columns: [
            {
                data: 'nama',
                className: 'pvt-col-nama',
                render: function (data, type, row) {
                    if (type !== 'display') return data;
                    var href = employeeShowBase + '/' + encodeURIComponent(row.id);
                    return '<a href="' + href + '" class="text-primary-light hover-text-primary fw-semibold">'
                        + escapeHtml(data || '-') + '</a>';
                }
            },
            { data: 'kode_sid' },
            { data: 'site' },
            { data: 'company', className: 'pvt-col-company' },
            {
                data: 'pvt_done_label',
                className: 'text-center',
                render: function (data, type, row) {
                    if (type !== 'display') return data;
                    var sudah = row.pvt_status !== 'belum';
                    return badgeHtml(sudah ? 'lulus' : 'belum', sudah ? 'Sudah' : 'Belum');
                }
            },
            {
                data: 'pvt_result_label',
                className: 'pvt-col-result',
                render: function (data, type, row) {
                    if (type !== 'display') return data;
                    var label = data || 'Belum dilaksanakan';
                    var extra = '';
                    if (row.pvt_status !== 'belum' && row.tested_at && row.tested_at !== '-') {
                        extra = '<div class="text-xs text-secondary-light mt-4">' + escapeHtml(row.tested_at) + '</div>';
                    }
                    return badgeHtml(row.pvt_status, label) + extra;
                }
            },
            { data: 'checked_in_at' }
        ],
        language: {
            processing: 'Memuat...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(difilter dari _MAX_ total data)',
            zeroRecords: 'Tidak ada operator check-in yang cocok.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    table.on('draw', function () {
        if (totalBadge) {
            totalBadge.textContent = Number(table.page.info().recordsDisplay || 0).toLocaleString('id-ID');
        }
        updateExportHref();
    });
    table.on('search.dt', updateExportHref);
    }

    if (applyBtn) {
        applyBtn.addEventListener('click', function () { reloadWithFilters(); });
    }
    if (resetBtn) {
        resetBtn.addEventListener('click', function () { window.location.href = indexUrl; });
    }

    document.querySelectorAll('[data-pvt-status]').forEach(function (el) {
        el.addEventListener('click', function () {
            reloadWithFilters({ pvt_status: el.getAttribute('data-pvt-status') || '' });
        });
        el.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                el.click();
            }
        });
    });

    function bindBreakdown(selector, key) {
        document.querySelectorAll(selector).forEach(function (el) {
            el.addEventListener('click', function () {
                var value = el.getAttribute(key === 'site' ? 'data-pvt-site' : 'data-pvt-company') || '';
                var current = currentFilters()[key] || '';
                var extra = {};
                extra[key] = current === value ? '' : value;
                reloadWithFilters(extra);
            });
            el.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    el.click();
                }
            });
        });
    }
    bindBreakdown('[data-pvt-site]', 'site');
    bindBreakdown('[data-pvt-company]', 'company');

    updateExportHref();
})();
</script>
@endsection

@section('content')
@php
  $f = $filters ?? ['date' => '', 'site' => '', 'company' => '', 'pvt_status' => ''];
  $opts = $filterOptions ?? ['sites' => [], 'companies' => []];
  $kpi = $kpi ?? ['checkin' => 0, 'sudah_pvt' => 0, 'belum_pvt' => 0, 'lulus' => 0, 'tidak_lulus' => 0];
  $siteRows = $siteRows ?? [];
  $companyRows = $companyRows ?? [];
  $chart = $checkinChart ?? ['categories' => [], 'dates' => [], 'checkin' => [], 'lulus' => [], 'tidak_lulus' => [], 'belum' => [], 'pct_sudah' => []];
  $isMitraOnlyUser = app(\App\Services\SportEvaluation\SportEvaluationAccessService::class)
      ->isMitraOnlyUser(auth()->user());
  $homeUrl = $isMitraOnlyUser ? route('evaluasi-well.mitra.index') : route('evaluasi-well.index');
  $homeLabel = $isMitraOnlyUser ? 'Mitra Kerja' : 'Dashboard';
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Evaluasi PVT</h6>
    <p class="text-sm text-secondary-light mb-0 mt-4">
      Operator yang check-IN lolos pada {{ $dateLabel ?? 'hari ini' }} · PVT = tes terakhir di hari yang sama (sebelum atau sesudah gate)
    </p>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ $homeUrl }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        {{ $homeLabel }}
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Evaluasi PVT</li>
  </ul>
</div>

@unless ($bewellUp ?? false)
<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-16 radius-8 d-flex align-items-start gap-2" role="alert">
  <iconify-icon icon="solar:danger-triangle-bold" class="icon text-xl mt-1"></iconify-icon>
  <div>Koneksi BeWell tidak tersedia. Pastikan <code>start-bewell-tunnel.bat</code> berjalan (port 3316).</div>
</div>
@endunless
@unless ($rfidUp ?? false)
<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8 d-flex align-items-start gap-2" role="alert">
  <iconify-icon icon="solar:danger-triangle-bold" class="icon text-xl mt-1"></iconify-icon>
  <div>Koneksi RFID HSE (Postgres) tidak tersedia. Pastikan <code>setup-ssh-tunnel.bat</code> berjalan (port 5433).</div>
</div>
@endunless

<div class="card radius-8 border-0 shadow-sm mb-24">
  <div class="card-body p-20">
    <div class="row g-3 align-items-end">
      <div class="col-xl-2 col-md-4 col-sm-6">
        <label for="pvt-date" class="form-label text-sm fw-medium mb-6">Tanggal</label>
        <input id="pvt-date" type="date" class="form-control form-control-sm" value="{{ $f['date'] ?? '' }}">
      </div>
      <div class="col-xl-3 col-md-4 col-sm-6">
        <label for="pvt-site" class="form-label text-sm fw-medium mb-6">Site</label>
        <select id="pvt-site" class="form-select form-select-sm js-pvt-searchable" data-placeholder="Semua Site">
          <option value="">Semua Site</option>
          @foreach (($opts['sites'] ?? []) as $site)
            <option value="{{ $site }}" @selected(($f['site'] ?? '') === $site)>{{ $site }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-xl-3 col-md-4 col-sm-6">
        <label for="pvt-company" class="form-label text-sm fw-medium mb-6">Perusahaan</label>
        <select id="pvt-company" class="form-select form-select-sm js-pvt-searchable" data-placeholder="Semua Perusahaan">
          <option value="">Semua Perusahaan</option>
          @foreach (($opts['companies'] ?? []) as $company)
            <option value="{{ $company }}" @selected(($f['company'] ?? '') === $company)>{{ $company }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <label for="pvt-status" class="form-label text-sm fw-medium mb-6">Status PVT</label>
        <select id="pvt-status" class="form-select form-select-sm">
          <option value="">Semua</option>
          <option value="belum" @selected(($f['pvt_status'] ?? '') === 'belum')>Belum tes</option>
          <option value="lulus" @selected(($f['pvt_status'] ?? '') === 'lulus')>Lulus</option>
          <option value="tidak_lulus" @selected(($f['pvt_status'] ?? '') === 'tidak_lulus')>Tidak lulus</option>
        </select>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="d-flex gap-2">
          <button type="button" id="pvt-reset-btn" class="btn btn-sm btn-outline-secondary w-100">Reset</button>
          <button type="button" id="pvt-apply-btn" class="btn btn-sm btn-primary-600 w-100">Filter</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row gy-4 mb-24">
  <div class="col">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-1 pvt-kpi-card {{ ($f['pvt_status'] ?? '') === '' ? 'is-active' : '' }}" role="button" tabindex="0" data-pvt-status="" title="Semua check-in">
      <div class="card-body p-2">
        <span class="fw-medium text-secondary-light text-sm mb-0">Check-in operator</span>
        <h6 class="fw-semibold mb-0">{{ number_format($kpi['checkin'] ?? 0) }}</h6>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-2 pvt-kpi-card {{ ($f['pvt_status'] ?? '') === 'belum' ? 'is-active' : '' }}" role="button" tabindex="0" data-pvt-status="belum">
      <div class="card-body p-2">
        <span class="fw-medium text-secondary-light text-sm mb-0">Belum PVT</span>
        <h6 class="fw-semibold mb-0">{{ number_format($kpi['belum_pvt'] ?? 0) }}</h6>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-3">
      <div class="card-body p-2">
        <span class="fw-medium text-secondary-light text-sm mb-0">Sudah PVT</span>
        <h6 class="fw-semibold mb-0">{{ number_format($kpi['sudah_pvt'] ?? 0) }}</h6>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-4 pvt-kpi-card {{ ($f['pvt_status'] ?? '') === 'lulus' ? 'is-active' : '' }}" role="button" tabindex="0" data-pvt-status="lulus">
      <div class="card-body p-2">
        <span class="fw-medium text-secondary-light text-sm mb-0">Lulus</span>
        <h6 class="fw-semibold mb-0">{{ number_format($kpi['lulus'] ?? 0) }}</h6>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-1 pvt-kpi-card {{ ($f['pvt_status'] ?? '') === 'tidak_lulus' ? 'is-active' : '' }}" role="button" tabindex="0" data-pvt-status="tidak_lulus">
      <div class="card-body p-2">
        <span class="fw-medium text-secondary-light text-sm mb-0">Tidak lulus</span>
        <h6 class="fw-semibold mb-0">{{ number_format($kpi['tidak_lulus'] ?? 0) }}</h6>
      </div>
    </div>
  </div>
</div>

<div class="card radius-8 border-0 shadow-sm mb-24">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Check-in operator 7 hari</h6>
        <p class="text-sm text-secondary-light mb-0">
          Jumlah karyawan yang masuk (RFID IN lolos) · batang = Lulus / Tidak lulus / Belum tes · angka atas = total masuk · % di batang hijau = % sudah PVT
          @if (($f['site'] ?? '') !== '' || ($f['company'] ?? '') !== '')
            · filter
            @if (($f['site'] ?? '') !== '')
              site <strong>{{ $f['site'] }}</strong>
            @endif
            @if (($f['company'] ?? '') !== '')
              perusahaan <strong>{{ $f['company'] }}</strong>
            @endif
          @endif
        </p>
      </div>
      <span class="text-xs text-secondary-light">Klik batang untuk membuka tanggal itu</span>
    </div>
  </div>
  <div class="card-body p-24">
    <div id="pvtCheckinChartEmpty" class="text-center text-secondary-light py-40 {{ collect($chart['checkin'] ?? [])->sum() > 0 ? 'd-none' : '' }}">
      Belum ada data check-in operator pada 7 hari ini.
    </div>
    <div id="pvtCheckinChart" class="{{ collect($chart['checkin'] ?? [])->sum() > 0 ? '' : 'd-none' }}"></div>
  </div>
</div>

<div class="row gy-4 mb-24 align-items-start">
  <div class="col-xxl-6">
    <div class="card radius-8 border-0 shadow-sm">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <div class="d-flex align-items-start justify-content-between gap-2">
          <div>
            <h6 class="text-lg fw-semibold mb-0">Distribusi Site</h6>
            <p class="text-sm text-secondary-light mb-0">Komposisi PVT per site · klik untuk filter</p>
          </div>
          <span class="bg-neutral-100 text-secondary-light text-xs fw-medium px-12 py-2 rounded-pill">{{ number_format(count($siteRows)) }} site</span>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-3 mt-8 text-xs text-secondary-light">
          <span class="pvt-mini-stat"><span class="pvt-dot is-lulus"></span>Lulus</span>
          <span class="pvt-mini-stat"><span class="pvt-dot is-tidak"></span>Tidak lulus</span>
          <span class="pvt-mini-stat"><span class="pvt-dot is-belum"></span>Belum tes</span>
        </div>
      </div>
      @include('evaluasi-well.pvt._breakdown-list', [
        'rows' => $siteRows,
        'attr' => 'data-pvt-site',
        'selected' => $f['site'] ?? '',
        'totalCheckin' => (int) ($kpi['checkin'] ?? 0),
      ])
    </div>
  </div>
  <div class="col-xxl-6">
    <div class="card radius-8 border-0 shadow-sm">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <div class="d-flex align-items-start justify-content-between gap-2">
          <div>
            <h6 class="text-lg fw-semibold mb-0">Distribusi Perusahaan</h6>
            <p class="text-sm text-secondary-light mb-0">Komposisi PVT per perusahaan · klik untuk filter</p>
          </div>
          <span class="bg-neutral-100 text-secondary-light text-xs fw-medium px-12 py-2 rounded-pill">{{ number_format(count($companyRows)) }} perusahaan</span>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-3 mt-8 text-xs text-secondary-light">
          <span class="pvt-mini-stat"><span class="pvt-dot is-lulus"></span>Lulus</span>
          <span class="pvt-mini-stat"><span class="pvt-dot is-tidak"></span>Tidak lulus</span>
          <span class="pvt-mini-stat"><span class="pvt-dot is-belum"></span>Belum tes</span>
        </div>
      </div>
      @include('evaluasi-well.pvt._breakdown-list', [
        'rows' => $companyRows,
        'attr' => 'data-pvt-company',
        'selected' => $f['company'] ?? '',
        'totalCheckin' => (int) ($kpi['checkin'] ?? 0),
      ])
    </div>
  </div>
</div>

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-4">
          <h6 class="text-lg fw-semibold mb-0">Operator Check-in</h6>
          <span id="pvt-total-badge" class="bg-primary-50 text-primary-600 text-sm fw-medium px-12 py-2 rounded-pill">{{ number_format($kpi['checkin'] ?? 0) }}</span>
        </div>
        <p class="text-sm text-secondary-light mb-0">Satu baris per SID · check-IN lolos pertama · PVT terakhir di hari yang sama</p>
      </div>
      <a id="pvt-export-btn" href="{{ route('evaluasi-well.pvt.export', request()->query()) }}" class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
        <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
        Download Excel
      </a>
    </div>
  </div>
  <div class="card-body p-24">
    <div class="table-responsive pvt-operators-datatable w-100">
      <table id="pvtOperatorsTable" class="table bordered-table mb-0 w-100" style="width:100%">
        <thead>
          <tr>
            <th scope="col">Nama</th>
            <th scope="col">SID</th>
            <th scope="col">Site</th>
            <th scope="col">Perusahaan</th>
            <th scope="col">PVT</th>
            <th scope="col">Hasil</th>
            <th scope="col">Jam check-in</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
@endsection
