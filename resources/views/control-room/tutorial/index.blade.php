@extends('control-room.layouts.app')

@section('page-title', 'Tutorial')

@push('styles')
    <style>
        .ocr-tutorial-wrap {
            margin: -8px -12px 0;
        }
        .ocr-tutorial-frame {
            display: block;
            width: 100%;
            height: calc(100vh - 132px);
            min-height: 640px;
            border: 0;
            background: #f4f6f8;
        }
        @media (max-width: 991px) {
            .ocr-tutorial-frame {
                height: calc(100vh - 96px);
                min-height: 480px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="ocr-tutorial-wrap">
        <iframe
            class="ocr-tutorial-frame"
            src="{{ $embedUrl }}"
            title="Tutorial Control Room — Panduan Bahaya dan Pelaporan HSE"
        ></iframe>
    </div>
@endsection
