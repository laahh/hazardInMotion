{{--
  Tab Ringkasan "GR Seatbelt".

  Berbeda dari parameter lain di modul ini: yang diukur cacah temuan, bukan
  persentase, dan targetnya nol. Karena itu tidak ada switch Persentase/Nilai,
  dan sel bernilai 0 diwarnai hijau karena memang itu keadaan yang diinginkan.
--}}
<div class="hp-overview" data-url="{{ route('ohs-score-card.gr-seatbelt.overview') }}"
     data-detail-url="{{ route('ohs-score-card.gr-seatbelt.detail-bulan') }}">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="hp-site">Site</label>
          <select class="form-select form-select-sm radius-8 hp-filter" id="hp-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="hp-mitra">Perusahaan PIC</label>
          <select class="form-select form-select-sm radius-8 hp-filter" id="hp-mitra" data-column="mitra">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="hp-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 hp-filter" id="hp-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-hp="reset">Reset</button>
        </div>
        <div class="col-12">
          <span class="text-sm text-secondary-light" data-hp="status">Memuat ringkasan…</span>
        </div>
        <div class="col-12 d-none" data-hp="note-wrap">
          <div class="alert alert-info bg-info-focus border-info-main text-info-main
                      radius-8 px-20 py-12 mb-0 d-flex align-items-start gap-2">
            <iconify-icon icon="solar:info-circle-outline" class="icon text-xl flex-shrink-0"></iconify-icon>
            <span class="text-sm" data-hp="note"></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Kartu ringkasan utama --}}
  <div class="row gy-4 mb-24" data-hp="kpi"></div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-8">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Pelanggaran per Bulan</h6>
          <span class="text-sm text-secondary-light">
            Hanya pasangan yang pernah kedapatan; hijau berarti tidak ada pelanggaran pada bulan itu
          </span>
        </div>
        <div class="card-body p-24">
          <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-hp="legend"></div>
          <div class="hp-matrix-wrap">
            <table class="hp-matrix" data-hp="matrix">
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
          <h6 class="text-lg fw-semibold mb-0">Pelanggaran per Site</h6>
        </div>
        <div class="card-body p-24" data-hp="per-site"></div>
      </div>
    </div>
  </div>

  <div class="row gy-4">
    <div class="col-xxl-5">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Perusahaan dengan Pelanggaran</h6>
          <span class="text-sm text-secondary-light">Diurutkan dari yang terbanyak</span>
        </div>
        <div class="card-body p-24">
          <div class="table-responsive">
            <table class="table bordered-table sm-table mb-0">
              <thead>
                <tr>
                  <th scope="col">Perusahaan</th>
                  <th scope="col" class="text-end">Pelanggaran</th>
                  <th scope="col" class="text-end">Porsi</th>
                </tr>
              </thead>
              <tbody data-hp="per-mitra"></tbody>
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
          <div data-hp="chart-monthly"></div>
        </div>
      </div>
    </div>
  </div>

</div>
