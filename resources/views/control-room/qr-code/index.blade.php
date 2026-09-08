@extends('control-room.layouts.app')

@section('page-title', 'QR Code Absensi')

@push('styles')
    <style>
        .ocr-qr-toolbar { gap: 8px; }
        .ocr-qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }
        .ocr-qr-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 20px 16px 16px;
            text-align: center;
            background: #fff;
            break-inside: avoid;
        }
        .ocr-qr-card h6 { margin: 0 0 4px; font-size: 1.05rem; }
        .ocr-qr-card p { margin: 0; color: #64748b; font-size: 12px; word-break: break-all; }
        .ocr-qr-svg {
            margin: 14px auto 12px;
            width: 180px;
            height: 180px;
        }
        .ocr-qr-svg svg { width: 100%; height: 100%; display: block; }
        .ocr-qr-code {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            background: #eef2ff;
            color: #1e3a8a;
            border-radius: 999px;
            padding: 4px 10px;
            margin-bottom: 8px;
        }
        @media print {
            .sidebar, .navbar-header, .d-footer, .ocr-qr-toolbar, .alert { display: none !important; }
            .dashboard-main { margin: 0 !important; padding: 0 !important; }
            .ocr-qr-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .ocr-qr-card { box-shadow: none; }
        }
    </style>
@endpush

@section('content')
    <div class="card shadow-none border mb-24">
        <div class="card-body d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <h6 class="mb-8">QR Code Absensi per Site</h6>
                <p class="text-secondary-light text-sm mb-0">
                    Setiap QR membuka form absensi site itu saja — hanya personil yang dijadwalkan di site tersebut yang tampil.
                    Tempel QR di Control Room masing-masing site.
                </p>
            </div>
            <div class="ocr-qr-toolbar d-flex">
                <button type="button" class="btn btn-primary-600 btn-sm" onclick="window.print()">Cetak</button>
            </div>
        </div>
    </div>

    <div class="ocr-qr-grid">
        @foreach ($cards as $card)
            <article class="ocr-qr-card">
                <span class="ocr-qr-code">{{ $card['site']->value }}</span>
                <h6>{{ $card['site']->label() }}</h6>
                <div class="ocr-qr-svg">{!! $card['svg'] !!}</div>
                <p>{{ $card['url'] }}</p>
            </article>
        @endforeach
    </div>
@endsection
