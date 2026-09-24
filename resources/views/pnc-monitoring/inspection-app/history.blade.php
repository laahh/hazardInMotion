@extends('pnc-monitoring.inspection-app.layout')

@section('title', 'Riwayat Inspeksi')
@section('header-title', 'Riwayat Inspeksi')

@section('content')
<div class="px-5 space-y-6">

  <section>
    <h1 class="text-xl font-extrabold tracking-tight text-on-surface">Riwayat Inspeksi</h1>
    <p class="text-sm text-on-surface-variant mt-1">Semua hasil inspeksi unit aset, dari yang terbaru.</p>
  </section>

  <section class="space-y-3 pb-4">
    @forelse ($records as $record)
      @php
        $resultStyle = match ($record->overall_result) {
          'Pass' => ['bg-emerald-100 text-emerald-700', 'check_circle'],
          'Fail' => ['bg-red-100 text-red-700', 'cancel'],
          'Conditional' => ['bg-amber-100 text-amber-700', 'warning'],
          default => ['bg-surface-container-high text-on-surface-variant', 'help'],
        };
      @endphp
      <div class="p-4 rounded-3xl bg-surface-container-lowest shadow-sm border border-outline-variant/10">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <h3 class="font-bold text-on-surface truncate">{{ $record->asset?->toolMaster?->standard_name ?? 'Alat tidak diketahui' }}</h3>
            <p class="text-xs text-on-surface-variant mt-0.5">{{ $record->asset?->inventory_id ?? '-' }} &middot; {{ $record->inspection_date?->format('d M Y') }}</p>
          </div>
          <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full whitespace-nowrap {{ $resultStyle[0] }}">
            <span class="material-symbols-outlined text-[14px]">{{ $resultStyle[1] }}</span>{{ $record->overall_result ?? '-' }}
          </span>
        </div>
        <div class="flex items-center justify-between mt-3 pt-3 border-t border-outline-variant/10">
          <span class="text-xs text-on-surface-variant">Inspektor: {{ $record->inspector?->name ?? '-' }}</span>
          @if ($record->next_due_date)
            <span class="text-xs font-semibold text-on-surface-variant">Jatuh tempo: {{ $record->next_due_date->format('d M Y') }}</span>
          @endif
        </div>
      </div>
    @empty
      <div class="text-center py-20 text-on-surface-variant">
        <span class="material-symbols-outlined text-6xl opacity-30">fact_check</span>
        <p class="mt-3 text-sm font-semibold">Belum ada riwayat inspeksi</p>
        <p class="text-xs mt-1 max-w-[240px] mx-auto">Riwayat akan muncul di sini setelah inspeksi pertama disimpan dari halaman detail alat.</p>
      </div>
    @endforelse
  </section>
</div>
@endsection
