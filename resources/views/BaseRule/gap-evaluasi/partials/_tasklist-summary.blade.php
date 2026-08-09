@php
   $summary = $tasklistSummary ?? [];
   $available = (bool) ($summary['available'] ?? false);
   $totals = $summary['totals'] ?? [];
   $rows = $summary['rows'] ?? [];
@endphp
<div class="hsecm-card rounded-2xl overflow-hidden mb-8 border border-slate-100">
   <div class="px-5 py-4 border-b border-slate-100 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
      <div>
         <h2 class="font-headline font-bold text-lg text-on-background">Summary Tasklist</h2>
         <p class="text-xs text-on-surface-variant mt-0.5">Total seluruh tasklist dari awal sampai akhir · submit &amp; approve per tasklist · per Site → Perusahaan</p>
      </div>
      @if($available)
      <span class="hsecm-badge shrink-0">{{ number_format((int) ($totals['tasklist_count'] ?? 0)) }} tasklist · {{ number_format((int) ($totals['item_total'] ?? 0)) }} item</span>
      @endif
   </div>

   @if(! $available)
   <div class="px-5 py-6 text-sm text-on-surface-variant">
      {{ $summary['message'] ?? 'Data tasklist tidak tersedia.' }}
   </div>
   @else
   <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 px-5 py-4 border-b border-slate-50">
      <div class="rounded-xl bg-slate-50 px-3 py-2">
         <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Tasklist</p>
         <p class="text-xl font-extrabold text-on-background">{{ number_format((int) ($totals['tasklist_count'] ?? 0)) }}</p>
      </div>
      <div class="rounded-xl bg-slate-50 px-3 py-2">
         <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Total Item</p>
         <p class="text-xl font-extrabold text-on-background">{{ number_format((int) ($totals['item_total'] ?? 0)) }}</p>
      </div>
      <div class="rounded-xl bg-amber-50 px-3 py-2 border border-amber-100">
         <p class="text-[10px] font-bold uppercase tracking-wider text-amber-800">Belum Submit</p>
         <p class="text-xl font-extrabold text-amber-900">{{ number_format((int) ($totals['belum_submit'] ?? 0)) }}</p>
         <p class="text-[10px] text-amber-800/80 mt-0.5">tasklist</p>
      </div>
      <div class="rounded-xl bg-sky-50 px-3 py-2 border border-sky-100">
         <p class="text-[10px] font-bold uppercase tracking-wider text-sky-800">Sudah Submit</p>
         <p class="text-xl font-extrabold text-sky-900">{{ number_format((int) ($totals['sudah_submit'] ?? 0)) }}</p>
         <p class="text-[10px] text-sky-800/80 mt-0.5">tasklist</p>
      </div>
      <div class="rounded-xl bg-orange-50 px-3 py-2 border border-orange-100">
         <p class="text-[10px] font-bold uppercase tracking-wider text-orange-800">Belum Approve</p>
         <p class="text-xl font-extrabold text-orange-900">{{ number_format((int) ($totals['belum_approve'] ?? 0)) }}</p>
         <p class="text-[10px] text-orange-800/80 mt-0.5">tasklist</p>
      </div>
      <div class="rounded-xl bg-emerald-50 px-3 py-2 border border-emerald-100">
         <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Sudah Approve</p>
         <p class="text-xl font-extrabold text-emerald-900">{{ number_format((int) ($totals['sudah_approve'] ?? 0)) }}</p>
         <p class="text-[10px] text-emerald-800/80 mt-0.5">tasklist</p>
      </div>
   </div>

   <div class="overflow-x-auto">
      <table class="hsecm-table w-full text-sm">
         <thead>
            <tr>
               <th class="px-4 py-2 text-left">Site</th>
               <th class="px-4 py-2 text-left">Perusahaan</th>
               <th class="px-4 py-2 text-right">Tasklist</th>
               <th class="px-4 py-2 text-right">Total Item</th>
               <th class="px-4 py-2 text-right">Belum Submit <span class="font-normal normal-case tracking-normal text-[10px] text-on-surface-variant">(tasklist)</span></th>
               <th class="px-4 py-2 text-right">Sudah Submit <span class="font-normal normal-case tracking-normal text-[10px] text-on-surface-variant">(tasklist)</span></th>
               <th class="px-4 py-2 text-right">Belum Approve <span class="font-normal normal-case tracking-normal text-[10px] text-on-surface-variant">(tasklist)</span></th>
               <th class="px-4 py-2 text-right">Sudah Approve <span class="font-normal normal-case tracking-normal text-[10px] text-on-surface-variant">(tasklist)</span></th>
               <th class="px-4 py-2 text-right">Rejected <span class="font-normal normal-case tracking-normal text-[10px] text-on-surface-variant">(item)</span></th>
            </tr>
         </thead>
         <tbody>
            @forelse($rows as $row)
            <tr class="border-t border-slate-50">
               <td class="px-4 py-2 font-semibold text-on-background whitespace-nowrap">{{ $row['site'] }}</td>
               <td class="px-4 py-2 whitespace-nowrap">{{ $row['perusahaan'] }}</td>
               <td class="px-4 py-2 text-right font-semibold">{{ number_format((int) $row['tasklist_count']) }}</td>
               <td class="px-4 py-2 text-right">{{ number_format((int) $row['item_total']) }}</td>
               <td class="px-4 py-2 text-right {{ (int) $row['belum_submit'] > 0 ? 'text-amber-800 font-semibold' : 'text-on-surface-variant' }}">
                  {{ number_format((int) $row['belum_submit']) }}
               </td>
               <td class="px-4 py-2 text-right {{ (int) $row['sudah_submit'] > 0 ? 'text-sky-800 font-semibold' : 'text-on-surface-variant' }}">
                  {{ number_format((int) $row['sudah_submit']) }}
               </td>
               <td class="px-4 py-2 text-right {{ (int) $row['belum_approve'] > 0 ? 'text-orange-800 font-semibold' : 'text-on-surface-variant' }}">
                  {{ number_format((int) $row['belum_approve']) }}
               </td>
               <td class="px-4 py-2 text-right {{ (int) $row['sudah_approve'] > 0 ? 'text-emerald-800 font-semibold' : 'text-on-surface-variant' }}">
                  {{ number_format((int) $row['sudah_approve']) }}
               </td>
               <td class="px-4 py-2 text-right {{ (int) $row['rejected'] > 0 ? 'text-red-700 font-semibold' : 'text-on-surface-variant' }}">
                  {{ number_format((int) $row['rejected']) }}
               </td>
            </tr>
            @empty
            <tr>
               <td colspan="9" class="px-4 py-8 text-center text-on-surface-variant">
                  {{ $summary['message'] ?? 'Belum ada tasklist.' }}
               </td>
            </tr>
            @endforelse
         </tbody>
         @if(count($rows) > 0)
         <tfoot>
            <tr class="border-t border-slate-200 bg-slate-50/80 font-semibold">
               <td class="px-4 py-2" colspan="2">Total</td>
               <td class="px-4 py-2 text-right">{{ number_format((int) ($totals['tasklist_count'] ?? 0)) }}</td>
               <td class="px-4 py-2 text-right">{{ number_format((int) ($totals['item_total'] ?? 0)) }}</td>
               <td class="px-4 py-2 text-right">{{ number_format((int) ($totals['belum_submit'] ?? 0)) }}</td>
               <td class="px-4 py-2 text-right">{{ number_format((int) ($totals['sudah_submit'] ?? 0)) }}</td>
               <td class="px-4 py-2 text-right">{{ number_format((int) ($totals['belum_approve'] ?? 0)) }}</td>
               <td class="px-4 py-2 text-right">{{ number_format((int) ($totals['sudah_approve'] ?? 0)) }}</td>
               <td class="px-4 py-2 text-right">{{ number_format((int) ($totals['rejected'] ?? 0)) }}</td>
            </tr>
         </tfoot>
         @endif
      </table>
   </div>
   @endif
</div>
