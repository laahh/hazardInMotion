{{--
  Scorecard per Karyawan & Timeline — kolom dan tata letaknya mengikuti tabel
  scorecard aplikasi referensi, temanya disesuaikan dengan halaman ini (terang).

  Barisnya LIVE: $karyawanLive berisi satu halaman hasil evaluasi rule engine
  atas pola roster nyata, lengkap dengan metadata paginasi. Nomor halaman
  dibawa lewat query ?hal=N. Baris contoh di bawah hanya dipakai bila tabel
  sinkronisasi belum terisi.

  Toolbar filter (cari, status, notes, roster, kategori, perusahaan, site,
  periode, urutan kolom, unduh CSV) bekerja lewat query string dan diproses
  DmsRosterComplianceService — sanitasinya sama dengan halaman Kepatuhan
  Roster (Live), lihat RosterComplianceController::filters().
--}}

@php
    // ── Sumber data ───────────────────────────────────────────────────────
    // $karyawanLive datang dari DmsRosterOverviewKaryawanReader (tabel lokal
    // hasil sinkronisasi RFID). Bila tidak ada, jatuh ke baris contoh.
    $live = $karyawanLive ?? null;

    $tlAwal = \Carbon\Carbon::create($live['tahun'] ?? 2026, 1, 1);
    $tlHari = $live['panjang'] ?? 273;
    // Label rentang di kepala panel collapse — ikut panjang data sebenarnya,
    // jangan ditulis tetap supaya tidak berbohong setelah sinkronisasi maju.
    $tlRentang = $tlAwal->translatedFormat('j M').' – '
        .$tlAwal->copy()->addDays(max(0, $tlHari - 1))->translatedFormat('j M Y');
    // Didefinisikan lokal supaya partial ini tidak bergantung pada scope pemanggil.
    $hariLabel = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $kodeLabel = ['P' => 'Shift Pagi', 'M' => 'Shift Malam', 'o' => 'Off', 'c' => 'Cuti'];

    /**
     * Susun pola harian jadi grid kalender (baris Senin–Minggu x kolom minggu),
     * format yang sama dengan kartu "Pola Kepatuhan Roster Harian".
     *
     * @param  string  $pola   satu karakter per hari (P/M/o/c)
     * @param  list<bool>  $merah  hari ter-flag pelanggaran
     * @return array{grid: array<int, array<int, array{kode:string,merah:bool,label:string}|null>>, kolom: int}
     */
    // Hari ke-N on-site sejak cuti terakhir yang WAJIB sudah cuti: aturan
    // banned periode kerja > 70 hari → batasnya hari ke-71.
    $batasWajibCuti = (int) config('dms_roster.reg_live.aturan.onsite_maks', 70) + 1;

    $gridDari = function (string $pola, array $merah) use ($tlAwal, $batasWajibCuti): array {
        $grid = array_fill(0, 7, []);
        $kolom = 0;
        $n = strlen($pola);
        // Hari ke-N on-site: kerja + off pendek dihitung, mulai lagi dari 1
        // setelah blok cuti — sama dengan cara aturan periode kerja menghitung.
        $ke = 0;

        for ($i = 0; $i < $n; $i++) {
            $t = $tlAwal->copy()->addDays($i);
            if ($i > 0 && $t->dayOfWeek === \Carbon\Carbon::MONDAY) {
                $kolom++;
            }
            $ke = $pola[$i] === 'c' ? 0 : $ke + 1;

            $grid[($t->dayOfWeek + 6) % 7][$kolom] = [
                'kode' => $pola[$i],
                'merah' => (bool) ($merah[$i] ?? false),
                'label' => $t->translatedFormat('D, d M Y'),
                'ke' => $ke,
                'batas' => $ke === $batasWajibCuti,
            ];
        }

        return ['grid' => $grid, 'kolom' => $kolom + 1];
    };

    /**
     * Pola contoh saat data asli belum ada — deterministik dari $benih supaya
     * tampilannya tidak berubah tiap kali halaman dimuat.
     *
     * @return array{0: string, 1: list<bool>}
     */
    $polaContoh = function (string $benih, bool $adaPelanggaran) use ($tlHari): array {
        $pola = '';
        $merah = [];

        for ($i = 0; $i < $tlHari; $i++) {
            $h = hexdec(substr(md5($benih . ':' . $i), 0, 3)) % 100;
            $pola .= match (true) {
                $h < 8 => 'c',
                $h < 22 => 'o',
                $h < 58 => 'P',
                default => 'M',
            };
            // Dikelompokkan per blok 6 hari supaya terbaca sebagai rentetan.
            $merah[] = $adaPelanggaran
                && hexdec(substr(md5($benih . ':blok:' . intdiv($i, 6)), 0, 2)) % 5 === 0;
        }

        return [$pola, $merah];
    };

    /**
     * Render satu baris sel heatmap sekaligus. Dikerjakan di PHP, bukan lewat
     * perulangan Blade, karena ribuan sel membuat indentasi template saja
     * menambah ratusan KB ke HTML hasil render.
     *
     * @param  array<int, array{kode:string,merah:bool,label:string}|null>  $baris
     */
    $selBaris = function (array $baris, int $kolom) use ($kodeLabel): string {
        $out = '';
        for ($c = 0; $c < $kolom; $c++) {
            $sel = $baris[$c] ?? null;
            if ($sel === null) {
                $out .= '<span class="ro-hm-cell is-empty"></span>';

                continue;
            }

            $ke = (int) ($sel['ke'] ?? 0);
            $judul = $sel['label'].' — '.$kodeLabel[$sel['kode']]
                .($ke > 0 ? ' · hari kerja ke-'.$ke : '')
                .(($sel['batas'] ?? false) ? ' · ⛔ seharusnya sudah cuti (periode kerja > '.($ke - 1).' hari)' : '')
                .($sel['merah'] ? ' · ⚠ pelanggaran' : '');
            $out .= '<span class="ro-hm-cell sh-'.$sel['kode'].($sel['merah'] ? ' is-flag' : '')
                .(($sel['batas'] ?? false) ? ' is-batas' : '')
                .'" title="'.e($judul).'">'
                .($ke > 0 ? '<span class="ro-hm-ke">'.$ke.'</span>' : '')
                .'</span>';
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

    // Baris contoh dinomori sendiri; data asli membawa nomornya dari pembaca
    // supaya urutannya lanjut antar halaman, bukan mulai 1 lagi tiap halaman.
    foreach ($scorecard as $i => $_) {
        $scorecard[$i]['no'] = $i + 1;
    }

    // ── Data asli menang atas baris contoh ────────────────────────────────
    if ($live) {
        $scorecard = array_map(static fn (array $r): array => [
            'no' => $r['no'],
            'sid' => $r['sid'],
            'pt' => $r['pt'],
            'nama' => $r['nama'],
            'jabatan' => $r['jabatan'],
            'site' => $r['site'],
            'roster' => $r['roster'],
            'onsite' => $r['onsite'],
            'pagi' => $r['pagi'],
            'malam' => $r['malam'],
            'off' => $r['off'],
            'cuti' => $r['cuti'],
            'shiftMaks' => $r['shiftMaks'],
            'onsiteMaks' => $r['onsiteMaks'],
            'cutiMin' => $r['cutiMin'],
            'status' => $r['status'],
            'pelanggaran' => $r['pelanggaran'],
            'pola' => $r['pola'],
            'merah' => $r['merah'],
            'riwayat' => $r['riwayat'] ?? [],
        ], $live['baris']);
    }

    // ── Paginasi ──────────────────────────────────────────────────────────
    $halKini = (int) ($live['halaman'] ?? 1);
    $halTotal = max(1, (int) ($live['total_halaman'] ?? 1));

    // Nomor halaman dibawa di query ?hal= (bukan ?page=) supaya tidak bentrok
    // dengan paginator lain bila nanti ada tabel kedua di halaman yang sama.
    // Anchor #ro-scorecard menjaga posisi baca: tanpa itu setiap klik
    // melempar pengguna kembali ke puncak halaman.
    $tautanHal = static fn (int $n): string =>
        request()->fullUrlWithQuery(['hal' => $n]).'#ro-scorecard';

    // Jendela nomor: halaman pertama, terakhir, dan dua tetangga halaman kini.
    $nomorHal = [1, $halTotal];
    for ($n = $halKini - 2; $n <= $halKini + 2; $n++) {
        if ($n >= 1 && $n <= $halTotal) {
            $nomorHal[] = $n;
        }
    }
    $nomorHal = array_values(array_unique($nomorHal));
    sort($nomorHal);

    // ── Filter tabel ──────────────────────────────────────────────────────
    // Semua lewat query string (GET) supaya hasil filter bisa dibagikan via
    // URL dan tombol back browser tetap bekerja. Keadaan & pilihan dropdown
    // datang dari pembaca (DmsRosterOverviewKaryawanReader::ambil()).
    $f = $live['filters'] ?? [];
    $opsi = $live['opsi'] ?? ['status' => [], 'kategori' => [], 'roster' => [], 'site' => [], 'pt' => []];
    $periodeTabel = $live['periode'] ?? ['jenis' => '', 'nilai' => '', 'minggu' => [], 'bulan' => [], 'dari' => '', 'sampai' => ''];
    $jenisPeriode = (string) ($periodeTabel['jenis'] ?? '');
    $nilaiPeriode = (string) ($periodeTabel['nilai'] ?? '');
    $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $urlTabel = request()->url();
    $adaFilter = collect(['q', 'status', 'note', 'roster', 'kategori', 'pt', 'site', 'periode'])
        ->contains(static fn (string $k): bool => (string) ($f[$k] ?? '') !== '');

    // Header kolom yang bisa diurutkan: klik sekali naik, klik lagi turun.
    // Kunci urut = nama kolom di DmsRosterComplianceService::urutkan().
    $urutKini = (string) ($f['sort'] ?? 'nama');
    $arahKini = (string) ($f['dir'] ?? 'asc');
    $tautanUrut = static function (string $kunci) use ($urutKini, $arahKini): string {
        $arah = $urutKini === $kunci && $arahKini === 'asc' ? 'desc' : 'asc';

        return request()->fullUrlWithQuery(['sort' => $kunci, 'dir' => $arah, 'hal' => null]).'#ro-scorecard';
    };
    $panahUrut = static fn (string $kunci): string => $urutKini === $kunci ? ($arahKini === 'asc' ? ' ▲' : ' ▼') : '';
@endphp

<div class="col-12" id="ro-scorecard">
  <div class="card ro-card">
    <div class="card-body p-24">

      <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-16">
        <div>
          <h6 class="mb-1 fw-bold text-lg ro-card__title">Scorecard per Karyawan &amp; Timeline</h6>
          <span class="ro-card__subtitle">Ringkasan roster tiap karyawan beserta pola kerja hariannya sepanjang periode</span>
        </div>
        <div class="ro-sc-legend">
          <span><i class="ro-sw ro-sw-pagi"></i>Pagi</span>
          <span><i class="ro-sw ro-sw-malam"></i>Malam</span>
          <span><i class="ro-sw ro-sw-off"></i>Off</span>
          <span><i class="ro-sw ro-sw-cuti"></i>Cuti</span>
          <span><i class="ro-sw ro-sw-flag"></i>Hari ter-flag</span>
        </div>
      </div>

      {{-- Toolbar filter (GET). Dropdown langsung menerapkan filter saat
           diganti; kolom cari diterapkan dengan Enter. Nomor halaman sengaja
           tidak ikut form, jadi setiap filter baru mulai lagi dari halaman 1. --}}
      <form method="GET" action="{{ $urlTabel }}#ro-scorecard" class="ro-sc-toolbar mb-16" id="roScFilter">
        <input type="hidden" name="sort" value="{{ $f['sort'] ?? 'nama' }}">
        <input type="hidden" name="dir" value="{{ $f['dir'] ?? 'asc' }}">
        <input type="hidden" name="periode" value="{{ $jenisPeriode }}" data-ro-periode>
        <input type="hidden" name="nilai" value="{{ $nilaiPeriode }}" data-ro-nilai>
        <input type="hidden" name="note" value="{{ $f['note'] ?? '' }}" data-ro-note>

        <div class="row g-2 align-items-center">
          <div class="col-xl-3 col-md-4 col-sm-6">
            <input type="search" name="q" value="{{ $f['q'] ?? '' }}" class="form-control form-control-sm"
                   placeholder="Cari nama / SID / jabatan… (Enter)" @disabled(! $live)>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <select name="status" class="form-select form-select-sm" data-ro-auto @disabled(! $live)>
              <option value="">Semua status</option>
              @foreach ($opsi['status'] as $s)
                <option value="{{ $s }}" @selected(($f['status'] ?? '') === $s)>{{ $s }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <select class="form-select form-select-sm" data-ro-note-select @disabled(! $live)>
              <option value="">Semua notes</option>
              <option value="pel" @selected(($f['note'] ?? '') === 'pel')>Pelanggaran regulasi (periode)</option>
              <option value="wajib" @selected(($f['note'] ?? '') === 'wajib')>Wajib cuti (segera)</option>
              <option value="map" @selected(($f['note'] ?? '') === 'map')>Tidak sesuai mapping</option>
            </select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <select name="roster" class="form-select form-select-sm" data-ro-auto @disabled(! $live)>
              <option value="">Semua roster</option>
              @foreach ($opsi['roster'] as $r)
                <option value="{{ $r }}" @selected(($f['roster'] ?? '') === (string) $r)>Roster ke-{{ $r }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xl-3 col-md-4 col-sm-6">
            <select name="kategori" class="form-select form-select-sm" data-ro-auto @disabled(! $live)>
              <option value="">Semua kategori jabatan</option>
              @foreach ($opsi['kategori'] as $k)
                <option value="{{ $k }}" @selected(($f['kategori'] ?? '') === $k)>{{ $k }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xl-3 col-md-4 col-sm-6">
            <select name="pt" class="form-select form-select-sm" data-ro-auto @disabled(! $live)>
              <option value="">Semua perusahaan</option>
              @foreach ($opsi['pt'] as $p)
                <option value="{{ $p['kode'] }}" @selected(($f['pt'] ?? '') === $p['kode'])>{{ $p['kode'] }} &mdash; {{ $p['nama'] }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <select name="site" class="form-select form-select-sm" data-ro-auto @disabled(! $live)>
              <option value="">Semua site</option>
              @foreach ($opsi['site'] as $s)
                <option value="{{ $s }}" @selected(($f['site'] ?? '') === $s)>{{ $s }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xl-1 col-md-4 col-sm-6">
            <a href="{{ $live ? route('dms.roster-compliance.scorecard', request()->except('hal')) : '#' }}"
               class="btn btn-sm btn-outline-success-600 radius-8 w-100 {{ $live ? '' : 'disabled' }}"
               title="Unduh CSV sesuai filter">
              <iconify-icon icon="solar:file-download-bold" class="align-middle"></iconify-icon>
            </a>
          </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mt-12">
          <button type="button" class="btn btn-sm btn-outline-success-600 radius-8" data-ro-detail-semua
                  aria-pressed="false" @disabled(! $live)>Detail harian</button>
          <button type="button" class="btn btn-sm radius-8 {{ ($f['note'] ?? '') === 'wajib' ? 'btn-danger' : 'btn-outline-danger' }}"
                  data-ro-wajib aria-pressed="{{ ($f['note'] ?? '') === 'wajib' ? 'true' : 'false' }}" @disabled(! $live)>Wajib cuti (segera)</button>
          <button type="button" class="btn btn-sm btn-outline-primary-600 radius-8 d-inline-flex align-items-center gap-1"
                  data-bs-toggle="modal" data-bs-target="#roModalCariManual"
                  title="Cek satu karyawan langsung dari RFID 75 hari terakhir">
            <iconify-icon icon="solar:magnifer-linear"></iconify-icon>Cari manual
          </button>

          <span class="text-secondary-light text-sm ms-8">Periode:</span>
          <select class="form-select form-select-sm w-auto" data-ro-jenis="kuartal" @disabled(! $live)>
            <option value="">Kuartal</option>
            @foreach ([1, 2, 3, 4] as $q)
              <option value="{{ $q }}" @selected($jenisPeriode === 'kuartal' && $nilaiPeriode === (string) $q)>Q{{ $q }}</option>
            @endforeach
          </select>
          <select class="form-select form-select-sm w-auto" data-ro-jenis="bulan" @disabled(! $live)>
            <option value="">Bulan</option>
            @foreach ($periodeTabel['bulan'] ?? [] as $b)
              <option value="{{ $b }}" @selected($jenisPeriode === 'bulan' && $nilaiPeriode === (string) $b)>{{ $namaBulan[$b] ?? $b }}</option>
            @endforeach
          </select>
          <select class="form-select form-select-sm w-auto" data-ro-jenis="minggu" @disabled(! $live)>
            <option value="">Minggu</option>
            @foreach ($periodeTabel['minggu'] ?? [] as $w)
              <option value="{{ $w }}" @selected($jenisPeriode === 'minggu' && $nilaiPeriode === (string) $w)>Minggu ke-{{ $w }}</option>
            @endforeach
          </select>
          <input type="date" name="dari" value="{{ $jenisPeriode === 'custom' ? ($periodeTabel['dari'] ?? '') : '' }}"
                 class="form-control form-control-sm w-auto" data-ro-tanggal title="Dari tanggal" @disabled(! $live)>
          <span class="text-secondary-light">–</span>
          <input type="date" name="sampai" value="{{ $jenisPeriode === 'custom' ? ($periodeTabel['sampai'] ?? '') : '' }}"
                 class="form-control form-control-sm w-auto" data-ro-tanggal title="Sampai tanggal" @disabled(! $live)>
          <a href="{{ $urlTabel }}#ro-scorecard" class="btn btn-sm btn-outline-primary-600 radius-8 {{ $adaFilter ? '' : 'disabled' }}">Reset</a>

          @if ($live && $jenisPeriode !== '')
            <span class="text-xs text-secondary-light">
              Periode aktif: <b>{{ $periodeTabel['label'] ?? '' }}</b> &mdash; memengaruhi status, kolom roster kini, dan notes;
              kolom "all roster" tetap YTD.
            </span>
          @endif
        </div>
      </form>

      <div class="ro-sc-scroll">
        <table class="ro-sc-table">
          <thead>
            <tr>
              <th class="num">#</th>
              @foreach ([
                ['sid', 'SID', ''], ['perusahaan', 'Perusahaan', ''], ['nama', 'Nama', ''],
                ['jabatan', 'Jabatan', ''], ['site', 'Site', ''], ['roster', 'Roster', 'num'],
                ['hadir', 'On-site', 'num'], ['pagi', 'Pagi', 'num'], ['malam', 'Malam', 'num'],
                ['off', 'Off', 'num'], ['cutiCur', 'Cuti', 'num'],
                ['onNoOff', 'Shift kerja maks (roster kini)', ''], ['onAll', 'On-site maks (all roster)', ''],
                ['cutiMin', 'Cuti min (all roster)', ''], ['status', 'Status', ''],
              ] as [$kunci, $judulKolom, $kelas])
                <th class="{{ $kelas }}">
                  @if ($live)
                    <a href="{{ $tautanUrut($kunci) }}" class="ro-sc-sort {{ $urutKini === $kunci ? 'is-aktif' : '' }}"
                       title="Urutkan menurut {{ $judulKolom }}">{{ $judulKolom }}{{ $panahUrut($kunci) }}</a>
                  @else
                    {{ $judulKolom }}
                  @endif
                </th>
              @endforeach
              <th>Notes</th>
              <th>Timeline harian</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($scorecard as $r)
              <tr>
                <td class="num ro-sc-muted">{{ $r['no'] }}</td>
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
              @php
                // Data asli sudah membawa pola & penanda merahnya sendiri;
                // baris contoh perlu dibangkitkan lebih dulu.
                [$polaStr, $merahArr] = isset($r['pola'])
                    ? [$r['pola'], $r['merah']]
                    : $polaContoh($r['sid'], $r['pelanggaran']);
                $pola = $gridDari($polaStr, $merahArr);
              @endphp
              <tr>
                <td colspan="18" style="padding:0;border-bottom:0">
                  <div class="collapse" id="tl-{{ $r['sid'] }}">
                    <div class="ro-sc-detail">
                     <div class="ro-sc-detail__panel">
                      <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-16">
                        <div>
                          <div class="ro-sc-detail__title">Pola Harian &mdash; {{ $r['nama'] }}</div>
                          <div class="ro-sc-detail__sub">{{ $r['sid'] }} &middot; {{ $r['pt'] }} &middot; {{ $r['site'] }} &middot; {{ $tlRentang }}</div>
                        </div>
                        <div class="ro-sc-legend">
                          <span><i class="ro-sw ro-sw-pagi"></i>Pagi</span>
                          <span><i class="ro-sw ro-sw-malam"></i>Malam</span>
                          <span><i class="ro-sw ro-sw-off"></i>Off</span>
                          <span><i class="ro-sw ro-sw-cuti"></i>Cuti</span>
                          <span><i class="ro-sw ro-sw-flag"></i>Hari ter-flag</span>
                          <span><i class="ro-sw ro-sw-batas"></i>Hari ke-{{ $batasWajibCuti }}: seharusnya sudah cuti</span>
                          <span class="ro-sc-muted">angka = hari kerja ke-N sejak cuti</span>
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
                            <div class="ro-hm-xlabels">@for ($c = 0; $c < $pola['kolom']; $c++)<div class="ro-hm-xlabel"><span>{{ $c % 4 === 0 ? 'W' . ($c + 1) : '' }}</span></div>@endfor</div>
                          </div>
                        </div>
                      </div>

                      {{-- Kapan saja karyawan ini melanggar — kejadian yang sama
                           dengan titik merah di kalender. --}}
                      @if (isset($r['riwayat']))
                        <div class="ro-sc-riwayat mt-16">
                          <div class="ro-sc-detail__title mb-8">
                            Riwayat pelanggaran
                            <span class="ro-sc-muted fw-normal">&middot; {{ count($r['riwayat']) }} kejadian sepanjang {{ $tlRentang }}</span>
                          </div>
                          @if ($r['riwayat'] === [])
                            <span class="text-success-600 text-sm fw-medium">Tidak ada pelanggaran regulasi.</span>
                          @else
                            <ul class="ro-sc-riwayat__list">
                              @foreach ($r['riwayat'] as $kej)
                                <li>
                                  <span class="ro-sc-riwayat__tgl">{{ $kej['mulai'] }}{{ $kej['mulai'] !== $kej['selesai'] ? ' – '.$kej['selesai'] : '' }}</span>
                                  <span class="ro-sc-note-red">⚠ {{ $kej['jenis'] }}</span>
                                  <span class="text-secondary-light">{{ $kej['teks'] }}</span>
                                </li>
                              @endforeach
                            </ul>
                          @endif
                        </div>
                      @endif
                     </div>
                    </div>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-16">
        <span class="text-secondary-light text-sm">
          @if ($live && $live['total'] === 0)
            Tidak ada karyawan yang cocok dengan filter ini.
            <a href="{{ $urlTabel }}#ro-scorecard" class="text-primary-600">Reset filter</a>
          @elseif ($live)
            Menampilkan <b>{{ number_format($live['dari'], 0, ',', '.') }}&ndash;{{ number_format($live['sampai'], 0, ',', '.') }}</b>
            dari <b>{{ number_format($live['total'], 0, ',', '.') }}</b>
            @if ($adaFilter)
              karyawan cocok filter (populasi {{ number_format($live['populasi'], 0, ',', '.') }})
            @else
              karyawan wajib dicek
            @endif
            &middot; hal {{ $halKini }}/{{ number_format($halTotal, 0, ',', '.') }}
            &middot; data s/d {{ \Carbon\Carbon::parse($live['hari_terakhir'])->translatedFormat('d M Y') }}
          @else
            Menampilkan {{ count($scorecard) }} baris contoh &middot; geser tabel ke kanan untuk melihat timeline harian
          @endif
        </span>

        @if ($live && $halTotal > 1)
          <nav aria-label="Navigasi halaman scorecard">
            <ul class="pagination pagination-sm mb-0 flex-wrap gap-1">
              <li class="page-item {{ $halKini <= 1 ? 'disabled' : '' }}">
                <a class="page-link radius-8" href="{{ $halKini <= 1 ? '#' : $tautanHal($halKini - 1) }}"
                   @if ($halKini <= 1) tabindex="-1" aria-disabled="true" @endif>&laquo; Sebelumnya</a>
              </li>

              @php $sebelum = 0; @endphp
              @foreach ($nomorHal as $n)
                @if ($sebelum > 0 && $n - $sebelum > 1)
                  <li class="page-item disabled"><span class="page-link radius-8 border-0 bg-transparent">&hellip;</span></li>
                @endif
                <li class="page-item {{ $n === $halKini ? 'active' : '' }}">
                  <a class="page-link radius-8" href="{{ $tautanHal($n) }}"
                     @if ($n === $halKini) aria-current="page" @endif>{{ number_format($n, 0, ',', '.') }}</a>
                </li>
                @php $sebelum = $n; @endphp
              @endforeach

              <li class="page-item {{ $halKini >= $halTotal ? 'disabled' : '' }}">
                <a class="page-link radius-8" href="{{ $halKini >= $halTotal ? '#' : $tautanHal($halKini + 1) }}"
                   @if ($halKini >= $halTotal) tabindex="-1" aria-disabled="true" @endif>Berikutnya &raquo;</a>
              </li>
            </ul>
          </nav>
        @elseif (! $live)
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary-600 radius-8" disabled>&laquo; Sebelumnya</button>
            <button type="button" class="btn btn-sm btn-outline-primary-600 radius-8" disabled>Berikutnya &raquo;</button>
          </div>
        @endif
      </div>

    </div>
  </div>
</div>

{{-- "Cari manual": satu karyawan, pola harian dirakit langsung dari seluruh
     tap RFID 75 hari terakhir (RosterComplianceController::cariManual).
     Tampilannya sama dengan panel "Pola Harian" di baris tabel. --}}
<div class="modal fade" id="roModalCariManual" tabindex="-1" aria-labelledby="roModalCariManualJudul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content radius-8">
      <div class="modal-header">
        <div>
          <h6 class="modal-title fw-bold" id="roModalCariManualJudul">Cari Manual Karyawan</h6>
          <span class="text-sm text-secondary-light">Satu karyawan &middot; seluruh tap RFID hari ini s/d 75 hari ke belakang</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <form class="d-flex flex-wrap gap-2 mb-16" id="roCariManualForm">
          <input type="search" class="form-control form-control-sm" style="max-width:420px" id="roCariManualInput"
                 placeholder="Nama, SID, atau NIK (min. 3 karakter)" autocomplete="off" required minlength="3">
          <button type="submit" class="btn btn-sm btn-primary-600 radius-8 d-inline-flex align-items-center gap-1">
            <iconify-icon icon="solar:magnifer-linear"></iconify-icon>Cari
          </button>
        </form>
        <div id="roCariManualHasil" class="ro-sc-detail p-0 border-0 bg-transparent">
          <span class="text-secondary-light text-sm">Masukkan nama, SID, atau NIK satu karyawan.</span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Modal "Cari manual": cari satu karyawan → bila banyak yang cocok, pilih
// satu → render grid Pola Harian (Senin–Minggu x minggu) dari RFID.
(function () {
    'use strict';

    var form = document.getElementById('roCariManualForm');
    if (!form) { return; }

    var input = document.getElementById('roCariManualInput');
    var hasil = document.getElementById('roCariManualHasil');
    var url = @json(route('dms.roster-compliance.cari-manual'));
    var namaHari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    var namaKode = { P: 'Shift Pagi', M: 'Shift Malam', o: 'Off', c: 'Cuti' };
    var minta = 0;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function pesan(teks, kelas) {
        hasil.innerHTML = '<span class="text-sm ' + (kelas || 'text-secondary-light') + '">' + esc(teks) + '</span>';
    }

    function muat(params) {
        var no = ++minta;
        pesan('Mengambil data RFID…');
        fetch(url + '?' + new URLSearchParams(params).toString(), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json().catch(function () { return { up: false, pesan: 'Respons server tidak valid (' + r.status + ').' }; }); })
            .then(function (d) {
                if (no !== minta) { return; }
                if (!d.up) { pesan(d.pesan || 'Gagal memuat data.', 'text-danger-600'); return; }
                if (d.pilihan) { pilihan(d); return; }
                render(d);
            })
            .catch(function () { if (no === minta) { pesan('Gagal terhubung ke server.', 'text-danger-600'); } });
    }

    function pilihan(d) {
        var html = '<div class="text-sm fw-semibold mb-8">Ditemukan beberapa karyawan, pilih satu:</div><div class="list-group">';
        d.pilihan.forEach(function (c) {
            html += '<button type="button" class="list-group-item list-group-item-action text-sm" data-sid="' + esc(c.sid) + '">'
                + '<b>' + esc(c.nama) + '</b> <span class="text-secondary-light">&middot; ' + esc(c.sid) + ' &middot; '
                + esc(c.pt) + ' &middot; ' + esc(c.site) + ' &middot; ' + esc(c.jabatan) + '</span></button>';
        });
        html += '</div>';
        if (d.terpotong) {
            html += '<div class="text-xs text-secondary-light mt-8">Hanya 10 hasil pertama yang ditampilkan — perjelas kata kunci.</div>';
        }
        hasil.innerHTML = html;
        hasil.querySelectorAll('[data-sid]').forEach(function (b) {
            b.addEventListener('click', function () { muat({ sid: b.dataset.sid }); });
        });
    }

    function judulSel(h, ke, batas) {
        var t = h.label + ' — ' + (h.kode === 'x' ? 'belum ada scan hari ini' : namaKode[h.kode]);
        if (ke > 0) { t += ' · hari kerja ke-' + ke; }
        if (h.masuk) { t += ' · masuk ' + h.masuk.jam + ' (' + h.masuk.gate + ')'; }
        if (h.keluar) { t += ' · keluar ' + (h.keluar.beda_hari ? h.keluar.tanggal + ' ' : '') + h.keluar.jam + ' (' + h.keluar.gate + ')'; }
        if (h.durasi) { t += ' · ' + h.durasi; }
        if (batas) { t += ' · ⛔ seharusnya sudah cuti (periode kerja > ' + (ke - 1) + ' hari)'; }
        if (h.merah) { t += ' · ⚠ ' + h.merah; }
        return t;
    }

    function render(d) {
        var batasKe = d.onsite_maks + 1;
        var baris = [[], [], [], [], [], [], []];
        var kolom = 0;
        var ke = 0;

        d.hari.forEach(function (h, i) {
            var tgl = new Date(h.iso + 'T00:00:00');
            var hariKe = (tgl.getDay() + 6) % 7;
            if (i > 0 && hariKe === 0) { kolom++; }
            ke = h.kode === 'c' ? 0 : (h.kode === 'x' ? ke : ke + 1);
            var tampilKe = h.kode === 'c' || h.kode === 'x' ? 0 : ke;
            var batas = tampilKe === batasKe;
            baris[hariKe][kolom] = '<span class="ro-hm-cell ' + (h.kode === 'x' ? 'is-empty' : 'sh-' + h.kode)
                + (h.merah ? ' is-flag' : '') + (batas ? ' is-batas' : '')
                + '" title="' + esc(judulSel(h, tampilKe, batas)) + '">'
                + (tampilKe > 0 ? '<span class="ro-hm-ke">' + tampilKe + '</span>' : '') + '</span>';
        });
        var nKolom = kolom + 1;

        var grid = '';
        namaHari.forEach(function (nama, r) {
            var sel = '';
            for (var c = 0; c < nKolom; c++) { sel += baris[r][c] || '<span class="ro-hm-cell is-empty"></span>'; }
            grid += '<div class="ro-hm-ylabel">' + nama + '</div><div class="ro-hm-row">' + sel + '</div>';
        });
        var xl = '';
        for (var c = 0; c < nKolom; c++) { xl += '<div class="ro-hm-xlabel"><span>' + (c % 2 === 0 ? 'W' + (c + 1) : '') + '</span></div>'; }
        grid += '<div class="ro-hm-corner"></div><div class="ro-hm-xlabels">' + xl + '</div>';

        var fmt = function (iso) { return new Date(iso + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }); };
        var rentang = fmt(d.awal) + ' – ' + fmt(d.akhir);
        var scan = d.hari.filter(function (h) { return h.kode === 'P' || h.kode === 'M'; }).length;

        var riwayat = '<div class="ro-sc-riwayat mt-16"><div class="ro-sc-detail__title mb-8">Riwayat pelanggaran'
            + ' <span class="ro-sc-muted fw-normal">&middot; ' + d.riwayat.length + ' kejadian sepanjang ' + esc(rentang) + '</span></div>';
        if (d.longgar) {
            riwayat += '<span class="text-secondary-light text-sm">Kategori ' + esc(d.kategori) + ' dikecualikan dari aturan roster.</span>';
        } else if (d.riwayat.length === 0) {
            riwayat += '<span class="text-success-600 text-sm fw-medium">Tidak ada pelanggaran aturan dalam 75 hari terakhir.</span>';
        } else {
            riwayat += '<ul class="ro-sc-riwayat__list">';
            d.riwayat.forEach(function (k) {
                riwayat += '<li><span class="ro-sc-riwayat__tgl">' + esc(k.mulai) + (k.mulai !== k.selesai ? ' – ' + esc(k.selesai) : '') + '</span>'
                    + '<span class="ro-sc-note-red">⚠ ' + esc(k.jenis) + '</span>'
                    + '<span class="text-secondary-light">' + esc(k.teks) + '</span></li>';
            });
            riwayat += '</ul>';
        }
        riwayat += '</div>';

        hasil.innerHTML = '<div class="ro-sc-detail__panel">'
            + '<div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-16"><div>'
            + '<div class="ro-sc-detail__title">Pola Harian &mdash; ' + esc(d.nama) + '</div>'
            + '<div class="ro-sc-detail__sub">' + esc(d.sid) + ' &middot; ' + esc(d.pt) + ' &middot; ' + esc(d.site) + ' &middot; '
            + esc(d.jabatan) + ' &middot; ' + esc(rentang) + ' &middot; ' + scan + ' hari ada check-in</div></div>'
            + '<div class="ro-sc-legend">'
            + '<span><i class="ro-sw ro-sw-pagi"></i>Pagi</span><span><i class="ro-sw ro-sw-malam"></i>Malam</span>'
            + '<span><i class="ro-sw ro-sw-off"></i>Off</span><span><i class="ro-sw ro-sw-cuti"></i>Cuti</span>'
            + '<span><i class="ro-sw ro-sw-flag"></i>Hari ter-flag</span>'
            + '<span><i class="ro-sw ro-sw-batas"></i>Hari ke-' + batasKe + ': seharusnya sudah cuti</span>'
            + '<span class="ro-sc-muted">angka = hari kerja ke-N sejak cuti</span></div></div>'
            + '<div class="ro-heatmap"><div class="ro-hm-scroll"><div class="ro-hm" style="--ro-cols: ' + nKolom + '">' + grid + '</div></div></div>'
            + '<div class="text-xs text-secondary-light mt-8">Arahkan kursor ke kotak untuk jam &amp; gate check-in/out. '
            + 'Bila cuti terakhir lebih dari 75 hari lalu, hitungan hari kerja dimulai dari awal jendela.</div>'
            + riwayat + '</div>';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var q = input.value.trim();
        if (q.length < 3) { pesan('Ketik minimal 3 karakter.', 'text-danger-600'); return; }
        muat({ q: q });
    });

    document.getElementById('roModalCariManual').addEventListener('shown.bs.modal', function () { input.focus(); });
})();
</script>

<script>
// Toolbar filter scorecard: dropdown langsung menerapkan filter; tiga pilihan
// periode (kuartal/bulan/minggu) dan rentang tanggal saling meniadakan.
(function () {
    'use strict';

    var form = document.getElementById('roScFilter');
    if (!form) { return; }

    var periode = form.querySelector('[data-ro-periode]');
    var nilai = form.querySelector('[data-ro-nilai]');
    var note = form.querySelector('[data-ro-note]');
    var jenis = form.querySelectorAll('[data-ro-jenis]');
    var tanggal = form.querySelectorAll('[data-ro-tanggal]');

    function kirim() { form.requestSubmit ? form.requestSubmit() : form.submit(); }

    form.querySelectorAll('[data-ro-auto]').forEach(function (el) {
        el.addEventListener('change', kirim);
    });

    form.querySelector('[data-ro-note-select]').addEventListener('change', function (e) {
        note.value = e.target.value;
        kirim();
    });

    form.querySelector('[data-ro-wajib]').addEventListener('click', function () {
        note.value = note.value === 'wajib' ? '' : 'wajib';
        kirim();
    });

    jenis.forEach(function (sel) {
        sel.addEventListener('change', function () {
            jenis.forEach(function (lain) { if (lain !== sel) { lain.value = ''; } });
            tanggal.forEach(function (t) { t.value = ''; });
            periode.value = sel.value === '' ? '' : sel.dataset.roJenis;
            nilai.value = sel.value;
            kirim();
        });
    });

    tanggal.forEach(function (t) {
        t.addEventListener('change', function () {
            jenis.forEach(function (sel) { sel.value = ''; });
            var adaTanggal = Array.prototype.some.call(tanggal, function (x) { return x.value !== ''; });
            periode.value = adaTanggal ? 'custom' : '';
            nilai.value = '';
            kirim();
        });
    });

    // "Detail harian": buka/tutup semua panel pola harian di halaman ini.
    var tombolDetail = form.querySelector('[data-ro-detail-semua]');
    tombolDetail.addEventListener('click', function () {
        var buka = tombolDetail.getAttribute('aria-pressed') !== 'true';
        tombolDetail.setAttribute('aria-pressed', buka ? 'true' : 'false');
        tombolDetail.classList.toggle('btn-success-600', buka);
        tombolDetail.classList.toggle('btn-outline-success-600', !buka);
        document.querySelectorAll('#ro-scorecard .collapse[id^="tl-"]').forEach(function (el) {
            if (window.bootstrap && window.bootstrap.Collapse) {
                window.bootstrap.Collapse.getOrCreateInstance(el, { toggle: false })[buka ? 'show' : 'hide']();
            } else {
                el.classList.toggle('show', buka);
            }
        });
    });
})();
</script>
