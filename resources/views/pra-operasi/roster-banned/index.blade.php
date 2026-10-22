@extends('dms.layouts.app')

@section('title', 'Master Roster Banned')

@section('css')
<link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/dataTables.min.css') }}">
<style>
  #rbTable_wrapper .dt-layout-row,
  .dt-container:has(#rbTable) .dt-layout-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0.75rem 0;
  }
  #rbTable_wrapper .dt-paging .dt-paging-button,
  .dt-container:has(#rbTable) .dt-paging .dt-paging-button {
    width: auto !important;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.625rem !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    border-radius: 6px !important;
  }
  #rbTable { width: 100% !important; }
  #rbTable th, #rbTable td { vertical-align: middle; }
  #rbTable td.rb-col-alasan { white-space: normal; min-width: 220px; }
  #rbTable td.rb-col-log { white-space: normal; min-width: 180px; }
  #rbTable .rb-log-error { max-width: 260px; word-break: break-word; }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Master Roster Banned</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('pra-operasi.dashboard') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Pra Operasi
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Master Roster Banned</li>
  </ul>
</div>

@if (session('success'))
<div class="alert alert-success bg-success-100 text-success-600 border-success-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ session('success') }}
</div>
@endif

@if (session('error'))
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ session('error') }}
</div>
@endif

@if (session('import_errors'))
<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8" role="alert">
  <div class="fw-semibold mb-8">Beberapa baris dilewati saat import:</div>
  <ul class="mb-0 ps-18">
    @foreach (session('import_errors') as $err)
      <li>{{ $err }}</li>
    @endforeach
  </ul>
</div>
@endif

@php
  $stats = $stats ?? [
      'total_banned' => 0, 'sudah_unbanned' => 0, 'masih_banned' => 0,
      'total_pengajuan' => 0, 'pengajuan_pending' => 0, 'pengajuan_approved' => 0, 'pengajuan_rejected' => 0,
  ];
@endphp

<div class="row gy-4 mb-24">
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-1">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="mb-0 w-48-px h-48-px bg-primary-600 flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
            <iconify-icon icon="solar:forbidden-circle-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="mb-2 fw-medium text-secondary-light text-sm">Total Karyawan Harus di Banned</span>
            <h6 class="fw-semibold mb-0">{{ number_format($stats['total_banned']) }}</h6>
          </div>
        </div>
        <p class="text-sm mb-0">Seluruh record di master roster banned</p>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-3">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="mb-0 w-48-px h-48-px bg-warning-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
            <iconify-icon icon="solar:shield-warning-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="mb-2 fw-medium text-secondary-light text-sm">Masih Banned</span>
            <h6 class="fw-semibold mb-0">{{ number_format($stats['masih_banned']) }}</h6>
          </div>
        </div>
        <p class="text-sm mb-0">Log automation SUCCESS</p>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-2">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="mb-0 w-48-px h-48-px bg-success-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
            <iconify-icon icon="solar:shield-check-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="mb-2 fw-medium text-secondary-light text-sm">Sudah Unbanned</span>
            <h6 class="fw-semibold mb-0">{{ number_format($stats['sudah_unbanned']) }}</h6>
          </div>
        </div>
        <p class="text-sm mb-0">Bukti treatment sudah di-approve admin</p>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-4">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="mb-0 w-48-px h-48-px bg-info-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
            <iconify-icon icon="solar:clipboard-check-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="mb-2 fw-medium text-secondary-light text-sm">Total Pengajuan Treatment</span>
            <h6 class="fw-semibold mb-0">{{ number_format($stats['total_pengajuan']) }}</h6>
          </div>
        </div>
        <p class="text-sm mb-0">
          <span class="text-warning-main fw-medium">{{ number_format($stats['pengajuan_pending']) }} pending</span> &middot;
          <span class="text-success-main fw-medium">{{ number_format($stats['pengajuan_approved']) }} approved</span> &middot;
          <span class="text-danger-main fw-medium">{{ number_format($stats['pengajuan_rejected']) }} rejected</span>
        </p>
      </div>
    </div>
  </div>
</div>

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="text-lg fw-semibold mb-4">Daftar Karyawan Banned</h6>
        <p class="text-sm text-secondary-light mb-0">NIK, SID, nama, perusahaan, site, dan alasan pelanggaran.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('pra-operasi.roster-banned.treatment.index') }}" class="btn btn-sm btn-outline-warning-600 d-inline-flex align-items-center gap-1">
          <iconify-icon icon="solar:clipboard-check-outline" class="icon"></iconify-icon>
          Review Bukti Treatment
        </a>
        <a href="{{ route('pra-operasi.roster-banned.export') }}" id="rbExportBtn" class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
          <iconify-icon icon="solar:download-minimalistic-bold" class="icon"></iconify-icon>
          Download Excel
        </a>
        <a href="{{ route('pra-operasi.roster-banned.import-form') }}" class="btn btn-sm btn-outline-success-600 d-inline-flex align-items-center gap-1">
          <iconify-icon icon="solar:upload-bold" class="icon"></iconify-icon>
          Upload Excel
        </a>
        <a href="{{ route('pra-operasi.roster-banned.download-template') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
          <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
          Template
        </a>
        <a href="{{ route('pra-operasi.roster-banned.create') }}" class="btn btn-sm btn-primary-600 d-inline-flex align-items-center gap-1">
          <iconify-icon icon="solar:add-circle-bold" class="icon"></iconify-icon>
          Tambah
        </a>
      </div>
    </div>
  </div>
  <div class="card-body p-24">
    <div class="d-flex flex-wrap align-items-end gap-3 mb-8">
      <div>
        <label for="rbFilterBanned" class="form-label text-sm fw-medium mb-4">Status Banned</label>
        <select id="rbFilterBanned" class="form-select form-select-sm">
          <option value="">Semua</option>
          <option value="BANNED">Sudah Banned</option>
          <option value="BELUM">Belum Banned</option>
          <option value="UNBANNED">Sudah Unbanned</option>
        </select>
      </div>
      <div>
        <label for="rbFilterLog" class="form-label text-sm fw-medium mb-4">Status Log Banned</label>
        <select id="rbFilterLog" class="form-select form-select-sm">
          <option value="">Semua</option>
          <option value="SUCCESS">Sukses</option>
          <option value="FAILED">Gagal / Error</option>
          <option value="SKIPPED">Dilewati</option>
          <option value="PROCESSING">Sedang Proses</option>
          <option value="NONE">Belum Ada Log</option>
        </select>
      </div>
    </div>
    <div class="table-responsive">
      <table id="rbTable" class="table bordered-table mb-0 w-100">
        <thead>
          <tr>
            <th>No</th>
            <th>NIK</th>
            <th>SID</th>
            <th>Nama</th>
            <th>Perusahaan</th>
            <th>Site</th>
            <th>Alasan Pelanggaran</th>
            <th>Tanggal Pelanggaran</th>
            <th>Status Banned</th>
            <th>Status Log Banned</th>
            <th>Dibuat Oleh</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@section('page-scripts')
<script src="{{ asset('evaluasi-well-assets/js/lib/dataTables.min.js') }}"></script>
<script>
(function () {
    if (typeof DataTable === 'undefined') {
        return;
    }

    var filterBanned = document.getElementById('rbFilterBanned');
    var filterLog = document.getElementById('rbFilterLog');
    var exportBtn = document.getElementById('rbExportBtn');
    var exportBase = @json(route('pra-operasi.roster-banned.export'));

    var table = new DataTable('#rbTable', {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[3, 'asc']],
        autoWidth: false,
        ajax: {
            url: @json(route('pra-operasi.roster-banned.data')),
            data: function (d) {
                d.status_banned = filterBanned.value;
                d.status_log = filterLog.value;
            },
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'nik' },
            { data: 'sid' },
            { data: 'nama' },
            { data: 'perusahaan' },
            { data: 'site_dedicated' },
            { data: 'alasan_pelanggaran', className: 'rb-col-alasan' },
            { data: 'tanggal_pelanggaran' },
            { data: 'status_banned', orderable: false, searchable: false },
            { data: 'status_log', searchable: false, className: 'rb-col-log' },
            { data: 'created_by' },
            { data: 'aksi', orderable: false, searchable: false },
        ],
        language: {
            processing: 'Memuat...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(difilter dari _MAX_ total data)',
            zeroRecords: 'Tidak ada data karyawan banned.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' },
        },
    });

    [filterBanned, filterLog].forEach(function (el) {
        el.addEventListener('change', function () { table.ajax.reload(); });
    });

    // Export ikut pencarian + filter yang sedang aktif di tabel.
    exportBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var params = new URLSearchParams({
            search: table.search() || '',
            status_banned: filterBanned.value,
            status_log: filterLog.value,
        });
        window.location.href = exportBase + '?' + params.toString();
    });
})();
</script>
@endsection
