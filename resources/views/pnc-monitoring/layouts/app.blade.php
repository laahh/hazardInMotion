<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal') | PNC Monitoring</title>
    <link rel="icon" type="image/png" href="{{ asset('evaluasi-well-assets/images/favicon.png') }}" sizes="16x16">

    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/remixicon.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/apexcharts.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/flatpickr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/style.css') }}">
    <style>
        /* A submenu opened just to preview it (click) should not look the same as the
           group that actually contains the current page (server-rendered `.open`). */
        .sidebar-menu li.dropdown.dropdown-open:not(.open) > a {
            background-color: transparent;
            color: var(--text-secondary-light);
        }
        .sidebar-menu li.dropdown.dropdown-open:not(.open) > a:hover {
            color: var(--brand);
        }
    </style>

    @yield('css')
</head>
<body>

@include('pnc-monitoring.partials._sidebar')

<main class="dashboard-main">
    @include('pnc-monitoring.partials._navbar')

    <div class="dashboard-main-body">
        @if (session('success'))
            <div class="alert alert-success bg-success-100 text-success-600 border-success-100 px-24 py-11 mb-24 radius-8" role="alert">
                {{ session('success') }}
            </div>
        @endif
        @if (session('warnings') && is_array(session('warnings')) && count(session('warnings')) > 0)
            <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-11 mb-24 radius-8" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach (session('warnings') as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-11 mb-24 radius-8" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

    <footer class="d-footer">
        <div class="row align-items-center justify-content-between">
            <div class="col-auto">
                <p class="mb-0">&copy; {{ date('Y') }} PNC Monitoring System. All Rights Reserved.</p>
            </div>
            <div class="col-auto">
                <p class="mb-0">Permit &amp; Compliance</p>
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
