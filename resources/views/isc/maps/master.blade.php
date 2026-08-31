<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Peta Boundary') · BERAU COAL OHS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="{{ asset('isc-assets/karhutla-styles.css') }}">
  <link rel="stylesheet" href="{{ asset('isc-assets/karhutla-maps.css') }}">
  @yield('css')
</head>
<body class="page-app">
  @yield('content')
  @yield('scripts')
</body>
</html>
