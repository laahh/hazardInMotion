@extends('BaseRule.layouts.app')

@section('title', 'Overview — HSECM Monitoring')

@push('head')
@include('BaseRule.partials.styles')
@endpush

@section('content')
@include('BaseRule.partials.page-header', [
   'title' => 'HSECM Monitoring Dashboard',
   'subtitle' => 'Monitoring SAP/RFID, CCTV coverage, TBC, task follow-up, IKK, aggregator, fatigue, dan sumber data RFID — per site & per perusahaan.',
   'breadcrumb' => 'Overview',
])

@include('BaseRule.partials.filter-bar', [
   'actionRoute' => 'hsecm.dashboard',
   'filters' => $filters,
   'filterOptions' => $filterOptions,
   'showCompany' => true,
   'showSearch' => false,
])

{{-- KPI cards --}}
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 mb-8">
   @foreach($kpis as $kpi)
   <div class="hsecm-card rounded-2xl p-4 hsecm-kpi-tone-{{ $kpi['tone'] }}">
      <div class="flex items-start justify-between gap-2">
         <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">{{ $kpi['label'] }}</p>
            <p class="text-2xl font-extrabold text-on-background mt-1">{{ $kpi['value'] }}</p>
            <p class="text-[11px] text-on-surface-variant mt-1">{{ $kpi['hint'] }}</p>
         </div>
         <span class="material-symbols-outlined text-primary text-2xl opacity-80">{{ $kpi['icon'] }}</span>
      </div>
   </div>
   @endforeach
</div>

{{-- Dataset shortcuts --}}
<div class="mb-8">
   <h2 class="font-headline font-bold text-lg text-on-background mb-3">Dataset</h2>
   <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
      @foreach($datasets as $item)
      <a href="{{ route('hsecm.datasets.show', array_merge(['dataset' => $item['key']], array_filter($filters))) }}"
         class="hsecm-card rounded-2xl p-4 flex items-center justify-between gap-3 hover:border-teal-300 transition-colors">
         <div class="flex items-center gap-3 min-w-0">
            <span class="material-symbols-outlined text-primary text-2xl">{{ $item['icon'] }}</span>
            <div class="min-w-0">
               <p class="text-sm font-bold text-on-background truncate">{{ $item['label'] }}</p>
               <p class="text-[11px] text-on-surface-variant">{{ number_format($item['count']) }} baris (terfilter)</p>
            </div>
         </div>
         <span class="material-symbols-outlined text-on-surface-variant">chevron_right</span>
      </a>
      @endforeach
   </div>
</div>

{{-- Monitoring per Site --}}
<div class="hsecm-card rounded-2xl overflow-hidden mb-8">
   <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
      <div>
         <h2 class="font-headline font-bold text-lg text-on-background">Monitoring per Site</h2>
         <p class="text-xs text-on-surface-variant mt-0.5">Agregasi record & rata-rata metrik utama</p>
      </div>
      <span class="hsecm-badge">{{ count($bySite) }} site</span>
   </div>
   <div class="overflow-x-auto">
      <table class="hsecm-table w-full text-sm">
         <thead>
            <tr>
               <th class="px-4 py-3 text-left">Site</th>
               <th class="px-3 py-3 text-right">Total</th>
               <th class="px-3 py-3 text-right">SAP/RFID</th>
               <th class="px-3 py-3 text-right">CCTV</th>
               <th class="px-3 py-3 text-right">Avg Cov%</th>
               <th class="px-3 py-3 text-right">TBC</th>
               <th class="px-3 py-3 text-right">Overdue</th>
               <th class="px-3 py-3 text-right">Submitted</th>
               <th class="px-3 py-3 text-right">IKK</th>
               <th class="px-3 py-3 text-right">Avg IKK%</th>
               <th class="px-3 py-3 text-right">Aggregator</th>
               <th class="px-3 py-3 text-right">Avg Agg%</th>
               <th class="px-3 py-3 text-right">Fatigue</th>
               <th class="px-3 py-3 text-right">Sumber RFID</th>
            </tr>
         </thead>
         <tbody>
            @forelse($bySite as $row)
            <tr class="border-t border-slate-50">
               <td class="px-4 py-3 font-semibold text-on-background whitespace-nowrap">
                  <a class="text-primary hover:underline" href="{{ route('hsecm.dashboard', array_filter(array_merge($filters, ['site' => $row['site']]))) }}">{{ $row['site'] }}</a>
               </td>
               <td class="px-3 py-3 text-right font-bold">{{ number_format($row['total_records']) }}</td>
               <td class="px-3 py-3 text-right">{{ number_format($row['sap_rfid']) }}</td>
               <td class="px-3 py-3 text-right">{{ number_format($row['coverage_cctv']) }}</td>
               <td class="px-3 py-3 text-right">{{ $row['avg_coverage'] }}%</td>
               <td class="px-3 py-3 text-right {{ $row['tbc_blindspot'] > 0 ? 'text-red-600 font-semibold' : '' }}">{{ number_format($row['tbc_blindspot']) }}</td>
               <td class="px-3 py-3 text-right {{ $row['task_overdue'] > 0 ? 'text-red-600 font-semibold' : '' }}">{{ number_format($row['task_overdue']) }}</td>
               <td class="px-3 py-3 text-right">{{ number_format($row['task_submitted']) }}</td>
               <td class="px-3 py-3 text-right">{{ number_format($row['ikk']) }}</td>
               <td class="px-3 py-3 text-right">{{ $row['avg_ikk'] }}%</td>
               <td class="px-3 py-3 text-right">{{ number_format($row['aggregator']) }}</td>
               <td class="px-3 py-3 text-right">{{ $row['avg_aggregator'] }}%</td>
               <td class="px-3 py-3 text-right">{{ number_format($row['fatigue']) }}</td>
               <td class="px-3 py-3 text-right">{{ number_format($row['sumber_rfid']) }}</td>
            </tr>
            @empty
            <tr>
               <td colspan="14" class="px-4 py-8 text-center text-on-surface-variant">Tidak ada data untuk filter yang dipilih.</td>
            </tr>
            @endforelse
         </tbody>
      </table>
   </div>
</div>

{{-- Monitoring per Perusahaan --}}
<div class="hsecm-card rounded-2xl overflow-hidden">
   <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
      <div>
         <h2 class="font-headline font-bold text-lg text-on-background">Monitoring per Perusahaan</h2>
         <p class="text-xs text-on-surface-variant mt-0.5">Agregasi record & rata-rata compliance / pengisian</p>
      </div>
      <span class="hsecm-badge">{{ count($byCompany) }} perusahaan</span>
   </div>
   <div class="overflow-x-auto">
      <table class="hsecm-table w-full text-sm">
         <thead>
            <tr>
               <th class="px-4 py-3 text-left">Perusahaan</th>
               <th class="px-3 py-3 text-right">Total</th>
               <th class="px-3 py-3 text-right">SAP/RFID</th>
               <th class="px-3 py-3 text-right">TBC</th>
               <th class="px-3 py-3 text-right">Overdue</th>
               <th class="px-3 py-3 text-right">Submitted</th>
               <th class="px-3 py-3 text-right">IKK</th>
               <th class="px-3 py-3 text-right">Avg IKK%</th>
               <th class="px-3 py-3 text-right">Aggregator</th>
               <th class="px-3 py-3 text-right">Avg Agg%</th>
               <th class="px-3 py-3 text-right">Fatigue</th>
               <th class="px-3 py-3 text-right">Sumber RFID</th>
            </tr>
         </thead>
         <tbody>
            @forelse($byCompany as $row)
            <tr class="border-t border-slate-50">
               <td class="px-4 py-3 font-semibold text-on-background whitespace-nowrap">
                  <a class="text-primary hover:underline" href="{{ route('hsecm.dashboard', array_filter(array_merge($filters, ['perusahaan' => $row['perusahaan']]))) }}">{{ $row['perusahaan'] }}</a>
               </td>
               <td class="px-3 py-3 text-right font-bold">{{ number_format($row['total_records']) }}</td>
               <td class="px-3 py-3 text-right">{{ number_format($row['sap_rfid']) }}</td>
               <td class="px-3 py-3 text-right {{ $row['tbc_blindspot'] > 0 ? 'text-red-600 font-semibold' : '' }}">{{ number_format($row['tbc_blindspot']) }}</td>
               <td class="px-3 py-3 text-right {{ $row['task_overdue'] > 0 ? 'text-red-600 font-semibold' : '' }}">{{ number_format($row['task_overdue']) }}</td>
               <td class="px-3 py-3 text-right">{{ number_format($row['task_submitted']) }}</td>
               <td class="px-3 py-3 text-right">{{ number_format($row['ikk']) }}</td>
               <td class="px-3 py-3 text-right">{{ $row['avg_ikk'] }}%</td>
               <td class="px-3 py-3 text-right">{{ number_format($row['aggregator']) }}</td>
               <td class="px-3 py-3 text-right">{{ $row['avg_aggregator'] }}%</td>
               <td class="px-3 py-3 text-right">{{ number_format($row['fatigue']) }}</td>
               <td class="px-3 py-3 text-right">{{ number_format($row['sumber_rfid']) }}</td>
            </tr>
            @empty
            <tr>
               <td colspan="12" class="px-4 py-8 text-center text-on-surface-variant">Tidak ada data untuk filter yang dipilih.</td>
            </tr>
            @endforelse
         </tbody>
      </table>
   </div>
</div>
@endsection
