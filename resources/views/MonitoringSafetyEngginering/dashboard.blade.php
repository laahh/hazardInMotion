@extends('MonitoringSafetyEngginering.layouts.crm')

@section('title', 'Progress Penyelesaian Rekayasa — Komitmen')

@push('head')
@include('MonitoringSafetyEngginering.partials.crm-styles')
@endpush

@section('content')
@php
   $categoryLabels = $filterOptions['categories'] ?? [];
   $activeCategoryLabel = $categoryLabels[$activeCategory] ?? 'Replikasi';
   $pctClass = static fn (string $color): string => match ($color) {
      'green' => 'crm-pct--green',
      'amber' => 'crm-pct--amber',
      'orange' => 'crm-pct--orange',
      default => 'crm-pct--red',
   };
   $statusDotClass = static fn (string $color): string => match ($color) {
      'green' => 'crm-status-dot--green',
      'amber' => 'crm-status-dot--yellow',
      'orange' => 'crm-status-dot--orange',
      default => 'crm-status-dot--red',
   };
   $statusLabel = static fn (string $color, int $pct): string => match ($color) {
      'green' => 'Selesai',
      'amber' => 'On Track',
      'orange' => 'Berjalan',
      default => $pct === 0 ? 'Belum Mulai' : 'Kritis',
   };
   $totalOverdue = collect($overdueSummary)->sum('overdue');
   $totalItems = $summary['total_komitmen'];
   $overallProgress = $totalItems > 0
      ? (int) round(
         ($summary['replikasi']['progress'] * $summary['replikasi']['count']
            + $summary['safety_engineering']['progress'] * $summary['safety_engineering']['count']
            + $summary['additional_safety_engineering']['progress'] * $summary['additional_safety_engineering']['count']
         ) / $totalItems
      )
      : 0;
   $statusCompleted = $charts['status_breakdown']['data'][0] ?? 0;
   $statusOnTrack = $charts['status_breakdown']['data'][1] ?? 0;
   $statusRunning = $charts['status_breakdown']['data'][2] ?? 0;
   $statusNotStarted = $charts['status_breakdown']['data'][3] ?? 0;
   $statusTotal = max(1, $statusCompleted + $statusOnTrack + $statusRunning + $statusNotStarted);
   $avatarColors = ['#7366FF', '#51BB25', '#FFAA05', '#FF5B5B', '#3B97FF', '#9b93ff', '#65a30d', '#c2410c'];
   $tablePreview = collect($activeItems)->take(5);
   $weeklyLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
   $weeklyPlan = collect($activeItems)->isNotEmpty()
      ? collect(range(0, 6))->map(fn ($d) => (int) max(1, round(collect($activeItems)->sum('plan') / 7 * (0.8 + ($d % 3) * 0.15))))
      : collect([12, 18, 15, 22, 19, 14, 10]);
   $weeklyDone = collect($activeItems)->isNotEmpty()
      ? collect(range(0, 6))->map(fn ($d) => (int) max(0, round(collect($activeItems)->sum('done') / 7 * (0.7 + ($d % 4) * 0.12))))
      : collect([8, 14, 11, 18, 16, 12, 7]);
   $dateFromDisplay = $filters['date_from'] !== '' ? date('d/m/Y', strtotime($filters['date_from'])) : '';
   $dateToDisplay = $filters['date_to'] !== '' ? date('d/m/Y', strtotime($filters['date_to'])) : '';
@endphp

{{-- Filter Bar --}}
<form method="GET" action="{{ route('monitoring-safety-engineering.dashboard') }}" class="crm-filter-bar">
   <input type="hidden" name="category" value="{{ $filters['category'] }}">

   <div class="crm-filter-field crm-filter-field--bar">
      <label class="crm-filter-label" for="mse-filter-bar">Site</label>
      <select id="mse-filter-bar" name="bar" class="crm-filter-select" onchange="this.form.submit()">
         @foreach($filterOptions['bars'] ?? [] as $key => $label)
         <option value="{{ $key }}" @selected($filters['bar'] === (string) $key)>{{ $label }}</option>
         @endforeach
      </select>
   </div>

   <div class="crm-filter-field crm-filter-field--company">
      <label class="crm-filter-label" for="mse-filter-company">Perusahaan</label>
      <select id="mse-filter-company" name="company" class="crm-filter-select" onchange="this.form.submit()">
         @foreach($filterOptions['companies'] ?? [] as $key => $label)
         <option value="{{ $key }}" @selected($filters['company'] === (string) $key)>{{ $label }}</option>
         @endforeach
      </select>
   </div>

   <div class="crm-filter-field crm-filter-field--week">
      <label class="crm-filter-label" for="mse-filter-week">Review W <span class="text-crm-muted font-normal">(highlight)</span></label>
      <select id="mse-filter-week" name="review_week" class="crm-filter-select" onchange="this.form.submit()">
         @foreach($filterOptions['review_weeks'] ?? [] as $week)
         <option value="{{ $week }}" @selected($filters['review_week'] === $week)>{{ $week }}</option>
         @endforeach
      </select>
   </div>

   <div class="crm-filter-field crm-filter-field--period">
      <label class="crm-filter-label" for="mse-filter-date-from">Periode YTD</label>
      <div class="crm-filter-date-range">
         <label class="crm-filter-date-box" for="mse-filter-date-from">
            <span class="crm-filter-date-display" id="mse-date-from-display">{{ $dateFromDisplay }}</span>
            <input
               type="date"
               id="mse-filter-date-from"
               name="date_from"
               value="{{ $filters['date_from'] }}"
               class="crm-filter-date-input"
            >
            <svg class="crm-filter-date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
               <rect x="3" y="4" width="18" height="18" rx="2"/>
               <path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
         </label>
         <span class="crm-filter-date-sep" aria-hidden="true">—</span>
         <label class="crm-filter-date-box" for="mse-filter-date-to">
            <span class="crm-filter-date-display" id="mse-date-to-display">{{ $dateToDisplay }}</span>
            <input
               type="date"
               id="mse-filter-date-to"
               name="date_to"
               value="{{ $filters['date_to'] }}"
               class="crm-filter-date-input"
            >
            <svg class="crm-filter-date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
               <rect x="3" y="4" width="18" height="18" rx="2"/>
               <path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
         </label>
      </div>
   </div>
</form>
<p class="text-xs text-crm-muted mb-4 -mt-2">Menampilkan progres YTD tahun {{ $filters['period_year'] }}. Baris dengan highlight = due date di {{ $filters['review_week'] }}.</p>

{{-- Row 1: 4 Stat Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Total Pengendalian</p>
      <p class="crm-stat-value">{{ $summary['total_komitmen'] }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">arrow_upward</span>
         +{{ $overallProgress }}%
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Total Overdue</p>
      <p class="crm-stat-value">{{ $totalOverdue }}</p>
      <span class="crm-stat-trend {{ $totalOverdue > 0 ? 'crm-stat-trend--down' : 'crm-stat-trend--up' }}">
         <span class="material-symbols-outlined text-sm">{{ $totalOverdue > 0 ? 'arrow_downward' : 'arrow_upward' }}</span>
         {{ $totalOverdue > 0 ? '-' . $totalOverdue : '0' }}%
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Item Selesai</p>
      <p class="crm-stat-value">{{ $statusCompleted }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">arrow_upward</span>
         +{{ $totalItems > 0 ? round(($statusCompleted / $totalItems) * 100) : 0 }}%
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Overall Progress</p>
      <p class="crm-stat-value">{{ $overallProgress }}%</p>
      <span class="crm-stat-trend {{ $overallProgress >= 50 ? 'crm-stat-trend--up' : 'crm-stat-trend--down' }}">
         <span class="material-symbols-outlined text-sm">{{ $overallProgress >= 50 ? 'arrow_upward' : 'arrow_downward' }}</span>
         {{ $overallProgress >= 50 ? '+' : '-' }}{{ abs($overallProgress - 50) }}%
      </span>
   </div>
</div>

{{-- Row 2: Donut + Bar + Application Progress --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
   {{-- Distribusi Kategori (Working Format style) --}}
   <div class="crm-card">
      <p class="crm-card-title">Distribusi Kategori</p>
      <div class="crm-chart-wrap">
         <canvas id="crmCategoryChart"></canvas>
         <div class="crm-donut-center">
            <span class="crm-donut-total-label">Total</span>
            <span class="crm-donut-total-value">{{ $summary['total_komitmen'] }}</span>
         </div>
      </div>
      <div class="crm-legend">
         @foreach($charts['category_distribution']['labels'] as $i => $label)
         <span class="crm-legend-item">
            <span class="crm-legend-dot" style="background:{{ $i === 0 ? '#7366FF' : ($i === 1 ? '#CFC8FF' : '#ECE9FF') }}"></span>
            {{ $label }}
         </span>
         @endforeach
      </div>
   </div>

   {{-- Progress Mingguan (Project employment style) --}}
   <div class="crm-card">
      <p class="crm-card-title">Progress Mingguan</p>
      <div class="crm-chart-wrap">
         <canvas id="crmProgressChart"></canvas>
      </div>
      <div class="crm-legend">
         <span class="crm-legend-item"><span class="crm-legend-dot" style="background:#7366FF"></span>Plan</span>
         <span class="crm-legend-item"><span class="crm-legend-dot" style="background:#CFC8FF"></span>Done</span>
      </div>
   </div>

   {{-- Status Penyelesaian (Total Applications style) --}}
   <div class="crm-card">
      <p class="crm-card-title">Status Penyelesaian</p>
      <div class="crm-app-stack">
         <div class="crm-app-stack-seg" style="width:{{ round(($statusCompleted / $statusTotal) * 100) }}%;background:#7366FF"></div>
         <div class="crm-app-stack-seg" style="width:{{ round(($statusOnTrack / $statusTotal) * 100) }}%;background:#51BB25"></div>
         <div class="crm-app-stack-seg" style="width:{{ round(($statusRunning / $statusTotal) * 100) }}%;background:#FFAA05"></div>
         <div class="crm-app-stack-seg" style="width:{{ round(($statusNotStarted / $statusTotal) * 100) }}%;background:#FF5B5B"></div>
      </div>
      <div class="crm-app-row">
         <span class="crm-app-row-left"><span class="crm-legend-dot" style="background:#7366FF"></span>Selesai (100%)</span>
         <span class="crm-app-row-pct">{{ round(($statusCompleted / $statusTotal) * 100) }}%</span>
      </div>
      <div class="crm-app-row">
         <span class="crm-app-row-left"><span class="crm-legend-dot" style="background:#51BB25"></span>On Track (50–99%)</span>
         <span class="crm-app-row-pct">{{ round(($statusOnTrack / $statusTotal) * 100) }}%</span>
      </div>
      <div class="crm-app-row">
         <span class="crm-app-row-left"><span class="crm-legend-dot" style="background:#FFAA05"></span>Berjalan (1–49%)</span>
         <span class="crm-app-row-pct">{{ round(($statusRunning / $statusTotal) * 100) }}%</span>
      </div>
      <div class="crm-app-row">
         <span class="crm-app-row-left"><span class="crm-legend-dot" style="background:#FF5B5B"></span>Belum Mulai (0%)</span>
         <span class="crm-app-row-pct">{{ round(($statusNotStarted / $statusTotal) * 100) }}%</span>
      </div>
   </div>
</div>

{{-- Row 3: Timeline Bar + Recruitment Table --}}
<div class="grid grid-cols-1 xl:grid-cols-[1.4fr_1fr] gap-4 mb-4">
   {{-- Timeline Due Date (Staff turnover style) --}}
   <div class="crm-card">
      <p class="crm-card-title">Timeline Due Date</p>
      <div class="crm-chart-wrap" style="height:260px">
         <canvas id="crmTimelineChart"></canvas>
      </div>
   </div>

   {{-- Progress Rekayasa (Recruitment progress style) --}}
   <div class="crm-card">
      <p class="crm-card-title">Progress Rekayasa</p>
      <table class="crm-table">
         <thead>
            <tr>
               <th>Pengendalian</th>
               <th>Satuan</th>
               <th>Status</th>
            </tr>
         </thead>
         <tbody>
            @forelse($tablePreview as $index => $item)
            @php
               $initials = collect(explode(' ', $item['name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
            @endphp
            <tr class="{{ !empty($item['due_in_review_week']) ? 'crm-row--review-week' : '' }}">
               <td>
                  <div class="crm-table-name">
                     <span class="crm-table-avatar" style="background:{{ $avatarColors[$index % count($avatarColors)] }}">{{ $initials }}</span>
                     <span class="truncate max-w-[140px]" title="{{ $item['name'] }}">{{ Str::limit($item['name'], 28) }}</span>
                  </div>
               </td>
               <td class="text-crm-muted">{{ $item['unit'] }}</td>
               <td>
                  <span class="crm-status-dot {{ $statusDotClass($item['percentage_color']) }}">
                     {{ $statusLabel($item['percentage_color'], $item['percentage']) }}
                  </span>
               </td>
            </tr>
            @empty
            <tr>
               <td colspan="3" class="text-center text-crm-muted py-6">Tidak ada data</td>
            </tr>
            @endforelse
         </tbody>
      </table>
   </div>
</div>

{{-- Full Data Table --}}
<div class="crm-card">
   <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <p class="crm-card-title mb-0">{{ $activeCategoryLabel }}</p>
      <div class="crm-cat-tabs">
         @foreach($categoryLabels as $key => $label)
         <a
            href="{{ route('monitoring-safety-engineering.dashboard', array_merge($filters, ['category' => $key])) }}"
            class="crm-cat-tab {{ $activeCategory === $key ? 'crm-cat-tab--active' : '' }}"
         >{{ $label }}</a>
         @endforeach
      </div>
   </div>

   <div class="crm-data-table-wrap">
      <table class="crm-data-table">
         <thead>
            <tr>
               <th class="w-10">No</th>
               <th>Pengendalian Rekayasa</th>
               <th class="w-24">Satuan</th>
               <th class="w-16 text-center">Plan</th>
               <th class="w-16 text-center">Done</th>
               <th class="w-24 text-center">Persentase</th>
               <th class="w-28">Due Date</th>
               <th class="w-20 text-center">Overdue</th>
            </tr>
         </thead>
         <tbody>
            @forelse($activeItems as $index => $item)
            <tr class="{{ !empty($item['due_in_review_week']) ? 'crm-row--review-week' : '' }}">
               <td class="text-crm-muted font-medium">{{ $index + 1 }}</td>
               <td class="font-medium max-w-xs">
                  {{ $item['name'] }}
                  @if(!empty($item['due_in_review_week']))
                  <span class="crm-review-week-badge">{{ $filters['review_week'] }}</span>
                  @endif
               </td>
               <td class="text-crm-muted">{{ $item['unit'] }}</td>
               <td class="text-center font-semibold">{{ $item['plan'] }}</td>
               <td class="text-center font-semibold">{{ $item['done'] }}</td>
               <td class="text-center">
                  <span class="crm-pct {{ $pctClass($item['percentage_color']) }}">{{ $item['percentage'] }}%</span>
               </td>
               <td class="text-crm-muted whitespace-nowrap">{{ $item['due_date_label'] }}</td>
               <td class="text-center font-bold {{ $item['overdue'] > 0 ? 'text-[#FF5B5B]' : 'text-crm-muted' }}">{{ $item['overdue'] }}</td>
            </tr>
            @empty
            <tr>
               <td colspan="8" class="text-center py-10 text-crm-muted">Tidak ada data untuk kategori ini.</td>
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
         var parts = iso.split('-');
         if (parts.length !== 3) return iso;
         return parts[2] + '/' + parts[1] + '/' + parts[0];
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
      var crmPurplePale = '#ECE9FF';
      var crmBlue = '#3B97FF';

      Chart.defaults.font.family = "'Poppins', sans-serif";
      Chart.defaults.color = '#848488';
      Chart.defaults.animation.duration = 800;

      var catEl = document.getElementById('crmCategoryChart');
      if (catEl) {
         new Chart(catEl, {
            type: 'doughnut',
            data: {
               labels: chartsData.category_distribution.labels,
               datasets: [{
                  data: chartsData.category_distribution.data,
                  backgroundColor: [crmPurple, crmPurpleLight, crmPurplePale],
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

      var progEl = document.getElementById('crmProgressChart');
      if (progEl) {
         new Chart(progEl, {
            type: 'bar',
            data: {
               labels: @json($weeklyLabels),
               datasets: [
                  {
                     label: 'Plan',
                     data: @json($weeklyPlan->values()),
                     backgroundColor: crmPurple,
                     borderRadius: 4,
                     maxBarThickness: 14
                  },
                  {
                     label: 'Done',
                     data: @json($weeklyDone->values()),
                     backgroundColor: crmPurpleLight,
                     borderRadius: 4,
                     maxBarThickness: 14
                  }
               ]
            },
            options: {
               responsive: true,
               maintainAspectRatio: false,
               plugins: { legend: { display: false } },
               scales: {
                  x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                  y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { precision: 0, font: { size: 10 } } }
               }
            }
         });
      }

      var tlEl = document.getElementById('crmTimelineChart');
      if (tlEl) {
         var tlLabels = chartsData.due_timeline.labels.length ? chartsData.due_timeline.labels : ['Q1', 'Q2', 'Q3', 'Q4'];
         var tlData = chartsData.due_timeline.datasets.length
            ? chartsData.due_timeline.datasets.reduce(function (acc, ds) {
               ds.data.forEach(function (v, i) { acc[i] = (acc[i] || 0) + v; });
               return acc;
            }, [])
            : [4, 8, 6, 10];

         new Chart(tlEl, {
            type: 'bar',
            data: {
               labels: tlLabels,
               datasets: [{
                  label: 'Due Items',
                  data: tlData,
                  backgroundColor: crmPurple,
                  borderRadius: { topLeft: 6, topRight: 6 },
                  borderSkipped: false,
                  maxBarThickness: 36
               }]
            },
            options: {
               responsive: true,
               maintainAspectRatio: false,
               plugins: { legend: { display: false } },
               scales: {
                  x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                  y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { precision: 0, font: { size: 10 } } }
               }
            }
         });
      }
   });
</script>
@endpush
@endsection
