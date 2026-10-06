{{-- Tab Daftar Regulasi (regulatory_compliance_detail): satu baris per regulasi. --}}
<div class="prg-datatable"
     data-url="{{ route('ohs-score-card.pemenuhan-regulasi.data') }}"
     data-export-url="{{ route('ohs-score-card.pemenuhan-regulasi.export') }}">

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">Daftar Regulasi</h6>
        <span class="text-sm text-secondary-light">
          Satu baris per regulasi, dari {{ $tabelDetail }}
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-prgd="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-prgd="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-prgd="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="alert alert-light border radius-8 text-sm">
        Tabel ini <strong>tidak punya kolom site maupun perusahaan</strong> — isinya daftar
        regulasi itu sendiri, cakupannya berbeda dari tab Ringkasan, dan kosakata sektornya
        pun berbeda. Angka di kedua tab tidak bisa dijumlahkan atau dipasangkan satu sama lain.
      </div>

      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-4 col-md-5 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="prgd-sektor">Sektor</label>
          <select class="form-select form-select-sm radius-8 prgd-filter" id="prgd-sektor"
                  data-column="sektor_detail">
            <option value="">Semua Sektor</option>
            @foreach ($filterOptions['sektor_detail'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-5 col-md-5 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8" for="prgd-kategori">Kategori Regulasi</label>
          <select class="form-select form-select-sm radius-8 prgd-filter" id="prgd-kategori"
                  data-column="kategori_detail">
            <option value="">Semua Kategori</option>
            @foreach ($filterOptions['kategori_detail'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-2 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-prgd="reset">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-prgd="table">
          <thead>
            <tr>
              <th>Regulasi</th>
              <th>Sektor</th>
              <th>Kategori</th>
              <th class="text-end">Kewajiban</th>
              <th class="text-end">Patuh</th>
              <th class="text-end">Dalam Proses</th>
              <th class="text-end">Kepatuhan</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <span class="text-xs text-secondary-light d-block mt-12">
        Angka dalam kurung di kolom "Dalam Proses" adalah kewajiban yang belum berstatus apa pun —
        ada di 40 dari 363 regulasi, di mana total kewajibannya lebih besar daripada
        patuh ditambah dalam proses.
      </span>
    </div>
  </div>

</div>
