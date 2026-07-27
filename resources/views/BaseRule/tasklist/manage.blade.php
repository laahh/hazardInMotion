@extends('BaseRule.layouts.app')

@section('title', 'Kelola Tasklist')

@push('head')
@include('BaseRule.partials.styles')
@endpush

@section('content')
@php
  $statusTone = [
    'open' => 'bg-slate-100 text-slate-700',
    'submitted' => 'bg-amber-100 text-amber-800',
    'rejected' => 'bg-red-100 text-red-800',
    'approved' => 'bg-emerald-100 text-emerald-800',
  ];
  $oldItems = collect(old('items', []))->map(fn ($v) => (int) $v);
  $programGroups = $items->groupBy(function ($item) {
      $key = trim((string) ($item->program_key ?? ''));
      if ($key !== '') {
          return $key;
      }

      return 'title:'.trim((string) ($item->title ?? 'Lainnya'));
  });
  $submittedCount = $items->where('status', 'submitted')->count();
@endphp

@include('BaseRule.partials.page-header', [
  'title' => 'Kelola Tasklist #'.$tasklist->id,
  'subtitle' => ($tasklist->site ?: 'Semua Site').' · '.$tasklist->perusahaan,
  'breadcrumb' => 'Tasklist Review',
])

@if(session('success'))
  <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif
@if($errors->any())
  <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
    <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
@endif

<div class="mb-4 hsecm-card rounded-2xl p-4 text-sm flex flex-wrap gap-4 justify-between">
  <div>
    <div>Status: <strong>{{ strtoupper($tasklist->status) }}</strong></div>
    <div>Batch: {{ optional($tasklist->batch_slot)->format('d/m/Y H:i') }}</div>
    <div>Escalate: #{{ $tasklist->escalate_count }}</div>
    <div class="text-xs text-on-surface-variant mt-1">{{ $programGroups->count() }} program · {{ $items->count() }} item · {{ $submittedCount }} menunggu ACC</div>
  </div>
  <div class="text-right">
    <div class="text-xs text-on-surface-variant">Link publik PJO</div>
    <a href="{{ $publicUrl }}" target="_blank" class="text-primary font-semibold text-xs break-all">{{ $publicUrl }}</a>
  </div>
</div>

@if($submittedCount > 0)
<div class="mb-4 hsecm-card rounded-2xl p-4 border border-amber-100 bg-amber-50/40">
  <div class="flex flex-wrap items-end gap-3">
    <div class="flex-1 min-w-[16rem]">
      <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Alasan tolak (wajib jika Tolak massal)</label>
      <textarea id="hsecm-bulk-reject-reason" rows="2" form="hsecm-bulk-reject-form"
                class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm bg-white"
                placeholder="Alasan penolakan untuk semua item terpilih...">{{ old('rejection_reason') }}</textarea>
    </div>
    <div class="flex flex-wrap gap-2">
      <button type="submit" form="hsecm-bulk-approve-form"
              class="inline-flex items-center px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700"
              onclick="return window.hsecmConfirmBulk('approve')">
        ACC item terpilih
      </button>
      <button type="submit" form="hsecm-bulk-reject-form"
              class="inline-flex items-center px-4 py-2.5 rounded-xl bg-red-600 text-white text-sm font-bold hover:bg-red-700"
              onclick="return window.hsecmConfirmBulk('reject')">
        Tolak item terpilih
      </button>
    </div>
  </div>
  <p class="text-[11px] text-on-surface-variant mt-2">Centang item berstatus <strong>submitted</strong>, lalu ACC atau Tolak sekaligus — tidak perlu satu-satu.</p>
</div>
@endif

<div class="mb-3 flex flex-wrap items-center justify-between gap-2">
  <div class="flex flex-wrap items-center gap-3">
    @if($submittedCount > 0)
      <label class="inline-flex items-center gap-2 text-sm font-semibold text-on-background cursor-pointer">
        <input type="checkbox" id="hsecm-select-all" class="rounded border-slate-300 text-teal-700" checked />
        Pilih semua submitted
      </label>
    @endif
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

<form id="hsecm-bulk-approve-form" method="POST" action="{{ route('hsecm.tasklist.approve-bulk', ['id' => $tasklist->id]) }}" class="hidden">
  @csrf
  <div id="hsecm-approve-items-holder"></div>
</form>
<form id="hsecm-bulk-reject-form" method="POST" action="{{ route('hsecm.tasklist.reject-bulk', ['id' => $tasklist->id]) }}" class="hidden">
  @csrf
  <div id="hsecm-reject-items-holder"></div>
</form>

<div class="space-y-3" id="hsecm-program-groups">
  @forelse($programGroups as $groupKey => $groupItems)
    @php
      $first = $groupItems->first();
      $groupTitle = (string) ($first->title ?? 'Program');
      $groupHint = (string) ($first->action_hint ?? '');
      $groupSubmitted = $groupItems->where('status', 'submitted');
      $groupOpen = $groupItems->where('status', 'open')->count();
      $groupRejected = $groupItems->where('status', 'rejected')->count();
      $groupApproved = $groupItems->where('status', 'approved')->count();
      $panelId = 'hsecm-mgr-'.md5((string) $groupKey);
    @endphp
    <div class="hsecm-card rounded-2xl overflow-hidden hsecm-program-group" data-group="{{ $panelId }}">
      <div class="bg-slate-50 px-4 py-3 flex items-start gap-3 border-b border-slate-100">
        @if($groupSubmitted->isNotEmpty())
          <label class="inline-flex items-center pt-1 cursor-pointer shrink-0" title="Pilih semua submitted di program ini" onclick="event.stopPropagation()">
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
            <span class="block font-bold text-on-background">{{ $groupTitle }}</span>
            @if($groupHint !== '')
              <span class="block text-xs text-on-surface-variant mt-0.5">{{ $groupHint }}</span>
            @endif
            <span class="mt-2 flex flex-wrap gap-1.5 text-[11px]">
              <span class="inline-flex px-2 py-0.5 rounded-full bg-white border border-slate-200 text-slate-600 font-semibold">{{ $groupItems->count() }} item</span>
              @if($groupSubmitted->count() > 0)<span class="inline-flex px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-semibold">{{ $groupSubmitted->count() }} submitted</span>@endif
              @if($groupOpen > 0)<span class="inline-flex px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-semibold">{{ $groupOpen }} open</span>@endif
              @if($groupRejected > 0)<span class="inline-flex px-2 py-0.5 rounded-full bg-red-100 text-red-800 font-semibold">{{ $groupRejected }} rejected</span>@endif
              @if($groupApproved > 0)<span class="inline-flex px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-semibold">{{ $groupApproved }} approved</span>@endif
            </span>
          </span>
          <span class="hsecm-toggle-label shrink-0 self-center ml-auto pl-3 text-xs font-bold text-primary whitespace-nowrap">Lihat</span>
        </button>
      </div>

      <div id="{{ $panelId }}-body" class="hsecm-group-body hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-white text-xs uppercase tracking-wide text-on-surface-variant">
              <tr>
                <th class="px-4 py-2 text-left w-14">Pilih</th>
                <th class="px-4 py-2 text-left">Item</th>
                <th class="px-4 py-2 text-left">Perulangan</th>
                <th class="px-4 py-2 text-left">Status</th>
                <th class="px-4 py-2 text-left">Submit</th>
                <th class="px-4 py-2 text-left">Evidence</th>
                <th class="px-4 py-2 text-left">Review</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              @foreach($groupItems as $item)
                @php
                  $canReview = $item->status === 'submitted';
                  $prevCount = (int) ($item->previous_recurrence_count ?? 0);
                @endphp
                <tr class="align-top">
                  <td class="px-4 py-3">
                    @if($canReview)
                      <input type="checkbox" value="{{ $item->id }}"
                             class="hsecm-item-check rounded border-slate-300 text-teal-700"
                             data-group-item="{{ $panelId }}"
                             @checked($oldItems->isEmpty() || $oldItems->contains($item->id)) />
                    @else
                      <span class="text-slate-300">—</span>
                    @endif
                  </td>
                  <td class="px-4 py-3">
                    <div class="font-medium text-on-background">{{ $item->value_label ?: $item->business_key }}</div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    @if($prevCount > 0)
                      <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900">{{ $prevCount }}× sebelumnya</span>
                    @else
                      <span class="text-xs text-on-surface-variant">Baru</span>
                    @endif
                  </td>
                  <td class="px-4 py-3">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold {{ $statusTone[$item->status] ?? 'bg-slate-100' }}">
                      {{ strtoupper($item->status) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-xs">
                    @if($item->submitted_at)
                      <div>{{ $item->submitted_by_name }}</div>
                      <div>{{ $item->submitted_at->format('d/m/Y H:i') }}</div>
                      <div class="mt-1 text-on-surface">{{ $item->remediation_notes }}</div>
                    @else
                      —
                    @endif
                    @if($item->rejection_reason)
                      <div class="mt-2 text-red-700">Tolak: {{ $item->rejection_reason }}</div>
                    @endif
                  </td>
                  <td class="px-4 py-3 text-xs">
                    @forelse($item->evidences as $ev)
                      <a href="{{ $ev->publicUrl() }}" target="_blank" class="block text-primary underline mb-1">{{ $ev->original_name }}</a>
                    @empty
                      —
                    @endforelse
                  </td>
                  <td class="px-4 py-3 text-xs">
                    @if($item->reviewed_at)
                      <div>{{ $item->reviewed_by_name }}</div>
                      <div class="text-on-surface-variant">{{ $item->reviewed_at->format('d/m/Y H:i') }}</div>
                    @elseif($canReview)
                      <span class="text-amber-700 font-semibold">Menunggu ACC</span>
                    @else
                      —
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @empty
    <div class="hsecm-card rounded-2xl px-4 py-8 text-center text-on-surface-variant text-sm">Tidak ada item.</div>
  @endforelse
</div>

<div class="mt-4">
  <a href="{{ route('hsecm.tasklist.index') }}" class="text-sm text-primary font-semibold">← Kembali ke daftar</a>
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
        setGroupOpen(groupEl, btn.getAttribute('aria-expanded') !== 'true');
      });
    });

    const expandAllBtn = document.getElementById('hsecm-expand-all');
    const collapseAllBtn = document.getElementById('hsecm-collapse-all');
    if (expandAllBtn) expandAllBtn.addEventListener('click', () => {
      document.querySelectorAll('.hsecm-program-group').forEach((el) => setGroupOpen(el, true));
    });
    if (collapseAllBtn) collapseAllBtn.addEventListener('click', () => {
      document.querySelectorAll('.hsecm-program-group').forEach((el) => setGroupOpen(el, false));
    });

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
      if (selectAll && checks.length > 0) {
        selectAll.checked = checks.every((el) => el.checked);
        selectAll.indeterminate = checks.some((el) => el.checked) && !selectAll.checked;
      }
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

    itemChecks().forEach((el) => el.addEventListener('change', syncSelectAll));

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

    document.querySelectorAll('.hsecm-program-group').forEach((el) => setGroupOpen(el, false));

    const fillItems = (holderId) => {
      const holder = document.getElementById(holderId);
      if (!holder) return 0;
      holder.innerHTML = '';
      const selected = itemChecks().filter((el) => el.checked);
      selected.forEach((el) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'items[]';
        input.value = el.value;
        holder.appendChild(input);
      });
      return selected.length;
    };

    window.hsecmConfirmBulk = function (action) {
      const count = fillItems(action === 'approve' ? 'hsecm-approve-items-holder' : 'hsecm-reject-items-holder');
      if (count === 0) {
        alert('Pilih minimal satu item submitted.');
        return false;
      }
      if (action === 'reject') {
        const reason = (document.getElementById('hsecm-bulk-reject-reason')?.value || '').trim();
        if (!reason) {
          alert('Isi alasan tolak terlebih dahulu.');
          return false;
        }
        return confirm('Tolak ' + count + ' item terpilih?');
      }
      return confirm('ACC ' + count + ' item terpilih?');
    };
  })();
</script>
@endsection
