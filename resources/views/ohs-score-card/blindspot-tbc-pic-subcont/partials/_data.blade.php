{{--
  Tab Data "% Blindspot TBC dengan PIC Subcontractor".

  Tiap baris di sini adalah temuan, bukan capaian; tabel yang pendek justru
  kabar baik.
--}}
<div class="bps-datatable"
     data-url="{{ route('ohs-score-card.blindspot-tbc-pic-subcont.data') }}"
     data-export-url="{{ route('ohs-score-card.blindspot-tbc-pic-subcont.export') }}">

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Daftar Temuan</h6>
        <span class="text-sm text-secondary-light">
          Satu baris per temuan, dari {{ $tabel }}
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-bps="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-bps="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-bps="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="bpsd-site">Site</label>
          <select class="form-select form-select-sm radius-8 bpsd-filter" id="bpsd-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          {{-- Minecon tidak ada di tabel ini; diturunkan dari view relasi
               perusahaan, jadi penyaringnya bekerja lewat daftar pasangan
               site/subkon. Lihat BlindspotTbcPicSubcontController::saringMinecon(). --}}
          <label class="form-label text-sm fw-medium mb-8" for="bpsd-minecon">Perusahaan Minecon</label>
          <select class="form-select form-select-sm radius-8 bpsd-filter" id="bpsd-minecon" data-column="minecon">
            <option value="">Semua Minecon</option>
            @foreach ($filterOptions['minecon'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="bpsd-mitra">Perusahaan PIC (Subkon)</label>
          <select class="form-select form-select-sm radius-8 bpsd-filter" id="bpsd-mitra" data-column="mitra">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="bpsd-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 bpsd-filter" id="bpsd-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="bpsd-pelapor">Perusahaan Pelapor</label>
          <select class="form-select form-select-sm radius-8 bpsd-filter" id="bpsd-pelapor"
                  data-column="pelapor">
            <option value="">Semua Pelapor</option>
            @foreach ($filterOptions['pelapor'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-bps="reset">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-bps="table">
          <thead>
            <tr>
              <th>Site</th>
              <th>Perusahaan Minecon</th>
              <th>Perusahaan PIC (Subkon)</th>
              <th>PIC</th>
              <th>Pelapor</th>
              <th>Deskripsi Temuan</th>
              <th>Bulan</th>
              <th class="text-end">Task</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div>
