@php
  $df = $dashboardFilters ?? ['site' => '', 'perusahaan' => '', 'division_group' => ''];
  $opts = $dashboardFilterOptions ?? ['sites' => [], 'companies' => [], 'division_groups' => []];
  $hasActiveDashboardFilter = ($df['site'] ?? '') !== ''
    || ($df['perusahaan'] ?? '') !== ''
    || ($df['division_group'] ?? '') !== '';
@endphp

<div class="modal fade" id="dashboardFilterModal" tabindex="-1" aria-labelledby="dashboardFilterModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content radius-12 border-0 shadow-lg">
      <form method="GET" action="{{ route('evaluasi-well.index') }}" id="dashboardFilterForm">
        <div class="modal-header border-bottom py-16 px-24">
          <div>
            <h5 class="modal-title fw-bold text-lg mb-0" id="dashboardFilterModalLabel">Filter Dashboard</h5>
            <p class="text-secondary-light text-sm mb-0 mt-4">Terapkan filter ke seluruh konten dashboard.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body p-24">
          <div class="row g-3">
            <div class="col-12">
              <label for="dashboard-filter-site" class="form-label text-sm fw-medium mb-6">Site</label>
              <select id="dashboard-filter-site" name="site" class="form-select">
                <option value="">Semua site</option>
                @foreach (($opts['sites'] ?? []) as $site)
                  <option value="{{ $site }}" @selected(($df['site'] ?? '') === $site)>{{ $site }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label for="dashboard-filter-perusahaan" class="form-label text-sm fw-medium mb-6">Perusahaan</label>
              <select id="dashboard-filter-perusahaan" name="perusahaan" class="form-select">
                <option value="">Semua perusahaan</option>
                @foreach (($opts['companies'] ?? []) as $company)
                  <option value="{{ $company }}" @selected(($df['perusahaan'] ?? '') === $company)>{{ $company }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label for="dashboard-filter-divisi" class="form-label text-sm fw-medium mb-6">Divisi</label>
              <select id="dashboard-filter-divisi" name="division_group" class="form-select">
                <option value="">Semua divisi</option>
                @foreach (($opts['division_groups'] ?? []) as $division)
                  <option value="{{ $division }}" @selected(($df['division_group'] ?? '') === $division)>{{ $division }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer border-top py-16 px-24 d-flex flex-wrap gap-2 justify-content-between">
          <a href="{{ route('evaluasi-well.index') }}" class="btn btn-outline-secondary btn-sm">
            Reset
          </a>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary-600 btn-sm px-16" id="dashboard-filter-apply-btn">
              Terapkan
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
