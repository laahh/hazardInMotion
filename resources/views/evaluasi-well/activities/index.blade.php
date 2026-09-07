@extends('evaluasi-well.layouts.app')

@section('title', 'Tren Aktivitas')

@section('css')
<style>
  .wa-page { color: #1f2937; }
  .wa-hero {
    background: linear-gradient(118deg, #123a7a 0%, #2f6dff 48%, #1fa971 100%);
    border-radius: 18px;
    padding: 28px 28px 24px;
    color: #fff;
    position: relative;
    overflow: hidden;
  }
  .wa-hero::after {
    content: '';
    position: absolute;
    right: -40px;
    top: -50px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(255,255,255,.12);
  }
  .wa-hero::before {
    content: '';
    position: absolute;
    right: 80px;
    bottom: -70px;
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background: rgba(255,255,255,.08);
  }
  .wa-hero-inner { position: relative; z-index: 1; }
  .wa-hero a, .wa-hero .wa-crumb { color: rgba(255,255,255,.82); }
  .wa-hero h5 { color: #fff; letter-spacing: -.02em; }
  .wa-period-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,.16);
    border: 1px solid rgba(255,255,255,.25);
    color: #fff;
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 12px;
  }
  .wa-preset-btn {
    border-radius: 999px !important;
    border-color: rgba(255,255,255,.35) !important;
    color: #fff !important;
    background: transparent;
  }
  .wa-preset-btn:hover { background: rgba(255,255,255,.14) !important; color: #fff !important; }
  .wa-preset-btn.is-active,
  .wa-granularity-btn.is-active,
  .wa-report-mode-btn.is-active {
    background: #fff !important;
    color: #1d4ed8 !important;
    border-color: #fff !important;
  }
  .wa-granularity-btn.is-active,
  .wa-report-mode-btn.is-active {
    background: #487fff !important;
    color: #fff !important;
    border-color: #487fff !important;
  }
  .wa-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
  }
  @media (max-width: 1199px) { .wa-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 575px) { .wa-stats { grid-template-columns: 1fr; } }
  .wa-stat {
    background: #fff;
    border: 1px solid #eef2f7;
    border-radius: 16px;
    padding: 18px 18px 16px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
    min-height: 108px;
  }
  .wa-stat-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
  .wa-stat-value { font-size: 26px; font-weight: 700; line-height: 1.15; margin: 6px 0 4px; color: #0f172a; }
  .wa-stat-note { font-size: 12px; color: #64748b; margin: 0; }
  .wa-stat-icon {
    width: 40px; height: 40px; border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 20px;
  }
  .wa-panel {
    border: 1px solid #eef2f7 !important;
    border-radius: 16px !important;
    box-shadow: 0 10px 28px rgba(15, 23, 42, .05) !important;
    overflow: hidden;
  }
  .wa-panel .card-header {
    background: #fff !important;
    border-bottom: 1px solid #f1f5f9 !important;
  }
  .wa-toolbar {
    background: #fff;
    border: 1px solid #eef2f7;
    border-radius: 16px;
    padding: 16px 18px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
  }
  .wa-filters-more summary {
    cursor: pointer;
    list-style: none;
    font-size: 13px;
    font-weight: 600;
    color: #487fff;
  }
  .wa-filters-more summary::-webkit-details-marker { display: none; }
  .wa-rank {
    width: 28px; height: 28px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; background: #eef2f7; color: #475569;
  }
  .wa-rank-1 { background: #f5c518; color: #7a4d00; }
  .wa-rank-2 { background: #c9d3df; color: #334155; }
  .wa-rank-3 { background: #e8b089; color: #7c3a0b; }
  .wa-alert { border-radius: 14px !important; }
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
  #waTrendChart, #waDistChart { min-height: 300px; }
  #waLeaderboardTable_wrapper .dt-search input { min-width: 180px; }
</style>
@endsection

@section('page-scripts')
@php
  $waTrendDaily = $trendDaily ?? [];
  $waTrendWeekly = $trendWeekly ?? [];
  $waDistribution = $distribution ?? [];
@endphp
<script>
(function () {
    var trendDaily = @json($waTrendDaily);
    var trendWeekly = @json($waTrendWeekly);
    var distribution = @json($waDistribution);
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
        var distCounts = distribution.counts || [];
        new ApexCharts(distEl, {
            series: [{ name: 'Frekuensi', data: distLabels.length ? distCounts : [] }],
            colors: ['#487FFF', '#45B369', '#FF9F29', '#8252E9', '#F86624', '#0EA5E9', '#EAB308', '#EF4444', '#14B8A6', '#A855F7', '#64748B', '#16A34A'],
            chart: { type: 'bar', height: 340, toolbar: { show: false } },
            xaxis: {
                categories: distLabels.length ? distLabels : ['Tidak ada data'],
                labels: { rotate: -35, style: { fontSize: '11px' } }
            },
            yaxis: {
                title: { text: 'Frekuensi' },
                labels: { formatter: function (v) { return Math.round(v); } }
            },
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
    var namaEl = document.querySelector('#wa-nama');
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

    function pad2(n) {
        return String(n).padStart(2, '0');
    }

    function ymd(date) {
        return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
    }

    function mondayOf(date) {
        var d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        var day = d.getDay();
        var diff = day === 0 ? -6 : 1 - day;
        d.setDate(d.getDate() + diff);
        return d;
    }

    function currentFilters() {
        return {
            from: fromEl ? fromEl.value : '',
            to: toEl ? toEl.value : '',
            site: siteEl ? siteEl.value : '',
            company: companyEl ? companyEl.value : '',
            division: divisionEl ? divisionEl.value.trim() : '',
            activity_type: typeEl ? typeEl.value : '',
            nama: namaEl ? namaEl.value.trim() : '',
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

    var periodTable = periodTableEl ? new DataTable(periodTableEl, {
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

    if (periodTable) {
        periodTable.on('draw', function () {
            if (periodBadge) {
                periodBadge.textContent = Number(periodTable.page.info().recordsDisplay || 0).toLocaleString('id-ID');
            }
            updateExportHref();
        });
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

    document.querySelectorAll('[data-wa-preset]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var preset = btn.getAttribute('data-wa-preset') || '';
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            if (!fromEl || !toEl) {
                return;
            }
            if (preset === 'today') {
                fromEl.value = ymd(today);
                toEl.value = ymd(today);
                reportMode = 'day';
            } else if (preset === 'week') {
                fromEl.value = ymd(mondayOf(today));
                toEl.value = ymd(today);
                reportMode = 'week';
            } else if (preset === '7d') {
                var from7 = new Date(today);
                from7.setDate(from7.getDate() - 6);
                fromEl.value = ymd(from7);
                toEl.value = ymd(today);
                reportMode = 'day';
            } else if (preset === '30d') {
                var from30 = new Date(today);
                from30.setDate(from30.getDate() - 29);
                fromEl.value = ymd(from30);
                toEl.value = ymd(today);
                reportMode = 'day';
            }
            if (applyBtn) {
                applyBtn.click();
            }
        });
    });

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
    if (namaEl) {
        namaEl.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (applyBtn) {
                    applyBtn.click();
                }
            }
        });
    }

    updateExportHref();

    (function markActivePreset() {
        if (!fromEl || !toEl) {
            return;
        }
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var from7 = new Date(today);
        from7.setDate(from7.getDate() - 6);
        var from30 = new Date(today);
        from30.setDate(from30.getDate() - 29);
        var expected = {
            today: [ymd(today), ymd(today)],
            week: [ymd(mondayOf(today)), ymd(today)],
            '7d': [ymd(from7), ymd(today)],
            '30d': [ymd(from30), ymd(today)]
        };
        document.querySelectorAll('[data-wa-preset]').forEach(function (btn) {
            var key = btn.getAttribute('data-wa-preset') || '';
            var pair = expected[key];
            btn.classList.toggle('is-active', !!(pair && fromEl.value === pair[0] && toEl.value === pair[1]));
        });
    })();
})();
</script>
@endsection

@section('content')
@php
  $f = $filters ?? ['from' => '', 'to' => '', 'site' => '', 'company' => '', 'division' => '', 'activity_type' => '', 'nama' => '', 'report_mode' => 'day'];
  $opts = $filterOptions ?? ['sites' => [], 'companies' => [], 'divisions' => [], 'activity_types' => []];
  $kpi = $kpi ?? [
    'total_sessions' => 0, 'active_users' => 0, 'total_minutes' => 0,
    'total_km' => 0, 'kcal_out' => 0, 'kcal_in' => 0,
    'avg_sessions_per_week' => 0, 'avg_sessions_per_user' => 0, 'period_days' => 7,
  ];
  $reportMode = ($f['report_mode'] ?? 'day') === 'week' ? 'week' : 'day';
  $hasAdvancedFilter = ($f['nama'] ?? '') !== ''
    || ($f['site'] ?? '') !== ''
    || ($f['company'] ?? '') !== ''
    || ($f['division'] ?? '') !== ''
    || ($f['activity_type'] ?? '') !== '';
@endphp

<div class="wa-page">
  <div class="wa-hero mb-24">
    <div class="wa-hero-inner">
      <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-12">
        <div>
          <div class="d-flex align-items-center gap-2 mb-8 wa-crumb">
            <a href="{{ route('evaluasi-well.index') }}" class="d-flex align-items-center gap-1 text-decoration-none">
              <iconify-icon icon="solar:home-smile-angle-outline" class="icon"></iconify-icon>
              Dashboard
            </a>
            <span>·</span>
            <span>Tren Aktivitas</span>
          </div>
          <h5 class="fw-bold mb-8">Siapa yang olahraga hari ini?</h5>
          <p class="mb-0 text-sm" style="color: rgba(255,255,255,.82); max-width: 560px;">
            Ringkasan sesi, kalori, dan peringkat karyawan dari log WELL — bukan program weight-loss.
          </p>
        </div>
        <span class="wa-period-chip">
          <iconify-icon icon="solar:calendar-mark-bold" class="icon"></iconify-icon>
          {{ $periodLabel ?? 'Periode' }}
        </span>
      </div>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group" role="group" aria-label="Preset periode">
          <button type="button" class="btn btn-sm wa-preset-btn" data-wa-preset="today">Hari ini</button>
          <button type="button" class="btn btn-sm wa-preset-btn" data-wa-preset="week">Minggu ini</button>
          <button type="button" class="btn btn-sm wa-preset-btn" data-wa-preset="7d">7 hari</button>
          <button type="button" class="btn btn-sm wa-preset-btn" data-wa-preset="30d">30 hari</button>
        </div>
      </div>
    </div>
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

  <div class="wa-toolbar mb-24">
    <div class="row g-3 align-items-end">
      <div class="col-xl-2 col-md-4 col-sm-6">
        <label for="wa-from" class="form-label text-sm fw-medium mb-6">Dari</label>
        <input id="wa-from" type="date" class="form-control form-control-sm" value="{{ $f['from'] ?? '' }}">
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <label for="wa-to" class="form-label text-sm fw-medium mb-6">Sampai</label>
        <input id="wa-to" type="date" class="form-control form-control-sm" value="{{ $f['to'] ?? '' }}">
      </div>
      <div class="col-xl-8 col-md-4 col-sm-12">
        <div class="d-flex gap-2 justify-content-xl-end">
          <button type="button" id="wa-reset-btn" class="btn btn-sm btn-outline-secondary">Reset</button>
          <button type="button" id="wa-apply-btn" class="btn btn-sm btn-primary-600">Terapkan</button>
        </div>
      </div>
    </div>
    <details class="wa-filters-more mt-16" @if ($hasAdvancedFilter) open @endif>
      <summary class="mb-12">Filter lanjutan — nama, site, perusahaan, divisi, jenis</summary>
      <div class="row g-3">
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="wa-nama" class="form-label text-sm fw-medium mb-6">Nama</label>
          <input id="wa-nama" type="search" class="form-control form-control-sm" placeholder="Cari nama..." value="{{ $f['nama'] ?? '' }}" autocomplete="off">
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
          <label for="wa-activity-type" class="form-label text-sm fw-medium mb-6">Jenis</label>
          <select id="wa-activity-type" class="form-select form-select-sm">
            <option value="">Semua jenis</option>
            @foreach ($opts['activity_types'] as $type)
              <option value="{{ $type }}" @selected(($f['activity_type'] ?? '') === $type)>{{ $type }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </details>
  </div>

  <div class="wa-stats mb-24">
    <div class="wa-stat">
      <div class="d-flex align-items-center justify-content-between mb-4">
        <span class="wa-stat-label">Sesi olahraga</span>
        <span class="wa-stat-icon" style="background:#487fff;"><iconify-icon icon="solar:running-round-bold"></iconify-icon></span>
      </div>
      <div class="wa-stat-value">{{ number_format($kpi['total_sessions'] ?? 0) }}</div>
      <p class="wa-stat-note">{{ number_format($kpi['avg_sessions_per_week'] ?? 0, 1) }} sesi/minggu</p>
    </div>
    <div class="wa-stat">
      <div class="d-flex align-items-center justify-content-between mb-4">
        <span class="wa-stat-label">Karyawan aktif</span>
        <span class="wa-stat-icon" style="background:#45b369;"><iconify-icon icon="solar:users-group-rounded-bold"></iconify-icon></span>
      </div>
      <div class="wa-stat-value">{{ number_format($kpi['active_users'] ?? 0) }}</div>
      <p class="wa-stat-note">Rata {{ number_format($kpi['avg_sessions_per_user'] ?? 0, 1) }} sesi/orang</p>
    </div>
    <div class="wa-stat">
      <div class="d-flex align-items-center justify-content-between mb-4">
        <span class="wa-stat-label">Kkal keluar</span>
        <span class="wa-stat-icon" style="background:#f86624;"><iconify-icon icon="solar:fire-bold"></iconify-icon></span>
      </div>
      <div class="wa-stat-value">{{ number_format($kpi['kcal_out'] ?? 0) }}</div>
      <p class="wa-stat-note">Dari log olahraga</p>
    </div>
    <div class="wa-stat">
      <div class="d-flex align-items-center justify-content-between mb-4">
        <span class="wa-stat-label">Kkal masuk</span>
        <span class="wa-stat-icon" style="background:#16a34a;"><iconify-icon icon="solar:cup-hot-bold"></iconify-icon></span>
      </div>
      <div class="wa-stat-value">{{ number_format($kpi['kcal_in'] ?? 0) }}</div>
      <p class="wa-stat-note">Dari log makanan</p>
    </div>
  </div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-7">
      <div class="card wa-panel h-100">
        <div class="card-header py-16 px-24 d-flex align-items-start justify-content-between flex-wrap gap-2">
          <div>
            <h6 class="text-lg fw-semibold mb-0">Leaderboard kalori</h6>
            <p class="text-sm text-secondary-light mb-0">10 orang per halaman · ranking by kkal olahraga</p>
          </div>
        </div>
        <div class="card-body p-20">
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
    </div>
    <div class="col-xxl-5">
      <div class="card wa-panel h-100">
        <div class="card-header py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Jenis olahraga</h6>
          <p class="text-sm text-secondary-light mb-0">Frekuensi per jenis di periode terpilih</p>
        </div>
        <div class="card-body p-20">
          <div id="waDistChart"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card wa-panel mb-24">
    <div class="card-header py-16 px-24 d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Tren sesi &amp; kalori</h6>
        <p class="text-sm text-secondary-light mb-0">{{ $periodLabel ?? '' }} · sesi vs kkal masuk/keluar</p>
      </div>
      <div class="btn-group" role="group" aria-label="Granularitas tren">
        <button type="button" class="btn btn-sm btn-outline-primary-600 wa-granularity-btn is-active" data-wa-granularity="daily">Harian</button>
        <button type="button" class="btn btn-sm btn-outline-primary-600 wa-granularity-btn" data-wa-granularity="weekly">Mingguan</button>
      </div>
    </div>
    <div class="card-body p-20">
      <div id="waTrendChart"></div>
    </div>
  </div>

  <div class="card wa-panel">
    <div class="card-header py-16 px-24">
      <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
          <h6 class="text-lg fw-semibold mb-4">Laporan siapa yang olahraga</h6>
          <p class="text-sm text-secondary-light mb-0">Satu baris per orang per hari atau per minggu.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <div class="btn-group" role="group" aria-label="Mode laporan">
            <button type="button" class="btn btn-sm btn-outline-primary-600 wa-report-mode-btn {{ $reportMode === 'day' ? 'is-active' : '' }}" data-wa-report-mode="day">Harian</button>
            <button type="button" class="btn btn-sm btn-outline-primary-600 wa-report-mode-btn {{ $reportMode === 'week' ? 'is-active' : '' }}" data-wa-report-mode="week">Mingguan</button>
          </div>
          <a id="wa-export-btn" href="{{ route('evaluasi-well.activities.export', request()->query()) }}" class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
            <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
            Download Excel
          </a>
        </div>
      </div>
    </div>
    <div class="card-body p-20">
      <ul class="nav nav-pills mb-20 gap-2" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="wa-period-tab" data-bs-toggle="tab" data-bs-target="#wa-period-pane" type="button" role="tab">
            Laporan periode
            <span id="wa-period-badge" class="bg-primary-50 text-primary-600 text-xs fw-medium px-8 py-2 rounded-pill ms-1">0</span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="wa-users-tab" data-bs-toggle="tab" data-bs-target="#wa-users-pane" type="button" role="tab">
            Ringkasan karyawan
            <span id="wa-users-badge" class="bg-neutral-200 text-secondary-light text-xs fw-medium px-8 py-2 rounded-pill ms-1">0</span>
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
        <div class="tab-pane fade show active" id="wa-period-pane" role="tabpanel">
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
        <div class="tab-pane fade" id="wa-users-pane" role="tabpanel">
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
</div>
@endsection
