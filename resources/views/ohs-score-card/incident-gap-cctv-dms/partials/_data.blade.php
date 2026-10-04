{{--
  Tab Data "Incident dengan Gap Coverage CCTV & Gap pada DMS".

  Tiap baris di sini adalah insiden, bukan capaian; tabel yang pendek justru
  kabar baik.
--}}
<div class="gap-datatable"
     data-url="{{ route('ohs-score-card.incident-gap-cctv-dms.data') }}"
     data-export-url="{{ route('ohs-score-card.incident-gap-cctv-dms.export') }}">

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Daftar Deviasi</h6>
        <span class="text-sm text-secondary-light">
          Satu baris per deviasi layer, dari {{ $tabel_detail }}; satu insiden bisa punya beberapa
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-gap="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-gap="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-gap="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="gapd-site">Site</label>
          <select class="form-select form-select-sm radius-8 gapd-filter" id="gapd-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="gapd-mitra">Perusahaan PIC</label>
          <select class="form-select form-select-sm radius-8 gapd-filter" id="gapd-mitra" data-column="mitra">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="gapd-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 gapd-filter" id="gapd-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="gapd-status">Status Layer</label>
          <select class="form-select form-select-sm radius-8 gapd-filter" id="gapd-status" data-column="status">
            <option value="">Semua Status</option>
            @foreach ($filterOptions['status'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="gapd-klasifikasi">Klasifikasi</label>
          <select class="form-select form-select-sm radius-8 gapd-filter" id="gapd-klasifikasi"
                  data-column="klasifikasi">
            <option value="">Semua Klasifikasi</option>
            @foreach ($filterOptions['klasifikasi'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-gap="reset">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-gap="table">
          <thead>
            <tr>
              <th>Site</th>
              <th>Perusahaan</th>
              <th>Status</th>
              <th>Klasifikasi</th>
              <th>Keterangan</th>
              <th>Bulan</th>
              <th class="text-end">Insiden</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div>
