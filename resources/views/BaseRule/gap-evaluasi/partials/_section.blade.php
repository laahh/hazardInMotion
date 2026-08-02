{{-- 1 parameter = summary tabel metrik + tabel detail item --}}
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
   <div class="px-5 py-3 border-b border-slate-100 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
      <div class="min-w-0">
         <h3 class="font-headline font-bold text-base text-on-background truncate">{{ $label }}</h3>
         <p class="text-xs text-on-surface-variant mt-0.5">{{ number_format($rowTotal) }} item evaluasi</p>
      </div>
   </div>

   {{-- Summary metrik = TABEL 3 kolom (sesuai mockup) --}}
   <div class="overflow-x-auto">
      <table class="w-full text-sm border-collapse">
         <thead>
            <tr class="bg-slate-900 text-white">
               <th class="px-4 py-3 text-left align-top font-semibold border-r border-white/10 w-1/3">
                  <div class="text-[11px] font-bold uppercase tracking-wider">Total Gap</div>
                  <div class="text-[10px] font-normal text-white/65 mt-1 normal-case tracking-normal leading-snug">
                     Distinct orang/lokasi/aktivitas (tanpa lihat berulang)
                  </div>
               </th>
               <th class="px-4 py-3 text-left align-top font-semibold border-r border-white/10 w-1/3">
                  <div class="text-[11px] font-bold uppercase tracking-wider">Total Perulangan</div>
                  <div class="text-[10px] font-normal text-white/65 mt-1 normal-case tracking-normal leading-snug">
                     Distinct tetap (D−1 &amp; D) atau streak ≥ 2
                  </div>
               </th>
               <th class="px-4 py-3 text-left align-top font-semibold w-1/3">
                  <div class="text-[11px] font-bold uppercase tracking-wider">Perbaikan tanpa Perulangan</div>
                  <div class="text-[10px] font-normal text-white/65 mt-1 normal-case tracking-normal leading-snug">
                     Pernah gap lalu clear · streak &lt; 2 · distinct
                  </div>
               </th>
            </tr>
         </thead>
         <tbody>
            <tr class="bg-slate-100">
               <td class="px-4 py-4 text-2xl font-extrabold text-on-background border-r border-slate-200">
                  {{ number_format($totalGap) }}
               </td>
               <td class="px-4 py-4 text-2xl font-extrabold text-on-background border-r border-slate-200">
                  {{ number_format($totalPerulangan) }}
               </td>
               <td class="px-4 py-4 text-2xl font-extrabold text-on-background">
                  {{ number_format($perbaikanTanpa) }}
               </td>
            </tr>
         </tbody>
         <tfoot>
            <tr class="bg-slate-900 text-white">
               <td colspan="3" class="px-4 py-2 text-center text-sm font-bold tracking-wide">
                  {{ number_format($totalGap) }} / {{ number_format($totalPerulangan) }} / {{ number_format($perbaikanTanpa) }}
               </td>
            </tr>
         </tfoot>
      </table>
   </div>

   {{-- Tabel detail item parameter --}}
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
