<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Personnel Geofencing &amp; Real-Time Alert · BERAU COAL</title>
  <link rel="icon" href="{{ URL::asset('build/images/logo-removebg.png') }}" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('isc-assets/isc-home.css') }}?v={{ filemtime(public_path('isc-assets/isc-home.css')) }}">
</head>
<body class="isc-home-body">
  <div class="isc-home">
    <div class="isc-home-photo" style="--isc-hero: url('{{ $heroImage }}')" aria-hidden="true"></div>
    <header class="isc-home-nav" aria-label="Navigasi ISC">
      <a class="isc-home-brand" href="{{ route('isc.index') }}">
        <span class="isc-home-logo">
          <img src="{{ URL::asset('build/images/logo-removebg.png') }}" alt="">
        </span>
        <span class="isc-home-brand-copy">
          <strong>beraucoal</strong>
          <small>better mining, brighter future.</small>
        </span>
      </a>
      <nav class="isc-home-links" aria-label="Bagian sistem">
        <span class="isc-home-link-pill" aria-hidden="true"></span>
        <a href="{{ $overviewUrl }}" class="is-on">Overview</a>
        <a href="{{ $mapsUrl }}">Area Berisiko</a>
        <a href="{{ $interventionsUrl }}">Alert &amp; Intervention</a>
        <a href="{{ $cctvUrl }}">Control Room</a>
      </nav>
      <a class="isc-home-enter" href="{{ $mapsUrl }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.2"/><path d="M5 19c1.4-3.2 3.8-5 7-5s5.6 1.8 7 5"/></svg>
        Masuk Dashboard
      </a>
    </header>

    <main class="isc-home-hero" id="overview">
      <section class="isc-home-copy">
        <p class="isc-home-badges">
          <span>Personnel Geofencing</span>
          <span>Real-Time Alert</span>
        </p>
        <h1>
        ASSESSMENT EFEKTIVITAS.<br>
          <em>ASSESSMENT EFEKTIVITAS</em>
        </h1>
        <p class="isc-home-lead">
          Geofencing menghubungkan posisi pekerja, batas area berisiko, dan control room
          dalam satu alur deteksi hingga intervensi — sebelum paparan terjadi.
        </p>
        <div class="isc-home-cta">
          <a class="isc-btn-lime" href="{{ $mapsUrl }}">
            Pantau Area Berisiko
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
          <a class="isc-btn-link" href="{{ $interventionsUrl }}">
            Lihat Alur Intervensi
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
        </div>
        <ul class="isc-home-features">
          <li>
            <span class="isc-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/></svg>
            </span>
            Real-Time Detection
          </li>
          <li>
            <span class="isc-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M4 8h16v10H4z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/><path d="M4 13h16"/></svg>
            </span>
            Risk Boundary
          </li>
          <li>
            <span class="isc-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24"><rect x="3" y="4" width="7" height="6" rx="1"/><rect x="14" y="4" width="7" height="6" rx="1"/><rect x="3" y="14" width="7" height="6" rx="1"/><path d="M15 17h6M18 14v6"/></svg>
            </span>
            Intervention Room
          </li>
        </ul>
      </section>

      <aside class="isc-home-stage" aria-hidden="true">
        <div class="isc-home-stage-track">
          <span class="isc-home-ping isc-home-ping--a"></span>
          <span class="isc-home-ping isc-home-ping--b"></span>
          <figure class="isc-home-control">
            <img src="{{ $controlRoomImage }}" alt="">
            <span class="isc-home-scan"></span>
          </figure>
          <svg class="isc-home-paths" viewBox="0 0 640 520" preserveAspectRatio="none" xmlns:xlink="http://www.w3.org/1999/xlink">
            <path id="isc-path-a" d="M90 430 C 180 360, 260 220, 520 78" />
            <path id="isc-path-b" d="M140 410 C 250 330, 340 250, 470 210" />
            <circle class="isc-home-packet" r="4" fill="#c6ef4a">
              <animateMotion dur="4.2s" repeatCount="indefinite" rotate="auto">
                <mpath href="#isc-path-a" xlink:href="#isc-path-a" />
              </animateMotion>
            </circle>
            <circle class="isc-home-packet" r="3" fill="#c6ef4a">
              <animateMotion dur="5.6s" begin="1.1s" repeatCount="indefinite" rotate="auto">
                <mpath href="#isc-path-b" xlink:href="#isc-path-b" />
              </animateMotion>
            </circle>
          </svg>
          <article class="isc-home-alert" aria-label="Peringatan geofence">
            <div class="isc-home-alert-icon">
              <svg viewBox="0 0 24 24"><path d="M12 4 21 19H3L12 4z"/><path d="M12 10v4M12 16.5v.5"/></svg>
            </div>
            <p class="isc-kicker">Person Detected</p>
            <strong>High-Risk Boundary</strong>
            <span>Control Room Alerted</span>
          </article>
        </div>
      </aside>
    </main>

    <a class="isc-home-explore" href="{{ $overviewUrl }}">
      <span class="isc-mouse" aria-hidden="true"></span>
      Explore the system
    </a>
    <div class="isc-home-dust" aria-hidden="true"></div>
  </div>
  <script src="{{ asset('isc-assets/isc-home.js') }}?v={{ filemtime(public_path('isc-assets/isc-home.js')) }}"></script>
</body>
</html>
