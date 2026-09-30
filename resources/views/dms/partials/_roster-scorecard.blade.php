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

    // Penanda awal bulan untuk penggaris timeline.
    $tlBulan = [];
    for ($m = 1; $m <= 9; $m++) {
        $t = \Carbon\Carbon::create(2026, $m, 1);
        $tlBulan[] = [
            'idx' => $tlAwal->diffInDays($t),
            'label' => $t->translatedFormat('M') . ($m % 3 === 1 && $m > 1 ? ' · Q' . (intdiv($m - 1, 3) + 1) : ''),
        ];
    }

    // Penanda minggu (tiap Senin).
    $tlMinggu = [];
    $w = 0;
    for ($i = 0; $i < $tlHari; $i++) {
        $t = $tlAwal->copy()->addDays($i);
        if ($i === 0 || $t->dayOfWeek === \Carbon\Carbon::MONDAY) {
            $w++;
            $tlMinggu[] = ['idx' => $i, 'label' => 'W' . $w];
        }
    }

    /**
     * Bentuk batang timeline satu karyawan — deterministik dari $benih supaya
     * tampilannya tidak berubah tiap kali halaman dimuat.
     */
    $tlStrip = function (string $benih, bool $adaPelanggaran) use ($tlHari): string {
        $out = '';
        for ($i = 0; $i < $tlHari; $i++) {
            $h = hexdec(substr(md5($benih . ':' . $i), 0, 3));
            $kode = match (true) {
                $h % 100 < 8 => 'c',
                $h % 100 < 22 => 'o',
                $h % 100 < 58 => 'P',
                default => 'M',
            };
            // Garis merah dikelompokkan supaya terlihat seperti rentetan hari.
            $merah = $adaPelanggaran && (hexdec(substr(md5($benih . ':blok:' . intdiv($i, 6)), 0, 2)) % 5 === 0);
            $out .= '<i class="d-' . $kode . ($merah ? ' is-red' : '') . '"></i>';
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
          <span><i style="background:#60A5FA"></i>Pagi</span>
          <span><i style="background:#1E3A8A"></i>Malam</span>
          <span><i style="background:#CBD5E1"></i>Off</span>
          <span><i style="background:#16A34A"></i>Cuti</span>
          <span><i style="background:#EF4444"></i>Merah = pelanggaran</span>
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
              <th>
                <div class="ro-tl ro-tl-head">
                  <div class="mb-2" style="font-weight:600;color:#475569">Timeline harian</div>
                  <div class="ro-tl-months">
                    @foreach ($tlBulan as $b)
                      <b style="left: calc({{ $b['idx'] }} * var(--ro-tl-day))">{{ $b['label'] }}</b>
                    @endforeach
                  </div>
                  <div class="ro-tl-weeks">
                    @foreach ($tlMinggu as $i => $mg)
                      @if ($i % 2 === 0)
                        <span style="left: calc({{ $mg['idx'] }} * var(--ro-tl-day))">{{ $mg['label'] }}</span>
                      @endif
                    @endforeach
                  </div>
                </div>
              </th>
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
                  <div class="ro-tl">
                    <div class="ro-tl-strip">{!! $tlStrip($r['sid'], $r['pelanggaran']) !!}</div>
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
