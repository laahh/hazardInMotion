@extends('evaluasi-well.layouts.app')

@section('title', 'Manajemen User')

@section('css')
<style>
  .users-datatable,
  #usersTable,
  #usersTable_wrapper {
    width: 100% !important;
    max-width: 100% !important;
  }

  .dt-container:has(#usersTable) .dt-layout-row,
  #usersTable_wrapper .dt-layout-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0.75rem 0;
  }

  .dt-container:has(#usersTable) .dt-paging,
  #usersTable_wrapper .dt-paging {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.375rem;
  }

  .dt-container:has(#usersTable) .dt-paging .dt-paging-button,
  #usersTable_wrapper .dt-paging .dt-paging-button {
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

  #usersTable {
    table-layout: fixed !important;
  }

  #usersTable th,
  #usersTable td {
    vertical-align: middle;
    word-break: break-word;
    white-space: normal;
  }

  #usersTable thead th {
    white-space: nowrap;
    font-weight: 600;
  }

  #usersTable .eu-actions {
    white-space: nowrap;
  }
</style>
@endsection

@section('page-scripts')
<script>
(function () {
    var tableEl = document.querySelector('#usersTable');
    if (!tableEl || typeof DataTable === 'undefined') {
        return;
    }

    var dataUrl = @json(route('evaluasi-well.users.data'));
    var indexUrl = @json(route('evaluasi-well.users.index'));
    var editBase = @json(url('/evaluasi-well/users'));

    var siteEl = document.querySelector('#eu-site');
    var companyEl = document.querySelector('#eu-company');
    var divisionEl = document.querySelector('#eu-division');
    var statusEl = document.querySelector('#eu-status');
    var applyBtn = document.querySelector('#eu-apply-btn');
    var resetBtn = document.querySelector('#eu-reset-btn');
    var totalBadge = document.querySelector('#eu-total-badge');

    function escapeHtml(value) {
        return String(value == null ? '' : value)
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
            status: statusEl ? statusEl.value : ''
        };
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
        ajax: {
            url: dataUrl,
            data: function (d) {
                var filters = currentFilters();
                d.site = filters.site;
                d.company = filters.company;
                d.division = filters.division;
                d.status = filters.status;
            }
        },
        columns: [
            {
                data: 'nama',
                width: '18%',
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data;
                    }
                    return '<span class="fw-medium">' + escapeHtml(data) + '</span>'
                        + '<span class="text-sm d-block fw-normal text-secondary-light">'
                        + escapeHtml(row.nik || '-')
                        + '</span>';
                }
            },
            { data: 'kode_sid', width: '10%' },
            { data: 'site', width: '8%' },
            { data: 'company', width: '16%' },
            { data: 'divisi', width: '12%' },
            { data: 'departement', width: '12%' },
            { data: 'jabatan_fungsional', width: '12%' },
            {
                data: 'status_karyawan',
                width: '8%',
                className: 'text-center',
                render: function (data, type) {
                    if (type !== 'display') {
                        return data;
                    }
                    var status = String(data || '-');
                    var cls = status.toUpperCase() === 'AKTIF'
                        ? 'bg-success-100 text-success-600'
                        : 'bg-neutral-200 text-secondary-light';
                    return '<span class="text-sm fw-medium px-12 py-2 rounded-pill ' + cls + '">'
                        + escapeHtml(status)
                        + '</span>';
                }
            },
            {
                data: 'id',
                width: '6%',
                orderable: false,
                searchable: false,
                className: 'text-center eu-actions',
                render: function (data) {
                    return '<a href="' + editBase + '/' + data + '/edit" class="btn btn-sm btn-outline-primary-600 radius-8 px-12 py-6">Edit</a>';
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
            zeroRecords: 'Tidak ada karyawan ditemukan.',
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
})();
</script>
@endsection

@section('content')
@php
  $f = $filters ?? ['site' => '', 'company' => '', 'division' => '', 'status' => ''];
  $opts = $filterOptions ?? ['sites' => [], 'companies' => [], 'divisions' => [], 'statuses' => ['AKTIF', 'NONAKTIF']];
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Manajemen User</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('evaluasi-well.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Manajemen User</li>
  </ul>
</div>

@if (session('success'))
<div class="alert alert-success bg-success-100 text-success-600 border-success-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ session('success') }}
</div>
@endif

@if ($errors->has('form'))
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ $errors->first('form') }}
</div>
@endif

@unless ($connectionUp ?? false)
<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8 d-flex align-items-start gap-2" role="alert">
  <iconify-icon icon="solar:danger-triangle-bold" class="icon text-xl mt-1"></iconify-icon>
  <div>Koneksi BeWell tidak tersedia. Pastikan <code>start-bewell-tunnel.bat</code> berjalan.</div>
</div>
@endunless

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-4">
          <h6 class="text-lg fw-semibold mb-0">Daftar Karyawan BeWell</h6>
          <span id="eu-total-badge" class="bg-primary-50 text-primary-600 text-sm fw-medium px-12 py-2 rounded-pill">0</span>
        </div>
        <p class="text-sm text-secondary-light mb-0">Create / edit profil di <code>employee_profiles</code>. Tanpa hapus. Password login = Kode SID.</p>
      </div>
      <a href="{{ route('evaluasi-well.users.create') }}" class="btn btn-primary-600 radius-8 px-16 py-10">
        <iconify-icon icon="solar:user-plus-outline" class="icon"></iconify-icon>
        Tambah Karyawan
      </a>
    </div>
  </div>
  <div class="card-body p-24">
    <div class="bg-neutral-50 border radius-8 p-16 mb-20">
      <div class="row g-3 align-items-end">
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="eu-site" class="form-label text-sm fw-medium mb-6">Site</label>
          <select id="eu-site" class="form-select form-select-sm">
            <option value="">Semua Site</option>
            @foreach ($opts['sites'] as $site)
              <option value="{{ $site }}" @selected(($f['site'] ?? '') === $site)>{{ $site }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xl-3 col-md-4 col-sm-6">
          <label for="eu-company" class="form-label text-sm fw-medium mb-6">Perusahaan</label>
          <select id="eu-company" class="form-select form-select-sm">
            <option value="">Semua Perusahaan</option>
            @foreach ($opts['companies'] as $company)
              <option value="{{ $company }}" @selected(($f['company'] ?? '') === $company)>{{ $company }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="eu-division" class="form-label text-sm fw-medium mb-6">Divisi</label>
          <input
            type="search"
            id="eu-division"
            class="form-control form-control-sm"
            value="{{ $f['division'] ?? '' }}"
            placeholder="Cari divisi..."
            autocomplete="off"
          >
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="eu-status" class="form-label text-sm fw-medium mb-6">Status</label>
          <select id="eu-status" class="form-select form-select-sm">
            <option value="">Semua</option>
            @foreach ($opts['statuses'] as $status)
              <option value="{{ $status }}" @selected(($f['status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xl-3 col-md-4 col-sm-6">
          <div class="d-flex gap-2">
            <button type="button" id="eu-reset-btn" class="btn btn-sm btn-outline-secondary w-100">Reset</button>
            <button type="button" id="eu-apply-btn" class="btn btn-sm btn-primary-600 w-100">Filter</button>
          </div>
        </div>
      </div>
    </div>

    <div class="users-datatable w-100">
      <table id="usersTable" class="table bordered-table mb-0 w-100" style="width:100%">
        <thead>
          <tr>
            <th scope="col" style="width:18%">Nama / NIK</th>
            <th scope="col" style="width:10%">Kode SID</th>
            <th scope="col" style="width:8%">Site</th>
            <th scope="col" style="width:16%">Perusahaan</th>
            <th scope="col" style="width:12%">Divisi</th>
            <th scope="col" style="width:12%">Departemen</th>
            <th scope="col" style="width:12%">Jabatan</th>
            <th scope="col" class="text-center" style="width:8%">Status</th>
            <th scope="col" class="text-center" style="width:6%">Aksi</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
@endsection
