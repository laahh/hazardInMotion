@php
    $mode = $mode ?? 'weekly';
    $panel = $panel ?? ['kpi' => ['total' => 0, 'covered' => 0, 'uncovered' => 0, 'percent' => 0], 'rows' => [], 'attention' => []];
    $isDaily = $mode === 'daily';
    $kpi = $panel['kpi'];
    $rows = $panel['rows'];
    $attention = $panel['attention'];
    $weekDays = $panel['week_days'] ?? [];
    $selectedDate = (string) ($panel['selected_date'] ?? '');
    $criticalCount = (int) ($panel['critical_count'] ?? collect($rows)->where('is_critical', true)->count());
    $noncriticalCount = (int) ($panel['noncritical_count'] ?? max(0, count($rows) - $criticalCount));
    $selectedDay = collect($weekDays)->firstWhere('date', $selectedDate);
    $selectedDateLabel = is_array($selectedDay)
        ? $selectedDay['label'].', '.$selectedDay['display']
        : $selectedDate;
    $ring = max(0, min(100, (float) $kpi['percent']));
    $tableId = 'ocr-cov-table-'.$mode;
    $searchId = 'ocr-cov-q-'.$mode;
    $titleId = 'ocr-cov-table-title-'.$mode;
    $colspan = $isDaily ? 6 : 7;
    $kpiSubTotal = $isDaily ? 'Semua lokasi pada tanggal terpilih' : 'Lokasi yang dipantau';
    $kpiSubCovered = $isDaily ? 'Ada ≥1 SAP pada tanggal ini' : 'Sudah ada ≥1 SAP minggu ini';
    $kpiSubUncovered = $isDaily ? 'Belum ada SAP pada tanggal ini' : 'Lokasi belum ter-cover';
    $kpiSubPercent = $isDaily
        ? 'dari '.$kpi['total'].' lokasi pada tanggal terpilih'
        : 'dari total '.$kpi['total'].' lokasi';
    $tableKicker = $isDaily
        ? $selectedDateLabel.' · 1 SAP pada tanggal ini cukup'
        : $weekRangeLabel.' · 1 SAP dalam minggu cukup';
    $attnKicker = $isDaily
        ? 'Area kritis / high risk tanpa SAP pada tanggal terpilih'
        : 'Area kritis / high risk yang belum ter-cover minggu ini';
    $flagLabel = static function (array $row): string {
        return str_contains(mb_strtolower((string) ($row['lokasi'] ?? '')), 'risk') ? 'High Risk' : 'Kritis';
    };
@endphp
<div
    class="ocr-cov-panel"
    data-cov-panel="{{ $mode }}"
    data-selected-date="{{ $selectedDate }}"
    @if (! $visible) hidden @endif
>
    @if ($isDaily && $weekDays !== [])
        <div class="ocr-cov-daywrap">
            <p class="ocr-cov-daywrap-label">Tanggal coverage</p>
            <div class="ocr-cov-daybar" role="radiogroup" aria-label="Pilih tanggal coverage harian">
                @foreach ($weekDays as $day)
                    <button
                        type="button"
                        role="radio"
                        data-cov-day="{{ $day['date'] }}"
                        data-cov-day-label="{{ $day['label'] }}"
                        data-cov-day-display="{{ $day['display'] }}"
                        aria-checked="{{ ! empty($day['selected']) ? 'true' : 'false' }}"
                        class="{{ ! empty($day['selected']) ? 'is-active' : '' }}{{ ! empty($day['is_future']) ? ' is-future' : '' }}"
                    >
                        <span>{{ $day['label'] }}</span>
                        <small>{{ $day['display'] }}{{ ! empty($day['is_today']) ? ' · Hari ini' : '' }}</small>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="ocr-kpi-grid ocr-cov-kpi">
        <div class="ocr-card ocr-cov-stat">
            <div class="ocr-cov-stat-top">
                <span class="ocr-kpi-icon is-primary" aria-hidden="true"><i class="ri-map-pin-line"></i></span>
            </div>
            <p class="ocr-kpi-value" data-cov-kpi="total">{{ $kpi['total'] }}</p>
            <p class="ocr-kpi-label">Total Lokasi</p>
            <p class="ocr-kpi-sub" data-cov-kpi-sub="total">{{ $kpiSubTotal }}</p>
        </div>
        <div class="ocr-card ocr-cov-stat">
            <div class="ocr-cov-stat-top">
                <span class="ocr-kpi-icon is-success" aria-hidden="true"><i class="ri-checkbox-circle-line"></i></span>
            </div>
            <p class="ocr-kpi-value" data-cov-kpi="covered">{{ $kpi['covered'] }}</p>
            <p class="ocr-kpi-label">Covered</p>
            <p class="ocr-kpi-sub" data-cov-kpi-sub="covered">{{ $kpiSubCovered }}</p>
        </div>
        <div class="ocr-card ocr-cov-stat">
            <div class="ocr-cov-stat-top">
                <span class="ocr-kpi-icon is-danger" aria-hidden="true"><i class="ri-error-warning-line"></i></span>
            </div>
            <p class="ocr-kpi-value" data-cov-kpi="uncovered">{{ $kpi['uncovered'] }}</p>
            <p class="ocr-kpi-label">Belum Ter-cover</p>
            <p class="ocr-kpi-sub" data-cov-kpi-sub="uncovered">{{ $kpiSubUncovered }}</p>
        </div>
        <div class="ocr-card ocr-cov-stat">
            <div class="ocr-cov-stat-top">
                <span class="ocr-cov-ring" aria-hidden="true">
                    <svg viewBox="0 0 36 36">
                        <path class="ocr-cov-ring-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
                        <path class="ocr-cov-ring-fg" data-cov-ring stroke-dasharray="{{ $ring }}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
                    </svg>
                </span>
            </div>
            <p class="ocr-kpi-value" data-cov-kpi="percent">{{ number_format($kpi['percent'], 1) }}%</p>
            <p class="ocr-kpi-label">Coverage %</p>
            <p class="ocr-kpi-sub" data-cov-kpi-sub="percent">{{ $kpiSubPercent }}</p>
        </div>
    </div>

    <div class="ocr-cov-grid">
        <div class="ocr-card ocr-cov-table-card">
            <div class="ocr-card-header">
                <div>
                    <h6 id="{{ $titleId }}">Detail Lokasi Belum Ter-cover</h6>
                    <p class="ocr-card-kicker" data-cov-table-kicker>{{ $tableKicker }} · {{ $coverageScope }}</p>
                </div>
            </div>
            <div class="ocr-cov-toolbar">
                <div class="ocr-cov-filters">
                    @if ($isDaily)
                        <div class="ocr-seg ocr-cov-kind" role="group" aria-label="Filter jenis lokasi">
                            <button type="button" class="is-active" data-cov-kind="all">Semua ({{ count($rows) }})</button>
                            <button type="button" data-cov-kind="critical">Kritis ({{ $criticalCount }})</button>
                            <button type="button" data-cov-kind="noncritical">Non-kritis ({{ $noncriticalCount }})</button>
                        </div>
                    @endif
                    <div class="ocr-seg ocr-cov-tabs" role="group" aria-label="Filter status coverage">
                        <button type="button" data-cov-tab="all">Semua Lokasi ({{ $kpi['total'] }})</button>
                        <button type="button" data-cov-tab="covered">Covered ({{ $kpi['covered'] }})</button>
                        <button type="button" class="is-active" data-cov-tab="uncovered">Belum Ter-cover ({{ $kpi['uncovered'] }})</button>
                    </div>
                </div>
                <label class="ocr-cov-search">
                    <i class="ri-search-line" aria-hidden="true"></i>
                    <span class="visually-hidden">Cari lokasi</span>
                    <input type="search" id="{{ $searchId }}" placeholder="Cari lokasi…" autocomplete="off">
                </label>
            </div>
            <div class="ocr-card-body ocr-card-body--flush">
                <div class="table-responsive ocr-cov-scroll">
                    <table class="ocr-heat ocr-cov-table" id="{{ $tableId }}">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Area / Site</th>
                                <th>Lokasi</th>
                                <th>Detail Lokasi</th>
                                <th>Status</th>
                                @if ($isDaily)
                                    <th>Keterangan</th>
                                @else
                                    <th>Terakhir Ter-cover</th>
                                    <th>Missed / Gap</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $index => $row)
                                <tr
                                    data-covered="{{ $row['covered'] ? '1' : '0' }}"
                                    data-critical="{{ ! empty($row['is_critical']) ? '1' : '0' }}"
                                    data-covered-dates="{{ implode(',', $row['covered_dates'] ?? []) }}"
                                    data-site="{{ $row['site'] }}"
                                    data-lokasi="{{ $row['lokasi'] }}"
                                    data-detail="{{ $row['detail_lokasi'] }}"
                                    data-search="{{ mb_strtolower($row['site'].' '.$row['lokasi'].' '.$row['detail_lokasi']) }}"
                                >
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <span class="ocr-cov-site">
                                            <span class="ocr-cov-dot {{ $row['covered'] ? 'is-ok' : 'is-gap' }}"></span>
                                            {{ $row['site'] }}
                                        </span>
                                    </td>
                                    <td class="ocr-cov-name">
                                        {{ $row['lokasi'] }}
                                        @if ($row['is_critical'])
                                            <span class="ocr-cov-flag">{{ $flagLabel($row) }}</span>
                                        @endif
                                    </td>
                                    <td class="ocr-cov-detail">{{ $row['detail_lokasi'] !== '' ? $row['detail_lokasi'] : '—' }}</td>
                                    <td>
                                        @if ($row['covered'])
                                            <span class="ocr-cov-pill is-ok">Ter-cover</span>
                                        @else
                                            <span class="ocr-cov-pill is-gap">Belum ter-cover</span>
                                        @endif
                                    </td>
                                    @if ($isDaily)
                                        <td class="{{ $row['covered'] ? '' : 'ocr-cov-gap' }}" data-cov-gap>{{ $row['gap_label'] }}</td>
                                    @else
                                        <td>{{ $coverageLastAt($row['last_at']) }}</td>
                                        <td class="{{ $row['covered'] ? '' : 'ocr-cov-gap' }}">{{ $row['gap_label'] }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr class="ocr-cov-empty-row">
                                    <td colspan="{{ $colspan }}" class="text-secondary-light">
                                        Belum ada master lokasi untuk filter ini.
                                    </td>
                                </tr>
                            @endforelse
                            <tr class="ocr-cov-empty-filter" hidden>
                                <td colspan="{{ $colspan }}" class="text-secondary-light">Tidak ada lokasi yang cocok dengan filter.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <aside class="ocr-card ocr-cov-attention">
            <div class="ocr-card-header">
                <div class="ocr-cov-attn-title">
                    <span class="ocr-cov-attn-icon" aria-hidden="true"><i class="ri-alert-fill"></i></span>
                    <div>
                        <h6>Perlu Perhatian</h6>
                        <p class="ocr-card-kicker" data-cov-attn-kicker>{{ $attnKicker }}</p>
                    </div>
                </div>
            </div>
            <div class="ocr-card-body" data-cov-attention-body>
                @forelse ($attention as $item)
                    <div class="ocr-cov-attn-row">
                        <div>
                            <strong>{{ $item['detail_lokasi'] !== '' ? $item['detail_lokasi'] : $item['lokasi'] }}</strong>
                            <span>{{ $item['lokasi'] }} · {{ $item['site'] }}</span>
                        </div>
                        <div class="ocr-cov-attn-meta">
                            <b>{{ $item['gap_label'] }}</b>
                            <small>{{ $isDaily ? 'tanpa SAP' : 'tidak ter-cover' }}</small>
                        </div>
                    </div>
                @empty
                    <p class="text-secondary-light mb-0" data-cov-attention-empty>
                        {{ $isDaily ? 'Semua area kritis / high risk sudah ter-cover pada tanggal ini.' : 'Tidak ada area kritis yang belum tercover pada minggu ini.' }}
                    </p>
                @endforelse
            </div>
            <button type="button" class="ocr-cov-attn-more" data-cov-more @if ($kpi['uncovered'] === 0) hidden @endif>
                Lihat semua lokasi belum ter-cover
                <i class="ri-arrow-right-s-line" aria-hidden="true"></i>
            </button>
        </aside>
    </div>
</div>
