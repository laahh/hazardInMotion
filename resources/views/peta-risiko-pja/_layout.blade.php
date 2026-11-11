<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>@yield('title') | BeSentry</title>
  <link rel="icon" href="{{ asset('build/images/favicon.ico') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
  @include('peta-risiko-pja._style')
</head>
<body class="pr">
  <header class="pr-hero" style="--hero: url('{{ asset('build/images/login-background.png') }}')">
    <div class="pr-hero-in">
      <div class="pr-hero-top">
        <span class="pr-logo"><img src="{{ asset('build/images/logo-removebg.png') }}" alt="Berau Coal"></span>
        @yield('hero-aksi')
      </div>
      @yield('hero')
    </div>
  </header>
  <main class="pr-main">
    @yield('content')
  </main>
  @include('peta-risiko-pja._status')
  @yield('scripts')
</body>
</html>
