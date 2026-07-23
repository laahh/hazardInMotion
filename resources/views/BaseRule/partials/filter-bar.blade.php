{{--
  Expected:
  - $actionRoute (string route name)
  - $actionParams (optional array)
  - $filters
  - $filterOptions
  - $showCompany (bool, default true)
  - $showSearch (bool, default false)
  - $showDate (bool, default true)
--}}
@php
   $actionParams = $actionParams ?? [];
   $showCompany = $showCompany ?? true;
   $showSearch = $showSearch ?? false;
   $showDate = $showDate ?? true;
@endphp
<form method="GET" action="{{ route($actionRoute, $actionParams) }}" class="hsecm-card rounded-2xl p-4 mb-6">
   <div class="flex flex-wrap items-end gap-3">
      <div class="min-w-[10rem]">
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Site</label>
         <select name="site" class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
            <option value="">Semua Site</option>
            @foreach($filterOptions['sites'] ?? [] as $site)
            <option value="{{ $site }}" @selected(($filters['site'] ?? '') === $site)>{{ $site }}</option>
            @endforeach
         </select>
      </div>

      @if($showCompany)
      <div class="min-w-[12rem]">
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Perusahaan</label>
         <select name="perusahaan" class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
            <option value="">Semua Perusahaan</option>
            @foreach($filterOptions['companies'] ?? [] as $company)
            <option value="{{ $company }}" @selected(($filters['perusahaan'] ?? '') === $company)>{{ $company }}</option>
            @endforeach
         </select>
      </div>
      @endif

      @if($showDate)
      <div class="min-w-[10rem]">
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Tanggal Dari</label>
         <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
      </div>
      <div class="min-w-[10rem]">
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Tanggal Sampai</label>
         <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
      </div>
      @endif

      <div class="min-w-[8rem]">
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Week</label>
         <select name="week" class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
            <option value="">Semua Week</option>
            @foreach($filterOptions['weeks'] ?? [] as $week)
            <option value="{{ $week }}" @selected(($filters['week'] ?? '') === (string) $week)>{{ $week }}</option>
            @endforeach
         </select>
      </div>

      <div class="min-w-[7rem]">
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Year</label>
         <select name="year" class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
            <option value="">Semua Year</option>
            @foreach($filterOptions['years'] ?? [] as $year)
            <option value="{{ $year }}" @selected(($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
            @endforeach
         </select>
      </div>

      @if($showSearch)
      <div class="min-w-[14rem] flex-1">
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Cari</label>
         <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="SID, nama, deskripsi..." class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
      </div>
      @endif

      <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white hover:opacity-95">
         <span class="material-symbols-outlined text-sm">filter_alt</span>
         Terapkan
      </button>
      <a href="{{ route($actionRoute, $actionParams) }}" class="inline-flex items-center gap-2 rounded-xl border border-outline-variant/40 bg-white px-4 py-2 text-sm font-semibold text-on-surface-variant hover:text-primary">
         Reset
      </a>
   </div>
</form>
