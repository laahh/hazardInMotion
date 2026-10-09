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
    $weekRangeLabel = $weekRangeLabel ?? ($weekStart->locale('id')->translatedFormat('l d M').' – '.$weekEnd->locale('id')->translatedFormat('l d M Y'));
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
    $sapLabel = static function (?float $value): string {
        if ($value === null) {
            return '—';
        }
        if (abs($value - round($value)) < 0.005) {
            return number_format($value, 0).'%';
        }

        return number_format($value, 2).'%';
    };
    $scheduleDays = $schedule['days'];
    $defaultDay = collect($scheduleDays)->firstWhere('is_today')
        ?? collect($scheduleDays)->first(fn (array $day): bool => $day['s1'] !== [] || $day['s2'] !== [])
        ?? $scheduleDays[0];
    $coverageScope = $site === \App\Enums\ControlRoomSiteCode::HeadOffice
        ? 'Semua site operasi'
        : $site->label();
    $personnelCoverage = $mock['personnelCoverage'];
    $personnelLokasiMax = max(1, ...(array_column($personnelCoverage, 'lokasi') ?: [0]));
    $personnelKritisMax = max(1, ...(array_column($personnelCoverage, 'kritis') ?: [0]));
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-dashboard.css') }}?v={{ filemtime(public_path('wowdash-admin/assets/css/control-room-dashboard.css')) }}">
@endpush

@section('content')
    <div class="ocr-dash">
        <!-- <div class="ocr-notice" role="status">
            <i class="ri-information-line"></i>
            <span><strong>Sebagian mockup.</strong> KPI header dan ranking coverage masih fiktif. Pencapaian Personil, Pareto, Highlight, dan Kualitas memakai jadwal + laporan OBDS. Blindspot/TBC dari snapshot HSECM bila tabelnya ada. Tombol Detail menampilkan laporan pada jendela jaga.</span>
        </div> -->
        <form method="GET" class="ocr-card" action="{{ route('control-room.dashboard') }}">
            <div class="ocr-toolbar">
                <div class="ocr-toolbar-left">
        <div>
                        <label for="ocr-dash-site">Site</label>
                        <select name="site" id="ocr-dash-site" class="form-control" onchange="this.form.submit()">
                        @foreach ($sites as $siteOption)
                                <option value="{{ $siteOption->value }}" @selected($site->value === $siteOption->value)>{{ $siteOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
                    @include('control-room.partials.week-period-filter', [
                        'weekInputId' => 'ocr-dash-iso-week',
                        'isoWeekValue' => $isoWeekValue,
                        'weekRangeLabel' => $weekRangeLabel,
                        'year' => $year,
                        'week' => $week,
                        'prevWeekUrl' => route('control-room.dashboard', ['site' => $site->value, 'year' => $prevYear, 'week' => $prevWeek]),
                        'nextWeekUrl' => route('control-room.dashboard', ['site' => $site->value, 'year' => $nextYear, 'week' => $nextWeek]),
                    ])
                </div>
                <div class="ocr-toolbar-right">
                    <span class="ocr-sync">Jadwal, pencapaian, dan KPI: data asli</span>
                </div>
            </div>
        </form>

        <section class="ocr-card ocr-board" aria-labelledby="ocr-board-title">
            <div class="ocr-card-header">
                <div>
                    <h6 id="ocr-board-title">Status Control Room</h6>
                    <p class="ocr-card-kicker">{{ $siteBoard['dutyDateLabel'] }} · {{ $siteBoard['shift']->label() }} — hijau = ada jadwal dan sudah absen jaga, merah = belum lengkap.</p>
                </div>
                <div class="ocr-legend">
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-sesuai"></span> Terjaga</span>
                    <span class="ocr-legend-item"><span class="ocr-legend-swatch is-absen"></span> Belum terjaga</span>
                </div>
            </div>
            <div class="ocr-board-grid">
                @foreach ($siteBoard['cards'] as $card)
                    <a
                        class="ocr-site-stat is-{{ $card['tone'] }}{{ $site->value === $card['site'] ? ' is-current' : '' }}"
                        href="{{ route('control-room.dashboard', ['site' => $card['site'], 'year' => $year, 'week' => $week, 'iso_week' => $isoWeekValue]) }}"
                    >
                        <div class="ocr-site-stat-row">
                            <span class="ocr-site-stat-icon" aria-hidden="true">
                                <i class="{{ $card['tone'] === 'green' ? 'ri-user-follow-fill' : 'ri-user-unfollow-fill' }}"></i>
                            </span>
                            <div class="ocr-site-stat-copy">
                                <p class="ocr-site-stat-kicker">{{ $card['site'] }}</p>
                                <p class="ocr-site-stat-value">{{ $card['label'] }}</p>
                            </div>
                            <svg class="ocr-site-spark" viewBox="0 0 88 36" width="88" height="36" aria-hidden="true">
                                <polygon class="ocr-site-spark-area" points="{{ $card['sparkArea'] }}"></polygon>
                                <polyline class="ocr-site-spark-line" points="{{ $card['sparkLine'] }}" fill="none" stroke-linecap="round" stroke-linejoin="round"></polyline>
                            </svg>
                        </div>
                        <p class="ocr-site-stat-foot">
                            @if ($card['hasSchedule'])
                                Jaga
                                <span class="ocr-site-pill">{{ $card['presentCount'] }}/{{ $card['scheduledCount'] }}</span>
                                {{ $card['state'] }}
                            @else
                                Jadwal
                                <span class="ocr-site-pill">{{ $card['state'] }}</span>
                            @endif
                        </p>
                    </a>
                @endforeach
            </div>
        </section>

        <div id="ocr-cov-mount" data-coverage-url="{{ $coverageUrl }}" data-coverage-date="{{ $coverageDate }}">
            <section class="ocr-cov" aria-labelledby="ocr-cov-title">
                <div class="ocr-cov-head">
                    <div>
                        <h6 id="ocr-cov-title">Coverage Lokasi</h6>
                        <p class="ocr-card-kicker">{{ $weekRangeLabel }} · {{ $coverageScope }}</p>
                    </div>
                </div>
                <div class="ocr-cov-skeleton" role="status" aria-live="polite">
                    <p class="ocr-cov-skeleton-copy">Memuat coverage lokasi…</p>
                    <div class="ocr-kpi-grid ocr-cov-kpi">
                        <div class="ocr-card ocr-cov-stat ocr-cov-skel-card"></div>
                        <div class="ocr-card ocr-cov-stat ocr-cov-skel-card"></div>
                        <div class="ocr-card ocr-cov-stat ocr-cov-skel-card"></div>
                        <div class="ocr-card ocr-cov-stat ocr-cov-skel-card"></div>
                    </div>
                    <div class="ocr-card ocr-cov-skel-table"></div>
                </div>
            </section>
        </div>

        <div class="ocr-kpi-grid">
            @foreach ($mock['kpi'] as $card)
                <div class="ocr-card ocr-kpi" title="{{ $card['formula'] }}" data-bs-toggle="tooltip" data-bs-title="{{ $card['formula'] }}">
                    <div class="ocr-kpi-top">
                        <div class="ocr-kpi-icon is-{{ $card['color'] }}">
                            <i class="{{ $card['icon'] }}"></i>
                        </div>
                        @if (($card['delta'] ?? null) === null)
                            <span class="ocr-delta is-flat">—</span>
                        @elseif ($card['delta'] > 0)
                            <span class="ocr-delta is-up"><i class="ri-arrow-up-line"></i> {{ $card['delta'] }}</span>
                        @elseif ($card['delta'] < 0)
                            <span class="ocr-delta is-down"><i class="ri-arrow-down-line"></i> {{ abs($card['delta']) }}</span>
                        @else
                            <span class="ocr-delta is-flat">0</span>
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
                                                                @if (($person['replacement'] ?? '') !== '')
                                                                    <span class="ocr-shift-replace">{{ $person['replacement'] }}</span>
                                                                @endif
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
                                                                @if (($person['replacement'] ?? '') !== '')
                                                                    <span class="ocr-shift-replace">{{ $person['replacement'] }}</span>
                                                                @endif
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
                        <p class="ocr-card-kicker">% pencapaian berdasarkan kehadiran sesuai jadwal, SAP berdasarkan target (1 Hazard, 1 Inspeksi, 1 Observasi/OAK), dan % TBC per orang = valid TBC ÷ (Hazard + Inspeksi) selama jaga minggu ini. Observasi/OAK tidak masuk rumus TBC. Klik Detail untuk laporan SAP selama jaga.</p>
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
                                            <td class="ocr-heat-name">
                                                {{ $row['name'] }}
                                                @if (($row['replacement'] ?? '') !== '')
                                                    <div class="ocr-heat-replace">{{ $row['replacement'] }}</div>
                                                @endif
                                            </td>
                                            <td class="text-center">{{ $row['shift'] }}</td>
                                            <td class="ocr-heat-cell {{ $heatClass($row['attendance_pct']) }}">{{ $heatLabel($row['attendance_pct']) }}</td>
                                            <td class="ocr-heat-cell {{ $heatClass($row['sap']) }}" title="{{ $row['sap_hint'] ?? '' }}">{{ $sapLabel($row['sap']) }}</td>
                                            <td class="ocr-heat-cell {{ $heatClass($row['tbc']) }}" title="{{ $row['tbc_hint'] ?? '' }}">{{ $heatLabel($row['tbc']) }}</td>
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
                        <p class="ocr-card-kicker">Lokasi unik yang dilaporkan saat jadwal jaga · kritis mengikuti CONTAINS Lokasi/Detil Lokasi</p>
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
                                @forelse ($personnelCoverage as $row)
                                    <tr class="{{ $row['lead'] ? 'is-lead' : '' }}">
                                        <td class="ocr-heat-name">{{ $row['name'] }}</td>
                                        <td class="ocr-heat-cell is-cov">
                                            <div class="ocr-cov-pbar">
                                                <strong>{{ $row['lokasi'] }}</strong>
                                                <div class="ocr-track is-info"><span style="width: {{ min(100, $row['lokasi'] / $personnelLokasiMax * 100) }}%"></span></div>
                                            </div>
                                        </td>
                                        <td class="ocr-heat-cell is-cov">
                                            <div class="ocr-cov-pbar">
                                                <strong>{{ $row['kritis'] }}</strong>
                                                <div class="ocr-track is-success"><span style="width: {{ min(100, $row['kritis'] / $personnelKritisMax * 100) }}%"></span></div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-secondary-light">Belum ada personil jadwal untuk dihitung coverage.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @include('control-room.partials.insights-panels', ['highlight' => $mock['highlight'], 'quality' => $mock['quality']])
    </div>

    @include('control-room.partials.insights-modals')
@endsection

@push('scripts')
    <script src="{{ asset('wowdash-admin/assets/js/control-room-insights.js') }}?v={{ filemtime(public_path('wowdash-admin/assets/js/control-room-insights.js')) }}"></script>
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
            var sapPhotosUrl = @json(route('control-room.dashboard.sap-photos'));
            var highlightData = @json($mock['highlight']);

            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (el) {
                new bootstrap.Tooltip(el);
            });

            function bindCoverage(root, mount) {
                if (!root || !mount) {
                    return;
                }
                var titles = {
                    all: 'Detail Semua Lokasi',
                    covered: 'Detail Lokasi Covered',
                    uncovered: 'Detail Lokasi Belum Ter-cover'
                };
                var kindLabels = {
                    all: 'Semua lokasi pada tanggal terpilih',
                    critical: 'Lokasi kritis / high risk',
                    noncritical: 'Lokasi non-kritis'
                };
                var attnKickers = {
                    all: 'Area kritis / high risk tanpa SAP pada tanggal terpilih',
                    critical: 'Lokasi kritis / high risk tanpa SAP pada tanggal terpilih',
                    noncritical: 'Lokasi non-kritis tanpa SAP pada tanggal terpilih'
                };

                var weeklyLoaded = !root.querySelector('[data-cov-pending]');

                function coverageRequest(params) {
                    var url = new URL(mount.getAttribute('data-coverage-url'), window.location.origin);
                    Object.keys(params).forEach(function (key) {
                        url.searchParams.set(key, params[key]);
                    });
                    return url.toString();
                }

                function fetchPartial(params) {
                    return fetch(coverageRequest(params), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html'
                        },
                        credentials: 'same-origin'
                    }).then(function (res) {
                        if (!res.ok) {
                            throw new Error('coverage ' + res.status);
                        }
                        return res.text();
                    });
                }

                function loadDaily(date) {
                    var host = document.getElementById('ocr-cov-panel-daily');
                    if (!host) {
                        return;
                    }
                    host.classList.add('is-loading');
                    fetchPartial({ mode: 'daily', date: date, partial: 'daily' }).then(function (html) {
                        host.innerHTML = html;
                        var next = host.querySelector('[data-cov-panel]');
                        if (next) {
                            next.hidden = false;
                            initPanel(next);
                        }
                    }).catch(function () {
                        host.innerHTML = '<div class="ocr-notice" role="alert"><i class="ri-error-warning-line"></i><span>Coverage harian gagal dimuat. Coba tanggal lain.</span></div>';
                    }).finally(function () {
                        host.classList.remove('is-loading');
                    });
                }

                function loadWeekly() {
                    var host = document.getElementById('ocr-cov-panel-weekly');
                    if (!host || weeklyLoaded) {
                        return;
                    }
                    host.classList.add('is-loading');
                    fetchPartial({ mode: 'weekly', partial: 'weekly' }).then(function (html) {
                        host.innerHTML = html;
                        weeklyLoaded = true;
                        var next = host.querySelector('[data-cov-panel]');
                        if (next) {
                            next.hidden = false;
                            initPanel(next);
                        }
                    }).catch(function () {
                        host.innerHTML = '<div class="ocr-notice" role="alert"><i class="ri-error-warning-line"></i><span>Coverage mingguan gagal dimuat.</span></div>';
                    }).finally(function () {
                        host.classList.remove('is-loading');
                    });
                }

                function initPanel(panel) {
                    var table = panel.querySelector('.ocr-cov-table');
                    if (!table) {
                        return;
                    }
                    var isDaily = panel.getAttribute('data-cov-panel') === 'daily';
                    var q = panel.querySelector('.ocr-cov-search input');
                    var emptyFilter = table.querySelector('.ocr-cov-empty-filter');
                    var title = panel.querySelector('h6[id^="ocr-cov-table-title"]');
                    var more = panel.querySelector('[data-cov-more]');
                    var attnBody = panel.querySelector('[data-cov-attention-body]');
                    var tab = 'uncovered';
                    var kind = 'all';
                    var selectedDate = panel.getAttribute('data-selected-date') || '';

                    function activeDayButton() {
                        return panel.querySelector('[data-cov-day].is-active');
                    }

                    function dayLabel() {
                        var btn = activeDayButton();
                        return btn ? (btn.getAttribute('data-cov-day-label') || '') : '';
                    }

                    function dayDisplay() {
                        var btn = activeDayButton();
                        if (!btn) {
                            return selectedDate;
                        }
                        return (btn.getAttribute('data-cov-day-label') || '') + ', ' + (btn.getAttribute('data-cov-day-display') || '');
                    }

                    function coveredDates(row) {
                        return (row.getAttribute('data-covered-dates') || '').split(',').filter(Boolean);
                    }

                    function matchesKind(row) {
                        if (!isDaily || kind === 'all') {
                            return true;
                        }
                        var critical = row.getAttribute('data-critical') === '1';
                        return kind === 'critical' ? critical : !critical;
                    }

                    function setText(selector, value) {
                        var el = panel.querySelector(selector);
                        if (el) {
                            el.textContent = value;
                        }
                    }

                    function applyDailyCoverage() {
                        if (!isDaily) {
                            return;
                        }
                        panel.setAttribute('data-selected-date', selectedDate);
                        table.querySelectorAll('tbody tr[data-covered]').forEach(function (row) {
                            var has = coveredDates(row).indexOf(selectedDate) !== -1;
                            row.setAttribute('data-covered', has ? '1' : '0');
                            var pill = row.querySelector('.ocr-cov-pill');
                            if (pill) {
                                pill.classList.toggle('is-ok', has);
                                pill.classList.toggle('is-gap', !has);
                                pill.textContent = has ? 'Ter-cover' : 'Belum ter-cover';
                            }
                            var dot = row.querySelector('.ocr-cov-dot');
                            if (dot) {
                                dot.classList.toggle('is-ok', has);
                                dot.classList.toggle('is-gap', !has);
                            }
                            var gap = row.querySelector('[data-cov-gap]');
                            if (gap) {
                                gap.textContent = has ? '—' : (dayLabel() || '—');
                                gap.classList.toggle('ocr-cov-gap', !has);
                            }
                        });
                    }

                    function updateKpi(scoped) {
                        var total = scoped.length;
                        var covered = scoped.filter(function (row) {
                            return row.getAttribute('data-covered') === '1';
                        }).length;
                        var uncovered = total - covered;
                        var pct = total === 0 ? 0 : Math.round(covered / total * 1000) / 10;
                        setText('[data-cov-kpi="total"]', String(total));
                        setText('[data-cov-kpi="covered"]', String(covered));
                        setText('[data-cov-kpi="uncovered"]', String(uncovered));
                        setText('[data-cov-kpi="percent"]', pct.toFixed(1) + '%');
                        var ring = panel.querySelector('[data-cov-ring]');
                        if (ring) {
                            ring.setAttribute('stroke-dasharray', pct + ', 100');
                        }
                        if (isDaily) {
                            setText('[data-cov-kpi-sub="total"]', kindLabels[kind] || kindLabels.all);
                            setText('[data-cov-kpi-sub="percent"]', 'dari ' + total + ' lokasi pada tanggal terpilih');
                            var scope = (root.getAttribute('data-coverage-scope') || '').trim();
                            setText('[data-cov-table-kicker]', dayDisplay() + ' · 1 SAP semua jenis cukup' + (scope ? ' · ' + scope : ''));
                            setText('[data-cov-attn-kicker]', attnKickers[kind] || attnKickers.all);
                        }
                        var tabAll = panel.querySelector('[data-cov-tab="all"]');
                        var tabCovered = panel.querySelector('[data-cov-tab="covered"]');
                        var tabUncovered = panel.querySelector('[data-cov-tab="uncovered"]');
                        if (tabAll) {
                            tabAll.textContent = 'Semua Lokasi (' + total + ')';
                        }
                        if (tabCovered) {
                            tabCovered.textContent = 'Covered (' + covered + ')';
                        }
                        if (tabUncovered) {
                            tabUncovered.textContent = 'Belum Ter-cover (' + uncovered + ')';
                        }
                        if (more) {
                            more.hidden = uncovered === 0;
                        }
                        return { total: total, covered: covered, uncovered: uncovered };
                    }

                    function updateAttention(scoped) {
                        if (!attnBody || !isDaily) {
                            return;
                        }
                        var items = scoped.filter(function (row) {
                            if (row.getAttribute('data-covered') === '1') {
                                return false;
                            }
                            if (kind === 'all') {
                                return row.getAttribute('data-critical') === '1';
                            }
                            return true;
                        });
                        var html = '';
                        items.slice(0, 25).forEach(function (row) {
                            var lokasi = row.getAttribute('data-lokasi') || '';
                            var detail = row.getAttribute('data-detail') || '';
                            var site = row.getAttribute('data-site') || '';
                            var gap = dayLabel() || '—';
                            html += '<div class="ocr-cov-attn-row"><div><strong>'
                                + escapeHtml(detail !== '' ? detail : lokasi)
                                + '</strong><span>'
                                + escapeHtml(lokasi + ' · ' + site)
                                + '</span></div><div class="ocr-cov-attn-meta"><b>'
                                + escapeHtml(gap)
                                + '</b><small>tanpa SAP</small></div></div>';
                        });
                        if (html === '') {
                            var emptyMsg = kind === 'noncritical'
                                ? 'Semua lokasi non-kritis sudah ter-cover pada tanggal ini.'
                                : 'Semua area kritis / high risk sudah ter-cover pada tanggal ini.';
                            html = '<p class="text-secondary-light mb-0" data-cov-attention-empty>' + emptyMsg + '</p>';
                        }
                        attnBody.innerHTML = html;
                    }

                    function applyCoverageFilter() {
                        applyDailyCoverage();
                        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-covered]'));
                        var scoped = rows.filter(matchesKind);
                        updateKpi(scoped);
                        updateAttention(scoped);
                        var query = (q && q.value ? q.value : '').trim().toLowerCase();
                        var visible = 0;
                        rows.forEach(function (row) {
                            var covered = row.getAttribute('data-covered') === '1';
                            var matchKind = matchesKind(row);
                            var matchTab = tab === 'all' || (tab === 'covered' && covered) || (tab === 'uncovered' && !covered);
                            var haystack = row.getAttribute('data-search') || '';
                            var matchQuery = query === '' || haystack.indexOf(query) !== -1;
                            var show = matchKind && matchTab && matchQuery;
                            row.hidden = !show;
                            if (show) {
                                visible++;
                            }
                        });
                        if (emptyFilter) {
                            emptyFilter.hidden = visible !== 0 || scoped.length === 0;
                        }
                    }

                    function setTab(next) {
                        tab = next || 'uncovered';
                        panel.querySelectorAll('[data-cov-tab]').forEach(function (el) {
                            el.classList.toggle('is-active', el.getAttribute('data-cov-tab') === tab);
                        });
                        if (title) {
                            title.textContent = titles[tab] || titles.uncovered;
                        }
                        applyCoverageFilter();
                    }

                    function setKind(next) {
                        kind = next || 'all';
                        panel.querySelectorAll('[data-cov-kind]').forEach(function (el) {
                            el.classList.toggle('is-active', el.getAttribute('data-cov-kind') === kind);
                        });
                        applyCoverageFilter();
                    }

                    function setDay(date) {
                        if (!date || date === selectedDate) {
                            return;
                        }
                        loadDaily(date);
                    }

                    panel.querySelectorAll('[data-cov-tab]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            setTab(btn.getAttribute('data-cov-tab'));
                        });
                    });
                    panel.querySelectorAll('[data-cov-kind]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            setKind(btn.getAttribute('data-cov-kind'));
                        });
                    });
                    panel.querySelectorAll('[data-cov-day]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            setDay(btn.getAttribute('data-cov-day'));
                        });
                    });
                    if (more) {
                        more.addEventListener('click', function () {
                            setTab('uncovered');
                            table.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    }
                    if (q) {
                        q.addEventListener('input', applyCoverageFilter);
                    }
                    applyCoverageFilter();
                }

                function setMode(mode) {
                    mode = mode === 'weekly' ? 'weekly' : 'daily';
                    root.querySelectorAll('[data-cov-mode]').forEach(function (el) {
                        var active = el.getAttribute('data-cov-mode') === mode;
                        el.classList.toggle('is-active', active);
                        el.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    root.querySelectorAll('[data-cov-panel]').forEach(function (panel) {
                        panel.hidden = panel.getAttribute('data-cov-panel') !== mode;
                    });
                    if (mode === 'weekly') {
                        loadWeekly();
                    }
                }

                root.querySelectorAll('[data-cov-mode]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        setMode(btn.getAttribute('data-cov-mode'));
                    });
                });
                root.querySelectorAll('[data-cov-panel]').forEach(initPanel);
                setMode('daily');
            }

            (function loadCoveragePanel() {
                var mount = document.getElementById('ocr-cov-mount');
                if (!mount) {
                    return;
                }
                var url = mount.getAttribute('data-coverage-url');
                if (!url) {
                    return;
                }
                var initial = new URL(url, window.location.origin);
                initial.searchParams.set('mode', 'daily');
                var initialDate = mount.getAttribute('data-coverage-date');
                if (initialDate) {
                    initial.searchParams.set('date', initialDate);
                }
                fetch(initial.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    },
                    credentials: 'same-origin'
                }).then(function (res) {
                    if (!res.ok) {
                        throw new Error('coverage ' + res.status);
                    }
                    return res.text();
                }).then(function (html) {
                    mount.innerHTML = html;
                    bindCoverage(mount.querySelector('[data-cov-root]'), mount);
                }).catch(function () {
                    mount.innerHTML = '<div class="ocr-notice" role="alert"><i class="ri-error-warning-line"></i><span>Coverage lokasi gagal dimuat. Muat ulang halaman atau pilih minggu lagi.</span></div>';
                });
            })();

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
                    + (person.replacement ? '<p class="ocr-roster-replace">' + escapeHtml(person.replacement) + '</p>' : '')
                    + (person.catatan && person.catatan !== person.replacement ? '<p class="ocr-roster-note">' + escapeHtml(person.catatan) + '</p>' : '')
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

            OcrInsights.init({
                pareto: pareto,
                quality: quality,
                highlight: highlightData,
                sapDetailUrl: sapDetailUrl,
                sapPhotosUrl: sapPhotosUrl
            });
        })();
    </script>
@endpush
