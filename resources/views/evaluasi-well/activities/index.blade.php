@extends('evaluasi-well.layouts.app')

@section('title', 'Tren Aktivitas')

@section('css')
<style>
  .wa-kpi-note { font-size: 12px; line-height: 1.4; }
  .wa-granularity-btn.is-active {
    background: #487fff;
    color: #fff;
    border-color: #487fff;
  }
  .dt-container:has(#waUsersTable) .dt-layout-row,
  #waUsersTable_wrapper .dt-layout-row,
  .dt-container:has(#waRawTable) .dt-layout-row,
  #waRawTable_wrapper .dt-layout-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0.75rem 0;
  }
  .dt-container:has(#waUsersTable) .dt-paging .dt-paging-button,
  #waUsersTable_wrapper .dt-paging .dt-paging-button,
  .dt-container:has(#waRawTable) .dt-paging .dt-paging-button,
  #waRawTable_wrapper .dt-paging .dt-paging-button {
    width: auto !important;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.625rem !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    border-radius: 6px !important;
  }
  #waUsersTable, #waRawTable { width: 100% !important; }
  #waUsersTable th, #waUsersTable td,
  #waRawTable th, #waRawTable td {
    vertical-align: middle;
    white-space: nowrap;
  }
  #waUsersTable td.wa-col-nama,
  #waRawTable td.wa-col-nama,
  #waRawTable td.wa-col-type {
    white-space: normal;
    min-width: 140px;
  }
  #waTrendChart, #waDistChart, #waCompanyChart { min-height: 300px; }
</style>
@endsection

@section('page-scripts')
@php
  $waTrendDaily = $trendDaily ?? [];
  $waTrendWeekly = $trendWeekly ?? [];
  $waDistribution = $distribution ?? [];
  $waTopCompanies = $topCompanies ?? [];
@endphp
<script>
(function () {
    var trendDaily = @json($waTrendDaily);
    var trendWeekly = @json($waTrendWeekly);
    var distribution = @json($waDistribution);
    var topCompanies = @json($waTopCompanies);
    var trendChart = null;
    var granularity = 'daily';

    function trendOptions(source) {
        return {
            series: [
                { name: 'Sesi olahraga', type: 'area', data: source.sesi || [] },
                { name: 'Kkal keluar', type: 'line', data: source.kcal_out || [] },
                { name: 'Kkal masuk', type: 'line', data: source.kcal_in || [] }
            ],
            colors: ['#487FFF', '#FF9F29', '#45B369'],
            chart: {
                height: 340,
                type: 'line',
                toolbar: { show: false },
                zoom: { enabled: false }
            },
            stroke: { width: [0, 3, 3], curve: 'smooth' },
            fill: {
                type: ['gradient', 'solid', 'solid'],
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            dataLabels: { enabled: false },
            grid: { borderColor: '#D1D5DB', strokeDashArray: 4 },
            xaxis: {
                categories: source.labels || [],
                labels: { rotate: -30, style: { fontSize: '11px' } }
            },
            yaxis: [
                {
                    seriesName: 'Sesi olahraga',
                    title: { text: 'Sesi' },
                    labels: { formatter: function (v) { return Math.round(v); } }
                },
                {
                    seriesName: 'Kkal keluar',
                    opposite: true,
                    title: { text: 'Kilokalori' },
                    labels: { formatter: function (v) { return Math.round(v).toLocaleString('id-ID'); } }
                },
                {
                    seriesName: 'Kkal keluar',
                    opposite: true,
                    show: false
                }
            ],
            legend: { position: 'top' },
            tooltip: { shared: true, intersect: false }
        };
    }

    var trendEl = document.querySelector('#waTrendChart');
    if (trendEl && typeof ApexCharts !== 'undefined') {
        trendChart = new ApexCharts(trendEl, trendOptions(trendDaily));
        trendChart.render();
    }

    document.querySelectorAll('[data-wa-granularity]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            granularity = btn.getAttribute('data-wa-granularity') || 'daily';
            document.querySelectorAll('[data-wa-granularity]').forEach(function (el) {
                el.classList.toggle('is-active', el === btn);
            });
            if (trendChart) {
                trendChart.updateOptions(trendOptions(granularity === 'weekly' ? trendWeekly : trendDaily), true, true);
            }
        });
    });

    var distEl = document.querySelector('#waDistChart');
    if (distEl && typeof ApexCharts !== 'undefined') {
        var distLabels = distribution.labels || [];
        new ApexCharts(distEl, {
            series: distLabels.length ? (distribution.counts || []) : [1],
            labels: distLabels.length ? distLabels : ['Tidak ada data'],
            colors: distLabels.length
                ? ['#487FFF', '#45B369', '#FF9F29', '#8252E9', '#F86624', '#16A34A', '#0EA5E9', '#EAB308', '#EF4444', '#64748B', '#14B8A6', '#A855F7']
                : ['#E5E7EB'],
            chart: { type: 'donut', height: 300 },
            legend: { position: 'bottom' },
            dataLabels: { enabled: distLabels.length > 0 },
            tooltip: { enabled: distLabels.length > 0 }
        }).render();
    }

    var companyEl = document.querySelector('#waCompanyChart');
    if (companyEl && typeof ApexCharts !== 'undefined') {
        new ApexCharts(companyEl, {
            series: [{ name: 'Sesi', data: topCompanies.counts || [] }],
            colors: ['#487FFF'],
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '70%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: topCompanies.labels || [] },
            grid: { borderColor: '#D1D5DB', strokeDashArray: 4 },
            noData: { text: 'Tidak ada data' }
        }).render();
    }
})();
</script>
<script>
(function () {
    if (typeof DataTable === 'undefined') {
        return;
    }

    var employeeShowBase = @json(url('/evaluasi-well/employees'));
    var dataUrl = @json(route('evaluasi-well.activities.data'));
    var rawDataUrl = @json(route('evaluasi-well.activities.raw-data'));
    var exportUrl = @json(route('evaluasi-well.activities.export'));
    var indexUrl = @json(route('evaluasi-well.activities.index'));

    var fromEl = document.querySelector('#wa-from');
    var toEl = document.querySelector('#wa-to');
    var siteEl = document.querySelector('#wa-site');
    var companyEl = document.querySelector('#wa-company');
    var divisionEl = document.querySelector('#wa-division');
    var typeEl = document.querySelector('#wa-activity-type');
    var applyBtn = document.querySelector('#wa-apply-btn');
    var resetBtn = document.querySelector('#wa-reset-btn');
    var exportBtn = document.querySelector('#wa-export-btn');
    var usersBadge = document.querySelector('#wa-users-badge');
    var rawBadge = document.querySelector('#wa-raw-badge');
    var usersTableEl = document.querySelector('#waUsersTable');
    var rawTableEl = document.querySelector('#waRawTable');

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
            from: fromEl ? fromEl.value : '',
            to: toEl ? toEl.value : '',
            site: siteEl ? siteEl.value : '',
            company: companyEl ? companyEl.value : '',
            division: divisionEl ? divisionEl.value.trim() : '',
            activity_type: typeEl ? typeEl.value : ''
        };
    }

    function updateExportHref() {
        if (!exportBtn) {
            return;
        }
        var filters = currentFilters();
        var params = new URLSearchParams();
        Object.keys(filters).forEach(function (key) {
            if (filters[key]) {
                params.set(key, filters[key]);
            }
        });
        var query = params.toString();
        exportBtn.href = query ? (exportUrl + '?' + query) : exportUrl;
    }

    function formatNumber(value, digits) {
        var num = Number(value || 0);
        return num.toLocaleString('id-ID', {
            minimumFractionDigits: digits || 0,
            maximumFractionDigits: digits || 0
        });
    }

    var usersTable = usersTableEl ? new DataTable(usersTableEl, {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[4, 'desc']],
        autoWidth: false,
        ajax: {
            url: dataUrl,
            data: function (d) {
                var filters = currentFilters();
                Object.keys(filters).forEach(function (key) { d[key] = filters[key]; });
            }
        },
        columns: [
            {
                data: 'nama',
                className: 'wa-col-nama',
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data;
                    }
                    return '<a href="' + employeeShowBase + '/' + row.id + '" class="text-primary-light hover-text-primary fw-medium">'
                        + escapeHtml(data)
                        + '</a>'
                        + '<span class="text-sm d-block fw-normal text-secondary-light">'
                        + escapeHtml(row.kode_sid)
                        + '</span>';
                }
            },
            { data: 'site' },
            { data: 'company' },
            { data: 'divisi' },
            { data: 'sesi', className: 'text-center fw-semibold' },
            {
                data: 'duration_minutes',
                className: 'text-end',
                render: function (data) { return formatNumber(data, 1); }
            },
            {
                data: 'distance_km',
                className: 'text-end',
                render: function (data) { return formatNumber(data, 2); }
            },
            {
                data: 'kcal_out',
                className: 'text-end',
                render: function (data) { return formatNumber(data, 0); }
            },
            {
                data: 'kcal_in',
                className: 'text-end',
                render: function (data) { return formatNumber(data, 0); }
            },
            { data: 'last_workout_at' }
        ],
        language: {
            processing: 'Memuat...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(difilter dari _MAX_ total data)',
            zeroRecords: 'Tidak ada karyawan dengan log olahraga di periode ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    }) : null;

    var rawTable = null;

    function bindRawTableEvents() {
        if (!rawTable) {
            return;
        }
        rawTable.on('draw', function () {
            if (rawBadge) {
                rawBadge.textContent = Number(rawTable.page.info().recordsDisplay || 0).toLocaleString('id-ID');
            }
        });
    }

    function initRawTable() {
        if (rawTable || !rawTableEl) {
            return;
        }
        rawTable = new DataTable(rawTableEl, {
            processing: true,
            serverSide: true,
            searching: true,
            ordering: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'desc']],
            autoWidth: false,
            ajax: {
                url: rawDataUrl,
                data: function (d) {
                    var filters = currentFilters();
                    Object.keys(filters).forEach(function (key) { d[key] = filters[key]; });
                }
            },
            columns: [
                { data: 'local_datetime' },
                {
                    data: 'nama',
                    className: 'wa-col-nama',
                    render: function (data, type, row) {
                        if (type !== 'display') {
                            return data;
                        }
                        return '<span class="fw-medium">' + escapeHtml(data) + '</span>'
                            + '<span class="text-sm d-block fw-normal text-secondary-light">'
                            + escapeHtml(row.kode_sid)
                            + '</span>';
                    }
                },
                { data: 'activity_type', className: 'wa-col-type' },
                {
                    data: 'duration_minutes',
                    className: 'text-end',
                    render: function (data) { return data === null || data === '' ? '-' : formatNumber(data, 1); }
                },
                {
                    data: 'distance_km',
                    className: 'text-end',
                    render: function (data) { return data === null || data === '' ? '-' : formatNumber(data, 2); }
                },
                {
                    data: 'calories_kcal',
                    className: 'text-end',
                    render: function (data) { return data === null || data === '' ? '-' : formatNumber(data, 0); }
                },
                {
                    data: 'avg_heart_rate',
                    render: function (data) { return data ? escapeHtml(data) : '-'; }
                }
            ],
            language: {
                processing: 'Memuat...',
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(difilter dari _MAX_ total data)',
                zeroRecords: 'Tidak ada log olahraga di periode ini.',
                paginate: { first: '«', last: '»', next: '›', previous: '‹' }
            }
        });
        bindRawTableEvents();
    }

    if (usersTable) {
        usersTable.on('draw', function () {
            if (usersBadge) {
                usersBadge.textContent = Number(usersTable.page.info().recordsDisplay || 0).toLocaleString('id-ID');
            }
            updateExportHref();
        });
    }
    var rawTab = document.querySelector('#wa-raw-tab');
    if (rawTab) {
        rawTab.addEventListener('shown.bs.tab', function () {
            initRawTable();
            if (rawTable) {
                rawTable.columns.adjust();
            }
        });
    }

    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            var filters = currentFilters();
            var params = new URLSearchParams();
            Object.keys(filters).forEach(function (key) {
                if (filters[key]) {
                    params.set(key, filters[key]);
                }
            });
            var query = params.toString();
            window.location.href = indexUrl + (query ? ('?' + query) : '');
        });
    }
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            window.location.href = indexUrl;
        });
    }
    if (divisionEl) {
        divisionEl.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (applyBtn) {
                    applyBtn.click();
                }
            }
        });
    }

    updateExportHref();
})();
</script>
@endsection

@section('content')
@php
  $f = $filters ?? ['from' => '', 'to' => '', 'site' => '', 'company' => '', 'division' => '', 'activity_type' => ''];
  $opts = $filterOptions ?? ['sites' => [], 'companies' => [], 'divisions' => [], 'activity_types' => []];
  $kpi = $kpi ?? [
    'total_sessions' => 0, 'active_users' => 0, 'total_minutes' => 0,
    'total_km' => 0, 'kcal_out' => 0, 'kcal_in' => 0,
    'avg_sessions_per_week' => 0, 'avg_sessions_per_user' => 0, 'period_days' => 30,
  ];
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Tren Aktivitas</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('evaluasi-well.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Tren Aktivitas</li>
  </ul>
</div>

@unless ($connectionUp ?? false)
<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8 d-flex align-items-start gap-2" role="alert">
  <iconify-icon icon="solar:danger-triangle-bold" class="icon text-xl mt-1"></iconify-icon>
  <div>Koneksi BeWell tidak tersedia. Pastikan <code>start-bewell-tunnel.bat</code> berjalan.</div>
</div>
@endunless

@if (! empty($loadError))
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 radius-8 d-flex align-items-start gap-2" role="alert">
  <iconify-icon icon="solar:danger-triangle-bold" class="icon text-xl mt-1"></iconify-icon>
  <div>{{ $loadError }}</div>
</div>
@endif

<div class="alert alert-info bg-info-50 text-info-600 border-info-100 px-24 py-13 mb-24 radius-8 d-flex align-items-start gap-2" role="status">
  <iconify-icon icon="solar:info-circle-bold" class="icon text-xl mt-1"></iconify-icon>
  <div class="text-sm">
    Sumber: log WELL <strong>workout_analyses</strong> (olahraga) dan <strong>food_analyses</strong> (kalori masuk).
    KPI header memakai agregasi SQL (sesi &amp; kalori) supaya halaman tidak timeout lewat tunnel.
    Durasi/jarak di-parse dari teks di tabel per halaman dan Excel. Jarak hanya untuk lari/jalan.
  </div>
</div>

<div class="row gy-4 mb-24">
  <div class="col-xxl-2 col-sm-6">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-1">
      <div class="card-body p-2">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="mb-0 w-48-px h-48-px bg-primary-600 flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
            <iconify-icon icon="solar:running-round-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="fw-medium text-secondary-light text-sm mb-0">Sesi olahraga</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpi['total_sessions'] ?? 0) }}</h6>
          </div>
        </div>
        <p class="text-sm mb-0 text-secondary-light wa-kpi-note">{{ number_format($kpi['avg_sessions_per_week'] ?? 0, 1) }} sesi/minggu</p>
      </div>
    </div>
  </div>
  <div class="col-xxl-2 col-sm-6">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-2">
      <div class="card-body p-2">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="mb-0 w-48-px h-48-px bg-success-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
            <iconify-icon icon="solar:users-group-rounded-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="fw-medium text-secondary-light text-sm mb-0">Karyawan aktif</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpi['active_users'] ?? 0) }}</h6>
          </div>
        </div>
        <p class="text-sm mb-0 text-secondary-light wa-kpi-note">Rata {{ number_format($kpi['avg_sessions_per_user'] ?? 0, 1) }} sesi/orang</p>
      </div>
    </div>
  </div>
  <div class="col-xxl-2 col-sm-6">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-3">
      <div class="card-body p-2">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="mb-0 w-48-px h-48-px bg-warning-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
            <iconify-icon icon="solar:clock-circle-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="fw-medium text-secondary-light text-sm mb-0">Durasi</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpi['total_minutes'] ?? 0) }} <span class="text-sm fw-normal">menit</span></h6>
          </div>
        </div>
        <p class="text-sm mb-0 text-secondary-light wa-kpi-note">Detail di tabel &amp; Excel</p>
      </div>
    </div>
  </div>
  <div class="col-xxl-2 col-sm-6">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-4">
      <div class="card-body p-2">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="mb-0 w-48-px h-48-px bg-info-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
            <iconify-icon icon="solar:map-point-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="fw-medium text-secondary-light text-sm mb-0">Jarak lari/jalan</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpi['total_km'] ?? 0, 1) }} <span class="text-sm fw-normal">km</span></h6>
          </div>
        </div>
        <p class="text-sm mb-0 text-secondary-light wa-kpi-note">Detail di tabel &amp; Excel</p>
      </div>
    </div>
  </div>
  <div class="col-xxl-2 col-sm-6">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-5">
      <div class="card-body p-2">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="mb-0 w-48-px h-48-px bg-danger-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
            <iconify-icon icon="solar:fire-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="fw-medium text-secondary-light text-sm mb-0">Kkal keluar</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpi['kcal_out'] ?? 0) }}</h6>
          </div>
        </div>
        <p class="text-sm mb-0 text-secondary-light wa-kpi-note">Dari log olahraga</p>
      </div>
    </div>
  </div>
  <div class="col-xxl-2 col-sm-6">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-1">
      <div class="card-body p-2">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="mb-0 w-48-px h-48-px bg-success-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
            <iconify-icon icon="solar:cup-hot-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="fw-medium text-secondary-light text-sm mb-0">Kkal masuk</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpi['kcal_in'] ?? 0) }}</h6>
          </div>
        </div>
        <p class="text-sm mb-0 text-secondary-light wa-kpi-note">Dari log makanan</p>
      </div>
    </div>
  </div>
</div>

<div class="card radius-8 border-0 shadow-sm mb-24">
  <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h6 class="text-lg fw-semibold mb-0">Tren aktivitas</h6>
      <p class="text-sm text-secondary-light mb-0">{{ $periodLabel ?? '' }} · sesi vs kalori masuk/keluar</p>
    </div>
    <div class="btn-group" role="group" aria-label="Granularitas tren">
      <button type="button" class="btn btn-sm btn-outline-primary-600 wa-granularity-btn is-active" data-wa-granularity="daily">Harian</button>
      <button type="button" class="btn btn-sm btn-outline-primary-600 wa-granularity-btn" data-wa-granularity="weekly">Mingguan</button>
    </div>
  </div>
  <div class="card-body p-24">
    <div id="waTrendChart"></div>
  </div>
</div>

<div class="row gy-4 mb-24">
  <div class="col-xxl-5">
    <div class="card radius-8 border-0 shadow-sm h-100">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-0">Jenis olahraga</h6>
      </div>
      <div class="card-body p-24">
        <div id="waDistChart"></div>
      </div>
    </div>
  </div>
  <div class="col-xxl-7">
    <div class="card radius-8 border-0 shadow-sm h-100">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-0">Top 10 perusahaan by sesi</h6>
      </div>
      <div class="card-body p-24">
        <div id="waCompanyChart"></div>
      </div>
    </div>
  </div>
</div>

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="text-lg fw-semibold mb-4">Data karyawan &amp; log mentah</h6>
        <p class="text-sm text-secondary-light mb-0">
          Filter periode (maks. 90 hari). Excel berisi ringkasan + raw olahraga + raw makanan + tren harian.
        </p>
      </div>
      <a id="wa-export-btn" href="{{ route('evaluasi-well.activities.export', request()->query()) }}" class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
        <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
        Download Excel
      </a>
    </div>
  </div>
  <div class="card-body p-24">
    <div class="bg-neutral-50 border radius-8 p-16 mb-20">
      <div class="row g-3 align-items-end">
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="wa-from" class="form-label text-sm fw-medium mb-6">Dari</label>
          <input id="wa-from" type="date" class="form-control form-control-sm" value="{{ $f['from'] ?? '' }}">
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="wa-to" class="form-label text-sm fw-medium mb-6">Sampai</label>
          <input id="wa-to" type="date" class="form-control form-control-sm" value="{{ $f['to'] ?? '' }}">
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="wa-site" class="form-label text-sm fw-medium mb-6">Site</label>
          <select id="wa-site" class="form-select form-select-sm">
            <option value="">Semua Site</option>
            @foreach ($opts['sites'] as $site)
              <option value="{{ $site }}" @selected(($f['site'] ?? '') === $site)>{{ $site }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="wa-company" class="form-label text-sm fw-medium mb-6">Perusahaan</label>
          <select id="wa-company" class="form-select form-select-sm">
            <option value="">Semua Perusahaan</option>
            @foreach ($opts['companies'] as $company)
              <option value="{{ $company }}" @selected(($f['company'] ?? '') === $company)>{{ $company }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="wa-division" class="form-label text-sm fw-medium mb-6">Divisi</label>
          <input
            id="wa-division"
            type="search"
            list="wa-division-options"
            class="form-control form-control-sm"
            placeholder="Cari divisi..."
            value="{{ $f['division'] ?? '' }}"
            autocomplete="off"
          >
          <datalist id="wa-division-options">
            @foreach ($opts['divisions'] as $division)
              <option value="{{ $division }}"></option>
            @endforeach
          </datalist>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="wa-activity-type" class="form-label text-sm fw-medium mb-6">Jenis aktivitas</label>
          <select id="wa-activity-type" class="form-select form-select-sm">
            <option value="">Semua jenis</option>
            @foreach ($opts['activity_types'] as $type)
              <option value="{{ $type }}" @selected(($f['activity_type'] ?? '') === $type)>{{ $type }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12">
          <div class="d-flex gap-2 justify-content-end">
            <button type="button" id="wa-reset-btn" class="btn btn-sm btn-outline-secondary">Reset</button>
            <button type="button" id="wa-apply-btn" class="btn btn-sm btn-primary-600">Terapkan</button>
          </div>
        </div>
      </div>
    </div>

    <ul class="nav nav-pills mb-20 gap-2" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="wa-users-tab" data-bs-toggle="tab" data-bs-target="#wa-users-pane" type="button" role="tab">
          Ringkasan karyawan
          <span id="wa-users-badge" class="bg-primary-50 text-primary-600 text-xs fw-medium px-8 py-2 rounded-pill ms-1">0</span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="wa-raw-tab" data-bs-toggle="tab" data-bs-target="#wa-raw-pane" type="button" role="tab">
          Log olahraga (raw)
          <span id="wa-raw-badge" class="bg-neutral-200 text-secondary-light text-xs fw-medium px-8 py-2 rounded-pill ms-1">0</span>
        </button>
      </li>
    </ul>

    <div class="tab-content">
      <div class="tab-pane fade show active" id="wa-users-pane" role="tabpanel">
        <div class="table-responsive">
          <table id="waUsersTable" class="table bordered-table mb-0 w-100">
            <thead>
              <tr>
                <th>Karyawan</th>
                <th>Site</th>
                <th>Perusahaan</th>
                <th>Divisi</th>
                <th>Sesi</th>
                <th>Menit</th>
                <th>Km</th>
                <th>Kkal out</th>
                <th>Kkal in</th>
                <th>Terakhir</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="tab-pane fade" id="wa-raw-pane" role="tabpanel">
        <div class="table-responsive">
          <table id="waRawTable" class="table bordered-table mb-0 w-100">
            <thead>
              <tr>
                <th>Waktu</th>
                <th>Karyawan</th>
                <th>Jenis</th>
                <th>Menit</th>
                <th>Km</th>
                <th>Kkal</th>
                <th>HR</th>
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
