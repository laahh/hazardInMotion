@extends('ohs-score-card.layouts.app')

@section('title', 'Jalan Sesuai Standar')

@php
  /** Label dropdown filter: key = nama kolom di road_summary. */
  $filterLabels = [
      'site' => 'Site',
      'mitra' => 'Mitra',
      'pit' => 'Pit',
      'year' => 'Tahun',
      'week' => 'Minggu',
      'grade_stat' => 'Grade',
      'road_width' => 'Lebar Jalan',
      'supereleva' => 'Superelevasi',
  ];
@endphp

@section('css')
<style>
  .rs-stat {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    background: #fff;
    padding: 16px 18px;
    height: 100%;
  }
  .rs-stat__label {
    font-size: 12px;
    font-weight: 500;
    color: #64748B;
    line-height: 1.25;
  }
  .rs-stat__value {
    font-size: 24px;
    font-weight: 800;
    color: #0F172A;
    line-height: 1.2;
    letter-spacing: -0.01em;
  }
  .rs-stat__meta {
    font-size: 12px;
    color: #64748B;
  }
  .rs-stat__icon {
    width: 42px;
    height: 42px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
  }

  /* Badge status: ACCEPT hijau, REJECT/>12% merah, WARNING kuning, OVERGRADE oranye. */
  .rs-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.5;
    white-space: nowrap;
  }
  .rs-badge--ok     { background: #ECFDF5; color: #166534; border: 1px solid #BBF7D0; }
  .rs-badge--warn   { background: #FEFCE8; color: #854D0E; border: 1px solid #FDE68A; }
  .rs-badge--over   { background: #FFF7ED; color: #9A3412; border: 1px solid #FED7AA; }
  .rs-badge--bad    { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
  .rs-badge--muted  { background: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; }

  /* Kesimpulan dibuat lebih tegas daripada badge status per-parameter. */
  .rs-verdict {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.01em;
    white-space: nowrap;
  }
  .rs-verdict--ok  { background: #16A34A; color: #fff; }
  .rs-verdict--bad { background: #E0484A; color: #fff; }

  #roadSummaryTable { width: 100% !important; }
  #roadSummaryTable th,
  #roadSummaryTable td { vertical-align: middle; white-space: nowrap; }
  #roadSummaryTable thead th { font-weight: 600; }

  #roadSummaryTable_wrapper .dt-layout-row,
  .dt-container:has(#roadSummaryTable) .dt-layout-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0.75rem 0;
  }
  #roadSummaryTable_wrapper .dt-paging,
  .dt-container:has(#roadSummaryTable) .dt-paging {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.375rem;
  }
  #roadSummaryTable_wrapper .dt-paging .dt-paging-button,
  .dt-container:has(#roadSummaryTable) .dt-paging .dt-paging-button {
    width: auto !important;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.625rem !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    line-height: 1 !important;
    border-radius: 6px !important;
  }
  #roadSummaryTable_wrapper .dt-search input,
  .dt-container:has(#roadSummaryTable) .dt-search input {
    margin-left: 0.5rem;
    min-width: 220px;
    width: auto;
    display: inline-block;
  }
  #roadSummaryTable_wrapper .dt-length select,
  .dt-container:has(#roadSummaryTable) .dt-length select {
    margin: 0 0.375rem;
    width: auto;
    display: inline-block;
  }
  #roadSummaryTable_wrapper .dt-length label,
  #roadSummaryTable_wrapper .dt-search label,
  .dt-container:has(#roadSummaryTable) .dt-length label,
  .dt-container:has(#roadSummaryTable) .dt-search label {
    display: inline-flex;
    align-items: center;
    margin-bottom: 0;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-secondary-light);
  }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Jalan Sesuai Standar</h6>
    <div class="text-secondary-light text-sm mt-4">
      Evaluasi per segmen jalan — grade, lebar, dan superelevasi
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
    <li class="fw-medium text-primary-600">Jalan Sesuai Standar</li>
  </ul>
</div>

{{-- Ringkasan kepatuhan; ikut filter yang sedang aktif (di-update dari respons AJAX). --}}
<div class="row gy-3 mb-24">
  <div class="col-xxl col-lg-4 col-sm-6">
    <div class="rs-stat d-flex align-items-center gap-3">
      <span class="rs-stat__icon" style="background:#EFF6FF;color:#2563EB;">
        <iconify-icon icon="solar:ruler-outline"></iconify-icon>
      </span>
      <div class="min-w-0">
        <div class="rs-stat__label">Total Segmen</div>
        <div class="rs-stat__value" id="rs-total">{{ number_format($totalSegments) }}</div>
        <div class="rs-stat__meta" id="rs-total-meta">seluruh data</div>
      </div>
    </div>
  </div>
  <div class="col-xxl col-lg-4 col-sm-6">
    <div class="rs-stat d-flex align-items-center gap-3" style="border-color:#BBF7D0;background:#F7FEF9;">
      <span class="rs-stat__icon" style="background:#16A34A;color:#fff;">
        <iconify-icon icon="solar:check-circle-outline"></iconify-icon>
      </span>
      <div class="min-w-0">
        <div class="rs-stat__label">Standar</div>
        <div class="rs-stat__value" id="rs-standar">–</div>
        <div class="rs-stat__meta" id="rs-standar-meta">&nbsp;</div>
      </div>
    </div>
  </div>
  <div class="col-xxl col-lg-4 col-sm-6">
    <div class="rs-stat d-flex align-items-center gap-3">
      <span class="rs-stat__icon" style="background:#ECFDF5;color:#16A34A;">
        <iconify-icon icon="solar:graph-up-outline"></iconify-icon>
      </span>
      <div class="min-w-0">
        <div class="rs-stat__label">Grade ACCEPT</div>
        <div class="rs-stat__value" id="rs-grade">–</div>
        <div class="rs-stat__meta" id="rs-grade-meta">&nbsp;</div>
      </div>
    </div>
  </div>
  <div class="col-xxl col-lg-4 col-sm-6">
    <div class="rs-stat d-flex align-items-center gap-3">
      <span class="rs-stat__icon" style="background:#ECFDF5;color:#16A34A;">
        <iconify-icon icon="solar:arrows-horizontal-outline"></iconify-icon>
      </span>
      <div class="min-w-0">
        <div class="rs-stat__label">Lebar Jalan ACCEPT</div>
        <div class="rs-stat__value" id="rs-width">–</div>
        <div class="rs-stat__meta" id="rs-width-meta">&nbsp;</div>
      </div>
    </div>
  </div>
  <div class="col-xxl col-lg-4 col-sm-6">
    <div class="rs-stat d-flex align-items-center gap-3">
      <span class="rs-stat__icon" style="background:#ECFDF5;color:#16A34A;">
        <iconify-icon icon="solar:slider-horizontal-outline"></iconify-icon>
      </span>
      <div class="min-w-0">
        <div class="rs-stat__label">Superelevasi ACCEPT</div>
        <div class="rs-stat__value" id="rs-super">–</div>
        <div class="rs-stat__meta" id="rs-super-meta">&nbsp;</div>
      </div>
    </div>
  </div>
</div>

<div class="card radius-8 border">
  <div class="card-body p-24">

    <div class="row gy-3 gx-3 align-items-end mb-20">
      @foreach ($filterLabels as $column => $label)
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-4" for="rs-filter-{{ $column }}">{{ $label }}</label>
          <select class="form-select form-select-sm radius-8 rs-filter" id="rs-filter-{{ $column }}" data-column="{{ $column }}">
            <option value="">Semua</option>
            @foreach ($filterOptions[$column] ?? [] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
      @endforeach

      <div class="col-xxl-3 col-md-4 col-sm-6">
        <label class="form-label text-sm fw-medium mb-4" for="rs-filter-kesimpulan">Kesimpulan</label>
        <select class="form-select form-select-sm radius-8 rs-filter" id="rs-filter-kesimpulan" data-column="kesimpulan">
          <option value="">Semua</option>
          <option value="standar">Standar</option>
          <option value="tidak-standar">Tidak Standar</option>
        </select>
      </div>

      <div class="col-xxl-12 d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-primary-600 radius-8" id="rs-apply">
          <iconify-icon icon="solar:filter-outline" class="icon"></iconify-icon> Terapkan
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary radius-8" id="rs-reset">
          Reset
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table bordered-table mb-0" id="roadSummaryTable">
        <thead>
          <tr>
            <th>Site</th>
            <th>Pit</th>
            <th>Mitra</th>
            <th>Tahun</th>
            <th>Minggu</th>
            <th>Nama Jalan</th>
            <th>Segmen</th>
            <th>Grade</th>
            <th>Lebar Jalan</th>
            <th>Superelevasi</th>
            <th>Junction 1</th>
            <th>Junction S</th>
            <th>Kesimpulan</th>
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
    var tableEl = document.querySelector('#roadSummaryTable');
    if (!tableEl || typeof DataTable === 'undefined') {
        return;
    }

    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = @json(route('ohs-score-card.jalan-sesuai-standar.data'));
    var filterEls = Array.prototype.slice.call(document.querySelectorAll('.rs-filter'));

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

    // Peta status -> kelas badge. Nilai tak dikenal jatuh ke 'muted'.
    var badgeClass = {
        'ACCEPT': 'rs-badge--ok',
        'WARNING': 'rs-badge--warn',
        'OVERGRADE': 'rs-badge--over',
        'REJECT': 'rs-badge--bad',
        '>12%': 'rs-badge--bad'
    };

    function renderBadge(value) {
        var text = (value === null || value === undefined || value === '') ? '–' : String(value);
        var cls = badgeClass[text] || 'rs-badge--muted';
        return '<span class="rs-badge ' + cls + '">' + escapeHtml(text) + '</span>';
    }

    function plain(value) {
        return escapeHtml(value === null || value === undefined || value === '' ? '–' : value);
    }

    // Junction hanya ikut dinilai bila segmen itu memang titik pertemuan;
    // '-' / kosong berarti tidak berlaku, jadi tidak dihitung.
    function junctionApplies(value) {
        return value !== null && value !== undefined && value !== '' && value !== '-';
    }

    function renderVerdict(isStandar, row) {
        var checked = ['grade_stat', 'road_width', 'supereleva'];
        if (junctionApplies(row.junction_1)) { checked.push('junction_1'); }
        if (junctionApplies(row.junction_s)) { checked.push('junction_s'); }

        var failed = checked.filter(function (key) { return row[key] !== 'ACCEPT'; });

        var tip = isStandar
            ? checked.length + ' parameter dinilai, semuanya ACCEPT'
            : checked.length + ' parameter dinilai, tidak ACCEPT: ' + failed.join(', ');

        return '<span class="rs-verdict ' + (isStandar ? 'rs-verdict--ok' : 'rs-verdict--bad') + '"'
            + ' title="' + escapeHtml(tip) + '">'
            + '<iconify-icon icon="' + (isStandar ? 'mdi:check-circle' : 'mdi:close-circle') + '"></iconify-icon>'
            + (isStandar ? 'STANDAR' : 'TIDAK STANDAR')
            + '</span>';
    }

    function currentFilters() {
        var out = {};
        filterEls.forEach(function (el) {
            var value = el.value;
            if (value) {
                out[el.dataset.column] = value;
            }
        });
        return out;
    }

    function updateSummary(summary) {
        if (!summary) {
            return;
        }
        document.querySelector('#rs-total').textContent = formatNumber(summary.total);
        document.querySelector('#rs-total-meta').textContent =
            Object.keys(currentFilters()).length ? 'sesuai filter' : 'seluruh data';

        [['standar', 'standar_pct', 'standar_ok'],
         ['grade', 'grade_pct', 'grade_ok'],
         ['width', 'width_pct', 'width_ok'],
         ['super', 'super_pct', 'super_ok']].forEach(function (item) {
            var pct = Number(summary[item[1]] || 0);
            document.querySelector('#rs-' + item[0]).textContent =
                pct.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
            document.querySelector('#rs-' + item[0] + '-meta').textContent =
                formatNumber(summary[item[2]]) + ' dari ' + formatNumber(summary.total) + ' segmen';
        });
    }

    var table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        pageLength: 25,
        lengthMenu: [25, 50, 100, 200],
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
                    console.error('Jalan Sesuai Standar: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'site', render: function (d) { return plain(d); } },
            { data: 'pit', render: function (d) { return plain(d); } },
            { data: 'mitra', render: function (d) { return plain(d); } },
            { data: 'year', render: function (d) { return plain(d); } },
            { data: 'week', render: function (d) { return plain(d); } },
            { data: 'nama_jalan', render: function (d) { return plain(d); } },
            { data: 'segment', className: 'text-center', render: function (d) { return plain(d); } },
            { data: 'grade_stat', className: 'text-center', render: function (d) { return renderBadge(d); } },
            { data: 'road_width', className: 'text-center', render: function (d) { return renderBadge(d); } },
            { data: 'supereleva', className: 'text-center', render: function (d) { return renderBadge(d); } },
            { data: 'junction_1', render: function (d) { return plain(d); } },
            { data: 'junction_s', render: function (d) { return plain(d); } },
            {
                data: 'is_standar',
                className: 'text-center',
                render: function (d, type, row) {
                    if (type !== 'display') {
                        return d ? 1 : 0;
                    }
                    return renderVerdict(d, row);
                }
            }
        ],
        language: {
            processing: 'Memuat...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ segmen',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(difilter dari _MAX_ total segmen)',
            zeroRecords: 'Tidak ada segmen untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    document.querySelector('#rs-apply').addEventListener('click', function () {
        table.ajax.reload();
    });

    document.querySelector('#rs-reset').addEventListener('click', function () {
        filterEls.forEach(function (el) { el.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    // Ganti dropdown langsung memuat ulang — tombol Terapkan tetap ada
    // untuk yang terbiasa menekannya setelah mengubah beberapa filter.
    filterEls.forEach(function (el) {
        el.addEventListener('change', function () { table.ajax.reload(); });
    });
})();
</script>
@endsection
