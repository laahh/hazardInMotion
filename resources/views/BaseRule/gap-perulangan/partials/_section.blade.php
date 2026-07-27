{{-- Section detail per program: chart + tabel scrollable --}}
@php
   $layout = $section['layout'] ?? 'generic';
   $chartId = 'gap-chart-' . $section['key'];
   $hasChart = count($section['top_chart'] ?? []) > 0;
@endphp
<div class="hsecm-card rounded-2xl overflow-hidden">
   <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
      <div class="flex items-center gap-2 min-w-0">
         <span class="material-symbols-outlined text-primary text-2xl">{{ $section['icon'] ?? 'analytics' }}</span>
         <div class="min-w-0">
            <h2 class="font-headline font-bold text-lg text-on-background truncate">{{ $section['label'] }}</h2>
            <p class="text-xs text-on-surface-variant mt-0.5">{{ number_format($section['total'] ?? 0) }} item (batch terkini)</p>
         </div>
      </div>
      <span class="hsecm-badge">{{ $section['key'] }}</span>
   </div>

   <div class="grid grid-cols-1 lg:grid-cols-3 gap-0 lg:divide-x divide-slate-100">
      <div class="p-4 lg:col-span-1">
         <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-3">{{ $section['chart_title'] }}</p>
         @if($hasChart)
         <div class="relative h-64">
            <canvas id="{{ $chartId }}" aria-label="{{ $section['chart_title'] }}"></canvas>
         </div>
         @else
         <div class="h-40 flex items-center justify-center rounded-xl bg-slate-50 text-xs text-on-surface-variant">
            Tidak ada data chart
         </div>
         @endif
      </div>

      <div class="p-4 lg:col-span-2">
         <div class="gap-scroll-table rounded-xl border border-slate-100">
            @if($layout === 'sap-rfid')
               <table class="hsecm-table w-full text-sm">
                  <thead>
                     <tr>
                        @foreach($section['table_headers'] as $header)
                        <th class="px-3 py-2 text-left">{{ $header }}</th>
                        @endforeach
                     </tr>
                  </thead>
                  <tbody>
                     @forelse($section['table_rows'] as $row)
                     <tr class="border-t border-slate-50">
                        <td class="px-3 py-2 text-on-surface-variant">{{ $row['rank'] }}</td>
                        <td class="px-3 py-2 font-semibold text-on-background whitespace-nowrap">{{ $row['nama'] }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $row['sid'] }}</td>
                        <td class="px-3 py-2 whitespace-nowrap font-semibold">{{ $row['site'] ?? '—' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $row['jabatan'] }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $row['perusahaan'] }}</td>
                        <td class="px-3 py-2 text-right font-semibold {{ $row['gap_count'] > 0 ? 'text-red-600' : '' }}">{{ number_format($row['gap_count']) }}</td>
                        <td class="px-3 py-2 text-right">{{ $row['sap'] }}</td>
                     </tr>
                     @empty
                     <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-on-surface-variant">Tidak ada data perulangan.</td>
                     </tr>
                     @endforelse
                  </tbody>
               </table>
            @elseif($layout === 'coverage-cctv')
               <div class="overflow-x-auto">
               <table class="hsecm-table w-full text-sm min-w-[48rem]">
                  <thead>
                     <tr>
                        <th class="px-3 py-2 text-left sticky left-0 bg-slate-50 z-[1]">Rank</th>
                        <th class="px-3 py-2 text-left sticky left-10 bg-slate-50 z-[1]">Site</th>
                        <th class="px-3 py-2 text-left">Lokasi</th>
                        <th class="px-3 py-2 text-left">Detail Lokasi</th>
                        @foreach($section['date_headers'] ?? [] as $ymd => $label)
                        <th class="px-2 py-2 text-center whitespace-nowrap">{{ $label }}</th>
                        @endforeach
                     </tr>
                  </thead>
                  <tbody>
                     @forelse($section['table_rows'] as $row)
                     <tr class="border-t border-slate-50">
                        <td class="px-3 py-2 text-on-surface-variant sticky left-0 bg-white">{{ $row['rank'] }}</td>
                        <td class="px-3 py-2 font-semibold whitespace-nowrap sticky left-10 bg-white">{{ $row['site'] }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $row['lokasi'] }}</td>
                        <td class="px-3 py-2 whitespace-nowrap max-w-[14rem] truncate" title="{{ $row['detil'] }}">{{ $row['detil'] }}</td>
                        @foreach($section['date_headers'] ?? [] as $ymd => $label)
                        @php $mark = $row['days'][$ymd] ?? ''; @endphp
                        <td class="px-2 py-2 text-center font-bold {{ $mark === 'V' ? 'text-emerald-600' : ($mark === 'X' ? 'text-red-600' : 'text-on-surface-variant') }}">
                           {{ $mark !== '' ? $mark : '' }}
                        </td>
                        @endforeach
                     </tr>
                     @empty
                     <tr>
                        <td colspan="{{ 4 + count($section['date_headers'] ?? []) }}" class="px-4 py-8 text-center text-on-surface-variant">Tidak ada data coverage.</td>
                     </tr>
                     @endforelse
                  </tbody>
               </table>
               </div>
               <p class="text-[10px] text-on-surface-variant mt-2">Bisa discroll · X = tidak tercover · V = tercover · kosong = tidak ada data hari itu</p>
            @else
               <table class="hsecm-table w-full text-sm">
                  <thead>
                     <tr>
                        @foreach($section['table_headers'] as $header)
                        <th class="px-3 py-2 text-left">{{ $header }}</th>
                        @endforeach
                     </tr>
                  </thead>
                  <tbody>
                     @forelse($section['table_rows'] as $row)
                     <tr class="border-t border-slate-50">
                        <td class="px-3 py-2 text-on-surface-variant">{{ $row['rank'] }}</td>
                        @foreach($section['table_column_keys'] as $col)
                        <td class="px-3 py-2 whitespace-nowrap max-w-[14rem] truncate" title="{{ $row['cells'][$col] ?? '' }}">{{ $row['cells'][$col] ?? '—' }}</td>
                        @endforeach
                        <td class="px-3 py-2 text-right font-semibold {{ ($row['gap_count'] ?? 0) > 0 ? 'text-red-600' : '' }}">{{ number_format($row['gap_count'] ?? 0) }}</td>
                     </tr>
                     @empty
                     <tr>
                        <td colspan="{{ count($section['table_headers'] ?? [1]) }}" class="px-4 py-8 text-center text-on-surface-variant">Tidak ada data perulangan.</td>
                     </tr>
                     @endforelse
                  </tbody>
               </table>
            @endif
         </div>
      </div>
   </div>
</div>
