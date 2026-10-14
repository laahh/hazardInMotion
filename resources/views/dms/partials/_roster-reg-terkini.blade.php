{{--
  Tiga kartu REG-1/2/3 — pelanggaran regulasi fatigue PER HARI INI, dihitung
  mundur langsung dari scan RFID (bcsid.mv_checkinout_rfid), tidak lewat
  dms_roster_pola. Berbeda dari tile "Pelanggaran" di atasnya yang menghitung
  siapa pun yang PERNAH melanggar sejak 1 Januari.

  Sumber: $regTerkiniLive dari DmsRosterRegLiveService::ambil().
  Kartu menampilkan beberapa nama teratas; modal memuat seluruh baris dan
  tombol unduh mengambil CSV lengkap dari server. Bila data belum tersedia,
  kartu menampilkan keadaan kosong — sengaja TANPA nama contoh, karena daftar
  nama palsu mudah disangka data asli.
--}}
@php
    $regKini = $regTerkiniLive ?? null;
    $ambangRoster = config('dms_roster.ambang', []);
    $tampilDiKartu = 6;

    $kartuReg = [
        'reg1' => [
            'judul' => 'REG-1 · Kerja Beruntun',
            'definisi' => 'Sedang kerja '.(($ambangRoster['kerja_beruntun'] ?? 13) + 1).' hari atau lebih tanpa off sampai hari ini.',
            'ikon' => 'solar:alarm-bold',
            'warna' => 'bg-warning-focus text-warning-main',
            'satuan' => 'hari beruntun',
            'kolomHari' => 'Kerja beruntun',
            'keterangan' => static fn (array $r): string => 'sejak '.$r['mulai'],
        ],
        'reg2' => [
            'judul' => 'REG-2 · On-site Belum Cuti',
            'definisi' => 'On-site lebih dari '.($ambangRoster['onsite'] ?? 71).' hari sejak cuti terakhir sampai hari ini.',
            'ikon' => 'solar:map-point-wave-bold',
            'warna' => 'bg-danger-focus text-danger-main',
            'satuan' => 'hari on-site',
            'kolomHari' => 'On-site tanpa cuti',
            'keterangan' => static fn (array $r): string => 'sejak '.$r['mulai'],
        ],
        'reg3' => [
            'judul' => 'REG-3 · Cuti Terlalu Pendek',
            'definisi' => 'Cuti terakhir yang sudah selesai kurang dari '.($ambangRoster['cuti_min'] ?? 12).' hari.',
            'ikon' => 'solar:calendar-minimalistic-bold',
            'warna' => 'bg-lilac-100 text-lilac-600',
            'satuan' => 'hari cuti',
            'kolomHari' => 'Panjang cuti',
            'keterangan' => static fn (array $r): string => $r['mulai'].' – '.$r['selesai'],
        ],
    ];
@endphp

@foreach ($kartuReg as $kode => $k)
  @php $daftarReg = $regKini['reg'][$kode] ?? []; @endphp
  <div class="col-xxl-4 col-lg-4 col-md-6">
    <div class="card h-100 radius-8 border-0">
      <div class="card-body p-24 d-flex flex-column">
        <div class="d-flex align-items-start gap-12">
          <span class="w-44-px h-44-px {{ $k['warna'] }} flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-0">
            <iconify-icon icon="{{ $k['ikon'] }}" class="icon"></iconify-icon>
          </span>
          <div style="min-width:0">
            <h6 class="mb-1 fw-bold text-lg">{{ $k['judul'] }}</h6>
            <span class="ro-card__subtitle">{{ $k['definisi'] }}</span>
          </div>
        </div>

        <div class="d-flex align-items-end gap-2 mt-16">
          <h4 class="fw-bold mb-0 text-primary-light">{{ $regKini ? number_format(count($daftarReg), 0, ',', '.') : '—' }}</h4>
          <span class="text-secondary-light text-sm mb-1">
            orang
            @if ($regKini)
              &middot; per {{ $regKini['per'] }}
              <span class="text-success-600 fw-semibold" title="Diambil langsung dari RFID {{ $regKini['diambil'] }}">&middot; RFID</span>
            @endif
          </span>
        </div>

        <div class="mt-16 flex-grow-1">
          @if (! $regKini)
            <p class="text-secondary-light text-sm mb-0">RFID tidak terjangkau saat ini; muat ulang beberapa saat lagi.</p>
          @elseif ($daftarReg === [])
            <p class="text-success-600 text-sm fw-medium mb-0">
              <iconify-icon icon="solar:check-circle-bold" class="align-middle"></iconify-icon>
              Tidak ada karyawan yang melanggar per hari ini.
            </p>
          @else
            @foreach (array_slice($daftarReg, 0, $tampilDiKartu) as $i => $r)
              <div class="d-flex align-items-center justify-content-between gap-3 {{ $loop->last ? '' : 'mb-16' }}">
                <div style="min-width:0">
                  <h6 class="text-md mb-0 fw-normal text-truncate" title="{{ $r['nama'] }}">{{ $r['nama'] }}</h6>
                  <span class="text-sm text-secondary-light fw-normal">
                    {{ $r['sid'] }} &middot; {{ $r['site'] }} &middot; {{ $r['pt'] }}
                  </span>
                </div>
                <span class="text-end flex-shrink-0">
                  <span class="text-danger-main text-sm fw-bold d-block">{{ $r['terpotong'] ? '≥' : '' }}{{ number_format($r['hari'], 0, ',', '.') }} hari</span>
                  <span class="text-secondary-light text-xs">{{ ($k['keterangan'])($r) }}</span>
                </span>
              </div>
            @endforeach
          @endif
        </div>

        @if ($regKini && $daftarReg !== [])
          <div class="d-flex gap-2 mt-20">
            <button type="button" class="btn btn-sm btn-outline-primary radius-8 flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1"
                    data-bs-toggle="modal" data-bs-target="#roModalReg-{{ $kode }}">
              <iconify-icon icon="solar:list-bold" class="icon"></iconify-icon>
              Lihat semua ({{ number_format(count($daftarReg), 0, ',', '.') }})
            </button>
            <a href="{{ route('dms.roster-compliance.reg-terkini', ['kode' => $kode]) }}"
               class="btn btn-sm btn-outline-danger radius-8 d-inline-flex align-items-center justify-content-center gap-1"
               title="Unduh daftar lengkap (CSV)">
              <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
              Unduh
            </a>
          </div>
        @endif
      </div>
    </div>
  </div>

  @if ($regKini && $daftarReg !== [])
    <div class="modal fade" id="roModalReg-{{ $kode }}" tabindex="-1" aria-labelledby="roModalRegJudul-{{ $kode }}" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content radius-8">
          <div class="modal-header">
            <div>
              <h6 class="modal-title fw-bold" id="roModalRegJudul-{{ $kode }}">{{ $k['judul'] }}</h6>
              <span class="text-secondary-light text-sm">
                {{ number_format(count($daftarReg), 0, ',', '.') }} orang &middot; per {{ $regKini['per'] }}
                &middot; RFID diambil {{ $regKini['diambil'] }} &middot; {{ $k['definisi'] }}
              </span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between mb-16">
              <input type="search" class="form-control form-control-sm w-auto" style="min-width:260px"
                     placeholder="Cari nama, SID, site, PT…" data-ro-reg-cari="{{ $kode }}">
              <a href="{{ route('dms.roster-compliance.reg-terkini', ['kode' => $kode]) }}"
                 class="btn btn-sm btn-outline-danger radius-8 d-inline-flex align-items-center gap-1">
                <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
                Unduh CSV
              </a>
            </div>
            <div class="table-responsive">
              <table class="table bordered-table sm-table mb-0" data-ro-reg-tabel="{{ $kode }}">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>SID</th>
                    <th>Nama</th>
                    <th>Jabatan</th>
                    <th>PT</th>
                    <th>Site</th>
                    <th class="text-end">{{ $k['kolomHari'] }}</th>
                    <th>Mulai</th>
                    <th>Selesai</th>
                    <th>Scan terakhir</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($daftarReg as $i => $r)
                    <tr>
                      <td>{{ $i + 1 }}</td>
                      <td>{{ $r['sid'] }}</td>
                      <td>{{ $r['nama'] }}</td>
                      <td>{{ $r['jabatan'] }}</td>
                      <td>{{ $r['pt'] }}</td>
                      <td>{{ $r['site'] }}</td>
                      <td class="text-end fw-semibold text-danger-main">{{ $r['terpotong'] ? '≥' : '' }}{{ number_format($r['hari'], 0, ',', '.') }}</td>
                      <td>{{ $r['mulai'] }}</td>
                      <td>{{ $r['selesai'] }}</td>
                      <td>{{ $r['scan_terakhir'] }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
              <p class="text-secondary-light text-sm text-center my-16 d-none" data-ro-reg-kosong="{{ $kode }}">Tidak ada yang cocok.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
@endforeach

@if ($regKini)
  <div class="col-12 mt-0">
    <span class="text-secondary-light text-xs">
      Dihitung langsung dari scan RFID {{ $regKini['jendela'] }} hari terakhir (diambil {{ $regKini['diambil'] }}).
      Karyawan yang belum scan hari ini dinilai sampai kemarin. Tanda ≥ = run lebih panjang dari jendela.
      Satu karyawan bisa masuk lebih dari satu REG; kategori longgar (Transportasi Massal, Mekanik) dikecualikan.
    </span>
  </div>
@endif

<script>
// Pencarian di tabel modal REG — saring baris di klien, data sudah lengkap di halaman.
(function () {
    'use strict';

    document.querySelectorAll('[data-ro-reg-cari]').forEach(function (input) {
        var kode = input.dataset.roRegCari;
        var tabel = document.querySelector('[data-ro-reg-tabel="' + kode + '"]');
        var kosong = document.querySelector('[data-ro-reg-kosong="' + kode + '"]');
        if (!tabel) { return; }

        var baris = Array.prototype.slice.call(tabel.tBodies[0].rows);

        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            var tampil = 0;

            baris.forEach(function (tr) {
                var cocok = q === '' || tr.textContent.toLowerCase().indexOf(q) !== -1;
                tr.classList.toggle('d-none', !cocok);
                if (cocok) { tampil++; }
            });

            if (kosong) { kosong.classList.toggle('d-none', tampil > 0); }
        });
    });
})();
</script>
