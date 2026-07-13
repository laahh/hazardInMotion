@php
   use App\Enums\AutoBannedManualBanScope;

   $manualBanScopes = $manualBanScopes ?? AutoBannedManualBanScope::cases();
   $manualBanSites = collect($manualBanSites ?? []);
   $manualBanDefaultScope = old('ban_scope', $manualBanDefaultScope ?? AutoBannedManualBanScope::Daily->value);
   $nowIso = now();
@endphp

<section class="rounded-2xl border border-outline-variant/15 bg-white shadow-sm overflow-hidden">
   <div class="flex flex-wrap items-start justify-between gap-3 border-b border-outline-variant/10 bg-slate-50/80 px-5 py-4">
      <div>
         <div class="flex items-center gap-2">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-slate-900 text-[11px] font-bold text-white">1</span>
            <h2 class="font-headline text-base font-bold text-on-background">Input Banned</h2>
         </div>
         <p class="mt-1 text-xs text-on-surface-variant ml-9">
            Masukkan karyawan ke <code class="text-[10px]">scr_daily_banned</code> / <code class="text-[10px]">scr_weekly_banned</code>
            + log banned SUCCESS.
         </p>
      </div>
   </div>

   <form method="POST" action="{{ route('auto-banned.inputasi.reconcile.manual-ban') }}" id="ab-manual-ban-form" class="p-5">
      @csrf

      @if($errors->any() && (old('ban_scope') || old('sid') || old('nama') || old('banned_reason')))
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
         <ul class="list-disc pl-4 space-y-1">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
         </ul>
      </div>
      @endif

      {{-- Baris 1: tipe + karyawan --}}
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-4">
         <div class="lg:col-span-3">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
               Tipe <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-2 gap-2">
               @foreach($manualBanScopes as $scope)
               <label class="cursor-pointer">
                  <input type="radio" name="ban_scope" value="{{ $scope->value }}" class="peer sr-only ab-manual-ban-scope"
                     @checked($manualBanDefaultScope === $scope->value)>
                  <span class="flex items-center justify-center rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm font-bold text-on-surface-variant peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white transition-colors">
                     {{ $scope->label() }}
                  </span>
               </label>
               @endforeach
            </div>
         </div>

         <div class="lg:col-span-5 relative" id="ab-manual-ban-karyawan-wrap"
            data-url="{{ route('auto-banned.inputasi.reconcile.options.karyawan') }}">
            <label for="ab-manual-ban-karyawan" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
               Cari karyawan <span class="text-red-500">*</span>
            </label>
            <input type="text" id="ab-manual-ban-karyawan" autocomplete="off"
               value="{{ old('nama') ? old('nama').(old('sid') ? ' ('.old('sid').')' : '') : '' }}"
               placeholder="Ketik nama, SID, atau NIK…"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm focus:border-primary/30 focus:ring-2 focus:ring-primary/10"/>
            <ul id="ab-manual-ban-karyawan-list"
               class="absolute z-30 mt-1 hidden max-h-56 w-full overflow-y-auto rounded-xl border border-outline-variant/30 bg-white py-1 shadow-lg"></ul>
         </div>

         <div class="lg:col-span-2">
            <label for="ab-manual-ban-sid" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
               SID <span class="text-red-500">*</span>
            </label>
            <input type="text" id="ab-manual-ban-sid" name="sid" required maxlength="32" value="{{ old('sid') }}"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm font-mono uppercase focus:border-primary/30 focus:ring-2 focus:ring-primary/10"/>
         </div>

         <div class="lg:col-span-2">
            <label for="ab-manual-ban-nik" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">NIK</label>
            <input type="text" id="ab-manual-ban-nik" name="nik" maxlength="64" value="{{ old('nik') }}"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm focus:border-primary/30 focus:ring-2 focus:ring-primary/10"/>
         </div>
      </div>

      {{-- Baris 2: identitas --}}
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
         <div>
            <label for="ab-manual-ban-nama" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
               Nama <span class="text-red-500">*</span>
            </label>
            <input type="text" id="ab-manual-ban-nama" name="nama" required maxlength="191" value="{{ old('nama') }}"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm focus:border-primary/30 focus:ring-2 focus:ring-primary/10"/>
         </div>
         <div>
            <label for="ab-manual-ban-perusahaan" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Perusahaan</label>
            <input type="text" id="ab-manual-ban-perusahaan" name="perusahaan" maxlength="191" value="{{ old('perusahaan') }}"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm focus:border-primary/30 focus:ring-2 focus:ring-primary/10"/>
         </div>
         <div>
            <label for="ab-manual-ban-site" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Site</label>
            <input type="text" id="ab-manual-ban-site" name="site_dedicated" list="ab-manual-ban-site-list" maxlength="64" value="{{ old('site_dedicated') }}"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm focus:border-primary/30 focus:ring-2 focus:ring-primary/10"/>
            <datalist id="ab-manual-ban-site-list">
               @foreach($manualBanSites as $site)
               <option value="{{ $site }}"></option>
               @endforeach
            </datalist>
         </div>
      </div>

      {{-- Baris 3: periode + status --}}
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
         <div>
            <label for="ab-manual-ban-shift" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
               Shift <span class="text-red-500">*</span>
            </label>
            <select id="ab-manual-ban-shift" name="filter_shift" required
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm focus:border-primary/30 focus:ring-2 focus:ring-primary/10">
               @foreach(['Shift 1', 'Shift 2'] as $shift)
               <option value="{{ $shift }}" @selected(old('filter_shift', 'Shift 1') === $shift)>{{ $shift }}</option>
               @endforeach
            </select>
         </div>
         <div id="ab-manual-ban-daily-fields">
            <label for="ab-manual-ban-filter-date" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
               Tanggal <span class="text-red-500">*</span>
            </label>
            <input type="date" id="ab-manual-ban-filter-date" name="filter_date"
               value="{{ old('filter_date', $nowIso->toDateString()) }}"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm focus:border-primary/30 focus:ring-2 focus:ring-primary/10"/>
         </div>
         <div id="ab-manual-ban-weekly-year" hidden>
            <label for="ab-manual-ban-iso-year" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
               ISO Year <span class="text-red-500">*</span>
            </label>
            <input type="number" id="ab-manual-ban-iso-year" name="iso_year" min="2020" max="2100"
               value="{{ old('iso_year', $nowIso->isoWeekYear()) }}"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm focus:border-primary/30 focus:ring-2 focus:ring-primary/10"/>
         </div>
         <div id="ab-manual-ban-weekly-week" hidden>
            <label for="ab-manual-ban-iso-week" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
               ISO Week <span class="text-red-500">*</span>
            </label>
            <input type="number" id="ab-manual-ban-iso-week" name="iso_week" min="1" max="53"
               value="{{ old('iso_week', $nowIso->isoWeek()) }}"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm focus:border-primary/30 focus:ring-2 focus:ring-primary/10"/>
         </div>
         <div>
            <label for="ab-manual-ban-status" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
               Status banned <span class="text-red-500">*</span>
            </label>
            <input type="text" id="ab-manual-ban-status" name="banned_status" required maxlength="64"
               value="{{ old('banned_status', AutoBannedManualBanScope::Daily->defaultBannedStatus()) }}"
               list="ab-manual-ban-status-list"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm focus:border-primary/30 focus:ring-2 focus:ring-primary/10"/>
            <datalist id="ab-manual-ban-status-list">
               <option value="BANNED RFID"></option>
               <option value="BANNED SAP"></option>
               <option value="BANNED SAP & TBC"></option>
               <option value="BANNED TBC"></option>
            </datalist>
         </div>
         <div>
            <label for="ab-manual-ban-onsite" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Onsite</label>
            <select id="ab-manual-ban-onsite" name="status_onsite"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm focus:border-primary/30 focus:ring-2 focus:ring-primary/10">
               @foreach(['ONSITE', 'OFFSITE'] as $onsite)
               <option value="{{ $onsite }}" @selected(old('status_onsite', 'ONSITE') === $onsite)>{{ $onsite }}</option>
               @endforeach
            </select>
         </div>
      </div>

      <div class="mb-4">
         <label for="ab-manual-ban-reason" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
            Alasan banned <span class="text-red-500">*</span>
         </label>
         <textarea id="ab-manual-ban-reason" name="banned_reason" rows="2" required maxlength="2000"
            placeholder="Contoh: No Data Offsite | No RFID | SAP N/A"
            class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm focus:border-primary/30 focus:ring-2 focus:ring-primary/10">{{ old('banned_reason') }}</textarea>
      </div>

      <input type="hidden" name="banned_at" id="ab-manual-ban-at" value="{{ old('banned_at') }}"/>

      <div class="flex justify-end">
         <button type="submit"
            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:opacity-95">
            <span class="material-symbols-outlined text-base">save</span>
            Simpan Banned
         </button>
      </div>
   </form>
</section>
