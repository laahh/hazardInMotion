@extends('pnc-monitoring.inspection-app.layout')

@section('title', $tool->standard_name)
@section('header-title', \Illuminate\Support\Str::limit($tool->standard_name, 22))
@section('back-url', route('pnc-monitoring.inventory-inspection.home'))

@section('content')
<div class="px-5 space-y-6 pb-8">

  <section class="rounded-[2rem] overflow-hidden bg-surface-container-lowest shadow-sm border border-outline-variant/10">
    <div class="relative w-full h-48 bg-gradient-to-br from-emerald-100 to-surface-container-high flex items-center justify-center">
      @if ($tool->image_url)
        <img src="{{ $tool->imageDisplayUrl() }}" alt="{{ $tool->standard_name }}" class="w-full h-full object-cover">
      @else
        <span class="material-symbols-outlined text-emerald-700/30 text-8xl">construction</span>
      @endif
      <span class="absolute top-4 left-4 bg-surface-container-lowest/90 backdrop-blur-md px-3 py-1 rounded-full text-xs font-bold text-on-surface">
        {{ $tool->category?->name }}
      </span>
    </div>
    <div class="p-5">
      <h1 class="text-xl font-extrabold text-on-surface leading-tight">{{ $tool->standard_name }}</h1>
      @if ($tool->sub_category)
        <p class="text-sm text-on-surface-variant mt-0.5">{{ $tool->sub_category }}</p>
      @endif
      <div class="flex flex-wrap items-center gap-2 mt-3">
        @if ($tool->criticality)
          <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-secondary-container text-on-secondary-container">Criticality: {{ $tool->criticality }}</span>
        @endif
        @if ($tool->risk_class)
          <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-800">Risk: {{ $tool->risk_class }}</span>
        @endif
        @if ($tool->is_regulated)
          <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-tertiary-container/10 text-tertiary">
            <span class="material-symbols-outlined text-[13px]">verified</span>Wajib Regulasi
          </span>
        @endif
        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-surface-container-high text-on-surface-variant">
          <span class="material-symbols-outlined text-[13px]">inventory_2</span>{{ $tool->assets->count() }} unit terdaftar
        </span>
      </div>
    </div>
  </section>

  <section>
    <div class="grid grid-cols-3 gap-2 bg-surface-container-low p-1.5 rounded-2xl" id="tab-bar">
      <button type="button" data-tab="panduan" class="tab-btn text-sm font-bold py-2.5 rounded-xl transition-all bg-surface-container-lowest text-on-surface shadow-sm">Panduan</button>
      <button type="button" data-tab="checklist" class="tab-btn text-sm font-bold py-2.5 rounded-xl transition-all text-on-surface-variant">Checklist</button>
      <button type="button" data-tab="riwayat" class="tab-btn text-sm font-bold py-2.5 rounded-xl transition-all text-on-surface-variant">Riwayat</button>
    </div>
  </section>

  {{-- PANEL: PANDUAN --}}
  <section data-panel="panduan" class="space-y-5">
    @if ($tool->main_function)
      <div class="p-4 rounded-3xl bg-surface-container-lowest border border-outline-variant/10">
        <h3 class="text-sm font-bold text-on-surface-variant uppercase tracking-wide mb-1.5">Fungsi Utama</h3>
        <p class="text-sm text-on-surface leading-relaxed">{{ $tool->main_function }}</p>
      </div>
    @endif

    @if ($tool->functions->isNotEmpty())
      <div>
        <h3 class="text-sm font-bold text-on-surface-variant uppercase tracking-wide mb-2">Fungsi Detail</h3>
        <ol class="space-y-2">
          @foreach ($tool->functions as $fn)
            <li class="flex gap-3 p-3 rounded-2xl bg-surface-container-lowest border border-outline-variant/10">
              <span class="w-6 h-6 rounded-full bg-primary-container text-on-primary-container text-xs font-bold flex items-center justify-center shrink-0">{{ $loop->iteration }}</span>
              <span class="text-sm text-on-surface">{{ $fn->description }}</span>
            </li>
          @endforeach
        </ol>
      </div>
    @endif

    @if ($tool->inspectionMethods->isNotEmpty())
      <div>
        <h3 class="text-sm font-bold text-on-surface-variant uppercase tracking-wide mb-2">Metode Inspeksi</h3>
        <div class="flex flex-wrap gap-2">
          @foreach ($tool->inspectionMethods as $method)
            <span class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-full bg-surface-container-high text-on-surface-variant">
              <span class="material-symbols-outlined text-[14px]">search</span>{{ $method->method_name }}
            </span>
          @endforeach
        </div>
      </div>
    @endif

    @if ($tool->safetyFeatures->isNotEmpty())
      <div>
        <h3 class="text-sm font-bold text-on-surface-variant uppercase tracking-wide mb-2">Fitur Keselamatan</h3>
        <div class="space-y-2">
          @foreach ($tool->safetyFeatures as $feature)
            <div class="flex items-center gap-3 p-3 rounded-2xl bg-surface-container-lowest border border-outline-variant/10">
              <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-emerald-700 text-[18px]">health_and_safety</span>
              </div>
              <div class="min-w-0">
                <p class="font-semibold text-sm text-on-surface">{{ $feature->feature_name }}</p>
                @if ($feature->description)
                  <p class="text-xs text-on-surface-variant">{{ $feature->description }}</p>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endif

    @if ($tool->standards->isNotEmpty())
      <div>
        <h3 class="text-sm font-bold text-on-surface-variant uppercase tracking-wide mb-2">Standar Acuan</h3>
        <div class="flex flex-wrap gap-2">
          @foreach ($tool->standards as $standard)
            <span class="text-xs font-semibold px-3 py-1.5 rounded-full bg-secondary-container text-on-secondary-container">{{ $standard->standard_name }}</span>
          @endforeach
        </div>
      </div>
    @endif

    @if ($tool->usageRules->isNotEmpty())
      <div>
        <h3 class="text-sm font-bold text-on-surface-variant uppercase tracking-wide mb-2">Aturan Penggunaan</h3>
        <div class="space-y-2">
          @foreach ($tool->usageRules as $rule)
            <div class="flex items-start gap-2.5 p-3 rounded-2xl {{ $rule->rule_type === 'do' ? 'bg-emerald-50' : 'bg-red-50' }}">
              <span class="material-symbols-outlined text-[18px] {{ $rule->rule_type === 'do' ? 'text-emerald-700' : 'text-red-700' }}">
                {{ $rule->rule_type === 'do' ? 'check_circle' : 'cancel' }}
              </span>
              <span class="text-sm text-on-surface leading-snug">{{ $rule->description }}</span>
            </div>
          @endforeach
        </div>
      </div>
    @endif

    @if ($tool->attributes->isNotEmpty())
      <div>
        <h3 class="text-sm font-bold text-on-surface-variant uppercase tracking-wide mb-2">Atribut Teknis</h3>
        <div class="grid grid-cols-2 gap-2">
          @foreach ($tool->attributes as $attr)
            <div class="p-3 rounded-2xl bg-surface-container-lowest border border-outline-variant/10">
              <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wide truncate">{{ $attr->attribute_name }}</p>
              <p class="text-sm font-semibold text-on-surface truncate">{{ $attr->attribute_value ?? '-' }}</p>
            </div>
          @endforeach
        </div>
      </div>
    @endif

    @if ($tool->functions->isEmpty() && $tool->safetyFeatures->isEmpty() && $tool->standards->isEmpty() && $tool->usageRules->isEmpty() && $tool->attributes->isEmpty() && ! $tool->main_function)
      <div class="text-center py-16 text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl opacity-30">menu_book</span>
        <p class="mt-2 text-sm">Panduan lengkap untuk alat ini belum diisi.</p>
      </div>
    @endif
  </section>

  {{-- PANEL: CHECKLIST --}}
  <section data-panel="checklist" class="space-y-2 hidden">
    @forelse ($tool->checklistItems as $item)
      <div class="p-4 rounded-2xl bg-surface-container-lowest border border-outline-variant/10 flex gap-3">
        <span class="w-7 h-7 rounded-full bg-surface-container-high text-on-surface-variant text-xs font-bold flex items-center justify-center shrink-0">{{ $item->sequence }}</span>
        <div class="min-w-0">
          <p class="font-semibold text-sm text-on-surface">{{ $item->komponen_diperiksa }}</p>
          <p class="text-xs text-on-surface-variant mt-0.5">{{ $item->kriteria_pemeriksaan }}</p>
        </div>
      </div>
    @empty
      <div class="text-center py-16 text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl opacity-30">checklist</span>
        <p class="mt-2 text-sm">Belum ada checklist pemeriksaan untuk alat ini.</p>
      </div>
    @endforelse
  </section>

  {{-- PANEL: RIWAYAT --}}
  <section data-panel="riwayat" class="space-y-3 hidden">
    @forelse ($inspectionRecords as $record)
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
            <p class="text-xs text-on-surface-variant">{{ $record->asset?->inventory_id ?? '-' }}</p>
            <p class="text-sm font-semibold text-on-surface">{{ $record->inspection_date?->format('d M Y') }}</p>
          </div>
          <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full whitespace-nowrap {{ $resultStyle[0] }}">
            <span class="material-symbols-outlined text-[14px]">{{ $resultStyle[1] }}</span>{{ $record->overall_result ?? '-' }}
          </span>
        </div>
      </div>
    @empty
      <div class="text-center py-16 text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl opacity-30">fact_check</span>
        <p class="mt-2 text-sm">Belum ada riwayat inspeksi untuk alat ini.</p>
      </div>
    @endforelse
  </section>
</div>

<div class="fixed bottom-24 left-0 w-full px-5 max-w-[480px] mx-auto z-40">
  <a href="{{ route('pnc-monitoring.inventory-inspection.tools.inspect', $tool) }}"
     class="flex items-center justify-center gap-2 w-full h-14 rounded-full bg-gradient-to-br from-primary-container to-primary text-white font-bold text-base shadow-[0_8px_24px_rgba(0,106,63,0.3)] active:scale-95 transition-transform">
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
      buttons.forEach((b) => b.classList.remove('bg-surface-container-lowest', 'text-on-surface', 'shadow-sm'));
      buttons.forEach((b) => b.classList.add('text-on-surface-variant'));
      btn.classList.remove('text-on-surface-variant');
      btn.classList.add('bg-surface-container-lowest', 'text-on-surface', 'shadow-sm');
      panels.forEach((p) => p.classList.toggle('hidden', p.getAttribute('data-panel') !== target));
    });
  });
})();
</script>
@endsection
