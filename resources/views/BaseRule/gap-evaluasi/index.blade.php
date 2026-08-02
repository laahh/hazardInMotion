@extends('BaseRule.layouts.app')

@section('title', 'Gap Evaluasi')

@push('head')
@include('BaseRule.partials.styles')
<style>
   .gap-eval-card { min-height: 6.5rem; }
   .gap-eval-table { max-height: 18rem; overflow: auto; }
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
   .gap-eval-cell { line-height: 1.15; min-width: 3.25rem; }
   .gap-eval-cell .lbl { font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; }
</style>
@endpush

@section('content')
@include('BaseRule.partials.page-header', [
   'title' => 'Gap Evaluasi',
   'subtitle' => 'Matriks perbaikan per program × site/mitra (scrape D vs D−1) + efektivitas pasca tasklist',
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

<p class="mb-4 text-xs text-on-surface-variant">
   <strong>Tanggal Dari</strong> = hari evaluasi (D). Sel matriks scrape: <strong>sudah</strong> (hilang di D) | <strong>belum</strong> (tetap di D).
   Matriks tasklist: <strong>efektif</strong> (submit/ACC + scrape clear) | <strong>belum efektif</strong>.
</p>

@include('BaseRule.gap-evaluasi.partials._cards-scrape', ['scrape' => $scrape])
@include('BaseRule.gap-evaluasi.partials._cards-tasklist', ['tasklist' => $tasklist])

@include('BaseRule.gap-evaluasi.partials._summary-matrix', [
   'summary' => $summaryScrape,
   'title' => 'Ringkasan Evaluasi Scrape',
   'subtitle' => 'Per program · kolom Site → Mitra · sel: sudah diperbaiki | belum',
])

@include('BaseRule.gap-evaluasi.partials._summary-matrix', [
   'summary' => $summaryTasklist,
   'title' => 'Ringkasan Efektivitas Tasklist',
   'subtitle' => 'Per program · kolom Site → Mitra · sel: efektif | belum efektif (pasca submit/ACC)',
])

<div class="space-y-8 mt-8">
   @forelse($sections as $section)
      @include('BaseRule.gap-evaluasi.partials._section', ['section' => $section])
   @empty
   <div class="hsecm-card rounded-2xl p-8 text-center text-sm text-on-surface-variant">
      Belum ada detail program untuk periode/filter ini.
   </div>
   @endforelse
</div>
@endsection

@push('scripts')
@php
   $chartPayload = [];
   foreach ($sections as $s) {
      $chartPayload[$s['key']] = [
         'labels' => $s['chart_labels'] ?? [],
         'belum' => $s['chart_belum'] ?? [],
         'sudah' => $s['chart_sudah'] ?? [],
      ];
   }
@endphp
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
   const charts = @json($chartPayload);
   Object.keys(charts).forEach(function (key) {
      const canvas = document.getElementById('gap-eval-chart-' + key);
      if (!canvas || typeof Chart === 'undefined') return;
      const data = charts[key];
      if (!data.labels.length) return;
      new Chart(canvas, {
         type: 'bar',
         data: {
            labels: data.labels,
            datasets: [
               {
                  label: 'Belum',
                  data: data.belum,
                  backgroundColor: 'rgba(220, 38, 38, 0.75)',
                  borderColor: '#dc2626',
                  borderWidth: 1,
                  borderRadius: 4,
                  barThickness: 14,
               },
               {
                  label: 'Sudah',
                  data: data.sudah,
                  backgroundColor: 'rgba(5, 150, 105, 0.75)',
                  borderColor: '#059669',
                  borderWidth: 1,
                  borderRadius: 4,
                  barThickness: 14,
               },
            ],
         },
         options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
            scales: {
               x: { ticks: { font: { size: 10 } }, grid: { display: false } },
               y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } },
            },
         },
      });
   });
});
</script>
@endpush
