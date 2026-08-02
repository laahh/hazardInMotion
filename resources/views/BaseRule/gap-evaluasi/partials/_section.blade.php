@php
   $chartId = 'gap-eval-chart-'.$section['key'];
   $hasChart = count($section['chart_labels'] ?? []) > 0;
@endphp
<div class="hsecm-card rounded-2xl overflow-hidden">
   <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
      <div class="flex items-center gap-2 min-w-0">
         <span class="material-symbols-outlined text-primary text-2xl">{{ $section['icon'] ?? 'analytics' }}</span>
         <div class="min-w-0">
            <h2 class="font-headline font-bold text-lg text-on-background truncate">{{ $section['label'] }}</h2>
            <p class="text-xs text-on-surface-variant mt-0.5">
               Belum {{ number_format((int) ($section['total_belum'] ?? 0)) }}
               · Sudah {{ number_format((int) ($section['total_sudah'] ?? 0)) }}
            </p>
         </div>
      </div>
      <span class="hsecm-badge">{{ $section['key'] }}</span>
   </div>

   <div class="grid grid-cols-1 lg:grid-cols-3 gap-0 lg:divide-x divide-slate-100">
      <div class="p-4 lg:col-span-1">
         <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-3">{{ $section['chart_title'] ?? 'Belum vs Sudah' }}</p>
         @if($hasChart)
         <div class="relative h-64">
            <canvas id="{{ $chartId }}" aria-label="{{ $section['chart_title'] ?? 'Chart' }}"></canvas>
         </div>
         @else
         <div class="h-40 flex items-center justify-center rounded-xl bg-slate-50 text-xs text-on-surface-variant">
            Tidak ada data chart
         </div>
         @endif
      </div>

      <div class="p-4 lg:col-span-2 space-y-4">
         <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-red-700 mb-2">Belum diperbaiki (tetap)</p>
            <div class="gap-eval-table rounded-xl border border-slate-100">
               <table class="hsecm-table w-full text-sm">
                  <thead>
                     <tr>
                        <th class="px-3 py-2 text-left">Item</th>
                        <th class="px-3 py-2 text-left">Site</th>
                        <th class="px-3 py-2 text-left">Perusahaan</th>
                        <th class="px-3 py-2 text-center">Streak</th>
                     </tr>
                  </thead>
                  <tbody>
                     @forelse($section['belum_rows'] ?? [] as $row)
                     <tr class="border-t border-slate-50">
                        <td class="px-3 py-2">
                           <div class="font-medium text-on-background">{{ $row['value_label'] ?: '—' }}</div>
                           <div class="text-[11px] text-on-surface-variant font-mono">{{ \Illuminate\Support\Str::limit($row['business_key'] ?? '', 40) }}</div>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $row['site'] !== '' ? $row['site'] : '—' }}</td>
                        <td class="px-3 py-2">{{ $row['perusahaan'] !== '' ? $row['perusahaan'] : '—' }}</td>
                        <td class="px-3 py-2 text-center font-semibold text-red-600">{{ (int) ($row['day_streak'] ?? 0) }}×</td>
                     </tr>
                     @empty
                     <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-on-surface-variant">Tidak ada item belum diperbaiki.</td>
                     </tr>
                     @endforelse
                  </tbody>
               </table>
            </div>
            @if(($section['belum_truncated'] ?? 0) > 0)
            <p class="text-[11px] text-on-surface-variant mt-1">+{{ number_format($section['belum_truncated']) }} baris lainnya</p>
            @endif
         </div>

         <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 mb-2">Sudah diperbaiki (hilang di scrape)</p>
            <div class="gap-eval-table rounded-xl border border-slate-100">
               <table class="hsecm-table w-full text-sm">
                  <thead>
                     <tr>
                        <th class="px-3 py-2 text-left">Item</th>
                        <th class="px-3 py-2 text-left">Site</th>
                        <th class="px-3 py-2 text-left">Perusahaan</th>
                        <th class="px-3 py-2 text-center">Streak sblm</th>
                     </tr>
                  </thead>
                  <tbody>
                     @forelse($section['sudah_rows'] ?? [] as $row)
                     <tr class="border-t border-slate-50">
                        <td class="px-3 py-2">
                           <div class="font-medium text-on-background">{{ $row['value_label'] ?: '—' }}</div>
                           <div class="text-[11px] text-on-surface-variant font-mono">{{ \Illuminate\Support\Str::limit($row['business_key'] ?? '', 40) }}</div>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $row['site'] !== '' ? $row['site'] : '—' }}</td>
                        <td class="px-3 py-2">{{ $row['perusahaan'] !== '' ? $row['perusahaan'] : '—' }}</td>
                        <td class="px-3 py-2 text-center font-semibold text-emerald-700">{{ (int) ($row['day_streak'] ?? 0) }}×</td>
                     </tr>
                     @empty
                     <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-on-surface-variant">Tidak ada item sudah diperbaiki.</td>
                     </tr>
                     @endforelse
                  </tbody>
               </table>
            </div>
            @if(($section['sudah_truncated'] ?? 0) > 0)
            <p class="text-[11px] text-on-surface-variant mt-1">+{{ number_format($section['sudah_truncated']) }} baris lainnya</p>
            @endif
         </div>
      </div>
   </div>
</div>
