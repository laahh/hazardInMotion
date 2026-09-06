@extends('control-room.layouts.app')

@section('page-title', 'Dashboard')

@php
    $chipClass = [
        'sesuai' => 'ocr-chip--sesuai',
        'menggantikan' => 'ocr-chip--ganti',
        'tidak_hadir' => 'ocr-chip--absen',
        'tidak_dijadwalkan' => 'ocr-chip--unplanned',
        'anomali' => 'ocr-chip--anomali',
    ];
    $shiftCardClass = [
        'sesuai' => 'is-sesuai',
        'menggantikan' => 'is-ganti',
        'tidak_hadir' => 'is-absen',
        'tidak_dijadwalkan' => 'is-unplanned',
        'anomali' => 'is-anomali',
    ];
    $statusLabel = [
        'sesuai' => 'Sesuai',
        'menggantikan' => 'Menggantikan',
        'tidak_hadir' => 'Tidak Hadir',
        'tidak_dijadwalkan' => 'Tidak Dijadwalkan',
        'anomali' => 'Anomali',
    ];
    $maxCoverage = max(1, ...array_column($mock['coverageRanking'], 'score'));
    $maxGoldenRule = max(1, ...array_column($mock['highlight']['goldenRules'], 'count'));
    $blindspotPct = $mock['highlight']['blindspotTotal'] > 0
        ? round(($mock['highlight']['blindspotCount'] / $mock['highlight']['blindspotTotal']) * 100, 1)
        : 0;
    $weekRangeLabel = $weekStart->locale('id')->translatedFormat('d M').' – '.$weekEnd->locale('id')->translatedFormat('d M Y');
    $toneFor = static fn (float $value): string => match (true) {
        $value >= 100 => 'success',
        $value >= 60 => 'warning',
        default => 'danger',
    };
    $scheduleDays = $mock['schedule']['days'];
    $defaultDay = collect($scheduleDays)->firstWhere('is_today') ?? $scheduleDays[0];
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-dashboard.css') }}">
@endpush

@section('content')
    <div class="ocr-dash">
        <div class="ocr-notice" role="status">
            <i class="ri-information-line"></i>
            <span><strong>Mockup visual.</strong> Angka di halaman ini fiktif — pipeline SAP &amp; agregasi belum aktif.</span>
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
                    <span class="ocr-sync">Last update: mockup — belum ada sync asli</span>
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
                    <p class="ocr-card-kicker">Klik hari untuk membuka Detail Roster.</p>
                </div>
                <div class="ocr-legend">
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-sesuai"></span> Sesuai</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-ganti"></span> Menggantikan</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-absen"></span> Tidak Hadir</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-unplanned"></span> Tidak Dijadwalkan</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-anomali"></span> Anomali</span>
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
                                            $s1 = $day['s1'][0] ?? null;
                                            $s2 = $day['s2'][0] ?? null;
                                        @endphp
                                        <div
                                            class="ocr-cal-day{{ $day['date'] === $defaultDay['date'] ? ' is-selected' : '' }}{{ $day['is_today'] ? ' is-today' : '' }}"
                                            data-date="{{ $day['date'] }}"
                                            role="button"
                                            tabindex="0"
                                            aria-pressed="{{ $day['date'] === $defaultDay['date'] ? 'true' : 'false' }}"
                                        >
                                            <div class="ocr-cal-slots">
                                                @if ($s1)
                                                    <article class="ocr-shift-card {{ $shiftCardClass[$s1['status']] }}">
                                                        <div class="ocr-shift-meta">
                                                            <span>Shift 1</span>
                                                            <span>06:00 - 18:00</span>
                                                        </div>
                                                        <strong>{{ $s1['name'] }}</strong>
                                                        <span class="ocr-shift-status">{{ $statusLabel[$s1['status']] }}</span>
                                                    </article>
                                                @else
                                                    <div class="ocr-shift-card is-empty">Kosong</div>
                                                @endif

                                                @if ($s2)
                                                    <article class="ocr-shift-card {{ $shiftCardClass[$s2['status']] }}">
                                                        <div class="ocr-shift-meta">
                                                            <span>Shift 2</span>
                                                            <span>18:00 - 24:00</span>
                                                        </div>
                                                        <strong>{{ $s2['name'] }}</strong>
                                                        <span class="ocr-shift-status">{{ $statusLabel[$s2['status']] }}</span>
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
                    <div id="ocr-roster-body"></div>
                </aside>
            </div>
        </div>

        <div class="ocr-widget-row">
            <div class="ocr-card">
                <div class="ocr-card-header">
                    <div>
                        <h6>Pencapaian Personil</h6>
                        <p class="ocr-card-kicker">Kehadiran · SAP · TBC</p>
                    </div>
                </div>
                <div class="ocr-card-body">
                    <div class="ocr-achieve-list">
                        @foreach ($mock['achievement'] as $row)
                            <div class="ocr-achieve-item">
                                <div>
                                    <p class="ocr-achieve-name">{{ $row['name'] }}</p>
                                    <p class="ocr-achieve-meta">{{ $row['date'] }} · {{ $row['attendance'] }}</p>
                                </div>
                                <strong>{{ number_format($row['attendance_pct'], 0) }}%</strong>
                                <div class="ocr-metric-row">
                                    <span>Kehadiran</span>
                                    <div class="ocr-track is-{{ $toneFor($row['attendance_pct']) }}"><span style="width: {{ min(100, $row['attendance_pct']) }}%"></span></div>
                                    <span>{{ number_format($row['attendance_pct'], 0) }}%</span>
                                </div>
                                <div class="ocr-metric-row">
                                    <span>SAP</span>
                                    <div class="ocr-track is-{{ $toneFor($row['sap']) }}"><span style="width: {{ min(100, $row['sap']) }}%"></span></div>
                                    <span>{{ number_format($row['sap'], 0) }}%</span>
                                </div>
                                <div class="ocr-metric-row">
                                    <span>TBC</span>
                                    @if ($row['tbc'] !== null)
                                        <div class="ocr-track is-{{ $toneFor($row['tbc']) }}"><span style="width: {{ min(100, $row['tbc']) }}%"></span></div>
                                        <span>{{ number_format($row['tbc'], 0) }}%</span>
                                    @else
                                        <div class="ocr-track"><span style="width: 0"></span></div>
                                        <span>—</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="ocr-card">
                <div class="ocr-card-header">
                    <div>
                        <h6>Top 5 Site</h6>
                        <p class="ocr-card-kicker">Coverage score · non-kritis ×1 · kritis ×2</p>
                    </div>
                </div>
                <div class="ocr-card-body">
                    <div class="ocr-rank-list">
                        @foreach ($mock['coverageRanking'] as $row)
                            <div class="ocr-rank-item {{ $row['rank'] <= 3 ? 'is-top' : '' }}">
                                <span class="ocr-rank-badge">{{ $row['rank'] }}</span>
                                <div>
                                    <p class="ocr-rank-name">{{ $row['name'] }}</p>
                                    <p class="ocr-rank-meta">{{ $row['non_critical'] }} non-kritis · {{ $row['critical'] }} kritis</p>
                                </div>
                                <span class="ocr-rank-score">{{ $row['score'] }}</span>
                                <div class="ocr-track is-primary"><span style="width: {{ ($row['score'] / $maxCoverage) * 100 }}%"></span></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

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
@endsection

@push('scripts')
    <script>
        (function () {
            var pareto = @json($mock['pareto']);
            var quality = @json($mock['quality']);
            var scheduleDays = @json($mock['schedule']['days']);
            var statusLabel = @json($statusLabel);
            var chipClass = @json($chipClass);
            var shiftCardClass = @json($shiftCardClass);
            var defaultDate = @json($defaultDay['date']);

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
                    + '<dl class="ocr-roster-fields">'
                    + '<div><dt>Nama</dt><dd>' + escapeHtml(person.name || '—') + '</dd></div>'
                    + '<div><dt>Jabatan</dt><dd>' + escapeHtml(person.jabatan || '—') + '</dd></div>'
                    + '<div><dt>Lokasi</dt><dd>' + escapeHtml(person.lokasi || '—') + '</dd></div>'
                    + '<div><dt>Catatan</dt><dd>' + escapeHtml(person.catatan || '—') + '</dd></div>'
                    + '</dl></div>';
            }

            function renderRoster(day) {
                return '<div class="ocr-roster-stack">'
                    + renderPerson('Shift 1 | 06:00 - 18:00', (day.s1 || [])[0])
                    + renderPerson('Shift 2 | 18:00 - 24:00', (day.s2 || [])[0])
                    + '</div>';
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
                    chart: { type: 'line', height: 260, toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } },
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
        })();
    </script>
@endpush
