<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>@yield('title') | BeSentry</title>
  <link rel="icon" href="{{ URL::asset('build/images/favicon.ico') }}">
  <style>
    html, body { margin: 0; padding: 0; }
    body {
      background: #f5f6f8;
      font-family: "Segoe UI", system-ui, -apple-system, Roboto, "Helvetica Neue", Arial, sans-serif;
      -webkit-font-smoothing: antialiased;
    }
    .pr-page { padding: 28px 24px; }
    @media (max-width: 760px) { .pr-page { padding: 18px 16px; } }
  </style>
  @yield('css')
</head>
<body>
  <main class="pr-page">
    @yield('content')
  </main>
  @yield('scripts')
</body>
</html>
