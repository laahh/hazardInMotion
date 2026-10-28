{{--
  Isi panel collapse Overview Roster untuk satu perusahaan (fragmen HTML,
  dimuat RosterMasterController::overviewJadwal). Tautan & form ber-atribut
  data-ro-muat / data-ro-filter dimuat ulang ke panel yang sama oleh skrip
  overview.blade — tanpa memuat ulang halaman.
--}}
@php
  $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  $dasar = ['pt' => $terpilih['perusahaan'], 'bulan' => $bulan->format('Y-m'), 'site' => $site ?: null, 'q' => $cari ?: null, 'menunggu' => $menunggu ? 1 : null];
  $url = static fn (array $ubah): string => route('dms.master-roster.overview.jadwal', array_filter($ubah + $dasar, static fn ($v) => $v !== null && $v !== ''));
  $sebelum = $bulan->subMonthNoOverflow();
  $sesudah = $bulan->addMonthNoOverflow();
@endphp
<div class="ro-sc-detail__panel">
  <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-12">
    <div class="rm-kepala-teks">
      <div class="ro-sc-detail__title">Jadwal &mdash; {{ $terpilih['perusahaan'] }} &middot; {{ $namaBulan[$bulan->month] }} {{ $bulan->year }}</div>
      <div class="ro-sc-detail__sub mb-8">
        {{ number_format($grid['total'], 0, ',', '.') }} karyawan
        &middot; {{ $menunggu ? 'roster disetujui + menunggu approval' : 'roster yang sudah disetujui' }}
      </div>
      @include('dms.roster-master._legenda')
    </div>
    <div class="d-flex gap-2 rm-hal">
      <a class="btn btn-sm btn-outline-secondary radius-8" href="{{ $url(['bulan' => $sebelum->format('Y-m'), 'hal' => null]) }}" data-ro-muat>&laquo; {{ $namaBulan[$sebelum->month] }}</a>
      <a class="btn btn-sm btn-outline-secondary radius-8" href="{{ $url(['bulan' => $sesudah->format('Y-m'), 'hal' => null]) }}" data-ro-muat>{{ $namaBulan[$sesudah->month] }} &raquo;</a>
    </div>
  </div>

  <form method="GET" action="{{ route('dms.master-roster.overview.jadwal', absolute: false) }}" class="d-flex flex-wrap align-items-end gap-3 ro-filter mb-16" data-ro-filter>
    <input type="hidden" name="pt" value="{{ $terpilih['perusahaan'] }}">
    <div>
      <label>Bulan</label>
      <input type="month" name="bulan" value="{{ $bulan->format('Y-m') }}" class="form-control form-control-sm" data-ro-auto>
    </div>
    <div>
      <label>Site</label>
      <select name="site" class="form-select form-select-sm" data-ro-auto>
        <option value="">Semua site</option>
        @foreach ($terpilih['site'] as $s)
          <option value="{{ $s }}" @selected($site === $s)>{{ $s }}</option>
        @endforeach
      </select>
    </div>
    <div style="flex: 1 1 220px">
      <label>Cari</label>
      <input type="search" name="q" value="{{ $cari }}" class="form-control form-control-sm" placeholder="SID / nama… (Enter)">
    </div>
    <label class="d-inline-flex align-items-center gap-2 mb-8 fw-normal text-sm" style="color:#334155">
      <input class="form-check-input m-0" type="checkbox" name="menunggu" value="1" @checked($menunggu) data-ro-auto>
      Termasuk roster yang menunggu approval
    </label>
  </form>

  @if ($grid['karyawan'] === [])
    <div class="text-center text-secondary-light py-24 text-sm">
      Tidak ada jadwal {{ $menunggu ? '' : 'yang sudah disetujui ' }}untuk {{ $terpilih['perusahaan'] }}
      di {{ $namaBulan[$bulan->month] }} {{ $bulan->year }}{{ $site !== '' ? ' (site '.$site.')' : '' }}.
      @if (! $menunggu && $terpilih['menunggu'] > 0)
        <a href="{{ $url(['menunggu' => 1, 'hal' => null]) }}" data-ro-muat class="text-primary-600 fw-semibold">Tampilkan juga yang menunggu approval</a>
      @endif
    </div>
  @else
    <div class="table-responsive rm-gulir">
      @include('dms.roster-master._grid', ['karyawanGrid' => $grid['karyawan'], 'tanggal' => $tanggal, 'nomorAwal' => ($halaman - 1) * $perHalaman])
    </div>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-12">
      <span class="text-sm text-secondary-light">
        hal {{ $halaman }}/{{ $totalHalaman }}
        @if ($menunggu) · <span class="text-warning-main">garis putus-putus = roster masih menunggu approval</span>@endif
      </span>
      @if ($totalHalaman > 1)
        <div class="d-flex gap-2 rm-hal">
          <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman <= 1 ? 'disabled' : '' }}" href="{{ $url(['hal' => $halaman - 1]) }}" data-ro-muat>&laquo; Sebelumnya</a>
          <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman >= $totalHalaman ? 'disabled' : '' }}" href="{{ $url(['hal' => $halaman + 1]) }}" data-ro-muat>Berikutnya &raquo;</a>
        </div>
      @endif
    </div>
  @endif
</div>
