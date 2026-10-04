{{--
  Tab Data Rasio Kelayakan Kerja untuk satu kumpulan data.

  Kolomnya berbeda antar kumpulan data: minecon menampilkan karyawan dari
  tabel rincian, subcon menampilkan baris bulanan dari tabel ringkasan karena
  belum punya rincian. Daftar kolom datang dari controller lewat
  $ds['dataColumns'] dan ikut dikirim ke JS sebagai JSON.
--}}
<div class="rkk-datatable" data-dataset="{{ $ds['slug'] }}"
     data-url="{{ route('ohs-score-card.rasio-kelayakan-kerja.data', $ds['slug']) }}"
     data-export-url="{{ route('ohs-score-card.rasio-kelayakan-kerja.export', $ds['slug']) }}"
     data-columns='@json($ds['dataColumns'])'>

  <div class="card radius-8 border">
    <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
      <div>
        <h6 class="text-lg fw-semibold mb-0">
          {{ $ds['detail_table'] ? 'Data Karyawan' : 'Data Bulanan' }} {{ $ds['label'] }}
        </h6>
        <span class="text-sm text-secondary-light">
          @if ($ds['detail_table'])
            Satu baris per karyawan beserta hasil MCU-nya, dari {{ $ds['detail_table'] }}
          @else
            Satu baris per site dan bulan, dari {{ $ds['summary_table'] }} — belum ada tabel rincian
          @endif
        </span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-sm text-secondary-light" data-rkk="hint"></span>
        <button type="button" class="btn btn-sm btn-success-600 radius-8" data-rkk="export" data-format="xlsx">
          <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Unduh Excel
        </button>
        <button type="button" class="btn btn-sm btn-outline-success radius-8" data-rkk="export" data-format="csv">
          <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
        </button>
      </div>
    </div>

    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end mb-20">
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="rkkd-{{ $ds['slug'] }}-site">Site</label>
          <select class="form-select form-select-sm radius-8 rkkd-filter"
                  id="rkkd-{{ $ds['slug'] }}-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($ds['filterOptions']['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>

        @if ($ds['has_mitra'])
          <div class="col-xxl-4 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8"
                   for="rkkd-{{ $ds['slug'] }}-mitra">Perusahaan</label>
            <select class="form-select form-select-sm radius-8 rkkd-filter"
                    id="rkkd-{{ $ds['slug'] }}-mitra" data-column="mitra">
              <option value="">Semua Perusahaan</option>
              @foreach ($ds['filterOptions']['mitra'] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>
        @endif

        @unless ($ds['detail_table'])
          {{-- Filter bulan hanya berguna untuk sumber yang punya kolom bulan;
               tabel rincian minecon tidak punya. --}}
          <div class="col-xxl-3 col-md-4 col-sm-6">
            <label class="form-label text-sm fw-medium mb-8"
                   for="rkkd-{{ $ds['slug'] }}-month">Bulan</label>
            <select class="form-select form-select-sm radius-8 rkkd-filter"
                    id="rkkd-{{ $ds['slug'] }}-month" data-column="month">
              <option value="">Semua Bulan</option>
              @foreach ($ds['monthOptions'] as $number => $label)
                <option value="{{ $number }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
        @endunless

        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-rkk="reset">Reset</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table bordered-table sm-table mb-0" data-rkk="table">
          <thead>
            <tr>
              @foreach ($ds['dataColumns'] as $kolom)
                <th class="{{ $kolom['class'] ?? '' }}">{{ $kolom['label'] }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div>
