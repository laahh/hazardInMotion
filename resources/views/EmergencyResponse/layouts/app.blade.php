<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('page-title', 'Dashboard') - Emergency Response &amp; Safety Management System</title>
    <link rel="icon" type="image/png" href="{{ asset('wowdash-admin/assets/images/favicon.png') }}" sizes="16x16">

    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/remixicon.css') }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/lib/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/lib/apexcharts.css') }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/lib/dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/lib/flatpickr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/lib/full-calendar.css') }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/lib/jquery-jvectormap-2.0.5.css') }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/lib/magnific-popup.css') }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/lib/file-upload.css') }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/style.css') }}">

    @stack('styles')
</head>
<body>

@include('EmergencyResponse.partials.sidebar')

<main class="dashboard-main">
    @include('EmergencyResponse.partials.topbar')

    <div class="dashboard-main-body">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </div>

    <footer class="d-footer">
        <div class="row align-items-center justify-content-between">
            <div class="col-auto">
                <p class="mb-0">&copy; {{ date('Y') }} Emergency Response &amp; Safety Management System.</p>
            </div>
        </div>
    </footer>
</main>

<script src="{{ asset('wowdash-admin/assets/js/lib/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('wowdash-admin/assets/js/lib/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('wowdash-admin/assets/js/lib/apexcharts.min.js') }}"></script>
<script src="{{ asset('wowdash-admin/assets/js/lib/dataTables.min.js') }}"></script>
<script src="{{ asset('wowdash-admin/assets/js/lib/iconify-icon.min.js') }}"></script>
<script src="{{ asset('wowdash-admin/assets/js/lib/jquery-jvectormap-2.0.5.min.js') }}"></script>
<script src="{{ asset('wowdash-admin/assets/js/lib/jquery-jvectormap-world-mill-en.js') }}"></script>
<script src="{{ asset('wowdash-admin/assets/js/lib/magnifc-popup.min.js') }}"></script>
<script src="{{ asset('wowdash-admin/assets/js/lib/file-upload.js') }}"></script>
<script src="{{ asset('wowdash-admin/assets/js/app.js') }}"></script>

@stack('scripts')
</body>
</html>
