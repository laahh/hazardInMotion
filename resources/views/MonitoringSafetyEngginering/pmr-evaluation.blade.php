@extends('MonitoringSafetyEngginering.layouts.crm')

@section('title', 'Dashboard Evaluasi Efektivitas Rekayasa PMR')

@push('head')
@include('MonitoringSafetyEngginering.partials.crm-styles')
@endpush

@section('content')
@php
   $dateFromDisplay = $filters['date_from'] !== '' ? date('d/m/Y', strtotime($filters['date_from'])) : '';
   $dateToDisplay = $filters['date_to'] !== '' ? date('d/m/Y', strtotime($filters['date_to'])) : '';
   $levelCounts = $summary['level_counts'] ?? [3 => 0, 2 => 0, 1 => 0, 0 => 0];
   $levelPct = $summary['level_pct'] ?? [3 => 0, 2 => 0, 1 => 0, 0 => 0];
   $validationCounts = $summary['validation_counts'] ?? [];
   $validationPct = $summary['validation_pct'] ?? [];
   $followUpSummary = $followUpSummary ?? [];
   $priorityItems = $priorityItems ?? [];
   $fokusAnalisis = $fokusAnalisis ?? [];
   $validationMatrix = $validationMatrix ?? [];
   $kpiCards = [
      [
         'tone' => 'total',
         'label' => 'Total Rekayasa Dievaluasi',
         'value' => (int) ($summary['total'] ?? 0),
         'sub' => 'Rekayasa PMR',
      ],
      [
         'tone' => 'l1',
         'label' => 'Turun 1 Tangga',
         'value' => (int) ($levelCounts[1] ?? 0),
         'sub' => number_format((float) ($levelPct[1] ?? 0), 1) . '%',
      ],
      [
         'tone' => 'l2',
         'label' => 'Turun 2 Tangga',
         'value' => (int) ($levelCounts[2] ?? 0),
         'sub' => number_format((float) ($levelPct[2] ?? 0), 1) . '%',
      ],
      [
         'tone' => 'l3',
         'label' => 'Turun 3 Tangga',
         'value' => (int) ($levelCounts[3] ?? 0),
         'sub' => number_format((float) ($levelPct[3] ?? 0), 1) . '%',
      ],
      [
         'tone' => 'none',
         'label' => 'Belum Ada Prediksi',
         'value' => (int) ($levelCounts[0] ?? 0),
         'sub' => number_format((float) ($levelPct[0] ?? 0), 1) . '%',
      ],
      [
         'tone' => 'upgrade',
         'label' => 'Potensi Naik Level',
         'value' => (int) ($summary['upgrade_potential_count'] ?? 0),
         'sub' => number_format((float) ($summary['upgrade_potential_pct'] ?? 0), 1) . '%',
      ],
   ];
   $predictionRows = [
      ['label' => 'Turun 1 Tangga', 'count' => (int) ($levelCounts[1] ?? 0), 'pct' => (float) ($levelPct[1] ?? 0), 'tone' => 'l1'],
      ['label' => 'Turun 2 Tangga', 'count' => (int) ($levelCounts[2] ?? 0), 'pct' => (float) ($levelPct[2] ?? 0), 'tone' => 'l2'],
      ['label' => 'Turun 3 Tangga', 'count' => (int) ($levelCounts[3] ?? 0), 'pct' => (float) ($levelPct[3] ?? 0), 'tone' => 'l3'],
      ['label' => 'Belum Ada Prediksi', 'count' => (int) ($levelCounts[0] ?? 0), 'pct' => (float) ($levelPct[0] ?? 0), 'tone' => 'none'],
   ];
   $validationRows = [
      ['key' => 'effective', 'label' => 'Efektif', 'tone' => 'effective'],
      ['key' => 'partial', 'label' => 'Sebagian Efektif', 'tone' => 'partial'],
      ['key' => 'ineffective', 'label' => 'Tidak Efektif', 'tone' => 'ineffective'],
      ['key' => 'needs_data', 'label' => 'Perlu Validasi Data', 'tone' => 'needs'],
   ];
@endphp

{{-- Header + filters --}}
<div class="mse-eff-header mb-4">
   <div>
      <p class="mse-eff-kicker">Monitoring Safety Engineering</p>
      <h1 class="mse-eff-title">Dashboard Evaluasi Efektivitas Rekayasa</h1>
      <p class="mse-eff-subtitle">Validasi Prediksi Penurunan Nilai Risiko berbasis Hazard &amp; Incident · PMR 2023–2025</p>
   </div>
   <form method="GET" action="{{ route('monitoring-safety-engineering.pmr-evaluation') }}" class="mse-eff-filters">
      <div class="crm-filter-field crm-filter-field--period">
         <label class="crm-filter-label" for="mse-pmr-date-from">Periode Data</label>
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
      <div class="crm-filter-field">
         <label class="crm-filter-label" for="mse-pmr-company">Filter</label>
         <select id="mse-pmr-company" name="company" class="crm-filter-select" onchange="this.form.submit()">
            @foreach($filterOptions['companies'] ?? [] as $key => $label)
            <option value="{{ $key }}" @selected($filters['company'] === (string) $key)>{{ $label }}</option>
            @endforeach
         </select>
      </div>
   </form>
</div>

{{-- KPI row --}}
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-4">
   @foreach($kpiCards as $card)
   <div class="mse-eff-kpi mse-eff-kpi--{{ $card['tone'] }}">
      <p class="mse-eff-kpi-label">{{ $card['label'] }}</p>
      <p class="mse-eff-kpi-value">{{ number_format($card['value']) }}</p>
      <p class="mse-eff-kpi-sub">{{ $card['sub'] }}</p>
   </div>
   @endforeach
</div>

{{-- Mid: prediksi + matriks + validasi --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
   <div class="crm-card mse-eff-card">
      <p class="mse-eff-card-title">Distribusi Prediksi Penurunan Risiko</p>
      <div class="mse-eff-split">
         <div class="mse-eff-donut-wrap">
            <canvas id="mseEffPredictionChart"></canvas>
            <div class="mse-eff-donut-center">
               <span>Total</span>
               <strong>{{ (int) $summary['total'] }}</strong>
            </div>
         </div>
         <table class="mse-eff-mini-table">
            <thead>
               <tr>
                  <th>Prediksi</th>
                  <th class="text-right">Jumlah</th>
                  <th class="text-right">%</th>
               </tr>
            </thead>
            <tbody>
               @foreach($predictionRows as $row)
               <tr>
                  <td><span class="mse-eff-dot mse-eff-dot--{{ $row['tone'] }}"></span>{{ $row['label'] }}</td>
                  <td class="text-right font-bold">{{ number_format($row['count']) }}</td>
                  <td class="text-right">{{ number_format($row['pct'], 1) }}%</td>
               </tr>
               @endforeach
            </tbody>
         </table>
      </div>
   </div>

   <div class="crm-card mse-eff-card">
      <p class="mse-eff-card-title">Matriks Validasi Efektivitas &amp; Tindak Lanjut</p>
      <p class="mse-eff-card-sub">Hazard setelah rekayasa × Incident setelah rekayasa</p>
      <div class="overflow-x-auto">
         <table class="mse-eff-matrix">
            <thead>
               <tr>
                  <th>Hazard</th>
                  <th>Incident</th>
                  <th>Validasi</th>
                  <th>Tindak Lanjut</th>
               </tr>
            </thead>
            <tbody>
               @foreach($validationMatrix as $row)
               <tr>
                  <td>{{ $row['hazard'] }}</td>
                  <td>{{ $row['insiden'] }}</td>
                  <td><span class="mse-eff-badge mse-eff-badge--{{ $row['status_key'] }}">{{ $row['status'] }}</span></td>
                  <td class="text-sm">{{ $row['follow_up'] }}</td>
               </tr>
               @endforeach
            </tbody>
         </table>
      </div>
   </div>

   <div class="crm-card mse-eff-card">
      <p class="mse-eff-card-title">Rekap Hasil Validasi Efektivitas</p>
      <div class="mse-eff-split">
         <div class="mse-eff-donut-wrap">
            <canvas id="mseEffValidationChart"></canvas>
            <div class="mse-eff-donut-center">
               <span>Total</span>
               <strong>{{ (int) $summary['total'] }}</strong>
            </div>
         </div>
         <table class="mse-eff-mini-table">
            <thead>
               <tr>
                  <th>Status Validasi</th>
                  <th class="text-right">Jumlah</th>
                  <th class="text-right">%</th>
               </tr>
            </thead>
            <tbody>
               @foreach($validationRows as $row)
               <tr>
                  <td><span class="mse-eff-dot mse-eff-dot--{{ $row['tone'] }}"></span>{{ $row['label'] }}</td>
                  <td class="text-right font-bold">{{ number_format((int) ($validationCounts[$row['key']] ?? 0)) }}</td>
                  <td class="text-right">{{ number_format((float) ($validationPct[$row['key']] ?? 0), 1) }}%</td>
               </tr>
               @endforeach
            </tbody>
         </table>
      </div>
   </div>
</div>

{{-- Bottom: tindak lanjut + fokus + prioritas --}}
<div class="grid grid-cols-1 xl:grid-cols-12 gap-4 mb-4">
   <div class="xl:col-span-4 space-y-4">
      <div class="crm-card mse-eff-card">
         <p class="mse-eff-card-title">Rekap Tindak Lanjut</p>
         <div class="mse-eff-bar-wrap">
            <canvas id="mseEffFollowUpChart"></canvas>
         </div>
         <table class="mse-eff-mini-table mt-3">
            <thead>
               <tr>
                  <th>Tindak Lanjut</th>
                  <th class="text-right">Jumlah Rekayasa</th>
               </tr>
            </thead>
            <tbody>
               @foreach($followUpSummary as $row)
               <tr>
                  <td>{{ $row['label'] }}</td>
                  <td class="text-right font-bold">{{ number_format((int) $row['count']) }}</td>
               </tr>
               @endforeach
            </tbody>
         </table>
      </div>

      <div class="crm-card mse-eff-card">
         <p class="mse-eff-card-title">Fokus Analisis</p>
         <div class="mse-eff-fokus-list">
            @foreach($fokusAnalisis as $point)
            <div class="mse-eff-fokus-item">
               <span class="mse-eff-fokus-icon">
                  <span class="material-symbols-outlined">{{ $point['icon'] }}</span>
               </span>
               <p>{{ $point['text'] }}</p>
            </div>
            @endforeach
         </div>
      </div>
   </div>

   <div class="crm-card mse-eff-card xl:col-span-8">
      <p class="mse-eff-card-title">Daftar Prioritas Rekayasa untuk Upgrade Level Efektivitas</p>
      <p class="mse-eff-card-sub">Urutan berdasarkan validasi lemah, insiden/hazard, dan potensi naik level</p>
      <div class="crm-data-table-wrap max-h-[34rem] overflow-y-auto">
         <table class="crm-data-table mse-eff-priority-table">
            <thead class="sticky top-0 z-10">
               <tr>
                  <th class="w-10">No</th>
                  <th>Pengendalian Rekayasa</th>
                  <th>Perusahaan</th>
                  <th>Prediksi</th>
                  <th class="text-center">Hazard</th>
                  <th class="text-center">Incident</th>
                  <th>Validasi</th>
                  <th>Tindak Lanjut</th>
               </tr>
            </thead>
            <tbody>
               @forelse($priorityItems as $index => $item)
               <tr>
                  <td class="text-crm-muted">{{ $index + 1 }}</td>
                  <td class="font-medium">{{ $item['name'] }}</td>
                  <td class="text-sm">{{ $item['perusahaan'] ?: '—' }}</td>
                  <td>
                     <span class="mse-eff-badge mse-eff-badge--level{{ (int) $item['level'] }}">{{ $item['level_label'] }}</span>
                  </td>
                  <td class="text-center">
                     <span class="mse-eff-flag {{ (int) $item['hazard'] > 0 ? 'mse-eff-flag--yes' : 'mse-eff-flag--no' }}">
                        {{ $item['hazard_label'] }}
                     </span>
                  </td>
                  <td class="text-center">
                     <span class="mse-eff-flag {{ (int) $item['insiden'] > 0 ? 'mse-eff-flag--yes' : 'mse-eff-flag--no' }}">
                        {{ $item['insiden_label'] }}
                     </span>
                  </td>
                  <td>
                     <span class="mse-eff-badge mse-eff-badge--{{ $item['validation_status'] }}">{{ $item['validation_label'] }}</span>
                  </td>
                  <td class="text-sm font-semibold text-[#0F766E]">{{ $item['follow_up'] }}</td>
               </tr>
               @empty
               <tr>
                  <td colspan="8" class="text-center py-10 text-crm-muted">Belum ada item prioritas pada filter ini.</td>
               </tr>
               @endforelse
            </tbody>
         </table>
      </div>
   </div>
</div>

{{-- Catatan --}}
<div class="mse-eff-footnote">
   <div>
      <strong>Catatan:</strong>
      Hazard = terkait hazard setelah pengendalian rekayasa.
      Incident = terkait insiden setelah pengendalian rekayasa.
      Prediksi dihitung dari Deteksi × Intervensi (atau nilai tersimpan jika sudah diisi).
   </div>
   <div class="mse-eff-footnote-meta">
      <span>Terakhir diperbarui: {{ now()->timezone(config('app.timezone'))->format('d M Y H:i') }} WIB</span>
      <span>Sumber data: monitoring_safety_engineering_records (PMR)</span>
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

      var charts = @json($charts);
      Chart.defaults.font.family = "'Poppins', sans-serif";
      Chart.defaults.color = '#64748B';
      Chart.defaults.animation.duration = 750;

      function makeDonut(id, payload) {
         var el = document.getElementById(id);
         if (!el) return;
         new Chart(el, {
            type: 'doughnut',
            data: {
               labels: payload.labels || [],
               datasets: [{
                  data: payload.data || [],
                  backgroundColor: payload.colors || [],
                  borderWidth: 0,
                  hoverOffset: 4,
               }],
            },
            options: {
               responsive: true,
               maintainAspectRatio: false,
               cutout: '70%',
               plugins: { legend: { display: false } },
            },
         });
      }

      makeDonut('mseEffPredictionChart', charts.prediction_distribution || {});
      makeDonut('mseEffValidationChart', charts.validation_distribution || {});

      var followEl = document.getElementById('mseEffFollowUpChart');
      if (followEl) {
         var follow = charts.follow_up_distribution || {};
         new Chart(followEl, {
            type: 'bar',
            data: {
               labels: follow.labels || [],
               datasets: [{
                  data: follow.data || [],
                  backgroundColor: follow.colors || [],
                  borderRadius: 6,
                  borderSkipped: false,
                  maxBarThickness: 22,
               }],
            },
            options: {
               indexAxis: 'y',
               responsive: true,
               maintainAspectRatio: false,
               plugins: { legend: { display: false } },
               scales: {
                  x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: 'rgba(15,23,42,0.05)' } },
                  y: { ticks: { font: { size: 10, weight: '600' } }, grid: { display: false } },
               },
            },
         });
      }
   });
</script>
@endpush
@endsection
