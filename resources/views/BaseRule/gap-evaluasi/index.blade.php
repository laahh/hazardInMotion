@extends('BaseRule.layouts.app')

@section('title', 'Gap Evaluasi')

@push('head')
@include('BaseRule.partials.styles')
<style>
   .gap-eval-card { min-height: 6.5rem; }
   .gap-eval-table { max-height: 22rem; overflow: auto; }
   .gap-eval-kpi {
      background: linear-gradient(145deg, #0f766e 0%, #134e4a 100%);
      color: #fff;
      border: none;
      box-shadow: 0 8px 24px -8px rgba(15, 118, 110, 0.45);
   }
   .gap-eval-kpi .gap-eval-kpi-sub {
      border-top: 1px solid rgba(255, 255, 255, 0.18);
      margin-top: 0.75rem;
      padding-top: 0.65rem;
   }
   .gap-summary-table th:first-child,
   .gap-summary-table td:first-child {
      position: sticky;
      left: 0;
      z-index: 2;
      background: #f8fafc;
   }
   .gap-summary-table thead th:first-child { z-index: 3; }
   .gap-summary-table tbody td:first-child {
      background: #fff;
      font-weight: 600;
   }
   .gap-summary-table tbody tr.gap-program-row {
      cursor: pointer;
   }
   .gap-summary-table tbody tr.gap-program-row.is-active td:first-child {
      background: rgba(15, 118, 110, 0.08);
      color: #0f766e;
   }
   .gap-summary-table tbody tr.gap-program-row.is-active td {
      background: rgba(15, 118, 110, 0.04);
   }
   .gap-program-strip {
      scroll-margin-top: 5rem;
      transition: box-shadow 0.2s ease, border-color 0.2s ease;
   }
   .gap-program-strip.is-active {
      border-color: rgba(15, 118, 110, 0.35);
      box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.12);
   }
</style>
@endpush

@section('content')
@include('BaseRule.partials.page-header', [
   'title' => 'Gap Evaluasi',
   'subtitle' => 'Ringkasan gap, perulangan, dan perbaikan per parameter Identifikasi & Intervensi',
   'breadcrumb' => 'Gap Evaluasi',
])

@include('BaseRule.partials.filter-bar', [
   'actionRoute' => 'hsecm.gap-evaluasi',
   'filters' => $filters,
   'filterOptions' => $filterOptions,
   'showCompany' => true,
   'showSearch' => false,
   'showDate' => true,
])

@if($periodLabel ?? null)
<div class="mb-6 flex flex-wrap items-center gap-2 text-sm text-on-surface-variant">
   <span class="material-symbols-outlined text-base text-primary">compare_arrows</span>
   <span class="font-semibold text-on-background">Periode:</span>
   <span>{{ $periodLabel }}</span>
   @if(!empty($slotsD))
      <span class="hsecm-badge ml-1">{{ count($slotsD) }} slot hari evaluasi</span>
   @endif
   @if(!empty($slotsPrev))
      <span class="hsecm-badge">{{ count($slotsPrev) }} slot hari pembanding</span>
   @endif
</div>
@endif

@include('BaseRule.gap-evaluasi.partials._overview-cards', ['overview' => $overview ?? []])

@include('BaseRule.gap-evaluasi.partials._summary-matrix', ['summary' => $summary ?? []])

<div class="space-y-4 mb-10" id="gap-program-sections">
   @foreach($programs ?? [] as $program)
      @include('BaseRule.gap-evaluasi.partials._section', ['program' => $program])
   @endforeach
</div>

<details class="mb-8 group">
   <summary class="cursor-pointer list-none flex items-center gap-2 text-sm font-semibold text-on-background mb-4">
      <span class="material-symbols-outlined text-primary transition group-open:rotate-90">chevron_right</span>
      Detail klasifikasi scrape & tasklist
   </summary>

   @include('BaseRule.gap-evaluasi.partials._cards-scrape', ['scrape' => $scrape])
   @include('BaseRule.gap-evaluasi.partials._cards-tasklist', ['tasklist' => $tasklist])

   <div class="space-y-8 mt-6">
      @include('BaseRule.gap-evaluasi.partials._table', [
         'title' => 'Tidak ada perbaikan — masih berulang (tetap)',
         'hint' => 'Ada di hari pembanding dan masih muncul di hari evaluasi',
         'rows' => $scrape['details']['tetap'] ?? [],
         'truncated' => (int) ($scrape['truncated']['tetap'] ?? 0),
         'tone' => 'danger',
      ])
      @include('BaseRule.gap-evaluasi.partials._table', [
         'title' => 'Perbaikan scrape (hilang)',
         'hint' => 'Ada di hari pembanding, tidak muncul lagi di hari evaluasi',
         'rows' => $scrape['details']['hilang'] ?? [],
         'truncated' => (int) ($scrape['truncated']['hilang'] ?? 0),
         'tone' => 'success',
      ])
      @include('BaseRule.gap-evaluasi.partials._table', [
         'title' => 'Gap baru',
         'hint' => 'Muncul di hari evaluasi, belum ada di hari pembanding',
         'rows' => $scrape['details']['baru'] ?? [],
         'truncated' => (int) ($scrape['truncated']['baru'] ?? 0),
         'tone' => 'warning',
      ])
      @include('BaseRule.gap-evaluasi.partials._table', [
         'title' => 'Kembali muncul (re-open)',
         'hint' => 'Muncul lagi setelah pernah absen / improve sebelumnya',
         'rows' => $scrape['details']['kembali'] ?? [],
         'truncated' => (int) ($scrape['truncated']['kembali'] ?? 0),
         'tone' => 'warning',
      ])

      @include('BaseRule.gap-evaluasi.partials._table-tasklist', [
         'title' => 'Ditindaklanjuti + perbaikan scrape',
         'hint' => 'Sudah submit/ACC tasklist dan key tidak ada di scrape hari evaluasi',
         'rows' => $tasklist['details']['tindaklanjut_berhasil'] ?? [],
         'tone' => 'success',
      ])
      @include('BaseRule.gap-evaluasi.partials._table-tasklist', [
         'title' => 'Ditindaklanjuti + masih gap',
         'hint' => 'Sudah submit/ACC tetapi key masih muncul di scrape hari evaluasi',
         'rows' => $tasklist['details']['tindaklanjut_belum_efektif'] ?? [],
         'tone' => 'danger',
      ])
      @include('BaseRule.gap-evaluasi.partials._table-tasklist', [
         'title' => 'Belum ditindaklanjuti + masih gap',
         'hint' => 'Item tasklist masih open/rejected dan gap masih ada di scrape',
         'rows' => $tasklist['details']['belum_tindaklanjut_masih_gap'] ?? [],
         'tone' => 'warning',
      ])
   </div>
</details>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
   const rows = document.querySelectorAll('[data-gap-program-key]');
   const strips = document.querySelectorAll('.gap-program-strip');

   function activate(key) {
      rows.forEach(function (row) {
         row.classList.toggle('is-active', row.getAttribute('data-gap-program-key') === key);
      });
      strips.forEach(function (strip) {
         const match = strip.getAttribute('data-program-key') === key;
         strip.classList.toggle('is-active', match);
      });
      const target = document.getElementById('gap-program-' + key);
      if (target) {
         target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
   }

   rows.forEach(function (row) {
      row.addEventListener('click', function () {
         activate(row.getAttribute('data-gap-program-key'));
      });
      row.addEventListener('keydown', function (e) {
         if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            activate(row.getAttribute('data-gap-program-key'));
         }
      });
   });

   if (rows.length) {
      rows[0].classList.add('is-active');
      const firstKey = rows[0].getAttribute('data-gap-program-key');
      strips.forEach(function (strip) {
         strip.classList.toggle('is-active', strip.getAttribute('data-program-key') === firstKey);
      });
   }
});
</script>
@endpush
