{{--
  Tab Ringkasan Rasio Kelayakan Kerja untuk satu kumpulan data.

  Elemen ditandai lewat atribut data-rkk, bukan id global, supaya partial ini
  bisa dipasang dua kali di halaman yang sama tanpa id bentrok.

  $ds: satu entri dari $datasets pada RasioKelayakanKerjaController::index().
--}}
<div class="rkk-overview" data-dataset="{{ $ds['slug'] }}"
     data-url="{{ route('ohs-score-card.rasio-kelayakan-kerja.overview', $ds['slug']) }}"
     data-detail-url="{{ route('ohs-score-card.rasio-kelayakan-kerja.detail-bulan', $ds['slug']) }}">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="rkk-{{ $ds['slug'] }}-site">Site</label>
          <select class="form-select form-select-sm radius-8 rkk-filter"
                  id="rkk-{{ $ds['slug'] }}-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($ds['filterOptions']['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>

        @if ($ds['has_mitra'])
          <div class="col-xxl-4 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8"
                   for="rkk-{{ $ds['slug'] }}-mitra">Perusahaan</label>
            <select class="form-select form-select-sm radius-8 rkk-filter"
                    id="rkk-{{ $ds['slug'] }}-mitra" data-column="mitra">
              <option value="">Semua Perusahaan</option>
              @foreach ($ds['filterOptions']['mitra'] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>
        @endif

        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="rkk-{{ $ds['slug'] }}-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 rkk-filter"
                  id="rkk-{{ $ds['slug'] }}-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($ds['monthOptions'] as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-rkk="reset">Reset</button>
        </div>
        <div class="col-12">
          <span class="text-sm text-secondary-light" data-rkk="status">Memuat ringkasan…</span>
        </div>
        <div class="col-12 d-none" data-rkk="note-wrap">
          <div class="alert alert-info bg-info-focus border-info-main text-info-main
                      radius-8 px-20 py-12 mb-0 d-flex align-items-start gap-2">
            <iconify-icon icon="solar:info-circle-outline" class="icon text-xl flex-shrink-0"></iconify-icon>
            <span class="text-sm" data-rkk="note"></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Kartu ringkasan utama --}}
  <div class="row gy-4 mb-24" data-rkk="kpi"></div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-8">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
          <div>
            <h6 class="text-lg fw-semibold mb-0">Capaian per Bulan</h6>
            <span class="text-sm text-secondary-light" data-rkk="matrix-subtitle">
              Persentase pekerja dengan hasil MCU Fit
            </span>
          </div>
          <ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 flex-nowrap"
              role="tablist">
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 active rkk-switch__btn"
                      data-mode="persen">Persentase</button>
            </li>
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 rkk-switch__btn"
                      data-mode="nilai">Nilai</button>
            </li>
          </ul>
        </div>
        <div class="card-body p-24">
          <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-rkk="legend"></div>
          <div class="rkk-matrix-wrap">
            <table class="rkk-matrix" data-rkk="matrix">
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
        <div class="card-body p-24" data-rkk="per-site"></div>
      </div>
    </div>
  </div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-7">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0" data-rkk="hasil-judul">Hasil MCU</h6>
          <span class="text-sm text-secondary-light" data-rkk="hasil-sub">
            Dari {{ $ds['detail_table'] ?? 'tabel rincian' }} — setahun penuh, tidak ikut filter bulan
          </span>
        </div>
        <div class="card-body p-24">
          <div data-rkk="chart-hasil"></div>
        </div>
      </div>
    </div>

    <div class="col-xxl-5">
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
                  <th scope="col" data-rkk="terendah-kol1">Site</th>
                  <th scope="col" class="text-center">Nilai</th>
                  <th scope="col" class="text-end">Rata-rata</th>
                </tr>
              </thead>
              <tbody data-rkk="terendah"></tbody>
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
          <span class="text-sm text-secondary-light" data-rkk="monthly-sub">
            Persentase MCU Fit; garis yang menanjak berarti membaik
          </span>
        </div>
        <div class="card-body p-24">
          <div data-rkk="chart-monthly"></div>
        </div>
      </div>
    </div>
  </div>

</div>
