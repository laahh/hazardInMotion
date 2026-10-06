{{-- Tab Ringkasan Laporan Perizinan Usaha Jasa. --}}
<div class="lpu-overview"
     data-url="{{ route('ohs-score-card.laporan-perizinan-usaha-jasa.overview') }}"
     data-detail-url="{{ route('ohs-score-card.laporan-perizinan-usaha-jasa.detail-bulan') }}"
     data-legenda="{{ json_encode($legenda) }}">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="lpu-site">Site</label>
          <select class="form-select form-select-sm radius-8 lpu-filter" id="lpu-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="lpu-mitra">Main Contractor</label>
          <select class="form-select form-select-sm radius-8 lpu-filter" id="lpu-mitra" data-column="mitra">
            <option value="">Semua Main Contractor</option>
            @foreach ($filterOptions['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="lpu-bulan">Bulan</label>
          <select class="form-select form-select-sm radius-8 lpu-filter" id="lpu-bulan" data-column="bulan_filter">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $nomor => $label)
              <option value="{{ $nomor }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-12 col-sm-6">
          <button type="button" class="btn btn-outline-secondary btn-sm w-100 radius-8" data-lpu="reset">
            Reset filter
          </button>
        </div>
      </div>
      <div class="mt-12">
        <span class="text-sm text-secondary-light" data-lpu="status">Memuat…</span>
      </div>
    </div>
  </div>

  {{-- Kartu ringkasan utama --}}
  <div class="row gy-4 mb-24" data-lpu="kpi"></div>

  {{-- Matriks site/main contractor x bulan --}}
  <div class="row gy-4 mb-24">
    <div class="col-12">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Deviasi per Bulan</h6>
          <span class="text-sm text-secondary-light">
            Subkontraktor yang menyimpang dibagi seluruh subkontraktor — 0% adalah hasil terbaik
          </span>
        </div>
        <div class="card-body p-24">
          <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-lpu="legend"></div>
          <div class="lpu-matrix-wrap">
            <table class="lpu-matrix" data-lpu="matrix">
              <thead></thead>
              <tbody></tbody>
            </table>
          </div>
          <span class="text-xs text-secondary-light d-block mt-12">
            <strong>Hijau berarti nol deviasi</strong>, bukan nol capaian — arah parameter ini
            terbalik dari halaman kepatuhan, dan barisnya diurutkan dari deviasi terbesar.
            Kolom "Rata" adalah rata-rata tertimbang: seluruh deviasi dan seluruh pemeriksaan
            dijumlahkan dulu, baru dibagi. Sel bergaris putus-putus berarti bulan itu belum
            terdata, bukan 0%. Klik sel untuk rinciannya.
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
          <span class="text-sm text-secondary-light">deviasi terbesar lebih dulu</span>
        </div>
        <div class="card-body p-24" data-lpu="per-site"></div>
      </div>
    </div>
    <div class="col-xxl-4 col-md-6">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Per Main Contractor</h6>
          <span class="text-sm text-secondary-light">deviasi terbesar lebih dulu</span>
        </div>
        <div class="card-body p-24" data-lpu="per-mitra"></div>
      </div>
    </div>
    <div class="col-xxl-4 col-md-12">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Deviasi per Bulan</h6>
          <span class="text-sm text-secondary-light">seluruh site digabung</span>
        </div>
        <div class="card-body p-24" data-lpu="per-bulan"></div>
      </div>
    </div>
  </div>

  <div class="alert alert-light border radius-8 text-sm d-none" data-lpu="catatan"></div>

  {{-- Modal rincian sel --}}
  <div class="modal fade" id="lpu-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content radius-8">
        <div class="modal-header">
          <h6 class="modal-title fw-semibold" data-lpu="modal-judul">Rincian</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body" data-lpu="modal-isi"></div>
      </div>
    </div>
  </div>
</div>
