@extends('MonitoringSafetyEngginering.layouts.crm')

@section('title', 'Tambah Role Akses — Monitoring Safety Engineering')

@push('head')
@include('MonitoringSafetyEngginering.partials.crm-styles')
@include('MonitoringSafetyEngginering.role-access._styles')
@endpush

@section('content')
<div class="ra-page-head">
   <div>
      <h1 class="ra-page-title">Tambah Role Akses</h1>
      <p class="ra-page-subtitle">Assign satu orang ke perusahaan dan site yang dia pegang.</p>
   </div>
   <a href="{{ route('monitoring-safety-engineering.role-access.index') }}" class="ra-btn ra-btn--ghost">
      <span class="material-symbols-outlined text-[18px]">arrow_back</span>
      Kembali
   </a>
</div>

<form method="POST" action="{{ route('monitoring-safety-engineering.role-access.store') }}" data-ra-form>
   @csrf
   @include('MonitoringSafetyEngginering.role-access._form', ['assignment' => null])
</form>
@endsection

@push('scripts')
@include('MonitoringSafetyEngginering.role-access._form-scripts')
@endpush
