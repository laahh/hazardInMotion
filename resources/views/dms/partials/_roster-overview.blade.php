{{--
  Ringkasan Kepatuhan Roster — tampilan mengikuti dashboard /pnc-monitoring/dashboard.

  Hampir seluruh kartu sudah LIVE, memakai variabel yang dikirim route:
  $totalKaryawanLive / $perSiteLive (populasi + sebaran site), $agregatLive
  (hasil rule engine: pelanggaran, REG, wajib cuti, status), $heatmapLive
  (grid kalender harian), $perluTindakanLive (peringkat wajib cuti), dan
  $karyawanLive (satu halaman baris scorecard).

  Setiap kartu punya cabang contoh: bila variabelnya null — tabel sinkronisasi
  belum terisi atau database tidak terjangkau — halaman tetap render dengan
  angka contoh, bukan 500. Variabel $judul, $subjudul, dan $lencana dikirim
  view pemanggil hanya untuk membedakan label halaman.
--}}

@php
    $judul = $judul ?? 'Ringkasan Kepatuhan Roster';
    $subjudul = $subjudul ?? 'Safety & Roster · Kepatuhan Jam Kerja Karyawan';
    $lencana = $lencana ?? ['teks' => 'DATA CONTOH', 'kelas' => 'bg-warning-focus text-warning-main'];

    // Ambang dibaca dari config yang sama dengan rule engine, supaya label
    // tile tidak pernah menyebut angka yang berbeda dari yang dihitung.
    $ambangReg3 = (int) config('dms_roster.ambang.cuti_min', 12);
    $ambangOvershift = (int) config('dms_roster.ambang.overshift', 8);
    $ambangWajib = (int) config('dms_roster.ambang.wajib_cuti', 71);

    // ── Total karyawan: LIVE bila tersedia, jika tidak pakai angka contoh ──
    // Angka utama = yang PUNYA SIMPER AKTIF, bukan seluruh karyawan aktif.
    // Populasi aktif ($aktifSemua) tetap dibawa sebagai konteks cakupan.
    $live = $totalKaryawanLive ?? null;
    $totalKaryawan = $live['punya_simper_aktif'] ?? 5588;
    $aktifSemua = $live['total'] ?? null;
    $persenSimper = ($live && $live['total'] > 0)
        ? $live['punya_simper_aktif'] / $live['total'] * 100
        : null;

    // ── Empat tile: pakai agregat asli bila tersedia ──────────────────────
    // Sumbernya DmsRosterComplianceService (dipakai bersama halaman
    // Kepatuhan Roster Live), jadi angka di dua halaman tidak mungkin beda.
    $ag = $agregatLive ?? null;
    $basisTile = $ag['total'] ?? 0;
    $pctTile = static fn (int $x): string => $basisTile > 0
        ? number_format($x / $basisTile * 100, 1, ',', '.').'%'
        : '0%';
    $rasio = static fn (int $x): string => number_format($x, 0, ',', '.')
        .' / '.number_format($basisTile, 0, ',', '.');

    // ── Donat pelanggaran ─────────────────────────────────────────────────
    // Penyebutnya selalu populasi yang BENAR-BENAR dievaluasi ($ag['total']),
    // bukan $totalKaryawan: kedua angka bisa berbeda bila ada karyawan yang
    // belum punya pola roster, dan mencampurnya menghasilkan persen palsu.
    $pelanggaran = $ag['pelanggaran'] ?? 983;
    $patuh = $ag ? $ag['total'] - $ag['pelanggaran'] : 4605;
    $basisDonat = $patuh + $pelanggaran;
    $persenPatuh = $basisDonat > 0 ? $patuh / $basisDonat * 100 : 0;
    $persenPelanggaran = 100 - $persenPatuh;

    if ($ag) {
        $cutiOk = $ag['total'] - $ag['reg3'];
        $overshift = $ag['status']['Overshift'] ?? 0;

        $tiles = [
            [
                'label' => 'Kepatuhan Roster',
                'nilai' => $pctTile($patuh),
                'sub' => $rasio($patuh),
                'subTeks' => 'tanpa pelanggaran REG',
                'ikon' => 'solar:clipboard-check-bold',
                'warnaIkon' => 'text-primary-600 bg-primary-light border-primary-light-white',
                'pil' => 'bg-success-focus text-success-main',
                'border' => 'border border-top-0',
            ],
            [
                'label' => 'Cuti Memenuhi Ambang',
                'nilai' => $pctTile($cutiOk),
                'sub' => $rasio($cutiOk),
                'subTeks' => 'cuti terpendek ≥ '.$ambangReg3.' hari',
                'ikon' => 'solar:calendar-date-bold',
                'warnaIkon' => 'text-yellow bg-yellow-light border-yellow-light-white',
                'pil' => 'bg-success-focus text-success-main',
                'border' => 'border border-top-0 border-start-0 border-end-0',
            ],
            [
                'label' => 'Overshift (kini)',
                'nilai' => $pctTile($overshift),
                'sub' => $rasio($overshift),
                'subTeks' => 'kerja ≥'.$ambangOvershift.' hari beruntun',
                'ikon' => 'solar:alarm-bold',
                // Dashboard PnC memakai text-lilac/bg-lilac-light yang tidak
                // pernah didefinisikan di style.css; dipakai palet ungu yang ada.
                'warnaIkon' => 'text-purple bg-purple-light border-purple-light-white',
                'pil' => 'bg-warning-focus text-warning-main',
                'border' => 'border border-top-0 border-bottom-0',
            ],
            [
                'label' => 'Wajib Cuti Segera',
                'nilai' => $pctTile($ag['wajib_cuti']),
                'sub' => $rasio($ag['wajib_cuti']),
                // Rumus persis referensi: hitung mundur dari hari terakhir
                // selama belum ketemu cuti; wajib bila > ambang dan bukan
                // kategori regulasi longgar.
                'subTeks' => 'on-site berjalan >'.$ambangWajib.' hari',
                'ikon' => 'solar:shield-warning-bold',
                'warnaIkon' => 'text-pink bg-pink-light border-pink-light-white',
                'pil' => 'bg-danger-focus text-danger-main',
                'border' => 'border border-top-0 border-bottom-0 border-start-0 border-end-0',
            ],
        ];
    } else {
        $tiles = [
            [
                'label' => 'Kepatuhan Roster', 'nilai' => '82,4%', 'sub' => '4.605 / 5.588',
                'subTeks' => 'tanpa pelanggaran REG', 'ikon' => 'solar:clipboard-check-bold',
                'warnaIkon' => 'text-primary-600 bg-primary-light border-primary-light-white',
                'pil' => 'bg-success-focus text-success-main', 'border' => 'border border-top-0',
            ],
            [
                'label' => 'Cuti Memenuhi Ambang', 'nilai' => '96,2%', 'sub' => '1.204 / 1.252',
                'subTeks' => 'cuti terpendek ≥ '.$ambangReg3.' hari', 'ikon' => 'solar:calendar-date-bold',
                'warnaIkon' => 'text-yellow bg-yellow-light border-yellow-light-white',
                'pil' => 'bg-success-focus text-success-main',
                'border' => 'border border-top-0 border-start-0 border-end-0',
            ],
            [
                'label' => 'Overshift (kini)', 'nilai' => '3,1%', 'sub' => '173 / 5.588',
                'subTeks' => 'kerja ≥'.$ambangOvershift.' hari beruntun', 'ikon' => 'solar:alarm-bold',
                'warnaIkon' => 'text-purple bg-purple-light border-purple-light-white',
                'pil' => 'bg-warning-focus text-warning-main',
                'border' => 'border border-top-0 border-bottom-0',
            ],
            [
                'label' => 'Wajib Cuti Segera', 'nilai' => '1,8%', 'sub' => '101 / 5.588',
                'subTeks' => 'on-site berjalan >'.$ambangWajib.' hari',
                'ikon' => 'solar:shield-warning-bold',
                'warnaIkon' => 'text-pink bg-pink-light border-pink-light-white',
                'pil' => 'bg-danger-focus text-danger-main',
                'border' => 'border border-top-0 border-bottom-0 border-start-0 border-end-0',
            ],
        ];
    }

    // ── Heatmap kalender harian ───────────────────────────────────────────
    // Data asli datang sudah berbentuk grid dari DmsRosterHeatmapBuilder:
    // baris Senin–Minggu x kolom minggu, tiap sel memuat level warna dan
    // judul tooltip. Skalanya kuintil, batasnya dicetak di legenda.
    $hariLabel = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $peta = $heatmapLive ?? null;

    if ($peta) {
        $kolom = $peta['kolom'];
        $grid = $peta['grid'];
        $labelKolom = $peta['labelKolom'];
        $legenda = $peta['legenda'];
    } else {
        // Contoh deterministik berbasis hash supaya tampilan tidak berubah
        // tiap render saat data asli belum tersedia.
        $kolom = 22;
        $grid = [];
        foreach (range(0, 6) as $r) {
            foreach (range(0, $kolom - 1) as $c) {
                $h = hexdec(substr(md5("roster-{$r}-{$c}"), 0, 2));
                $grid[$r][$c] = $c < 1 ? null : ['level' => $h % 6, 'judul' => ''];
            }
        }
        $labelKolom = ['27 Apr', '04 May', '', '', '', '01 Jun', '', '', '', '06 Jul', '', '', '', '03 Aug', '', '', '', '07 Sep', '', '', '', ''];
        $legenda = [
            ['level' => 0, 'teks' => 'Tidak ada data'], ['level' => 1, 'teks' => '<50%'],
            ['level' => 2, 'teks' => '50–74%'], ['level' => 3, 'teks' => '75–89%'],
            ['level' => 4, 'teks' => '90–99%'], ['level' => 5, 'teks' => '100%'],
        ];
    }

    // Satu baris sel digambar sekaligus di PHP: ±280 kolom x 7 baris lewat
    // perulangan Blade membuat indentasi template saja menambah ratusan KB.
    $selHeatmap = static function (array $baris, int $kolom): string {
        $out = '';
        for ($c = 0; $c < $kolom; $c++) {
            $sel = $baris[$c] ?? null;
            if ($sel === null || (int) $sel['level'] === 0) {
                $out .= '<span class="ro-hm-cell lvl-0 is-empty"></span>';

                continue;
            }
            $out .= '<span class="ro-hm-cell lvl-'.(int) $sel['level'].'"'
                .($sel['judul'] !== '' ? ' title="'.e($sel['judul']).'"' : '').'></span>';
        }

        return $out;
    };

    // Sorotan di bawah heatmap: dua hari terpadat dirangkai jadi satu kalimat.
    $duaTerpadat = $peta ? array_slice($peta['perHari'], 0, 2) : [];
    // Flag pelanggaran berlaku per blok, bukan per kejadian harian: sekali
    // seorang karyawan melanggar REG-2 ia ter-flag berhari-hari berturut-turut.
    // Akibatnya selisih antar hari dalam seminggu nyaris nol, dan menyebut
    // "hari terpadat" justru menyesatkan. Di bawah 5% kita nyatakan apa adanya.
    $adaPolaHarian = ($peta['sebaranHari'] ?? 0) >= 0.05;

    // ── Daftar karyawan perlu tindakan ────────────────────────────────────
    // Peringkat wajib cuti (on-site berjalan terpanjang lebih dulu).
    $tindakan = $perluTindakanLive ?? null;
    $perluTindakan = $tindakan['baris'] ?? [
        ['nama' => 'A-3-00014042', 'sid' => 'A-3-00014042', 'site' => 'LMO', 'pt' => 'BUMA', 'hari' => 112, 'sejak' => '11 Jun 2026'],
        ['nama' => 'A-14-00014039', 'sid' => 'A-14-00014039', 'site' => 'LMO', 'pt' => 'BUMA', 'hari' => 104, 'sejak' => '19 Jun 2026'],
        ['nama' => 'A-14-00013966-2', 'sid' => 'A-14-00013966-2', 'site' => 'LMO', 'pt' => 'BUMA', 'hari' => 98, 'sejak' => '25 Jun 2026'],
        ['nama' => 'B-7-00013904', 'sid' => 'B-7-00013904', 'site' => 'BMO 2', 'pt' => 'PAMA', 'hari' => 91, 'sejak' => '2 Jul 2026'],
        ['nama' => 'B-2-00013877', 'sid' => 'B-2-00013877', 'site' => 'GMO', 'pt' => 'PAMA', 'hari' => 88, 'sejak' => '5 Jul 2026'],
        ['nama' => 'C-9-00013810', 'sid' => 'C-9-00013810', 'site' => 'SMO', 'pt' => 'MTN', 'hari' => 83, 'sejak' => '10 Jul 2026'],
        ['nama' => 'C-1-00013788', 'sid' => 'C-1-00013788', 'site' => 'BMO 1', 'pt' => 'KDC', 'hari' => 79, 'sejak' => '14 Jul 2026'],
    ];
    $totalTindakan = $tindakan['total'] ?? count($perluTindakan);

    // ── Bar chart per site, dipecah kelompok jabatan struktural ───────────
    // Pakai data asli bila tersedia; kalau tidak, contoh dengan bentuk sama.
    // Dikelompokkan menurut Working Permit unit, tersaring SIMPER aktif.
    // Angka contoh memakai bentuk & makna yang sama dengan data aslinya.
    $site = $perSiteLive ?? [
        ['site' => 'BMO 2', 'a2b' => 502, 'hauler' => 687, 'massal' => 74, 'tanpa' => 348],
        ['site' => 'GMO', 'a2b' => 410, 'hauler' => 558, 'massal' => 47, 'tanpa' => 255],
        ['site' => 'LMO', 'a2b' => 325, 'hauler' => 470, 'massal' => 92, 'tanpa' => 141],
        ['site' => 'SMO', 'a2b' => 253, 'hauler' => 400, 'massal' => 12, 'tanpa' => 242],
        ['site' => 'BMO 1', 'a2b' => 137, 'hauler' => 208, 'massal' => 13, 'tanpa' => 58],
        ['site' => 'BMO 3', 'a2b' => 61, 'hauler' => 106, 'massal' => 6, 'tanpa' => 14],
    ];

    $grupWp = config('dms_roster.total_karyawan.wp_grup', []);
    $seriWp = [];
    foreach (config('dms_roster.total_karyawan.wp_prioritas', []) as $kunci) {
        if (isset($grupWp[$kunci])) {
            $seriWp[] = ['kunci' => $kunci, 'label' => $grupWp[$kunci]['label']];
        }
    }
    $seriWp[] = ['kunci' => 'tanpa', 'label' => config('dms_roster.total_karyawan.wp_grup_tanpa_label', 'Tanpa WP unit')];

    // Warna urut: A2B, Hauler, Angkutan Massal, lalu abu untuk "tanpa".
    $warnaWp = ['#487FFF', '#16A34A', '#F59E0B', '#CBD5E1'];
    foreach ($seriWp as $i => $s) {
        $seriWp[$i]['warna'] = $warnaWp[$i] ?? '#94A3B8';
        $seriWp[$i]['data'] = array_map(
            static fn (array $r): int => (int) ($r[$s['kunci']] ?? 0),
            $site,
        );
    }

    $siteLabel = array_column($site, 'site');
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

@if ($live && $ag)
  <div class="alert alert-success bg-success-100 text-success-600 border-success-100 px-24 py-13 mb-24 radius-8 d-flex gap-2 align-items-start" role="alert">
    <iconify-icon icon="solar:check-circle-bold" class="icon text-lg flex-shrink-0 mt-2"></iconify-icon>
    <div class="text-sm">
      <b>Data live.</b> Total karyawan, keempat tile, donat pelanggaran, heatmap harian, daftar perlu tindakan,
      dan tabel scorecard dihitung dari pola roster hasil sinkronisasi RFID
      (<code>dms_roster_pola</code> &times; <code>dms_roster_karyawan</code>, basis <code>wajib_cek</code>),
      memakai rumus yang sama dengan halaman Kepatuhan Roster (Live).
    </div>
  </div>
@else
  <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8 d-flex gap-2 align-items-start" role="alert">
    <iconify-icon icon="solar:info-circle-bold" class="icon text-lg flex-shrink-0 mt-2"></iconify-icon>
    <div class="text-sm">
      <b>Sebagian angka masih contoh.</b>
      @if (! $live)
        Kartu Total Karyawan belum bisa dibaca &mdash; koneksi database tidak tersedia.
      @endif
      @if (! $ag)
        Agregat roster belum tersedia: tabel sinkronisasi masih kosong atau belum pernah dikompilasi.
        Jalankan <code>php artisan dms:sync-roster-rfid --master --full</code> lebih dulu.
      @endif
    </div>
  </div>
@endif

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
                  <span class="text-secondary-light fw-medium text-sm d-block mb-2">
                    Total Karyawan
                    @if ($live)
                      <span class="text-success-600 fw-semibold" title="Diambil langsung dari database">&middot; live</span>
                      {{-- <span class="d-block" style="font-size:10.5px">punya SIMPER aktif</span> --}}
                    @endif
                  </span>
                  <h5 class="fw-bold mb-0 text-primary-light">{{ number_format($totalKaryawan, 0, ',', '.') }}</h5>
                </div>
               
              </div>
              {{-- <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
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
              </div> --}}
            </div>
            @if ($live)
              <div class="d-flex flex-wrap gap-2 mt-12">
                @foreach ($live['kelompok'] as $g)
                  <span class="bg-neutral-100 text-secondary-light px-10 py-2 radius-8 text-xs fw-medium"
                        title="{{ number_format($g['punya_simper_aktif'], 0, ',', '.') }} punya SIMPER aktif dari {{ number_format($g['total'], 0, ',', '.') }} karyawan aktif">
                    {{ $g['kelompok'] }}: <b class="text-primary-light">{{ number_format($g['punya_simper_aktif'], 0, ',', '.') }}</b>
                  </span>
                @endforeach
              </div>
              {{-- <div class="text-secondary-light text-xs mt-8">
                Karyawan AKTIF berjabatan operator/driver &amp; mekanik (daftar jabatan di
                <code>config/dms_roster.php</code>) yang <b>punya SIMPER aktif</b> &middot;
                tanpa SIMPER: {{ number_format($live['tanpa_simper'], 0, ',', '.') }} &middot;
                WP unit lolos: {{ number_format($live['wp_unit_passed'], 0, ',', '.') }}
              </div> --}}
            @endif

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-24">
              <span class="text-sm fw-semibold text-primary-light">
                Sebaran per Site
                <span class="text-secondary-light fw-normal">&middot; punya SIMPER aktif</span>
              </span>
              <div class="d-flex align-items-center gap-3 text-xs text-secondary-light flex-wrap">
                @foreach ($seriWp as $s)
                  <span><i class="ro-sw" style="background:{{ $s['warna'] }}"></i>{{ $s['label'] }}</span>
                @endforeach
              </div>
            </div>
            <div class="mt-8">
              {{-- Data chart dioper lewat atribut data-* supaya berkas skrip
                   (dimuat setelah ApexCharts) tidak perlu variabel PHP. --}}
              <div id="roBarChart" class="margin-16-minus"
                   data-kategori="{{ json_encode($siteLabel) }}"
                   data-seri="{{ json_encode(array_map(
                       static fn (array $s): array => ['name' => $s['label'], 'data' => $s['data']],
                       $seriWp,
                   )) }}"
                   data-warna="{{ json_encode(array_column($seriWp, 'warna')) }}"></div>
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
              <span class="ro-card__subtitle">
                @if ($peta)
                  Seberapa padat pelanggaran roster tiap hari sepanjang periode?
                  <span class="text-success-600 fw-semibold">&middot; {{ $peta['periode'] }}</span>
                @else
                  Hari apa pelanggaran roster paling sering terjadi?
                @endif
              </span>
            </div>
          </div>
          <div class="ro-card__badge">
            <iconify-icon icon="mdi:account-alert"></iconify-icon>
            <span>
              Terbanyak
              {{ $peta ? number_format($peta['puncak']['jumlah'], 0, ',', '.') : '72' }} pelanggaran
              &middot; {{ $peta['puncak']['tanggal'] ?? '04 Jun 2026' }}
            </span>
          </div>
        </div>

        <div class="ro-heatmap flex-grow-1 mb-8">
          <div class="ro-hm-scroll">
            <div class="ro-hm" style="--ro-cols: {{ $kolom }}">
              @foreach ($hariLabel as $r => $nama)
                <div class="ro-hm-ylabel">{{ $nama }}</div>
                <div class="ro-hm-row">{!! $selHeatmap($grid[$r] ?? [], $kolom) !!}</div>
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
          <span class="text-xs fw-medium" style="color:#64748B">
            Tingkat kepatuhan harian
            @if ($peta)
              {{-- Skala kuintil: lima kelompok berisi jumlah hari yang sama.
                   Ambang persen tetap tidak dipakai karena kepatuhan harian
                   bergerak di rentang sempit dan petanya jadi satu warna. --}}
              <span class="fw-normal">(skala kuintil)</span>
            @endif
          </span>
          @foreach ($legenda as $l)
            <span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B">
              <span class="ro-legend-key ro-hm-cell lvl-{{ $l['level'] }}"></span>{{ $l['teks'] }}
            </span>
          @endforeach
        </div>

        <div class="ro-tip ro-tip--amber mb-16 flex-shrink-0">
          <iconify-icon icon="solar:calendar-bold" class="flex-shrink-0"></iconify-icon>
          @if ($peta && $adaPolaHarian && count($duaTerpadat) === 2)
            <span>
              Pelanggaran paling padat jatuh di hari <b>{{ $duaTerpadat[0]['nama'] }}</b>
              (rata-rata {{ number_format($duaTerpadat[0]['rata'], 0, ',', '.') }} karyawan)
              dan <b>{{ $duaTerpadat[1]['nama'] }}</b>
              ({{ number_format($duaTerpadat[1]['rata'], 0, ',', '.') }} karyawan).
            </span>
          @elseif ($peta)
            <span>
              <b>Tidak ada pola hari-dalam-seminggu</b> &mdash; selisih hari terpadat dan terlonggar hanya
              {{ number_format($peta['sebaranHari'] * 100, 1, ',', '.') }}%. Wajar: flag pelanggaran
              berlaku per <b>blok</b> (sekali melewati ambang on-site, karyawan ter-flag berhari-hari
              berturut-turut), bukan per kejadian harian. Yang terbaca di heatmap adalah
              <b>naik-turun antar minggu</b>, dengan puncak {{ number_format($peta['puncak']['jumlah'], 0, ',', '.') }}
              karyawan pada {{ $peta['puncak']['tanggal'] }}.
            </span>
          @else
            <span>Pelanggaran paling padat jatuh di hari <b>Kamis</b> dan <b>Jumat</b> &mdash; berimpit dengan akhir blok kerja sebelum jadwal off.</span>
          @endif
        </div>

        <div class="row g-3 flex-shrink-0">
          @foreach ([
            {{-- Tanpa pola hari-dalam-seminggu, menampilkan "hari terbanyak"
                 jadi angka acak. Yang bermakna adalah tanggal puncaknya. --}}
            $peta && ! $adaPolaHarian
              ? ['solar:calendar-mark-bold', 'Puncak Pelanggaran',
                  $peta['puncak']['tanggal'],
                  number_format($peta['puncak']['jumlah'], 0, ',', '.').' karyawan ter-flag']
              : ['solar:calendar-mark-bold', 'Hari Terbanyak',
                  $duaTerpadat[0]['nama'] ?? 'Kamis',
                  $peta ? number_format($duaTerpadat[0]['rata'] ?? 0, 0, ',', '.').' pelanggaran (rata-rata)' : '72 pelanggaran'],
            ['solar:chart-2-bold', 'Rata-rata Harian',
              $peta ? number_format($peta['rata'], 0, ',', '.') : '38',
              'karyawan kena pelanggaran / hari'],
            ['solar:shield-check-bold', 'Kepatuhan Keseluruhan',
              $ag ? $pctTile($ag['total'] - $ag['pelanggaran']) : '82,4%',
              'tanpa pelanggaran sepanjang periode'],
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
          <h6 class="mb-0 fw-bold text-lg">Karyawan Perlu Tindakan</h6>
          <span class="text-secondary-light text-sm">
            {{ number_format($totalTindakan, 0, ',', '.') }} orang
            @if ($tindakan)
              <span class="text-success-600 fw-semibold">&middot; live</span>
            @endif
          </span>
        </div>
        <span class="ro-card__subtitle mt-4">
          Wajib cuti segera &mdash; on-site berjalan lebih dari {{ $ambangWajib }} hari tanpa cuti,
          diurutkan dari yang terpanjang.
        </span>

        <div class="mt-24 overflow-y-auto scroll-sm" style="max-height: 380px;">
          @foreach ($perluTindakan as $i => $p)
            <div class="d-flex align-items-center justify-content-between gap-3 {{ $i === count($perluTindakan) - 1 ? '' : 'mb-24' }}">
              <div class="d-flex align-items-center gap-2" style="min-width:0">
                <span class="w-40-px h-40-px radius-8 flex-shrink-0 bg-danger-focus text-danger-main d-flex justify-content-center align-items-center">
                  <iconify-icon icon="solar:close-circle-bold"></iconify-icon>
                </span>
                <div class="flex-grow-1" style="min-width:0">
                  <h6 class="text-md mb-0 fw-normal text-truncate">{{ $p['nama'] }}</h6>
                  <span class="text-sm text-secondary-light fw-normal">
                    {{ $p['sid'] }} &middot; {{ $p['site'] }} &middot; {{ $p['pt'] }}
                  </span>
                </div>
              </div>
              <span class="text-end flex-shrink-0">
                <span class="text-danger-main text-sm fw-bold d-block">{{ number_format($p['hari'], 0, ',', '.') }} hari</span>
                <span class="text-secondary-light text-xs">sejak {{ $p['sejak'] }}</span>
              </span>
            </div>
          @endforeach
        </div>

        @if ($tindakan && $totalTindakan > count($perluTindakan))
          <a href="{{ route('dms.roster-compliance.wajib-cuti') }}"
             class="btn btn-sm btn-outline-danger radius-8 w-100 mt-16 d-inline-flex align-items-center justify-content-center gap-1">
            <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
            Unduh daftar lengkap ({{ number_format($totalTindakan, 0, ',', '.') }})
          </a>
        @endif
      </div>
    </div>
  </div>

  {{-- Tabel scorecard: di bawah kartu heatmap & daftar perlu tindakan --}}
  @include('dms.partials._roster-scorecard')
</div>
