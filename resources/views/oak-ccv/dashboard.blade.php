@extends('oak-ccv.layouts.oak-app')

@section('title', 'OAK CCV Evaluation')

@php
    $d = $dash ?? [];
    $kpi = $d['kpi'] ?? [];
    $meta = $d['meta'] ?? [];
    $filters = $d['filters'] ?? [];
    $weekly = $d['weekly'] ?? [];
    $eval = $d['evaluation'] ?? ['narrative' => '', 'rows' => []];
    $aktivitas = $d['aktivitas'] ?? [];
    $aktColors = $d['aktivitas_colors'] ?? [];
    $stopByAkt = $d['stop_by_aktivitas'] ?? [];
    $sitesRows = $d['sites_rows'] ?? [];
    $entities = $d['entities'] ?? [];
    $heatmap = $d['heatmap'] ?? ['sites' => [], 'entities' => [], 'cells' => [], 'max' => 0];
    $topMitra = $d['top_mitra'] ?? [];
    $tools = $d['tools'] ?? [];
    $layers = $d['layers'] ?? [];
    $stopRows = $d['stop_rows'] ?? [];
    $stopWeeks = $d['stop_weeks'] ?? [];
    $dailyBcVsMitra = $d['daily_bc_vs_mitra'] ?? [];
    $colors = $d['colors'] ?? [];
    $qKeep = array_filter([
        'site' => $filters['site'] ?? '',
        'week' => $filters['week'] ?? '',
        'group' => ($filters['group'] ?? 'all') !== 'all' ? ($filters['group'] ?? '') : '',
        'entity' => $filters['entity'] ?? '',
    ], static fn ($v) => $v !== null && $v !== '');
    $maxStopWeek = 0;
    foreach ($stopWeeks as $sw) {
        $maxStopWeek = max($maxStopWeek, (int) ($sw['rows'] ?? 0));
    }
@endphp

@section('content')
         <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-6 pb-6 border-b border-outline-variant/30">
            <div>
               <nav class="flex items-center gap-2 text-xs font-bold text-on-surface-variant uppercase mb-2">
                  <span>Dashboard</span>
                  <span class="material-symbols-outlined text-xs">chevron_right</span>
                  <span class="text-primary">OAK CCV Evaluation</span>
               </nav>
               <h2 class="font-headline font-extrabold text-4xl text-on-background tracking-tight">OAK CCV Evaluation</h2>
               <p class="text-on-surface-variant font-medium mt-1">
                  Jenis data: <span class="font-semibold text-on-surface">{{ $d['jenis_data'] ?? 'OBSERVASI AREA KRITIS' }}</span>
                  · {{ $meta['date_min'] ?? '—' }} s/d {{ $meta['date_max'] ?? '—' }}
                  ({{ (int) ($meta['days'] ?? 0) }} hari)
                  · Stop/gap: {{ $meta['stop_date_min'] ?? '—' }} s/d {{ $meta['stop_date_max'] ?? '—' }}
               </p>
            </div>
            <form method="get" action="{{ route('oak-ccv.dashboard') }}" class="flex flex-wrap items-end gap-3">
               <label class="flex min-w-[8rem] flex-col gap-1">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Site</span>
                  <select name="site" class="rounded-xl border-outline-variant/30 bg-[#f8fafc] text-sm font-semibold shadow-inner">
                     <option value="">Semua site</option>
                     @foreach (($filters['sites'] ?? []) as $siteOpt)
                     <option value="{{ $siteOpt }}" @selected(($filters['site'] ?? '') === $siteOpt)>{{ $siteOpt }}</option>
                     @endforeach
                  </select>
               </label>
               <label class="flex min-w-[10rem] flex-col gap-1">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Minggu</span>
                  <select name="week" class="rounded-xl border-outline-variant/30 bg-[#f8fafc] text-sm font-semibold shadow-inner">
                     <option value="">Semua minggu</option>
                     @foreach (($filters['weeks'] ?? []) as $wOpt)
                     <option value="{{ $wOpt['week'] ?? '' }}" @selected(($filters['week'] ?? '') === ($wOpt['week'] ?? ''))>{{ $wOpt['label'] ?? ($wOpt['week'] ?? '') }}</option>
                     @endforeach
                  </select>
               </label>
               <label class="flex min-w-[8rem] flex-col gap-1">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Grup</span>
                  <select name="group" class="rounded-xl border-outline-variant/30 bg-[#f8fafc] text-sm font-semibold shadow-inner">
                     <option value="all" @selected(($filters['group'] ?? 'all') === 'all')>Semua</option>
                     <option value="bc" @selected(($filters['group'] ?? '') === 'bc')>BC group</option>
                     <option value="mitra" @selected(($filters['group'] ?? '') === 'mitra')>Mitra kerja</option>
                  </select>
               </label>
               <label class="flex min-w-[9rem] flex-col gap-1">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Entitas BC</span>
                  <select name="entity" class="rounded-xl border-outline-variant/30 bg-[#f8fafc] text-sm font-semibold shadow-inner">
                     <option value="">Semua entitas</option>
                     @foreach (['BC', 'BCE', 'Unggul', 'Primac', 'Suprima', 'Yayasan', 'Mitra'] as $entOpt)
                     <option value="{{ $entOpt }}" @selected(($filters['entity'] ?? '') === $entOpt)>{{ $entOpt }}</option>
                     @endforeach
                  </select>
               </label>
               <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-xs font-bold text-white shadow-sm">Terapkan</button>
               <a href="{{ route('oak-ccv.dashboard') }}" class="inline-flex items-center rounded-xl px-3 py-2.5 text-xs font-bold text-on-surface-variant hover:bg-[#f1f5f9]">Reset</a>
            </form>
         </div>

         <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white p-6 rounded-2xl anchored-card flex flex-col justify-between">
               <div class="flex justify-between items-start">
                  <span class="text-on-surface-variant text-[11px] font-bold tracking-wider uppercase">Total Laporan OAK</span>
                  <div class="p-2 bg-primary/10 rounded-lg">
                     <span class="material-symbols-outlined text-primary">assignment</span>
                  </div>
               </div>
               <div class="mt-4">
                  <p class="font-headline font-extrabold text-4xl tabular-nums">{{ number_format((int) ($kpi['laporan_rows'] ?? 0)) }}</p>
                  @if(($kpi['trend_pct'] ?? null) !== null)
                  <p class="text-[11px] font-bold flex items-center gap-1 mt-1 {{ ($kpi['trend_pct'] ?? 0) <= 0 ? 'text-[#059669]' : 'text-error' }}">
                     <span class="material-symbols-outlined text-xs">{{ ($kpi['trend_pct'] ?? 0) <= 0 ? 'trending_down' : 'trending_up' }}</span>
                     {{ $kpi['trend_label'] ?? '' }}
                  </p>
                  @else
                  <p class="text-on-surface-variant text-[11px] font-medium mt-1">{{ $kpi['trend_label'] ?? '—' }}</p>
                  @endif
                  <p class="text-on-surface-variant text-[10px] font-medium mt-2">{{ number_format((int) ($kpi['laporan_tasks'] ?? 0)) }} unique task · Observasi Area Kritis</p>
               </div>
            </div>

            <button type="button" id="oak-kpi-bc-mitra-card" class="bg-white p-6 rounded-2xl anchored-card flex flex-col justify-between text-left w-full cursor-pointer transition-all hover:shadow-md hover:ring-2 hover:ring-secondary/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary/40" aria-haspopup="dialog" aria-expanded="false" aria-controls="oak-bc-mitra-daily-modal">
               <div class="flex justify-between items-start">
                  <span class="text-on-surface-variant text-[11px] font-bold tracking-wider uppercase">BC vs Mitra Kerja</span>
                  <div class="p-2 bg-secondary/10 rounded-lg">
                     <span class="material-symbols-outlined text-secondary">apartment</span>
                  </div>
               </div>
               <div class="mt-4">
                  <p class="font-headline font-extrabold text-4xl tabular-nums">{{ number_format((float) ($kpi['bc_pct'] ?? 0), 1) }}<span class="text-2xl font-bold">%</span></p>
                  <p class="text-on-surface-variant text-[11px] font-medium mt-1">Porsi grup BC · mitra {{ number_format((float) ($kpi['mitra_pct'] ?? 0), 1) }}%</p>
                  <div class="w-full bg-[#f1f5f9] h-2 rounded-full mt-3 overflow-hidden border border-outline-variant/10 flex">
                     <div class="bg-primary h-full" style="width: {{ max(0, min(100, (float) ($kpi['bc_pct'] ?? 0))) }}%"></div>
                     <div class="bg-[#94a3b8] h-full" style="width: {{ max(0, min(100, (float) ($kpi['mitra_pct'] ?? 0))) }}%"></div>
                  </div>
                  <p class="text-on-surface-variant text-[10px] font-medium mt-2">{{ number_format((int) ($kpi['bc_rows'] ?? 0)) }} BC group · {{ number_format((int) ($kpi['mitra_rows'] ?? 0)) }} mitra · klik untuk grafik harian</p>
               </div>
            </button>

            <div class="bg-white p-6 rounded-2xl anchored-card flex flex-col justify-between">
               <div class="flex justify-between items-start">
                  <span class="text-on-surface-variant text-[11px] font-bold tracking-wider uppercase">Stop / Gap CCV</span>
                  <div class="p-2 bg-[#fef3c7] rounded-lg">
                     <span class="material-symbols-outlined text-[#d97706]">front_hand</span>
                  </div>
               </div>
               <div class="mt-4">
                  <p class="font-headline font-extrabold text-4xl tabular-nums">{{ number_format((int) ($kpi['stop_jobs'] ?? 0)) }}</p>
                  <p class="text-on-surface-variant text-[11px] font-medium mt-1">Pekerjaan di-stop · {{ number_format((int) ($kpi['stop_gaps'] ?? 0)) }} item gap (Tidak Sesuai)</p>
                  <p class="text-on-surface-variant text-[10px] font-medium mt-2">{{ number_format((int) ($kpi['stop_matched'] ?? 0)) }} task ketemu di window OAK · {{ number_format((float) ($kpi['stop_per_1000'] ?? 0), 2) }} stop / 1.000 observasi</p>
               </div>
            </div>

            <div class="bg-white p-6 rounded-2xl anchored-card flex flex-col justify-between">
               <div class="flex justify-between items-start">
                  <span class="text-on-surface-variant text-[11px] font-bold tracking-wider uppercase">Window data</span>
                  <div class="p-2 bg-[#dcfce7] rounded-lg">
                     <span class="material-symbols-outlined text-[#16a34a]">calendar_month</span>
                  </div>
               </div>
               <div class="mt-4">
                  <p class="font-headline font-extrabold text-4xl tabular-nums">{{ (int) ($meta['days'] ?? 0) }}<span class="text-2xl font-bold"> hari</span></p>
                  <p class="text-on-surface-variant text-[11px] font-medium mt-1">Pelaksanaan OAK (bukan YTD penuh)</p>
                  <p class="text-on-surface-variant text-[10px] font-medium mt-2">Stop sheet Maret–Agustus · {{ number_format((int) ($meta['stop_rows'] ?? 0)) }} gap YTD extract</p>
               </div>
            </div>
         </div>

         <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-8 bg-white p-8 rounded-2xl anchored-card">
               <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                  <div>
                     <h3 class="font-headline font-bold text-xl">Trend Observasi OAK per Minggu</h3>
                     <p class="text-[10px] text-on-surface-variant font-medium mt-0.5">Batang bertumpuk = grup BC vs mitra kerja · ISO week</p>
                  </div>
                  <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-[9px] font-bold uppercase tracking-wider text-on-surface-variant">
                     <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-primary"></span> BC group</span>
                     <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#94a3b8]"></span> Mitra kerja</span>
                  </div>
               </div>
               <div class="relative h-80">
                  <div class="flex h-full w-full items-stretch gap-2">
                     @forelse ($weekly as $w)
                     @php
                        $barH = min(100, max(0, (float) ($w['bar_height_pct'] ?? 0)));
                        $cnt = (int) ($w['rows'] ?? 0);
                     @endphp
                     <div class="group relative flex h-full min-w-[2.5rem] flex-1 flex-col justify-end rounded-t-lg border-x border-t border-outline-variant/10 bg-[#f8fafc]" title="{{ ($w['label'] ?? '').' — '.$cnt.' laporan (BC '.($w['bc'] ?? 0).', mitra '.($w['mitra'] ?? 0).')' }}">
                        <div class="relative w-full" style="height: {{ $barH }}%">
                           <span class="absolute -top-6 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap text-[10px] font-semibold text-on-surface">{{ number_format($cnt) }}</span>
                           <div class="absolute inset-0 flex flex-col justify-end overflow-hidden rounded-t-md ring-1 ring-black/10">
                              @if((float) ($w['mitra_stack_pct'] ?? 0) > 0)
                              <div class="min-h-[2px] w-full bg-[#94a3b8]" style="height: {{ (float) $w['mitra_stack_pct'] }}%"></div>
                              @endif
                              @if((float) ($w['bc_stack_pct'] ?? 0) > 0)
                              <div class="min-h-[2px] w-full bg-primary" style="height: {{ (float) $w['bc_stack_pct'] }}%"></div>
                              @endif
                           </div>
                        </div>
                     </div>
                     @empty
                     <div class="flex w-full items-center justify-center text-sm text-on-surface-variant">Belum ada data untuk chart.</div>
                     @endforelse
                  </div>
               </div>
               <div class="mt-6 flex w-full gap-2 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
                  @foreach ($weekly as $w)
                  <span class="flex-1 text-center leading-tight">{{ $w['label'] ?? '—' }}</span>
                  @endforeach
               </div>
            </div>

            <div class="lg:col-span-4 flex flex-col gap-6">
               <div class="rounded-2xl border border-outline-variant/20 bg-white p-6 shadow-sm anchored-card">
                  <div class="mb-4 flex items-start justify-between gap-3">
                     <div class="flex min-w-0 items-center gap-2">
                        <span class="material-symbols-outlined shrink-0 text-2xl text-primary">smart_toy</span>
                        <div class="min-w-0">
                           <h4 class="font-headline text-base font-bold text-on-surface">Ringkasan evaluasi data</h4>
                           <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Aturan data · bukan model AI</p>
                        </div>
                     </div>
                     <span class="shrink-0 rounded-full bg-[#f1f5f9] px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-on-surface-variant">Aturan data</span>
                  </div>
                  <p class="mb-4 text-xs leading-relaxed text-on-surface-variant">{{ $eval['narrative'] ?? '' }}</p>
                  <div class="overflow-x-auto rounded-xl border border-outline-variant/20">
                     <table class="w-full text-left text-[11px]">
                        <thead>
                           <tr class="border-b border-outline-variant/20 bg-[#f8fafc] text-[9px] font-bold uppercase tracking-wider text-on-surface-variant">
                              <th class="px-2 py-2">Metrik</th>
                              <th class="px-2 py-2">Deskripsi</th>
                              <th class="px-2 py-2 text-right">Status</th>
                           </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/15">
                           @forelse (($eval['rows'] ?? []) as $row)
                           <tr class="bg-white">
                              <td class="px-2 py-2.5 align-top font-bold">{{ $row['metric'] ?? '—' }}</td>
                              <td class="px-2 py-2.5 align-top text-on-surface-variant">{{ $row['description'] ?? '—' }}</td>
                              <td class="px-2 py-2.5 text-right align-top">
                                 <span class="inline-flex items-center justify-end gap-1.5">
                                    <span class="h-2 w-2 shrink-0 rounded-full @if(($row['status'] ?? '') === 'critical') bg-red-500 @elseif(($row['status'] ?? '') === 'warning') bg-amber-500 @elseif(($row['status'] ?? '') === 'ok') bg-emerald-500 @else bg-slate-400 @endif"></span>
                                    <span class="text-[10px] font-semibold leading-snug">{{ $row['action_threshold'] ?? '—' }}</span>
                                 </span>
                              </td>
                           </tr>
                           @empty
                           <tr><td colspan="3" class="px-3 py-6 text-center text-on-surface-variant">Belum ada data.</td></tr>
                           @endforelse
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>
         </div>

         <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white p-6 rounded-2xl anchored-card">
               <h3 class="font-headline font-bold text-[11px] mb-6 uppercase tracking-widest text-on-surface-variant">Aktivitas OAK</h3>
               <div class="flex justify-center mb-6">
                  <div class="relative w-36 h-36 rounded-full p-[14px] shadow-inner" style="background: {{ $d['aktivitas_donut'] ?? 'conic-gradient(rgb(241 245 249) 0% 100%)' }}">
                     <div class="flex h-full w-full items-center justify-center rounded-full bg-white">
                        <div class="text-center">
                           <span class="block font-extrabold text-2xl tabular-nums">{{ number_format((int) ($kpi['laporan_rows'] ?? 0)) }}</span>
                           <span class="block text-[9px] uppercase font-bold text-on-surface-variant">Total</span>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="max-h-64 space-y-3 overflow-y-auto pr-1">
                  @forelse ($aktivitas as $row)
                  <div class="flex justify-between items-center gap-2 text-xs">
                     <span class="flex min-w-0 flex-1 items-center gap-2">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $aktColors[$row['name']] ?? '#94a3b8' }}"></span>
                        <span class="truncate" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                     </span>
                     <span class="shrink-0 font-bold tabular-nums">{{ number_format((float) $row['pct'], 1) }}%</span>
                  </div>
                  @empty
                  <p class="text-[11px] text-on-surface-variant">Belum ada data.</p>
                  @endforelse
               </div>
            </div>

            <div class="bg-white p-6 rounded-2xl anchored-card">
               <h3 class="font-headline font-bold text-[11px] mb-2 uppercase tracking-widest text-on-surface-variant">Stop terkait apa</h3>
               <p class="mb-4 text-[10px] text-on-surface-variant">Item gap CCV (Jawaban = Tidak Sesuai) per aktivitas.</p>
               <div class="max-h-96 space-y-4 overflow-y-auto pr-1">
                  @forelse ($stopByAkt as $row)
                  <div class="space-y-1.5">
                     <div class="flex justify-between text-[10px] font-bold uppercase tracking-wider gap-2">
                        <span class="min-w-0 truncate" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                        <span class="shrink-0 text-primary tabular-nums">{{ (int) $row['count'] }}</span>
                     </div>
                     <div class="w-full bg-[#f1f5f9] h-2 rounded-full overflow-hidden border border-outline-variant/10">
                        <div class="bg-[#d97706] h-full rounded-full" style="width: {{ (float) $row['bar_pct'] }}%"></div>
                     </div>
                  </div>
                  @empty
                  <p class="text-[11px] text-on-surface-variant">Tidak ada stop pada filter ini. Filter site/grup hanya menampilkan stop yang join ke observasi OAK.</p>
                  @endforelse
               </div>
            </div>

            <div class="bg-white p-6 rounded-2xl anchored-card">
               <h3 class="font-headline font-bold text-[11px] mb-6 uppercase tracking-widest text-on-surface-variant">Volume per site</h3>
               <div class="max-h-96 space-y-4 overflow-y-auto pr-1">
                  @forelse ($sitesRows as $loc)
                  <div class="space-y-1.5">
                     <div class="flex justify-between text-[10px] font-bold uppercase tracking-wider">
                        <span>{{ $loc['name'] }}</span>
                        <span class="text-primary tabular-nums">{{ number_format((int) $loc['count']) }}</span>
                     </div>
                     <div class="w-full bg-[#f1f5f9] h-2.5 rounded-full overflow-hidden border border-outline-variant/10">
                        <div class="bg-primary h-full rounded-full" style="width: {{ (float) $loc['bar_pct'] }}%"></div>
                     </div>
                  </div>
                  @empty
                  <p class="text-[11px] text-on-surface-variant">Belum ada data lokasi.</p>
                  @endforelse
               </div>
            </div>

            <div class="bg-white p-6 rounded-2xl anchored-card">
               <h3 class="font-headline font-bold text-[11px] mb-2 uppercase tracking-widest text-on-surface-variant">Profiling perusahaan</h3>
               <p class="mb-4 text-[10px] text-on-surface-variant">Grup BC (6 entitas) vs porsi mitra. Klik filter Entitas di atas untuk drill-down.</p>
               <div class="max-h-80 space-y-2.5 overflow-y-auto pr-1">
                  @foreach ($entities as $ent)
                  <a href="{{ route('oak-ccv.dashboard', array_filter(array_merge($qKeep, ['entity' => $ent['entity']]))) }}" class="flex items-center gap-3 rounded-xl border border-outline-variant/15 bg-[#fafbfc] p-2.5 hover:bg-[#f1f5f9] hover:border-primary/20">
                     <span class="h-9 w-9 shrink-0 rounded-full" style="background: {{ $ent['color'] }}22; color: {{ $ent['color'] }}">
                        <span class="flex h-full w-full items-center justify-center text-[10px] font-extrabold">{{ mb_substr($ent['entity'], 0, 2) }}</span>
                     </span>
                     <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-bold">{{ $ent['entity'] }}</p>
                        <p class="truncate text-[10px] text-on-surface-variant">{{ $ent['company'] }}</p>
                     </div>
                     <div class="shrink-0 text-right">
                        <p class="text-sm font-extrabold tabular-nums text-primary">{{ number_format((int) $ent['count']) }}</p>
                        <p class="text-[9px] text-on-surface-variant">{{ number_format((float) $ent['pct'], 1) }}%</p>
                     </div>
                  </a>
                  @endforeach
               </div>
            </div>
         </div>

         <div class="bg-white p-6 rounded-2xl anchored-card overflow-x-auto">
            <div class="mb-4 flex items-start justify-between gap-3">
               <div>
                  <h3 class="font-headline font-bold text-xl">Heatmap site × entitas</h3>
                  <p class="text-[10px] text-on-surface-variant font-medium mt-1">Jumlah observasi OAK. Grup BC: BC, BCE, Unggul, Primac, Suprima, Yayasan. Mitra = selain itu.</p>
               </div>
            </div>
            <table class="w-full min-w-[720px] text-[11px]">
               <thead>
                  <tr class="text-[9px] font-bold uppercase tracking-wider text-on-surface-variant">
                     <th class="px-2 py-2 text-left">Site</th>
                     @foreach (($heatmap['entities'] ?? []) as $hent)
                     <th class="px-2 py-2 text-right">{{ $hent }}</th>
                     @endforeach
                  </tr>
               </thead>
               <tbody>
                  @foreach (($heatmap['sites'] ?? []) as $hsite)
                  <tr class="border-t border-outline-variant/15">
                     <td class="px-2 py-2 font-bold">{{ $hsite }}</td>
                     @foreach (($heatmap['entities'] ?? []) as $hent)
                     @php
                        $cell = (int) (($heatmap['cells'][$hsite][$hent] ?? 0));
                        $maxH = (int) ($heatmap['max'] ?? 0);
                        $t = $maxH > 0 ? $cell / $maxH : 0;
                        $a = $cell > 0 ? 0.08 + 0.72 * $t : 0;
                     @endphp
                     <td class="px-2 py-2 text-right tabular-nums font-semibold" style="background: rgba(57, 82, 188, {{ $a }})">{{ $cell > 0 ? number_format($cell) : '—' }}</td>
                     @endforeach
                  </tr>
                  @endforeach
               </tbody>
            </table>
         </div>

         <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-7 bg-white p-6 rounded-2xl anchored-card">
               <h3 class="font-headline font-bold text-xl mb-1">Top mitra kerja</h3>
               <p class="text-[10px] text-on-surface-variant font-medium mb-4">Selain 6 entitas BC. Urut volume observasi OAK.</p>
               <div class="overflow-x-auto rounded-xl border border-outline-variant/20">
                  <table class="w-full text-[12px]">
                     <thead>
                        <tr class="bg-[#f8fafc] text-[9px] font-bold uppercase tracking-wider text-on-surface-variant">
                           <th class="px-3 py-2 text-left">Perusahaan</th>
                           <th class="px-3 py-2 text-right">Laporan</th>
                        </tr>
                     </thead>
                     <tbody class="divide-y divide-outline-variant/15">
                        @forelse ($topMitra as $m)
                        <tr>
                           <td class="px-3 py-2 font-medium">{{ $m['company'] ?? '—' }}</td>
                           <td class="px-3 py-2 text-right tabular-nums font-bold">{{ number_format((int) ($m['rows'] ?? 0)) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="px-3 py-6 text-center text-on-surface-variant">Tidak ada mitra pada filter ini.</td></tr>
                        @endforelse
                     </tbody>
                  </table>
               </div>
            </div>
            <div class="lg:col-span-5 bg-white p-6 rounded-2xl anchored-card">
               <h3 class="font-headline font-bold text-xl mb-1">Tools pengawasan</h3>
               <p class="text-[10px] text-on-surface-variant font-medium mb-4">Bagaimana observasi OAK dilakukan.</p>
               <div class="max-h-80 space-y-3 overflow-y-auto pr-1">
                  @forelse ($tools as $t)
                  <div class="space-y-1">
                     <div class="flex justify-between text-[10px] font-bold gap-2">
                        <span class="truncate" title="{{ $t['name'] }}">{{ $t['name'] }}</span>
                        <span class="tabular-nums text-primary">{{ number_format((int) $t['count']) }}</span>
                     </div>
                     <div class="w-full bg-[#f1f5f9] h-1.5 rounded-full overflow-hidden">
                        <div class="bg-secondary h-full rounded-full" style="width: {{ (float) $t['bar_pct'] }}%"></div>
                     </div>
                  </div>
                  @empty
                  <p class="text-[11px] text-on-surface-variant">Belum ada data.</p>
                  @endforelse
               </div>
               <h4 class="mt-6 text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Layer pelapor</h4>
               <div class="mt-3 flex flex-wrap gap-2">
                  @foreach ($layers as $ly)
                  <span class="inline-flex items-center gap-1.5 rounded-full border border-outline-variant/20 bg-[#f8fafc] px-3 py-1 text-[11px] font-semibold">
                     {{ $ly['name'] }} <span class="tabular-nums text-primary">{{ number_format((int) $ly['count']) }}</span>
                  </span>
                  @endforeach
               </div>
            </div>
         </div>

         <div class="bg-white p-6 rounded-2xl anchored-card">
            <div class="mb-4 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
               <div>
                  <h3 class="font-headline font-bold text-xl">Daftar stop / gap CCV</h3>
                  <p class="text-[10px] text-on-surface-variant font-medium mt-1">Setiap baris = satu kontrol CCV dengan jawaban Tidak Sesuai. Filter site/grup hanya menampilkan yang join ke observasi OAK pada window pelaksanaan.</p>
               </div>
               <p class="text-xs font-bold text-on-surface-variant">{{ number_format(count($stopRows)) }} item</p>
            </div>
            @if($stopWeeks !== [])
            <div class="mb-6">
               <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-2">Trend stop per minggu (sheet stop, rentang lebih panjang dari pelaksanaan)</p>
               <div class="flex h-16 items-end gap-0.5">
                  @foreach ($stopWeeks as $sw)
                  @php $h = $maxStopWeek > 0 ? round(100 * (int) $sw['rows'] / $maxStopWeek) : 0; @endphp
                  <div class="flex-1 min-w-[4px] rounded-t bg-[#d97706]" style="height: {{ max(8, $h) }}%" title="{{ ($sw['label'] ?? $sw['week']).': '.($sw['rows'] ?? 0) }}"></div>
                  @endforeach
               </div>
            </div>
            @endif
            <div class="overflow-x-auto max-h-[32rem] rounded-xl border border-outline-variant/20">
               <table class="w-full min-w-[960px] text-[11px]">
                  <thead class="sticky top-0 bg-[#f8fafc] z-10">
                     <tr class="text-[9px] font-bold uppercase tracking-wider text-on-surface-variant">
                        <th class="px-3 py-2 text-left">Tanggal</th>
                        <th class="px-3 py-2 text-left">Task</th>
                        <th class="px-3 py-2 text-left">Aktivitas</th>
                        <th class="px-3 py-2 text-left">Gap (detil object)</th>
                        <th class="px-3 py-2 text-left">Join OAK</th>
                     </tr>
                  </thead>
                  <tbody class="divide-y divide-outline-variant/10">
                     @forelse ($stopRows as $sr)
                     <tr class="align-top hover:bg-[#fafbfc]">
                        <td class="px-3 py-2 whitespace-nowrap tabular-nums">{{ $sr['tanggal'] ?? '—' }}</td>
                        <td class="px-3 py-2 font-mono">{{ $sr['task'] ?? '—' }}</td>
                        <td class="px-3 py-2">
                           <p class="font-semibold">{{ $sr['aktivitas'] ?? '—' }}</p>
                           <p class="text-on-surface-variant">{{ $sr['sub_aktivitas'] ?? '' }}</p>
                        </td>
                        <td class="px-3 py-2 text-on-surface">{{ $sr['detil_object'] ?? '—' }}</td>
                        <td class="px-3 py-2">
                           @if(!empty($sr['matched_oak']))
                           <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-800">Ya · {{ $sr['oak_site'] ?? '—' }}</span>
                           @else
                           <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">Tidak di window</span>
                           @endif
                        </td>
                     </tr>
                     @empty
                     <tr><td colspan="5" class="px-3 py-8 text-center text-on-surface-variant">Tidak ada stop pada filter ini.</td></tr>
                     @endforelse
                  </tbody>
               </table>
            </div>
         </div>

         <div id="oak-bc-mitra-daily-modal" class="hidden fixed inset-0 z-[200] flex items-center justify-center p-4 sm:p-6 bg-black/40 backdrop-blur-sm" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="oak-bc-mitra-daily-title">
            <div class="absolute inset-0" data-oak-modal-backdrop></div>
            <div class="relative z-10 flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
               <div class="flex items-start justify-between gap-4 border-b border-outline-variant/20 px-6 py-4">
                  <div>
                     <h2 id="oak-bc-mitra-daily-title" class="font-headline text-xl font-bold text-on-surface">Perbandingan BC vs mitra kerja per hari</h2>
                     <p class="mt-1 text-[11px] text-on-surface-variant">Bar chart grouped: biru = grup BC (BC, BCE, Unggul, Primac, Suprima, Yayasan) · abu-abu = mitra kerja. Mengikuti filter site dan minggu.</p>
                  </div>
                  <button type="button" id="oak-bc-mitra-daily-close" class="rounded-lg p-2 text-on-surface-variant hover:bg-[#f1f5f9]" aria-label="Tutup">
                     <span class="material-symbols-outlined">close</span>
                  </button>
               </div>
               <div class="min-h-0 flex-1 overflow-auto px-6 py-5">
                  <div class="mb-4 flex flex-wrap items-center gap-4 text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                     <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-primary"></span> BC group</span>
                     <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#94a3b8]"></span> Mitra kerja</span>
                  </div>
                  <div class="w-full overflow-x-auto">
                     <div class="flex min-w-full items-stretch gap-2 px-1" style="min-width: max(100%, {{ max(24, count($dailyBcVsMitra)) * 3.1 }}rem)">
                        @forelse ($dailyBcVsMitra as $day)
                        <div class="flex min-w-[2.75rem] flex-1 flex-col items-center" title="{{ ($day['weekday'] ?? '').' '.($day['label'] ?? '').' — BC '.number_format((int) ($day['bc'] ?? 0)).', mitra '.number_format((int) ($day['mitra'] ?? 0)) }}">
                           <div class="flex h-64 w-full items-end justify-center gap-1">
                              <div class="flex h-full w-[45%] max-w-[1.15rem] flex-col justify-end">
                                 <span class="mb-1 text-center text-[8px] font-semibold tabular-nums leading-none text-primary">{{ number_format((int) ($day['bc'] ?? 0)) }}</span>
                                 <div class="w-full rounded-t-md bg-primary ring-1 ring-black/10" style="height: {{ max(0, min(100, (float) ($day['bc_bar_pct'] ?? 0))) }}%"></div>
                              </div>
                              <div class="flex h-full w-[45%] max-w-[1.15rem] flex-col justify-end">
                                 <span class="mb-1 text-center text-[8px] font-semibold tabular-nums leading-none text-[#64748b]">{{ number_format((int) ($day['mitra'] ?? 0)) }}</span>
                                 <div class="w-full rounded-t-md bg-[#94a3b8] ring-1 ring-black/10" style="height: {{ max(0, min(100, (float) ($day['mitra_bar_pct'] ?? 0))) }}%"></div>
                              </div>
                           </div>
                           <span class="mt-2 text-center text-[9px] font-bold uppercase leading-tight tracking-wide text-on-surface-variant">{{ $day['weekday'] ?? '' }}<br>{{ $day['label'] ?? '—' }}</span>
                        </div>
                        @empty
                        <p class="w-full py-12 text-center text-sm text-on-surface-variant">Belum ada data harian untuk filter ini.</p>
                        @endforelse
                     </div>
                  </div>
                  @if($dailyBcVsMitra !== [])
                  <div class="mt-6 overflow-x-auto rounded-xl border border-outline-variant/20">
                     <table class="w-full min-w-[480px] text-[11px]">
                        <thead>
                           <tr class="bg-[#f8fafc] text-[9px] font-bold uppercase tracking-wider text-on-surface-variant">
                              <th class="px-3 py-2 text-left">Hari</th>
                              <th class="px-3 py-2 text-right">BC group</th>
                              <th class="px-3 py-2 text-right">Mitra kerja</th>
                              <th class="px-3 py-2 text-right">Total</th>
                           </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/10">
                           @foreach ($dailyBcVsMitra as $day)
                           <tr>
                              <td class="px-3 py-1.5 font-medium">{{ ($day['weekday'] ?? '').' '.($day['label'] ?? $day['date'] ?? '—') }}</td>
                              <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-primary">{{ number_format((int) ($day['bc'] ?? 0)) }}</td>
                              <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ number_format((int) ($day['mitra'] ?? 0)) }}</td>
                              <td class="px-3 py-1.5 text-right tabular-nums font-bold">{{ number_format((int) ($day['rows'] ?? 0)) }}</td>
                           </tr>
                           @endforeach
                        </tbody>
                     </table>
                  </div>
                  @endif
               </div>
            </div>
         </div>
         <script>
            (function () {
               var card = document.getElementById('oak-kpi-bc-mitra-card');
               var modal = document.getElementById('oak-bc-mitra-daily-modal');
               var closeBtn = document.getElementById('oak-bc-mitra-daily-close');
               if (!card || !modal) return;
               function openModal() {
                  modal.classList.remove('hidden');
                  modal.setAttribute('aria-hidden', 'false');
                  card.setAttribute('aria-expanded', 'true');
                  document.body.classList.add('overflow-hidden');
               }
               function closeModal() {
                  modal.classList.add('hidden');
                  modal.setAttribute('aria-hidden', 'true');
                  card.setAttribute('aria-expanded', 'false');
                  document.body.classList.remove('overflow-hidden');
               }
               card.addEventListener('click', openModal);
               if (closeBtn) closeBtn.addEventListener('click', closeModal);
               modal.addEventListener('click', function (e) {
                  if (e.target && e.target.hasAttribute('data-oak-modal-backdrop')) closeModal();
               });
               document.addEventListener('keydown', function (e) {
                  if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
               });
            })();
         </script>
@endsection
