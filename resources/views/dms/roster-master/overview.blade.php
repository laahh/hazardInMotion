@extends('dms.layouts.app')

@section('title', 'Overview Roster')

@section('css')
{{-- Tombol "Lihat …" & panel collapse sama dengan tabel Scorecard Ringkasan Roster. --}}
<link rel="stylesheet" href="{{ asset('dms-assets/roster-compliance/roster-overview.css') }}?v=18">
@include('dms.roster-master._grid-css')
@include('dms.roster-master._ui-css')
<style>
  /* ── KPI ── */

  /* ── Perlu perhatian ── */
  .rov-perhatian { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
  .rov-perhatian__kartu { background: #fff; border: 1px solid #EEF2F7; border-radius: 12px; padding: 14px 18px; display: flex; gap: 12px; align-items: flex-start; }
  .rov-perhatian__kartu > iconify-icon { font-size: 20px; flex: none; margin-top: 1px; }
  .rov-kpi > *, .rov-perhatian > *, .rov-perhatian__kartu > div { min-width: 0; }
  .rov-chip { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .rov-perhatian__judul { font-size: 13px; font-weight: 600; color: #0F172A; }
  .rov-perhatian__isi { font-size: 12px; color: #64748B; margin-top: 2px; }

  /* ── Daftar perusahaan ── */
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

  /* ── Cakupan upload (perusahaan & site: sudah / menunggu / belum) ── */
  .rov-cak-kpi { padding: 20px 24px 4px; margin-bottom: 0; }
  .rov-cak-kpi .rov-kpi__kartu { box-shadow: none; }
  /* Batang comply rate: hijau disetujui, oranye menunggu, celah 2px. */
  .rov-cak-rate { display: flex; gap: 2px; height: 6px; border-radius: 3px; background: #F1F5F9; margin-top: 8px; overflow: hidden; }
  .rov-cak-rate > span { display: block; height: 100%; border-radius: 3px; }
  .rov-cak-rate .is-sudah { background: #10B981; }
  .rov-cak-rate .is-menunggu { background: #F59E0B; }
  .rov-cak-saring i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; flex: none; }
  .rov-cak-saring .is-sudah { background: #10B981; }
  .rov-cak-saring .is-menunggu { background: #F59E0B; }
  .rov-cak-saring .is-belum { background: #CBD5E1; }
  .rov-cak-saring .is-sebagian { background: #3B82F6; }
  .rov-cak-status.is-sebagian { background: #EFF6FF; color: #1D4ED8; }

  .rov-cak-alat { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 24px 16px; }
  .rov-cak-saring { display: inline-flex; flex-wrap: wrap; gap: 6px; }
  .rov-cak-saring button { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #E2E8F0; background: #fff; color: #475569; border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 600; }
  .rov-cak-saring button b { color: #0F172A; font-variant-numeric: tabular-nums; }
  .rov-cak-saring button.is-aktif { border-color: #6366F1; background: #EEF2FF; color: #4338CA; }
  .rov-cak-saring button:focus-visible { outline: 2px solid #6366F1; outline-offset: 2px; }
  .rov-cak-filter { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
  .rov-cak-filter .form-select { width: 220px; }
  .rov-cak-cari { width: 220px; }
  .rov-cak-konteks { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; background: #EEF2FF; color: #4338CA; font-size: 12px; font-weight: 600; }
  .rov-cak-gulir { max-height: 560px; overflow: auto; border-top: 1px solid #EEF2F7; }
  .rov-cak-gulir thead th { position: sticky; top: 0; z-index: 1; }
  /* Chip site tidak boleh menumpuk satu-satu ke bawah di layar sempit. */
  .rov-cak-gulir td:nth-child(5) { min-width: 280px; }

  .rov-cak-status { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
  .rov-cak-status iconify-icon { font-size: 14px; }
  .rov-cak-status.is-sudah, .rov-cak-chip.is-sudah { background: #ECFDF5; color: #047857; }
  .rov-cak-status.is-menunggu, .rov-cak-chip.is-menunggu { background: #FFF7ED; color: #C2410C; }
  .rov-cak-status.is-belum, .rov-cak-chip.is-belum { background: #F1F5F9; color: #64748B; }
  .rov-cak-jumlah { font-size: 12px; font-weight: 600; color: #0F172A; font-variant-numeric: tabular-nums; }
  .rov-cak-situs { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
  .rov-cak-chip { display: inline-flex; align-items: center; gap: 3px; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
  .rov-cak-chip iconify-icon { font-size: 12px; }
  .rov-cak-kurang-angka { font-weight: 700; color: #DC2626; font-variant-numeric: tabular-nums; }
  .rov-cak-lengkap { display: inline-flex; align-items: center; gap: 4px; font-weight: 700; color: #047857; }
  .rov-cak-tab { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #E2E8F0; background: #fff; color: #475569; border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 600; }
  .rov-cak-tab b { color: #0F172A; }
  .rov-cak-tab.is-aktif { border-color: #6366F1; background: #EEF2FF; color: #4338CA; }
  .rov-cak-tidakcocok { display: flex; gap: 8px; padding: 12px 24px 16px; border-top: 1px solid #EEF2F7; font-size: 12px; color: #64748B; }
  .rov-cak-tidakcocok > iconify-icon { font-size: 16px; flex: none; margin-top: 1px; }

  @media (max-width: 767.98px) {
    .rov-perhatian { grid-template-columns: 1fr; }
    .rov-cak-kpi, .rov-cak-alat { padding-left: 16px; padding-right: 16px; }
    .rov-cak-filter, .rov-cak-filter .form-select, .rov-cak-cari { width: 100%; }
  }
  @media (max-width: 575.98px) {
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
    <p class="text-sm text-secondary-light mb-0 mt-4">Cakupan &amp; status roster kerja yang sudah di-upload per perusahaan, per {{ now()->locale('id')->translatedFormat('d F Y') }}.</p>
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

@if ($cakupan !== null)
  @include('dms.roster-master._cakupan', ['cakupan' => $cakupan])
@endif

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
                     data-ro-url="{{ route('dms.master-roster.overview.jadwal', ['pt' => $p['perusahaan']], absolute: false) }}">
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
// Cakupan upload: saring status (Semua/Belum/Menunggu/Sudah), perusahaan, site,
// dan cari. Bila satu site dipilih, status tiap perusahaan = status site itu dan
// angka perbandingan di atas dihitung ulang hanya untuk site tersebut.
(function () {
    'use strict';

    var kartu = document.getElementById('roCakupan');
    if (!kartu) { return; }

    var STATUS = ['sudah', 'sebagian', 'menunggu', 'belum'];
    var tombol = kartu.querySelectorAll('[data-cak-status]');
    var cari = kartu.querySelector('[data-cak-cari]');
    var pilihPt = kartu.querySelector('[data-cak-pt]');
    var pilihSite = kartu.querySelector('[data-cak-site]');
    var reset = kartu.querySelector('[data-cak-reset]');
    var konteks = kartu.querySelector('[data-cak-konteks]');
    var kosong = kartu.querySelector('[data-cak-kosong]');
    var baris = Array.prototype.map.call(kartu.querySelectorAll('[data-cak-baris]'), function (tr) {
        return {
            tr: tr,
            nama: tr.dataset.nama,
            status: tr.dataset.status,
            karyawan: parseInt(tr.dataset.karyawan, 10) || 0,
            site: JSON.parse(tr.dataset.site || '[]'),
            cari: tr.dataset.cari,
            chip: tr.querySelectorAll('[data-cak-chip]'),
            jumlah: tr.querySelector('[data-cak-jumlah]'),
            badge: tr.querySelector('.rov-cak-status'),
            pasti: tr.dataset.kurangPasti === '1',
            // Isi asli sel angka (semua site) — dikembalikan saat filter site dilepas.
            sel: ['karyawan', 'kurang', 'roster'].reduce(function (o, k) {
                var td = tr.querySelector('[data-cak-sel="' + k + '"]');
                o[k] = { td: td, asli: td.innerHTML };
                return o;
            }, {}),
        };
    });

    // Angka baris untuk satu site: karyawan aktif, kurang, dan di roster site itu.
    function isiAngkaSite(b, s) {
        if (!s) {
            ['karyawan', 'kurang', 'roster'].forEach(function (k) { b.sel[k].td.innerHTML = b.sel[k].asli; });
            return;
        }
        b.sel.karyawan.td.innerHTML = '<span class="rov-angka">' + angka(s[2]) + '</span>';
        b.sel.kurang.td.innerHTML = s[4] === 0
            ? '<span class="rov-cak-lengkap" title="Semua karyawan aktif site ini sudah ada di roster"><iconify-icon icon="solar:check-circle-bold"></iconify-icon>0</span>'
            : '<span class="rov-cak-kurang-angka">' + (b.pasti ? '' : '≈ ') + angka(s[4]) + '</span>';
        b.sel.roster.td.innerHTML = s[3] > 0 ? '<span class="rov-angka">' + angka(s[3]) + '</span>' : '<span class="ro-pt-site">—</span>';
    }
    var LABEL = { sudah: 'Sudah', sebagian: 'Sudah, belum semua karyawan', menunggu: 'Menunggu approval', belum: 'Belum upload' };
    var IKON = { sudah: 'solar:check-circle-bold', sebagian: 'solar:pie-chart-2-bold', menunggu: 'solar:hourglass-bold', belum: 'solar:minus-circle-linear' };

    // Badge status baris = status efektif (status site itu bila satu site dipilih).
    function isiBadge(b, efektif, site) {
        b.badge.className = 'rov-cak-status is-' + efektif;
        b.badge.innerHTML = '<iconify-icon icon="' + IKON[efektif] + '"></iconify-icon>' + LABEL[efektif];
        b.badge.title = site === '' ? '' : 'Status roster untuk site ' + site;
    }
    var status = '';

    function angka(n) { return n.toLocaleString('id-ID'); }
    function persen(n, t) { return t > 0 ? (n / t * 100).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) : '0'; }
    function nol() { return { sudah: 0, sebagian: 0, menunggu: 0, belum: 0 }; }

    var nKurang = 0;
    function isiKpi(nPt, nSite, nKar) {
        var kpi = kartu.querySelector('[data-cak-kpi]');
        var disetujui = nPt.sudah + nPt.sebagian;
        var total = disetujui + nPt.menunggu + nPt.belum;
        var set = function (k, v) { kpi.querySelector('[data-cak-n="' + k + '"]').textContent = v; };
        set('total', angka(total));
        set('kar_total', angka(nKar.sudah + nKar.menunggu + nKar.belum));
        set('upload', angka(disetujui + nPt.menunggu));
        set('disetujui', angka(disetujui));
        set('menunggu', angka(nPt.menunggu));
        set('belum', angka(nPt.belum));
        set('kurang', angka(nKurang));
        set('rate', persen(disetujui, total));
        kpi.querySelector('[data-cak-seg="sudah"]').style.width = (total > 0 ? disetujui / total * 100 : 0) + '%';
        kpi.querySelector('[data-cak-seg="menunggu"]').style.width = (total > 0 ? nPt.menunggu / total * 100 : 0) + '%';
    }

    function terapkan() {
        var q = cari.value.trim().toLowerCase();
        var pt = pilihPt.value;
        var site = pilihSite.value;
        var nPt = nol(), nSite = nol(), nKar = nol(), tampil = 0;
        nKurang = 0;

        baris.forEach(function (b) {
            // Status & karyawan efektif: per site terpilih, atau per perusahaan.
            var efektif = b.status, karyawan = b.karyawan, situs = b.site, kurang = parseInt(b.tr.dataset.kurang, 10) || 0, siteIni = null;
            var dasar = (pt === '' || b.nama === pt) && (q === '' || b.cari.indexOf(q) !== -1);
            if (dasar && site !== '') {
                var s = b.site.filter(function (x) { return x[0] === site; })[0];
                dasar = !!s;
                if (s) {
                    // Roster site ini disetujui tapi masih ada karyawan aktif site ini yang belum terjadwal.
                    efektif = s[1] === 'sudah' && s[4] > 0 ? 'sebagian' : s[1];
                    karyawan = s[2]; situs = [s]; kurang = s[4]; siteIni = s;
                }
            }
            if (dasar) {
                nKurang += kurang;
                nPt[efektif]++;
                nKar[efektif] += karyawan;
                situs.forEach(function (x) { nSite[x[1]]++; });
            }
            var lihat = dasar && (status === '' || efektif === status);
            b.tr.classList.toggle('d-none', !lihat);
            if (lihat) { tampil++; }

            if (lihat) { isiBadge(b, efektif, site); isiAngkaSite(b, siteIni); }
            b.chip.forEach(function (c) { c.classList.toggle('d-none', site !== '' && c.dataset.cakChip !== site); });
            if (b.jumlah) { b.jumlah.classList.toggle('d-none', site !== ''); }
        });

        isiKpi(nPt, nSite, nKar);
        STATUS.forEach(function (s) { kartu.querySelector('[data-cak-hitung="' + s + '"]').textContent = angka(nPt[s]); });
        kartu.querySelector('[data-cak-hitung=""]').textContent = angka(nPt.sudah + nPt.sebagian + nPt.menunggu + nPt.belum);
        kosong.classList.toggle('d-none', tampil > 0);

        var label = [];
        if (pt !== '') { label.push(pt); }
        if (site !== '') { label.push('Site ' + site); }
        konteks.textContent = label.length ? 'Menampilkan: ' + label.join(' · ') : '';
        konteks.classList.toggle('d-none', label.length === 0);
        reset.classList.toggle('d-none', label.length === 0 && q === '' && status === '');
    }

    tombol.forEach(function (b) {
        b.addEventListener('click', function () {
            status = b.dataset.cakStatus;
            tombol.forEach(function (x) { x.classList.toggle('is-aktif', x === b); });
            terapkan();
        });
    });
    cari.addEventListener('input', terapkan);
    pilihPt.addEventListener('change', terapkan);
    pilihSite.addEventListener('change', terapkan);
    reset.addEventListener('click', function () {
        status = '';
        cari.value = '';
        pilihPt.value = '';
        pilihSite.value = '';
        tombol.forEach(function (x) { x.classList.toggle('is-aktif', x.dataset.cakStatus === ''); });
        terapkan();
    });
})();
</script>
<script>
// Modal "Kurang": karyawan aktif yang belum / sudah di roster untuk satu
// perusahaan. Tab, bulan, cari, dan halaman dimuat ulang di modal yang sama.
(function () {
    'use strict';

    var modalEl = document.getElementById('roCakModal');
    if (!modalEl || typeof bootstrap === 'undefined') { return; }
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var isi = modalEl.querySelector('[data-cak-modal-isi]');
    var judul = modalEl.querySelector('#roCakModalJudul');
    var minta = 0;

    function muat(url) {
        var no = ++minta;
        isi.style.opacity = isi.querySelector('.rm-xl') ? '.55' : '';
        if (!isi.querySelector('.rm-xl')) { isi.innerHTML = '<div class="ro-memuat">Memuat…</div>'; }
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.text(); })
            .then(function (html) { if (no === minta) { isi.innerHTML = html; } })
            .catch(function () {
                if (no !== minta) { return; }
                isi.innerHTML = '<div class="ro-memuat text-danger-600">Gagal memuat. <a href="#" class="text-primary-600 fw-semibold" data-ulang>Coba lagi</a></div>';
                isi.querySelector('[data-ulang]').addEventListener('click', function (e) { e.preventDefault(); muat(url); });
            })
            .finally(function () { isi.style.opacity = ''; });
    }

    isi.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-ro-muat]');
        if (!a) { return; }
        e.preventDefault();
        if (!a.classList.contains('disabled')) { muat(a.href); }
    });
    isi.addEventListener('submit', function (e) {
        var form = e.target.closest('form[data-ro-filter]');
        if (!form) { return; }
        e.preventDefault();
        muat(form.action + '?' + new URLSearchParams(new FormData(form)).toString());
    });

    document.querySelectorAll('[data-cak-kurang]').forEach(function (b) {
        b.addEventListener('click', function () {
            var pt = b.dataset.cakKurang;
            judul.textContent = 'Karyawan aktif vs roster — ' + pt;
            isi.innerHTML = '<div class="ro-memuat">Memuat…</div>';
            modal.show();
            muat(modalEl.dataset.url + '?' + new URLSearchParams({ pt: pt, tampil: b.dataset.cakTab || 'belum' }).toString());
        });
    });
})();
</script>
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
