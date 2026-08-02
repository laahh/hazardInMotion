@php
   $toneClass = match ($tone ?? 'neutral') {
      'success' => 'border-emerald-100',
      'danger' => 'border-red-100',
      'warning' => 'border-amber-100',
      default => 'border-slate-100',
   };
@endphp
<div class="hsecm-card rounded-2xl overflow-hidden border {{ $toneClass }}">
   <div class="px-5 py-4 border-b border-slate-100 flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
      <div>
         <h3 class="font-headline font-bold text-base text-on-background">{{ $title }}</h3>
         <p class="text-xs text-on-surface-variant mt-0.5">{{ $hint ?? '' }}</p>
      </div>
      <span class="hsecm-badge shrink-0">{{ number_format(count($rows)) }} ditampilkan</span>
   </div>
   <div class="gap-eval-table overflow-x-auto">
      <table class="hsecm-table w-full text-sm">
         <thead>
            <tr>
               <th class="px-4 py-2 text-left">Program</th>
               <th class="px-4 py-2 text-left">Item</th>
               <th class="px-4 py-2 text-left">Site</th>
               <th class="px-4 py-2 text-left">Perusahaan</th>
               <th class="px-4 py-2 text-center">Streak hari</th>
            </tr>
         </thead>
         <tbody>
            @forelse($rows as $row)
            <tr class="border-t border-slate-50">
               <td class="px-4 py-2 whitespace-nowrap font-semibold text-on-background">{{ $row['title'] ?: $row['program_key'] }}</td>
               <td class="px-4 py-2">
                  <div class="text-on-background">{{ $row['value_label'] ?: '—' }}</div>
                  <div class="text-[11px] text-on-surface-variant font-mono">{{ \Illuminate\Support\Str::limit($row['business_key'] ?? '', 48) }}</div>
               </td>
               <td class="px-4 py-2 whitespace-nowrap">{{ $row['site'] !== '' ? $row['site'] : '—' }}</td>
               <td class="px-4 py-2">{{ $row['perusahaan'] !== '' ? $row['perusahaan'] : '—' }}</td>
               <td class="px-4 py-2 text-center font-semibold">{{ (int) ($row['day_streak'] ?? 0) }}×</td>
            </tr>
            @empty
            <tr>
               <td colspan="5" class="px-4 py-8 text-center text-on-surface-variant">Tidak ada data.</td>
            </tr>
            @endforelse
         </tbody>
      </table>
   </div>
   @if(($truncated ?? 0) > 0)
   <div class="px-5 py-3 border-t border-slate-100 text-xs text-on-surface-variant">
      +{{ number_format($truncated) }} baris lainnya tidak ditampilkan (batas {{ 200 }}).
   </div>
   @endif
</div>
