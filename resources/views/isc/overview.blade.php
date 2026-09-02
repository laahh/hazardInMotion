<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Belajar dari Pola. Cegah Perulangan. · BERAU COAL</title>
  <link rel="icon" href="{{ URL::asset('build/images/logo-removebg.png') }}" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('isc-assets/isc-overview.css') }}?v={{ filemtime(public_path('isc-assets/isc-overview.css')) }}">
</head>
<body class="isc-ov-body">
  <div class="isc-ov" id="top" style="--isc-ov-hero: url('{{ $heroImage }}')">
    <header class="isc-ov-nav">
      <a class="isc-ov-logo" href="{{ $homeUrl }}">
        <img src="{{ URL::asset('build/images/logo-removebg.png') }}" alt="">
        <strong>berau coal</strong>
      </a>
      <nav aria-label="Bagian overview">
        <a href="#top" class="is-on">Overview</a>
        <a href="#timeline">Historical Cases</a>
        <a href="#pathways">Exposure Pathways</a>
        <a href="#gaps">Control Gaps</a>
      </nav>
      <a class="isc-ov-plan" href="{{ $interventionsUrl }}">
        Lihat Action Plan
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </header>

    <section class="isc-ov-hero">
      <div class="isc-ov-hero-copy">
        <p class="isc-ov-badges">
          <span>Historical Learning</span>
          <span>Outside Cabin Injury</span>
        </p>
        <h1>Belajar dari Pola.<br>Cegah Perulangan.</h1>
        <p class="isc-ov-lead">Timeline historikal injury aktivitas di luar kabin untuk mengidentifikasi pola paparan dan titik intervensi prioritas.</p>
        <p class="isc-ov-period">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
          2013–2026 YTD
        </p>
        <div class="isc-ov-cta">
          <a class="isc-ov-btn-fill" href="#timeline">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
            Review Historical Cases
          </a>
          <a class="isc-ov-btn-line" href="#gaps">
            Lihat Control Gaps
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
        </div>
      </div>
    </section>

    <section class="isc-ov-cases" id="timeline">
      <h2>Timeline Historikal Injury</h2>
      <div class="isc-ov-rail" aria-hidden="true">
        @foreach ($incidents as $incident)
          <i class="is-{{ $incident['severity'] }}"></i>
        @endforeach
      </div>
      <div class="isc-ov-cards">
        @foreach ($incidents as $incident)
          <article class="isc-ov-card is-{{ $incident['severity'] }}">
            <header>
              <strong>{{ $incident['year'] }} {{ $incident['site'] }}</strong>
              <span>{{ $incident['severityLabel'] }}</span>
            </header>
            <p>{{ $incident['summary'] }}</p>
            <p class="isc-ov-tags">{{ implode(' · ', $incident['tags']) }}</p>
          </article>
        @endforeach
      </div>
    </section>

    <section class="isc-ov-pathways" id="pathways">
      <p>Recurring exposure pathways</p>
      <div>
        <span class="isc-ov-path is-green">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v6c0 4.5 3 7.6 7 9 4-1.4 7-4.5 7-9V6z"/><path d="m9 12 2 2 4-4"/></svg>
          UNSAFE SUPERVISORY / ACTIVITY POSITION
        </span>
        <span class="isc-ov-path is-red">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4 21 19H3L12 4z"/><path d="M12 10v4M12 16.5v.5"/></svg>
          GROUND / UNSAFE WORK ZONE
        </span>
      </div>
    </section>

    <section class="isc-ov-phases" id="gaps">
      @foreach ($phases as $phase)
        <article class="isc-ov-phase is-{{ $phase['tone'] }}">
          <header>
            @if ($phase['tone'] === 'ready')
              <span class="isc-ov-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 5 6v6c0 4.5 3 7.6 7 9 4-1.4 7-4.5 7-9V6z"/><path d="m9 12 2 2 4-4"/></svg></span>
            @elseif ($phase['tone'] === 'expose')
              <span class="isc-ov-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 14a4 4 0 1 1 8 0"/><path d="M6 20v-1a6 6 0 0 1 12 0v1"/><path d="M9 10V8a3 3 0 0 1 6 0v2"/></svg></span>
            @else
              <span class="isc-ov-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="6" y="3" width="12" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
            @endif
            <div>
              <h3><span>{{ $phase['code'] }}</span> {{ $phase['title'] }}</h3>
              <small>{{ $phase['subtitle'] }}</small>
            </div>
          </header>
          <ul>
            @foreach ($phase['points'] as $point)
              <li>{{ $point }}</li>
            @endforeach
          </ul>
        </article>
      @endforeach
    </section>

    <footer class="isc-ov-take">
      <div class="isc-ov-take-label">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 1 4 10c-.8.8-1 1.5-1 3H9c0-1.5-.2-2.2-1-3A6 6 0 0 1 12 3z"/></svg>
        KEY TAKEAWAY
      </div>
      <p>Severe Injury muncul ketika 3 kondisi bertemu: Pekerjaan di Luar Kabin + Area yang Tidak Aman + Deviasi yang Tidak Terintervensi.</p>
      <div class="isc-ov-take-actions">
        <a class="isc-ov-btn-light" href="{{ $interventionsUrl }}">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2"/></svg>
          Susun Intervensi
        </a>
        <a class="isc-ov-link" href="{{ $evaluationUrl }}">
          Buka Action Register
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>
    </footer>
  </div>
</body>
</html>
