@extends('BaseRule.layouts.app')

@section('title', 'Gap Perulangan')

@push('head')
@include('BaseRule.partials.styles')
<style>
   .gap-summary-table th:first-child,
   .gap-summary-table td:first-child {
      position: sticky;
      left: 0;
      z-index: 2;
      background: #f8fafc;
   }
   .gap-summary-table thead th:first-child {
      z-index: 3;
   }
   .gap-summary-table tbody td:first-child {
      background: #fff;
      font-weight: 600;
   }
   .gap-scroll-table {
      max-height: 22rem;
      overflow: auto;
   }
</style>
@endpush

@section('content')
@include('BaseRule.partials.page-header', [
   'title' => 'Gap Perulangan',
   'subtitle' => 'Monitoring gap batch terkini lintas program Daily Monitoring (diurutkan gap_count)',
   'breadcrumb' => 'Gap Perulangan',
])

@include('BaseRule.partials.filter-bar', [
   'actionRoute' => 'hsecm.gap-perulangan',
   'filters' => $filters,
   'filterOptions' => $filterOptions,
   'showCompany' => true,
   'showSearch' => false,
   'showDate' => true,
])

@if($periodLabel ?? null)
<div class="mb-6 flex flex-wrap items-center gap-2 text-sm text-on-surface-variant">
   <span class="material-symbols-outlined text-base text-primary">calendar_month</span>
   <span class="font-semibold text-on-background">Periode data:</span>
   <span>{{ $periodLabel }}</span>
   <span class="hsecm-badge ml-1">batch terkini</span>
</div>
@endif

@include('BaseRule.gap-perulangan.partials._summary', ['summary' => $summary])

<div class="space-y-8">
   @foreach($sections as $section)
      @include('BaseRule.gap-perulangan.partials._section', ['section' => $section])
   @endforeach
</div>
@endsection

@push('scripts')
@php
   $chartPayload = [];
   foreach ($sections as $s) {
      $chartPayload[$s['key']] = [
         'title' => $s['chart_title'],
         'labels' => array_column($s['top_chart'], 'label'),
         'values' => array_column($s['top_chart'], 'value'),
      ];
   }
@endphp
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
   const charts = @json($chartPayload);

   Object.keys(charts).forEach(function (key) {
      const canvas = document.getElementById('gap-chart-' + key);
      if (!canvas || typeof Chart === 'undefined') return;

      const data = charts[key];
      if (!data.labels.length) return;

      new Chart(canvas, {
         type: 'bar',
         data: {
            labels: data.labels,
            datasets: [{
               label: data.title,
               data: data.values,
               backgroundColor: 'rgba(15, 118, 110, 0.75)',
               borderColor: '#0f766e',
               borderWidth: 1,
               borderRadius: 4,
               barThickness: 16,
            }],
         },
         options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
               legend: { display: false },
               tooltip: {
                  callbacks: {
                     label: function (ctx) {
                        return ' ' + ctx.parsed.x + ' perulangan';
                     }
                  }
               }
            },
            scales: {
               x: {
                  beginAtZero: true,
                  ticks: { precision: 0, font: { size: 10 } },
                  grid: { color: 'rgba(148, 163, 184, 0.2)' },
               },
               y: {
                  ticks: { font: { size: 10 } },
                  grid: { display: false },
               },
            },
         },
      });
   });
});
</script>
@endpush
