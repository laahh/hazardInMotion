{{--
  Isi modal "Kurang" di kartu Cakupan upload roster (fragmen HTML, dimuat
  RosterMasterController::overviewKurang). Tautan [data-ro-muat] dan form
  [data-ro-filter] dimuat ulang ke modal yang sama oleh skrip overview.blade.
--}}
@php
  $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  $angka = static fn (int $n): string => number_format($n, 0, ',', '.');
  $dasar = ['pt' => $pt, 'tampil' => $tampil, 'bulan' => $bulan->format('Y-m'), 'q' => $cari ?: null];
  $url = static fn (array $ubah): string => route('dms.master-roster.overview.kurang', array_filter($ubah + $dasar, static fn ($v) => $v !== null && $v !== ''), false);
@endphp
<div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-12">
  <div>
    <div class="text-sm text-secondary-light">
      @if ($jumlah['aktif'] !== null)
        {{ $angka($jumlah['aktif']) }} karyawan aktif &middot; {{ $angka($jumlah['aktif'] - $jumlah['belum']) }} sudah di roster &middot;
        <b class="text-danger-600">{{ $angka($jumlah['belum']) }} belum di roster</b>
      @else
        Daftar karyawan aktif tidak terbaca saat ini — coba lagi nanti.
      @endif
    </div>
  </div>
  <div class="d-flex gap-2 rm-hal">
    <a class="btn btn-sm btn-outline-secondary radius-8" href="{{ $url(['bulan' => $bulan->subMonthNoOverflow()->format('Y-m'), 'hal' => null]) }}" data-ro-muat>&laquo; {{ $namaBulan[$bulan->subMonthNoOverflow()->month] }}</a>
    <span class="btn btn-sm radius-8 fw-semibold" style="pointer-events:none">{{ $namaBulan[$bulan->month] }} {{ $bulan->year }}</span>
    <a class="btn btn-sm btn-outline-secondary radius-8" href="{{ $url(['bulan' => $bulan->addMonthNoOverflow()->format('Y-m'), 'hal' => null]) }}" data-ro-muat>{{ $namaBulan[$bulan->addMonthNoOverflow()->month] }} &raquo;</a>
  </div>
</div>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-12">
  <div class="rov-cak-saring" role="tablist">
    <a href="{{ $url(['tampil' => 'belum', 'hal' => null]) }}" data-ro-muat role="tab" aria-selected="{{ $tampil === 'belum' ? 'true' : 'false' }}"
       class="rov-cak-tab {{ $tampil === 'belum' ? 'is-aktif' : '' }}"><i class="is-belum"></i>Belum di roster <b>{{ $angka($jumlah['belum']) }}</b></a>
    <a href="{{ $url(['tampil' => 'sudah', 'hal' => null]) }}" data-ro-muat role="tab" aria-selected="{{ $tampil === 'sudah' ? 'true' : 'false' }}"
       class="rov-cak-tab {{ $tampil === 'sudah' ? 'is-aktif' : '' }}"><i class="is-sudah"></i>Sudah di roster <b>{{ $angka($jumlah['aktif'] === null ? $jumlah['roster'] : $jumlah['aktif'] - $jumlah['belum']) }}</b></a>
  </div>
  <form method="GET" action="{{ route('dms.master-roster.overview.kurang', absolute: false) }}" data-ro-filter style="width:260px;max-width:100%">
    <input type="hidden" name="pt" value="{{ $pt }}">
    <input type="hidden" name="tampil" value="{{ $tampil }}">
    <input type="hidden" name="bulan" value="{{ $bulan->format('Y-m') }}">
    <input type="search" name="q" value="{{ $cari }}" class="form-control form-control-sm" placeholder="Cari SID / nama… (Enter)" aria-label="Cari SID atau nama">
  </form>
</div>

@if ($tampil === 'belum')
  <p class="text-xs text-secondary-light mb-8">Karyawan aktif yang SID-nya belum ada di roster mana pun (menunggu approval maupun disetujui) — sel jadwalnya kosong.</p>
@else
  <p class="text-xs text-secondary-light mb-8">Jadwal gabungan semua roster; bila dobel, roster terbaru yang dipakai. Garis putus oranye = roster masih menunggu approval.</p>
@endif

@if ($grid['karyawan'] === [])
  <div class="text-center text-secondary-light py-24 text-sm">
    {{ $tampil === 'belum' ? 'Tidak ada karyawan aktif yang belum di roster' : 'Tidak ada jadwal di '.$namaBulan[$bulan->month].' '.$bulan->year }}{{ $cari !== '' ? ' untuk "'.$cari.'"' : '' }}.
  </div>
@else
  <div class="table-responsive rm-gulir" style="max-height:60vh">
    @include('dms.roster-master._grid', ['karyawanGrid' => $grid['karyawan'], 'tanggal' => $tanggal, 'nomorAwal' => ($halaman - 1) * $perHalaman])
  </div>
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-12">
    <span class="text-sm text-secondary-light">{{ $angka($grid['total']) }} karyawan · hal {{ $halaman }}/{{ $totalHalaman }}</span>
    @if ($totalHalaman > 1)
      <div class="d-flex gap-2 rm-hal">
        <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman <= 1 ? 'disabled' : '' }}" href="{{ $url(['hal' => $halaman - 1]) }}" data-ro-muat>&laquo; Sebelumnya</a>
        <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman >= $totalHalaman ? 'disabled' : '' }}" href="{{ $url(['hal' => $halaman + 1]) }}" data-ro-muat>Berikutnya &raquo;</a>
      </div>
    @endif
  </div>
@endif
