@extends('dms.layouts.app')

@section('title', 'Overview Roster')

@section('css')
@include('dms.roster-master._grid-css')
<style>
  .ro-pt-tabel th { font-size: 12px; color: #64748B; font-weight: 600; white-space: nowrap; }
  .ro-pt-tabel td { font-size: 13px; vertical-align: middle; }
  .ro-pt-tabel tr.is-terpilih td { background: #EEF4FF; }
  .ro-pt-tabel tr.is-terpilih td:first-child { box-shadow: inset 3px 0 0 #487fff; }
  .ro-pt-nama { font-weight: 600; color: #0F172A; }
  .ro-filter .form-control, .ro-filter .form-select { min-width: 0; }
  .ro-filter label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
  @media (max-width: 575.98px) { .ro-filter > div { flex: 1 1 100%; } }
</style>
@endsection

@section('content')
@php
  $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  $tautan = static fn (array $ubah): string => request()->fullUrlWithQuery($ubah + ['hal' => null]);
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Overview Roster</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('dms.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>DMS
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Master Roster</li>
    <li>-</li>
    <li class="fw-medium">Overview Roster</li>
  </ul>
</div>

{{-- ══════════ PERUSAHAAN YANG SUDAH UPLOAD ══════════ --}}
<div class="card radius-8 border-0 shadow-sm mb-24">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-4">Perusahaan yang sudah upload roster</h6>
    <p class="text-sm text-secondary-light mb-0">Roster yang ditolak tidak dihitung. Pilih perusahaan untuk melihat jadwalnya.</p>
  </div>
  <div class="card-body p-0">
    @if ($perusahaan === [])
      <div class="p-24 text-center text-secondary-light">
        Belum ada perusahaan yang meng-upload roster.
        <a href="{{ route('dms.master-roster.upload') }}" class="text-primary-600 fw-semibold">Upload roster</a>
      </div>
    @else
      <div class="table-responsive">
        <table class="table mb-0 ro-pt-tabel">
          <thead>
            <tr>
              <th class="ps-24">Perusahaan</th>
              <th class="text-end">Karyawan</th>
              <th>Roster</th>
              <th>Periode jadwal</th>
              <th>Site</th>
              <th>Upload terakhir</th>
              <th class="pe-24"></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($perusahaan as $p)
              @php $aktif = $terpilih && $terpilih['perusahaan'] === $p['perusahaan']; @endphp
              <tr class="{{ $aktif ? 'is-terpilih' : '' }}">
                <td class="ps-24"><span class="ro-pt-nama">{{ $p['perusahaan'] }}</span></td>
                <td class="text-end">{{ number_format($p['karyawan'], 0, ',', '.') }}</td>
                <td class="text-nowrap">
                  @if ($p['disetujui'])<span class="badge bg-success-focus text-success-main">{{ $p['disetujui'] }} disetujui</span>@endif
                  @if ($p['menunggu'])<span class="badge bg-warning-focus text-warning-main">{{ $p['menunggu'] }} menunggu</span>@endif
                </td>
                <td class="text-nowrap">{{ \Carbon\CarbonImmutable::parse($p['dari'])->format('d/m/Y') }} – {{ \Carbon\CarbonImmutable::parse($p['sampai'])->format('d/m/Y') }}</td>
                <td>{{ $p['site'] === [] ? '—' : implode(', ', $p['site']) }}</td>
                <td class="text-nowrap">{{ $p['terakhir'] ? \Carbon\CarbonImmutable::parse($p['terakhir'])->format('d/m/Y H:i') : '—' }}</td>
                <td class="pe-24 text-end">
                  <a href="{{ route('dms.master-roster.overview', ['pt' => $p['perusahaan'], 'menunggu' => $menunggu ? 1 : null]) }}#jadwal"
                     class="btn btn-sm {{ $aktif ? 'btn-primary-600' : 'btn-outline-primary-600' }} radius-8 text-nowrap">
                    {{ $aktif ? 'Ditampilkan' : 'Lihat jadwal' }}
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>

{{-- ══════════ JADWAL BERLAKU PERUSAHAAN TERPILIH ══════════ --}}
@if ($terpilih)
<div class="card radius-8 border-0 shadow-sm" id="jadwal">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-12">
      <div class="rm-kepala-teks">
        <h6 class="text-lg fw-semibold mb-4">{{ $terpilih['perusahaan'] }} &middot; {{ $namaBulan[$bulan->month] }} {{ $bulan->year }}</h6>
        @include('dms.roster-master._legenda')
      </div>
      <div class="d-flex gap-2 rm-hal">
        <a class="btn btn-sm btn-outline-secondary radius-8" href="{{ $tautan(['bulan' => $bulan->subMonthNoOverflow()->format('Y-m')]) }}#jadwal">&laquo; {{ $namaBulan[$bulan->subMonthNoOverflow()->month] }}</a>
        <a class="btn btn-sm btn-outline-secondary radius-8" href="{{ $tautan(['bulan' => $bulan->addMonthNoOverflow()->format('Y-m')]) }}#jadwal">{{ $namaBulan[$bulan->addMonthNoOverflow()->month] }} &raquo;</a>
      </div>
    </div>

    {{-- Filter (GET): ganti nilai langsung menerapkan. --}}
    <form method="GET" action="{{ route('dms.master-roster.overview') }}#jadwal" class="d-flex flex-wrap align-items-end gap-3 ro-filter" id="roFilterOverview">
      <input type="hidden" name="pt" value="{{ $terpilih['perusahaan'] }}">
      <div>
        <label for="roBulan">Bulan</label>
        <input type="month" id="roBulan" name="bulan" value="{{ $bulan->format('Y-m') }}" class="form-control form-control-sm" data-ro-auto>
      </div>
      <div>
        <label for="roSite">Site</label>
        <select id="roSite" name="site" class="form-select form-select-sm" data-ro-auto>
          <option value="">Semua site</option>
          @foreach ($terpilih['site'] as $s)
            <option value="{{ $s }}" @selected($site === $s)>{{ $s }}</option>
          @endforeach
        </select>
      </div>
      <div style="flex: 1 1 220px">
        <label for="roCari">Cari</label>
        <input type="search" id="roCari" name="q" value="{{ $cari }}" class="form-control form-control-sm" placeholder="SID / nama… (Enter)">
      </div>
      <div class="form-check mb-6">
        <input class="form-check-input" type="checkbox" id="roMenunggu" name="menunggu" value="1" @checked($menunggu) data-ro-auto>
        <label class="form-check-label text-sm fw-normal" for="roMenunggu">Termasuk roster yang menunggu approval</label>
      </div>
    </form>
  </div>

  <div class="card-body rm-isi">
    @if ($grid['karyawan'] === [])
      <div class="text-center text-secondary-light py-24">
        Tidak ada jadwal {{ $menunggu ? '' : 'yang sudah disetujui ' }}untuk {{ $terpilih['perusahaan'] }}
        di {{ $namaBulan[$bulan->month] }} {{ $bulan->year }}{{ $site !== '' ? ' (site '.$site.')' : '' }}.
        @if (! $menunggu && $terpilih['menunggu'] > 0)
          <a href="{{ $tautan(['menunggu' => 1]) }}#jadwal" class="text-primary-600 fw-semibold">Tampilkan juga yang menunggu approval</a>
        @endif
      </div>
    @else
      <div class="table-responsive rm-gulir">
        @include('dms.roster-master._grid', ['karyawanGrid' => $grid['karyawan'], 'tanggal' => $tanggal, 'nomorAwal' => ($halaman - 1) * $perHalaman])
      </div>
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-16">
        <span class="text-sm text-secondary-light">
          {{ number_format($grid['total'], 0, ',', '.') }} karyawan · hal {{ $halaman }}/{{ $totalHalaman }}
          @if ($menunggu) · <span class="text-warning-main">garis putus-putus = roster masih menunggu approval</span>@endif
        </span>
        <div class="d-flex gap-2 rm-hal">
          <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman <= 1 ? 'disabled' : '' }}"
             href="{{ request()->fullUrlWithQuery(['hal' => $halaman - 1]) }}#jadwal">&laquo; Sebelumnya</a>
          <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman >= $totalHalaman ? 'disabled' : '' }}"
             href="{{ request()->fullUrlWithQuery(['hal' => $halaman + 1]) }}#jadwal">Berikutnya &raquo;</a>
        </div>
      </div>
    @endif
  </div>
</div>
@endif
@endsection

@section('page-scripts')
<script>
// Filter Overview: bulan, site, dan centang "menunggu" langsung diterapkan.
(function () {
    var f = document.getElementById('roFilterOverview');
    if (!f) { return; }
    f.querySelectorAll('[data-ro-auto]').forEach(function (el) {
        el.addEventListener('change', function () { f.requestSubmit ? f.requestSubmit() : f.submit(); });
    });
})();
</script>
@endsection
