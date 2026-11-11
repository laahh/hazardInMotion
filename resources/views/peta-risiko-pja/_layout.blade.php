<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>@yield('title') | BeSentry</title>
  <link rel="icon" type="image/png" href="{{ asset('wowdash-admin/assets/images/favicon.png') }}" sizes="16x16">
  <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/remixicon.css') }}">
  <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/lib/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/style.css') }}">
  @include('peta-risiko-pja._style')
  @yield('css')
</head>
<body>
  <main class="dashboard-main-body pr-body">
    @yield('content')
  </main>
  <script src="{{ asset('wowdash-admin/assets/js/lib/bootstrap.bundle.min.js') }}"></script>
  @yield('scripts')
</body>
</html>
