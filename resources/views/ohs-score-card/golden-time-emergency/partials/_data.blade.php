{{--
  Tab Data "Tidak ada pelaporan melewati batas golden time".

  Sumbernya tabel rincian, bukan tabel ringkasan: satu baris per insiden,
  lengkap dengan jam kejadian, jam pelaporan, dan jarak keduanya.
--}}
<div class="gte-datatable"
     data-url="{{ route('ohs-score-card.golden-time-emergency.data') }}"
     data-export-url="{{ route('ohs-score-card.golden-time-emergency.export') }}"
     data-ambang="{{ $ambang }}">

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Data Insiden</h6>
        <span class="text-sm text-secondary-light">
          Satu baris per insiden, dari {{ $tabel_detail }} — batas golden time {{ $ambang }} menit
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-gte="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-gte="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-gte="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="gted-site">Site</label>
          <select class="form-select form-select-sm radius-8 gted-filter" id="gted-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="gted-mitra">Perusahaan</label>
          <select class="form-select form-select-sm radius-8 gted-filter" id="gted-mitra" data-column="mitra">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="gted-lead">Lead Investigasi</label>
          <select class="form-select form-select-sm radius-8 gted-filter" id="gted-lead" data-column="lead">
            <option value="">Semua</option>
            @foreach ($filterOptions['lead'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="gted-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 gted-filter" id="gted-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="gted-status">Status</label>
          <select class="form-select form-select-sm radius-8 gted-filter" id="gted-status" data-column="status">
            <option value="">Semua</option>
            <option value="tepat">Dalam golden time</option>
            <option value="telat">Melewati batas</option>
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-gte="reset">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-gte="table">
          <thead>
            <tr>
              <th>Site</th>
              <th>Perusahaan</th>
              <th>Lead Investigasi</th>
              <th>Bulan</th>
              <th>Waktu Insiden</th>
              <th class="text-end">Jarak Lapor</th>
              <th>Status</th>
              <th>Kronologi</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div>
