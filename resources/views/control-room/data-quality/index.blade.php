@extends('control-room.layouts.app')

@section('page-title', 'Data Quality')

@php
    $weekRangeLabel = $weekStart->locale('id')->translatedFormat('d M').' – '.$weekEnd->locale('id')->translatedFormat('d M Y');
    $filter = ['year' => $year, 'week' => $week];
    if ($site !== null) {
        $filter['site'] = $site->value;
    }
    $rows = $quality['rows'];
    $kpi = $quality['kpi'];
    $badge = [
        'Belum ada temuan' => 'is-empty',
        'Perlu perbaikan' => 'is-low',
        'Cukup' => 'is-mid',
        'Baik' => 'is-good',
    ];
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-dashboard.css') }}?v={{ filemtime(public_path('wowdash-admin/assets/css/control-room-dashboard.css')) }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-data-quality.css') }}?v={{ filemtime(public_path('wowdash-admin/assets/css/control-room-data-quality.css')) }}">
@endpush

@section('content')
    <div class="ocr-dash ocr-dq">
        <form method="GET" class="ocr-card" action="{{ route('control-room.data-quality.index') }}">
            <div class="ocr-toolbar">
                <div class="ocr-toolbar-left">
                    <div>
                        <label for="ocr-dq-site">Site</label>
                        <select name="site" id="ocr-dq-site" class="form-control" onchange="this.form.submit()">
                            <option value="ALL" @selected($site === null)>Semua site operasi</option>
                            @foreach ($boardSites as $siteOption)
                                <option value="{{ $siteOption->value }}" @selected($site?->value === $siteOption->value)>{{ $siteOption->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="week" value="{{ $week }}">
                    <div>
                        <label>Minggu</label>
                        <div class="ocr-week-stepper">
                            <a href="{{ route('control-room.data-quality.index', array_merge($filter, ['year' => $prevYear, 'week' => $prevWeek])) }}" aria-label="Minggu sebelumnya">
                                <i class="ri-arrow-left-s-line"></i>
                            </a>
                            <div class="ocr-week-label">
                                <strong>{{ $weekRangeLabel }}</strong>
                                <span>Minggu {{ $week }} · {{ $year }}</span>
                            </div>
                            <a href="{{ route('control-room.data-quality.index', array_merge($filter, ['year' => $nextYear, 'week' => $nextWeek])) }}" aria-label="Minggu berikutnya">
                                <i class="ri-arrow-right-s-line"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="ocr-toolbar-right">
                    <span class="ocr-sync">Evaluasi SAP unik H s/d H+1 · TBC / GR / Blindspot belum dipakai</span>
                </div>
            </div>
        </form>

        @if (! $quality['loaded'])
            <div class="ocr-notice" role="status">
                <i class="ri-error-warning-line"></i>
                <span>Sumber SAP (OBDS) tidak terjangkau. Personil jadwal tetap tampil, skor temuan kosong.</span>
            </div>
        @endif

        <div class="ocr-kpi-grid ocr-dq-kpi">
            <div class="ocr-card ocr-kpi">
                <p class="ocr-kpi-label">Personil jaga</p>
                <p class="ocr-kpi-value">{{ $kpi['personnel'] }}</p>
            </div>
            <div class="ocr-card ocr-kpi">
                <p class="ocr-kpi-label">Rata-rata komposit</p>
                <p class="ocr-kpi-value">{{ $kpi['avg_composite'] === null ? '—' : number_format($kpi['avg_composite'], 1) }}</p>
            </div>
            <div class="ocr-card ocr-kpi">
                <p class="ocr-kpi-label">Rata-rata kelengkapan mix</p>
                <p class="ocr-kpi-value">{{ $kpi['avg_mix'] === null ? '—' : number_format($kpi['avg_mix'], 1).'%' }}</p>
            </div>
            <div class="ocr-card ocr-kpi">
                <p class="ocr-kpi-label">Distribusi evaluasi</p>
                <p class="ocr-dq-dist">
                    <span>Baik {{ $kpi['labels']['Baik'] }}</span>
                    <span>Cukup {{ $kpi['labels']['Cukup'] }}</span>
                    <span>Perbaikan {{ $kpi['labels']['Perlu perbaikan'] }}</span>
                    <span>Kosong {{ $kpi['labels']['Belum ada temuan'] }}</span>
                </p>
            </div>
        </div>

        <div class="ocr-dq-charts">
            <div class="ocr-card">
                <div class="ocr-card-header">
                    <div>
                        <h6 id="ocr-dq-radar-title">Radar kualitas</h6>
                        <p class="ocr-card-kicker">Klik baris tabel untuk membandingkan enam sumbu orang itu.</p>
                    </div>
                </div>
                <div class="ocr-card-body"><div id="chart-dq-radar"></div></div>
            </div>
            <div class="ocr-card">
                <div class="ocr-card-header">
                    <div>
                        <h6>Volume vs Variasi</h6>
                        <p class="ocr-card-kicker">Kanan-atas = banyak laporan dan kategori beragam.</p>
                    </div>
                </div>
                <div class="ocr-card-body"><div id="chart-dq-scatter"></div></div>
            </div>
        </div>

        <div class="ocr-card">
            <div class="ocr-card-header">
                <div>
                    <h6>Evaluasi personil</h6>
                    <p class="ocr-card-kicker">Total = laporan unik Hazard + Inspeksi + Observasi + OAK. Mix = % SAP 1+1+1 per hari jaga. Waktu = % di hari H. Kedalaman = teks temuan plus foto atau geotag.</p>
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
                            @forelse ($rows as $index => $row)
                                <tr class="ocr-dq-row{{ $index === 0 ? ' is-current' : '' }}" data-sid="{{ $row['sid'] }}" tabindex="0">
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
                                    <td>
                                        <span class="ocr-dq-badge {{ $badge[$row['label']] ?? 'is-empty' }}">{{ $row['label'] }}</span>
                                    </td>
                                    <td>{{ $row['weakest_label'] ? 'lemah di '.$row['weakest_label'] : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-secondary-light">Belum ada personil jadwal pada minggu yang dipilih.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var rows = @json($rows);
            var radarEl = document.getElementById('chart-dq-radar');
            var scatterEl = document.getElementById('chart-dq-scatter');
            var titleEl = document.getElementById('ocr-dq-radar-title');
            var axes = ['Kelengkapan', 'Volume', 'Variasi', 'Waktu', 'Lokasi', 'Kedalaman'];
            var radarChart = null;

            function renderRadar(row) {
                if (!radarEl || !window.ApexCharts) {
                    return;
                }
                var options = {
                    chart: { type: 'radar', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
                    series: [{ name: row ? row.name : 'Personil', data: row ? row.radar : [0, 0, 0, 0, 0, 0] }],
                    xaxis: { categories: axes },
                    yaxis: { min: 0, max: 100, tickAmount: 4, labels: { formatter: function (v) { return Math.round(v); } } },
                    colors: ['#3952bc'],
                    markers: { size: 4 },
                    stroke: { width: 2 },
                    fill: { opacity: 0.18 },
                };
                if (radarChart) {
                    radarChart.updateOptions(options, false, true);
                    return;
                }
                radarChart = new ApexCharts(radarEl, options);
                radarChart.render();
            }

            function selectRow(sid) {
                document.querySelectorAll('.ocr-dq-row').forEach(function (el) {
                    el.classList.toggle('is-current', el.getAttribute('data-sid') === sid);
                });
                var row = rows.find(function (item) { return item.sid === sid; }) || rows[0] || null;
                if (titleEl) {
                    titleEl.textContent = row ? 'Radar · ' + row.name : 'Radar kualitas';
                }
                renderRadar(row);
            }

            if (rows.length > 0) {
                selectRow(rows[0].sid);
            } else {
                renderRadar(null);
            }

            document.querySelectorAll('.ocr-dq-row').forEach(function (el) {
                el.addEventListener('click', function () { selectRow(el.getAttribute('data-sid')); });
                el.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        selectRow(el.getAttribute('data-sid'));
                    }
                });
            });

            var scatterRows = rows.filter(function (row) { return row.total > 0; });
            if (scatterEl && window.ApexCharts) {
                new ApexCharts(scatterEl, {
                    chart: { type: 'scatter', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
                    series: [{
                        name: 'Personil',
                        data: scatterRows.map(function (row) { return [row.total, row.scores.variety / 100]; }),
                    }],
                    xaxis: { title: { text: 'Volume Temuan' }, tickAmount: 5, min: 0 },
                    yaxis: { title: { text: 'Variasi' }, min: 0, max: 1 },
                    colors: ['#487FFF'],
                    grid: { borderColor: 'rgba(209, 213, 219, 0.4)', strokeDashArray: 4 },
                    tooltip: {
                        custom: function (opts) {
                            var row = scatterRows[opts.dataPointIndex];
                            if (!row) {
                                return '';
                            }
                            return '<div class="p-8 text-xs"><strong>' + row.name + '</strong><br>Total: ' + row.total + '<br>Variasi: ' + row.scores.variety + '<br>' + row.label + '</div>';
                        },
                    },
                }).render();
            }
        })();
    </script>
@endpush
