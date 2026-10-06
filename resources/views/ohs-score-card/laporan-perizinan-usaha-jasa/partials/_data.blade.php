{{-- Tab Data Laporan Perizinan Usaha Jasa: satu baris per site & main contractor. --}}
<div class="lpu-datatable"
     data-url="{{ route('ohs-score-card.laporan-perizinan-usaha-jasa.data') }}"
     data-export-url="{{ route('ohs-score-card.laporan-perizinan-usaha-jasa.export') }}">

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Data {{ $judul }}</h6>
        <span class="text-sm text-secondary-light">
          Satu baris per site dan main contractor, dari {{ $tabel }}
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-lpud="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-lpud="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-lpud="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-4 col-md-5 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="lpud-site">Site</label>
          <select class="form-select form-select-sm radius-8 lpud-filter" id="lpud-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-5 col-md-5 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="lpud-mitra">Main Contractor</label>
          <select class="form-select form-select-sm radius-8 lpud-filter" id="lpud-mitra" data-column="mitra">
            <option value="">Semua Main Contractor</option>
            @foreach ($filterOptions['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-2 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-lpud="reset">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-lpud="table">
          <thead>
            <tr>
              <th>Site</th>
              <th>Main Contractor</th>
              <th class="text-end">Subcont</th>
              <th class="text-end">Total Deviasi</th>
              <th class="text-end">Rata-rata Pemenuhan</th>
              <th>Per Bulan</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <span class="text-xs text-secondary-light d-block mt-12">
        Angka di kolom "Per Bulan" adalah <strong>persentase pemenuhan</strong> yang dibulatkan;
        arahkan kursor untuk melihat cacah deviasinya. Hijau berarti 100% terpenuhi.
      </span>
    </div>
  </div>

</div>
