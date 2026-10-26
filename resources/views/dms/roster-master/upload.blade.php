@extends('dms.layouts.app')

@section('title', 'Upload Roster')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Upload Roster</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('dms.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>DMS
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Master Roster</li>
    <li>-</li>
    <li class="fw-medium">Upload Roster</li>
  </ul>
</div>

@include('dms.roster-master._alert')
@include('dms.roster-master._ringkas')

<div class="row gy-4">
  <div class="col-xxl-5">
    <div class="card radius-8 border-0 shadow-sm h-100">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-4">Upload jadwal roster karyawan</h6>
        <p class="text-sm text-secondary-light mb-0">Excel satu baris per karyawan, satu kolom per tanggal. Setelah upload, roster menunggu approval.</p>
      </div>
      <div class="card-body p-24">
        <form action="{{ route('dms.master-roster.store') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-3">
          @csrf
          <div>
            <label for="rmJudul" class="form-label fw-semibold text-sm">Judul roster <span class="text-danger-600">*</span></label>
            <input type="text" id="rmJudul" name="judul" value="{{ old('judul') }}" class="form-control" maxlength="150" required
                   placeholder="mis. Roster PAMA BMO 2 — November 2026">
          </div>
          <div>
            <label for="rmFile" class="form-label fw-semibold text-sm">File Excel <span class="text-danger-600">*</span></label>
            <input type="file" id="rmFile" name="file" accept=".xlsx,.xls" class="form-control" required>
            <span class="text-xs text-secondary-light">.xlsx / .xls, maks 10 MB, maks 62 hari per file. Paling mudah: download template di bawah lalu isi.</span>
          </div>
          <div>
            <label for="rmCatatan" class="form-label fw-semibold text-sm">Catatan untuk approver</label>
            <textarea id="rmCatatan" name="catatan" rows="3" class="form-control" maxlength="1000">{{ old('catatan') }}</textarea>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary-600 radius-8 d-inline-flex align-items-center gap-1">
              <iconify-icon icon="solar:upload-bold"></iconify-icon>Upload roster
            </button>
          </div>
        </form>

        <hr class="my-24">

        <form action="{{ route('dms.master-roster.template') }}" method="GET" class="d-flex flex-wrap align-items-end gap-2">
          <div>
            <label for="rmBulan" class="form-label fw-semibold text-sm">Template untuk bulan</label>
            <input type="month" id="rmBulan" name="bulan" value="{{ $bulanTemplate }}" class="form-control form-control-sm">
          </div>
          <button type="submit" class="btn btn-sm btn-outline-secondary radius-8 d-inline-flex align-items-center gap-1">
            <iconify-icon icon="solar:file-download-bold"></iconify-icon>Download template
          </button>
        </form>

        <div class="alert alert-info bg-info-100 text-info-600 border-info-100 px-16 py-12 mt-24 mb-0 radius-8 text-sm">
          <strong>Format "ROSTER KERJA KARYAWAN":</strong> isi <b>Tanggal Awal Periode</b> dan <b>Departemen / Site</b> di atas;
          judul kolom No, SID, Nama Karyawan, Perusahaan, Jabatan, Site, Jam Check-in, Jam Check-out, Total Jam Kerja,
          lalu satu kolom per tanggal berurutan. Kolom jam boleh kosong = ikut jam bawaan kode
          (@foreach (\App\Services\Dms\Roster\DmsRosterMasterService::KODE as $kodeJam => [, , $mJam, $kJam])@if ($mJam){{ $kodeJam }} {{ $mJam }}–{{ $kJam }}{{ $kodeJam === 'OF' ? '' : ', ' }}@endif @endforeach).
          Kode: @foreach (\App\Services\Dms\Roster\DmsRosterMasterService::KODE as $kodeInfo => [$ketInfo])<b>{{ $kodeInfo }}</b> ({{ $ketInfo }}){{ $loop->last ? '' : ', ' }}@endforeach;
          sel kosong = tidak dijadwalkan. File dengan satu kesalahan saja ditolak utuh beserta daftar kesalahannya.
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-7">
    <div class="card radius-8 border-0 shadow-sm h-100">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-0">Riwayat upload</h6>
      </div>
      <div class="card-body p-24">
        @include('dms.roster-master._daftar', ['daftar' => $daftar])
      </div>
    </div>
  </div>
</div>
@endsection
