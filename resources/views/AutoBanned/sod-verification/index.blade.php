@extends('AutoBanned.layouts.app')

@section('title', 'Verifikasi SOD — Pengajuan Treatment')

@push('head')
<style>
   .ab-sod { --ab-ease: cubic-bezier(0.4, 0, 0.2, 1); }
   .ab-fade-in { animation: abFadeUp 0.55s var(--ab-ease) both; }
   @keyframes abFadeUp {
      from { opacity: 0; transform: translateY(10px); }
      to   { opacity: 1; transform: translateY(0); }
   }
   .ab-surface-card {
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid rgba(57, 82, 188, 0.07);
      box-shadow: 0 1px 2px rgba(44, 47, 49, 0.04), 0 8px 24px -6px rgba(57, 82, 188, 0.08);
   }
   .ab-badge {
      display: inline-flex; align-items: center; border-radius: 0.375rem;
      padding: 0.125rem 0.5rem; font-size: 10px; font-weight: 600;
      border: 1px solid rgba(57, 82, 188, 0.12); background: rgba(57, 82, 188, 0.06); color: #3952bc;
   }
   .ab-badge--ok { border-color: rgba(16,185,129,.25); background: rgba(16,185,129,.08); color: #047857; }
   .ab-badge--wait { border-color: rgba(245,158,11,.3); background: rgba(245,158,11,.1); color: #b45309; }
   .ab-badge--muted { border-color: rgba(171,173,175,.35); background: #f1f5f9; color: #595c5e; }
   .ab-sheet-table { border-collapse: collapse; font-size: 11px; width: 100%; }
   .ab-sheet-table thead th {
      background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.5rem 0.625rem;
      font-size: 10px; font-weight: 600; color: #475569; white-space: nowrap;
   }
   .ab-sheet-table tbody td { border: 1px solid #e2e8f0; padding: 0.5rem 0.625rem; color: #1e293b; vertical-align: top; }
   .ab-sheet-table tbody tr:hover td { background: #f8fafc; }
   .ab-tab-btn { transition: all .2s var(--ab-ease); }
   .ab-tab-btn[aria-selected="true"] { background: #fff; color: #3952bc; box-shadow: 0 1px 3px rgba(57,82,188,.1); }
   .ab-btn-approve { background: #059669; color: #fff; }
   .ab-btn-reject { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
   .ab-btn-sm {
      display: inline-flex; align-items: center; gap: .25rem; border-radius: .5rem;
      padding: .35rem .65rem; font-size: 10px; font-weight: 700;
   }
   .ab-sod-actions { display: flex; flex-wrap: wrap; gap: .35rem; }
   .ab-sod-swal-popup { width: 32rem; max-width: calc(100vw - 2rem); }
   .ab-sod-swal-html { text-align: left; }
   .ab-sod-swal-meta {
      text-align: center; font-size: .875rem; color: #475569; margin-bottom: .85rem; line-height: 1.45;
   }
   .ab-sod-swal-box {
      border-radius: .75rem; padding: .75rem .85rem; margin-bottom: .65rem;
   }
   .ab-sod-swal-box--banned {
      background: #fef2f2; border: 1px solid #fecaca;
   }
   .ab-sod-swal-box--treatment {
      background: rgba(57, 82, 188, 0.06); border: 1px solid rgba(57, 82, 188, 0.14);
   }
   .ab-sod-swal-label {
      font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; margin-bottom: .35rem;
   }
   .ab-sod-swal-box--banned .ab-sod-swal-label { color: #991b1b; }
   .ab-sod-swal-box--treatment .ab-sod-swal-label { color: #3952bc; }
   .ab-sod-swal-text {
      font-size: 12px; color: #334155; line-height: 1.55; white-space: pre-wrap; word-break: break-word;
   }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endpush

@section('content')
@php
   $periodLabel = trim(($period['week'] ?? '').' · '.($period['year'] ?? ''), ' ·');
   $summaryCounts = $summaryCounts ?? ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0, 'withEvidence' => 0];
   $submittedRequests = $submittedRequests ?? collect();
   $currentStatus = $filters['status'] ?? 'pending';
   $statusTabs = [
      'pending' => 'Menunggu Review',
      'approved' => 'Disetujui',
      'rejected' => 'Ditolak',
      'all' => 'Semua',
   ];
   $queryBase = array_filter([
      'site' => $filters['site'] ?? '',
      'week' => $filters['week'] ?? '',
      'year' => $filters['year'] ?? '',
      'perusahaan' => $filters['perusahaan'] ?? '',
      'q' => $filters['q'] ?? '',
   ], fn ($v) => $v !== '' && $v !== null);

   // Tab "Semua": seluruh data lintas periode & status, disaring/diekspor
   // DataTables di browser. Pilihan filter kolom diambil dari datanya sendiri.
   $modeSemua = $modeSemua ?? false;
   $batasSemua = $batasSemua ?? 10000;
   $mingguLabel = fn ($row): string => trim(($row->week ?? '').' · '.($row->iso_year ?? ''), ' ·');
   $opsiKolom = $modeSemua ? [
      'status' => $submittedRequests->map(fn ($r) => $r->status->label())->unique()->sort()->values(),
      'perusahaan' => $submittedRequests->pluck('perusahaan')->filter()->unique()->sort()->values(),
      'site' => $submittedRequests->pluck('site_dedicated')->filter()->unique()->sort()->values(),
      'minggu' => $submittedRequests->map($mingguLabel)->filter()->unique()->sortDesc()->values(),
   ] : [];
@endphp

<div class="ab-sod -mt-2 space-y-7">
   <section class="pb-6 border-b border-outline-variant/30">
      <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
         <div class="min-w-0 ab-fade-in">
            <nav class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.08em] text-on-surface-variant mb-2.5">
               <span>Auto Banned</span>
               <span class="material-symbols-outlined text-[13px] opacity-60">chevron_right</span>
               <span class="text-primary">Verifikasi SOD</span>
            </nav>
            <h1 class="font-headline font-extrabold text-3xl text-on-background tracking-tight">Verifikasi Pengajuan Treatment</h1>
            <p class="text-sm text-on-surface-variant mt-2 max-w-2xl">
               Daftar pengajuan unbanned / evidence treatment yang sudah disubmit dari form publik atau inputasi.
               @if($periodLabel !== '') Periode filter: <strong>{{ $periodLabel }}</strong>.@endif
            </p>
         </div>
         <div class="shrink-0">
            @if($modeSemua)
            <p class="text-xs text-on-surface-variant max-w-xs">
               Tab <strong>Semua</strong> menampilkan seluruh pengajuan lintas minggu &amp; status.
               Saring lewat filter di atas tabel.
            </p>
            @else
            @include('AutoBanned.partials.filter-bar', [
               'filters' => $filters,
               'filterOptions' => $filterOptions,
               'filterRoute' => $filterRoute,
               'preserveParams' => ['status' => in_array($currentStatus, ['pending', 'approved', 'rejected', 'all'], true) ? $currentStatus : 'pending'],
            ])
            @endif
         </div>
      </div>
   </section>

   @if(!$tableAvailable)
   <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
      Tabel <code class="text-xs">auto_banned_unban_requests</code> belum tersedia. Jalankan migration.
   </div>
   @else
   <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div class="ab-surface-card rounded-2xl p-4">
         <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Menunggu Review</p>
         <p class="mt-1 font-headline text-3xl font-bold text-amber-600 tabular-nums">{{ $summaryCounts['pending'] }}</p>
      </div>
      <div class="ab-surface-card rounded-2xl p-4">
         <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Disetujui</p>
         <p class="mt-1 font-headline text-3xl font-bold text-emerald-700 tabular-nums">{{ $summaryCounts['approved'] }}</p>
      </div>
      <div class="ab-surface-card rounded-2xl p-4">
         <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Ditolak</p>
         <p class="mt-1 font-headline text-3xl font-bold text-on-surface-variant tabular-nums">{{ $summaryCounts['rejected'] }}</p>
      </div>
      <div class="ab-surface-card rounded-2xl p-4">
         <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Ada Evidence</p>
         <p class="mt-1 font-headline text-3xl font-bold text-primary tabular-nums">{{ $summaryCounts['withEvidence'] }}</p>
      </div>
   </section>

   <section class="ab-surface-card rounded-2xl overflow-hidden">
      <div class="px-5 sm:px-6 pt-5 pb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-outline-variant/10">
         <div>
            <h2 class="font-headline font-semibold text-base text-on-background">Daftar Pengajuan</h2>
            <p class="text-xs text-on-surface-variant mt-0.5">
               {{ $submittedRequests->count() }} baris · verifikasi treatment oleh SOD
               @if($modeSemua && $submittedRequests->count() >= $batasSemua)
               <span class="text-amber-700 font-semibold">· dibatasi {{ number_format($batasSemua, 0, ',', '.') }} baris terbaru</span>
               @endif
            </p>
         </div>
         <div class="inline-flex p-1 rounded-xl bg-[#f1f5f9]/80 gap-0.5 flex-wrap" role="tablist">
            @foreach($statusTabs as $statusKey => $statusLabel)
            @php
               // "Semua" WAJIB membawa status=all: tanpa itu controller
               // menganggap status kosong = pending, jadi tab tak berpindah.
               // Tab Semua juga tidak membawa filter periode — ia menampilkan
               // seluruh data lintas minggu.
               $tabQuery = $statusKey === 'all'
                  ? ['status' => 'all']
                  : array_merge($queryBase, ['status' => $statusKey]);
               $tabAktif = $currentStatus === $statusKey;
            @endphp
            <a href="{{ route('auto-banned.sod-verification.index', $tabQuery) }}"
               role="tab"
               aria-selected="{{ $tabAktif ? 'true' : 'false' }}"
               class="ab-tab-btn rounded-lg px-3 py-2 text-xs font-semibold {{ $tabAktif ? 'text-primary bg-white shadow-sm' : 'text-on-surface-variant' }}">
               {{ $statusLabel }}
            </a>
            @endforeach
         </div>
      </div>

      @if($modeSemua)
      {{-- Filter DataTables (di browser): mempengaruhi tabel & ekspor Excel. --}}
      <div class="px-5 sm:px-6 py-3 border-b border-outline-variant/5 bg-[#fafbfc]/50 flex flex-wrap gap-2 items-center">
         <div class="relative flex-1 min-w-[200px] max-w-sm">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-base">search</span>
            <input type="search" id="abSodCari" placeholder="Cari SID, nama, perusahaan, treatment…" class="w-full rounded-xl border border-outline-variant/25 bg-white pl-9 pr-3 py-2 text-xs font-medium"/>
         </div>
         @foreach([
            'status' => ['Semua status', 'status'],
            'perusahaan' => ['Semua perusahaan', 'perusahaan'],
            'site' => ['Semua site', 'site'],
            'minggu' => ['Semua minggu', 'minggu'],
         ] as $kunciOpsi => [$placeholder, $kolom])
         <select class="rounded-xl border border-outline-variant/25 bg-white py-2 pl-3 pr-8 text-xs font-medium" data-ab-sod-filter="{{ $kolom }}">
            <option value="">{{ $placeholder }}</option>
            @foreach($opsiKolom[$kunciOpsi] as $opsi)
            <option value="{{ $opsi }}">{{ $opsi }}</option>
            @endforeach
         </select>
         @endforeach
         <button type="button" id="abSodReset" class="rounded-xl border border-outline-variant/25 bg-white px-3 py-2 text-xs font-semibold text-on-surface-variant">Reset</button>
         <button type="button" id="abSodExcel" class="ml-auto inline-flex items-center gap-1 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white">
            <span class="material-symbols-outlined text-base">download</span>
            Unduh Excel
         </button>
      </div>
      @else
      <div class="px-5 sm:px-6 py-3 border-b border-outline-variant/5 bg-[#fafbfc]/50">
         <form method="GET" action="{{ route('auto-banned.sod-verification.index') }}" class="flex flex-wrap gap-2 items-center">
            @foreach($queryBase as $key => $val)
            <input type="hidden" name="{{ $key }}" value="{{ $val }}"/>
            @endforeach
            @if($currentStatus !== '' && $currentStatus !== 'all')
            <input type="hidden" name="status" value="{{ $currentStatus }}"/>
            @endif
            <div class="relative flex-1 min-w-[200px] max-w-sm">
               <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-base">search</span>
               <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari SID, nama, perusahaan…" class="w-full rounded-xl border border-outline-variant/25 bg-white pl-9 pr-3 py-2 text-xs font-medium"/>
            </div>
            <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-xs font-bold text-white">Cari</button>
            <a href="{{ route('auto-banned.public.treatment.form', array_filter(['week' => $filters['week'] ?? '', 'year' => $filters['year'] ?? ''])) }}" target="_blank" rel="noopener" class="ml-auto inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
               <span class="material-symbols-outlined text-base">open_in_new</span>
               Form publik treatment
            </a>
         </form>
      </div>
      @endif

      <div class="overflow-x-auto {{ $modeSemua ? 'p-4' : '' }}">
         <table class="ab-sheet-table min-w-[1100px]" @if($modeSemua) id="abSodTabel" @endif>
            <thead>
               <tr>
                  <th>No</th>
                  <th>Tanggal Submit</th>
                  @if($modeSemua)<th data-ab-kolom="minggu">Minggu</th>@endif
                  <th>SID</th>
                  <th>Karyawan</th>
                  <th data-ab-kolom="perusahaan">Perusahaan</th>
                  <th data-ab-kolom="site">Site</th>
                  <th>Ringkasan Treatment</th>
                  <th>Evidence</th>
                  <th data-ab-kolom="status">Status</th>
                  <th>Verifikasi</th>
                  <th class="ab-no-export">Aksi</th>
               </tr>
            </thead>
            <tbody>
               @forelse($submittedRequests as $index => $row)
               @php
                  $badgeClass = match ($row->status->value) {
                     'approved' => 'ab-badge--ok',
                     'rejected' => 'ab-badge--muted',
                     default => 'ab-badge--wait',
                  };
               @endphp
               <tr>
                  <td class="tabular-nums text-on-surface-variant">{{ $index + 1 }}</td>
                  <td class="whitespace-nowrap text-[11px]" data-order="{{ $row->created_at?->format('Y-m-d H:i:s') }}">
                     {{ $row->created_at?->format('d M Y H:i') }}
                     @if($row->submitted_by_name)
                     <span class="block text-[10px] text-on-surface-variant mt-0.5">{{ $row->submitted_by_name }}</span>
                     @endif
                  </td>
                  @if($modeSemua)<td class="whitespace-nowrap" data-search="{{ $mingguLabel($row) }}">{{ $mingguLabel($row) ?: '—' }}</td>@endif
                  <td class="font-mono font-bold text-primary">{{ $row->sid }}</td>
                  <td class="font-medium">{{ $row->karyawan }}</td>
                  <td>{{ $row->perusahaan ?: '—' }}</td>
                  <td>{{ $row->site_dedicated ?: '—' }}</td>
                  <td class="max-w-[220px]">
                     <p class="line-clamp-3" title="{{ $row->alasan_pengajuan }}">{{ $row->alasan_pengajuan }}</p>
                     @if($row->banned_reason)
                     <span class="block text-[10px] text-on-surface-variant mt-1">Alasan banned: {{ $row->banned_reason }}</span>
                     @endif
                  </td>
                  <td>
                     @if(!empty($row->evidence_file_path))
                     <a href="{{ route('auto-banned.unban-requests.evidence', $row) }}" class="inline-flex items-center gap-1 font-semibold text-primary hover:underline text-[11px]">
                        <span class="material-symbols-outlined text-sm">download</span>
                        {{ \Illuminate\Support\Str::limit($row->evidence_original_name ?: 'Evidence', 20) }}
                     </a>
                     @else
                     <span class="text-on-surface-variant">—</span>
                     @endif
                  </td>
                  <td data-search="{{ $row->status->label() }}"><span class="ab-badge {{ $badgeClass }}">{{ $row->status->label() }}</span></td>
                  <td class="text-[11px] text-on-surface-variant whitespace-nowrap">
                     @if($row->reviewed_at)
                     <span class="block">{{ $row->reviewed_at->format('d M Y H:i') }}</span>
                     <span class="block">{{ $row->reviewed_by_name ?: '—' }}</span>
                     @if($row->catatan_review)
                     <span class="block mt-1 text-[10px] italic" title="{{ $row->catatan_review }}">{{ \Illuminate\Support\Str::limit($row->catatan_review, 40) }}</span>
                     @endif
                     @else
                     —
                     @endif
                  </td>
                  <td>
                     @if($row->status === \App\Enums\AutoBannedUnbanStatus::Pending)
                     @php
                        $reviewContext = json_encode([
                           'banned_reason' => $row->banned_reason ?? '',
                           'alasan_pengajuan' => $row->alasan_pengajuan ?? '',
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
                     @endphp
                     <div class="ab-sod-actions">
                        <button
                           type="button"
                           class="ab-btn-sm ab-btn-approve"
                           data-ab-sod-review-btn
                           data-action="approve"
                           data-form="ab-sod-review-form-{{ $row->id }}"
                           data-sid="{{ $row->sid }}"
                           data-karyawan="{{ $row->karyawan }}"
                           data-review-context="{{ $reviewContext }}"
                        >
                           <span class="material-symbols-outlined text-sm">check_circle</span>
                           Terima
                        </button>
                        <button
                           type="button"
                           class="ab-btn-sm ab-btn-reject"
                           data-ab-sod-review-btn
                           data-action="reject"
                           data-form="ab-sod-review-form-{{ $row->id }}"
                           data-sid="{{ $row->sid }}"
                           data-karyawan="{{ $row->karyawan }}"
                           data-review-context="{{ $reviewContext }}"
                        >
                           <span class="material-symbols-outlined text-sm">cancel</span>
                           Tolak
                        </button>
                        <form
                           id="ab-sod-review-form-{{ $row->id }}"
                           method="POST"
                           action="{{ route('auto-banned.unban-requests.review', $row) }}"
                           class="hidden"
                        >
                           @csrf
                           <input type="hidden" name="action" value=""/>
                           <input type="hidden" name="catatan_review" value=""/>
                           @foreach($queryBase as $key => $val)
                           <input type="hidden" name="{{ $key }}" value="{{ $val }}"/>
                           @endforeach
                           @if($currentStatus !== '' && $currentStatus !== 'all')
                           <input type="hidden" name="status" value="{{ $currentStatus }}"/>
                           @endif
                        </form>
                     </div>
                     @else
                     <span class="text-[10px] text-on-surface-variant">Selesai</span>
                     @endif
                  </td>
               </tr>
               @empty
               {{-- Mode Semua: biarkan kosong — DataTables menampilkan pesannya sendiri
                    dan menolak baris ber-colspan. --}}
               @if(! $modeSemua)
               <tr>
                  <td colspan="11" class="py-14 text-center text-sm text-on-surface-variant">
                     @if($currentStatus === 'pending')
                     Tidak ada pengajuan yang menunggu verifikasi SOD.
                     @else
                     Tidak ada data untuk filter ini.
                     @endif
                  </td>
               </tr>
               @endif
               @endforelse
            </tbody>
         </table>
      </div>
   </section>
   @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
   function escapeHtml(value) {
      return String(value ?? '')
         .replace(/&/g, '&amp;')
         .replace(/</g, '&lt;')
         .replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;');
   }

   function displayText(value) {
      var text = String(value ?? '').trim();
      return text !== '' ? escapeHtml(text) : '<span class="text-slate-400">—</span>';
   }

   function buildReviewModalHtml(sid, karyawan, bannedReason, alasanPengajuan) {
      var meta = 'SID <strong>' + escapeHtml(sid) + '</strong>';
      if (karyawan) {
         meta += ' — ' + escapeHtml(karyawan);
      }

      return '<div class="ab-sod-swal-html">' +
         '<p class="ab-sod-swal-meta">' + meta + '</p>' +
         '<div class="ab-sod-swal-box ab-sod-swal-box--banned">' +
            '<p class="ab-sod-swal-label">Alasan Banned</p>' +
            '<p class="ab-sod-swal-text">' + displayText(bannedReason) + '</p>' +
         '</div>' +
         '<div class="ab-sod-swal-box ab-sod-swal-box--treatment">' +
            '<p class="ab-sod-swal-label">Ringkasan Treatment</p>' +
            '<p class="ab-sod-swal-text">' + displayText(alasanPengajuan) + '</p>' +
         '</div>' +
      '</div>';
   }

   function openReviewModal(action, sid, karyawan, bannedReason, alasanPengajuan, formId) {
      var form = document.getElementById(formId);
      if (!form) return;

      var isApprove = action === 'approve';
      var title = isApprove ? 'Terima Pengajuan?' : 'Tolak Pengajuan?';

      if (typeof Swal === 'undefined') {
         var catatan = window.prompt((isApprove ? 'Terima' : 'Tolak') + ' pengajuan SID ' + sid + '. Catatan (opsional):', '') || '';
         form.querySelector('[name="action"]').value = action;
         form.querySelector('[name="catatan_review"]').value = catatan;
         form.submit();
         return;
      }

      Swal.fire({
         title: title,
         html: buildReviewModalHtml(sid, karyawan, bannedReason, alasanPengajuan),
         input: 'textarea',
         inputLabel: 'Catatan verifikasi',
         inputPlaceholder: 'Catatan (opsional)',
         inputAttributes: {
            maxlength: '2000',
            rows: '4',
            'aria-label': 'Catatan verifikasi',
         },
         icon: isApprove ? 'question' : 'warning',
         showCancelButton: true,
         confirmButtonText: isApprove ? 'Ya, Terima' : 'Ya, Tolak',
         cancelButtonText: 'Batal',
         confirmButtonColor: isApprove ? '#059669' : '#dc2626',
         cancelButtonColor: '#94a3b8',
         reverseButtons: true,
         focusConfirm: false,
         customClass: {
            popup: 'ab-sod-swal-popup',
         },
      }).then(function (result) {
         if (!result.isConfirmed) return;

         form.querySelector('[name="action"]').value = action;
         form.querySelector('[name="catatan_review"]').value = (result.value || '').trim();
         form.submit();
      });
   }

   function parseReviewContext(raw) {
      if (!raw) {
         return { banned_reason: '', alasan_pengajuan: '' };
      }

      try {
         return JSON.parse(raw);
      } catch (error) {
         return { banned_reason: '', alasan_pengajuan: '' };
      }
   }

   document.querySelectorAll('[data-ab-sod-review-btn]').forEach(function (button) {
      button.addEventListener('click', function () {
         var context = parseReviewContext(button.getAttribute('data-review-context'));

         openReviewModal(
            button.getAttribute('data-action') || '',
            button.getAttribute('data-sid') || '',
            button.getAttribute('data-karyawan') || '',
            context.banned_reason || '',
            context.alasan_pengajuan || '',
            button.getAttribute('data-form') || ''
         );
      });
   });
})();
</script>

@if($modeSemua)
{{-- Tab "Semua": DataTables (cari, urut, paginasi, filter kolom) + ekspor
     Excel di browser. Dimuat sesudah skrip review di atas: tombol Terima/Tolak
     sudah terikat ke semua baris sebelum DataTables memindahkan baris antar
     halaman, jadi tetap berfungsi di halaman mana pun. --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-dt@2.1.8/css/dataTables.dataTables.min.css">
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@3.1.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@3.1.2/js/buttons.html5.min.js"></script>
<style>
   /* Tombol Excel bawaan DataTables disembunyikan; dipicu tombol hijau di toolbar. */
   #abSodTabel_wrapper .dt-buttons { display: none; }
   #abSodTabel_wrapper .dt-length,
   #abSodTabel_wrapper .dt-info,
   #abSodTabel_wrapper .dt-paging { font-size: 12px; margin: .5rem 0; }
   #abSodTabel_wrapper .dt-length select { border-radius: .5rem; padding: .15rem 1.5rem .15rem .5rem; font-size: 12px; }
</style>
<script>
(function () {
   var el = document.getElementById('abSodTabel');
   if (!el || typeof DataTable === 'undefined') return;

   var tabel = new DataTable(el, {
      order: [[1, 'desc']],
      pageLength: 25,
      lengthMenu: [10, 25, 50, 100, { label: 'Semua', value: -1 }],
      columnDefs: [{ targets: 'ab-no-export', orderable: false, searchable: false }],
      layout: {
         topStart: 'pageLength',
         topEnd: {
            buttons: [{
               extend: 'excelHtml5',
               className: 'ab-btn-excel',
               title: 'Verifikasi SOD — Semua Pengajuan',
               filename: 'verifikasi-sod-semua-' + new Date().toISOString().slice(0, 10),
               // Hanya baris yang lolos filter/pencarian; kolom Aksi tidak ikut.
               exportOptions: {
                  columns: ':not(.ab-no-export)',
                  modifier: { search: 'applied', order: 'applied', page: 'all' },
               },
            }],
         },
         bottomStart: 'info',
         bottomEnd: 'paging',
      },
      language: {
         lengthMenu: 'Tampilkan _MENU_ baris',
         info: '_START_–_END_ dari _TOTAL_ pengajuan',
         infoEmpty: 'Tidak ada data',
         infoFiltered: '(disaring dari _MAX_)',
         zeroRecords: 'Tidak ada pengajuan yang cocok dengan filter.',
         emptyTable: 'Belum ada pengajuan.',
         paginate: { first: '«', last: '»', next: '›', previous: '‹' },
      },
   });

   // Indeks kolom dari atribut di <th>, supaya tidak bergantung urutan kolom.
   function indeksKolom(nama) {
      var th = el.querySelector('thead th[data-ab-kolom="' + nama + '"]');
      return th ? Array.prototype.indexOf.call(th.parentNode.children, th) : -1;
   }

   var filter = document.querySelectorAll('[data-ab-sod-filter]');
   filter.forEach(function (sel) {
      sel.addEventListener('change', function () {
         var idx = indeksKolom(sel.getAttribute('data-ab-sod-filter'));
         if (idx < 0) return;
         var nilai = sel.value;
         // Cocok persis (bukan "mengandung"), mis. site "BMO 1" tidak ikut "BMO 10".
         tabel.column(idx).search(nilai ? '^' + DataTable.util.escapeRegex(nilai) + '$' : '', true, false).draw();
      });
   });

   var cari = document.getElementById('abSodCari');
   if (cari) {
      cari.addEventListener('input', function () { tabel.search(cari.value).draw(); });
   }

   var reset = document.getElementById('abSodReset');
   if (reset) {
      reset.addEventListener('click', function () {
         filter.forEach(function (sel) { sel.value = ''; });
         if (cari) cari.value = '';
         tabel.search('').columns().search('').draw();
      });
   }

   var excel = document.getElementById('abSodExcel');
   if (excel) {
      excel.addEventListener('click', function () { tabel.button('.ab-btn-excel').trigger(); });
   }
})();
</script>
@endif
@endpush
