{{--
  Cakupan upload roster: seluruh perusahaan & site karyawan aktif (view safety,
  disalin Tahap 8 dms:sync-roster-rfid) dibanding roster yang sudah di-upload.
  Data dari DmsRosterCakupanUpload::bandingkan() — lihat docblock-nya untuk
  aturan status & pencocokan nama.

  Filter (status, perusahaan, site, cari) bekerja di browser. Bila satu site
  dipilih, status tiap perusahaan = status site itu, dan angka perbandingan di
  atas dihitung ulang hanya untuk site tersebut (skrip di overview.blade.php).
--}}
@php
  $angka = static fn (int $n) => number_format($n, 0, ',', '.');
  $persen = static fn (int $n, int $t) => $t > 0 ? number_format($n / $t * 100, 1, ',', '.') : '0';
  $label = ['sudah' => 'Sudah', 'menunggu' => 'Menunggu approval', 'belum' => 'Belum upload'];
  $ikon = ['sudah' => 'solar:check-circle-bold', 'menunggu' => 'solar:hourglass-bold', 'belum' => 'solar:minus-circle-linear'];
  $r = $cakupan['ringkas'];
  // Belum dulu (yang perlu dikejar), lalu karyawan terbanyak.
  $urutan = ['belum' => 0, 'menunggu' => 1, 'sudah' => 2];
  $daftar = $cakupan['perusahaan'];
  usort($daftar, static fn (array $a, array $b): int => [$urutan[$a['status']], -$a['karyawan'], $a['nama']] <=> [$urutan[$b['status']], -$b['karyawan'], $b['nama']]);
  // Pilihan dropdown: perusahaan urut nama, site urut nama dengan jumlah perusahaannya.
  $opsiPt = array_column($cakupan['perusahaan'], 'nama');
  natcasesort($opsiPt);
  $opsiSite = [];
  foreach ($cakupan['perusahaan'] as $p) {
      foreach ($p['site'] as $s) {
          $opsiSite[$s['nama']] = ($opsiSite[$s['nama']] ?? 0) + 1;
      }
  }
  uksort($opsiSite, 'strnatcasecmp');
@endphp

<div class="rov-kartu mb-16" id="roCakupan">
  <div class="rov-kartu__kepala">
    <div>
      <div class="rov-kartu__judul">Cakupan upload roster</div>
      <p class="rov-kartu__sub">
        Seluruh perusahaan &amp; site karyawan aktif dibanding roster yang sudah di-upload (roster ditolak tidak dihitung).
        <b>Sudah</b> = ada roster disetujui · <b>Menunggu</b> = baru di-upload, belum disetujui.
      </p>
    </div>
    <div class="rov-cak-konteks d-none" data-cak-konteks></div>
  </div>

  {{-- KPI cakupan; angka diisi ulang oleh skrip saat filter perusahaan/site dipakai. --}}
  @php
    $pt = $r['pt'];
    $diupload = $pt['sudah'] + $pt['menunggu'];
  @endphp
  <div class="rov-kpi rov-cak-kpi" data-cak-kpi>
    <div class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-indigo"><iconify-icon icon="solar:buildings-2-bold"></iconify-icon></span>
      <div>
        <div class="rov-kpi__label">Total perusahaan</div>
        <div class="rov-kpi__nilai" data-cak-n="total">{{ $angka($pt['total']) }}</div>
        <div class="rov-kpi__sub"><span data-cak-n="site_total">{{ $angka($r['site']['total']) }}</span> site · <span data-cak-n="kar_total">{{ $angka($r['karyawan']['total']) }}</span> karyawan aktif</div>
      </div>
    </div>
    <div class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-hijau"><iconify-icon icon="solar:upload-minimalistic-bold"></iconify-icon></span>
      <div>
        <div class="rov-kpi__label">Sudah upload</div>
        <div class="rov-kpi__nilai" data-cak-n="upload">{{ $angka($diupload) }}</div>
        <div class="rov-kpi__sub"><span data-cak-n="sudah">{{ $angka($pt['sudah']) }}</span> disetujui · <span data-cak-n="menunggu">{{ $angka($pt['menunggu']) }}</span> menunggu approval</div>
      </div>
    </div>
    <div class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-merah"><iconify-icon icon="solar:danger-circle-bold"></iconify-icon></span>
      <div>
        <div class="rov-kpi__label">Belum upload</div>
        <div class="rov-kpi__nilai" data-cak-n="belum">{{ $angka($pt['belum']) }}</div>
        <div class="rov-kpi__sub"><span data-cak-n="site_belum">{{ $angka($r['site']['belum']) }}</span> site belum ada roster</div>
      </div>
    </div>
    <div class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-sky"><iconify-icon icon="solar:chart-2-bold"></iconify-icon></span>
      <div class="flex-grow-1">
        <div class="rov-kpi__label">Comply rate</div>
        <div class="rov-kpi__nilai"><span data-cak-n="rate">{{ $persen($pt['sudah'], $pt['total']) }}</span>%</div>
        <div class="rov-cak-rate" role="img" aria-label="Comply rate" data-cak-rate>
          <span class="is-sudah" data-cak-seg="sudah" style="width: {{ $pt['total'] > 0 ? round($pt['sudah'] / $pt['total'] * 100, 2) : 0 }}%"></span>
          <span class="is-menunggu" data-cak-seg="menunggu" style="width: {{ $pt['total'] > 0 ? round($pt['menunggu'] / $pt['total'] * 100, 2) : 0 }}%"></span>
        </div>
        <div class="rov-kpi__sub">Disetujui ÷ total perusahaan</div>
      </div>
    </div>
  </div>

  <div class="rov-cak-alat">
    <div class="rov-cak-saring" role="group" aria-label="Saring status">
      <button type="button" class="is-aktif" data-cak-status="">Semua <b data-cak-hitung="">{{ $angka($r['pt']['total']) }}</b></button>
      @foreach (['belum', 'menunggu', 'sudah'] as $s)
        <button type="button" data-cak-status="{{ $s }}"><i class="is-{{ $s }}"></i>{{ $label[$s] }} <b data-cak-hitung="{{ $s }}">{{ $angka($r['pt'][$s]) }}</b></button>
      @endforeach
    </div>
    <div class="rov-cak-filter">
      <select class="form-select form-select-sm" data-cak-pt aria-label="Filter perusahaan">
        <option value="">Semua perusahaan</option>
        @foreach ($opsiPt as $nama)
          <option value="{{ $nama }}">{{ $nama }}</option>
        @endforeach
      </select>
      <select class="form-select form-select-sm" data-cak-site aria-label="Filter site">
        <option value="">Semua site</option>
        @foreach ($opsiSite as $nama => $jml)
          <option value="{{ $nama }}">{{ $nama }} ({{ $jml }} PT)</option>
        @endforeach
      </select>
      <input type="search" class="form-control form-control-sm rov-cak-cari" placeholder="Cari perusahaan / site…" data-cak-cari aria-label="Cari perusahaan atau site">
      <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-cak-reset>Reset</button>
    </div>
  </div>

  <div class="rov-cak-gulir">
    <table class="table ro-pt-tabel mb-0">
      <thead>
        <tr>
          <th class="ps-24">Perusahaan</th>
          <th class="text-end">Karyawan aktif</th>
          <th>Status</th>
          <th>Site <span class="fw-normal text-lowercase">(sudah / total)</span></th>
          <th class="pe-24 text-end">Di roster</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($daftar as $p)
          <tr data-cak-baris data-status="{{ $p['status'] }}" data-nama="{{ $p['nama'] }}" data-karyawan="{{ $p['karyawan'] }}"
              data-site="{{ json_encode(array_map(static fn (array $s): array => [$s['nama'], $s['status'], $s['karyawan']], $p['site'])) }}"
              data-cari="{{ mb_strtolower($p['nama'].' '.implode(' ', $p['alias']).' '.implode(' ', array_column($p['site'], 'nama'))) }}">
            <td class="ps-24">
              <div class="ro-pt-nama">{{ $p['nama'] }}</div>
              @if ($p['alias'] !== [])
                <div class="ro-pt-site" title="Ejaan di file roster yang dicocokkan ke perusahaan ini">di roster: {{ implode(', ', $p['alias']) }}</div>
              @endif
            </td>
            <td class="text-end"><span class="rov-angka">{{ $angka($p['karyawan']) }}</span></td>
            <td>
              <span class="rov-cak-status is-{{ $p['status'] }}"><iconify-icon icon="{{ $ikon[$p['status']] }}"></iconify-icon>{{ $label[$p['status']] }}</span>
            </td>
            <td>
              @if ($p['site'] === [])
                <span class="ro-pt-site">Tanpa site</span>
              @else
                <div class="rov-cak-jumlah" data-cak-jumlah>{{ $p['site_sudah'] }} / {{ count($p['site']) }}</div>
                <div class="rov-cak-situs">
                  @foreach ($p['site'] as $s)
                    <span class="rov-cak-chip is-{{ $s['status'] }}" data-cak-chip="{{ $s['nama'] }}" title="{{ $s['nama'] }} · {{ $label[$s['status']] }} · {{ $angka($s['karyawan']) }} karyawan aktif">
                      <iconify-icon icon="{{ $ikon[$s['status']] }}"></iconify-icon>{{ $s['nama'] }}
                    </span>
                  @endforeach
                </div>
              @endif
              @if ($p['site_lain'] !== [])
                <div class="ro-pt-site mt-4" title="Site di roster yang tidak ada di data karyawan aktif perusahaan ini">+ di roster saja: {{ implode(', ', $p['site_lain']) }}</div>
              @endif
            </td>
            <td class="pe-24 text-end">
              @if ($p['karyawan_roster'] > 0)
                <span class="rov-angka">{{ $angka($p['karyawan_roster']) }}</span>
              @else
                <span class="ro-pt-site">—</span>
              @endif
            </td>
          </tr>
        @endforeach
        <tr data-cak-kosong class="d-none"><td colspan="5" class="text-center text-secondary-light py-24">Tidak ada perusahaan yang cocok.</td></tr>
      </tbody>
    </table>
  </div>

  @if ($cakupan['tidak_cocok'] !== [])
    <div class="rov-cak-tidakcocok">
      <iconify-icon icon="solar:info-circle-linear"></iconify-icon>
      <div>
        Nama perusahaan di roster yang tidak ditemukan di data karyawan aktif (tidak ikut dihitung):
        @foreach ($cakupan['tidak_cocok'] as $nama)<span class="rov-chip">{{ $nama }}</span>@endforeach
      </div>
    </div>
  @endif
</div>
