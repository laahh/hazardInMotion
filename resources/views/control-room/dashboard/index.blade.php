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
    $maxGoldenRule = max(1, ...(array_column($mock['highlight']['goldenRules'], 'count') ?: [0]));
    $blindspotPct = $mock['highlight']['blindspotTotal'] > 0
        ? round(($mock['highlight']['blindspotCount'] / $mock['highlight']['blindspotTotal']) * 100, 1)
        : 0;
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

        <div id="ocr-cov-mount" data-coverage-url="{{ $coverageUrl }}">
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
                        @forelse ($mock['highlight']['goldenRules'] as $gr)
                            <button type="button" class="ocr-gr-row" data-highlight-kind="golden_rule" data-highlight-name="{{ $gr['name'] }}" aria-haspopup="dialog" aria-controls="ocr-highlight-modal">
                                <span>{{ $gr['name'] }}</span>
                                <strong>{{ $gr['count'] }}</strong>
                                <div class="ocr-track is-primary"><span style="width: {{ ($gr['count'] / $maxGoldenRule) * 100 }}%"></span></div>
                            </button>
                        @empty
                            <p class="text-secondary-light mb-0">Tidak ada nama Golden Rule pada laporan minggu ini.</p>
                        @endforelse
                    </div>
                    <!-- <div class="ocr-highlight-metrics">
                        <button type="button" class="ocr-metric-mini is-clickable" data-highlight-kind="blindspot" aria-haspopup="dialog" aria-controls="ocr-highlight-modal">
                            <p class="ocr-kpi-label">Blindspot</p>
                            <p class="ocr-kpi-value">{{ $mock['highlight']['blindspotCount'] }} <span class="fs-6 fw-normal text-secondary-light">/ {{ $mock['highlight']['blindspotTotal'] }}</span></p>
                            <div class="ocr-track is-danger"><span style="width: {{ $blindspotPct }}%"></span></div>
                        </button>
                        <button type="button" class="ocr-metric-mini is-clickable" data-highlight-kind="tbc" aria-haspopup="dialog" aria-controls="ocr-highlight-modal">
                            <p class="ocr-kpi-label">Ratio TBC</p>
                            <p class="ocr-kpi-value">{{ $mock['highlight']['tbcPercentage'] === null ? '—' : number_format($mock['highlight']['tbcPercentage'], 1).'%' }}</p>
                            <div class="ocr-track is-warning"><span style="width: {{ min(100, $mock['highlight']['tbcPercentage'] ?? 0) }}%"></span></div>
                        </button>
                    </div> -->
            </div>
        </div>
    </div>

        <div class="row gy-4">
            <div class="col-lg-7">
                <div class="ocr-card h-100">
                    <div class="ocr-card-header">
                        <div>
                            <h6>Kualitas Temuan per Personil</h6>
                            <p class="ocr-card-kicker">Total = jumlah kartu SAP unik seperti di modal Detail (satu laporan = satu), digabung dari semua hari jaga tanpa dihitung dua kali. Kategori = sub ketidaksesuaian · Variasi = kategori unik / jumlah laporan unik. TBC = valid TBC ÷ Hazard+Inspeksi.</p>
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
                                    @forelse ($mock['quality'] as $row)
                                        <tr>
                                <td>{{ $row['name'] }}</td>
                                            <td class="text-center">{{ $row['total_findings'] }}</td>
                                            <td class="text-center">{{ $row['distinct_categories'] }}</td>
                                            <td>
                                                @if ($row['variety_score'] === null)
                                                    <span class="text-secondary-light">—</span>
                                                @else
                                                    <div class="ocr-variety">
                                                        <div class="ocr-track is-primary"><span style="width: {{ $row['variety_score'] * 100 }}%"></span></div>
                                                        <span>{{ number_format((float) $row['variety_score'], 2) }}</span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if ($row['tbc'] === null || (int) ($row['tbc_basis'] ?? -1) === 0)
                                                    —
                                                @elseif (array_key_exists('tbc_basis', $row) && $row['tbc_basis'] !== null)
                                                    {{ $row['tbc'] }}/{{ $row['tbc_basis'] }}
                                                @else
                                                    {{ $row['tbc'] }}
                                                @endif
                                            </td>
                                            <td class="text-center">{{ $row['gr'] === null ? '—' : $row['gr'] }}</td>
                                            <td class="text-center">{{ $row['blindspot'] === null ? '—' : $row['blindspot'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-secondary-light">Belum ada personil jadwal pada minggu yang dipilih.</td>
                            </tr>
                                    @endforelse
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
                            <p class="ocr-card-kicker">Kanan-atas = banyak temuan saat jaga &amp; sub ketidaksesuaian beragam</p>
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
                    <div class="ocr-seg ocr-detail-tabs" role="tablist" aria-label="Jenis detail personil">
                        <button type="button" role="tab" id="ocr-detail-tab-sap" class="is-active" aria-selected="true" aria-controls="ocr-sap-pane-sap" data-detail-pane="sap">SAP</button>
                        <button type="button" role="tab" id="ocr-detail-tab-tbc" aria-selected="false" aria-controls="ocr-sap-pane-tbc" data-detail-pane="tbc">TBC</button>
                    </div>
                    <div id="ocr-sap-pane-sap" role="tabpanel" aria-labelledby="ocr-detail-tab-sap">
                        <div class="ocr-sap-filters" id="ocr-sap-filters" hidden>
                            <button type="button" class="is-active" data-sap-filter="all">Semua</button>
                            <button type="button" data-sap-filter="hazard">Hazard</button>
                            <button type="button" data-sap-filter="inspeksi">Inspeksi</button>
                            <button type="button" data-sap-filter="observasi">Observasi</button>
                            <button type="button" data-sap-filter="oak">OAK</button>
                        </div>
                    </div>
                    <div id="ocr-sap-pane-tbc" role="tabpanel" aria-labelledby="ocr-detail-tab-tbc" hidden>
                        <p class="ocr-card-kicker ocr-tbc-kicker" id="ocr-tbc-kicker">Hazard &amp; Inspeksi orang ini pada jendela jaga. Sudah TBC = tasklist ada di Google Sheet.</p>
                        <div class="ocr-sap-filters" id="ocr-tbc-filters" hidden>
                            <button type="button" class="is-active" data-tbc-filter="all">Semua</button>
                            <button type="button" data-tbc-filter="sudah">Sudah TBC</button>
                            <button type="button" data-tbc-filter="belum">Belum TBC</button>
                        </div>
                    </div>
                    <p class="ocr-sap-status-msg" id="ocr-sap-status">Memuat laporan…</p>
                    <div class="ocr-sap-grid" id="ocr-sap-grid"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ocr-highlight-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h6 class="modal-title" id="ocr-highlight-title">Detail Highlight Temuan</h6>
                        <p class="ocr-card-kicker mb-0" id="ocr-highlight-meta"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="ocr-table ocr-highlight-table">
                            <thead>
                                <tr>
                                    <th>Kode Tasklist</th>
                                    <th>Tanggal temuan</th>
                                    <th>Deskripsi temuan</th>
                                    <th>Perusahaan &amp; PIC</th>
                                    <th>Status temuan</th>
                                </tr>
                            </thead>
                            <tbody id="ocr-highlight-body"></tbody>
                        </table>
                    </div>
                    <p class="ocr-sap-status-msg" id="ocr-highlight-empty" hidden>Tidak ada temuan untuk kategori ini.</p>
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
            var sapPhotosUrl = @json(route('control-room.dashboard.sap-photos'));
            var highlightData = @json($mock['highlight']);

            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (el) {
                new bootstrap.Tooltip(el);
            });

            function bindCoverage(root) {
                if (!root) {
                    return;
                }
                var titles = {
                    all: 'Detail Semua Lokasi',
                    covered: 'Detail Lokasi Covered',
                    uncovered: 'Detail Lokasi Belum Ter-cover'
                };

                function initPanel(panel) {
                    var table = panel.querySelector('.ocr-cov-table');
                    if (!table) {
                        return;
                    }
                    var q = panel.querySelector('.ocr-cov-search input');
                    var emptyFilter = table.querySelector('.ocr-cov-empty-filter');
                    var title = panel.querySelector('h6[id^="ocr-cov-table-title"]');
                    var tab = 'uncovered';

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

                    function applyCoverageFilter() {
                        var query = (q && q.value ? q.value : '').trim().toLowerCase();
                        var rows = table.querySelectorAll('tbody tr[data-covered]');
                        var visible = 0;
                        rows.forEach(function (row) {
                            var covered = row.getAttribute('data-covered') === '1';
                            var matchTab = tab === 'all' || (tab === 'covered' && covered) || (tab === 'uncovered' && !covered);
                            var haystack = row.getAttribute('data-search') || '';
                            var matchQuery = query === '' || haystack.indexOf(query) !== -1;
                            var show = matchTab && matchQuery;
                            row.hidden = !show;
                            if (show) {
                                visible++;
                            }
                        });
                        if (emptyFilter) {
                            emptyFilter.hidden = visible !== 0 || rows.length === 0;
                        }
                    }

                    panel.querySelectorAll('[data-cov-tab]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            setTab(btn.getAttribute('data-cov-tab'));
                        });
                    });
                    var more = panel.querySelector('[data-cov-more]');
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
                fetch(url, {
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
                    bindCoverage(mount.querySelector('[data-cov-root]'));
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

            function paretoOptions(series) {
                series = series || [];
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
                series = series || [];
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

            var qualityPoints = quality.filter(function (row) {
                return row.variety_score !== null;
            });
            new ApexCharts(document.getElementById('chart-quality-scatter'), {
                chart: { type: 'scatter', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [{
                    name: 'Personil',
                    data: qualityPoints.map(function (row) { return [row.total_findings, row.variety_score]; }),
                }],
                xaxis: { title: { text: 'Volume Temuan' }, tickAmount: 5 },
                yaxis: { title: { text: 'Variasi Score' }, min: 0, max: 1 },
                colors: ['#487FFF'],
                grid: { borderColor: 'rgba(209, 213, 219, 0.4)', strokeDashArray: 4 },
                tooltip: {
                    custom: function (opts) {
                        var row = qualityPoints[opts.dataPointIndex];
                        if (!row) {
                            return '';
                        }
                        return '<div class="p-8 text-xs"><strong>' + row.name + '</strong><br>Volume: ' + row.total_findings + '<br>Kategori: ' + row.distinct_categories + '<br>Variasi: ' + row.variety_score + '</div>';
                    },
                },
            }).render();

            var sapCards = [];
            var tbcCards = [];
            var tbcLoaded = false;
            var sapFilter = 'all';
            var tbcFilter = 'all';
            var detailPane = 'sap';
            var sapModalEl = document.getElementById('ocr-sap-modal');
            var sapModal = sapModalEl ? new bootstrap.Modal(sapModalEl) : null;
            var sapTitle = document.getElementById('ocr-sap-title');
            var sapMeta = document.getElementById('ocr-sap-meta');
            var sapStatus = document.getElementById('ocr-sap-status');
            var sapGrid = document.getElementById('ocr-sap-grid');
            var sapFilters = document.getElementById('ocr-sap-filters');
            var tbcFilters = document.getElementById('ocr-tbc-filters');
            var sapPane = document.getElementById('ocr-sap-pane-sap');
            var tbcPane = document.getElementById('ocr-sap-pane-tbc');

            function setDetailPane(pane) {
                detailPane = pane;
                if (sapPane) {
                    sapPane.hidden = pane !== 'sap';
                }
                if (tbcPane) {
                    tbcPane.hidden = pane !== 'tbc';
                }
                document.querySelectorAll('[data-detail-pane]').forEach(function (btn) {
                    var active = btn.getAttribute('data-detail-pane') === pane;
                    btn.classList.toggle('is-active', active);
                    btn.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                renderDetailCards();
            }

            function setSapFilterCounts(counts) {
                document.querySelectorAll('[data-sap-filter]').forEach(function (btn) {
                    var key = btn.getAttribute('data-sap-filter');
                    var label = {
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

            function setTbcFilterCounts(counts) {
                document.querySelectorAll('[data-tbc-filter]').forEach(function (btn) {
                    var key = btn.getAttribute('data-tbc-filter');
                    var label = {
                        all: 'Semua',
                        sudah: 'Sudah TBC',
                        belum: 'Belum TBC'
                    }[key] || key;
                    var n = counts && counts[key] != null ? counts[key] : 0;
                    btn.textContent = label + ' (' + n + ')';
                });
            }

            function tbcBadge(card) {
                if (card.tbc === 'sudah') {
                    return '<span class="ocr-sap-badge is-tbc-yes">Sudah TBC</span>';
                }
                if (card.tbc === 'belum') {
                    return '<span class="ocr-sap-badge is-tbc-no">Belum TBC</span>';
                }
                return '<span class="ocr-sap-badge is-tbc-wait">TBC belum termuat</span>';
            }

            function sapPhotoMarkup(url, label, reportId) {
                if (!url) {
                    return '';
                }
                return '<figure class="ocr-sap-photo">'
                    + '<figcaption class="ocr-sap-photo-label">' + escapeHtml(label) + '</figcaption>'
                    + '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener">'
                    + '<img src="' + escapeHtml(url) + '" alt="' + escapeHtml(label + ' ' + reportId) + '" loading="lazy" onerror="this.closest(\'figure\').hidden=true">'
                    + '</a></figure>';
            }

            function sapPhotoBlock(card) {
                if (card.photo_page_id) {
                    var kind = card.photo_page_kind || 'photocar';
                    return '<div class="ocr-sap-photos" data-photo-page="' + escapeHtml(String(card.photo_page_id)) + '" data-photo-kind="' + escapeHtml(kind) + '" data-report-id="' + escapeHtml(card.id) + '">'
                        + '<p class="ocr-sap-muted">Memuat foto…</p>'
                        + '</div>';
                }
                if (card.photo_url) {
                    return sapPhotoMarkup(card.photo_url, 'Foto', card.id);
                }
                return '';
            }

            function hydrateSapPhotos() {
                sapGrid.querySelectorAll('[data-photo-page]').forEach(function (el) {
                    var id = el.getAttribute('data-photo-page');
                    var kind = el.getAttribute('data-photo-kind') || 'photocar';
                    var reportId = el.getAttribute('data-report-id') || id;
                    fetch(sapPhotosUrl + '?id=' + encodeURIComponent(id) + '&kind=' + encodeURIComponent(kind), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                        .then(function (result) {
                            var data = (result.ok && result.body && result.body.data) ? result.body.data : {};
                            var html = sapPhotoMarkup(data.foto_temuan, 'Foto Temuan', reportId)
                                + sapPhotoMarkup(data.foto_penyelesaian, 'Foto Penyelesaian', reportId);
                            el.innerHTML = html;
                        })
                        .catch(function () {
                            el.innerHTML = '';
                        });
                });
            }

            function renderSapCards() {
                var list = sapFilter === 'all' ? sapCards : sapCards.filter(function (card) {
                    return card.type === sapFilter;
                });
                renderCardList(list, sapCards.length, 'Tidak ada laporan untuk filter ini.', false);
            }

            function renderTbcCards() {
                var list = tbcFilter === 'all' ? tbcCards : tbcCards.filter(function (card) {
                    return card.tbc === tbcFilter;
                });
                var empty = tbcCards.length
                    ? 'Tidak ada Hazard/Inspeksi untuk filter TBC ini.'
                    : 'Tidak ada Hazard/Inspeksi pada jendela jaga ini.';
                renderCardList(list, tbcCards.length, empty, true);
            }

            function renderDetailCards() {
                if (detailPane === 'tbc') {
                    renderTbcCards();
                    return;
                }
                renderSapCards();
            }

            function renderCardList(list, sourceCount, emptyText, showTbc) {
                if (!list.length) {
                    sapGrid.innerHTML = '';
                    sapStatus.hidden = false;
                    if (sourceCount || detailPane === 'tbc') {
                        sapStatus.textContent = emptyText;
                    }
                    return;
                }
                sapStatus.hidden = true;
                sapGrid.innerHTML = list.map(function (card) {
                    var photo = sapPhotoBlock(card);
                    var geotag = card.geotag
                        ? '<p class="ocr-sap-geotag">GEOTAGGING Jam: ' + escapeHtml(card.geotag) + '</p>'
                        : '';
                    var status = (card.status && card.status !== '—')
                        ? '<span class="ocr-sap-badge ' + ((card.status || '').toLowerCase() === 'closed' ? 'is-closed' : 'is-plain') + '">' + escapeHtml(card.status) + '</span>'
                        : '';
                    var tbcMark = showTbc ? tbcBadge(card) : '';
                    return '<article class="ocr-sap-card" data-type="' + escapeHtml(card.type) + '">'
                        + photo
                        + '<p class="ocr-sap-id">' + escapeHtml(card.id) + '</p>'
                        + geotag
                        + '<p>Submit BEATS: ' + escapeHtml(card.submitted_label || '—') + '</p>'
                        + '<p class="ocr-sap-headline">' + escapeHtml(card.headline) + '</p>'
                        + (card.subcategory ? '<p>' + escapeHtml(card.subcategory) + '</p>' : '')
                        + (card.description ? '<p class="ocr-sap-desc">' + escapeHtml(card.description) + '</p>' : '')
                        + (card.pic ? '<p>PIC: ' + escapeHtml(card.pic) + '</p>' : '')
                        + (card.pic_meta && card.pic_meta !== '—' ? '<p class="ocr-sap-muted">' + escapeHtml(card.pic_meta) + '</p>' : '')
                        + (card.reporter ? '<p>Pelapor: ' + escapeHtml(card.reporter) + '</p>' : '')
                        + (card.reporter_meta && card.reporter_meta !== '—' ? '<p class="ocr-sap-muted">' + escapeHtml(card.reporter_meta) + '</p>' : '')
                        + (card.location ? '<p>Lokasi: ' + escapeHtml(card.location) + '</p>' : '')
                        + (card.location_detail ? '<p>Detail Lok: ' + escapeHtml(card.location_detail) + '</p>' : '')
                        + tbcMark
                        + status
                        + '</article>';
                }).join('');
                hydrateSapPhotos();
            }

            document.querySelectorAll('[data-sap-filter]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    sapFilter = btn.getAttribute('data-sap-filter');
                    document.querySelectorAll('[data-sap-filter]').forEach(function (el) { el.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                    renderSapCards();
                });
            });

            document.querySelectorAll('[data-tbc-filter]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    tbcFilter = btn.getAttribute('data-tbc-filter');
                    document.querySelectorAll('[data-tbc-filter]').forEach(function (el) { el.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                    renderTbcCards();
                });
            });

            document.querySelectorAll('[data-detail-pane]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setDetailPane(btn.getAttribute('data-detail-pane'));
                });
            });

            (function initHighlightDetail() {
                var modalEl = document.getElementById('ocr-highlight-modal');
                var titleEl = document.getElementById('ocr-highlight-title');
                var metaEl = document.getElementById('ocr-highlight-meta');
                var bodyEl = document.getElementById('ocr-highlight-body');
                var emptyEl = document.getElementById('ocr-highlight-empty');
                if (!modalEl || !bodyEl) {
                    return;
                }
                var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

                function highlightItems(kind, name) {
                    if (kind === 'golden_rule') {
                        var rules = highlightData.goldenRules || [];
                        for (var i = 0; i < rules.length; i += 1) {
                            if (rules[i].name === name) {
                                return rules[i].items || [];
                            }
                        }
                        return [];
                    }
                    if (kind === 'blindspot') {
                        return highlightData.blindspotItems || [];
                    }
                    return highlightData.tbcItems || [];
                }

                function highlightTitle(kind, name) {
                    if (kind === 'golden_rule') {
                        return name || 'Golden Rule';
                    }
                    if (kind === 'blindspot') {
                        return 'Blindspot';
                    }
                    return 'Ratio TBC';
                }

                document.querySelectorAll('[data-highlight-kind]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var kind = btn.getAttribute('data-highlight-kind');
                        var name = btn.getAttribute('data-highlight-name') || '';
                        var items = highlightItems(kind, name);
                        titleEl.textContent = 'Highlight — ' + highlightTitle(kind, name);
                        metaEl.textContent = items.length + ' temuan';
                        bodyEl.innerHTML = items.map(function (item) {
                            return '<tr>'
                                + '<td>' + escapeHtml(item.tasklist || '—') + '</td>'
                                + '<td>' + escapeHtml(item.found_at || '—') + '</td>'
                                + '<td>' + escapeHtml(item.description || '—') + '</td>'
                                + '<td>' + escapeHtml(item.company_pic || '—') + '</td>'
                                + '<td>' + escapeHtml(item.status || '—') + '</td>'
                                + '</tr>';
                        }).join('');
                        emptyEl.hidden = items.length > 0;
                        var tableWrap = modalEl.querySelector('.table-responsive');
                        if (tableWrap) {
                            tableWrap.hidden = items.length === 0;
                        }
                        modal.show();
                    });
                });
            })();

            document.querySelectorAll('.ocr-detail-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var sid = btn.getAttribute('data-sid');
                    var date = btn.getAttribute('data-date');
                    var shift = btn.getAttribute('data-shift');
                    var name = btn.getAttribute('data-name') || sid;
                    sapCards = [];
                    tbcCards = [];
                    tbcLoaded = false;
                    sapFilter = 'all';
                    tbcFilter = 'all';
                    sapGrid.innerHTML = '';
                    sapFilters.hidden = true;
                    if (tbcFilters) {
                        tbcFilters.hidden = true;
                    }
                    sapStatus.hidden = false;
                    sapStatus.textContent = 'Memuat laporan…';
                    sapTitle.textContent = 'Detail — ' + name;
                    sapMeta.textContent = shift + ' · ' + date + ' · SID ' + sid;
                    setSapFilterCounts({ all: 0, hazard: 0, inspeksi: 0, observasi: 0, oak: 0 });
                    setTbcFilterCounts({ all: 0, sudah: 0, belum: 0 });
                    document.querySelectorAll('[data-sap-filter]').forEach(function (el) { el.classList.toggle('is-active', el.getAttribute('data-sap-filter') === 'all'); });
                    document.querySelectorAll('[data-tbc-filter]').forEach(function (el) { el.classList.toggle('is-active', el.getAttribute('data-tbc-filter') === 'all'); });
                    setDetailPane('sap');
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
                            tbcCards = data.tbc_cards || [];
                            tbcLoaded = !!data.tbc_loaded;
                            sapMeta.textContent = shift + ' · jendela ' + (data.window_start || '') + ' – ' + (data.window_end || '') + ' · SID ' + sid;
                            setSapFilterCounts(data.counts || {});
                            setTbcFilterCounts(data.tbc_counts || {});
                            sapFilters.hidden = false;
                            if (tbcFilters) {
                                tbcFilters.hidden = false;
                            }
                            if (!data.reachable) {
                                sapStatus.textContent = (data.errors && data.errors.length) ? data.errors.join(' ') : 'Sumber SAP (OBDS) tidak terjangkau.';
                                sapGrid.innerHTML = '';
                                return;
                            }
                            if (data.errors && data.errors.length && !sapCards.length) {
                                sapStatus.textContent = data.errors.join(' ');
                                sapGrid.innerHTML = '';
                                return;
                            }
                            if (!sapCards.length) {
                                sapStatus.textContent = 'Tidak ada laporan SAP pada hari jaga dan H+1.';
                                sapGrid.innerHTML = '';
                                return;
                            }
                            var extra = [];
                            if (data.truncated) extra.push('Menampilkan maksimal 40 laporan per jenis.');
                            if (data.errors && data.errors.length) extra.push(data.errors.join(' '));
                            if (detailPane === 'tbc' && !tbcLoaded) extra.push('Sumber TBC belum termuat.');
                            sapStatus.textContent = extra.join(' ');
                            sapStatus.hidden = extra.length === 0;
                            renderDetailCards();
                        })
                        .catch(function () {
                            sapStatus.textContent = 'Gagal memuat detail SAP.';
                        });
                });
            });
        })();
    </script>
@endpush
