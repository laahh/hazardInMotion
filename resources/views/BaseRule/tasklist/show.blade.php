<!DOCTYPE html>
<html class="light" lang="id">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title>Tasklist — Daily Monitoring</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { primary: '#0f766e', 'on-background': '#1f2937', 'on-surface-variant': '#64748b' },
          fontFamily: { body: ['Poppins'] },
        },
      },
    }
  </script>
</head>
<body class="bg-[#f1f5f9] font-body text-slate-700 min-h-screen">
@php
  $statusTone = [
    'open' => 'bg-slate-100 text-slate-700',
    'submitted' => 'bg-amber-100 text-amber-800',
    'rejected' => 'bg-red-100 text-red-800',
    'approved' => 'bg-emerald-100 text-emerald-800',
  ];
  $isClosed = $tasklist->status === 'closed';
  $submittableCount = $items->filter(fn ($item) => in_array($item->status, ['open', 'rejected'], true) && ! $isClosed)->count();
  $oldItems = collect(old('items', []))->map(fn ($v) => (int) $v);
  $programGroups = $items->groupBy(function ($item) {
      $key = trim((string) ($item->program_key ?? ''));
      if ($key !== '') {
          return $key;
      }

      return 'title:'.trim((string) ($item->title ?? 'Lainnya'));
  });
@endphp
<div class="max-w-5xl mx-auto px-4 py-8">
  <div class="bg-white border border-teal-100 rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-teal-700 to-teal-900 text-white px-6 py-5">
      <p class="text-[11px] uppercase tracking-widest font-bold opacity-90">Tasklist Monitoring &amp; Intervensi</p>
      <h1 class="text-xl font-bold mt-1">{{ $tasklist->site ?: 'Semua Site' }} · {{ $tasklist->perusahaan }}</h1>
      <p class="text-sm mt-1 opacity-90">
        Batch {{ optional($tasklist->batch_slot)->format('d/m/Y H:i') }} ·
        Status tasklist: <strong>{{ strtoupper($tasklist->status) }}</strong>
      </p>
    </div>

    <div class="p-6 space-y-5">
      @if(session('success'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
      @endif
      @if($errors->any())
        <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
          <ul class="list-disc pl-4 space-y-1">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @if($isClosed)
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 px-4 py-3 text-sm font-semibold">
          Tasklist sudah closed — semua item telah di-ACC HSE.
        </div>
      @endif

      <form method="POST" action="{{ route('hsecm.tasklist.submit', ['token' => $tasklist->token]) }}" enctype="multipart/form-data" class="space-y-5" id="hsecm-tasklist-form">
        @csrf
        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Nama pengirim</label>
            <input type="text" name="submitted_by_name" value="{{ old('submitted_by_name') }}" required
                   class="w-full rounded-xl border-slate-200 text-sm" placeholder="Nama PJO / PIC"
                   @disabled($isClosed) />
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Catatan perbaikan</label>
            <textarea name="remediation_notes" rows="2" required class="w-full rounded-xl border-slate-200 text-sm"
                      placeholder="Jelaskan tindakan perbaikan untuk semua item yang dipilih..."
                      @disabled($isClosed)>{{ old('remediation_notes') }}</textarea>
          </div>
        </div>

        @if(! $isClosed && $submittableCount > 0)
          <div class="rounded-xl border border-teal-100 bg-teal-50/60 px-4 py-4 space-y-3">
            <div>
              <label class="block text-xs font-bold uppercase tracking-wide text-teal-900 mb-1">Evidence (1 file untuk semua item terpilih)</label>
              <input type="file" name="evidence_shared" required
                     class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-700 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                     accept=".jpg,.jpeg,.png,.pdf,.webp,.doc,.docx,.xls,.xlsx" />
              <p class="text-[11px] text-teal-800/80 mt-1">
                Satu file evidence akan dipakai untuk semua gap yang masih open/rejected yang Anda centang
                ({{ $submittableCount }} item siap submit). Maks. 10 MB.
              </p>
            </div>
          </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex flex-wrap items-center gap-3">
            @if(! $isClosed && $submittableCount > 0)
              <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 cursor-pointer">
                <input type="checkbox" id="hsecm-select-all" class="rounded border-slate-300 text-teal-700" checked />
                Pilih semua item
              </label>
            @endif
            <span class="text-xs text-slate-500">{{ $programGroups->count() }} program · {{ $items->count() }} item</span>
          </div>
          <div class="flex items-center gap-2 text-xs">
            <button type="button" id="hsecm-expand-all" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 font-semibold text-slate-600">
              Lihat semua
            </button>
            <button type="button" id="hsecm-collapse-all" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 font-semibold text-slate-600">
              Sembunyikan semua
            </button>
          </div>
        </div>

        <div class="space-y-3" id="hsecm-program-groups">
          @forelse($programGroups as $groupKey => $groupItems)
            @php
              $first = $groupItems->first();
              $groupTitle = (string) ($first->title ?? 'Program');
              $groupHint = (string) ($first->action_hint ?? '');
              $groupSubmittable = $groupItems->filter(fn ($item) => in_array($item->status, ['open', 'rejected'], true) && ! $isClosed);
              $groupOpen = $groupItems->where('status', 'open')->count();
              $groupRejected = $groupItems->where('status', 'rejected')->count();
              $groupSubmitted = $groupItems->where('status', 'submitted')->count();
              $groupApproved = $groupItems->where('status', 'approved')->count();
              $groupRepeat = $groupItems->filter(fn ($item) => (int) ($item->previous_recurrence_count ?? 0) > 0)->count();
              $panelId = 'hsecm-group-'.md5((string) $groupKey);
            @endphp
            <div class="border border-slate-200 rounded-xl overflow-hidden hsecm-program-group" data-group="{{ $panelId }}">
              <div class="bg-slate-50 px-4 py-3 flex items-start gap-3">
                @if(! $isClosed && $groupSubmittable->isNotEmpty())
                  <label class="inline-flex items-center pt-1 cursor-pointer shrink-0" title="Pilih semua di program ini" onclick="event.stopPropagation()">
                    <input type="checkbox" class="hsecm-group-check rounded border-slate-300 text-teal-700" data-group-check="{{ $panelId }}" />
                  </label>
                @endif
                <button type="button"
                        class="hsecm-group-toggle flex-1 min-w-0 text-left flex items-start gap-3"
                        data-target="{{ $panelId }}"
                        aria-expanded="false"
                        aria-controls="{{ $panelId }}-body">
                  <span class="hsecm-chevron mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white border border-slate-200 text-slate-500 transition-transform">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block font-bold text-slate-800">{{ $groupTitle }}</span>
                    @if($groupHint !== '')
                      <span class="block text-xs text-slate-500 mt-0.5">{{ $groupHint }}</span>
                    @endif
                    <span class="mt-2 flex flex-wrap gap-1.5 text-[11px]">
                      <span class="inline-flex px-2 py-0.5 rounded-full bg-white border border-slate-200 text-slate-600 font-semibold">{{ $groupItems->count() }} item</span>
                      @if($groupOpen > 0)<span class="inline-flex px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-semibold">{{ $groupOpen }} open</span>@endif
                      @if($groupRejected > 0)<span class="inline-flex px-2 py-0.5 rounded-full bg-red-100 text-red-800 font-semibold">{{ $groupRejected }} rejected</span>@endif
                      @if($groupSubmitted > 0)<span class="inline-flex px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-semibold">{{ $groupSubmitted }} submitted</span>@endif
                      @if($groupApproved > 0)<span class="inline-flex px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-semibold">{{ $groupApproved }} approved</span>@endif
                      @if($groupRepeat > 0)<span class="inline-flex px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 font-semibold">{{ $groupRepeat }} berulang</span>@endif
                    </span>
                  </span>
                  <span class="hsecm-toggle-label shrink-0 self-center ml-auto pl-3 text-xs font-bold text-teal-700 whitespace-nowrap">Lihat</span>
                </button>
              </div>

              <div id="{{ $panelId }}-body" class="hsecm-group-body hidden border-t border-slate-100">
                <div class="overflow-x-auto">
                  <table class="min-w-full text-sm">
                    <thead class="bg-white text-xs uppercase tracking-wide text-slate-500">
                      <tr>
                        <th class="px-3 py-2 text-left w-14">Pilih</th>
                        <th class="px-3 py-2 text-left">Item</th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">Perulangan</th>
                        <th class="px-3 py-2 text-left">Status</th>
                        <th class="px-3 py-2 text-left">Evidence terakhir</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      @foreach($groupItems as $item)
                        @php
                          $canSubmit = in_array($item->status, ['open', 'rejected'], true) && ! $isClosed;
                          $prevCount = (int) ($item->previous_recurrence_count ?? 0);
                        @endphp
                        <tr class="align-top">
                          <td class="px-3 py-3">
                            @if($canSubmit)
                              <input type="checkbox" name="items[]" value="{{ $item->id }}"
                                     class="hsecm-item-check rounded border-slate-300 text-teal-700"
                                     data-group-item="{{ $panelId }}"
                                     @checked($oldItems->contains($item->id)) />
                            @else
                              <span class="text-slate-300">—</span>
                            @endif
                          </td>
                          <td class="px-3 py-3">
                            <div class="font-medium text-slate-800">{{ $item->value_label ?: $item->business_key }}</div>
                            @if($item->status === 'rejected' && $item->rejection_reason)
                              <div class="mt-2 text-xs text-red-700 bg-red-50 border border-red-100 rounded-lg px-2 py-1">
                                Ditolak: {{ $item->rejection_reason }}
                              </div>
                            @endif
                            @if($item->remediation_notes)
                              <div class="mt-2 text-xs text-slate-500">Catatan terakhir: {{ $item->remediation_notes }}</div>
                            @endif
                          </td>
                          <td class="px-3 py-3 whitespace-nowrap">
                            @if($prevCount > 0)
                              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900" title="Jumlah slot/hari sebelumnya yang masih muncul sebagai gap">
                                {{ $prevCount }}× sebelumnya
                              </span>
                            @else
                              <span class="text-xs text-slate-400" title="Belum muncul di hari/slot sebelumnya">Baru</span>
                            @endif
                          </td>
                          <td class="px-3 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold {{ $statusTone[$item->status] ?? 'bg-slate-100' }}">
                              {{ strtoupper($item->status) }}
                            </span>
                          </td>
                          <td class="px-3 py-3 min-w-[12rem]">
                            @forelse($item->evidences->sortByDesc('id')->take(2) as $ev)
                              <a href="{{ $ev->publicUrl() }}" target="_blank" class="block text-xs text-teal-700 underline">
                                {{ $ev->original_name }} (batch {{ $ev->submission_batch }})
                              </a>
                            @empty
                              <span class="text-xs text-slate-400">Belum ada evidence</span>
                            @endforelse
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          @empty
            <div class="rounded-xl border border-slate-100 px-4 py-8 text-center text-slate-500 text-sm">Tidak ada item.</div>
          @endforelse
        </div>

        @if(! $isClosed && $submittableCount > 0)
          <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Centang item yang ingin di-submit, lalu upload 1 evidence di atas.</p>
            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-teal-700 text-white text-sm font-bold hover:bg-teal-800">
              Submit semua item terpilih
            </button>
          </div>
        @endif
      </form>
    </div>
  </div>
</div>
<script>
  (function () {
    const setGroupOpen = (groupEl, open) => {
      const body = groupEl.querySelector('.hsecm-group-body');
      const toggle = groupEl.querySelector('.hsecm-group-toggle');
      const chevron = groupEl.querySelector('.hsecm-chevron');
      const label = groupEl.querySelector('.hsecm-toggle-label');
      if (!body || !toggle) return;
      body.classList.toggle('hidden', !open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (chevron) chevron.classList.toggle('rotate-180', open);
      if (label) label.textContent = open ? 'Sembunyikan' : 'Lihat';
    };

    document.querySelectorAll('.hsecm-group-toggle').forEach((btn) => {
      btn.addEventListener('click', () => {
        const groupEl = btn.closest('.hsecm-program-group');
        if (!groupEl) return;
        const isOpen = btn.getAttribute('aria-expanded') === 'true';
        setGroupOpen(groupEl, !isOpen);
      });
    });

    const expandAllBtn = document.getElementById('hsecm-expand-all');
    const collapseAllBtn = document.getElementById('hsecm-collapse-all');
    if (expandAllBtn) {
      expandAllBtn.addEventListener('click', () => {
        document.querySelectorAll('.hsecm-program-group').forEach((el) => setGroupOpen(el, true));
      });
    }
    if (collapseAllBtn) {
      collapseAllBtn.addEventListener('click', () => {
        document.querySelectorAll('.hsecm-program-group').forEach((el) => setGroupOpen(el, false));
      });
    }

    const selectAll = document.getElementById('hsecm-select-all');
    const itemChecks = () => Array.from(document.querySelectorAll('.hsecm-item-check'));

    const syncGroupCheck = (groupId) => {
      const groupCheck = document.querySelector('.hsecm-group-check[data-group-check="' + groupId + '"]');
      const items = Array.from(document.querySelectorAll('.hsecm-item-check[data-group-item="' + groupId + '"]'));
      if (!groupCheck || items.length === 0) return;
      groupCheck.checked = items.every((el) => el.checked);
      groupCheck.indeterminate = items.some((el) => el.checked) && !groupCheck.checked;
    };

    const syncSelectAll = () => {
      const checks = itemChecks();
      if (!selectAll || checks.length === 0) return;
      selectAll.checked = checks.every((el) => el.checked);
      selectAll.indeterminate = checks.some((el) => el.checked) && !selectAll.checked;
      document.querySelectorAll('.hsecm-group-check').forEach((el) => {
        syncGroupCheck(el.getAttribute('data-group-check'));
      });
    };

    if (selectAll) {
      selectAll.addEventListener('change', () => {
        itemChecks().forEach((el) => { el.checked = selectAll.checked; });
        document.querySelectorAll('.hsecm-group-check').forEach((el) => {
          el.checked = selectAll.checked;
          el.indeterminate = false;
        });
      });
    }

    document.querySelectorAll('.hsecm-group-check').forEach((groupCheck) => {
      groupCheck.addEventListener('change', () => {
        const groupId = groupCheck.getAttribute('data-group-check');
        document.querySelectorAll('.hsecm-item-check[data-group-item="' + groupId + '"]').forEach((el) => {
          el.checked = groupCheck.checked;
        });
        syncSelectAll();
      });
    });

    itemChecks().forEach((el) => {
      el.addEventListener('change', syncSelectAll);
    });

    @if($oldItems->isEmpty())
      itemChecks().forEach((el) => { el.checked = true; });
      if (selectAll) selectAll.checked = true;
      document.querySelectorAll('.hsecm-group-check').forEach((el) => {
        el.checked = true;
        el.indeterminate = false;
      });
    @else
      syncSelectAll();
    @endif

    // Default: collapse semua program (ringkas). User bisa "Lihat" per program.
    document.querySelectorAll('.hsecm-program-group').forEach((el) => setGroupOpen(el, false));
  })();
</script>
</body>
</html>
