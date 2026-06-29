@php
   use App\Enums\AutoBannedSidAutomationStatus;

   $stats = $stats ?? [];
   $chartData = $chartData ?? [];
   $bannedRows = collect($bannedRows ?? []);
   $logRows = collect($logRows ?? []);
   $scrTableAvailable = $scrTableAvailable ?? false;
   $compact = $compact ?? false;

   $successRate = 100.0;
   $totalSuccess = (int) ($stats['totalBannedToday'] ?? $stats['success'] ?? 0);

   $productCards = [
      ['title' => 'Harus Di-Banned', 'value' => number_format($totalSuccess), 'icon' => 'block'],
      ['title' => 'Automasi OK', 'value' => number_format($totalSuccess), 'icon' => 'check_circle'],
      ['title' => 'Diproses', 'value' => number_format($totalSuccess), 'icon' => 'task_alt'],
      ['title' => 'Belum Proses', 'value' => number_format(0), 'icon' => 'hourglass_empty'],
   ];

   $pieLabels = $chartData['byBannedStatus']['labels'] ?? [];
   $pieValues = $chartData['byBannedStatus']['values'] ?? [];
   if (empty($pieLabels) && !empty($chartData['topReasons']['labels'])) {
      $pieLabels = $chartData['topReasons']['labels'];
      $pieValues = $chartData['topReasons']['values'];
   }

   $bannedDoneRows = $logRows->filter(
      fn ($row) => $row->automation_status?->value === AutoBannedSidAutomationStatus::Success->value
   )->values();

   if ($bannedDoneRows->isEmpty()) {
      $bannedDoneRows = $bannedRows->filter(
         fn ($row) => ($row['automationStatus']?->value ?? '') === AutoBannedSidAutomationStatus::Success->value
      )->values();
   }

   $trend = $chartData['dailyTrend'] ?? ['success' => []];
   $sparkData = $trend['success'] ?? [];
   $kpiCardClass = $compact ? 'prod-p-card ab-overview-kpi' : 'prod-p-card';
   $widgetCardClass = $compact ? 'dash-card support-bar ab-overview-widget' : 'dash-card support-bar';
   $pieCardClass = $compact ? 'dash-card satisfaction ab-overview-widget' : 'dash-card satisfaction';
   $pieHeight = $compact ? 140 : 260;
   $sparkHeight = $compact ? 70 : 100;
   $listMaxHeight = $compact ? '200px' : '320px';
   $sparkId = $sparkId ?? 'ab-banned-monitoring-spark';
   $pieId = $pieId ?? 'ab-banned-monitoring-pie';
@endphp

@if(!$scrTableAvailable)
<div class="dash-card card-body text-sm text-[#888]{{ $compact ? '' : ' mb-3' }}">Tabel <code>scr_daily_banned</code> belum tersedia.</div>
@else
<div class="dash-row">
   @foreach($productCards as $card)
   <div class="dash-col-6">
      <div class="{{ $kpiCardClass }}">
         <div class="card-body">
            <div class="flex items-center justify-between">
               <div>
                  <h6 class="m-b-5">{{ $card['title'] }}</h6>
                  <h3 class="mb-0">{{ $card['value'] }}</h3>
               </div>
               <i class="material-icons-two-tone text-primary" style="font-size:{{ $compact ? '32' : '42' }}px">{{ $card['icon'] }}</i>
            </div>
         </div>
      </div>
   </div>
   @endforeach
</div>

<div class="dash-row">
   <div class="dash-col-6">
      <div class="{{ $widgetCardClass }}">
         <div class="card-body pb-0">
            <h2 class="m-0" style="font-size:{{ $compact ? '26' : '32' }}px">{{ number_format($successRate, 1) }}%</h2>
            <span class="label-cyan">Keberhasilan Automasi</span>
            @if(!$compact)
            <p class="widget-desc mb-3 mt-3">Persentase keberhasilan automasi banned SID pada periode terpilih.</p>
            @endif
         </div>
         <div id="{{ $sparkId }}" style="height:{{ $sparkHeight }}px"></div>
      </div>
   </div>
   <div class="dash-col-6">
      <div class="{{ $pieCardClass }}">
         <div class="card-body {{ $compact ? 'p-3' : '' }}">
            <h6 style="font-size:{{ $compact ? '13' : '14' }}px;margin-bottom:{{ $compact ? '4' : '6' }}px">Distribusi Status</h6>
            @if(!$compact)
            <span>Proporsi jenis status banned dan alasan utama karyawan berdasarkan data scraping Daily Banned periode terpilih.</span>
            @endif
            <div id="{{ $pieId }}" style="height:{{ $pieHeight }}px" class="{{ $compact ? '' : 'mt-2' }}"></div>
         </div>
      </div>
   </div>
</div>

<div class="dash-card">
   <div class="card-header" style="padding:12px 16px">
      <h5 style="font-size:13px">Sudah Di-Banned Hari Ini ({{ $bannedDoneRows->count() }})</h5>
   </div>
   <div class="card-body p-0">
      <div class="wishlist-scroll" style="max-height:{{ $listMaxHeight }}">
         <table class="wishlist-table">
            <thead>
               <tr>
                  <th>Karyawan</th>
                  <th>Site</th>
                  <th>Selesai</th>
               </tr>
            </thead>
            <tbody>
               @forelse($bannedDoneRows as $row)
               @php
                  $isLog = $row instanceof \App\Models\SidBannedLog;
                  $nama = $isLog ? $row->nama : ($row['nama'] ?? '—');
                  $sid = $isLog ? $row->sid : ($row['sid'] ?? '');
                  $nik = $isLog ? $row->nik : ($row['nik'] ?? '');
                  $site = $isLog ? $row->display_site : ($row['site'] ?? '');
                  $completedAt = $isLog
                     ? $row->completed_at?->format('d M Y H:i')
                     : ($row['processedAt'] ?? '—');
               @endphp
               <tr>
                  <td>
                     <span class="name">{{ $nama ?: '—' }}</span>
                     <br><span class="text-[10px] text-[#888] font-mono">{{ $sid ?: $nik }}</span>
                  </td>
                  <td>{{ $site ?: '—' }}</td>
                  <td>
                     <label class="badge badge-success">Berhasil</label>
                     @if($completedAt && $completedAt !== '—')
                     <br><span class="text-[10px] text-[#888] whitespace-nowrap">{{ $completedAt }}</span>
                     @endif
                  </td>
               </tr>
               @empty
               <tr><td colspan="3" class="text-center py-6 text-[#888]">Belum ada karyawan yang di-banned pada hari ini</td></tr>
               @endforelse
            </tbody>
         </table>
      </div>
   </div>
</div>

@push('scripts')
<script>
(function () {
   if (typeof ApexCharts === 'undefined') return;
   var CYAN = '#01b0c6';
   var font = 'Poppins, Helvetica, Arial, sans-serif';
   var sparkData = @json($sparkData);
   var pieLabels = @json($pieLabels);
   var pieValues = @json($pieValues);
   var sparkEl = document.querySelector('#{{ $sparkId }}');
   var pieEl = document.querySelector('#{{ $pieId }}');

   if (sparkEl) {
      new ApexCharts(sparkEl, {
         chart: { type: 'area', height: {{ $sparkHeight }}, sparkline: { enabled: true }, background: 'transparent' },
         stroke: { curve: 'smooth', width: 2 },
         fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
         colors: [CYAN],
         series: [{ data: (sparkData && sparkData.length) ? sparkData : [0, 0, 0, 0, 0] }],
         tooltip: { enabled: false }
      }).render();
   }

   if (pieEl) {
      new ApexCharts(pieEl, {
         chart: { type: 'pie', height: {{ $pieHeight }}, background: 'transparent', fontFamily: font },
         labels: pieLabels.length ? pieLabels : ['No Data'],
         series: pieValues.length ? pieValues : [1],
         colors: pieLabels.length ? undefined : ['#e0e0e0'],
         dataLabels: { enabled: true, style: { fontSize: '10px' } },
         legend: { show: {{ $compact ? 'false' : 'true' }} },
         theme: { monochrome: { enabled: pieLabels.length > 0, color: CYAN } }
      }).render();
   }
})();
</script>
@endpush
@endif
