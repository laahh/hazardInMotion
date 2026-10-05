@extends('ohs-score-card.layouts.app')

@section('title', 'Master Data Incident')

@section('css')
<style>
  /* Hanya yang tidak disediakan tema: pemotongan teks panjang dan chip angka. */
  .imm-page .imm-potong {
    display: inline-block; max-width: 420px; vertical-align: bottom;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
  .imm-page .imm-potong--sempit { max-width: 180px; }
</style>
@endsection

@section('content')
<div class="imm-page"
     data-url="{{ route('ohs-score-card.incident-management.master-data.data') }}"
     data-export-url="{{ route('ohs-score-card.incident-management.master-data.export') }}">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
    <div>
      <h6 class="fw-semibold mb-0">Master Data Incident</h6>
      <div class="text-secondary-light text-sm mt-4">
        Baris mentah <code>{{ $tabel }}</code> — tanpa penyaringan, apa adanya dari OBDS
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
      <li class="fw-medium">
        <a href="{{ route('ohs-score-card.incident-management.dashboard.index') }}" class="hover-text-primary">
          Incident Management
        </a>
      </li>
      <li class="text-secondary-light">/</li>
      <li class="fw-medium text-primary-600">Master Data</li>
    </ul>
  </div>

  @unless ($tersambung)
    <div class="alert alert-danger bg-danger-focus border-danger-main text-danger-main
                radius-8 px-20 py-16 mb-24 d-flex align-items-start gap-3">
      <iconify-icon icon="solar:danger-triangle-outline" class="icon text-xxl flex-shrink-0"></iconify-icon>
      <div>
        <h6 class="text-md fw-semibold mb-4 text-danger-main">OBDS tidak terjangkau</h6>
        <p class="text-sm mb-0">
          Pilihan filter tidak bisa dimuat dan tabel di bawah akan kosong. Dari jaringan lokal
          database OLAP memang tidak terjangkau; halaman ini butuh dijalankan dari server.
        </p>
      </div>
    </div>
  @endunless

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Daftar Insiden</h6>
        <span class="text-sm text-secondary-light">
          Satu baris per catatan di sumber, termasuk yang berstatus DELETED dan yang belum
          diinvestigasi — pakai filter Status untuk menyempitkannya
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-imm="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-imm="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-imm="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="imm-tahun">Tahun</label>
          <select class="form-select form-select-sm radius-8 imm-filter" id="imm-tahun" data-column="tahun">
            <option value="">Semua Tahun</option>
            @foreach ($pilihan['tahun'] as $t)
              <option value="{{ $t }}">{{ $t }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="imm-site">Site</label>
          <select class="form-select form-select-sm radius-8 imm-filter" id="imm-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($pilihan['site'] as $s)
              <option value="{{ $s }}">{{ $s }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="imm-jenis">Jenis Insiden</label>
          <select class="form-select form-select-sm radius-8 imm-filter" id="imm-jenis" data-column="jenis">
            <option value="">Semua Jenis</option>
            @foreach ($pilihan['jenis'] as $j)
              <option value="{{ $j }}">{{ $j }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="imm-kategori">Kategori</label>
          <select class="form-select form-select-sm radius-8 imm-filter" id="imm-kategori" data-column="kategori">
            <option value="">Semua Kategori</option>
            @foreach ($pilihan['kategori'] as $k)
              <option value="{{ $k }}">{{ $k }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="imm-status">Status</label>
          <select class="form-select form-select-sm radius-8 imm-filter" id="imm-status" data-column="status">
            <option value="">Semua Status</option>
            @foreach ($pilihan['status'] as $s)
              <option value="{{ $s }}">{{ $s }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-imm="reset">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-imm="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Tanggal</th>
              <th>Site</th>
              <th>Lokasi</th>
              <th>Perusahaan</th>
              <th>Jenis</th>
              <th>Kategori</th>
              <th>Status</th>
              <th class="text-end">Temuan</th>
              <th class="text-end">CAR</th>
              <th>Kronologi</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection

@section('page-scripts')
<script>
// ---- Master Data Incident ---------------------------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.imm-page');
    if (!root) { return; }

    var tableEl = root.querySelector('[data-imm="table"]');
    if (!tableEl || typeof DataTable === 'undefined') { return; }
    if (DataTable.ext) { DataTable.ext.errMode = 'none'; }

    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.imm-filter'));
    var hintEl = root.querySelector('[data-imm="hint"]');

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmt(v) { return Number(v || 0).toLocaleString('id-ID'); }

    function filterSaatIni() {
        var out = {};
        filterEls.forEach(function (n) { if (n.value) { out[n.dataset.column] = n.value; } });
        return out;
    }

    /** Badge status mengikuti sistem warna WowDash, bukan warna inline. */
    function kelasStatus(v) {
        return ({
            'INVESTIGASI': 'bg-success-focus text-success-main',
            'TIDAK INVESTIGASI': 'bg-neutral-200 text-secondary-light',
            'INSIDEN BARU': 'bg-warning-focus text-warning-main',
            'DELETED': 'bg-danger-focus text-danger-main'
        })[v] || 'bg-neutral-200 text-secondary-light';
    }

    var table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        order: [[1, 'desc']],
        pageLength: 25,
        lengthMenu: [25, 50, 100, 200],
        autoWidth: false,
        layout: {
            topStart: 'pageLength',
            topEnd: 'search',
            bottomStart: 'info',
            bottomEnd: 'paging'
        },
        ajax: {
            url: root.dataset.url,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            data: function (d) {
                var f = filterSaatIni();
                Object.keys(f).forEach(function (k) { d[k] = f[k]; });
            },
            dataSrc: function (json) {
                hintEl.textContent = json.error
                    ? 'gagal memuat'
                    : fmt(json.recordsFiltered) + ' dari ' + fmt(json.recordsTotal) + ' baris';
                return json.data || [];
            },
            error: function (xhr, error) {
                hintEl.textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Master Data Incident: gagal memuat', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            {
                data: 'id',
                render: function (d, type, row) {
                    if (type !== 'display') { return d === null ? -1 : d; }
                    return (d === null
                        ? '<span class="text-secondary-light">belum ada</span>'
                        : '<span class="fw-semibold">#' + d + '</span>')
                        + '<span class="text-xs text-secondary-light d-block">CCR ' + esc(row.id_ccr) + '</span>';
                }
            },
            { data: 'tanggal' },
            { data: 'site' },
            {
                data: 'lokasi',
                orderable: true,
                render: function (d, type, row) {
                    if (type !== 'display') { return d; }
                    return '<span class="imm-potong imm-potong--sempit" title="' + esc(d) + '">' + esc(d) + '</span>'
                        + (row.detil_lokasi && row.detil_lokasi !== '-'
                            ? '<span class="text-xs text-secondary-light d-block imm-potong imm-potong--sempit" title="'
                                + esc(row.detil_lokasi) + '">' + esc(row.detil_lokasi) + '</span>'
                            : '');
                }
            },
            {
                data: 'perusahaan',
                render: function (d, type, row) {
                    if (type !== 'display') { return d; }
                    return '<span class="imm-potong imm-potong--sempit" title="' + esc(d) + '">' + esc(d) + '</span>'
                        + (row.pja_bc && row.pja_bc !== '-'
                            ? '<span class="text-xs text-secondary-light d-block">PJA ' + esc(row.pja_bc) + '</span>'
                            : '');
                }
            },
            { data: 'jenis' },
            { data: 'kategori' },
            {
                data: 'status',
                render: function (d, type, row) {
                    if (type !== 'display') { return d; }
                    return '<span class="' + kelasStatus(d) + ' px-8 py-2 rounded-pill fw-medium text-xs">'
                        + esc(d) + '</span>'
                        + (row.status_lpi && row.status_lpi !== '-'
                            ? '<span class="text-xs text-secondary-light d-block">LPI ' + esc(row.status_lpi) + '</span>'
                            : '');
                }
            },
            {
                data: 'n_temuan',
                className: 'text-end',
                render: function (d, type) {
                    if (type !== 'display') { return d; }
                    return d
                        ? '<span class="fw-semibold">' + fmt(d) + '</span>'
                        : '<span class="text-secondary-light">0</span>';
                }
            },
            {
                data: 'n_car',
                className: 'text-end',
                render: function (d, type) {
                    if (type !== 'display') { return d; }
                    return d
                        ? '<span class="fw-semibold">' + fmt(d) + '</span>'
                        : '<span class="text-secondary-light">0</span>';
                }
            },
            {
                data: 'kronologi',
                orderable: false,
                render: function (d, type) {
                    if (type !== 'display') { return d; }
                    if (!d) { return '<span class="text-secondary-light">–</span>'; }
                    return '<span class="imm-potong text-sm text-secondary-light" title="' + esc(d) + '">'
                        + esc(d) + '</span>';
                }
            }
        ],
        language: {
            processing: 'Memuat…',
            lengthMenu: 'Tampilkan _MENU_ baris',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
            infoEmpty: 'Tidak ada baris',
            infoFiltered: '(disaring dari _MAX_ baris)',
            search: 'Cari:',
            zeroRecords: 'Tidak ada baris untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    filterEls.forEach(function (n) {
        n.addEventListener('change', function () { table.ajax.reload(); });
    });

    root.querySelector('[data-imm="reset"]').addEventListener('click', function () {
        filterEls.forEach(function (n) { n.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    root.querySelectorAll('[data-imm="export"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var params = new URLSearchParams(filterSaatIni());
            var cari = table.search();
            if (cari) { params.set('search', cari); }
            params.set('format', btn.dataset.format);
            window.location.href = root.dataset.exportUrl + '?' + params.toString();
        });
    });
})();
</script>
@endsection
