<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OHS Roster, Leave & Event Portal</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('ohs-dashboard-assets/css/portal.css') }}?v=20260822g">
</head>
<body>
    <header class="ohs-topbar">
        <div class="ohs-topbar-inner">
            <div class="ohs-brand">
                <span class="ohs-mark">OHS DIV</span>
                <div>
                    <strong>Roster, Leave & Event Portal</strong>
                    <div class="ohs-sub">PT Berau Coal · Occupational Health & Safety</div>
                </div>
            </div>
            @include('OhsDashboard.partials.nav')
        </div>
    </header>
    <main class="ohs-main">
        @yield('content')
    </main>
    <div id="ohs-modal-root"></div>
    <div id="ohs-loading" hidden>
        <div class="ohs-loading-card"><span class="ohs-spinner" aria-hidden="true"></span><p>Memuat data…</p></div>
    </div>
    <div id="ohs-toast-root"></div>
    <script>window.OHS_API_BASE = '/ohs-dashboard/api';</script>
    <script src="{{ asset('ohs-dashboard-assets/js/portal.js') }}?v=20260822g"></script>
    @stack('scripts')
</body>
</html>
