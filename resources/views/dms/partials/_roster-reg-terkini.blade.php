{{--
  Dua kartu temuan roster PER HARI INI, dihitung mundur langsung dari scan
  RFID (bcsid.mv_checkinout_rfid), tidak lewat dms_roster_pola:

    • Pelanggaran Regulasi (merah)      REG-1 / REG-2 / REG-3
    • Tidak Sesuai Mapping Roster (kuning)  MAP-1 / MAP-2 / MAP-3

  Berbeda dari tile "Pelanggaran" di atasnya yang menghitung siapa pun yang
  PERNAH melanggar sejak 1 Januari.

  Sumber angka: $regTerkiniLive dari DmsRosterRegLiveService::ambil(). Kartu
  hanya menampilkan jumlah; daftar nama dimuat modal lewat JSON saat tab dibuka
  (route dms.roster-compliance.terkini.detail), karena REG-3/MAP bisa ribuan
  baris dan tidak pantas ikut di-render bersama halaman.
--}}
@php
    $temuanKini = $regTerkiniLive ?? null;
    $ambangRoster = config('dms_roster.ambang', []);

    $kartuTemuan = [
        [
            'id' => 'regulasi',
            'judul' => 'Pelanggaran Regulasi',
            'sub' => 'Regulasi fatigue yang sedang dilanggar per hari ini.',
            'ikon' => 'solar:danger-triangle-bold',
            'warnaIkon' => 'bg-danger-focus text-danger-main',
            'warnaAngka' => 'text-danger-main',
            'tombol' => 'btn-outline-danger',
            'keterangan' => false,
            'item' => [
                'reg1' => ['REG-1', 'Kerja beruntun', 'Kerja '.(($ambangRoster['kerja_beruntun'] ?? 13) + 1).' hari atau lebih tanpa off sampai hari ini.', 'Kerja beruntun (hari)'],
                'reg2' => ['REG-2', 'On-site belum cuti', 'On-site lebih dari '.($ambangRoster['onsite'] ?? 71).' hari sejak cuti terakhir.', 'On-site (hari)'],
                'reg3' => ['REG-3', 'Cuti terlalu pendek', 'Cuti terakhir yang sudah selesai kurang dari '.($ambangRoster['cuti_min'] ?? 12).' hari.', 'Panjang cuti (hari)'],
            ],
        ],
        [
            'id' => 'mapping',
            'judul' => 'Tidak Sesuai Mapping Roster',
            'sub' => 'Peringatan desain roster pada blok kerja terakhir.',
            'ikon' => 'solar:map-arrow-square-bold',
            'warnaIkon' => 'bg-warning-focus text-warning-main',
            'warnaAngka' => 'text-warning-main',
            'tombol' => 'btn-outline-warning',
            'keterangan' => true,
            'item' => [
                'map1' => ['MAP-1', 'Melebihi blok roster', 'Blok kerja lebih panjang dari pola roster PT-nya, tapi belum '.(($ambangRoster['kerja_beruntun'] ?? 13) + 1).' hari.', 'Blok kerja (hari)'],
                'map2' => ['MAP-2', 'Wajib OFF saat ganti shift', 'Khusus PAMA: pindah Pagi↔Malam tanpa diselingi off.', 'Blok kerja (hari)'],
                'map3' => ['MAP-3', 'Urutan shift terbalik', 'PT selain PAMA: shift Pagi muncul setelah Malam dalam satu blok.', 'Blok kerja (hari)'],
            ],
        ],
    ];
@endphp

@foreach ($kartuTemuan as $kartu)
  <div class="col-xxl-6 col-lg-6">
    <div class="card h-100 radius-8 border-0">
      <div class="card-body p-24 d-flex flex-column">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
          <div class="d-flex align-items-start gap-12" style="min-width:0">
            <span class="w-44-px h-44-px {{ $kartu['warnaIkon'] }} flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-0">
              <iconify-icon icon="{{ $kartu['ikon'] }}" class="icon"></iconify-icon>
            </span>
            <div style="min-width:0">
              <h6 class="mb-1 fw-bold text-lg">{{ $kartu['judul'] }}</h6>
              <span class="ro-card__subtitle">{{ $kartu['sub'] }}</span>
            </div>
          </div>
          @if ($temuanKini)
            <span class="text-secondary-light text-xs" title="Diambil langsung dari RFID {{ $temuanKini['diambil'] }}">
              per {{ $temuanKini['per'] }} <span class="text-success-600 fw-semibold">&middot; RFID</span>
            </span>
          @endif
        </div>

        <div class="row g-3 mt-8 flex-grow-1">
          @foreach ($kartu['item'] as $kode => [$singkat, $label, $definisi])
            <div class="col-sm-4">
              <button type="button"
                      class="ro-temuan-tile w-100 h-100 text-start bg-neutral-50 border radius-8 p-16 d-flex flex-column"
                      data-bs-toggle="modal" data-bs-target="#roModalTemuan-{{ $kartu['id'] }}"
                      data-ro-tab="{{ $kode }}" @disabled(! $temuanKini) title="{{ $definisi }}">
                <span class="text-xs fw-bold text-secondary-light">{{ $singkat }}</span>
                <span class="text-sm fw-semibold text-primary-light">{{ $label }}</span>
                <span class="h5 fw-bold mb-0 mt-auto pt-8 {{ $kartu['warnaAngka'] }}">
                  {{ $temuanKini ? number_format(count($temuanKini['daftar'][$kode]), 0, ',', '.') : '—' }}
                </span>
                <span class="text-xs text-secondary-light">orang</span>
              </button>
            </div>
          @endforeach
        </div>

        @if ($temuanKini)
          <button type="button" class="btn btn-sm {{ $kartu['tombol'] }} radius-8 w-100 mt-16 d-inline-flex align-items-center justify-content-center gap-1"
                  data-bs-toggle="modal" data-bs-target="#roModalTemuan-{{ $kartu['id'] }}" data-ro-tab="{{ array_key_first($kartu['item']) }}">
            <iconify-icon icon="solar:list-bold" class="icon"></iconify-icon>
            Lihat detail {{ implode(' · ', array_column($kartu['item'], 0)) }}
          </button>
        @else
          <p class="text-secondary-light text-sm mb-0 mt-16">RFID tidak terjangkau saat ini; muat ulang beberapa saat lagi.</p>
        @endif
      </div>
    </div>
  </div>

  @if ($temuanKini)
    <div class="modal fade" id="roModalTemuan-{{ $kartu['id'] }}" tabindex="-1" aria-labelledby="roModalTemuanJudul-{{ $kartu['id'] }}" aria-hidden="true"
         data-ro-keterangan="{{ $kartu['keterangan'] ? '1' : '0' }}">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content radius-8">
          <div class="modal-header">
            <div>
              <h6 class="modal-title fw-bold" id="roModalTemuanJudul-{{ $kartu['id'] }}">{{ $kartu['judul'] }}</h6>
              <span class="text-secondary-light text-sm">
                Per {{ $temuanKini['per'] }} &middot; RFID diambil {{ $temuanKini['diambil'] }}
              </span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <ul class="nav nav-pills gap-2 mb-16" role="tablist">
              @foreach ($kartu['item'] as $kode => [$singkat, $label, $definisi, $kolomHari])
                <li class="nav-item" role="presentation">
                  <button type="button" class="nav-link radius-8 px-16 py-8 text-sm" role="tab"
                          data-ro-tab-btn="{{ $kode }}"
                          data-ro-definisi="{{ $definisi }}"
                          data-ro-kolom-hari="{{ $kolomHari }}"
                          data-ro-url="{{ route('dms.roster-compliance.terkini.detail', ['kode' => $kode]) }}"
                          data-ro-unduh="{{ route('dms.roster-compliance.terkini.unduh', ['kode' => $kode]) }}">
                    <b>{{ $singkat }}</b> &middot; {{ $label }}
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
                    <th>SID</th>
                    <th>Nama</th>
                    <th>Jabatan</th>
                    <th>PT</th>
                    <th>Site</th>
                    <th class="text-end" data-ro-th-hari>Hari</th>
                    <th>Mulai</th>
                    <th>Selesai</th>
                    @if ($kartu['keterangan'])
                      <th>Keterangan</th>
                    @endif
                    <th>Scan terakhir</th>
                  </tr>
                </thead>
                <tbody data-ro-tbody></tbody>
              </table>
              <p class="text-secondary-light text-sm text-center my-16 d-none" data-ro-pesan></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
@endforeach

@if ($temuanKini)
  <div class="col-12 mt-0">
    <span class="text-secondary-light text-xs">
      Dihitung langsung dari scan RFID {{ $temuanKini['jendela'] }} hari terakhir (diambil {{ $temuanKini['diambil'] }}).
      Karyawan yang belum scan hari ini dinilai sampai kemarin; tanda ≥ = run lebih panjang dari jendela.
      Satu karyawan bisa masuk lebih dari satu kode. Kategori longgar (Transportasi Massal, Mekanik) dikecualikan dari REG-1/2/3 dan MAP-1.
    </span>
  </div>
@endif

<script>
// Modal temuan: buka tab sesuai tile yang diklik, muat daftarnya sekali lewat
// JSON, lalu saring di klien.
(function () {
    'use strict';

    var cache = {};

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function render(modal, baris) {
        var pakaiKet = modal.dataset.roKeterangan === '1';
        var tbody = modal.querySelector('[data-ro-tbody]');
        tbody.innerHTML = baris.map(function (r, i) {
            return '<tr>'
                + '<td>' + (i + 1) + '</td>'
                + '<td>' + esc(r.sid) + '</td>'
                + '<td>' + esc(r.nama) + '</td>'
                + '<td>' + esc(r.jabatan) + '</td>'
                + '<td>' + esc(r.pt) + '</td>'
                + '<td>' + esc(r.site) + '</td>'
                + '<td class="text-end fw-semibold">' + (r.terpotong ? '≥' : '') + esc(r.hari) + '</td>'
                + '<td>' + esc(r.mulai) + '</td>'
                + '<td>' + esc(r.selesai) + '</td>'
                + (pakaiKet ? '<td>' + esc(r.keterangan) + '</td>' : '')
                + '<td>' + esc(r.scan_terakhir) + '</td>'
                + '</tr>';
        }).join('');
        saring(modal);
    }

    function pesan(modal, teks) {
        var p = modal.querySelector('[data-ro-pesan]');
        p.textContent = teks || '';
        p.classList.toggle('d-none', !teks);
    }

    function saring(modal) {
        var q = modal.querySelector('[data-ro-cari]').value.trim().toLowerCase();
        var rows = modal.querySelectorAll('[data-ro-tbody] tr');
        var tampil = 0;
        rows.forEach(function (tr) {
            var cocok = q === '' || tr.textContent.toLowerCase().indexOf(q) !== -1;
            tr.classList.toggle('d-none', !cocok);
            if (cocok) { tampil++; }
        });
        pesan(modal, rows.length === 0 ? 'Tidak ada karyawan pada kode ini per hari ini.'
            : (tampil === 0 ? 'Tidak ada yang cocok.' : ''));
    }

    function bukaTab(modal, kode) {
        var btn = modal.querySelector('[data-ro-tab-btn="' + kode + '"]');
        if (!btn) { return; }

        modal.querySelectorAll('[data-ro-tab-btn]').forEach(function (b) {
            b.classList.toggle('active', b === btn);
        });
        modal.querySelector('[data-ro-definisi-teks]').textContent = btn.dataset.roDefinisi;
        modal.querySelector('[data-ro-th-hari]').textContent = btn.dataset.roKolomHari;
        modal.querySelector('[data-ro-unduh-btn]').href = btn.dataset.roUnduh;
        modal.querySelector('[data-ro-tbody]').innerHTML = '';

        if (cache[kode]) {
            render(modal, cache[kode]);
            return;
        }

        pesan(modal, 'Memuat data RFID…');
        fetch(btn.dataset.roUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json.up) { throw new Error(json.pesan || 'RFID tidak terjangkau.'); }
                cache[kode] = json.baris;
                // Abaikan respons bila pengguna sudah pindah tab sebelum data tiba.
                if (btn.classList.contains('active')) { render(modal, json.baris); }
            })
            .catch(function (err) {
                pesan(modal, 'Gagal memuat: ' + err.message);
            });
    }

    document.querySelectorAll('[id^="roModalTemuan-"]').forEach(function (modal) {
        modal.addEventListener('show.bs.modal', function (e) {
            var pemicu = e.relatedTarget;
            modal.querySelector('[data-ro-cari]').value = '';
            bukaTab(modal, pemicu && pemicu.dataset.roTab
                ? pemicu.dataset.roTab
                : modal.querySelector('[data-ro-tab-btn]').dataset.roTabBtn);
        });

        modal.querySelectorAll('[data-ro-tab-btn]').forEach(function (b) {
            b.addEventListener('click', function () {
                modal.querySelector('[data-ro-cari]').value = '';
                bukaTab(modal, b.dataset.roTabBtn);
            });
        });

        modal.querySelector('[data-ro-cari]').addEventListener('input', function () {
            saring(modal);
        });
    });
})();
</script>
