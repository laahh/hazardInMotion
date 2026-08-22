<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>Absensi Event</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('ohs-dashboard-assets/css/checkin.css') }}?v=20260822f">
</head>
<body>
    @yield('content')
    <script>window.OHS_API_BASE = '/ohs-dashboard/api';</script>
    <script src="{{ asset('ohs-dashboard-assets/js/checkin.js') }}?v=20260822f"></script>
</body>
</html>
