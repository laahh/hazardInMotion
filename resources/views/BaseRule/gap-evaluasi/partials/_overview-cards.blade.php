@php
   $ov = $overview ?? [];
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-8">
   <div class="hsecm-card gap-eval-card rounded-2xl p-4 border border-teal-100 bg-teal-50/50">
      <p class="text-[10px] font-bold uppercase tracking-wider text-teal-800">Total Gap</p>
      <p class="text-3xl font-extrabold text-teal-900 mt-1">{{ number_format((int) ($ov['total_gap'] ?? 0)) }}</p>
      <p class="text-[11px] text-teal-700/80 mt-1">Distinct orang/lokasi/aktivitas (tanpa lihat berulang)</p>
   </div>

   <div class="hsecm-card gap-eval-card rounded-2xl p-4 border border-amber-100 bg-amber-50/50">
      <p class="text-[10px] font-bold uppercase tracking-wider text-amber-800">Total Perulangan</p>
      <p class="text-3xl font-extrabold text-amber-900 mt-1">{{ number_format((int) ($ov['total_perulangan'] ?? 0)) }}</p>
      <p class="text-[11px] text-amber-800/80 mt-1">Distinct tetap (D−1 &amp; D) / streak ≥ 2</p>
   </div>

   <div class="hsecm-card gap-eval-card rounded-2xl p-4 border border-emerald-100 bg-emerald-50/50">
      <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Total Sudah ada Perbaikan</p>
      <p class="text-3xl font-extrabold text-emerald-900 mt-1">{{ number_format((int) ($ov['perbaikan_total'] ?? 0)) }}</p>
      <div class="mt-3 pt-3 border-t border-emerald-200/80">
         <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Tanpa Perulangan</p>
         <p class="text-xl font-extrabold text-emerald-900 mt-0.5">{{ number_format((int) ($ov['perbaikan_tanpa_perulangan'] ?? 0)) }}</p>
         <p class="text-[11px] text-emerald-700/80 mt-0.5">Streak &lt; 2 · distinct</p>
      </div>
   </div>

   <div class="hsecm-card gap-eval-card rounded-2xl p-4 border border-sky-100 bg-sky-50/50">
      <p class="text-[10px] font-bold uppercase tracking-wider text-sky-800">Sudah Ditindaklanjuti (Tasklist)</p>
      <p class="text-3xl font-extrabold text-sky-900 mt-1">{{ number_format((int) ($ov['tindaklanjut_berhasil'] ?? 0)) }}</p>
      <div class="mt-3 pt-3 border-t border-sky-200/80">
         <p class="text-[10px] font-bold uppercase tracking-wider text-sky-700">Tanpa Perulangan</p>
         <p class="text-xl font-extrabold text-sky-900 mt-0.5">{{ number_format((int) ($ov['tindaklanjut_tanpa_perulangan'] ?? 0)) }}</p>
         <p class="text-[11px] text-sky-700/80 mt-0.5">Submit/ACC + perbaikan · streak &lt; 2</p>
      </div>
   </div>
</div>
