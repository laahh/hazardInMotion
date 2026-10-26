@extends('dms.layouts.app')

@section('title', 'Overview Roster')

@section('css')
{{-- Tombol "Lihat …" & panel collapse sama dengan tabel Scorecard Ringkasan Roster. --}}
<link rel="stylesheet" href="{{ asset('dms-assets/roster-compliance/roster-overview.css') }}?v=16">
@include('dms.roster-master._grid-css')
<style>
  .ro-pt-tabel th { font-size: 12px; color: #64748B; font-weight: 600; white-space: nowrap; }
  .ro-pt-tabel td { font-size: 13px; vertical-align: middle; }
  .ro-pt-tabel tr.is-buka > td { background: #F8FAFF; }
  .ro-pt-tabel tr.is-buka > td:first-child { box-shadow: inset 3px 0 0 #4F46E5; }
  .ro-pt-nama { font-weight: 600; color: #0F172A; }
  .ro-pt-detail > td { padding: 0 !important; border-bottom: 0; }
  /* Isi panel tidak boleh ikut menentukan lebar tabel perusahaan (tabel jadwal
     bisa sangat lebar): lebar 0 + min-width 100% = selebar sel, sisanya digulir di dalam. */
  .ro-pt-detail .collapse, .ro-pt-detail .collapsing { width: 0; min-width: 100%; }
  .ro-filter .form-control, .ro-filter .form-select { min-width: 0; }
  .ro-filter label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
  .ro-memuat { padding: 28px; text-align: center; color: #64748B; font-size: 13px; }
  @media (max-width: 575.98px) {
    .ro-filter > div { flex: 1 1 100%; }
    .ro-sc-detail { padding: 12px; }
    .ro-sc-detail__panel { padding: 12px; }
  }
</style>
@endsection

@section('content')
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

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-4">Perusahaan yang sudah upload roster</h6>
    <p class="text-sm text-secondary-light mb-0">Roster yang ditolak tidak dihitung. Klik <b>Lihat jadwal</b> untuk membuka jadwal perusahaan di bawah barisnya.</p>
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
              <th class="pe-24 text-end">Jadwal</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($perusahaan as $i => $p)
              @php $buka = $buka === $p['perusahaan']; $idPanel = 'roPt'.$i; @endphp
              <tr class="{{ $buka ? 'is-buka' : '' }}" data-ro-baris="{{ $idPanel }}">
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
                  <button class="ro-sc-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $idPanel }}"
                          aria-expanded="{{ $buka ? 'true' : 'false' }}" aria-controls="{{ $idPanel }}">
                    <iconify-icon icon="solar:alt-arrow-down-linear"></iconify-icon>
                    Lihat jadwal
                  </button>
                </td>
              </tr>
              <tr class="ro-pt-detail">
                <td colspan="7">
                  <div class="collapse {{ $buka ? 'show' : '' }}" id="{{ $idPanel }}"
                       data-ro-url="{{ route('dms.master-roster.overview.jadwal', ['pt' => $p['perusahaan']]) }}">
                    <div class="ro-sc-detail" data-ro-isi>
                      <div class="ro-memuat">Memuat jadwal…</div>
                    </div>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
@endsection

@section('page-scripts')
<script>
// Overview Roster: jadwal tiap perusahaan dimuat saat barisnya dibuka; navigasi
// bulan, filter, cari, dan paginasi di dalam panel memuat ulang panel itu saja.
(function () {
    'use strict';

    function muat(panel, url) {
        var isi = panel.querySelector('[data-ro-isi]');
        isi.setAttribute('aria-busy', 'true');
        if (!isi.querySelector('.ro-sc-detail__panel')) {
            isi.innerHTML = '<div class="ro-memuat">Memuat jadwal…</div>';
        } else {
            isi.style.opacity = '.55';
        }
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.text(); })
            .then(function (html) {
                isi.innerHTML = html;
                panel.dataset.roDimuat = '1';
                pasang(panel);
            })
            .catch(function () {
                isi.innerHTML = '<div class="ro-memuat text-danger-600">Gagal memuat jadwal. <a href="#" data-ro-ulang class="text-primary-600 fw-semibold">Coba lagi</a></div>';
                isi.querySelector('[data-ro-ulang]').addEventListener('click', function (e) { e.preventDefault(); muat(panel, url); });
            })
            .finally(function () { isi.style.opacity = ''; isi.removeAttribute('aria-busy'); });
    }

    function pasang(panel) {
        panel.querySelectorAll('[data-ro-muat]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                if (!a.classList.contains('disabled')) { muat(panel, a.href); }
            });
        });
        var form = panel.querySelector('[data-ro-filter]');
        if (!form) { return; }
        var kirim = function () { muat(panel, form.action + '?' + new URLSearchParams(new FormData(form)).toString()); };
        form.addEventListener('submit', function (e) { e.preventDefault(); kirim(); });
        form.querySelectorAll('[data-ro-auto]').forEach(function (el) { el.addEventListener('change', kirim); });
    }

    document.querySelectorAll('.collapse[data-ro-url]').forEach(function (panel) {
        var baris = document.querySelector('[data-ro-baris="' + panel.id + '"]');
        panel.addEventListener('show.bs.collapse', function () {
            if (baris) { baris.classList.add('is-buka'); }
            if (!panel.dataset.roDimuat) { muat(panel, panel.dataset.roUrl); }
        });
        panel.addEventListener('hide.bs.collapse', function () { if (baris) { baris.classList.remove('is-buka'); } });
        // Dibuka sejak awal (?pt=…): langsung muat.
        if (panel.classList.contains('show')) { muat(panel, panel.dataset.roUrl); }
    });
})();
</script>
@endsection
