{{-- Modal detail statistik Total User Install --}}
<div class="modal fade" id="installStatsModal" tabindex="-1" aria-labelledby="installStatsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xxl modal-dialog-scrollable install-stats-modal-dialog">
    <div class="modal-content radius-8 border-0 shadow-lg">
      <div class="modal-header border-bottom py-16 px-24">
        <div class="min-w-0 pe-12">
          <h5 class="modal-title fw-bold text-lg mb-4" id="installStatsModalLabel">Detail Total User Install</h5>
          <p id="install-stats-footnote" class="text-sm text-secondary-light mb-0">
            Sudah Install mengikuti KPI kartu (tanpa filter). Filter global mengubah seluruh ringkasan; Divisi digabung per grup sejenis.
          </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
          <a id="install-stats-export-btn" href="{{ ($ajaxRoutes['installStatsExport'] ?? route('evaluasi-well.install-stats.export')) }}" class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
            <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
            Download Excel
          </a>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
      </div>

      <div class="modal-body p-24 position-relative">
        <div id="install-stats-loading" class="position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center bg-base" style="z-index: 5; opacity: 0.92;">
          <div class="text-center">
            <div class="spinner-border text-primary-600" role="status" aria-hidden="true"></div>
            <p class="text-sm text-secondary-light mt-12 mb-0">Memuat statistik…</p>
          </div>
        </div>

        <div id="install-stats-unavailable" class="d-none">
          <div class="border radius-8 p-20 text-center bg-neutral-50">
            <iconify-icon icon="solar:danger-triangle-bold" class="text-warning-main text-3xl mb-8"></iconify-icon>
            <p id="install-stats-message" class="text-secondary-light text-sm mb-0">Koneksi BeWell belum tersedia.</p>
          </div>
        </div>

        <div id="install-stats-content">
          {{-- Filter global (mempengaruhi seluruh modal) --}}
          <div class="bg-neutral-50 border radius-8 p-16 mb-20" id="install-stats-global-filters">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-12">
              <h6 class="mb-0 fw-semibold text-md">Filter</h6>
              <span class="text-xs text-secondary-light">Filter ini mengubah KPI, ringkasan, chart, dan daftar karyawan</span>
            </div>
            <div class="row g-3 align-items-end">
              <div class="col-6 col-md-4 col-xl-2">
                <label for="install-global-site" class="form-label text-sm fw-medium mb-6">Site</label>
                <select id="install-global-site" class="form-select form-select-sm">
                  <option value="">Semua Site</option>
                </select>
              </div>
              <div class="col-6 col-md-4 col-xl-2">
                <label for="install-global-division" class="form-label text-sm fw-medium mb-6">Divisi</label>
                <select id="install-global-division" class="form-select form-select-sm">
                  <option value="">Semua Divisi</option>
                </select>
              </div>
              <div class="col-6 col-md-4 col-xl-2">
                <label for="install-global-jabatan" class="form-label text-sm fw-medium mb-6">Jabatan</label>
                <select id="install-global-jabatan" class="form-select form-select-sm">
                  <option value="">Semua Jabatan</option>
                </select>
              </div>
              <div class="col-6 col-md-4 col-xl-2">
                <label for="install-global-company" class="form-label text-sm fw-medium mb-6">Perusahaan (Minecon)</label>
                <select id="install-global-company" class="form-select form-select-sm">
                  <option value="">Semua Minecon</option>
                </select>
              </div>
              <div class="col-6 col-md-4 col-xl-2">
                <label for="install-global-departement" class="form-label text-sm fw-medium mb-6">Departemen</label>
                <input
                  type="text"
                  id="install-global-departement"
                  class="form-control form-control-sm"
                  list="install-global-departement-options"
                  placeholder="Semua Departemen"
                  autocomplete="off"
                >
                <datalist id="install-global-departement-options"></datalist>
              </div>
              <div class="col-6 col-md-4 col-xl-2">
                <label for="install-global-install" class="form-label text-sm fw-medium mb-6">Status Install</label>
                <select id="install-global-install" class="form-select form-select-sm">
                  <option value="">Semua</option>
                  <option value="sudah">Sudah</option>
                  <option value="belum">Belum</option>
                </select>
              </div>
              <div class="col-12 col-md-4 col-xl-3">
                <div class="d-flex gap-2">
                  <button type="button" id="install-global-reset-btn" class="btn btn-sm btn-outline-secondary w-100">Reset</button>
                  <button type="button" id="install-global-apply-btn" class="btn btn-sm btn-primary-600 w-100">Filter</button>
                </div>
              </div>
            </div>
          </div>

          {{-- Global KPI --}}
          <div class="row g-3 mb-20" id="install-stats-summary">
            <div class="col-6 col-xl-3">
              <div class="install-stats-kpi-card h-100 p-16 radius-8 border bg-base">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-10">
                  <span class="text-secondary-light text-sm fw-medium">Sudah Install</span>
                  <span class="w-36-px h-36-px bg-primary-50 text-primary-600 radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                    <iconify-icon icon="solar:download-minimalistic-bold" class="text-lg"></iconify-icon>
                  </span>
                </div>
                <h4 class="fw-bold mb-0" id="install-stats-kpi-installed">0</h4>
                <span class="text-xs text-secondary-light">KPI kartu: <span id="install-stats-kpi-card-total">0</span></span>
              </div>
            </div>
            <div class="col-6 col-xl-3">
              <div class="install-stats-kpi-card h-100 p-16 radius-8 border bg-base">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-10">
                  <span class="text-secondary-light text-sm fw-medium">Belum Install</span>
                  <span class="w-36-px h-36-px bg-warning-focus text-warning-main radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                    <iconify-icon icon="solar:close-circle-bold" class="text-lg"></iconify-icon>
                  </span>
                </div>
                <h4 class="fw-bold mb-0" id="install-stats-kpi-not-installed">0</h4>
                <span class="text-xs text-secondary-light">Dari total karyawan AKTIF</span>
              </div>
            </div>
            <div class="col-6 col-xl-3">
              <div class="install-stats-kpi-card h-100 p-16 radius-8 border bg-base">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-10">
                  <span class="text-secondary-light text-sm fw-medium">Total Karyawan</span>
                  <span class="w-36-px h-36-px bg-neutral-100 text-secondary-light radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                    <iconify-icon icon="solar:users-group-rounded-bold" class="text-lg"></iconify-icon>
                  </span>
                </div>
                <h4 class="fw-bold mb-0" id="install-stats-kpi-total">0</h4>
                <span class="text-xs text-secondary-light">Status AKTIF · exclude VISITOR</span>
              </div>
            </div>
            <div class="col-6 col-xl-3">
              <div class="install-stats-kpi-card h-100 p-16 radius-8 border bg-base">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-10">
                  <span class="text-secondary-light text-sm fw-medium">Adoption Rate</span>
                  <span class="w-36-px h-36-px bg-success-focus text-success-main radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                    <iconify-icon icon="solar:chart-bold" class="text-lg"></iconify-icon>
                  </span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <h4 class="fw-bold mb-0" id="install-stats-kpi-adoption">0%</h4>
                  <span id="install-stats-kpi-adoption-badge" class="text-xs fw-medium px-8 py-2 rounded-pill d-none"></span>
                </div>
                <span class="text-xs text-secondary-light" id="install-stats-kpi-groups-hint">0 grup pada dimensi aktif</span>
              </div>
            </div>
          </div>

          {{-- Tren harian 4 minggu terakhir --}}
          <div class="border radius-8 overflow-hidden mb-24" id="install-stats-trend-section">
            <div class="px-16 py-12 border-bottom bg-neutral-50 d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div>
                <h6 class="mb-0 fw-semibold text-md">Tren Install & Penggunaan Harian</h6>
                <span class="text-xs text-secondary-light" id="install-stats-trend-subtitle">
                  4 minggu terakhir (termasuk minggu berjalan)
                </span>
              </div>
              <span class="text-xs text-secondary-light">Install baru = first signal · Penggunaan = user aktif harian</span>
            </div>
            <div class="p-16">
              <div id="install-stats-trend" style="min-height: 220px;"></div>
              <p id="install-stats-trend-empty" class="text-secondary-light text-sm mb-0 d-none text-center py-40">
                Belum ada data tren untuk rentang ini.
              </p>
            </div>
          </div>

          {{-- Summary per dimensi --}}
          <div class="mb-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 fw-semibold text-md">Ringkasan per Dimensi</h6>
            <span class="text-xs text-secondary-light">Klik kartu untuk melihat detail</span>
          </div>
          <div class="row g-3 mb-24" id="install-stats-overview"></div>

          {{-- Detail dimensi terpilih --}}
          <div class="border radius-8 overflow-hidden">
            <div class="px-16 py-12 border-bottom bg-neutral-50 d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div>
                <h6 class="mb-0 fw-semibold text-md">
                  Detail <span id="install-stats-detail-title">Site</span>
                </h6>
                <span class="text-xs text-secondary-light" id="install-stats-detail-subtitle">Perbandingan install vs belum</span>
              </div>
              <span class="bg-primary-50 text-primary-600 text-xs fw-medium px-10 py-4 rounded-pill" id="install-stats-chart-hint">Top 15 + Lainnya</span>
            </div>

            <div class="p-16">
              <div class="row g-3" id="install-stats-detail-row">
                <div class="col-lg-6">
                  <div id="install-stats-bar" style="height: 300px;"></div>
                  <p id="install-stats-chart-empty" class="text-secondary-light text-sm mb-0 d-none text-center py-40">Belum ada data untuk dimensi ini.</p>
                </div>
                <div class="col-lg-6">
                  <div class="table-responsive install-stats-table-wrap">
                    <table class="table bordered-table mb-0 align-middle" id="install-stats-table">
                      <thead>
                        <tr>
                          <th scope="col" id="install-stats-table-dim-label">Site</th>
                          <th scope="col" class="text-end">Total</th>
                          <th scope="col" class="text-end">Sudah</th>
                          <th scope="col" class="text-end">Belum</th>
                          <th scope="col" class="text-end">%</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                      <tfoot>
                        <tr class="fw-semibold">
                          <td>Total</td>
                          <td class="text-end" id="install-stats-tfoot-total">0</td>
                          <td class="text-end" id="install-stats-tfoot-installed">0</td>
                          <td class="text-end" id="install-stats-tfoot-not-installed">0</td>
                          <td class="text-end" id="install-stats-tfoot-pct">0%</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                  <p id="install-stats-table-empty" class="text-secondary-light text-sm mb-0 d-none mt-12">Belum ada data.</p>
                </div>
              </div>
            </div>
          </div>

          {{-- Detail orang (mengikuti filter global) --}}
          <div class="border radius-8 overflow-hidden mt-20" id="install-people-section">
            <div class="px-16 py-12 border-bottom bg-neutral-50 d-flex align-items-start justify-content-between flex-wrap gap-2">
              <div>
                <h6 class="mb-0 fw-semibold text-md">
                  Daftar Karyawan
                </h6>
                <span class="text-xs text-secondary-light" id="install-people-subtitle">
                  Mengikuti filter global di atas
                </span>
              </div>
              <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <span id="install-people-total-badge" class="bg-primary-50 text-primary-600 text-sm fw-medium px-12 py-2 rounded-pill">0</span>
                <a id="install-people-export-btn" href="{{ ($ajaxRoutes['installStatsExport'] ?? route('evaluasi-well.install-stats.export')) }}" class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
                  <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
                  Download Excel
                </a>
              </div>
            </div>

            <div class="p-16">
              <div class="table-responsive">
                <table id="installPeopleTable" class="table bordered-table mb-0 w-100" style="width:100%">
                  <thead>
                    <tr>
                      <th scope="col">Karyawan</th>
                      <th scope="col">Site</th>
                      <th scope="col">Perusahaan</th>
                      <th scope="col">Departemen</th>
                      <th scope="col">Jabatan</th>
                      <th scope="col">Install</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer border-top py-16 px-24">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary-600 btn-sm" id="install-stats-open-status-btn">
          Buka Status Install
        </button>
      </div>
    </div>
  </div>
</div>
