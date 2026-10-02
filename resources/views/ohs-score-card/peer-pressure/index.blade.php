@extends('ohs-score-card.layouts.app')

@section('title', 'Peer Pressure — beRecord')

@section('css')
<style>
  .bp-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.5;
    white-space: nowrap;
  }
  .bp-badge--ok    { background: #ECFDF5; color: #166534; border: 1px solid #BBF7D0; }
  .bp-badge--warn  { background: #FEFCE8; color: #854D0E; border: 1px solid #FDE68A; }
  .bp-badge--bad   { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
  .bp-badge--muted { background: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; }

  /* Deskripsi bisa sangat panjang (varchar 65535): dipotong, teks penuh di title. */
  .bp-desc {
    display: block;
    max-width: 320px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .bp-name { font-weight: 600; color: #0F172A; }
  .bp-sub  { font-size: 11px; color: #64748B; display: block; }

  #berecordTable { width: 100% !important; }
  #berecordTable th, #berecordTable td { vertical-align: middle; white-space: nowrap; }
  #berecordTable thead th { font-weight: 600; }

  #berecordTable_wrapper .dt-layout-row,
  .dt-container:has(#berecordTable) .dt-layout-row {
    display: flex; flex-wrap: wrap; align-items: center;
    justify-content: space-between; gap: 0.75rem; margin: 0.75rem 0;
  }
  #berecordTable_wrapper .dt-paging,
  .dt-container:has(#berecordTable) .dt-paging {
    display: flex; flex-wrap: wrap; align-items: center;
    justify-content: flex-end; gap: 0.375rem;
  }
  #berecordTable_wrapper .dt-paging .dt-paging-button,
  .dt-container:has(#berecordTable) .dt-paging .dt-paging-button {
    width: auto !important; min-width: 2rem; height: 2rem;
    padding: 0 0.625rem !important; display: inline-flex !important;
    align-items: center; justify-content: center;
    line-height: 1 !important; border-radius: 6px !important;
  }
  #berecordTable_wrapper .dt-search input,
  .dt-container:has(#berecordTable) .dt-search input {
    margin-left: 0.5rem; min-width: 220px; width: auto; display: inline-block;
  }
  #berecordTable_wrapper .dt-length select,
  .dt-container:has(#berecordTable) .dt-length select {
    margin: 0 0.375rem; width: auto; display: inline-block;
  }
  #berecordTable_wrapper .dt-length label,
  #berecordTable_wrapper .dt-search label,
  .dt-container:has(#berecordTable) .dt-length label,
  .dt-container:has(#berecordTable) .dt-search label {
    display: inline-flex; align-items: center; margin-bottom: 0;
    font-size: 0.875rem; font-weight: 500; color: var(--text-secondary-light);
  }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Peer Pressure — beRecord</h6>
    <div class="text-secondary-light text-sm mt-4">
      Sanksi &amp; pelanggaran HSE karyawan · sumber <code>bcsid.mv_berecord</code> (hse_automation)
    </div>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('ohs-score-card.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li class="text-secondary-light">/</li>
    <li class="fw-medium text-primary-600">Peer Pressure</li>
  </ul>
</div>

@unless ($connectionUp)
  <div class="alert alert-warning radius-8 d-flex align-items-start gap-2 mb-24">
    <iconify-icon icon="solar:danger-triangle-outline" class="text-xl flex-shrink-0 mt-1"></iconify-icon>
    <div>
      <strong>Database hse_automation tidak terjangkau.</strong>
      <div class="text-sm mt-1">
        Halaman tetap terbuka, tetapi tabel di bawah akan kosong sampai koneksi ke RDS
        (<code>pgsql_direct</code>) tersedia. Dari jaringan lokal biasanya perlu tunnel SSH aktif.
      </div>
    </div>
  </div>
@endunless

<div class="card radius-8 border">
  <div class="card-body p-24">

    <div class="table-responsive">
      <table class="table bordered-table mb-0" id="berecordTable">
        <thead>
          <tr>
            <th>Kode SID</th>
            <th>Nama Karyawan</th>
            <th>Perusahaan</th>
            <th>Site</th>
            <th>Jabatan</th>
            <th>Kategori</th>
            <th>Tipe</th>
            <th>Golden Rules</th>
            <th>Mulai</th>
            <th>Selesai</th>
            <th>Status</th>
            <th>Status Proses</th>
            <th>Permit</th>
            <th>Deskripsi</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

  </div>
</div>
@endsection

@section('page-scripts')
<script>
(function () {
    var tableEl = document.querySelector('#berecordTable');
    if (!tableEl || typeof DataTable === 'undefined') {
        return;
    }

    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = @json(route('ohs-score-card.peer-pressure.data'));

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('id-ID');
    }

    function plain(value) {
        return escapeHtml(value === null || value === undefined || value === '' ? '–' : value);
    }

    // Tanggal datang sebagai 'YYYY-MM-DD' (kolom date Postgres).
    function formatDate(value) {
        if (!value) {
            return '–';
        }
        var parts = String(value).slice(0, 10).split('-');
        if (parts.length !== 3) {
            return escapeHtml(value);
        }
        var bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        return parts[2] + ' ' + (bulan[Number(parts[1]) - 1] || parts[1]) + ' ' + parts[0];
    }

    function badge(value, cls) {
        if (value === null || value === undefined || value === '') {
            return '<span class="bp-badge bp-badge--muted">–</span>';
        }
        return '<span class="bp-badge ' + cls + '">' + escapeHtml(value) + '</span>';
    }

    // Sama dengan aturan di controller: "Not Banned" dikecualikan lebih dulu.
    function isBanned(tipe) {
        var t = String(tipe || '').toLowerCase();
        return t.indexOf('banned') !== -1 && t.indexOf('not banned') === -1;
    }

    var table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        pageLength: 25,
        lengthMenu: [25, 50, 100, 200],
        order: [[8, 'desc']],
        autoWidth: false,
        layout: {
            topStart: 'pageLength',
            topEnd: 'search',
            bottomStart: 'info',
            bottomEnd: 'paging'
        },
        ajax: {
            url: dataUrl,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            dataSrc: function (json) {
                return json.data || [];
            },
            error: function (xhr, error) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Peer Pressure: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'kode_sid', render: function (d) { return plain(d); } },
            {
                data: 'nama_karyawan',
                render: function (d, type, row) {
                    if (type !== 'display') { return d; }
                    return '<span class="bp-name">' + escapeHtml(d || '–') + '</span>'
                        + '<span class="bp-sub">' + escapeHtml(row.jabatan_struktural || '') + '</span>';
                }
            },
            { data: 'perusahaan', render: function (d) { return plain(d); } },
            { data: 'site', render: function (d) { return plain(d); } },
            { data: 'jabatan_fungsional', render: function (d) { return plain(d); } },
            { data: 'kategori_berecord', render: function (d) { return plain(d); } },
            {
                data: 'tipe_berecord',
                render: function (d, type) {
                    if (type !== 'display') { return d; }
                    return badge(d, isBanned(d) ? 'bp-badge--bad' : 'bp-badge--ok');
                }
            },
            { data: 'golden_rules', render: function (d) { return plain(d); } },
            { data: 'tanggal_mulai_berecord', render: function (d, type) { return type === 'display' ? formatDate(d) : (d || ''); } },
            { data: 'tanggal_selesai_berecord', render: function (d, type) { return type === 'display' ? formatDate(d) : (d || ''); } },
            {
                data: 'status_berecord',
                render: function (d, type) {
                    if (type !== 'display') { return d; }
                    return badge(d, d === 'Masih Berlaku' ? 'bp-badge--warn' : 'bp-badge--muted');
                }
            },
            { data: 'status_proses_berecord', render: function (d) { return plain(d); } },
            {
                data: 'status_permit',
                render: function (d, type) {
                    if (type !== 'display') { return d; }
                    return badge(d, d === 'PASSED' ? 'bp-badge--ok' : 'bp-badge--bad');
                }
            },
            {
                data: 'diskripsi',
                orderable: false,
                render: function (d, type) {
                    if (type !== 'display') { return d; }
                    if (!d) { return '–'; }
                    return '<span class="bp-desc" title="' + escapeHtml(d) + '">' + escapeHtml(d) + '</span>';
                }
            }
        ],
        language: {
            processing: 'Memuat...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ beRecord',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(difilter dari _MAX_ total beRecord)',
            zeroRecords: 'Tidak ada beRecord untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });
})();
</script>
@endsection
