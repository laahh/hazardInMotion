<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Integrated Safety Control · BERAU COAL</title>
  <link rel="icon" href="{{ URL::asset('build/images/logo-removebg.png') }}" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('isc-assets/isc-home.css') }}">
</head>
<body class="isc-home-body">
  <div class="isc-home" style="--isc-hero: url('{{ $heroImage }}')">
    <header class="isc-home-nav" aria-label="Navigasi ISC">
      <a class="isc-home-brand" href="{{ route('isc.index') }}">
        <span class="isc-home-logo">
          <img src="{{ URL::asset('build/images/logo-removebg.png') }}" alt="Berau Coal">
        </span>
        <span class="isc-home-brand-copy">
          <strong>beraucoal</strong>
          <small>better mining, brighter future.</small>
        </span>
      </a>
      <nav class="isc-home-links">
        <span class="isc-home-link-pill" aria-hidden="true"></span>
        <a href="#overview" class="is-on">Overview</a>
        <a href="{{ $mapsUrl }}">Skema Improvement</a>
        <a href="{{ $interventionsUrl }}">Prioritas Risiko</a>
        <a href="{{ $postEventUrl }}">Progress</a>
      </nav>
      <a class="isc-home-enter" href="{{ $mapsUrl }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.2"/><path d="M5 19c1.4-3.2 3.8-5 7-5s5.6 1.8 7 5"/></svg>
        Masuk Dashboard
      </a>
    </header>

    <main class="isc-home-hero" id="overview">
      <section class="isc-home-copy">
        <p class="isc-home-badge">
          <span class="isc-home-pulse" aria-hidden="true"></span>
          Aktivitas di luar kabin · YTD 2026
        </p>
        <h1>
          Perkuat <em>Pengendalian</em>.<br>
          Cegah Paparan Sebelum Terjadi.
        </h1>
        <p class="isc-home-lead">
          ISC menghubungkan identifikasi risiko, standar pengendalian, dan tindak lanjut di lapangan —
          Person on Board, zona Safe/Unsafe, serta intervensi PIC dalam satu layar.
        </p>
        <div class="isc-home-cta">
          <a class="isc-btn-lime" href="{{ $mapsUrl }}">
            Jelajahi Skema
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
          <a class="isc-btn-ghost" href="{{ $postEventUrl }}">
            Lihat Progress
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
        </div>
        <ul class="isc-home-features">
          <li>
            <span class="isc-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M12 3 5 6v6c0 4.5 3 7.6 7 9 4-1.4 7-4.5 7-9V6z"/></svg>
            </span>
            Risk-Based
          </li>
          <li>
            <span class="isc-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/></svg>
            </span>
            Critical Control
          </li>
          <li>
            <span class="isc-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M8 4h8v3H8zM6 7h12v13H6z"/><path d="m9 13 2.2 2.2L16 10.5"/></svg>
            </span>
            Action Tracking
          </li>
        </ul>
      </section>

      <aside class="isc-home-alert" aria-label="Geofence alert">
        <div class="isc-home-alert-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M12 4 21 19H3L12 4z"/><path d="M12 10v4M12 16.5v.5"/></svg>
        </div>
        <p class="isc-kicker">Geofence alert</p>
        <strong>Person Detected</strong>
        <p>High-Risk Boundary</p>
        <span>Control Room Notified</span>
      </aside>
    </main>

    <p class="isc-home-scroll">
      <span class="isc-mouse" aria-hidden="true"></span>
      Scroll to explore
    </p>
  </div>
  <script src="{{ asset('isc-assets/isc-home.js') }}"></script>
</body>
</html>
