@extends('control-room.layouts.app')

@section('page-title', 'Monitoring Pengawas')

@php
    $filter = ['site' => $siteParam, 'year' => $year, 'week' => $week, 'iso_week' => $isoWeekValue];
    $days = $monitor['days'];
    $rows = $monitor['rows'];
    $dq = $monitor['dataQuality'];
    $dqRows = $dq['rows'];
    $dqKpi = $dq['kpi'];
    $personnelCoverage = $monitor['personnelCoverage'];
    $personnelLokasiMax = max(1, ...(array_column($personnelCoverage, 'lokasi') ?: [0]));
    $personnelKritisMax = max(1, ...(array_column($personnelCoverage, 'kritis') ?: [0]));
    $dqBadge = [
        'Belum ada temuan' => 'is-empty',
        'Perlu perbaikan' => 'is-low',
        'Cukup' => 'is-mid',
        'Baik' => 'is-good',
    ];
    $heatClass = static function (?float $value): string {
        if ($value === null) {
            return 'is-heat-empty';
        }
        if ($value >= 100) {
            return 'is-heat-100';
        }

        return $value >= 60 ? 'is-heat-mid' : 'is-heat-low';
    };
    $pctLabel = static function (?float $value): string {
        if ($value === null) {
            return '—';
        }

        return (abs($value - round($value)) < 0.05 ? number_format($value, 0) : number_format($value, 1)).'%';
    };
    $searchKey = static fn (string $name, string $sid = ''): string => mb_strtolower(trim($name.' '.$sid));
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-dashboard.css') }}?v={{ filemtime(public_path('wowdash-admin/assets/css/control-room-dashboard.css')) }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-data-quality.css') }}?v={{ filemtime(public_path('wowdash-admin/assets/css/control-room-data-quality.css')) }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-pengawas.css') }}?v={{ filemtime(public_path('wowdash-admin/assets/css/control-room-pengawas.css')) }}">
@endpush

@section('content')
    <div class="ocr-dash ocr-dq ocr-pg">
        <form method="GET" class="ocr-card" action="{{ route('control-room.pengawas.index') }}">
            <div class="ocr-toolbar">
                <div class="ocr-toolbar-left">
                    <div>
                        <label for="ocr-pg-site">Site</label>
                        <select name="site" id="ocr-pg-site" class="form-control" onchange="this.form.submit()">
                            <option value="ALL" @selected($site === null)>Semua site</option>
                            @foreach ($sites as $siteOption)
                                <option value="{{ $siteOption->value }}" @selected($site?->value === $siteOption->value)>{{ $siteOption->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('control-room.partials.week-period-filter', [
                        'weekInputId' => 'ocr-pg-iso-week',
                        'isoWeekValue' => $isoWeekValue,
                        'weekRangeLabel' => $weekRangeLabel,
                        'year' => $year,
                        'week' => $week,
                        'prevWeekUrl' => route('control-room.pengawas.index', array_merge($filter, ['year' => $prevYear, 'week' => $prevWeek, 'iso_week' => null])),
                        'nextWeekUrl' => route('control-room.pengawas.index', array_merge($filter, ['year' => $nextYear, 'week' => $nextWeek, 'iso_week' => null])),
                    ])
                    <div>
                        <label for="ocr-pg-search">Cari pengawas</label>
                        <input type="search" id="ocr-pg-search" class="form-control ocr-pg-search" placeholder="Nama atau SID" autocomplete="off">
                    </div>
                </div>
                <div class="ocr-toolbar-right">
                    <span class="ocr-sync">{{ $monitor['people'] }} pengawas · laporan SAP dihitung per hari (H saja)</span>
                </div>
            </div>
        </form>

        @if (! $monitor['loaded'])
            <div class="ocr-notice" role="status">
                <i class="ri-error-warning-line"></i>
                <span>Sumber SAP (OBDS) tidak terjangkau. Daftar pengawas tetap tampil, angka SAP kosong. Muat ulang beberapa saat lagi.</span>
            </div>
        @endif

        <div class="ocr-kpi-grid">
            @foreach ($monitor['kpi'] as $card)
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

        <div class="ocr-widget-tables">
            <div class="ocr-card">
                <div class="ocr-card-header">
                    <div>
                        <h6>Pencapaian Personil</h6>
                        <p class="ocr-card-kicker">% SAP harian per pengawas berdasarkan target 1 Hazard, 1 Inspeksi, 1 Observasi/OAK pada tanggal itu. % TBC = valid TBC ÷ (Hazard + Inspeksi) selama minggu ini. Klik angka hari untuk detail laporan.</p>
                    </div>
                </div>
                <div class="ocr-card-body ocr-card-body--flush">
                    <div class="table-responsive">
                        <table class="ocr-heat ocr-pg-table">
                            <thead>
                                <tr>
                                    <th class="ocr-pg-sticky">Nama</th>
                                    @foreach ($days as $day)
                                        <th class="ocr-pg-day{{ $day['is_today'] ? ' is-today' : '' }}">{{ $day['weekday'] }}<small>{{ $day['label'] }}</small></th>
                                    @endforeach
                                    <th class="text-center">Hari aktif</th>
                                    <th class="text-center">Avg % SAP</th>
                                    <th class="text-center">% TBC</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr data-pg-search="{{ $searchKey($row['name'], $row['sid']) }}">
                                        <td class="ocr-heat-name ocr-pg-sticky">
                                            {{ $row['name'] }}
                                            <span class="ocr-pg-site">{{ $row['sid'] }} · {{ $row['site_label'] }}</span>
                                        </td>
                                        @foreach ($days as $day)
                                            @php $cell = $row['cells'][$day['date']]; @endphp
                                            <td class="ocr-heat-cell {{ $heatClass($cell['sap']) }}">
                                                <button
                                                    type="button"
                                                    class="ocr-detail-btn ocr-pg-cell-btn"
                                                    title="{{ $cell['hint'] }}"
                                                    data-sid="{{ $row['sid'] }}"
                                                    data-date="{{ $day['date'] }}"
                                                    data-shift="Harian"
                                                    data-name="{{ $row['name'] }}"
                                                    aria-label="Detail SAP {{ $row['name'] }} {{ $day['label'] }}"
                                                >{{ $pctLabel($cell['sap']) }}</button>
                                            </td>
                                        @endforeach
                                        <td class="text-center">{{ $row['active_days'] === null ? '—' : $row['active_days'].'/'.$row['running_days'] }}</td>
                                        <td class="ocr-heat-cell {{ $heatClass($row['avg_sap']) }}">{{ $pctLabel($row['avg_sap']) }}</td>
                                        <td class="ocr-heat-cell {{ $heatClass($row['tbc']) }}" title="{{ $row['tbc_hint'] }}">{{ $pctLabel($row['tbc']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($days) + 4 }}" class="text-secondary-light">
                                            {{ $days === [] ? 'Minggu terpilih belum berjalan.' : 'Tidak ada pengawas pada site ini.' }}
                                        </td>
                                    </tr>
                                @endforelse
                                <tr class="ocr-pg-empty-filter" hidden>
                                    <td colspan="{{ count($days) + 4 }}" class="text-secondary-light">Tidak ada pengawas yang cocok dengan pencarian.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="ocr-card">
                <div class="ocr-card-header">
                    <div>
                        <h6>Coverage Personil</h6>
                        <p class="ocr-card-kicker">Lokasi unik yang dilaporkan pengawas minggu ini · kritis mengikuti CONTAINS Lokasi/Detil Lokasi</p>
                    </div>
                </div>
                <div class="ocr-card-body ocr-card-body--flush">
                    <div class="table-responsive">
                        <table class="ocr-heat ocr-heat--coverage">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th class="text-center">Coverage Detail Lokasi</th>
                                    <th class="text-center">Coverage Area Kritis</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($personnelCoverage as $row)
                                    <tr class="{{ $row['lead'] ? 'is-lead' : '' }}" data-pg-search="{{ $searchKey($row['name']) }}">
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
                                        <td colspan="3" class="text-secondary-light">Belum ada laporan pengawas untuk dihitung coverage.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @include('control-room.partials.insights-panels', [
            'highlight' => $monitor['highlight'],
            'quality' => $monitor['quality'],
            'qualityEmpty' => 'Belum ada pengawas pada minggu yang dipilih.',
        ])

        <h5 class="ocr-pg-section-title" id="ocr-pg-dq-title">Data Quality Pengawas</h5>

        <div class="ocr-kpi-grid ocr-dq-kpi" aria-labelledby="ocr-pg-dq-title">
            <div class="ocr-card ocr-kpi">
                <p class="ocr-kpi-label">Pengawas dievaluasi</p>
                <p class="ocr-kpi-value">{{ $dqKpi['personnel'] }}</p>
            </div>
            <div class="ocr-card ocr-kpi">
                <p class="ocr-kpi-label">Rata-rata komposit</p>
                <p class="ocr-kpi-value">{{ $dqKpi['avg_composite'] === null ? '—' : number_format($dqKpi['avg_composite'], 1) }}</p>
            </div>
            <div class="ocr-card ocr-kpi">
                <p class="ocr-kpi-label">Rata-rata kelengkapan mix</p>
                <p class="ocr-kpi-value">{{ $dqKpi['avg_mix'] === null ? '—' : number_format($dqKpi['avg_mix'], 1).'%' }}</p>
            </div>
            <div class="ocr-card ocr-kpi">
                <p class="ocr-kpi-label">Distribusi evaluasi</p>
                <p class="ocr-dq-dist">
                    <span>Baik {{ $dqKpi['labels']['Baik'] }}</span>
                    <span>Cukup {{ $dqKpi['labels']['Cukup'] }}</span>
                    <span>Perbaikan {{ $dqKpi['labels']['Perlu perbaikan'] }}</span>
                    <span>Kosong {{ $dqKpi['labels']['Belum ada temuan'] }}</span>
                </p>
            </div>
        </div>

        <div class="row gy-4">
            <div class="col-lg-4">
                <div class="ocr-card h-100">
                    <div class="ocr-card-header">
                        <div>
                            <h6 id="ocr-dq-radar-title">Radar kualitas</h6>
                            <p class="ocr-card-kicker">Klik baris tabel untuk melihat lima sumbu pengawas itu.</p>
                        </div>
                    </div>
                    <div class="ocr-card-body"><div id="chart-dq-radar"></div></div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="ocr-card h-100">
                    <div class="ocr-card-header">
                        <div>
                            <h6>Evaluasi pengawas</h6>
                            <p class="ocr-card-kicker">Total = laporan unik Hazard + Inspeksi + Observasi + OAK. Mix = % SAP 1+1+1 per hari. Kedalaman = teks temuan plus foto atau geotag. Komposit dari 5 sumbu — disiplin waktu tidak dinilai karena laporan pengawas dihitung per hari.</p>
                        </div>
                    </div>
                    <div class="ocr-card-body ocr-card-body--flush">
                        <div class="table-responsive">
                            <table class="ocr-table ocr-dq-table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Site</th>
                                        <th class="text-center">Hari</th>
                                        <th class="text-center">H</th>
                                        <th class="text-center">I</th>
                                        <th class="text-center">O</th>
                                        <th class="text-center">OAK</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">Komposit</th>
                                        <th>Evaluasi</th>
                                        <th>Sumbu terlemah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($dqRows as $index => $row)
                                        <tr class="ocr-dq-row{{ $index === 0 ? ' is-current' : '' }}" data-sid="{{ $row['sid'] }}" data-pg-search="{{ $searchKey($row['name'], $row['sid']) }}" tabindex="0">
                                            <td>
                                                <strong>{{ $row['name'] }}</strong>
                                                <span class="ocr-dq-sid">{{ $row['sid'] }}</span>
                                            </td>
                                            <td>{{ $row['site_label'] }}</td>
                                            <td class="text-center">{{ $row['duty_count'] }}</td>
                                            <td class="text-center">{{ $row['hazard'] }}</td>
                                            <td class="text-center">{{ $row['inspeksi'] }}</td>
                                            <td class="text-center">{{ $row['observasi'] }}</td>
                                            <td class="text-center">{{ $row['oak'] }}</td>
                                            <td class="text-center">{{ $row['total'] }}</td>
                                            <td class="text-center">{{ number_format($row['composite'], 1) }}</td>
                                            <td><span class="ocr-dq-badge {{ $dqBadge[$row['label']] ?? 'is-empty' }}">{{ $row['label'] }}</span></td>
                                            <td>{{ $row['weakest_label'] ? 'lemah di '.$row['weakest_label'] : '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-secondary-light">Belum ada pengawas untuk dievaluasi pada minggu yang dipilih.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('control-room.partials.insights-modals')
@endsection

@push('scripts')
    <script src="{{ asset('wowdash-admin/assets/js/control-room-insights.js') }}?v={{ filemtime(public_path('wowdash-admin/assets/js/control-room-insights.js')) }}"></script>
    <script>
        (function () {
            [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).forEach(function (el) {
                new bootstrap.Tooltip(el);
            });

            OcrInsights.init({
                pareto: @json($monitor['pareto']),
                quality: @json($monitor['quality']),
                highlight: @json($monitor['highlight']),
                sapDetailUrl: @json(route('control-room.pengawas.sap-detail')),
                sapPhotosUrl: @json(route('control-room.dashboard.sap-photos')),
                emptyDetailText: 'Tidak ada laporan SAP pada tanggal ini.'
            });

            var dqRows = @json($dqRows);
            var radarEl = document.getElementById('chart-dq-radar');
            var radarTitle = document.getElementById('ocr-dq-radar-title');
            var radarChart = null;

            function renderRadar(row) {
                if (!radarEl || !window.ApexCharts) {
                    return;
                }
                var options = {
                    chart: { type: 'radar', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
                    series: [{ name: row ? row.name : 'Pengawas', data: row ? row.radar : [0, 0, 0, 0, 0] }],
                    xaxis: { categories: ['Kelengkapan', 'Volume', 'Variasi', 'Lokasi', 'Kedalaman'] },
                    yaxis: { min: 0, max: 100, tickAmount: 4, labels: { formatter: function (v) { return Math.round(v); } } },
                    colors: ['#3952bc'],
                    markers: { size: 4 },
                    stroke: { width: 2 },
                    fill: { opacity: 0.18 }
                };
                if (radarChart) {
                    radarChart.updateOptions(options, false, true);
                    return;
                }
                radarChart = new ApexCharts(radarEl, options);
                radarChart.render();
            }

            function selectDqRow(sid) {
                document.querySelectorAll('.ocr-dq-row').forEach(function (el) {
                    el.classList.toggle('is-current', el.getAttribute('data-sid') === sid);
                });
                var row = dqRows.find(function (item) { return item.sid === sid; }) || dqRows[0] || null;
                radarTitle.textContent = row ? 'Radar · ' + row.name : 'Radar kualitas';
                renderRadar(row);
            }

            selectDqRow(dqRows.length ? dqRows[0].sid : null);
            document.querySelectorAll('.ocr-dq-row').forEach(function (el) {
                el.addEventListener('click', function () { selectDqRow(el.getAttribute('data-sid')); });
                el.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        selectDqRow(el.getAttribute('data-sid'));
                    }
                });
            });

            var search = document.getElementById('ocr-pg-search');
            var emptyFilter = document.querySelector('.ocr-pg-empty-filter');
            search.addEventListener('input', function () {
                var query = search.value.trim().toLowerCase();
                var visibleAchievement = 0;
                document.querySelectorAll('[data-pg-search]').forEach(function (row) {
                    var show = query === '' || row.getAttribute('data-pg-search').indexOf(query) !== -1;
                    row.hidden = !show;
                    if (show && row.closest('.ocr-pg-table')) {
                        visibleAchievement++;
                    }
                });
                if (emptyFilter) {
                    emptyFilter.hidden = query === '' || visibleAchievement > 0;
                }
            });
        })();
    </script>
@endpush
