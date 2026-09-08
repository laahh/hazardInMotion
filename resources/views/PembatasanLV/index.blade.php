@extends('PembatasanLV.layouts.app')

@section('title', 'Dashboard Overview Pembatasan LV & Orang')

@section('page-header')
@endsection

@push('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
   .ab-overview { --ab-ease: cubic-bezier(0.4, 0, 0.2, 1); }
   .ab-fade-in { animation: abFadeUp 0.55s var(--ab-ease) both; }
   .ab-fade-in-delay-1 { animation-delay: 0.06s; }
   .ab-fade-in-delay-2 { animation-delay: 0.12s; }
   .ab-fade-in-delay-3 { animation-delay: 0.18s; }
   .ab-fade-in-delay-4 { animation-delay: 0.24s; }
   @keyframes abFadeUp {
      from { opacity: 0; transform: translateY(10px); }
      to   { opacity: 1; transform: translateY(0); }
   }
   .ab-surface-card {
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid rgba(57, 82, 188, 0.07);
      box-shadow: 0 1px 2px rgba(44, 47, 49, 0.04), 0 8px 24px -6px rgba(57, 82, 188, 0.08);
      transition: box-shadow 0.35s var(--ab-ease), border-color 0.35s var(--ab-ease);
   }
   .ab-surface-card:hover {
      box-shadow: 0 2px 4px rgba(44, 47, 49, 0.05), 0 14px 32px -8px rgba(57, 82, 188, 0.12);
      border-color: rgba(57, 82, 188, 0.12);
   }
   .ab-hero-glow {
      background:
         radial-gradient(ellipse 80% 60% at 100% 0%, rgba(57, 82, 188, 0.09) 0%, transparent 55%),
         radial-gradient(ellipse 50% 40% at 0% 100%, rgba(114, 71, 158, 0.05) 0%, transparent 50%),
         linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
   }
   .ab-stat-accent { position: relative; }
   .ab-stat-accent::before {
      content: '';
      position: absolute;
      left: 0; top: 1.25rem; bottom: 1.25rem;
      width: 3px;
      border-radius: 0 4px 4px 0;
      background: linear-gradient(180deg, rgba(57,82,188,0.5), rgba(57,82,188,0.15));
      opacity: 0;
      transition: opacity 0.35s var(--ab-ease);
   }
   .ab-stat-accent:hover::before { opacity: 1; }
   .ab-tab-btn {
      transition: color 0.25s var(--ab-ease), background 0.25s var(--ab-ease), box-shadow 0.25s var(--ab-ease);
   }
   .ab-tab-btn[aria-selected="true"] {
      background: #fff;
      color: #3952bc;
      box-shadow: 0 1px 3px rgba(57, 82, 188, 0.1);
   }
   .ab-empty-illus {
      background: linear-gradient(145deg, #f1f5f9 0%, #eef2ff 100%);
   }
   .ab-table-soft thead th { font-weight: 600; letter-spacing: 0.04em; }
   .ab-table-soft tbody tr { transition: background 0.2s var(--ab-ease); }
   .ab-pulse-dot { animation: abPulse 2.4s ease-in-out infinite; }
   @keyframes abPulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.55; transform: scale(0.92); }
   }
   .ab-ring-track { stroke: #e8ecf4; }
   .ab-ring-fill {
      stroke: #3952bc;
      stroke-linecap: round;
      transition: stroke-dashoffset 1s var(--ab-ease);
   }
   .plv-page-header {
      position: relative;
      z-index: 40;
      border-bottom: 1px solid rgba(171, 173, 175, 0.35);
   }
   .plv-filter-dropdown {
      background: #ffffff;
      color: #1e293b;
      isolation: isolate;
      box-shadow: 0 12px 32px rgba(15, 23, 42, 0.16);
   }
   .plv-filter-pill {
      background: #ffffff;
      border: 1px solid rgba(171, 173, 175, 0.28);
      box-shadow: 0 1px 2px rgba(44, 47, 49, 0.04);
      transition: border-color 0.25s var(--ab-ease), box-shadow 0.25s var(--ab-ease);
   }
   .plv-filter-pill:hover {
      border-color: rgba(57, 82, 188, 0.22);
      box-shadow: 0 2px 8px rgba(57, 82, 188, 0.08);
   }
   .plv-add-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      border-radius: 0.75rem;
      padding: 0.75rem 1rem;
      font-size: 0.8125rem;
      font-weight: 700;
      line-height: 1.25;
      white-space: nowrap;
      transition: opacity 0.2s var(--ab-ease), box-shadow 0.2s var(--ab-ease), background 0.2s var(--ab-ease);
   }
   .plv-add-btn--lv {
      background: #3952bc;
      color: #fff;
      box-shadow: 0 1px 3px rgba(57, 82, 188, 0.28);
   }
   .plv-add-btn--lv:hover { opacity: 0.94; box-shadow: 0 3px 10px rgba(57, 82, 188, 0.32); }
   .plv-add-btn--orang {
      background: #ffffff;
      color: #3952bc;
      border: 1px solid rgba(57, 82, 188, 0.28);
      box-shadow: 0 1px 2px rgba(44, 47, 49, 0.04);
   }
   .plv-add-btn--orang:hover {
      background: rgba(57, 82, 188, 0.04);
      border-color: rgba(57, 82, 188, 0.4);
   }
</style>
@endpush

@section('content')
@php
   $shiftHour = (int) now()->timezone(config('app.timezone'))->format('H');
   $activeShift = ($shiftHour >= 6 && $shiftHour < 18) ? 1 : 2;
   $shiftTimeLabel = $activeShift === 1 ? '06:00 – 18:00' : '18:00 – 06:00';
   $totalDiArea = $lvMasukAktif + $orangMasukAktif;
   $totalCheckout = $lvKeluar + $orangKeluar;
   $checkoutDateLabel = \Carbon\Carbon::parse($filters['tanggal'])->format('d M Y');
   $crFilterLabel = $filters['control_room'] !== '' ? $filters['control_room'] : 'Semua CR';
   $ringPct = min(100, $totalDiArea > 0 ? max(12, $totalDiArea * 8) : 0);
   $ringOffset = 97.4 - (97.4 * $ringPct / 100);
   $stats = [
      ['label' => 'LV Masuk', 'value' => $lvMasukAktif, 'hint' => 'Belum checkout', 'icon' => 'inventory_2'],
      ['label' => 'LV Keluar', 'value' => $lvKeluar, 'hint' => 'Checkout ' . $checkoutDateLabel . ($filters['control_room'] !== '' ? ' · ' . $filters['control_room'] : ''), 'icon' => 'logout'],
      ['label' => 'Orang Masuk', 'value' => $orangMasukAktif, 'hint' => 'Belum checkout', 'icon' => 'groups'],
      ['label' => 'Orang Keluar', 'value' => $orangKeluar, 'hint' => 'Checkout ' . $checkoutDateLabel . ($filters['control_room'] !== '' ? ' · ' . $filters['control_room'] : ''), 'icon' => 'person_off'],
   ];
@endphp

<div class="ab-overview -mt-2 space-y-7">

   {{-- Page header --}}
   <section class="plv-page-header pb-6">
      <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5 lg:gap-8">
         <div class="min-w-0">
            <nav class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.08em] text-on-surface-variant mb-2.5" aria-label="Breadcrumb">
               <span>Dashboard</span>
               <span class="material-symbols-outlined text-[13px] opacity-60">chevron_right</span>
               <span class="text-primary">Pembatasan LV</span>
            </nav>
            <h1 class="font-headline font-extrabold text-3xl sm:text-[2.125rem] text-on-background tracking-tight leading-tight">Dashboard Overview</h1>
            <p class="mt-1.5 text-sm text-on-surface-variant">
               Monitoring dan evaluasi pembatasan level &bull; {{ $checkoutDateLabel }}
            </p>
         </div>

         <div class="shrink-0 w-full lg:w-auto">
            @include('PembatasanLV.partials.filter-bar', [
               'sites' => $sites,
               'controlRooms' => $controlRooms,
               'filters' => $filters,
            ])
         </div>
      </div>
   </section>

   {{-- KPI Bento --}}
   <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-12 gap-4">
      <div class="ab-fade-in ab-fade-in-delay-1 ab-surface-card ab-stat-accent rounded-2xl p-6 xl:col-span-4 flex flex-col justify-between min-h-[168px]">
         <div class="flex items-start justify-between gap-3">
            <div>
               <p class="text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant">Total di Area</p>
               <p class="mt-3 font-headline font-bold text-5xl tabular-nums text-on-background leading-none">{{ number_format($totalDiArea) }}</p>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/[0.08] text-primary">
               <span class="material-symbols-outlined text-2xl">insights</span>
            </div>
         </div>
         <div class="mt-5 flex flex-wrap gap-2">
            <span class="inline-flex items-center gap-1 rounded-lg bg-[#f1f5f9] px-2.5 py-1 text-[11px] font-medium text-on-surface-variant">
               <span class="material-symbols-outlined text-sm text-primary/70">local_shipping</span> {{ number_format($lvMasukAktif) }} LV
            </span>
            <span class="inline-flex items-center gap-1 rounded-lg bg-[#f1f5f9] px-2.5 py-1 text-[11px] font-medium text-on-surface-variant">
               <span class="material-symbols-outlined text-sm text-primary/70">groups</span> {{ number_format($orangMasukAktif) }} Orang
            </span>
         </div>
      </div>

      @foreach($stats as $i => $stat)
      <div class="ab-fade-in ab-fade-in-delay-{{ min($i + 2, 4) }} ab-surface-card ab-stat-accent rounded-2xl p-5 xl:col-span-2 flex flex-col justify-between min-h-[140px]">
         <div class="flex items-center justify-between">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant">{{ $stat['label'] }}</span>
            <span class="material-symbols-outlined text-lg text-primary/40">{{ $stat['icon'] }}</span>
         </div>
         <div class="mt-2">
            <p class="font-headline font-bold text-3xl tabular-nums text-on-background">{{ number_format($stat['value']) }}</p>
            <p class="mt-1 text-[11px] text-on-surface-variant/80 line-clamp-2">{{ $stat['hint'] }}</p>
         </div>
      </div>
      @endforeach
   </section>

   {{-- Main grid --}}
   <section class="grid grid-cols-1 xl:grid-cols-12 gap-5">
      <div class="ab-fade-in ab-fade-in-delay-2 ab-surface-card rounded-2xl overflow-hidden xl:col-span-8">
         <div class="px-5 sm:px-6 pt-5 pb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-outline-variant/10">
            <div>
               <h2 class="font-headline font-semibold text-base text-on-background">Aktivitas Langsung</h2>
               <p class="text-xs text-on-surface-variant mt-0.5">Unit & personel yang masih berada di area</p>
            </div>
            <div class="inline-flex p-1 rounded-xl bg-[#f1f5f9]/80 gap-0.5" role="tablist" aria-label="Jenis aktivitas langsung">
               <button type="button" role="tab" id="plv-live-tab-lv" data-plv-live-tab="lv" aria-selected="true" aria-controls="plv-live-panel-lv" class="ab-tab-btn rounded-lg px-4 py-2 text-xs font-semibold text-on-surface-variant">LV</button>
               <button type="button" role="tab" id="plv-live-tab-orang" data-plv-live-tab="orang" aria-selected="false" aria-controls="plv-live-panel-orang" class="ab-tab-btn rounded-lg px-4 py-2 text-xs font-semibold text-on-surface-variant">Orang</button>
            </div>
         </div>

         <div id="plv-live-panel-lv" role="tabpanel" aria-labelledby="plv-live-tab-lv" data-plv-live-panel="lv" class="p-5 sm:p-6 max-h-[380px] overflow-y-auto">
            <div class="overflow-x-auto -mx-1">
               <table class="ab-table-soft w-full text-left min-w-[560px]">
                  <thead class="sticky top-0 bg-white/95 backdrop-blur-sm z-10">
                     <tr class="border-b border-outline-variant/10">
                        <th class="px-3 py-2.5 text-[10px] uppercase text-on-surface-variant/70">No</th>
                        <th class="px-3 py-2.5 text-[10px] uppercase text-on-surface-variant/70">Driver</th>
                        <th class="px-3 py-2.5 text-[10px] uppercase text-on-surface-variant/70">LV</th>
                        <th class="px-3 py-2.5 text-[10px] uppercase text-on-surface-variant/70">Lokasi</th>
                        <th class="px-3 py-2.5 text-[10px] uppercase text-on-surface-variant/70">Durasi</th>
                        <th class="px-3 py-2.5 text-[10px] uppercase text-on-surface-variant/70 w-24">Aksi</th>
                     </tr>
                  </thead>
                  <tbody id="plv-lv-masuk-aktif-tbody" class="divide-y divide-outline-variant/5">
                     @forelse($lvMasukAktifList as $index => $row)
                     <tr class="hover:bg-[#f8fafc]/80" data-inputasi-id="{{ $row->id }}">
                        <td class="px-3 py-3 text-sm tabular-nums text-on-surface-variant">{{ $index + 1 }}</td>
                        <td class="px-3 py-3 text-sm text-on-surface">{{ $row->nama_driver }}</td>
                        <td class="px-3 py-3 text-sm font-semibold text-on-background">{{ $row->no_lambung }}</td>
                        <td class="px-3 py-3 text-sm text-on-surface">
                           <div>{{ $row->lokasi }}</div>
                           @if($row->detail_lokasi)
                           <div class="text-xs text-on-surface-variant mt-0.5">{{ $row->detail_lokasi }}</div>
                           @endif
                        </td>
                        <td class="px-3 py-3 text-sm whitespace-nowrap">
                           <span class="plv-durasi-live font-mono font-semibold text-primary tabular-nums" data-checkin-at="{{ $row->checkin_at?->timezone(config('app.timezone'))->toIso8601String() }}">00:00:00</span>
                        </td>
                        <td class="px-3 py-3 text-sm">
                           <form method="POST" action="{{ route('pembatasan-lv.checkout.lv', $row) }}" class="plv-checkout-form inline" data-unit="{{ $row->no_lambung }}" data-driver="{{ $row->nama_driver }}">
                              @csrf
                              <button type="submit" class="inline-flex items-center gap-0.5 rounded-lg bg-emerald-600/90 px-2.5 py-1 text-[10px] font-semibold text-white transition-colors hover:bg-emerald-700">
                                 <span class="material-symbols-outlined text-sm">logout</span>
                                 Checkout
                              </button>
                           </form>
                        </td>
                     </tr>
                     @empty
                     <tr id="plv-lv-masuk-aktif-empty">
                        <td colspan="6" class="px-3 py-10">
                           <div class="ab-empty-illus mx-auto max-w-sm rounded-2xl px-6 py-8 text-center">
                              <span class="material-symbols-outlined text-4xl text-primary/25 mb-3 block">local_shipping</span>
                              <p class="text-sm font-medium text-on-surface">
                                 @if($supervisedRooms->isEmpty())
                                    Tidak ada control room terdaftar
                                 @else
                                    Belum ada LV di area
                                 @endif
                              </p>
                              <p class="text-xs text-on-surface-variant mt-1">Data akan muncul setelah ada check-in</p>
                           </div>
                        </td>
                     </tr>
                     @endforelse
                  </tbody>
               </table>
            </div>
         </div>

         <div id="plv-live-panel-orang" role="tabpanel" aria-labelledby="plv-live-tab-orang" data-plv-live-panel="orang" class="hidden p-5 sm:p-6 max-h-[380px] overflow-y-auto">
            <div class="overflow-x-auto -mx-1">
               <table class="ab-table-soft w-full text-left min-w-[560px]">
                  <thead class="sticky top-0 bg-white/95 backdrop-blur-sm z-10">
                     <tr class="border-b border-outline-variant/10">
                        <th class="px-3 py-2.5 text-[10px] uppercase text-on-surface-variant/70">No</th>
                        <th class="px-3 py-2.5 text-[10px] uppercase text-on-surface-variant/70">Nama</th>
                        <th class="px-3 py-2.5 text-[10px] uppercase text-on-surface-variant/70">SID</th>
                        <th class="px-3 py-2.5 text-[10px] uppercase text-on-surface-variant/70">Lokasi</th>
                        <th class="px-3 py-2.5 text-[10px] uppercase text-on-surface-variant/70">Durasi</th>
                        <th class="px-3 py-2.5 text-[10px] uppercase text-on-surface-variant/70 w-24">Aksi</th>
                     </tr>
                  </thead>
                  <tbody id="plv-orang-masuk-aktif-tbody" class="divide-y divide-outline-variant/5">
                     @forelse($orangMasukAktifList as $index => $row)
                     <tr class="hover:bg-[#f8fafc]/80" data-inputasi-id="{{ $row->id }}">
                        <td class="px-3 py-3 text-sm tabular-nums text-on-surface-variant">{{ $index + 1 }}</td>
                        <td class="px-3 py-3 text-sm text-on-surface">{{ $row->nama }}</td>
                        <td class="px-3 py-3 text-sm font-semibold text-on-background">{{ $row->sid }}</td>
                        <td class="px-3 py-3 text-sm text-on-surface">
                           <div>{{ $row->lokasi }}</div>
                           @if($row->detail_lokasi)
                           <div class="text-xs text-on-surface-variant mt-0.5">{{ $row->detail_lokasi }}</div>
                           @endif
                        </td>
                        <td class="px-3 py-3 text-sm whitespace-nowrap">
                           <span class="plv-durasi-live font-mono font-semibold text-primary tabular-nums" data-checkin-at="{{ $row->checkin_at?->timezone(config('app.timezone'))->toIso8601String() }}">00:00:00</span>
                        </td>
                        <td class="px-3 py-3 text-sm">
                           <form method="POST" action="{{ route('pembatasan-lv.checkout.orang', $row) }}" class="plv-checkout-orang-form inline" data-sid="{{ $row->sid }}" data-nama="{{ $row->nama }}">
                              @csrf
                              <button type="submit" class="inline-flex items-center gap-0.5 rounded-lg bg-emerald-600/90 px-2.5 py-1 text-[10px] font-semibold text-white transition-colors hover:bg-emerald-700">
                                 <span class="material-symbols-outlined text-sm">logout</span>
                                 Checkout
                              </button>
                           </form>
                        </td>
                     </tr>
                     @empty
                     <tr id="plv-orang-masuk-aktif-empty">
                        <td colspan="6" class="px-3 py-10">
                           <div class="ab-empty-illus mx-auto max-w-sm rounded-2xl px-6 py-8 text-center">
                              <span class="material-symbols-outlined text-4xl text-primary/25 mb-3 block">groups</span>
                              <p class="text-sm font-medium text-on-surface">
                                 @if($supervisedRooms->isEmpty())
                                    Tidak ada control room terdaftar
                                 @else
                                    Belum ada personel di area
                                 @endif
                              </p>
                              <p class="text-xs text-on-surface-variant mt-1">Data akan muncul setelah ada check-in</p>
                           </div>
                        </td>
                     </tr>
                     @endforelse
                  </tbody>
               </table>
            </div>
         </div>
      </div>

      <aside class="xl:col-span-4 space-y-4">
         <div class="ab-fade-in ab-fade-in-delay-3 ab-surface-card rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
               <h3 class="font-headline font-semibold text-sm text-on-background">Ringkasan Shift</h3>
               <span class="text-[10px] font-bold uppercase tracking-wider text-primary bg-primary/[0.08] px-2 py-0.5 rounded-md">Shift {{ $activeShift }}</span>
            </div>
            <div class="flex items-center gap-4">
               <div class="relative shrink-0">
                  <svg class="w-20 h-20 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                     <circle class="ab-ring-track" cx="18" cy="18" r="15.5" fill="none" stroke-width="2.5"/>
                     <circle class="ab-ring-fill" cx="18" cy="18" r="15.5" fill="none" stroke-width="2.5" stroke-dasharray="97.4" stroke-dashoffset="{{ $ringOffset }}"/>
                  </svg>
                  <span class="absolute inset-0 flex items-center justify-center font-headline font-bold text-lg text-on-background tabular-nums">{{ $totalDiArea }}</span>
               </div>
               <div class="min-w-0 space-y-2 text-xs text-on-surface-variant">
                  <p><span class="font-semibold text-on-surface">{{ $shiftTimeLabel }}</span> · Shift aktif</p>
                  <p>{{ number_format($totalCheckout) }} checkout pada {{ $checkoutDateLabel }}</p>
               </div>
            </div>
         </div>

         <div class="ab-fade-in ab-fade-in-delay-3 ab-surface-card rounded-2xl p-5">
            <h3 class="font-headline font-semibold text-sm text-on-background mb-4">Aksi Cepat</h3>
            <div class="space-y-2">
               <button type="button" data-plv-open-inputasi="lv" class="group flex w-full items-center gap-3 rounded-xl border border-transparent bg-[#f8fafc] px-4 py-3 text-left transition-all duration-300 hover:border-primary/15 hover:bg-primary/[0.04]">
                  <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white shadow-sm text-primary transition-transform duration-300 group-hover:scale-105">
                     <span class="material-symbols-outlined text-xl">local_shipping</span>
                  </span>
                  <div class="min-w-0">
                     <p class="text-sm font-semibold text-on-background">Inputasi LV</p>
                     <p class="text-[11px] text-on-surface-variant truncate">Catat unit masuk / keluar</p>
                  </div>
                  <span class="material-symbols-outlined text-on-surface-variant/40 ml-auto text-lg transition-transform duration-300 group-hover:translate-x-0.5">arrow_forward</span>
               </button>
               <button type="button" data-plv-open-inputasi="orang" class="group flex w-full items-center gap-3 rounded-xl border border-transparent bg-[#f8fafc] px-4 py-3 text-left transition-all duration-300 hover:border-primary/15 hover:bg-primary/[0.04]">
                  <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white shadow-sm text-primary transition-transform duration-300 group-hover:scale-105">
                     <span class="material-symbols-outlined text-xl">person_add</span>
                  </span>
                  <div class="min-w-0">
                     <p class="text-sm font-semibold text-on-background">Inputasi Orang</p>
                     <p class="text-[11px] text-on-surface-variant truncate">Catat personel masuk / keluar</p>
                  </div>
                  <span class="material-symbols-outlined text-on-surface-variant/40 ml-auto text-lg transition-transform duration-300 group-hover:translate-x-0.5">arrow_forward</span>
               </button>
               <button type="button" id="plv-open-planning-overview" class="group flex w-full items-center gap-3 rounded-xl border border-transparent bg-[#f8fafc] px-4 py-3 text-left transition-all duration-300 hover:border-primary/15 hover:bg-primary/[0.04]">
                  <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white shadow-sm text-primary transition-transform duration-300 group-hover:scale-105">
                     <span class="material-symbols-outlined text-xl">event_note</span>
                  </span>
                  <div class="min-w-0">
                     <p class="text-sm font-semibold text-on-background">Planning</p>
                     <p class="text-[11px] text-on-surface-variant truncate">Check-in dari data yang direncanakan</p>
                  </div>
                  <span class="material-symbols-outlined text-on-surface-variant/40 ml-auto text-lg transition-transform duration-300 group-hover:translate-x-0.5">arrow_forward</span>
               </button>
               <a href="{{ route('pembatasan-lv.master-data.index') }}" class="group flex items-center gap-3 rounded-xl border border-transparent bg-[#f8fafc] px-4 py-3 transition-all duration-300 hover:border-primary/15 hover:bg-primary/[0.04]">
                  <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white shadow-sm text-primary transition-transform duration-300 group-hover:scale-105">
                     <span class="material-symbols-outlined text-xl">database</span>
                  </span>
                  <div class="min-w-0">
                     <p class="text-sm font-semibold text-on-background">Master Data</p>
                     <p class="text-[11px] text-on-surface-variant truncate">Kelola referensi & batas</p>
                  </div>
                  <span class="material-symbols-outlined text-on-surface-variant/40 ml-auto text-lg transition-transform duration-300 group-hover:translate-x-0.5">arrow_forward</span>
               </a>
            </div>
         </div>

         <div class="ab-fade-in ab-fade-in-delay-4 ab-surface-card rounded-2xl p-5">
            <h3 class="font-headline font-semibold text-sm text-on-background mb-3">Status Area</h3>
            <div class="flex flex-wrap gap-2">
               <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-100/80 px-3 py-1.5 text-[11px] font-medium text-amber-800">
                  <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                  {{ number_format($totalDiArea) }} Di Area
               </span>
               <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-100/80 px-3 py-1.5 text-[11px] font-medium text-emerald-800">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                  {{ number_format($totalCheckout) }} Checkout
               </span>
               <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 border border-slate-200/80 px-3 py-1.5 text-[11px] font-medium text-slate-600">
                  <span class="material-symbols-outlined text-sm">meeting_room</span>
                  {{ $crFilterLabel }}
               </span>
            </div>
            @if($supervisedRooms->isNotEmpty())
            <p class="mt-3 text-[11px] text-on-surface-variant leading-relaxed">
               CR Anda: {{ $supervisedRooms->implode(', ') }}
            </p>
            @endif
         </div>
      </aside>
   </section>

   {{-- Riwayat --}}
   <section class="ab-fade-in ab-fade-in-delay-4 ab-surface-card rounded-2xl overflow-hidden">
      <div class="px-5 sm:px-6 pt-5 pb-0 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 border-b border-outline-variant/10">
         <div>
            <h2 class="font-headline font-semibold text-base text-on-background">Riwayat Inputasi</h2>
            <p class="text-xs text-on-surface-variant mt-0.5">Semua check-in & check-out · {{ $checkoutDateLabel }}</p>
         </div>
         <div class="inline-flex p-1 rounded-xl bg-[#f1f5f9]/80 gap-0.5 mb-4 lg:mb-5" role="tablist" aria-label="Riwayat inputasi">
            <button type="button" role="tab" id="plv-history-tab-lv" data-plv-history-tab="lv" aria-selected="true" aria-controls="plv-history-panel-lv" class="ab-tab-btn rounded-lg px-4 py-2 text-xs font-semibold text-on-surface-variant">Inputasi LV</button>
            <button type="button" role="tab" id="plv-history-tab-orang" data-plv-history-tab="orang" aria-selected="false" aria-controls="plv-history-panel-orang" class="ab-tab-btn rounded-lg px-4 py-2 text-xs font-semibold text-on-surface-variant">Inputasi Orang</button>
         </div>
      </div>

      <div id="plv-history-panel-lv" role="tabpanel" aria-labelledby="plv-history-tab-lv" data-plv-history-panel="lv">
         <div class="px-5 sm:px-6 py-3 flex justify-end border-b border-outline-variant/5 bg-[#fafbfc]/50">
            <button type="button" data-plv-open-inputasi="lv" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:text-primary-dim transition-colors">
               <span class="material-symbols-outlined text-base">add_circle</span>
               Tambah LV
            </button>
         </div>
         <div class="overflow-x-auto">
            <table class="ab-table-soft w-full min-w-[900px] text-left">
               <thead class="bg-[#fafbfc]/80">
                  <tr class="border-b border-outline-variant/10">
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">No</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">No Unit</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Driver</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Control Room</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Lokasi</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Check-in</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Check-out</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Durasi</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Status</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Shift</th>
                  </tr>
               </thead>
               <tbody class="divide-y divide-outline-variant/5">
                  @forelse($lvAllList as $index => $row)
                  <tr class="hover:bg-[#f8fafc]/80">
                     <td class="px-4 py-3.5 text-sm tabular-nums text-on-surface-variant">{{ $index + 1 }}</td>
                     <td class="px-4 py-3.5 text-sm font-semibold text-on-background">{{ $row->no_lambung }}</td>
                     <td class="px-4 py-3.5 text-sm text-on-surface">{{ $row->nama_driver }}</td>
                     <td class="px-4 py-3.5 text-sm text-on-surface">{{ $row->control_room }}</td>
                     <td class="px-4 py-3.5 text-sm text-on-surface">
                        <div>{{ $row->lokasi }}</div>
                        @if($row->detail_lokasi)
                        <div class="text-xs text-on-surface-variant mt-0.5">{{ $row->detail_lokasi }}</div>
                        @endif
                     </td>
                     <td class="px-4 py-3.5 text-sm text-on-surface whitespace-nowrap">{{ $row->checkin_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                     <td class="px-4 py-3.5 text-sm text-on-surface whitespace-nowrap">
                        @if($row->checkout_at)
                           {{ $row->checkout_at->timezone(config('app.timezone'))->format('d M Y H:i') }}
                        @else
                           <span class="text-on-surface-variant">—</span>
                        @endif
                     </td>
                     <td class="px-4 py-3.5 text-sm whitespace-nowrap">
                        @if($row->checkout_at)
                        <span class="font-mono font-semibold text-on-surface tabular-nums">{{ $row->plvDurasiLabel() }}</span>
                        @elseif($row->checkin_at)
                        <span class="plv-durasi-live font-mono font-semibold text-primary tabular-nums" data-checkin-at="{{ $row->checkin_at->timezone(config('app.timezone'))->toIso8601String() }}">{{ $row->plvDurasiLabel() }}</span>
                        @else
                        <span class="text-on-surface-variant">—</span>
                        @endif
                     </td>
                     <td class="px-4 py-3.5 text-sm">
                        @if($row->checkout_at)
                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-800">Checkout</span>
                        @else
                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-800">Di Area</span>
                        @endif
                     </td>
                     <td class="px-4 py-3.5 text-sm">
                        <span class="inline-flex rounded-full bg-[#eef2ff] px-2.5 py-0.5 text-[10px] font-semibold text-primary">Shift {{ $row->shift }}</span>
                     </td>
                  </tr>
                  @empty
                  <tr>
                     <td colspan="10" class="px-6 py-14 text-center">
                        <p class="text-sm text-on-surface-variant">
                           @if($supervisedRooms->isEmpty())
                              Tidak ada control room terdaftar untuk akun Anda.
                           @else
                              Tidak ada data inputasi LV pada tanggal ini.
                           @endif
                        </p>
                     </td>
                  </tr>
                  @endforelse
               </tbody>
            </table>
         </div>
      </div>

      <div id="plv-history-panel-orang" role="tabpanel" aria-labelledby="plv-history-tab-orang" data-plv-history-panel="orang" class="hidden">
         <div class="px-5 sm:px-6 py-3 flex justify-end border-b border-outline-variant/5 bg-[#fafbfc]/50">
            <button type="button" data-plv-open-inputasi="orang" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:text-primary-dim transition-colors">
               <span class="material-symbols-outlined text-base">add_circle</span>
               Tambah Orang
            </button>
         </div>
         <div class="overflow-x-auto">
            <table class="ab-table-soft w-full min-w-[1000px] text-left">
               <thead class="bg-[#fafbfc]/80">
                  <tr class="border-b border-outline-variant/10">
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">No</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">SID</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Nama</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Perusahaan</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Dept</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Control Room</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Lokasi</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Check-in</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Check-out</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Durasi</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Status</th>
                     <th class="px-4 py-3 text-[10px] uppercase text-on-surface-variant/70">Shift</th>
                  </tr>
               </thead>
               <tbody class="divide-y divide-outline-variant/5">
                  @forelse($orangAllList as $index => $row)
                  <tr class="hover:bg-[#f8fafc]/80">
                     <td class="px-4 py-3.5 text-sm tabular-nums text-on-surface-variant">{{ $index + 1 }}</td>
                     <td class="px-4 py-3.5 text-sm font-semibold text-on-background">{{ $row->sid }}</td>
                     <td class="px-4 py-3.5 text-sm text-on-surface">{{ $row->nama }}</td>
                     <td class="px-4 py-3.5 text-sm text-on-surface">{{ $row->nama_perusahaan ?: '—' }}</td>
                     <td class="px-4 py-3.5 text-sm text-on-surface">{{ $row->dept ?: '—' }}</td>
                     <td class="px-4 py-3.5 text-sm text-on-surface">{{ $row->control_room }}</td>
                     <td class="px-4 py-3.5 text-sm text-on-surface">
                        <div>{{ $row->lokasi }}</div>
                        @if($row->detail_lokasi)
                        <div class="text-xs text-on-surface-variant mt-0.5">{{ $row->detail_lokasi }}</div>
                        @endif
                     </td>
                     <td class="px-4 py-3.5 text-sm text-on-surface whitespace-nowrap">{{ $row->checkin_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                     <td class="px-4 py-3.5 text-sm text-on-surface whitespace-nowrap">
                        @if($row->checkout_at)
                           {{ $row->checkout_at->timezone(config('app.timezone'))->format('d M Y H:i') }}
                        @else
                           <span class="text-on-surface-variant">—</span>
                        @endif
                     </td>
                     <td class="px-4 py-3.5 text-sm whitespace-nowrap">
                        @if($row->checkout_at)
                        <span class="font-mono font-semibold text-on-surface tabular-nums">{{ $row->plvDurasiLabel() }}</span>
                        @elseif($row->checkin_at)
                        <span class="plv-durasi-live font-mono font-semibold text-primary tabular-nums" data-checkin-at="{{ $row->checkin_at->timezone(config('app.timezone'))->toIso8601String() }}">{{ $row->plvDurasiLabel() }}</span>
                        @else
                        <span class="text-on-surface-variant">—</span>
                        @endif
                     </td>
                     <td class="px-4 py-3.5 text-sm">
                        @if($row->checkout_at)
                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-800">Checkout</span>
                        @else
                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-800">Di Area</span>
                        @endif
                     </td>
                     <td class="px-4 py-3.5 text-sm">
                        <span class="inline-flex rounded-full bg-[#eef2ff] px-2.5 py-0.5 text-[10px] font-semibold text-primary">Shift {{ $row->shift }}</span>
                     </td>
                  </tr>
                  @empty
                  <tr>
                     <td colspan="12" class="px-6 py-14 text-center">
                        <p class="text-sm text-on-surface-variant">
                           @if($supervisedRooms->isEmpty())
                              Tidak ada control room terdaftar untuk akun Anda.
                           @else
                              Tidak ada data inputasi orang pada tanggal ini.
                           @endif
                        </p>
                     </td>
                  </tr>
                  @endforelse
               </tbody>
            </table>
         </div>
      </div>
   </section>

   @include('PembatasanLV.inputasi.partials.modals')
   @include('PembatasanLV.partials.planning-overview-modal')
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
   function setupTabs(tabAttr, panelAttr) {
      var tabs = document.querySelectorAll('[' + tabAttr + ']');
      var panels = document.querySelectorAll('[' + panelAttr + ']');
      if (!tabs.length) return;
      tabs.forEach(function (tab) {
         tab.addEventListener('click', function () {
            var name = tab.getAttribute(tabAttr);
            tabs.forEach(function (t) {
               t.setAttribute('aria-selected', t.getAttribute(tabAttr) === name ? 'true' : 'false');
            });
            panels.forEach(function (panel) {
               panel.classList.toggle('hidden', panel.getAttribute(panelAttr) !== name);
            });
         });
      });
   }
   setupTabs('data-plv-live-tab', 'data-plv-live-panel');
   setupTabs('data-plv-history-tab', 'data-plv-history-panel');

   function showFlash() {
      @if(session('success'))
      if (typeof Swal !== 'undefined') {
         Swal.fire({ icon: 'success', title: 'Berhasil', text: @json(session('success')), confirmButtonText: 'OK', confirmButtonColor: '#3952bc' });
      }
      @endif
      @if(session('error'))
      if (typeof Swal !== 'undefined') {
         Swal.fire({ icon: 'error', title: 'Gagal', text: @json(session('error')), confirmButtonText: 'OK', confirmButtonColor: '#3952bc' });
      }
      @endif
   }

   document.addEventListener('submit', function (e) {
      var lvForm = e.target.closest('.plv-checkout-form');
      var orangForm = e.target.closest('.plv-checkout-orang-form');
      var form = lvForm || orangForm;
      if (!form) return;
      e.preventDefault();
      var message, title;
      if (orangForm) {
         var nama = orangForm.getAttribute('data-nama') || 'Orang';
         var sid = orangForm.getAttribute('data-sid') || '';
         message = sid !== '' ? 'Checkout ' + nama + ' (SID: ' + sid + ') sekarang?' : 'Checkout ' + nama + ' sekarang?';
         title = 'Checkout Orang?';
      } else {
         var unit = form.getAttribute('data-unit') || 'LV';
         var driver = form.getAttribute('data-driver') || '';
         message = driver !== '' ? 'Checkout unit ' + unit + ' (Driver: ' + driver + ') sekarang?' : 'Checkout unit ' + unit + ' sekarang?';
         title = 'Checkout LV?';
      }
      if (typeof Swal === 'undefined') {
         if (window.confirm(message)) form.submit();
         return;
      }
      Swal.fire({
         title: title, text: message, icon: 'question',
         showCancelButton: true, confirmButtonText: 'Ya, Checkout', cancelButtonText: 'Batal',
         confirmButtonColor: '#059669', cancelButtonColor: '#94a3b8', reverseButtons: true,
      }).then(function (result) { if (result.isConfirmed) form.submit(); });
   });

   var masukAktifUrl = @json(route('pembatasan-lv.lv-masuk-aktif.data'));
   var orangMasukAktifUrl = @json(route('pembatasan-lv.orang-masuk-aktif.data'));
   var checkoutUrlBase = @json(url('/pembatasan-lv/checkout'));
   var checkoutOrangUrlBase = @json(url('/pembatasan-lv/checkout-orang'));
   var csrfToken = @json(csrf_token());
   var filterParams = @json($filters);
   var supervisedRoomsEmpty = @json($supervisedRooms->isEmpty());
   var serverOffsetMs = 0;

   function escapeHtml(value) {
      return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
   }

   function emptyLiveHtml(icon, title, subtitle, colspan) {
      return '<tr><td colspan="' + (colspan || 6) + '" class="px-3 py-10">' +
         '<div class="ab-empty-illus mx-auto max-w-sm rounded-2xl px-6 py-8 text-center">' +
            '<span class="material-symbols-outlined text-4xl text-primary/25 mb-3 block">' + icon + '</span>' +
            '<p class="text-sm font-medium text-on-surface">' + escapeHtml(title) + '</p>' +
            '<p class="text-xs text-on-surface-variant mt-1">' + escapeHtml(subtitle) + '</p>' +
         '</div></td></tr>';
   }

   function formatDurasi(totalSeconds) {
      totalSeconds = Math.max(0, Math.floor(totalSeconds));
      var h = Math.floor(totalSeconds / 3600);
      var m = Math.floor((totalSeconds % 3600) / 60);
      var s = totalSeconds % 60;
      var pad = function (n) { return String(n).padStart(2, '0'); };
      if (h >= 24) {
         var d = Math.floor(h / 24);
         h = h % 24;
         return d + 'h ' + pad(h) + ':' + pad(m) + ':' + pad(s);
      }
      return pad(h) + ':' + pad(m) + ':' + pad(s);
   }

   function durasiSecondsFromCheckin(checkinIso) {
      if (!checkinIso) return 0;
      var start = Date.parse(checkinIso);
      if (Number.isNaN(start)) return 0;
      return Math.max(0, Math.floor((Date.now() + serverOffsetMs - start) / 1000));
   }

   function tickDurasiLive() {
      document.querySelectorAll('.plv-durasi-live').forEach(function (el) {
         el.textContent = formatDurasi(durasiSecondsFromCheckin(el.getAttribute('data-checkin-at')));
      });
   }

   function renderMasukAktifRows(rows) {
      var tbody = document.getElementById('plv-lv-masuk-aktif-tbody');
      if (!tbody) return;
      if (!rows.length) {
         tbody.innerHTML = emptyLiveHtml(
            'local_shipping',
            supervisedRoomsEmpty ? 'Tidak ada control room terdaftar' : 'Belum ada LV di area',
            'Data akan muncul setelah ada check-in'
         );
         return;
      }
      tbody.innerHTML = rows.map(function (row, index) {
         return '<tr class="hover:bg-[#f8fafc]/80" data-inputasi-id="' + row.id + '">' +
            '<td class="px-3 py-3 text-sm tabular-nums text-on-surface-variant">' + (index + 1) + '</td>' +
            '<td class="px-3 py-3 text-sm text-on-surface">' + escapeHtml(row.nama_driver) + '</td>' +
            '<td class="px-3 py-3 text-sm font-semibold text-on-background">' + escapeHtml(row.no_lambung) + '</td>' +
            '<td class="px-3 py-3 text-sm text-on-surface"><div>' + escapeHtml(row.lokasi) + '</div>' +
               (row.detail_lokasi ? '<div class="text-xs text-on-surface-variant mt-0.5">' + escapeHtml(row.detail_lokasi) + '</div>' : '') + '</td>' +
            '<td class="px-3 py-3 text-sm whitespace-nowrap"><span class="plv-durasi-live font-mono font-semibold text-primary tabular-nums" data-checkin-at="' + escapeHtml(row.checkin_at) + '">' + formatDurasi(row.durasi_detik || 0) + '</span></td>' +
            '<td class="px-3 py-3 text-sm"><form method="POST" action="' + checkoutUrlBase + '/' + row.id + '" class="plv-checkout-form inline" data-unit="' + escapeHtml(row.no_lambung) + '" data-driver="' + escapeHtml(row.nama_driver) + '">' +
               '<input type="hidden" name="_token" value="' + escapeHtml(csrfToken) + '">' +
               '<button type="submit" class="inline-flex items-center gap-0.5 rounded-lg bg-emerald-600/90 px-2.5 py-1 text-[10px] font-semibold text-white transition-colors hover:bg-emerald-700">' +
                  '<span class="material-symbols-outlined text-sm">logout</span> Checkout</button></form></td></tr>';
      }).join('');
      tickDurasiLive();
   }

   function fetchMasukAktif() {
      var url = new URL(masukAktifUrl, window.location.origin);
      if (filterParams.site) url.searchParams.set('site', filterParams.site);
      if (filterParams.tanggal) url.searchParams.set('tanggal', filterParams.tanggal);
      if (filterParams.control_room) url.searchParams.set('control_room', filterParams.control_room);
      fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
         .then(function (res) { return res.json(); })
         .then(function (json) {
            if (json.meta && json.meta.server_now) {
               var serverNow = Date.parse(json.meta.server_now);
               if (!Number.isNaN(serverNow)) serverOffsetMs = serverNow - Date.now();
            }
            renderMasukAktifRows(json.data || []);
         })
         .catch(function () {});
   }

   function renderOrangMasukAktifRows(rows) {
      var tbody = document.getElementById('plv-orang-masuk-aktif-tbody');
      if (!tbody) return;
      if (!rows.length) {
         tbody.innerHTML = emptyLiveHtml(
            'groups',
            supervisedRoomsEmpty ? 'Tidak ada control room terdaftar' : 'Belum ada personel di area',
            'Data akan muncul setelah ada check-in',
            6
         );
         return;
      }
      tbody.innerHTML = rows.map(function (row, index) {
         return '<tr class="hover:bg-[#f8fafc]/80" data-inputasi-id="' + row.id + '">' +
            '<td class="px-3 py-3 text-sm tabular-nums text-on-surface-variant">' + (index + 1) + '</td>' +
            '<td class="px-3 py-3 text-sm text-on-surface">' + escapeHtml(row.nama) + '</td>' +
            '<td class="px-3 py-3 text-sm font-semibold text-on-background">' + escapeHtml(row.sid) + '</td>' +
            '<td class="px-3 py-3 text-sm text-on-surface"><div>' + escapeHtml(row.lokasi) + '</div>' +
               (row.detail_lokasi ? '<div class="text-xs text-on-surface-variant mt-0.5">' + escapeHtml(row.detail_lokasi) + '</div>' : '') + '</td>' +
            '<td class="px-3 py-3 text-sm whitespace-nowrap"><span class="plv-durasi-live font-mono font-semibold text-primary tabular-nums" data-checkin-at="' + escapeHtml(row.checkin_at) + '">' + formatDurasi(row.durasi_detik || 0) + '</span></td>' +
            '<td class="px-3 py-3 text-sm"><form method="POST" action="' + checkoutOrangUrlBase + '/' + row.id + '" class="plv-checkout-orang-form inline" data-sid="' + escapeHtml(row.sid) + '" data-nama="' + escapeHtml(row.nama) + '">' +
               '<input type="hidden" name="_token" value="' + escapeHtml(csrfToken) + '">' +
               '<button type="submit" class="inline-flex items-center gap-0.5 rounded-lg bg-emerald-600/90 px-2.5 py-1 text-[10px] font-semibold text-white transition-colors hover:bg-emerald-700">' +
                  '<span class="material-symbols-outlined text-sm">logout</span> Checkout</button></form></td></tr>';
      }).join('');
      tickDurasiLive();
   }

   function fetchOrangMasukAktif() {
      var url = new URL(orangMasukAktifUrl, window.location.origin);
      if (filterParams.site) url.searchParams.set('site', filterParams.site);
      if (filterParams.tanggal) url.searchParams.set('tanggal', filterParams.tanggal);
      if (filterParams.control_room) url.searchParams.set('control_room', filterParams.control_room);
      fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
         .then(function (res) { return res.json(); })
         .then(function (json) {
            if (json.meta && json.meta.server_now) {
               var serverNow = Date.parse(json.meta.server_now);
               if (!Number.isNaN(serverNow)) serverOffsetMs = serverNow - Date.now();
            }
            renderOrangMasukAktifRows(json.data || []);
         })
         .catch(function () {});
   }

   tickDurasiLive();
   setInterval(tickDurasiLive, 1000);
   setInterval(fetchMasukAktif, 30000);
   setInterval(fetchOrangMasukAktif, 30000);
   fetchMasukAktif();
   fetchOrangMasukAktif();
   window.fetchMasukAktif = fetchMasukAktif;
   window.fetchOrangMasukAktif = fetchOrangMasukAktif;
   showFlash();
})();
</script>
@endpush
