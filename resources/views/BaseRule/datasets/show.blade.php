@extends('BaseRule.layouts.app')

@section('title', $dataset['label'].' — HSECM')

@push('head')
@include('BaseRule.partials.styles')
@endpush

@section('content')
@include('BaseRule.partials.page-header', [
   'title' => $dataset['label'],
   'subtitle' => 'Detail data HSECM dengan filter site, perusahaan, week, dan pencarian.',
   'breadcrumb' => $dataset['label'],
])

@include('BaseRule.partials.filter-bar', [
   'actionRoute' => 'hsecm.datasets.show',
   'actionParams' => ['dataset' => $dataset['key']],
   'filters' => $filters,
   'filterOptions' => $filterOptions,
   'showCompany' => $dataset['has_company_filter'],
   'showSearch' => true,
])

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

<div class="hsecm-card rounded-2xl overflow-hidden">
   <div class="overflow-x-auto">
      <table class="hsecm-table w-full text-sm">
         <thead>
            <tr>
               @foreach($dataset['columns'] as $label)
               <th class="px-3 py-3 text-left">{{ $label }}</th>
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
                  @elseif(is_numeric($value) && str_starts_with($column, 'pct_'))
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
      {{ $rows->links() }}
   </div>
   @endif
</div>
@endsection
