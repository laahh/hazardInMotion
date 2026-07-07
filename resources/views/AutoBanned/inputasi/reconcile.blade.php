@extends('AutoBanned.layouts.app')

@section('title', 'Rekonsiliasi Log Unban')

@section('page-header')
   @include('AutoBanned.partials.page-header', [
      'breadcrumbCurrent' => 'Rekonsiliasi Log',
      'pageTitle' => 'Rekonsiliasi Log Unban',
      'pageSubtitle' => 'Backfill pengajuan Disetujui — opsional dengan log unban SUCCESS',
   ])
@endsection

@section('content')
@php
   use App\Enums\AutoBannedReconcileUnbanLogMode;

   $gapRows = collect($gapRows ?? []);
   $tableAvailable = $tableAvailable ?? false;
   $filters = $filters ?? [];
   $filterOptions = $filterOptions ?? ['sites' => collect()];
   $queryBase = array_filter([
      'min_days_old' => $filters['min_days_old'] ?? $defaultMinDaysOld,
      'site' => $filters['site'] ?? '',
      'sid' => $filters['sid'] ?? '',
      'q' => $filters['q'] ?? '',
   ], static fn ($value) => $value !== '' && $value !== null);
@endphp

<div class="mb-4 flex flex-wrap items-center gap-3">
   <a href="{{ route('auto-banned.inputasi.index') }}" class="inline-flex items-center gap-1 text-sm font-bold text-primary hover:underline">
      <span class="material-symbols-outlined text-base">arrow_back</span>
      Kembali ke Inputasi
   </a>
   <a href="{{ route('auto-banned.pipeline-monitoring.index', ['pipeline_stage' => 'no_request']) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-on-surface-variant hover:text-primary">
      <span class="material-symbols-outlined text-base">timeline</span>
      Lihat Pipeline
   </a>
</div>

@if(session('success'))
<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
   {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
   {{ session('error') }}
</div>
@endif

<div class="rounded-2xl border border-amber-200/70 bg-amber-50/60 px-5 py-4 text-sm text-amber-950 mb-5">
   <p class="font-bold mb-1">Kapan menggunakan fitur ini?</p>
   <p class="text-xs leading-relaxed text-amber-900/90">
      Untuk SID yang belum tercatat di sistem. Pilih mode rekonsiliasi:
      <strong>SUCCESS</strong> = pengajuan Disetujui + log unban SUCCESS (unban sudah selesai manual).
      <strong>BLM SUKSES</strong> = hanya pengajuan Disetujui (unban otomatis belum berhasil / masih menunggu).
      Default filter: data banned dengan <code class="text-[11px]">filter_date</code> H-{{ $defaultMinDaysOld }} atau lebih lama.
   </p>
</div>

@if(!$tableAvailable)
<div class="rounded-2xl border border-outline-variant/15 bg-white p-6 text-sm text-on-surface-variant">
   Tabel <code>sid_banned_log</code> belum tersedia.
</div>
@else

<div class="rounded-2xl border border-outline-variant/15 bg-white shadow-sm mb-5">
   <div class="border-b border-outline-variant/15 px-5 py-4">
      <form method="GET" action="{{ route('auto-banned.inputasi.reconcile.index') }}" class="flex flex-wrap items-end gap-3">
         <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Min. hari lalu (H-N)</label>
            <input type="number" name="min_days_old" min="0" max="90" value="{{ $filters['min_days_old'] ?? $defaultMinDaysOld }}"
               class="w-24 rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2 text-sm"/>
         </div>
         <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Site</label>
            <select name="site" class="min-w-[10rem] rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2 text-sm">
               <option value="">Semua site</option>
               @foreach($filterOptions['sites'] ?? [] as $site)
               <option value="{{ $site }}" @selected(($filters['site'] ?? '') === $site)>{{ $site }}</option>
               @endforeach
            </select>
         </div>
         <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">SID</label>
            <input type="text" name="sid" value="{{ $filters['sid'] ?? '' }}" placeholder="U8WAP"
               class="w-28 rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2 text-sm font-mono uppercase"/>
         </div>
         <div class="min-w-[12rem] flex-1">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Cari nama / alasan</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama karyawan…"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2 text-sm"/>
         </div>
         <button type="submit" class="inline-flex items-center gap-1 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white hover:opacity-95">
            <span class="material-symbols-outlined text-base">filter_alt</span>
            Filter
         </button>
         @if($queryBase !== [])
         <a href="{{ route('auto-banned.inputasi.reconcile.index') }}" class="text-sm font-semibold text-on-surface-variant hover:text-primary">Reset</a>
         @endif
      </form>
   </div>

   <form method="POST" action="{{ route('auto-banned.inputasi.reconcile.store') }}" id="ab-reconcile-form">
      @csrf
      @foreach($queryBase as $key => $val)
      <input type="hidden" name="{{ $key }}" value="{{ $val }}"/>
      @endforeach

      <div class="border-b border-outline-variant/15 px-5 py-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
         <div>
            <label for="ab-reconcile-unban-mode" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
               Status log unban <span class="text-red-500">*</span>
            </label>
            <select id="ab-reconcile-unban-mode" name="unban_log_mode" required
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm text-on-surface focus:border-primary/30 focus:ring-2 focus:ring-primary/10">
               @foreach($unbanLogModes ?? AutoBannedReconcileUnbanLogMode::cases() as $mode)
               <option value="{{ $mode->value }}" @selected(old('unban_log_mode', AutoBannedReconcileUnbanLogMode::Success->value) === $mode->value)>
                  {{ $mode->shortLabel() }}
               </option>
               @endforeach
            </select>
            <p id="ab-reconcile-mode-hint" class="mt-1.5 text-[11px] text-on-surface-variant leading-relaxed"></p>
         </div>
         <div>
            <label for="ab-reconcile-alasan" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
               Ringkasan pengajuan (untuk semua terpilih)
            </label>
            <textarea id="ab-reconcile-alasan" name="alasan_pengajuan" rows="2" maxlength="2000"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm"
               placeholder="{{ $defaultAlasan }}">{{ old('alasan_pengajuan', $defaultAlasan) }}</textarea>
         </div>
         <div id="ab-reconcile-unban-at-wrap">
            <label for="ab-reconcile-unban-at" class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
               <span id="ab-reconcile-unban-at-label">Waktu unban selesai (opsional)</span>
            </label>
            <input type="datetime-local" id="ab-reconcile-unban-at" name="unban_completed_at" value="{{ old('unban_completed_at') }}"
               class="w-full rounded-xl border border-outline-variant/25 bg-[#f8fafc] px-3 py-2.5 text-sm"/>
            <p id="ab-reconcile-unban-at-hint" class="mt-1 text-[11px] text-on-surface-variant">Kosongkan untuk memakai waktu sekarang.</p>
         </div>
      </div>

      @if($errors->any())
      <div class="mx-5 mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
         <ul class="list-disc pl-4 space-y-1">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
         </ul>
      </div>
      @endif

      <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 border-b border-outline-variant/10 bg-[#fafbfc]">
         <div class="text-sm text-on-surface-variant">
            <strong class="text-on-background">{{ $gapRows->count() }}</strong> riwayat gap ditemukan
            @if(($filters['min_days_old'] ?? 0) > 0)
            <span class="text-xs">(filter_date &le; H-{{ (int) $filters['min_days_old'] }})</span>
            @endif
         </div>
         <div class="flex items-center gap-2">
            <button type="button" id="ab-reconcile-select-all" class="text-xs font-bold text-primary hover:underline">Pilih semua</button>
            <span class="text-on-surface-variant/30">|</span>
            <button type="button" id="ab-reconcile-clear-all" class="text-xs font-semibold text-on-surface-variant hover:underline">Bersihkan</button>
            <button type="submit" id="ab-reconcile-submit"
               class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:opacity-95 disabled:opacity-50"
               disabled>
               <span class="material-symbols-outlined text-base">sync</span>
               Rekonsiliasi terpilih
            </button>
         </div>
      </div>

      <div class="overflow-x-auto">
         <table class="w-full text-sm border-collapse">
            <thead>
               <tr class="bg-[#f8fafc] text-[10px] uppercase tracking-wider text-on-surface-variant">
                  <th class="border-b border-outline-variant/15 px-4 py-3 w-10"></th>
                  <th class="border-b border-outline-variant/15 px-4 py-3 text-left">Tanggal / SID</th>
                  <th class="border-b border-outline-variant/15 px-4 py-3 text-left">Karyawan</th>
                  <th class="border-b border-outline-variant/15 px-4 py-3 text-left">Site</th>
                  <th class="border-b border-outline-variant/15 px-4 py-3 text-left">Alasan</th>
                  <th class="border-b border-outline-variant/15 px-4 py-3 text-left">Banned at</th>
               </tr>
            </thead>
            <tbody>
               @forelse($gapRows as $row)
               @php
                  $canReconcile = $row->scr_daily_banned_id !== null;
                  $hoursSince = $row->completed_at ? (int) $row->completed_at->diffInHours(now()) : null;
               @endphp
               <tr class="hover:bg-[#fafbfc] {{ !$canReconcile ? 'opacity-60' : '' }}">
                  <td class="border-b border-outline-variant/10 px-4 py-3 align-top">
                     @if($canReconcile)
                     <input type="checkbox" name="ban_log_ids[]" value="{{ $row->id }}" class="ab-reconcile-check rounded border-outline-variant/40 text-primary focus:ring-primary/20"/>
                     @else
                     <span class="text-[10px] text-red-600" title="Tidak ada scr_daily_banned_id">—</span>
                     @endif
                  </td>
                  <td class="border-b border-outline-variant/10 px-4 py-3 align-top">
                     <div class="font-semibold">{{ $row->filter_date?->format('d M Y') ?? '—' }}</div>
                     <div class="font-mono text-xs font-bold text-primary">{{ $row->sid }}</div>
                     <div class="text-[10px] text-on-surface-variant">#{{ $row->id }} · SCR {{ $row->scr_daily_banned_id ?? '—' }}</div>
                  </td>
                  <td class="border-b border-outline-variant/10 px-4 py-3 align-top">
                     <div class="font-semibold">{{ $row->nama ?: '—' }}</div>
                     <div class="text-[10px] text-on-surface-variant">{{ $row->perusahaan ?: '—' }}</div>
                  </td>
                  <td class="border-b border-outline-variant/10 px-4 py-3 align-top">{{ $row->display_site ?: '—' }}</td>
                  <td class="border-b border-outline-variant/10 px-4 py-3 align-top text-xs leading-snug max-w-[14rem]">
                     @if($row->banned_status)
                     <div class="font-semibold text-[10px]">{{ $row->banned_status }}</div>
                     @endif
                     {{ \Illuminate\Support\Str::limit($row->banned_reason ?? '—', 80) }}
                  </td>
                  <td class="border-b border-outline-variant/10 px-4 py-3 align-top text-xs whitespace-nowrap">
                     {{ $row->completed_at?->format('d M Y H:i') ?? '—' }}
                     @if($hoursSince !== null)
                     <div class="text-[10px] text-amber-700">{{ $hoursSince }} jam lalu</div>
                     @endif
                  </td>
               </tr>
               @empty
               <tr>
                  <td colspan="6" class="px-4 py-10 text-center text-on-surface-variant">
                     Tidak ada gap log untuk filter ini. Semua data H-{{ (int) ($filters['min_days_old'] ?? $defaultMinDaysOld) }} sudah memiliki request/log unban, atau belum ada data banned.
                  </td>
               </tr>
               @endforelse
            </tbody>
         </table>
      </div>
   </form>
</div>

@endif

@push('scripts')
<script>
(function () {
   var checks = document.querySelectorAll('.ab-reconcile-check');
   var submitBtn = document.getElementById('ab-reconcile-submit');
   var form = document.getElementById('ab-reconcile-form');
   var modeSelect = document.getElementById('ab-reconcile-unban-mode');
   var modeHint = document.getElementById('ab-reconcile-mode-hint');
   var unbanAtWrap = document.getElementById('ab-reconcile-unban-at-wrap');
   var unbanAtLabel = document.getElementById('ab-reconcile-unban-at-label');
   var unbanAtHint = document.getElementById('ab-reconcile-unban-at-hint');

   var modeHints = {
      success: 'Pengajuan Disetujui + insert sid_unban_log berstatus SUCCESS. Pipeline akan tampil Sudah Unban.',
      belum_sukses: 'Hanya insert auto_banned_unban_requests berstatus Disetujui. Tidak ada log unban — pipeline Menunggu Automasi Unban.'
   };

   function updateModeUi() {
      if (!modeSelect) return;
      var mode = modeSelect.value;
      if (modeHint) {
         modeHint.textContent = modeHints[mode] || '';
      }
      if (unbanAtLabel) {
         unbanAtLabel.textContent = mode === 'belum_sukses'
            ? 'Waktu disetujui SOD (opsional)'
            : 'Waktu unban selesai (opsional)';
      }
      if (unbanAtHint) {
         unbanAtHint.textContent = mode === 'belum_sukses'
            ? 'Digunakan sebagai reviewed_at pengajuan. Kosongkan = sekarang.'
            : 'Digunakan sebagai completed_at log unban. Kosongkan = sekarang.';
      }
   }

   if (modeSelect) {
      modeSelect.addEventListener('change', updateModeUi);
      updateModeUi();
   }

   function updateSubmitState() {
      if (!submitBtn) return;
      var anyChecked = Array.prototype.some.call(checks, function (el) { return el.checked; });
      submitBtn.disabled = !anyChecked;
   }

   checks.forEach(function (el) {
      el.addEventListener('change', updateSubmitState);
   });

   var selectAll = document.getElementById('ab-reconcile-select-all');
   var clearAll = document.getElementById('ab-reconcile-clear-all');

   if (selectAll) {
      selectAll.addEventListener('click', function () {
         checks.forEach(function (el) { el.checked = true; });
         updateSubmitState();
      });
   }

   if (clearAll) {
      clearAll.addEventListener('click', function () {
         checks.forEach(function (el) { el.checked = false; });
         updateSubmitState();
      });
   }

   if (form) {
      form.addEventListener('submit', function (e) {
         var selected = Array.prototype.filter.call(checks, function (el) { return el.checked; });
         if (!selected.length) {
            e.preventDefault();
            return;
         }
         var mode = modeSelect ? modeSelect.value : 'success';
         var modeLabel = mode === 'belum_sukses'
            ? 'pengajuan Disetujui saja (tanpa log unban)'
            : 'pengajuan Disetujui + log unban SUCCESS';
         if (!confirm('Rekonsiliasi ' + selected.length + ' riwayat?\n\nMode: ' + modeLabel + '.')) {
            e.preventDefault();
         }
      });
   }

   updateSubmitState();
})();
</script>
@endpush
@endsection
