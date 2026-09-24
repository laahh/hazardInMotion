@extends('pnc-monitoring.inspection-app.layout')

@section('title', 'Inspeksi Alat')
@section('header-title', 'Inspeksi Alat')

@section('content')
<div class="px-5 space-y-6">

  <section>
    <h1 class="text-2xl font-extrabold tracking-tight text-on-surface leading-tight">Halo, Teknisi 👋</h1>
    <p class="text-sm text-on-surface-variant mt-1">{{ number_format($totalTools) }} jenis alat terdaftar di Katalog Alat. Pilih alat untuk lihat panduan, checklist, dan mulai inspeksi.</p>
  </section>

  <section>
    <form method="GET" class="relative flex items-center">
      <span class="absolute left-4 material-symbols-outlined text-on-surface-variant">search</span>
      <input
        type="text" name="q" value="{{ $q }}"
        class="w-full pl-12 pr-4 py-4 bg-surface-container-highest border-none rounded-2xl focus:ring-2 focus:ring-primary/40 focus:bg-surface-container-lowest transition-all text-on-surface placeholder:text-on-surface-variant/60"
        placeholder="Cari nama alat...">
    </form>
  </section>

  <section class="overflow-x-auto no-scrollbar -mx-5">
    <div class="flex gap-3 px-5">
      <a href="{{ route('pnc-monitoring.inventory-inspection.home', ['q' => $q]) }}"
         class="whitespace-nowrap px-6 py-2.5 rounded-full font-semibold text-sm transition-colors {{ $categoryId === null ? 'bg-primary-container text-on-primary-container shadow-lg shadow-primary/10' : 'bg-surface-container-high text-on-surface-variant hover:bg-surface-container-highest' }}">
        Semua
      </a>
      @foreach ($categories as $cat)
        <a href="{{ route('pnc-monitoring.inventory-inspection.home', ['q' => $q, 'category_id' => $cat->category_id]) }}"
           class="whitespace-nowrap px-6 py-2.5 rounded-full font-semibold text-sm transition-colors {{ (int) $categoryId === $cat->category_id ? 'bg-primary-container text-on-primary-container shadow-lg shadow-primary/10' : 'bg-surface-container-high text-on-surface-variant hover:bg-surface-container-highest' }}">
          {{ $cat->name }}
        </a>
      @endforeach
    </div>
  </section>

  <section class="space-y-4 pb-4">
    <div class="flex justify-between items-end">
      <h2 class="text-lg font-extrabold tracking-tight">Daftar Alat</h2>
      <span class="text-xs font-semibold text-on-surface-variant">{{ $tools->count() }} ditampilkan</span>
    </div>

    @forelse ($tools as $tool)
      <a href="{{ route('pnc-monitoring.inventory-inspection.tools.show', $tool) }}"
         class="flex items-center gap-4 p-3 rounded-3xl bg-surface-container-lowest shadow-sm hover:shadow-lg border border-outline-variant/10 transition-all">
        <div class="w-16 h-16 rounded-2xl overflow-hidden bg-surface-container-high flex items-center justify-center shrink-0">
          @if ($tool->image_url)
            <img src="{{ $tool->imageDisplayUrl() }}" alt="{{ $tool->standard_name }}" class="w-full h-full object-cover">
          @else
            <span class="material-symbols-outlined text-on-surface-variant text-3xl">construction</span>
          @endif
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-bold text-on-surface truncate">{{ $tool->standard_name }}</h3>
          <p class="text-xs text-on-surface-variant truncate">{{ $tool->category?->name }} @if($tool->sub_category) &middot; {{ $tool->sub_category }} @endif</p>
          <div class="flex items-center gap-2 mt-1.5">
            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-on-surface-variant bg-surface-container-high px-2 py-0.5 rounded-full">
              <span class="material-symbols-outlined text-[13px]">checklist</span>{{ $tool->checklist_items_count }} poin
            </span>
            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-on-surface-variant bg-surface-container-high px-2 py-0.5 rounded-full">
              <span class="material-symbols-outlined text-[13px]">inventory_2</span>{{ $tool->assets_count }} unit
            </span>
            @if ($tool->is_regulated)
              <span class="inline-flex items-center gap-1 text-[11px] font-bold text-tertiary bg-tertiary-container/10 px-2 py-0.5 rounded-full">
                <span class="material-symbols-outlined text-[13px]">verified</span>Regulasi
              </span>
            @endif
          </div>
        </div>
        <span class="material-symbols-outlined text-on-surface-variant">chevron_right</span>
      </a>
    @empty
      <div class="text-center py-16 text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl opacity-40">search_off</span>
        <p class="mt-2 text-sm">Tidak ada alat yang cocok.</p>
      </div>
    @endforelse
  </section>
</div>
@endsection
