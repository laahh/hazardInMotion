{{--
  Cakupan upload roster: seluruh perusahaan & site karyawan aktif (view safety,
  disalin Tahap 8 dms:sync-roster-rfid) dibanding roster yang sudah di-upload.
  Data dari DmsRosterCakupanUpload::bandingkan() — lihat docblock-nya untuk
  aturan status & pencocokan nama.
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
  </div>

  <div class="rov-cak-banding">
    @foreach (['pt' => ['Perusahaan', 'solar:buildings-2-bold'], 'site' => ['Site perusahaan', 'solar:map-point-bold']] as $kunci => [$judul, $ikonJudul])
      @php $n = $r[$kunci]; @endphp
      <div class="rov-cak-blok">
        <div class="rov-cak-blok__judul"><iconify-icon icon="{{ $ikonJudul }}"></iconify-icon>{{ $judul }}</div>
        <div class="rov-cak-blok__nilai">
          {{ $angka($n['sudah']) }}<span> / {{ $angka($n['total']) }} sudah</span>
          <em>{{ $persen($n['sudah'], $n['total']) }}%</em>
        </div>
        <div class="rov-cak-bar" role="img"
             aria-label="{{ $judul }}: {{ $n['sudah'] }} sudah, {{ $n['menunggu'] }} menunggu, {{ $n['belum'] }} belum dari {{ $n['total'] }}">
          @foreach (['sudah', 'menunggu', 'belum'] as $s)
            @if ($n[$s] > 0)
              <span class="is-{{ $s }}" style="flex-grow: {{ $n[$s] }}" title="{{ $label[$s] }}: {{ $angka($n[$s]) }}"></span>
            @endif
          @endforeach
        </div>
        <div class="rov-cak-legenda">
          @foreach (['sudah', 'menunggu', 'belum'] as $s)
            <span><i class="is-{{ $s }}"></i>{{ $label[$s] }} <b>{{ $angka($n[$s]) }}</b></span>
          @endforeach
        </div>
        @if ($kunci === 'pt')
          <div class="rov-cak-blok__catatan">
            Karyawan aktif di perusahaan yang sudah: <b>{{ $angka($r['karyawan']['sudah']) }}</b> dari {{ $angka($r['karyawan']['total']) }}
            ({{ $persen($r['karyawan']['sudah'], $r['karyawan']['total']) }}%)
          </div>
        @else
          <div class="rov-cak-blok__catatan">Karyawan aktif tanpa site tidak dihitung sebagai site.</div>
        @endif
      </div>
    @endforeach
  </div>

  <div class="rov-cak-alat">
    <div class="rov-cak-saring" role="group" aria-label="Saring status">
      <button type="button" class="is-aktif" data-cak-status="">Semua <b>{{ $angka($r['pt']['total']) }}</b></button>
      @foreach (['belum', 'menunggu', 'sudah'] as $s)
        <button type="button" data-cak-status="{{ $s }}"><i class="is-{{ $s }}"></i>{{ $label[$s] }} <b>{{ $angka($r['pt'][$s]) }}</b></button>
      @endforeach
    </div>
    <input type="search" class="form-control form-control-sm rov-cak-cari" placeholder="Cari perusahaan / site…" data-cak-cari aria-label="Cari perusahaan atau site">
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
          <tr data-cak-baris data-status="{{ $p['status'] }}"
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
                <div class="rov-cak-jumlah">{{ $p['site_sudah'] }} / {{ count($p['site']) }}</div>
                <div class="rov-cak-situs">
                  @foreach ($p['site'] as $s)
                    <span class="rov-cak-chip is-{{ $s['status'] }}" title="{{ $s['nama'] }} · {{ $label[$s['status']] }} · {{ $angka($s['karyawan']) }} karyawan aktif">
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
