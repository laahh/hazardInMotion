{{-- Tab Data Pemenuhan Sertifikasi (Pengawas / Tenaga Teknis). --}}
<div class="skp-datatable"
     data-url="{{ route('ohs-score-card.' . $slug . '.data') }}"
     data-export-url="{{ route('ohs-score-card.' . $slug . '.export') }}">

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Data {{ $peran }}</h6>
        <span class="text-sm text-secondary-light">
          {{-- Satuannya ORANG, bukan baris tabel: itulah satuan yang dipakai rumusnya. --}}
          Satu baris per orang di satu site dan perusahaan, diringkas dari {{ $tabel }}
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-skpd="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-skpd="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-skpd="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="skpd-site">Site</label>
          <select class="form-select form-select-sm radius-8 skpd-filter" id="skpd-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="skpd-perusahaan">Perusahaan</label>
          <select class="form-select form-select-sm radius-8 skpd-filter" id="skpd-perusahaan" data-column="perusahaan">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['perusahaan'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="skpd-izin">Izin kerja</label>
          <select class="form-select form-select-sm radius-8 skpd-filter" id="skpd-izin" data-column="izin">
            <option value="">Semua Izin Kerja</option>
            @foreach ($filterOptions['izin'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="skpd-status">Status</label>
          <select class="form-select form-select-sm radius-8 skpd-filter" id="skpd-status" data-column="status">
            <option value="">Semua</option>
            <option value="bersertifikat">Bersertifikat</option>
            <option value="belum">Belum</option>
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-skpd="reset">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-skpd="table">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Site</th>
              <th>Perusahaan</th>
              <th class="text-end">Izin kerja</th>
              <th>Sertifikasi</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div>
