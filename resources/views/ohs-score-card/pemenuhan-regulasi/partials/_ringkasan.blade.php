{{-- Tab Ringkasan Pemenuhan Regulasi (regulatory_compliance_summary). --}}
<div class="prg-overview"
     data-url="{{ route('ohs-score-card.pemenuhan-regulasi.overview') }}"
     data-detail-url="{{ route('ohs-score-card.pemenuhan-regulasi.detail-sel') }}"
     data-legenda="{{ json_encode($legenda) }}">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="prg-site">Site</label>
          <select class="form-select form-select-sm radius-8 prg-filter" id="prg-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="prg-mitra">Perusahaan</label>
          <select class="form-select form-select-sm radius-8 prg-filter" id="prg-mitra" data-column="mitra">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="prg-sektor">Sektor</label>
          <select class="form-select form-select-sm radius-8 prg-filter" id="prg-sektor" data-column="sektor">
            <option value="">Semua Sektor</option>
            @foreach ($sektor as $kunci => $label)
              <option value="{{ $kunci }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-12 col-sm-6">
          <button type="button" class="btn btn-outline-secondary btn-sm w-100 radius-8" data-prg="reset">
            Reset filter
          </button>
        </div>
      </div>
      <div class="mt-12">
        <span class="text-sm text-secondary-light" data-prg="status">Memuat…</span>
      </div>
    </div>
  </div>

  {{-- Kartu ringkasan utama --}}
  <div class="row gy-4 mb-24" data-prg="kpi"></div>

  {{-- Matriks site/perusahaan x sektor --}}
  <div class="row gy-4 mb-24">
    <div class="col-12">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Kepatuhan per Sektor</h6>
          <span class="text-sm text-secondary-light">
            Kewajiban yang sudah dipenuhi dibagi seluruh kewajiban, tiap perusahaan di tiap site
          </span>
        </div>
        <div class="card-body p-24">
          <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-prg="legend"></div>
          <div class="prg-matrix-wrap">
            <table class="prg-matrix" data-prg="matrix">
              <thead></thead>
              <tbody></tbody>
            </table>
          </div>
          <span class="text-xs text-secondary-light d-block mt-12">
            Kolom "Semua Sektor" adalah rata-rata tertimbang — seluruh kewajiban dijumlahkan
            dulu, baru dibagi, jadi sektor berisi tiga kewajiban tidak dihitung sama besar
            dengan sektor berisi tiga ratus. Sel bergaris putus-putus berarti sektor itu tidak
            ditugaskan ke kombinasi tersebut, bukan 0%. Klik sel untuk rinciannya.
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
        <div class="card-body p-24" data-prg="per-site"></div>
      </div>
    </div>
    <div class="col-xxl-4 col-md-6">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Per Perusahaan</h6>
          <span class="text-sm text-secondary-light">terendah lebih dulu</span>
        </div>
        <div class="card-body p-24" data-prg="per-mitra"></div>
      </div>
    </div>
    <div class="col-xxl-4 col-md-12">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Per Sektor</h6>
          <span class="text-sm text-secondary-light">terendah lebih dulu</span>
        </div>
        <div class="card-body p-24" data-prg="per-sektor"></div>
      </div>
    </div>
  </div>

  <div class="alert alert-light border radius-8 text-sm d-none" data-prg="catatan"></div>

  {{-- Modal rincian sel --}}
  <div class="modal fade" id="prg-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content radius-8">
        <div class="modal-header">
          <h6 class="modal-title fw-semibold" data-prg="modal-judul">Rincian</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body" data-prg="modal-isi"></div>
      </div>
    </div>
  </div>
</div>
