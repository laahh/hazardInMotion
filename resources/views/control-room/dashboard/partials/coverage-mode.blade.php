@php
    $mode = $mode ?? 'weekly';
    $panel = $panel ?? ['kpi' => ['total' => 0, 'covered' => 0, 'uncovered' => 0, 'percent' => 0], 'rows' => [], 'attention' => []];
    $isDaily = $mode === 'daily';
    $kpi = $panel['kpi'];
    $rows = $panel['rows'];
    $attention = $panel['attention'];
    $ring = max(0, min(100, (float) $kpi['percent']));
    $tableId = 'ocr-cov-table-'.$mode;
    $searchId = 'ocr-cov-q-'.$mode;
    $titleId = 'ocr-cov-table-title-'.$mode;
    $colspan = $isDaily ? 13 : 7;
    $kpiSubTotal = $isDaily ? 'Area kritis / high risk' : 'Lokasi yang dipantau';
    $kpiSubCovered = $isDaily ? 'Ada SAP setiap hari wajib' : 'Sudah ada ≥1 SAP minggu ini';
    $kpiSubUncovered = $isDaily ? 'Belum ada SAP harian' : 'Lokasi belum ter-cover';
    $kpiSubPercent = $isDaily
        ? 'dari '.$kpi['total'].' area wajib harian'
        : 'dari total '.$kpi['total'].' lokasi';
    $tableKicker = $isDaily
        ? $weekRangeLabel.' · wajib SAP tiap hari yang sudah lewat'
        : $weekRangeLabel.' · 1 SAP dalam minggu cukup';
    $attnKicker = $isDaily
        ? 'Area kritis / high risk tanpa SAP harian lengkap'
        : 'Area kritis / high risk yang belum ter-cover minggu ini';
@endphp
<div class="ocr-cov-panel" data-cov-panel="{{ $mode }}" @if (! $visible) hidden @endif>
    <div class="ocr-kpi-grid ocr-cov-kpi">
        <div class="ocr-card ocr-cov-stat">
            <div class="ocr-cov-stat-top">
                <span class="ocr-kpi-icon is-primary" aria-hidden="true"><i class="ri-map-pin-line"></i></span>
            </div>
            <p class="ocr-kpi-value">{{ $kpi['total'] }}</p>
            <p class="ocr-kpi-label">Total Lokasi</p>
            <p class="ocr-kpi-sub">{{ $kpiSubTotal }}</p>
        </div>
        <div class="ocr-card ocr-cov-stat">
            <div class="ocr-cov-stat-top">
                <span class="ocr-kpi-icon is-success" aria-hidden="true"><i class="ri-checkbox-circle-line"></i></span>
            </div>
            <p class="ocr-kpi-value">{{ $kpi['covered'] }}</p>
            <p class="ocr-kpi-label">Covered</p>
            <p class="ocr-kpi-sub">{{ $kpiSubCovered }}</p>
        </div>
        <div class="ocr-card ocr-cov-stat">
            <div class="ocr-cov-stat-top">
                <span class="ocr-kpi-icon is-danger" aria-hidden="true"><i class="ri-error-warning-line"></i></span>
            </div>
            <p class="ocr-kpi-value">{{ $kpi['uncovered'] }}</p>
            <p class="ocr-kpi-label">Belum Ter-cover</p>
            <p class="ocr-kpi-sub">{{ $kpiSubUncovered }}</p>
        </div>
        <div class="ocr-card ocr-cov-stat">
            <div class="ocr-cov-stat-top">
                <span class="ocr-cov-ring" aria-hidden="true">
                    <svg viewBox="0 0 36 36">
                        <path class="ocr-cov-ring-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
                        <path class="ocr-cov-ring-fg" stroke-dasharray="{{ $ring }}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
                    </svg>
                </span>
            </div>
            <p class="ocr-kpi-value">{{ number_format($kpi['percent'], 1) }}%</p>
            <p class="ocr-kpi-label">Coverage %</p>
            <p class="ocr-kpi-sub">{{ $kpiSubPercent }}</p>
        </div>
    </div>

    <div class="ocr-cov-grid{{ $isDaily ? ' ocr-cov-grid--daily' : '' }}">
        <div class="ocr-card ocr-cov-table-card">
            <div class="ocr-card-header">
                <div>
                    <h6 id="{{ $titleId }}">Detail Lokasi Belum Ter-cover</h6>
                    <p class="ocr-card-kicker">{{ $tableKicker }} · {{ $coverageScope }}</p>
                </div>
            </div>
            <div class="ocr-cov-toolbar">
                <div class="ocr-seg ocr-cov-tabs" role="group" aria-label="Filter status coverage">
                    <button type="button" data-cov-tab="all">Semua Lokasi ({{ $kpi['total'] }})</button>
                    <button type="button" data-cov-tab="covered">Covered ({{ $kpi['covered'] }})</button>
                    <button type="button" class="is-active" data-cov-tab="uncovered">Belum Ter-cover ({{ $kpi['uncovered'] }})</button>
                </div>
                <label class="ocr-cov-search">
                    <i class="ri-search-line" aria-hidden="true"></i>
                    <span class="visually-hidden">Cari lokasi</span>
                    <input type="search" id="{{ $searchId }}" placeholder="Cari lokasi…" autocomplete="off">
                </label>
            </div>
            @if ($isDaily)
                <p class="ocr-cov-day-legend">
                    <span><span class="ocr-cov-swatch is-ok" aria-hidden="true"><i class="ri-check-line"></i></span> ada SAP</span>
                    <span><span class="ocr-cov-swatch is-miss" aria-hidden="true"><i class="ri-close-line"></i></span> tanpa SAP</span>
                    <span><span class="ocr-cov-swatch is-pending" aria-hidden="true"></span> belum lewat</span>
                </p>
            @endif
            <div class="ocr-card-body ocr-card-body--flush">
                <div class="table-responsive ocr-cov-scroll">
                    <table class="ocr-heat ocr-cov-table{{ $isDaily ? ' ocr-cov-table--daily' : '' }}" id="{{ $tableId }}">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Area / Site</th>
                                <th>Lokasi</th>
                                <th>Detail Lokasi</th>
                                @if ($isDaily)
                                    @foreach (($rows[0]['day_marks'] ?? []) as $mark)
                                        <th class="ocr-cov-dow" scope="col" title="{{ $mark['date'] }}">{{ $mark['label'] }}</th>
                                    @endforeach
                                    @if (($rows[0]['day_marks'] ?? []) === [])
                                        @foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $dow)
                                            <th class="ocr-cov-dow" scope="col">{{ $dow }}</th>
                                        @endforeach
                                    @endif
                                    <th class="ocr-cov-score-head">SAP</th>
                                @endif
                                <th>Status</th>
                                @unless ($isDaily)
                                    <th>Terakhir Ter-cover</th>
                                    <th>Missed / Gap</th>
                                @endunless
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $index => $row)
                                <tr
                                    data-covered="{{ $row['covered'] ? '1' : '0' }}"
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
                                            <span class="ocr-cov-flag">{{ str_contains(mb_strtolower($row['lokasi']), 'risk') ? 'High Risk' : 'Kritis' }}</span>
                                        @endif
                                    </td>
                                    <td class="ocr-cov-detail">{{ $row['detail_lokasi'] !== '' ? $row['detail_lokasi'] : '—' }}</td>
                                    @if ($isDaily)
                                        @foreach ($row['day_marks'] as $mark)
                                            @php
                                                $dayTitle = $mark['label'].' '.$mark['date'].' · '.($mark['state'] === 'ok' ? 'ada SAP' : ($mark['state'] === 'miss' ? 'tanpa SAP' : 'belum lewat'));
                                            @endphp
                                            <td class="ocr-cov-cell">
                                                <span class="ocr-cov-swatch is-{{ $mark['state'] }}" title="{{ $dayTitle }}">
                                                    @if ($mark['state'] === 'ok')
                                                        <i class="ri-check-line" aria-hidden="true"></i>
                                                    @elseif ($mark['state'] === 'miss')
                                                        <i class="ri-close-line" aria-hidden="true"></i>
                                                    @endif
                                                    <span class="visually-hidden">{{ $dayTitle }}</span>
                                                </span>
                                            </td>
                                        @endforeach
                                        <td class="ocr-cov-score {{ $row['covered'] ? 'is-ok' : 'is-gap' }}" title="Terakhir ter-cover: {{ $coverageLastAt($row['last_at']) }}">
                                            <b>{{ (int) ($row['covered_days'] ?? 0) }}</b><span>/{{ (int) ($row['required_days'] ?? 0) }}</span>
                                        </td>
                                    @endif
                                    <td>
                                        @if ($row['covered'])
                                            <span class="ocr-cov-pill is-ok">Ter-cover</span>
                                        @else
                                            <span class="ocr-cov-pill is-gap">Belum ter-cover</span>
                                        @endif
                                    </td>
                                    @unless ($isDaily)
                                        <td>{{ $coverageLastAt($row['last_at']) }}</td>
                                        <td class="{{ $row['covered'] ? '' : 'ocr-cov-gap' }}">{{ $row['gap_label'] }}</td>
                                    @endunless
                                </tr>
                            @empty
                                <tr class="ocr-cov-empty-row">
                                    <td colspan="{{ $colspan }}" class="text-secondary-light">
                                        {{ $isDaily ? 'Tidak ada area kritis / high risk pada filter ini.' : 'Belum ada master lokasi untuk filter ini.' }}
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
                        <p class="ocr-card-kicker">{{ $attnKicker }}</p>
                    </div>
                </div>
            </div>
            <div class="ocr-card-body">
                @forelse ($attention as $item)
                    <div class="ocr-cov-attn-row">
                        <div>
                            <strong>{{ $item['detail_lokasi'] !== '' ? $item['detail_lokasi'] : $item['lokasi'] }}</strong>
                            <span>{{ $item['lokasi'] }} · {{ $item['site'] }}</span>
                            @if ($isDaily && ($item['day_marks'] ?? []) !== [])
                                <div class="ocr-cov-days ocr-cov-days--attn" aria-hidden="true">
                                    @foreach ($item['day_marks'] as $mark)
                                        <span class="ocr-cov-swatch is-{{ $mark['state'] }} is-sm" title="{{ $mark['label'] }}"></span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="ocr-cov-attn-meta">
                            <b>{{ $item['gap_label'] }}</b>
                            <small>{{ $isDaily ? 'tanpa SAP' : 'tidak ter-cover' }}</small>
                        </div>
                    </div>
                @empty
                    <p class="text-secondary-light mb-0">
                        {{ $isDaily ? 'Semua area kritis / high risk sudah ter-cover harian.' : 'Tidak ada area kritis yang belum tercover pada minggu ini.' }}
                    </p>
                @endforelse
            </div>
            @if ($kpi['uncovered'] > 0)
                <button type="button" class="ocr-cov-attn-more" data-cov-more>
                    Lihat semua lokasi belum ter-cover
                    <i class="ri-arrow-right-s-line" aria-hidden="true"></i>
                </button>
            @endif
        </aside>
    </div>
</div>
