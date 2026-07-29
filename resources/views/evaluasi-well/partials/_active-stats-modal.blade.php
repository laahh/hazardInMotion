{{-- Modal detail statistik Total User Aktif --}}
<div class="modal fade" id="activeStatsModal" tabindex="-1" aria-labelledby="activeStatsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content radius-8 border-0 shadow-lg">
      <div class="modal-header border-bottom py-16 px-24">
        <div class="min-w-0 pe-12">
          <h5 class="modal-title fw-bold text-lg mb-4" id="activeStatsModalLabel">Detail Total User Aktif</h5>
          <p id="active-stats-footnote" class="text-sm text-secondary-light mb-0">
            User aktif (luas) = food photo / workout / komunitas / Main Bareng. Evaluasi = food + workout.
          </p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body p-24 position-relative">
        <div id="active-stats-loading" class="position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center bg-base" style="z-index: 5; opacity: 0.92;">
          <div class="text-center">
            <div class="spinner-border text-success-main" role="status" aria-hidden="true"></div>
            <p class="text-sm text-secondary-light mt-12 mb-0">Memuat statistik…</p>
          </div>
        </div>

        <div id="active-stats-unavailable" class="d-none">
          <div class="border radius-8 p-20 text-center bg-neutral-50">
            <iconify-icon icon="solar:danger-triangle-bold" class="text-warning-main text-3xl mb-8"></iconify-icon>
            <p id="active-stats-message" class="text-secondary-light text-sm mb-0">Koneksi BeWell belum tersedia.</p>
          </div>
        </div>

        <div id="active-stats-content">
          {{-- Week picker + trend --}}
          <div class="row g-3 mb-20 align-items-stretch">
            <div class="col-lg-4">
              <div class="active-stats-kpi-card h-100 p-16 radius-8 border bg-base">
                <label for="active-stats-week" class="form-label text-sm fw-medium mb-6">Pilih Minggu</label>
                <select id="active-stats-week" class="form-select form-select-sm mb-12"></select>
                <div class="text-xs text-secondary-light" id="active-stats-week-label">—</div>
              </div>
            </div>
            <div class="col-lg-8">
              <div class="active-stats-kpi-card h-100 p-16 radius-8 border bg-base">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-8">
                  <h6 class="mb-0 fw-semibold text-md">Tren User Aktif (12 Minggu)</h6>
                  <span class="text-xs text-secondary-light">Definisi aktif luas (KPI kartu)</span>
                </div>
                <div id="active-stats-trend" style="height: 140px;"></div>
                <p id="active-stats-trend-empty" class="text-secondary-light text-sm mb-0 d-none text-center py-20">Belum ada data tren.</p>
              </div>
            </div>
          </div>

          {{-- Global KPI --}}
          <div class="row g-3 mb-20" id="active-stats-summary">
            <div class="col-6 col-xl-3">
              <div class="active-stats-kpi-card h-100 p-16 radius-8 border bg-base">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-10">
                  <span class="text-secondary-light text-sm fw-medium">User Aktif</span>
                  <span class="w-36-px h-36-px bg-success-focus text-success-main radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                    <iconify-icon icon="mingcute:user-follow-fill" class="text-lg"></iconify-icon>
                  </span>
                </div>
                <h4 class="fw-bold mb-0" id="active-stats-kpi-active">0</h4>
                <span class="text-xs text-secondary-light">KPI kartu: <span id="active-stats-kpi-card-total">0</span></span>
              </div>
            </div>
            <div class="col-6 col-xl-3">
              <div class="active-stats-kpi-card h-100 p-16 radius-8 border bg-base">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-10">
                  <span class="text-secondary-light text-sm fw-medium">Eval. Makanan</span>
                  <span class="w-36-px h-36-px bg-primary-50 text-primary-600 radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                    <iconify-icon icon="solar:chef-hat-bold" class="text-lg"></iconify-icon>
                  </span>
                </div>
                <h4 class="fw-bold mb-0" id="active-stats-kpi-food">0</h4>
                <span class="text-xs text-secondary-light">Upload photo minggu ini</span>
              </div>
            </div>
            <div class="col-6 col-xl-3">
              <div class="active-stats-kpi-card h-100 p-16 radius-8 border bg-base">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-10">
                  <span class="text-secondary-light text-sm fw-medium">Eval. Olahraga</span>
                  <span class="w-36-px h-36-px bg-warning-focus text-warning-main radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                    <iconify-icon icon="solar:running-round-bold" class="text-lg"></iconify-icon>
                  </span>
                </div>
                <h4 class="fw-bold mb-0" id="active-stats-kpi-workout">0</h4>
                <span class="text-xs text-secondary-light">Workout minggu ini</span>
              </div>
            </div>
            <div class="col-6 col-xl-3">
              <div class="active-stats-kpi-card h-100 p-16 radius-8 border bg-base">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-10">
                  <span class="text-secondary-light text-sm fw-medium">Total Evaluasi</span>
                  <span class="w-36-px h-36-px bg-info-focus text-info-main radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                    <iconify-icon icon="solar:chart-bold" class="text-lg"></iconify-icon>
                  </span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <h4 class="fw-bold mb-0" id="active-stats-kpi-total-evals">0</h4>
                  <span id="active-stats-kpi-increase-badge" class="text-xs fw-medium px-8 py-2 rounded-pill bg-success-focus text-success-main">+0</span>
                </div>
                <span class="text-xs text-secondary-light" id="active-stats-kpi-groups-hint">0 grup pada dimensi aktif</span>
              </div>
            </div>
          </div>

          {{-- Summary per dimensi --}}
          <div class="mb-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 fw-semibold text-md">Ringkasan per Dimensi</h6>
            <span class="text-xs text-secondary-light">Klik kartu untuk melihat detail</span>
          </div>
          <div class="row g-3 mb-24" id="active-stats-overview"></div>

          {{-- Detail dimensi terpilih --}}
          <div class="border radius-8 overflow-hidden">
            <div class="px-16 py-12 border-bottom bg-neutral-50 d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div>
                <h6 class="mb-0 fw-semibold text-md">
                  Detail <span id="active-stats-detail-title">Site</span>
                </h6>
                <span class="text-xs text-secondary-light" id="active-stats-detail-subtitle">Ranking user aktif & evaluasi</span>
              </div>
              <span class="bg-success-focus text-success-main text-xs fw-medium px-10 py-4 rounded-pill" id="active-stats-chart-hint">Top 15 + Lainnya</span>
            </div>

            <div class="p-16">
              <div class="row g-3" id="active-stats-detail-row">
                <div class="col-lg-6">
                  <div id="active-stats-bar" style="height: 300px;"></div>
                  <p id="active-stats-chart-empty" class="text-secondary-light text-sm mb-0 d-none text-center py-40">Belum ada data untuk dimensi ini.</p>
                </div>
                <div class="col-lg-6">
                  <div class="table-responsive active-stats-table-wrap">
                    <table class="table bordered-table mb-0 align-middle" id="active-stats-table">
                      <thead>
                        <tr>
                          <th scope="col" id="active-stats-table-dim-label">Site</th>
                          <th scope="col" class="text-end">Aktif</th>
                          <th scope="col" class="text-end">Food</th>
                          <th scope="col" class="text-end">Workout</th>
                          <th scope="col" class="text-end">Eval</th>
                          <th scope="col" class="text-end">%</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                      <tfoot>
                        <tr class="fw-semibold">
                          <td>Total</td>
                          <td class="text-end" id="active-stats-tfoot-active">0</td>
                          <td class="text-end" id="active-stats-tfoot-food">0</td>
                          <td class="text-end" id="active-stats-tfoot-workout">0</td>
                          <td class="text-end" id="active-stats-tfoot-evals">0</td>
                          <td class="text-end" id="active-stats-tfoot-pct">100%</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                  <p id="active-stats-table-empty" class="text-secondary-light text-sm mb-0 d-none mt-12">Belum ada data.</p>
                </div>
              </div>
            </div>
          </div>

          {{-- Leaderboard individu --}}
          <div class="border radius-8 overflow-hidden mt-20" id="active-leaderboard-section">
            <div class="px-16 py-12 border-bottom bg-neutral-50 d-flex align-items-start justify-content-between flex-wrap gap-2">
              <div>
                <h6 class="mb-0 fw-semibold text-md">Leaderboard Individu</h6>
                <span class="text-xs text-secondary-light">
                  Top 20 paling aktif berdasarkan total evaluasi (food + workout) minggu terpilih
                </span>
              </div>
              <span id="active-leaderboard-total-badge" class="bg-success-focus text-success-main text-sm fw-medium px-12 py-2 rounded-pill">0</span>
            </div>

            <div class="p-16">
              <div class="table-responsive">
                <table class="table bordered-table mb-0 align-middle" id="active-stats-leaderboard">
                  <thead>
                    <tr>
                      <th scope="col" style="width:6%">#</th>
                      <th scope="col">Nama</th>
                      <th scope="col">Site</th>
                      <th scope="col">Perusahaan</th>
                      <th scope="col">Jabatan</th>
                      <th scope="col" class="text-end">Food</th>
                      <th scope="col" class="text-end">Workout</th>
                      <th scope="col" class="text-end">Eval</th>
                      <th scope="col" class="text-center">Aktif</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
              <p id="active-stats-leaderboard-empty" class="text-secondary-light text-sm mb-0 d-none mt-12">Belum ada data leaderboard.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer border-top py-16 px-24">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary-600 btn-sm" id="active-stats-open-status-btn">
          Buka Status Install (User Aktif)
        </button>
      </div>
    </div>
  </div>
</div>
