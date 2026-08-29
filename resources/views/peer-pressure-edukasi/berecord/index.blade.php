@extends('peer-pressure-edukasi.layouts.peer-app')

@section('title', 'BeRecord (OLAP · Postgres)')

@section('content')
<div class="bg-white rounded-2xl anchored-card overflow-hidden">
   <div class="p-6 border-b border-outline-variant/20 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div>
         <h2 class="font-headline font-bold text-xl text-on-surface">BeRecord</h2>
         <p class="text-xs text-on-surface-variant font-medium">List BeRecord dari database</p>
      </div>
      <div class="flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
         <form method="get" action="{{ route('peer-pressure-edukasi.berecord.index') }}" class="flex w-full min-w-0 flex-1 flex-col gap-2 sm:max-w-md sm:flex-row sm:items-center">
            <div class="relative min-w-0 flex-1">
               <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 z-0 -translate-y-1/2 text-lg text-on-surface-variant">search</span>
               <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="Cari nama, SID, deskripsi, perusahaan, status…" autocomplete="off" class="w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] py-2 pl-10 pr-3 text-sm text-on-surface outline-none transition-shadow placeholder:text-on-surface-variant/60 focus:border-primary/40 focus:ring-2 focus:ring-primary/15" aria-label="Cari BeRecord" {{ ! $connected ? 'disabled' : '' }}>
            </div>
            <div class="flex shrink-0 gap-2">
               <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-outline-variant/30 bg-white px-4 py-2 text-xs font-bold shadow-sm transition-colors hover:bg-surface-container-high disabled:opacity-50" {{ ! $connected ? 'disabled' : '' }}>Cari</button>
               @if(filled($q ?? null))
               <a href="{{ route('peer-pressure-edukasi.berecord.index') }}" class="inline-flex items-center justify-center rounded-xl px-3 py-2 text-xs font-bold text-on-surface-variant hover:bg-[#f1f5f9]">Reset</a>
               @endif
            </div>
         </form>
      </div>
   </div>

   @if (! $connected)
   <div class="px-6 py-8 border-b border-outline-variant/15 bg-amber-50/80 text-sm text-amber-950">
      <p class="font-bold flex items-center gap-2"><span class="material-symbols-outlined text-xl">cloud_off</span> Postgres OLAP tidak terhubung</p>
      <p class="mt-2 text-xs text-amber-900/90">Periksa <span class="font-mono">pgsql_direct</span> (PG_HOST) atau tunnel <span class="font-mono">pgsql_ssh</span> (PG_SSH_HOST:PG_SSH_LOCAL_PORT) dan view <span class="font-mono">bcsid.bep_vw_berecord</span>.</p>
   </div>
   @elseif ($chError)
   <div class="px-6 py-8 border-b border-outline-variant/15 bg-red-50 text-sm text-red-900">
      <p class="font-bold">Query gagal</p>
      <p class="mt-1 text-xs font-mono break-all">{{ $chError }}</p>
   </div>
   @endif

   <div class="overflow-x-auto">
      <table class="w-full text-sm text-left min-w-[2000px]">
         <thead class="bg-[#f8fafc] text-on-surface-variant font-bold text-[9px] uppercase tracking-[0.08em] border-b border-outline-variant/20">
            <tr>
               @foreach ($columnLabels as $colKey => $label)
                  <th class="px-4 py-4 whitespace-nowrap max-w-[200px]" title="{{ $label }}">{{ \Illuminate\Support\Str::limit($label, 28) }}</th>
               @endforeach
            </tr>
         </thead>
         <tbody class="divide-y divide-outline-variant/10">
            @forelse ($rows as $r)
               <tr class="hover:bg-[#f8fafc] transition-colors align-top">
                  @foreach ($columnLabels as $colKey => $label)
                     @php
                        $cell = $r[$colKey] ?? $r[str_replace('`', '', $colKey)] ?? null;
                        $display = $cell === null || $cell === '' ? '—' : $cell;
                        if (is_string($display) && mb_strlen($display) > 120) {
                           $short = mb_substr($display, 0, 120) . '…';
                        } else {
                           $short = $display;
                        }
                     @endphp
                     <td class="px-4 py-3 text-[11px] text-on-surface max-w-[220px]" title="{{ is_string($cell) ? $cell : '' }}">
                        <span class="line-clamp-3 break-words">{{ $short }}</span>
                     </td>
                  @endforeach
               </tr>
            @empty
               <tr>
                  <td colspan="{{ count($columnLabels) }}" class="px-8 py-10 text-center text-sm text-on-surface-variant font-medium">
                     @if (! $connected)
                        Tidak ada data — koneksi tidak tersedia.
                     @elseif ($chError)
                        Tidak dapat menampilkan data.
                     @elseif(filled($q ?? null))
                        Tidak ada hasil untuk pencarian ini.
                     @else
                        Belum ada baris di view ini.
                     @endif
                  </td>
               </tr>
            @endforelse
         </tbody>
      </table>
   </div>
   <div class="p-6 bg-[#f8fafc] flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-t border-outline-variant/20">
      <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">
         @if (! $connected || $chError)
            Menampilkan 0 entri
         @elseif ($rows->total() === 0)
            Menampilkan 0 entri
         @else
            Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ number_format($rows->total()) }} entri
         @endif
      </p>
      <div class="flex gap-2">
         @if (! $connected || $chError || $rows->total() === 0)
            <button type="button" class="p-2 border border-outline-variant/30 rounded-lg opacity-40 cursor-not-allowed" disabled aria-disabled="true"><span class="material-symbols-outlined text-sm">chevron_left</span></button>
            <button type="button" class="p-2 border border-outline-variant/30 rounded-lg opacity-40 cursor-not-allowed" disabled aria-disabled="true"><span class="material-symbols-outlined text-sm">chevron_right</span></button>
         @else
            @if ($rows->onFirstPage())
               <button type="button" class="p-2 border border-outline-variant/30 rounded-lg opacity-40 cursor-not-allowed" disabled aria-disabled="true"><span class="material-symbols-outlined text-sm">chevron_left</span></button>
            @else
               <a href="{{ $rows->previousPageUrl() }}" class="p-2 border border-outline-variant/30 rounded-lg hover:bg-white hover:shadow-md transition-all inline-flex" rel="prev"><span class="material-symbols-outlined text-sm">chevron_left</span></a>
            @endif
            @if (! $rows->hasMorePages())
               <button type="button" class="p-2 border border-outline-variant/30 rounded-lg opacity-40 cursor-not-allowed" disabled aria-disabled="true"><span class="material-symbols-outlined text-sm">chevron_right</span></button>
            @else
               <a href="{{ $rows->nextPageUrl() }}" class="p-2 border border-outline-variant/30 rounded-lg hover:bg-white hover:shadow-md transition-all inline-flex" rel="next"><span class="material-symbols-outlined text-sm">chevron_right</span></a>
            @endif
         @endif
      </div>
   </div>
</div>
@endsection
