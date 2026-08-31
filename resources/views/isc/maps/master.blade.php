<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Peta')</title>
  <link rel="stylesheet" href="{{ asset('isc-assets/isc-command.css') }}">
  @yield('css')
</head>
<body class="page-isc page-isc-full">
  @yield('content')
  @yield('scripts')
</body>
</html>
