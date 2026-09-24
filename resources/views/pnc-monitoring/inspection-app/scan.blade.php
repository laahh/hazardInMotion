@extends('pnc-monitoring.inspection-app.layout')

@section('title', 'Scan Alat')
@section('header-title', 'Scan Alat')

@section('content')
<div class="px-5 pt-5 space-y-6 pb-4">

  <section>
    <div class="relative w-full aspect-square rounded-[2rem] bg-zinc-900 overflow-hidden flex items-center justify-center">
      <div class="absolute inset-0 bg-gradient-to-b from-black/20 via-transparent to-black/40"></div>

      <div class="relative w-[72%] aspect-square">
        <div class="absolute -top-1 -left-1 w-10 h-10 border-t-4 border-l-4 border-emerald-400 rounded-tl-2xl"></div>
        <div class="absolute -top-1 -right-1 w-10 h-10 border-t-4 border-r-4 border-emerald-400 rounded-tr-2xl"></div>
        <div class="absolute -bottom-1 -left-1 w-10 h-10 border-b-4 border-l-4 border-emerald-400 rounded-bl-2xl"></div>
        <div class="absolute -bottom-1 -right-1 w-10 h-10 border-b-4 border-r-4 border-emerald-400 rounded-br-2xl"></div>
        <div class="absolute left-0 right-0 top-1/2 h-0.5 bg-emerald-400/80 shadow-[0_0_12px_2px_rgba(52,211,153,0.6)] animate-pulse"></div>
      </div>

      <span class="material-symbols-outlined text-white/10 text-[160px] absolute">photo_camera</span>

      <button type="button" class="absolute top-4 right-4 w-11 h-11 rounded-full bg-white/15 backdrop-blur-md flex items-center justify-center text-white active:scale-90 transition-transform">
        <span class="material-symbols-outlined">flash_on</span>
      </button>
    </div>
    <p class="text-center text-sm text-on-surface-variant mt-4">Arahkan kamera ke QR / barcode pada label alat</p>
    <p class="text-center text-xs text-on-surface-variant/70 mt-1">(Fitur kamera akan diaktifkan setelah tahap UI/UX ini disetujui)</p>
  </section>

  <section class="flex items-center gap-3">
    <div class="flex-1 h-px bg-outline-variant/40"></div>
    <span class="text-xs font-bold text-on-surface-variant uppercase tracking-widest">atau</span>
    <div class="flex-1 h-px bg-outline-variant/40"></div>
  </section>

  <section>
    <form method="GET" action="{{ route('pnc-monitoring.inventory-inspection.home') }}" class="relative flex items-center">
      <span class="absolute left-4 material-symbols-outlined text-on-surface-variant">search</span>
      <input
        type="text" name="q"
        class="w-full pl-12 pr-24 py-4 bg-surface-container-lowest border border-outline-variant/60 rounded-2xl shadow-sm focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface placeholder:text-on-surface-variant/60"
        placeholder="Cari manual nama alat...">
      <button type="submit" class="absolute right-2 px-4 py-2.5 rounded-xl bg-primary text-white font-bold text-sm active:scale-95 transition-transform">Cari</button>
    </form>
  </section>

  @if ($recentTools->isNotEmpty())
    <section class="space-y-3 pb-4">
      <h2 class="text-sm font-bold text-on-surface-variant uppercase tracking-wide">Baru ditambahkan</h2>
      <div class="flex gap-3 overflow-x-auto no-scrollbar -mx-5 px-5">
        @foreach ($recentTools as $tool)
          <a href="{{ route('pnc-monitoring.inventory-inspection.tools.show', $tool) }}" class="min-w-[140px] bg-surface-container-low rounded-3xl p-3 space-y-2">
            <div class="h-20 w-full rounded-2xl overflow-hidden bg-surface-container-high flex items-center justify-center">
              @if ($tool->image_url)
                <img src="{{ $tool->imageDisplayUrl() }}" alt="{{ $tool->standard_name }}" class="w-full h-full object-cover">
              @else
                <span class="material-symbols-outlined text-on-surface-variant text-2xl">construction</span>
              @endif
            </div>
            <h4 class="font-bold text-on-surface text-xs truncate">{{ $tool->standard_name }}</h4>
            <p class="text-[10px] text-on-surface-variant truncate">{{ $tool->category?->name }}</p>
          </a>
        @endforeach
      </div>
    </section>
  @endif
</div>
@endsection
