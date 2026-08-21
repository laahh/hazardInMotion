<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Check-in Event — OHS Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('ohs-dashboard-assets/css/portal.css') }}">
</head>
<body class="ohs-checkin-body">
    @yield('content')
    <script>window.OHS_API_BASE = '/ohs-dashboard/api';</script>
    <script src="{{ asset('ohs-dashboard-assets/js/checkin.js') }}"></script>
</body>
</html>
