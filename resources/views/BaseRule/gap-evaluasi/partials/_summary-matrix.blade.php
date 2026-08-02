{{-- Ringkasan matriks program × Site / Perusahaan — klik baris untuk fokus parameter --}}
@php
   $groups = $summary['groups'] ?? [];
   $columns = $summary['columns'] ?? [];
   $columnCount = count($columns);
@endphp
<div class="hsecm-card rounded-2xl overflow-hidden mb-8">
   <div class="px-5 py-4 border-b border-slate-100 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
         <h2 class="font-headline font-bold text-lg text-on-background">Ringkasan Gap Perulangan</h2>
         <p class="text-xs text-on-surface-variant mt-0.5">Jumlah gap berulang (tetap D−1 &amp; D) per program · kolom Site → Perusahaan · klik baris untuk detail</p>
      </div>
      <span class="hsecm-badge shrink-0">{{ $columnCount }} kolom</span>
   </div>

   <div class="overflow-x-auto">
      <table class="hsecm-table gap-summary-table w-full text-sm">
         <thead>
            <tr>
               <th rowspan="2" class="px-4 py-3 text-left min-w-[14rem] align-middle border-b border-slate-200">Parameter</th>
               @forelse($groups as $group)
               <th colspan="{{ max(1, count($group['companies'])) }}" class="px-2 py-2 text-center border-b border-l border-slate-200 font-bold text-on-background">
                  {{ $group['site'] }}
               </th>
               @empty
               <th class="px-3 py-3 text-right text-on-surface-variant font-normal normal-case tracking-normal">Belum ada scope</th>
               @endforelse
            </tr>
            @if(count($groups) > 0)
            <tr>
               @foreach($groups as $group)
                  @foreach($group['companies'] as $company)
                  <th class="px-2 py-2 text-center border-l border-slate-100 font-semibold" title="{{ $company['name'] }}">
                     {{ $company['code'] }}
                  </th>
                  @endforeach
               @endforeach
            </tr>
            @endif
         </thead>
         <tbody>
            @forelse($summary['rows'] ?? [] as $row)
            <tr
               class="border-t border-slate-50 gap-program-row"
               data-gap-program-key="{{ $row['key'] }}"
               tabindex="0"
               role="button"
               aria-label="Lihat detail {{ $row['label'] }}"
            >
               <td class="px-4 py-3 text-on-background whitespace-nowrap">{{ $row['label'] }}</td>
               @if($columnCount === 0)
               <td class="px-3 py-3 text-right text-on-surface-variant">0</td>
               @else
                  @foreach($columns as $col)
                  @php $count = (int) ($row['counts'][$col] ?? 0); @endphp
                  <td class="px-2 py-3 text-center border-l border-slate-50 {{ $count > 0 ? 'text-red-600 font-semibold' : 'text-on-surface-variant' }}">
                     {{ number_format($count) }}
                  </td>
                  @endforeach
               @endif
            </tr>
            @empty
            <tr>
               <td colspan="{{ max(2, $columnCount + 1) }}" class="px-4 py-8 text-center text-on-surface-variant">
                  Tidak ada data gap untuk filter yang dipilih.
               </td>
            </tr>
            @endforelse
         </tbody>
      </table>
   </div>
</div>
