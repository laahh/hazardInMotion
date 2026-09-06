@extends('control-room.layouts.app')

@section('page-title', 'Dashboard')

@section('content')
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-24">
        <i class="ri-information-line text-xl"></i>
        <div>
            <strong>Ini mockup visual, bukan data asli.</strong> Semua angka di halaman ini contoh/fiktif —
            dibuat supaya ada gambaran layout sebelum pipeline data sungguhan (Fase 4 normalisasi SAP &amp;
            Fase 5 agregasi) selesai. T0.1 sendiri sudah selesai diverifikasi (lihat <code>plan-OCR.md</code>
            bagian 0.6) — yang masih menunggu adalah keputusan desain agregasi (#27) dan beberapa sumber
            lain (Sheet ID TBC #23, definisi kolom blindspot #24). Lihat <code>plan-OCR.md</code> Lampiran D.
        </div>
    </div>

    <form method="GET" class="card shadow-none border mb-24">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-sm mb-1">Site</label>
                    <select name="site" class="form-control">
                        @foreach ($sites as $siteOption)
                            <option value="{{ $siteOption->value }}" @selected($site === $siteOption)>{{ $siteOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-sm mb-1">Tahun</label>
                    <input type="number" name="year" value="{{ $year }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-sm mb-1">Minggu</label>
                    <input type="number" name="week" value="{{ $week }}" min="1" max="53" class="form-control">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary-600 w-100">Terapkan Filter</button>
                </div>
                <div class="col-md-3 text-md-end">
                    <span class="text-secondary-light text-xs">Last Data Update: <em>mockup — belum ada sync asli</em></span>
                </div>
            </div>
        </div>
    </form>

    {{-- T6.2 — Panel KPI Header --}}
    <div class="row gy-4 mb-24">
        @foreach ($mock['kpi'] as $card)
            <div class="col-xxl-2 col-md-4 col-sm-6">
                <div class="card shadow-none border h-100" title="{{ $card['formula'] }}">
                    <div class="card-body p-20">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-8">
                            <div class="w-40-px h-40-px bg-{{ $card['color'] }}-100 text-{{ $card['color'] }}-600 rounded-circle d-flex justify-content-center align-items-center">
                                <i class="{{ $card['icon'] }}"></i>
                            </div>
                            @if ($card['delta'] > 0)
                                <span class="text-success-600 text-xs fw-semibold"><i class="ri-arrow-up-line"></i> {{ $card['delta'] }}</span>
                            @elseif ($card['delta'] < 0)
                                <span class="text-danger-600 text-xs fw-semibold"><i class="ri-arrow-down-line"></i> {{ abs($card['delta']) }}</span>
                            @else
                                <span class="text-secondary-light text-xs">—</span>
                            @endif
                        </div>
                        <h6 class="mb-4">{{ $card['value'] }}</h6>
                        <p class="text-secondary-light text-xs mb-0">{{ $card['label'] }}</p>
                        <p class="text-secondary-light text-xs mb-0 fst-italic">{{ $card['deltaLabel'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row gy-4 mb-24">
        {{-- T6.3 — Panel Penjadwalan (Rencana vs Aktual) --}}
        <div class="col-xxl-7">
            <div class="card shadow-none border h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Penjadwalan — Rencana vs Aktual</h6>
                    <div class="d-flex gap-8 text-xs">
                        <span><span class="badge bg-success-focus text-success-600 px-8 py-2 radius-4">&nbsp;</span> Sesuai</span>
                        <span><span class="badge bg-warning-focus text-warning-600 px-8 py-2 radius-4">&nbsp;</span> Menggantikan</span>
                        <span><span class="badge bg-danger-focus text-danger-600 px-8 py-2 radius-4">&nbsp;</span> Tidak Hadir</span>
                        <span><span class="badge bg-neutral-200 text-neutral-600 px-8 py-2 radius-4">&nbsp;</span> Tdk Dijadwalkan</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 text-center">
                            <thead>
                                <tr>
                                    <th class="text-start">Personil</th>
                                    @foreach ($mock['schedule']['dates'] as $date)
                                        <th colspan="2" class="text-xs">{{ $date }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    <th></th>
                                    @foreach ($mock['schedule']['dates'] as $date)
                                        <th class="text-xs fw-normal">S1</th>
                                        <th class="text-xs fw-normal">S2</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mock['schedule']['rows'] as $row)
                                    <tr>
                                        <td class="text-start text-xs">{{ $row['name'] }}</td>
                                        @foreach ($mock['schedule']['dates'] as $date)
                                            @foreach (['S1', 'S2'] as $shift)
                                                @php
                                                    $cell = $row['cells']["{$date}|{$shift}"];
                                                    $cellColor = match ($cell['status']) {
                                                        'sesuai' => 'success',
                                                        'menggantikan' => 'warning',
                                                        'tidak_hadir' => 'danger',
                                                        'anomali' => 'info',
                                                        default => 'neutral-200',
                                                    };
                                                @endphp
                                                <td>
                                                    <span
                                                        class="d-inline-block rounded-circle bg-{{ $cellColor }}{{ $cellColor !== 'neutral-200' ? '-focus' : '' }}"
                                                        style="width:12px;height:12px;{{ $cell['planned'] ? 'border:2px solid #444;' : '' }}"
                                                        title="{{ $cell['status'] }}"
                                                    ></span>
                                                </td>
                                            @endforeach
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- T6.5 — Coverage Score & Ranking --}}
        <div class="col-xxl-5">
            <div class="card shadow-none border h-100">
                <div class="card-header"><h6 class="mb-0">Coverage Score &amp; Ranking</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th class="text-center">Non-kritis (×1)</th>
                                    <th class="text-center">Kritis (×2)</th>
                                    <th class="text-center">Skor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mock['coverageRanking'] as $row)
                                    <tr>
                                        <td>{{ $row['rank'] }}</td>
                                        <td class="text-sm">{{ $row['name'] }}</td>
                                        <td class="text-center">{{ $row['non_critical'] }}</td>
                                        <td class="text-center">{{ $row['critical'] }}</td>
                                        <td class="text-center fw-semibold">{{ $row['score'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- T6.4 — Panel Pencapaian per Personil --}}
    <div class="card shadow-none border mb-24">
        <div class="card-header"><h6 class="mb-0">Pencapaian per Personil</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Nama</th>
                            <th>Kehadiran</th>
                            <th>%SAP</th>
                            <th>%TBC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mock['achievement'] as $row)
                            @php
                                $sapColor = match (true) {
                                    $row['sap'] >= 100 => 'success',
                                    $row['sap'] >= 60 => 'warning',
                                    default => 'danger',
                                };
                            @endphp
                            <tr>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['attendance'] }}</td>
                                <td><span class="badge bg-{{ $sapColor }}-focus text-{{ $sapColor }}-600 px-8 py-2 radius-4">{{ $row['sap'] }}%</span></td>
                                <td>{{ $row['tbc'] !== null ? $row['tbc'].'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- T6.6 — Panel Pareto Distribusi Jam --}}
    <div class="row gy-4 mb-24">
        <div class="col-lg-6">
            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">Pareto Distribusi Jam — Shift 1</h6></div>
                <div class="card-body"><div id="chart-pareto-s1"></div></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">Pareto Distribusi Jam — Shift 2</h6></div>
                <div class="card-body"><div id="chart-pareto-s2"></div></div>
            </div>
        </div>
    </div>

    {{-- T6.7 — Panel Highlight Temuan --}}
    <div class="row gy-4 mb-24">
        <div class="col-lg-6">
            <div class="card shadow-none border h-100">
                <div class="card-header"><h6 class="mb-0">Highlight Golden Rule</h6></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach ($mock['highlight']['goldenRules'] as $gr)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="text-sm">{{ $gr['name'] }}</span>
                                <span class="badge bg-primary-focus text-primary-600 px-8 py-2 radius-4">{{ $gr['count'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card shadow-none border h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <i class="ri-map-2-line text-3xl text-danger-600 mb-8"></i>
                    <h6 class="mb-4">{{ $mock['highlight']['blindspotCount'] }} / {{ $mock['highlight']['blindspotTotal'] }}</h6>
                    <p class="text-secondary-light text-sm mb-0">Lokasi Blindspot (belum tersentuh SAP)</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card shadow-none border h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <i class="ri-shield-check-line text-3xl text-warning-600 mb-8"></i>
                    <h6 class="mb-4">{{ $mock['highlight']['tbcPercentage'] }}%</h6>
                    <p class="text-secondary-light text-sm mb-0">Ratio TBC (To Be Concerned)</p>
                </div>
            </div>
        </div>
    </div>

    {{-- T6.8 — Panel Kualitas --}}
    <div class="row gy-4">
        <div class="col-lg-7">
            <div class="card shadow-none border h-100">
                <div class="card-header"><h6 class="mb-0">Kualitas Temuan per Personil</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Kategori Unik</th>
                                    <th class="text-center">Variasi</th>
                                    <th class="text-center">TBC</th>
                                    <th class="text-center">GR</th>
                                    <th class="text-center">Blindspot</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mock['quality'] as $row)
                                    <tr>
                                        <td class="text-sm">{{ $row['name'] }}</td>
                                        <td class="text-center">{{ $row['total_findings'] }}</td>
                                        <td class="text-center">{{ $row['distinct_categories'] }}</td>
                                        <td class="text-center">{{ $row['variety_score'] }}</td>
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
            <div class="card shadow-none border h-100">
                <div class="card-header"><h6 class="mb-0">Volume vs Variasi</h6></div>
                <div class="card-body"><div id="chart-quality-scatter"></div></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var pareto = @json($mock['pareto']);
            var quality = @json($mock['quality']);

            function renderPareto(elementId, series) {
                var hours = series.map(function (row) { return row.hour + ':00'; });
                var counts = series.map(function (row) { return row.count; });
                var cumulative = series.map(function (row) { return row.cumulative; });

                new ApexCharts(document.getElementById(elementId), {
                    chart: { type: 'line', height: 280, toolbar: { show: false } },
                    stroke: { width: [0, 3], curve: 'straight' },
                    series: [
                        { name: 'Jumlah Laporan', type: 'column', data: counts },
                        { name: 'Kumulatif %', type: 'line', data: cumulative },
                    ],
                    xaxis: { categories: hours, title: { text: 'Jam' } },
                    yaxis: [
                        { title: { text: 'Jumlah Laporan' } },
                        { opposite: true, min: 0, max: 100, title: { text: 'Kumulatif %' } },
                    ],
                    colors: ['#0d6efd', '#fd7e14'],
                    annotations: {
                        yaxis: [{ y: 80, yAxisIndex: 1, borderColor: '#adb5bd', label: { text: '80%', style: { fontSize: '10px' } } }],
                    },
                }).render();
            }

            renderPareto('chart-pareto-s1', pareto.s1);
            renderPareto('chart-pareto-s2', pareto.s2);

            new ApexCharts(document.getElementById('chart-quality-scatter'), {
                chart: { type: 'scatter', height: 280, toolbar: { show: false } },
                series: [{
                    name: 'Personil',
                    data: quality.map(function (row) { return [row.total_findings, row.variety_score]; }),
                }],
                xaxis: { title: { text: 'Volume Temuan' } },
                yaxis: { title: { text: 'Variasi Score' }, min: 0, max: 1 },
                colors: ['#0d6efd'],
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
