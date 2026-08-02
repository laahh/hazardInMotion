@extends('evaluasi-well.layouts.app')

@section('title', 'Dashboard')

@section('css')
<style>
  .not-installed-datatable + .dt-layout-row,
  .dt-container:has(#notInstalledTable) .dt-layout-row,
  #notInstalledTable_wrapper .dt-layout-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0.75rem 0;
  }

  .dt-container:has(#notInstalledTable) .dt-paging,
  #notInstalledTable_wrapper .dt-paging {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.375rem;
  }

  .dt-container:has(#notInstalledTable) .dt-paging .dt-paging-button,
  #notInstalledTable_wrapper .dt-paging .dt-paging-button {
    width: auto !important;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.625rem !important;
    white-space: nowrap !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    line-height: 1 !important;
    border-radius: 6px !important;
  }

  .dt-container:has(#notInstalledTable) .dt-paging .dt-paging-button.first,
  .dt-container:has(#notInstalledTable) .dt-paging .dt-paging-button.previous,
  .dt-container:has(#notInstalledTable) .dt-paging .dt-paging-button.next,
  .dt-container:has(#notInstalledTable) .dt-paging .dt-paging-button.last,
  #notInstalledTable_wrapper .dt-paging .dt-paging-button.first,
  #notInstalledTable_wrapper .dt-paging .dt-paging-button.previous,
  #notInstalledTable_wrapper .dt-paging .dt-paging-button.next,
  #notInstalledTable_wrapper .dt-paging .dt-paging-button.last {
    min-width: 2.25rem;
    font-weight: 600;
  }

  .dt-container:has(#notInstalledTable) .dt-search input,
  #notInstalledTable_wrapper .dt-search input {
    margin-left: 0.5rem;
    min-width: 240px;
    display: inline-block;
    width: auto;
  }

  .dt-container:has(#notInstalledTable) .dt-length select,
  #notInstalledTable_wrapper .dt-length select {
    margin: 0 0.375rem;
    width: auto;
    display: inline-block;
  }

  .dt-container:has(#notInstalledTable) .dt-length label,
  .dt-container:has(#notInstalledTable) .dt-search label,
  #notInstalledTable_wrapper .dt-length label,
  #notInstalledTable_wrapper .dt-search label {
    display: inline-flex;
    align-items: center;
    margin-bottom: 0;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-secondary-light);
  }

  .dt-container:has(#notInstalledTable) .dt-info,
  #notInstalledTable_wrapper .dt-info {
    font-size: 0.875rem;
    color: var(--text-secondary-light);
    padding-top: 0;
  }

  .dt-container:has(#notInstalledTable),
  #notInstalledTable_wrapper,
  .not-installed-datatable,
  .dt-container:has(#notInstalledTable) .dt-layout-table,
  #notInstalledTable_wrapper .dt-layout-table,
  .dt-container:has(#notInstalledTable) table.dataTable,
  #notInstalledTable {
    width: 100% !important;
    max-width: 100% !important;
  }

  .dt-container:has(#notInstalledTable) .dt-layout-table,
  #notInstalledTable_wrapper .dt-layout-table {
    display: block !important;
  }

  #notInstalledTable {
    table-layout: fixed !important;
    width: 100% !important;
  }

  #notInstalledTable colgroup,
  #notInstalledTable col {
    width: auto !important;
  }

  #notInstalledTable th,
  #notInstalledTable td {
    vertical-align: middle;
  }

  #notInstalledTable thead th {
    white-space: nowrap;
    font-weight: 600;
  }

  #notInstalledTable th:nth-child(1),
  #notInstalledTable td:nth-child(1) {
    width: 26% !important;
  }

  #notInstalledTable th:nth-child(2),
  #notInstalledTable td:nth-child(2) {
    width: 26% !important;
    word-break: break-word;
  }

  #notInstalledTable th:nth-child(3),
  #notInstalledTable td:nth-child(3) {
    width: 28% !important;
    word-break: break-word;
  }

  #notInstalledTable th:nth-child(4),
  #notInstalledTable td:nth-child(4),
  #notInstalledTable th:nth-child(5),
  #notInstalledTable td:nth-child(5) {
    width: 10% !important;
    text-align: center;
  }

  #install-stats-loading.is-visible {
    display: flex !important;
  }

  .install-stats-kpi-card {
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }

  .install-stats-dim-card {
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    border: 1px solid var(--input-form-light, #e5e7eb);
    background: var(--white, #fff);
  }

  .install-stats-dim-card:hover {
    border-color: #487fff;
    box-shadow: 0 6px 18px rgba(72, 127, 255, 0.08);
  }

  .install-stats-dim-card.is-active {
    border-color: #487fff;
    box-shadow: 0 0 0 1px #487fff;
    background: rgba(72, 127, 255, 0.04);
  }

  .install-stats-dim-card .dim-meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
  }

  .install-stats-dim-card .dim-meta-item {
    background: var(--neutral-50, #f8fafc);
    border-radius: 8px;
    padding: 0.5rem 0.625rem;
  }

  .install-stats-table-wrap {
    height: 300px;
    min-height: 300px;
    max-height: 300px;
    overflow: auto;
  }

  #install-stats-bar {
    width: 100%;
    overflow: hidden;
  }

  #install-stats-detail-row > [class*='col-'] {
    min-height: 0;
  }

  #install-stats-table thead th,
  #install-stats-table tfoot td {
    position: sticky;
    background: var(--white, #fff);
    z-index: 1;
  }

  #install-stats-table thead th {
    top: 0;
  }

  #install-stats-table tfoot td {
    bottom: 0;
    border-top: 1px solid var(--input-form-light, #e5e7eb);
  }

  #install-stats-table tbody tr.install-stats-row {
    cursor: pointer;
  }

  #install-stats-table tbody tr.install-stats-row:hover {
    background: rgba(72, 127, 255, 0.06);
  }

  #install-stats-table tbody tr.install-stats-row.is-selected {
    background: rgba(72, 127, 255, 0.1);
  }

  #active-stats-loading.is-visible {
    display: flex !important;
  }

  .active-stats-kpi-card {
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }

  .active-stats-dim-card {
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    border: 1px solid var(--input-form-light, #e5e7eb);
    background: var(--white, #fff);
  }

  .active-stats-dim-card:hover {
    border-color: #45b369;
    box-shadow: 0 6px 18px rgba(69, 179, 105, 0.08);
  }

  .active-stats-dim-card.is-active {
    border-color: #45b369;
    box-shadow: 0 0 0 1px #45b369;
    background: rgba(69, 179, 105, 0.04);
  }

  .active-stats-dim-card .dim-meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
  }

  .active-stats-dim-card .dim-meta-item {
    background: var(--neutral-50, #f8fafc);
    border-radius: 8px;
    padding: 0.5rem 0.625rem;
  }

  .active-stats-table-wrap {
    height: 300px;
    min-height: 300px;
    max-height: 300px;
    overflow: auto;
  }

  #active-stats-bar,
  #active-stats-trend {
    width: 100%;
    overflow: hidden;
  }

  #active-stats-detail-row > [class*='col-'] {
    min-height: 0;
  }

  #active-stats-table thead th,
  #active-stats-table tfoot td {
    position: sticky;
    background: var(--white, #fff);
    z-index: 1;
  }

  #active-stats-table thead th {
    top: 0;
  }

  #active-stats-table tfoot td {
    bottom: 0;
    border-top: 1px solid var(--input-form-light, #e5e7eb);
  }

  #installPeopleTable_wrapper .dt-layout-row,
  .dt-container:has(#installPeopleTable) .dt-layout-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0.75rem 0;
  }

  #installPeopleTable th,
  #installPeopleTable td {
    vertical-align: middle;
  }
</style>
@endsection

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
<script>
(function () {
    var tableEl = document.querySelector('#notInstalledTable');
    if (!tableEl || typeof DataTable === 'undefined') {
        return;
    }

    var employeeShowBase = @json(url('/evaluasi-well/employees'));
    var dataUrl = @json(route('evaluasi-well.not-installed.data'));
    var exportUrl = @json(route('evaluasi-well.not-installed.export'));

    var siteEl = document.querySelector('#not-installed-site');
    var companyEl = document.querySelector('#not-installed-company');
    var divisionEl = document.querySelector('#not-installed-division');
    var departementEl = document.querySelector('#not-installed-departement');
    var jabatanFungsionalEl = document.querySelector('#not-installed-jabatan-fungsional');
    var installEl = document.querySelector('#not-installed-install');
    var userAktifEl = document.querySelector('#not-installed-user-aktif');
    var applyBtn = document.querySelector('#not-installed-apply-btn');
    var resetBtn = document.querySelector('#not-installed-reset-btn');
    var exportBtn = document.querySelector('#not-installed-export-btn');
    var totalBadge = document.querySelector('#not-installed-total-badge');

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
            site: siteEl ? siteEl.value : '',
            company: companyEl ? companyEl.value : '',
            division: divisionEl ? divisionEl.value.trim() : '',
            departement: departementEl ? departementEl.value.trim() : '',
            jabatan_fungsional: jabatanFungsionalEl ? jabatanFungsionalEl.value : '',
            install: installEl ? installEl.value : 'belum',
            user_aktif: userAktifEl ? userAktifEl.value : ''
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

        var search = table.search();
        if (search) {
            params.set('search', search);
        }

        var query = params.toString();
        exportBtn.href = query ? (exportUrl + '?' + query) : exportUrl;
    }

    function badgeHtml(label, className) {
        return '<span class="' + className + ' px-16 py-4 rounded-pill fw-medium text-sm">'
            + escapeHtml(label)
            + '</span>';
    }

    var table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, 'asc']],
        autoWidth: false,
        scrollX: false,
        layout: {
            topStart: 'pageLength',
            topEnd: 'search',
            bottomStart: 'info',
            bottomEnd: 'paging'
        },
        columnDefs: [
            { targets: 0, width: '20%' },
            { targets: 1, width: '18%' },
            { targets: 2, width: '18%' },
            { targets: 3, width: '18%' },
            { targets: 4, width: '13%', className: 'text-center' },
            { targets: 5, width: '13%', className: 'text-center' }
        ],
        ajax: {
            url: dataUrl,
            data: function (d) {
                var filters = currentFilters();
                d.site = filters.site;
                d.company = filters.company;
                d.division = filters.division;
                d.departement = filters.departement;
                d.jabatan_fungsional = filters.jabatan_fungsional;
                d.install = filters.install;
                d.user_aktif = filters.user_aktif;
            }
        },
        columns: [
            {
                data: 'nama',
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
            { data: 'company' },
            { data: 'departement' },
            { data: 'divisi' },
            {
                data: 'install',
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data;
                    }
                    return badgeHtml(data, row.install_class);
                }
            },
            {
                data: 'user_aktif',
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data;
                    }
                    return badgeHtml(data, row.user_aktif_class);
                }
            }
        ],
        language: {
            processing: 'Memuat...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(difilter dari _MAX_ total data)',
            zeroRecords: 'Tidak ada data untuk filter ini.',
            paginate: {
                first: '«',
                last: '»',
                next: '›',
                previous: '‹'
            }
        }
    });

    function forceFullWidthTable() {
        tableEl.style.setProperty('width', '100%', 'important');
        tableEl.removeAttribute('width');

        var colgroup = tableEl.querySelector('colgroup');
        if (colgroup) {
            colgroup.remove();
        }

        var container = tableEl.closest('.dt-container');
        if (container) {
            container.style.setProperty('width', '100%', 'important');
            var layoutTable = container.querySelector('.dt-layout-table');
            if (layoutTable) {
                layoutTable.style.setProperty('width', '100%', 'important');
            }
        }
    }

    table.on('init', function () {
        forceFullWidthTable();

        var container = tableEl.closest('.dt-container');
        var searchInput = container ? container.querySelector('.dt-search input') : null;
        if (searchInput) {
            searchInput.setAttribute('placeholder', 'Cari nama / SID / departemen / divisi...');
            searchInput.classList.add('form-control', 'form-control-sm');
        }
    });

    table.on('draw', function () {
        forceFullWidthTable();
        if (totalBadge) {
            totalBadge.textContent = Number(table.page.info().recordsDisplay || 0).toLocaleString('id-ID');
        }
        updateExportHref();
    });

    table.on('search.dt', function () {
        updateExportHref();
    });

    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            table.ajax.reload();
            updateExportHref();
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (siteEl) siteEl.value = '';
            if (companyEl) companyEl.value = '';
            if (divisionEl) divisionEl.value = '';
            if (departementEl) departementEl.value = '';
            if (jabatanFungsionalEl) jabatanFungsionalEl.value = '';
            if (installEl) installEl.value = 'belum';
            if (userAktifEl) userAktifEl.value = '';
            table.search('');
            table.ajax.reload();
            updateExportHref();
        });
    }

    if (divisionEl) {
        divisionEl.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                table.ajax.reload();
                updateExportHref();
            }
        });
    }

    if (departementEl) {
        departementEl.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                table.ajax.reload();
                updateExportHref();
            }
        });
    }

    updateExportHref();
})();
</script>
<script>
(function () {
    var modalEl = document.getElementById('installStatsModal');
    if (!modalEl) {
        return;
    }

    var dataUrl = @json(route('evaluasi-well.install-stats'));
    var peopleDataUrl = @json(route('evaluasi-well.not-installed.data'));
    var employeeShowBase = @json(url('/evaluasi-well/employees'));
    var cache = {};
    var currentDimension = 'site';
    var barChart = null;
    var overviewRendered = false;
    var peopleTable = null;
    var latestRows = [];
    var filterOptionsReady = false;

    var loadingEl = document.getElementById('install-stats-loading');
    var unavailableEl = document.getElementById('install-stats-unavailable');
    var contentEl = document.getElementById('install-stats-content');
    var messageEl = document.getElementById('install-stats-message');
    var footnoteEl = document.getElementById('install-stats-footnote');
    var overviewEl = document.getElementById('install-stats-overview');
    var tableBody = document.querySelector('#install-stats-table tbody');
    var tableEmptyEl = document.getElementById('install-stats-table-empty');
    var chartEmptyEl = document.getElementById('install-stats-chart-empty');
    var chartEl = document.getElementById('install-stats-bar');
    var tableWrapEl = document.querySelector('.install-stats-table-wrap');
    var tableDimLabelEl = document.getElementById('install-stats-table-dim-label');
    var detailTitleEl = document.getElementById('install-stats-detail-title');
    var detailSubtitleEl = document.getElementById('install-stats-detail-subtitle');
    var groupsHintEl = document.getElementById('install-stats-kpi-groups-hint');
    var openStatusBtn = document.getElementById('install-stats-open-status-btn');
    var cardEl = document.getElementById('total-user-install-card');
    var peopleSubtitleEl = document.getElementById('install-people-subtitle');
    var peopleTotalBadge = document.getElementById('install-people-total-badge');
    var peopleTableEl = document.getElementById('installPeopleTable');

    var globalSiteEl = document.getElementById('install-global-site');
    var globalDivisionEl = document.getElementById('install-global-division');
    var globalJabatanEl = document.getElementById('install-global-jabatan');
    var globalCompanyEl = document.getElementById('install-global-company');
    var globalDepartementEl = document.getElementById('install-global-departement');
    var globalDepartementListEl = document.getElementById('install-global-departement-options');
    var globalInstallEl = document.getElementById('install-global-install');
    var globalApplyBtn = document.getElementById('install-global-apply-btn');
    var globalResetBtn = document.getElementById('install-global-reset-btn');

    var dimensionUnit = {
        site: 'site',
        divisi: 'grup divisi',
        company: 'perusahaan',
        departement: 'departemen',
        jabatan: 'jabatan'
    };

    var dimensionLabels = {
        site: 'Site',
        divisi: 'Divisi',
        company: 'Perusahaan',
        departement: 'Departemen',
        jabatan: 'Jabatan'
    };

    var dimensionToGlobalFilter = {
        site: 'site',
        divisi: 'division_group',
        company: 'company',
        departement: 'departement',
        jabatan: 'jabatan'
    };

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('id-ID');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function badgeHtml(label, className) {
        return '<span class="' + className + ' px-12 py-4 rounded-pill fw-medium text-sm">'
            + escapeHtml(label)
            + '</span>';
    }

    function setLoading(isLoading) {
        if (!loadingEl) {
            return;
        }
        loadingEl.classList.toggle('d-none', !isLoading);
        loadingEl.classList.toggle('is-visible', isLoading);
    }

    function adoptionBadgeClass(pct) {
        if (pct >= 50) {
            return { cls: ['bg-success-focus', 'text-success-main'], text: 'Baik' };
        }
        if (pct >= 25) {
            return { cls: ['bg-warning-focus', 'text-warning-main'], text: 'Perlu dorongan' };
        }
        return { cls: ['bg-danger-focus', 'text-danger-main'], text: 'Rendah' };
    }

    function renderSummary(payload) {
        var summary = payload.summary || {};
        var installedEl = document.getElementById('install-stats-kpi-installed');
        var notInstalledEl = document.getElementById('install-stats-kpi-not-installed');
        var totalEl = document.getElementById('install-stats-kpi-total');
        var adoptionEl = document.getElementById('install-stats-kpi-adoption');
        var adoptionBadge = document.getElementById('install-stats-kpi-adoption-badge');
        var kpiCardEl = document.getElementById('install-stats-kpi-card-total');

        if (installedEl) installedEl.textContent = formatNumber(summary.installed);
        if (notInstalledEl) notInstalledEl.textContent = formatNumber(summary.not_installed);
        if (totalEl) totalEl.textContent = formatNumber(summary.total);
        if (adoptionEl) adoptionEl.textContent = (summary.adoption_pct || 0) + '%';
        if (kpiCardEl) kpiCardEl.textContent = formatNumber(summary.kpi_card_total);

        if (adoptionBadge) {
            var badge = adoptionBadgeClass(Number(summary.adoption_pct || 0));
            adoptionBadge.className = 'text-xs fw-medium px-8 py-2 rounded-pill';
            badge.cls.forEach(function (c) { adoptionBadge.classList.add(c); });
            adoptionBadge.textContent = badge.text;
        }

        if (footnoteEl && payload.footnote) {
            footnoteEl.textContent = payload.footnote;
        }
        if (tableDimLabelEl) {
            tableDimLabelEl.textContent = payload.dimension_label || 'Site';
        }
        if (detailTitleEl) {
            detailTitleEl.textContent = payload.dimension_label || 'Site';
        }
        if (detailSubtitleEl) {
            detailSubtitleEl.textContent = formatNumber(summary.groups || 0) + ' ' +
                (dimensionUnit[payload.dimension] || 'grup') +
                ' · ' + formatNumber(summary.installed) + ' sudah install · ' +
                formatNumber(summary.not_installed) + ' belum';
        }
        if (groupsHintEl) {
            groupsHintEl.textContent = formatNumber(summary.groups || 0) + ' ' +
                (dimensionUnit[payload.dimension] || 'grup') + ' pada dimensi aktif';
        }

        var tfootTotal = document.getElementById('install-stats-tfoot-total');
        var tfootInstalled = document.getElementById('install-stats-tfoot-installed');
        var tfootNotInstalled = document.getElementById('install-stats-tfoot-not-installed');
        var tfootPct = document.getElementById('install-stats-tfoot-pct');
        if (tfootTotal) tfootTotal.textContent = formatNumber(summary.total);
        if (tfootInstalled) tfootInstalled.textContent = formatNumber(summary.installed);
        if (tfootNotInstalled) tfootNotInstalled.textContent = formatNumber(summary.not_installed);
        if (tfootPct) tfootPct.textContent = (summary.adoption_pct || 0) + '%';
    }

    function renderOverview(overview, activeDimension) {
        if (!overviewEl) {
            return;
        }

        overviewEl.innerHTML = '';
        (overview || []).forEach(function (item) {
            var col = document.createElement('div');
            col.className = 'col-12 col-md-6 col-xl-3';

            var card = document.createElement('div');
            card.className = 'install-stats-dim-card radius-8 p-16 h-100' +
                (item.dimension === activeDimension ? ' is-active' : '');
            card.setAttribute('role', 'button');
            card.setAttribute('tabindex', '0');
            card.setAttribute('data-dimension', item.dimension);

            var unit = dimensionUnit[item.dimension] || 'grup';
            var badge = adoptionBadgeClass(Number(item.adoption_pct || 0));

            card.innerHTML =
                '<div class="d-flex align-items-start justify-content-between gap-2 mb-12">' +
                    '<div class="d-flex align-items-center gap-2 min-w-0">' +
                        '<span class="w-40-px h-40-px bg-primary-50 text-primary-600 radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">' +
                            '<iconify-icon icon="' + (item.icon || 'solar:chart-bold') + '" class="text-xl"></iconify-icon>' +
                        '</span>' +
                        '<div class="min-w-0">' +
                            '<h6 class="mb-0 fw-semibold text-md text-truncate"></h6>' +
                            '<span class="text-xs text-secondary-light"></span>' +
                        '</div>' +
                    '</div>' +
                    '<span class="text-xs fw-medium px-8 py-2 rounded-pill flex-shrink-0 ' + badge.cls.join(' ') + '"></span>' +
                '</div>' +
                '<div class="dim-meta mb-12">' +
                    '<div class="dim-meta-item">' +
                        '<div class="text-xs text-secondary-light mb-2">Total</div>' +
                        '<div class="fw-semibold text-sm meta-total">0</div>' +
                    '</div>' +
                    '<div class="dim-meta-item">' +
                        '<div class="text-xs text-secondary-light mb-2">Sudah</div>' +
                        '<div class="fw-semibold text-sm text-primary-600 meta-installed">0</div>' +
                    '</div>' +
                    '<div class="dim-meta-item">' +
                        '<div class="text-xs text-secondary-light mb-2">Belum</div>' +
                        '<div class="fw-semibold text-sm text-warning-main meta-not-installed">0</div>' +
                    '</div>' +
                '</div>' +
                '<div class="d-flex align-items-center justify-content-between gap-2">' +
                    '<span class="text-xs text-secondary-light text-truncate meta-top" title=""></span>' +
                    '<span class="fw-bold text-md meta-pct flex-shrink-0">0%</span>' +
                '</div>';

            card.querySelector('h6').textContent = item.label || item.dimension;
            card.querySelector('.text-xs.text-secondary-light').textContent =
                formatNumber(item.groups) + ' ' + unit;
            card.querySelector('.rounded-pill').textContent = badge.text;
            card.querySelector('.meta-total').textContent = formatNumber(item.total);
            card.querySelector('.meta-installed').textContent = formatNumber(item.installed);
            card.querySelector('.meta-not-installed').textContent = formatNumber(item.not_installed);
            card.querySelector('.meta-pct').textContent = (item.adoption_pct || 0) + '%';

            var topText = 'Teratas: ' + (item.top_name || '-') +
                ' (' + formatNumber(item.top_installed) + ')';
            var topEl = card.querySelector('.meta-top');
            topEl.textContent = topText;
            topEl.setAttribute('title', topText);

            card.addEventListener('click', function () {
                loadDimension(item.dimension);
            });
            card.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    loadDimension(item.dimension);
                }
            });

            col.appendChild(card);
            overviewEl.appendChild(col);
        });

        overviewRendered = true;
    }

    function highlightOverview(dimension) {
        if (!overviewEl) {
            return;
        }
        overviewEl.querySelectorAll('.install-stats-dim-card').forEach(function (card) {
            card.classList.toggle('is-active', card.getAttribute('data-dimension') === dimension);
        });
    }

    function applyDetailHeight(height) {
        var px = Math.max(300, Math.ceil(Number(height) || 300));
        if (chartEl) {
            chartEl.style.height = px + 'px';
            chartEl.style.minHeight = px + 'px';
            chartEl.style.maxHeight = px + 'px';
        }
        if (tableWrapEl) {
            tableWrapEl.style.height = px + 'px';
            tableWrapEl.style.minHeight = px + 'px';
            tableWrapEl.style.maxHeight = px + 'px';
        }
    }

    function renderChart(payload) {
        if (!chartEl || typeof ApexCharts === 'undefined') {
            return;
        }

        var chart = payload.chart || {};
        var categories = chart.categories || [];
        var installed = chart.installed || [];
        var notInstalled = chart.not_installed || [];
        var hasData = categories.length > 0;

        if (chartEmptyEl) {
            chartEmptyEl.classList.toggle('d-none', hasData);
        }
        chartEl.classList.toggle('d-none', !hasData);

        if (barChart) {
            barChart.destroy();
            barChart = null;
        }

        if (!hasData) {
            applyDetailHeight(300);
            return;
        }

        // Tinggi chart = tinggi tabel (kotak merah).
        var height = Math.max(300, Math.min(520, categories.length * 34));
        applyDetailHeight(height);

        barChart = new ApexCharts(chartEl, {
            series: [
                { name: 'Sudah Install', data: installed },
                { name: 'Belum Install', data: notInstalled }
            ],
            chart: {
                type: 'bar',
                height: height,
                width: '100%',
                stacked: true,
                toolbar: { show: false },
                parentHeightOffset: 0,
                events: {
                    dataPointSelection: function (event, chartContext, config) {
                        var cats = (payload.chart && payload.chart.categories) ? payload.chart.categories : [];
                        var name = cats[config.dataPointIndex] || '';
                        if (name) {
                            applyDimensionFilter(currentDimension, name);
                        }
                    }
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 4,
                    barHeight: '64%'
                }
            },
            colors: ['#487FFF', '#FF9F29'],
            dataLabels: { enabled: false },
            grid: {
                show: true,
                borderColor: '#D1D5DB',
                strokeDashArray: 4,
                position: 'back',
                padding: { left: 8, right: 8 }
            },
            xaxis: {
                categories: categories,
                labels: {
                    formatter: function (value) {
                        var n = Number(value);
                        if (n >= 1000) {
                            return (n / 1000).toFixed(0) + 'k';
                        }
                        return value;
                    }
                }
            },
            yaxis: {
                labels: {
                    style: { fontSize: '11px' },
                    maxWidth: 120
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'left',
                fontSize: '12px'
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return formatNumber(value) + ' orang';
                    }
                }
            }
        });
        barChart.render().then(function () {
            applyDetailHeight(height);
        });
    }

    function readGlobalFilters() {
        return {
            site: globalSiteEl ? globalSiteEl.value : '',
            division_group: globalDivisionEl ? globalDivisionEl.value : '',
            jabatan: globalJabatanEl ? globalJabatanEl.value : '',
            company: globalCompanyEl ? globalCompanyEl.value : '',
            departement: globalDepartementEl ? globalDepartementEl.value.trim() : '',
            install: globalInstallEl ? globalInstallEl.value : ''
        };
    }

    function filtersCacheKey(filters) {
        return [
            filters.site || '',
            filters.division_group || '',
            filters.jabatan || '',
            filters.company || '',
            filters.departement || '',
            filters.install || ''
        ].join('|');
    }

    function fillSelectOptions(selectEl, values, allLabel, selectedValue) {
        if (!selectEl) {
            return;
        }
        var previous = selectedValue !== undefined ? selectedValue : selectEl.value;
        selectEl.innerHTML = '';
        var allOpt = document.createElement('option');
        allOpt.value = '';
        allOpt.textContent = allLabel;
        selectEl.appendChild(allOpt);
        (values || []).forEach(function (value) {
            if (!value) {
                return;
            }
            var opt = document.createElement('option');
            opt.value = value;
            opt.textContent = value;
            selectEl.appendChild(opt);
        });
        if (previous && Array.prototype.some.call(selectEl.options, function (o) { return o.value === previous; })) {
            selectEl.value = previous;
        } else {
            selectEl.value = '';
        }
    }

    function populateGlobalFilterOptions(options) {
        if (!options) {
            return;
        }
        fillSelectOptions(globalSiteEl, options.sites || [], 'Semua Site');
        fillSelectOptions(globalDivisionEl, options.division_groups || [], 'Semua Divisi');
        fillSelectOptions(globalJabatanEl, options.jabatans || [], 'Semua Jabatan');
        fillSelectOptions(globalCompanyEl, options.companies || [], 'Semua Perusahaan');

        if (globalDepartementListEl) {
            globalDepartementListEl.innerHTML = '';
            (options.departements || []).forEach(function (value) {
                var opt = document.createElement('option');
                opt.value = value;
                globalDepartementListEl.appendChild(opt);
            });
        }
        filterOptionsReady = true;
    }

    function updatePeopleSubtitle() {
        if (!peopleSubtitleEl) {
            return;
        }
        var filters = readGlobalFilters();
        var parts = [];
        if (filters.site) parts.push('Site: ' + filters.site);
        if (filters.division_group) parts.push('Divisi: ' + filters.division_group);
        if (filters.jabatan) parts.push('Jabatan: ' + filters.jabatan);
        if (filters.company) parts.push('Perusahaan: ' + filters.company);
        if (filters.departement) parts.push('Departemen: ' + filters.departement);
        if (filters.install === 'sudah') parts.push('sudah install');
        else if (filters.install === 'belum') parts.push('belum install');
        peopleSubtitleEl.textContent = parts.length
            ? parts.join(' · ')
            : 'Mengikuti filter global di atas (semua data)';
    }

    function highlightSummaryRow(name) {
        if (!tableBody) {
            return;
        }
        tableBody.querySelectorAll('tr.install-stats-row').forEach(function (tr) {
            tr.classList.toggle('is-selected', !!name && tr.getAttribute('data-name') === name);
        });
    }

    function ensurePeopleTable() {
        if (peopleTable || !peopleTableEl || typeof DataTable === 'undefined') {
            return peopleTable;
        }

        peopleTable = new DataTable(peopleTableEl, {
            processing: true,
            serverSide: true,
            searching: true,
            ordering: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50],
            order: [[0, 'asc']],
            autoWidth: false,
            layout: {
                topStart: 'pageLength',
                topEnd: 'search',
                bottomStart: 'info',
                bottomEnd: 'paging'
            },
            ajax: {
                url: peopleDataUrl,
                data: function (d) {
                    var filters = readGlobalFilters();
                    d.site = filters.site;
                    d.company = filters.company;
                    d.division_group = filters.division_group;
                    d.division = '';
                    d.departement = filters.departement;
                    d.jabatan_fungsional = filters.jabatan;
                    d.install = filters.install;
                    d.user_aktif = '';
                }
            },
            columns: [
                {
                    data: 'nama',
                    render: function (data, type, row) {
                        var name = escapeHtml(data || '-');
                        var sid = escapeHtml(row.kode_sid || '-');
                        var href = employeeShowBase + '/' + encodeURIComponent(row.id);
                        return '<div class="fw-medium"><a href="' + href + '" class="text-primary-light hover-text-primary">' + name + '</a></div>'
                            + '<div class="text-xs text-secondary-light">' + sid + '</div>';
                    }
                },
                { data: 'site' },
                { data: 'company' },
                { data: 'departement' },
                { data: 'jabatan', defaultContent: '-' },
                {
                    data: 'install',
                    orderable: true,
                    render: function (data, type, row) {
                        return badgeHtml(data, row.install_class || 'bg-neutral-200 text-secondary-light');
                    }
                }
            ],
            language: {
                processing: 'Memuat…',
                search: 'Cari:',
                lengthMenu: 'Tampil _MENU_',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_',
                infoEmpty: 'Tidak ada data',
                zeroRecords: 'Tidak ada karyawan ditemukan',
                paginate: {
                    previous: '‹',
                    next: '›'
                }
            }
        });

        peopleTable.on('draw', function () {
            if (peopleTotalBadge) {
                peopleTotalBadge.textContent = formatNumber(peopleTable.page.info().recordsDisplay || 0);
            }
        });

        return peopleTable;
    }

    function reloadPeopleTable() {
        updatePeopleSubtitle();
        var table = ensurePeopleTable();
        if (table) {
            table.ajax.reload();
        }
    }

    function applyDimensionFilter(dimension, name) {
        if (!name || name === 'Lainnya' || name === 'Tidak diketahui') {
            if (dimension !== currentDimension) {
                loadDimension(dimension);
            }
            return;
        }

        var filterKey = dimensionToGlobalFilter[dimension];
        if (filterKey === 'site' && globalSiteEl) {
            globalSiteEl.value = name;
        } else if (filterKey === 'division_group' && globalDivisionEl) {
            if (!Array.prototype.some.call(globalDivisionEl.options, function (o) { return o.value === name; })) {
                var opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                globalDivisionEl.appendChild(opt);
            }
            globalDivisionEl.value = name;
        } else if (filterKey === 'company' && globalCompanyEl) {
            if (!Array.prototype.some.call(globalCompanyEl.options, function (o) { return o.value === name; })) {
                var cOpt = document.createElement('option');
                cOpt.value = name;
                cOpt.textContent = name;
                globalCompanyEl.appendChild(cOpt);
            }
            globalCompanyEl.value = name;
        } else if (filterKey === 'departement' && globalDepartementEl) {
            globalDepartementEl.value = name;
        } else if (filterKey === 'jabatan' && globalJabatanEl) {
            if (!Array.prototype.some.call(globalJabatanEl.options, function (o) { return o.value === name; })) {
                var jOpt = document.createElement('option');
                jOpt.value = name;
                jOpt.textContent = name;
                globalJabatanEl.appendChild(jOpt);
            }
            globalJabatanEl.value = name;
        }

        cache = {};
        overviewRendered = false;
        loadDimension(dimension);
    }

    function scrollToStatusInstall() {
        var section = document.getElementById('notInstalledTable');
        if (section) {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function syncPeopleFilterToDashboard() {
        var siteEl = document.getElementById('not-installed-site');
        var companyEl = document.getElementById('not-installed-company');
        var divisionEl = document.getElementById('not-installed-division');
        var departementEl = document.getElementById('not-installed-departement');
        var jabatanEl = document.getElementById('not-installed-jabatan-fungsional');
        var installEl = document.getElementById('not-installed-install');
        var userAktifEl = document.getElementById('not-installed-user-aktif');
        var applyBtn = document.getElementById('not-installed-apply-btn');
        var filters = readGlobalFilters();

        if (siteEl) siteEl.value = filters.site || '';
        if (companyEl) companyEl.value = filters.company || '';
        if (divisionEl) divisionEl.value = filters.division_group || '';
        if (departementEl) departementEl.value = filters.departement || '';
        if (jabatanEl) jabatanEl.value = filters.jabatan || '';
        if (userAktifEl) userAktifEl.value = '';
        if (installEl) installEl.value = filters.install || '';

        if (applyBtn) {
            applyBtn.click();
        }
    }

    function renderTable(payload) {
        var rows = payload.rows || [];
        latestRows = rows;
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '';
        if (tableEmptyEl) {
            tableEmptyEl.classList.toggle('d-none', rows.length > 0);
        }

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.className = 'install-stats-row';
            tr.setAttribute('data-name', row.name);
            tr.setAttribute('role', 'button');
            tr.setAttribute('tabindex', '0');
            tr.title = 'Klik untuk filter daftar karyawan: ' + row.name;

            tr.innerHTML =
                '<td><div class="text-truncate fw-medium" style="max-width: 160px;"></div>' +
                    '<div class="progress progress-sm rounded-pill mt-6" style="height: 4px;">' +
                        '<div class="progress-bar rounded-pill"></div>' +
                    '</div>' +
                '</td>' +
                '<td class="text-end"></td>' +
                '<td class="text-end text-primary-600 fw-medium"></td>' +
                '<td class="text-end text-warning-main"></td>' +
                '<td class="text-end fw-semibold"></td>';

            var nameEl = tr.querySelector('.text-truncate');
            nameEl.textContent = row.name;
            nameEl.setAttribute('title', row.name);

            var bar = tr.querySelector('.progress-bar');
            bar.classList.add(row.bar_class || 'bg-primary-600');
            bar.style.width = Math.min(100, Number(row.pct || 0)) + '%';

            var cells = tr.querySelectorAll('td');
            cells[1].textContent = formatNumber(row.total);
            cells[2].textContent = formatNumber(row.installed);
            cells[3].textContent = formatNumber(row.not_installed);
            cells[4].textContent = (row.pct || 0) + '%';

            tr.addEventListener('click', function () {
                applyDimensionFilter(payload.dimension, row.name);
            });
            tr.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    applyDimensionFilter(payload.dimension, row.name);
                }
            });

            tableBody.appendChild(tr);
        });

        highlightSummaryRow('');
    }

    function renderPayload(payload) {
        var available = !!payload.available;
        if (unavailableEl) {
            unavailableEl.classList.toggle('d-none', available);
        }
        if (contentEl) {
            contentEl.classList.toggle('d-none', !available && !(payload.rows && payload.rows.length));
        }
        if (!available) {
            if (messageEl) {
                messageEl.textContent = payload.message || 'Koneksi BeWell belum tersedia.';
            }
            if (contentEl && (!payload.rows || !payload.rows.length)) {
                contentEl.classList.add('d-none');
                return;
            }
        } else if (contentEl) {
            contentEl.classList.remove('d-none');
        }

        if (payload.filter_options) {
            populateGlobalFilterOptions(payload.filter_options);
        }

        if (payload.overview && payload.overview.length) {
            if (!overviewRendered) {
                renderOverview(payload.overview, payload.dimension);
            } else {
                highlightOverview(payload.dimension);
            }
        }

        renderSummary(payload);
        renderChart(payload);
        renderTable(payload);
        ensurePeopleTable();
        reloadPeopleTable();
    }

    function loadDimension(dimension) {
        currentDimension = dimension;
        highlightOverview(dimension);

        var filters = readGlobalFilters();
        var key = dimension + '::' + filtersCacheKey(filters);

        if (cache[key]) {
            renderPayload(cache[key]);
            return;
        }

        setLoading(true);
        var params = new URLSearchParams();
        params.set('dimension', dimension);
        if (filters.site) params.set('site', filters.site);
        if (filters.division_group) params.set('division_group', filters.division_group);
        if (filters.jabatan) params.set('jabatan', filters.jabatan);
        if (filters.company) params.set('company', filters.company);
        if (filters.departement) params.set('departement', filters.departement);
        if (filters.install) params.set('install', filters.install);

        fetch(dataUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (payload) {
                cache[key] = payload;
                if (payload.overview && payload.overview.length) {
                    overviewRendered = false;
                }
                renderPayload(payload);
            })
            .catch(function () {
                renderPayload({
                    available: false,
                    dimension: dimension,
                    dimension_label: dimensionLabels[dimension] || 'Site',
                    footnote: 'Filter global mempengaruhi seluruh ringkasan. Divisi digabung per grup sejenis.',
                    message: 'Gagal memuat statistik install.',
                    summary: { total: 0, installed: 0, not_installed: 0, adoption_pct: 0, kpi_card_total: 0, groups: 0 },
                    overview: [],
                    rows: [],
                    chart: { categories: [], installed: [], not_installed: [] }
                });
            })
            .finally(function () {
                setLoading(false);
            });
    }

    function resetGlobalFilters() {
        if (globalSiteEl) globalSiteEl.value = '';
        if (globalDivisionEl) globalDivisionEl.value = '';
        if (globalJabatanEl) globalJabatanEl.value = '';
        if (globalCompanyEl) globalCompanyEl.value = '';
        if (globalDepartementEl) globalDepartementEl.value = '';
        if (globalInstallEl) globalInstallEl.value = '';
    }

    function applyGlobalFilters() {
        cache = {};
        overviewRendered = false;
        loadDimension(currentDimension || 'site');
    }

    modalEl.addEventListener('shown.bs.modal', function () {
        overviewRendered = false;
        filterOptionsReady = false;
        resetGlobalFilters();
        cache = {};
        loadDimension(currentDimension || 'site');
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (barChart) {
            barChart.destroy();
            barChart = null;
        }
    });

    if (globalApplyBtn) {
        globalApplyBtn.addEventListener('click', function () {
            applyGlobalFilters();
        });
    }

    if (globalResetBtn) {
        globalResetBtn.addEventListener('click', function () {
            resetGlobalFilters();
            applyGlobalFilters();
        });
    }

    if (globalDepartementEl) {
        globalDepartementEl.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                applyGlobalFilters();
            }
        });
    }

    if (openStatusBtn) {
        openStatusBtn.addEventListener('click', function () {
            syncPeopleFilterToDashboard();
            var modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) {
                modalInstance.hide();
            }
            window.setTimeout(scrollToStatusInstall, 250);
        });
    }

    if (cardEl) {
        cardEl.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }
        });
    }
})();
</script>
<script>
(function () {
    var modalEl = document.getElementById('activeStatsModal');
    if (!modalEl) {
        return;
    }

    var dataUrl = @json(route('evaluasi-well.active-stats'));
    var employeeShowBase = @json(url('/evaluasi-well/employees'));
    var cache = {};
    var currentDimension = 'site';
    var currentWeekStart = '';
    var barChart = null;
    var trendChart = null;
    var overviewRendered = false;
    var weekOptionsFilled = false;

    var loadingEl = document.getElementById('active-stats-loading');
    var unavailableEl = document.getElementById('active-stats-unavailable');
    var contentEl = document.getElementById('active-stats-content');
    var messageEl = document.getElementById('active-stats-message');
    var footnoteEl = document.getElementById('active-stats-footnote');
    var overviewEl = document.getElementById('active-stats-overview');
    var tableBody = document.querySelector('#active-stats-table tbody');
    var tableEmptyEl = document.getElementById('active-stats-table-empty');
    var chartEmptyEl = document.getElementById('active-stats-chart-empty');
    var chartEl = document.getElementById('active-stats-bar');
    var trendEl = document.getElementById('active-stats-trend');
    var trendEmptyEl = document.getElementById('active-stats-trend-empty');
    var tableWrapEl = document.querySelector('.active-stats-table-wrap');
    var tableDimLabelEl = document.getElementById('active-stats-table-dim-label');
    var detailTitleEl = document.getElementById('active-stats-detail-title');
    var detailSubtitleEl = document.getElementById('active-stats-detail-subtitle');
    var groupsHintEl = document.getElementById('active-stats-kpi-groups-hint');
    var weekSelectEl = document.getElementById('active-stats-week');
    var weekLabelEl = document.getElementById('active-stats-week-label');
    var leaderboardBody = document.querySelector('#active-stats-leaderboard tbody');
    var leaderboardEmptyEl = document.getElementById('active-stats-leaderboard-empty');
    var leaderboardBadge = document.getElementById('active-leaderboard-total-badge');
    var openStatusBtn = document.getElementById('active-stats-open-status-btn');
    var cardEl = document.getElementById('total-user-aktif-card');

    var dimensionUnit = {
        site: 'site',
        company: 'perusahaan',
        jabatan: 'jabatan'
    };

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('id-ID');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function cacheKey(dimension, weekStart) {
        return dimension + '|' + (weekStart || '');
    }

    function setLoading(isLoading) {
        if (!loadingEl) {
            return;
        }
        loadingEl.classList.toggle('d-none', !isLoading);
        loadingEl.classList.toggle('is-visible', isLoading);
    }

    function applyDetailHeight(height) {
        if (chartEl) {
            chartEl.style.height = height + 'px';
        }
        if (tableWrapEl) {
            tableWrapEl.style.height = height + 'px';
            tableWrapEl.style.minHeight = height + 'px';
            tableWrapEl.style.maxHeight = height + 'px';
        }
    }

    function fillWeekOptions(options, selected) {
        if (!weekSelectEl || weekOptionsFilled) {
            return;
        }
        weekSelectEl.innerHTML = '';
        (options || []).forEach(function (opt) {
            var option = document.createElement('option');
            option.value = opt.start;
            option.textContent = opt.label;
            if (opt.start === selected) {
                option.selected = true;
            }
            weekSelectEl.appendChild(option);
        });
        weekOptionsFilled = true;
    }

    function renderSummary(payload) {
        var summary = payload.summary || {};
        var week = payload.week || {};

        var activeEl = document.getElementById('active-stats-kpi-active');
        var foodEl = document.getElementById('active-stats-kpi-food');
        var workoutEl = document.getElementById('active-stats-kpi-workout');
        var totalEvalsEl = document.getElementById('active-stats-kpi-total-evals');
        var kpiCardEl = document.getElementById('active-stats-kpi-card-total');
        var increaseBadge = document.getElementById('active-stats-kpi-increase-badge');

        if (activeEl) activeEl.textContent = formatNumber(summary.active_users);
        if (foodEl) foodEl.textContent = formatNumber(summary.food_evals);
        if (workoutEl) workoutEl.textContent = formatNumber(summary.workout_evals);
        if (totalEvalsEl) totalEvalsEl.textContent = formatNumber(summary.total_evals);
        if (kpiCardEl) kpiCardEl.textContent = formatNumber(summary.kpi_card_total);
        if (increaseBadge) {
            increaseBadge.textContent = '+' + formatNumber(summary.week_increase);
        }

        if (footnoteEl && payload.footnote) {
            footnoteEl.textContent = payload.footnote;
        }
        if (weekLabelEl) {
            weekLabelEl.textContent = week.label || '—';
        }
        if (tableDimLabelEl) {
            tableDimLabelEl.textContent = payload.dimension_label || 'Site';
        }
        if (detailTitleEl) {
            detailTitleEl.textContent = payload.dimension_label || 'Site';
        }
        if (detailSubtitleEl) {
            detailSubtitleEl.textContent = formatNumber(summary.groups || 0) + ' ' +
                (dimensionUnit[payload.dimension] || 'grup') +
                ' · ' + formatNumber(summary.active_users) + ' user aktif · ' +
                formatNumber(summary.total_evals) + ' evaluasi';
        }
        if (groupsHintEl) {
            groupsHintEl.textContent = formatNumber(summary.groups || 0) + ' ' +
                (dimensionUnit[payload.dimension] || 'grup') + ' · +' +
                formatNumber(summary.week_increase) + ' vs minggu lalu';
        }

        var tfootActive = document.getElementById('active-stats-tfoot-active');
        var tfootFood = document.getElementById('active-stats-tfoot-food');
        var tfootWorkout = document.getElementById('active-stats-tfoot-workout');
        var tfootEvals = document.getElementById('active-stats-tfoot-evals');
        if (tfootActive) tfootActive.textContent = formatNumber(summary.active_users);
        if (tfootFood) tfootFood.textContent = formatNumber(summary.food_evals);
        if (tfootWorkout) tfootWorkout.textContent = formatNumber(summary.workout_evals);
        if (tfootEvals) tfootEvals.textContent = formatNumber(summary.total_evals);
    }

    function renderOverview(overview, activeDimension) {
        if (!overviewEl) {
            return;
        }

        overviewEl.innerHTML = '';
        (overview || []).forEach(function (item) {
            var col = document.createElement('div');
            col.className = 'col-12 col-md-4';

            var card = document.createElement('div');
            card.className = 'active-stats-dim-card radius-8 p-16 h-100' +
                (item.dimension === activeDimension ? ' is-active' : '');
            card.setAttribute('role', 'button');
            card.setAttribute('tabindex', '0');
            card.setAttribute('data-dimension', item.dimension);

            var unit = dimensionUnit[item.dimension] || 'grup';

            card.innerHTML =
                '<div class="d-flex align-items-start justify-content-between gap-2 mb-12">' +
                    '<div class="d-flex align-items-center gap-2 min-w-0">' +
                        '<span class="w-40-px h-40-px bg-success-focus text-success-main radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">' +
                            '<iconify-icon icon="' + (item.icon || 'solar:chart-bold') + '" class="text-xl"></iconify-icon>' +
                        '</span>' +
                        '<div class="min-w-0">' +
                            '<h6 class="mb-0 fw-semibold text-md text-truncate"></h6>' +
                            '<span class="text-xs text-secondary-light"></span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="dim-meta mb-12">' +
                    '<div class="dim-meta-item">' +
                        '<div class="text-xs text-secondary-light mb-2">Aktif</div>' +
                        '<div class="fw-semibold text-sm meta-active">0</div>' +
                    '</div>' +
                    '<div class="dim-meta-item">' +
                        '<div class="text-xs text-secondary-light mb-2">Food</div>' +
                        '<div class="fw-semibold text-sm text-primary-600 meta-food">0</div>' +
                    '</div>' +
                    '<div class="dim-meta-item">' +
                        '<div class="text-xs text-secondary-light mb-2">Workout</div>' +
                        '<div class="fw-semibold text-sm text-warning-main meta-workout">0</div>' +
                    '</div>' +
                '</div>' +
                '<div class="d-flex align-items-center justify-content-between gap-2">' +
                    '<span class="text-xs text-secondary-light text-truncate top-label">Top: -</span>' +
                    '<span class="text-xs fw-medium text-success-main flex-shrink-0 top-evals">0 eval</span>' +
                '</div>';

            card.querySelector('h6').textContent = item.label || item.dimension;
            card.querySelector('.text-xs.text-secondary-light').textContent =
                formatNumber(item.groups || 0) + ' ' + unit;
            card.querySelector('.meta-active').textContent = formatNumber(item.active_users);
            card.querySelector('.meta-food').textContent = formatNumber(item.food_evals);
            card.querySelector('.meta-workout').textContent = formatNumber(item.workout_evals);
            card.querySelector('.top-label').textContent = 'Top: ' + (item.top_name || '-');
            card.querySelector('.top-evals').textContent = formatNumber(item.top_evals) + ' eval';

            card.addEventListener('click', function () {
                loadDimension(item.dimension, currentWeekStart);
            });
            card.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    loadDimension(item.dimension, currentWeekStart);
                }
            });

            col.appendChild(card);
            overviewEl.appendChild(col);
        });

        overviewRendered = true;
    }

    function highlightOverview(activeDimension) {
        if (!overviewEl) {
            return;
        }
        overviewEl.querySelectorAll('.active-stats-dim-card').forEach(function (card) {
            card.classList.toggle('is-active', card.getAttribute('data-dimension') === activeDimension);
        });
    }

    function renderTable(rows) {
        if (!tableBody) {
            return;
        }
        tableBody.innerHTML = '';
        if (!rows || !rows.length) {
            if (tableEmptyEl) tableEmptyEl.classList.remove('d-none');
            return;
        }
        if (tableEmptyEl) tableEmptyEl.classList.add('d-none');

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td class="fw-medium">' + escapeHtml(row.name) + '</td>' +
                '<td class="text-end">' + formatNumber(row.active_users) + '</td>' +
                '<td class="text-end">' + formatNumber(row.food_evals) + '</td>' +
                '<td class="text-end">' + formatNumber(row.workout_evals) + '</td>' +
                '<td class="text-end fw-semibold">' + formatNumber(row.total_evals) + '</td>' +
                '<td class="text-end">' + (row.pct || 0) + '%</td>';
            tableBody.appendChild(tr);
        });
    }

    function renderLeaderboard(rows) {
        if (!leaderboardBody) {
            return;
        }
        leaderboardBody.innerHTML = '';
        if (leaderboardBadge) {
            leaderboardBadge.textContent = formatNumber((rows || []).length);
        }
        if (!rows || !rows.length) {
            if (leaderboardEmptyEl) leaderboardEmptyEl.classList.remove('d-none');
            return;
        }
        if (leaderboardEmptyEl) leaderboardEmptyEl.classList.add('d-none');

        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            var nameHtml = escapeHtml(row.nama);
            if (row.user_id) {
                nameHtml = '<a href="' + employeeShowBase + '/' + row.user_id + '" class="text-primary-600 hover-text-primary fw-medium">' +
                    escapeHtml(row.nama) + '</a>';
            }
            var activeBadge = row.is_active
                ? '<span class="bg-success-focus text-success-main px-10 py-2 rounded-pill text-xs fw-medium">Ya</span>'
                : '<span class="bg-neutral-100 text-secondary-light px-10 py-2 rounded-pill text-xs fw-medium">Tidak</span>';

            tr.innerHTML =
                '<td class="fw-semibold">' + formatNumber(row.rank) + '</td>' +
                '<td>' + nameHtml + '</td>' +
                '<td>' + escapeHtml(row.site) + '</td>' +
                '<td>' + escapeHtml(row.perusahaan) + '</td>' +
                '<td>' + escapeHtml(row.jabatan) + '</td>' +
                '<td class="text-end">' + formatNumber(row.food_evals) + '</td>' +
                '<td class="text-end">' + formatNumber(row.workout_evals) + '</td>' +
                '<td class="text-end fw-semibold">' + formatNumber(row.total_evals) + '</td>' +
                '<td class="text-center">' + activeBadge + '</td>';
            leaderboardBody.appendChild(tr);
        });
    }

    function renderTrend(trend) {
        if (!trendEl || typeof ApexCharts === 'undefined') {
            return;
        }
        var labels = (trend && trend.labels) ? trend.labels : [];
        var series = (trend && trend.active_users) ? trend.active_users : [];

        if (trendChart) {
            trendChart.destroy();
            trendChart = null;
        }

        if (!labels.length) {
            if (trendEmptyEl) trendEmptyEl.classList.remove('d-none');
            trendEl.classList.add('d-none');
            return;
        }
        if (trendEmptyEl) trendEmptyEl.classList.add('d-none');
        trendEl.classList.remove('d-none');

        trendChart = new ApexCharts(trendEl, {
            series: [{ name: 'User Aktif', data: series }],
            chart: {
                type: 'area',
                height: 140,
                toolbar: { show: false },
                sparkline: { enabled: false },
                parentHeightOffset: 0
            },
            stroke: { curve: 'smooth', width: 2 },
            colors: ['#45b369'],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            dataLabels: { enabled: false },
            grid: {
                borderColor: '#E5E7EB',
                strokeDashArray: 4,
                padding: { left: 8, right: 8 }
            },
            xaxis: {
                categories: labels,
                labels: { style: { fontSize: '10px' } }
            },
            yaxis: {
                labels: {
                    style: { fontSize: '10px' },
                    formatter: function (value) {
                        return formatNumber(value);
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return formatNumber(value) + ' user';
                    }
                }
            }
        });
        trendChart.render();
    }

    function renderChart(payload) {
        if (!chartEl || typeof ApexCharts === 'undefined') {
            return;
        }

        var categories = (payload.chart && payload.chart.categories) ? payload.chart.categories : [];
        var activeUsers = (payload.chart && payload.chart.active_users) ? payload.chart.active_users : [];
        var foodEvals = (payload.chart && payload.chart.food_evals) ? payload.chart.food_evals : [];
        var workoutEvals = (payload.chart && payload.chart.workout_evals) ? payload.chart.workout_evals : [];

        if (barChart) {
            barChart.destroy();
            barChart = null;
        }

        if (!categories.length) {
            if (chartEmptyEl) chartEmptyEl.classList.remove('d-none');
            chartEl.classList.add('d-none');
            return;
        }
        if (chartEmptyEl) chartEmptyEl.classList.add('d-none');
        chartEl.classList.remove('d-none');

        var height = Math.max(300, categories.length * 28 + 80);
        applyDetailHeight(height);

        barChart = new ApexCharts(chartEl, {
            series: [
                { name: 'User Aktif', data: activeUsers },
                { name: 'Eval. Makanan', data: foodEvals },
                { name: 'Eval. Olahraga', data: workoutEvals }
            ],
            chart: {
                type: 'bar',
                height: height,
                width: '100%',
                stacked: false,
                toolbar: { show: false },
                parentHeightOffset: 0
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 4,
                    barHeight: '68%',
                    dataLabels: { position: 'top' }
                }
            },
            colors: ['#45b369', '#487FFF', '#FF9F29'],
            dataLabels: { enabled: false },
            grid: {
                show: true,
                borderColor: '#D1D5DB',
                strokeDashArray: 4,
                position: 'back',
                padding: { left: 8, right: 8 }
            },
            xaxis: {
                categories: categories,
                labels: {
                    formatter: function (value) {
                        var n = Number(value);
                        if (n >= 1000) {
                            return (n / 1000).toFixed(0) + 'k';
                        }
                        return value;
                    }
                }
            },
            yaxis: {
                labels: {
                    style: { fontSize: '11px' },
                    maxWidth: 120
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'left',
                fontSize: '12px'
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return formatNumber(value);
                    }
                }
            }
        });
        barChart.render().then(function () {
            applyDetailHeight(height);
        });
    }

    function renderPayload(payload) {
        if (footnoteEl && payload.footnote) {
            footnoteEl.textContent = payload.footnote;
        }

        if (!payload.available) {
            if (unavailableEl) unavailableEl.classList.remove('d-none');
            if (contentEl) contentEl.classList.add('d-none');
            if (messageEl) messageEl.textContent = payload.message || 'Koneksi BeWell belum tersedia.';
            return;
        }

        if (unavailableEl) unavailableEl.classList.add('d-none');
        if (contentEl) contentEl.classList.remove('d-none');

        currentDimension = payload.dimension || 'site';
        if (payload.week && payload.week.start) {
            currentWeekStart = payload.week.start;
        }

        fillWeekOptions(payload.week_options || [], currentWeekStart);
        if (weekSelectEl && currentWeekStart) {
            weekSelectEl.value = currentWeekStart;
        }

        renderSummary(payload);
        if (!overviewRendered || !(payload.overview && payload.overview.length)) {
            renderOverview(payload.overview || [], currentDimension);
        } else {
            highlightOverview(currentDimension);
        }
        renderTable(payload.rows || []);
        renderChart(payload);
        renderTrend(payload.weekly_trend || {});
        renderLeaderboard(payload.leaderboard || []);
    }

    function loadDimension(dimension, weekStart) {
        dimension = dimension || 'site';
        weekStart = weekStart || currentWeekStart || '';
        var key = cacheKey(dimension, weekStart);

        if (cache[key]) {
            renderPayload(cache[key]);
            return;
        }

        setLoading(true);
        var url = dataUrl + '?dimension=' + encodeURIComponent(dimension);
        if (weekStart) {
            url += '&week_start=' + encodeURIComponent(weekStart);
        }

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (payload) {
                cache[key] = payload;
                if (payload.overview && payload.overview.length) {
                    overviewRendered = false;
                }
                renderPayload(payload);
            })
            .catch(function () {
                renderPayload({
                    available: false,
                    dimension: dimension,
                    dimension_label: 'Site',
                    footnote: 'User aktif (luas) = food photo / workout / komunitas / Main Bareng. Evaluasi = food + workout.',
                    message: 'Gagal memuat statistik user aktif.',
                    week: { start: weekStart, end: '', label: '—', prev_start: '' },
                    week_options: [],
                    weekly_trend: { labels: [], active_users: [], week_starts: [] },
                    summary: {
                        active_users: 0,
                        food_evals: 0,
                        workout_evals: 0,
                        total_evals: 0,
                        week_increase: 0,
                        kpi_card_total: 0,
                        groups: 0
                    },
                    overview: [],
                    rows: [],
                    chart: { categories: [], active_users: [], food_evals: [], workout_evals: [] },
                    leaderboard: []
                });
            })
            .finally(function () {
                setLoading(false);
            });
    }

    function scrollToStatusInstall() {
        var section = document.getElementById('notInstalledTable');
        var userAktifEl = document.getElementById('not-installed-user-aktif');
        var installEl = document.getElementById('not-installed-install');
        if (userAktifEl) {
            userAktifEl.value = 'ya';
        }
        if (installEl) {
            installEl.value = '';
        }
        if (section) {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        var applyBtn = document.getElementById('not-installed-apply-btn');
        if (applyBtn) {
            applyBtn.click();
        }
    }

    modalEl.addEventListener('shown.bs.modal', function () {
        overviewRendered = false;
        weekOptionsFilled = false;
        loadDimension(currentDimension || 'site', currentWeekStart || '');
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (barChart) {
            barChart.destroy();
            barChart = null;
        }
        if (trendChart) {
            trendChart.destroy();
            trendChart = null;
        }
    });

    if (weekSelectEl) {
        weekSelectEl.addEventListener('change', function () {
            currentWeekStart = weekSelectEl.value || '';
            overviewRendered = false;
            loadDimension(currentDimension || 'site', currentWeekStart);
        });
    }

    if (openStatusBtn) {
        openStatusBtn.addEventListener('click', function () {
            var modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) {
                modalInstance.hide();
            }
            window.setTimeout(scrollToStatusInstall, 250);
        });
    }

    if (cardEl) {
        cardEl.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }
        });
    }
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
            <div
              class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-1 cursor-pointer"
              role="button"
              tabindex="0"
              data-bs-toggle="modal"
              data-bs-target="#installStatsModal"
              aria-label="Lihat detail statistik install"
              title="Lihat detail statistik install"
              id="total-user-install-card"
            >
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
            <div
              class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-2 cursor-pointer"
              role="button"
              tabindex="0"
              data-bs-toggle="modal"
              data-bs-target="#activeStatsModal"
              aria-label="Lihat detail statistik user aktif"
              title="Lihat detail statistik user aktif"
              id="total-user-aktif-card"
            >
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


      <!-- Belum Install Start -->
      <div class="col-12">
        <div class="card radius-8 border-0 shadow-sm">
          <div class="card-header border-bottom bg-base py-16 px-24">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
              <div>
                <div class="d-flex align-items-center gap-2 mb-4">
                  <h6 class="text-lg fw-semibold mb-0">Status Install Karyawan</h6>
                  <span id="not-installed-total-badge" class="bg-warning-focus text-warning-main text-sm fw-medium px-12 py-2 rounded-pill">{{ number_format($notInstalledTotal ?? 0) }}</span>
                </div>
                <p class="text-sm text-secondary-light mb-0">
                  Karyawan status AKTIF (exclude VISITOR) · User aktif = upload makanan/olahraga minggu ini ({{ $notInstalledWeekLabel ?? 'Sen–Min' }})
                </p>
              </div>
              <a id="not-installed-export-btn" href="{{ route('evaluasi-well.not-installed.export', ['install' => 'belum']) }}" class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
                <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
                Download Excel
              </a>
            </div>
          </div>
          <div class="card-body p-24">
            <div class="bg-neutral-50 border radius-8 p-16 mb-20">
              <div class="row g-3 align-items-end">
                <div class="col-xl-2 col-md-4 col-sm-6">
                  <label for="not-installed-site" class="form-label text-sm fw-medium mb-6">Site</label>
                  <select id="not-installed-site" class="form-select form-select-sm">
                    <option value="">Semua Site</option>
                    @foreach (($notInstalledSites ?? []) as $site)
                      <option value="{{ $site }}">{{ $site }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                  <label for="not-installed-company" class="form-label text-sm fw-medium mb-6">Perusahaan</label>
                  <select id="not-installed-company" class="form-select form-select-sm">
                    <option value="">Semua Perusahaan</option>
                    @foreach (($notInstalledCompanies ?? []) as $company)
                      <option value="{{ $company }}">{{ $company }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                  <label for="not-installed-division" class="form-label text-sm fw-medium mb-6">Divisi</label>
                  <input
                    id="not-installed-division"
                    type="search"
                    list="not-installed-division-options"
                    class="form-control form-control-sm"
                    placeholder="Cari divisi..."
                    autocomplete="off"
                  >
                  <datalist id="not-installed-division-options">
                    @foreach (($notInstalledDivisions ?? []) as $division)
                      <option value="{{ $division }}"></option>
                    @endforeach
                  </datalist>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                  <label for="not-installed-departement" class="form-label text-sm fw-medium mb-6">Departemen</label>
                  <input
                    id="not-installed-departement"
                    type="search"
                    list="not-installed-departement-options"
                    class="form-control form-control-sm"
                    placeholder="Cari departemen..."
                    autocomplete="off"
                  >
                  <datalist id="not-installed-departement-options">
                    @foreach (($notInstalledDepartements ?? []) as $departement)
                      <option value="{{ $departement }}"></option>
                    @endforeach
                  </datalist>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                  <label for="not-installed-jabatan-fungsional" class="form-label text-sm fw-medium mb-6">Jabatan Fungsional</label>
                  <select id="not-installed-jabatan-fungsional" class="form-select form-select-sm">
                    <option value="">Semua Jabatan</option>
                    @foreach (($notInstalledJabatanFungsionals ?? []) as $jabatan)
                      <option value="{{ $jabatan }}">{{ $jabatan }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                  <label for="not-installed-install" class="form-label text-sm fw-medium mb-6">Install</label>
                  <select id="not-installed-install" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="belum" selected>Belum</option>
                    <option value="sudah">Sudah</option>
                  </select>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                  <label for="not-installed-user-aktif" class="form-label text-sm fw-medium mb-6">User Aktif</label>
                  <select id="not-installed-user-aktif" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="ya">Ya</option>
                    <option value="tidak">Tidak</option>
                  </select>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                  <div class="d-flex gap-2">
                    <button type="button" id="not-installed-reset-btn" class="btn btn-sm btn-outline-secondary w-100">Reset</button>
                    <button type="button" id="not-installed-apply-btn" class="btn btn-sm btn-primary-600 w-100">Filter</button>
                  </div>
                </div>
              </div>
            </div>

            <div class="not-installed-datatable w-100">
              <table id="notInstalledTable" class="table bordered-table mb-0 w-100" style="width:100%">
                <thead>
                  <tr>
                    <th scope="col" style="width:20%">Karyawan</th>
                    <th scope="col" style="width:18%">Perusahaan</th>
                    <th scope="col" style="width:18%">Departemen</th>
                    <th scope="col" style="width:18%">Divisi</th>
                    <th scope="col" style="width:13%">Install</th>
                    <th scope="col" style="width:13%">User Aktif</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <!-- Belum Install End -->
    </div>

@include('evaluasi-well.partials._install-stats-modal')
@include('evaluasi-well.partials._active-stats-modal')
@endsection
