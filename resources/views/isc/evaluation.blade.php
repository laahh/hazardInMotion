<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Sebaran Aktivitas Kritis · BERAU COAL</title>
  <link rel="icon" href="{{ URL::asset('build/images/logo-removebg.png') }}" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('isc-assets/isc-evaluation.css') }}?v={{ filemtime(public_path('isc-assets/isc-evaluation.css')) }}">
</head>
<body class="isc-ev-body">
  <div class="isc-ev">
    <header class="isc-ev-nav">
      <a class="isc-ev-logo" href="{{ $homeUrl }}">
        <img src="{{ URL::asset('build/images/logo-removebg.png') }}" alt="">
        beraucoal
      </a>
      <nav aria-label="Navigasi ISC">
        <a href="{{ $overviewUrl }}">Overview</a>
        <a href="{{ url()->current() }}" aria-current="page">Evaluation</a>
        <a href="#heatmap">Critical activities</a>
        <a href="#hazards">Hazard highlights</a>
      </nav>
      <a class="isc-ev-plan" href="{{ $interventionsUrl }}">Action plan</a>
    </header>

    <section class="isc-ev-hero">
      <div>
        <p class="isc-ev-kicker">Out of cabin review</p>
        <h1>Sebaran aktivitas kritis</h1>
        <p class="isc-ev-lead">Lokasi, grouping, dan exposure pathway dengan konsentrasi paparan tertinggi — untuk menajamkan intervensi.</p>
      </div>
      <dl class="isc-ev-kpis">
        <div>
          <dt>Total record</dt>
          <dd>{{ number_format($totals['records'], 0, ',', '.') }}</dd>
          <small>aktivitas–risiko</small>
        </div>
        <div>
          <dt>Grouping kritis</dt>
          <dd>{{ $totals['groupings'] }}</dd>
          <small>aktivitas</small>
        </div>
        <div>
          <dt>Rata-rata 4 minggu</dt>
          <dd>{{ $totals['l4w'] }}</dd>
          <small>vs W28–31: {{ $totals['baseline'] }}</small>
        </div>
      </dl>
    </section>

    <section class="isc-ev-charts" id="trend">
      <article class="isc-ev-panel">
        <h2>Trend mingguan</h2>
        <svg viewBox="0 0 {{ $trend['width'] }} {{ $trend['height'] }}" preserveAspectRatio="none" role="img" aria-label="Tren mingguan W28–W35">
          <polyline points="{{ $trend['polyline'] }}" />
          @foreach ($trend['points'] as $point)
            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3" />
            <text x="{{ $point['x'] }}" y="{{ $point['y'] - 8 }}" text-anchor="middle">{{ $point['v'] }}</text>
            <text class="isc-ev-x" x="{{ $point['x'] }}" y="{{ $trend['height'] - 4 }}" text-anchor="middle">{{ $point['label'] }}</text>
          @endforeach
        </svg>
      </article>
      <article class="isc-ev-panel">
        <h2>Porsi site</h2>
        <ul class="isc-ev-bars">
          @foreach ($siteShare as $share)
            <li>
              <span>{{ $share['site'] }}</span>
              <div><i style="width: {{ $share['pct'] }}%"></i></div>
              <b>{{ number_format($share['pct'], 1, ',', '.') }}%</b>
            </li>
          @endforeach
        </ul>
        <p class="isc-ev-note">Tiga site teratas menampung 82% record.</p>
      </article>
      <article class="isc-ev-panel">
        <h2>Sinyal paparan</h2>
        <p class="isc-ev-note is-top">Berdasarkan kata kunci di register.</p>
        <ul class="isc-ev-bars is-signal">
          @foreach ($signals as $signal)
            <li>
              <span>{{ $signal['label'] }}</span>
              <div><i style="width: {{ $signal['pct'] }}%; background: {{ $signal['color'] }}"></i></div>
              <b>{{ rtrim(rtrim(number_format($signal['pct'], 1, ',', '.'), '0'), ',') }}%</b>
            </li>
          @endforeach
        </ul>
      </article>
    </section>

    <section class="isc-ev-bottom">
      <article class="isc-ev-panel isc-ev-heat" id="heatmap">
        <div class="isc-ev-heat-head">
          <h2>10 grouping kritis per site</h2>
          <div class="isc-ev-scale" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
        </div>
        <div class="isc-ev-table-wrap">
          <table>
            <thead>
              <tr>
                <th>Grouping</th>
                <th>Total</th>
                @foreach ($heatmap['sites'] as $site)
                  <th>{{ $site }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @foreach ($heatmap['rows'] as $row)
                <tr>
                  <th>{{ $row['name'] }}</th>
                  <td>{{ $row['total'] }}</td>
                  @foreach ($row['cells'] as $cell)
                    <td style="background: {{ $cell['color'] }}; color: {{ $cell['ink'] }}">{{ $cell['value'] ?: '–' }}</td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </article>

      <article class="isc-ev-panel isc-ev-hazards" id="hazards">
        <h2>Hazard di luar kabin</h2>
        <ul>
          @foreach ($hazards as $hazard)
            <li>
              <span>{{ $hazard['tag'] }}</span>
              <p>{{ $hazard['text'] }}</p>
            </li>
          @endforeach
        </ul>
      </article>
    </section>

    <footer class="isc-ev-foot">
      <p>Fokus intervensi: aktivitas dan area dengan konsentrasi paparan tertinggi.</p>
      <a href="{{ $interventionsUrl }}">Susun intervensi</a>
      <a class="is-ghost" href="{{ $mapsUrl }}">Buka peta</a>
    </footer>
  </div>
</body>
</html>
