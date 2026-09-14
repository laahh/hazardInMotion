@extends('pnc-monitoring.layouts.app')

@section('title', 'Portal')

@section('content')
<div class="mb-24">
  <h4 class="mb-8">PNC Monitoring System</h4>
  <p class="text-secondary-light mb-0">Portal monitoring kinerja IKK dan Performance Pengawas Teknis (Commissioning / SPIP).</p>
</div>

<div class="row gy-4">
  <div class="col-xxl-3 col-sm-6">
    <a href="{{ route('pnc-monitoring.dashboard.ikk') }}" class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-1 text-decoration-none">
      <div class="card-body p-0">
        <span class="mb-12 w-48-px h-48-px bg-primary-600 flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
          <iconify-icon icon="solar:chart-2-bold" class="icon"></iconify-icon>
        </span>
        <h6 class="fw-semibold mb-4 text-primary-light">Dashboard IKK</h6>
        <p class="text-sm text-secondary-light mb-0">KPI IPK, IA, Cancel, OKK Layer 1 &amp; Layer 2 Up.</p>
      </div>
    </a>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <a href="{{ route('pnc-monitoring.dashboard.pengawas') }}" class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-2 text-decoration-none">
      <div class="card-body p-0">
        <span class="mb-12 w-48-px h-48-px bg-success-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
          <iconify-icon icon="solar:user-check-bold" class="icon"></iconify-icon>
        </span>
        <h6 class="fw-semibold mb-4 text-primary-light">Dashboard Pengawas</h6>
        <p class="text-sm text-secondary-light mb-0">Performance commissioning, SKO release/reject, ranking pengawas.</p>
      </div>
    </a>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <a href="{{ route('pnc-monitoring.ikk-records.index') }}" class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-3 text-decoration-none">
      <div class="card-body p-0">
        <span class="mb-12 w-48-px h-48-px bg-yellow flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
          <iconify-icon icon="solar:document-text-bold" class="icon"></iconify-icon>
        </span>
        <h6 class="fw-semibold mb-4 text-primary-light">Data IKK</h6>
        <p class="text-sm text-secondary-light mb-0">CRUD &amp; import Excel Main Data IKK.</p>
      </div>
    </a>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <a href="{{ route('pnc-monitoring.commissionings.index') }}" class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-4 text-decoration-none">
      <div class="card-body p-0">
        <span class="mb-12 w-48-px h-48-px bg-purple flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
          <iconify-icon icon="solar:clipboard-list-bold" class="icon"></iconify-icon>
        </span>
        <h6 class="fw-semibold mb-4 text-primary-light">Data Commissioning</h6>
        <p class="text-sm text-secondary-light mb-0">CRUD &amp; import Excel Commissioning / SPIP.</p>
      </div>
    </a>
  </div>
</div>
@endsection
