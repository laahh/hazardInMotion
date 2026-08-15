<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | BeSentry DMS</title>
    <link rel="icon" type="image/png" href="{{ URL::asset('build/images/logo-removebg.png') }}">

    {{-- Asset stack yang sama persis dengan evaluasi-well.layouts.app (template WowDash) --}}
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/remixicon.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/apexcharts.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/style.css') }}">

    @yield('css')
</head>
<body>

@include('dms.layouts.partials._sidebar')

<main class="dashboard-main">
    @include('dms.layouts.partials._navbar')

    <div class="dashboard-main-body">
        @yield('content')
    </div>

    <footer class="d-footer">
        <div class="row align-items-center justify-content-between">
            <div class="col-auto">
                <p class="mb-0">&copy; {{ date('Y') }} BeSentry &middot; Driver Monitoring System.</p>
            </div>
            <div class="col-auto">
                <p class="mb-0">Berau Coal</p>
            </div>
        </div>
    </footer>
</main>

<script src="{{ asset('evaluasi-well-assets/js/lib/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/apexcharts.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/iconify-icon.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/app.js') }}"></script>

@yield('page-scripts')
@yield('scripts')
</body>
</html>
