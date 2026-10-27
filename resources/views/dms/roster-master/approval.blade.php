@extends('dms.layouts.app')

@section('title', 'Approval Roster')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Approval Roster</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('dms.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>DMS
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Master Roster</li>
    <li>-</li>
    <li class="fw-medium">Approval Roster</li>
  </ul>
</div>

@include('dms.roster-master._alert')

{{-- Assign Role: cakupan perusahaan akun ini (null = semua). --}}
@if ($cakupan === [])
  <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8" role="alert">
    Akun Anda belum di-assign ke perusahaan mana pun, jadi belum ada roster yang bisa Anda approve.
    Minta admin menambahkan perusahaan Anda di menu <b>Assign Role</b>.
  </div>
@elseif ($cakupan !== null)
  <div class="alert alert-info bg-info-50 text-info-600 border-info-100 px-24 py-13 mb-24 radius-8 text-sm" role="alert">
    Anda hanya melihat roster perusahaan: <b>{{ implode(', ', $cakupan) }}</b>.
  </div>
@endif

@include('dms.roster-master._ringkas')

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="text-lg fw-semibold mb-4">Daftar roster</h6>
        <p class="text-sm text-secondary-light mb-0">Buka roster untuk melihat jadwalnya, lalu setujui atau tolak.</p>
      </div>
      <form method="GET" action="{{ route('dms.master-roster.approval') }}">
        <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
          @foreach (['PENDING' => 'Menunggu approval', 'APPROVED' => 'Disetujui', 'REJECTED' => 'Ditolak', 'SEMUA' => 'Semua status'] as $kode => $label)
            <option value="{{ $kode }}" @selected($status === $kode)>{{ $label }}</option>
          @endforeach
        </select>
      </form>
    </div>
  </div>
  <div class="card-body p-24">
    @include('dms.roster-master._daftar', ['daftar' => $daftar])
  </div>
</div>
@endsection
