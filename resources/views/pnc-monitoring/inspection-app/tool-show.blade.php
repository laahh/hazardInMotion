@extends('pnc-monitoring.inspection-app.layout')

@section('title', $tool->standard_name)
@section('header-title', \Illuminate\Support\Str::limit($tool->standard_name, 20))
@section('back-url', route('pnc-monitoring.inventory-inspection.home'))

@section('content')
<div class="px-5 pt-5 space-y-5 pb-8">

  <section>
    <div class="relative w-full h-56 rounded-[2rem] overflow-hidden bg-gradient-to-br from-primary-light via-surface-container to-surface-container-high flex items-center justify-center">
      <svg class="absolute inset-0 w-full h-full opacity-[0.15]" viewBox="0 0 300 150" preserveAspectRatio="xMidYMax slice">
        <path d="M0 150 L50 70 L90 110 L140 40 L190 100 L230 60 L300 150 Z" fill="#0E8A4F"/>
      </svg>
      @if ($tool->image_url)
        <img src="{{ $tool->imageDisplayUrl() }}" alt="{{ $tool->standard_name }}" class="relative w-full h-full object-cover">
      @else
        <span class="material-symbols-outlined text-primary/30 text-[100px] relative">construction</span>
      @endif
    </div>
  </section>

  <section>
    <span class="inline-block text-xs font-bold text-primary border border-primary/30 bg-primary-light px-3 py-1 rounded-full">{{ $tool->category?->name ?? 'Umum' }}</span>
    <h1 class="text-2xl font-extrabold text-on-surface leading-tight mt-2">{{ $tool->standard_name }}</h1>
    @if ($tool->sub_category)
      <p class="text-sm text-on-surface-variant mt-0.5">{{ $tool->sub_category }}</p>
    @endif
  </section>

  <section class="grid grid-cols-3 gap-2.5">
    @php
      $badges = [
        ['icon' => 'shield', 'label' => 'Aman'],
        ['icon' => 'settings', 'label' => 'Efisien'],
        ['icon' => 'eco', 'label' => 'Andal'],
      ];
    @endphp
    @foreach ($badges as $badge)
      <div class="flex flex-col items-center justify-center gap-1.5 bg-surface-container-lowest rounded-2xl py-3.5 shadow-sm border border-outline-variant/40">
        <span class="material-symbols-outlined text-primary text-[20px]">{{ $badge['icon'] }}</span>
        <span class="text-[11px] font-bold text-on-surface-variant text-center leading-tight">{{ $badge['label'] }}</span>
      </div>
    @endforeach
  </section>

  <section class="bg-surface-container-lowest rounded-[2rem] p-4 shadow-sm border border-outline-variant/40">

    <div class="flex items-start justify-between gap-2 pb-4 mb-4 border-b border-outline-variant/50">
      <div class="min-w-0">
        <h2 class="font-extrabold text-on-surface truncate">{{ $tool->standard_name }}</h2>
        <p class="text-xs text-on-surface-variant mt-0.5">{{ $tool->category?->name ?? 'Umum' }}</p>
        @if ($tool->main_function)
          <p class="text-sm text-on-surface-variant mt-2 leading-relaxed">{{ $tool->main_function }}</p>
        @endif
      </div>
      <span class="material-symbols-outlined text-on-surface-variant shrink-0">chevron_right</span>
    </div>

    <div class="flex items-center gap-2 mb-4">
      <span class="inline-flex items-center gap-1.5 text-xs font-bold text-on-surface-variant bg-surface-container px-3 py-1.5 rounded-full">
        <span class="material-symbols-outlined text-[15px]">inventory_2</span>{{ $tool->assets->count() }} unit terdaftar
      </span>
      <span class="inline-flex items-center gap-1.5 text-xs font-bold text-on-surface-variant bg-surface-container px-3 py-1.5 rounded-full">
        <span class="material-symbols-outlined text-[15px]">star</span>{{ $tool->checklistItems->count() }} poin inspeksi
      </span>
    </div>

    <div class="grid grid-cols-3 gap-1.5 bg-surface-container p-1.5 rounded-2xl mb-5" id="tab-bar">
      <button type="button" data-tab="panduan" class="tab-btn flex items-center justify-center gap-1 text-xs font-bold py-2.5 rounded-xl transition-all bg-primary text-white shadow-sm">
        <span class="material-symbols-outlined text-[15px]">menu_book</span>Panduan
      </button>
      <button type="button" data-tab="checklist" class="tab-btn flex items-center justify-center gap-1 text-xs font-bold py-2.5 rounded-xl transition-all text-on-surface-variant">
        <span class="material-symbols-outlined text-[15px]">checklist</span>Checklist
      </button>
      <button type="button" data-tab="riwayat" class="tab-btn flex items-center justify-center gap-1 text-xs font-bold py-2.5 rounded-xl transition-all text-on-surface-variant">
        <span class="material-symbols-outlined text-[15px]">history</span>Riwayat
      </button>
    </div>

    {{-- PANEL: PANDUAN --}}
    <div data-panel="panduan" class="space-y-5">
      @if ($tool->main_function)
        <div>
          <h3 class="text-sm font-bold text-on-surface flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-4 bg-primary rounded-full inline-block"></span>Deskripsi Singkat
          </h3>
          <p class="text-sm text-on-surface-variant leading-relaxed">{{ $tool->main_function }}</p>
        </div>
      @endif

      @php $step = 0; @endphp

      @if ($tool->functions->isNotEmpty())
        @php $step++; @endphp
        <div>
          <div class="flex items-center gap-2 mb-2.5">
            <span class="w-7 h-7 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center shrink-0">{{ $step }}</span>
            <h3 class="font-bold text-on-surface text-sm">Fungsi Detail</h3>
          </div>
          <div class="bg-surface-container-low rounded-2xl p-3 space-y-3">
            <div class="h-32 w-full rounded-xl overflow-hidden bg-gradient-to-br from-primary-light to-surface-container-high flex items-center justify-center">
              @if ($tool->image_url)
                <img src="{{ $tool->imageDisplayUrl() }}" alt="{{ $tool->standard_name }}" class="w-full h-full object-cover">
              @else
                <span class="material-symbols-outlined text-primary/40 text-5xl">construction</span>
              @endif
            </div>
            <div class="space-y-2">
              @foreach ($tool->functions as $fn)
                <div class="flex items-start gap-2">
                  <span class="material-symbols-outlined text-primary text-[16px] mt-0.5">task_alt</span>
                  <p class="text-sm text-on-surface leading-snug">{{ $fn->description }}</p>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      @endif

      @if ($tool->inspectionMethods->isNotEmpty())
        @php $step++; @endphp
        <div>
          <div class="flex items-center gap-2 mb-2.5">
            <span class="w-7 h-7 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center shrink-0">{{ $step }}</span>
            <h3 class="font-bold text-on-surface text-sm">Metode Inspeksi</h3>
          </div>
          <div class="space-y-2">
            @foreach ($tool->inspectionMethods as $i => $method)
              <div class="flex items-center gap-3 bg-surface-container-low rounded-2xl p-3">
                <span class="w-9 h-9 rounded-xl bg-surface-container-lowest shadow-sm flex items-center justify-center shrink-0 text-primary">
                  <span class="material-symbols-outlined text-[18px]">{{ $i % 2 === 0 ? 'event_available' : 'sell' }}</span>
                </span>
                <span class="text-sm font-semibold text-on-surface flex-1">{{ $method->method_name }}</span>
                <span class="material-symbols-outlined text-on-surface-variant text-[18px]">chevron_right</span>
              </div>
            @endforeach
          </div>
        </div>
      @endif

      @if ($tool->safetyFeatures->isNotEmpty())
        @php $step++; @endphp
        <div>
          <div class="flex items-center gap-2 mb-2.5">
            <span class="w-7 h-7 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center shrink-0">{{ $step }}</span>
            <h3 class="font-bold text-on-surface text-sm">Fitur Keselamatan</h3>
          </div>
          <div class="space-y-2">
            @foreach ($tool->safetyFeatures as $i => $feature)
              <div class="flex items-center gap-3 bg-surface-container-low rounded-2xl p-3">
                <span class="w-9 h-9 rounded-xl bg-surface-container-lowest shadow-sm flex items-center justify-center shrink-0 text-primary">
                  <span class="material-symbols-outlined text-[18px]">{{ $i % 2 === 0 ? 'health_and_safety' : 'settings' }}</span>
                </span>
                <div class="min-w-0 flex-1">
                  <p class="font-semibold text-sm text-on-surface truncate">{{ $feature->feature_name }}</p>
                  @if ($feature->description)
                    <p class="text-xs text-on-surface-variant truncate">{{ $feature->description }}</p>
                  @endif
                </div>
                <span class="material-symbols-outlined text-on-surface-variant text-[18px] shrink-0">chevron_right</span>
              </div>
            @endforeach
          </div>
        </div>
      @endif

      @if ($tool->usageRules->isNotEmpty())
        @php $step++; @endphp
        <div>
          <div class="flex items-center gap-2 mb-2.5">
            <span class="w-7 h-7 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center shrink-0">{{ $step }}</span>
            <h3 class="font-bold text-on-surface text-sm">Aturan Penggunaan</h3>
          </div>
          <div class="space-y-2">
            @foreach ($tool->usageRules as $rule)
              <div class="flex items-center gap-3 bg-surface-container-low rounded-2xl p-3">
                <span class="w-9 h-9 rounded-xl bg-surface-container-lowest shadow-sm flex items-center justify-center shrink-0 {{ $rule->rule_type === 'do' ? 'text-primary' : 'text-tertiary' }}">
                  <span class="material-symbols-outlined text-[18px]">{{ $rule->rule_type === 'do' ? 'check_circle' : 'cancel' }}</span>
                </span>
                <span class="text-sm font-semibold text-on-surface flex-1 leading-snug">{{ $rule->description }}</span>
                <span class="material-symbols-outlined text-on-surface-variant text-[18px] shrink-0">chevron_right</span>
              </div>
            @endforeach
          </div>
        </div>
      @endif

      @if ($step === 0 && ! $tool->main_function)
        <div class="text-center py-12 text-on-surface-variant">
          <span class="material-symbols-outlined text-5xl opacity-30">menu_book</span>
          <p class="mt-2 text-sm">Panduan lengkap untuk alat ini belum diisi.</p>
        </div>
      @endif

      @if ($tool->standards->isNotEmpty() || $tool->attributes->isNotEmpty())
        <div class="pt-4 border-t border-outline-variant/50 space-y-4">
          <p class="text-[11px] font-bold text-on-surface-variant uppercase tracking-widest">Informasi Tambahan</p>

          @if ($tool->standards->isNotEmpty())
            <div>
              <h4 class="text-sm font-bold text-on-surface mb-2">Standar Acuan</h4>
              <div class="flex flex-wrap gap-2">
                @foreach ($tool->standards as $standard)
                  <span class="text-xs font-semibold px-3 py-1.5 rounded-full bg-surface-container-low text-on-surface-variant border border-outline-variant/60">{{ $standard->standard_name }}</span>
                @endforeach
              </div>
            </div>
          @endif

          @if ($tool->attributes->isNotEmpty())
            <div>
              <h4 class="text-sm font-bold text-on-surface mb-2">Atribut Teknis</h4>
              <div class="grid grid-cols-2 gap-2">
                @foreach ($tool->attributes as $attr)
                  <div class="p-3 rounded-2xl bg-surface-container-low">
                    <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wide truncate">{{ $attr->attribute_name }}</p>
                    <p class="text-sm font-bold text-on-surface truncate">{{ $attr->attribute_value ?? '-' }}</p>
                  </div>
                @endforeach
              </div>
            </div>
          @endif
        </div>
      @endif
    </div>

    {{-- PANEL: CHECKLIST --}}
    <div data-panel="checklist" class="space-y-2 hidden">
      @forelse ($tool->checklistItems as $item)
        <div class="p-3.5 rounded-2xl bg-surface-container-low flex gap-3">
          <span class="w-7 h-7 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center shrink-0">{{ $item->sequence }}</span>
          <div class="min-w-0">
            <p class="font-semibold text-sm text-on-surface">{{ $item->komponen_diperiksa }}</p>
            <p class="text-xs text-on-surface-variant mt-0.5">{{ $item->kriteria_pemeriksaan }}</p>
          </div>
        </div>
      @empty
        <div class="text-center py-12 text-on-surface-variant">
          <span class="material-symbols-outlined text-5xl opacity-30">checklist</span>
          <p class="mt-2 text-sm">Belum ada checklist pemeriksaan untuk alat ini.</p>
        </div>
      @endforelse
    </div>

    {{-- PANEL: RIWAYAT --}}
    <div data-panel="riwayat" class="hidden">
      @forelse ($inspectionRecords as $record)
        @php
          $resultStyle = match ($record->overall_result) {
            'Pass' => ['bg-primary-light text-primary', 'check_circle'],
            'Fail' => ['bg-tertiary-light text-tertiary', 'cancel'],
            'Conditional' => ['bg-amber-100 text-amber-700', 'warning'],
            default => ['bg-surface-container text-on-surface-variant', 'help'],
          };
        @endphp
        <div class="p-3.5 rounded-2xl bg-surface-container-low mb-2.5">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="text-xs text-on-surface-variant">{{ $record->asset?->inventory_id ?? '-' }}</p>
              <p class="text-sm font-semibold text-on-surface">{{ $record->inspection_date?->format('d M Y') }}</p>
            </div>
            <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full whitespace-nowrap {{ $resultStyle[0] }}">
              <span class="material-symbols-outlined text-[14px]">{{ $resultStyle[1] }}</span>{{ $record->overall_result ?? '-' }}
            </span>
          </div>
        </div>
      @empty
        @include('pnc-monitoring.inspection-app.partials._empty-history', ['emptyText' => 'Riwayat inspeksi untuk alat ini akan muncul di sini setelah inspeksi pertama disimpan.'])
      @endforelse
    </div>
  </section>
</div>

<div class="fixed bottom-24 left-0 w-full px-5 max-w-[480px] mx-auto z-40">
  <a href="{{ route('pnc-monitoring.inventory-inspection.tools.inspect', $tool) }}"
     class="flex items-center justify-center gap-2 w-full h-14 rounded-2xl bg-gradient-to-r from-primary to-primary-dark text-white font-bold text-base shadow-[0_10px_24px_rgba(14,138,79,0.35)] active:scale-95 transition-transform">
    <span class="material-symbols-outlined">assignment_turned_in</span>Mulai Inspeksi
  </a>
</div>
@endsection

@section('scripts')
<script>
(() => {
  const buttons = document.querySelectorAll('.tab-btn');
  const panels = document.querySelectorAll('[data-panel]');
  buttons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-tab');
      buttons.forEach((b) => {
        b.classList.remove('bg-primary', 'text-white', 'shadow-sm');
        b.classList.add('text-on-surface-variant');
      });
      btn.classList.remove('text-on-surface-variant');
      btn.classList.add('bg-primary', 'text-white', 'shadow-sm');
      panels.forEach((p) => p.classList.toggle('hidden', p.getAttribute('data-panel') !== target));
    });
  });
})();
</script>
@endsection
