{{--
  Tab Ringkasan % Blindspot temuan Real Time.

  Urutan panelnya mengikuti bobot sumbernya: persentase resmi dari tabel
  bulanan di atas sebagai ukuran utama, cacah temuan dari tabel detail di
  bawahnya sebagai rincian. Kalau tabel bulanannya belum berisi persentase,
  JS menukar posisi keduanya -- lihat aturTataLetak() di index.
--}}
<div class="bs-overview"
     data-url="{{ route('ohs-score-card.blindspot-real-time.overview') }}"
     data-detail-url="{{ route('ohs-score-card.blindspot-real-time.detail-bulan') }}">

  {{-- Filter --}}
  <div class="card radius-8 border mb-24">
    <div class="card-body p-24">
      <div class="row gy-3 gx-3 align-items-end">
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="bs-gr-site">Site</label>
          <select class="form-select form-select-sm radius-8 bs-filter"
                  id="bs-gr-site" data-column="site">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['site'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-4 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="bs-gr-mitra">Perusahaan PIC</label>
          <select class="form-select form-select-sm radius-8 bs-filter"
                  id="bs-gr-mitra" data-column="mitra">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['mitra'] as $option)
              <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xxl-3 col-md-4 col-sm-6">
          <label class="form-label text-sm fw-medium mb-8"
                 for="bs-gr-month">Bulan</label>
          <select class="form-select form-select-sm radius-8 bs-filter"
                  id="bs-gr-month" data-column="month">
            <option value="">Semua Bulan</option>
            @foreach ($monthOptions as $number => $label)
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
        <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
          <div>
            <h6 class="text-lg fw-semibold mb-0">Persentase Blindspot per Bulan</h6>
            {{-- ARAHNYA NAIK di parameter ini, kebalikan Blindspot TBC dan GR:
                 yang diukur temuan yang BERHASIL ditangkap real time, jadi
                 angka besar yang baik. --}}
            <span class="text-sm text-secondary-light" data-bs-el="persen-sub">
              Persentase temuan yang tertangkap real time; makin besar makin baik
            </span>
          </div>
          <ul class="nav nav-pills pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex flex-nowrap"
              role="tablist">
            <li class="nav-item flex-shrink-0" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 active bs-switch__btn"
                      data-mode="persen">Persentase</button>
            </li>
            <li class="nav-item flex-shrink-0" role="presentation">
              <button type="button" class="nav-link px-16 py-6 text-sm text-center radius-8 bs-switch__btn"
                      data-mode="nilai">Nilai</button>
            </li>
          </ul>
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
          <span class="text-sm text-secondary-light" data-bs-el="per-site-sub">Ambang {{ $ambang }}%</span>
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
            Cacah temuan dari {{ $tabel_detail }}
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

  <div class="row gy-4 mt-0">
    <div class="col-12">
      <div class="card h-100 radius-8 border">
        <div class="card-header border-bottom bg-base py-16 px-24">
          <h6 class="text-lg fw-semibold mb-0">Alat Pengawasan</h6>
          <span class="text-sm text-secondary-light">
            Lewat alat apa temuannya tertangkap; dimensi ini hanya ada di parameter real time
          </span>
        </div>
        <div class="card-body p-24">
          <div data-bs-el="chart-tools"></div>
        </div>
      </div>
    </div>
  </div>

</div>
