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
            <th>No. WA</th>
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

<div class="modal fade" id="rteRfidModal" tabindex="-1" aria-labelledby="rteRfidModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content radius-8 border-0 shadow-lg">
      <div class="modal-header border-bottom py-16 px-24">
        <div class="min-w-0 pe-12">
          <h5 class="modal-title fw-bold text-lg mb-4" id="rteRfidModalLabel">Cek RFID Selama Periode Cuti</h5>
          <p id="rte-rfid-subtitle" class="text-sm text-secondary-light mb-0"></p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24">
        <div id="rte-rfid-loading" class="text-center py-32">
          <div class="spinner-border text-primary-600" role="status" aria-hidden="true"></div>
          <p class="text-sm text-secondary-light mt-12 mb-0">Memuat data RFID…</p>
        </div>
        <div id="rte-rfid-message" class="d-none border radius-8 p-20 text-center bg-neutral-50">
          <iconify-icon icon="solar:danger-triangle-bold" class="text-warning-main text-3xl mb-8"></iconify-icon>
          <p id="rte-rfid-message-text" class="text-secondary-light text-sm mb-0"></p>
        </div>
        <div id="rte-rfid-content" class="d-none">
          <div class="row g-3 mb-20">
            <div class="col-4">
              <div class="border radius-8 p-16 text-center h-100">
                <div class="text-secondary-light text-xs text-uppercase fw-semibold mb-4">Total Hari</div>
                <div class="text-xl fw-bold" id="rte-rfid-total">0</div>
              </div>
            </div>
            <div class="col-4">
              <div class="border border-danger-100 bg-danger-100 radius-8 p-16 text-center h-100">
                <div class="text-danger-600 text-xs text-uppercase fw-semibold mb-4">Ada RFID</div>
                <div class="text-xl fw-bold text-danger-600" id="rte-rfid-with">0</div>
              </div>
            </div>
            <div class="col-4">
              <div class="border border-success-100 bg-success-100 radius-8 p-16 text-center h-100">
                <div class="text-success-600 text-xs text-uppercase fw-semibold mb-4">Tidak Ada RFID</div>
                <div class="text-xl fw-bold text-success-600" id="rte-rfid-without">0</div>
              </div>
            </div>
          </div>
          <div id="rte-rfid-warning" class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-16 py-12 radius-8 mb-16 d-none">
            <iconify-icon icon="solar:danger-triangle-bold" class="align-middle me-1"></iconify-icon>
            Ada aktivitas RFID (scan gate) di site pada saat karyawan ini seharusnya sedang cuti — mohon ditindaklanjuti.
          </div>
          <div class="table-responsive border radius-8">
            <table class="table table-hover mb-0 align-middle">
              <thead class="bg-neutral-50">
                <tr>
                  <th>Tanggal</th>
                  <th>Status</th>
                  <th>Jam Check-in</th>
                  <th>Gate</th>
                </tr>
              </thead>
              <tbody id="rte-rfid-days"></tbody>
            </table>
          </div>
        </div>
      </div>
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
            { data: 'whatsapp' },
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

    var rfidModalEl = document.getElementById('rteRfidModal');
    var rfidModal = window.bootstrap ? new window.bootstrap.Modal(rfidModalEl) : null;
    var rfidLoading = document.getElementById('rte-rfid-loading');
    var rfidMessage = document.getElementById('rte-rfid-message');
    var rfidMessageText = document.getElementById('rte-rfid-message-text');
    var rfidContent = document.getElementById('rte-rfid-content');
    var rfidSubtitle = document.getElementById('rte-rfid-subtitle');

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function formatTanggalPendek(iso) {
        var d = new Date(iso + 'T00:00:00');
        var hari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'][d.getDay()];
        var bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][d.getMonth()];
        return hari + ', ' + d.getDate() + ' ' + bulan + ' ' + d.getFullYear();
    }

    function showRfidState(state) {
        rfidLoading.classList.toggle('d-none', state !== 'loading');
        rfidMessage.classList.toggle('d-none', state !== 'message');
        rfidContent.classList.toggle('d-none', state !== 'content');
    }

    function loadRfidDetail(url) {
        showRfidState('loading');
        rfidSubtitle.textContent = '';
        if (rfidModal) {
            rfidModal.show();
        }

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json.available) {
                    rfidMessageText.textContent = json.message || 'Data RFID tidak tersedia.';
                    showRfidState('message');
                    return;
                }

                rfidSubtitle.textContent = (json.nama || '-') + ' (' + json.sid + ') — Periode cuti: ' + (json.periode_cuti || '-');

                if (json.note) {
                    rfidMessageText.textContent = json.note;
                    showRfidState('message');
                    return;
                }

                document.getElementById('rte-rfid-total').textContent = json.summary.total_days;
                document.getElementById('rte-rfid-with').textContent = json.summary.days_with_rfid;
                document.getElementById('rte-rfid-without').textContent = json.summary.days_without_rfid;
                document.getElementById('rte-rfid-warning').classList.toggle('d-none', json.summary.days_with_rfid === 0);

                document.getElementById('rte-rfid-days').innerHTML = json.days.map(function (d) {
                    var badge = d.has_rfid
                        ? '<span class="badge bg-danger-focus text-danger-main">Ada RFID</span>'
                        : '<span class="badge bg-success-focus text-success-main">Tidak Ada</span>';
                    return '<tr><td>' + escapeHtml(formatTanggalPendek(d.date)) + '</td><td>' + badge + '</td>'
                        + '<td>' + (d.checked_in_at ? escapeHtml(d.checked_in_at) : '-') + '</td>'
                        + '<td>' + (d.gate ? escapeHtml(d.gate) : '-') + '</td></tr>';
                }).join('') || '<tr><td colspan="4" class="text-center text-secondary-light py-16">Belum ada hari untuk dicek.</td></tr>';

                showRfidState('content');
            })
            .catch(function () {
                rfidMessageText.textContent = 'Gagal menghubungi server. Coba lagi.';
                showRfidState('message');
            });
    }

    document.querySelector('#rteTable tbody').addEventListener('click', function (e) {
        var rfidBtn = e.target.closest('.rte-rfid-btn');
        if (rfidBtn) {
            loadRfidDetail(rfidBtn.dataset.rfidUrl);
            return;
        }

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
