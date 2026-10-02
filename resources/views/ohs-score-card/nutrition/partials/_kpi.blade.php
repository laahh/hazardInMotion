@php
    $kpi = $kpi ?? ['usersLogged' => 0, 'totalEntries' => 0, 'alertCount' => 0, 'goodScorePct' => 0];
@endphp
<div class="row gy-4 mb-24">
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-1">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2">
          <span class="mb-0 w-48-px h-48-px bg-primary-600 flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
            <iconify-icon icon="solar:users-group-rounded-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="mb-2 fw-medium text-secondary-light text-sm">User Log 7 Hari</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpi['usersLogged']) }}</h6>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-2">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2">
          <span class="mb-0 w-48-px h-48-px bg-success-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
            <iconify-icon icon="solar:document-text-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="mb-2 fw-medium text-secondary-light text-sm">Total Entri 7 Hari</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpi['totalEntries']) }}</h6>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-3">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2">
          <span class="mb-0 w-48-px h-48-px bg-danger-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
            <iconify-icon icon="solar:danger-triangle-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="mb-2 fw-medium text-secondary-light text-sm">Alert Terbuka</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpi['alertCount']) }}</h6>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-4">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2">
          <span class="mb-0 w-48-px h-48-px bg-info-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
            <iconify-icon icon="solar:graph-up-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="mb-2 fw-medium text-secondary-light text-sm">Skor Good/Excellent</span>
            <h6 class="fw-semibold mb-0">{{ number_format($kpi['goodScorePct'], 1) }}%</h6>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
