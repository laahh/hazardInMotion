@extends('MonitoringSafetyEngginering.layouts.crm')

@section('title', 'Progress Penyelesaian Rekayasa — Overall Perusahaan')

@push('head')
@include('MonitoringSafetyEngginering.partials.crm-styles')
@endpush

@section('content')
@php
   $barTone = static fn (int $progress): string => match (true) {
      $progress >= 100 => 'full',
      $progress >= 70 => 'high',
      $progress >= 40 => 'mid',
      $progress > 0 => 'low',
      default => 'empty',
   };
   $dateFromDisplay = ($filters['date_from'] ?? '') !== '' ? date('d/m/Y', strtotime($filters['date_from'])) : '';
   $dateToDisplay = ($filters['date_to'] ?? '') !== '' ? date('d/m/Y', strtotime($filters['date_to'])) : '';
   $stats = $stats ?? [];
   $siteGroups = $siteGroups ?? [];
   $siteRows = $siteRows ?? [];
   $charts = $charts ?? ['site_progress' => ['labels' => [], 'data' => []], 'company_progress' => ['labels' => [], 'data' => []]];
   $categoryCols = [
      ['key' => 'total', 'label' => 'Total'],
      ['key' => 'replikasi', 'label' => 'Replikasi'],
      ['key' => 'safety_engineering', 'label' => 'Safety Eng.'],
      ['key' => 'additional_safety_engineering', 'label' => 'Additional'],
   ];
@endphp

<div class="mse-ov2-mono">
<form method="GET" action="{{ route('monitoring-safety-engineering.company-overview') }}" class="crm-filter-bar">
   <div class="crm-filter-field crm-filter-field--bar">
      <label class="crm-filter-label" for="mse-ov-filter-bar">Site</label>
      <select id="mse-ov-filter-bar" name="bar" class="crm-filter-select" onchange="this.form.submit()">
         @foreach($filterOptions['bars'] ?? [] as $key => $label)
         <option value="{{ $key }}" @selected(($filters['bar'] ?? '') === (string) $key)>{{ $label }}</option>
         @endforeach
      </select>
   </div>

   <div class="crm-filter-field crm-filter-field--company">
      <label class="crm-filter-label" for="mse-ov-filter-company">Perusahaan</label>
      <select id="mse-ov-filter-company" name="company" class="crm-filter-select" onchange="this.form.submit()">
         @foreach($filterOptions['companies'] ?? [] as $key => $label)
         <option value="{{ $key }}" @selected(($filters['company'] ?? '') === (string) $key)>{{ $label }}</option>
         @endforeach
      </select>
   </div>

   <div class="crm-filter-field crm-filter-field--dates">
      <label class="crm-filter-label" for="mse-ov-filter-date-from">Periode YTD</label>
      <div class="crm-filter-dates">
         <label class="crm-filter-date-box" for="mse-ov-filter-date-from">
            <span class="crm-filter-date-display" id="mse-ov-date-from-display">{{ $dateFromDisplay }}</span>
            <input type="date" id="mse-ov-filter-date-from" name="date_from" class="crm-filter-date-input" value="{{ $filters['date_from'] ?? '' }}" onchange="this.form.submit()">
         </label>
         <span class="crm-filter-date-sep">–</span>
         <label class="crm-filter-date-box" for="mse-ov-filter-date-to">
            <span class="crm-filter-date-display" id="mse-ov-date-to-display">{{ $dateToDisplay }}</span>
            <input type="date" id="mse-ov-filter-date-to" name="date_to" class="crm-filter-date-input" value="{{ $filters['date_to'] ?? '' }}" onchange="this.form.submit()">
         </label>
      </div>
   </div>
</form>

{{-- KPI Statistik --}}
<div class="mse-ov2-kpi-grid mb-4">
   <div class="mse-ov2-kpi mse-ov2-kpi--primary">
      <p class="mse-ov2-kpi-label">Overall Progress</p>
      <p class="mse-ov2-kpi-value">{{ (int) ($stats['progress'] ?? 0) }}%</p>
      <div class="mse-ov2-bar mt-2">
         <div class="mse-ov2-bar-track">
            <div class="mse-ov2-bar-fill mse-ov2-bar-fill--{{ $barTone((int) ($stats['progress'] ?? 0)) }}" style="width: {{ min(100, max(0, (int) ($stats['progress'] ?? 0))) }}%"></div>
         </div>
      </div>
      <p class="mse-ov2-kpi-hint">{{ number_format((int) ($stats['selesai'] ?? 0)) }} selesai dari {{ number_format((int) ($stats['items_count'] ?? 0)) }} item</p>
   </div>
   <div class="mse-ov2-kpi">
      <p class="mse-ov2-kpi-label">Site</p>
      <p class="mse-ov2-kpi-value">{{ number_format((int) ($stats['sites_count'] ?? 0)) }}</p>
      <p class="mse-ov2-kpi-hint">lokasi aktif</p>
   </div>
   <div class="mse-ov2-kpi">
      <p class="mse-ov2-kpi-label">Perusahaan</p>
      <p class="mse-ov2-kpi-value">{{ number_format((int) ($stats['companies_count'] ?? 0)) }}</p>
      <p class="mse-ov2-kpi-hint">mitra / DIC</p>
   </div>
   <div class="mse-ov2-kpi">
      <p class="mse-ov2-kpi-label">On Progress</p>
      <p class="mse-ov2-kpi-value">{{ number_format((int) ($stats['onprogress'] ?? 0)) }}</p>
      <p class="mse-ov2-kpi-hint">item berjalan</p>
   </div>
   <div class="mse-ov2-kpi">
      <p class="mse-ov2-kpi-label">Overdue</p>
      <p class="mse-ov2-kpi-value">{{ number_format((int) ($stats['overdue'] ?? 0)) }}</p>
      <p class="mse-ov2-kpi-hint">melewati due date</p>
   </div>
   <div class="mse-ov2-kpi">
      <p class="mse-ov2-kpi-label">Selesai</p>
      <p class="mse-ov2-kpi-value">{{ number_format((int) ($stats['selesai'] ?? 0)) }}</p>
      <p class="mse-ov2-kpi-hint">item selesai 100%</p>
   </div>
</div>

{{-- Progress per kategori --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
   @foreach([
      ['label' => 'Replikasi', 'progress' => (int) ($stats['replikasi_progress'] ?? 0), 'icon' => 'sync_alt'],
      ['label' => 'Safety Engineering', 'progress' => (int) ($stats['safety_progress'] ?? 0), 'icon' => 'engineering'],
      ['label' => 'Additional Safety', 'progress' => (int) ($stats['additional_progress'] ?? 0), 'icon' => 'add_moderator'],
   ] as $cat)
   <div class="mse-ov2-cat">
      <div class="mse-ov2-cat-head">
         <span class="material-symbols-outlined">{{ $cat['icon'] }}</span>
         <span>{{ $cat['label'] }}</span>
      </div>
      <p class="mse-ov2-cat-value">{{ $cat['progress'] }}%</p>
      <div class="mse-ov2-bar">
         <div class="mse-ov2-bar-track">
            <div class="mse-ov2-bar-fill mse-ov2-bar-fill--{{ $barTone($cat['progress']) }}" style="width: {{ min(100, max(0, $cat['progress'])) }}%"></div>
         </div>
      </div>
   </div>
   @endforeach
</div>

{{-- Kartu per Site --}}
<div class="crm-card mse-ov2-card mb-4">
   <div class="flex items-center justify-between gap-2 mb-3">
      <div>
         <p class="crm-card-title mb-0">Progress per Site</p>
         <p class="text-xs text-crm-muted mt-1">Ringkasan penyelesaian komitmen di tiap lokasi</p>
      </div>
   </div>

   @if(count($siteRows) === 0)
   <p class="text-sm text-crm-muted py-6 text-center">Belum ada data site untuk filter yang dipilih.</p>
   @else
   <div class="mse-ov2-site-grid">
      @foreach($siteRows as $site)
      @php $progress = (int) ($site['total']['progress'] ?? 0); @endphp
      <div class="mse-ov2-site-card">
         <div class="mse-ov2-site-card-top">
            <p class="mse-ov2-site-name">{{ $site['site'] }}</p>
            <span class="mse-ov2-site-pct">{{ $progress }}%</span>
         </div>
         <div class="mse-ov2-bar mb-2">
            <div class="mse-ov2-bar-track">
               <div class="mse-ov2-bar-fill mse-ov2-bar-fill--{{ $barTone($progress) }}" style="width: {{ min(100, max(0, $progress)) }}%"></div>
            </div>
         </div>
         <div class="mse-ov2-site-meta">
            <span>{{ (int) ($site['companies_count'] ?? 0) }} perusahaan</span>
            <span>{{ (int) ($site['total']['count'] ?? 0) }} item</span>
         </div>
         <div class="mse-ov2-site-status">
            <span class="mse-ov2-chip">OP {{ (int) ($site['total']['onprogress'] ?? 0) }}</span>
            <span class="mse-ov2-chip">OV {{ (int) ($site['total']['overdue'] ?? 0) }}</span>
            <span class="mse-ov2-chip mse-ov2-chip--strong">OK {{ (int) ($site['total']['selesai'] ?? 0) }}</span>
         </div>
         <div class="mse-ov2-site-cats">
            <div><span>Rep</span><strong>{{ (int) ($site['replikasi']['progress'] ?? 0) }}%</strong></div>
            <div><span>SE</span><strong>{{ (int) ($site['safety_engineering']['progress'] ?? 0) }}%</strong></div>
            <div><span>Add</span><strong>{{ (int) ($site['additional_safety_engineering']['progress'] ?? 0) }}%</strong></div>
         </div>
      </div>
      @endforeach
   </div>
   @endif
</div>

{{-- Charts + ranking --}}
<div class="grid grid-cols-1 xl:grid-cols-12 gap-4 mb-4">
   <div class="crm-card mse-ov2-card xl:col-span-7">
      <p class="crm-card-title">Perbandingan Progress Site</p>
      <div class="mse-ov2-chart-wrap">
         <canvas id="mse-ov-site-chart" height="220"></canvas>
      </div>
   </div>
   <div class="crm-card mse-ov2-card xl:col-span-5">
      <p class="crm-card-title">Perlu Perhatian</p>
      <p class="text-xs text-crm-muted mb-3">Perusahaan dengan progress terendah (punya item)</p>
      <div class="mse-ov2-rank-list">
         @forelse(($stats['attention_companies'] ?? []) as $row)
         @php $progress = (int) ($row['total']['progress'] ?? 0); @endphp
         <div class="mse-ov2-rank-item">
            <div class="mse-ov2-rank-main">
               <span class="mse-ov2-rank-name">{{ $row['perusahaan'] }}</span>
               <span class="mse-ov2-rank-site">{{ $row['site'] ?? '-' }}</span>
            </div>
            <div class="mse-ov2-rank-prog">
               <div class="mse-ov2-bar">
                  <div class="mse-ov2-bar-track">
                     <div class="mse-ov2-bar-fill mse-ov2-bar-fill--{{ $barTone($progress) }}" style="width: {{ min(100, max(0, $progress)) }}%"></div>
                  </div>
                  <span class="mse-ov2-pct">{{ $progress }}%</span>
               </div>
            </div>
         </div>
         @empty
         <p class="text-sm text-crm-muted py-4 text-center">Tidak ada data.</p>
         @endforelse
      </div>
   </div>
</div>

{{-- Tabel per site → perusahaan --}}
<div class="crm-card mse-ov2-card">
   <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
      <div>
         <p class="crm-card-title mb-0">Detail Progress per Site &amp; Perusahaan</p>
         <p class="text-xs text-crm-muted mt-1">
            Data sama dengan Dashboard Komitmen · OP / OV / OK &amp; % selesai
         </p>
      </div>
   </div>

   @forelse($siteGroups as $group)
   @php $siteProgress = (int) ($group['total']['progress'] ?? 0); @endphp
   <div class="mse-ov2-site-block">
      <div class="mse-ov2-site-block-head">
         <div class="mse-ov2-site-block-title">
            <span class="material-symbols-outlined">location_on</span>
            <div>
               <p class="mse-ov2-site-block-name">{{ $group['site'] }}</p>
               <p class="mse-ov2-site-block-sub">
                  {{ count($group['companies']) }} perusahaan ·
                  {{ (int) ($group['total']['count'] ?? 0) }} item ·
                  OP {{ (int) ($group['total']['onprogress'] ?? 0) }} ·
                  OV {{ (int) ($group['total']['overdue'] ?? 0) }} ·
                  OK {{ (int) ($group['total']['selesai'] ?? 0) }}
               </p>
            </div>
         </div>
         <div class="mse-ov2-site-block-progress">
            <div class="mse-ov2-bar">
               <div class="mse-ov2-bar-track mse-ov2-bar-track--lg">
                  <div class="mse-ov2-bar-fill mse-ov2-bar-fill--{{ $barTone($siteProgress) }}" style="width: {{ min(100, max(0, $siteProgress)) }}%"></div>
               </div>
               <span class="mse-ov2-pct">{{ $siteProgress }}%</span>
            </div>
         </div>
      </div>

      <div class="crm-data-table-wrap">
         <table class="crm-data-table mse-ov-table mse-ov2-detail-table">
            <thead>
               <tr>
                  <th class="w-10 text-center">No</th>
                  <th>Perusahaan</th>
                  @foreach($categoryCols as $col)
                  <th class="text-center">{{ $col['label'] }}</th>
                  @endforeach
                  <th class="text-center">Status</th>
               </tr>
            </thead>
            <tbody>
               @foreach($group['companies'] as $index => $row)
               <tr>
                  <td class="text-center text-crm-muted">{{ $index + 1 }}</td>
                  <td class="font-semibold">{{ $row['perusahaan'] }}</td>
                  @foreach($categoryCols as $col)
                  @php $p = (int) ($row[$col['key']]['progress'] ?? 0); @endphp
                  <td>
                     <div class="mse-ov2-bar">
                        <div class="mse-ov2-bar-track">
                           <div class="mse-ov2-bar-fill mse-ov2-bar-fill--{{ $barTone($p) }}" style="width: {{ min(100, max(0, $p)) }}%"></div>
                        </div>
                        <span class="mse-ov2-pct">{{ $p }}%</span>
                     </div>
                  </td>
                  @endforeach
                  <td>
                     <div class="mse-ov2-mini-status">
                        <span class="mse-ov2-chip">OP {{ (int) ($row['total']['onprogress'] ?? 0) }}</span>
                        <span class="mse-ov2-chip">OV {{ (int) ($row['total']['overdue'] ?? 0) }}</span>
                        <span class="mse-ov2-chip mse-ov2-chip--strong">OK {{ (int) ($row['total']['selesai'] ?? 0) }}</span>
                     </div>
                  </td>
               </tr>
               @endforeach
               <tr class="mse-ov2-subtotal-row">
                  <td></td>
                  <td class="font-semibold">Subtotal {{ $group['site'] }}</td>
                  @foreach($categoryCols as $col)
                  @php $p = (int) ($group[$col['key']]['progress'] ?? 0); @endphp
                  <td class="font-semibold text-center">{{ $p }}%</td>
                  @endforeach
                  <td>
                     <div class="mse-ov2-mini-status">
                        <span class="mse-ov2-chip">OP {{ (int) ($group['total']['onprogress'] ?? 0) }}</span>
                        <span class="mse-ov2-chip">OV {{ (int) ($group['total']['overdue'] ?? 0) }}</span>
                        <span class="mse-ov2-chip mse-ov2-chip--strong">OK {{ (int) ($group['total']['selesai'] ?? 0) }}</span>
                     </div>
                  </td>
               </tr>
            </tbody>
         </table>
      </div>
   </div>
   @empty
   <p class="text-sm text-crm-muted py-8 text-center">Belum ada data komitmen untuk filter yang dipilih.</p>
   @endforelse

   @if(count($siteGroups) > 0)
   <div class="mse-ov2-grand-total">
      <div>
         <p class="mse-ov2-grand-label">TOTAL KESELURUHAN</p>
         <p class="mse-ov2-grand-sub">
            {{ (int) ($stats['sites_count'] ?? 0) }} site ·
            {{ (int) ($stats['companies_count'] ?? 0) }} perusahaan ·
            {{ (int) ($stats['items_count'] ?? 0) }} item
         </p>
      </div>
      <div class="mse-ov2-grand-metrics">
         @foreach($categoryCols as $col)
         <div class="mse-ov2-grand-metric">
            <span>{{ $col['label'] }}</span>
            <strong>{{ (int) ($totals[$col['key']]['progress'] ?? 0) }}%</strong>
         </div>
         @endforeach
      </div>
   </div>
   @endif
</div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
   function bindDateDisplay(inputId, displayId) {
      var input = document.getElementById(inputId);
      var display = document.getElementById(displayId);
      if (!input || !display) return;
      function sync() {
         if (!input.value) { display.textContent = ''; return; }
         var parts = input.value.split('-');
         if (parts.length === 3) display.textContent = parts[2] + '/' + parts[1] + '/' + parts[0];
      }
      input.addEventListener('change', sync);
      sync();
   }
   bindDateDisplay('mse-ov-filter-date-from', 'mse-ov-date-from-display');
   bindDateDisplay('mse-ov-filter-date-to', 'mse-ov-date-to-display');

   var siteChartEl = document.getElementById('mse-ov-site-chart');
   if (siteChartEl && window.Chart) {
      var siteData = @json($charts['site_progress'] ?? ['labels' => [], 'data' => []]);
      var colors = (siteData.data || []).map(function (v) {
         if (v >= 100) return '#111827';
         if (v >= 70) return '#374151';
         if (v >= 40) return '#6B7280';
         if (v > 0) return '#9CA3AF';
         return '#E5E7EB';
      });
      new Chart(siteChartEl, {
         type: 'bar',
         data: {
            labels: siteData.labels || [],
            datasets: [{
               label: 'Progress %',
               data: siteData.data || [],
               backgroundColor: colors,
               borderRadius: 4,
               maxBarThickness: 36
            }]
         },
         options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
               legend: { display: false },
               tooltip: {
                  callbacks: {
                     label: function (ctx) { return (ctx.parsed.y || 0) + '%'; }
                  }
               }
            },
            scales: {
               y: {
                  beginAtZero: true,
                  max: 100,
                  ticks: {
                     color: '#6B7280',
                     callback: function (v) { return v + '%'; }
                  },
                  grid: { color: '#F3F4F6' }
               },
               x: {
                  ticks: { color: '#6B7280' },
                  grid: { display: false }
               }
            }
         }
      });
   }
});
</script>
@endpush
