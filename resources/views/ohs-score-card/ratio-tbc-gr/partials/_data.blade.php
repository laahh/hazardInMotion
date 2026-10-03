{{--
  Tab Data untuk satu kumpulan data (minecon / subcon).

  Sama seperti partial Ringkasan: elemen ditandai lewat data-rs, bukan id
  global, supaya aman dipasang dua kali di satu halaman.

  $ds: satu entri dari $datasets pada RatioTbcGrController::index().
--}}
<div class="osc-datatable" data-dataset="{{ $ds['slug'] }}"
     data-url="{{ route('ohs-score-card.ratio-tbc-gr.data', $ds['slug']) }}"
     data-export-url="{{ route('ohs-score-card.ratio-tbc-gr.export', $ds['slug']) }}">

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Data Pelaporan {{ $ds['label'] }}</h6>
        <span class="text-sm text-secondary-light">
          Satu baris per pengawas per bulan, dari {{ $ds['detail_table'] }}
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-rs="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-rs="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-rs="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="rs-{{ $ds['slug'] }}-site">Site</label>
          <select class="form-select form-select-sm radius-8 rs-filter"
                  id="rs-{{ $ds['slug'] }}-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($ds['filterOptions']['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="rs-{{ $ds['slug'] }}-mitra">Perusahaan</label>
          <select class="form-select form-select-sm radius-8 rs-filter"
                  id="rs-{{ $ds['slug'] }}-mitra" data-column="mitra">
            <option value="">Semua Perusahaan</option>
            @foreach ($ds['filterOptions']['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="rs-{{ $ds['slug'] }}-jabatan">Jabatan</label>
          <select class="form-select form-select-sm radius-8 rs-filter"
                  id="rs-{{ $ds['slug'] }}-jabatan" data-column="jabatan">
            <option value="">Semua Jabatan</option>
            @foreach ($ds['filterOptions']['jabatan'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-2 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="rs-{{ $ds['slug'] }}-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 rs-filter"
                  id="rs-{{ $ds['slug'] }}-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($ds['monthOptions'] as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="rs-{{ $ds['slug'] }}-status">Status</label>
          <select class="form-select form-select-sm radius-8 rs-filter"
                  id="rs-{{ $ds['slug'] }}-status" data-column="status">
            <option value="">Semua</option>
            <option value="melapor">Melapor</option>
            <option value="belum">Belum</option>
            <option value="offsite">Di luar RFID</option>
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-rs="reset">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-rs="table">
          <thead>
            <tr>
              <th>Site</th>
              <th>Perusahaan</th>
              <th>SID</th>
              <th>Nama Pelapor</th>
              <th>Jabatan</th>
              <th>Bulan</th>
              <th>RFID</th>
              <th>Lapor TBC</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div>
