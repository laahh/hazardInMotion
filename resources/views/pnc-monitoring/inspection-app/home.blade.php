@extends('pnc-monitoring.inspection-app.layout')

@section('title', 'Inspeksi Alat')
@section('header-title', 'Inspeksi Alat')

@section('content')
<div class="px-5 pt-5 space-y-6">

  <section>
    <h1 class="text-2xl font-extrabold tracking-tight text-on-surface leading-tight">Halo, Teknisi! 👋</h1>
    <p class="text-sm text-on-surface-variant mt-1.5 leading-relaxed">{{ number_format($totalTools) }} jenis alat terdaftar di katalog. Pilih alat untuk lihat panduan, checklist, dan mulai inspeksi.</p>
  </section>

  <section class="flex items-center gap-3">
    <form method="GET" class="relative flex-1">
      <span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-on-surface-variant text-[20px]">search</span>
      <input
        type="text" name="q" value="{{ $q }}"
        class="w-full pl-11 pr-4 py-3.5 bg-surface-container-lowest border border-outline-variant/60 rounded-2xl shadow-sm focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm text-on-surface placeholder:text-on-surface-variant/60"
        placeholder="Cari nama alat...">
      @if ($categoryId)
        <input type="hidden" name="category_id" value="{{ $categoryId }}">
      @endif
    </form>
    <button type="button" class="w-12 h-12 shrink-0 flex items-center justify-center rounded-2xl bg-primary text-white shadow-sm active:scale-95 transition-transform">
      <span class="material-symbols-outlined text-[20px]">tune</span>
    </button>
  </section>

  <section class="overflow-x-auto no-scrollbar -mx-5">
    @php
      $catIcons = ['001' => 'handyman', '002' => 'precision_manufacturing', '003' => 'construction', '004' => 'conveyor_belt', '005' => 'engineering'];
    @endphp
    <div class="flex gap-2.5 px-5">
      <a href="{{ route('pnc-monitoring.inventory-inspection.home', ['q' => $q]) }}"
         class="whitespace-nowrap flex items-center gap-1.5 px-4 py-2.5 rounded-full font-bold text-sm transition-colors {{ $categoryId === null ? 'bg-primary text-white shadow-sm' : 'bg-surface-container-lowest text-on-surface-variant border border-outline-variant/60' }}">
        <span class="material-symbols-outlined text-[16px]">grid_view</span>Semua
      </a>
      @foreach ($categories as $cat)
        <a href="{{ route('pnc-monitoring.inventory-inspection.home', ['q' => $q, 'category_id' => $cat->category_id]) }}"
           class="whitespace-nowrap flex items-center gap-1.5 px-4 py-2.5 rounded-full font-semibold text-sm transition-colors {{ (int) $categoryId === $cat->category_id ? 'bg-primary text-white shadow-sm' : 'bg-surface-container-lowest text-on-surface-variant border border-outline-variant/60' }}">
          <span class="material-symbols-outlined text-[16px]">{{ $catIcons[$cat->code] ?? 'category' }}</span>{{ $cat->name }}
        </a>
      @endforeach
    </div>
  </section>

  <section class="space-y-3 pb-6">
    <div class="flex justify-between items-center">
      <h2 class="text-base font-extrabold tracking-tight flex items-center gap-1.5">
        <span class="w-1.5 h-4 bg-primary rounded-full inline-block"></span>Daftar Alat
      </h2>
      <span class="text-xs font-bold text-primary">{{ $tools->count() }} alat</span>
    </div>

    @if ($tools->isNotEmpty())
      <div class="grid grid-cols-2 gap-3">
        @foreach ($tools as $tool)
          <a href="{{ route('pnc-monitoring.inventory-inspection.tools.show', $tool) }}"
             class="bg-surface-container-lowest rounded-3xl p-2.5 shadow-sm border border-outline-variant/40 hover:shadow-md transition-shadow">
            <div class="h-24 w-full rounded-2xl overflow-hidden bg-gradient-to-br from-primary-light to-surface-container-high flex items-center justify-center mb-2.5">
              @if ($tool->image_url)
                <img src="{{ $tool->imageDisplayUrl() }}" alt="{{ $tool->standard_name }}" class="w-full h-full object-cover">
              @else
                <span class="material-symbols-outlined text-primary/40 text-4xl">construction</span>
              @endif
            </div>
            <div class="flex items-start justify-between gap-1">
              <h3 class="font-bold text-on-surface text-sm leading-tight line-clamp-2">{{ $tool->standard_name }}</h3>
              <span class="material-symbols-outlined text-on-surface-variant text-[18px] shrink-0 mt-0.5">chevron_right</span>
            </div>
            <p class="text-[11px] text-on-surface-variant mt-1 truncate">{{ $tool->category?->name }}</p>
          </a>
        @endforeach
      </div>
    @else
      <div class="text-center py-16 text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl opacity-40">search_off</span>
        <p class="mt-2 text-sm">Tidak ada alat yang cocok.</p>
      </div>
    @endif
  </section>
</div>
@endsection
