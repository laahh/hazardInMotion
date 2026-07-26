@php
  $kpiData = $kpi ?? ['p1' => 0, 'p2' => 0, 'p3' => 0, 'mcu_abnormal' => 0];
@endphp
<div class="row gy-4 mb-24">
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-1">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2">
          <span class="mb-0 w-48-px h-48-px bg-danger-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
            <iconify-icon icon="solar:danger-triangle-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="mb-2 fw-medium text-secondary-light text-sm">Prioritas 1</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpiData['p1'] ?? 0) }}</h6>
            <span class="text-xs text-secondary-light">MCU buruk + pola makan buruk</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-2">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2">
          <span class="mb-0 w-48-px h-48-px bg-warning-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
            <iconify-icon icon="solar:eye-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="mb-2 fw-medium text-secondary-light text-sm">Prioritas 2</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpiData['p2'] ?? 0) }}</h6>
            <span class="text-xs text-secondary-light">MCU buruk · pantau medis</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-3">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2">
          <span class="mb-0 w-48-px h-48-px bg-info-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
            <iconify-icon icon="solar:book-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="mb-2 fw-medium text-secondary-light text-sm">Prioritas 3</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpiData['p3'] ?? 0) }}</h6>
            <span class="text-xs text-secondary-light">Pola makan buruk (edukasi)</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-4">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2">
          <span class="mb-0 w-48-px h-48-px bg-primary-600 flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
            <iconify-icon icon="solar:heart-pulse-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="mb-2 fw-medium text-secondary-light text-sm">MCU Abnormal</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpiData['mcu_abnormal'] ?? 0) }}</h6>
            <span class="text-xs text-secondary-light">Karyawan AKTIF terpetakan</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
