@extends('dms.layouts.app')

@section('title', 'Import Roster Banned')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Import Roster Banned</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('pra-operasi.roster-banned.index') }}" class="hover-text-primary">Master Roster Banned</a>
    </li>
    <li>-</li>
    <li class="fw-medium">Import Excel</li>
  </ul>
</div>

@if (session('error'))
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ session('error') }}
</div>
@endif

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-0">Upload Excel ke sid_roster_banned_master</h6>
  </div>
  <div class="card-body p-24">
    <div class="alert alert-info bg-info-100 text-info-600 border-info-100 px-24 py-13 mb-24 radius-8">
      <strong>Format Excel</strong>
      <ul class="mb-0 mt-8">
        <li>Baris pertama = header, urutan kolom: <code>NIK, SID, Nama, Perusahaan, Site Dedicated, Alasan Pelanggaran, Tanggal Pelanggaran</code>.</li>
        <li>Kolom wajib: <strong>NIK</strong>, <strong>Nama</strong>, <strong>Alasan Pelanggaran</strong>.</li>
        <li>Tanggal Pelanggaran opsional, format tanggal Excel atau teks (mis. 2026-09-28).</li>
        <li>Setiap baris selalu jadi <strong>record baru</strong> (bukan update) — riwayat pelanggaran berulang untuk NIK yang sama tetap tercatat terpisah.</li>
      </ul>
    </div>

    <div class="mb-24">
      <a href="{{ route('pra-operasi.roster-banned.download-template') }}" class="btn btn-outline-primary-600 radius-8 px-16 py-10">
        Download Template Excel
      </a>
    </div>

    <form action="{{ route('pra-operasi.roster-banned.import') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <div class="row g-3">
        <div class="col-12">
          <label for="excel_file" class="form-label">File Excel <span class="text-danger">*</span></label>
          <input type="file" class="form-control @error('excel_file') is-invalid @enderror" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required>
          @error('excel_file')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <div class="form-text">Format: xlsx / xls / csv. Maks. 10 MB.</div>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-primary-600 radius-8 px-20 py-11">Import</button>
          <a href="{{ route('pra-operasi.roster-banned.index') }}" class="btn btn-outline-secondary radius-8 px-20 py-11">Batal</a>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
