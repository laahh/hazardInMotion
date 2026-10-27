@extends('dms.layouts.app')

@section('title', 'Overview Roster')

@section('css')
{{-- Tombol "Lihat …" & panel collapse sama dengan tabel Scorecard Ringkasan Roster. --}}
<link rel="stylesheet" href="{{ asset('dms-assets/roster-compliance/roster-overview.css') }}?v=16">
@include('dms.roster-master._grid-css')
<style>
  /* ── KPI ── */
  .rov-kpi { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
  .rov-kpi__kartu { background: #fff; border: 1px solid #EEF2F7; border-radius: 12px; padding: 18px 20px; display: flex; gap: 14px; align-items: flex-start; }
  .rov-kpi__ikon { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; font-size: 20px; flex: none; }
  .rov-kpi__label { font-size: 12px; font-weight: 600; color: #64748B; margin-bottom: 4px; }
  .rov-kpi__nilai { font-size: 26px; line-height: 1.1; font-weight: 700; color: #0F172A; font-variant-numeric: tabular-nums; }
  .rov-kpi__sub { font-size: 12px; color: #64748B; margin-top: 4px; }
  .rov-kpi__sub a { color: #4F46E5; font-weight: 600; }
  .rov-ikon-indigo { background: #EEF2FF; color: #4F46E5; }
  .rov-ikon-sky { background: #F0F9FF; color: #0284C7; }
  .rov-ikon-hijau { background: #ECFDF5; color: #059669; }
  .rov-ikon-oranye { background: #FFF7ED; color: #C2410C; }

  /* ── Perlu perhatian ── */
  .rov-perhatian { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
  .rov-perhatian__kartu { background: #fff; border: 1px solid #EEF2F7; border-radius: 12px; padding: 14px 18px; display: flex; gap: 12px; align-items: flex-start; }
  .rov-perhatian__kartu > iconify-icon { font-size: 20px; flex: none; margin-top: 1px; }
  .rov-kpi > *, .rov-perhatian > *, .rov-perhatian__kartu > div { min-width: 0; }
  .rov-chip { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .rov-perhatian__judul { font-size: 13px; font-weight: 600; color: #0F172A; }
  .rov-perhatian__isi { font-size: 12px; color: #64748B; margin-top: 2px; }
  .rov-chip { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 999px; background: #F1F5F9; color: #334155; font-size: 11px; font-weight: 600; margin: 4px 4px 0 0; }

  /* ── Daftar perusahaan ── */
  .rov-kartu { background: #fff; border: 1px solid #EEF2F7; border-radius: 12px; overflow: hidden; }
  .rov-kartu__kepala { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; padding: 18px 24px; border-bottom: 1px solid #EEF2F7; }
  .rov-kartu__judul { font-size: 16px; font-weight: 600; color: #0F172A; margin: 0; }
  .rov-kartu__sub { font-size: 12px; color: #64748B; margin: 2px 0 0; }
  .rov-legenda { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: #475569; }
  .rov-legenda span { display: inline-flex; align-items: center; gap: 6px; }
  .rov-legenda i { width: 14px; height: 8px; border-radius: 4px; display: inline-block; }
  .rov-legenda i.is-garis { width: 2px; height: 12px; border-radius: 1px; background: #0F172A; }

  .ro-pt-tabel { margin: 0; }
  .ro-pt-tabel th { font-size: 11px; color: #64748B; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; background: #F8FAFC; border-bottom: 1px solid #EEF2F7; padding-top: 10px; padding-bottom: 10px; }
  .ro-pt-tabel td { font-size: 13px; vertical-align: middle; border-bottom: 1px solid #F1F5F9; padding-top: 14px; padding-bottom: 14px; }
  .ro-pt-tabel tbody tr[data-ro-baris]:hover > td { background: #FAFBFF; }
  .ro-pt-tabel tr.is-buka > td { background: #F8FAFF; }
  .ro-pt-tabel tr.is-buka > td:first-child { box-shadow: inset 3px 0 0 #4F46E5; }
  .ro-pt-nama { font-weight: 600; color: #0F172A; }
  .ro-pt-site { font-size: 12px; color: #64748B; margin-top: 2px; }
  .ro-pt-detail > td { padding: 0 !important; border-bottom: 0; }
  /* Isi panel tidak boleh ikut menentukan lebar tabel perusahaan (tabel jadwal
     bisa sangat lebar): lebar 0 + min-width 100% = selebar sel, sisanya digulir di dalam. */
  .ro-pt-detail .collapse, .ro-pt-detail .collapsing { width: 0; min-width: 100%; }

  .rov-angka { font-weight: 600; color: #0F172A; font-variant-numeric: tabular-nums; }
  .rov-minibar { height: 6px; border-radius: 3px; background: #F1F5F9; margin-top: 6px; width: 96px; }
  .rov-minibar > span { display: block; height: 100%; border-radius: 3px; background: #6366F1; }

  /* Garis waktu periode: semua baris memakai sumbu tanggal yang sama. */
  .rov-waktu { position: relative; height: 10px; border-radius: 5px; background: #F1F5F9; min-width: 180px; }
  .rov-waktu__bar { position: absolute; top: 0; bottom: 0; border-radius: 5px; background: #A5B4FC; }
  .rov-waktu__bar.is-berlaku { background: #6366F1; }
  .rov-waktu__bar.is-segera { background: #6366F1; }
  .rov-waktu__bar.is-berakhir { background: #CBD5E1; }
  .rov-waktu__kini { position: absolute; top: -3px; bottom: -3px; width: 2px; border-radius: 1px; background: #0F172A; }
  .rov-waktu__tgl { display: flex; justify-content: space-between; font-size: 11px; color: #64748B; margin-top: 6px; font-variant-numeric: tabular-nums; }
  .rov-sumbu { display: flex; justify-content: space-between; font-size: 10px; font-weight: 500; color: #94A3B8; text-transform: none; letter-spacing: 0; margin-top: 2px; }

  .rov-status { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; white-space: nowrap; }
  .rov-status iconify-icon { font-size: 15px; }
  .rov-status.is-berlaku { color: #047857; }
  .rov-status.is-segera { color: #B45309; }
  .rov-status.is-berakhir { color: #64748B; }
  .rov-status.is-belum { color: #4338CA; }
  .rov-upload { font-size: 11px; color: #94A3B8; margin-top: 3px; white-space: nowrap; }
  .rov-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
  .rov-badge.is-ok { background: #ECFDF5; color: #047857; }
  .rov-badge.is-tunggu { background: #FFF7ED; color: #C2410C; }

  .ro-filter .form-control, .ro-filter .form-select { min-width: 0; }
  .ro-filter label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
  .ro-memuat { padding: 28px; text-align: center; color: #64748B; font-size: 13px; }

  @media (max-width: 1199.98px) { .rov-kpi { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 767.98px) { .rov-perhatian { grid-template-columns: 1fr; } }
  @media (max-width: 575.98px) {
    .rov-kpi { grid-template-columns: 1fr; }
    .rov-kartu__kepala { padding: 16px; }
    .ro-filter > div { flex: 1 1 100%; }
    .ro-sc-detail { padding: 12px; }
    .ro-sc-detail__panel { padding: 12px; }
  }
</style>
@endsection

@section('content')
@php
  $fmt = static fn ($t, string $f = 'd/m/Y') => \Carbon\CarbonImmutable::parse($t)->format($f);
  $angka = static fn (int $n) => number_format($n, 0, ',', '.');
  $ikonStatus = ['berlaku' => 'solar:check-circle-bold', 'segera' => 'solar:danger-triangle-bold', 'berakhir' => 'solar:history-bold', 'belum' => 'solar:calendar-bold'];
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Overview Roster</h6>
    <p class="text-sm text-secondary-light mb-0 mt-4">Status roster kerja yang sudah di-upload per perusahaan, per {{ now()->locale('id')->translatedFormat('d F Y') }}.</p>
  </div>
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

@if ($perusahaan === [])
  <div class="rov-kartu p-24 text-center text-secondary-light">
    Belum ada perusahaan yang meng-upload roster.
    <a href="{{ route('dms.master-roster.upload') }}" class="text-primary-600 fw-semibold">Upload roster</a>
  </div>
@else
  {{-- KPI --}}
  <div class="rov-kpi">
    <div class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-indigo"><iconify-icon icon="solar:buildings-2-bold"></iconify-icon></span>
      <div>
        <div class="rov-kpi__label">Perusahaan</div>
        <div class="rov-kpi__nilai">{{ $angka($ringkas['perusahaan']) }}</div>
        <div class="rov-kpi__sub">{{ $angka($ringkas['berlaku']) }} punya roster berlaku hari ini</div>
      </div>
    </div>
    <div class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-sky"><iconify-icon icon="solar:users-group-rounded-bold"></iconify-icon></span>
      <div>
        <div class="rov-kpi__label">Karyawan terjadwal</div>
        <div class="rov-kpi__nilai">{{ $angka($ringkas['karyawan']) }}</div>
        <div class="rov-kpi__sub">{{ $angka($ringkas['karyawan_berlaku']) }} di roster yang berlaku hari ini</div>
      </div>
    </div>
    <div class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-hijau"><iconify-icon icon="solar:check-circle-bold"></iconify-icon></span>
      <div>
        <div class="rov-kpi__label">Roster disetujui</div>
        <div class="rov-kpi__nilai">{{ $angka($ringkas['disetujui']) }}</div>
        <div class="rov-kpi__sub">dari {{ $angka($ringkas['disetujui'] + $ringkas['menunggu']) }} upload aktif</div>
      </div>
    </div>
    <div class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-oranye"><iconify-icon icon="solar:hourglass-bold"></iconify-icon></span>
      <div>
        <div class="rov-kpi__label">Menunggu approval</div>
        <div class="rov-kpi__nilai">{{ $angka($ringkas['menunggu']) }}</div>
        <div class="rov-kpi__sub">
          @if ($ringkas['menunggu'] > 0)
            <a href="{{ route('dms.master-roster.approval') }}">Buka Approval Roster →</a>
          @else
            Semua roster sudah direview
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Perlu perhatian --}}
  <div class="rov-perhatian">
    <div class="rov-perhatian__kartu">
      <iconify-icon icon="solar:hourglass-line-bold" class="{{ $ringkas['pt_menunggu'] === [] ? 'text-success-main' : 'text-warning-main' }}"></iconify-icon>
      <div>
        <div class="rov-perhatian__judul">
          {{ $ringkas['pt_menunggu'] === [] ? 'Tidak ada roster yang menunggu approval' : count($ringkas['pt_menunggu']).' perusahaan menunggu approval' }}
        </div>
        @if ($ringkas['pt_menunggu'] !== [])
          <div>@foreach ($ringkas['pt_menunggu'] as $nama)<span class="rov-chip">{{ $nama }}</span>@endforeach</div>
        @else
          <div class="rov-perhatian__isi">Semua upload sudah disetujui atau ditolak.</div>
        @endif
      </div>
    </div>
    <div class="rov-perhatian__kartu">
      <iconify-icon icon="solar:calendar-minimalistic-bold" class="{{ $ringkas['pt_segera'] === [] ? 'text-success-main' : 'text-warning-main' }}"></iconify-icon>
      <div>
        <div class="rov-perhatian__judul">
          {{ $ringkas['pt_segera'] === [] ? 'Tidak ada roster yang habis dalam '.$ringkas['segera_hari'].' hari' : count($ringkas['pt_segera']).' roster habis dalam '.$ringkas['segera_hari'].' hari' }}
        </div>
        @if ($ringkas['pt_segera'] !== [])
          <div>@foreach ($ringkas['pt_segera'] as $nama)<span class="rov-chip">{{ $nama }}</span>@endforeach</div>
          <div class="rov-perhatian__isi">Minta perusahaan ini meng-upload roster periode berikutnya.</div>
        @else
          <div class="rov-perhatian__isi">Roster yang berlaku masih cukup panjang.</div>
        @endif
      </div>
    </div>
  </div>

  {{-- Daftar perusahaan --}}
  <div class="rov-kartu">
    <div class="rov-kartu__kepala">
      <div>
        <div class="rov-kartu__judul">Roster per perusahaan</div>
        <p class="rov-kartu__sub">Roster yang ditolak tidak dihitung. Klik <b>Lihat jadwal</b> untuk membuka jadwal di bawah barisnya.</p>
      </div>
      <div class="rov-legenda" aria-label="Keterangan garis waktu">
        <span><i style="background:#6366F1"></i>Berlaku</span>
        <span><i style="background:#A5B4FC"></i>Belum mulai</span>
        <span><i style="background:#CBD5E1"></i>Berakhir</span>
        @if ($ringkas['hari_ini_persen'] !== null)<span><i class="is-garis"></i>Hari ini</span>@endif
      </div>
    </div>
    <div class="table-responsive">
      <table class="table ro-pt-tabel">
        <thead>
          <tr>
            <th class="ps-24">Perusahaan</th>
            <th>Karyawan</th>
            <th>Roster</th>
            <th style="min-width:220px">
              Periode jadwal
              <div class="rov-sumbu"><span>{{ $ringkas['rentang_awal']->format('d/m/y') }}</span><span>{{ $ringkas['rentang_akhir']->format('d/m/y') }}</span></div>
            </th>
            <th>Status</th>
            <th class="pe-24 text-end">Jadwal</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($perusahaan as $i => $p)
            @php $dibuka = $buka === $p['perusahaan']; $idPanel = 'roPt'.$i; @endphp
            <tr class="{{ $dibuka ? 'is-buka' : '' }}" data-ro-baris="{{ $idPanel }}">
              <td class="ps-24">
                <div class="ro-pt-nama">{{ $p['perusahaan'] }}</div>
                <div class="ro-pt-site">{{ $p['site'] === [] ? '—' : implode(' · ', $p['site']) }}</div>
              </td>
              <td>
                <span class="rov-angka">{{ $angka($p['karyawan']) }}</span>
                <div class="rov-minibar" title="{{ $angka($p['karyawan']) }} karyawan"><span style="width: {{ $p['karyawan_persen'] }}%"></span></div>
              </td>
              <td>
                <div class="d-flex flex-column align-items-start gap-1">
                  @if ($p['disetujui'])<span class="rov-badge is-ok"><iconify-icon icon="solar:check-circle-linear"></iconify-icon>{{ $p['disetujui'] }} disetujui</span>@endif
                  @if ($p['menunggu'])<span class="rov-badge is-tunggu"><iconify-icon icon="solar:hourglass-linear"></iconify-icon>{{ $p['menunggu'] }} menunggu</span>@endif
                </div>
              </td>
              <td>
                <div class="rov-waktu" title="{{ $fmt($p['dari']) }} – {{ $fmt($p['sampai']) }}">
                  <span class="rov-waktu__bar is-{{ $p['status'] }}" style="left: {{ $p['bar_kiri'] }}%; width: {{ $p['bar_lebar'] }}%"></span>
                  @if ($ringkas['hari_ini_persen'] !== null)<span class="rov-waktu__kini" style="left: calc({{ $ringkas['hari_ini_persen'] }}% - 1px)"></span>@endif
                </div>
                <div class="rov-waktu__tgl"><span>{{ $fmt($p['dari']) }}</span><span>{{ $fmt($p['sampai']) }}</span></div>
              </td>
              <td>
                <span class="rov-status is-{{ $p['status'] }}"><iconify-icon icon="{{ $ikonStatus[$p['status']] }}"></iconify-icon>{{ $p['status_label'] }}</span>
                <div class="rov-upload">Upload terakhir {{ $p['terakhir'] ? $fmt($p['terakhir'], 'd/m/Y H:i') : '—' }}</div>
              </td>
              <td class="pe-24 text-end">
                <button class="ro-sc-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $idPanel }}"
                        aria-expanded="{{ $dibuka ? 'true' : 'false' }}" aria-controls="{{ $idPanel }}">
                  <iconify-icon icon="solar:alt-arrow-down-linear"></iconify-icon>
                  Lihat jadwal
                </button>
              </td>
            </tr>
            <tr class="ro-pt-detail">
              <td colspan="6">
                <div class="collapse {{ $dibuka ? 'show' : '' }}" id="{{ $idPanel }}"
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
  </div>
@endif
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
