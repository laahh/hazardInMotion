@extends('control-room.layouts.app')

@section('page-title', 'Dashboard')

@php
    $chipClass = [
        'sesuai' => 'ocr-chip--sesuai',
        'menggantikan' => 'ocr-chip--ganti',
        'tidak_hadir' => 'ocr-chip--absen',
        'tidak_dijadwalkan' => 'ocr-chip--unplanned',
        'anomali' => 'ocr-chip--anomali',
        'belum_absen' => 'ocr-chip--rencana',
    ];
    $shiftCardClass = [
        'sesuai' => 'is-sesuai',
        'menggantikan' => 'is-ganti',
        'tidak_hadir' => 'is-absen',
        'tidak_dijadwalkan' => 'is-unplanned',
        'anomali' => 'is-anomali',
        'belum_absen' => 'is-rencana',
    ];
    $statusLabel = [
        'sesuai' => 'Sesuai',
        'menggantikan' => 'Menggantikan',
        'tidak_hadir' => 'Tidak Hadir',
        'tidak_dijadwalkan' => 'Tidak Dijadwalkan',
        'anomali' => 'Anomali',
        'belum_absen' => 'Belum absen',
    ];
    $maxGoldenRule = max(1, ...array_column($mock['highlight']['goldenRules'], 'count'));
    $blindspotPct = $mock['highlight']['blindspotTotal'] > 0
        ? round(($mock['highlight']['blindspotCount'] / $mock['highlight']['blindspotTotal']) * 100, 1)
        : 0;
    $weekRangeLabel = $weekStart->locale('id')->translatedFormat('d M').' – '.$weekEnd->locale('id')->translatedFormat('d M Y');
    $heatClass = static function (?float $value): string {
        if ($value === null) {
            return 'is-heat-empty';
        }
        if ($value >= 100) {
            return 'is-heat-100';
        }
        if ($value >= 60) {
            return 'is-heat-mid';
        }

        return 'is-heat-low';
    };
    $heatLabel = static function (?float $value): string {
        return $value === null ? '—' : number_format($value, 0).'%';
    };
    $scheduleDays = $schedule['days'];
    $defaultDay = collect($scheduleDays)->firstWhere('is_today')
        ?? collect($scheduleDays)->first(fn (array $day): bool => $day['s1'] !== [] || $day['s2'] !== [])
        ?? $scheduleDays[0];
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-dashboard.css') }}">
@endpush

@section('content')
    <div class="ocr-dash">
        <div class="ocr-notice" role="status">
            <i class="ri-information-line"></i>
            <span><strong>Sebagian mockup.</strong> KPI, % SAP, % TBC, coverage, Pareto, dan kualitas masih fiktif. Nama/tanggal/check-in RFID memakai jadwal nyata. Tombol Detail memuat laporan SAP (hazard, inspeksi, observasi, OAK) dari OBDS pada jendela jaga.</span>
        </div>

        <form method="GET" class="ocr-card">
            <div class="ocr-toolbar">
                <div class="ocr-toolbar-left">
                    <div>
                        <label for="ocr-site">Site</label>
                        <select name="site" id="ocr-site" class="form-control" onchange="this.form.submit()">
                            @foreach ($sites as $siteOption)
                                <option value="{{ $siteOption->value }}" @selected($site === $siteOption)>{{ $siteOption->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="week" value="{{ $week }}">

                    <div>
                        <label>Minggu</label>
                        <div class="ocr-week-stepper">
                            <a href="{{ route('control-room.dashboard', ['site' => $site->value, 'year' => $prevYear, 'week' => $prevWeek]) }}" aria-label="Minggu sebelumnya">
                                <i class="ri-arrow-left-s-line"></i>
                            </a>
                            <div class="ocr-week-label">
                                <strong>{{ $weekRangeLabel }}</strong>
                                <span>Minggu {{ $week }} · {{ $year }}</span>
                            </div>
                            <a href="{{ route('control-room.dashboard', ['site' => $site->value, 'year' => $nextYear, 'week' => $nextWeek]) }}" aria-label="Minggu berikutnya">
                                <i class="ri-arrow-right-s-line"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="ocr-toolbar-right">
                    <span class="ocr-sync">Panel jadwal: data asli · widget lain: mockup</span>
                </div>
            </div>
        </form>

        <div class="ocr-kpi-grid">
            @foreach ($mock['kpi'] as $card)
                <div class="ocr-card ocr-kpi" title="{{ $card['formula'] }}" data-bs-toggle="tooltip" data-bs-title="{{ $card['formula'] }}">
                    <div class="ocr-kpi-top">
                        <div class="ocr-kpi-icon is-{{ $card['color'] }}">
                            <i class="{{ $card['icon'] }}"></i>
                        </div>
                        @if ($card['delta'] > 0)
                            <span class="ocr-delta is-up"><i class="ri-arrow-up-line"></i> {{ $card['delta'] }}</span>
                        @elseif ($card['delta'] < 0)
                            <span class="ocr-delta is-down"><i class="ri-arrow-down-line"></i> {{ abs($card['delta']) }}</span>
                        @else
                            <span class="ocr-delta is-flat">—</span>
                        @endif
                    </div>
                    <p class="ocr-kpi-value">{{ $card['value'] }}</p>
                    <p class="ocr-kpi-label">{{ $card['label'] }}</p>
                    <p class="ocr-kpi-sub">{{ $card['deltaLabel'] }}</p>
                    <div class="ocr-track is-{{ $card['color'] }}"><span style="width: {{ min(100, $card['progress']) }}%"></span></div>
                </div>
            @endforeach
        </div>

        <div class="ocr-card ocr-cal-card">
            <div class="ocr-card-header">
                <div>
                    <h6>Penjadwalan — Rencana vs Aktual</h6>
                    <p class="ocr-card-kicker">Data jadwal site terpilih. Klik hari untuk Detail Roster.</p>
                </div>
                <div class="ocr-legend">
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-sesuai"></span> Sesuai</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-ganti"></span> Menggantikan</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-absen"></span> Tidak Hadir</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-unplanned"></span> Tidak Dijadwalkan</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-anomali"></span> Anomali</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-rencana"></span> Belum absen</span>
                </div>
            </div>

            <div class="ocr-cal-layout" id="ocr-cal-layout">
                <div class="ocr-cal-main">
                    <div class="ocr-cal-scroll">
                        <div class="ocr-cal-board">
                            <div class="ocr-cal-head">
                                <div></div>
                                <div class="ocr-cal-day-heads">
                                    @foreach ($scheduleDays as $day)
                                        <div class="ocr-cal-day-head{{ $day['date'] === $defaultDay['date'] ? ' is-selected' : '' }}{{ $day['is_today'] ? ' is-today' : '' }}" data-head-date="{{ $day['date'] }}">
                                            <span>{{ $day['weekday'] }}</span>
                                            <strong>{{ $day['day_number'] }} {{ $day['month_short'] }}</strong>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="ocr-cal-body" id="ocr-cal-body">
                                <div class="ocr-cal-gutter" aria-hidden="true">
                                    <span style="top: 0">06:00</span>
                                    <span style="top: 33.333%">12:00</span>
                                    <span style="top: 66.666%">18:00</span>
                                    <span style="top: 100%">24:00</span>
                                    <span class="ocr-now-label" id="ocr-now-label">15:00</span>
                                </div>
                                <div class="ocr-cal-track">
                                    <div class="ocr-now-line" id="ocr-now-line"></div>
                                    <div class="ocr-cal-days" role="list">
                                    @foreach ($scheduleDays as $day)
                                        @php
                                            $s1People = $day['s1'];
                                            $s2People = $day['s2'];
                                        @endphp
                                        <div
                                            class="ocr-cal-day{{ $day['date'] === $defaultDay['date'] ? ' is-selected' : '' }}{{ $day['is_today'] ? ' is-today' : '' }}"
                                            data-date="{{ $day['date'] }}"
                                            role="button"
                                            tabindex="0"
                                            aria-pressed="{{ $day['date'] === $defaultDay['date'] ? 'true' : 'false' }}"
                                        >
                                            <div class="ocr-cal-slots">
                                                @if ($s1People !== [])
                                                    <article class="ocr-shift-card {{ $shiftCardClass[$s1People[0]['status']] ?? 'is-rencana' }}">
                                                        <div class="ocr-shift-meta">
                                                            <span>Shift 1</span>
                                                            <span>06:00 - 18:00</span>
                                                        </div>
                                                        @foreach ($s1People as $person)
                                                            <div class="ocr-shift-person">
                                                                <strong>{{ $person['name'] }}</strong>
                                                                <span class="ocr-shift-status">{{ $statusLabel[$person['status']] ?? $person['status'] }}</span>
                                                            </div>
                                                        @endforeach
                                                    </article>
                                                @else
                                                    <div class="ocr-shift-card is-empty">Kosong</div>
                                                @endif

                                                @if ($s2People !== [])
                                                    <article class="ocr-shift-card {{ $shiftCardClass[$s2People[0]['status']] ?? 'is-rencana' }}">
                                                        <div class="ocr-shift-meta">
                                                            <span>Shift 2</span>
                                                            <span>18:00 - 24:00</span>
                                                        </div>
                                                        @foreach ($s2People as $person)
                                                            <div class="ocr-shift-person">
                                                                <strong>{{ $person['name'] }}</strong>
                                                                <span class="ocr-shift-status">{{ $statusLabel[$person['status']] ?? $person['status'] }}</span>
                                                            </div>
                                                        @endforeach
                                                    </article>
                                                @else
                                                    <div class="ocr-shift-card is-empty">Kosong</div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="ocr-roster" id="ocr-roster" aria-live="polite">
                    <div class="ocr-roster-head">
                        <h6 id="ocr-roster-title">Detail Roster — {{ $defaultDay['weekday'] }}, {{ $defaultDay['day_number'] }} {{ $defaultDay['month_short'] }} {{ $defaultDay['year'] }}</h6>
                        <button type="button" class="ocr-roster-close" id="ocr-roster-close" aria-label="Tutup detail roster">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                    <div class="ocr-roster-body" id="ocr-roster-body"></div>
                </aside>
            </div>
        </div>

        <div class="ocr-widget-tables">
            <div class="ocr-card">
                <div class="ocr-card-header">
                    <div>
                        <h6>Pencapaian Personil</h6>
                        <p class="ocr-card-kicker">% pencapaian berdasarkan kehadiran sesuai jadwal, SAP berdasarkan target (1 Hazard, 1 Inspeksi, 1 Observasi/OAK), dan % TBC dari Hazard &amp; Inspeksi. Klik Detail untuk laporan SAP selama jaga.</p>
                    </div>
                </div>
                <div class="ocr-card-body ocr-card-body--flush">
                    <div class="table-responsive">
                        <table class="ocr-heat">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Actual — Nama</th>
                                    <th class="text-center">Shift</th>
                                    <th class="text-center">Kehadiran</th>
                                    <th class="text-center">% SAP</th>
                                    <th class="text-center">% TBC</th>
                                    <th class="text-center">Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($mock['achievementGroups'] as $group)
                                    @foreach ($group['rows'] as $index => $row)
                                        <tr>
                                            @if ($index === 0)
                                                <td class="ocr-heat-date" rowspan="{{ count($group['rows']) }}">{{ $group['date_label'] }}</td>
                                            @endif
                                            <td class="ocr-heat-name">{{ $row['name'] }}</td>
                                            <td class="text-center">{{ $row['shift'] }}</td>
                                            <td class="ocr-heat-cell {{ $heatClass($row['attendance_pct']) }}">{{ $heatLabel($row['attendance_pct']) }}</td>
                                            <td class="ocr-heat-cell {{ $heatClass($row['sap']) }}">{{ $heatLabel($row['sap']) }}</td>
                                            <td class="ocr-heat-cell {{ $heatClass($row['tbc']) }}">{{ $heatLabel($row['tbc']) }}</td>
                                            <td class="text-center">
                                                @if (($row['sid'] ?? '') !== '')
                                                    <button
                                                        type="button"
                                                        class="ocr-detail-btn"
                                                        data-sid="{{ $row['sid'] }}"
                                                        data-date="{{ $row['date'] }}"
                                                        data-shift="{{ $row['shift'] }}"
                                                        data-name="{{ $row['name'] }}"
                                                    >Detail</button>
                                                @else
                                                    <span class="ocr-tap-empty">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-secondary-light">Belum ada data pencapaian untuk minggu ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="ocr-card">
                <div class="ocr-card-header">
                    <div>
                        <h6>Coverage Personil</h6>
                        <p class="ocr-card-kicker">Coverage Detail Lokasi · Coverage Area Kritis</p>
                    </div>
                </div>
                <div class="ocr-card-body ocr-card-body--flush">
                    <div class="table-responsive">
                        <table class="ocr-heat ocr-heat--coverage">
                            <thead>
                                <tr>
                                    <th>Actual — Nama</th>
                                    <th class="text-center">Coverage Detail Lokasi</th>
                                    <th class="text-center">Coverage Area Kritis</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mock['personnelCoverage'] as $row)
                                    <tr class="{{ $row['lead'] ? 'is-lead' : '' }}">
                                        <td class="ocr-heat-name">{{ $row['name'] }}</td>
                                        <td class="ocr-heat-cell is-cov">{{ $row['lokasi'] }}</td>
                                        <td class="ocr-heat-cell is-cov">{{ $row['kritis'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="ocr-widget-charts">
            <div class="ocr-card">
                <div class="ocr-card-header">
                    <div>
                        <h6>Pareto Jam Laporan</h6>
                        <p class="ocr-card-kicker">Garis putus 80% kumulatif</p>
                    </div>
                    <div class="ocr-seg" role="group" aria-label="Pilih shift Pareto">
                        <button type="button" class="is-active" data-pareto="s1">S1</button>
                        <button type="button" data-pareto="s2">S2</button>
                    </div>
                </div>
                <div class="ocr-card-body"><div id="chart-pareto"></div></div>
            </div>

            <div class="ocr-card">
                <div class="ocr-card-header">
                    <div>
                        <h6>Highlight Temuan</h6>
                        <p class="ocr-card-kicker">Golden Rule · Blindspot · TBC</p>
                    </div>
                </div>
                <div class="ocr-card-body">
                    <div class="ocr-gr-list">
                        @foreach ($mock['highlight']['goldenRules'] as $gr)
                            <div class="ocr-gr-row">
                                <span>{{ $gr['name'] }}</span>
                                <strong>{{ $gr['count'] }}</strong>
                                <div class="ocr-track is-primary"><span style="width: {{ ($gr['count'] / $maxGoldenRule) * 100 }}%"></span></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="ocr-highlight-metrics">
                        <div class="ocr-metric-mini">
                            <p class="ocr-kpi-label">Blindspot</p>
                            <p class="ocr-kpi-value">{{ $mock['highlight']['blindspotCount'] }} <span class="fs-6 fw-normal text-secondary-light">/ {{ $mock['highlight']['blindspotTotal'] }}</span></p>
                            <div class="ocr-track is-danger"><span style="width: {{ $blindspotPct }}%"></span></div>
                        </div>
                        <div class="ocr-metric-mini">
                            <p class="ocr-kpi-label">Ratio TBC</p>
                            <p class="ocr-kpi-value">{{ $mock['highlight']['tbcPercentage'] }}%</p>
                            <div class="ocr-track is-warning"><span style="width: {{ min(100, $mock['highlight']['tbcPercentage']) }}%"></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row gy-4">
            <div class="col-lg-7">
                <div class="ocr-card h-100">
                    <div class="ocr-card-header">
                        <div>
                            <h6>Kualitas Temuan per Personil</h6>
                            <p class="ocr-card-kicker">Variasi 0–1 · semakin tinggi semakin beragam kategori</p>
                        </div>
                    </div>
                    <div class="ocr-card-body ocr-card-body--flush">
                        <div class="table-responsive">
                            <table class="ocr-table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">Kategori</th>
                                        <th>Variasi</th>
                                        <th class="text-center">TBC</th>
                                        <th class="text-center">GR</th>
                                        <th class="text-center">Blindspot</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($mock['quality'] as $row)
                                        <tr>
                                            <td>{{ $row['name'] }}</td>
                                            <td class="text-center">{{ $row['total_findings'] }}</td>
                                            <td class="text-center">{{ $row['distinct_categories'] }}</td>
                                            <td>
                                                <div class="ocr-variety">
                                                    <div class="ocr-track is-primary"><span style="width: {{ $row['variety_score'] * 100 }}%"></span></div>
                                                    <span>{{ number_format($row['variety_score'], 2) }}</span>
                                                </div>
                                            </td>
                                            <td class="text-center">{{ $row['tbc'] }}</td>
                                            <td class="text-center">{{ $row['gr'] }}</td>
                                            <td class="text-center">{{ $row['blindspot'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="ocr-card h-100">
                    <div class="ocr-card-header">
                        <div>
                            <h6>Volume vs Variasi</h6>
                            <p class="ocr-card-kicker">Kanan-atas = banyak temuan &amp; kategori beragam</p>
                        </div>
                    </div>
                    <div class="ocr-card-body"><div id="chart-quality-scatter"></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ocr-sap-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h6 class="modal-title" id="ocr-sap-title">Detail SAP</h6>
                        <p class="ocr-card-kicker mb-0" id="ocr-sap-meta"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="ocr-sap-filters" id="ocr-sap-filters" hidden>
                        <button type="button" class="is-active" data-sap-filter="all">Semua</button>
                        <button type="button" data-sap-filter="hazard">Hazard</button>
                        <button type="button" data-sap-filter="inspeksi">Inspeksi</button>
                        <button type="button" data-sap-filter="observasi">Observasi</button>
                        <button type="button" data-sap-filter="oak">OAK</button>
                    </div>
                    <p class="ocr-sap-status-msg" id="ocr-sap-status">Memuat laporan…</p>
                    <div class="ocr-sap-grid" id="ocr-sap-grid"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var pareto = @json($mock['pareto']);
            var quality = @json($mock['quality']);
            var scheduleDays = @json($schedule['days']);
            var statusLabel = @json($statusLabel);
            var chipClass = @json($chipClass);
            var shiftCardClass = @json($shiftCardClass);
            var defaultDate = @json($defaultDay['date']);
            var sapDetailUrl = @json(route('control-room.dashboard.sap-detail'));

            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (el) {
                new bootstrap.Tooltip(el);
            });

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function renderPerson(shiftLabel, person) {
                if (!person) {
                    return '<p class="ocr-roster-empty">Tidak ada personil.</p>';
                }

                var status = person.status || 'tidak_dijadwalkan';
                var klass = chipClass[status] || 'ocr-chip--unplanned';
                var tone = shiftCardClass[status] || 'is-unplanned';
                return '<div class="ocr-roster-person ' + tone + '">'
                    + '<div class="ocr-roster-person-head">'
                    + '<span>' + escapeHtml(shiftLabel) + '</span>'
                    + '<span class="ocr-chip ' + klass + '">' + escapeHtml(statusLabel[status] || status) + '</span>'
                    + '</div>'
                    + '<p class="ocr-roster-name">' + escapeHtml(person.name || '—') + '</p>'
                    + renderCheckinout(person.checkinout || [])
                    + '</div>';
            }

            function renderCheckinout(events) {
                var html = '<div class="ocr-roster-taps">';
                html += '<p class="ocr-roster-taps-title">Check-in / Check-out RFID</p>';
                if (!events.length) {
                    return html + '<p class="ocr-roster-taps-empty">Tidak ada tap di jendela shift (termasuk ±2 jam).</p></div>';
                }

                html += '<ol class="ocr-roster-taps-list">';
                events.forEach(function (tap) {
                    var tone = tap.type === 'in' ? 'is-in' : 'is-out';
                    var time = escapeHtml((tap.time || '—') + ' · ' + (tap.date_label || ''));
                    var gate = escapeHtml(tap.gate || '—');
                    var pass = tap.passed ? '' : ' <span class="ocr-roster-tap-fail">Tidak lolos</span>';
                    html += '<li class="' + tone + '">'
                        + '<span class="ocr-roster-tap-type">' + escapeHtml(tap.type_label || tap.type) + '</span>'
                        + '<span class="ocr-roster-tap-time">' + time + '</span>'
                        + '<span class="ocr-roster-tap-gate">' + gate + '</span>'
                        + pass
                        + '</li>';
                });
                return html + '</ol></div>';
            }

            function renderRoster(day) {
                var s1 = day.s1 || [];
                var s2 = day.s2 || [];
                var html = '<div class="ocr-roster-stack">';
                if (s1.length === 0) {
                    html += renderPerson('Shift 1 | 06:00 - 18:00', null);
                } else {
                    s1.forEach(function (person) {
                        html += renderPerson('Shift 1 | 06:00 - 18:00', person);
                    });
                }
                if (s2.length === 0) {
                    html += renderPerson('Shift 2 | 18:00 - 06:00', null);
                } else {
                    s2.forEach(function (person) {
                        html += renderPerson('Shift 2 | 18:00 - 06:00', person);
                    });
                }
                return html + '</div>';
            }

            var daysByDate = {};
            scheduleDays.forEach(function (day) { daysByDate[day.date] = day; });

            var titleEl = document.getElementById('ocr-roster-title');
            var bodyEl = document.getElementById('ocr-roster-body');
            var layoutEl = document.getElementById('ocr-cal-layout');
            var dayButtons = document.querySelectorAll('.ocr-cal-day');
            var dayHeads = document.querySelectorAll('[data-head-date]');

            function selectDay(date) {
                var day = daysByDate[date];
                if (!day) {
                    return;
                }

                titleEl.textContent = 'Detail Roster — ' + day.weekday + ', ' + day.day_number + ' ' + day.month_short + ' ' + day.year;
                bodyEl.innerHTML = renderRoster(day);
                layoutEl.classList.remove('is-roster-closed');

                dayButtons.forEach(function (btn) {
                    var active = btn.getAttribute('data-date') === date;
                    btn.classList.toggle('is-selected', active);
                    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                dayHeads.forEach(function (head) {
                    head.classList.toggle('is-selected', head.getAttribute('data-head-date') === date);
                });
            }

            dayHeads.forEach(function (head) {
                head.addEventListener('click', function () {
                    selectDay(head.getAttribute('data-head-date'));
                });
            });

            dayButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    selectDay(btn.getAttribute('data-date'));
                });
                btn.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        selectDay(btn.getAttribute('data-date'));
                    }
                });
            });

            document.getElementById('ocr-roster-close').addEventListener('click', function () {
                layoutEl.classList.add('is-roster-closed');
            });

            selectDay(defaultDate);

            function placeNowLine() {
                var now = new Date();
                var hours = now.getHours() + now.getMinutes() / 60;
                if (hours < 6 || hours >= 24) {
                    hours = 15;
                }
                var pct = ((hours - 6) / 18) * 100;
                var hour = Math.floor(hours);
                var minute = Math.round((hours - hour) * 60);
                if (minute === 60) {
                    hour += 1;
                    minute = 0;
                }
                var label = String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
                var line = document.getElementById('ocr-now-line');
                var pill = document.getElementById('ocr-now-label');
                line.style.top = pct + '%';
                pill.style.top = pct + '%';
                pill.textContent = label;
            }

            placeNowLine();
            setInterval(placeNowLine, 60000);

            function paretoOptions(series) {
                return {
                    chart: { type: 'line', height: 280, toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } },
                    stroke: { width: [0, 3], curve: 'straight' },
                    series: [
                        { name: 'Jumlah Laporan', type: 'column', data: series.map(function (row) { return row.count; }) },
                        { name: 'Kumulatif %', type: 'line', data: series.map(function (row) { return row.cumulative; }) },
                    ],
                    xaxis: { categories: series.map(function (row) { return row.hour + ':00'; }), axisBorder: { show: false } },
                    yaxis: [
                        { title: { text: 'Laporan' } },
                        { opposite: true, min: 0, max: 100, title: { text: '%' } },
                    ],
                    colors: ['#487FFF', '#FF9F29'],
                    grid: { borderColor: 'rgba(209, 213, 219, 0.4)', strokeDashArray: 4 },
                    legend: { position: 'top', horizontalAlign: 'right' },
                    dataLabels: { enabled: false },
                    annotations: {
                        yaxis: [{ y: 80, yAxisIndex: 1, borderColor: '#9CA3AF', strokeDashArray: 6, label: { text: '80%', style: { fontSize: '10px' } } }],
                    },
                };
            }

            var paretoChart = new ApexCharts(document.getElementById('chart-pareto'), paretoOptions(pareto.s1));
            paretoChart.render();

            function applyPareto(series) {
                paretoChart.updateOptions({
                    xaxis: { categories: series.map(function (row) { return row.hour + ':00'; }) },
                }, false, false);
                paretoChart.updateSeries([
                    { name: 'Jumlah Laporan', type: 'column', data: series.map(function (row) { return row.count; }) },
                    { name: 'Kumulatif %', type: 'line', data: series.map(function (row) { return row.cumulative; }) },
                ]);
            }

            document.querySelectorAll('[data-pareto]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('[data-pareto]').forEach(function (el) { el.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                    applyPareto(pareto[btn.getAttribute('data-pareto')]);
                });
            });

            new ApexCharts(document.getElementById('chart-quality-scatter'), {
                chart: { type: 'scatter', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [{
                    name: 'Personil',
                    data: quality.map(function (row) { return [row.total_findings, row.variety_score]; }),
                }],
                xaxis: { title: { text: 'Volume Temuan' }, tickAmount: 5 },
                yaxis: { title: { text: 'Variasi Score' }, min: 0, max: 1 },
                colors: ['#487FFF'],
                grid: { borderColor: 'rgba(209, 213, 219, 0.4)', strokeDashArray: 4 },
                tooltip: {
                    custom: function (opts) {
                        var row = quality[opts.dataPointIndex];
                        return '<div class="p-8 text-xs"><strong>' + row.name + '</strong><br>Volume: ' + row.total_findings + '<br>Variasi: ' + row.variety_score + '</div>';
                    },
                },
            }).render();

            var sapCards = [];
            var sapFilter = 'all';
            var sapModalEl = document.getElementById('ocr-sap-modal');
            var sapModal = sapModalEl ? new bootstrap.Modal(sapModalEl) : null;
            var sapTitle = document.getElementById('ocr-sap-title');
            var sapMeta = document.getElementById('ocr-sap-meta');
            var sapStatus = document.getElementById('ocr-sap-status');
            var sapGrid = document.getElementById('ocr-sap-grid');
            var sapFilters = document.getElementById('ocr-sap-filters');

            function setSapFilterCounts(counts) {
                document.querySelectorAll('[data-sap-filter]').forEach(function (btn) {
                    var key = btn.getAttribute('data-sap-filter');
                    var label = key === 'all' ? 'Semua' : btn.getAttribute('data-sap-filter');
                    label = {
                        all: 'Semua',
                        hazard: 'Hazard',
                        inspeksi: 'Inspeksi',
                        observasi: 'Observasi',
                        oak: 'OAK'
                    }[key] || key;
                    var n = counts && counts[key] != null ? counts[key] : 0;
                    btn.textContent = label + ' (' + n + ')';
                });
            }

            function renderSapCards() {
                var list = sapFilter === 'all' ? sapCards : sapCards.filter(function (card) {
                    return card.type === sapFilter;
                });
                if (!list.length) {
                    sapGrid.innerHTML = '';
                    sapStatus.hidden = false;
                    sapStatus.textContent = sapCards.length ? 'Tidak ada laporan untuk filter ini.' : sapStatus.textContent;
                    return;
                }
                sapStatus.hidden = true;
                sapGrid.innerHTML = list.map(function (card) {
                    var photo = card.photo_url
                        ? '<a class="ocr-sap-photo" href="' + escapeHtml(card.photo_url) + '" target="_blank" rel="noopener">'
                            + '<img src="' + escapeHtml(card.photo_url) + '" alt="Foto laporan ' + escapeHtml(card.id) + '" loading="lazy" onerror="this.parentElement.hidden=true">'
                            + '</a>'
                        : '';
                    var geotag = card.geotag ? ('GEOTAGGING Jam: ' + escapeHtml(card.geotag)) : 'Null';
                    var statusClass = (card.status || '').toLowerCase() === 'closed' ? 'is-closed' : 'is-plain';
                    return '<article class="ocr-sap-card" data-type="' + escapeHtml(card.type) + '">'
                        + photo
                        + '<p class="ocr-sap-id">' + escapeHtml(card.id) + '</p>'
                        + '<p class="ocr-sap-geotag">' + geotag + '</p>'
                        + '<p>Submit BEATS: ' + escapeHtml(card.submitted_label) + '</p>'
                        + '<p class="ocr-sap-headline">' + escapeHtml(card.headline) + '</p>'
                        + '<p>' + escapeHtml(card.subcategory) + '</p>'
                        + '<p class="ocr-sap-desc">' + escapeHtml(card.description) + '</p>'
                        + '<p>PIC: ' + escapeHtml(card.pic) + '</p>'
                        + '<p class="ocr-sap-muted">' + escapeHtml(card.pic_meta) + '</p>'
                        + '<p>Pelapor: ' + escapeHtml(card.reporter) + '</p>'
                        + '<p class="ocr-sap-muted">' + escapeHtml(card.reporter_meta) + '</p>'
                        + '<p>Lokasi: ' + escapeHtml(card.location) + '</p>'
                        + '<p>Detail Lok: ' + escapeHtml(card.location_detail) + '</p>'
                        + '<span class="ocr-sap-badge ' + statusClass + '">' + escapeHtml(card.status || '—') + '</span>'
                        + '</article>';
                }).join('');
            }

            document.querySelectorAll('[data-sap-filter]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    sapFilter = btn.getAttribute('data-sap-filter');
                    document.querySelectorAll('[data-sap-filter]').forEach(function (el) { el.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                    renderSapCards();
                });
            });

            document.querySelectorAll('.ocr-detail-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var sid = btn.getAttribute('data-sid');
                    var date = btn.getAttribute('data-date');
                    var shift = btn.getAttribute('data-shift');
                    var name = btn.getAttribute('data-name') || sid;
                    sapCards = [];
                    sapFilter = 'all';
                    sapGrid.innerHTML = '';
                    sapFilters.hidden = true;
                    sapStatus.hidden = false;
                    sapStatus.textContent = 'Memuat laporan…';
                    sapTitle.textContent = 'Detail SAP — ' + name;
                    sapMeta.textContent = shift + ' · ' + date + ' · SID ' + sid;
                    setSapFilterCounts({ all: 0, hazard: 0, inspeksi: 0, observasi: 0, oak: 0 });
                    document.querySelectorAll('[data-sap-filter]').forEach(function (el) { el.classList.toggle('is-active', el.getAttribute('data-sap-filter') === 'all'); });
                    if (sapModal) {
                        sapModal.show();
                    }

                    var url = sapDetailUrl + '?sid=' + encodeURIComponent(sid) + '&date=' + encodeURIComponent(date) + '&shift=' + encodeURIComponent(shift);
                    fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                        .then(function (result) {
                            if (!result.ok) {
                                sapStatus.textContent = 'Gagal memuat detail SAP.';
                                return;
                            }
                            var data = result.body;
                            sapCards = data.cards || [];
                            sapMeta.textContent = shift + ' · jendela ' + (data.window_start || '') + ' – ' + (data.window_end || '') + ' · SID ' + sid;
                            setSapFilterCounts(data.counts || {});
                            sapFilters.hidden = false;
                            if (!data.reachable) {
                                sapStatus.textContent = 'Sumber SAP (OBDS) tidak terjangkau.';
                                sapGrid.innerHTML = '';
                                return;
                            }
                            if (!sapCards.length) {
                                sapStatus.textContent = 'Tidak ada laporan SAP di jendela shift ini.';
                                sapGrid.innerHTML = '';
                                return;
                            }
                            var extra = data.truncated ? ' Menampilkan maksimal 40 laporan per jenis.' : '';
                            sapStatus.textContent = extra;
                            sapStatus.hidden = !extra;
                            renderSapCards();
                        })
                        .catch(function () {
                            sapStatus.textContent = 'Gagal memuat detail SAP.';
                        });
                });
            });
        })();
    </script>
@endpush
