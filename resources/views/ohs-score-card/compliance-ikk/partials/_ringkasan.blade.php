{{-- Tab Ringkasan Kesesuaian Implementasi IKK. --}}
<div class="ikk-overview" data-url="{{ route('ohs-score-card.compliance-ikk.overview') }}"
     data-detail-url="{{ route('ohs-score-card.compliance-ikk.detail-bulan') }}">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="ikk-site">Site</label>
          <select class="form-select form-select-sm radius-8 ikk-filter" id="ikk-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="ikk-pic">Perusahaan</label>
          <select class="form-select form-select-sm radius-8 ikk-filter" id="ikk-pic" data-column="pic">
            <option value="">Semua PIC</option>
            @foreach ($filterOptions['pic'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="ikk-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 ikk-filter" id="ikk-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-ikk="reset">Reset</button>
        </div>
        <div class="col-12">
          <span class="text-sm text-secondary-light" data-ikk="status">Memuat ringkasan…</span>
        </div>
        <div class="col-12 d-none" data-ikk="note-wrap">
          <div class="alert alert-warning bg-warning-focus border-warning-main text-warning-main
                      radius-8 px-20 py-12 mb-0 d-flex align-items-start gap-2">
            <iconify-icon icon="solar:info-circle-outline" class="icon text-xl flex-shrink-0"></iconify-icon>
            <span class="text-sm" data-ikk="note"></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Kartu ringkasan utama --}}
  <div class="row gy-4 mb-24" data-ikk="kpi"></div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-8">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
          <div>
            <h6 class="text-lg fw-semibold mb-0">Capaian per Bulan</h6>
            <span class="text-sm text-secondary-light" data-ikk="matrix-subtitle">
              Persentase IPK yang sudah ber-OKK, tiap perusahaan di tiap site
            </span>
          </div>
          <ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 flex-nowrap"
              role="tablist">
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 active ikk-switch__btn"
                      data-mode="persen">Persentase</button>
            </li>
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 ikk-switch__btn"
                      data-mode="nilai">Nilai</button>
            </li>
          </ul>
        </div>
        <div class="card-body p-24">
          <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-ikk="legend"></div>
          <div class="ikk-matrix-wrap">
            <table class="ikk-matrix" data-ikk="matrix">
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
        <div class="card-body p-24" data-ikk="per-site"></div>
      </div>
    </div>
  </div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-8">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Capaian per Perusahaan</h6>
          <span class="text-sm text-secondary-light">Diurutkan dari yang tertinggi</span>
        </div>
        <div class="card-body p-24">
          <div class="row gy-3" data-ikk="per-pic"></div>
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
              <tbody data-ikk="terendah"></tbody>
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
            Kesesuaian IKK per site, berbobot jumlah IPK; garis menanjak berarti membaik
          </span>
        </div>
        <div class="card-body p-24">
          <div data-ikk="chart-monthly"></div>
        </div>
      </div>
    </div>
  </div>

</div>
