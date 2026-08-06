@extends('evaluasi-well.layouts.app')

@section('title', 'Tambah Karyawan')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Tambah Karyawan</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('evaluasi-well.users.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        Manajemen User
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Tambah</li>
  </ul>
</div>

@unless ($connectionUp ?? false)
<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8 d-flex align-items-start gap-2" role="alert">
  <iconify-icon icon="solar:danger-triangle-bold" class="icon text-xl mt-1"></iconify-icon>
  <div>Koneksi BeWell tidak tersedia. Form tidak dapat disimpan sampai tunnel aktif.</div>
</div>
@endunless

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-0">Form Karyawan Baru</h6>
  </div>
  <div class="card-body p-24">
    <form action="{{ route('evaluasi-well.users.store') }}" method="POST" class="needs-validation" novalidate>
      @csrf
      @include('evaluasi-well.users._form')
    </form>
  </div>
</div>
@endsection
