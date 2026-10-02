@extends('ohs-score-card.layouts.app')

@section('title', 'Peer Pressure — beRecord')

@php
  /** Label dropdown filter: key = nama kolom di bcsid.mv_berecord. */
  $filterLabels = [
      'perusahaan' => 'Perusahaan',
      'kategori_berecord' => 'Kategori',
      'tipe_berecord' => 'Tipe',
      'golden_rules' => 'Golden Rules',
      'jabatan_fungsional' => 'Jabatan Fungsional',
      'status_berecord' => 'Status beRecord',
      'status_proses_berecord' => 'Status Proses',
      'status_permit' => 'Status Permit',
  ];
@endphp

@section('css')
<style>
  .bp-stat {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    background: #fff;
    padding: 16px 18px;
    height: 100%;
  }
  .bp-stat__label { font-size: 12px; font-weight: 500; color: #64748B; line-height: 1.25; }
  .bp-stat__value { font-size: 24px; font-weight: 800; color: #0F172A; line-height: 1.2; letter-spacing: -0.01em; }
  .bp-stat__meta  { font-size: 12px; color: #64748B; }
  .bp-stat__icon {
    width: 42px; height: 42px; border-radius: 999px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
  }

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

<div class="row gy-3 mb-24">
  <div class="col-xxl-3 col-sm-6">
    <div class="bp-stat d-flex align-items-center gap-3">
      <span class="bp-stat__icon" style="background:#EFF6FF;color:#2563EB;">
        <iconify-icon icon="solar:clipboard-list-outline"></iconify-icon>
      </span>
      <div class="min-w-0">
        <div class="bp-stat__label">Total beRecord</div>
        <div class="bp-stat__value" id="bp-total">{{ number_format($totalRecords) }}</div>
        <div class="bp-stat__meta" id="bp-total-meta">seluruh data</div>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="bp-stat d-flex align-items-center gap-3">
      <span class="bp-stat__icon" style="background:#FEFCE8;color:#CA8A04;">
        <iconify-icon icon="solar:clock-circle-outline"></iconify-icon>
      </span>
      <div class="min-w-0">
        <div class="bp-stat__label">Masih Berlaku</div>
        <div class="bp-stat__value" id="bp-berlaku">–</div>
        <div class="bp-stat__meta" id="bp-berlaku-meta">&nbsp;</div>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="bp-stat d-flex align-items-center gap-3">
      <span class="bp-stat__icon" style="background:#FEF2F2;color:#DC2626;">
        <iconify-icon icon="solar:forbidden-circle-outline"></iconify-icon>
      </span>
      <div class="min-w-0">
        <div class="bp-stat__label">Banned</div>
        <div class="bp-stat__value" id="bp-banned">–</div>
        <div class="bp-stat__meta" id="bp-banned-meta">&nbsp;</div>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="bp-stat d-flex align-items-center gap-3">
      <span class="bp-stat__icon" style="background:#FFF7ED;color:#EA580C;">
        <iconify-icon icon="solar:shield-cross-outline"></iconify-icon>
      </span>
      <div class="min-w-0">
        <div class="bp-stat__label">Permit NOT PASSED</div>
        <div class="bp-stat__value" id="bp-permit">–</div>
        <div class="bp-stat__meta" id="bp-permit-meta">&nbsp;</div>
      </div>
    </div>
  </div>
</div>

<div class="card radius-8 border">
  <div class="card-body p-24">

    <div class="row gy-3 gx-3 align-items-end mb-20">
      @foreach ($filterLabels as $column => $label)
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-4" for="bp-filter-{{ $column }}">{{ $label }}</label>
          <select class="form-select form-select-sm radius-8 bp-filter" id="bp-filter-{{ $column }}" data-column="{{ $column }}">
            <option value="">Semua</option>
            @foreach ($filterOptions[$column] ?? [] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
      @endforeach

      <div class="col-xxl-3 col-md-4 col-sm-6">
        <label class="form-label text-sm fw-medium mb-4" for="bp-filter-banned">Banned</label>
        <select class="form-select form-select-sm radius-8 bp-filter" id="bp-filter-banned" data-column="banned">
          <option value="">Semua</option>
          <option value="banned">Banned</option>
          <option value="not-banned">Tidak Banned</option>
        </select>
      </div>

      <div class="col-xxl-3 col-md-4 col-sm-6">
        <label class="form-label text-sm fw-medium mb-4" for="bp-filter-tanggal_dari">Mulai dari</label>
        <input type="date" class="form-control form-control-sm radius-8 bp-filter" id="bp-filter-tanggal_dari" data-column="tanggal_dari">
      </div>

      <div class="col-xxl-3 col-md-4 col-sm-6">
        <label class="form-label text-sm fw-medium mb-4" for="bp-filter-tanggal_sampai">Sampai</label>
        <input type="date" class="form-control form-control-sm radius-8 bp-filter" id="bp-filter-tanggal_sampai" data-column="tanggal_sampai">
      </div>

      <div class="col-12 d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-primary-600 radius-8" id="bp-apply">
          <iconify-icon icon="solar:filter-outline" class="icon"></iconify-icon> Terapkan
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary radius-8" id="bp-reset">Reset</button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table bordered-table mb-0" id="berecordTable">
        <thead>
          <tr>
            <th>Kode SID</th>
            <th>Nama Karyawan</th>
            <th>Perusahaan</th>
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
    var filterEls = Array.prototype.slice.call(document.querySelectorAll('.bp-filter'));

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

    function currentFilters() {
        var out = {};
        filterEls.forEach(function (el) {
            if (el.value) {
                out[el.dataset.column] = el.value;
            }
        });
        return out;
    }

    function updateSummary(summary) {
        if (!summary) {
            return;
        }
        document.querySelector('#bp-total').textContent = formatNumber(summary.total);
        document.querySelector('#bp-total-meta').textContent =
            Object.keys(currentFilters()).length ? 'sesuai filter' : 'seluruh data';

        [['berlaku', 'masih_berlaku', 'masih_berlaku_pct'],
         ['banned', 'banned', 'banned_pct'],
         ['permit', 'permit_gagal', 'permit_gagal_pct']].forEach(function (item) {
            document.querySelector('#bp-' + item[0]).textContent = formatNumber(summary[item[1]]);
            document.querySelector('#bp-' + item[0] + '-meta').textContent =
                Number(summary[item[2]] || 0).toLocaleString('id-ID', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                }) + '% dari ' + formatNumber(summary.total);
        });
    }

    var table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        pageLength: 25,
        lengthMenu: [25, 50, 100, 200],
        order: [[7, 'desc']],
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
            data: function (d) {
                var filters = currentFilters();
                Object.keys(filters).forEach(function (key) {
                    d[key] = filters[key];
                });
            },
            dataSrc: function (json) {
                updateSummary(json.summary);
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

    document.querySelector('#bp-apply').addEventListener('click', function () {
        table.ajax.reload();
    });

    document.querySelector('#bp-reset').addEventListener('click', function () {
        filterEls.forEach(function (el) { el.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    filterEls.forEach(function (el) {
        el.addEventListener('change', function () { table.ajax.reload(); });
    });
})();
</script>
@endsection
