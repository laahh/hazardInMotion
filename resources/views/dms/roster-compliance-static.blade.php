@extends('dms.layouts.app')

@section('title', 'Kepatuhan Roster Karyawan')

@section('css')
<link rel="stylesheet" href="{{ asset('dms-assets/roster-compliance/roster-compliance.css') }}">
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Kepatuhan Roster Karyawan</h6>
    <div class="text-secondary-light text-sm mt-4" id="rkGenLabel">Memuat snapshot roster&hellip;</div>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('dms.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        DMS
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Kepatuhan Roster</li>
  </ul>
</div>

<div class="alert-info bg-info-100 text-info-600 border-info-100 border px-16 py-13 rounded-8 mb-24 text-sm d-flex gap-2 align-items-start">
  <iconify-icon icon="solar:info-circle-bold" class="icon text-lg flex-shrink-0 mt-2"></iconify-icon>
  <div>
    Snapshot statis hasil ekstraksi satu kali (bukan koneksi live ke database) &mdash; kesepakatan yang diambil agar data
    pribadi karyawan tidak dipublikasikan tanpa autentikasi. Sumber data: scan gate PASSED
    (check-in pertama &lt;12:00 = Pagi, &ge;12:00 = Malam, tanpa check-in = Off; Off &gt;5 hari berturut dianggap fase Cuti).
    Metodologi &amp; ambang pelanggaran identik dengan referensi yang dipelajari &mdash; lihat panel <b>Parameter per Kontraktor</b> di bawah.
  </div>
</div>

<div id="rkLoading" class="text-center text-secondary-light py-64">
  <div class="spinner-border text-primary-600 mb-12" role="status"></div>
  <div>Memuat data roster (&plusmn;14&nbsp;MB, mohon tunggu sebentar)&hellip;</div>
</div>

<div id="rkError" class="alert-danger bg-danger-100 text-danger-600 border-danger-100 border px-16 py-13 rounded-8 d-none">
  <iconify-icon icon="solar:danger-circle-bold" class="icon me-1 align-middle"></iconify-icon>
  Gagal memuat data roster. Coba muat ulang halaman.
</div>

<div id="rkApp" class="d-none">

  <div class="d-flex flex-wrap gap-2 mb-16" id="rkCoPills"></div>

  <div class="card radius-8 border mb-24">
    <div class="card-body py-12">
      <div class="row g-2 align-items-center">
        <div class="col-lg-3">
          <input type="search" id="rkSearch" class="form-control" placeholder="Cari nama / SID&hellip;">
        </div>
        <div class="col-lg-2">
          <select id="rkSiteSelect" class="form-select"></select>
        </div>
        <div class="col-lg-2">
          <select id="rkRosterSelect" class="form-select"></select>
        </div>
        <div class="col-lg-2">
          <select id="rkJabSelect" class="form-select"></select>
        </div>
        <div class="col-lg-2">
          <select id="rkStatusSelect" class="form-select">
            <option value="">Semua Status Kini</option>
            <option value="Shift Pagi">Shift Pagi</option>
            <option value="Shift Malam">Shift Malam</option>
            <option value="Overshift">Overshift</option>
            <option value="Off">Off</option>
            <option value="Cuti">Cuti</option>
          </select>
        </div>
        <div class="col-lg-1">
          <select id="rkNoteSelect" class="form-select">
            <option value="">Semua Catatan</option>
            <option value="pel">Pelanggaran</option>
            <option value="wajib">Wajib Cuti</option>
            <option value="map">Mapping</option>
          </select>
        </div>
      </div>
      <div class="row g-2 align-items-center mt-4">
        <div class="col-auto">
          <label class="text-secondary-light text-sm mb-0">Periode:</label>
        </div>
        <div class="col-lg-2">
          <select id="rkPeriodSelect" class="form-select form-select-sm"></select>
        </div>
        <div class="col-auto text-secondary-light text-sm">atau rentang bebas</div>
        <div class="col-lg-2">
          <input type="date" id="rkDateFrom" class="form-control form-control-sm">
        </div>
        <div class="col-auto text-secondary-light text-sm">&ndash;</div>
        <div class="col-lg-2">
          <input type="date" id="rkDateTo" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
          <button type="button" id="rkResetRange" class="btn btn-sm btn-outline-neutral-600">Reset Rentang</button>
        </div>
        <div class="col-auto ms-auto">
          <button type="button" id="rkExportBtn" class="btn btn-sm btn-primary-600">
            <iconify-icon icon="solar:download-minimalistic-outline" class="align-middle me-1"></iconify-icon>Export CSV
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="row gy-4 mb-4" id="rkKpiRow"></div>

  <div class="row gy-4 mb-4">
    <div class="col-xxl-5">
      <div class="card radius-8 border h-100">
        <div class="card-header border-bottom bg-transparent">
          <h6 class="text-lg mb-0">Distribusi Status Kini</h6>
        </div>
        <div class="card-body">
          <div id="rkDonutChart"></div>
        </div>
      </div>
    </div>
    <div class="col-xxl-7" id="rkSiteAggWrap">
      <div class="card radius-8 border h-100">
        <div class="card-header border-bottom bg-transparent">
          <h6 class="text-lg mb-0">Rekap per Site</h6>
          <span class="text-secondary-light text-sm">Ditampilkan saat &ldquo;Semua Perusahaan&rdquo; dipilih</span>
        </div>
        <div class="card-body p-0" id="rkSiteAgg"></div>
      </div>
    </div>
  </div>

  <div class="row gy-4">
    <div class="col-xxl-7">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-transparent d-flex align-items-center justify-content-between">
          <h6 class="text-lg mb-0">Daftar Karyawan</h6>
          <span class="text-secondary-light text-sm" id="rkTableNote"></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive scroll-sm" style="max-height:600px;overflow-y:auto">
            <table class="table bordered-table mb-0 rk-table">
              <thead>
                <tr>
                  <th scope="col">SID</th>
                  <th scope="col" data-k="nama" style="cursor:pointer">Karyawan</th>
                  <th scope="col" data-k="jab" style="cursor:pointer">Jabatan</th>
                  <th scope="col" class="text-center" data-k="roster" style="cursor:pointer">Roster</th>
                  <th scope="col" class="text-center" data-k="onAll" style="cursor:pointer">On-site&nbsp;Maks&nbsp;YTD</th>
                  <th scope="col" class="text-center" data-k="cutiMin" style="cursor:pointer">Cuti&nbsp;Min&nbsp;YTD</th>
                  <th scope="col" data-k="status" style="cursor:pointer">Status Kini</th>
                  <th scope="col">Catatan</th>
                  <th scope="col">Pola (ringkas)</th>
                </tr>
              </thead>
              <tbody id="rkTableBody"></tbody>
            </table>
          </div>
        </div>
        <div class="card-footer d-flex align-items-center justify-content-between bg-transparent">
          <span class="text-secondary-light text-sm" id="rkPageInfo"></span>
          <div class="d-flex gap-2">
            <button type="button" id="rkPrev" class="btn btn-sm btn-outline-neutral-600">&laquo; Sebelumnya</button>
            <button type="button" id="rkNext" class="btn btn-sm btn-outline-neutral-600">Berikutnya &raquo;</button>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xxl-5">
      <div class="card h-100 radius-8 border">
        <div class="card-body" id="rkDetailWrap"></div>
      </div>
    </div>
  </div>

  <div class="card radius-8 border mt-24">
    <div class="card-header border-bottom bg-transparent">
      <h6 class="text-lg mb-0">Parameter per Kontraktor</h6>
      <span class="text-secondary-light text-sm">Ambang blok roster (kuning) &amp; minimum cuti (merah) berbeda per PT &mdash; sesuai konfigurasi masing-masing</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table bordered-table mb-0 rk-param-table">
          <thead>
            <tr>
              <th>PT</th>
              <th>Nama Lengkap</th>
              <th>Siklus Roster</th>
              <th>Pola Shift</th>
              <th class="text-center">Maks Blok Kerja</th>
              <th class="text-center">Min Cuti</th>
            </tr>
          </thead>
          <tbody id="rkParamsBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-3 mt-24 pt-16 border-top text-secondary-light text-sm">
    <span><b class="text-primary-light">Aturan pelanggaran (merah)</b></span>
    <span>On-site tanpa cuti &gt;71 hari</span>
    <span>Kerja beruntun (Pagi/Malam tanpa off) &gt;13 hari</span>
    <span>Durasi cuti &lt;12 hari</span>
    <span><b class="text-primary-light">Peringatan (kuning)</b></span>
    <span>Blok kerja melebihi standar roster PT &amp; urutan/pergantian shift tak sesuai mapping</span>
    <span class="text-warning-600 fw-semibold">&mdash; kategori Operator Transportasi Massal &amp; Mekanik dikecualikan dari aturan on-site/cuti (pola kerja berbeda).</span>
  </div>

</div>
@endsection

@section('page-scripts')
<script data-base="{{ asset('dms-assets/roster-compliance') }}" src="{{ asset('dms-assets/roster-compliance/roster-compliance.js') }}"></script>
@endsection
