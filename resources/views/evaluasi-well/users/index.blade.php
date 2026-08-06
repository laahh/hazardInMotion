@extends('evaluasi-well.layouts.app')

@section('title', 'Manajemen User')

@section('css')
<style>
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
    width: 100% !important;
    table-layout: fixed !important;
  }

  #usersTable th,
  #usersTable td {
    vertical-align: middle;
    word-break: break-word;
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
            { data: 'kode_sid' },
            { data: 'site' },
            { data: 'company' },
            { data: 'divisi' },
            { data: 'departement' },
            { data: 'jabatan_fungsional' },
            {
                data: 'status_karyawan',
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
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (data) {
                    return '<a href="' + editBase + '/' + data + '/edit" class="btn btn-sm btn-outline-primary-600 radius-8 px-12 py-6">'
                        + 'Edit</a>';
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
        <p class="text-sm text-secondary-light mb-0">Create / edit profil di <code>employee_profiles</code>. Tanpa hapus.</p>
      </div>
      <a href="{{ route('evaluasi-well.users.create') }}" class="btn btn-primary-600 radius-8 px-16 py-10">
        <iconify-icon icon="solar:user-plus-outline" class="icon"></iconify-icon>
        Tambah Karyawan
      </a>
    </div>
  </div>
  <div class="card-body p-24">
    <div class="row g-3 mb-20">
      <div class="col-md-3">
        <label for="eu-site" class="form-label text-sm">Site</label>
        <select id="eu-site" class="form-select">
          <option value="">Semua</option>
          @foreach ($opts['sites'] as $site)
            <option value="{{ $site }}" @selected(($f['site'] ?? '') === $site)>{{ $site }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label for="eu-company" class="form-label text-sm">Perusahaan</label>
        <select id="eu-company" class="form-select">
          <option value="">Semua</option>
          @foreach ($opts['companies'] as $company)
            <option value="{{ $company }}" @selected(($f['company'] ?? '') === $company)>{{ $company }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label for="eu-division" class="form-label text-sm">Divisi</label>
        <input type="text" id="eu-division" class="form-control" value="{{ $f['division'] ?? '' }}" placeholder="Cari divisi...">
      </div>
      <div class="col-md-2">
        <label for="eu-status" class="form-label text-sm">Status</label>
        <select id="eu-status" class="form-select">
          <option value="">Semua</option>
          @foreach ($opts['statuses'] as $status)
            <option value="{{ $status }}" @selected(($f['status'] ?? '') === $status)>{{ $status }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end gap-2">
        <button type="button" id="eu-apply-btn" class="btn btn-primary-600 radius-8 px-16 py-10">Terapkan</button>
        <button type="button" id="eu-reset-btn" class="btn btn-outline-secondary radius-8 px-16 py-10">Reset</button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table bordered-table mb-0" id="usersTable">
        <thead>
          <tr>
            <th>Nama / NIK</th>
            <th>Kode SID</th>
            <th>Site</th>
            <th>Perusahaan</th>
            <th>Divisi</th>
            <th>Departemen</th>
            <th>Jabatan</th>
            <th class="text-center">Status</th>
            <th class="text-center">Aksi</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
</div>
@endsection
