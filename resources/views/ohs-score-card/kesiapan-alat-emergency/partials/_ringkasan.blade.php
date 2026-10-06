{{-- Tab Ringkasan Kesiapan Alat Emergency. --}}
<div class="kae-overview"
     data-url="{{ route('ohs-score-card.kesiapan-alat-emergency.overview') }}"
     data-detail-url="{{ route('ohs-score-card.kesiapan-alat-emergency.detail-bulan') }}"
     data-legenda="{{ json_encode($legenda) }}">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="kae-site">Site</label>
          <select class="form-select form-select-sm radius-8 kae-filter" id="kae-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="kae-kategori">Kategori Alat</label>
          <select class="form-select form-select-sm radius-8 kae-filter" id="kae-kategori" data-column="kategori">
            <option value="">Semua Kategori</option>
            @foreach ($filterOptions['kategori'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="kae-klasifikasi">Klasifikasi</label>
          <select class="form-select form-select-sm radius-8 kae-filter" id="kae-klasifikasi" data-column="klasifikasi">
            <option value="">Semua</option>
            @foreach ($filterOptions['klasifikasi'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-6 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="kae-bulan">Bulan</label>
          <select class="form-select form-select-sm radius-8 kae-filter" id="kae-bulan" data-column="bulan_filter">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $nomor => $label)
              <option value="{{ $nomor }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-6 col-sm-6">
          <button type="button" class="btn btn-outline-secondary btn-sm w-100 radius-8" data-kae="reset">
            Reset filter
          </button>
        </div>
      </div>
      <div class="mt-12">
        <span class="text-sm text-secondary-light" data-kae="status">Memuat…</span>
      </div>
    </div>
  </div>

  {{-- Kartu ringkasan utama --}}
  <div class="row gy-4 mb-24" data-kae="kpi"></div>

  {{-- Matriks site/kategori x bulan --}}
  <div class="row gy-4 mb-24">
    <div class="col-12">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
          <div>
            <h6 class="text-lg fw-semibold mb-0">Kesiapan per Bulan</h6>
            <span class="text-sm text-secondary-light" data-kae="matrix-subtitle">
              Persentase alat siap tiap kategori di tiap site
            </span>
          </div>
          <ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 flex-nowrap"
              role="tablist" data-kae="switch">
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 active kae-switch__btn"
                      data-mode="persen">Persentase</button>
            </li>
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 kae-switch__btn"
                      data-mode="nilai">Nilai</button>
            </li>
          </ul>
        </div>
        <div class="card-body p-24">
          <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-kae="legend"></div>
          <div class="kae-matrix-wrap">
            <table class="kae-matrix" data-kae="matrix">
              <thead></thead>
              <tbody></tbody>
            </table>
          </div>
          <span class="text-xs text-secondary-light d-block mt-12">
            Penyebut tiap sel adalah seluruh alat di inventaris, jadi alat yang bulan itu
            tidak diperiksa ikut dihitung belum siap. Sel bergaris putus-putus berarti
            kelompok itu belum punya lembar periksa sama sekali pada bulan tersebut, bukan 0%.
            Klik sel untuk rinciannya.
          </span>
        </div>
      </div>
    </div>
  </div>

  {{-- Panel rincian --}}
  <div class="row gy-4 mb-24">
    <div class="col-xxl-6 col-md-6">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Per Site</h6>
          <span class="text-sm text-secondary-light">terendah lebih dulu</span>
        </div>
        <div class="card-body p-24" data-kae="per-site"></div>
      </div>
    </div>
    <div class="col-xxl-6 col-md-6">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Per Kategori Alat</h6>
          <span class="text-sm text-secondary-light">terendah lebih dulu</span>
        </div>
        <div class="card-body p-24" data-kae="per-kategori"></div>
      </div>
    </div>
  </div>

  <div class="alert alert-light border radius-8 text-sm d-none" data-kae="catatan"></div>

  {{-- Modal rincian sel --}}
  <div class="modal fade" id="kae-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content radius-8">
        <div class="modal-header">
          <h6 class="modal-title fw-semibold" data-kae="modal-judul">Rincian</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body" data-kae="modal-isi"></div>
      </div>
    </div>
  </div>
</div>
