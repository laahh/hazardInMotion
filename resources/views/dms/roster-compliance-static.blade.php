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

<div class="alert alert-info bg-info-100 text-info-600 border-info-100 px-24 py-13 mb-24 radius-8 d-flex gap-2 align-items-start" role="alert">
  <iconify-icon icon="solar:info-circle-bold" class="icon text-lg flex-shrink-0 mt-2"></iconify-icon>
  <div class="text-sm">
    Snapshot statis hasil ekstraksi satu kali (bukan koneksi live ke database) &mdash; kesepakatan yang diambil agar data
    pribadi karyawan tidak dipublikasikan tanpa autentikasi. Sumber data: scan gate PASSED
    (check-in pertama &lt;12:00 = Pagi, &ge;12:00 = Malam, tanpa check-in = Off; Off &gt;5 hari berturut dianggap fase Cuti).
    Metodologi &amp; ambang pelanggaran identik dengan referensi yang dipelajari &mdash; lihat panel <b>Parameter per Kontraktor</b> di bawah.
  </div>
</div>

<div id="rkLoading" class="card radius-8 border-0 shadow-sm">
  <div class="card-body text-center py-64">
    <div class="spinner-border text-primary-600 mb-16" role="status"></div>
    <h6 class="text-md fw-semibold mb-4">Memuat data roster</h6>
    <p class="text-sm text-secondary-light mb-0">Ukuran &plusmn;14&nbsp;MB, mohon tunggu sebentar&hellip;</p>
  </div>
</div>

<div id="rkError" class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 radius-8 d-none" role="alert">
  <iconify-icon icon="solar:danger-circle-bold" class="icon me-1 align-middle"></iconify-icon>
  Gagal memuat data roster. Coba muat ulang halaman.
</div>

<div id="rkApp" class="d-none">

  {{-- ===================== KONTRAKTOR + FILTER ===================== --}}
  <div class="card radius-8 border-0 shadow-sm mb-24">
    <div class="card-header border-bottom bg-base py-16 px-24">
      <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
          <div class="d-flex align-items-center gap-2 mb-4">
            <h6 class="text-lg fw-semibold mb-0">Filter Data Roster</h6>
          </div>
          <p class="text-sm text-secondary-light mb-0">
            Pilih kontraktor, lalu persempit dengan site, kategori jabatan, status, atau periode
          </p>
        </div>
        <button type="button" id="rkExportBtn" class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
          <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
          Download CSV
        </button>
      </div>
    </div>
    <div class="card-body p-24">

      <div class="mb-20">
        <label class="form-label text-sm fw-medium mb-6">Perusahaan / Kontraktor</label>
        <div class="d-flex flex-wrap gap-2" id="rkCoPills"></div>
      </div>

      <div class="bg-neutral-50 border radius-8 p-16">
        <div class="row g-3 align-items-end">
          <div class="col-xl-3 col-md-4 col-sm-6">
            <label for="rkSearch" class="form-label text-sm fw-medium mb-6">Cari Karyawan</label>
            <input type="search" id="rkSearch" class="form-control form-control-sm" placeholder="Nama atau SID&hellip;">
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkSiteSelect" class="form-label text-sm fw-medium mb-6">Site</label>
            <select id="rkSiteSelect" class="form-select form-select-sm"></select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkJabSelect" class="form-label text-sm fw-medium mb-6">Kategori Jabatan</label>
            <select id="rkJabSelect" class="form-select form-select-sm"></select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkRosterSelect" class="form-label text-sm fw-medium mb-6">Roster</label>
            <select id="rkRosterSelect" class="form-select form-select-sm"></select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkStatusSelect" class="form-label text-sm fw-medium mb-6">Status Kini</label>
            <select id="rkStatusSelect" class="form-select form-select-sm">
              <option value="">Semua Status Kini</option>
              <option value="Shift Pagi">Shift Pagi</option>
              <option value="Shift Malam">Shift Malam</option>
              <option value="Overshift">Overshift</option>
              <option value="Off">Off</option>
              <option value="Cuti">Cuti</option>
            </select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkNoteSelect" class="form-label text-sm fw-medium mb-6">Catatan</label>
            <select id="rkNoteSelect" class="form-select form-select-sm">
              <option value="">Semua Catatan</option>
              <option value="pel">Pelanggaran</option>
              <option value="wajib">Wajib Cuti</option>
              <option value="map">Mapping</option>
            </select>
          </div>

          <div class="col-12">
            <hr class="my-4 text-neutral-200">
          </div>

          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkPeriodSelect" class="form-label text-sm fw-medium mb-6">Periode</label>
            <select id="rkPeriodSelect" class="form-select form-select-sm"></select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkDateFrom" class="form-label text-sm fw-medium mb-6">Rentang Dari</label>
            <input type="date" id="rkDateFrom" class="form-control form-control-sm">
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkDateTo" class="form-label text-sm fw-medium mb-6">Sampai</label>
            <input type="date" id="rkDateTo" class="form-control form-control-sm">
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <button type="button" id="rkResetRange" class="btn btn-sm btn-outline-primary-600 radius-8 w-100">
              Reset Rentang
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ===================== KARTU RINGKASAN ===================== --}}
  <div class="row gy-4 mb-24" id="rkKpiRow"></div>

  {{-- ===================== DISTRIBUSI + REKAP SITE ===================== --}}
  <div class="row gy-4 mb-24">
    <div class="col-xxl-5 d-flex">
      <div class="card h-100 w-100 radius-8 border-0 shadow-sm">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-4">Distribusi Status Kini</h6>
          <p class="text-sm text-secondary-light mb-0">Kondisi pada hari terakhir periode terpilih</p>
        </div>
        <div class="card-body p-24">
          <div id="rkDonutChart"></div>
        </div>
      </div>
    </div>
    <div class="col-xxl-7 d-flex" id="rkSiteAggWrap">
      <div class="card h-100 w-100 radius-8 border-0 shadow-sm">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-4">Rekap per Site</h6>
          <p class="text-sm text-secondary-light mb-0">Ditampilkan saat &ldquo;Semua Perusahaan&rdquo; dipilih</p>
        </div>
        <div class="card-body p-0" id="rkSiteAgg"></div>
      </div>
    </div>
  </div>

  {{-- ===================== TABEL + PANEL DETAIL ===================== --}}
  <div class="row gy-4">
    <div class="col-xxl-8 d-flex">
      <div class="card h-100 w-100 radius-8 border-0 shadow-sm rk-master-card">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div>
              <div class="d-flex align-items-center gap-2 mb-4">
                <h6 class="text-lg fw-semibold mb-0">Daftar Karyawan</h6>
                <span id="rkTableNote" class="bg-primary-50 text-primary-600 text-sm fw-medium px-12 py-2 rounded-pill"></span>
              </div>
              <p class="text-sm text-secondary-light mb-0">Klik satu baris untuk melihat timeline harian &amp; riwayat alert DMS</p>
            </div>
          </div>
        </div>
        <div class="card-body p-0 d-flex flex-column">
          <div class="table-responsive rk-table-scroll">
            <table class="table bordered-table mb-0 rk-table">
              <thead>
                <tr>
                  <th scope="col">SID</th>
                  <th scope="col" class="rk-col-name" data-k="nama" style="cursor:pointer">Karyawan</th>
                  <th scope="col" class="text-center" data-k="roster" style="cursor:pointer">Roster</th>
                  <th scope="col" class="text-center" data-k="onAll" style="cursor:pointer">On-site&nbsp;YTD</th>
                  <th scope="col" class="text-center" data-k="cutiMin" style="cursor:pointer">Cuti&nbsp;Min</th>
                  <th scope="col" data-k="status" style="cursor:pointer">Status Kini</th>
                  <th scope="col" class="text-center">Alert&nbsp;DMS</th>
                </tr>
              </thead>
              <tbody id="rkTableBody"></tbody>
            </table>
          </div>
        </div>
        <div class="card-footer bg-base border-top py-12 px-24 d-flex align-items-center justify-content-between flex-wrap gap-2">
          <span class="text-secondary-light text-sm" id="rkPageInfo"></span>
          <div class="d-flex gap-2">
            <button type="button" id="rkPrev" class="btn btn-sm btn-outline-primary-600 radius-8">&laquo; Sebelumnya</button>
            <button type="button" id="rkNext" class="btn btn-sm btn-outline-primary-600 radius-8">Berikutnya &raquo;</button>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xxl-4 d-flex">
      <div class="card h-100 w-100 radius-8 border-0 shadow-sm">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-4">Rincian Karyawan</h6>
          <p class="text-sm text-secondary-light mb-0">Timeline harian, flag pelanggaran, dan alert DMS</p>
        </div>
        <div class="card-body p-24" id="rkDetailWrap"></div>
      </div>
    </div>
  </div>

  {{-- ===================== INSIDEN BELUM TERHUBUNG ===================== --}}
  <div class="card radius-8 border-0 shadow-sm mt-24 d-none" id="rkUnmatchedIncidentsWrap">
    <div class="card-header border-bottom bg-base py-16 px-24">
      <div class="d-flex align-items-center gap-2 mb-4">
        <h6 class="text-lg fw-semibold mb-0">Insiden Belum Terhubung ke Data Roster</h6>
        <span class="bg-warning-focus text-warning-main text-sm fw-medium px-12 py-2 rounded-pill" id="rkUnmatchedCount">0</span>
      </div>
      <p class="text-sm text-secondary-light mb-0">
        NPK pada catatan insiden ini tidak cocok dengan kode SID mana pun di snapshot roster &mdash; kemungkinan NPK
        (nomor pokok karyawan) berbeda dari kode SID (kartu akses gate), atau jabatannya di luar populasi roster
        (Operator/Driver/Mekanik lapangan). Ditampilkan tetap di sini supaya datanya tidak hilang.
      </p>
    </div>
    <div class="card-body p-24 d-flex flex-column gap-2" id="rkUnmatchedIncidents"></div>
  </div>

  {{-- ===================== PARAMETER PER KONTRAKTOR ===================== --}}
  <div class="card radius-8 border-0 shadow-sm mt-24">
    <div class="card-header border-bottom bg-base py-16 px-24">
      <h6 class="text-lg fw-semibold mb-4">Parameter per Kontraktor</h6>
      <p class="text-sm text-secondary-light mb-0">
        Ambang blok roster (kuning) &amp; minimum cuti (merah) berbeda per PT &mdash; sesuai konfigurasi masing-masing
      </p>
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

  {{-- ===================== KETERANGAN ATURAN ===================== --}}
  <div class="card radius-8 border-0 shadow-sm mt-24">
    <div class="card-header border-bottom bg-base py-16 px-24">
      <h6 class="text-lg fw-semibold mb-0">Keterangan Aturan</h6>
    </div>
    <div class="card-body p-24">
      <div class="row gy-3">
        <div class="col-md-6">
          <div class="d-flex align-items-start gap-2 mb-8">
            <span class="w-32-px h-32-px bg-danger-focus text-danger-main radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">
              <iconify-icon icon="solar:danger-triangle-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <h6 class="text-md fw-semibold mb-4">Pelanggaran regulasi (merah)</h6>
              <ul class="text-sm text-secondary-light mb-0 ps-16">
                <li>On-site tanpa cuti &gt;71 hari</li>
                <li>Kerja beruntun (Pagi/Malam tanpa off) &gt;13 hari</li>
                <li>Durasi cuti &lt;12 hari</li>
              </ul>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="d-flex align-items-start gap-2 mb-8">
            <span class="w-32-px h-32-px bg-warning-focus text-warning-main radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">
              <iconify-icon icon="solar:shield-warning-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <h6 class="text-md fw-semibold mb-4">Peringatan mapping (kuning)</h6>
              <ul class="text-sm text-secondary-light mb-0 ps-16">
                <li>Blok kerja melebihi standar roster PT</li>
                <li>Urutan / pergantian shift tidak sesuai mapping</li>
              </ul>
            </div>
          </div>
        </div>
        <div class="col-12">
          <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-16 py-12 mb-0 radius-8 text-sm" role="alert">
            Kategori <b>Operator Transportasi Massal</b> &amp; <b>Mekanik</b> dikecualikan dari aturan on-site/cuti
            karena pola kerjanya berbeda.
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection

@section('page-scripts')
<script
  data-base="{{ asset('dms-assets/roster-compliance') }}"
  data-alert-counts-url="{{ route('dms.roster-compliance-static.alert-counts') }}"
  data-alert-timeline-base="{{ url('dms/roster-compliance-static/alerts') }}"
  src="{{ asset('dms-assets/roster-compliance/roster-compliance.js') }}"></script>
@endsection
