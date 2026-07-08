@extends('MonitoringSafetyEngginering.layouts.crm')

@section('title', 'Progress Penyelesaian Rekayasa — di Luar Komitmen')

@push('head')
@include('MonitoringSafetyEngginering.partials.crm-styles')
@endpush

@section('content')
@php
   $categoryLabels = $filterOptions['categories'] ?? [];
   $activeCategoryLabel = $categoryLabels[$activeCategory] ?? 'Arahan Manajemen';
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
   $maxOverdue = max(1, collect($overdueSummary)->max('overdue'));
   $totalItems = $summary['total_luar_komitmen'];
   $overallProgress = $totalItems > 0
      ? (int) round(
         ($summary['arahan_manajemen']['progress'] * $summary['arahan_manajemen']['count']
            + $summary['rekom_insiden']['progress'] * $summary['rekom_insiden']['count']
            + $summary['rekom_gr']['progress'] * $summary['rekom_gr']['count']
         ) / $totalItems
      )
      : 0;
   $statusCompleted = $charts['status_breakdown']['data'][0] ?? 0;
   $statusOnTrack = $charts['status_breakdown']['data'][1] ?? 0;
   $statusRunning = $charts['status_breakdown']['data'][2] ?? 0;
   $statusNotStarted = $charts['status_breakdown']['data'][3] ?? 0;
   $statusTotal = max(1, $statusCompleted + $statusOnTrack + $statusRunning + $statusNotStarted);
   $avatarColors = ['#7366FF', '#51BB25', '#FFAA05', '#FF5B5B', '#3B97FF', '#9b93ff'];
   $tablePreview = collect($activeItems)->take(5);
   $categoryIcons = [
      'arahan_manajemen' => 'campaign',
      'rekom_insiden' => 'report',
      'rekom_gr' => 'gavel',
   ];
   $categoryCounts = [
      'arahan_manajemen' => $summary['arahan_manajemen']['count'],
      'rekom_insiden' => $summary['rekom_insiden']['count'],
      'rekom_gr' => $summary['rekom_gr']['count'],
   ];
   $activePlan = collect($activeItems)->sum('plan');
   $activeDone = collect($activeItems)->sum('done');
   $activeOverdue = collect($activeItems)->sum('overdue');
   $activeProgress = $activePlan > 0 ? (int) round(($activeDone / $activePlan) * 100) : 0;
   $dateFromDisplay = $filters['date_from'] !== '' ? date('d/m/Y', strtotime($filters['date_from'])) : '';
   $dateToDisplay = $filters['date_to'] !== '' ? date('d/m/Y', strtotime($filters['date_to'])) : '';
@endphp

{{-- Filter Bar --}}
<form method="GET" action="{{ route('monitoring-safety-engineering.outside-commitment') }}" class="crm-filter-bar">
   <input type="hidden" name="category" value="{{ $filters['category'] }}">

   <div class="crm-filter-field crm-filter-field--bar">
      <label class="crm-filter-label" for="moc-filter-bar">Site</label>
      <select id="moc-filter-bar" name="bar" class="crm-filter-select" onchange="this.form.submit()">
         @foreach($filterOptions['bars'] ?? [] as $key => $label)
         <option value="{{ $key }}" @selected($filters['bar'] === (string) $key)>{{ $label }}</option>
         @endforeach
      </select>
   </div>

   <div class="crm-filter-field crm-filter-field--company">
      <label class="crm-filter-label" for="moc-filter-company">Perusahaan</label>
      <select id="moc-filter-company" name="company" class="crm-filter-select" onchange="this.form.submit()">
         @foreach($filterOptions['companies'] ?? [] as $key => $label)
         <option value="{{ $key }}" @selected($filters['company'] === (string) $key)>{{ $label }}</option>
         @endforeach
      </select>
   </div>

   <div class="crm-filter-field crm-filter-field--week">
      <label class="crm-filter-label" for="moc-filter-week">Review W <span class="text-crm-muted font-normal">(highlight)</span></label>
      <select id="moc-filter-week" name="review_week" class="crm-filter-select" onchange="this.form.submit()">
         @foreach($filterOptions['review_weeks'] ?? [] as $week)
         <option value="{{ $week }}" @selected($filters['review_week'] === $week)>{{ $week }}</option>
         @endforeach
      </select>
   </div>

   <div class="crm-filter-field crm-filter-field--period">
      <label class="crm-filter-label" for="moc-filter-date-from">Periode YTD</label>
      <div class="crm-filter-date-range">
         <label class="crm-filter-date-box" for="moc-filter-date-from">
            <span class="crm-filter-date-display">{{ $dateFromDisplay }}</span>
            <input type="date" id="moc-filter-date-from" name="date_from" value="{{ $filters['date_from'] }}" class="crm-filter-date-input">
            <svg class="crm-filter-date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
               <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
         </label>
         <span class="crm-filter-date-sep" aria-hidden="true">—</span>
         <label class="crm-filter-date-box" for="moc-filter-date-to">
            <span class="crm-filter-date-display">{{ $dateToDisplay }}</span>
            <input type="date" id="moc-filter-date-to" name="date_to" value="{{ $filters['date_to'] }}" class="crm-filter-date-input">
            <svg class="crm-filter-date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
               <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
         </label>
      </div>
   </div>
</form>
<p class="text-xs text-crm-muted mb-4 -mt-2">Menampilkan progres YTD tahun {{ $filters['period_year'] }}. Baris dengan highlight = due date di {{ $filters['review_week'] }}.</p>

{{-- Row 1: KPI Stat Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Total Pengendalian</p>
      <p class="crm-stat-value">{{ $summary['total_luar_komitmen'] }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">arrow_upward</span>
         Luar Komitmen
      </span>
   </div>
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Total Overdue</p>
      <p class="crm-stat-value">{{ $totalOverdue }}</p>
      <span class="crm-stat-trend {{ $totalOverdue > 0 ? 'crm-stat-trend--down' : 'crm-stat-trend--up' }}">
         <span class="material-symbols-outlined text-sm">{{ $totalOverdue > 0 ? 'arrow_downward' : 'arrow_upward' }}</span>
         {{ $totalOverdue }}
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
         {{ $overallProgress }}%
      </span>
   </div>
</div>

{{-- Row 2: Category KPI --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
   @foreach(['arahan_manajemen' => 'campaign', 'rekom_insiden' => 'report', 'rekom_gr' => 'gavel'] as $key => $icon)
   @php $cat = $summary[$key]; @endphp
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">{{ $cat['label'] }}</p>
      <p class="crm-stat-value">{{ $cat['count'] }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">{{ $icon }}</span>
         {{ $cat['progress'] }}% progress
      </span>
   </div>
   @endforeach
</div>

{{-- Row 3: Charts --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
   <div class="crm-card">
      <p class="crm-card-title">Distribusi Kategori</p>
      <div class="crm-chart-wrap">
         <canvas id="mocCategoryChart"></canvas>
         <div class="crm-donut-center">
            <span class="crm-donut-total-label">Total</span>
            <span class="crm-donut-total-value">{{ $summary['total_luar_komitmen'] }}</span>
         </div>
      </div>
      <div class="crm-legend">
         @foreach($charts['category_distribution']['labels'] as $i => $label)
         <span class="crm-legend-item">
            <span class="crm-legend-dot" style="background:{{ ['#7366FF','#CFC8FF','#FFAA05'][$i] ?? '#848488' }}"></span>
            {{ $label }}
         </span>
         @endforeach
      </div>
   </div>

   <div class="crm-card">
      <p class="crm-card-title">Progress per Kategori</p>
      <div class="crm-chart-wrap">
         <canvas id="mocProgressChart"></canvas>
      </div>
   </div>

   <div class="crm-card">
      <p class="crm-card-title">Status Penyelesaian</p>
      <div class="crm-app-stack">
         <div class="crm-app-stack-seg" style="width:{{ round(($statusCompleted / $statusTotal) * 100) }}%;background:#7366FF"></div>
         <div class="crm-app-stack-seg" style="width:{{ round(($statusOnTrack / $statusTotal) * 100) }}%;background:#51BB25"></div>
         <div class="crm-app-stack-seg" style="width:{{ round(($statusRunning / $statusTotal) * 100) }}%;background:#FFAA05"></div>
         <div class="crm-app-stack-seg" style="width:{{ round(($statusNotStarted / $statusTotal) * 100) }}%;background:#FF5B5B"></div>
      </div>
      @foreach($charts['status_breakdown']['labels'] as $i => $label)
      <div class="crm-app-row">
         <span class="crm-app-row-left">
            <span class="crm-legend-dot" style="background:{{ ['#7366FF','#51BB25','#FFAA05','#FF5B5B'][$i] ?? '#848488' }}"></span>
            {{ $label }}
         </span>
         <span class="crm-app-row-pct">{{ $charts['status_breakdown']['data'][$i] }} ({{ round(($charts['status_breakdown']['data'][$i] / $statusTotal) * 100) }}%)</span>
      </div>
      @endforeach
   </div>
</div>

{{-- Row 4: Overdue + Preview + Sidebar --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
   <div class="crm-card">
      <p class="crm-card-title">Overdue per Kategori</p>
      @foreach($overdueSummary as $item)
      <div class="mb-3 last:mb-0">
         <div class="flex justify-between text-xs font-semibold mb-1">
            <span class="text-crm-muted">{{ $item['label'] }}</span>
            <span class="{{ $item['overdue'] > 0 ? 'text-[#FF5B5B]' : 'text-crm-muted' }}">{{ $item['overdue'] }}</span>
         </div>
         <div class="h-1.5 rounded-full bg-[#F4F7F9] overflow-hidden">
            <div class="h-full rounded-full bg-gradient-to-r from-[#FF5B5B] to-[#ef4444]" style="width:{{ $item['overdue'] === 0 ? 4 : round(($item['overdue'] / $maxOverdue) * 100) }}%"></div>
         </div>
      </div>
      @endforeach
   </div>

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
            @php $initials = collect(explode(' ', $item['name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''); @endphp
            <tr class="{{ !empty($item['due_in_review_week']) ? 'crm-row--review-week' : '' }}">
               <td>
                  <div class="crm-table-name">
                     <span class="crm-table-avatar" style="background:{{ $avatarColors[$index % count($avatarColors)] }}">{{ $initials }}</span>
                     <span class="truncate max-w-[130px]" title="{{ $item['name'] }}">{{ Str::limit($item['name'], 26) }}</span>
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
            <tr><td colspan="3" class="text-center text-crm-muted py-6">Tidak ada data</td></tr>
            @endforelse
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
   <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <p class="crm-card-title mb-0">{{ $activeCategoryLabel }}</p>
      <div class="crm-cat-tabs">
         @foreach($categoryLabels as $key => $label)
         <a
            href="{{ route('monitoring-safety-engineering.outside-commitment', array_merge($filters, ['category' => $key])) }}"
            class="crm-cat-tab {{ $activeCategory === $key ? 'crm-cat-tab--active' : '' }}"
         >
            <span class="material-symbols-outlined text-sm">{{ $categoryIcons[$key] ?? 'folder' }}</span>
            {{ $label }}
            <span class="crm-cat-tab-count">{{ $categoryCounts[$key] ?? 0 }}</span>
         </a>
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
         @if(count($activeItems) > 0)
         <tfoot>
            <tr class="font-bold bg-[#F4F7F9]">
               <td colspan="3" class="text-right text-[#7366FF] uppercase text-[10px] tracking-wide">Subtotal {{ $activeCategoryLabel }}</td>
               <td class="text-center">{{ $activePlan }}</td>
               <td class="text-center">{{ $activeDone }}</td>
               <td class="text-center"><span class="crm-pct {{ $pctClass($activeProgress >= 100 ? 'green' : ($activeProgress >= 50 ? 'amber' : ($activeProgress > 0 ? 'orange' : 'red'))) }}">{{ $activeProgress }}%</span></td>
               <td></td>
               <td class="text-center {{ $activeOverdue > 0 ? 'text-[#FF5B5B]' : 'text-crm-muted' }}">{{ $activeOverdue }}</td>
            </tr>
         </tfoot>
         @endif
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
      var crmOrange = '#FFAA05';

      Chart.defaults.font.family = "'Poppins', sans-serif";
      Chart.defaults.color = '#848488';
      Chart.defaults.animation.duration = 800;

      var catEl = document.getElementById('mocCategoryChart');
      if (catEl) {
         new Chart(catEl, {
            type: 'doughnut',
            data: {
               labels: chartsData.category_distribution.labels,
               datasets: [{
                  data: chartsData.category_distribution.data,
                  backgroundColor: [crmPurple, crmPurpleLight, crmOrange],
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

      var progEl = document.getElementById('mocProgressChart');
      if (progEl) {
         var ctx = progEl.getContext('2d');
         var barColors = [crmPurple, crmPurpleLight, crmOrange];
         new Chart(progEl, {
            type: 'bar',
            data: {
               labels: chartsData.progress_by_category.labels,
               datasets: [{
                  label: 'Progress %',
                  data: chartsData.progress_by_category.data,
                  backgroundColor: barColors,
                  borderRadius: { topLeft: 8, topRight: 8 },
                  borderSkipped: false,
                  maxBarThickness: 42
               }]
            },
            options: {
               indexAxis: 'y',
               responsive: true,
               maintainAspectRatio: false,
               plugins: {
                  legend: { display: false },
                  tooltip: {
                     backgroundColor: crmPurple,
                     padding: 10,
                     cornerRadius: 8,
                     callbacks: { label: function (c) { return 'Progress: ' + c.raw + '%'; } }
                  }
               },
               scales: {
                  x: {
                     beginAtZero: true,
                     max: 100,
                     ticks: { callback: function (v) { return v + '%'; }, font: { size: 10 } },
                     grid: { color: 'rgba(0,0,0,0.04)' }
                  },
                  y: { ticks: { font: { size: 10, weight: '600' } }, grid: { display: false } }
               }
            }
         });
      }
   });
</script>
@endpush
@endsection
