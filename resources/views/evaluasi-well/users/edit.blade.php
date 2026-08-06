@extends('evaluasi-well.layouts.app')

@section('title', 'Edit Karyawan')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Edit Karyawan</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('evaluasi-well.users.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        Manajemen User
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Edit</li>
  </ul>
</div>

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-0">{{ $employee['nama'] ?? 'Edit Karyawan' }}</h6>
    <p class="text-sm text-secondary-light mb-0 mt-4">ID: {{ $employee['id'] ?? '-' }} · SID: {{ $employee['kode_sid'] ?? '-' }}</p>
  </div>
  <div class="card-body p-24">
    <form action="{{ route('evaluasi-well.users.update', $employee['id']) }}" method="POST" class="needs-validation" novalidate>
      @csrf
      @method('PUT')
      @include('evaluasi-well.users._form')
    </form>
  </div>
</div>
@endsection
