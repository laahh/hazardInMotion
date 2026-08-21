<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OHS Roster, Leave & Event Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('ohs-dashboard-assets/css/portal.css') }}">
</head>
<body>
    <header class="ohs-topbar">
        <div class="ohs-topbar-inner">
            <div class="ohs-brand">
                <span class="ohs-mark">OHS</span>
                <div>
                    <strong>Roster, Leave & Event Portal</strong>
                    <div class="ohs-sub">PT Berau Coal</div>
                </div>
            </div>
            @include('OhsDashboard.partials.nav')
        </div>
    </header>
    <main class="ohs-main">
        @yield('content')
    </main>
    <div id="ohs-modal-root"></div>
    <script>window.OHS_API_BASE = '/ohs-dashboard/api';</script>
    <script src="{{ asset('ohs-dashboard-assets/js/portal.js') }}"></script>
    @stack('scripts')
</body>
</html>
