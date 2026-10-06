{{-- Tab Data Kesiapan Alat Emergency: satu baris per alat per bulan. --}}
<div class="kae-datatable"
     data-url="{{ route('ohs-score-card.kesiapan-alat-emergency.data') }}"
     data-export-url="{{ route('ohs-score-card.kesiapan-alat-emergency.export') }}">

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Data {{ $judul }}</h6>
        <span class="text-sm text-secondary-light">
          Satu baris per alat per bulan, dari {{ $tabelInspeksi }} yang dipadankan
          ke {{ $tabelInventaris }} lewat no_registrasi
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-kaed="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-kaed="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-kaed="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="kaed-site">Site</label>
          <select class="form-select form-select-sm radius-8 kaed-filter" id="kaed-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="kaed-kategori">Kategori Alat</label>
          <select class="form-select form-select-sm radius-8 kaed-filter" id="kaed-kategori" data-column="kategori">
            <option value="">Semua Kategori</option>
            @foreach ($filterOptions['kategori'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="kaed-klasifikasi">Klasifikasi</label>
          <select class="form-select form-select-sm radius-8 kaed-filter" id="kaed-klasifikasi" data-column="klasifikasi">
            <option value="">Semua</option>
            @foreach ($filterOptions['klasifikasi'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-6 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="kaed-bulan">Bulan</label>
          <select class="form-select form-select-sm radius-8 kaed-filter" id="kaed-bulan" data-column="bulan_filter">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $nomor => $label)
              <option value="{{ $nomor }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-6 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-kaed="reset">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-kaed="table">
          <thead>
            <tr>
              <th>No Registrasi</th>
              <th>Peralatan</th>
              <th>Site</th>
              <th>Kategori</th>
              <th>Bulan</th>
              <th class="text-end">Hari Good</th>
              <th class="text-center">Status</th>
              <th>Keterangan</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div>
