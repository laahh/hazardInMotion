@extends('evaluasi-well.layouts.app')

@section('title', 'Risiko MCU × Nutrisi')

@section('css')
<style>
  .dt-container:has(#healthNutritionTable) .dt-layout-row,
  #healthNutritionTable_wrapper .dt-layout-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0.75rem 0;
  }

  .dt-container:has(#healthNutritionTable) .dt-paging,
  #healthNutritionTable_wrapper .dt-paging {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.375rem;
  }

  .dt-container:has(#healthNutritionTable) .dt-paging .dt-paging-button,
  #healthNutritionTable_wrapper .dt-paging .dt-paging-button {
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

  .dt-container:has(#healthNutritionTable),
  #healthNutritionTable_wrapper,
  .health-nutrition-datatable,
  #healthNutritionTable {
    width: 100% !important;
    max-width: 100% !important;
  }

  #healthNutritionTable {
    table-layout: fixed !important;
  }

  #healthNutritionTable th,
  #healthNutritionTable td {
    vertical-align: middle;
    word-break: break-word;
  }

  #healthNutritionTable thead th {
    white-space: nowrap;
    font-weight: 600;
  }
</style>
@endsection

@section('page-scripts')
<script>
(function () {
    var el = document.querySelector('#healthNutritionLabChart');
    if (!el || typeof ApexCharts === 'undefined') {
        return;
    }

    var labels = @json($labChartLabels ?? []);
    var warn = @json($labChartWarn ?? []);
    var high = @json($labChartHigh ?? []);

    new ApexCharts(el, {
        series: [
            { name: 'Waspada', data: warn },
            { name: 'Tinggi', data: high }
        ],
        colors: ['#FF9F29', '#EF4A00'],
        chart: { type: 'bar', height: 300, toolbar: { show: false }, stacked: true },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: '#D1D5DB', strokeDashArray: 4 },
        xaxis: { categories: labels },
        legend: { position: 'top' },
        tooltip: { shared: true, intersect: false }
    }).render();
})();
</script>
<script>
(function () {
    var tableEl = document.querySelector('#healthNutritionTable');
    if (!tableEl || typeof DataTable === 'undefined') {
        return;
    }

    var employeeShowBase = @json(url('/evaluasi-well/employees'));
    var dataUrl = @json(route('evaluasi-well.health-nutrition.data'));
    var exportUrl = @json(route('evaluasi-well.health-nutrition.export'));

    var siteEl = document.querySelector('#hn-site');
    var companyEl = document.querySelector('#hn-company');
    var divisionEl = document.querySelector('#hn-division');
    var severityEl = document.querySelector('#hn-mcu-severity');
    var labEl = document.querySelector('#hn-lab-type');
    var applyBtn = document.querySelector('#hn-apply-btn');
    var resetBtn = document.querySelector('#hn-reset-btn');
    var exportBtn = document.querySelector('#hn-export-btn');
    var totalBadge = document.querySelector('#hn-p1-badge');

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
            mcu_severity: severityEl ? severityEl.value : '',
            lab_type: labEl ? labEl.value : ''
        };
    }

    function updateExportHref() {
        if (!exportBtn) return;
        var filters = currentFilters();
        var params = new URLSearchParams();
        Object.keys(filters).forEach(function (key) {
            if (filters[key]) params.set(key, filters[key]);
        });
        var search = table.search();
        if (search) params.set('search', search);
        var query = params.toString();
        exportBtn.href = query ? (exportUrl + '?' + query) : exportUrl;
    }

    function forceFullWidth() {
        tableEl.style.setProperty('width', '100%', 'important');
        var colgroup = tableEl.querySelector('colgroup');
        if (colgroup) colgroup.remove();
    }

    var table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[6, 'desc']],
        autoWidth: false,
        scrollX: false,
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
                d.site = filters.site;
                d.company = filters.company;
                d.division = filters.division;
                d.mcu_severity = filters.mcu_severity;
                d.lab_type = filters.lab_type;
            }
        },
        columns: [
            {
                data: 'nama',
                render: function (data, type, row) {
                    if (type !== 'display') return data;
                    return '<a href="' + employeeShowBase + '/' + row.user_id + '" class="text-primary-light hover-text-primary fw-medium">'
                        + escapeHtml(data) + '</a>'
                        + '<span class="text-sm d-block fw-normal text-secondary-light">' + escapeHtml(row.kode_sid) + '</span>';
                }
            },
            { data: 'company' },
            { data: 'divisi' },
            {
                data: 'mcu_badges',
                orderable: false,
                render: function (data, type) {
                    if (type !== 'display') return '';
                    if (!data || !data.length) return '-';
                    return data.map(function (b) {
                        return '<span class="' + escapeHtml(b.class) + ' px-12 py-4 rounded-pill fw-medium text-sm d-inline-block mb-4 me-4">'
                            + escapeHtml(b.label) + '</span>';
                    }).join('');
                }
            },
            {
                data: 'alert_codes',
                orderable: false,
                render: function (data, type) {
                    if (type !== 'display') return (data || []).join(',');
                    if (!data || !data.length) return '-';
                    return data.map(function (code) {
                        return '<span class="bg-neutral-200 text-secondary-light px-10 py-2 rounded-pill text-sm d-inline-block mb-4 me-4">'
                            + escapeHtml(code) + '</span>';
                    }).join('');
                }
            },
            {
                data: 'days_logged',
                orderable: false,
                render: function (data, type, row) {
                    if (type !== 'display') return data;
                    return escapeHtml(String(data)) + ' hari<br><span class="text-secondary-light text-sm">'
                        + Number(row.avg_calories || 0).toLocaleString('id-ID') + ' kkal</span>';
                }
            },
            {
                data: 'risk_score',
                render: function (data, type, row) {
                    if (type !== 'display') return data;
                    return '<div class="fw-semibold">' + escapeHtml(String(data)) + '</div>'
                        + '<span class="text-sm text-secondary-light">' + escapeHtml(row.evidence || '') + '</span>';
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
            zeroRecords: 'Tidak ada karyawan Prioritas 1 untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    table.on('init', function () {
        forceFullWidth();
        var container = tableEl.closest('.dt-container');
        var searchInput = container ? container.querySelector('.dt-search input') : null;
        if (searchInput) {
            searchInput.setAttribute('placeholder', 'Cari nama / SID / divisi...');
            searchInput.classList.add('form-control', 'form-control-sm');
        }
    });

    table.on('draw', function () {
        forceFullWidth();
        if (totalBadge) {
            totalBadge.textContent = Number(table.page.info().recordsDisplay || 0).toLocaleString('id-ID');
        }
        updateExportHref();
    });

    table.on('search.dt', updateExportHref);

    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            // Full page reload keeps KPI/chart in sync with filters
            var filters = currentFilters();
            var params = new URLSearchParams();
            Object.keys(filters).forEach(function (key) {
                if (filters[key]) params.set(key, filters[key]);
            });
            var query = params.toString();
            window.location.href = @json(route('evaluasi-well.health-nutrition.index')) + (query ? ('?' + query) : '');
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            window.location.href = @json(route('evaluasi-well.health-nutrition.index'));
        });
    }

    if (divisionEl) {
        divisionEl.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (applyBtn) applyBtn.click();
            }
        });
    }

    updateExportHref();
})();
</script>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Risiko MCU × Nutrisi</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('evaluasi-well.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Risiko MCU × Nutrisi</li>
  </ul>
</div>

@if (!($bewellUp ?? false))
<div class="alert alert-warning radius-8 mb-24" role="alert">
  Koneksi BeWell tidak tersedia. Pastikan <code>start-bewell-tunnel.bat</code> berjalan.
</div>
@endif

@if (!($mcuUp ?? false))
<div class="alert alert-warning radius-8 mb-24" role="alert">
  Koneksi MCU (OLAP Postgres) tidak tersedia. Jalankan <code>setup-ssh-tunnel.bat</code> atau <code>start-bemcu-tunnel.bat</code> (port 5433).
</div>
@elseif (!($mcuMappingReady ?? false))
<div class="alert alert-info radius-8 mb-24" role="alert">
  MCU terhubung, tetapi mapping belum siap. Pastikan <code>config/bemcu.php</code> mengarah ke <code>mv_ftw_mcu</code> + mapping kondisi metabolik.
</div>
@endif

<div class="card radius-8 border-0 shadow-sm mb-24">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-4">
          <h6 class="text-lg fw-semibold mb-0">Prioritas Intervensi</h6>
          <span id="hn-p1-badge" class="bg-danger-focus text-danger-main text-sm fw-medium px-12 py-2 rounded-pill">{{ number_format($priorityOneTotal ?? ($kpi['p1'] ?? 0)) }}</span>
        </div>
        <p class="text-sm text-secondary-light mb-0">
          Karyawan AKTIF · MCU metabolik abnormal × pola makan berisiko · Minggu {{ $weekLabel ?? 'Sen–Min' }}
        </p>
      </div>
      <a id="hn-export-btn" href="{{ route('evaluasi-well.health-nutrition.export', request()->query()) }}" class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
        <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
        Download Excel
      </a>
    </div>
  </div>
  <div class="card-body p-24">
    @include('evaluasi-well.health-nutrition.partials._filters')

    @include('evaluasi-well.health-nutrition.partials._kpi')

    <div class="row gy-4 mb-24">
      <div class="col-12">
        <div class="card radius-8 border h-100">
          <div class="card-body p-24">
            <h6 class="fw-semibold mb-12">Distribusi Temuan Metabolik (MCU)</h6>
            <div id="healthNutritionLabChart"></div>
          </div>
        </div>
      </div>
    </div>

    <h6 class="fw-semibold mb-12">Daftar Prioritas 1</h6>
    <div class="health-nutrition-datatable w-100">
      <table id="healthNutritionTable" class="table bordered-table mb-0 w-100" style="width:100%">
        <thead>
          <tr>
            <th scope="col" style="width:18%">Karyawan</th>
            <th scope="col" style="width:16%">Perusahaan</th>
            <th scope="col" style="width:16%">Divisi</th>
            <th scope="col" style="width:18%">Temuan MCU</th>
            <th scope="col" style="width:12%">Alert Nutrisi</th>
            <th scope="col" style="width:10%">Log / Kkal</th>
            <th scope="col" style="width:10%">Skor &amp; Evidence</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
@endsection
