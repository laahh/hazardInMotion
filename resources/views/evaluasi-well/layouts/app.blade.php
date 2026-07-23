<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | BeWell Health</title>
    <link rel="icon" type="image/png" href="{{ asset('evaluasi-well-assets/images/favicon.png') }}" sizes="16x16">

    {{-- Exact CSS stack from EvaluasiWell/index-2.html --}}
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/remixicon.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/apexcharts.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/editor-katex.min.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/editor.atom-one-dark.min.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/editor.quill.snow.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/flatpickr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/full-calendar.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/jquery-jvectormap-2.0.5.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/magnific-popup.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/slick.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/prism.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/file-upload.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/audioplayer.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/style.css') }}">

    @yield('css')
</head>
<body>

@include('evaluasi-well.partials._sidebar')

<main class="dashboard-main">
    @include('evaluasi-well.partials._navbar')

    <div class="dashboard-main-body">
        @yield('content')
    </div>

    <footer class="d-footer">
        <div class="row align-items-center justify-content-between">
            <div class="col-auto">
                <p class="mb-0">&copy; {{ date('Y') }} BeWell Health. All Rights Reserved.</p>
            </div>
            <div class="col-auto">
                <p class="mb-0">Made by <span class="text-primary-600">BeWell</span></p>
            </div>
        </div>
    </footer>
</main>

{{-- Exact JS stack from EvaluasiWell/index-2.html --}}
<script src="{{ asset('evaluasi-well-assets/js/lib/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/apexcharts.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/dataTables.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/iconify-icon.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/jquery-ui.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/jquery-jvectormap-2.0.5.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/jquery-jvectormap-world-mill-en.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/magnifc-popup.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/slick.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/prism.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/file-upload.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/audioplayer.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/app.js') }}"></script>

@yield('page-scripts')
@yield('scripts')
</body>
</html>
