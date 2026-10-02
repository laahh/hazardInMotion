@extends('evaluasi-well.layouts.app')

@section('title', 'Upload Mingguan')

@section('css')
<style>
  .dt-container:has(#weeklyUploadsTable) .dt-layout-row,
  #weeklyUploadsTable_wrapper .dt-layout-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0.75rem 0;
  }

  .dt-container:has(#weeklyUploadsTable) .dt-paging,
  #weeklyUploadsTable_wrapper .dt-paging {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.375rem;
  }

  .dt-container:has(#weeklyUploadsTable) .dt-paging .dt-paging-button,
  #weeklyUploadsTable_wrapper .dt-paging .dt-paging-button {
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

  .dt-container:has(#weeklyUploadsTable),
  #weeklyUploadsTable_wrapper,
  .weekly-uploads-datatable,
  #weeklyUploadsTable {
    width: 100% !important;
    max-width: 100% !important;
  }

  #weeklyUploadsTable {
    table-layout: fixed !important;
  }

  #weeklyUploadsTable th,
  #weeklyUploadsTable td {
    vertical-align: middle;
    word-break: break-word;
  }

  #weeklyUploadsTable thead th {
    white-space: nowrap;
    font-weight: 600;
  }
</style>
@endsection

@section('page-scripts')
<script>
(function () {
    var el = document.querySelector('#weeklyUploadsTrendChart');
    if (!el || typeof ApexCharts === 'undefined') {
        return;
    }

    var labels = @json($trendLabels ?? []);
    var uploaders = @json($trendUploaders ?? []);
    var food = @json($trendFood ?? []);
    var workout = @json($trendWorkout ?? []);

    new ApexCharts(el, {
        series: [
            { name: 'Uploader', data: uploaders },
            { name: 'Makanan', data: food },
            { name: 'Olahraga', data: workout }
        ],
        colors: ['#487FFF', '#45B369', '#FF9F29'],
        chart: {
            type: 'area',
            height: 300,
            toolbar: { show: false },
            zoom: { enabled: false }
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.35,
                opacityTo: 0.05,
                stops: [0, 90, 100]
            }
        },
        grid: {
            borderColor: '#D1D5DB',
            strokeDashArray: 4
        },
        xaxis: {
            categories: labels,
            labels: {
                rotate: -30,
                style: { fontSize: '11px' }
            }
        },
        yaxis: {
            labels: {
                formatter: function (value) {
                    return Math.round(value);
                }
            }
        },
        legend: { position: 'top' },
        tooltip: { shared: true, intersect: false }
    }).render();
})();
</script>
<script>
(function () {
    var tableEl = document.querySelector('#weeklyUploadsTable');
    if (!tableEl || typeof DataTable === 'undefined') {
        return;
    }

    var employeeShowBase = @json(url('/evaluasi-well/employees'));
    var dataUrl = @json(route('evaluasi-well.weekly-uploads.data'));
    var exportUrl = @json(route('evaluasi-well.weekly-uploads.export'));
    var indexUrl = @json(route('evaluasi-well.weekly-uploads.index'));

    var weekEl = document.querySelector('#wu-week');
    var siteEl = document.querySelector('#wu-site');
    var companyEl = document.querySelector('#wu-company');
    var divisionEl = document.querySelector('#wu-division');
    var uploadTypeEl = document.querySelector('#wu-upload-type');
    var applyBtn = document.querySelector('#wu-apply-btn');
    var resetBtn = document.querySelector('#wu-reset-btn');
    var exportBtn = document.querySelector('#wu-export-btn');
    var totalBadge = document.querySelector('#wu-total-badge');

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
            week: weekEl ? weekEl.value : '',
            site: siteEl ? siteEl.value : '',
            company: companyEl ? companyEl.value : '',
            division: divisionEl ? divisionEl.value.trim() : '',
            upload_type: uploadTypeEl ? uploadTypeEl.value : ''
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
                d.week = filters.week;
                d.site = filters.site;
                d.company = filters.company;
                d.division = filters.division;
                d.upload_type = filters.upload_type;
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
            { data: 'food_count', className: 'text-center' },
            { data: 'workout_count', className: 'text-center' },
            { data: 'total_count', className: 'text-center fw-semibold' },
            { data: 'last_upload_at' }
        ],
        language: {
            processing: 'Memuat...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(difilter dari _MAX_ total data)',
            zeroRecords: 'Tidak ada karyawan yang upload di minggu ini.',
            paginate: {
                first: '«',
                last: '»',
                next: '›',
                previous: '‹'
            }
        }
    });

    table.on('draw', function () {
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
  $f = $filters ?? ['week' => '', 'site' => '', 'company' => '', 'division' => '', 'upload_type' => ''];
  $opts = $filterOptions ?? ['sites' => [], 'companies' => [], 'divisions' => []];
  $kpi = $kpi ?? ['uploaders' => 0, 'food_uploaders' => 0, 'workout_uploaders' => 0, 'food_entries' => 0, 'workout_entries' => 0];
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Upload Mingguan</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('evaluasi-well.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Upload Mingguan</li>
  </ul>
</div>

@unless ($connectionUp ?? false)
<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8 d-flex align-items-start gap-2" role="alert">
  <iconify-icon icon="solar:danger-triangle-bold" class="icon text-xl mt-1"></iconify-icon>
  <div>Koneksi BeWell tidak tersedia. Pastikan <code>start-bewell-tunnel.bat</code> berjalan.</div>
</div>
@endunless

<div class="row gy-4 mb-24">
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-1">
      <div class="card-body p-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
          <div class="d-flex align-items-center gap-2">
            <span class="mb-0 w-48-px h-48-px bg-primary-600 flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
              <iconify-icon icon="solar:users-group-rounded-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="fw-medium text-secondary-light text-sm mb-0">Uploader</span>
              <h6 class="fw-semibold mb-0">{{ number_format($kpi['uploaders'] ?? 0) }}</h6>
            </div>
          </div>
        </div>
        <p class="text-sm mb-0 text-secondary-light">Karyawan unik yang upload minggu ini</p>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-2">
      <div class="card-body p-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
          <div class="d-flex align-items-center gap-2">
            <span class="mb-0 w-48-px h-48-px bg-success-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
              <iconify-icon icon="solar:cup-hot-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="fw-medium text-secondary-light text-sm mb-0">Upload Makanan</span>
              <h6 class="fw-semibold mb-0">{{ number_format($kpi['food_uploaders'] ?? 0) }} <span class="text-sm fw-normal text-secondary-light">org / {{ number_format($kpi['food_entries'] ?? 0) }} entri</span></h6>
            </div>
          </div>
        </div>
        <p class="text-sm mb-0 text-secondary-light">Foto makanan (source_type = photo)</p>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-3">
      <div class="card-body p-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
          <div class="d-flex align-items-center gap-2">
            <span class="mb-0 w-48-px h-48-px bg-warning-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
              <iconify-icon icon="solar:running-round-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="fw-medium text-secondary-light text-sm mb-0">Upload Olahraga</span>
              <h6 class="fw-semibold mb-0">{{ number_format($kpi['workout_uploaders'] ?? 0) }} <span class="text-sm fw-normal text-secondary-light">org / {{ number_format($kpi['workout_entries'] ?? 0) }} entri</span></h6>
            </div>
          </div>
        </div>
        <p class="text-sm mb-0 text-secondary-light">Workout analyses minggu terpilih</p>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-none radius-8 border h-100 bg-gradient-start-4">
      <div class="card-body p-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
          <div class="d-flex align-items-center gap-2">
            <span class="mb-0 w-48-px h-48-px bg-info-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
              <iconify-icon icon="solar:calendar-mark-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="fw-medium text-secondary-light text-sm mb-0">Periode</span>
              <h6 class="fw-semibold mb-0 text-sm">{{ $weekLabel ?? 'Sen–Min' }}</h6>
            </div>
          </div>
        </div>
        <p class="text-sm mb-0 text-secondary-light">Exclude VISITOR · status AKTIF</p>
      </div>
    </div>
  </div>
</div>

<div class="card radius-8 border-0 shadow-sm mb-24">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-0">Tren Uploader 12 Minggu</h6>
  </div>
  <div class="card-body p-24">
    <div id="weeklyUploadsTrendChart"></div>
  </div>
</div>

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-4">
          <h6 class="text-lg fw-semibold mb-0">Siapa yang Upload</h6>
          <span id="wu-total-badge" class="bg-primary-50 text-primary-600 text-sm fw-medium px-12 py-2 rounded-pill">{{ number_format($kpi['uploaders'] ?? 0) }}</span>
        </div>
        <p class="text-sm text-secondary-light mb-0">
          Daftar karyawan yang mengunggah makanan dan/atau olahraga pada {{ $weekLabel ?? 'minggu terpilih' }}
        </p>
      </div>
      <a id="wu-export-btn" href="{{ route('evaluasi-well.weekly-uploads.export', request()->query()) }}" class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
        <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
        Download Excel
      </a>
    </div>
  </div>
  <div class="card-body p-24">
    <div class="bg-neutral-50 border radius-8 p-16 mb-20">
      <div class="row g-3 align-items-end">
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="wu-week" class="form-label text-sm fw-medium mb-6">Minggu</label>
          <select id="wu-week" class="form-select form-select-sm">
            @foreach (($weekOptions ?? []) as $week)
              <option value="{{ $week['key'] }}" @selected(($f['week'] ?? '') === $week['key'])>{{ $week['label'] }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="wu-site" class="form-label text-sm fw-medium mb-6">Site</label>
          <select id="wu-site" class="form-select form-select-sm">
            <option value="">Semua Site</option>
            @foreach (($opts['sites'] ?? []) as $site)
              <option value="{{ $site }}" @selected(($f['site'] ?? '') === $site)>{{ $site }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="wu-company" class="form-label text-sm fw-medium mb-6">Perusahaan</label>
          <select id="wu-company" class="form-select form-select-sm">
            <option value="">Semua Perusahaan</option>
            @foreach (($opts['companies'] ?? []) as $company)
              <option value="{{ $company }}" @selected(($f['company'] ?? '') === $company)>{{ $company }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="wu-division" class="form-label text-sm fw-medium mb-6">Divisi</label>
          <input
            id="wu-division"
            type="search"
            list="wu-division-options"
            class="form-control form-control-sm"
            placeholder="Cari divisi..."
            value="{{ $f['division'] ?? '' }}"
            autocomplete="off"
          >
          <datalist id="wu-division-options">
            @foreach (($opts['divisions'] ?? []) as $division)
              <option value="{{ $division }}"></option>
            @endforeach
          </datalist>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="wu-upload-type" class="form-label text-sm fw-medium mb-6">Jenis Upload</label>
          <select id="wu-upload-type" class="form-select form-select-sm">
            <option value="">Semua</option>
            <option value="food" @selected(($f['upload_type'] ?? '') === 'food')>Makanan saja</option>
            <option value="workout" @selected(($f['upload_type'] ?? '') === 'workout')>Olahraga saja</option>
            <option value="both" @selected(($f['upload_type'] ?? '') === 'both')>Makanan + Olahraga</option>
          </select>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <div class="d-flex gap-2">
            <button type="button" id="wu-reset-btn" class="btn btn-sm btn-outline-secondary w-100">Reset</button>
            <button type="button" id="wu-apply-btn" class="btn btn-sm btn-primary-600 w-100">Filter</button>
          </div>
        </div>
      </div>
    </div>

    <div class="weekly-uploads-datatable w-100">
      <table id="weeklyUploadsTable" class="table bordered-table mb-0 w-100" style="width:100%">
        <thead>
          <tr>
            <th scope="col" style="width:18%">Karyawan</th>
            <th scope="col" style="width:16%">Perusahaan</th>
            <th scope="col" style="width:14%">Departemen</th>
            <th scope="col" style="width:14%">Divisi</th>
            <th scope="col" style="width:8%">Makanan</th>
            <th scope="col" style="width:8%">Olahraga</th>
            <th scope="col" style="width:8%">Total</th>
            <th scope="col" style="width:14%">Upload Terakhir</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
@endsection
