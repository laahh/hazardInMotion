@php
   $summaryStats = $summaryStats ?? [];
   $totalSudahDiBanned = (int) ($summaryStats['totalSudahDiBanned'] ?? 0);
   $totalMasihBanned = (int) ($summaryStats['totalMasihBanned'] ?? 0);
   $totalUnBanned = (int) ($summaryStats['totalUnBanned'] ?? 0);

   $summaryCards = [
      [
         'title' => 'Total Sudah Di-Banned',
         'value' => number_format($totalSudahDiBanned),
         'icon' => 'block',
         'accent' => '#01b0c6',
      ],
      [
         'title' => 'Total Masih Banned',
         'value' => number_format($totalMasihBanned),
         'icon' => 'gpp_bad',
         'accent' => '#e67e22',
      ],
      [
         'title' => 'Total Un Banned',
         'value' => number_format($totalUnBanned),
         'icon' => 'lock_open',
         'accent' => '#3952bc',
      ],
   ];
@endphp

<div class="ab-overview-summary">
   <p class="ab-overview-summary-note">Data kumulatif dari awal — <code>sid_banned_log</code> (SUCCESS) &amp; <code>sid_unban_log</code> (tanpa filter tanggal)</p>
   @foreach($summaryCards as $card)
   <div class="ab-overview-summary-card" style="--summary-accent: {{ $card['accent'] }}">
      <div class="ab-overview-summary-body">
         <div>
            <h6>{{ $card['title'] }}</h6>
            <h3>{{ $card['value'] }}</h3>
         </div>
         <i class="material-icons-two-tone" style="color: {{ $card['accent'] }}">{{ $card['icon'] }}</i>
      </div>
   </div>
   @endforeach
</div>
