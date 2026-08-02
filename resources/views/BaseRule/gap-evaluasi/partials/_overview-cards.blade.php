@php
   $ov = $overview ?? [];
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-8">
   <div class="hsecm-card gap-eval-card gap-eval-kpi rounded-2xl p-4">
      <p class="text-[10px] font-bold uppercase tracking-wider text-white/70">Total Gap</p>
      <p class="text-3xl font-extrabold mt-1">{{ number_format((int) ($ov['total_gap'] ?? 0)) }}</p>
      <p class="text-[11px] text-white/75 mt-1">Distinct orang/lokasi/aktivitas (tanpa lihat berulang)</p>
   </div>

   <div class="hsecm-card gap-eval-card gap-eval-kpi rounded-2xl p-4">
      <p class="text-[10px] font-bold uppercase tracking-wider text-white/70">Total Perulangan</p>
      <p class="text-3xl font-extrabold mt-1">{{ number_format((int) ($ov['total_perulangan'] ?? 0)) }}</p>
         <p class="text-[11px] text-white/75 mt-1">Distinct tetap (D−1 & D) / streak ≥ 2</p>
   </div>

   <div class="hsecm-card gap-eval-card gap-eval-kpi rounded-2xl p-4">
      <p class="text-[10px] font-bold uppercase tracking-wider text-white/70">Total Sudah ada Perbaikan</p>
      <p class="text-3xl font-extrabold mt-1">{{ number_format((int) ($ov['perbaikan_total'] ?? 0)) }}</p>
      <div class="gap-eval-kpi-sub">
         <p class="text-[10px] font-bold uppercase tracking-wider text-white/70">Tanpa Perulangan</p>
         <p class="text-xl font-extrabold mt-0.5">{{ number_format((int) ($ov['perbaikan_tanpa_perulangan'] ?? 0)) }}</p>
         <p class="text-[11px] text-white/70 mt-0.5">Streak &lt; 2 · distinct</p>
      </div>
   </div>

   <div class="hsecm-card gap-eval-card gap-eval-kpi rounded-2xl p-4">
      <p class="text-[10px] font-bold uppercase tracking-wider text-white/70">Sudah Ditindaklanjuti (Tasklist)</p>
      <p class="text-3xl font-extrabold mt-1">{{ number_format((int) ($ov['tindaklanjut_berhasil'] ?? 0)) }}</p>
      <div class="gap-eval-kpi-sub">
         <p class="text-[10px] font-bold uppercase tracking-wider text-white/70">Tanpa Perulangan</p>
         <p class="text-xl font-extrabold mt-0.5">{{ number_format((int) ($ov['tindaklanjut_tanpa_perulangan'] ?? 0)) }}</p>
         <p class="text-[11px] text-white/70 mt-0.5">Submit/ACC + perbaikan · streak &lt; 2</p>
      </div>
   </div>
</div>
