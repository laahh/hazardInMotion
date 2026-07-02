@extends('AutoBanned.layouts.app')

@section('title', 'Pipeline Banned → Unban')

@push('head')
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Two+Tone" rel="stylesheet"/>
@include('AutoBanned.partials.phppot-dashboard-styles')
<style>
   .ab-pipeline { --ab-ease: cubic-bezier(0.4, 0, 0.2, 1); }
   .ab-pipeline-badge {
      display: inline-flex; align-items: center; border-radius: 0.375rem;
      padding: 0.15rem 0.5rem; font-size: 10px; font-weight: 700; white-space: nowrap;
      border: 1px solid transparent;
   }
   .ab-pipeline-badge--ok { background: rgba(16,185,129,.1); color: #047857; border-color: rgba(16,185,129,.25); }
   .ab-pipeline-badge--warn { background: rgba(245,158,11,.12); color: #b45309; border-color: rgba(245,158,11,.3); }
   .ab-pipeline-badge--info { background: rgba(59,130,246,.1); color: #1d4ed8; border-color: rgba(59,130,246,.25); }
   .ab-pipeline-badge--primary { background: rgba(57,82,188,.08); color: #3952bc; border-color: rgba(57,82,188,.2); }
   .ab-pipeline-badge--wait { background: rgba(168,85,247,.1); color: #7c3aed; border-color: rgba(168,85,247,.25); }
   .ab-pipeline-badge--danger { background: rgba(239,68,68,.1); color: #b91c1c; border-color: rgba(239,68,68,.25); }
   .ab-pipeline-badge--muted { background: #f1f5f9; color: #64748b; border-color: #e2e8f0; }
   .ab-due--ok { color: #047857; font-weight: 600; }
   .ab-due--wait { color: #b45309; font-weight: 600; }
   .ab-due--danger { color: #b91c1c; font-weight: 700; }
   .ab-due--muted { color: #94a3b8; }
   .ab-pipeline-table { border-collapse: collapse; font-size: 11px; width: 100%; }
   .ab-pipeline-table thead th {
      background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.55rem 0.65rem;
      font-size: 10px; font-weight: 700; color: #475569; white-space: nowrap; text-transform: uppercase; letter-spacing: .03em;
   }
   .ab-pipeline-table tbody td {
      border: 1px solid #e2e8f0; padding: 0.55rem 0.65rem; color: #1e293b; vertical-align: top;
   }
   .ab-pipeline-table tbody tr:hover td { background: #f8fafc; }
   .ab-pipeline-table tbody tr.is-overdue td { background: #fff7ed; }
   .ab-pipeline-table tbody tr.is-unbanned td { background: #f0fdf4; }
   .ab-pipeline-kpi-note { font-size: 11px; color: #888; margin: -12px 0 20px; }
   .ab-search-wrap {
      display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
      border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; padding: .45rem .65rem;
   }
   .ab-pipeline-table-toolbar {
      display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
      gap: .75rem; width: 100%; margin-top: .75rem; padding-top: .75rem;
      border-top: 1px solid #f1f5f9;
   }
   .ab-search-wrap input[type="search"],
   .ab-search-wrap input[type="text"] {
      border: 0; outline: none; font-size: 12px; background: transparent;
   }
   .ab-search-wrap input[name="sid"] {
      font-family: ui-monospace, monospace; font-weight: 700; text-transform: uppercase;
      width: 7rem; min-width: 7rem;
   }
   .ab-search-wrap input[name="nama"] {
      width: 10rem; min-width: 10rem;
   }
   .ab-search-btn {
      border: 0; border-radius: 8px; background: #3952bc; color: #fff;
      font-size: 11px; font-weight: 700; padding: .45rem .75rem; cursor: pointer;
   }
   .ab-search-btn:hover { opacity: .92; }
   .ab-search-clear {
      font-size: 11px; font-weight: 600; color: #64748b; text-decoration: none;
   }
   .ab-search-clear:hover { color: #3952bc; }
   @media (max-width: 1199px) {
      .ab-pipeline .dash-col-6[style*="33.333%"] { width: 50% !important; }
   }
   @media (max-width: 767px) {
      .ab-pipeline .dash-col-6[style*="33.333%"] { width: 100% !important; }
   }
</style>
@endpush

@section('content')
@php
   use App\Services\AutoBanned\AutoBannedSlaCalculator;

   $periodLabel = !empty($period['filter_date'])
      ? \Carbon\Carbon::parse($period['filter_date'])->format('d M Y')
      : 'Semua Tanggal';
   $stats = $stats ?? [];
   $pipelineRows = $pipelineRows ?? collect();
   $tableAvailable = $tableAvailable ?? false;

   $productCards = [
      ['title' => 'Total Di-Banned', 'value' => number_format($stats['total'] ?? 0), 'icon' => 'block'],
      ['title' => 'Belum Pengajuan', 'value' => number_format($stats['no_request'] ?? 0), 'icon' => 'assignment_late'],
      ['title' => 'Menunggu Review', 'value' => number_format($stats['request_pending'] ?? 0), 'icon' => 'hourglass_top'],
      ['title' => 'Menunggu Automasi Unban', 'value' => number_format($stats['awaiting_unban'] ?? 0), 'icon' => 'lock_clock'],
      ['title' => 'Sudah Unban', 'value' => number_format($stats['unbanned'] ?? 0), 'icon' => 'lock_open'],
      ['title' => 'Lewat Deadline', 'value' => number_format($stats['overdue'] ?? 0), 'icon' => 'warning'],
   ];

   $queryBase = array_filter([
      'filter_date' => $filters['filter_date'] ?? '',
      'site' => $filters['site'] ?? '',
      'perusahaan' => $filters['perusahaan'] ?? '',
      'pipeline_stage' => $filters['pipeline_stage'] ?? '',
      'sid' => $filters['sid'] ?? '',
      'nama' => $filters['nama'] ?? '',
      'q' => $filters['q'] ?? '',
   ], fn ($v) => $v !== '' && $v !== null);

   $hasActiveSearch = ($filters['sid'] ?? '') !== '' || ($filters['nama'] ?? '') !== '';
@endphp

<div class="ab-phppot ab-pipeline -mt-1">
   <div class="page-top">
      <div class="min-w-0">
         <h1>Pipeline Banned → Unban</h1>
         <p>
            Monitoring end-to-end: siapa sudah di-banned, status pengajuan treatment, approval SOD, dan target unban.
            &bull; {{ $periodLabel }}
         </p>
      </div>
      <div class="flex flex-col items-end gap-2.5">
         @include('AutoBanned.partials.pipeline-filter-bar', [
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'filterRoute' => 'auto-banned.pipeline-monitoring.index',
         ])
      </div>
   </div>

   @if(!$tableAvailable)
   <div class="dash-card card-body text-sm text-[#888] mb-3">
      Tabel <code>sid_banned_log</code> belum tersedia.
   </div>
   @else

   <p class="ab-pipeline-kpi-note">
      Sumber: <code>sid_banned_log</code> (SUCCESS) + <code>auto_banned_unban_requests</code> + <code>sid_unban_log</code>.
      Target unban otomatis: <strong>{{ AutoBannedSlaCalculator::AUTOMATION_UNBAN_HOURS }} jam</strong> sejak waktu banned (<code>completed_at</code>).
   </p>

   <div class="dash-row">
      @foreach($productCards as $card)
      <div class="dash-col-6" style="width:33.333%">
         <div class="prod-p-card">
            <div class="card-body">
               <div class="flex items-center justify-between">
                  <div>
                     <h6 class="m-b-5">{{ $card['title'] }}</h6>
                     <h3 class="mb-0">{{ $card['value'] }}</h3>
                  </div>
                  <i class="material-icons-two-tone text-primary">{{ $card['icon'] }}</i>
               </div>
            </div>
         </div>
      </div>
      @endforeach
   </div>

   <div class="dash-card">
      <div class="card-header" style="display:block">
         <div class="flex flex-wrap items-center justify-between gap-2">
            <h5 style="margin:0">Daftar Pipeline ({{ $pipelineRows->count() }})</h5>
            <div class="flex flex-wrap gap-2 text-[10px]">
               <a href="{{ route('auto-banned.pipeline-monitoring.index', array_merge($queryBase, ['pipeline_stage' => 'no_request'])) }}" class="ab-pipeline-badge ab-pipeline-badge--warn">Belum ajukan</a>
               <a href="{{ route('auto-banned.pipeline-monitoring.index', array_merge($queryBase, ['pipeline_stage' => 'request_pending'])) }}" class="ab-pipeline-badge ab-pipeline-badge--info">Pending SOD</a>
               <a href="{{ route('auto-banned.pipeline-monitoring.index', array_merge($queryBase, ['pipeline_stage' => 'awaiting_unban'])) }}" class="ab-pipeline-badge ab-pipeline-badge--wait">Menunggu automasi unban</a>
               <a href="{{ route('auto-banned.pipeline-monitoring.index', array_merge($queryBase, ['pipeline_stage' => 'overdue'])) }}" class="ab-pipeline-badge ab-pipeline-badge--danger">Lewat deadline</a>
            </div>
         </div>
         <div class="ab-pipeline-table-toolbar">
            <form method="GET" action="{{ route('auto-banned.pipeline-monitoring.index') }}" class="ab-search-wrap" id="ab-pipeline-search-form">
               @foreach($queryBase as $key => $val)
                  @if(!in_array($key, ['sid', 'nama', 'q'], true))
                  <input type="hidden" name="{{ $key }}" value="{{ $val }}"/>
                  @endif
               @endforeach
               <span class="material-symbols-outlined text-primary/70 text-base">search</span>
               <input
                  type="text"
                  name="sid"
                  value="{{ $filters['sid'] ?? '' }}"
                  placeholder="Cari SID"
                  maxlength="64"
                  autocomplete="off"
               />
               <span class="text-[#cbd5e1]">|</span>
               <input
                  type="search"
                  name="nama"
                  value="{{ $filters['nama'] ?? '' }}"
                  placeholder="Cari nama karyawan"
                  maxlength="255"
                  autocomplete="off"
               />
               <button type="submit" class="ab-search-btn">Cari</button>
               @if($hasActiveSearch)
               <a href="{{ route('auto-banned.pipeline-monitoring.index', collect($queryBase)->except(['sid', 'nama', 'q'])->all()) }}" class="ab-search-clear">Reset</a>
               @endif
            </form>
            @if($hasActiveSearch)
            <span class="text-[11px] text-[#64748b]">
               Filter aktif:
               @if(($filters['sid'] ?? '') !== '')<strong class="text-primary">{{ $filters['sid'] }}</strong>@endif
               @if(($filters['sid'] ?? '') !== '' && ($filters['nama'] ?? '') !== '') &bull; @endif
               @if(($filters['nama'] ?? '') !== '')<strong>{{ $filters['nama'] }}</strong>@endif
            </span>
            @endif
         </div>
      </div>
      <div class="card-body p-0 overflow-x-auto">
         @if($pipelineRows->isEmpty())
         <p class="p-6 text-sm text-[#888]">
            @if($hasActiveSearch)
            Tidak ada data untuk SID <strong>{{ $filters['sid'] ?: '—' }}</strong>
            @if(($filters['nama'] ?? '') !== '')
            / nama <strong>{{ $filters['nama'] }}</strong>
            @endif
            . Coba kata kunci lain atau <a href="{{ route('auto-banned.pipeline-monitoring.index', collect($queryBase)->except(['sid', 'nama', 'q'])->all()) }}" class="text-primary font-semibold hover:underline">reset pencarian</a>.
            @else
            Tidak ada data untuk filter yang dipilih.
            @endif
         </p>
         @else
         <table class="ab-pipeline-table">
            <thead>
               <tr>
                  <th>Tanggal Banned</th>
                  <th>SID / Karyawan</th>
                  <th>Site / Perusahaan</th>
                  <th>Alasan</th>
                  <th>Tahapan Pipeline</th>
                  <th>Status Pengajuan</th>
                  <th>Aksi Berikutnya</th>
                  <th>Target Unban Otomatis</th>
                  <th>Sisa Waktu</th>
                  <th>Unban Selesai</th>
               </tr>
            </thead>
            <tbody>
               @foreach($pipelineRows as $row)
               @php
                  $rowClass = '';
                  if (($row['pipelineStage'] ?? '') === 'unbanned') {
                     $rowClass = 'is-unbanned';
                  } elseif (($row['isOverdue'] ?? false) === true) {
                     $rowClass = 'is-overdue';
                  }
                  $dueToneClass = 'ab-due--'.($row['dueTone'] ?? 'muted');
               @endphp
               <tr class="{{ $rowClass }}">
                  <td>
                     <div class="font-semibold">{{ $row['filterDate'] ?? '—' }}</div>
                     @if(!empty($row['filterShift']))
                     <div class="text-[10px] text-[#64748b]">{{ $row['filterShift'] }}</div>
                     @endif
                     <div class="text-[10px] text-[#64748b]">Banned: {{ $row['bannedAt'] ?? '—' }}</div>
                  </td>
                  <td>
                     <div class="font-mono font-bold text-primary">{{ $row['sid'] ?? '—' }}</div>
                     <div class="font-semibold">{{ $row['nama'] ?? '—' }}</div>
                     @if(!empty($row['nik']))
                     <div class="text-[10px] text-[#64748b]">NIK: {{ $row['nik'] }}</div>
                     @endif
                  </td>
                  <td>
                     <div>{{ $row['site'] ?: '—' }}</div>
                     <div class="text-[10px] text-[#64748b]">{{ $row['perusahaan'] ?: '—' }}</div>
                  </td>
                  <td>
                     @if(!empty($row['bannedStatus']))
                     <div class="font-semibold text-[10px]">{{ $row['bannedStatus'] }}</div>
                     @endif
                     <div class="text-[10px] leading-snug">{{ \Illuminate\Support\Str::limit($row['bannedReason'] ?? '—', 80) }}</div>
                  </td>
                  <td>
                     <span class="ab-pipeline-badge {{ $row['pipelineBadgeClass'] ?? 'ab-pipeline-badge--muted' }}">
                        {{ $row['pipelineLabel'] ?? '—' }}
                     </span>
                  </td>
                  <td>
                     @if($row['hasRequest'] ?? false)
                     <div class="font-semibold">{{ $row['requestStatusLabel'] ?? '—' }}</div>
                     <div class="text-[10px] text-[#64748b]">Diajukan: {{ $row['requestSubmittedAt'] ?? '—' }}</div>
                     @if(!empty($row['requestReviewedAt']))
                     <div class="text-[10px] text-[#64748b]">Review: {{ $row['requestReviewedAt'] }}</div>
                     @if(!empty($row['requestReviewedBy']))
                     <div class="text-[10px] text-[#64748b]">Oleh: {{ $row['requestReviewedBy'] }}</div>
                     @endif
                     @endif
                     @if(!empty($row['hsctNotifiedAt']))
                     <div class="text-[10px] text-emerald-700">HSCT: {{ $row['hsctNotifiedAt'] }}</div>
                     @endif
                     @if(!empty($row['requestId']))
                     <a href="{{ route('auto-banned.sod-verification.index', ['q' => $row['sid'] ?? '']) }}" class="text-[10px] text-primary font-semibold hover:underline">Lihat di SOD →</a>
                     @endif
                     @else
                     <span class="text-[#94a3b8]">Belum ada</span>
                     @endif
                  </td>
                  <td class="text-[11px] leading-snug">{{ $row['nextActionLabel'] ?? '—' }}</td>
                  <td class="{{ $dueToneClass }}">
                     <div class="font-semibold">{{ $row['dueAtLabel'] ?? '—' }}</div>
                     @if(!empty($row['bannedAtLabel']) && $row['bannedAtLabel'] !== '—')
                     <div class="text-[10px] text-[#64748b] font-normal">Dari banned: {{ $row['bannedAtLabel'] }}</div>
                     @endif
                  </td>
                  <td class="{{ $dueToneClass }}">
                     <div class="font-semibold">{{ $row['remainingLabel'] ?? '—' }}</div>
                     @if(($row['pipelineStage'] ?? '') !== 'unbanned' && !empty($row['dueAtLabel']) && ($row['dueAtLabel'] ?? '—') !== '—')
                     <div class="text-[10px] text-[#64748b] font-normal">hingga unban otomatis</div>
                     @endif
                  </td>
                  <td>
                     @if(!empty($row['unbanCompletedAt']))
                     <span class="ab-pipeline-badge ab-pipeline-badge--ok">{{ $row['unbanCompletedAt'] }}</span>
                     @else
                     <span class="text-[#94a3b8]">—</span>
                     @endif
                  </td>
               </tr>
               @endforeach
            </tbody>
         </table>
         @endif
      </div>
   </div>

   @endif
</div>

@push('scripts')
<script>
(function () {
   var form = document.getElementById('ab-pipeline-search-form');
   if (!form) return;
   var sidInput = form.querySelector('input[name="sid"]');
   if (!sidInput) return;
   sidInput.addEventListener('input', function () {
      var pos = sidInput.selectionStart;
      sidInput.value = sidInput.value.toUpperCase();
      if (pos !== null) sidInput.setSelectionRange(pos, pos);
   });
})();
</script>
@endpush
@endsection
