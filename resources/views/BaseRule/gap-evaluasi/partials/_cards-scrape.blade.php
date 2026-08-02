@php
   $cards = $scrape['cards'] ?? [];
@endphp
<div class="mb-6">
   <h2 class="font-headline font-bold text-lg text-on-background mb-3">Blok A — Evaluasi scrape harian</h2>
   <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="hsecm-card gap-eval-card rounded-2xl p-4 border border-red-100 bg-red-50/40">
         <p class="text-[10px] font-bold uppercase tracking-wider text-red-700">Tidak perbaikan & masih berulang</p>
         <p class="text-2xl font-extrabold text-red-700 mt-1">{{ number_format((int) ($cards['tidak_perbaikan_masih_berulang'] ?? 0)) }}</p>
         <p class="text-[11px] text-red-700/80 mt-1">Multi-hari: {{ number_format((int) ($cards['tidak_perbaikan_masih_berulang_multi_hari'] ?? 0)) }}</p>
      </div>
      <div class="hsecm-card gap-eval-card rounded-2xl p-4 border border-emerald-100 bg-emerald-50/40">
         <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Perbaikan (hilang)</p>
         <p class="text-2xl font-extrabold text-emerald-800 mt-1">{{ number_format((int) ($cards['hilang'] ?? 0)) }}</p>
         <p class="text-[11px] text-emerald-800/80 mt-1">Tanpa perulangan: {{ number_format((int) ($cards['perbaikan_tanpa_perulangan'] ?? 0)) }}</p>
      </div>
      <div class="hsecm-card gap-eval-card rounded-2xl p-4">
         <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Perbaikan + riwayat perulangan</p>
         <p class="text-2xl font-extrabold text-on-background mt-1">{{ number_format((int) ($cards['perbaikan_dengan_perulangan'] ?? 0)) }}</p>
         <p class="text-[11px] text-on-surface-variant mt-1">Streak ≥ 2 hari lalu clear</p>
      </div>
      <div class="hsecm-card gap-eval-card rounded-2xl p-4">
         <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Baru / Kembali</p>
         <p class="text-2xl font-extrabold text-on-background mt-1">
            {{ number_format((int) ($cards['baru'] ?? 0)) }}
            <span class="text-base font-semibold text-on-surface-variant">/</span>
            {{ number_format((int) ($cards['kembali'] ?? 0)) }}
         </p>
         <p class="text-[11px] text-on-surface-variant mt-1">Gap baru · re-open</p>
      </div>
   </div>
</div>
