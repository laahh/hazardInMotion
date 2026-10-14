{{--
  Empat kartu "Penegakan Aturan & Alur Banned/Unban" PER HARI INI, dihitung
  mundur langsung dari scan RFID (bcsid.mv_checkinout_rfid), tidak lewat
  dms_roster_pola:

    1. Pelanggaran periode kerja > 70 hari         → Banned 14 hari
    2. Pelanggaran bekerja > 13 hari berturut-turut → Banned 1 hari off
    3. Waktu kerja maksimal 12 jam                  → Highlight (peringatan)
    4. Tidak sesuai mapping roster perusahaan       → Highlight (peringatan)

  Angka: $regTerkiniLive dari DmsRosterRegLiveService::ambil(); ambang dari
  config dms_roster.reg_live.aturan. Kartu hanya menampilkan jumlah; daftar
  nama dimuat modal lewat JSON saat dibuka, dan baris daftar membuka timeline
  tap RFID karyawan itu (public/dms-assets/roster-compliance/roster-temuan.js).
--}}
@php
    $temuanKini = $regTerkiniLive ?? null;
    $aturan = config('dms_roster.reg_live.aturan', []);
    $onsiteMaks = $aturan['onsite_maks'] ?? 70;
    $beruntunMaks = $aturan['beruntun_maks'] ?? 13;
    $jamMaks = $aturan['jam_kerja_maks'] ?? 12;

    // Item per kartu: [label tab, definisi, kolom nilai, kolom mulai, kolom selesai].
    $kartuAturan = [
        [
            'id' => 'onsite',
            'ikon' => 'solar:calendar-date-bold',
            'warna' => 'danger',
            'judul' => 'Pelanggaran periode kerja',
            'ambang' => '> '.$onsiteMaks.' hari',
            'perlakuan' => 'Banned '.($aturan['onsite_banned_lama'] ?? 14).' hari',
            'perlakuanSub' => '(cuti '.($aturan['onsite_banned_lama'] ?? 14).' hari)',
            'catatan' => 'Banned akan hari ke-'.($aturan['onsite_banned_hari_ke'] ?? 74).', dengan asumsi 1 hari perjalanan keluar mess & 1 hari induksi awal.',
            'item' => [
                'onsite' => ['Periode kerja > '.$onsiteMaks.' hari', 'On-site (kerja + off pendek) lebih dari '.$onsiteMaks.' hari sejak cuti terakhir, sampai hari ini.', 'Periode kerja', 'Mulai on-site', 'Hari terakhir'],
            ],
        ],
        [
            'id' => 'beruntun',
            'ikon' => 'solar:users-group-rounded-bold',
            'warna' => 'danger',
            'judul' => 'Pelanggaran bekerja',
            'ambang' => '> '.$beruntunMaks.' hari berturut-turut',
            'perlakuan' => 'Banned 1 hari off',
            'perlakuanSub' => '',
            'catatan' => 'Banned hari ke-'.($aturan['beruntun_banned_hari_ke'] ?? 14).'.',
            'item' => [
                'beruntun' => ['Bekerja > '.$beruntunMaks.' hari berturut-turut', 'Kerja setiap hari tanpa satu pun hari off, lebih dari '.$beruntunMaks.' hari sampai hari ini.', 'Berturut-turut', 'Mulai', 'Hari terakhir'],
            ],
        ],
        [
            'id' => 'jam12',
            'ikon' => 'solar:clock-circle-bold',
            'warna' => 'warning',
            'judul' => 'Waktu kerja',
            'ambang' => 'maksimal '.$jamMaks.' jam',
            'perlakuan' => 'Highlight warna',
            'perlakuanSub' => '(peringatan)',
            'catatan' => 'Akan mempengaruhi waktu istirahat hari berikutnya.',
            'item' => [
                'jam12' => ['Waktu kerja > '.$jamMaks.' jam', 'Shift terakhir ('.($aturan['jam_kerja_hari_terkini'] ?? 3).' hari terakhir) lebih dari '.$jamMaks.' jam dari tap check-in sampai check-out gate.', 'Durasi', 'Check-in', 'Check-out'],
            ],
        ],
        [
            'id' => 'mapping',
            'ikon' => 'solar:document-text-bold',
            'warna' => 'warning',
            'judul' => 'Tidak sesuai mapping',
            'ambang' => 'roster perusahaan',
            'perlakuan' => 'Highlight warna',
            'perlakuanSub' => '(peringatan)',
            'catatan' => 'Perusahaan tidak sesuai komitmen plan design roster, waktu kerja & istirahat yang diajukan.',
            'item' => [
                'map1' => ['MAP-1 · Melebihi blok roster', 'Blok kerja terakhir lebih panjang dari pola roster PT-nya, tapi belum '.($beruntunMaks + 1).' hari.', 'Blok kerja', 'Mulai blok', 'Akhir blok'],
                'map2' => ['MAP-2 · Wajib OFF saat ganti shift', 'Khusus PAMA: pindah Pagi↔Malam tanpa diselingi off.', 'Blok kerja', 'Mulai blok', 'Akhir blok'],
                'map3' => ['MAP-3 · Urutan shift terbalik', 'PT selain PAMA: shift Pagi muncul setelah Malam dalam satu blok.', 'Blok kerja', 'Mulai blok', 'Akhir blok'],
            ],
        ],
    ];

    // Satu orang bisa kena lebih dari satu MAP; angka kartu = orang unik.
    $jumlahOrang = static function (array $kode) use ($temuanKini): ?int {
        if (! $temuanKini) {
            return null;
        }
        $sid = [];
        foreach ($kode as $k) {
            foreach ($temuanKini['daftar'][$k] ?? [] as $r) {
                $sid[$r['sid']] = true;
            }
        }

        return count($sid);
    };
@endphp

@foreach ($kartuAturan as $kartu)
  @php $orang = $jumlahOrang(array_keys($kartu['item'])); @endphp
  <div class="col-xxl-3 col-md-6">
    <button type="button"
            class="ro-aturan card h-100 radius-8 border-0 w-100 text-start p-0 ro-aturan--{{ $kartu['warna'] }}"
            data-bs-toggle="modal" data-bs-target="#roModalTemuan-{{ $kartu['id'] }}"
            data-ro-tab="{{ array_key_first($kartu['item']) }}" @disabled(! $temuanKini)>
      <span class="card-body p-20 d-flex flex-column w-100">
        <span class="d-flex align-items-start gap-12">
          <span class="w-44-px h-44-px bg-{{ $kartu['warna'] }}-focus text-{{ $kartu['warna'] }}-main flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-0">
            <iconify-icon icon="{{ $kartu['ikon'] }}" class="icon"></iconify-icon>
          </span>
          <span style="min-width:0">
            <span class="d-block text-sm text-secondary-light fw-medium">{{ $kartu['judul'] }}</span>
            <span class="d-block fw-bold text-{{ $kartu['warna'] }}-main">{{ $kartu['ambang'] }}</span>
          </span>
        </span>

        <span class="d-flex align-items-end gap-2 mt-16">
          <span class="h4 fw-bold mb-0 text-primary-light">{{ $orang === null ? '—' : number_format($orang, 0, ',', '.') }}</span>
          <span class="text-secondary-light text-sm mb-1">orang</span>
        </span>

        <span class="d-block mt-12">
          <span class="badge bg-{{ $kartu['warna'] }}-focus text-{{ $kartu['warna'] }}-main fw-semibold">{{ $kartu['perlakuan'] }}</span>
          @if ($kartu['perlakuanSub'])
            <span class="text-xs text-secondary-light">{{ $kartu['perlakuanSub'] }}</span>
          @endif
        </span>
        <span class="d-block text-xs text-secondary-light mt-8 flex-grow-1">{{ $kartu['catatan'] }}</span>

        <span class="d-flex align-items-center justify-content-between mt-12 text-xs">
          @if ($temuanKini)
            <span class="text-secondary-light">per {{ $temuanKini['per'] }} <span class="text-success-600 fw-semibold">&middot; RFID</span></span>
            <span class="fw-semibold text-primary-600">Lihat detail &rarr;</span>
          @else
            <span class="text-secondary-light">RFID tidak terjangkau saat ini</span>
          @endif
        </span>
      </span>
    </button>
  </div>

  @if ($temuanKini)
    <div class="modal fade" id="roModalTemuan-{{ $kartu['id'] }}" tabindex="-1" aria-labelledby="roModalTemuanJudul-{{ $kartu['id'] }}" aria-hidden="true"
         data-ro-timeline-url="{{ route('dms.roster-compliance.terkini.timeline', ['sid' => '__SID__']) }}">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content radius-8">
          <div class="modal-header">
            <div>
              <h6 class="modal-title fw-bold" id="roModalTemuanJudul-{{ $kartu['id'] }}">
                {{ $kartu['judul'] }} {{ $kartu['ambang'] }}
                <span class="badge bg-{{ $kartu['warna'] }}-focus text-{{ $kartu['warna'] }}-main ms-1">{{ $kartu['perlakuan'] }}</span>
              </h6>
              <span class="text-secondary-light text-sm">
                Per {{ $temuanKini['per'] }} &middot; RFID diambil {{ $temuanKini['diambil'] }} &middot; {{ $kartu['catatan'] }}
              </span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            {{-- Tampilan detail satu karyawan (timeline RFID), diisi JS. --}}
            <div class="d-none" data-ro-view-detail></div>

            <div data-ro-view-daftar>
              <ul class="nav nav-pills gap-2 mb-16 {{ count($kartu['item']) === 1 ? 'd-none' : '' }}" role="tablist">
                @foreach ($kartu['item'] as $kode => [$label, $definisi, $kolomNilai, $kolomMulai, $kolomSelesai])
                  <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link radius-8 px-16 py-8 text-sm" role="tab"
                            data-ro-tab-btn="{{ $kode }}"
                            data-ro-definisi="{{ $definisi }}"
                            data-ro-kolom="{{ json_encode([$kolomNilai, $kolomMulai, $kolomSelesai]) }}"
                            data-ro-url="{{ route('dms.roster-compliance.terkini.detail', ['kode' => $kode]) }}"
                            data-ro-unduh="{{ route('dms.roster-compliance.terkini.unduh', ['kode' => $kode]) }}">
                      {{ $label }}
                      <span class="badge bg-neutral-200 text-secondary-light ms-1">{{ number_format(count($temuanKini['daftar'][$kode]), 0, ',', '.') }}</span>
                    </button>
                  </li>
                @endforeach
              </ul>

              <p class="text-secondary-light text-sm mb-12" data-ro-definisi-teks></p>

              <div class="d-flex flex-wrap gap-2 justify-content-between mb-16">
                <input type="search" class="form-control form-control-sm w-auto" style="min-width:260px"
                       placeholder="Cari nama, SID, site, PT…" data-ro-cari>
                <a href="#" class="btn btn-sm btn-outline-danger radius-8 d-inline-flex align-items-center gap-1" data-ro-unduh-btn>
                  <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
                  Unduh CSV
                </a>
              </div>

              <div class="table-responsive">
                <table class="table bordered-table sm-table mb-0">
                  <thead>
                    <tr>
                      <th>No</th>
                      <th>Nama</th>
                      <th>PT</th>
                      <th>Site</th>
                      <th class="text-end" data-ro-th="0">Nilai</th>
                      <th data-ro-th="1">Mulai</th>
                      <th data-ro-th="2">Selesai</th>
                      <th>Keterangan</th>
                      <th>Scan terakhir</th>
                    </tr>
                  </thead>
                  <tbody data-ro-tbody></tbody>
                </table>
                <p class="text-secondary-light text-sm text-center my-16 d-none" data-ro-pesan></p>
              </div>
              <p class="text-secondary-light text-xs mt-8 mb-0">Klik baris untuk melihat timeline tap RFID karyawan tersebut (gate, jam check-in/out, durasi per hari).</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
@endforeach

{{-- Modal: daftar per aturan (JSON) dan timeline tap RFID per karyawan. --}}
<script src="{{ asset('dms-assets/roster-compliance/roster-temuan.js') }}?v=4"></script>
