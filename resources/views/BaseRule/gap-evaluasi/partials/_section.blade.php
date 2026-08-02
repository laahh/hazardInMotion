{{-- 1 parameter = summary metrik + tabel Site/Perusahaan vertikal --}}
@php
   $key = (string) ($program['key'] ?? '');
   $label = (string) ($program['label'] ?? $key);
   $totalGap = (int) ($program['total_gap'] ?? 0);
   $totalPerulangan = (int) ($program['total_perulangan'] ?? 0);
   $perbaikanTanpa = (int) ($program['perbaikan_tanpa_perulangan'] ?? 0);
   $matrixRows = $program['matrix_rows'] ?? [];
   $rows = $program['rows'] ?? [];
   $truncated = (int) ($program['truncated'] ?? 0);

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
      <div>
         <h3 class="font-headline font-bold text-base text-on-background">{{ $label }}</h3>
         <p class="text-xs text-on-surface-variant mt-0.5">Ringkasan per Site → Perusahaan (baris ke bawah)</p>
      </div>
      <span class="inline-flex items-center rounded-md bg-teal-600 text-white text-xs font-bold px-2.5 py-1 tracking-wide shrink-0">
         {{ number_format($totalGap) }} / {{ number_format($totalPerulangan) }} / {{ number_format($perbaikanTanpa) }}
      </span>
   </div>

   {{-- 4 kartu summary khusus parameter ini --}}
   <div class="px-4 pt-4">
      @include('BaseRule.gap-evaluasi.partials._overview-cards', [
         'wrapClass' => 'mb-4',
         'overview' => [
            'total_gap' => (int) ($program['total_gap'] ?? 0),
            'total_perulangan' => (int) ($program['total_perulangan'] ?? 0),
            'perbaikan_total' => (int) ($program['perbaikan_total'] ?? 0),
            'perbaikan_tanpa_perulangan' => (int) ($program['perbaikan_tanpa_perulangan'] ?? 0),
            'tindaklanjut_berhasil' => (int) ($program['tindaklanjut_berhasil'] ?? 0),
            'tindaklanjut_tanpa_perulangan' => (int) ($program['tindaklanjut_tanpa_perulangan'] ?? 0),
         ],
      ])
   </div>

   {{-- Matriks vertikal: Site & Perusahaan ke bawah --}}
   <div class="border-t border-slate-100">
      <div class="px-5 py-3 border-b border-slate-50">
         <p class="text-xs font-semibold text-on-background">Matriks Site / Perusahaan</p>
      </div>
      <div class="overflow-x-auto">
         <table class="hsecm-table w-full text-sm">
            <thead>
               <tr>
                  <th class="px-4 py-2 text-left">Site</th>
                  <th class="px-4 py-2 text-left">Perusahaan</th>
                  <th class="px-4 py-2 text-right">Total Gap</th>
                  <th class="px-4 py-2 text-right">Total Perulangan</th>
                  <th class="px-4 py-2 text-right">Perbaikan tanpa Perulangan</th>
               </tr>
            </thead>
            <tbody>
               @forelse($matrixRows as $m)
               @php
                  $g = (int) ($m['total_gap'] ?? 0);
                  $p = (int) ($m['total_perulangan'] ?? 0);
                  $b = (int) ($m['perbaikan_tanpa_perulangan'] ?? 0);
               @endphp
               <tr class="border-t border-slate-50">
                  <td class="px-4 py-2 font-semibold text-on-background whitespace-nowrap">{{ $m['site'] }}</td>
                  <td class="px-4 py-2 whitespace-nowrap" title="{{ $m['company_name'] ?? '' }}">
                     <span class="font-semibold">{{ $m['company_code'] }}</span>
                     @if(($m['company_name'] ?? '') !== '' && ($m['company_name'] ?? '') !== ($m['company_code'] ?? ''))
                        <span class="block text-[11px] text-on-surface-variant">{{ $m['company_name'] }}</span>
                     @endif
                  </td>
                  <td class="px-4 py-2 text-right font-semibold {{ $g > 0 ? 'text-red-600' : 'text-on-surface-variant' }}">{{ number_format($g) }}</td>
                  <td class="px-4 py-2 text-right font-semibold {{ $p > 0 ? 'text-red-600' : 'text-on-surface-variant' }}">{{ number_format($p) }}</td>
                  <td class="px-4 py-2 text-right font-semibold {{ $b > 0 ? 'text-emerald-700' : 'text-on-surface-variant' }}">{{ number_format($b) }}</td>
               </tr>
               @empty
               <tr>
                  <td colspan="5" class="px-4 py-8 text-center text-on-surface-variant">Belum ada data scope.</td>
               </tr>
               @endforelse
            </tbody>
         </table>
      </div>
   </div>

   {{-- Detail item (opsional, collapse) --}}
   <details class="border-t border-slate-100 group/detail">
      <summary class="px-5 py-3 cursor-pointer list-none flex items-center gap-2 text-xs font-semibold text-on-background">
         <span class="material-symbols-outlined text-primary text-base transition group-open/detail:rotate-90">chevron_right</span>
         Detail item {{ $label }}
         <span class="hsecm-badge ml-auto">{{ number_format(count($rows)) }} ditampilkan</span>
      </summary>
      <div class="gap-eval-table overflow-x-auto border-t border-slate-50">
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
               @php $st = (string) ($row['status'] ?? ''); @endphp
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
                  <td colspan="5" class="px-4 py-8 text-center text-on-surface-variant">Tidak ada item.</td>
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
   </details>
</div>
