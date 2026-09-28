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

    new DataTable('#rbTable', {
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
})();
</script>
@endsection
