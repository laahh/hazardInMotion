@extends('AutoBanned.layouts.app')

@section('title', 'Monitoring Un Banned')

@section('page-header')
@endsection

@push('head')
@include('AutoBanned.partials.phppot-dashboard-styles')
@endpush

@section('content')
@php
   use App\Enums\AutoBannedUnbanStatus;

   $periodLabel = !empty($period['filter_date'])
      ? \Carbon\Carbon::parse($period['filter_date'])->format('d M Y')
      : 'Semua Tanggal';
   $chartData = $chartData ?? [];
   $tableAvailable = $tableAvailable ?? false;

   $approvalRate = (float) ($stats['approvalRate'] ?? 0);
   $trend = $chartData['dailyTrend'] ?? ['labels' => [], 'total' => [], 'approved' => [], 'rejected' => []];
   $trendLabels = $trend['labels'] ?? [];
   $trendTotal = $trend['total'] ?? [];
   $trendApproved = $trend['approved'] ?? [];
   $trendTotalSum = array_sum($trendTotal);
   $trendAvg = count($trendTotal) > 0 ? round(array_sum($trendTotal) / count($trendTotal), 1) : 0;

   $last3Labels = array_values(array_slice($trendLabels, -3));
   $last3Total = array_values(array_slice($trendTotal, -3));
   $last3Approved = array_values(array_slice($trendApproved, -3));
   while (count($last3Labels) < 3) { array_unshift($last3Labels, '—'); array_unshift($last3Total, 0); array_unshift($last3Approved, 0); }
   $last3Labels = array_slice($last3Labels, -3);
   $last3Total = array_slice($last3Total, -3);
   $last3Approved = array_slice($last3Approved, -3);

   $productCards = [
      ['title' => 'Total Pengajuan', 'value' => number_format($stats['total'] ?? 0), 'icon' => 'assignment'],
      ['title' => 'Menunggu Review', 'value' => number_format($stats['pending'] ?? 0), 'icon' => 'hourglass_top'],
      ['title' => 'Disetujui', 'value' => number_format($stats['approved'] ?? 0), 'icon' => 'check_circle'],
      ['title' => 'Ditolak', 'value' => number_format($stats['rejected'] ?? 0), 'icon' => 'cancel'],
   ];

   $pieLabels = $chartData['byStatus']['labels'] ?? [];
   $pieValues = $chartData['byStatus']['values'] ?? [];

   $thumbColors = ['#01b0c6', '#019aab', '#48d1e0', '#017a8a', '#33c9dc', '#00bcd4'];

   $rowsPending = $unbanRows->filter(fn ($row) => $row->status === AutoBannedUnbanStatus::Pending)->values();
   $rowsApproved = $unbanRows->filter(fn ($row) => $row->status === AutoBannedUnbanStatus::Approved)->values();
   $rowsRejected = $unbanRows->filter(fn ($row) => $row->status === AutoBannedUnbanStatus::Rejected)->values();

   $avgLineData = [];
   if (count($trendTotal) > 0) {
      $mean = array_sum($trendTotal) / count($trendTotal);
      $avgLineData = array_fill(0, count($trendTotal), round($mean, 1));
   }

   $approvalSlaAvgSeconds = $stats['approvalSlaAvgSeconds'] ?? null;
   $approvalSlaSampleCount = (int) ($stats['approvalSlaSampleCount'] ?? 0);
   if ($approvalSlaAvgSeconds === null || $approvalSlaSampleCount === 0) {
      $approvalSlaLabel = '—';
   } elseif ($approvalSlaAvgSeconds >= 86400) {
      $approvalSlaLabel = round($approvalSlaAvgSeconds / 86400, 1).' hari';
   } elseif ($approvalSlaAvgSeconds >= 3600) {
      $approvalSlaLabel = round($approvalSlaAvgSeconds / 3600, 1).' jam';
   } else {
      $approvalSlaLabel = round($approvalSlaAvgSeconds / 60, 0).' menit';
   }

   $approvalSlaDesc = 'Rata-rata waktu dari pengajuan hingga approval';
   if ($approvalSlaSampleCount > 0) {
      $approvalSlaDesc .= ' ('.number_format($approvalSlaSampleCount).' pengajuan disetujui)';
   }
   $approvalSlaDesc .= '.';
@endphp

<div class="ab-phppot -mt-1">
   <div class="page-top">
      <div>
         <h1>Monitoring Un Banned</h1>
         <p>Pengajuan unban dari <code>auto_banned_unban_requests</code> &bull; {{ $periodLabel }}</p>
      </div>
      @include('AutoBanned.partials.unban-filter-bar', [
         'filters' => $filters,
         'filterOptions' => $filterOptions,
         'filterRoute' => 'auto-banned.unban-monitoring.index',
      ])
   </div>

   @if(!$tableAvailable)
   <div class="dash-card card-body text-sm text-[#888] mb-3">
      Tabel <code>auto_banned_unban_requests</code> belum tersedia. Jalankan migration.
   </div>
   @else

   <div class="dash-row">
      <div class="dash-col-12 dash-col-xl-6">
         <div class="dash-row">
            @foreach($productCards as $card)
            <div class="dash-col-6">
               <div class="prod-p-card">
                  <div class="card-body">
                     <div class="flex items-center justify-between">
                        <div>
                           <h6 class="m-b-5">{{ $card['title'] }}</h6>
                           <h3 class="mb-0">{{ $card['value'] }}</h3>
                        </div>
                        <div class="card-icon-col">
                           <i class="material-icons-two-tone text-primary">{{ $card['icon'] }}</i>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            @endforeach
         </div>

         <div class="dash-row">
            <div class="dash-col-6">
               <div class="dash-card support-bar">
                  <div class="card-body pb-0">
                     <h2 class="m-0">{{ number_format($approvalRate, 1) }}%</h2>
                     <span class="label-cyan">Tingkat Persetujuan</span>
                     <p class="widget-desc mb-3 mt-3">Persentase pengajuan unban yang disetujui dari total yang sudah direview.</p>
                  </div>
                  <div id="ab-unban-chart-conversion"></div>
                  <div class="card-footer border-0 bg-cyan">
                     <div class="footer-row">
                        @foreach($last3Labels as $i => $lbl)
                        <div class="footer-stat">
                           <h4 class="m-0 text-white">{{ $last3Approved[$i] ?? 0 }}</h4>
                           <span>{{ $lbl }}</span>
                        </div>
                        @endforeach
                     </div>
                  </div>
               </div>
            </div>
            <div class="dash-col-6">
               <div class="dash-card support-bar">
                  <div class="card-body pb-0">
                     <h2 class="m-0">{{ $approvalSlaLabel }}</h2>
                     <span class="label-cyan">LSA</span>
                     <p class="widget-desc mb-3 mt-3">{{ $approvalSlaDesc }}</p>
                  </div>
                  <div class="card-footer border-0">
                     <div class="footer-row">
                        @foreach($last3Labels as $i => $lbl)
                        <div class="footer-stat">
                           <h4 class="m-0">{{ $last3Total[$i] ?? 0 }}</h4>
                           <span>{{ $lbl }}</span>
                        </div>
                        @endforeach
                     </div>
                  </div>
                  <div id="ab-unban-chart-orders"></div>
               </div>
            </div>
         </div>
      </div>

      <div class="dash-col-12 dash-col-xl-6 right-col-stretch">
         <div class="dash-card">
            <div class="card-header">
               <h5>Trend Pengajuan Unban (14 Hari)</h5>
            </div>
            <div class="card-body">
               <div class="report-metrics pb-2">
                  <div>
                     <h3 class="mb-1">{{ number_format($trendTotalSum) }}</h3>
                     <span>Total</span>
                  </div>
                  <div>
                     <h3 class="mb-1">{{ $trendAvg }}</h3>
                     <span>Avg / hari</span>
                  </div>
                  <div>
                     <h3 class="mb-1">{{ number_format($stats['linkedScr'] ?? 0) }}</h3>
                     <span>Link SCR</span>
                  </div>
               </div>
               <div class="chart-main" id="ab-unban-chart-main"></div>
            </div>
         </div>
      </div>

      <div class="dash-col-12 dash-col-xl-6">
         <div class="dash-card satisfaction">
            <div class="card-body">
               <h6>Distribusi Status Pengajuan</h6>
               <span>Proporsi status pengajuan unban: menunggu review, disetujui, dan ditolak pada periode filter.</span>
               <div class="chart-pie mt-2" id="ab-unban-chart-pie"></div>
            </div>
         </div>
      </div>

      <div class="dash-col-12 dash-col-xl-6">
         <div class="dash-card">
            <div class="card-header card-header-with-tabs">
               <h5>Daftar Pengajuan Unban</h5>
               <div class="list-tabs" role="tablist">
                  <button type="button" class="list-tab-btn" data-ab-list-tab="pending" aria-selected="true" id="ab-unban-tab-pending">
                     Menunggu <span class="tab-count">({{ $rowsPending->count() }})</span>
                  </button>
                  <button type="button" class="list-tab-btn" data-ab-list-tab="approved" aria-selected="false" id="ab-unban-tab-approved">
                     Disetujui <span class="tab-count">({{ $rowsApproved->count() }})</span>
                  </button>
                  <button type="button" class="list-tab-btn" data-ab-list-tab="rejected" aria-selected="false" id="ab-unban-tab-rejected">
                     Ditolak <span class="tab-count">({{ $rowsRejected->count() }})</span>
                  </button>
               </div>
            </div>

            @foreach([
               'pending' => $rowsPending,
               'approved' => $rowsApproved,
               'rejected' => $rowsRejected,
            ] as $tabKey => $tabRows)
            <div class="card-body p-0 list-panel {{ $tabKey !== 'pending' ? 'hidden' : '' }}" data-ab-list-panel="{{ $tabKey }}" role="tabpanel">
               <div class="wishlist-scroll">
                  <table class="wishlist-table">
                     <thead>
                        <tr>
                           <th>Karyawan</th>
                           <th>Status</th>
                           <th>Site</th>
                           <th>Alasan Di-Banned</th>
                           <th>Alasan / Evidence</th>
                        </tr>
                     </thead>
                     <tbody>
                        @forelse($tabRows as $row)
                        @php
                           $nama = (string) ($row->karyawan ?? '');
                           $sid = (string) ($row->sid ?? '');
                           $initials = collect(explode(' ', $nama))->filter()->take(2)
                              ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
                           $cIdx = abs(crc32($sid ?: $nama)) % count($thumbColors);
                           $scr = $row->scrDailyBanned;
                           $statusBadge = match ($row->status) {
                              AutoBannedUnbanStatus::Approved => 'badge-success',
                              AutoBannedUnbanStatus::Rejected => 'badge-danger',
                              default => 'badge-warning',
                           };
                           $hasEvidence = trim((string) ($row->evidence_file_path ?? '')) !== '';
                        @endphp
                        <tr>
                           <td>
                              <span class="item-desc">
                                 <span class="item-thumb" style="background:{{ $thumbColors[$cIdx] }}">{{ $initials ?: '?' }}</span>
                                 <span>
                                    <span class="name" title="{{ $nama }}">{{ $nama ?: '—' }}</span>
                                    <br><span class="text-[10px] text-[#888] font-mono">{{ $sid }}</span>
                                 </span>
                              </span>
                           </td>
                           <td><label class="badge {{ $statusBadge }}">{{ $row->status->label() }}</label></td>
                           <td>{{ $row->site_dedicated ?: '—' }}</td>
                           <td>
                              @if($scr)
                              @php $scrBannedReason = trim((string) ($scr->Banned_Daily_Reason ?? '')); @endphp
                              <span class="reason-cell" title="{{ $scrBannedReason }}">{{ $scrBannedReason ?: '—' }}</span>
                              @if($scr->Status_Banned_Daily)
                              <br><span class="text-[10px] text-[#aaa]">{{ $scr->Status_Banned_Daily }}</span>
                              @endif
                              @if($scr->filter_date)
                              <br><span class="text-[10px] text-[#aaa]">{{ $scr->filter_date->format('d M Y') }}</span>
                              @endif
                              @else
                              <span class="text-[11px] text-[#aaa]">—</span>
                              @endif
                           </td>
                           <td>
                              <span class="reason-cell" title="{{ $row->banned_reason }}">{{ $row->banned_reason ?: '—' }}</span>
                              @if($hasEvidence)
                              <br>
                              <a href="{{ route('auto-banned.unban-requests.evidence', $row) }}" class="text-[10px] text-[#01b0c6] font-semibold hover:underline">Lihat evidence</a>
                              @endif
                              <br><span class="text-[10px] text-[#aaa]">{{ $row->created_at?->format('d M Y H:i') }}</span>
                           </td>
                        </tr>
                        @empty
                        <tr>
                           <td colspan="5" class="text-center py-10" style="color:#888">Tidak ada data pada tab ini</td>
                        </tr>
                        @endforelse
                     </tbody>
                  </table>
               </div>
            </div>
            @endforeach
         </div>
      </div>
   </div>
   @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
   document.querySelectorAll('[data-ab-list-tab]').forEach(function (tab) {
      tab.addEventListener('click', function () {
         var name = tab.getAttribute('data-ab-list-tab');
         document.querySelectorAll('[data-ab-list-tab]').forEach(function (t) {
            t.setAttribute('aria-selected', t.getAttribute('data-ab-list-tab') === name ? 'true' : 'false');
         });
         document.querySelectorAll('[data-ab-list-panel]').forEach(function (panel) {
            panel.classList.toggle('hidden', panel.getAttribute('data-ab-list-panel') !== name);
         });
      });
   });
})();
</script>
<script>
(function () {
   if (typeof ApexCharts === 'undefined') return;

   var CYAN = '#01b0c6';
   var BLUE = '#0000ff';
   var chartData = @json($chartData);
   var trend = chartData.dailyTrend || { labels: [], total: [], approved: [] };
   var pieLabels = @json($pieLabels);
   var pieValues = @json($pieValues);
   var avgLine = @json($avgLineData);
   var font = 'Poppins, Helvetica, Arial, sans-serif';

   var convEl = document.querySelector('#ab-unban-chart-conversion');
   if (convEl) {
      new ApexCharts(convEl, {
         chart: { type: 'area', height: 100, sparkline: { enabled: true }, background: 'transparent' },
         dataLabels: { enabled: false },
         stroke: { curve: 'smooth', width: 2 },
         fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1, stops: [0, 90, 100] } },
         colors: [CYAN],
         series: [{ name: 'approved', data: (trend.approved && trend.approved.length) ? trend.approved : [0, 0, 0, 0, 0] }],
         tooltip: { fixed: { enabled: false }, x: { show: false }, y: { title: { formatter: function () { return ''; } } }, marker: { show: false } }
      }).render();
   }

   var ordersEl = document.querySelector('#ab-unban-chart-orders');
   if (ordersEl) {
      var barData = (trend.total && trend.total.length) ? trend.total : [0, 0, 0, 0, 0];
      new ApexCharts(ordersEl, {
         chart: { type: 'bar', height: 100, sparkline: { enabled: true } },
         plotOptions: { bar: { columnWidth: '80%' } },
         colors: [CYAN],
         series: [{ name: 'total', data: barData }],
         tooltip: { fixed: { enabled: false }, x: { show: false }, y: { title: { formatter: function () { return ''; } } }, marker: { show: false } }
      }).render();
   }

   var mainEl = document.querySelector('#ab-unban-chart-main');
   if (mainEl) {
      new ApexCharts(mainEl, {
         chart: { height: 350, type: 'line', background: 'transparent', toolbar: { show: true }, fontFamily: font },
         stroke: { width: [0, 3], curve: 'smooth' },
         plotOptions: { bar: { columnWidth: '50%' } },
         colors: [CYAN, BLUE],
         fill: { opacity: [0.85, 1] },
         labels: (trend.labels && trend.labels.length) ? trend.labels : ['—'],
         markers: { size: 0 },
         series: [
            { name: 'Total Pengajuan', type: 'column', data: trend.total || [] },
            { name: 'Rata-rata', type: 'line', data: (avgLine.length ? avgLine : (trend.approved || [])) }
         ],
         yaxis: { min: 0 },
         legend: { labels: { useSeriesColors: true } },
         tooltip: { shared: true, intersect: false }
      }).render();
   }

   var pieEl = document.querySelector('#ab-unban-chart-pie');
   if (pieEl) {
      new ApexCharts(pieEl, {
         chart: { type: 'pie', height: 260, background: 'transparent', toolbar: { show: true }, fontFamily: font },
         labels: pieLabels.length ? pieLabels : ['No Data'],
         series: pieValues.length ? pieValues : [1],
         colors: pieLabels.length ? undefined : ['#e0e0e0'],
         dataLabels: { enabled: true },
         theme: { mode: 'light', monochrome: { enabled: pieLabels.length > 0, color: CYAN } },
         legend: { show: true, position: 'right', offsetY: 50 }
      }).render();
   }
})();
</script>
@endpush
