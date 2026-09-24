@extends('pnc-monitoring.inspection-app.layout')

@section('title', 'Riwayat Inspeksi')
@section('header-title', 'Riwayat Inspeksi')

@section('content')
<div class="px-5 pt-5 space-y-5 pb-8">

  @if ($records->isNotEmpty())
    <section>
      <h1 class="text-xl font-extrabold tracking-tight text-on-surface">Riwayat Inspeksi</h1>
      <p class="text-sm text-on-surface-variant mt-1">Semua hasil inspeksi unit aset, dari yang terbaru.</p>
    </section>

    <section class="space-y-2.5">
      @foreach ($records as $record)
        @php
          $resultStyle = match ($record->overall_result) {
            'Pass' => ['bg-primary-light text-primary', 'check_circle'],
            'Fail' => ['bg-tertiary-light text-tertiary', 'cancel'],
            'Conditional' => ['bg-amber-100 text-amber-700', 'warning'],
            default => ['bg-surface-container text-on-surface-variant', 'help'],
          };
        @endphp
        <div class="p-4 rounded-2xl bg-surface-container-lowest shadow-sm border border-outline-variant/40">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <h3 class="font-bold text-on-surface truncate">{{ $record->asset?->toolMaster?->standard_name ?? 'Alat tidak diketahui' }}</h3>
              <p class="text-xs text-on-surface-variant mt-0.5">{{ $record->asset?->inventory_id ?? '-' }} &middot; {{ $record->inspection_date?->format('d M Y') }}</p>
            </div>
            <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full whitespace-nowrap {{ $resultStyle[0] }}">
              <span class="material-symbols-outlined text-[14px]">{{ $resultStyle[1] }}</span>{{ $record->overall_result ?? '-' }}
            </span>
          </div>
          <div class="flex items-center justify-between mt-3 pt-3 border-t border-outline-variant/40">
            <span class="text-xs text-on-surface-variant">Inspektor: {{ $record->inspector?->name ?? '-' }}</span>
            @if ($record->next_due_date)
              <span class="text-xs font-semibold text-on-surface-variant">Jatuh tempo: {{ $record->next_due_date->format('d M Y') }}</span>
            @endif
          </div>
        </div>
      @endforeach
    </section>
  @else
    <div class="pt-8">
      @include('pnc-monitoring.inspection-app.partials._empty-history')
    </div>
  @endif
</div>
@endsection
