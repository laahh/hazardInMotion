@extends('pnc-monitoring.inspection-app.layout')

@section('title', 'Inspeksi: '.$tool->standard_name)
@section('header-title', 'Form Inspeksi')
@section('back-url', route('pnc-monitoring.inventory-inspection.tools.show', $tool))

@section('content')
<div class="px-5 space-y-6 pb-8">

  <section class="p-4 rounded-3xl bg-surface-container-lowest border border-outline-variant/10 flex items-center gap-3">
    <div class="w-12 h-12 rounded-2xl overflow-hidden bg-surface-container-high flex items-center justify-center shrink-0">
      @if ($tool->image_url)
        <img src="{{ $tool->imageDisplayUrl() }}" alt="{{ $tool->standard_name }}" class="w-full h-full object-cover">
      @else
        <span class="material-symbols-outlined text-on-surface-variant">construction</span>
      @endif
    </div>
    <div class="min-w-0">
      <p class="font-bold text-sm text-on-surface truncate">{{ $tool->standard_name }}</p>
      <p class="text-xs text-on-surface-variant truncate">{{ $tool->category?->name }}</p>
    </div>
  </section>

  <section>
    <h3 class="text-sm font-bold text-on-surface-variant uppercase tracking-wide mb-2">1. Pilih Unit Aset</h3>
    @if ($tool->assets->isNotEmpty())
      <div class="flex flex-wrap gap-2">
        @foreach ($tool->assets as $index => $asset)
          <label class="relative">
            <input type="radio" name="asset_id" value="{{ $asset->asset_id }}" class="sr-only peer" @checked($index === 0)>
            <span class="block px-4 py-2.5 rounded-2xl border-2 border-outline-variant/30 text-sm font-semibold text-on-surface-variant cursor-pointer peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary-container transition-all">
              {{ $asset->inventory_id ?? ('Unit #'.$asset->asset_id) }}
            </span>
          </label>
        @endforeach
      </div>
    @else
      <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 flex items-start gap-3">
        <span class="material-symbols-outlined text-amber-700">warning</span>
        <div>
          <p class="text-sm font-semibold text-amber-900">Belum ada unit aset terdaftar</p>
          <p class="text-xs text-amber-800/80 mt-0.5">Daftarkan unit fisik jenis alat ini dulu di Master Data &raquo; Unit Aset sebelum inspeksi bisa disimpan.</p>
        </div>
      </div>
    @endif
  </section>

  <section>
    <div class="flex items-center justify-between mb-2">
      <h3 class="text-sm font-bold text-on-surface-variant uppercase tracking-wide">2. Checklist Pemeriksaan</h3>
      @if ($tool->checklistItems->isNotEmpty())
        <span class="text-xs font-semibold text-on-surface-variant">{{ $tool->checklistItems->count() }} poin</span>
      @endif
    </div>

    @forelse ($tool->checklistItems as $item)
      <div class="p-4 rounded-2xl bg-surface-container-lowest border border-outline-variant/10 mb-3">
        <div class="flex gap-3 mb-3">
          <span class="w-7 h-7 rounded-full bg-surface-container-high text-on-surface-variant text-xs font-bold flex items-center justify-center shrink-0">{{ $item->sequence }}</span>
          <div class="min-w-0">
            <p class="font-semibold text-sm text-on-surface">{{ $item->komponen_diperiksa }}</p>
            <p class="text-xs text-on-surface-variant mt-0.5">{{ $item->kriteria_pemeriksaan }}</p>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-2">
          @foreach ([['ok', 'OK', 'check_circle', 'emerald'], ['not_ok', 'Tidak OK', 'cancel', 'red'], ['na', 'N/A', 'remove_circle', 'zinc'], ] as [$val, $label, $icon, $color])
            <label class="relative">
              <input type="radio" name="checklist[{{ $item->id }}]" value="{{ $val }}" class="sr-only peer">
              <span class="flex flex-col items-center gap-1 py-2.5 rounded-xl border-2 border-outline-variant/30 text-on-surface-variant cursor-pointer peer-checked:border-{{ $color }}-500 peer-checked:bg-{{ $color }}-50 peer-checked:text-{{ $color }}-700 transition-all">
                <span class="material-symbols-outlined text-[18px]">{{ $icon }}</span>
                <span class="text-[10px] font-bold">{{ $label }}</span>
              </span>
            </label>
          @endforeach
        </div>
      </div>
    @empty
      <div class="text-center py-12 text-on-surface-variant">
        <span class="material-symbols-outlined text-4xl opacity-30">checklist</span>
        <p class="mt-2 text-sm">Belum ada checklist untuk alat ini.</p>
      </div>
    @endforelse
  </section>

  <section>
    <h3 class="text-sm font-bold text-on-surface-variant uppercase tracking-wide mb-2">3. Hasil Keseluruhan</h3>
    <div class="grid grid-cols-3 gap-2 mb-3">
      @foreach ([['Pass', 'Lulus', 'emerald'], ['Conditional', 'Bersyarat', 'amber'], ['Fail', 'Tidak Lulus', 'red']] as [$val, $label, $color])
        <label class="relative">
          <input type="radio" name="overall_result" value="{{ $val }}" class="sr-only peer">
          <span class="block text-center py-3 rounded-2xl border-2 border-outline-variant/30 text-sm font-bold text-on-surface-variant cursor-pointer peer-checked:border-{{ $color }}-500 peer-checked:bg-{{ $color }}-50 peer-checked:text-{{ $color }}-700 transition-all">
            {{ $label }}
          </span>
        </label>
      @endforeach
    </div>
    <textarea rows="3" placeholder="Catatan tambahan (opsional)..." class="w-full p-4 bg-surface-container-highest border-none rounded-2xl focus:ring-2 focus:ring-primary/40 focus:bg-surface-container-lowest transition-all text-sm text-on-surface placeholder:text-on-surface-variant/60"></textarea>
  </section>

  <section>
    <button type="button" class="flex items-center justify-center gap-2 w-full py-3.5 rounded-2xl border-2 border-dashed border-outline-variant/40 text-on-surface-variant font-semibold text-sm active:scale-95 transition-transform">
      <span class="material-symbols-outlined">add_a_photo</span>Lampirkan Foto (opsional)
    </button>
  </section>
</div>

<div class="fixed bottom-24 left-0 w-full px-5 max-w-[480px] mx-auto z-40">
  <button type="button"
    onclick="alert('Ini masih tahap UI/UX — penyimpanan inspeksi akan disambungkan ke backend setelah desain disetujui.')"
    class="flex items-center justify-center gap-2 w-full h-14 rounded-full bg-gradient-to-br from-primary-container to-primary text-white font-bold text-base shadow-[0_8px_24px_rgba(0,106,63,0.3)] active:scale-95 transition-transform">
    <span class="material-symbols-outlined">save</span>Simpan Inspeksi
  </button>
</div>
@endsection
