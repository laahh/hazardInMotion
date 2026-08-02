@php
   $cards = $tasklist['cards'] ?? [];
   $available = (bool) ($tasklist['available'] ?? false);
@endphp
<div class="mb-2">
   <h2 class="font-headline font-bold text-lg text-on-background mb-3">Blok B — Efektivitas pasca tasklist</h2>
   @if(! $available)
      <div class="hsecm-card rounded-2xl p-4 text-sm text-on-surface-variant">
         {{ $tasklist['message'] ?? 'Data tasklist tidak tersedia.' }}
      </div>
   @else
   <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="hsecm-card gap-eval-card rounded-2xl p-4 border border-emerald-100 bg-emerald-50/40">
         <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Ditindaklanjuti + perbaikan</p>
         <p class="text-2xl font-extrabold text-emerald-800 mt-1">{{ number_format((int) ($cards['tindaklanjut_berhasil'] ?? 0)) }}</p>
         <p class="text-[11px] text-emerald-800/80 mt-1">Submit/ACC, gap hilang di scrape</p>
      </div>
      <div class="hsecm-card gap-eval-card rounded-2xl p-4 border border-red-100 bg-red-50/40">
         <p class="text-[10px] font-bold uppercase tracking-wider text-red-700">Ditindaklanjuti + masih gap</p>
         <p class="text-2xl font-extrabold text-red-700 mt-1">{{ number_format((int) ($cards['tindaklanjut_belum_efektif'] ?? 0)) }}</p>
         <p class="text-[11px] text-red-700/80 mt-1">Sudah isi tasklist, scrape belum clear</p>
      </div>
      <div class="hsecm-card gap-eval-card rounded-2xl p-4">
         <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Belum ditindaklanjuti + gap</p>
         <p class="text-2xl font-extrabold text-on-background mt-1">{{ number_format((int) ($cards['belum_tindaklanjut_masih_gap'] ?? 0)) }}</p>
         <p class="text-[11px] text-on-surface-variant mt-1">Open/rejected, masih di scrape</p>
      </div>
      <div class="hsecm-card gap-eval-card rounded-2xl p-4">
         <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Hilang tanpa tasklist</p>
         <p class="text-2xl font-extrabold text-on-background mt-1">{{ number_format((int) ($cards['hilang_tanpa_tindaklanjut'] ?? 0)) }}</p>
         <p class="text-[11px] text-on-surface-variant mt-1">Perbaikan scrape tanpa submit</p>
      </div>
   </div>
   @endif
</div>
