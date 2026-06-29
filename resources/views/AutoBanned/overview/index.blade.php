@extends('AutoBanned.layouts.app')

@section('title', 'Overview Monitoring Banned & Un Banned')

@section('page-header')
@endsection

@push('head')
@include('AutoBanned.partials.phppot-dashboard-styles')
<style>
   .ab-overview-split {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
   }
   @media (min-width: 1200px) {
      .ab-overview-split { grid-template-columns: 1fr 1fr; align-items: start; }
   }
   .ab-overview-section {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 1px 20px 0 rgba(69, 90, 100, 0.08);
      padding: 18px 16px 16px;
      border-top: 4px solid #01b0c6;
   }
   .ab-overview-section:last-child { border-top-color: #3952bc; }
   .ab-overview-section-head {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 14px;
      padding-bottom: 12px;
      border-bottom: 1px solid #f1f1f1;
   }
   .ab-overview-section-title {
      font-size: 16px;
      font-weight: 700;
      margin: 0;
      color: #333;
   }
   .ab-overview-section-sub {
      font-size: 11px;
      color: #888;
      margin: 3px 0 0;
   }
   .ab-overview-detail-link {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 12px;
      font-weight: 600;
      color: #01b0c6;
      text-decoration: none;
   }
   .ab-overview-detail-link:hover { text-decoration: underline; }
   .ab-overview-kpi { margin-bottom: 12px !important; }
   .ab-overview-kpi .card-body { padding: 14px 16px !important; }
   .ab-overview-kpi h3.mb-0 { font-size: 22px !important; }
   .ab-overview-kpi h6.m-b-5 { font-size: 11px !important; }
   .ab-overview-widget { margin-bottom: 12px !important; }
   .ab-overview-summary {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
      margin-bottom: 20px;
   }
   @media (min-width: 768px) {
      .ab-overview-summary { grid-template-columns: repeat(3, 1fr); }
   }
   .ab-overview-summary-card {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 1px 20px 0 rgba(69, 90, 100, 0.08);
      border-top: 4px solid var(--summary-accent, #01b0c6);
   }
   .ab-overview-summary-body {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px 20px;
   }
   .ab-overview-summary-body h6 {
      margin: 0 0 6px;
      font-size: 12px;
      font-weight: 500;
      color: #888;
   }
   .ab-overview-summary-body h3 {
      margin: 0;
      font-size: 28px;
      font-weight: 600;
      color: #333;
      line-height: 1.2;
   }
   .ab-overview-summary-body .material-icons-two-tone {
      font-size: 40px;
   }
</style>
@endpush

@section('content')
@php
   $periodLabel = ($filters['filter_date'] ?? '') !== ''
      ? \Carbon\Carbon::parse($filters['filter_date'])->format('d M Y')
      : 'Semua Tanggal';
   $detailQuery = array_filter($filters ?? [], static fn ($v) => $v !== '' && $v !== null);

   $bannedChart = $banned['chartData'] ?? [];
   $unbanChart = $unban['chartData'] ?? [];
   $unbanTrend = $unbanChart['dailyTrend'] ?? ['approved' => []];
   $unbanPieLabels = $unbanChart['byStatus']['labels'] ?? [];
   $unbanPieValues = $unbanChart['byStatus']['values'] ?? [];
@endphp

<div class="ab-phppot -mt-1">
   <div class="page-top">
      <div>
         <h1>Overview Monitoring</h1>
         <p>Gabungan Monitoring Banned & Monitoring Un Banned &bull; {{ $periodLabel }}</p>
      </div>
      @include('AutoBanned.partials.overview-filter-bar', [
         'filters' => $filters,
         'filterOptions' => $filterOptions,
         'filterRoute' => 'auto-banned.index',
      ])
   </div>

   @include('AutoBanned.overview.partials.summary-cards', [
      'summaryStats' => $summaryStats ?? [],
   ])

   <div class="ab-overview-split">
      @include('AutoBanned.overview.partials.banned-panel', [
         'banned' => $banned,
         'detailQuery' => $detailQuery,
      ])
      @include('AutoBanned.overview.partials.unban-panel', [
         'unban' => $unban,
         'detailQuery' => $detailQuery,
      ])
   </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
   if (typeof ApexCharts === 'undefined') return;
   var CYAN = '#01b0c6';
   var BLUE = '#3952bc';
   var font = 'Poppins, Helvetica, Arial, sans-serif';

   var unbanSpark = @json($unbanTrend['approved'] ?? []);
   var unbanPieLabels = @json($unbanPieLabels);
   var unbanPieValues = @json($unbanPieValues);

   function renderSpark(selector, data, color) {
      var el = document.querySelector(selector);
      if (!el) return;
      new ApexCharts(el, {
         chart: { type: 'area', height: 70, sparkline: { enabled: true }, background: 'transparent' },
         stroke: { curve: 'smooth', width: 2 },
         fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
         colors: [color],
         series: [{ data: (data && data.length) ? data : [0, 0, 0, 0, 0] }],
         tooltip: { enabled: false }
      }).render();
   }

   function renderPie(selector, labels, values, color) {
      var el = document.querySelector(selector);
      if (!el) return;
      new ApexCharts(el, {
         chart: { type: 'pie', height: 140, background: 'transparent', fontFamily: font },
         labels: labels.length ? labels : ['No Data'],
         series: values.length ? values : [1],
         colors: labels.length ? undefined : ['#e0e0e0'],
         dataLabels: { enabled: true, style: { fontSize: '10px' } },
         legend: { show: false },
         theme: { monochrome: { enabled: labels.length > 0, color: color } }
      }).render();
   }

   renderSpark('#ab-overview-unban-spark', unbanSpark, BLUE);
   renderPie('#ab-overview-unban-pie', unbanPieLabels, unbanPieValues, BLUE);
})();
</script>
@endpush
