{{-- Tab Data Kesesuaian Implementasi IKK. --}}
<div class="ikk-datatable"
     data-url="{{ route('ohs-score-card.compliance-ikk.data') }}"
     data-export-url="{{ route('ohs-score-card.compliance-ikk.export') }}">

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Data Coverage Area Kritis</h6>
        <span class="text-sm text-secondary-light">
          Satu baris per site, PIC lokasi, dan bulan, dari {{ $tabel }}
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-ikk="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-ikk="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-ikk="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="ikkd-site">Site</label>
          <select class="form-select form-select-sm radius-8 ikkd-filter" id="ikkd-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="ikkd-pic">Perusahaan</label>
          <select class="form-select form-select-sm radius-8 ikkd-filter" id="ikkd-pic" data-column="pic">
            <option value="">Semua PIC</option>
            @foreach ($filterOptions['pic'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="ikkd-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 ikkd-filter" id="ikkd-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="ikkd-nilai">Nilai</label>
          <select class="form-select form-select-sm radius-8 ikkd-filter" id="ikkd-nilai" data-column="nilai">
            <option value="">Semua Nilai</option>
            <option value="4">Nilai 4 · 98–100%</option>
            <option value="3">Nilai 3 · 90–98%</option>
            <option value="2">Nilai 2 · 80–90%</option>
            <option value="1">Nilai 1 · &lt;80%</option>
            <option value="kosong">Belum ada data</option>
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-ikk="reset">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-ikk="table">
          <thead>
            <tr>
              <th>Site</th>
              <th>Perusahaan</th>
              <th>Bulan</th>
              <th class="text-end">Dikunjungi</th>
              <th class="text-end">Terdaftar</th>
              <th class="text-end">Coverage</th>
              <th class="text-center">Nilai</th>
              <th>Keterangan</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div>
