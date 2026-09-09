@extends('control-room.layouts.app')

@section('page-title', 'Data SAP')

@php
    $weekRangeLabel = $weekStart->locale('id')->translatedFormat('d M').' – '.$weekEnd->locale('id')->translatedFormat('d M Y');
    $filter = ['year' => $year, 'week' => $week];
    if ($site !== null) {
        $filter['site'] = $site->value;
    }
    $rows = $sap['rows'];
    $kpi = $sap['kpi'];
    $formatAt = static function (string $at): string {
        if ($at === '') {
            return '—';
        }
        try {
            return \Carbon\CarbonImmutable::parse($at)->locale('id')->translatedFormat('d M Y H:i');
        } catch (\Throwable) {
            return $at;
        }
    };
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-dashboard.css') }}?v={{ filemtime(public_path('wowdash-admin/assets/css/control-room-dashboard.css')) }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-sap.css') }}?v={{ filemtime(public_path('wowdash-admin/assets/css/control-room-sap.css')) }}">
@endpush

@section('content')
    <div class="ocr-dash ocr-sap-page">
        <form method="GET" class="ocr-card" action="{{ route('control-room.sap.index') }}">
            <div class="ocr-toolbar">
                <div class="ocr-toolbar-left">
                    <div>
                        <label for="ocr-sap-site">Site</label>
                        <select name="site" id="ocr-sap-site" class="form-control" onchange="this.form.submit()">
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
                            <a href="{{ route('control-room.sap.index', array_merge($filter, ['year' => $prevYear, 'week' => $prevWeek])) }}" aria-label="Minggu sebelumnya">
                                <i class="ri-arrow-left-s-line"></i>
                            </a>
                            <div class="ocr-week-label">
                                <strong>{{ $weekRangeLabel }}</strong>
                                <span>Minggu {{ $week }} · {{ $year }}</span>
                            </div>
                            <a href="{{ route('control-room.sap.index', array_merge($filter, ['year' => $nextYear, 'week' => $nextWeek])) }}" aria-label="Minggu berikutnya">
                                <i class="ri-arrow-right-s-line"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="ocr-toolbar-right">
                    <span class="ocr-sync">Laporan SAP personil jaga · jendela H s/d H+1</span>
                </div>
            </div>
        </form>

        @if (! $sap['loaded'])
            <div class="ocr-notice" role="status">
                <i class="ri-error-warning-line"></i>
                <span>Sumber SAP (OBDS) tidak terjangkau. Personil jaga tetap dihitung; tabel laporan dikosongkan.</span>
            </div>
        @endif

        <div class="ocr-kpi-grid ocr-sap-kpi">
            <div class="ocr-card ocr-kpi">
                <p class="ocr-kpi-label">Personil jaga</p>
                <p class="ocr-kpi-value">{{ $kpi['personnel'] }}</p>
                <p class="ocr-kpi-sub">{{ $kpi['reporters'] }} sudah lapor · {{ $kpi['without_sap'] }} belum</p>
            </div>
            <div class="ocr-card ocr-kpi">
                <p class="ocr-kpi-label">Total laporan</p>
                <p class="ocr-kpi-value">{{ $kpi['total'] }}</p>
                <p class="ocr-kpi-sub">Unik Hazard + Inspeksi + Observasi + OAK</p>
            </div>
            <div class="ocr-card ocr-kpi">
                <p class="ocr-kpi-label">Hazard / Inspeksi</p>
                <p class="ocr-kpi-value">{{ $kpi['hazard'] }} / {{ $kpi['inspeksi'] }}</p>
            </div>
            <div class="ocr-card ocr-kpi">
                <p class="ocr-kpi-label">Observasi / OAK</p>
                <p class="ocr-kpi-value">{{ $kpi['observasi'] }} / {{ $kpi['oak'] }}</p>
            </div>
        </div>

        <div class="ocr-card">
            <div class="ocr-card-header">
                <div>
                    <h6 id="ocr-sap-table-title">Laporan SAP minggu ini</h6>
                    <p class="ocr-card-kicker">Hanya SID yang ada di Jadwal Rencana Control Room pada minggu terpilih.</p>
                </div>
            </div>
            <div class="ocr-cov-toolbar">
                <div class="ocr-seg ocr-sap-tabs" role="group" aria-label="Filter jenis SAP">
                    <button type="button" class="is-active" data-sap-tab="all">Semua ({{ $kpi['total'] }})</button>
                    <button type="button" data-sap-tab="hazard">Hazard ({{ $kpi['hazard'] }})</button>
                    <button type="button" data-sap-tab="inspeksi">Inspeksi ({{ $kpi['inspeksi'] }})</button>
                    <button type="button" data-sap-tab="observasi">Observasi ({{ $kpi['observasi'] }})</button>
                    <button type="button" data-sap-tab="oak">OAK ({{ $kpi['oak'] }})</button>
                </div>
                <label class="ocr-cov-search">
                    <i class="ri-search-line" aria-hidden="true"></i>
                    <span class="visually-hidden">Cari laporan</span>
                    <input type="search" id="ocr-sap-q" placeholder="Cari nama, SID, lokasi…" autocomplete="off">
                </label>
            </div>
            <div class="ocr-card-body ocr-card-body--flush">
                <div class="table-responsive">
                    <table class="ocr-heat ocr-sap-table" id="ocr-sap-table">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Personil</th>
                                <th>Site jaga</th>
                                <th>Jenis</th>
                                <th>Kategori / aktivitas</th>
                                <th>Lokasi</th>
                                <th>Detil lokasi</th>
                                <th>ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr
                                    data-component="{{ $row['component'] }}"
                                    data-search="{{ mb_strtolower($row['name'].' '.$row['sid'].' '.$row['sites'].' '.$row['category'].' '.$row['lokasi'].' '.$row['detil_lokasi'].' '.$row['report_id']) }}"
                                >
                                    <td data-order="{{ $row['at'] }}">{{ $formatAt($row['at']) }}</td>
                                    <td>
                                        <strong>{{ $row['name'] }}</strong>
                                        <span class="ocr-dq-sid">{{ $row['sid'] }}</span>
                                    </td>
                                    <td>{{ $row['sites'] !== '' ? $row['sites'] : '—' }}</td>
                                    <td>
                                        <span class="ocr-sap-type is-{{ $row['component'] }}">{{ $row['component_label'] }}</span>
                                    </td>
                                    <td>
                                        {{ $row['category'] !== '' ? $row['category'] : '—' }}
                                        @if ($row['golden_rule'] !== '')
                                            <span class="ocr-dq-sid">{{ $row['golden_rule'] }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $row['lokasi'] !== '' ? $row['lokasi'] : '—' }}</td>
                                    <td>{{ $row['detil_lokasi'] !== '' ? $row['detil_lokasi'] : '—' }}</td>
                                    <td class="ocr-sap-id">{{ $row['report_id'] !== '' ? $row['report_id'] : '—' }}</td>
                                </tr>
                            @endforeach
                            <tr class="ocr-sap-empty" @if ($rows !== []) hidden @endif>
                                <td colspan="8" class="text-center text-secondary-light py-24">
                                    @if ($kpi['personnel'] === 0)
                                        Belum ada personil jaga pada minggu ini.
                                    @else
                                        Belum ada laporan SAP pada jendela jaga minggu ini.
                                    @endif
                                </td>
                            </tr>
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
            var tab = 'all';
            var query = '';
            var buttons = document.querySelectorAll('[data-sap-tab]');
            var input = document.getElementById('ocr-sap-q');
            var rows = document.querySelectorAll('#ocr-sap-table tbody tr[data-component]');
            var emptyRow = document.querySelector('#ocr-sap-table tbody tr.ocr-sap-empty');

            function applyFilter() {
                var visible = 0;
                rows.forEach(function (row) {
                    var component = row.getAttribute('data-component') || '';
                    var haystack = row.getAttribute('data-search') || '';
                    var matchTab = tab === 'all' || component === tab;
                    var matchQuery = query === '' || haystack.indexOf(query) !== -1;
                    var show = matchTab && matchQuery;
                    row.hidden = !show;
                    if (show) {
                        visible++;
                    }
                });
                if (!emptyRow) {
                    return;
                }
                if (rows.length === 0) {
                    emptyRow.hidden = false;
                    return;
                }
                emptyRow.hidden = visible > 0;
                if (visible === 0) {
                    emptyRow.querySelector('td').textContent = 'Tidak ada laporan untuk filter ini.';
                }
            }

            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    tab = btn.getAttribute('data-sap-tab') || 'all';
                    buttons.forEach(function (el) { el.classList.toggle('is-active', el === btn); });
                    applyFilter();
                });
            });

            if (input) {
                input.addEventListener('input', function () {
                    query = (input.value || '').toLowerCase().trim();
                    applyFilter();
                });
            }
        })();
    </script>
@endpush
