@extends('control-room.layouts.bare')

@section('page-title', 'Jadwal Tim Safety')

@php
    $shareQuery = [
        'site' => request('site', $grid['site']),
        'year' => $grid['year'],
        'week' => $grid['week'],
        'scope' => $grid['scope'],
    ];
    $prev = $period->previous();
    $next = $period->next();
    $today = now()->toDateString();
    $shift1 = \App\Enums\ControlRoomShiftCode::S1;
    $shift2 = \App\Enums\ControlRoomShiftCode::S2;
@endphp

@push('styles')
    <style>
        .ocr-wrap.ocr-wrap-wide { max-width: 1280px; }
        a.ocr-btn { text-decoration: none; }
        a.ocr-btn-primary { color: #fff; }
        a.ocr-btn-secondary { color: #475569; }
        .ocr-btn:focus-visible {
            outline: none;
            box-shadow: 0 0 0 4px rgba(57, 82, 188, .22);
        }
        .ocr-share-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: 1rem;
        }
        .ocr-share-toolbar-cluster {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5rem;
        }
        .ocr-share-toolbar .ocr-btn {
            min-height: 44px;
            padding: .7rem 1rem;
            font-size: .84rem;
        }
        .ocr-share-seg {
            display: inline-flex;
            align-items: stretch;
            background: #f1f5f9;
            border: 1px solid var(--ocr-line);
            border-radius: 12px;
            padding: 3px;
        }
        .ocr-share-seg a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: .45rem .9rem;
            border-radius: 9px;
            font-size: .78rem;
            font-weight: 700;
            color: #475569;
            text-decoration: none;
        }
        .ocr-share-seg a:focus-visible {
            outline: none;
            box-shadow: 0 0 0 4px rgba(57, 82, 188, .22);
        }
        .ocr-share-seg a.is-active {
            background: #fff;
            color: var(--ocr-brand);
            box-shadow: 0 1px 2px rgba(15, 23, 42, .08);
        }
        .ocr-share-hero-pills {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: .85rem;
        }
        .ocr-share-hero .ocr-preview {
            display: block;
            margin-top: 1.15rem;
            border-style: solid;
        }
        .ocr-share-hero .ocr-preview-grid { grid-template-columns: repeat(4, 1fr); }
        .ocr-share-legend {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem 1rem;
            margin: -.25rem 0 1rem;
        }
        .ocr-share-legend-item {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            font-size: .78rem;
            font-weight: 600;
            color: var(--ocr-muted);
        }
        .ocr-share-scroll {
            overflow: auto;
            margin: 0 -1.25rem -1.35rem;
            padding: 0;
            -webkit-overflow-scrolling: touch;
        }
        .ocr-share-table {
            width: 100%;
            min-width: 860px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: .82rem;
        }
        .ocr-share-table th,
        .ocr-share-table td {
            border-bottom: 1px solid var(--ocr-line);
            border-right: 1px solid #f1f5f9;
            padding: .7rem .75rem;
            vertical-align: middle;
        }
        .ocr-share-table thead th {
            position: sticky;
            top: 0;
            z-index: 3;
            background: #f8fafc;
            color: #334155;
            text-align: center;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            line-height: 1.35;
        }
        .ocr-share-table thead th:first-child {
            text-align: left;
            text-transform: none;
            letter-spacing: 0;
            font-size: .82rem;
        }
        .ocr-share-dow {
            display: block;
            font-weight: 800;
            color: var(--ocr-ink);
            letter-spacing: 0;
            text-transform: none;
            font-size: .88rem;
        }
        .ocr-share-date {
            display: block;
            margin-top: .15rem;
            color: var(--ocr-muted);
            font-weight: 600;
            text-transform: none;
            letter-spacing: 0;
            font-size: .72rem;
        }
        .ocr-share-table thead th.is-today {
            background: var(--ocr-brand-soft);
            color: var(--ocr-brand);
            box-shadow: inset 0 3px 0 var(--ocr-brand);
        }
        .ocr-share-table thead th.is-weekend .ocr-share-dow { color: #72479e; }
        .ocr-share-group-row th {
            background: #f8fafc;
            color: var(--ocr-muted);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            text-align: left;
            padding: .85rem .9rem .55rem;
            border-right: 0;
            border-bottom: 1px solid var(--ocr-line);
        }
        .ocr-share-group-row span {
            color: var(--ocr-brand);
            font-weight: 600;
            letter-spacing: 0;
            text-transform: none;
            margin-left: .4rem;
        }
        .ocr-share-person {
            position: sticky;
            left: 0;
            z-index: 1;
            background: #fff;
            min-width: 196px;
            max-width: 240px;
        }
        .ocr-share-name {
            font-weight: 700;
            color: var(--ocr-ink);
            line-height: 1.3;
        }
        .ocr-share-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .35rem;
            margin-top: .28rem;
        }
        .ocr-share-sid {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .04em;
            color: var(--ocr-muted);
        }
        .ocr-share-cell {
            text-align: center;
            min-width: 108px;
            background: #fff;
        }
        .ocr-share-cell.is-today { background: rgba(57, 82, 188, .04); }
        .ocr-share-cell.is-weekend { background: #fbfaff; }
        .ocr-share-pills {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .28rem;
        }
        .ocr-share-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 26px;
            border-radius: 999px;
            padding: .2rem .65rem;
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .02em;
            white-space: nowrap;
        }
        .ocr-share-pill.is-s1 {
            background: var(--ocr-brand-soft);
            color: var(--ocr-brand);
        }
        .ocr-share-pill.is-s2 {
            background: rgba(114, 71, 158, .12);
            color: #5b2d91;
        }
        .ocr-share-empty { color: #cbd5e1; font-weight: 700; }
        .ocr-share-empty-state {
            text-align: center;
            padding: 2rem 1rem 2.25rem;
        }
        .ocr-share-empty-state .material-symbols-outlined {
            font-size: 2rem;
            color: var(--ocr-brand);
        }
        .ocr-share-empty-state p { margin: .5rem 0 0; color: var(--ocr-muted); }
        .ocr-btn-ok {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: .7rem 1rem;
        }
        @media (max-width: 720px) {
            .ocr-share-toolbar { flex-direction: column; align-items: stretch; }
            .ocr-share-toolbar-cluster { width: 100%; }
            .ocr-share-toolbar .ocr-btn { flex: 1; }
            .ocr-share-hero .ocr-preview-grid { grid-template-columns: 1fr 1fr; }
        }
        @media print {
            body.ocr-gf-page { background: #fff !important; }
            .ocr-share-toolbar { display: none !important; }
            .ocr-wrap { max-width: none; padding: 0; }
            .ocr-hero, .ocr-card { box-shadow: none; }
            .ocr-share-scroll { overflow: visible; margin: 0; }
            .ocr-share-table { min-width: 0; }
            .ocr-share-person { position: static; }
            @page { size: A4 landscape; margin: 8mm; }
        }
        @media (prefers-reduced-motion: reduce) {
            .ocr-btn, .ocr-share-seg a { transition: none; }
        }
    </style>
@endpush

@section('content')
    <div class="ocr-wrap ocr-wrap-wide">
        <nav class="ocr-share-toolbar" aria-label="Aksi bagikan jadwal">
            <div class="ocr-share-toolbar-cluster">
                <a href="{{ route('control-room.schedule.index', ['site' => $shareQuery['site']]) }}" class="ocr-btn ocr-btn-secondary">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Kalender
                </a>
                <a href="{{ route('control-room.schedule.share', array_merge($shareQuery, ['year' => $prev->year, 'week' => $prev->week])) }}" class="ocr-btn ocr-btn-secondary">
                    <span class="material-symbols-outlined">chevron_left</span>
                    Minggu lalu
                </a>
                <a href="{{ route('control-room.schedule.share', array_merge($shareQuery, ['year' => $next->year, 'week' => $next->week])) }}" class="ocr-btn ocr-btn-secondary">
                    Minggu depan
                    <span class="material-symbols-outlined">chevron_right</span>
                </a>
                <div class="ocr-share-seg" role="group" aria-label="Cakupan site">
                    <a href="{{ route('control-room.schedule.share', array_merge($shareQuery, ['scope' => 'site'])) }}" class="{{ $grid['scope'] === 'site' ? 'is-active' : '' }}">Site ini</a>
                    <a href="{{ route('control-room.schedule.share', array_merge($shareQuery, ['scope' => 'all'])) }}" class="{{ $grid['scope'] === 'all' ? 'is-active' : '' }}">Semua site</a>
                </div>
            </div>
            <div class="ocr-share-toolbar-cluster">
                <button type="button" class="ocr-btn ocr-btn-secondary" id="ocr-share-copy">
                    <span class="material-symbols-outlined">link</span>
                    Salin tautan
                </button>
                <a href="{{ route('control-room.schedule.share.excel', $shareQuery) }}" class="ocr-btn ocr-btn-ok">
                    <span class="material-symbols-outlined">download</span>
                    Unduh Excel
                </a>
                <button type="button" class="ocr-btn ocr-btn-primary" onclick="window.print()">
                    <span class="material-symbols-outlined">print</span>
                    Cetak / PDF
                </button>
            </div>
        </nav>

        <article id="ocr-share-poster">
            <div class="ocr-hero ocr-share-hero">
                <div class="ocr-hero-top"></div>
                <div class="ocr-hero-body">
                    <span class="ocr-badge">
                        <span class="material-symbols-outlined" style="font-size:15px">health_and_safety</span>
                        Control Room · {{ $grid['site_label'] }}
                    </span>
                    <h1>Tim Safety</h1>
                    <p class="ocr-lead">
                        Jadwal jaga Control Room untuk <strong>{{ $grid['week_label'] }}</strong>.
                        Data mengikuti rencana di kalender, bukan template kosong.
                    </p>
                    <div class="ocr-share-hero-pills">
                        <span class="ocr-pill">
                            <span class="material-symbols-outlined" style="font-size:16px">calendar_today</span>
                            Minggu ISO {{ $grid['week'] }}/{{ $grid['year'] }}
                        </span>
                        <span class="ocr-pill">
                            <span class="material-symbols-outlined" style="font-size:16px">groups</span>
                            {{ $grid['people_count'] }} personil
                        </span>
                        <span class="ocr-pill">
                            <span class="material-symbols-outlined" style="font-size:16px">event_available</span>
                            {{ $grid['slot_count'] }} slot jaga
                        </span>
                    </div>
                    <div class="ocr-preview is-visible" aria-label="Ringkasan minggu">
                        <div class="ocr-preview-grid">
                            <div>
                                <span>Site</span>
                                <strong>{{ $grid['site_label'] }}</strong>
                            </div>
                            <div>
                                <span>Periode</span>
                                <strong>{{ $grid['week_label'] }}</strong>
                            </div>
                            <div>
                                <span>Personil</span>
                                <strong>{{ $grid['people_count'] }} orang</strong>
                            </div>
                            <div>
                                <span>Slot jaga</span>
                                <strong>{{ $grid['slot_count'] }} jadwal</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <section class="ocr-card" aria-labelledby="ocr-share-matrix-title">
                <h2 class="ocr-card-title" id="ocr-share-matrix-title">
                    <span class="material-symbols-outlined" style="color:#3952bc">calendar_view_week</span>
                    Roster minggu ini
                </h2>
                <div class="ocr-share-legend" aria-label="Legenda shift">
                    <span class="ocr-share-legend-item">
                        <span class="ocr-share-pill is-s1">{{ $shift1->label() }}</span>
                        {{ $shift1->start() }}–{{ $shift1->end() }}
                    </span>
                    <span class="ocr-share-legend-item">
                        <span class="ocr-share-pill is-s2">{{ $shift2->label() }}</span>
                        {{ $shift2->start() }}–{{ $shift2->end() }}
                    </span>
                </div>

                @if ($grid['groups'] === [])
                    <div class="ocr-share-empty-state">
                        <span class="material-symbols-outlined">event_busy</span>
                        <p>Belum ada jadwal plan pada minggu ini.</p>
                    </div>
                @else
                    <div class="ocr-share-scroll">
                        <table class="ocr-share-table">
                            <thead>
                                <tr>
                                    <th scope="col">Personil</th>
                                    @foreach ($grid['days'] as $day)
                                        @php
                                            $isToday = $day['date'] === $today;
                                            $isWeekend = in_array($day['label'], ['Min', 'Sab'], true);
                                        @endphp
                                        <th scope="col" class="{{ $isToday ? 'is-today' : '' }} {{ $isWeekend ? 'is-weekend' : '' }}">
                                            <span class="ocr-share-dow">{{ $day['label'] }}</span>
                                            <span class="ocr-share-date">{{ $day['display'] }}</span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grid['groups'] as $group)
                                    <tr class="ocr-share-group-row">
                                        <th colspan="{{ 1 + count($grid['days']) }}" scope="colgroup">
                                            {{ $group['title'] }}
                                            @if ($group['subtitle'] !== '')
                                                <span>{{ $group['subtitle'] }}</span>
                                            @endif
                                        </th>
                                    </tr>
                                    @foreach ($group['rows'] as $row)
                                        <tr>
                                            <th class="ocr-share-person" scope="row">
                                                <div class="ocr-share-name">{{ $row['name'] }}</div>
                                                <div class="ocr-share-meta">
                                                    <span class="ocr-roster-site">{{ $row['site'] }}</span>
                                                    <span class="ocr-share-sid">{{ $row['sid'] }}</span>
                                                </div>
                                            </th>
                                            @foreach ($row['cells'] as $index => $cell)
                                                @php
                                                    $day = $grid['days'][$index];
                                                    $isToday = $day['date'] === $today;
                                                    $isWeekend = in_array($day['label'], ['Min', 'Sab'], true);
                                                @endphp
                                                <td class="ocr-share-cell {{ $isToday ? 'is-today' : '' }} {{ $isWeekend ? 'is-weekend' : '' }}">
                                                    @if ($cell['labels'] === [])
                                                        <span class="ocr-share-empty">—</span>
                                                    @else
                                                        <div class="ocr-share-pills">
                                                            @foreach ($cell['labels'] as $label)
                                                                <span class="ocr-share-pill is-{{ str_contains(mb_strtolower($label), 'shift 2') ? 's2' : 's1' }}">{{ $label }}</span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </article>

        <p class="ocr-footer">
            PT Berau Coal · Control Room (Pengawasan OCR)<br>
            Poster jadwal rencana {{ $grid['site_label'] }} · {{ $grid['week_label'] }}.
        </p>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var btn = document.getElementById('ocr-share-copy');
            if (!btn) {
                return;
            }
            var idleHtml = btn.innerHTML;
            btn.addEventListener('click', function () {
                navigator.clipboard.writeText(window.location.href).then(function () {
                    btn.innerHTML = '<span class="material-symbols-outlined">check</span> Tautan disalin';
                    window.setTimeout(function () {
                        btn.innerHTML = idleHtml;
                    }, 1600);
                }).catch(function () {
                    window.prompt('Salin tautan ini:', window.location.href);
                });
            });
        })();
    </script>
@endpush
