{{--
  Ringkasan Kepatuhan Roster — tampilan mengikuti dashboard /pnc-monitoring/dashboard.

  Hampir seluruh kartu sudah LIVE, memakai variabel yang dikirim route:
  $totalKaryawanLive / $perSiteLive (populasi + sebaran site), $agregatLive
  (hasil rule engine: pelanggaran, status), $heatmapLive (grid kalender
  harian), $karyawanLive (satu halaman baris scorecard), dan $regTerkiniLive
  (aturan banned per hari ini langsung dari RFID: empat tile + daftar
  Karyawan Perlu Tindakan).

  Setiap kartu punya cabang contoh: bila variabelnya null — tabel sinkronisasi
  belum terisi atau database tidak terjangkau — halaman tetap render dengan
  angka contoh, bukan 500. Pengecualian: kartu berbasis RFID menampilkan
  keadaan "tidak terjangkau", karena nama orang contoh mudah disangka asli.
  Variabel $judul, $subjudul, dan $lencana dikirim view pemanggil hanya untuk
  membedakan label halaman.
--}}

@php
    $judul = $judul ?? 'Ringkasan Kepatuhan Roster';
    $subjudul = $subjudul ?? 'Safety & Roster · Kepatuhan Jam Kerja Karyawan';
    $lencana = $lencana ?? ['teks' => 'DATA CONTOH', 'kelas' => 'bg-warning-focus text-warning-main'];

    // ── Total karyawan: LIVE bila tersedia, jika tidak pakai angka contoh ──
    // Angka utama = yang PUNYA SIMPER AKTIF, bukan seluruh karyawan aktif.
    // Populasi aktif ($aktifSemua) tetap dibawa sebagai konteks cakupan.
    $live = $totalKaryawanLive ?? null;
    $totalKaryawan = $live['punya_simper_aktif'] ?? 5588;
    $aktifSemua = $live['total'] ?? null;
    $persenSimper = ($live && $live['total'] > 0)
        ? $live['punya_simper_aktif'] / $live['total'] * 100
        : null;

    // ── Agregat pola roster (donat, kepatuhan keseluruhan) ────────────────
    // Sumbernya DmsRosterComplianceService (dipakai bersama halaman
    // Kepatuhan Roster Live), jadi angka di dua halaman tidak mungkin beda.
    $ag = $agregatLive ?? null;
    $basisTile = $ag['total'] ?? 0;
    $pctTile = static fn (int $x): string => $basisTile > 0
        ? number_format($x / $basisTile * 100, 1, ',', '.').'%'
        : '0%';

    // ── Donat pelanggaran ─────────────────────────────────────────────────
    // Penyebutnya selalu populasi yang BENAR-BENAR dievaluasi ($ag['total']),
    // bukan $totalKaryawan: kedua angka bisa berbeda bila ada karyawan yang
    // belum punya pola roster, dan mencampurnya menghasilkan persen palsu.
    $pelanggaran = $ag['pelanggaran'] ?? 983;
    $patuh = $ag ? $ag['total'] - $ag['pelanggaran'] : 4605;
    $basisDonat = $patuh + $pelanggaran;
    $persenPatuh = $basisDonat > 0 ? $patuh / $basisDonat * 100 : 0;
    $persenPelanggaran = 100 - $persenPatuh;

    // ── Empat tile: aturan banned per hari ini, langsung dari RFID ────────
    // Sumbernya DmsRosterRegLiveService ($regTerkiniLive) — angka yang sama
    // dengan modal detailnya. Persen dihitung terhadap karyawan yang benar-
    // benar dinilai aturan ini (semua PT, tanpa master banned aktif), bukan total
    // karyawan di kartu kiri.
    $regKini = $regTerkiniLive ?? null;
    // Hasil terakhir dipakai karena pembaruan dari RFID gagal → tampilkan
    // jam datanya; null sama sekali → tampilkan alasannya.
    $regBasi = (bool) ($regKini['basi'] ?? false);
    $regGalat = (string) ($regKini['galat'] ?? $regTerkiniGalat ?? '');
    $regStatus = $regKini
        ? ($regBasi ? 'data RFID '.$regKini['diambil'] : 'RFID · '.$regKini['diambil'])
        : 'RFID tidak terjangkau'.($regGalat !== '' ? ': '.$regGalat : '');
    $populasiAturan = (int) ($regKini['populasi'] ?? 0);
    $borderTile = [
        'border border-top-0',
        'border border-top-0 border-start-0 border-end-0',
        'border border-top-0 border-bottom-0',
        'border border-top-0 border-bottom-0 border-start-0 border-end-0',
    ];
    // Tile > 70 hari & > 13 hari beruntun: orang yang sudah dimasukkan ke
    // Master Roster Banned dikeluarkan dari hitungan RFID live, jadi angkanya
    // = baris master aturan itu yang "Belum Banned" + "Belum Ada Log"
    // (menunggu dieksekusi, dipisah lewat aturan_pelanggaran) + pelanggar
    // live yang belum masuk master.
    $menungguMaster = [];
    foreach (['onsite', 'beruntun'] as $kodeAturan) {
        try {
            $qTunggu = \App\Services\Dms\Roster\DmsRosterBannedAktif::menungguPerAturan($kodeAturan);
            $menungguMaster[$kodeAturan] = $qTunggu === null ? null : $qTunggu->distinct()->count('m.sid');
        } catch (\Throwable) {
            $menungguMaster[$kodeAturan] = null;
        }
    }
    $tiles = [];
    foreach (\App\Services\Dms\Roster\DmsRosterAturanKartu::daftar() as $i => $k) {
        $n = \App\Services\Dms\Roster\DmsRosterAturanKartu::jumlahOrang($k, $regKini);
        $tile = $k + [
            'jumlah' => $n === null ? '—' : number_format($n, 0, ',', '.'),
            'persen' => $n !== null && $populasiAturan > 0
                ? number_format($n / $populasiAturan * 100, 1, ',', '.').'% dari '.number_format($populasiAturan, 0, ',', '.').' karyawan'
                : null,
            'tab' => array_key_first($k['item']),
            'border' => $borderTile[$i] ?? 'border',
            'master' => null,
        ];
        $tunggu = $menungguMaster[$k['id']] ?? null;
        if ($tunggu !== null && ($tunggu > 0 || $k['id'] === 'onsite')) {
            $tile['jumlah'] = number_format($tunggu + (int) $n, 0, ',', '.');
            $tile['persen'] = null;
            $tile['master'] = number_format($tunggu, 0, ',', '.').' menunggu banned di Master (belum ada log)'
                .((int) $n > 0 ? ' + '.number_format((int) $n, 0, ',', '.').' baru dari RFID' : '');
        }
        $tiles[] = $tile;
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
    // = yang harus di-banned sekarang: "Pelanggaran periode kerja > 70 hari"
    // dari RFID langsung — daftar yang SAMA dengan tile & modalnya (semua PT,
    // tanpa master banned), sudah urut periode terpanjang lebih dulu.
    // Tanpa data RFID kartu menampilkan keadaan kosong, bukan nama contoh.
    $perluTindakan = $regKini['daftar']['onsite'] ?? [];
    $totalTindakan = count($perluTindakan);
    $jatuhTempo = count(array_filter($perluTindakan, static fn (array $p): bool => (bool) ($p['jatuh_tempo'] ?? false)));
    $onsiteMaks = (int) config('dms_roster.reg_live.aturan.onsite_maks', 70);
    $hariBanned = (int) config('dms_roster.reg_live.aturan.onsite_banned_hari_ke', 74);

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
            {{-- Tiap tile membuka modal detail aturannya (daftar nama, unduh
                 CSV, timeline tap RFID per orang) dari _roster-reg-terkini. --}}
            @foreach ($tiles as $t)
              <div class="col-6 p-0 m-0">
                <button type="button"
                        class="ro-aturan-tile card-body p-24 h-100 w-100 text-start bg-transparent d-flex flex-column justify-content-center {{ $t['border'] }}"
                        data-bs-toggle="modal" data-bs-target="#roModalTemuan-{{ $t['id'] }}" data-ro-tab="{{ $t['tab'] }}"
                        title="{{ $t['catatan'] }}" @disabled(! $regKini)>
                  <span class="mb-12 w-44-px h-44-px bg-{{ $t['warna'] }}-focus text-{{ $t['warna'] }}-main border flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6">
                    <iconify-icon icon="{{ $t['ikon'] }}" class="icon"></iconify-icon>
                  </span>
                  <span class="d-block mb-1 fw-bold text-secondary-light text-md">
                    {{ $t['judul'] }} <span class="text-{{ $t['warna'] }}-main">{{ $t['ambang'] }}</span>
                  </span>
                  <span class="d-block h6 fw-semibold text-primary-light mb-1">
                    {{ $t['jumlah'] }} <span class="text-sm text-secondary-light fw-normal">orang</span>
                  </span>
                  <span class="d-block text-sm mb-0">
                    <span class="bg-{{ $t['warna'] }}-focus text-{{ $t['warna'] }}-main px-1 rounded-2 fw-medium text-sm">{{ $t['perlakuan'] }}</span>
                    @if ($t['master'] !== null)
                      <span class="d-block text-xs text-secondary-light mt-4">{{ $t['master'] }}</span>
                    @else
                      {{ $t['persen'] ?? ($regKini ? '' : 'RFID tidak terjangkau saat ini') }}
                    @endif
                    @if (! $regKini && $regGalat !== '')
                      <span class="d-block text-xs text-danger-main mt-4" title="{{ $regGalat }}">{{ \Illuminate\Support\Str::limit($regGalat, 70) }}</span>
                    @endif
                  </span>
                  @if ($regKini)
                    <span class="d-flex justify-content-between gap-2 text-xs mt-8">
                      <span class="{{ $regBasi ? 'text-warning-main' : 'text-secondary-light' }}"
                            @if ($regBasi) title="Pembaruan dari RFID gagal{{ $regGalat !== '' ? ': '.$regGalat : '' }} — menampilkan hasil terakhir." @endif>
                        {{ $regBasi ? 'data '.$regKini['diambil'] : 'per '.$regKini['diambil'] }}
                      </span>
                      <span class="fw-semibold text-primary-600">Lihat detail &rarr;</span>
                    </span>
                  @endif
                </button>
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

  {{-- ══════════ REG-1/2/3 PER HARI INI ══════════ --}}
  @include('dms.partials._roster-reg-terkini')

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
      <div class="card-body p-24 d-flex flex-column">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
          <h6 class="mb-0 fw-bold text-lg">Karyawan Perlu Tindakan</h6>
          <span class="text-secondary-light text-sm">
            @if ($regKini)
              {{ number_format($totalTindakan, 0, ',', '.') }} orang
              <span class="{{ $regBasi ? 'text-warning-main' : 'text-success-600' }} fw-semibold" title="{{ $regBasi ? 'Pembaruan dari RFID gagal — menampilkan hasil terakhir. '.$regGalat : '' }}">&middot; {{ $regStatus }}</span>
            @else
              &mdash;
            @endif
          </span>
        </div>
        <span class="ro-card__subtitle mt-4">
          Harus di-banned sekarang &mdash; periode kerja lebih dari {{ $onsiteMaks }} hari sejak cuti terakhir
          (banned 14 hari di hari ke-{{ $hariBanned }}), urut dari yang terpanjang.
          @if ($jatuhTempo > 0)
            <b class="text-danger-main">{{ number_format($jatuhTempo, 0, ',', '.') }} sudah lewat hari ke-{{ $hariBanned }}.</b>
          @endif
        </span>

        {{-- Daftar mengisi sisa tinggi kartu lalu bergulir, bukan menentukan
             tinggi baris: kalau tidak, kartu heatmap di sebelahnya ikut
             memanjang dan menyisakan ruang kosong di bawah grid. --}}
        <div class="ro-isi-sisa mt-24">
        <div class="ro-isi-sisa__gulir overflow-y-auto scroll-sm">
          @if (! $regKini)
            <p class="text-secondary-light text-sm mb-0">
              RFID tidak terjangkau saat ini; muat ulang beberapa saat lagi.
              @if ($regGalat !== '')
                <span class="d-block text-xs text-danger-main mt-4">{{ \Illuminate\Support\Str::limit($regGalat, 160) }}</span>
              @endif
            </p>
          @elseif ($perluTindakan === [])
            <p class="text-success-600 text-sm fw-medium mb-0">Tidak ada karyawan yang harus di-banned per hari ini.</p>
          @endif
          {{-- Klik nama membuka modal "Pelanggaran periode kerja" (daftar
               lengkap + timeline tap RFID) dari _roster-reg-terkini. --}}
          @foreach ($perluTindakan as $i => $p)
            <button type="button"
                    class="ro-tindakan-item w-100 text-start bg-transparent border-0 p-0 d-flex align-items-center justify-content-between gap-3 {{ $loop->last ? '' : 'mb-20' }}"
                    data-bs-toggle="modal" data-bs-target="#roModalTemuan-onsite" data-ro-tab="onsite"
                    title="{{ $p['keterangan'] }}">
              <span class="d-flex align-items-center gap-2" style="min-width:0">
                <span class="w-40-px h-40-px radius-8 flex-shrink-0 bg-danger-focus text-danger-main d-flex justify-content-center align-items-center">
                  <iconify-icon icon="solar:forbidden-circle-bold"></iconify-icon>
                </span>
                <span class="flex-grow-1" style="min-width:0">
                  <span class="d-block text-md text-primary-light text-truncate">{{ $p['nama'] }}</span>
                  <span class="d-block text-sm text-secondary-light text-truncate">
                    {{ $p['sid'] }} &middot; {{ $p['site'] }} &middot; {{ $p['pt'] }}
                  </span>
                </span>
              </span>
              <span class="text-end flex-shrink-0">
                <span class="text-danger-main text-sm fw-bold d-block">{{ $p['nilai'] }}</span>
                @if ($p['jatuh_tempo'] ?? false)
                  <span class="badge bg-danger-focus text-danger-main text-xs" title="Hari ke-{{ $hariBanned }} jatuh {{ $p['tgl_banned'] ?? '' }}">Jatuh tempo</span>
                @else
                  <span class="text-secondary-light text-xs">banned {{ $p['tgl_banned'] ?? '' }}</span>
                @endif
              </span>
            </button>
          @endforeach
        </div>
        </div>

        @if ($regKini && $totalTindakan > 0)
          <div class="d-flex gap-2 mt-16">
            <button type="button" data-bs-toggle="modal" data-bs-target="#roModalTemuan-onsite" data-ro-tab="onsite"
                    class="btn btn-sm btn-outline-primary radius-8 flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1">
              <iconify-icon icon="solar:list-bold" class="icon"></iconify-icon>
              Lihat detail
            </button>
            <a href="{{ route('dms.roster-compliance.terkini.unduh', ['kode' => 'onsite']) }}"
               class="btn btn-sm btn-outline-danger radius-8 d-inline-flex align-items-center justify-content-center gap-1"
               title="Unduh daftar lengkap (CSV)">
              <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
              Unduh ({{ number_format($totalTindakan, 0, ',', '.') }})
            </a>
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- Tabel scorecard: di bawah kartu heatmap & daftar perlu tindakan --}}
  @include('dms.partials._roster-scorecard')
</div>
