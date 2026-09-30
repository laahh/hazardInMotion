{{--
  Scorecard per Karyawan & Timeline — kolom dan tata letaknya mengikuti tabel
  scorecard aplikasi referensi, temanya disesuaikan dengan halaman ini (terang).

  SEPENUHNYA STATIS: seluruh baris, angka, dan batang timeline ditulis/dihitung
  di dalam berkas ini. Tidak ada query, service, maupun fetch. Kontrol filter
  sengaja tidak difungsikan karena tidak ada data untuk disaring.
--}}

@php
    // 1 Jan – 30 Sep 2026 = 273 hari
    $tlHari = 273;
    $tlAwal = \Carbon\Carbon::create(2026, 1, 1);
    // Didefinisikan lokal supaya partial ini tidak bergantung pada scope pemanggil.
    $hariLabel = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    // Ditulis eksplisit, bukan translatedFormat(): locale aplikasi berbahasa
    // Inggris sehingga akan muncul "May"/"Aug", tidak konsisten dengan
    // "Mei"/"Agu" yang dipakai kolom lain di tabel ini.
    $bulanSingkat = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $hariSingkat = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    /**
     * Pola harian satu karyawan dalam bentuk grid kalender (baris Senin–Minggu
     * x kolom minggu), format yang sama dengan kartu "Pola Kepatuhan Roster
     * Harian". Deterministik dari $benih supaya tampilannya tetap sama tiap
     * kali halaman dimuat.
     *
     * Selnya memakai SKALA KEPATUHAN yang sama dengan kartu "Pola Kepatuhan
     * Roster Harian" (0 = tidak ada data, 1 = <50%, sampai 5 = 100%), bukan
     * warna kode shift — supaya kedua heatmap di halaman ini berbicara dalam
     * bahasa visual yang sama.
     *
     * @return array{grid: array<int, array<int, array{lvl:int,label:string}|null>>, kolom: int, labelKolom: array<int, string>}
     */
    $tlGrid = function (string $benih, bool $adaPelanggaran) use ($tlHari, $tlAwal, $bulanSingkat, $hariSingkat): array {
        $grid = array_fill(0, 7, []);
        $kolom = 0;
        // Label sumbu-X memakai NAMA BULAN (bukan nomor minggu), sama seperti
        // penggaris kartu "Pola Kepatuhan Roster Harian": kolom minggu yang
        // memuat tanggal 1 diberi nama bulannya.
        $labelKolom = [];

        for ($i = 0; $i < $tlHari; $i++) {
            $t = $tlAwal->copy()->addDays($i);
            if ($i > 0 && $t->dayOfWeek === \Carbon\Carbon::MONDAY) {
                $kolom++;
            }
            if ($i === 0 || $t->day === 1) {
                $labelKolom[$kolom] ??= $bulanSingkat[$t->month];
            }

            // Hari bermasalah dikelompokkan per blok 6 hari supaya terbaca
            // sebagai rentetan, bukan titik acak.
            $blokBuruk = $adaPelanggaran
                && hexdec(substr(md5($benih . ':blok:' . intdiv($i, 6)), 0, 2)) % 5 === 0;

            $h = hexdec(substr(md5($benih . ':' . $i), 0, 3)) % 100;
            $lvl = $blokBuruk
                ? ($h < 55 ? 1 : 2)              // hari melanggar → merah / kuning
                : match (true) {
                    $h < 4 => 0,                  // tidak ada data
                    $h < 16 => 3,                 // 75–89%
                    $h < 52 => 4,                 // 90–99%
                    default => 5,                 // 100%
                };

            $baris = ($t->dayOfWeek + 6) % 7; // 0 = Senin
            $grid[$baris][$kolom] = [
                'lvl' => $lvl,
                'label' => $hariSingkat[$t->dayOfWeek].', '.$t->day.' '.$bulanSingkat[$t->month].' '.$t->year,
            ];
        }

        return ['grid' => $grid, 'kolom' => $kolom + 1, 'labelKolom' => $labelKolom];
    };

    // Sama persis dengan legenda kartu "Pola Kepatuhan Roster Harian".
    $lvlLabel = [
        0 => 'tidak ada data',
        1 => 'kepatuhan <50%',
        2 => 'kepatuhan 50–74%',
        3 => 'kepatuhan 75–89%',
        4 => 'kepatuhan 90–99%',
        5 => 'kepatuhan 100%',
    ];

    /**
     * Render satu baris sel heatmap sekaligus. Dikerjakan di PHP, bukan lewat
     * perulangan Blade, karena 8 karyawan x 280 sel membuat indentasi template
     * saja menambah ratusan KB ke HTML hasil render.
     *
     * @param  array<int, array{kode:string,merah:bool,label:string}|null>  $baris
     */
    $selBaris = function (array $baris, int $kolom) use ($lvlLabel): string {
        $out = '';
        for ($c = 0; $c < $kolom; $c++) {
            $sel = $baris[$c] ?? null;
            if ($sel === null) {
                $out .= '<span class="ro-hm-cell lvl-0 is-empty"></span>';

                continue;
            }

            $judul = $sel['label'].' — '.$lvlLabel[$sel['lvl']];
            $out .= '<span class="ro-hm-cell lvl-'.$sel['lvl'].'" title="'.e($judul).'"></span>';
        }

        return $out;
    };

    $statusPill = [
        'Off' => 'is-off',
        'Shift Pagi' => 'is-pagi',
        'Shift Malam' => 'is-malam',
        'Cuti' => 'is-cuti',
        'Overshift' => 'is-over',
    ];

    // ── Baris contoh ─────────────────────────────────────────────────────
    $scorecard = [
        [
            'sid' => 'RM9H6', 'pt' => 'PAMA', 'nama' => 'A AZIZ HIDAYAT', 'jabatan' => 'OPERATOR TP',
            'site' => 'GMO', 'roster' => 5, 'onsite' => 57, 'pagi' => 17, 'malam' => 21, 'off' => 19, 'cuti' => 0,
            'shiftMaks' => ['11 hari beruntun', '17 Sep–27 Sep', false],
            'onsiteMaks' => ['57 hari', 'roster ke-5 · 5 Agu–30 Sep', false],
            'cutiMin' => ['6 hari', 'roster ke-1 · 1 Jan–6 Jan', true],
            'status' => 'Off', 'pelanggaran' => true,
        ],
        [
            'sid' => 'R94GU', 'pt' => 'ARC', 'nama' => 'A MUH FATRA AULIA RAMADHAN', 'jabatan' => 'Driver LV',
            'site' => 'BMO 2', 'roster' => 6, 'onsite' => 66, 'pagi' => 50, 'malam' => 0, 'off' => 16, 'cuti' => 0,
            'shiftMaks' => ['10 hari beruntun', '9 Agu–18 Agu', false],
            'onsiteMaks' => ['66 hari', 'roster ke-6 · 27 Jul–30 Sep', false],
            'cutiMin' => ['7 hari', 'roster ke-2 · 11 Feb–17 Feb', false],
            'status' => 'Shift Pagi', 'pelanggaran' => false,
        ],
        [
            'sid' => 'IMPWJ', 'pt' => 'MIJ', 'nama' => 'A MULYANSYAH', 'jabatan' => 'Driver DT',
            'site' => 'GMO', 'roster' => 3, 'onsite' => 32, 'pagi' => 16, 'malam' => 11, 'off' => 5, 'cuti' => 0,
            'shiftMaks' => ['7 hari beruntun', '30 Agu–5 Sep', false],
            'onsiteMaks' => ['137 hari', 'roster ke-2 · 31 Mar–14 Agu', true],
            'cutiMin' => ['15 hari', 'roster ke-2 · 15 Agu–29 Agu', false],
            'status' => 'Shift Pagi', 'pelanggaran' => true,
        ],
        [
            'sid' => 'HRYX0', 'pt' => 'KDC', 'nama' => 'A RAHMANSYAH', 'jabatan' => 'OPERATOR DT',
            'site' => 'BMO 1', 'roster' => 4, 'onsite' => 16, 'pagi' => 8, 'malam' => 7, 'off' => 1, 'cuti' => 0,
            'shiftMaks' => ['8 hari beruntun', '15 Sep–22 Sep', false],
            'onsiteMaks' => ['74 hari', 'roster ke-3 · 19 Jun–31 Agu', true],
            'cutiMin' => ['14 hari', 'roster ke-3 · 1 Sep–14 Sep', false],
            'status' => 'Shift Pagi', 'pelanggaran' => true,
        ],
        [
            'sid' => 'WRNGM', 'pt' => 'PMJ', 'nama' => 'A RASMIL', 'jabatan' => 'DRIVER',
            'site' => 'GMO', 'roster' => 4, 'onsite' => 41, 'pagi' => 16, 'malam' => 12, 'off' => 13, 'cuti' => 0,
            'shiftMaks' => ['6 hari beruntun', '1 Sep–6 Sep', false],
            'onsiteMaks' => ['90 hari', 'roster ke-3 · 12 Mei–9 Agu', true],
            'cutiMin' => ['10 hari', 'roster ke-1 · 4 Feb–13 Feb', true],
            'status' => 'Shift Pagi', 'pelanggaran' => true,
        ],
        [
            'sid' => 'QK82L', 'pt' => 'BUMA', 'nama' => 'A SYAHRUL GUNAWAN', 'jabatan' => 'Mechanic - OB Haulers',
            'site' => 'BMO 2', 'roster' => 5, 'onsite' => 68, 'pagi' => 30, 'malam' => 26, 'off' => 12, 'cuti' => 0,
            'shiftMaks' => ['9 hari beruntun', '2 Sep–10 Sep', false],
            'onsiteMaks' => ['68 hari', 'roster ke-5 · 25 Jul–30 Sep', false],
            'cutiMin' => ['17 hari', 'roster ke-4 · 8 Jul–24 Jul', false],
            'status' => 'Shift Malam', 'pelanggaran' => false,
        ],
        [
            'sid' => 'TZ4P1', 'pt' => 'MTN', 'nama' => 'ABDUL HARIS NASUTION', 'jabatan' => 'OPERATOR EXCAVATOR 200T',
            'site' => 'SMO', 'roster' => 2, 'onsite' => 81, 'pagi' => 44, 'malam' => 24, 'off' => 13, 'cuti' => 0,
            'shiftMaks' => ['15 hari beruntun', '11 Agu–25 Agu', true],
            'onsiteMaks' => ['81 hari', 'roster ke-2 · 12 Jul–30 Sep', true],
            'cutiMin' => ['9 hari', 'roster ke-1 · 3 Jul–11 Jul', true],
            'status' => 'Overshift', 'pelanggaran' => true,
        ],
        [
            'sid' => 'VD7MQ', 'pt' => 'FAD', 'nama' => 'ACHMAD FAUZAN', 'jabatan' => 'DRIVER WT 30KL',
            'site' => 'LMO', 'roster' => 6, 'onsite' => 24, 'pagi' => 11, 'malam' => 9, 'off' => 4, 'cuti' => 14,
            'shiftMaks' => ['5 hari beruntun', '20 Sep–24 Sep', false],
            'onsiteMaks' => ['58 hari', 'roster ke-4 · 1 Jun–28 Jul', false],
            'cutiMin' => ['14 hari', 'roster ke-6 · 17 Sep–30 Sep', false],
            'status' => 'Cuti', 'pelanggaran' => false,
        ],
    ];
@endphp

<div class="col-12">
  <div class="card ro-card">
    <div class="card-body p-24">

      <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-16">
        <div>
          <h6 class="mb-1 fw-bold text-lg ro-card__title">Scorecard per Karyawan &amp; Timeline</h6>
          <span class="ro-card__subtitle">Ringkasan roster tiap karyawan beserta pola kerja hariannya sepanjang periode</span>
        </div>
        <div class="ro-sc-legend">
          <span class="fw-medium">Tingkat kepatuhan</span>
          <span><i class="ro-sw sw-0"></i>Tidak ada data</span>
          <span><i class="ro-sw sw-1"></i>&lt;50%</span>
          <span><i class="ro-sw sw-2"></i>50–74%</span>
          <span><i class="ro-sw sw-3"></i>75–89%</span>
          <span><i class="ro-sw sw-4"></i>90–99%</span>
          <span><i class="ro-sw sw-5"></i>100%</span>
        </div>
      </div>

      {{-- Toolbar tampilan saja — tidak difungsikan karena halaman ini mockup. --}}
      <div class="ro-sc-toolbar mb-16">
        <div class="row g-2 align-items-center">
          <div class="col-xl-3 col-md-4 col-sm-6">
            <input type="search" class="form-control form-control-sm" placeholder="Cari nama / kode SID…" disabled>
          </div>
          @foreach (['Semua status', 'Semua notes', 'Semua roster', 'Semua jabatan', 'Semua perusahaan'] as $ph)
            <div class="col-xl-2 col-md-4 col-sm-6">
              <select class="form-select form-select-sm" disabled><option>{{ $ph }}</option></select>
            </div>
          @endforeach
          <div class="col-xl-1 col-md-4 col-sm-6">
            <button type="button" class="btn btn-sm btn-outline-success-600 radius-8 w-100" disabled>
              <iconify-icon icon="solar:file-download-bold" class="align-middle"></iconify-icon>
            </button>
          </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mt-12">
          <button type="button" class="btn btn-sm btn-outline-success-600 radius-8" disabled>Detail harian</button>
          <button type="button" class="btn btn-sm btn-outline-danger radius-8" disabled>Wajib cuti (segera)</button>
          <span class="text-secondary-light text-sm ms-8">Periode:</span>
          @foreach (['Kuartal', 'Bulan', 'Minggu'] as $p)
            <select class="form-select form-select-sm w-auto" disabled><option>{{ $p }}</option></select>
          @endforeach
          <input type="date" class="form-control form-control-sm w-auto" value="2026-01-01" disabled>
          <span class="text-secondary-light">–</span>
          <input type="date" class="form-control form-control-sm w-auto" value="2026-09-30" disabled>
          <button type="button" class="btn btn-sm btn-outline-primary-600 radius-8" disabled>Reset</button>
        </div>
      </div>

      <div class="ro-sc-scroll">
        <table class="ro-sc-table">
          <thead>
            <tr>
              <th class="num">#</th>
              <th>SID</th>
              <th>Perusahaan</th>
              <th>Nama ▲</th>
              <th>Jabatan</th>
              <th>Site</th>
              <th class="num">Roster</th>
              <th class="num">On-site</th>
              <th class="num">Pagi</th>
              <th class="num">Malam</th>
              <th class="num">Off</th>
              <th class="num">Cuti</th>
              <th>Shift kerja maks (roster kini)</th>
              <th>On-site maks (all roster)</th>
              <th>Cuti min (all roster)</th>
              <th>Status</th>
              <th>Notes</th>
              <th>Timeline harian</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($scorecard as $i => $r)
              <tr>
                <td class="num ro-sc-muted">{{ $i + 1 }}</td>
                <td class="fw-semibold">{{ $r['sid'] }}</td>
                <td>{{ $r['pt'] }}</td>
                <td class="fw-medium">{{ $r['nama'] }}</td>
                <td>{{ $r['jabatan'] }}</td>
                <td>{{ $r['site'] }}</td>
                <td class="num">{{ $r['roster'] }}</td>
                <td class="num">{{ $r['onsite'] }}</td>
                <td class="num">{{ $r['pagi'] }}</td>
                <td class="num">{{ $r['malam'] }}</td>
                <td class="num">{{ $r['off'] }}</td>
                <td class="num">{{ $r['cuti'] }}</td>

                @foreach (['shiftMaks', 'onsiteMaks', 'cutiMin'] as $kol)
                  @php [$utama, $sub, $bad] = $r[$kol]; @endphp
                  <td>
                    <span class="{{ $bad ? 'ro-sc-bad' : 'fw-medium' }}">
                      @if ($bad) ⚠ @endif{{ $utama }}
                    </span>
                    <span class="ro-sc-sub {{ $bad ? 'ro-sc-bad' : '' }}">{{ $sub }}</span>
                  </td>
                @endforeach

                <td><span class="ro-sc-pill {{ $statusPill[$r['status']] ?? 'is-off' }}">{{ $r['status'] }}</span></td>
                <td>
                  @if ($r['pelanggaran'])
                    <span class="ro-sc-note-red">⚠ Pelanggaran regulasi</span>
                  @else
                    <span class="ro-sc-muted">—</span>
                  @endif
                </td>
                <td>
                  <button class="ro-sc-toggle" type="button" data-bs-toggle="collapse"
                          data-bs-target="#tl-{{ $r['sid'] }}" aria-expanded="false"
                          aria-controls="tl-{{ $r['sid'] }}">
                    <iconify-icon icon="solar:alt-arrow-down-linear"></iconify-icon>
                    Lihat pola harian
                  </button>
                </td>
              </tr>

              {{-- Baris collapse: pola harian karyawan ini, format kalender
                   sama dengan kartu "Pola Kepatuhan Roster Harian". --}}
              @php $pola = $tlGrid($r['sid'], $r['pelanggaran']); @endphp
              <tr>
                <td colspan="18" style="padding:0;border-bottom:0">
                  <div class="collapse" id="tl-{{ $r['sid'] }}">
                    <div class="ro-sc-detail">
                     <div class="ro-sc-detail__panel">
                      <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-16">
                        <div>
                          <div class="ro-sc-detail__title">Pola Harian &mdash; {{ $r['nama'] }}</div>
                          <div class="ro-sc-detail__sub">{{ $r['sid'] }} &middot; {{ $r['pt'] }} &middot; {{ $r['site'] }} &middot; 1 Jan – 30 Sep 2026</div>
                        </div>
                        <div class="ro-sc-legend">
                          <span class="fw-medium">Tingkat kepatuhan</span>
                          <span><i class="ro-sw sw-0"></i>Tidak ada data</span>
                          <span><i class="ro-sw sw-1"></i>&lt;50%</span>
                          <span><i class="ro-sw sw-2"></i>50–74%</span>
                          <span><i class="ro-sw sw-3"></i>75–89%</span>
                          <span><i class="ro-sw sw-4"></i>90–99%</span>
                          <span><i class="ro-sw sw-5"></i>100%</span>
                        </div>
                      </div>

                      <div class="ro-heatmap">
                        <div class="ro-hm-scroll">
                          <div class="ro-hm" style="--ro-cols: {{ $pola['kolom'] }}">
                            @foreach ($hariLabel as $baris => $namaHari)
                              <div class="ro-hm-ylabel">{{ $namaHari }}</div>
                              <div class="ro-hm-row">{!! $selBaris($pola['grid'][$baris], $pola['kolom']) !!}</div>
                            @endforeach

                            <div class="ro-hm-corner"></div>
                            <div class="ro-hm-xlabels">@for ($c = 0; $c < $pola['kolom']; $c++)<div class="ro-hm-xlabel {{ isset($pola['labelKolom'][$c]) ? 'is-month' : '' }}"><span>{{ $pola['labelKolom'][$c] ?? '' }}</span></div>@endfor</div>
                          </div>
                        </div>
                      </div>
                     </div>
                    </div>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-16">
        <span class="text-secondary-light text-sm">
          Menampilkan {{ count($scorecard) }} baris contoh &middot; geser tabel ke kanan untuk melihat timeline harian
        </span>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-primary-600 radius-8" disabled>&laquo; Sebelumnya</button>
          <button type="button" class="btn btn-sm btn-outline-primary-600 radius-8" disabled>Berikutnya &raquo;</button>
        </div>
      </div>

    </div>
  </div>
</div>
