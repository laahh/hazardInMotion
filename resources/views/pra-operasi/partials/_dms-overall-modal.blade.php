{{-- Modal overview unit beroperasi & alert — ukuran standar modal-xl --}}
<div class="modal fade" id="dmsOverallModal" tabindex="-1" aria-labelledby="dmsOverallModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable dms-overall-modal-dialog">
    <div class="modal-content radius-8 border-0 shadow-lg">
      <div class="modal-header border-bottom py-16 px-24">
        <div class="min-w-0 pe-12">
          <h5 class="modal-title fw-bold text-lg mb-4" id="dmsOverallModalLabel">Overview Unit & Alert</h5>
          <p id="dms-overall-subtitle" class="text-sm text-secondary-light mb-0"></p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body p-24 position-relative">
        <div id="dms-overall-loading" class="position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center bg-base" style="z-index: 5; opacity: 0.92;">
          <div class="text-center">
            <div class="spinner-border text-primary-600" role="status" aria-hidden="true"></div>
            <p class="text-sm text-secondary-light mt-12 mb-0">Memuat overview…</p>
          </div>
        </div>

        <div id="dms-overall-error" class="d-none">
          <div class="border radius-8 p-20 text-center bg-neutral-50">
            <iconify-icon icon="solar:danger-triangle-bold" class="text-warning-main text-3xl mb-8"></iconify-icon>
            <p id="dms-overall-error-message" class="text-secondary-light text-sm mb-0"></p>
          </div>
        </div>

        <div id="dms-overall-content" class="d-none">
          {{-- Summary cards --}}
          <div id="dms-overall-summary" class="row g-3 mb-24"></div>

          {{-- Top units + control chart (2 kolom di layar lebar) --}}
          <div class="row g-3 mb-24">
            <div class="col-xl-5">
              <div class="card border radius-8 h-100">
                <div class="card-body p-20">
                  <h6 class="fw-bold text-md mb-12">Unit dengan Alert Terbanyak</h6>
                  <div id="dms-overall-top-units" class="row g-2 mb-12"></div>
                  <div id="dms-overall-top-units-chart" class="dms-overall-mini-chart"></div>
                </div>
              </div>
            </div>
            <div class="col-xl-7">
              <div class="card border radius-8 h-100">
                <div class="card-body p-20">
                  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-12">
                    <div>
                      <h6 class="fw-bold text-md mb-4">Control Chart Alert Harian</h6>
                      <p class="text-xs text-secondary-light mb-0">Mean ± 3σ (UCL / LCL) dari total alert per hari</p>
                    </div>
                    <div id="dms-overall-control-legend" class="d-flex flex-wrap gap-3 text-xs"></div>
                  </div>
                  <div id="dms-overall-control-chart" class="dms-overall-main-chart"></div>
                </div>
              </div>
            </div>
          </div>

          {{-- Daftar unit beroperasi --}}
          <div class="card border radius-8">
            <div class="card-body p-20">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-16">
                <h6 class="fw-bold text-md mb-0">Daftar Unit Beroperasi</h6>
                <span id="dms-overall-table-count" class="text-sm text-secondary-light"></span>
              </div>
              <div class="d-flex flex-wrap gap-2 mb-16" id="dms-overall-table-tabs">
                <button type="button" class="btn btn-sm btn-primary" data-status="with_alert">
                  Unit Dengan Alert
                  <span class="ms-4" id="dms-overall-tab-count-with-alert">0</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-status="without_alert">
                  Unit Tanpa Alert
                  <span class="ms-4" id="dms-overall-tab-count-without-alert">0</span>
                </button>
              </div>
              <div class="table-responsive border radius-8">
                <table class="table table-hover mb-0 align-middle">
                  <thead class="bg-neutral-50">
                    <tr>
                      <th scope="col">Unit</th>
                      <th scope="col">Site</th>
                      <th scope="col">Perusahaan</th>
                      <th scope="col" style="min-width: 220px;">Bukti Operasi</th>
                      <th scope="col" style="min-width: 160px;">Status Alert</th>
                      <th scope="col" class="text-end">Total Alert</th>
                      <th scope="col" style="min-width: 140px;">Detail</th>
                    </tr>
                  </thead>
                  <tbody id="dms-overall-table-body"></tbody>
                </table>
              </div>
              <div id="dms-overall-table-empty" class="d-none text-center py-24 text-secondary-light text-sm">
                Tidak ada unit beroperasi pada periode ini.
              </div>
              <div id="dms-overall-pagination" class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-16 d-none">
                <span id="dms-overall-page-info" class="text-sm text-secondary-light"></span>
                <div class="d-flex gap-2">
                  <button type="button" id="dms-overall-prev" class="btn btn-sm btn-outline-secondary" disabled>Sebelumnya</button>
                  <button type="button" id="dms-overall-next" class="btn btn-sm btn-outline-secondary" disabled>Berikutnya</button>
                </div>
              </div>
            </div>
          </div>

          <p class="text-xs text-secondary-light mt-16 mb-0">
            Unit beroperasi dari <code>dms_vehicle_status_alerts</code> (fallback) / GPS bila tersedia.
            Alert dari <code>mv_dms_alert</code>. UCL/LCL = mean ± 3 standar deviasi harian.
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
