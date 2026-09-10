@php
    $coverageDaily = $locationCoverage['daily'] ?? ['kpi' => ['total' => 0, 'covered' => 0, 'uncovered' => 0, 'percent' => 0.0], 'rows' => [], 'attention' => []];
    $coverageWeekly = $locationCoverage['weekly'] ?? ['kpi' => ['total' => 0, 'covered' => 0, 'uncovered' => 0, 'percent' => 0.0], 'rows' => [], 'attention' => []];
    $coverageScope = $site === \App\Enums\ControlRoomSiteCode::HeadOffice
        ? 'Semua site operasi'
        : $site->label();
    $coverageLastAt = static function (?string $at): string {
        if ($at === null || $at === '') {
            return '—';
        }
        try {
            return \Carbon\CarbonImmutable::parse($at)->locale('id')->translatedFormat('d M Y');
        } catch (\Throwable) {
            return $at;
        }
    };
@endphp
@if (! ($locationCoverage['loaded'] ?? false))
    <div class="ocr-notice" role="status">
        <i class="ri-error-warning-line"></i>
        <span>Sumber SAP (OBDS) tidak terjangkau. Master lokasi tetap tampil; status tercover dikosongkan.</span>
    </div>
@endif

<section class="ocr-cov" data-cov-root aria-labelledby="ocr-cov-title">
    <div class="ocr-cov-head">
        <div>
            <h6 id="ocr-cov-title">Coverage Lokasi</h6>
            <p class="ocr-card-kicker">{{ $weekRangeLabel }} · {{ $coverageScope }}</p>
        </div>
        <div class="ocr-seg ocr-cov-mode" role="tablist" aria-label="Jenis coverage lokasi">
            <button type="button" role="tab" id="ocr-cov-mode-daily" aria-selected="true" aria-controls="ocr-cov-panel-daily" data-cov-mode="daily" class="is-active">Harian</button>
            <button type="button" role="tab" id="ocr-cov-mode-weekly" aria-selected="false" aria-controls="ocr-cov-panel-weekly" data-cov-mode="weekly">Mingguan</button>
        </div>
    </div>
    <div id="ocr-cov-panel-daily">
        @include('control-room.dashboard.partials.coverage-mode', [
            'mode' => 'daily',
            'panel' => $coverageDaily,
            'visible' => true,
            'weekRangeLabel' => $weekRangeLabel,
            'coverageScope' => $coverageScope,
            'coverageLastAt' => $coverageLastAt,
        ])
    </div>
    <div id="ocr-cov-panel-weekly">
        @include('control-room.dashboard.partials.coverage-mode', [
            'mode' => 'weekly',
            'panel' => $coverageWeekly,
            'visible' => false,
            'weekRangeLabel' => $weekRangeLabel,
            'coverageScope' => $coverageScope,
            'coverageLastAt' => $coverageLastAt,
        ])
    </div>
</section>
