@extends('MonitoringSafetyEngginering.layouts.crm')

@section('title', 'Progress Penyelesaian Rekayasa — Overall Perusahaan')

@push('head')
@include('MonitoringSafetyEngginering.partials.crm-styles')
@endpush

@section('content')
@php
   $pctClass = static fn (int $progress): string => match (true) {
      $progress >= 100 => 'crm-pct--green',
      $progress >= 50 => 'crm-pct--amber',
      $progress > 0 => 'crm-pct--orange',
      default => 'crm-pct--red',
   };
   $statusDotClass = static fn (string $cls): string => match ($cls) {
      'mse-status--closed' => 'crm-status-dot--green',
      'mse-status--ontrack' => 'crm-status-dot--purple',
      'mse-status--acceleration' => 'crm-status-dot--orange',
      default => 'crm-status-dot--red',
   };
   $closedPct = $totals['item'] > 0 ? round(($totals['item_closed'] / $totals['item']) * 100) : 0;
   $avatarColors = ['#7366FF', '#51BB25', '#FFAA05', '#FF5B5B', '#3B97FF', '#9b93ff', '#65a30d'];
   $mitraPreview = collect($mitraRows)->take(5);
   $sumberChartLabels = collect($sumberProgramRows)->pluck('label')->map(fn ($l) => Str::limit($l, 18))->all();
   $sumberChartData = collect($sumberProgramRows)->pluck('item')->all();
@endphp

{{-- Filter Bar --}}
<form method="GET" action="{{ route('monitoring-safety-engineering.company-overview') }}" class="crm-filter-bar crm-filter-bar--single">
   <div class="crm-filter-field crm-filter-field--company-wide">
      <label class="crm-filter-label" for="mse-co-company">Perusahaan Inisiator</label>
      <select id="mse-co-company" name="company" class="crm-filter-select" onchange="this.form.submit()">
         @foreach($filterOptions['companies'] ?? [] as $key => $label)
         <option value="{{ $key }}" @selected($filters['company'] === (string) $key)>{{ $label }}</option>
         @endforeach
      </select>
   </div>
</form>

{{-- Row 1: KPI Stat Cards --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-3 mb-4">
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Mitra Kerja (DIC)</p>
      <p class="crm-stat-value">{{ $totals['mitra'] }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">groups</span>
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Total Pengendalian</p>
      <p class="crm-stat-value">{{ number_format($totals['item']) }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">arrow_upward</span>
         +{{ $closedPct }}%
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Total Plan</p>
      <p class="crm-stat-value">{{ number_format($totals['plan']) }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">flag</span>
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Total Done</p>
      <p class="crm-stat-value">{{ number_format($totals['done']) }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">arrow_upward</span>
         +{{ $totals['progress'] }}%
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Overall Progress</p>
      <p class="crm-stat-value">{{ $totals['progress'] }}%</p>
      <span class="crm-stat-trend {{ $totals['progress'] >= 50 ? 'crm-stat-trend--up' : 'crm-stat-trend--down' }}">
         <span class="material-symbols-outlined text-sm">{{ $totals['progress'] >= 50 ? 'arrow_upward' : 'arrow_downward' }}</span>
         {{ $totals['progress'] }}%
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Gap / Belum Selesai</p>
      <p class="crm-stat-value">{{ number_format($totals['gap']) }}</p>
      <span class="crm-stat-trend crm-stat-trend--down">
         <span class="material-symbols-outlined text-sm">pending_actions</span>
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Total Overdue</p>
      <p class="crm-stat-value">{{ number_format($totals['overdue']) }}</p>
      <span class="crm-stat-trend crm-stat-trend--down">
         <span class="material-symbols-outlined text-sm">arrow_downward</span>
         {{ $totals['overdue'] }}
      </span>
   </div>
</div>

{{-- Row 2: Sumber Program + Mitra Preview + Brief Analysis --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
   {{-- Sumber Program Table --}}
   <div class="crm-card">
      <p class="crm-card-title">Progress Berdasarkan Sumber Program</p>
      <div class="crm-data-table-wrap">
         <table class="crm-data-table">
            <thead>
               <tr>
                  <th>Sumber Program</th>
                  <th class="text-center">Item</th>
                  <th class="text-center">Progress</th>
                  <th class="text-center">Overdue</th>
               </tr>
            </thead>
            <tbody>
               @foreach($sumberProgramRows as $row)
               <tr>
                  <td class="font-medium">{{ $row['label'] }}</td>
                  <td class="text-center">{{ $row['item'] }}</td>
                  <td class="text-center"><span class="crm-pct {{ $pctClass($row['progress']) }}">{{ $row['progress'] }}%</span></td>
                  <td class="text-center font-bold {{ $row['overdue'] > 0 ? 'text-[#FF5B5B]' : 'text-crm-muted' }}">{{ $row['overdue'] }}</td>
               </tr>
               @endforeach
               <tr class="font-bold bg-[#F4F7F9]">
                  <td class="text-[#7366FF]">TOTAL</td>
                  <td class="text-center text-[#7366FF]">{{ $totals['item'] }}</td>
                  <td class="text-center text-[#7366FF]">{{ $totals['progress'] }}%</td>
                  <td class="text-center text-[#FF5B5B]">{{ $totals['overdue'] }}</td>
               </tr>
            </tbody>
         </table>
      </div>
   </div>

   {{-- Mitra Kerja Preview --}}
   <div class="crm-card">
      <p class="crm-card-title">Progress per Mitra Kerja (DIC)</p>
      <table class="crm-table">
         <thead>
            <tr>
               <th>Mitra Kerja</th>
               <th>Progress</th>
               <th>Status</th>
            </tr>
         </thead>
         <tbody>
            @foreach($mitraPreview as $index => $row)
            @php $initials = collect(explode(' ', $row['name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''); @endphp
            <tr>
               <td>
                  <div class="crm-table-name">
                     <span class="crm-table-avatar" style="background:{{ $avatarColors[$index % count($avatarColors)] }}">{{ $initials }}</span>
                     <span class="truncate max-w-[120px]" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                  </div>
               </td>
               <td><span class="crm-pct {{ $pctClass($row['progress']) }}">{{ $row['progress'] }}%</span></td>
               <td>
                  <span class="crm-status-dot {{ $statusDotClass($row['status']['class']) }}">{{ $row['status']['label'] }}</span>
               </td>
            </tr>
            @endforeach
         </tbody>
      </table>
      <p class="text-[10px] text-crm-muted mt-3 font-medium">+ {{ count($mitraRows) - count($mitraPreview) }} mitra lainnya di tabel lengkap</p>
   </div>

   {{-- Brief Analysis --}}
   <div class="crm-card overflow-hidden p-0">
      <div class="crm-sidebar-header crm-sidebar-header--purple">
         <span class="material-symbols-outlined text-sm mr-1 align-middle">analytics</span>
         Brief Analysis
      </div>
      <div class="crm-sidebar-body">
         @foreach($briefAnalysis as $section)
         <p class="font-bold text-sm text-[#7366FF] mb-2">{{ $section['title'] }}</p>
         @foreach($section['points'] as $pi => $point)
         <div class="crm-sidebar-point">
            <span class="crm-sidebar-point-num">{{ $pi + 1 }}</span>
            <span>{{ $point }}</span>
         </div>
         @endforeach
         @endforeach
         <p class="text-[10px] text-crm-muted mt-3 font-medium">Data per: {{ now()->translatedFormat('d F Y') }}</p>
      </div>
   </div>
</div>

{{-- Row 3: Donut Sumber + Trend Chart --}}
<div class="grid grid-cols-1 xl:grid-cols-[1fr_1.6fr] gap-4 mb-4">
   <div class="crm-card">
      <p class="crm-card-title">Distribusi Item per Sumber Program</p>
      <div class="crm-chart-wrap">
         <canvas id="crmSumberProgramChart"></canvas>
         <div class="crm-donut-center">
            <span class="crm-donut-total-label">Total</span>
            <span class="crm-donut-total-value">{{ $totals['item'] }}</span>
         </div>
      </div>
      <div class="crm-legend">
         @foreach(collect($sumberProgramRows)->take(4) as $i => $row)
         <span class="crm-legend-item">
            <span class="crm-legend-dot" style="background:{{ ['#7366FF','#CFC8FF','#51BB25','#FFAA05'][$i] ?? '#848488' }}"></span>
            {{ Str::limit($row['label'], 16) }}
         </span>
         @endforeach
      </div>
   </div>

   <div class="crm-card">
      <p class="crm-card-title">Progress Overall (Trend Bulanan)</p>
      <p class="text-[11px] text-crm-muted mb-3 -mt-2">Realisasi vs Target · Januari — Desember 2026</p>
      <div class="crm-chart-wrap" style="height:260px">
         <canvas id="crmCompanyTrendChart"></canvas>
      </div>
      <div class="crm-legend mt-2">
         <span class="crm-legend-item"><span class="crm-legend-dot" style="background:#7366FF"></span>Realisasi</span>
         <span class="crm-legend-item"><span class="crm-legend-dot" style="background:#CFC8FF"></span>Target</span>
      </div>
   </div>
</div>

{{-- Row 4: Full Mitra Table + Top Overdue --}}
<div class="grid grid-cols-1 xl:grid-cols-[1.3fr_1fr] gap-4 mb-4">
   <div class="crm-card">
      <p class="crm-card-title">Seluruh Mitra Kerja (DIC)</p>
      <div class="crm-data-table-wrap max-h-[28rem] overflow-y-auto">
         <table class="crm-data-table">
            <thead class="sticky top-0 z-10">
               <tr>
                  <th>Mitra Kerja</th>
                  <th class="text-center">Item</th>
                  <th class="text-center">Plan</th>
                  <th class="text-center">Done</th>
                  <th class="text-center">Progress</th>
                  <th class="text-center">Overdue</th>
                  <th>Status</th>
               </tr>
            </thead>
            <tbody>
               @foreach($mitraRows as $row)
               <tr>
                  <td class="font-medium whitespace-nowrap">{{ $row['name'] }}</td>
                  <td class="text-center">{{ $row['item'] }}</td>
                  <td class="text-center">{{ $row['plan'] }}</td>
                  <td class="text-center">{{ $row['done'] }}</td>
                  <td class="text-center"><span class="crm-pct {{ $pctClass($row['progress']) }}">{{ $row['progress'] }}%</span></td>
                  <td class="text-center font-bold {{ $row['overdue'] > 0 ? 'text-[#FF5B5B]' : 'text-crm-muted' }}">{{ $row['overdue'] }}</td>
                  <td><span class="crm-status-dot {{ $statusDotClass($row['status']['class']) }}">{{ $row['status']['label'] }}</span></td>
               </tr>
               @endforeach
            </tbody>
         </table>
      </div>
   </div>

   <div class="crm-card overflow-hidden p-0">
      <div class="crm-sidebar-header" style="background:#FF5B5B">
         <span class="material-symbols-outlined text-sm mr-1 align-middle">priority_high</span>
         Top 5 Overdue Tertinggi
      </div>
      <div class="crm-data-table-wrap p-3">
         <table class="crm-data-table">
            <thead>
               <tr>
                  <th class="w-8">No</th>
                  <th>Pengendalian Rekayasa</th>
                  <th class="text-center">Progress</th>
                  <th class="text-center">Overdue</th>
               </tr>
            </thead>
            <tbody>
               @foreach($topOverdue as $index => $item)
               <tr>
                  <td class="text-crm-muted font-medium">{{ $index + 1 }}</td>
                  <td class="font-medium">{{ Str::limit($item['name'], 32) }}</td>
                  <td class="text-center"><span class="crm-pct {{ $pctClass($item['progress']) }}">{{ $item['progress'] }}%</span></td>
                  <td class="text-center font-bold text-[#FF5B5B]">{{ $item['overdue'] }}</td>
               </tr>
               @endforeach
            </tbody>
         </table>
      </div>
   </div>
</div>

{{-- Next To Do --}}
<div class="crm-card overflow-hidden p-0">
   <div class="crm-sidebar-header crm-sidebar-header--green">
      <span class="material-symbols-outlined text-sm mr-1 align-middle">task_alt</span>
      Perkuatan dan Next To Do
   </div>
   <div class="crm-sidebar-body">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
         @foreach($nextTodo as $ti => $todo)
         <div class="crm-todo-item">
            <span class="crm-todo-num">{{ $ti + 1 }}</span>
            <span>{{ $todo }}</span>
         </div>
         @endforeach
      </div>
   </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script>
   document.addEventListener('DOMContentLoaded', function () {
      var trend = @json($trend);
      var sumberLabels = @json($sumberChartLabels);
      var sumberData = @json($sumberChartData);
      var crmPurple = '#7366FF';
      var crmColors = ['#7366FF', '#CFC8FF', '#51BB25', '#FFAA05', '#FF5B5B', '#3B97FF', '#848488'];

      Chart.defaults.font.family = "'Poppins', sans-serif";
      Chart.defaults.color = '#848488';
      Chart.defaults.animation.duration = 800;

      var sumberEl = document.getElementById('crmSumberProgramChart');
      if (sumberEl) {
         new Chart(sumberEl, {
            type: 'doughnut',
            data: {
               labels: sumberLabels,
               datasets: [{
                  data: sumberData,
                  backgroundColor: crmColors,
                  borderWidth: 0,
                  hoverOffset: 4
               }]
            },
            options: {
               responsive: true,
               maintainAspectRatio: false,
               cutout: '72%',
               plugins: { legend: { display: false } }
            }
         });
      }

      var trendEl = document.getElementById('crmCompanyTrendChart');
      if (trendEl) {
         var ctx = trendEl.getContext('2d');
         var gradient = ctx.createLinearGradient(0, 0, 0, 260);
         gradient.addColorStop(0, 'rgba(115, 102, 255, 0.25)');
         gradient.addColorStop(1, 'rgba(115, 102, 255, 0.02)');

         new Chart(trendEl, {
            type: 'line',
            data: {
               labels: trend.labels,
               datasets: [
                  {
                     label: 'Realisasi (%)',
                     data: trend.realisasi,
                     borderColor: crmPurple,
                     backgroundColor: gradient,
                     borderWidth: 2.5,
                     fill: true,
                     tension: 0.35,
                     pointRadius: 3,
                     pointHoverRadius: 5,
                     spanGaps: false
                  },
                  {
                     label: 'Target (%)',
                     data: trend.target,
                     borderColor: '#CFC8FF',
                     borderDash: [6, 4],
                     backgroundColor: 'transparent',
                     borderWidth: 2,
                     tension: 0.35,
                     pointRadius: 2,
                     pointHoverRadius: 4
                  }
               ]
            },
            options: {
               responsive: true,
               maintainAspectRatio: false,
               interaction: { intersect: false, mode: 'index' },
               plugins: {
                  legend: { display: false },
                  tooltip: {
                     backgroundColor: '#7366FF',
                     padding: 10,
                     cornerRadius: 8,
                     callbacks: { label: function (c) { return c.dataset.label + ': ' + (c.raw === null ? '-' : c.raw + '%'); } }
                  }
               },
               scales: {
                  y: {
                     beginAtZero: true,
                     max: 100,
                     ticks: { callback: function (v) { return v + '%'; }, font: { size: 10 } },
                     grid: { color: 'rgba(0,0,0,0.04)' }
                  },
                  x: { ticks: { font: { size: 9 }, maxRotation: 45 }, grid: { display: false } }
               }
            }
         });
      }
   });
</script>
@endpush
@endsection
