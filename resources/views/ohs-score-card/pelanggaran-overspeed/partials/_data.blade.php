{{--
  Tab Data "Deviasi Rekayasa Engineering Overspeed".

  Tiap baris di sini adalah kedapatan, bukan capaian; tabel yang pendek justru
  kabar baik. Kolom Pelanggar mencacah SID karyawan berbeda, jadi angkanya
  bukan banyaknya kejadian.
--}}
<div class="osp-datatable"
     data-url="{{ route('ohs-score-card.pelanggaran-overspeed.data') }}"
     data-export-url="{{ route('ohs-score-card.pelanggaran-overspeed.export') }}">

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Daftar Kedapatan</h6>
        <span class="text-sm text-secondary-light">
          Satu baris per site, PIC, perusahaan, dan bulan yang kedapatan, dari {{ $tabel }}
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-osp="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-osp="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-osp="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="ospd-site">Site</label>
          <select class="form-select form-select-sm radius-8 ospd-filter" id="ospd-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="ospd-pic">PIC Approval</label>
          <select class="form-select form-select-sm radius-8 ospd-filter" id="ospd-pic" data-column="pic">
            <option value="">Semua PIC</option>
            @foreach ($filterOptions['pic'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="ospd-mitra">Perusahaan</label>
          <select class="form-select form-select-sm radius-8 ospd-filter" id="ospd-mitra" data-column="mitra">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="ospd-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 ospd-filter" id="ospd-month" data-column="month">
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
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-osp="table">
          <thead>
            <tr>
              <th>Site</th>
              <th>PIC Approval</th>
              <th>Perusahaan</th>
              <th>Bulan</th>
              <th class="text-end">Pelanggar</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div>
