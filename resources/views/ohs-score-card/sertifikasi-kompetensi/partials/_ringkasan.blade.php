{{-- Tab Ringkasan Pemenuhan Sertifikasi (Pengawas / Tenaga Teknis). --}}
<div class="skp-overview"
     data-url="{{ route('ohs-score-card.' . $slug . '.overview') }}"
     data-detail-url="{{ route('ohs-score-card.' . $slug . '.detail-sel') }}">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="skp-site">Site</label>
          <select class="form-select form-select-sm radius-8 skp-filter" id="skp-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="skp-perusahaan">Perusahaan</label>
          <select class="form-select form-select-sm radius-8 skp-filter" id="skp-perusahaan" data-column="perusahaan">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['perusahaan'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="skp-izin">Izin kerja</label>
          <select class="form-select form-select-sm radius-8 skp-filter" id="skp-izin" data-column="izin">
            <option value="">Semua Izin Kerja</option>
            @foreach ($filterOptions['izin'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-12 col-sm-6">
          <button type="button" class="btn btn-outline-secondary btn-sm w-100 radius-8" data-skp="reset">
            Reset filter
          </button>
        </div>
      </div>
      <div class="mt-12">
        <span class="text-sm text-secondary-light" data-skp="status">Memuat…</span>
      </div>
    </div>
  </div>

  {{-- Kartu ringkasan utama --}}
  <div class="row gy-4 mb-24" data-skp="kpi"></div>

  {{-- Matriks site x perusahaan --}}
  <div class="row gy-4 mb-24">
    <div class="col-12">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
          <div>
            <h6 class="text-lg fw-semibold mb-0">Capaian per Perusahaan</h6>
            <span class="text-sm text-secondary-light" data-skp="matrix-subtitle">
              Persentase pemenuhan sertifikasi, tiap perusahaan di tiap site
            </span>
          </div>
          <ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 flex-nowrap"
              role="tablist">
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 active skp-switch__btn"
                      data-mode="persen">Persentase</button>
            </li>
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 skp-switch__btn"
                      data-mode="nilai">Nilai</button>
            </li>
          </ul>
        </div>
        <div class="card-body p-24">
          <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-skp="legend"></div>
          <div class="skp-matrix-wrap">
            <table class="skp-matrix" data-skp="matrix">
              <thead></thead>
              <tbody></tbody>
            </table>
          </div>
          <span class="text-xs text-secondary-light d-block mt-12">
            Angka kecil di tiap sel adalah jumlah bersertifikat dibagi total orang. Klik sel untuk melihat daftar orangnya.
          </span>
        </div>
      </div>
    </div>
  </div>

  {{-- Panel rincian --}}
  <div class="row gy-4 mb-24">
    <div class="col-xxl-4 col-md-6">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Per Site</h6>
          <span class="text-sm text-secondary-light">terendah lebih dulu</span>
        </div>
        <div class="card-body p-24" data-skp="per-site"></div>
      </div>
    </div>
    <div class="col-xxl-4 col-md-6">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Per Perusahaan</h6>
          <span class="text-sm text-secondary-light">terendah lebih dulu</span>
        </div>
        <div class="card-body p-24" data-skp="per-mitra"></div>
      </div>
    </div>
    <div class="col-xxl-4 col-md-12">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Per Izin Kerja</h6>
          <span class="text-sm text-secondary-light">12 izin kerja dengan orang terbanyak</span>
        </div>
        <div class="card-body p-24" data-skp="per-izin"></div>
      </div>
    </div>
  </div>

  <div class="alert alert-light border radius-8 text-sm" data-skp="catatan"></div>

  {{-- Modal rincian sel --}}
  <div class="modal fade" id="skp-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content radius-8">
        <div class="modal-header">
          <h6 class="modal-title fw-semibold" data-skp="modal-judul">Rincian</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body" data-skp="modal-isi"></div>
      </div>
    </div>
  </div>
</div>
