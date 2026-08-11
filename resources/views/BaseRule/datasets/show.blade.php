@extends('BaseRule.layouts.app')

@section('title', $dataset['label'].' — HSECM')

@push('head')
@include('BaseRule.partials.styles')
@endpush

@section('content')
@include('BaseRule.partials.page-header', [
   'title' => $dataset['label'],
   'subtitle' => 'Detail gap — filter site, perusahaan, tanggal dari–sampai, week, year, dan pencarian. Filter tanggal berlaku untuk semua sumber data.',
   'breadcrumb' => $dataset['label'],
])

@include('BaseRule.partials.filter-bar', [
   'actionRoute' => 'hsecm.datasets.show',
   'actionParams' => ['dataset' => $dataset['key']],
   'filters' => $filters,
   'filterOptions' => $filterOptions,
   'showCompany' => $dataset['has_company_filter'],
   'showSearch' => true,
   'showDate' => true,
])

@if(($filters['date_from'] ?? '') !== '' || ($filters['date_to'] ?? '') !== '')
@php
   $dateFromLabel = ($filters['date_from'] ?? '') !== ''
      ? \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y')
      : 'awal';
   $dateToLabel = ($filters['date_to'] ?? '') !== ''
      ? \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y')
      : 'akhir';
@endphp
<div class="mb-4 rounded-xl border border-teal-100 bg-teal-50/70 px-4 py-2.5 text-sm text-teal-900">
   Menampilkan data untuk rentang tanggal <strong>{{ $dateFromLabel }}</strong> s/d <strong>{{ $dateToLabel }}</strong>.
</div>
@endif
{{-- Summary chips --}}
<div class="flex flex-wrap gap-2 mb-4">
   <span class="hsecm-badge">Total: {{ number_format($summary['total'] ?? 0) }}</span>
   @isset($summary['avg_sap'])
   <span class="hsecm-badge">Avg SAP/SID: {{ $summary['avg_sap'] }}</span>
   @endisset
   @isset($summary['avg_rfid'])
   <span class="hsecm-badge">Avg RFID/SID: {{ $summary['avg_rfid'] }}</span>
   @endisset
   @isset($summary['avg_pct'])
   <span class="hsecm-badge">Avg Coverage: {{ $summary['avg_pct'] }}%</span>
   @endisset
   @isset($summary['avg_hours'])
   <span class="hsecm-badge">Avg Selisih Jam: {{ $summary['avg_hours'] }}</span>
   @endisset
   @isset($summary['avg_compliance'])
   <span class="hsecm-badge">Avg Compliance: {{ $summary['avg_compliance'] }}%</span>
   @endisset
   @isset($summary['avg_fill'])
   <span class="hsecm-badge">Avg Pengisian: {{ $summary['avg_fill'] }}%</span>
   @endisset
</div>

@php
   $currentSortBy  = $filters['sort_by']  ?? '';
   $currentSortDir = $filters['sort_dir'] ?? 'asc';
   $baseQuery = array_filter(array_merge(request()->query(), [
      'sort_by'  => null,
      'sort_dir' => null,
      'page'     => null,
   ]), static fn ($v) => $v !== null && $v !== '');
@endphp

<div class="hsecm-card rounded-2xl overflow-hidden">
   <div class="overflow-x-auto">
      <table class="hsecm-table w-full text-sm">
         <thead>
            <tr>
               @foreach($dataset['columns'] as $column => $label)
               @php
                  $isSorted   = $currentSortBy === $column;
                  $nextDir    = ($isSorted && $currentSortDir === 'asc') ? 'desc' : 'asc';
                  $sortUrl    = route('hsecm.datasets.show', array_merge($baseQuery, [
                     'dataset'  => $dataset['key'],
                     'sort_by'  => $column,
                     'sort_dir' => $nextDir,
                  ]));
               @endphp
               <th class="px-3 py-3 text-left whitespace-nowrap">
                  <a href="{{ $sortUrl }}" class="inline-flex items-center gap-1 group hover:text-primary transition-colors">
                     <span>{{ $label }}</span>
                     @if($isSorted)
                        <span class="material-symbols-outlined text-xs text-primary">
                           {{ $currentSortDir === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
                        </span>
                     @else
                        <span class="material-symbols-outlined text-xs text-slate-300 group-hover:text-slate-400 transition-colors">unfold_more</span>
                     @endif
                  </a>
               </th>
               @endforeach
            </tr>
         </thead>
         <tbody>
            @forelse($rows as $row)
            <tr class="border-t border-slate-50">
               @foreach($dataset['columns'] as $column => $label)
               @php $value = $row->{$column} ?? null; @endphp
               <td class="px-3 py-2.5 whitespace-nowrap max-w-[18rem] truncate" title="{{ is_scalar($value) ? $value : '' }}">
                  @if($value === null || $value === '')
                  <span class="text-slate-300">—</span>
                  @elseif(is_numeric($value) && (str_starts_with($column, 'pct_') || str_starts_with($column, 'Compliance') || str_starts_with($column, 'Pengisian') || str_starts_with($column, 'Tercover')))
                  <span class="font-semibold {{ (float) $value >= 80 ? 'text-emerald-700' : ((float) $value >= 50 ? 'text-amber-700' : 'text-red-600') }}">{{ $value }}%</span>
                  @else
                  {{ $value }}
                  @endif
               </td>
               @endforeach
            </tr>
            @empty
            <tr>
               <td colspan="{{ count($dataset['columns']) }}" class="px-4 py-10 text-center text-on-surface-variant">Tidak ada data untuk filter yang dipilih.</td>
            </tr>
            @endforelse
         </tbody>
      </table>
   </div>

   @if($rows->hasPages())
   <div class="px-4 py-3 border-t border-slate-100">
      {{ $rows->appends(request()->query())->links() }}
   </div>
   @endif
</div>
@endsection
