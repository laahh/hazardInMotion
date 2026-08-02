{{-- Matriks Program × Site/Mitra (header 2 tingkat) --}}
@php
   $groups = $summary['groups'] ?? [];
   $columns = $summary['columns'] ?? [];
   $columnCount = count($columns);
   $mode = $summary['mode'] ?? 'scrape';
@endphp
<div class="hsecm-card rounded-2xl overflow-hidden mb-8">
   <div class="px-5 py-4 border-b border-slate-100 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
         <h2 class="font-headline font-bold text-lg text-on-background">{{ $title }}</h2>
         <p class="text-xs text-on-surface-variant mt-0.5">{{ $subtitle }}</p>
      </div>
      <span class="hsecm-badge shrink-0">{{ $columnCount }} kolom</span>
   </div>

   <div class="overflow-x-auto">
      <table class="hsecm-table gap-summary-table w-full text-sm">
         <thead>
            <tr>
               <th rowspan="2" class="px-4 py-3 text-left min-w-[12rem] align-middle border-b border-slate-200">Program</th>
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
            <tr class="border-t border-slate-50">
               <td class="px-4 py-3 text-on-background whitespace-nowrap">{{ $row['label'] }}</td>
               @if($columnCount === 0)
               <td class="px-3 py-3 text-right text-on-surface-variant">—</td>
               @else
                  @foreach($columns as $col)
                  @php
                     $cell = $row['cells'][$col] ?? [];
                     if ($mode === 'tasklist') {
                        $good = (int) ($cell['efektif'] ?? 0);
                        $bad = (int) ($cell['belum_efektif'] ?? 0);
                        $goodLabel = 'Efektif';
                        $badLabel = 'Belum';
                        $extra = '';
                     } else {
                        $good = (int) ($cell['sudah'] ?? 0);
                        $bad = (int) ($cell['belum'] ?? 0);
                        $baru = (int) ($cell['baru'] ?? 0);
                        $goodLabel = 'Sudah';
                        $badLabel = 'Belum';
                        $denom = $good + $bad;
                        $pct = $denom > 0 ? round(100 * $good / $denom) : null;
                        $extra = 'Baru/re-open: '.$baru.($pct !== null ? ' · Clear '.$pct.'%' : '');
                     }
                     $tone = $bad > 0
                        ? 'text-red-600'
                        : ($good > 0 ? 'text-emerald-700' : 'text-on-surface-variant');
                  @endphp
                  <td class="px-1.5 py-2 text-center border-l border-slate-50 {{ $tone }}"
                      @if($extra !== '') title="{{ $extra }}" @endif>
                     <div class="gap-eval-cell inline-flex flex-col items-center font-semibold">
                        <span><span class="lbl">{{ $goodLabel }}</span> {{ number_format($good) }}</span>
                        <span class="text-slate-300 font-normal">|</span>
                        <span><span class="lbl">{{ $badLabel }}</span> {{ number_format($bad) }}</span>
                     </div>
                  </td>
                  @endforeach
               @endif
            </tr>
            @empty
            <tr>
               <td colspan="{{ max(2, $columnCount + 1) }}" class="px-4 py-8 text-center text-on-surface-variant">
                  Tidak ada data evaluasi untuk filter yang dipilih.
               </td>
            </tr>
            @endforelse
         </tbody>
      </table>
   </div>
</div>
