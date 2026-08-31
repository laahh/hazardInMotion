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
  <link rel="stylesheet" href="{{ asset('isc-assets/isc-command.css') }}">
  @yield('css')
</head>
<body class="page-isc">
  <header class="isc-topbar">
    <a class="isc-brand" href="{{ route('isc.maps.index') }}" aria-label="Berau Coal OHS">
      <span>BERAU COAL</span><i>·</i><strong>OHS</strong>
    </a>
    <nav class="isc-nav" aria-label="Navigasi ISC">
      <a class="{{ request()->routeIs('isc.maps.*') ? 'active' : '' }}" href="{{ route('isc.maps.index') }}" @if(request()->routeIs('isc.maps.*')) aria-current="page" @endif>Peta Boundary</a>
    </nav>
  </header>

  <main class="isc-shell">
    @yield('content')
  </main>

  @yield('scripts')
</body>
</html>
