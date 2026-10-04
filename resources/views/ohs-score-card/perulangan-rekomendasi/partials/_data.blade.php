{{--
  Tab Data "Perulangan rekomendasi hasil investigasi".

  Tiap baris di sini adalah perulangan, bukan capaian; tabel yang pendek justru
  kabar baik.
--}}
<div class="rek-datatable"
     data-url="{{ route('ohs-score-card.perulangan-rekomendasi.data') }}"
     data-export-url="{{ route('ohs-score-card.perulangan-rekomendasi.export') }}">

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Daftar Perulangan</h6>
        <span class="text-sm text-secondary-light">
          Satu baris per rekomendasi yang terulang, dari {{ $tabel_detail }}
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-rek="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-rek="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-rek="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="rekd-site">Site</label>
          <select class="form-select form-select-sm radius-8 rekd-filter" id="rekd-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="rekd-mitra">Perusahaan PIC</label>
          <select class="form-select form-select-sm radius-8 rekd-filter" id="rekd-mitra" data-column="mitra">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="rekd-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 rekd-filter" id="rekd-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-rek="reset">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-rek="table">
          <thead>
            <tr>
              <th>Site</th>
              <th>Perusahaan</th>
              <th>Keterangan</th>
              <th>Tindakan Perbaikan &amp; Pencegahan</th>
              <th>Bulan</th>
              <th class="text-end">Jumlah</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div>
