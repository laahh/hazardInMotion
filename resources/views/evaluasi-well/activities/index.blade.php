@extends('evaluasi-well.layouts.app')

@section('title', 'Tren Aktivitas')

@section('css')
<style>
  .wa-report-mode-btn.is-active {
    background: #487fff !important;
    color: #fff !important;
    border-color: #487fff !important;
  }
  .wa-rank {
    width: 28px; height: 28px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; background: #eef2f7; color: #475569;
  }
  .wa-rank-1 { background: #f5c518; color: #7a4d00; }
  .wa-rank-2 { background: #c9d3df; color: #334155; }
  .wa-rank-3 { background: #e8b089; color: #7c3a0b; }
  .wa-alert { border-radius: 8px !important; }
  .dt-container:has(#waUsersTable) .dt-layout-row,
  #waUsersTable_wrapper .dt-layout-row,
  .dt-container:has(#waRawTable) .dt-layout-row,
  #waRawTable_wrapper .dt-layout-row,
  .dt-container:has(#waPeriodTable) .dt-layout-row,
  #waPeriodTable_wrapper .dt-layout-row,
  .dt-container:has(#waLeaderboardTable) .dt-layout-row,
  #waLeaderboardTable_wrapper .dt-layout-row {
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
  #waRawTable_wrapper .dt-paging .dt-paging-button,
  .dt-container:has(#waPeriodTable) .dt-paging .dt-paging-button,
  #waPeriodTable_wrapper .dt-paging .dt-paging-button,
  .dt-container:has(#waLeaderboardTable) .dt-paging .dt-paging-button,
  #waLeaderboardTable_wrapper .dt-paging .dt-paging-button {
    width: auto !important;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.625rem !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    border-radius: 6px !important;
  }
  #waUsersTable, #waRawTable, #waPeriodTable, #waLeaderboardTable { width: 100% !important; }
  #waUsersTable th, #waUsersTable td,
  #waRawTable th, #waRawTable td,
  #waPeriodTable th, #waPeriodTable td,
  #waLeaderboardTable th, #waLeaderboardTable td {
    vertical-align: middle;
    white-space: nowrap;
  }
  #waUsersTable td.wa-col-nama,
  #waRawTable td.wa-col-nama,
  #waRawTable td.wa-col-type,
  #waPeriodTable td.wa-col-nama,
  #waPeriodTable td.wa-col-jenis,
  #waLeaderboardTable td.wa-col-nama {
    white-space: normal;
    min-width: 140px;
  }
  #waDistChart { min-height: 320px; }
  #waCompanyChart { min-height: 360px; }
  #waLeaderboardTable_wrapper .dt-search input { min-width: 180px; }
</style>
@endsection

@section('page-scripts')
@php
  $waDistribution = $distribution ?? [];
  $waTopCompanies = $topCompanies ?? [];
@endphp
<script>
(function () {
    var distribution = @json($waDistribution);
    var topCompanies = @json($waTopCompanies);

    var distEl = document.querySelector('#waDistChart');
    if (distEl && typeof ApexCharts !== 'undefined') {
        var distLabels = distribution.labels || [];
        new ApexCharts(distEl, {
            series: distLabels.length ? (distribution.counts || []) : [1],
            labels: distLabels.length ? distLabels : ['Tidak ada data'],
            colors: distLabels.length
                ? ['#487FFF', '#45B369', '#FF9F29', '#8252E9', '#F86624', '#16A34A', '#0EA5E9', '#EAB308', '#EF4444', '#64748B', '#14B8A6', '#A855F7']
                : ['#E5E7EB'],
            chart: { type: 'donut', height: 320 },
            legend: {
                position: 'bottom',
                formatter: function (seriesName, opts) {
                    var totals = opts.w.globals.seriesTotals || [];
                    var total = totals.reduce(function (sum, n) { return sum + Number(n || 0); }, 0);
                    var value = Number(opts.w.globals.series[opts.seriesIndex] || 0);
                    var pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                    return seriesName + ' (' + pct + '%)';
                }
            },
            dataLabels: { enabled: distLabels.length > 0 },
            tooltip: { enabled: distLabels.length > 0 }
        }).render();
    }

    var companyEl = document.querySelector('#waCompanyChart');
    if (companyEl && typeof ApexCharts !== 'undefined') {
        new ApexCharts(companyEl, {
            series: [{ name: 'Sesi', data: topCompanies.counts || [] }],
            colors: ['#487FFF'],
            chart: { type: 'bar', height: 360, toolbar: { show: false } },
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '70%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: topCompanies.labels || [] },
            yaxis: { labels: { maxWidth: 220, style: { fontSize: '11px' } } },
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
    var periodDataUrl = @json(route('evaluasi-well.activities.period-data'));
    var leaderboardDataUrl = @json(route('evaluasi-well.activities.leaderboard-data'));
    var exportUrl = @json(route('evaluasi-well.activities.export'));
    var indexUrl = @json(route('evaluasi-well.activities.index'));

    var fromEl = document.querySelector('#wa-from');
    var toEl = document.querySelector('#wa-to');
    var siteEl = document.querySelector('#wa-site');
    var companyEl = document.querySelector('#wa-company');
    var divisionEl = document.querySelector('#wa-division');
    var typeEl = document.querySelector('#wa-activity-type');
    var reportMode = @json(($filters['report_mode'] ?? 'day'));
    var applyBtn = document.querySelector('#wa-apply-btn');
    var resetBtn = document.querySelector('#wa-reset-btn');
    var exportBtn = document.querySelector('#wa-export-btn');
    var usersBadge = document.querySelector('#wa-users-badge');
    var rawBadge = document.querySelector('#wa-raw-badge');
    var periodBadge = document.querySelector('#wa-period-badge');
    var usersTableEl = document.querySelector('#waUsersTable');
    var rawTableEl = document.querySelector('#waRawTable');
    var periodTableEl = document.querySelector('#waPeriodTable');
    var leaderboardTableEl = document.querySelector('#waLeaderboardTable');

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
            activity_type: typeEl ? typeEl.value : '',
            report_mode: reportMode || 'day'
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

    var dtLanguage = {
        processing: 'Memuat...',
        search: 'Cari:',
        lengthMenu: 'Tampilkan _MENU_ data',
        info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
        infoEmpty: 'Tidak ada data',
        infoFiltered: '(difilter dari _MAX_ total data)',
        paginate: { first: '«', last: '»', next: '›', previous: '‹' }
    };

    var usersTable = null;

    function initUsersTable() {
        if (usersTable || !usersTableEl) {
            return;
        }
        usersTable = new DataTable(usersTableEl, {
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
            language: Object.assign({}, dtLanguage, {
                zeroRecords: 'Tidak ada karyawan dengan log olahraga di periode ini.'
            })
        });
        usersTable.on('draw', function () {
            if (usersBadge) {
                usersBadge.textContent = Number(usersTable.page.info().recordsDisplay || 0).toLocaleString('id-ID');
            }
            updateExportHref();
        });
    }

    var leaderboardTable = leaderboardTableEl ? new DataTable(leaderboardTableEl, {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        paging: true,
        pageLength: 10,
        lengthChange: false,
        order: [[5, 'desc']],
        autoWidth: false,
        ajax: {
            url: leaderboardDataUrl,
            data: function (d) {
                var filters = currentFilters();
                Object.keys(filters).forEach(function (key) { d[key] = filters[key]; });
            }
        },
        columns: [
            { data: 'rank', className: 'text-center', orderable: false, searchable: false, render: function (data, type) {
                if (type !== 'display') { return data; }
                var n = Number(data);
                var cls = 'wa-rank';
                if (n === 1) { cls += ' wa-rank-1'; }
                else if (n === 2) { cls += ' wa-rank-2'; }
                else if (n === 3) { cls += ' wa-rank-3'; }
                return '<span class="' + cls + '">' + n + '</span>';
            } },
            { data: 'kode_sid' },
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
                        + escapeHtml(row.site)
                        + '</span>';
                }
            },
            { data: 'divisi' },
            { data: 'sesi', className: 'text-center fw-semibold' },
            {
                data: 'kcal_out',
                className: 'text-end',
                render: function (data) { return formatNumber(data, 0); }
            }
        ],
        language: Object.assign({}, dtLanguage, {
            zeroRecords: 'Belum ada log olahraga di periode ini.'
        })
    }) : null;

    var periodTable = null;

    function initPeriodTable() {
        if (periodTable || !periodTableEl) {
            return;
        }
        periodTable = new DataTable(periodTableEl, {
            processing: true,
            serverSide: true,
            searching: true,
            ordering: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[5, 'desc']],
            autoWidth: false,
            ajax: {
                url: periodDataUrl,
                data: function (d) {
                    var filters = currentFilters();
                    Object.keys(filters).forEach(function (key) { d[key] = filters[key]; });
                }
            },
            columns: [
                { data: 'period' },
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
                { data: 'divisi' },
                { data: 'sesi', className: 'text-center fw-semibold' },
                {
                    data: 'kcal_out',
                    className: 'text-end',
                    render: function (data) { return formatNumber(data, 0); }
                },
                { data: 'jenis', className: 'wa-col-jenis' }
            ],
            language: Object.assign({}, dtLanguage, {
                zeroRecords: 'Tidak ada karyawan yang olahraga di periode ini.'
            })
        });
        periodTable.on('draw', function () {
            if (periodBadge) {
                periodBadge.textContent = Number(periodTable.page.info().recordsDisplay || 0).toLocaleString('id-ID');
            }
            updateExportHref();
        });
    }

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

    var usersTab = document.querySelector('#wa-users-tab');
    if (usersTab) {
        usersTab.addEventListener('shown.bs.tab', function () {
            initUsersTable();
            if (usersTable) {
                usersTable.columns.adjust();
            }
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
    var periodTab = document.querySelector('#wa-period-tab');
    if (periodTab) {
        periodTab.addEventListener('shown.bs.tab', function () {
            initPeriodTable();
            if (periodTable) {
                periodTable.columns.adjust();
            }
        });
    }

    initUsersTable();

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

    document.querySelectorAll('[data-wa-report-mode]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            reportMode = btn.getAttribute('data-wa-report-mode') || 'day';
            document.querySelectorAll('[data-wa-report-mode]').forEach(function (el) {
                el.classList.toggle('is-active', el === btn);
            });
            if (periodTable) {
                periodTable.ajax.reload();
            }
            updateExportHref();
        });
    });

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
  $f = $filters ?? ['from' => '', 'to' => '', 'site' => '', 'company' => '', 'division' => '', 'activity_type' => '', 'nama' => '', 'report_mode' => 'day'];
  $opts = $filterOptions ?? ['sites' => [], 'companies' => [], 'divisions' => [], 'activity_types' => []];
  $reportMode = ($f['report_mode'] ?? 'day') === 'week' ? 'week' : 'day';
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
<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 wa-alert d-flex align-items-start gap-2" role="alert">
  <iconify-icon icon="solar:danger-triangle-bold" class="icon text-xl mt-1"></iconify-icon>
  <div>Koneksi BeWell tidak tersedia. Pastikan <code>start-bewell-tunnel.bat</code> berjalan.</div>
</div>
@endunless

@if (! empty($loadError))
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 wa-alert d-flex align-items-start gap-2" role="alert">
  <iconify-icon icon="solar:danger-triangle-bold" class="icon text-xl mt-1"></iconify-icon>
  <div>{{ $loadError }}</div>
</div>
@endif

<div class="row gy-4 mb-24">
  <div class="col-xxl-6">
    <div class="card h-100 radius-8 border-0 shadow-sm">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-0">Jenis olahraga</h6>
      </div>
      <div class="card-body p-24">
        <div id="waDistChart"></div>
      </div>
    </div>
  </div>
  <div class="col-xxl-6">
    <div class="card h-100 radius-8 border-0 shadow-sm">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-0">Top 10 perusahaan by sesi</h6>
      </div>
      <div class="card-body p-24">
        <div id="waCompanyChart"></div>
      </div>
    </div>
  </div>
</div>

<div class="card radius-8 border-0 shadow-sm mb-24">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-4">Leaderboard aktivitas kalori</h6>
    <p class="text-sm text-secondary-light mb-0">10 orang per halaman · ranking berdasarkan kkal olahraga pada periode terpilih.</p>
  </div>
  <div class="card-body p-24">
    <div class="table-responsive">
      <table id="waLeaderboardTable" class="table bordered-table mb-0 w-100">
        <thead>
          <tr>
            <th>Rank</th>
            <th>Kode SID</th>
            <th>Nama</th>
            <th>Divisi</th>
            <th>Frekuensi</th>
            <th>Kkal</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="text-lg fw-semibold mb-4">Data karyawan &amp; log mentah</h6>
        <p class="text-sm text-secondary-light mb-0">Filter periode (maks. 90 hari). Excel berisi ringkasan + raw olahraga + raw makanan + tren harian.</p>
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
        <div class="col-xl-3 col-md-4 col-sm-6">
          <label for="wa-company" class="form-label text-sm fw-medium mb-6">Perusahaan</label>
          <select id="wa-company" class="form-select form-select-sm">
            <option value="">Semua Perusahaan</option>
            @foreach ($opts['companies'] as $company)
              <option value="{{ $company }}" @selected(($f['company'] ?? '') === $company)>{{ $company }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xl-3 col-md-4 col-sm-6">
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
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="wa-period-tab" data-bs-toggle="tab" data-bs-target="#wa-period-pane" type="button" role="tab">
          Laporan periode
          <span id="wa-period-badge" class="bg-neutral-200 text-secondary-light text-xs fw-medium px-8 py-2 rounded-pill ms-1">0</span>
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
      <div class="tab-pane fade" id="wa-period-pane" role="tabpanel">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-16">
          <p class="text-sm text-secondary-light mb-0">Satu baris per orang per hari atau per minggu.</p>
          <div class="btn-group" role="group" aria-label="Mode laporan">
            <button type="button" class="btn btn-sm btn-outline-primary-600 wa-report-mode-btn {{ $reportMode === 'day' ? 'is-active' : '' }}" data-wa-report-mode="day">Harian</button>
            <button type="button" class="btn btn-sm btn-outline-primary-600 wa-report-mode-btn {{ $reportMode === 'week' ? 'is-active' : '' }}" data-wa-report-mode="week">Mingguan</button>
          </div>
        </div>
        <div class="table-responsive">
          <table id="waPeriodTable" class="table bordered-table mb-0 w-100">
            <thead>
              <tr>
                <th>Periode</th>
                <th>Karyawan</th>
                <th>Site</th>
                <th>Divisi</th>
                <th>Frekuensi</th>
                <th>Kkal</th>
                <th>Jenis olahraga</th>
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
