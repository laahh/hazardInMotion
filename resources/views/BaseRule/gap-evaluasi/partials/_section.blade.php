{{-- Strip metrik per parameter: Total Gap / Total Perulangan / Perbaikan tanpa Perulangan --}}
@php
   $key = (string) ($program['key'] ?? '');
   $label = (string) ($program['label'] ?? $key);
   $totalGap = (int) ($program['total_gap'] ?? 0);
   $totalPerulangan = (int) ($program['total_perulangan'] ?? 0);
   $perbaikanTanpa = (int) ($program['perbaikan_tanpa_perulangan'] ?? 0);
@endphp
<div
   id="gap-program-{{ $key }}"
   class="gap-program-strip hsecm-card rounded-2xl overflow-hidden border border-slate-100"
   data-program-key="{{ $key }}"
>
   <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between gap-3">
      <h3 class="font-headline font-bold text-base text-on-background">{{ $label }}</h3>
      <span class="inline-flex items-center rounded-md bg-slate-900 text-white text-xs font-bold px-2.5 py-1 tracking-wide">
         {{ number_format($totalGap) }} / {{ number_format($totalPerulangan) }} / {{ number_format($perbaikanTanpa) }}
      </span>
   </div>

   <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-slate-100">
      <div class="px-5 py-4 bg-slate-900 text-white md:rounded-none">
         <p class="text-[10px] font-bold uppercase tracking-wider text-white/65 leading-snug">
            Total Gap
         </p>
         <p class="text-[11px] text-white/55 mt-1 leading-snug">
            Distinct orang/lokasi/aktivitas (tanpa lihat berulang)
         </p>
         <p class="text-2xl font-extrabold mt-2">{{ number_format($totalGap) }}</p>
      </div>
      <div class="px-5 py-4 bg-slate-900 text-white">
         <p class="text-[10px] font-bold uppercase tracking-wider text-white/65 leading-snug">
            Total Perulangan
         </p>
         <p class="text-[11px] text-white/55 mt-1 leading-snug">
            Distinct tetap (D−1 &amp; D) atau streak ≥ 2
         </p>
         <p class="text-2xl font-extrabold mt-2">{{ number_format($totalPerulangan) }}</p>
      </div>
      <div class="px-5 py-4 bg-slate-900 text-white">
         <p class="text-[10px] font-bold uppercase tracking-wider text-white/65 leading-snug">
            Perbaikan tanpa Perulangan
         </p>
         <p class="text-[11px] text-white/55 mt-1 leading-snug">
            Pernah gap lalu clear · streak &lt; 2 · distinct
         </p>
         <p class="text-2xl font-extrabold mt-2">{{ number_format($perbaikanTanpa) }}</p>
      </div>
   </div>
</div>
