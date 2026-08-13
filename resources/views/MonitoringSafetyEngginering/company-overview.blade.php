@extends('MonitoringSafetyEngginering.layouts.crm')

@section('title', 'Progress Penyelesaian Rekayasa — Overall Perusahaan')

@push('head')
@include('MonitoringSafetyEngginering.partials.crm-styles')
@endpush

@section('content')
@php
   $pctClass = static fn (int $progress): string => match (true) {
      $progress >= 100 => 'crm-pct--green',
      $progress >= 70 => 'crm-pct--amber',
      $progress >= 40 => 'crm-pct--orange',
      default => 'crm-pct--red',
   };
   $barClass = static fn (int $progress): string => match (true) {
      $progress >= 100 => 'mse-prog-bar--green',
      $progress >= 70 => 'mse-prog-bar--amber',
      $progress >= 40 => 'mse-prog-bar--orange',
      default => 'mse-prog-bar--red',
   };
   $statusDotClass = static fn (string $cls): string => match ($cls) {
      'mse-status--closed' => 'crm-status-dot--green',
      'mse-status--ontrack' => 'crm-status-dot--purple',
      'mse-status--acceleration' => 'crm-status-dot--orange',
      default => 'crm-status-dot--red',
   };
   $closedPct = ($totals['item'] ?? 0) > 0
      ? (int) round((($totals['item_closed'] ?? 0) / $totals['item']) * 100)
      : 0;
   $sumberIcons = [
      'Komitmen' => 'verified',
      'Di Luar Komitmen' => 'pending',
      'Safety Engineering' => 'health_and_safety',
      'Additional Safety Engineering' => 'add_moderator',
      'Rekomendasi Insiden' => 'report',
      'Arahan Manajemen' => 'corporate_fare',
      'Pelanggaran Golden Rules' => 'gavel',
   ];
   $kpiCards = [
      ['icon' => 'groups', 'label' => 'Total Mitra Kerja (DIC)', 'value' => number_format((int) $totals['mitra']), 'tone' => 'blue'],
      ['icon' => 'assignment', 'label' => 'Total Pengendalian Rekayasa', 'value' => number_format((int) $totals['item']), 'tone' => 'slate'],
      ['icon' => 'flag', 'label' => 'Total Plan (Unit/Kegiatan)', 'value' => number_format((int) $totals['plan']), 'tone' => 'indigo'],
      ['icon' => 'task_alt', 'label' => 'Total Done (Unit/Kegiatan)', 'value' => number_format((int) $totals['done']), 'tone' => 'green'],
      ['icon' => 'donut_large', 'label' => 'Overall Progress', 'value' => ((int) $totals['progress']).'%', 'tone' => 'teal'],
      ['icon' => 'pending_actions', 'label' => 'Total Gap / Belum Selesai', 'value' => number_format((int) $totals['gap']), 'tone' => 'rose'],
      ['icon' => 'event_busy', 'label' => 'Total Overdue', 'value' => number_format((int) $totals['overdue']), 'tone' => 'red'],
      ['icon' => 'verified_user', 'label' => 'Item Selesai 100%', 'value' => number_format((int) $totals['item_closed']).' / '.number_format((int) $totals['item']).' ('.$closedPct.'%)', 'tone' => 'emerald'],
   ];
@endphp

{{-- Header --}}
<div class="mse-ov-header mb-4">
   <div>
      <h1 class="mse-ov-title">1. Progress Penyelesaian Rekayasa – Overall Perusahaan</h1>
      <p class="mse-ov-sub">Monitoring realisasi Plan vs Done per mitra kerja &amp; sumber program</p>
   </div>
   <div class="mse-ov-header-meta">
      <form method="GET" action="{{ route('monitoring-safety-engineering.company-overview') }}" class="mse-ov-filters">
         <select name="company" class="crm-filter-select" onchange="this.form.submit()">
            @foreach($filterOptions['companies'] ?? [] as $key => $label)
            <option value="{{ $key }}" @selected($filters['company'] === (string) $key)>{{ $label }}</option>
            @endforeach
         </select>
         <select name="period_year" class="crm-filter-select" onchange="this.form.submit()">
            @foreach($filterOptions['period_years'] ?? [] as $year => $label)
            <option value="{{ $year }}" @selected((int) $filters['period_year'] === (int) $year)>{{ $label }}</option>
            @endforeach
         </select>
      </form>
      <div class="mse-ov-meta-text">
         <span><span class="material-symbols-outlined text-sm">calendar_month</span> Data per: {{ now()->translatedFormat('d F Y') }}</span>
         <span>Sumber: monitoring_safety_engineering_records</span>
      </div>
   </div>
</div>

{{-- KPI row --}}
<div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3 mb-4">
   @foreach($kpiCards as $card)
   <div class="mse-ov-kpi mse-ov-kpi--{{ $card['tone'] }}">
      <span class="mse-ov-kpi-icon">
         <span class="material-symbols-outlined">{{ $card['icon'] }}</span>
      </span>
      <p class="mse-ov-kpi-value">{{ $card['value'] }}</p>
      <p class="mse-ov-kpi-label">{{ $card['label'] }}</p>
   </div>
   @endforeach
</div>

{{-- Middle: sumber + mitra + analysis --}}
<div class="grid grid-cols-1 xl:grid-cols-12 gap-4 mb-4">
   <div class="crm-card xl:col-span-4">
      <p class="crm-card-title">Progress Berdasarkan Sumber Program</p>
      <div class="crm-data-table-wrap max-h-[28rem] overflow-y-auto">
         <table class="crm-data-table mse-ov-table">
            <thead class="sticky top-0 z-10">
               <tr>
                  <th>Sumber Program</th>
                  <th class="text-center">Item</th>
                  <th class="text-center">Plan</th>
                  <th class="text-center">Done</th>
                  <th style="min-width:7.5rem">Progress</th>
                  <th class="text-center">OV</th>
               </tr>
            </thead>
            <tbody>
               @foreach($sumberProgramRows as $row)
               <tr>
                  <td>
                     <span class="inline-flex items-center gap-1.5 font-medium">
                        <span class="material-symbols-outlined text-base text-[#1E3A8A]">{{ $sumberIcons[$row['label']] ?? 'folder' }}</span>
                        {{ $row['label'] }}
                     </span>
                  </td>
                  <td class="text-center">{{ (int) $row['item'] }}</td>
                  <td class="text-center">{{ number_format((int) $row['plan']) }}</td>
                  <td class="text-center">{{ number_format((int) $row['done']) }}</td>
                  <td>
                     <div class="mse-prog">
                        <div class="mse-prog-track">
                           <div class="mse-prog-bar {{ $barClass((int) $row['progress']) }}" style="width: {{ min(100, max(0, (int) $row['progress'])) }}%"></div>
                        </div>
                        <span class="mse-prog-pct">{{ (int) $row['progress'] }}%</span>
                     </div>
                  </td>
                  <td class="text-center font-bold {{ (int) $row['overdue'] > 0 ? 'text-[#DC2626]' : 'text-crm-muted' }}">{{ (int) $row['overdue'] }}</td>
               </tr>
               @endforeach
               <tr class="mse-ov-total-row">
                  <td class="font-bold text-[#1E3A8A]">TOTAL</td>
                  <td class="text-center font-bold">{{ number_format((int) $totals['item']) }}</td>
                  <td class="text-center font-bold">{{ number_format((int) $totals['plan']) }}</td>
                  <td class="text-center font-bold">{{ number_format((int) $totals['done']) }}</td>
                  <td class="font-bold text-[#1E3A8A]">{{ (int) $totals['progress'] }}%</td>
                  <td class="text-center font-bold text-[#DC2626]">{{ (int) $totals['overdue'] }}</td>
               </tr>
            </tbody>
         </table>
      </div>
   </div>

   <div class="crm-card xl:col-span-5">
      <p class="crm-card-title">Progress per Mitra Kerja / DIC</p>
      <div class="crm-data-table-wrap max-h-[28rem] overflow-y-auto">
         <table class="crm-data-table mse-ov-table">
            <thead class="sticky top-0 z-10">
               <tr>
                  <th>Mitra Kerja (DIC)</th>
                  <th class="text-center">Item</th>
                  <th class="text-center">Plan</th>
                  <th class="text-center">Done</th>
                  <th class="text-center">Progress</th>
                  <th class="text-center">Gap</th>
                  <th class="text-center">OV</th>
                  <th>Status</th>
               </tr>
            </thead>
            <tbody>
               @forelse($mitraRows as $row)
               <tr>
                  <td class="font-medium whitespace-nowrap">{{ $row['name'] }}</td>
                  <td class="text-center">{{ (int) $row['item'] }}</td>
                  <td class="text-center">{{ number_format((int) $row['plan']) }}</td>
                  <td class="text-center">{{ number_format((int) $row['done']) }}</td>
                  <td class="text-center">
                     <span class="crm-pct {{ $pctClass((int) $row['progress']) }}">{{ (int) $row['progress'] }}%</span>
                  </td>
                  <td class="text-center">{{ number_format((int) $row['gap']) }}</td>
                  <td class="text-center font-bold {{ (int) $row['overdue'] > 0 ? 'text-[#DC2626]' : 'text-crm-muted' }}">{{ (int) $row['overdue'] }}</td>
                  <td><span class="crm-status-dot {{ $statusDotClass($row['status']['class']) }}">{{ $row['status']['label'] }}</span></td>
               </tr>
               @empty
               <tr>
                  <td colspan="8" class="text-center py-8 text-crm-muted">Belum ada data mitra.</td>
               </tr>
               @endforelse
            </tbody>
         </table>
      </div>
   </div>

   <aside class="xl:col-span-3 space-y-4">
      <div class="crm-card overflow-hidden p-0">
         <div class="crm-sidebar-header" style="background:#1E3A8A">
            <span class="material-symbols-outlined text-sm mr-1 align-middle">analytics</span>
            Brief Analysis
         </div>
         <div class="crm-sidebar-body">
            @foreach($briefAnalysis as $section)
            @foreach($section['points'] as $pi => $point)
            <div class="crm-sidebar-point">
               <span class="crm-sidebar-point-num">{{ $pi + 1 }}</span>
               <span>{{ $point }}</span>
            </div>
            @endforeach
            @endforeach
         </div>
      </div>

      <div class="crm-card overflow-hidden p-0">
         <div class="crm-sidebar-header crm-sidebar-header--green">
            <span class="material-symbols-outlined text-sm mr-1 align-middle">task_alt</span>
            Perkuatan Dan Next To Do
         </div>
         <div class="crm-sidebar-body">
            @foreach($nextTodo as $ti => $todo)
            <div class="crm-todo-item">
               <span class="crm-todo-num">{{ $ti + 1 }}</span>
               <span>{{ $todo }}</span>
            </div>
            @endforeach
         </div>
      </div>
   </aside>
</div>

{{-- Bottom: trend + top overdue --}}
<div class="grid grid-cols-1 xl:grid-cols-12 gap-4 mb-4">
   <div class="crm-card xl:col-span-7">
      <p class="crm-card-title">Progress Overall (Trend Bulanan)</p>
      <p class="text-[11px] text-crm-muted mb-3 -mt-1">Realisasi vs Target · Januari — Desember {{ (int) $filters['period_year'] }}</p>
      <div class="mse-ov-chart-wrap">
         <canvas id="mseOvTrendChart"></canvas>
      </div>
      <div class="crm-legend mt-2">
         <span class="crm-legend-item"><span class="crm-legend-dot" style="background:#16A34A"></span>Realisasi (%)</span>
         <span class="crm-legend-item"><span class="crm-legend-dot" style="background:#3B82F6"></span>Target (%)</span>
      </div>
   </div>

   <div class="crm-card overflow-hidden p-0 xl:col-span-5">
      <div class="crm-sidebar-header" style="background:#DC2626">
         <span class="material-symbols-outlined text-sm mr-1 align-middle">priority_high</span>
         Top 5 Overdue Tertinggi
      </div>
      <div class="crm-data-table-wrap p-3">
         <table class="crm-data-table mse-ov-table">
            <thead>
               <tr>
                  <th class="w-8">No</th>
                  <th>Pengendalian Rekayasa</th>
                  <th class="text-center">Plan</th>
                  <th class="text-center">Done</th>
                  <th style="min-width:6.5rem">Progress</th>
                  <th class="text-center">OV</th>
               </tr>
            </thead>
            <tbody>
               @forelse($topOverdue as $index => $item)
               <tr>
                  <td class="text-crm-muted font-medium">{{ $index + 1 }}</td>
                  <td class="font-medium">{{ Str::limit($item['name'], 36) }}</td>
                  <td class="text-center">{{ number_format((int) $item['plan']) }}</td>
                  <td class="text-center">{{ number_format((int) $item['done']) }}</td>
                  <td>
                     <div class="mse-prog">
                        <div class="mse-prog-track">
                           <div class="mse-prog-bar {{ $barClass((int) $item['progress']) }}" style="width: {{ min(100, max(0, (int) $item['progress'])) }}%"></div>
                        </div>
                        <span class="mse-prog-pct">{{ (int) $item['progress'] }}%</span>
                     </div>
                  </td>
                  <td class="text-center font-bold text-[#DC2626]">{{ (int) $item['overdue'] }}</td>
               </tr>
               @empty
               <tr>
                  <td colspan="6" class="text-center py-8 text-crm-muted">Tidak ada item overdue.</td>
               </tr>
               @endforelse
            </tbody>
         </table>
      </div>
   </div>
</div>

<div class="mse-ov-footnote">
   <strong>Catatan:</strong>
   Plan = target komitmen / fase proses · Done = realisasi aktual ·
   Progress = Done ÷ Plan × 100% · Gap = Plan − Done ·
   Overdue = item belum selesai 100% dengan due date sudah lewat.
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script>
   document.addEventListener('DOMContentLoaded', function () {
      var trend = @json($trend);
      var el = document.getElementById('mseOvTrendChart');
      if (!el) return;

      Chart.defaults.font.family = "'Poppins', sans-serif";
      Chart.defaults.color = '#64748B';

      var ctx = el.getContext('2d');
      var gradient = ctx.createLinearGradient(0, 0, 0, 280);
      gradient.addColorStop(0, 'rgba(22, 163, 74, 0.22)');
      gradient.addColorStop(1, 'rgba(22, 163, 74, 0.02)');

      new Chart(el, {
         type: 'line',
         data: {
            labels: trend.labels || [],
            datasets: [
               {
                  label: 'Realisasi (%)',
                  data: trend.realisasi || [],
                  borderColor: '#16A34A',
                  backgroundColor: gradient,
                  borderWidth: 2.5,
                  fill: true,
                  tension: 0.35,
                  pointRadius: 3.5,
                  pointBackgroundColor: '#16A34A',
                  spanGaps: false,
               },
               {
                  label: 'Target (%)',
                  data: trend.target || [],
                  borderColor: '#3B82F6',
                  borderDash: [6, 4],
                  backgroundColor: 'transparent',
                  borderWidth: 2,
                  tension: 0.35,
                  pointRadius: 3,
                  pointBackgroundColor: '#3B82F6',
               },
            ],
         },
         options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
               legend: { display: false },
               tooltip: {
                  callbacks: {
                     label: function (c) {
                        return c.dataset.label + ': ' + (c.raw === null ? '-' : c.raw + '%');
                     },
                  },
               },
            },
            scales: {
               y: {
                  beginAtZero: true,
                  max: 100,
                  ticks: { callback: function (v) { return v + '%'; }, font: { size: 10 } },
                  grid: { color: 'rgba(15,23,42,0.05)' },
               },
               x: {
                  ticks: { font: { size: 10 }, maxRotation: 40 },
                  grid: { display: false },
               },
            },
         },
      });
   });
</script>
@endpush
@endsection
