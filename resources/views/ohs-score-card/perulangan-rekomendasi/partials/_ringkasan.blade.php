{{--
  Tab Ringkasan "Perulangan rekomendasi hasil investigasi".

  Berbeda dari parameter lain di modul ini: yang diukur cacah perulangan, bukan
  persentase, dan targetnya nol. Karena itu tidak ada switch Persentase/Nilai,
  dan sel bernilai 0 diwarnai hijau karena memang itu keadaan yang diinginkan.
--}}
<div class="rek-overview" data-url="{{ route('ohs-score-card.perulangan-rekomendasi.overview') }}"
     data-detail-url="{{ route('ohs-score-card.perulangan-rekomendasi.detail-bulan') }}">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="rek-site">Site</label>
          <select class="form-select form-select-sm radius-8 rek-filter" id="rek-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="rek-mitra">Perusahaan PIC</label>
          <select class="form-select form-select-sm radius-8 rek-filter" id="rek-mitra" data-column="mitra">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="gap-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 rek-filter" id="gap-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-rek="reset">Reset</button>
        </div>
        <div class="col-12">
          <span class="text-sm text-secondary-light" data-rek="status">Memuat ringkasan…</span>
        </div>
        <div class="col-12 d-none" data-rek="note-wrap">
          <div class="alert alert-info bg-info-focus border-info-main text-info-main
                      radius-8 px-20 py-12 mb-0 d-flex align-items-start gap-2">
            <iconify-icon icon="solar:info-circle-outline" class="icon text-xl flex-shrink-0"></iconify-icon>
            <span class="text-sm" data-rek="note"></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Kartu ringkasan utama --}}
  <div class="row gy-4 mb-24" data-rek="kpi"></div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-8">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
          <div>
            <h6 class="text-lg fw-semibold mb-0">Perulangan per Bulan</h6>
            <span class="text-sm text-secondary-light">
            Cacah perulangan dari {{ $tabel_ringkasan }}; hijau berarti tidak ada yang terulang bulan itu
            </span>
          </div>
          <ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 flex-nowrap"
              role="tablist">
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 active rek-switch__btn"
                      data-mode="jumlah">Jumlah</button>
            </li>
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 rek-switch__btn"
                      data-mode="nilai">Nilai</button>
            </li>
          </ul>
        </div>
        <div class="card-body p-24">
          <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-rek="legend"></div>
          <div class="rek-matrix-wrap">
            <table class="rek-matrix" data-rek="matrix">
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
          <h6 class="text-lg fw-semibold mb-0">Perulangan per Site</h6>
        </div>
        <div class="card-body p-24" data-rek="per-site"></div>
      </div>
    </div>
  </div>

  <div class="row gy-4">
    <div class="col-xxl-5">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Perusahaan dengan Perulangan</h6>
          <span class="text-sm text-secondary-light">Diurutkan dari yang terbanyak</span>
        </div>
        <div class="card-body p-24">
          <div class="table-responsive">
            <table class="table bordered-table sm-table mb-0">
              <thead>
                <tr>
                  <th scope="col">Perusahaan</th>
                  <th scope="col" class="text-end">Perulangan</th>
                  <th scope="col" class="text-end">Porsi</th>
                </tr>
              </thead>
              <tbody data-rek="per-mitra"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xxl-7">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Tren Bulanan</h6>
          <span class="text-sm text-secondary-light">
            Garis yang menukik ke nol berarti membaik; nol adalah targetnya
          </span>
        </div>
        <div class="card-body p-24">
          <div data-rek="chart-monthly"></div>
        </div>
      </div>
    </div>
  </div>

</div>
