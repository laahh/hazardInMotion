@extends('evaluasi-well.layouts.app')

@section('title', 'Import Karyawan Excel')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Import Karyawan Excel</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('evaluasi-well.users.index') }}" class="hover-text-primary">Manajemen User</a>
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

@unless ($connectionUp ?? false)
<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8" role="alert">
  Koneksi BeWell tidak tersedia. Pastikan tunnel BeWell aktif sebelum import.
</div>
@endunless

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-0">Upload Excel ke employee_profiles</h6>
  </div>
  <div class="card-body p-24">
    <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8">
      Perubahan menulis ke <strong>database produksi BeWell</strong>.
      Password login otomatis = <strong>Kode SID</strong> (bcrypt).
      Jika <code>kode_sid</code> sudah ada → data di-<strong>update</strong>; jika belum → <strong>create</strong>.
    </div>

    <div class="alert alert-info bg-info-100 text-info-600 border-info-100 px-24 py-13 mb-24 radius-8">
      <strong>Format Excel</strong>
      <ul class="mb-0 mt-8">
        <li>Baris pertama = header.</li>
        <li>Kolom wajib: <code>nama</code>, <code>kode_sid</code>.</li>
        <li>Opsional: nik, status_karyawan, site, usia, divisi, departement, nama_perusahaan, jabatan_fungsional, dll.</li>
        <li>Default status jika kosong: <code>AKTIF</code>.</li>
      </ul>
    </div>

    <div class="mb-24">
      <a href="{{ route('evaluasi-well.users.import-template') }}" class="btn btn-outline-primary-600 radius-8 px-16 py-10">
        Download Template Excel
      </a>
    </div>

    <form action="{{ route('evaluasi-well.users.import') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <div class="row g-3">
        <div class="col-12">
          <label for="file" class="form-label">File Excel <span class="text-danger">*</span></label>
          <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file" accept=".xlsx,.xls,.csv" required>
          @error('file')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <div class="form-text">Format: xlsx / xls / csv. Maks. 10 MB.</div>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-primary-600 radius-8 px-20 py-11">Import</button>
          <a href="{{ route('evaluasi-well.users.index') }}" class="btn btn-outline-secondary radius-8 px-20 py-11">Batal</a>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
