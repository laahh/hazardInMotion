<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <title>@yield('page-title', 'Absensi') — Control Room</title>
    <link rel="icon" type="image/png" href="{{ asset('wowdash-admin/assets/images/favicon.png') }}" sizes="16x16">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/remixicon.css') }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-attendance-form.css') }}">
    @stack('styles')
</head>
<body class="ocr-gf-page">
    @yield('content')
    @stack('scripts')
</body>
</html>
