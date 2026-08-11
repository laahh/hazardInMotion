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
   $totalItems = $summary['total_komitmen'];
   $categoryWeight = (int) ($summary['replikasi']['count'] ?? 0)
      + (int) ($summary['safety_engineering']['count'] ?? 0)
      + (int) ($summary['additional_safety_engineering']['count'] ?? 0);
   $overallProgress = $categoryWeight > 0
      ? (int) round(
         ($summary['replikasi']['progress'] * $summary['replikasi']['count']
            + $summary['safety_engineering']['progress'] * $summary['safety_engineering']['count']
            + $summary['additional_safety_engineering']['progress'] * $summary['additional_safety_engineering']['count']
         ) / $categoryWeight
      )
      : 0;
   $categoryStatCards = [
      [
         'key' => 'replikasi',
         'label' => 'Total Replikasi',
         'title' => 'Replikasi',
         'stat' => $summary['replikasi'],
         'items' => $replikasiItems ?? [],
      ],
      [
         'key' => 'safety_engineering',
         'label' => 'Total Safety Engineering',
         'title' => 'Safety Engineering',
         'stat' => $summary['safety_engineering'],
         'items' => $safetyEngineeringItems ?? [],
      ],
      [
         'key' => 'additional_safety_engineering',
         'label' => 'Total Additional Safety',
         'title' => 'Additional Safety',
         'stat' => $summary['additional_safety_engineering'],
         'items' => $additionalSafetyItems ?? [],
      ],
   ];
   $categoryModalPayload = collect($categoryStatCards)->mapWithKeys(static function (array $card): array {
      return [
         $card['key'] => [
            'key' => $card['key'],
            'label' => $card['label'],
            'title' => $card['title'],
            'stat' => $card['stat'],
            'items' => collect($card['items'])->map(static function (array $item): array {
               $progressStatus = $item['progress_status'] ?? $item['replikasi_status'] ?? null;

               return [
                  'id' => $item['id'] ?? null,
                  'name' => $item['name'] ?? '-',
                  'unit' => $item['unit'] ?? '-',
                  'plan' => $item['plan'] ?? 0,
                  'done' => $item['done'] ?? 0,
                  'percentage' => $item['percentage'] ?? 0,
                  'percentage_color' => $item['percentage_color'] ?? 'red',
                  'due_date_label' => $item['due_date_label'] ?? '-',
                  'overdue' => $item['overdue'] ?? 0,
                  'progress_status' => $progressStatus,
                  'replikasi_status' => $progressStatus,
                  'replikasi_target_komitmen' => $item['replikasi_target_komitmen'] ?? 0,
                  'replikasi_aktual' => $item['replikasi_aktual'] ?? 0,
                  'standardisasi_status' => $item['standardisasi_status'] ?? null,
                  'standardisasi_due_date' => $item['standardisasi_due_date'] ?? null,
                  'site' => $item['site'] ?? '-',
                  'perusahaan' => $item['perusahaan'] ?? '-',
               ];
            })->values()->all(),
         ],
      ];
   })->all();
   $recordDetailById = $recordDetailById ?? ($safetyEngineeringDetailById ?? []);
   $dateFromDisplay = $filters['date_from'] !== '' ? date('d/m/Y', strtotime($filters['date_from'])) : '';
   $dateToDisplay = $filters['date_to'] !== '' ? date('d/m/Y', strtotime($filters['date_to'])) : '';
   $safetyEngineeringDetailById = $safetyEngineeringDetailById ?? [];
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

{{-- Row 1: Total + Category Stat Cards --}}
@php
   $replikasiStat = $summary['replikasi'] ?? [];
   $safetyStat = $summary['safety_engineering'] ?? [];
   $additionalStat = $summary['additional_safety_engineering'] ?? [];

   // Total Pengendalian = agregat 3 card kategori utama saja
   $totalPengendalian = (int) ($replikasiStat['count'] ?? 0)
      + (int) ($safetyStat['count'] ?? 0)
      + (int) ($additionalStat['count'] ?? 0);
   $totalOnprogress = (int) ($replikasiStat['onprogress'] ?? 0)
      + (int) ($safetyStat['onprogress'] ?? 0)
      + (int) ($additionalStat['onprogress'] ?? 0);
   $totalOverdue = (int) ($replikasiStat['overdue'] ?? 0)
      + (int) ($safetyStat['overdue'] ?? 0)
      + (int) ($additionalStat['overdue'] ?? 0);
   $totalSelesai = (int) ($replikasiStat['selesai'] ?? 0)
      + (int) ($safetyStat['selesai'] ?? 0)
      + (int) ($additionalStat['selesai'] ?? 0);
   $totalSelesaiPct = $totalPengendalian > 0
      ? (int) round(($totalSelesai / $totalPengendalian) * 100)
      : 0;
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
   <div class="crm-card crm-kpi-card crm-kpi-card--total">
      <div class="crm-kpi-head">
         <div>
            <p class="crm-kpi-label">Total Pengendalian</p>
            <p class="crm-kpi-subtitle">Replikasi + Safety + Additional</p>
         </div>
         <span class="crm-kpi-icon" aria-hidden="true">
            <span class="material-symbols-outlined">analytics</span>
         </span>
      </div>
      <div class="crm-kpi-value-row">
         <p class="crm-kpi-value">{{ $totalPengendalian }}</p>
         <span class="crm-kpi-badge">{{ $totalSelesaiPct }}% selesai</span>
      </div>
      <div class="crm-kpi-metrics" role="list">
         <div class="crm-kpi-metric" role="listitem">
            <span class="crm-kpi-metric-label">On Progress</span>
            <span class="crm-kpi-metric-value crm-kpi-metric-value--info">{{ $totalOnprogress }}</span>
         </div>
         <div class="crm-kpi-metric" role="listitem">
            <span class="crm-kpi-metric-label">Overdue</span>
            <span class="crm-kpi-metric-value crm-kpi-metric-value--danger">{{ $totalOverdue }}</span>
         </div>
         <div class="crm-kpi-metric" role="listitem">
            <span class="crm-kpi-metric-label">Selesai</span>
            <span class="crm-kpi-metric-value crm-kpi-metric-value--success">{{ $totalSelesai }}</span>
         </div>
      </div>
      <div class="crm-kpi-foot">
         <div class="crm-kpi-progress-track" aria-hidden="true">
            <div class="crm-kpi-progress-fill" style="width: {{ min(100, max(0, $totalSelesaiPct)) }}%"></div>
         </div>
         <p class="crm-kpi-foot-text">Agregat dari 3 kategori utama</p>
      </div>
   </div>

   @foreach($categoryStatCards as $categoryCard)
   @php
      $stat = $categoryCard['stat'];
      $cardKey = (string) ($categoryCard['key'] ?? '');
      $isReplikasiCard = $cardKey === 'replikasi';
      $isSafetyCard = $cardKey === 'safety_engineering';
      $isAdditionalCard = $cardKey === 'additional_safety_engineering';
      $onprogressCount = (int) ($stat['onprogress'] ?? 0);
      $overdueCount = (int) ($stat['overdue'] ?? 0);
      $selesaiCount = (int) ($stat['selesai'] ?? 0);
      $totalCount = max(1, (int) ($stat['count'] ?? 0));
      $selesaiPct = (int) round(($selesaiCount / $totalCount) * 100);
      $cardTitle = $isReplikasiCard
         ? 'Total Replikasi 2026'
         : ($isSafetyCard
            ? 'Total Safety Engineering'
            : ($isAdditionalCard ? 'Total Additional Safety' : ($categoryCard['label'] ?? 'Kategori')));
      $cardSubtitle = $isReplikasiCard
         ? 'Monitoring replikasi'
         : ($isSafetyCard
            ? 'Berbasis standardisasi'
            : ($isAdditionalCard ? 'Additional engineering' : 'Kategori pengendalian'));
      $cardIcon = $isReplikasiCard
         ? 'sync_alt'
         : ($isSafetyCard ? 'engineering' : ($isAdditionalCard ? 'add_moderator' : 'category'));
      $cardTheme = $isReplikasiCard
         ? 'replikasi'
         : ($isSafetyCard ? 'safety' : ($isAdditionalCard ? 'additional' : 'default'));
   @endphp
   <div
      class="crm-card crm-kpi-card crm-kpi-card--{{ $cardTheme }} crm-kpi-card--clickable"
      role="button"
      tabindex="0"
      data-category-key="{{ $categoryCard['key'] }}"
      aria-label="Lihat detail {{ $categoryCard['title'] }}"
   >
      <div class="crm-kpi-head">
         <div>
            <p class="crm-kpi-label">{{ $cardTitle }}</p>
            <p class="crm-kpi-subtitle">{{ $cardSubtitle }}</p>
         </div>
         <span class="crm-kpi-icon" aria-hidden="true">
            <span class="material-symbols-outlined">{{ $cardIcon }}</span>
         </span>
      </div>
      <div class="crm-kpi-value-row">
         <p class="crm-kpi-value">{{ $stat['count'] ?? 0 }}</p>
         <span class="crm-kpi-badge">{{ $selesaiPct }}% selesai</span>
      </div>
      <div class="crm-kpi-metrics" role="list">
         <div class="crm-kpi-metric" role="listitem">
            <span class="crm-kpi-metric-label">On Progress</span>
            <span class="crm-kpi-metric-value crm-kpi-metric-value--info">{{ $onprogressCount }}</span>
         </div>
         <div class="crm-kpi-metric" role="listitem">
            <span class="crm-kpi-metric-label">Overdue</span>
            <span class="crm-kpi-metric-value crm-kpi-metric-value--danger">{{ $overdueCount }}</span>
         </div>
         <div class="crm-kpi-metric" role="listitem">
            <span class="crm-kpi-metric-label">Selesai</span>
            <span class="crm-kpi-metric-value crm-kpi-metric-value--success">{{ $selesaiCount }}</span>
         </div>
      </div>
      <div class="crm-kpi-foot">
         <div class="crm-kpi-progress-track" aria-hidden="true">
            <div class="crm-kpi-progress-fill" style="width: {{ min(100, max(0, $selesaiPct)) }}%"></div>
         </div>
         <p class="crm-kpi-foot-text">Klik untuk membuka detail</p>
      </div>
   </div>
   @endforeach
</div>

{{-- Trend per Kategori --}}
@php
   $categoryTrends = $charts['category_trends'] ?? [];
@endphp
@if(count($categoryTrends) > 0)
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
   @foreach($categoryTrends as $trend)
   @php
      $trendTheme = match ((string) ($trend['key'] ?? '')) {
         'replikasi' => 'replikasi',
         'safety_engineering' => 'safety',
         'additional_safety_engineering' => 'additional',
         default => 'default',
      };
      $trendDelta = (int) ($trend['trend_delta'] ?? 0);
      $trendUp = (bool) ($trend['trend_up'] ?? ($trendDelta >= 0));
      $canvasId = 'mse-trend-chart-'.($trend['key'] ?? $loop->index);
   @endphp
   <div class="crm-card crm-trend-card crm-trend-card--{{ $trendTheme }}">
      <div class="crm-trend-card-head">
         <div>
            <p class="crm-trend-card-label">Trend {{ $trend['label'] }}</p>
            <p class="crm-trend-card-subtitle">Plan vs Done · 6 bulan (due date)</p>
         </div>
         <span class="crm-trend-delta {{ $trendUp ? 'crm-trend-delta--up' : 'crm-trend-delta--down' }}">
            <span class="material-symbols-outlined text-sm">{{ $trendUp ? 'trending_up' : 'trending_down' }}</span>
            {{ $trendDelta >= 0 ? '+' : '' }}{{ $trendDelta }}%
         </span>
      </div>

      <div class="crm-trend-card-main">
         <div>
            <p class="crm-trend-progress-value">{{ (int) ($trend['progress'] ?? 0) }}%</p>
            <p class="crm-trend-progress-label">selesai dari {{ (int) ($trend['count'] ?? 0) }} item</p>
         </div>
         <div class="crm-trend-mini-metrics">
            <span class="crm-trend-chip crm-trend-chip--info">OP {{ (int) ($trend['onprogress'] ?? 0) }}</span>
            <span class="crm-trend-chip crm-trend-chip--danger">OV {{ (int) ($trend['overdue'] ?? 0) }}</span>
            <span class="crm-trend-chip crm-trend-chip--success">OK {{ (int) ($trend['selesai'] ?? 0) }}</span>
         </div>
      </div>

      <div class="crm-trend-chart-wrap">
         <canvas
            id="{{ $canvasId }}"
            class="mse-category-trend-chart"
            data-trend-key="{{ $trend['key'] }}"
         ></canvas>
      </div>

      <div class="crm-trend-card-foot">
         <span>Plan <strong>{{ number_format((int) ($trend['plan'] ?? 0)) }}</strong></span>
         <span>Done <strong>{{ number_format((int) ($trend['done'] ?? 0)) }}</strong></span>
         <span class="crm-trend-legend">
            <i style="background:{{ $trend['color'] }}"></i> Done
            <i class="crm-trend-legend-plan"></i> Plan
         </span>
      </div>
   </div>
   @endforeach
</div>
@endif

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

   <p class="text-xs text-crm-muted mb-3 flex items-center gap-1">
      <span class="material-symbols-outlined text-sm">touch_app</span>
      Klik card kategori di atas, atau baris tabel untuk membuka/menutup detail.
   </p>

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
            @php
               $rowClickable = !empty($item['id']) && isset($recordDetailById[$item['id']]);
               $rowClasses = trim(implode(' ', array_filter([
                  !empty($item['due_in_review_week']) ? 'crm-row--review-week' : '',
                  $rowClickable ? 'crm-row--clickable' : '',
               ])));
            @endphp
            <tr
               class="{{ $rowClasses }}"
               @if($rowClickable) data-record-id="{{ $item['id'] }}" role="button" tabindex="0" aria-expanded="false" aria-label="Lihat detail {{ $item['name'] }}" @endif
            >
               <td class="text-crm-muted font-medium">{{ $index + 1 }}</td>
               <td class="font-medium max-w-xs">
                  <span class="inline-flex items-start gap-1.5">
                     @if($rowClickable)
                     <span class="crm-row-expand-icon material-symbols-outlined" aria-hidden="true">expand_more</span>
                     @endif
                     <span>
                        {{ $item['name'] }}
                        @if(!empty($item['due_in_review_week']))
                        <span class="crm-review-week-badge">{{ $filters['review_week'] }}</span>
                        @endif
                     </span>
                  </span>
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

<div id="mse-category-detail-modal" class="crm-history-modal" role="dialog" aria-modal="true" aria-labelledby="mse-category-detail-title">
   <div class="crm-history-panel crm-category-panel">
      <div class="crm-history-header">
         <div>
            <p id="mse-category-detail-title" class="crm-history-title">Detail Kategori</p>
            <p id="mse-category-detail-subtitle" class="crm-history-subtitle">—</p>
         </div>
         <button type="button" id="mse-category-detail-close" class="crm-history-close" aria-label="Tutup">&times;</button>
      </div>
      <div id="mse-category-detail-body" class="crm-history-body">
         <p class="crm-history-empty">Memuat detail...</p>
      </div>
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
      var categoryTrends = chartsData.category_trends || [];
      var crmPurple = '#7366FF';

      Chart.defaults.font.family = "'Poppins', sans-serif";
      Chart.defaults.color = '#848488';
      Chart.defaults.animation.duration = 800;

      categoryTrends.forEach(function (trend) {
         var canvas = document.querySelector('.mse-category-trend-chart[data-trend-key="' + trend.key + '"]');
         if (!canvas) return;

         var color = trend.color || crmPurple;
         new Chart(canvas, {
            type: 'line',
            data: {
               labels: trend.labels || [],
               datasets: [
                  {
                     label: 'Plan',
                     data: trend.plan_series || [],
                     borderColor: '#C9CDD4',
                     backgroundColor: 'rgba(201, 205, 212, 0.12)',
                     borderWidth: 2,
                     pointRadius: 2.5,
                     pointHoverRadius: 4,
                     tension: 0.35,
                     fill: false,
                  },
                  {
                     label: 'Done',
                     data: trend.done_series || [],
                     borderColor: color,
                     backgroundColor: color + '22',
                     borderWidth: 2.5,
                     pointRadius: 3,
                     pointHoverRadius: 5,
                     tension: 0.35,
                     fill: true,
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
                        afterBody: function (items) {
                           var idx = items[0] ? items[0].dataIndex : 0;
                           var completion = (trend.completion_series || [])[idx];
                           return typeof completion === 'number' ? 'Selesai rate: ' + completion + '%' : '';
                        },
                     },
                  },
               },
               scales: {
                  x: {
                     grid: { display: false },
                     ticks: { font: { size: 10, weight: '600' }, color: '#8B8F98' },
                  },
                  y: {
                     beginAtZero: true,
                     grid: { color: 'rgba(0,0,0,0.04)' },
                     ticks: { precision: 0, font: { size: 10 }, color: '#8B8F98' },
                  },
               },
            },
         });
      });

      var categoryModalData = @json($categoryModalPayload);
      var recordDetailById = @json($recordDetailById);
      var categoryModal = document.getElementById('mse-category-detail-modal');
      var categoryPanel = categoryModal ? categoryModal.querySelector('.crm-category-panel') : null;
      var categoryBody = document.getElementById('mse-category-detail-body');
      var categoryTitle = document.getElementById('mse-category-detail-title');
      var categorySubtitle = document.getElementById('mse-category-detail-subtitle');
      var categoryClose = document.getElementById('mse-category-detail-close');
      var categoryCharts = [];
      var activeReplikasiPayload = null;
      var activeStatusPayload = null;
      var categoryModalFilters = { site: '', company: '', status: '' };
      var siteMatrixTipStore = {};
      var siteMatrixFloatTip = null;
      var siteMatrixTipBound = false;

      function isStatusBreakdownMode(payload) {
         var meta = payload?.stat?.meta_mode || '';
         return meta === 'replikasi_status'
            || meta === 'standardisasi_status'
            || meta === 'additional_status'
            || payload?.key === 'replikasi'
            || payload?.key === 'safety_engineering'
            || payload?.key === 'additional_safety_engineering';
      }

      function isSafetyEngineeringPayload(payload) {
         return payload?.key === 'safety_engineering'
            || payload?.stat?.meta_mode === 'standardisasi_status';
      }

      function isAdditionalSafetyPayload(payload) {
         return payload?.key === 'additional_safety_engineering'
            || payload?.stat?.meta_mode === 'additional_status';
      }

      function isReplikasiPayload(payload) {
         return payload?.key === 'replikasi'
            || payload?.stat?.meta_mode === 'replikasi_status';
      }

      function statusModalTitle(payload) {
         if (isSafetyEngineeringPayload(payload)) return 'Total Safety Engineering';
         if (isAdditionalSafetyPayload(payload)) return 'Total Additional Safety';
         return 'Total Replikasi 2026';
      }

      function statusModalSectionTitle(payload) {
         if (isSafetyEngineeringPayload(payload)) return 'Data Pengendalian Safety Engineering';
         if (isAdditionalSafetyPayload(payload)) return 'Data Pengendalian Additional Safety';
         return 'Data Pengendalian Replikasi 2026';
      }

      function standardisasiStatusLabel(value) {
         var key = String(value || '').toLowerCase();
         if (key === 'done') return 'Done';
         if (key === 'in_progress') return 'In Progress';
         if (key === 'not_yet') return 'Not Yet';
         return value ? String(value) : '-';
      }

      function setCategoryPanelTheme(payload) {
         if (!categoryPanel) return;
         var isXl = isStatusBreakdownMode(payload);
         var isSafety = isSafetyEngineeringPayload(payload);
         var isAdditional = isAdditionalSafetyPayload(payload);
         categoryPanel.classList.toggle('crm-category-panel--xl', !!isXl);
         categoryPanel.classList.toggle('crm-category-panel--safety', !!isSafety);
         categoryPanel.classList.toggle('crm-category-panel--additional', !!isAdditional);
      }

      function destroyCategoryCharts() {
         categoryCharts.forEach(function (chart) {
            try { chart.destroy(); } catch (e) {}
         });
         categoryCharts = [];
      }

      function setCategoryPanelSize(isXl) {
         if (!categoryPanel) return;
         categoryPanel.classList.toggle('crm-category-panel--xl', !!isXl);
         if (!isXl) {
            categoryPanel.classList.remove('crm-category-panel--safety');
            categoryPanel.classList.remove('crm-category-panel--additional');
         }
      }

      function filterCategoryItems(items, filters) {
         var site = String(filters?.site || '').trim();
         var company = String(filters?.company || '').trim();
         var status = String(filters?.status || '').trim();

         return (items || []).filter(function (item) {
            if (site !== '' && String(item.site || '').trim() !== site) return false;
            if (company !== '' && String(item.perusahaan || '').trim() !== company) return false;
            if (status !== '') {
               var itemStatus = String(item.progress_status || item.replikasi_status || '');
               if (itemStatus !== status) return false;
            }
            return true;
         });
      }

      function filterReplikasiItems(items, filters) {
         return filterCategoryItems(items, filters);
      }

      function uniqueSortedValues(items, keyName) {
         var seen = {};
         var values = [];
         (items || []).forEach(function (item) {
            var value = String(item[keyName] || '').trim();
            if (value === '' || seen[value]) return;
            seen[value] = true;
            values.push(value);
         });
         return values.sort(function (a, b) {
            return a.localeCompare(b, 'id', { sensitivity: 'base', numeric: true });
         });
      }

      function buildReplikasiStatFromItems(items, metaMode) {
         var onprogress = 0;
         var overdue = 0;
         var selesai = 0;

         (items || []).forEach(function (item) {
            var status = String(item.progress_status || item.replikasi_status || '');
            if (status === 'onprogress') onprogress += 1;
            else if (status === 'overdue') overdue += 1;
            else if (status === 'selesai') selesai += 1;
         });

         var count = (items || []).length;

         return {
            count: count,
            onprogress: onprogress,
            overdue: overdue,
            selesai: selesai,
            done: (items || []).reduce(function (sum, item) { return sum + Number(item.done || 0); }, 0),
            plan: (items || []).reduce(function (sum, item) { return sum + Number(item.plan || 0); }, 0),
            completed: selesai,
            progress: count > 0 ? Math.round((selesai / count) * 100) : 0,
            meta_mode: metaMode || 'replikasi_status',
         };
      }

      function buildReplikasiFilterOptions(items, filters) {
         var allSites = uniqueSortedValues(items, 'site');
         var companySource = filters.site
            ? (items || []).filter(function (item) { return String(item.site || '').trim() === filters.site; })
            : (items || []);

         return {
            sites: allSites,
            companies: uniqueSortedValues(companySource, 'perusahaan'),
         };
      }

      function emptySiteCompanyBucket() {
         return {
            plan: 0,
            done: 0,
            onprogress: 0,
            overdue: 0,
            selesai: 0,
            count: 0,
            items: [],
         };
      }

      function resolveCellStatus(bucket) {
         if (!bucket || bucket.count <= 0) return '';
         if (Number(bucket.overdue || 0) > 0) return 'overdue';
         if (Number(bucket.onprogress || 0) > 0) return 'onprogress';
         if (Number(bucket.selesai || 0) > 0) return 'selesai';
         return '';
      }

      function buildReplikasiSiteCompanyMatrix(items) {
         var siteMap = {};

         (items || []).forEach(function (item) {
            var site = String(item.site || '').trim() || '-';
            var company = String(item.perusahaan || '').trim() || '-';
            if (!siteMap[site]) siteMap[site] = {};
            if (!siteMap[site][company]) siteMap[site][company] = emptySiteCompanyBucket();

            var bucket = siteMap[site][company];
            bucket.plan += Number(item.plan || 0);
            bucket.done += Number(item.done || 0);
            bucket.count += 1;

            var status = String(item.progress_status || item.replikasi_status || '');
            if (status === 'onprogress') bucket.onprogress += 1;
            else if (status === 'overdue') bucket.overdue += 1;
            else if (status === 'selesai') bucket.selesai += 1;

            bucket.items.push({
               id: item.id ?? null,
               name: item.name || '-',
               plan: Number(item.plan || 0),
               done: Number(item.done || 0),
               unit: item.unit || '-',
               status: status,
               percentage: Number(item.percentage || 0),
            });
         });

         var groups = Object.keys(siteMap).sort(function (a, b) {
            return a.localeCompare(b, 'id', { sensitivity: 'base', numeric: true });
         }).map(function (site) {
            var companies = Object.keys(siteMap[site]).sort(function (a, b) {
               return a.localeCompare(b, 'id', { sensitivity: 'base', numeric: true });
            }).map(function (company) {
               var stats = siteMap[site][company];
               stats.items.sort(function (a, b) {
                  return String(a.name).localeCompare(String(b.name), 'id', { sensitivity: 'base' });
               });

               return {
                  code: company,
                  name: company,
                  key: site + '||' + company,
                  site: site,
                  stats: stats,
               };
            });

            return { site: site, companies: companies };
         });

         var columns = [];
         groups.forEach(function (group) {
            group.companies.forEach(function (company) {
               columns.push(company);
            });
         });

         return { groups: groups, columns: columns };
      }

      function ensureSiteMatrixFloatTip() {
         if (siteMatrixFloatTip && document.body.contains(siteMatrixFloatTip)) {
            return siteMatrixFloatTip;
         }

         siteMatrixFloatTip = document.createElement('div');
         siteMatrixFloatTip.id = 'mse-site-matrix-float-tip';
         siteMatrixFloatTip.className = 'crm-site-matrix-float-tip';
         siteMatrixFloatTip.setAttribute('role', 'tooltip');
         document.body.appendChild(siteMatrixFloatTip);
         return siteMatrixFloatTip;
      }

      function hideSiteMatrixFloatTip() {
         if (!siteMatrixFloatTip) return;
         siteMatrixFloatTip.classList.remove('crm-site-matrix-float-tip--open');
         siteMatrixFloatTip.innerHTML = '';
      }

      function renderSiteMatrixTipHtml(payload) {
         var items = payload.items || [];
         var stats = payload.stats || emptySiteCompanyBucket();
         var listHtml = items.map(function (item, index) {
            return '<li class="crm-site-matrix-tip-item">'
               + '<div class="crm-site-matrix-tip-item-top">'
               + '<span class="crm-site-matrix-tip-no">' + (index + 1) + '.</span>'
               + '<span class="crm-site-matrix-tip-name">' + escapeHtml(item.name) + '</span>'
               + statusPill(item.status)
               + '</div>'
               + '<div class="crm-site-matrix-tip-meta">'
               + 'Plan ' + escapeHtml(item.plan)
               + ' · Done ' + escapeHtml(item.done)
               + ' · ' + escapeHtml(item.percentage) + '%'
               + (item.unit ? ' · ' + escapeHtml(item.unit) : '')
               + '</div>'
               + '</li>';
         }).join('');

         return '<div class="crm-site-matrix-tip-head">'
            + '<p class="crm-site-matrix-tip-title">' + escapeHtml(payload.site) + ' · ' + escapeHtml(payload.company) + '</p>'
            + '<p class="crm-site-matrix-tip-subtitle">'
            + escapeHtml(stats.count) + ' item · Plan ' + escapeHtml(stats.plan)
            + ' · Done ' + escapeHtml(stats.done)
            + '</p>'
            + '</div>'
            + '<ul class="crm-site-matrix-tip-list">'
            + (listHtml || '<li class="crm-site-matrix-tip-empty">Tidak ada item</li>')
            + '</ul>';
      }

      function positionSiteMatrixFloatTip(anchor) {
         var tip = ensureSiteMatrixFloatTip();
         var rect = anchor.getBoundingClientRect();
         var tipWidth = Math.min(360, window.innerWidth - 24);
         tip.style.width = tipWidth + 'px';
         tip.style.visibility = 'hidden';
         tip.classList.add('crm-site-matrix-float-tip--open');

         var tipRect = tip.getBoundingClientRect();
         var left = rect.left + (rect.width / 2) - (tipRect.width / 2);
         left = Math.max(12, Math.min(left, window.innerWidth - tipRect.width - 12));

         var top = rect.bottom + 8;
         if (top + tipRect.height > window.innerHeight - 12) {
            top = rect.top - tipRect.height - 8;
         }
         top = Math.max(12, top);

         tip.style.left = left + 'px';
         tip.style.top = top + 'px';
         tip.style.visibility = 'visible';
      }

      function showSiteMatrixFloatTip(anchor) {
         var key = anchor.getAttribute('data-matrix-key');
         var payload = siteMatrixTipStore[key];
         if (!payload || !payload.items || payload.items.length === 0) {
            hideSiteMatrixFloatTip();
            return;
         }

         var tip = ensureSiteMatrixFloatTip();
         tip.innerHTML = renderSiteMatrixTipHtml(payload);
         positionSiteMatrixFloatTip(anchor);
      }

      function bindSiteMatrixHover(container) {
         if (!container) return;
         var tip = ensureSiteMatrixFloatTip();

         if (!siteMatrixTipBound) {
            tip.addEventListener('mouseenter', function () {
               tip.classList.add('crm-site-matrix-float-tip--open');
            });
            tip.addEventListener('mouseleave', function () {
               hideSiteMatrixFloatTip();
            });
            siteMatrixTipBound = true;
         }

         container.querySelectorAll('[data-matrix-key]').forEach(function (el) {
            el.addEventListener('mouseenter', function () {
               showSiteMatrixFloatTip(this);
            });
            el.addEventListener('mouseleave', function (event) {
               var related = event.relatedTarget;
               if (siteMatrixFloatTip && related && siteMatrixFloatTip.contains(related)) {
                  return;
               }
               hideSiteMatrixFloatTip();
            });
            el.addEventListener('focus', function () {
               showSiteMatrixFloatTip(this);
            });
            el.addEventListener('blur', function () {
               hideSiteMatrixFloatTip();
            });
         });
      }

      function matrixCellAttrs(column, extraClass) {
         var classes = ['crm-site-matrix-cell', 'crm-site-matrix-hoverable'];
         if (extraClass) classes.push(extraClass);
         return ' class="' + classes.join(' ') + '"'
            + ' data-matrix-key="' + escapeHtml(column.key) + '"'
            + ' tabindex="0"'
            + ' aria-label="Detail ' + escapeHtml(column.site) + ' ' + escapeHtml(column.code) + '"';
      }

      function matrixMetricClass(metricKey, value) {
         var num = Number(value || 0);
         if (num <= 0) return 'crm-site-matrix-cell--muted';
         if (metricKey === 'overdue') return 'crm-site-matrix-cell--danger';
         if (metricKey === 'selesai') return 'crm-site-matrix-cell--success';
         if (metricKey === 'onprogress') return 'crm-site-matrix-cell--info';
         return 'crm-site-matrix-cell--strong';
      }

      function renderSiteCompanyProgressMatrix(items, options) {
         var opts = options || {};
         var title = opts.title || 'Ringkasan Plan / Done per Site';
         var subtitle = opts.subtitle
            || 'Kolom Site → Perusahaan · hover sel untuk melihat daftar item';
         var matrix = buildReplikasiSiteCompanyMatrix(items);
         var groups = matrix.groups || [];
         var columns = matrix.columns || [];
         var columnCount = columns.length;

         siteMatrixTipStore = {};
         columns.forEach(function (column) {
            siteMatrixTipStore[column.key] = {
               site: column.site,
               company: column.code,
               stats: column.stats,
               items: (column.stats && column.stats.items) ? column.stats.items : [],
            };
         });

         if (columnCount === 0) {
            return '<div class="crm-site-matrix-card">'
               + '<div class="crm-site-matrix-head">'
               + '<div><p class="crm-site-matrix-title">' + escapeHtml(title) + '</p>'
               + '<p class="crm-site-matrix-subtitle">' + escapeHtml(subtitle) + '</p></div>'
               + '</div>'
               + '<p class="crm-history-empty">Tidak ada data untuk matrix ini.</p>'
               + '</div>';
         }

         var metricRows = [
            { key: 'plan', label: 'Plan', type: 'number' },
            { key: 'done', label: 'Done', type: 'number' },
            { key: 'onprogress', label: 'On Progress', type: 'number' },
            { key: 'overdue', label: 'Overdue', type: 'number' },
            { key: 'selesai', label: 'Selesai', type: 'number' },
            { key: 'status', label: 'Status Dominan', type: 'status' },
         ];

         var siteHeader = groups.map(function (group) {
            return '<th colspan="' + Math.max(1, group.companies.length) + '" class="crm-site-matrix-site">'
               + escapeHtml(group.site)
               + '</th>';
         }).join('');

         var companyHeader = groups.map(function (group) {
            return group.companies.map(function (company) {
               return '<th class="crm-site-matrix-company crm-site-matrix-hoverable"'
                  + ' data-matrix-key="' + escapeHtml(company.key) + '"'
                  + ' tabindex="0"'
                  + ' title="Hover untuk detail item"'
                  + ' aria-label="Detail ' + escapeHtml(group.site) + ' ' + escapeHtml(company.code) + '">'
                  + escapeHtml(company.code)
                  + '</th>';
            }).join('');
         }).join('');

         var bodyRows = metricRows.map(function (metric) {
            var cells = columns.map(function (column) {
               var stats = column.stats || emptySiteCompanyBucket();

               if (metric.type === 'status') {
                  var status = resolveCellStatus(stats);
                  return '<td' + matrixCellAttrs(column, 'crm-site-matrix-cell--status') + '>'
                     + (status ? statusPill(status) : '<span class="crm-site-matrix-empty">—</span>')
                     + '</td>';
               }

               var value = Number(stats[metric.key] || 0);
               return '<td' + matrixCellAttrs(column, matrixMetricClass(metric.key, value)) + '>'
                  + escapeHtml(value)
                  + '</td>';
            }).join('');

            return '<tr>'
               + '<th scope="row" class="crm-site-matrix-metric">' + escapeHtml(metric.label) + '</th>'
               + cells
               + '</tr>';
         }).join('');

         return '<div class="crm-site-matrix-card">'
            + '<div class="crm-site-matrix-head">'
            + '<div>'
            + '<p class="crm-site-matrix-title">' + escapeHtml(title) + '</p>'
            + '<p class="crm-site-matrix-subtitle">' + escapeHtml(subtitle) + ' · hover sel untuk detail item</p>'
            + '</div>'
            + '<span class="crm-site-matrix-badge">' + columnCount + ' kolom</span>'
            + '</div>'
            + '<div class="crm-site-matrix-wrap">'
            + '<table class="crm-site-matrix-table">'
            + '<thead>'
            + '<tr>'
            + '<th rowspan="2" class="crm-site-matrix-corner">Metrik</th>'
            + siteHeader
            + '</tr>'
            + '<tr>' + companyHeader + '</tr>'
            + '</thead>'
            + '<tbody>' + bodyRows + '</tbody>'
            + '</table>'
            + '</div>'
            + '</div>';
      }

      function renderReplikasiSiteCompanyMatrix(items) {
         return renderSiteCompanyProgressMatrix(items, {
            title: 'Ringkasan Plan / Done per Site',
            subtitle: 'Kolom Site → Perusahaan · Plan, Done, On Progress, Overdue, Selesai',
         });
      }

      function renderSelectOptions(values, selected, allLabel) {
         return '<option value="">' + escapeHtml(allLabel) + '</option>'
            + (values || []).map(function (value) {
               return '<option value="' + escapeHtml(value) + '"'
                  + (selected === value ? ' selected' : '')
                  + '>' + escapeHtml(value) + '</option>';
            }).join('');
      }

      function statusPill(status) {
         var key = String(status || '').toLowerCase();
         var label = key === 'onprogress' ? 'Onprogress'
            : (key === 'overdue' ? 'Overdue' : (key === 'selesai' ? 'Selesai' : (status || '-')));
         var cls = key === 'onprogress' || key === 'overdue' || key === 'selesai'
            ? 'crm-status-pill crm-status-pill--' + key
            : 'crm-status-pill';
         return '<span class="' + cls + '">' + escapeHtml(label) + '</span>';
      }

      function aggregateByKey(items, keyName, valueFn) {
         var map = {};
         items.forEach(function (item) {
            var key = String(item[keyName] || '-');
            if (!map[key]) map[key] = { label: key, count: 0, plan: 0, done: 0 };
            map[key].count += 1;
            map[key].plan += Number(item.plan || 0);
            map[key].done += Number(item.done || 0);
            if (typeof valueFn === 'function') valueFn(map[key], item);
         });
         return Object.values(map).sort(function (a, b) { return b.count - a.count; });
      }

      function progressBuckets(items) {
         var buckets = [
            { label: '0–25%', count: 0 },
            { label: '26–50%', count: 0 },
            { label: '51–75%', count: 0 },
            { label: '76–100%', count: 0 },
         ];
         items.forEach(function (item) {
            var pct = Math.max(0, Number(item.percentage || 0));
            if (pct <= 25) buckets[0].count += 1;
            else if (pct <= 50) buckets[1].count += 1;
            else if (pct <= 75) buckets[2].count += 1;
            else buckets[3].count += 1;
         });
         return buckets;
      }

      function escapeHtml(value) {
         return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
      }

      function pctClass(color) {
         if (color === 'green') return 'crm-pct--green';
         if (color === 'amber') return 'crm-pct--amber';
         if (color === 'orange') return 'crm-pct--orange';
         return 'crm-pct--red';
      }

      function syncBodyScroll() {
         var anyOpen = categoryModal?.classList.contains('crm-history-modal--open');
         document.body.style.overflow = anyOpen ? 'hidden' : '';
      }

      function closeRowCollapse(row) {
         if (!row) return;
         var next = row.nextElementSibling;
         if (next && next.classList.contains('crm-row-collapse')) {
            next.remove();
         }
         row.classList.remove('crm-row--expanded');
         row.setAttribute('aria-expanded', 'false');
      }

      function closeAllRowCollapses(scope) {
         var root = scope || document;
         root.querySelectorAll('tr.crm-row--expanded').forEach(function (row) {
            closeRowCollapse(row);
         });
         root.querySelectorAll('tr.crm-row-collapse').forEach(function (el) {
            el.remove();
         });
      }

      function toggleRowDetail(row) {
         var recordId = row.getAttribute('data-record-id');
         if (!recordId) return;

         var detail = recordDetailById[String(recordId)] || recordDetailById[recordId];
         if (!detail) return;

         var tbody = row.parentElement;
         var next = row.nextElementSibling;
         var isOpen = next
            && next.classList.contains('crm-row-collapse')
            && next.getAttribute('data-detail-for') === String(recordId);

         closeAllRowCollapses(tbody);

         if (isOpen) return;

         var colCount = row.children.length;
         var subtitle = [detail.site, detail.perusahaan, detail.sumber_rekayasa].filter(Boolean).join(' · ');
         var detailRow = document.createElement('tr');
         detailRow.className = 'crm-row-collapse';
         detailRow.setAttribute('data-detail-for', String(recordId));
         detailRow.innerHTML = '<td colspan="' + colCount + '">'
            + '<div class="crm-row-collapse-panel">'
            + '<div class="crm-row-collapse-head">'
            + '<div>'
            + '<p class="crm-row-collapse-title">' + escapeHtml(detail.pengendalian_rekayasa || 'Detail Pengendalian') + '</p>'
            + (subtitle ? '<p class="crm-row-collapse-subtitle">' + escapeHtml(subtitle) + '</p>' : '')
            + '</div>'
            + '<button type="button" class="crm-row-collapse-close" aria-label="Tutup detail">&times;</button>'
            + '</div>'
            + '<div class="crm-row-collapse-body">' + renderDetail(detail) + '</div>'
            + '</div>'
            + '</td>';

         row.after(detailRow);
         row.classList.add('crm-row--expanded');
         row.setAttribute('aria-expanded', 'true');

         var panel = detailRow.querySelector('.crm-row-collapse-panel');
         requestAnimationFrame(function () {
            detailRow.classList.add('crm-row-collapse--open');
            if (panel) {
               panel.style.maxHeight = panel.scrollHeight + 'px';
            }
            try {
               detailRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } catch (e) {
               // ignore scroll errors
            }
         });

         var closeBtn = detailRow.querySelector('.crm-row-collapse-close');
         if (closeBtn) {
            closeBtn.addEventListener('click', function (event) {
               event.stopPropagation();
               closeRowCollapse(row);
            });
         }
      }

      function renderDetail(detail) {
         if (!detail) {
            return '<p class="crm-history-empty">Detail tidak tersedia.</p>';
         }

         var phasesHtml = (detail.phases || []).map(function (phase) {
            return '<tr>'
               + '<td>' + escapeHtml(phase.label) + '</td>'
               + '<td>' + escapeHtml(phase.status) + '</td>'
               + '<td>' + escapeHtml(phase.due_date) + '</td>'
               + '<td>' + escapeHtml(phase.compliance) + '</td>'
               + '</tr>';
         }).join('');

         var replikasiHtml = '';
         if (detail.replikasi) {
            var r = detail.replikasi;
            replikasiHtml = '<div class="crm-detail-section">'
               + '<p class="crm-detail-section-title">Replikasi</p>'
               + '<div class="crm-detail-grid">'
               + '<div><span class="crm-detail-label">Target</span><span class="crm-detail-value">' + escapeHtml(r.target_komitmen) + ' ' + escapeHtml(r.satuan) + '</span></div>'
               + '<div><span class="crm-detail-label">Aktual</span><span class="crm-detail-value">' + escapeHtml(r.aktual) + ' ' + escapeHtml(r.satuan) + '</span></div>'
               + '<div><span class="crm-detail-label">Populasi</span><span class="crm-detail-value">' + escapeHtml(r.total_populasi) + '</span></div>'
               + '<div><span class="crm-detail-label">Due Date</span><span class="crm-detail-value">' + escapeHtml(r.due_date) + '</span></div>'
               + '</div></div>';
         }

         var flagsHtml = '<div class="crm-detail-flags">'
            + '<span class="crm-detail-flag ' + (detail.terkait_hazard ? 'crm-detail-flag--on' : '') + '">Terkait Hazard</span>'
            + '<span class="crm-detail-flag ' + (detail.terkait_insiden ? 'crm-detail-flag--on' : '') + '">Terkait Insiden</span>'
            + '<span class="crm-detail-flag ' + (detail.potensi_peningkatan_efektivitas ? 'crm-detail-flag--on' : '') + '">Potensi Efektivitas</span>'
            + '</div>';

         var analysisHtml = detail.brief_analysis_challenge
            ? '<div class="crm-detail-section"><p class="crm-detail-section-title">Brief Analysis / Challenge</p><p class="crm-detail-text">' + escapeHtml(detail.brief_analysis_challenge) + '</p></div>'
            : '';

         var todoHtml = detail.next_to_do
            ? '<div class="crm-detail-section"><p class="crm-detail-section-title">Next To Do</p><p class="crm-detail-text">' + escapeHtml(detail.next_to_do).replace(/\n/g, '<br>') + '</p></div>'
            : '';

         return '<div class="crm-detail-progress">'
            + '<div class="crm-detail-progress-value">' + escapeHtml(detail.progress.percentage) + '%</div>'
            + '<div class="crm-detail-progress-meta">'
            + escapeHtml(detail.progress.done) + ' / ' + escapeHtml(detail.progress.plan) + ' ' + escapeHtml(detail.progress.unit)
            + '</div></div>'
            + '<div class="crm-detail-section"><p class="crm-detail-section-title">Informasi Umum</p>'
            + '<div class="crm-detail-grid">'
            + '<div><span class="crm-detail-label">Site</span><span class="crm-detail-value">' + escapeHtml(detail.site) + '</span></div>'
            + '<div><span class="crm-detail-label">Perusahaan</span><span class="crm-detail-value">' + escapeHtml(detail.perusahaan) + '</span></div>'
            + '<div><span class="crm-detail-label">Aktivitas</span><span class="crm-detail-value">' + escapeHtml(detail.aktivitas) + '</span></div>'
            + '<div><span class="crm-detail-label">Sumber Rekayasa</span><span class="crm-detail-value">' + escapeHtml(detail.sumber_rekayasa) + '</span></div>'
            + '<div><span class="crm-detail-label">Pelaksana</span><span class="crm-detail-value">' + escapeHtml(detail.pelaksana_rekayasa) + '</span></div>'
            + '<div><span class="crm-detail-label">Tanggal Ideation</span><span class="crm-detail-value">' + escapeHtml(detail.tanggal_ideation) + '</span></div>'
            + '<div><span class="crm-detail-label">Tahun Periode</span><span class="crm-detail-value">' + escapeHtml(detail.period_year) + '</span></div>'
            + '<div><span class="crm-detail-label">Intervensi Deviasi</span><span class="crm-detail-value">' + escapeHtml(detail.intervensi_deviasi) + '</span></div>'
            + '<div><span class="crm-detail-label">Deteksi Deviasi</span><span class="crm-detail-value">' + escapeHtml(detail.deteksi_deviasi ?? '—') + '</span></div>'
            + '<div><span class="crm-detail-label">Prediksi Turun Tangga</span><span class="crm-detail-value">' + escapeHtml(detail.prediksi_penurunan_tangga_risiko ?? '—') + '</span></div>'
            + '</div></div>'
            + flagsHtml
            + '<div class="crm-detail-section"><p class="crm-detail-section-title">Fase Penyelesaian</p>'
            + '<table class="crm-detail-phase-table"><thead><tr><th>Fase</th><th>Status</th><th>Due Date</th><th>Compliance</th></tr></thead>'
            + '<tbody>' + (phasesHtml || '<tr><td colspan="4" class="text-center text-crm-muted py-4">Tidak ada data fase</td></tr>') + '</tbody></table></div>'
            + replikasiHtml
            + analysisHtml
            + todoHtml;
      }

      function renderCategoryModal(payload, filters) {
         var sourceItems = payload.items || [];
         var hasStatusBreakdown = isStatusBreakdownMode(payload);
         var isSafety = isSafetyEngineeringPayload(payload);
         var metaMode = payload?.stat?.meta_mode
            || (isSafety ? 'standardisasi_status' : (isAdditionalSafetyPayload(payload) ? 'additional_status' : 'replikasi_status'));
         var activeFilters = Object.assign({ site: '', company: '', status: '' }, filters || {});
         var items = hasStatusBreakdown ? filterCategoryItems(sourceItems, activeFilters) : sourceItems;
         var stat = hasStatusBreakdown
            ? buildReplikasiStatFromItems(items, metaMode)
            : (payload.stat || {});
         var filterOptions = hasStatusBreakdown
            ? buildReplikasiFilterOptions(sourceItems, activeFilters)
            : { sites: [], companies: [] };

         if (
            hasStatusBreakdown
            && activeFilters.company
            && filterOptions.companies.indexOf(activeFilters.company) === -1
         ) {
            activeFilters.company = '';
            items = filterCategoryItems(sourceItems, activeFilters);
            stat = buildReplikasiStatFromItems(items, metaMode);
            filterOptions = buildReplikasiFilterOptions(sourceItems, activeFilters);
         }

         var rowsHtml = items.map(function (item, index) {
            var clickable = item.id != null && (recordDetailById[String(item.id)] || recordDetailById[item.id]);
            var rowStatus = item.progress_status || item.replikasi_status;

            if (isSafety) {
               return '<tr class="' + (clickable ? 'crm-row--clickable' : '') + '"'
                  + (clickable ? ' data-record-id="' + escapeHtml(item.id) + '" role="button" tabindex="0" aria-expanded="false"' : '')
                  + '>'
                  + '<td class="crm-modal-col-no">' + (index + 1) + '</td>'
                  + '<td>'
                  + '<div class="crm-modal-name"><div class="crm-modal-name-top">'
                  + (clickable ? '<span class="crm-row-expand-icon material-symbols-outlined" aria-hidden="true">expand_more</span>' : '')
                  + '<span class="crm-modal-name-title">' + escapeHtml(item.name) + '</span></div></div>'
                  + '<div class="crm-modal-meta">' + escapeHtml(item.site) + ' · ' + escapeHtml(item.perusahaan) + '</div>'
                  + '</td>'
                  + '<td class="text-center"><span class="crm-modal-chip crm-modal-chip--muted">'
                  + escapeHtml(standardisasiStatusLabel(item.standardisasi_status))
                  + '</span></td>'
                  + '<td class="text-crm-muted whitespace-nowrap">' + escapeHtml(item.due_date_label) + '</td>'
                  + '<td class="text-center"><span class="crm-pct ' + pctClass(item.percentage_color) + '">' + escapeHtml(item.percentage) + '%</span></td>'
                  + '<td class="text-center">' + statusPill(rowStatus) + '</td>'
                  + '</tr>';
            }

            return '<tr class="' + (clickable ? 'crm-row--clickable' : '') + '"'
               + (clickable ? ' data-record-id="' + escapeHtml(item.id) + '" role="button" tabindex="0" aria-expanded="false"' : '')
               + '>'
               + '<td class="crm-modal-col-no">' + (index + 1) + '</td>'
               + '<td>'
               + '<div class="crm-modal-name"><div class="crm-modal-name-top">'
               + (clickable ? '<span class="crm-row-expand-icon material-symbols-outlined" aria-hidden="true">expand_more</span>' : '')
               + '<span class="crm-modal-name-title">' + escapeHtml(item.name) + '</span></div></div>'
               + '<div class="crm-modal-meta">' + escapeHtml(item.site) + ' · ' + escapeHtml(item.perusahaan) + '</div>'
               + '</td>'
               + '<td><span class="crm-modal-chip crm-modal-chip--muted">' + escapeHtml(item.unit) + '</span></td>'
               + '<td class="text-center font-semibold">' + escapeHtml(item.plan) + '</td>'
               + '<td class="text-center font-semibold">' + escapeHtml(item.done) + '</td>'
               + '<td class="text-center"><span class="crm-pct ' + pctClass(item.percentage_color) + '">' + escapeHtml(item.percentage) + '%</span></td>'
               + '<td class="text-crm-muted whitespace-nowrap">' + escapeHtml(item.due_date_label) + '</td>'
               + (hasStatusBreakdown
                  ? '<td class="text-center">' + statusPill(rowStatus) + '</td>'
                  : '<td class="text-center font-bold ' + (Number(item.overdue) > 0 ? 'text-[#FF5B5B]' : 'text-crm-muted') + '">' + escapeHtml(item.overdue) + '</td>')
               + '</tr>';
         }).join('');

         var filterHtml = '';
         if (hasStatusBreakdown) {
            filterHtml = '<div class="crm-category-modal-filters">'
               + '<div class="crm-category-modal-filter">'
               + '<label for="mse-category-filter-site">Site</label>'
               + '<select id="mse-category-filter-site">'
               + renderSelectOptions(filterOptions.sites, activeFilters.site, 'Semua Site')
               + '</select></div>'
               + '<div class="crm-category-modal-filter">'
               + '<label for="mse-category-filter-company">Perusahaan</label>'
               + '<select id="mse-category-filter-company">'
               + renderSelectOptions(filterOptions.companies, activeFilters.company, 'Semua Perusahaan')
               + '</select></div>'
               + '<div class="crm-category-modal-filter">'
               + '<label for="mse-category-filter-status">Status</label>'
               + '<select id="mse-category-filter-status">'
               + '<option value="">Semua Status</option>'
               + '<option value="onprogress"' + (activeFilters.status === 'onprogress' ? ' selected' : '') + '>Onprogress</option>'
               + '<option value="overdue"' + (activeFilters.status === 'overdue' ? ' selected' : '') + '>Overdue</option>'
               + '<option value="selesai"' + (activeFilters.status === 'selesai' ? ' selected' : '') + '>Selesai</option>'
               + '</select></div>'
               + '</div>';
         }

         var summaryHtml = '<div class="crm-category-summary">'
            + '<div class="crm-category-summary-item crm-category-summary-item--accent">'
            + '<span class="crm-category-summary-label">Total</span>'
            + '<span class="crm-category-summary-value crm-category-summary-value--lg">' + escapeHtml(stat.count || 0) + '</span>'
            + '</div>'
            + (hasStatusBreakdown
               ? (
                  '<div class="crm-category-summary-item">'
                  + '<span class="crm-category-summary-label">Onprogress</span>'
                  + '<span class="crm-category-summary-value">' + escapeHtml(stat.onprogress || 0) + '</span>'
                  + '</div>'
                  + '<div class="crm-category-summary-item">'
                  + '<span class="crm-category-summary-label">Overdue</span>'
                  + '<span class="crm-category-summary-value">' + escapeHtml(stat.overdue || 0) + '</span>'
                  + '</div>'
                  + '<div class="crm-category-summary-item">'
                  + '<span class="crm-category-summary-label">Selesai</span>'
                  + '<span class="crm-category-summary-value">' + escapeHtml(stat.selesai || 0) + '</span>'
                  + '</div>'
               )
               : (
                  '<div class="crm-category-summary-item">'
                  + '<span class="crm-category-summary-label">Overdue</span>'
                  + '<span class="crm-category-summary-value">' + escapeHtml(stat.overdue || 0) + '</span>'
                  + '</div>'
                  + '<div class="crm-category-summary-item">'
                  + '<span class="crm-category-summary-label">Selesai</span>'
                  + '<span class="crm-category-summary-value">' + escapeHtml(stat.done || 0) + '/' + escapeHtml(stat.plan || 0) + '</span>'
                  + '</div>'
                  + '<div class="crm-category-summary-item">'
                  + '<span class="crm-category-summary-label">Progress</span>'
                  + '<span class="crm-category-summary-value">' + escapeHtml(stat.progress || 0) + '%</span>'
                  + '</div>'
               ))
            + '</div>';

         var chartsHtml = '';
         if (hasStatusBreakdown) {
            var barTitle = isSafety ? 'Selesai vs Belum per Site' : 'Plan vs Aktual per Site';
            var progressTitle = isSafety ? 'Sebaran Progress Standardisasi' : 'Sebaran Progress';
            chartsHtml = '<div class="crm-category-charts">'
               + '<div class="crm-category-chart-card">'
               + '<p class="crm-category-chart-title">Distribusi Status</p>'
               + '<div class="crm-category-chart-wrap crm-category-chart-wrap--pie"><canvas id="mse-replikasi-pie-chart"></canvas></div>'
               + '</div>'
               + '<div class="crm-category-chart-card">'
               + '<p class="crm-category-chart-title">' + barTitle + '</p>'
               + '<div class="crm-category-chart-wrap"><canvas id="mse-replikasi-bar-chart"></canvas></div>'
               + '</div>'
               + '<div class="crm-category-chart-card">'
               + '<p class="crm-category-chart-title">' + progressTitle + '</p>'
               + '<div class="crm-category-chart-wrap"><canvas id="mse-replikasi-progress-chart"></canvas></div>'
               + '</div>'
               + '</div>';
         }

         var tableHead = isSafety
            ? '<th class="crm-modal-col-no">No</th><th>Pengendalian</th>'
               + '<th class="text-center">Status Standardisasi</th><th>Due Date</th>'
               + '<th class="text-center">%</th><th class="text-center">Status</th>'
            : '<th class="crm-modal-col-no">No</th><th>Pengendalian</th><th>Satuan</th>'
               + '<th class="text-center">Plan</th><th class="text-center">Done</th>'
               + '<th class="text-center">%</th><th>Due Date</th>'
               + '<th class="text-center">' + (hasStatusBreakdown ? 'Status' : 'Overdue') + '</th>';

         var colSpan = isSafety ? 6 : 8;
         var isReplikasi = isReplikasiPayload(payload);
         var isAdditional = isAdditionalSafetyPayload(payload);
         var matrixHtml = '';
         if (hasStatusBreakdown && isReplikasi) {
            matrixHtml = renderSiteCompanyProgressMatrix(items, {
               title: 'Ringkasan Plan / Done per Site',
               subtitle: 'Kolom Site → Perusahaan · Plan, Done, On Progress, Overdue, Selesai',
            });
         } else if (hasStatusBreakdown && isSafety) {
            matrixHtml = renderSiteCompanyProgressMatrix(items, {
               title: 'Ringkasan Progress per Site',
               subtitle: 'Kolom Site → Perusahaan · Plan/Done standardisasi · On Progress, Overdue, Selesai',
            });
         } else if (hasStatusBreakdown && isAdditional) {
            matrixHtml = renderSiteCompanyProgressMatrix(items, {
               title: 'Ringkasan Plan / Done per Site',
               subtitle: 'Kolom Site → Perusahaan · Plan, Done, On Progress, Overdue, Selesai',
            });
         }

         return {
            html: filterHtml
               + summaryHtml
               + matrixHtml
               + chartsHtml
               + '<p class="crm-category-section-title">' + (hasStatusBreakdown ? statusModalSectionTitle(payload) : 'Data Pengendalian') + '</p>'
               + '<div class="crm-data-table-wrap crm-modal-table-wrap">'
               + '<table class="crm-data-table"><thead><tr>'
               + tableHead
               + '</tr></thead><tbody>'
               + (rowsHtml || '<tr><td colspan="' + colSpan + '" class="crm-modal-empty">Tidak ada data untuk filter ini.</td></tr>')
               + '</tbody></table></div>',
            stat: stat,
            items: items,
            filters: activeFilters,
            payload: payload,
         };
      }

      function mountReplikasiCategoryCharts(viewPayload) {
         destroyCategoryCharts();
         var stat = viewPayload.stat || {};
         if (
            stat.meta_mode !== 'replikasi_status'
            && stat.meta_mode !== 'standardisasi_status'
            && stat.meta_mode !== 'additional_status'
         ) return;

         var items = viewPayload.items || [];
         var pieEl = document.getElementById('mse-replikasi-pie-chart');
         var barEl = document.getElementById('mse-replikasi-bar-chart');
         var progressEl = document.getElementById('mse-replikasi-progress-chart');
         var isSafety = stat.meta_mode === 'standardisasi_status';

         if (pieEl) {
            categoryCharts.push(new Chart(pieEl, {
               type: 'pie',
               data: {
                  labels: ['Onprogress', 'Overdue', 'Selesai'],
                  datasets: [{
                     data: [
                        Number(stat.onprogress || 0),
                        Number(stat.overdue || 0),
                        Number(stat.selesai || 0),
                     ],
                     backgroundColor: ['#3B97FF', '#FF5B5B', '#51BB25'],
                     borderWidth: 0,
                     hoverOffset: 6,
                  }],
               },
               options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: {
                     legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, usePointStyle: true, pointStyle: 'circle', padding: 14 },
                     },
                     tooltip: {
                        callbacks: {
                           label: function (ctx) {
                              var total = (ctx.dataset.data || []).reduce(function (s, n) { return s + Number(n || 0); }, 0);
                              var value = Number(ctx.raw || 0);
                              var pct = total > 0 ? Math.round((value / total) * 100) : 0;
                              return ' ' + ctx.label + ': ' + value + ' (' + pct + '%)';
                           },
                        },
                     },
                  },
               },
            }));
         }

         if (barEl) {
            var bySite = aggregateByKey(items, 'site').slice(0, 8);
            var barDatasets = isSafety
               ? [
                  {
                     label: 'Selesai',
                     data: bySite.map(function (row) {
                        return items.filter(function (item) {
                           return String(item.site || '-') === row.label
                              && String(item.progress_status || item.replikasi_status || '') === 'selesai';
                        }).length;
                     }),
                     backgroundColor: '#51BB25',
                     borderRadius: 6,
                     maxBarThickness: 22,
                  },
                  {
                     label: 'Belum selesai',
                     data: bySite.map(function (row) {
                        return items.filter(function (item) {
                           return String(item.site || '-') === row.label
                              && String(item.progress_status || item.replikasi_status || '') !== 'selesai';
                        }).length;
                     }),
                     backgroundColor: '#CFC8FF',
                     borderRadius: 6,
                     maxBarThickness: 22,
                  },
               ]
               : [
                  {
                     label: 'Plan',
                     data: bySite.map(function (row) { return row.plan; }),
                     backgroundColor: '#CFC8FF',
                     borderRadius: 6,
                     maxBarThickness: 22,
                  },
                  {
                     label: 'Aktual',
                     data: bySite.map(function (row) { return row.done; }),
                     backgroundColor: '#7366FF',
                     borderRadius: 6,
                     maxBarThickness: 22,
                  },
               ];

            categoryCharts.push(new Chart(barEl, {
               type: 'bar',
               data: {
                  labels: bySite.map(function (row) { return row.label; }),
                  datasets: barDatasets,
               },
               options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: {
                     legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, usePointStyle: true, pointStyle: 'circle', padding: 12 },
                     },
                  },
                  scales: {
                     x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, font: { size: 10 } } },
                     y: { beginAtZero: true, grid: { color: '#F1F2F4' }, ticks: { precision: 0 } },
                  },
               },
            }));
         }

         if (progressEl) {
            var buckets = progressBuckets(items);
            categoryCharts.push(new Chart(progressEl, {
               type: 'bar',
               data: {
                  labels: buckets.map(function (row) { return row.label; }),
                  datasets: [{
                     label: 'Jumlah item',
                     data: buckets.map(function (row) { return row.count; }),
                     backgroundColor: ['#FF5B5B', '#FFAA05', '#3B97FF', '#51BB25'],
                     borderRadius: 8,
                     maxBarThickness: 36,
                  }],
               },
               options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: { legend: { display: false } },
                  scales: {
                     x: { grid: { display: false } },
                     y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#F1F2F4' } },
                  },
               },
            }));
         }
      }

      function updateReplikasiSubtitle(stat) {
         categorySubtitle.textContent = (stat.count || 0) + ' pengendalian · Onprogress ' + (stat.onprogress || 0)
            + ' · Overdue ' + (stat.overdue || 0)
            + ' · Selesai ' + (stat.selesai || 0);
      }

      function bindReplikasiModalFilters() {
         var siteSelect = document.getElementById('mse-category-filter-site');
         var companySelect = document.getElementById('mse-category-filter-company');
         var statusSelect = document.getElementById('mse-category-filter-status');

         if (siteSelect) {
            siteSelect.addEventListener('change', function () {
               categoryModalFilters.site = this.value || '';
               var options = buildReplikasiFilterOptions(activeStatusPayload?.items || [], categoryModalFilters);
               if (
                  categoryModalFilters.company
                  && options.companies.indexOf(categoryModalFilters.company) === -1
               ) {
                  categoryModalFilters.company = '';
               }
               refreshReplikasiModal();
            });
         }

         if (companySelect) {
            companySelect.addEventListener('change', function () {
               categoryModalFilters.company = this.value || '';
               refreshReplikasiModal();
            });
         }

         if (statusSelect) {
            statusSelect.addEventListener('change', function () {
               categoryModalFilters.status = this.value || '';
               refreshReplikasiModal();
            });
         }
      }

      function refreshReplikasiModal() {
         if (!activeStatusPayload || !categoryBody) return;

         destroyCategoryCharts();
         var view = renderCategoryModal(activeStatusPayload, categoryModalFilters);
         updateReplikasiSubtitle(view.stat);
         categoryBody.innerHTML = view.html;
         bindModalRowClicks(categoryBody);
         bindSiteMatrixHover(categoryBody);
         bindReplikasiModalFilters();
         requestAnimationFrame(function () {
            mountReplikasiCategoryCharts(view);
         });
      }

      function bindModalRowClicks(container) {
         container.querySelectorAll('tr.crm-row--clickable[data-record-id]').forEach(function (row) {
            row.addEventListener('click', function () {
               toggleRowDetail(this);
            });
            row.addEventListener('keydown', function (event) {
               if (event.key === 'Enter' || event.key === ' ') {
                  event.preventDefault();
                  toggleRowDetail(this);
               }
            });
         });
      }

      function openCategoryModal(categoryKey) {
         var payload = categoryModalData[categoryKey];
         if (!payload || !categoryModal) return;

         destroyCategoryCharts();

         var hasStatusBreakdown = isStatusBreakdownMode(payload);
         setCategoryPanelTheme(payload);

         categoryTitle.textContent = hasStatusBreakdown
            ? statusModalTitle(payload)
            : (payload.title || 'Detail Kategori');

         if (hasStatusBreakdown) {
            activeStatusPayload = payload;
            activeReplikasiPayload = payload;
            categoryModalFilters = { site: '', company: '', status: '' };
            var view = renderCategoryModal(payload, categoryModalFilters);
            updateReplikasiSubtitle(view.stat);
            categoryBody.innerHTML = view.html;
            categoryModal.classList.add('crm-history-modal--open');
            syncBodyScroll();
            bindModalRowClicks(categoryBody);
            bindSiteMatrixHover(categoryBody);
            bindReplikasiModalFilters();
            requestAnimationFrame(function () {
               mountReplikasiCategoryCharts(view);
            });
            return;
         }

         activeStatusPayload = null;
         activeReplikasiPayload = null;
         var defaultView = renderCategoryModal(payload);
         categorySubtitle.textContent = ((payload.stat?.count || 0) + ' pengendalian · progress ' + (payload.stat?.progress || 0) + '%');
         categoryBody.innerHTML = defaultView.html;
         categoryModal.classList.add('crm-history-modal--open');
         syncBodyScroll();
         bindModalRowClicks(categoryBody);
         bindSiteMatrixHover(categoryBody);
      }

      function closeCategoryModal() {
         destroyCategoryCharts();
         setCategoryPanelSize(false);
         activeStatusPayload = null;
         activeReplikasiPayload = null;
         categoryModalFilters = { site: '', company: '', status: '' };
         closeAllRowCollapses(categoryBody);
         hideSiteMatrixFloatTip();
         siteMatrixTipStore = {};
         categoryModal?.classList.remove('crm-history-modal--open');
         syncBodyScroll();
      }

      document.querySelectorAll('.crm-kpi-card--clickable[data-category-key]').forEach(function (card) {
         card.addEventListener('click', function () {
            openCategoryModal(this.getAttribute('data-category-key'));
         });
         card.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
               event.preventDefault();
               openCategoryModal(this.getAttribute('data-category-key'));
            }
         });
      });

      document.querySelectorAll('tr.crm-row--clickable[data-record-id]').forEach(function (row) {
         row.setAttribute('aria-expanded', 'false');
         row.addEventListener('click', function () {
            toggleRowDetail(this);
         });
         row.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
               event.preventDefault();
               toggleRowDetail(this);
            }
         });
      });

      categoryClose?.addEventListener('click', closeCategoryModal);
      categoryModal?.addEventListener('click', function (event) {
         if (event.target === categoryModal) closeCategoryModal();
      });
      document.addEventListener('keydown', function (event) {
         if (event.key !== 'Escape') return;

         var openCollapse = (categoryBody || document).querySelector('tr.crm-row--expanded')
            || document.querySelector('.crm-data-table tr.crm-row--expanded, .crm-table tr.crm-row--expanded');
         if (openCollapse) {
            closeRowCollapse(openCollapse);
            return;
         }

         if (categoryModal?.classList.contains('crm-history-modal--open')) {
            closeCategoryModal();
         }
      });
   });
</script>
@endpush
@endsection
