@extends('dms.layouts.app')

@section('title', 'Review Bukti Treatment')

@section('css')
<link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/dataTables.min.css') }}">
<style>
  #rteTable { width: 100% !important; }
  #rteTable th, #rteTable td { vertical-align: middle; }
  #rteTable td.rte-col-catatan { white-space: normal; min-width: 200px; }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Review Bukti Treatment</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('pra-operasi.roster-banned.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Master Roster Banned
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Review Bukti Treatment</li>
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

<div class="alert alert-info bg-info-100 text-info-600 border-info-100 px-24 py-13 mb-24 radius-8">
  Link form publik untuk karyawan (bisa dibagikan lewat WA):<br>
  <code>{{ route('roster-treatment.public.form') }}</code>
</div>

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="text-lg fw-semibold mb-4">Pengajuan Bukti Treatment</h6>
        <p class="text-sm text-secondary-light mb-0">Approve/reject bukti yang diupload karyawan dari form publik.</p>
      </div>
      <div class="d-flex align-items-center gap-2">
        <label for="rte-status-filter" class="text-sm fw-medium mb-0">Status</label>
        <select id="rte-status-filter" class="form-select form-select-sm" style="width:auto;">
          <option value="">Semua</option>
          <option value="PENDING" selected>Pending</option>
          <option value="APPROVED">Approved</option>
          <option value="REJECTED">Rejected</option>
        </select>
      </div>
    </div>
  </div>
  <div class="card-body p-24">
    <div class="table-responsive">
      <table id="rteTable" class="table bordered-table mb-0 w-100">
        <thead>
          <tr>
            <th>No</th>
            <th>Diajukan</th>
            <th>NIK</th>
            <th>SID</th>
            <th>Nama</th>
            <th>Perusahaan</th>
            <th>Tgl Treatment</th>
            <th>Periode Cuti</th>
            <th>Status</th>
            <th>Pengaju</th>
            <th>Catatan</th>
            <th>Alasan Ditolak</th>
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

    var statusFilter = document.getElementById('rte-status-filter');

    var table = new DataTable('#rteTable', {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'desc']],
        autoWidth: false,
        ajax: {
            url: @json(route('pra-operasi.roster-banned.treatment.data')),
            data: function (d) {
                d.status = statusFilter.value;
            },
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'submitted_at' },
            { data: 'nik' },
            { data: 'sid' },
            { data: 'nama' },
            { data: 'perusahaan' },
            { data: 'tanggal_treatment' },
            { data: 'periode_cuti' },
            { data: 'approval_status' },
            { data: 'submitted_by' },
            { data: 'catatan', className: 'rte-col-catatan' },
            { data: 'rejection_reason', className: 'rte-col-catatan' },
            { data: 'aksi', orderable: false, searchable: false },
        ],
        language: {
            processing: 'Memuat...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(difilter dari _MAX_ total data)',
            zeroRecords: 'Tidak ada pengajuan bukti treatment.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' },
        },
    });

    statusFilter.addEventListener('change', function () {
        table.ajax.reload();
    });

    document.querySelector('#rteTable tbody').addEventListener('click', function (e) {
        var btn = e.target.closest('.rb-reject-btn');
        if (!btn) {
            return;
        }

        var reason = window.prompt('Alasan penolakan:');
        if (reason === null) {
            return;
        }
        reason = reason.trim();
        if (reason === '') {
            window.alert('Alasan penolakan wajib diisi.');
            return;
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = btn.dataset.reviewUrl;
        form.style.display = 'none';

        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = btn.dataset.csrf;
        form.appendChild(csrfInput);

        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'reject';
        form.appendChild(actionInput);

        var reasonInput = document.createElement('input');
        reasonInput.type = 'hidden';
        reasonInput.name = 'rejection_reason';
        reasonInput.value = reason;
        form.appendChild(reasonInput);

        document.body.appendChild(form);
        form.submit();
    });
})();
</script>
@endsection
