{{--
  Tab Ringkasan "Deviasi Rekayasa Engineering Overspeed".

  Yang diukur cacah PELANGGAR (SID karyawan berbeda), bukan persentase, dan
  targetnya nol. Karena itu tidak ada switch Persentase/Nilai, dan sel bernilai
  0 diwarnai hijau karena memang itu keadaan yang diinginkan.

  PIC approval punya panelnya sendiri, bukan jadi baris matriks: tiap PIC
  terikat tepat satu site, jadi memecah matriks dengannya hanya memanjangkan
  tabel tanpa menambah informasi.
--}}
<div class="osp-overview" data-url="{{ route('ohs-score-card.pelanggaran-overspeed.overview') }}"
     data-detail-url="{{ route('ohs-score-card.pelanggaran-overspeed.detail-bulan') }}">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="osp-site">Site</label>
          <select class="form-select form-select-sm radius-8 osp-filter" id="osp-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="osp-pic">PIC Approval</label>
          <select class="form-select form-select-sm radius-8 osp-filter" id="osp-pic" data-column="pic">
            <option value="">Semua PIC</option>
            @foreach ($filterOptions['pic'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="osp-mitra">Perusahaan</label>
          <select class="form-select form-select-sm radius-8 osp-filter" id="osp-mitra" data-column="mitra">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="osp-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 osp-filter" id="osp-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-osp="reset">Reset</button>
        </div>
        <div class="col-12">
          <span class="text-sm text-secondary-light" data-osp="status">Memuat ringkasan…</span>
        </div>
        <div class="col-12 d-none" data-osp="note-wrap">
          <div class="alert alert-info bg-info-focus border-info-main text-info-main
                      radius-8 px-20 py-12 mb-0 d-flex align-items-start gap-2">
            <iconify-icon icon="solar:info-circle-outline" class="icon text-xl flex-shrink-0"></iconify-icon>
            <span class="text-sm" data-osp="note"></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Kartu ringkasan utama --}}
  <div class="row gy-4 mb-24" data-osp="kpi"></div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-8">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
          <div>
            <h6 class="text-lg fw-semibold mb-0">Pelanggar per Bulan</h6>
            <span class="text-sm text-secondary-light">
            Hanya pasangan yang pernah kedapatan; hijau berarti tidak ada pelanggar pada bulan itu
            </span>
          </div>
          <ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 flex-nowrap"
              role="tablist">
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 active osp-switch__btn"
                      data-mode="jumlah">Jumlah</button>
            </li>
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 osp-switch__btn"
                      data-mode="nilai">Nilai</button>
            </li>
          </ul>
        </div>
        <div class="card-body p-24">
          <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-osp="legend"></div>
          <div class="osp-matrix-wrap">
            <table class="osp-matrix" data-osp="matrix">
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
          <h6 class="text-lg fw-semibold mb-0">Pelanggar per Site</h6>
        </div>
        <div class="card-body p-24" data-osp="per-site"></div>
      </div>
    </div>
  </div>

  <div class="row gy-4 mb-24">
    <div class="col-12">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Pelanggar per PIC Approval</h6>
          <span class="text-sm text-secondary-light">
            Tiap PIC bertugas di satu site, jadi ini rincian di dalam site — bukan pemotongan baru
          </span>
        </div>
        <div class="card-body p-24">
          <div class="row gy-3" data-osp="per-pic"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row gy-4">
    <div class="col-xxl-5">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Perusahaan dengan Pelanggar</h6>
          <span class="text-sm text-secondary-light">Diurutkan dari yang terbanyak</span>
        </div>
        <div class="card-body p-24">
          <div class="table-responsive">
            <table class="table bordered-table sm-table mb-0">
              <thead>
                <tr>
                  <th scope="col">Perusahaan</th>
                  <th scope="col" class="text-end">Pelanggar</th>
                  <th scope="col" class="text-end">Porsi</th>
                </tr>
              </thead>
              <tbody data-osp="per-mitra"></tbody>
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
          <div data-osp="chart-monthly"></div>
        </div>
      </div>
    </div>
  </div>

</div>
