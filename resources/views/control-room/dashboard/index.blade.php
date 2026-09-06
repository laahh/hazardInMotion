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
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('build/plugins/fullcalendar/css/main.min.css') }}">
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

        {{-- T6.2 — Panel KPI Header --}}
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

        {{-- T6.3 — Kalender minggu Rencana vs Aktual --}}
        <div class="ocr-card">
            <div class="ocr-card-header">
                <div>
                    <h6>Penjadwalan — Rencana vs Aktual</h6>
                    <p class="ocr-card-kicker">Satu orang per shift. Klik blok atau tanggal untuk roster. Outline gelap = dijadwalkan.</p>
                </div>
                <div class="ocr-legend">
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-sesuai"></span> Sesuai</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-ganti"></span> Menggantikan</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-absen"></span> Tidak Hadir</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-unplanned"></span> Tdk Dijadwalkan</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-anomali"></span> Anomali</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-planned"></span> Ada di rencana</span>
                </div>
            </div>
            <div class="ocr-fc">
                <div id="ocr-schedule-calendar"></div>
            </div>
        </div>

        <div class="row gy-4">
            {{-- T6.4 — Pencapaian per Personil --}}
            <div class="col-xxl-7">
                <div class="ocr-card h-100">
                    <div class="ocr-card-header">
                        <div>
                            <h6>Pencapaian per Personil</h6>
                            <p class="ocr-card-kicker">SAP hijau ≥100%, kuning 60–99%, merah &lt;60%.</p>
                        </div>
                    </div>
                    <div class="ocr-card-body">
                        <div class="ocr-achieve-list">
                            @foreach ($mock['achievement'] as $row)
                                @php $sapTone = $toneFor($row['sap']); @endphp
                                <div class="ocr-achieve-item">
                                    <div>
                                        <p class="ocr-achieve-name">{{ $row['name'] }}</p>
                                        <p class="ocr-achieve-meta">{{ $row['date'] }} · {{ $row['attendance'] }}</p>
                                    </div>
                                    <strong>{{ number_format($row['sap'], 1) }}%</strong>
                                    <div class="ocr-metric-row">
                                        <span>SAP</span>
                                        <div class="ocr-track is-{{ $sapTone }}"><span style="width: {{ min(100, $row['sap']) }}%"></span></div>
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
            </div>

            {{-- T6.5 — Coverage Score & Ranking --}}
            <div class="col-xxl-5">
                <div class="ocr-card h-100">
                    <div class="ocr-card-header">
                        <div>
                            <h6>Coverage Score &amp; Ranking</h6>
                            <p class="ocr-card-kicker">Non-kritis ×1 · Kritis ×2</p>
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
            </div>
        </div>

        {{-- T6.6 — Pareto --}}
        <div class="row gy-4">
            <div class="col-lg-6">
                <div class="ocr-card">
                    <div class="ocr-card-header">
                        <div>
                            <h6>Pareto Distribusi Jam — Shift 1</h6>
                            <p class="ocr-card-kicker">Garis putus 80% kumulatif</p>
                        </div>
                    </div>
                    <div class="ocr-card-body"><div id="chart-pareto-s1"></div></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="ocr-card">
                    <div class="ocr-card-header">
                        <div>
                            <h6>Pareto Distribusi Jam — Shift 2</h6>
                            <p class="ocr-card-kicker">Garis putus 80% kumulatif</p>
                        </div>
                    </div>
                    <div class="ocr-card-body"><div id="chart-pareto-s2"></div></div>
                </div>
            </div>
        </div>

        {{-- T6.7 — Highlight --}}
        <div class="row gy-4">
            <div class="col-lg-6">
                <div class="ocr-card h-100">
                    <div class="ocr-card-header">
                        <div>
                            <h6>Highlight Golden Rule</h6>
                            <p class="ocr-card-kicker">Sebaran temuan per aturan</p>
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
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="ocr-card ocr-metric-tile">
                    <i class="ri-map-2-line text-danger-600"></i>
                    <p class="ocr-kpi-value">{{ $mock['highlight']['blindspotCount'] }} <span class="fs-6 fw-normal text-secondary-light">/ {{ $mock['highlight']['blindspotTotal'] }}</span></p>
                    <p class="ocr-kpi-label mb-12">Lokasi Blindspot</p>
                    <p class="ocr-kpi-sub">Belum tersentuh SAP minggu ini</p>
                    <div class="ocr-track is-danger"><span style="width: {{ $blindspotPct }}%"></span></div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="ocr-card ocr-metric-tile">
                    <i class="ri-shield-check-line text-warning-600"></i>
                    <p class="ocr-kpi-value">{{ $mock['highlight']['tbcPercentage'] }}%</p>
                    <p class="ocr-kpi-label mb-12">Ratio TBC</p>
                    <p class="ocr-kpi-sub">To Be Concerned vs total temuan</p>
                    <div class="ocr-track is-warning"><span style="width: {{ min(100, $mock['highlight']['tbcPercentage']) }}%"></span></div>
                </div>
            </div>
        </div>

        {{-- T6.8 — Kualitas --}}
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

    <div class="offcanvas offcanvas-end" tabindex="-1" id="ocrDayDrawer" aria-labelledby="ocrDayDrawerTitle">
        <div class="offcanvas-header">
            <h6 class="offcanvas-title" id="ocrDayDrawerTitle">Roster hari</h6>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
        </div>
        <div class="offcanvas-body" id="ocrDayDrawerBody"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('build/plugins/fullcalendar/js/main.min.js') }}"></script>
    <script>
        (function () {
            var pareto = @json($mock['pareto']);
            var quality = @json($mock['quality']);
            var scheduleDays = @json($mock['schedule']['days']);
            var statusLabel = @json($statusLabel);
            var chipClass = @json($chipClass);
            var weekStart = @json($weekStart->toDateString());

            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (el) {
                new bootstrap.Tooltip(el);
            });

            function renderPareto(elementId, series) {
                var hours = series.map(function (row) { return row.hour + ':00'; });
                var counts = series.map(function (row) { return row.count; });
                var cumulative = series.map(function (row) { return row.cumulative; });

                new ApexCharts(document.getElementById(elementId), {
                    chart: { type: 'line', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
                    stroke: { width: [0, 3], curve: 'straight' },
                    series: [
                        { name: 'Jumlah Laporan', type: 'column', data: counts },
                        { name: 'Kumulatif %', type: 'line', data: cumulative },
                    ],
                    xaxis: { categories: hours, title: { text: 'Jam' }, axisBorder: { show: false } },
                    yaxis: [
                        { title: { text: 'Jumlah Laporan' } },
                        { opposite: true, min: 0, max: 100, title: { text: 'Kumulatif %' } },
                    ],
                    colors: ['#487FFF', '#FF9F29'],
                    grid: { borderColor: 'rgba(209, 213, 219, 0.4)', strokeDashArray: 4 },
                    legend: { position: 'top', horizontalAlign: 'right' },
                    dataLabels: { enabled: false },
                    annotations: {
                        yaxis: [{ y: 80, yAxisIndex: 1, borderColor: '#9CA3AF', strokeDashArray: 6, label: { text: '80%', style: { fontSize: '10px' } } }],
                    },
                }).render();
            }

            renderPareto('chart-pareto-s1', pareto.s1);
            renderPareto('chart-pareto-s2', pareto.s2);

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

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function renderShift(title, people) {
                var html = '<div class="ocr-roster-shift"><h6>' + escapeHtml(title) + '</h6>';
                if (!people.length) {
                    html += '<p class="text-secondary-light text-sm mb-0">Tidak ada personil.</p></div>';
                    return html;
                }

                people.forEach(function (person) {
                    var klass = chipClass[person.status] || 'ocr-chip--unplanned';
                    var planned = person.planned ? 'is-planned' : '';
                    html += '<div class="ocr-roster-row">'
                        + '<div><strong>' + escapeHtml(person.name) + '</strong>'
                        + '<div class="text-xs text-secondary-light">' + (person.planned ? 'Ada di rencana' : 'Tidak dijadwalkan') + '</div></div>'
                        + '<span class="ocr-chip ' + klass + ' ' + planned + '">' + escapeHtml(statusLabel[person.status] || person.status) + '</span>'
                        + '</div>';
                });

                return html + '</div>';
            }

            var drawerEl = document.getElementById('ocrDayDrawer');
            var drawer = new bootstrap.Offcanvas(drawerEl);
            var titleEl = document.getElementById('ocrDayDrawerTitle');
            var bodyEl = document.getElementById('ocrDayDrawerBody');
            var daysByDate = {};
            scheduleDays.forEach(function (day) { daysByDate[day.date] = day; });

            function dateKey(value) {
                if (typeof value === 'string') {
                    return value.slice(0, 10);
                }
                if (value instanceof Date) {
                    var year = value.getFullYear();
                    var month = String(value.getMonth() + 1).padStart(2, '0');
                    var day = String(value.getDate()).padStart(2, '0');
                    return year + '-' + month + '-' + day;
                }
                return String(value);
            }

            function addDays(iso, days) {
                var d = new Date(iso + 'T12:00:00');
                d.setDate(d.getDate() + days);
                return dateKey(d);
            }

            function openDayDrawer(dateValue) {
                var key = dateKey(dateValue);
                var day = daysByDate[key];
                if (!day) {
                    return;
                }

                titleEl.textContent = day.label + ', ' + day.day_number + ' ' + day.month_short;
                bodyEl.innerHTML = renderShift('Shift 1', day.s1) + renderShift('Shift 2', day.s2);
                drawer.show();
            }

            var statusColors = {
                sesuai: { bg: '#16A34A', border: '#15803D' },
                menggantikan: { bg: '#FF9F29', border: '#C28800' },
                tidak_hadir: { bg: '#DC2626', border: '#B91C1C' },
                tidak_dijadwalkan: { bg: '#9CA3AF', border: '#6B7280' },
                anomali: { bg: '#2563EB', border: '#1D4ED8' },
            };

            var calendarEvents = [];
            scheduleDays.forEach(function (day) {
                [
                    { key: 's1', code: 'S1', label: 'Shift 1', startHour: '06:00:00', endHour: '18:00:00', nextDayEnd: false },
                    { key: 's2', code: 'S2', label: 'Shift 2', startHour: '18:00:00', endHour: '00:00:00', nextDayEnd: true },
                ].forEach(function (shift) {
                    var person = (day[shift.key] || [])[0];
                    if (!person) {
                        return;
                    }

                    var colors = statusColors[person.status] || statusColors.tidak_dijadwalkan;
                    var endDate = shift.nextDayEnd ? addDays(day.date, 1) : day.date;
                    calendarEvents.push({
                        title: person.name,
                        start: day.date + 'T' + shift.startHour,
                        end: endDate + 'T' + shift.endHour,
                        allDay: false,
                        backgroundColor: colors.bg,
                        borderColor: person.planned ? '#111827' : colors.bg,
                        textColor: '#ffffff',
                        extendedProps: {
                            date: day.date,
                            shift: shift.code,
                            shiftLabel: shift.label,
                            status: person.status,
                            planned: person.planned,
                            personnel: person.name,
                            initial: person.initial,
                        },
                    });
                });
            });

            var calendarEl = document.getElementById('ocr-schedule-calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                headerToolbar: { left: '', center: 'title', right: '' },
                initialView: 'timeGridWeek',
                initialDate: weekStart,
                locale: 'id',
                firstDay: 1,
                height: 'auto',
                allDaySlot: false,
                slotMinTime: '06:00:00',
                slotMaxTime: '24:00:00',
                slotDuration: '03:00:00',
                slotLabelInterval: '03:00:00',
                slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                nowIndicator: true,
                navLinks: false,
                editable: false,
                selectable: false,
                eventDisplay: 'block',
                dayHeaderFormat: { weekday: 'short', day: 'numeric', omitCommas: true },
                events: calendarEvents,
                eventContent: function (arg) {
                    var props = arg.event.extendedProps;
                    var plannedClass = props.planned ? ' is-planned' : '';
                    var status = statusLabel[props.status] || props.status;

                    return {
                        html:
                            '<div class="ocr-fc-block' + plannedClass + '">' +
                            '<span class="ocr-fc-shift">' + escapeHtml(props.shiftLabel || props.shift) + '</span>' +
                            '<strong>' + escapeHtml(props.personnel || arg.event.title) + '</strong>' +
                            '<span class="ocr-fc-status">' + escapeHtml(status) + '</span>' +
                            '</div>',
                    };
                },
                dateClick: function (info) {
                    openDayDrawer(info.date);
                },
                eventClick: function (info) {
                    info.jsEvent.preventDefault();
                    openDayDrawer(info.event.extendedProps.date || info.event.start);
                },
            });

            calendar.render();
        })();
    </script>
@endpush
