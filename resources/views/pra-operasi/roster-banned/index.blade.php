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
  #rbTable td.rb-col-karyawan { white-space: normal; min-width: 220px; }
  #rbTable .rb-karyawan-nama { font-weight: 600; color: var(--text-primary-light, #0F172A); }
  #rbTable .rb-karyawan-id { display: block; font-size: 12px; color: var(--text-secondary-light, #64748B); margin-top: 2px; }
  #rbTable td.rb-col-alasan { white-space: normal; min-width: 220px; }
  #rbTable td.rb-col-log { white-space: normal; min-width: 180px; }
  #rbTable .rb-log-error { max-width: 260px; word-break: break-word; }

  /* Ringkasan: total + per site. Merah = sudah banned, hijau = sudah unbanned, kuning = belum banned. */
  .rb-kpi { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
  .rb-kpi__kartu { display: flex; gap: 14px; align-items: flex-start; width: 100%; text-align: left; background: #fff; border: 1px solid #EEF2F7; border-radius: 12px; padding: 18px 20px; color: inherit; transition: border-color .15s ease, box-shadow .15s ease; }
  .rb-kpi__kartu:hover, .rb-kpi__kartu.is-aktif { border-color: #C7D2FE; box-shadow: 0 4px 14px rgba(79, 70, 229, .08); }
  .rb-kpi__kartu:focus-visible { outline: 2px solid #6366F1; outline-offset: 2px; }
  .rb-kpi__ikon { width: 40px; height: 40px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; flex: none; }
  .rb-kpi__label { font-size: 13px; font-weight: 600; color: #475569; }
  .rb-kpi__nilai { font-size: 26px; font-weight: 700; color: #0F172A; line-height: 1.2; font-variant-numeric: tabular-nums; }
  .rb-kpi__sub { font-size: 12px; color: #64748B; margin-top: 2px; }
  .rb-i-indigo { background: #EEF2FF; color: #4F46E5; }
  .rb-i-merah { background: #FEF2F2; color: #DC2626; }
  .rb-i-hijau { background: #ECFDF5; color: #059669; }
  .rb-i-kuning { background: #FFFBEB; color: #D97706; }
  .rb-site { width: 100%; }
  .rb-site th, .rb-site td { font-size: 13px; vertical-align: middle; }
  .rb-site tbody tr { cursor: pointer; }
  .rb-site tbody tr:hover { background: #F8FAFC; }
  .rb-site tbody tr.is-aktif { background: #EEF2FF; }
  .rb-site tbody tr:focus-visible { outline: 2px solid #6366F1; outline-offset: -2px; }
  .rb-site .rb-angka { font-variant-numeric: tabular-nums; }
  .rb-bar { display: flex; gap: 2px; height: 10px; min-width: 160px; background: #F1F5F9; border-radius: 4px; overflow: hidden; }
  .rb-bar > span { display: block; height: 100%; }
  .rb-w-banned { background: #EF4444; }
  .rb-w-unbanned { background: #10B981; }
  .rb-w-belum { background: #F59E0B; }
  .rb-legenda { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: #475569; }
  .rb-legenda i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }
  @media (max-width: 767.98px) { .rb-kpi { grid-template-columns: 1fr; } }
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

@if ($ringkasan !== null)
@php
  $angka = static fn (int $n): string => number_format($n, 0, ',', '.');
  $persen = static fn (int $n, int $t): string => $t > 0 ? number_format($n / $t * 100, 1, ',', '.') : '0';
  $t = $ringkasan['total'];
  $kpi = [
      // Total banned = pernah di-banned (masih banned + sudah unbanned); tidak ada filter tunggalnya.
      ['', 'Total banned', $t['banned'] + $t['unbanned'], 'rb-i-kuning', 'solar:shield-warning-bold', $angka($t['belum']).' belum diproses banned'],
      ['UNBANNED', 'Sudah unbanned', $t['unbanned'], 'rb-i-hijau', 'solar:check-circle-bold', $persen($t['unbanned'], $t['banned'] + $t['unbanned']).'% dari total banned'],
      ['BANNED', 'Masih banned', $t['banned'], 'rb-i-merah', 'solar:forbidden-circle-bold', $persen($t['banned'], $t['banned'] + $t['unbanned']).'% dari total banned · saat ini'],
  ];
@endphp
{{-- Kartu & baris site memfilter tabel di bawah (skrip di page-scripts). --}}
<div class="rb-kpi">
  @foreach ($kpi as [$kode, $label, $nilai, $warna, $ikon, $sub])
    <button type="button" class="rb-kpi__kartu" data-rb-status="{{ $kode }}" aria-pressed="false">
      <span class="rb-kpi__ikon {{ $warna }}"><iconify-icon icon="{{ $ikon }}"></iconify-icon></span>
      <span>
        <span class="rb-kpi__label d-block">{{ $label }}</span>
        <span class="rb-kpi__nilai d-block">{{ $angka($nilai) }}</span>
        <span class="rb-kpi__sub d-block">{{ $sub }}</span>
      </span>
    </button>
  @endforeach
</div>

<div class="card radius-8 border-0 shadow-sm mb-24">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="text-lg fw-semibold mb-4">Rekap per Site</h6>
        <p class="text-sm text-secondary-light mb-0">Klik baris site untuk menyaring daftar karyawan di bawah.</p>
      </div>
      <div class="rb-legenda">
        <span><i class="rb-w-banned"></i>Masih banned</span>
        <span><i class="rb-w-unbanned"></i>Sudah unbanned</span>
        <span><i class="rb-w-belum"></i>Belum banned</span>
      </div>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table rb-site mb-0">
        <thead>
          <tr>
            <th class="ps-24">Site</th>
            <th class="text-end">Total</th>
            <th class="text-end">Total banned</th>
            <th class="text-end">Sudah unbanned</th>
            <th class="text-end">Masih banned</th>
            <th class="text-end">Belum banned</th>
            <th class="pe-24">Komposisi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($ringkasan['site'] as $s)
            <tr data-rb-site="{{ $s['site'] }}" tabindex="0" aria-label="Saring site {{ $s['site'] === '-' ? 'tanpa site' : $s['site'] }}">
              <td class="ps-24 fw-semibold">{{ $s['site'] === '-' ? 'Tanpa site' : $s['site'] }}</td>
              <td class="text-end rb-angka fw-semibold">{{ $angka($s['total']) }}</td>
              <td class="text-end rb-angka">{{ $angka($s['banned'] + $s['unbanned']) }}</td>
              <td class="text-end rb-angka">{{ $angka($s['unbanned']) }} <span class="text-secondary-light">({{ $persen($s['unbanned'], $s['banned'] + $s['unbanned']) }}%)</span></td>
              <td class="text-end rb-angka">{{ $angka($s['banned']) }} <span class="text-secondary-light">({{ $persen($s['banned'], $s['banned'] + $s['unbanned']) }}%)</span></td>
              <td class="text-end rb-angka">{{ $angka($s['belum']) }}</td>
              <td class="pe-24">
                <div class="rb-bar" title="{{ $s['site'] }}: {{ $s['banned'] }} banned, {{ $s['unbanned'] }} unbanned, {{ $s['belum'] }} belum">
                  @foreach (['banned', 'unbanned', 'belum'] as $k)
                    @if ($s[$k] > 0)<span class="rb-w-{{ $k }}" style="width: {{ round($s[$k] / max(1, $s['total']) * 100, 2) }}%"></span>@endif
                  @endforeach
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-secondary-light py-16">Belum ada data banned.</td></tr>
          @endforelse
        </tbody>
        @if ($ringkasan['site'] !== [])
          <tfoot>
            <tr class="fw-semibold">
              <td class="ps-24">Total</td>
              <td class="text-end rb-angka">{{ $angka($t['total']) }}</td>
              <td class="text-end rb-angka">{{ $angka($t['banned'] + $t['unbanned']) }}</td>
              <td class="text-end rb-angka">{{ $angka($t['unbanned']) }}</td>
              <td class="text-end rb-angka">{{ $angka($t['banned']) }}</td>
              <td class="text-end rb-angka">{{ $angka($t['belum']) }}</td>
              <td class="pe-24"></td>
            </tr>
          </tfoot>
        @endif
      </table>
    </div>
  </div>
</div>
@endif

<div class="card radius-8 border-0 shadow-sm" id="rbDaftar">
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
      <div>
        <label for="rbFilterSite" class="form-label text-sm fw-medium mb-4">Site</label>
        <select id="rbFilterSite" class="form-select form-select-sm">
          <option value="">Semua</option>
          @foreach ($ringkasan['site'] ?? [] as $s)
            <option value="{{ $s['site'] }}">{{ $s['site'] === '-' ? 'Tanpa site' : $s['site'] }}</option>
          @endforeach
        </select>
      </div>
      @if (\App\Models\SidRosterBannedMaster::adaKolomAturan())
      <div>
        <label for="rbFilterAturan" class="form-label text-sm fw-medium mb-4">Aturan</label>
        <select id="rbFilterAturan" class="form-select form-select-sm">
          <option value="">Semua</option>
          @foreach (\App\Models\SidRosterBannedMaster::LABEL_ATURAN as $kodeAturan => $labelAturan)
            <option value="{{ $kodeAturan }}">{{ $labelAturan }}</option>
          @endforeach
          <option value="MANUAL">Manual / tidak diketahui</option>
        </select>
      </div>
      @endif
    </div>
    <div class="table-responsive">
      <table id="rbTable" class="table bordered-table mb-0 w-100">
        <thead>
          <tr>
            <th>No</th>
            <th>Karyawan</th>
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
    var filterAturan = document.getElementById('rbFilterAturan');
    var filterSite = document.getElementById('rbFilterSite');
    var exportBtn = document.getElementById('rbExportBtn');
    var exportBase = @json(route('pra-operasi.roster-banned.export'));

    var table = new DataTable('#rbTable', {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'asc']],
        autoWidth: false,
        ajax: {
            url: @json(route('pra-operasi.roster-banned.data')),
            data: function (d) {
                d.status_banned = filterBanned.value;
                d.status_log = filterLog.value;
                d.aturan = filterAturan ? filterAturan.value : '';
                d.site = filterSite.value;
            },
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            // Nama + SID · NIK dalam satu kolom; diurutkan menurut nama.
            { data: 'karyawan', className: 'rb-col-karyawan' },
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

    // Sorotan kartu & baris site mengikuti filter yang aktif.
    function sorot() {
        document.querySelectorAll('[data-rb-status]').forEach(function (k) {
            var aktif = k.dataset.rbStatus !== '' && k.dataset.rbStatus === filterBanned.value;
            k.classList.toggle('is-aktif', aktif);
            k.setAttribute('aria-pressed', aktif ? 'true' : 'false');
        });
        document.querySelectorAll('[data-rb-site]').forEach(function (tr) {
            tr.classList.toggle('is-aktif', tr.dataset.rbSite === filterSite.value);
        });
    }

    [filterBanned, filterLog, filterAturan, filterSite].filter(Boolean).forEach(function (el) {
        el.addEventListener('change', function () { sorot(); table.ajax.reload(); });
    });

    function keDaftar() {
        sorot();
        table.ajax.reload();
        document.getElementById('rbDaftar').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    // Kartu ringkasan -> filter Status Banned (klik lagi = lepas).
    document.querySelectorAll('[data-rb-status]').forEach(function (k) {
        k.addEventListener('click', function () {
            filterBanned.value = filterBanned.value === k.dataset.rbStatus ? '' : k.dataset.rbStatus;
            keDaftar();
        });
    });
    // Baris site -> filter Site (klik lagi = lepas).
    document.querySelectorAll('[data-rb-site]').forEach(function (tr) {
        function pilih() {
            filterSite.value = filterSite.value === tr.dataset.rbSite ? '' : tr.dataset.rbSite;
            keDaftar();
        }
        tr.addEventListener('click', pilih);
        tr.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pilih(); }
        });
    });

    // Export ikut pencarian + filter yang sedang aktif di tabel.
    exportBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var params = new URLSearchParams({
            search: table.search() || '',
            status_banned: filterBanned.value,
            status_log: filterLog.value,
            aturan: filterAturan ? filterAturan.value : '',
            site: filterSite.value,
        });
        window.location.href = exportBase + '?' + params.toString();
    });
})();
</script>
@endsection
