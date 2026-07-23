@extends('BaseRule.layouts.app')

@section('title', 'Aksi PJO — Monitoring & Intervensi')

@push('head')
@include('BaseRule.partials.styles')
<style>
   .hsecm-scroll-box {
      max-height: 280px;
      overflow: auto;
      border: 1px solid rgba(15, 118, 110, 0.12);
      border-radius: 0.75rem;
      background: #fff;
   }
   .hsecm-section-tone-danger { border-left: 4px solid #dc2626; }
   .hsecm-section-tone-warning { border-left: 4px solid #d97706; }
   .hsecm-section-tone-success { border-left: 4px solid #059669; }
   .hsecm-section-tone-info { border-left: 4px solid #0f766e; }
   .hsecm-section-tone-muted { border-left: 4px solid #94a3b8; }
   .hsecm-action-chip {
      display: inline-flex;
      align-items: flex-start;
      gap: 0.4rem;
      padding: 0.5rem 0.75rem;
      border-radius: 0.75rem;
      background: rgba(15, 118, 110, 0.06);
      color: #134e4a;
      font-size: 12px;
      line-height: 1.45;
   }
</style>
@endpush

@section('content')
@include('BaseRule.partials.page-header', [
   'title' => 'Aksi PJO — Monitoring & Intervensi',
   'subtitle' => 'Checklist gap & exposure shift berjalan: apa yang harus dikontrol dan ditindaklanjuti sebelum akhir shift.',
   'breadcrumb' => 'Aksi PJO',
])

@include('BaseRule.partials.filter-bar', [
   'actionRoute' => 'hsecm.pjo-action',
   'filters' => $filters,
   'filterOptions' => $filterOptions,
   'showCompany' => true,
   'showSearch' => false,
   'showDate' => true,
])

@if(!empty($periodLabel))
<div class="mb-6 flex flex-wrap items-center gap-2 text-sm text-on-surface-variant">
   <span class="material-symbols-outlined text-base text-primary">calendar_month</span>
   <span class="font-semibold text-on-background">Periode data:</span>
   <span>{{ $periodLabel }}</span>
</div>
@endif

{{-- Summary --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
   <div class="hsecm-card rounded-2xl p-4">
      <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Gap perlu aksi</p>
      <p class="text-3xl font-extrabold text-on-background mt-1">{{ number_format((int) ($summary['open_actions'] ?? 0)) }}</p>
      <p class="text-[11px] text-on-surface-variant mt-1">poin concern aktif</p>
   </div>
   <div class="hsecm-card rounded-2xl p-4">
      <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Total baris gap</p>
      <p class="text-3xl font-extrabold text-on-background mt-1">{{ number_format((int) ($summary['total_gap_rows'] ?? 0)) }}</p>
      <p class="text-[11px] text-on-surface-variant mt-1">yang perlu ditindaklanjuti</p>
   </div>
   <div class="hsecm-card rounded-2xl p-4">
      <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Exposure</p>
      <p class="text-3xl font-extrabold text-on-background mt-1">{{ number_format((int) ($summary['exposure_items'] ?? 0)) }}</p>
      <p class="text-[11px] text-on-surface-variant mt-1">item pantauan shift</p>
   </div>
   <div class="hsecm-card rounded-2xl p-4">
      <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Semua gap</p>
      <p class="text-3xl font-extrabold text-on-background mt-1">{{ number_format((int) ($summary['gap_items'] ?? 0)) }}</p>
      <p class="text-[11px] text-on-surface-variant mt-1">checklist Monitoring & Intervensi</p>
   </div>
</div>

{{-- Checklist cepat --}}
<div class="hsecm-card rounded-2xl p-5 mb-8">
   <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
      <div>
         <h2 class="font-headline font-bold text-lg text-on-background">Yang harus dilakukan PJO sekarang</h2>
         <p class="text-xs text-on-surface-variant mt-0.5">Fokus pada gap yang masih terbuka sebelum akhir shift.</p>
      </div>
      <a href="{{ route('hsecm.wa-notify.index', array_filter(['week' => $filters['week'] ?? null, 'year' => $filters['year'] ?? null])) }}"
         class="inline-flex items-center gap-1.5 rounded-xl border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-bold text-teal-800 hover:bg-teal-100">
         <span class="material-symbols-outlined text-sm">mail</span>
         Kirim ringkasan ke PJO
      </a>
   </div>

   @if(count($actionGaps) === 0)
      <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
         Tidak ada gap terbuka pada filter saat ini. Tetap pantau exposure IKK & pekerja baru.
      </div>
   @else
      <ol class="space-y-2">
         @foreach($actionGaps as $i => $item)
         <li class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
            <div class="min-w-0">
               <p class="text-sm font-bold text-on-background">
                  {{ $i + 1 }}. {{ $item['title'] }}:
                  <span class="text-primary">{{ $item['value'] }}</span>
               </p>
               <p class="text-xs text-on-surface-variant mt-1">{{ $item['action'] ?? '' }}</p>
            </div>
            <a href="#section-{{ $item['key'] }}" class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline shrink-0">
               Lihat detail
               <span class="material-symbols-outlined text-sm">south</span>
            </a>
         </li>
         @endforeach
      </ol>
   @endif
</div>

{{-- Exposure --}}
<div class="mb-3">
   <h2 class="font-headline font-bold text-lg text-on-background">Exposure & perhatian shift berjalan</h2>
   <p class="text-xs text-on-surface-variant mt-0.5">Hal yang perlu menjadi perhatian PJO pada shift berjalan.</p>
</div>
<div class="space-y-4 mb-10">
   @foreach($exposure as $i => $section)
      @include('BaseRule.pjo-action.partials.section', [
         'number' => $i + 1,
         'section' => $section,
         'filters' => $filters,
      ])
   @endforeach
</div>

{{-- Gaps --}}
<div class="mb-3">
   <h2 class="font-headline font-bold text-lg text-on-background">Gap concern — tindaklanjuti sebelum shift berakhir</h2>
   <p class="text-xs text-on-surface-variant mt-0.5">Setiap poin di bawah wajib dikontrol dan ditindaklanjuti.</p>
</div>
<div class="space-y-4 mb-8">
   @foreach($gaps as $i => $section)
      @include('BaseRule.pjo-action.partials.section', [
         'number' => $i + 1,
         'section' => $section,
         'filters' => $filters,
      ])
   @endforeach
</div>

<p class="text-sm text-on-surface-variant leading-relaxed mb-2">
   Mohon setiap point dari gap yang muncul di atas dapat dikontrol dan ditindaklanjuti sebelum akhir shift.
</p>
@endsection
