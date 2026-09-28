@extends('dms.layouts.app')

@section('title', 'Tambah Roster Banned')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Tambah Roster Banned</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('pra-operasi.roster-banned.index') }}" class="hover-text-primary">Master Roster Banned</a>
    </li>
    <li>-</li>
    <li class="fw-medium">Tambah</li>
  </ul>
</div>

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-0">Data Karyawan Banned</h6>
  </div>
  <div class="card-body p-24">
    <form action="{{ route('pra-operasi.roster-banned.store') }}" method="POST">
      @csrf
      @include('pra-operasi.roster-banned._form')
    </form>
  </div>
</div>
@endsection
