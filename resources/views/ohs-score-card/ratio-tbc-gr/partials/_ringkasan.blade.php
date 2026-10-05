{{--
  Tab Ringkasan untuk satu kumpulan data (minecon / subcon).

  Semua elemen ditandai lewat atribut data-ov, bukan id global, supaya partial
  ini bisa dipasang dua kali di halaman yang sama tanpa id bentrok.

  $ds: satu entri dari $datasets pada RatioTbcGrController::index().
--}}
<div class="osc-overview" data-dataset="{{ $ds['slug'] }}"
     data-url="{{ route('ohs-score-card.ratio-tbc-gr.overview', $ds['slug']) }}"
     data-detail-url="{{ route('ohs-score-card.ratio-tbc-gr.detail-bulan', $ds['slug']) }}">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="ov-{{ $ds['slug'] }}-site">Site</label>
          <select class="form-select form-select-sm radius-8 ov-filter"
                  id="ov-{{ $ds['slug'] }}-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($ds['filterOptions']['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>

        @if ($ds['summary_has_mitra'])
          {{-- Ringkasan subcon tidak punya kolom perusahaan, jadi filter ini
               hanya ditawarkan pada minecon. --}}
          <div class="col-xxl-4 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8"
                   for="ov-{{ $ds['slug'] }}-mitra">Perusahaan</label>
            <select class="form-select form-select-sm radius-8 ov-filter"
                    id="ov-{{ $ds['slug'] }}-mitra" data-column="mitra">
              <option value="">Semua Perusahaan</option>
              @foreach ($ds['filterOptions']['mitra'] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>
        @endif

        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="ov-{{ $ds['slug'] }}-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 ov-filter"
                  id="ov-{{ $ds['slug'] }}-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($ds['monthOptions'] as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-ov="reset">Reset</button>
        </div>
        <div class="col-12">
          <span class="text-sm text-secondary-light" data-ov="status">Memuat ringkasan…</span>
        </div>
        <div class="col-12 d-none" data-ov="note-wrap">
          <div class="alert alert-warning bg-warning-focus border-warning-main text-warning-main
                      radius-8 px-20 py-12 mb-0 d-flex align-items-start gap-2">
            <iconify-icon icon="solar:info-circle-outline" class="icon text-xl flex-shrink-0"></iconify-icon>
            <span class="text-sm" data-ov="note"></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Kartu ringkasan utama --}}
  <div class="row gy-4 mb-24" data-ov="kpi"></div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-8">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Pemenuhan per Perusahaan</h6>
          <span class="text-sm text-secondary-light">
            @if (count($ds['filterOptions']['mitra']) > 12)
              12 perusahaan dengan pengawas terbanyak, diurutkan dari rasio tertinggi
            @else
              Dari {{ $ds['detail_table'] }}, diurutkan dari rasio tertinggi
            @endif
          </span>
        </div>
        <div class="card-body p-24">
          <div class="row gy-3" data-ov="perusahaan"></div>
        </div>
      </div>
    </div>

    <div class="col-xxl-4">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Pemenuhan per Site</h6>
        </div>
        <div class="card-body p-24" data-ov="site-target"></div>
      </div>
    </div>
  </div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-8">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
          <div>
            <h6 class="text-lg fw-semibold mb-0">Pemenuhan per Bulan</h6>
            <span class="text-sm text-secondary-light" data-ov="matrix-subtitle">
              Persentase pengawas yang melapor TBC
            </span>
          </div>
          <ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 flex-nowrap"
              role="tablist">
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 active ov-switch__btn"
                      data-mode="persen">Persentase</button>
            </li>
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 ov-switch__btn"
                      data-mode="nilai">Nilai</button>
            </li>
          </ul>
        </div>
        <div class="card-body p-24">
          <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-ov="legend"></div>
          <div class="ov-matrix-wrap">
            <table class="ov-matrix" data-ov="matrix">
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
          <h6 class="text-lg fw-semibold mb-0">Perlu Perhatian</h6>
        </div>
        <div class="card-body p-24">
          <div class="table-responsive">
            <table class="table bordered-table sm-table mb-0">
              <thead>
                <tr>
                  <th scope="col">Site &amp; Perusahaan</th>
                  <th scope="col" class="text-center">Nilai</th>
                  <th scope="col" class="text-end">Belum Lapor</th>
                </tr>
              </thead>
              <tbody data-ov="top5"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-7">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Rasio per Jabatan Fungsional</h6>
          <span class="text-sm text-secondary-light">Jumlah pengawas yang belum melapor, per jenjang jabatan</span>
        </div>
        <div class="card-body p-24">
          <div data-ov="chart-pareto"></div>
        </div>
      </div>
    </div>

    <div class="col-xxl-5">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Belum Melapor per Perusahaan</h6>
          <span class="text-sm text-secondary-light">Diurutkan dari yang terbanyak</span>
        </div>
        <div class="card-body p-24">
          <div data-ov="chart-area"></div>
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
            @if ($ds['summary_has_mitra'])
              Persentase pengawas yang melapor TBC per perusahaan
            @else
              Persentase pengawas yang melapor TBC per site
            @endif
          </span>
        </div>
        <div class="card-body p-24">
          <div data-ov="chart-monthly"></div>
        </div>
      </div>
    </div>
  </div>

</div>
