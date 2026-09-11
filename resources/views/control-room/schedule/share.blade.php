@extends('control-room.layouts.app')

@section('page-title', 'Bagikan Jadwal')

@php
    $shareQuery = [
        'site' => request('site', $grid['site']),
        'year' => $grid['year'],
        'week' => $grid['week'],
        'scope' => $grid['scope'],
    ];
    $prev = $period->previous();
    $next = $period->next();
@endphp

@push('styles')
    <style>
        .ocr-share-poster {
            --ocr-s1: #dbeafe;
            --ocr-s1-ink: #1e3a8a;
            --ocr-s2: #ffedd5;
            --ocr-s2-ink: #9a3412;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }
        .ocr-share-banner {
            background: linear-gradient(90deg, #1d4ed8, #2563eb);
            color: #fff;
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .ocr-share-banner h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .ocr-share-banner p {
            margin: 4px 0 0;
            opacity: 0.9;
            font-size: 13px;
        }
        .ocr-share-stats {
            display: flex;
            gap: 16px;
            font-size: 12px;
            font-weight: 700;
        }
        .ocr-share-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .ocr-share-table th,
        .ocr-share-table td {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            vertical-align: middle;
        }
        .ocr-share-table thead th {
            background: #fde68a;
            color: #1e3a8a;
            text-align: center;
            font-size: 12px;
            line-height: 1.25;
        }
        .ocr-share-dow { display: block; font-weight: 800; }
        .ocr-share-group {
            background: #fde68a;
            color: #92400e;
            font-weight: 800;
            text-align: center;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            width: 46px;
            letter-spacing: 0.04em;
        }
        .ocr-share-site {
            font-weight: 700;
            color: #111827;
            white-space: nowrap;
        }
        .ocr-share-name { color: #374151; }
        .ocr-share-cell { text-align: center; min-width: 88px; background: #fff; }
        .ocr-share-pill {
            display: inline-block;
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 800;
        }
        .ocr-share-pill.is-s1 { background: var(--ocr-s1); color: var(--ocr-s1-ink); }
        .ocr-share-pill.is-s2 { background: var(--ocr-s2); color: var(--ocr-s2-ink); }
        .ocr-share-pill.is-mix { background: #e0e7ff; color: #3730a3; }
        .ocr-share-empty { color: #d1d5db; }
        .ocr-share-toolbar .btn { min-height: 38px; }
        @media print {
            .sidebar, .navbar-header, .ocr-share-toolbar, .dashboard-main-body > .alert {
                display: none !important;
            }
            .dashboard-main { margin: 0 !important; padding: 0 !important; }
            .ocr-share-poster { box-shadow: none; border: 0; border-radius: 0; }
        }
    </style>
@endpush

@section('content')
    <div class="ocr-share-toolbar d-flex flex-wrap align-items-center justify-content-between gap-2 mb-16">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="{{ route('control-room.schedule.index', ['site' => $shareQuery['site']]) }}" class="btn btn-outline-secondary btn-sm">
                &larr; Kembali ke kalender
            </a>
            <a href="{{ route('control-room.schedule.share', array_merge($shareQuery, ['year' => $prev->year, 'week' => $prev->week])) }}" class="btn btn-outline-secondary btn-sm">Minggu lalu</a>
            <a href="{{ route('control-room.schedule.share', array_merge($shareQuery, ['year' => $next->year, 'week' => $next->week])) }}" class="btn btn-outline-secondary btn-sm">Minggu depan</a>
            <div class="btn-group btn-group-sm" role="group" aria-label="Cakupan site">
                <a href="{{ route('control-room.schedule.share', array_merge($shareQuery, ['scope' => 'site'])) }}" class="btn {{ $grid['scope'] === 'site' ? 'btn-primary-600' : 'btn-outline-primary' }}">Site ini</a>
                <a href="{{ route('control-room.schedule.share', array_merge($shareQuery, ['scope' => 'all'])) }}" class="btn {{ $grid['scope'] === 'all' ? 'btn-primary-600' : 'btn-outline-primary' }}">Semua site</a>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" id="ocr-share-copy">
                <i class="ri-link"></i> Salin tautan
            </button>
            <a href="{{ route('control-room.schedule.share.excel', $shareQuery) }}" class="btn btn-outline-success btn-sm">
                <i class="ri-file-excel-2-line"></i> Unduh Excel
            </a>
            <button type="button" class="btn btn-primary-600 btn-sm" onclick="window.print()">
                <i class="ri-printer-line"></i> Cetak / PDF
            </button>
        </div>
    </div>

    <section class="ocr-share-poster" id="ocr-share-poster">
        <div class="ocr-share-banner">
            <div>
                <h1>Tim Safety</h1>
                <p>{{ $grid['site_label'] }} · {{ $grid['week_label'] }} · Minggu ISO {{ $grid['week'] }}/{{ $grid['year'] }}</p>
            </div>
            <div class="ocr-share-stats">
                <span>{{ $grid['people_count'] }} personil</span>
                <span>{{ $grid['slot_count'] }} slot jaga</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="ocr-share-table">
                <thead>
                    <tr>
                        <th colspan="2">Tim Safety</th>
                        @foreach ($grid['days'] as $day)
                            <th>
                                <span class="ocr-share-dow">{{ $day['label'] }}</span>
                                {{ $day['header'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($grid['groups'] as $group)
                        @foreach ($group['rows'] as $index => $row)
                            <tr>
                                @if ($index === 0)
                                    <th class="ocr-share-group" rowspan="{{ count($group['rows']) }}" scope="rowgroup">
                                        {{ $group['title'] }}
                                        @if ($group['subtitle'] !== '')
                                            <div style="font-weight:600;margin-top:6px;">{{ $group['subtitle'] }}</div>
                                        @endif
                                    </th>
                                @endif
                                <td>
                                    <div class="ocr-share-site">{{ $row['site'] }}</div>
                                    <div class="ocr-share-name">{{ $row['name'] }}</div>
                                </td>
                                @foreach ($row['cells'] as $cell)
                                    <td class="ocr-share-cell">
                                        @if ($cell['text'] !== '')
                                            <span class="ocr-share-pill is-{{ $cell['tone'] }}">{{ $cell['text'] }}</span>
                                        @else
                                            <span class="ocr-share-empty">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="{{ 2 + count($grid['days']) }}" class="text-center text-secondary-light py-32">
                                Belum ada jadwal plan pada minggu ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (function () {
            var btn = document.getElementById('ocr-share-copy');
            if (!btn) {
                return;
            }
            btn.addEventListener('click', function () {
                navigator.clipboard.writeText(window.location.href).then(function () {
                    btn.textContent = 'Tautan disalin';
                    setTimeout(function () {
                        btn.innerHTML = '<i class="ri-link"></i> Salin tautan';
                    }, 1600);
                }).catch(function () {
                    window.prompt('Salin tautan ini:', window.location.href);
                });
            });
        })();
    </script>
@endpush
