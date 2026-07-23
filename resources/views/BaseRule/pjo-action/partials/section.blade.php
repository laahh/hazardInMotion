@php
   /** @var array<string, mixed> $section */
   $tone = $section['tone'] ?? 'info';
   $available = (bool) ($section['available'] ?? false);
   $needsAction = (bool) ($section['needs_action'] ?? false);
   $columns = $section['columns'] ?? [];
   $rows = $section['rows'] ?? [];
   $total = (int) ($section['total'] ?? 0);
   $truncated = (bool) ($section['truncated'] ?? false);
@endphp

<section id="section-{{ $section['key'] }}" class="hsecm-card rounded-2xl overflow-hidden hsecm-section-tone-{{ $tone }}">
   <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
         <div class="flex flex-wrap items-center gap-2">
            <h3 class="font-headline font-bold text-base text-on-background">
               {{ $number }}. {{ $section['title'] }}:
               <span class="text-primary">{{ $section['value'] ?? '—' }}</span>
            </h3>
            @if($needsAction)
               <span class="hsecm-badge hsecm-badge--danger">Perlu aksi</span>
            @elseif($available && $total === 0)
               <span class="hsecm-badge hsecm-badge--success">Clear</span>
            @elseif(! $available)
               <span class="hsecm-badge">Belum tersedia</span>
            @else
               <span class="hsecm-badge">Pantau</span>
            @endif
            @if($available && $total > 0)
               <span class="text-[11px] text-on-surface-variant">{{ number_format($total) }} baris</span>
            @endif
         </div>
         @if(!empty($section['action']))
            <div class="hsecm-action-chip mt-2">
               <span class="material-symbols-outlined text-sm mt-0.5">task_alt</span>
               <span><strong>Aksi PJO:</strong> {{ $section['action'] }}</span>
            </div>
         @endif
      </div>
      @if(!empty($section['detail_url']))
         <a href="{{ $section['detail_url'] }}"
            class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-3 py-2 text-xs font-bold text-white hover:opacity-95 shrink-0">
            <span class="material-symbols-outlined text-sm">open_in_new</span>
            Buka dataset
         </a>
      @endif
   </div>

   <div class="p-5">
      @if(! $available)
         <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-on-surface-variant">
            {{ $section['note'] ?? 'Data belum tersedia.' }}
         </div>
      @elseif($rows === [])
         <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            Tidak ada data pada scope filter saat ini.
         </div>
      @else
         <div class="hsecm-scroll-box">
            <table class="hsecm-table w-full text-sm">
               <thead>
                  <tr>
                     @foreach($columns as $column)
                        <th class="px-3 py-2 text-left">{{ $column['label'] }}</th>
                     @endforeach
                  </tr>
               </thead>
               <tbody>
                  @foreach($rows as $row)
                     <tr class="border-t border-slate-50">
                        @foreach($columns as $column)
                           <td class="px-3 py-2 align-top">{{ $row[$column['key']] ?? '—' }}</td>
                        @endforeach
                     </tr>
                  @endforeach
               </tbody>
            </table>
         </div>
         @if($truncated && !empty($section['detail_url']))
            <p class="mt-2 text-[11px] text-on-surface-variant">
               Menampilkan preview {{ count($rows) }} dari {{ number_format($total) }} baris.
               <a href="{{ $section['detail_url'] }}" class="font-bold text-primary hover:underline">Lihat semua</a>
            </p>
         @endif
      @endif
   </div>
</section>
