<div class="card h-100 radius-8 border-0">
  <div class="card-body p-24">
    <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
      <div>
        <h6 class="mb-2 fw-bold text-lg">{{ $statistic['title'] ?? 'Matriks Site' }}</h6>
        <span class="text-sm fw-medium text-secondary-light">{{ $statistic['subtitle'] ?? 'Check-in vs Alert / Orang' }}</span>
      </div>
      <div>
        <span class="form-select form-select-sm w-auto bg-base border text-secondary-light d-inline-block pe-none">{{ $dateLabel }}</span>
      </div>
    </div>

    <div class="mt-20 d-flex justify-content-center flex-wrap gap-3">
      <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
        <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
          <iconify-icon icon="mingcute:user-follow-fill" class="icon"></iconify-icon>
        </span>
        <div>
          <span class="text-secondary-light text-sm fw-medium">Total Check-in</span>
          <h6 class="text-md fw-semibold mb-0">{{ $statistic['confirmed'] }}</h6>
        </div>
      </div>
      <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
        <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
          <iconify-icon icon="solar:danger-triangle-bold" class="icon"></iconify-icon>
        </span>
        <div>
          <span class="text-secondary-light text-sm fw-medium">Total Alert</span>
          <h6 class="text-md fw-semibold mb-0">{{ $statistic['total'] }}</h6>
        </div>
      </div>
      <div class="d-inline-flex align-items-center gap-2 p-2 radius-8 border pe-36 br-hover-primary group-item">
        <span class="bg-neutral-100 w-44-px h-44-px text-xxl radius-8 d-flex justify-content-center align-items-center text-secondary-light group-hover:bg-primary-600 group-hover:text-white">
          <iconify-icon icon="solar:chart-2-bold" class="icon"></iconify-icon>
        </span>
        <div>
          <span class="text-secondary-light text-sm fw-medium">Alert / Orang</span>
          <h6 class="text-md fw-semibold mb-0">{{ $statistic['dismissed'] }}</h6>
        </div>
      </div>
    </div>

    <div class="mt-12 text-xs text-secondary-light text-center">
      Median check-in: <strong>{{ number_format((float) ($statistic['x_median'] ?? 0), 0) }}</strong>
      &nbsp;·&nbsp;
      Median alert/orang: <strong>{{ number_format((float) ($statistic['y_median'] ?? 0), 2) }}</strong>
    </div>

    <div class="dms-quadrant-wrap mt-16">
      <div class="dms-quadrant-y-title">Alert Intensity – Rasio Alert / Orang</div>
      <div class="dms-quadrant-axis-hint mb-4"><span>Tinggi</span></div>
      <div class="dms-quadrant-grid">
        @foreach($quadrantOrder as $qKey)
          @php
            $q = $statistic['quadrants'][$qKey] ?? [
              'label' => $qKey,
              'description' => '',
              'bg' => '#f9fafb',
              'border' => '#9CA3AF',
              'text' => '#374151',
              'icon' => 'solar:map-point-bold',
              'sites' => [],
            ];
          @endphp
          <div class="dms-quadrant-cell" style="background: {{ $q['bg'] }};">
            <div>
              <div class="dms-quadrant-cell-head">
                <span class="dms-quadrant-cell-icon" style="background: {{ $q['bg'] }}; color: {{ $q['text'] }}; border: 1.5px solid {{ $q['border'] }};">
                  <iconify-icon icon="{{ $q['icon'] }}" class="icon"></iconify-icon>
                </span>
                <div>
                  <div class="dms-quadrant-cell-title" style="color: {{ $q['text'] }};">{{ $q['label'] }}</div>
                  <div class="dms-quadrant-cell-desc">{{ $q['description'] }}</div>
                </div>
              </div>
            </div>
            <div class="dms-quadrant-sites">
              @forelse($q['sites'] ?? [] as $siteRow)
                <span
                  class="dms-quadrant-pill"
                  style="color: {{ $q['text'] }};"
                  title="Check-in: {{ number_format($siteRow['checkin_count'] ?? 0) }} | Alert: {{ number_format($siteRow['alert_count'] ?? 0) }} | Rasio: {{ number_format((float) ($siteRow['ratio'] ?? 0), 2) }} | WoW: {{ ($siteRow['wow'] ?? 0) >= 0 ? '+' : '' }}{{ number_format((float) ($siteRow['wow'] ?? 0), 2) }}"
                >{{ $siteRow['site'] ?? '-' }} {{ $siteRow['arrow'] ?? '' }}</span>
              @empty
                <span class="text-xs text-secondary-light">—</span>
              @endforelse
            </div>
          </div>
        @endforeach
        <div class="dms-quadrant-center" title="Overall: {{ $statistic['confirmed'] }} check-in, {{ $statistic['total'] }} alert, {{ $statistic['dismissed'] }} alert/orang">Overall</div>
      </div>
      <div class="dms-quadrant-axis-hint"><span>Rendah</span><span>Tinggi</span></div>
      <div class="dms-quadrant-x-title">Exposure – Total Orang Check-in</div>
    </div>
  </div>
</div>
