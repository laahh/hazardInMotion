@extends('MonitoringSafetyEngginering.layouts.crm')

@section('title', 'Dashboard Evaluasi Efektivitas Rekayasa')

@push('head')
@include('MonitoringSafetyEngginering.partials.crm-styles')
@endpush

@section('content')
@php
   $pct = static fn (int $n, int $total): float => $total > 0 ? round(($n / $total) * 100, 1) : 0.0;
   $chipClass = static fn (string $cls): string => str_replace('mse-level-chip', 'crm-level-chip', $cls);
   $dateFromDisplay = $filters['date_from'] !== '' ? date('d/m/Y', strtotime($filters['date_from'])) : '';
   $dateToDisplay = $filters['date_to'] !== '' ? date('d/m/Y', strtotime($filters['date_to'])) : '';
   $efektifCount = $validationRecap['data'][0] ?? 0;
   $efektifPct = $pct($efektifCount, $summary['total']);
   $potensiPct = $pct($summary['potensi_naik_level'], $summary['total']);
   $turun1Pct = $pct($summary['turun_1'], $summary['total']);
   $avatarColors = ['#7366FF', '#51BB25', '#FFAA05', '#FF5B5B', '#3B97FF', '#9b93ff'];
   $tablePreview = collect($priorityList)->take(5);
   $statusDots = [
      'Efektif' => 'crm-status-dot--green',
      'Efektif Sebagian' => 'crm-status-dot--yellow',
      'Tidak Efektif' => 'crm-status-dot--red',
      'Perlu Validasi Data' => 'crm-status-dot--purple',
   ];
@endphp

{{-- Filter Bar --}}
<form method="GET" action="{{ route('monitoring-safety-engineering.effectiveness') }}" class="crm-filter-bar crm-filter-bar--compact">
   <div class="crm-filter-field crm-filter-field--company-wide">
      <label class="crm-filter-label" for="mse-eff-company">Perusahaan Inisiator</label>
      <select id="mse-eff-company" name="company" class="crm-filter-select" onchange="this.form.submit()">
         @foreach($filterOptions['companies'] ?? [] as $key => $label)
         <option value="{{ $key }}" @selected($filters['company'] === (string) $key)>{{ $label }}</option>
         @endforeach
      </select>
   </div>

   <div class="crm-filter-field crm-filter-field--period">
      <label class="crm-filter-label" for="mse-eff-date-from">Periode Data</label>
      <div class="crm-filter-date-range">
         <label class="crm-filter-date-box" for="mse-eff-date-from">
            <span class="crm-filter-date-display" id="mse-eff-date-from-display">{{ $dateFromDisplay }}</span>
            <input type="date" id="mse-eff-date-from" name="date_from" value="{{ $filters['date_from'] }}" class="crm-filter-date-input">
            <svg class="crm-filter-date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
               <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
         </label>
         <span class="crm-filter-date-sep" aria-hidden="true">—</span>
         <label class="crm-filter-date-box" for="mse-eff-date-to">
            <span class="crm-filter-date-display" id="mse-eff-date-to-display">{{ $dateToDisplay }}</span>
            <input type="date" id="mse-eff-date-to" name="date_to" value="{{ $filters['date_to'] }}" class="crm-filter-date-input">
            <svg class="crm-filter-date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
               <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
         </label>
      </div>
   </div>
</form>

{{-- Row 1: KPI Stat Cards --}}
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4 mb-4">
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Total Dievaluasi</p>
      <p class="crm-stat-value">{{ $summary['total'] }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">arrow_upward</span>
         100%
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Turun 1 Tangga</p>
      <p class="crm-stat-value">{{ $summary['turun_1'] }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">arrow_upward</span>
         +{{ $turun1Pct }}%
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Turun 2 Tangga</p>
      <p class="crm-stat-value">{{ $summary['turun_2'] }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">arrow_upward</span>
         +{{ $pct($summary['turun_2'], $summary['total']) }}%
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Turun 3 Tangga</p>
      <p class="crm-stat-value">{{ $summary['turun_3'] }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">arrow_upward</span>
         +{{ $pct($summary['turun_3'], $summary['total']) }}%
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Belum Prediksi</p>
      <p class="crm-stat-value">{{ $summary['belum_ada_prediksi'] }}</p>
      <span class="crm-stat-trend crm-stat-trend--down">
         <span class="material-symbols-outlined text-sm">arrow_downward</span>
         {{ $pct($summary['belum_ada_prediksi'], $summary['total']) }}%
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Potensi Naik Level</p>
      <p class="crm-stat-value">{{ $summary['potensi_naik_level'] }}</p>
      <span class="crm-stat-trend {{ $potensiPct > 15 ? 'crm-stat-trend--down' : 'crm-stat-trend--up' }}">
         <span class="material-symbols-outlined text-sm">{{ $potensiPct > 15 ? 'arrow_downward' : 'arrow_upward' }}</span>
         {{ $potensiPct > 15 ? '-' : '+' }}{{ $potensiPct }}%
      </span>
   </div>
</div>

{{-- Row 2: Charts + Matrix --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
   {{-- Distribusi Prediksi --}}
   <div class="crm-card">
      <p class="crm-card-title">Distribusi Prediksi Penurunan Risiko</p>
      <div class="crm-chart-wrap">
         <canvas id="crmRiskDistributionChart"></canvas>
         <div class="crm-donut-center">
            <span class="crm-donut-total-label">Total</span>
            <span class="crm-donut-total-value">{{ $summary['total'] }}</span>
         </div>
      </div>
      <div class="crm-legend">
         @foreach($riskDistribution['labels'] as $i => $label)
         <span class="crm-legend-item">
            <span class="crm-legend-dot" style="background:{{ ['#7366FF','#FFAA05','#FF5B5B','#CFC8FF'][$i] ?? '#848488' }}"></span>
            {{ $label }}
         </span>
         @endforeach
      </div>
   </div>

   {{-- Matriks Validasi --}}
   <div class="crm-card">
      <p class="crm-card-title">Matriks Validasi &amp; Tindak Lanjut</p>
      <div class="overflow-x-auto">
         <table class="crm-matrix-table">
            <thead>
               <tr>
                  <th>Hazard</th>
                  <th>Insiden</th>
                  <th>Validasi</th>
                  <th>Tindak Lanjut</th>
               </tr>
            </thead>
            <tbody>
               @foreach($validationMatrix as $row)
               <tr>
                  <td>{{ $row['hazard'] }}</td>
                  <td>{{ $row['insiden'] }}</td>
                  <td><span class="{{ $chipClass($row['validasi_class']) }}">{{ $row['validasi'] }}</span></td>
                  <td>{{ $row['tindak_lanjut'] }}</td>
               </tr>
               @endforeach
            </tbody>
         </table>
      </div>
   </div>

   {{-- Rekap Validasi (stack bar style) --}}
   <div class="crm-card">
      <p class="crm-card-title">Rekap Hasil Validasi Efektivitas</p>
      @php
         $recapTotal = max(1, array_sum($validationRecap['data']));
         $recapColors = ['#7366FF', '#51BB25', '#FF5B5B', '#848488'];
      @endphp
      <div class="crm-app-stack">
         @foreach($validationRecap['data'] as $i => $val)
         <div class="crm-app-stack-seg" style="width:{{ round(($val / $recapTotal) * 100) }}%;background:{{ $recapColors[$i] ?? '#848488' }}"></div>
         @endforeach
      </div>
      @foreach($validationRecap['labels'] as $i => $label)
      <div class="crm-app-row">
         <span class="crm-app-row-left">
            <span class="crm-legend-dot" style="background:{{ $recapColors[$i] ?? '#848488' }}"></span>
            {{ $label }}
         </span>
         <span class="crm-app-row-pct">{{ $validationRecap['data'][$i] }} ({{ $pct($validationRecap['data'][$i], $summary['total']) }}%)</span>
      </div>
      @endforeach
      <div class="crm-chart-wrap mt-4" style="height:160px">
         <canvas id="crmValidationRecapChart"></canvas>
         <div class="crm-donut-center">
            <span class="crm-donut-total-label">Efektif</span>
            <span class="crm-donut-total-value">{{ $efektifPct }}%</span>
         </div>
      </div>
   </div>
</div>

{{-- Row 3: Preview Table + Sidebar --}}
<div class="grid grid-cols-1 xl:grid-cols-[1.4fr_1fr] gap-4 mb-4">
   <div class="crm-card">
      <p class="crm-card-title">Progress Rekayasa Prioritas</p>
      <table class="crm-table">
         <thead>
            <tr>
               <th>Pengendalian</th>
               <th>Perusahaan</th>
               <th>Validasi</th>
            </tr>
         </thead>
         <tbody>
            @foreach($tablePreview as $index => $row)
            @php
               $initials = collect(explode(' ', $row['name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
            @endphp
            <tr>
               <td>
                  <div class="crm-table-name">
                     <span class="crm-table-avatar" style="background:{{ $avatarColors[$index % count($avatarColors)] }}">{{ $initials }}</span>
                     <span class="truncate max-w-[160px]" title="{{ $row['name'] }}">{{ Str::limit($row['name'], 24) }}</span>
                  </div>
               </td>
               <td class="text-crm-muted">{{ $row['perusahaan'] }}</td>
               <td>
                  <span class="crm-status-dot {{ $statusDots[$row['validasi']] ?? 'crm-status-dot--purple' }}">
                     {{ $row['validasi'] }}
                  </span>
               </td>
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

{{-- Full Priority Table --}}
<div class="crm-card">
   <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <p class="crm-card-title mb-0">Daftar Prioritas Rekayasa untuk Upgrade Level Efektivitas</p>
      <span class="crm-badge">
         <span class="material-symbols-outlined text-xs">sort</span>
         Diurutkan berdasarkan Prioritas
      </span>
   </div>

   <div class="crm-data-table-wrap">
      <table class="crm-data-table">
         <thead>
            <tr>
               <th class="w-10">No</th>
               <th>Pengendalian Rekayasa</th>
               <th class="w-28">Perusahaan Inisiator</th>
               <th class="w-32">Prediksi Penurunan Risiko</th>
               <th class="w-32">Hazard Setelah Rekayasa</th>
               <th class="w-24">Incident</th>
               <th class="w-32">Validasi Efektivitas</th>
               <th>Tindak Lanjut / Arah Upgrade</th>
            </tr>
         </thead>
         <tbody>
            @forelse($priorityList as $index => $row)
            <tr>
               <td class="text-crm-muted font-medium">{{ $index + 1 }}</td>
               <td class="font-medium whitespace-nowrap">{{ $row['name'] }}</td>
               <td class="text-crm-muted whitespace-nowrap">{{ $row['perusahaan'] }}</td>
               <td class="whitespace-nowrap">{{ $row['prediksi'] }}</td>
               <td class="whitespace-nowrap">
                  {{ $row['hazard'] }}
                  @if($row['hazard_up'])
                  <span class="material-symbols-outlined text-[#FF5B5B] text-sm align-middle">arrow_upward</span>
                  @endif
               </td>
               <td>{{ $row['insiden'] }}</td>
               <td><span class="{{ $chipClass($row['validasi_class']) }}">{{ $row['validasi'] }}</span></td>
               <td class="text-xs">{{ $row['tindak_lanjut'] }}</td>
            </tr>
            @empty
            <tr>
               <td colspan="8" class="text-center py-10 text-crm-muted">Belum ada data evaluasi efektivitas.</td>
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

      var riskDistribution = @json($riskDistribution);
      var validationRecap = @json($validationRecap);
      var crmPurple = '#7366FF';
      var crmColors = ['#7366FF', '#FFAA05', '#FF5B5B', '#CFC8FF'];
      var recapColors = ['#7366FF', '#51BB25', '#FF5B5B', '#848488'];

      Chart.defaults.font.family = "'Poppins', sans-serif";
      Chart.defaults.color = '#848488';
      Chart.defaults.animation.duration = 800;

      var riskEl = document.getElementById('crmRiskDistributionChart');
      if (riskEl) {
         new Chart(riskEl, {
            type: 'doughnut',
            data: {
               labels: riskDistribution.labels,
               datasets: [{
                  data: riskDistribution.data,
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

      var recapEl = document.getElementById('crmValidationRecapChart');
      if (recapEl) {
         new Chart(recapEl, {
            type: 'doughnut',
            data: {
               labels: validationRecap.labels,
               datasets: [{
                  data: validationRecap.data,
                  backgroundColor: recapColors,
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
   });
</script>
@endpush
@endsection
