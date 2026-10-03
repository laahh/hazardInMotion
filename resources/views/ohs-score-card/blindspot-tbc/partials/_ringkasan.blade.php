{{--
  Tab Ringkasan Blindspot TBC untuk satu kumpulan data (minecon / subcon).

  Elemen ditandai lewat atribut data-bs-el, bukan id global, supaya partial ini
  bisa dipasang dua kali di halaman yang sama tanpa id bentrok.

  Urutan panelnya mengikuti bobot sumbernya: persentase resmi dari tabel
  bulanan di atas sebagai ukuran utama, cacah temuan dari tabel detail di
  bawahnya sebagai rincian.

  $ds: satu entri dari $datasets pada BlindspotTbcController::index().
--}}
<div class="bs-overview" data-dataset="{{ $ds['slug'] }}"
     data-url="{{ route('ohs-score-card.blindspot-tbc.overview', $ds['slug']) }}">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="bs-{{ $ds['slug'] }}-site">Site</label>
          <select class="form-select form-select-sm radius-8 bs-filter"
                  id="bs-{{ $ds['slug'] }}-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($ds['filterOptions']['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="bs-{{ $ds['slug'] }}-mitra">Perusahaan PIC</label>
          <select class="form-select form-select-sm radius-8 bs-filter"
                  id="bs-{{ $ds['slug'] }}-mitra" data-column="mitra">
            <option value="">Semua Perusahaan</option>
            @foreach ($ds['filterOptions']['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="bs-{{ $ds['slug'] }}-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 bs-filter"
                  id="bs-{{ $ds['slug'] }}-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($ds['monthOptions'] as $number => $label)
              <option value="{{ $number }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-1 col-md-4 col-sm-6">
          <button type="button" class="btn btn-sm btn-outline-secondary radius-8 w-100"
                  data-bs-el="reset">Reset</button>
        </div>
        <div class="col-12">
          <span class="text-sm text-secondary-light" data-bs-el="status">Memuat ringkasan…</span>
        </div>
        <div class="col-12 d-none" data-bs-el="note-wrap">
          <div class="alert alert-warning bg-warning-focus border-warning-main text-warning-main
                      radius-8 px-20 py-12 mb-0 d-flex align-items-start gap-2">
            <iconify-icon icon="solar:info-circle-outline" class="icon text-xl flex-shrink-0"></iconify-icon>
            <span class="text-sm" data-bs-el="note"></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Kartu ringkasan utama --}}
  <div class="row gy-4 mb-24" data-bs-el="kpi"></div>

  {{-- Ukuran utama: persentase resmi --}}
  <div class="row gy-4 mb-24">
    {{-- Kartu di slot ini ditentukan JS: persentase kalau tabel bulanannya
         terisi, cacah temuan kalau belum. Lihat aturTataLetak(). --}}
    <div class="col-xxl-8" data-bs-el="slot-utama">
      <div class="card h-100 radius-8 border" data-bs-el="kartu-persen">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Persentase Blindspot per Bulan</h6>
          <span class="text-sm text-secondary-light">
            Dari {{ $ds['monthly_table'] }}; makin besar angkanya makin banyak yang luput,
            jadi hijau berarti baik
          </span>
        </div>
        <div class="card-body p-24">
          <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-bs-el="legend-persen"></div>
          <div class="bs-matrix-wrap">
            <table class="bs-matrix" data-bs-el="persen">
              <thead></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xxl-4">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0" data-bs-el="per-site-judul">Rata-rata per Site</h6>
          <span class="text-sm text-secondary-light" data-bs-el="per-site-sub">Ambang {{ $ds['ambang'] }}%</span>
        </div>
        <div class="card-body p-24" data-bs-el="per-site"></div>
      </div>
    </div>
  </div>

  <div class="row gy-4 mb-24">
    <div class="col-xxl-5">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Peringkat Perusahaan PIC</h6>
          <span class="text-sm text-secondary-light" data-bs-el="per-mitra-sub">Diurutkan dari persentase tertinggi</span>
        </div>
        <div class="card-body p-24">
          <div class="table-responsive">
            <table class="table bordered-table sm-table mb-0">
              <thead>
                <tr>
                  <th scope="col">Perusahaan</th>
                  <th scope="col" class="text-end" data-bs-el="per-mitra-kol1">Rata-rata</th>
                  <th scope="col" class="text-end">Tertinggi</th>
                </tr>
              </thead>
              <tbody data-bs-el="per-mitra"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xxl-7">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Tren Bulanan</h6>
          <span class="text-sm text-secondary-light" data-bs-el="monthly-sub">
            Persentase blindspot per perusahaan PIC; garis yang menanjak berarti memburuk
          </span>
        </div>
        <div class="card-body p-24">
          <div data-bs-el="chart-monthly"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- Rincian dari tabel detail --}}
  <div class="row gy-4 mb-24" data-bs-el="baris-kedua">
    <div class="col-12" data-bs-el="slot-kedua">
      <div class="card h-100 radius-8 border" data-bs-el="kartu-temuan">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Jumlah Temuan per Bulan</h6>
          <span class="text-sm text-secondary-light" data-bs-el="temuan-sub">
            Cacah temuan dari {{ $ds['detail_table'] }}
          </span>
        </div>
        <div class="card-body p-24">
          <div class="d-flex align-items-center flex-wrap gap-3 mb-16" data-bs-el="legend-temuan"></div>
          <div class="bs-matrix-wrap">
            <table class="bs-matrix" data-bs-el="temuan">
              <thead></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row gy-4">
    <div class="col-xxl-7">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">PIC dengan Temuan Terbanyak</h6>
          <span class="text-sm text-secondary-light">
            Sepuluh teratas; makin panjang batangnya, makin sering areanya ketahuan pihak lain
          </span>
        </div>
        <div class="card-body p-24">
          <div data-bs-el="chart-pic"></div>
        </div>
      </div>
    </div>

    <div class="col-xxl-5">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Asal Pelapor</h6>
          <span class="text-sm text-secondary-light">Siapa yang menemukan, bukan siapa yang ditemukan</span>
        </div>
        <div class="card-body p-24">
          <div data-bs-el="chart-pelapor"></div>
        </div>
      </div>
    </div>
  </div>

</div>
