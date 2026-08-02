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
               <th class="px-4 py-2 text-left">Scope</th>
               <th class="px-4 py-2 text-left">Status TL</th>
               <th class="px-4 py-2 text-left">Pengirim</th>
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
               <td class="px-4 py-2 text-xs">
                  {{ ($row['site'] ?: '—').' · '.($row['perusahaan'] ?: '—') }}
               </td>
               <td class="px-4 py-2 uppercase text-xs font-bold">{{ $row['status'] ?? '—' }}</td>
               <td class="px-4 py-2 text-xs">
                  <div>{{ $row['submitted_by_name'] !== '' ? $row['submitted_by_name'] : '—' }}</div>
                  <div class="text-on-surface-variant">{{ $row['submitted_at'] ?? '' }}</div>
               </td>
            </tr>
            @empty
            <tr>
               <td colspan="5" class="px-4 py-8 text-center text-on-surface-variant">Tidak ada data.</td>
            </tr>
            @endforelse
         </tbody>
      </table>
   </div>
</div>
