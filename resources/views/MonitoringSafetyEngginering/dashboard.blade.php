@extends('MonitoringSafetyEngginering.layouts.app')

@section('title', 'Progress Penyelesaian Rekayasa — Komitmen')

@push('head')
@include('MonitoringSafetyEngginering.partials.styles')
@endpush

@section('content')
@php
   $categoryLabels = $filterOptions['categories'] ?? [];
   $activeCategoryLabel = $categoryLabels[$activeCategory] ?? 'Replikasi';
   $pctClass = static fn (string $color): string => match ($color) {
      'green' => 'mse-pct--green',
      'amber' => 'mse-pct--amber',
      'orange' => 'mse-pct--orange',
      default => 'mse-pct--red',
   };
@endphp

<div class="space-y-6">
   {{-- Header --}}
   <section class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
      <div class="min-w-0">
         <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-on-surface-variant mb-1">Monitoring Safety Engineering</p>
         <h1 class="font-headline font-extrabold text-2xl md:text-[1.65rem] text-primary leading-tight">
            1.a Progress Penyelesaian Rekayasa — Komitmen
         </h1>
      </div>

      <div class="flex flex-col sm:flex-row items-stretch sm:items-start gap-3 shrink-0">
         <form method="GET" action="{{ route('monitoring-safety-engineering.dashboard') }}" class="flex flex-wrap items-end gap-2">
            <input type="hidden" name="category" value="{{ $filters['category'] }}">

            <div>
               <label class="block text-[9px] font-bold uppercase tracking-wider text-on-surface-variant mb-0.5">BAR</label>
               <select name="bar" class="mse-filter-select min-w-[7rem]" onchange="this.form.submit()">
                  @foreach($filterOptions['bars'] ?? [] as $bar)
                  <option value="{{ $bar }}" @selected($filters['bar'] === $bar)>{{ $bar }}</option>
                  @endforeach
               </select>
            </div>

            <div>
               <label class="block text-[9px] font-bold uppercase tracking-wider text-on-surface-variant mb-0.5">Perusahaan</label>
               <select name="company" class="mse-filter-select min-w-[9rem]" onchange="this.form.submit()">
                  @foreach($filterOptions['companies'] ?? [] as $key => $label)
                  <option value="{{ $key }}" @selected($filters['company'] === (string) $key)>{{ $label }}</option>
                  @endforeach
               </select>
            </div>

            <div>
               <label class="block text-[9px] font-bold uppercase tracking-wider text-on-surface-variant mb-0.5">Review W</label>
               <select name="review_week" class="mse-filter-select min-w-[4.5rem]" onchange="this.form.submit()">
                  @foreach($filterOptions['review_weeks'] ?? [] as $week)
                  <option value="{{ $week }}" @selected($filters['review_week'] === $week)>{{ $week }}</option>
                  @endforeach
               </select>
            </div>

            <div>
               <label class="block text-[9px] font-bold uppercase tracking-wider text-on-surface-variant mb-0.5">Filter Tanggal</label>
               <div class="flex items-center gap-1">
                  <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="mse-filter-select" onchange="this.form.submit()">
                  <span class="text-xs text-on-surface-variant">—</span>
                  <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="mse-filter-select" onchange="this.form.submit()">
               </div>
            </div>
         </form>

         {{-- Overdue Summary --}}
         <div class="mse-overdue-box min-w-[13rem]">
            <p class="font-bold text-[10px] uppercase tracking-wider text-on-surface-variant mb-1.5">Overdue</p>
            @foreach($overdueSummary as $item)
            <div class="mse-overdue-item">
               <span class="text-gray-600">{{ $item['label'] }}</span>
               <span class="mse-overdue-value {{ $item['overdue'] === 0 ? 'mse-overdue-value--zero' : '' }}">{{ $item['overdue'] }}</span>
            </div>
            @endforeach
         </div>
      </div>
   </section>

   {{-- KPI Cards --}}
   <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <div class="mse-card p-5">
         <div class="flex items-center gap-3">
            <div class="mse-kpi-icon mse-kpi-icon--navy">
               <span class="material-symbols-outlined">engineering</span>
            </div>
            <div>
               <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Total Pengendalian</p>
               <p class="text-xs text-on-surface-variant">Rekayasa Komitmen</p>
               <p class="text-3xl font-extrabold text-primary mt-0.5">{{ $summary['total_komitmen'] }}</p>
            </div>
         </div>
      </div>

      <div class="mse-card p-5">
         <div class="flex items-center gap-3 mb-3">
            <div class="mse-kpi-icon mse-kpi-icon--blue">
               <span class="material-symbols-outlined">content_copy</span>
            </div>
            <div>
               <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Plan Replikasi</p>
               <p class="text-xs text-on-surface-variant">2026</p>
               <p class="text-3xl font-extrabold text-[#2563eb] mt-0.5">{{ $summary['replikasi']['count'] }}</p>
            </div>
         </div>
         <div class="mse-progress-track">
            <div class="mse-progress-fill mse-progress-fill--blue" style="width: {{ $summary['replikasi']['progress'] }}%"></div>
         </div>
         <p class="text-[10px] font-semibold text-on-surface-variant mt-1.5">{{ $summary['replikasi']['progress'] }}% Overall Progress</p>
      </div>

      <div class="mse-card p-5">
         <div class="flex items-center gap-3 mb-3">
            <div class="mse-kpi-icon mse-kpi-icon--green">
               <span class="material-symbols-outlined">health_and_safety</span>
            </div>
            <div>
               <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Safety</p>
               <p class="text-xs text-on-surface-variant">Engineering</p>
               <p class="text-3xl font-extrabold text-accent mt-0.5">{{ $summary['safety_engineering']['count'] }}</p>
            </div>
         </div>
         <div class="mse-progress-track">
            <div class="mse-progress-fill mse-progress-fill--green" style="width: {{ $summary['safety_engineering']['progress'] }}%"></div>
         </div>
         <p class="text-[10px] font-semibold text-on-surface-variant mt-1.5">{{ $summary['safety_engineering']['progress'] }}% Overall Progress</p>
      </div>

      <div class="mse-card p-5">
         <div class="flex items-center gap-3 mb-3">
            <div class="mse-kpi-icon mse-kpi-icon--lime">
               <span class="material-symbols-outlined">add_moderator</span>
            </div>
            <div>
               <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Additional Safety</p>
               <p class="text-xs text-on-surface-variant">Engineering</p>
               <p class="text-3xl font-extrabold text-[#65a30d] mt-0.5">{{ $summary['additional_safety_engineering']['count'] }}</p>
            </div>
         </div>
         <div class="mse-progress-track">
            <div class="mse-progress-fill mse-progress-fill--lime" style="width: {{ $summary['additional_safety_engineering']['progress'] }}%"></div>
         </div>
         <p class="text-[10px] font-semibold text-on-surface-variant mt-1.5">{{ $summary['additional_safety_engineering']['progress'] }}% Overall Progress</p>
      </div>
   </section>

   {{-- Main Content: Table + Sidebar --}}
   <section class="grid grid-cols-1 xl:grid-cols-[1fr_22rem] gap-5 items-start">
      {{-- Data Table --}}
      <div class="mse-card overflow-hidden">
         <div class="px-5 pt-5 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="mse-section-title">
               <span class="mse-section-accent"></span>
               {{ $activeCategoryLabel }}
            </div>
            <div class="flex flex-wrap gap-2">
               @foreach($categoryLabels as $key => $label)
               <a
                  href="{{ route('monitoring-safety-engineering.dashboard', array_merge($filters, ['category' => $key])) }}"
                  class="mse-category-tab {{ $activeCategory === $key ? 'mse-category-tab--active' : '' }}"
               >{{ $label }}</a>
               @endforeach
            </div>
         </div>

         <div class="mse-table-wrap px-3 pb-4">
            <table class="mse-table">
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
                  <tr>
                     <td class="text-on-surface-variant font-medium">{{ $index + 1 }}</td>
                     <td class="font-medium text-on-background max-w-xs">{{ $item['name'] }}</td>
                     <td class="text-on-surface-variant">{{ $item['unit'] }}</td>
                     <td class="text-center font-semibold">{{ $item['plan'] }}</td>
                     <td class="text-center font-semibold">{{ $item['done'] }}</td>
                     <td class="text-center">
                        <span class="mse-pct {{ $pctClass($item['percentage_color']) }}">{{ $item['percentage'] }}%</span>
                     </td>
                     <td class="text-on-surface-variant whitespace-nowrap">{{ $item['due_date_label'] }}</td>
                     <td class="text-center">
                        @if($item['overdue'] > 0)
                        <span class="mse-overdue-cell mse-overdue-cell--active">{{ $item['overdue'] }}</span>
                        @else
                        <span class="mse-overdue-cell text-gray-400">{{ $item['overdue'] }}</span>
                        @endif
                     </td>
                  </tr>
                  @empty
                  <tr>
                     <td colspan="8" class="text-center py-10 text-on-surface-variant">Tidak ada data untuk kategori ini.</td>
                  </tr>
                  @endforelse
               </tbody>
            </table>
         </div>
      </div>

      {{-- Sidebar --}}
      <aside class="space-y-4 xl:sticky xl:top-24">
         {{-- Brief Analysis --}}
         <div class="mse-card overflow-hidden">
            <div class="mse-sidebar-header mse-sidebar-header--navy">
               <span class="material-symbols-outlined text-sm mr-1">analytics</span>
               Brief Analysis
            </div>
            <div class="mse-sidebar-body space-y-4">
               @foreach($briefAnalysis as $section)
               <div>
                  <p class="font-bold text-sm text-primary mb-1.5">{{ $section['title'] }}</p>
                  <ul>
                     @foreach($section['points'] as $point)
                     <li>{{ $point }}</li>
                     @endforeach
                  </ul>
               </div>
               @endforeach
            </div>
         </div>

         {{-- Perkuatan dan Next To Do --}}
         <div class="mse-card overflow-hidden">
            <div class="mse-sidebar-header mse-sidebar-header--green">
               <span class="material-symbols-outlined text-sm mr-1">task_alt</span>
               Perkuatan dan Next To Do
            </div>
            <div class="mse-sidebar-body">
               <ul>
                  @foreach($nextTodo as $todo)
                  <li>{{ $todo }}</li>
                  @endforeach
               </ul>
            </div>
         </div>
      </aside>
   </section>
</div>
@endsection
