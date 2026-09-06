<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('page-title', 'Absensi') — Control Room</title>
    <link rel="icon" type="image/png" href="{{ asset('wowdash-admin/assets/images/favicon.png') }}" sizes="16x16">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/remixicon.css') }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/lib/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-attendance-form.css') }}">
    @stack('styles')
</head>
<body class="ocr-absensi-page">
    @yield('content')
    @stack('scripts')
</body>
</html>
