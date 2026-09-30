@extends('MonitoringSafetyEngginering.layouts.crm')

@section('title', 'Ubah Role Akses — Monitoring Safety Engineering')

@push('head')
@include('MonitoringSafetyEngginering.partials.crm-styles')
@include('MonitoringSafetyEngginering.role-access._styles')
@endpush

@section('content')
<div class="ra-page-head">
   <div>
      <h1 class="ra-page-title">Ubah Role Akses</h1>
      <p class="ra-page-subtitle">{{ $assignment->identityLabel() }}</p>
   </div>
   <a href="{{ route('monitoring-safety-engineering.role-access.index') }}" class="ra-btn ra-btn--ghost">
      <span class="material-symbols-outlined text-[18px]">arrow_back</span>
      Kembali
   </a>
</div>

<form method="POST" action="{{ route('monitoring-safety-engineering.role-access.update', $assignment->id) }}" data-ra-form>
   @csrf
   @method('PUT')
   @include('MonitoringSafetyEngginering.role-access._form')
</form>
@endsection

@push('scripts')
@include('MonitoringSafetyEngginering.role-access._form-scripts')
@endpush
