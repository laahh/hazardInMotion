@extends('BaseRule.layouts.app')

@section('title', 'Gap Evaluasi')

@push('head')
@include('BaseRule.partials.styles')
<style>
   .gap-eval-card { min-height: 6.5rem; }
   .gap-eval-table { max-height: 22rem; overflow: auto; }
   .gap-program-strip {
      scroll-margin-top: 5rem;
      transition: box-shadow 0.2s ease, border-color 0.2s ease;
   }
   .gap-program-strip.is-active {
      border-color: rgba(15, 118, 110, 0.35);
      box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.12);
   }
   .gap-nav-chip.is-active {
      background: rgba(15, 118, 110, 0.14);
      border-color: rgba(15, 118, 110, 0.35);
      color: #0f766e;
   }
</style>
@endpush

@section('content')
@include('BaseRule.partials.page-header', [
   'title' => 'Gap Evaluasi',
   'subtitle' => 'Ringkasan gap, perulangan, dan perbaikan per parameter Identifikasi & Intervensi',
   'breadcrumb' => 'Gap Evaluasi',
])

@include('BaseRule.partials.filter-bar', [
   'actionRoute' => 'hsecm.gap-evaluasi',
   'filters' => $filters,
   'filterOptions' => $filterOptions,
   'showCompany' => true,
   'showSearch' => false,
   'showDate' => true,
])

@if($periodLabel ?? null)
<div class="mb-6 flex flex-wrap items-center gap-2 text-sm text-on-surface-variant">
   <span class="material-symbols-outlined text-base text-primary">compare_arrows</span>
   <span class="font-semibold text-on-background">Periode:</span>
   <span>{{ $periodLabel }}</span>
   @if(!empty($slotsD))
      <span class="hsecm-badge ml-1">{{ count($slotsD) }} slot di range</span>
   @endif
   @if(!empty($rangeDates))
      <span class="hsecm-badge">{{ count($rangeDates) }} hari scrape</span>
   @endif
   @if(!empty($slotsPrev))
      <span class="hsecm-badge">{{ count($slotsPrev) }} slot hari pembanding</span>
   @endif
</div>
@endif

{{-- Navigasi cepat ke parameter --}}
@if(!empty($programs))
<div class="mb-6 flex flex-wrap gap-2">
   @foreach($programs as $navProgram)
   <a href="#gap-program-{{ $navProgram['key'] }}"
      class="hsecm-badge gap-nav-chip hover:bg-primary/10 transition"
      data-gap-program-key="{{ $navProgram['key'] }}">
      {{ $navProgram['label'] }}
   </a>
   @endforeach
</div>
@endif

<div class="space-y-8 mb-10" id="gap-program-sections">
   @foreach($programs ?? [] as $program)
      @include('BaseRule.gap-evaluasi.partials._section', ['program' => $program])
   @endforeach
</div>

<details class="mb-8 group">
   <summary class="cursor-pointer list-none flex items-center gap-2 text-sm font-semibold text-on-background mb-4">
      <span class="material-symbols-outlined text-primary transition group-open:rotate-90">chevron_right</span>
      Detail klasifikasi scrape & tasklist
   </summary>

   @include('BaseRule.gap-evaluasi.partials._cards-scrape', ['scrape' => $scrape])
   @include('BaseRule.gap-evaluasi.partials._cards-tasklist', ['tasklist' => $tasklist])

   <div class="space-y-8 mt-6">
      @include('BaseRule.gap-evaluasi.partials._table', [
         'title' => 'Tidak ada perbaikan — masih berulang (tetap)',
         'hint' => 'Ada di hari pembanding dan masih muncul di hari evaluasi',
         'rows' => $scrape['details']['tetap'] ?? [],
         'truncated' => (int) ($scrape['truncated']['tetap'] ?? 0),
         'tone' => 'danger',
      ])
      @include('BaseRule.gap-evaluasi.partials._table', [
         'title' => 'Perbaikan scrape (hilang)',
         'hint' => 'Ada di hari pembanding, tidak muncul lagi di hari evaluasi',
         'rows' => $scrape['details']['hilang'] ?? [],
         'truncated' => (int) ($scrape['truncated']['hilang'] ?? 0),
         'tone' => 'success',
      ])
      @include('BaseRule.gap-evaluasi.partials._table', [
         'title' => 'Gap baru',
         'hint' => 'Muncul di hari evaluasi, belum ada di hari pembanding',
         'rows' => $scrape['details']['baru'] ?? [],
         'truncated' => (int) ($scrape['truncated']['baru'] ?? 0),
         'tone' => 'warning',
      ])
      @include('BaseRule.gap-evaluasi.partials._table', [
         'title' => 'Kembali muncul (re-open)',
         'hint' => 'Muncul lagi setelah pernah absen / improve sebelumnya',
         'rows' => $scrape['details']['kembali'] ?? [],
         'truncated' => (int) ($scrape['truncated']['kembali'] ?? 0),
         'tone' => 'warning',
      ])

      @include('BaseRule.gap-evaluasi.partials._table-tasklist', [
         'title' => 'Ditindaklanjuti + perbaikan scrape',
         'hint' => 'Sudah submit/ACC tasklist dan key tidak ada di scrape hari evaluasi',
         'rows' => $tasklist['details']['tindaklanjut_berhasil'] ?? [],
         'tone' => 'success',
      ])
      @include('BaseRule.gap-evaluasi.partials._table-tasklist', [
         'title' => 'Ditindaklanjuti + masih gap',
         'hint' => 'Sudah submit/ACC tetapi key masih muncul di scrape hari evaluasi',
         'rows' => $tasklist['details']['tindaklanjut_belum_efektif'] ?? [],
         'tone' => 'danger',
      ])
      @include('BaseRule.gap-evaluasi.partials._table-tasklist', [
         'title' => 'Belum ditindaklanjuti + masih gap',
         'hint' => 'Item tasklist masih open/rejected dan gap masih ada di scrape',
         'rows' => $tasklist['details']['belum_tindaklanjut_masih_gap'] ?? [],
         'tone' => 'warning',
      ])
   </div>
</details>

{{-- Modal detail gap per Site/Perusahaan --}}
<div id="gap-eval-modal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
   <div class="absolute inset-0 bg-slate-900/40" data-gap-modal-close></div>
   <div class="relative mx-auto mt-10 mb-10 w-[min(56rem,94vw)] max-h-[85vh] flex flex-col rounded-2xl bg-white shadow-xl overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 flex items-start justify-between gap-3">
         <div class="min-w-0">
            <p id="gap-eval-modal-metric" class="text-[10px] font-bold uppercase tracking-wider text-teal-700"></p>
            <h3 id="gap-eval-modal-title" class="font-headline font-bold text-lg text-on-background truncate"></h3>
            <p id="gap-eval-modal-subtitle" class="text-xs text-on-surface-variant mt-0.5"></p>
         </div>
         <button type="button" class="rounded-lg p-1.5 text-on-surface-variant hover:bg-slate-100" data-gap-modal-close aria-label="Tutup">
            <span class="material-symbols-outlined">close</span>
         </button>
      </div>
      <div class="overflow-auto flex-1">
         <table class="hsecm-table w-full text-sm">
            <thead>
               <tr>
                  <th class="px-4 py-2 text-left">Status</th>
                  <th class="px-4 py-2 text-left">Item</th>
                  <th class="px-4 py-2 text-left">Site</th>
                  <th class="px-4 py-2 text-left">Perusahaan</th>
                  <th class="px-4 py-2 text-center">Streak</th>
               </tr>
            </thead>
            <tbody id="gap-eval-modal-body"></tbody>
         </table>
      </div>
      <div class="px-5 py-3 border-t border-slate-100 text-xs text-on-surface-variant" id="gap-eval-modal-footer"></div>
   </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
   const chips = document.querySelectorAll('.gap-nav-chip[data-gap-program-key]');
   const strips = document.querySelectorAll('.gap-program-strip');

   function activate(key) {
      chips.forEach(function (chip) {
         chip.classList.toggle('is-active', chip.getAttribute('data-gap-program-key') === key);
      });
      strips.forEach(function (strip) {
         strip.classList.toggle('is-active', strip.getAttribute('data-program-key') === key);
      });
      const target = document.getElementById('gap-program-' + key);
      if (target) {
         target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
   }

   chips.forEach(function (chip) {
      chip.addEventListener('click', function (e) {
         e.preventDefault();
         activate(chip.getAttribute('data-gap-program-key'));
      });
   });

   if (chips.length) {
      chips[0].classList.add('is-active');
      const firstKey = chips[0].getAttribute('data-gap-program-key');
      strips.forEach(function (strip) {
         strip.classList.toggle('is-active', strip.getAttribute('data-program-key') === firstKey);
      });
   }

   const detailStore = {};
   document.querySelectorAll('.gap-eval-scope-json').forEach(function (el) {
      const programKey = el.getAttribute('data-program-key');
      try {
         detailStore[programKey] = JSON.parse(el.textContent || '{}');
      } catch (e) {
         detailStore[programKey] = {};
      }
   });

   const statusLabel = {
      tetap: 'Masih berulang',
      hilang: 'Sudah perbaikan',
      baru: 'Gap baru',
      kembali: 'Kembali muncul'
   };
   const statusBadge = {
      tetap: 'hsecm-badge--danger',
      hilang: 'hsecm-badge--success',
      baru: 'hsecm-badge--warning',
      kembali: 'hsecm-badge--warning'
   };

   const modal = document.getElementById('gap-eval-modal');
   const modalTitle = document.getElementById('gap-eval-modal-title');
   const modalMetric = document.getElementById('gap-eval-modal-metric');
   const modalSubtitle = document.getElementById('gap-eval-modal-subtitle');
   const modalBody = document.getElementById('gap-eval-modal-body');
   const modalFooter = document.getElementById('gap-eval-modal-footer');

   function escapeHtml(str) {
      return String(str ?? '')
         .replace(/&/g, '&amp;')
         .replace(/</g, '&lt;')
         .replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;');
   }

   function openModal(btn) {
      const programKey = btn.getAttribute('data-program-key');
      const programLabel = btn.getAttribute('data-program-label') || programKey;
      const scopeKey = btn.getAttribute('data-scope-key');
      const scopeTitle = btn.getAttribute('data-scope-title') || '';
      const metric = btn.getAttribute('data-metric');
      const metricLabel = btn.getAttribute('data-metric-label') || metric;
      const rows = (((detailStore[programKey] || {})[scopeKey] || {})[metric]) || [];

      modalMetric.textContent = metricLabel;
      modalTitle.textContent = programLabel;
      modalSubtitle.textContent = scopeTitle;

      if (!rows.length) {
         modalBody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-on-surface-variant">Tidak ada detail untuk ditampilkan.</td></tr>';
      } else {
         modalBody.innerHTML = rows.map(function (row) {
            const st = row.status || '';
            const badge = statusBadge[st] || '';
            const label = statusLabel[st] || st || '—';
            const streak = Number(row.day_streak || 0);
            const streakClass = streak >= 2 ? 'text-red-600' : '';
            return (
               '<tr class="border-t border-slate-50">' +
                  '<td class="px-4 py-2 whitespace-nowrap"><span class="hsecm-badge ' + badge + '">' + escapeHtml(label) + '</span></td>' +
                  '<td class="px-4 py-2"><div class="text-on-background">' + escapeHtml(row.value_label || '—') + '</div>' +
                     '<div class="text-[11px] text-on-surface-variant font-mono">' + escapeHtml((row.business_key || '').slice(0, 48)) + '</div></td>' +
                  '<td class="px-4 py-2 whitespace-nowrap">' + escapeHtml(row.site || '—') + '</td>' +
                  '<td class="px-4 py-2">' + escapeHtml(row.perusahaan || '—') + '</td>' +
                  '<td class="px-4 py-2 text-center font-semibold ' + streakClass + '">' + streak + '×</td>' +
               '</tr>'
            );
         }).join('');
      }

      modalFooter.textContent = rows.length + ' item ditampilkan' + (rows.length >= 100 ? ' (maks. 100 per sel)' : '');
      modal.classList.remove('hidden');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('overflow-hidden');
   }

   function closeModal() {
      modal.classList.add('hidden');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('overflow-hidden');
   }

   document.querySelectorAll('.gap-eval-open-modal').forEach(function (btn) {
      btn.addEventListener('click', function () {
         openModal(btn);
      });
   });

   modal.querySelectorAll('[data-gap-modal-close]').forEach(function (el) {
      el.addEventListener('click', closeModal);
   });

   document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
         closeModal();
      }
   });
});
</script>
@endpush
