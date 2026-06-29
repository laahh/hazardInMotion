@php
   $period = $banned['period'] ?? [];
   $detailQuery = $detailQuery ?? [];
   $periodLabel = !empty($period['filter_date'])
      ? \Carbon\Carbon::parse($period['filter_date'])->format('d M Y')
      : 'Semua Tanggal';
@endphp

<section class="ab-overview-section">
   <div class="ab-overview-section-head">
      <div>
         <h2 class="ab-overview-section-title">Monitoring Banned</h2>
         <p class="ab-overview-section-sub">Daily banned & automasi SID &bull; {{ $periodLabel }}</p>
      </div>
      <a href="{{ route('auto-banned.banned-monitoring.index', $detailQuery) }}" class="ab-overview-detail-link">
         Lihat detail <span class="material-symbols-outlined text-sm">arrow_forward</span>
      </a>
   </div>

   @include('AutoBanned.partials.banned-monitoring-body', [
      'stats' => $banned['stats'] ?? [],
      'chartData' => $banned['chartData'] ?? [],
      'bannedRows' => $banned['bannedRows'] ?? [],
      'logRows' => $banned['logRows'] ?? [],
      'scrTableAvailable' => $banned['scrTableAvailable'] ?? false,
      'compact' => true,
      'sparkId' => 'ab-overview-banned-spark',
      'pieId' => 'ab-overview-banned-pie',
   ])
</section>
