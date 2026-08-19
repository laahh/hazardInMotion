{{-- Modal drill-down detail KPI dashboard DMS --}}
<div class="modal fade" id="dmsKpiDetailModal" tabindex="-1" aria-labelledby="dmsKpiDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xxl modal-dialog-scrollable">
    <div class="modal-content radius-8 border-0 shadow-lg">
      <div class="modal-header border-bottom py-16 px-24">
        <div class="min-w-0 pe-12">
          <h5 class="modal-title fw-bold text-lg mb-4" id="dmsKpiDetailModalLabel">Detail KPI</h5>
          <p id="dms-kpi-detail-subtitle" class="text-sm text-secondary-light mb-0"></p>
          <nav id="dms-kpi-detail-breadcrumb" class="mt-8" aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 flex-wrap"></ol>
          </nav>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body p-24 position-relative">
        <div id="dms-kpi-detail-loading" class="position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center bg-base" style="z-index: 5; opacity: 0.92;">
          <div class="text-center">
            <div class="spinner-border text-primary-600" role="status" aria-hidden="true"></div>
            <p class="text-sm text-secondary-light mt-12 mb-0">Memuat detail…</p>
          </div>
        </div>

        <div id="dms-kpi-detail-error" class="d-none">
          <div class="border radius-8 p-20 text-center bg-neutral-50">
            <iconify-icon icon="solar:danger-triangle-bold" class="text-warning-main text-3xl mb-8"></iconify-icon>
            <p id="dms-kpi-detail-error-message" class="text-secondary-light text-sm mb-0"></p>
          </div>
        </div>

        <div id="dms-kpi-detail-content">
          <div id="dms-kpi-detail-summary" class="row g-3 mb-20 d-none"></div>

          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-12">
            <div>
              <span class="text-secondary-light text-sm">Total: </span>
              <strong id="dms-kpi-detail-total" class="text-md">0</strong>
            </div>
            <span id="dms-kpi-detail-hint" class="text-xs text-secondary-light"></span>
          </div>

          <div class="table-responsive border radius-8">
            <table class="table table-hover mb-0 align-middle">
              <thead class="bg-neutral-50">
                <tr id="dms-kpi-detail-head"></tr>
              </thead>
              <tbody id="dms-kpi-detail-body"></tbody>
            </table>
          </div>

          <div id="dms-kpi-detail-empty" class="d-none text-center py-24 text-secondary-light text-sm">
            Tidak ada data untuk filter ini.
          </div>

          <div id="dms-kpi-detail-pagination" class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-16 d-none">
            <span id="dms-kpi-detail-page-info" class="text-sm text-secondary-light"></span>
            <div class="d-flex gap-2">
              <button type="button" id="dms-kpi-detail-prev" class="btn btn-sm btn-outline-secondary" disabled>Sebelumnya</button>
              <button type="button" id="dms-kpi-detail-next" class="btn btn-sm btn-outline-secondary" disabled>Berikutnya</button>
            </div>
          </div>

          <p id="dms-kpi-detail-footnote" class="text-xs text-secondary-light mt-16 mb-0 d-none">
            Unit online diambil dari <code>dms_vehicle_status_alerts</code> (global). Breakdown site/perusahaan untuk unit aktivitas alert dari <code>mv_dms_alert</code>.
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
