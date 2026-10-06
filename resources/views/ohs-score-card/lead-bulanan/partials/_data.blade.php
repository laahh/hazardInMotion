{{-- Tab Data parameter bulanan (BeSigma / Rekayasa / Sobriety). --}}
<div class="lbn-datatable"
     data-url="{{ route('ohs-score-card.' . $slug . '.data') }}"
     data-export-url="{{ route('ohs-score-card.' . $slug . '.export') }}">

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Data {{ $judul }}</h6>
        <span class="text-sm text-secondary-light">
          Satu baris per site, perusahaan, dan bulan, dari {{ $tabel }}
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-lbnd="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-lbnd="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-lbnd="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="lbnd-site">Site</label>
          <select class="form-select form-select-sm radius-8 lbnd-filter" id="lbnd-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="lbnd-mitra">Perusahaan</label>
          <select class="form-select form-select-sm radius-8 lbnd-filter" id="lbnd-mitra" data-column="mitra">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="lbnd-bulan">Bulan</label>
          <select class="form-select form-select-sm radius-8 lbnd-filter" id="lbnd-bulan" data-column="bulan_filter">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $nomor => $label)
              <option value="{{ $nomor }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-12 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-lbnd="reset">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-lbnd="table">
          <thead>
            <tr>
              <th>Site</th>
              <th>Perusahaan</th>
              <th>Bulan</th>
              <th class="text-end">{{ $pecahan[0] }}</th>
              <th class="text-end">{{ $pecahan[1] }}</th>
              <th class="text-end">Capaian</th>
              <th class="text-center">Nilai</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div>
