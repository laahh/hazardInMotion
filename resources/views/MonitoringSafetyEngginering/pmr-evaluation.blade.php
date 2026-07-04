@extends('MonitoringSafetyEngginering.layouts.crm')

@section('title', 'Evaluasi Pengendalian Rekayasa Standar PMR 1, 2, 3')

@push('head')
@include('MonitoringSafetyEngginering.partials.crm-styles')
@endpush

@section('content')
@php
   $dateFromDisplay = $filters['date_from'] !== '' ? date('d/m/Y', strtotime($filters['date_from'])) : '';
   $dateToDisplay = $filters['date_to'] !== '' ? date('d/m/Y', strtotime($filters['date_to'])) : '';
   $totalHazard = $summary['total_hazard'] ?? collect($items)->sum('hazard');
   $totalInsiden = $summary['total_insiden'] ?? collect($items)->sum('insiden');
   $avatarColors = ['#7366FF', '#51BB25', '#FFAA05', '#FF5B5B', '#3B97FF'];
   $pmrGroups = $pmrGroups ?? ['PMR 1', 'PMR 2', 'PMR 3'];
   $pmrColors = config('monitoring_safety_engineering.pmr_evaluation.group_colors', [
      'PMR 1' => '#7366FF',
      'PMR 2' => '#CFC8FF',
      'PMR 3' => '#51BB25',
   ]);
   $pmrIcons = ['PMR 1' => 'shield', 'PMR 2' => 'settings', 'PMR 3' => 'checklist'];
   $itemPreview = collect($items)->take(5);
@endphp

{{-- Filter Bar --}}
<form method="GET" action="{{ route('monitoring-safety-engineering.pmr-evaluation') }}" class="crm-filter-bar crm-filter-bar--compact">
   <div class="crm-filter-field crm-filter-field--company-wide">
      <label class="crm-filter-label" for="mse-pmr-company">Perusahaan</label>
      <select id="mse-pmr-company" name="company" class="crm-filter-select" onchange="this.form.submit()">
         @foreach($filterOptions['companies'] ?? [] as $key => $label)
         <option value="{{ $key }}" @selected($filters['company'] === (string) $key)>{{ $label }}</option>
         @endforeach
      </select>
   </div>

   <div class="crm-filter-field crm-filter-field--period">
      <label class="crm-filter-label" for="mse-pmr-date-from">Periode</label>
      <div class="crm-filter-date-range">
         <label class="crm-filter-date-box" for="mse-pmr-date-from">
            <span class="crm-filter-date-display" id="mse-pmr-date-from-display">{{ $dateFromDisplay }}</span>
            <input type="date" id="mse-pmr-date-from" name="date_from" value="{{ $filters['date_from'] }}" class="crm-filter-date-input">
            <svg class="crm-filter-date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
               <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
         </label>
         <span class="crm-filter-date-sep" aria-hidden="true">—</span>
         <label class="crm-filter-date-box" for="mse-pmr-date-to">
            <span class="crm-filter-date-display" id="mse-pmr-date-to-display">{{ $dateToDisplay }}</span>
            <input type="date" id="mse-pmr-date-to" name="date_to" value="{{ $filters['date_to'] }}" class="crm-filter-date-input">
            <svg class="crm-filter-date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
               <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
         </label>
      </div>
   </div>
</form>

{{-- Row 1: KPI Stat Cards --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Total Pengendalian</p>
      <p class="crm-stat-value">{{ $summary['total'] }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">verified</span>
         Rekayasa Standar
      </span>
   </div>
   @foreach($pmrGroups as $pmr)
   @php $icon = $pmrIcons[$pmr] ?? 'verified'; @endphp
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">{{ $pmr }}</p>
      <p class="crm-stat-value">{{ $summary[$pmr] ?? 0 }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">{{ $icon }}</span>
         {{ $summary['total'] > 0 ? round((($summary[$pmr] ?? 0) / $summary['total']) * 100) : 0 }}%
      </span>
   </div>
   @endforeach
</div>

{{-- Row 2: Insight band --}}
<div class="crm-card p-4 mb-4">
   <div class="crm-insight-band">
      <div class="crm-insight-item crm-insight-item--purple">
         <div class="crm-insight-icon crm-insight-icon--purple">
            <span class="material-symbols-outlined">warning</span>
         </div>
         <div>
            <p class="crm-insight-value">{{ number_format($totalHazard) }}</p>
            <p class="crm-insight-label">Total Hazard Terkait</p>
         </div>
      </div>
      <div class="crm-insight-item crm-insight-item--red">
         <div class="crm-insight-icon crm-insight-icon--red">
            <span class="material-symbols-outlined">report</span>
         </div>
         <div>
            <p class="crm-insight-value">{{ $totalInsiden }}</p>
            <p class="crm-insight-label">Total Insiden Terkait</p>
         </div>
      </div>
      <div class="crm-insight-item crm-insight-item--green">
         <div class="crm-insight-icon crm-insight-icon--green">
            <span class="material-symbols-outlined">stairs</span>
         </div>
         <div>
            <p class="crm-insight-value">{{ $summary['dominant_level_count'] ?? 0 }}</p>
            <p class="crm-insight-label">Dominan: {{ $summary['dominant_level_label'] ?? 'Belum Ada Prediksi' }}</p>
         </div>
      </div>
   </div>
</div>

{{-- Row 3: Matrix + Charts --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
   {{-- Matriks Level Efektivitas --}}
   <div class="crm-card xl:col-span-2">
      <p class="crm-card-title">Matriks Level Efektivitas</p>
      <div class="overflow-x-auto">
         <table class="crm-matrix-table">
            <thead>
               <tr>
                  <th class="w-16">Level</th>
                  <th>Sifat Pengendalian Rekayasa terhadap KTA/TTA</th>
                  <th class="w-36">Prediksi Penurunan Risiko</th>
                  <th>Keterangan</th>
               </tr>
            </thead>
            <tbody>
               @foreach($effectivenessLevels as $row)
               <tr>
                  <td class="text-center font-bold text-[#7366FF]">{{ $row['level'] }}</td>
                  <td>{{ $row['sifat'] }}</td>
                  <td class="whitespace-nowrap font-semibold">{{ $row['prediksi'] }}</td>
                  <td>{{ $row['keterangan'] }}</td>
               </tr>
               @endforeach
            </tbody>
         </table>
      </div>
   </div>

   {{-- PMR Distribution Donut --}}
   <div class="crm-card">
      <p class="crm-card-title">Distribusi PMR</p>
      <div class="crm-chart-wrap">
         <canvas id="crmPmrDistributionChart"></canvas>
         <div class="crm-donut-center">
            <span class="crm-donut-total-label">Total</span>
            <span class="crm-donut-total-value">{{ $summary['total'] }}</span>
         </div>
      </div>
      <div class="crm-legend">
         @foreach($pmrGroups as $pmr)
         <span class="crm-legend-item">
            <span class="crm-legend-dot" style="background:{{ $pmrColors[$pmr] ?? '#7366FF' }}"></span>
            {{ $pmr }} ({{ $summary[$pmr] ?? 0 }})
         </span>
         @endforeach
      </div>
   </div>
</div>

{{-- Row 4: Hazard Chart + Item Preview + Sidebar --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
   <div class="crm-card">
      <p class="crm-card-title">Exposure Hazard per Item</p>
      <div class="crm-chart-wrap" style="height:240px">
         <canvas id="crmPmrHazardChart"></canvas>
      </div>
   </div>

   <div class="crm-card">
      <p class="crm-card-title">Pengendalian Rekayasa Standar</p>
      <table class="crm-table">
         <thead>
            <tr>
               <th>Pengendalian</th>
               <th>PMR</th>
               <th>Hazard</th>
            </tr>
         </thead>
         <tbody>
            @foreach($itemPreview as $index => $item)
            @php $initials = collect(explode(' ', $item['name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''); @endphp
            <tr>
               <td>
                  <div class="crm-table-name">
                     <span class="crm-table-avatar" style="background:{{ $avatarColors[$index % count($avatarColors)] }}">{{ $initials }}</span>
                     <span>{{ $item['name'] }}</span>
                  </div>
               </td>
               <td class="text-crm-muted">{{ $item['pmr'] }}</td>
               <td class="font-bold text-[#7366FF]">{{ number_format($item['hazard']) }}</td>
            </tr>
            @endforeach
         </tbody>
      </table>
   </div>

   <aside class="space-y-4">
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
         </div>
      </div>

      <div class="crm-card overflow-hidden p-0">
         <div class="crm-sidebar-header crm-sidebar-header--green">
            <span class="material-symbols-outlined text-sm mr-1 align-middle">task_alt</span>
            Perkuatan dan Next To Do
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

{{-- Full Data Table --}}
<div class="crm-card">
   <p class="crm-card-title mb-4">Daftar Lengkap Pengendalian Rekayasa Standar</p>
   <div class="crm-data-table-wrap">
      <table class="crm-data-table">
         <thead>
            <tr>
               <th class="w-10">No</th>
               <th>Pengendalian Rekayasa</th>
               <th class="w-24">PMR</th>
               <th class="w-40">Level Efektivitas</th>
               <th class="w-40 text-center">Hazard Terkait</th>
               <th class="w-40 text-center">Insiden Terkait</th>
            </tr>
         </thead>
         <tbody>
            @forelse($items as $index => $item)
            <tr>
               <td class="text-crm-muted font-medium">{{ $index + 1 }}</td>
               <td class="font-medium">
                  <span class="inline-flex items-center gap-2">
                     <span class="material-symbols-outlined text-[#7366FF] text-base">{{ $item['icon'] }}</span>
                     {{ $item['name'] }}
                  </span>
               </td>
               <td>
                  <span class="crm-pct" style="background:{{ $pmrColors[$item['pmr']] ?? '#ECE9FF' }}22;color:{{ $pmrColors[$item['pmr']] ?? '#7366FF' }}">{{ $item['pmr'] }}</span>
               </td>
               <td>
                  <span class="crm-level-chip {{ $item['level'] > 0 ? 'crm-level-chip--warn' : '' }}">
                     @if($item['level'] > 0)
                     <span class="material-symbols-outlined text-sm">arrow_downward</span>
                     @endif
                     {{ $item['level_label'] }}
                  </span>
               </td>
               <td class="text-center font-bold text-[#7366FF]">{{ number_format($item['hazard']) }}</td>
               <td class="text-center font-bold {{ $item['insiden'] > 0 ? 'text-[#FF5B5B]' : 'text-crm-muted' }}">{{ $item['insiden'] }}</td>
            </tr>
            @empty
            <tr>
               <td colspan="6" class="text-center py-10 text-crm-muted">Belum ada data pengendalian rekayasa standar.</td>
            </tr>
            @endforelse
         </tbody>
      </table>
   </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script>
   document.addEventListener('DOMContentLoaded', function () {
      function formatDateDisplay(iso) {
         if (!iso) return '';
         var p = iso.split('-');
         if (p.length !== 3) return iso;
         return p[2] + '/' + p[1] + '/' + p[0];
      }
      document.querySelectorAll('.crm-filter-date-input').forEach(function (input) {
         input.addEventListener('change', function () {
            var display = this.closest('.crm-filter-date-box')?.querySelector('.crm-filter-date-display');
            if (display) display.textContent = formatDateDisplay(this.value);
            if (this.form) this.form.submit();
         });
      });

      var chartsData = @json($charts);
      var crmPurple = '#7366FF';
      var crmPurpleLight = '#CFC8FF';
      var crmGreen = '#51BB25';

      Chart.defaults.font.family = "'Poppins', sans-serif";
      Chart.defaults.color = '#848488';
      Chart.defaults.animation.duration = 800;

      var pmrDistEl = document.getElementById('crmPmrDistributionChart');
      if (pmrDistEl) {
         var dist = chartsData.category_distribution || {};
         new Chart(pmrDistEl, {
            type: 'doughnut',
            data: {
               labels: dist.labels || [],
               datasets: [{
                  data: dist.data || [],
                  backgroundColor: dist.colors || [crmPurple, crmPurpleLight, crmGreen],
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

      var hazardEl = document.getElementById('crmPmrHazardChart');
      if (hazardEl) {
         var ctx = hazardEl.getContext('2d');
         var barColors = chartsData.hazard_by_item.colors.map(function (c, i) {
            var palette = [crmPurple, crmPurpleLight, crmGreen];
            return palette[i] || crmPurple;
         });
         new Chart(hazardEl, {
            type: 'bar',
            data: {
               labels: chartsData.hazard_by_item.labels,
               datasets: [{
                  label: 'Hazard',
                  data: chartsData.hazard_by_item.data,
                  backgroundColor: barColors,
                  borderRadius: { topLeft: 8, topRight: 8 },
                  borderSkipped: false,
                  maxBarThickness: 48
               }]
            },
            options: {
               responsive: true,
               maintainAspectRatio: false,
               plugins: {
                  legend: { display: false },
                  tooltip: {
                     backgroundColor: crmPurple,
                     padding: 10,
                     cornerRadius: 8
                  }
               },
               scales: {
                  x: { ticks: { font: { size: 10 } }, grid: { display: false } },
                  y: { beginAtZero: true, ticks: { font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.04)' } }
               }
            }
         });
      }
   });
</script>
@endpush
@endsection
