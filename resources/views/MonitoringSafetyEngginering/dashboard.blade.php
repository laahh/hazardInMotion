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
   $totalItems = $summary['total_komitmen'];
   $overallProgress = $totalItems > 0
      ? (int) round(
         ($summary['replikasi']['progress'] * $summary['replikasi']['count']
            + $summary['safety_engineering']['progress'] * $summary['safety_engineering']['count']
            + $summary['additional_safety_engineering']['progress'] * $summary['additional_safety_engineering']['count']
         ) / $totalItems
      )
      : 0;
   $categoryStatCards = [
      [
         'key' => 'replikasi',
         'label' => 'total Replikasi',
         'title' => 'Replikasi',
         'stat' => $summary['replikasi'],
         'items' => $replikasiItems ?? [],
      ],
      [
         'key' => 'safety_engineering',
         'label' => 'total safety engineering',
         'title' => 'Safety Engineering',
         'stat' => $summary['safety_engineering'],
         'items' => $safetyEngineeringItems ?? [],
      ],
      [
         'key' => 'additional_safety_engineering',
         'label' => 'total additional safety',
         'title' => 'Additional Safety',
         'stat' => $summary['additional_safety_engineering'],
         'items' => $additionalSafetyItems ?? [],
      ],
   ];
   $categoryModalPayload = collect($categoryStatCards)->mapWithKeys(static function (array $card): array {
      return [
         $card['key'] => [
            'label' => $card['label'],
            'title' => $card['title'],
            'stat' => $card['stat'],
            'items' => collect($card['items'])->map(static function (array $item): array {
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
                  'site' => $item['site'] ?? '-',
                  'perusahaan' => $item['perusahaan'] ?? '-',
               ];
            })->values()->all(),
         ],
      ];
   })->all();
   $recordDetailById = $recordDetailById ?? ($safetyEngineeringDetailById ?? []);
   $riskReductionMatrix = $riskReductionMatrix ?? ['columns' => [], 'rows' => [], 'total' => 0, 'without_prediksi' => 0];
   $riskMatrixCellPayload = [];
   foreach ($riskReductionMatrix['rows'] ?? [] as $row) {
      foreach ($riskReductionMatrix['columns'] ?? [] as $column) {
         $cell = $row['cells'][$column['key']] ?? ['count' => 0, 'items' => []];
         $cellId = $row['key'].'-'.$column['key'];
         $riskMatrixCellPayload[$cellId] = [
            'title' => $row['label'],
            'subtitle' => $column['label'],
            'count' => (int) ($cell['count'] ?? 0),
            'items' => $cell['items'] ?? [],
         ];
      }
   }
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
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
   <div class="crm-card crm-stat-card">
      <p class="crm-stat-label">Total Pengendalian</p>
      <p class="crm-stat-value">{{ $summary['total_komitmen'] }}</p>
      <span class="crm-stat-trend crm-stat-trend--up">
         <span class="material-symbols-outlined text-sm">arrow_upward</span>
         +{{ $overallProgress }}%
      </span>
   </div>
   @foreach($categoryStatCards as $categoryCard)
   @php
      $stat = $categoryCard['stat'];
      $progress = (int) ($stat['progress'] ?? 0);
      $trendUp = $progress >= 50;
   @endphp
   <div
      class="crm-card crm-stat-card crm-stat-card--clickable"
      role="button"
      tabindex="0"
      data-category-key="{{ $categoryCard['key'] }}"
      aria-label="Lihat detail {{ $categoryCard['title'] }}"
   >
      <p class="crm-stat-label">{{ $categoryCard['label'] }}</p>
      <div class="crm-stat-main">
         <p class="crm-stat-value">{{ $stat['count'] ?? 0 }}</p>
         <div class="crm-stat-meta">
            <span>overdue {{ $stat['overdue'] ?? 0 }}</span>
            <span>selesai {{ $stat['done'] ?? 0 }}/{{ $stat['plan'] ?? 0 }}</span>
         </div>
      </div>
      <span class="crm-stat-trend {{ $trendUp ? 'crm-stat-trend--up' : 'crm-stat-trend--down' }}">
         <span class="material-symbols-outlined text-sm">{{ $trendUp ? 'arrow_upward' : 'arrow_downward' }}</span>
         {{ $trendUp ? '+' : '-' }}{{ $progress }}%
      </span>
   </div>
   @endforeach
</div>

{{-- Matriks Penurunan Risiko (Deteksi & Intervensi Deviasi) --}}
<div class="crm-card mb-4">
   <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 mb-3">
      <div>
         <p class="crm-card-title mb-1">Matriks Penurunan Risiko</p>
         <p class="text-xs text-crm-muted">
            Baris diklasifikasi dari Deteksi &amp; Intervensi Deviasi. Kolom dari Prediksi Penurunan Tangga Risiko.
         </p>
      </div>
      <div class="text-xs text-crm-muted whitespace-nowrap">
         Terpetakan: <strong class="text-crm-ink">{{ $riskReductionMatrix['total'] ?? 0 }}</strong>
         @if(($riskReductionMatrix['without_prediksi'] ?? 0) > 0)
         · Estimasi hierarki (belum isi prediksi): <strong class="text-[#FFAA05]">{{ $riskReductionMatrix['without_prediksi'] }}</strong>
         @endif
      </div>
   </div>
   <div class="overflow-x-auto">
      <table class="crm-matrix-table crm-risk-matrix-table">
         <thead>
            <tr>
               <th></th>
               @foreach($riskReductionMatrix['columns'] ?? [] as $column)
               <th class="text-center">{{ $column['label'] }}</th>
               @endforeach
            </tr>
         </thead>
         <tbody>
            @forelse($riskReductionMatrix['rows'] ?? [] as $row)
            <tr>
               <th scope="row" class="crm-risk-matrix-row-label">{{ $row['label'] }}</th>
               @foreach($riskReductionMatrix['columns'] ?? [] as $column)
               @php
                  $cell = $row['cells'][$column['key']] ?? ['count' => 0, 'items' => []];
                  $count = (int) ($cell['count'] ?? 0);
                  $cellId = $row['key'].'-'.$column['key'];
               @endphp
               <td class="text-center {{ $count > 0 ? 'crm-risk-matrix-cell--clickable' : '' }}"
                  @if($count > 0)
                  role="button"
                  tabindex="0"
                  data-risk-cell="{{ $cellId }}"
                  aria-label="Lihat {{ $count }} item {{ $row['label'] }} — {{ $column['label'] }}"
                  @endif
               >
                  @if($count > 0)
                  <span class="crm-risk-matrix-count">{{ $count }}</span>
                  @else
                  <span class="text-crm-muted">—</span>
                  @endif
               </td>
               @endforeach
            </tr>
            @empty
            <tr>
               <td colspan="{{ max(1, count($riskReductionMatrix['columns'] ?? []) + 1) }}" class="text-center py-8 text-crm-muted">
                  Belum ada data matriks penurunan risiko.
               </td>
            </tr>
            @endforelse
         </tbody>
      </table>
   </div>
   <p class="text-xs text-crm-muted mt-3 flex items-center gap-1">
      <span class="material-symbols-outlined text-sm">info</span>
      Jika Prediksi Penurunan Tangga belum diisi, sistem memakai estimasi dari hierarki Deteksi→Intervensi. Klik angka untuk detail.
   </p>
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
               $rowClickable = !empty($item['id']) && isset($recordDetailById[$item['id']]);
               $rowClasses = trim(implode(' ', array_filter([
                  !empty($item['due_in_review_week']) ? 'crm-row--review-week' : '',
                  $rowClickable ? 'crm-row--clickable' : '',
               ])));
            @endphp
            <tr
               class="{{ $rowClasses }}"
               @if($rowClickable) data-record-id="{{ $item['id'] }}" role="button" tabindex="0" aria-label="Lihat detail {{ $item['name'] }}" @endif
            >
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

   <p class="text-xs text-crm-muted mb-3 flex items-center gap-1">
      <span class="material-symbols-outlined text-sm">touch_app</span>
      Klik card kategori di atas atau baris tabel untuk melihat detail data.
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
               @if($rowClickable) data-record-id="{{ $item['id'] }}" role="button" tabindex="0" aria-label="Lihat detail {{ $item['name'] }}" @endif
            >
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

<div id="mse-record-detail-modal" class="crm-history-modal" role="dialog" aria-modal="true" aria-labelledby="mse-record-detail-title">
   <div class="crm-history-panel crm-detail-panel">
      <div class="crm-history-header">
         <div>
            <p id="mse-record-detail-title" class="crm-history-title">Detail Pengendalian</p>
            <p id="mse-record-detail-subtitle" class="crm-history-subtitle">—</p>
         </div>
         <button type="button" id="mse-record-detail-close" class="crm-history-close" aria-label="Tutup">&times;</button>
      </div>
      <div id="mse-record-detail-body" class="crm-history-body">
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

      var categoryModalData = @json($categoryModalPayload);
      var riskMatrixCellData = @json($riskMatrixCellPayload);
      var recordDetailById = @json($recordDetailById);
      var categoryModal = document.getElementById('mse-category-detail-modal');
      var categoryBody = document.getElementById('mse-category-detail-body');
      var categoryTitle = document.getElementById('mse-category-detail-title');
      var categorySubtitle = document.getElementById('mse-category-detail-subtitle');
      var categoryClose = document.getElementById('mse-category-detail-close');
      var detailModal = document.getElementById('mse-record-detail-modal');
      var detailBody = document.getElementById('mse-record-detail-body');
      var detailTitle = document.getElementById('mse-record-detail-title');
      var detailSubtitle = document.getElementById('mse-record-detail-subtitle');
      var detailClose = document.getElementById('mse-record-detail-close');

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
         var anyOpen = categoryModal?.classList.contains('crm-history-modal--open')
            || detailModal?.classList.contains('crm-history-modal--open');
         document.body.style.overflow = anyOpen ? 'hidden' : '';
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

      function renderCategoryModal(payload) {
         var stat = payload.stat || {};
         var items = payload.items || [];
         var rowsHtml = items.map(function (item, index) {
            var clickable = item.id != null && (recordDetailById[String(item.id)] || recordDetailById[item.id]);
            return '<tr class="' + (clickable ? 'crm-row--clickable' : '') + '"'
               + (clickable ? ' data-record-id="' + escapeHtml(item.id) + '" role="button" tabindex="0"' : '')
               + '>'
               + '<td class="crm-modal-col-no">' + (index + 1) + '</td>'
               + '<td>'
               + '<div class="crm-modal-name"><div class="crm-modal-name-top"><span class="crm-modal-name-title">' + escapeHtml(item.name) + '</span></div></div>'
               + '<div class="crm-modal-meta">' + escapeHtml(item.site) + ' · ' + escapeHtml(item.perusahaan) + '</div>'
               + '</td>'
               + '<td><span class="crm-modal-chip crm-modal-chip--muted">' + escapeHtml(item.unit) + '</span></td>'
               + '<td class="text-center font-semibold">' + escapeHtml(item.plan) + '</td>'
               + '<td class="text-center font-semibold">' + escapeHtml(item.done) + '</td>'
               + '<td class="text-center"><span class="crm-pct ' + pctClass(item.percentage_color) + '">' + escapeHtml(item.percentage) + '%</span></td>'
               + '<td class="text-crm-muted whitespace-nowrap">' + escapeHtml(item.due_date_label) + '</td>'
               + '<td class="text-center font-bold ' + (Number(item.overdue) > 0 ? 'text-[#FF5B5B]' : 'text-crm-muted') + '">' + escapeHtml(item.overdue) + '</td>'
               + '</tr>';
         }).join('');

         return '<div class="crm-category-summary">'
            + '<div class="crm-category-summary-item crm-category-summary-item--accent">'
            + '<span class="crm-category-summary-label">Total</span>'
            + '<span class="crm-category-summary-value crm-category-summary-value--lg">' + escapeHtml(stat.count || 0) + '</span>'
            + '</div>'
            + '<div class="crm-category-summary-item">'
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
            + '</div>'
            + '<div class="crm-data-table-wrap crm-modal-table-wrap">'
            + '<table class="crm-data-table"><thead><tr>'
            + '<th class="crm-modal-col-no">No</th><th>Pengendalian</th><th>Satuan</th>'
            + '<th class="text-center">Plan</th><th class="text-center">Done</th>'
            + '<th class="text-center">%</th><th>Due Date</th>'
            + '<th class="text-center">Overdue</th>'
            + '</tr></thead><tbody>'
            + (rowsHtml || '<tr><td colspan="8" class="crm-modal-empty">Tidak ada data untuk kategori ini.</td></tr>')
            + '</tbody></table></div>';
      }

      function shortRiskTitle(title) {
         var map = {
            'Full Automasi (Deteksi & Intervensi Alat)': 'Full Automasi',
            'Deteksi & Intervensi Manusia': 'Intervensi Manusia',
            'Menahan & Mengurangi': 'Menahan & Mengurangi',
            'Hybrid (Alat & Manusia)': 'Hybrid',
            'Eliminasi': 'Eliminasi'
         };
         return map[title] || title;
      }

      function renderRiskMatrixModal(payload) {
         var items = payload.items || [];
         var derivedCount = items.filter(function (item) { return !!item.is_derived; }).length;

         var rowsHtml = items.map(function (item, index) {
            var clickable = item.id != null && (recordDetailById[String(item.id)] || recordDetailById[item.id]);
            var derivedBadge = item.is_derived
               ? '<span class="crm-modal-badge crm-modal-badge--warn">estimasi</span>'
               : '';

            return '<tr class="' + (clickable ? 'crm-row--clickable' : '') + '"'
               + (clickable ? ' data-record-id="' + escapeHtml(item.id) + '" role="button" tabindex="0"' : '')
               + '>'
               + '<td class="crm-modal-col-no">' + (index + 1) + '</td>'
               + '<td>'
               + '<div class="crm-modal-name">'
               + '<div class="crm-modal-name-top"><span class="crm-modal-name-title">' + escapeHtml(item.name) + '</span>' + derivedBadge + '</div>'
               + '</div>'
               + '<div class="crm-modal-meta">' + escapeHtml(item.site) + ' · ' + escapeHtml(item.perusahaan) + '</div>'
               + '</td>'
               + '<td class="crm-modal-col-side"><span class="crm-modal-chip crm-modal-chip--muted">' + escapeHtml(item.intervensi_deviasi) + '</span></td>'
               + '</tr>';
         }).join('');

         return '<div class="crm-modal-toolbar">'
            + '<span class="crm-modal-stat-pill"><strong>' + escapeHtml(payload.count || items.length) + '</strong> item</span>'
            + '<span class="crm-modal-stat-pill">' + escapeHtml(payload.subtitle || '') + '</span>'
            + (derivedCount > 0
               ? '<span class="crm-modal-stat-pill crm-modal-stat-pill--soft">' + derivedCount + ' estimasi hierarki</span>'
               : '')
            + '</div>'
            + '<div class="crm-data-table-wrap crm-modal-table-wrap">'
            + '<table class="crm-data-table"><thead><tr>'
            + '<th class="crm-modal-col-no">No</th>'
            + '<th>Pengendalian Rekayasa</th>'
            + '<th class="crm-modal-col-side">Intervensi</th>'
            + '</tr></thead><tbody>'
            + (rowsHtml || '<tr><td colspan="3" class="crm-modal-empty">Tidak ada data pada sel ini.</td></tr>')
            + '</tbody></table></div>';
      }

      function bindModalRowClicks(container) {
         container.querySelectorAll('tr.crm-row--clickable[data-record-id]').forEach(function (row) {
            row.addEventListener('click', function () {
               openDetailModal(this.getAttribute('data-record-id'));
            });
            row.addEventListener('keydown', function (event) {
               if (event.key === 'Enter' || event.key === ' ') {
                  event.preventDefault();
                  openDetailModal(this.getAttribute('data-record-id'));
               }
            });
         });
      }

      function openRiskMatrixModal(cellId) {
         var payload = riskMatrixCellData[cellId];
         if (!payload || !categoryModal) return;

         var shortTitle = shortRiskTitle(payload.title || 'Matriks Penurunan Risiko');
         categoryTitle.textContent = shortTitle;
         categoryTitle.setAttribute('title', payload.title || shortTitle);
         categorySubtitle.textContent = (payload.subtitle || '') + ' · ' + (payload.count || 0) + ' pengendalian';
         categoryBody.innerHTML = renderRiskMatrixModal(payload);
         categoryModal.classList.add('crm-history-modal--open');
         syncBodyScroll();
         bindModalRowClicks(categoryBody);
      }

      function openCategoryModal(categoryKey) {
         var payload = categoryModalData[categoryKey];
         if (!payload || !categoryModal) return;

         categoryTitle.textContent = payload.title || 'Detail Kategori';
         categorySubtitle.textContent = (payload.stat?.count || 0) + ' pengendalian · progress ' + (payload.stat?.progress || 0) + '%';
         categoryBody.innerHTML = renderCategoryModal(payload);
         categoryModal.classList.add('crm-history-modal--open');
         syncBodyScroll();
         bindModalRowClicks(categoryBody);
      }

      function closeCategoryModal() {
         categoryModal?.classList.remove('crm-history-modal--open');
         syncBodyScroll();
      }

      function openDetailModal(recordId) {
         var detail = recordDetailById[String(recordId)] || recordDetailById[recordId];
         if (!detail || !detailModal) return;

         detailTitle.textContent = detail.pengendalian_rekayasa || 'Detail Pengendalian';
         detailSubtitle.textContent = [detail.site, detail.perusahaan, detail.sumber_rekayasa].filter(Boolean).join(' · ');
         detailBody.innerHTML = renderDetail(detail);
         detailModal.classList.add('crm-history-modal--open');
         syncBodyScroll();
      }

      function closeDetailModal() {
         detailModal?.classList.remove('crm-history-modal--open');
         syncBodyScroll();
      }

      document.querySelectorAll('.crm-stat-card--clickable[data-category-key]').forEach(function (card) {
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

      document.querySelectorAll('[data-risk-cell]').forEach(function (cell) {
         cell.addEventListener('click', function () {
            openRiskMatrixModal(this.getAttribute('data-risk-cell'));
         });
         cell.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
               event.preventDefault();
               openRiskMatrixModal(this.getAttribute('data-risk-cell'));
            }
         });
      });

      document.querySelectorAll('tr.crm-row--clickable[data-record-id]').forEach(function (row) {
         row.addEventListener('click', function () {
            openDetailModal(this.getAttribute('data-record-id'));
         });
         row.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
               event.preventDefault();
               openDetailModal(this.getAttribute('data-record-id'));
            }
         });
      });

      categoryClose?.addEventListener('click', closeCategoryModal);
      categoryModal?.addEventListener('click', function (event) {
         if (event.target === categoryModal) closeCategoryModal();
      });
      detailClose?.addEventListener('click', closeDetailModal);
      detailModal?.addEventListener('click', function (event) {
         if (event.target === detailModal) closeDetailModal();
      });
      document.addEventListener('keydown', function (event) {
         if (event.key !== 'Escape') return;
         if (detailModal?.classList.contains('crm-history-modal--open')) {
            closeDetailModal();
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
