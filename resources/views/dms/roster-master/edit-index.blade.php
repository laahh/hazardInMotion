@extends('dms.layouts.app')

@section('title', 'Edit Roster')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Edit Roster</h6>
    <p class="text-sm text-secondary-light mb-0 mt-4">Ubah jadwal roster yang sudah di-upload. Perubahan langsung berlaku dan tercatat di riwayat edit.</p>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('dms.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>DMS
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Master Roster</li>
    <li>-</li>
    <li class="fw-medium">Edit Roster</li>
  </ul>
</div>

@include('dms.roster-master._alert')

@if ($assignment === [])
  <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8" role="alert">
    Edit roster hanya untuk akun yang di-assign ke perusahaan. Minta admin menambahkan perusahaan Anda di menu <b>Assign Role</b>.
  </div>
@else
  <div class="alert alert-info bg-info-50 text-info-600 border-info-100 px-24 py-13 mb-24 radius-8 text-sm" role="alert">
    Anda bisa mengedit jadwal karyawan perusahaan: <b>{{ implode(', ', $assignment) }}</b>.
    Karyawan perusahaan lain dalam roster yang sama tidak ditampilkan.
  </div>
@endif

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="text-lg fw-semibold mb-4">Roster yang bisa diedit</h6>
        <p class="text-sm text-secondary-light mb-0">Roster menunggu approval &amp; disetujui. Roster yang ditolak tidak bisa diedit — upload ulang.</p>
      </div>
      <form method="GET" action="{{ route('dms.master-roster.edit.index', absolute: false) }}">
        <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()" aria-label="Filter status">
          @foreach (['' => 'Semua status', 'PENDING' => 'Menunggu approval', 'APPROVED' => 'Disetujui'] as $kode => $label)
            <option value="{{ $kode }}" @selected($status === $kode)>{{ $label }}</option>
          @endforeach
        </select>
      </form>
    </div>
  </div>
  <div class="card-body p-24">
    @include('dms.roster-master._daftar', ['daftar' => $daftar, 'aksi' => 'edit'])
  </div>
</div>
@endsection
