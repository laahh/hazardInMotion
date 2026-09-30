{{--
  Ringkasan Kepatuhan Roster — tampilan mengikuti dashboard /pnc-monitoring/dashboard.

  HALAMAN INI SEPENUHNYA STATIS. Seluruh angka di bawah adalah contoh yang
  ditulis langsung di file ini: tidak ada query database, tidak ada pemanggilan
  service, tidak ada fetch/AJAX. Variabel $judul, $subjudul, dan $lencana
  dikirim dari view pemanggil hanya untuk membedakan label halaman.
--}}

@php
    $judul = $judul ?? 'Ringkasan Kepatuhan Roster';
    $subjudul = $subjudul ?? 'Safety & Roster · Kepatuhan Jam Kerja Karyawan';
    $lencana = $lencana ?? ['teks' => 'DATA CONTOH', 'kelas' => 'bg-warning-focus text-warning-main'];

    // ── Angka ringkasan (dummy) ───────────────────────────────────────────
    $totalKaryawan = 5588;
    $patuh = 4605;
    $pelanggaran = 983;
    $persenPatuh = $totalKaryawan > 0 ? $patuh / $totalKaryawan * 100 : 0;
    $persenPelanggaran = 100 - $persenPatuh;

    $tiles = [
        [
            'label' => 'Kepatuhan Roster Aktif',
            'nilai' => '82,4%',
            'sub' => '4.605 / 5.588',
            'subTeks' => 'karyawan patuh',
            'ikon' => 'solar:clipboard-check-bold',
            'warnaIkon' => 'text-primary-600 bg-primary-light border-primary-light-white',
            'pil' => 'bg-success-focus text-success-main',
            'border' => 'border border-top-0',
        ],
        [
            'label' => 'Cuti Terpenuhi',
            'nilai' => '96,2%',
            'sub' => '1.204 / 1.252',
            'subTeks' => 'siklus cuti tuntas',
            'ikon' => 'solar:calendar-date-bold',
            'warnaIkon' => 'text-yellow bg-yellow-light border-yellow-light-white',
            'pil' => 'bg-success-focus text-success-main',
            'border' => 'border border-top-0 border-start-0 border-end-0',
        ],
        [
            'label' => 'Overshift Aktif',
            'nilai' => '3,1%',
            'sub' => '173 / 5.588',
            'subTeks' => 'kerja ≥8 hari beruntun',
            'ikon' => 'solar:alarm-bold',
            // Dashboard PnC memakai 'text-lilac bg-lilac-light' yang tidak
            // pernah didefinisikan di style.css (hanya varian bernomor seperti
            // text-lilac-100), sehingga ikonnya tampil tanpa warna. Di sini
            // dipakai palet ungu yang benar-benar ada.
            'warnaIkon' => 'text-purple bg-purple-light border-purple-light-white',
            'pil' => 'bg-warning-focus text-warning-main',
            'border' => 'border border-top-0 border-bottom-0',
        ],
        [
            'label' => 'Wajib Cuti Segera',
            'nilai' => '1,8%',
            'sub' => '101 / 5.588',
            'subTeks' => 'on-site >71 hari',
            'ikon' => 'solar:shield-warning-bold',
            'warnaIkon' => 'text-pink bg-pink-light border-pink-light-white',
            'pil' => 'bg-danger-focus text-danger-main',
            'border' => 'border border-top-0 border-bottom-0 border-start-0 border-end-0',
        ],
    ];

    // ── Heatmap kalender (dummy, deterministik) ───────────────────────────
    // Level 0 = tidak ada data, 1 = <50%, 2 = 50–74%, 3 = 75–89%, 4 = 90–99%, 5 = 100%.
    $hariLabel = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $kolom = 22;
    $grid = [];
    foreach (range(0, 6) as $r) {
        foreach (range(0, $kolom - 1) as $c) {
            // Pola tetap berbasis hash supaya tampilannya konsisten tiap render.
            $h = hexdec(substr(md5("roster-{$r}-{$c}"), 0, 2));
            $grid[$r][$c] = $c < 1 ? 0 : ($h % 6);
        }
    }
    $labelKolom = ['27 Apr', '04 May', '', '', '', '01 Jun', '', '', '', '06 Jul', '', '', '', '03 Aug', '', '', '', '07 Sep', '', '', '', ''];

    // ── Daftar karyawan perlu tindakan (dummy) ────────────────────────────
    $perluTindakan = [
        ['sid' => 'A-3-00014042', 'site' => 'LMO', 'pt' => 'PT BUKIT MAKMUR MANDIRI UTAMA', 'tanggal' => '18 Sep 2026'],
        ['sid' => 'A-14-00014039', 'site' => 'LMO', 'pt' => 'PT BUKIT MAKMUR MANDIRI UTAMA', 'tanggal' => '18 Sep 2026'],
        ['sid' => 'A-14-00013966-2', 'site' => 'LMO', 'pt' => 'PT BUKIT MAKMUR MANDIRI UTAMA', 'tanggal' => '18 Sep 2026'],
        ['sid' => 'B-7-00013904', 'site' => 'BMO 2', 'pt' => 'PT PAMAPERSADA NUSANTARA', 'tanggal' => '17 Sep 2026'],
        ['sid' => 'B-2-00013877', 'site' => 'GMO', 'pt' => 'PT PAMAPERSADA NUSANTARA', 'tanggal' => '17 Sep 2026'],
        ['sid' => 'C-9-00013810', 'site' => 'SMO', 'pt' => 'PT MADHANI TALATAH NUSANTARA', 'tanggal' => '16 Sep 2026'],
        ['sid' => 'C-1-00013788', 'site' => 'BMO 1', 'pt' => 'PT KALTIM DIAMOND COAL', 'tanggal' => '16 Sep 2026'],
    ];

    // ── Seri chart batang mingguan (dummy) ────────────────────────────────
    $mingguLabel = ['30 Agu–5 Sep', '6 Sep–12 Sep', '13 Sep–19 Sep', '20 Sep–26 Sep', '27 Sep–3 Okt'];
    $seriPelanggaran = [55, 59, 72, 0, 0];
    $seriWajibCuti = [43, 46, 57, 0, 0];
@endphp

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-4">{{ $judul }}</h6>
    <p class="text-secondary-light mb-0 text-sm">{{ $subjudul }}</p>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="px-12 py-6 rounded-pill fw-semibold text-sm {{ $lencana['kelas'] }}">{{ $lencana['teks'] }}</span>
    <ul class="d-flex align-items-center gap-2 mb-0">
      <li class="fw-medium">
        <a href="{{ route('dms.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
          <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
          DMS
        </a>
      </li>
      <li>-</li>
      <li class="fw-medium">Ringkasan Roster</li>
    </ul>
  </div>
</div>

<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8 d-flex gap-2 align-items-start" role="alert">
  <iconify-icon icon="solar:info-circle-bold" class="icon text-lg flex-shrink-0 mt-2"></iconify-icon>
  <div class="text-sm">
    <b>Halaman contoh (mockup).</b> Seluruh angka, grafik, dan daftar di halaman ini ditulis langsung di dalam
    berkas tampilan &mdash; tidak terhubung ke database, service, maupun API mana pun. Dipakai untuk menyepakati
    tata letak sebelum data sungguhan disambungkan.
  </div>
</div>

<div class="row gy-4">
  {{-- ══════════ KARTU HERO + 4 TILE ══════════ --}}
  <div class="col-xxl-9">
    <div class="card radius-8 border-0">
      <div class="row">
        <div class="col-xxl-6 pe-xxl-0">
          <div class="card-body p-24">
            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
              <div class="d-flex align-items-center gap-12">
                <span class="w-44-px h-44-px text-primary-600 bg-primary-light border border-primary-light-white flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-0">
                  <iconify-icon icon="solar:users-group-rounded-bold" class="icon"></iconify-icon>
                </span>
                <div>
                  <span class="text-secondary-light fw-medium text-sm d-block mb-2">Total Karyawan</span>
                  <h5 class="fw-bold mb-0 text-primary-light">{{ number_format($totalKaryawan, 0, ',', '.') }}</h5>
                </div>
                <span class="px-12 py-4 rounded-pill fw-semibold text-sm bg-success-focus text-success-main">
                  Patuh {{ number_format($persenPatuh, 1, ',', '.') }}%
                </span>
              </div>
              <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <select class="form-select form-select-sm w-auto bg-base border text-secondary-light">
                  <option>Bulanan</option>
                  <option>Tahunan</option>
                  <option>Mingguan</option>
                </select>
                <select class="form-select form-select-sm w-auto bg-base border text-secondary-light">
                  <option>2026</option>
                </select>
                <select class="form-select form-select-sm w-auto bg-base border text-secondary-light">
                  <option>Sep</option>
                </select>
              </div>
            </div>
            <div class="mt-40">
              {{-- Data chart dioper lewat atribut data-* supaya berkas skrip
                   (dimuat setelah ApexCharts) tidak perlu variabel PHP. --}}
              <div id="roBarChart" class="margin-16-minus"
                   data-kategori="{{ json_encode($mingguLabel) }}"
                   data-pelanggaran="{{ json_encode($seriPelanggaran) }}"
                   data-wajib="{{ json_encode($seriWajibCuti) }}"></div>
            </div>
          </div>
        </div>

        <div class="col-xxl-6">
          <div class="row h-100 g-0">
            @foreach ($tiles as $t)
              <div class="col-6 p-0 m-0">
                <div class="card-body p-24 h-100 d-flex flex-column justify-content-center {{ $t['border'] }}">
                  <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                    <div>
                      <span class="mb-12 w-44-px h-44-px {{ $t['warnaIkon'] }} border flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6">
                        <iconify-icon icon="{{ $t['ikon'] }}" class="icon"></iconify-icon>
                      </span>
                      <span class="mb-1 fw-bold text-secondary-light text-md">{{ $t['label'] }}</span>
                      <h6 class="fw-semibold text-primary-light mb-1">{{ $t['nilai'] }}</h6>
                    </div>
                  </div>
                  <p class="text-sm mb-0">
                    <span class="{{ $t['pil'] }} px-1 rounded-2 fw-medium text-sm">{{ $t['sub'] }}</span>
                    {{ $t['subTeks'] }}
                  </p>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ══════════ DONAT PELANGGARAN ══════════ --}}
  <div class="col-xxl-3 col-lg-6">
    <div class="card h-100 radius-8 border-0">
      <div class="card-body p-24">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
          <h6 class="mb-2 fw-bold text-lg">Persentase Pelanggaran</h6>
          <select class="form-select form-select-sm w-auto bg-base border text-secondary-light">
            <option>Yearly</option>
            <option>Monthly</option>
            <option>Weekly</option>
          </select>
        </div>

        <div class="position-relative">
          <span class="w-80-px h-80-px bg-base shadow text-danger-main fw-semibold text-xl d-flex justify-content-center align-items-center rounded-circle position-absolute end-0 top-0 z-1">
            {{ number_format($persenPelanggaran, 1, ',', '.') }}%
          </span>
          <div id="roDonutChart" class="mt-36 flex-grow-1 apexcharts-tooltip-z-none title-style circle-none"
               data-pelanggaran="{{ $pelanggaran }}" data-patuh="{{ $patuh }}"></div>
          <span class="w-80-px h-80-px bg-base shadow text-primary-light fw-semibold text-xl d-flex justify-content-center align-items-center rounded-circle position-absolute start-0 bottom-0 z-1">
            {{ number_format($persenPatuh, 1, ',', '.') }}%
          </span>
        </div>

        <ul class="d-flex flex-wrap align-items-center justify-content-between mt-3 gap-3">
          <li class="d-flex align-items-center gap-2">
            <span class="w-12-px h-12-px radius-2 bg-danger-main"></span>
            <span class="text-secondary-light text-sm fw-normal">Pelanggaran:
              <span class="text-primary-light fw-bold">{{ number_format($pelanggaran, 0, ',', '.') }}</span>
            </span>
          </li>
          <li class="d-flex align-items-center gap-2">
            <span class="w-12-px h-12-px radius-2 bg-primary-600"></span>
            <span class="text-secondary-light text-sm fw-normal">Patuh:
              <span class="text-primary-light fw-bold">{{ number_format($patuh, 0, ',', '.') }}</span>
            </span>
          </li>
        </ul>
      </div>
    </div>
  </div>

  {{-- ══════════ HEATMAP HARIAN ══════════ --}}
  <div class="col-xxl-9 col-lg-6">
    <div class="card h-100 ro-card">
      <div class="card-body p-24 d-flex flex-column h-100">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-16 flex-shrink-0">
          <div class="d-flex align-items-start gap-3" style="min-width:0">
            <span class="ro-card__head-icon">
              <iconify-icon icon="mdi:calendar-month-outline"></iconify-icon>
            </span>
            <div style="min-width:0">
              <h6 class="mb-1 fw-bold text-lg ro-card__title">Pola Kepatuhan Roster Harian</h6>
              <span class="ro-card__subtitle">Hari apa pelanggaran roster paling sering terjadi?</span>
            </div>
          </div>
          <div class="ro-card__badge">
            <iconify-icon icon="mdi:account-alert"></iconify-icon>
            <span>Terbanyak 72 pelanggaran &middot; 04 Jun 2026</span>
          </div>
        </div>

        <div class="ro-heatmap flex-grow-1 mb-8">
          <div class="ro-hm-scroll">
            <div class="ro-hm" style="--ro-cols: {{ $kolom }}">
              @foreach ($hariLabel as $r => $nama)
                <div class="ro-hm-ylabel">{{ $nama }}</div>
                <div class="ro-hm-row">
                  @foreach ($grid[$r] as $c => $lvl)
                    <span class="ro-hm-cell lvl-{{ $lvl }} {{ $lvl === 0 ? 'is-empty' : '' }}"
                          title="{{ $nama }}, kolom {{ $c + 1 }} — {{ ['tidak ada data', 'kepatuhan <50%', 'kepatuhan 50–74%', 'kepatuhan 75–89%', 'kepatuhan 90–99%', 'kepatuhan 100%'][$lvl] }}"></span>
                  @endforeach
                </div>
              @endforeach

              <div class="ro-hm-corner"></div>
              <div class="ro-hm-xlabels">
                @foreach (range(0, $kolom - 1) as $c)
                  <div class="ro-hm-xlabel"><span>{{ $labelKolom[$c] ?? '' }}</span></div>
                @endforeach
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-3 mb-16 flex-shrink-0">
          <span class="text-xs fw-medium" style="color:#64748B">Tingkat kepatuhan</span>
          @foreach ([
            [0, 'Tidak ada data'], [1, '<50%'], [2, '50–74%'],
            [3, '75–89%'], [4, '90–99%'], [5, '100%'],
          ] as [$lvl, $teks])
            <span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B">
              <span class="ro-legend-key ro-hm-cell lvl-{{ $lvl }}"></span>{{ $teks }}
            </span>
          @endforeach
        </div>

        <div class="ro-tip ro-tip--amber mb-16 flex-shrink-0">
          <iconify-icon icon="solar:calendar-bold" class="flex-shrink-0"></iconify-icon>
          <span>Pelanggaran paling padat jatuh di hari <b>Kamis</b> dan <b>Jumat</b> &mdash; berimpit dengan akhir blok kerja sebelum jadwal off.</span>
        </div>

        <div class="row g-3 flex-shrink-0">
          @foreach ([
            ['solar:calendar-mark-bold', 'Hari Terbanyak', 'Kamis', '72 pelanggaran'],
            ['solar:chart-2-bold', 'Rata-rata Harian', '38', 'pelanggaran / hari'],
            ['solar:shield-check-bold', 'Kepatuhan Keseluruhan', '82,4%', 'patuh / total karyawan'],
          ] as [$ikon, $label, $nilai, $sub])
            <div class="col-sm-6 col-xl-4">
              <div class="ro-metric h-100">
                <span class="ro-metric__icon"><iconify-icon icon="{{ $ikon }}"></iconify-icon></span>
                <div class="ro-metric__body">
                  <div class="ro-metric__label">{{ $label }}</div>
                  <div class="ro-metric__value">{{ $nilai }}</div>
                  <div class="ro-metric__sub">{{ $sub }}</div>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  {{-- ══════════ DAFTAR PERLU TINDAKAN ══════════ --}}
  <div class="col-xxl-3">
    <div class="card h-100 radius-8 border-0">
      <div class="card-body p-24">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
          <h6 class="mb-2 fw-bold text-lg">Karyawan Perlu Tindakan</h6>
          <span class="text-secondary-light text-sm">{{ count($perluTindakan) }} orang</span>
        </div>

        <div class="mt-32 overflow-y-auto scroll-sm" style="max-height: 380px;">
          @foreach ($perluTindakan as $i => $p)
            <div class="d-flex align-items-center justify-content-between gap-3 {{ $i === count($perluTindakan) - 1 ? '' : 'mb-32' }}">
              <div class="d-flex align-items-center gap-2">
                <span class="w-40-px h-40-px radius-8 flex-shrink-0 bg-danger-focus text-danger-main d-flex justify-content-center align-items-center">
                  <iconify-icon icon="solar:close-circle-bold"></iconify-icon>
                </span>
                <div class="flex-grow-1" style="min-width:0">
                  <h6 class="text-md mb-0 fw-normal">{{ $p['sid'] }}</h6>
                  <span class="text-sm text-secondary-light fw-normal">{{ $p['site'] }} &middot; {{ $p['pt'] }}</span>
                </div>
              </div>
              <span class="text-danger-main text-sm fw-medium text-end">{{ $p['tanggal'] }}</span>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
