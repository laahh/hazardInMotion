{{-- 1 parameter = 1 card metrik + 1 tabel detail --}}
@php
   $key = (string) ($program['key'] ?? '');
   $label = (string) ($program['label'] ?? $key);
   $totalGap = (int) ($program['total_gap'] ?? 0);
   $totalPerulangan = (int) ($program['total_perulangan'] ?? 0);
   $perbaikanTanpa = (int) ($program['perbaikan_tanpa_perulangan'] ?? 0);
   $rows = $program['rows'] ?? [];
   $truncated = (int) ($program['truncated'] ?? 0);
   $rowTotal = (int) ($program['row_total'] ?? count($rows));

   $statusLabel = [
      'tetap' => 'Masih berulang',
      'hilang' => 'Sudah perbaikan',
      'baru' => 'Gap baru',
      'kembali' => 'Kembali muncul',
   ];
   $statusBadge = [
      'tetap' => 'hsecm-badge--danger',
      'hilang' => 'hsecm-badge--success',
      'baru' => 'hsecm-badge--warning',
      'kembali' => 'hsecm-badge--warning',
   ];
@endphp
<div
   id="gap-program-{{ $key }}"
   class="gap-program-strip hsecm-card rounded-2xl overflow-hidden border border-slate-100"
   data-program-key="{{ $key }}"
>
   <div class="px-5 py-3 border-b border-slate-100 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div class="min-w-0">
         <h3 class="font-headline font-bold text-base text-on-background truncate">{{ $label }}</h3>
         <p class="text-xs text-on-surface-variant mt-0.5">{{ number_format($rowTotal) }} item evaluasi</p>
      </div>
      <span class="inline-flex items-center rounded-md bg-slate-900 text-white text-xs font-bold px-2.5 py-1 tracking-wide shrink-0">
         {{ number_format($totalGap) }} / {{ number_format($totalPerulangan) }} / {{ number_format($perbaikanTanpa) }}
      </span>
   </div>

   {{-- Card metrik --}}
   <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-white/10">
      <div class="px-5 py-4 bg-slate-900 text-white">
         <p class="text-[10px] font-bold uppercase tracking-wider text-white/65 leading-snug">Total Gap</p>
         <p class="text-[11px] text-white/55 mt-1 leading-snug">Distinct orang/lokasi/aktivitas (tanpa lihat berulang)</p>
         <p class="text-2xl font-extrabold mt-2">{{ number_format($totalGap) }}</p>
      </div>
      <div class="px-5 py-4 bg-slate-900 text-white">
         <p class="text-[10px] font-bold uppercase tracking-wider text-white/65 leading-snug">Total Perulangan</p>
         <p class="text-[11px] text-white/55 mt-1 leading-snug">Distinct tetap (D−1 &amp; D) atau streak ≥ 2</p>
         <p class="text-2xl font-extrabold mt-2">{{ number_format($totalPerulangan) }}</p>
      </div>
      <div class="px-5 py-4 bg-slate-900 text-white">
         <p class="text-[10px] font-bold uppercase tracking-wider text-white/65 leading-snug">Perbaikan tanpa Perulangan</p>
         <p class="text-[11px] text-white/55 mt-1 leading-snug">Pernah gap lalu clear · streak &lt; 2 · distinct</p>
         <p class="text-2xl font-extrabold mt-2">{{ number_format($perbaikanTanpa) }}</p>
      </div>
   </div>

   {{-- Tabel detail parameter --}}
   <div class="border-t border-slate-100">
      <div class="px-5 py-3 flex items-center justify-between gap-2 border-b border-slate-50">
         <p class="text-xs font-semibold text-on-background">Detail {{ $label }}</p>
         <span class="hsecm-badge">{{ number_format(count($rows)) }} ditampilkan</span>
      </div>
      <div class="gap-eval-table overflow-x-auto">
         <table class="hsecm-table w-full text-sm">
            <thead>
               <tr>
                  <th class="px-4 py-2 text-left">Status</th>
                  <th class="px-4 py-2 text-left">Item</th>
                  <th class="px-4 py-2 text-left">Site</th>
                  <th class="px-4 py-2 text-left">Perusahaan</th>
                  <th class="px-4 py-2 text-center">Streak hari</th>
               </tr>
            </thead>
            <tbody>
               @forelse($rows as $row)
               @php
                  $st = (string) ($row['status'] ?? '');
               @endphp
               <tr class="border-t border-slate-50">
                  <td class="px-4 py-2 whitespace-nowrap">
                     <span class="hsecm-badge {{ $statusBadge[$st] ?? '' }}">
                        {{ $statusLabel[$st] ?? ($st !== '' ? $st : '—') }}
                     </span>
                  </td>
                  <td class="px-4 py-2">
                     <div class="text-on-background">{{ $row['value_label'] ?: '—' }}</div>
                     <div class="text-[11px] text-on-surface-variant font-mono">{{ \Illuminate\Support\Str::limit($row['business_key'] ?? '', 48) }}</div>
                  </td>
                  <td class="px-4 py-2 whitespace-nowrap">{{ ($row['site'] ?? '') !== '' ? $row['site'] : '—' }}</td>
                  <td class="px-4 py-2">{{ ($row['perusahaan'] ?? '') !== '' ? $row['perusahaan'] : '—' }}</td>
                  <td class="px-4 py-2 text-center font-semibold {{ (int) ($row['day_streak'] ?? 0) >= 2 ? 'text-red-600' : '' }}">
                     {{ (int) ($row['day_streak'] ?? 0) }}×
                  </td>
               </tr>
               @empty
               <tr>
                  <td colspan="5" class="px-4 py-8 text-center text-on-surface-variant">
                     Tidak ada item untuk parameter ini pada periode evaluasi.
                  </td>
               </tr>
               @endforelse
            </tbody>
         </table>
      </div>
      @if($truncated > 0)
      <div class="px-5 py-3 border-t border-slate-100 text-xs text-on-surface-variant">
         +{{ number_format($truncated) }} baris lainnya tidak ditampilkan (batas {{ 200 }}).
      </div>
      @endif
   </div>
</div>
