{{-- Tab Ringkasan Coverage Area Kritis Pengawas Suptend up. --}}
<div class="cak-overview" data-url="{{ route('ohs-score-card.coverage-area-kritis.overview') }}"
     data-detail-url="{{ route('ohs-score-card.coverage-area-kritis.detail-bulan') }}">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="cak-site">Site</label>
          <select class="form-select form-select-sm radius-8 cak-filter" id="cak-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="cak-pic">PIC Lokasi</label>
          <select class="form-select form-select-sm radius-8 cak-filter" id="cak-pic" data-column="pic">
            <option value="">Semua PIC</option>
            @foreach ($filterOptions['pic'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="cak-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 cak-filter" id="cak-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-cak="reset">Reset</button>
        </div>
        <div class="col-12">
          <span class="text-sm text-secondary-light" data-cak="status">Memuat ringkasan…</span>
        </div>
        <div class="col-12 d-none" data-cak="note-wrap">
          <div class="alert alert-warning bg-warning-focus border-warning-main text-warning-main
                      radius-8 px-20 py-12 mb-0 d-flex align-items-start gap-2">
            <iconify-icon icon="solar:info-circle-outline" class="icon text-xl flex-shrink-0"></iconify-icon>
            <span class="text-sm" data-cak="note"></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Kartu ringkasan utama --}}
  <div class="row gy-4 mb-24" data-cak="kpi"></div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-8">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
          <div>
            <h6 class="text-lg fw-semibold mb-0">Capaian per Bulan</h6>
            <span class="text-sm text-secondary-light" data-cak="matrix-subtitle">
              Persentase lokasi-minggu area kritis yang dikunjungi, tiap PIC di tiap site
            </span>
          </div>
          <ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 flex-nowrap"
              role="tablist">
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 active cak-switch__btn"
                      data-mode="persen">Persentase</button>
            </li>
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 cak-switch__btn"
                      data-mode="nilai">Nilai</button>
            </li>
          </ul>
        </div>
        <div class="card-body p-24">
          <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-cak="legend"></div>
          <div class="cak-matrix-wrap">
            <table class="cak-matrix" data-cak="matrix">
              <thead></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xxl-4">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Capaian per Site</h6>
          <span class="text-sm text-secondary-light">Garis tipis menandai target {{ $target }}%</span>
        </div>
        <div class="card-body p-24" data-cak="per-site"></div>
      </div>
    </div>
  </div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-8">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Capaian per PIC Lokasi</h6>
          <span class="text-sm text-secondary-light">Diurutkan dari yang tertinggi</span>
        </div>
        <div class="card-body p-24">
          <div class="row gy-3" data-cak="per-pic"></div>
        </div>
      </div>
    </div>

    <div class="col-xxl-4">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Perlu Perhatian</h6>
          <span class="text-sm text-secondary-light">Lima capaian terendah</span>
        </div>
        <div class="card-body p-24">
          <div class="table-responsive">
            <table class="table bordered-table sm-table mb-0">
              <thead>
                <tr>
                  <th scope="col">Site &amp; PIC</th>
                  <th scope="col" class="text-center">Nilai</th>
                  <th scope="col" class="text-end">Rata-rata</th>
                </tr>
              </thead>
              <tbody data-cak="terendah"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row gy-4">
    <div class="col-12">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Tren Bulanan</h6>
          <span class="text-sm text-secondary-light">
            Coverage area kritis per site, berbobot jumlah lokasi terdaftar; garis menanjak berarti membaik
          </span>
        </div>
        <div class="card-body p-24">
          <div data-cak="chart-monthly"></div>
        </div>
      </div>
    </div>
  </div>

</div>
