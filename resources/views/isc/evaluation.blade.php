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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('isc-assets/isc-evaluation.css') }}?v={{ filemtime(public_path('isc-assets/isc-evaluation.css')) }}">
</head>
<body class="isc-ev-body">
  <div class="isc-ev" style="--isc-ev-mark: url('{{ $watermark }}')">
    <header class="isc-ev-head">
      <div class="isc-ev-kicker">
        <a class="isc-ev-pill isc-ev-pill--ink" href="{{ $overviewUrl }}">Evaluation Section</a>
        <span class="isc-ev-pill isc-ev-pill--lime">Out of Cabin Review</span>
      </div>
      <a class="isc-ev-brand" href="{{ $homeUrl }}">PT BERAU COAL</a>
    </header>

    <div class="isc-ev-title">
      <h1>Sebaran Aktivitas Kritis</h1>
      <p>Aktivitas luar kabin by register paling banyak terdapat pada aktivitas Maintenance Unit, diikuti Survey dan Peledakan. Konsentrasi tertinggi di BMO 1, dengan sinyal paparan terkuat pada front / loading / dumping.</p>
    </div>

    <div class="isc-ev-grid">
      <div class="isc-ev-main">
        <section class="isc-ev-card isc-ev-trend" aria-label="Trend dan site share">
          <div class="isc-ev-trend-chart">
            <h2>TREND &amp; CONCENTRATION</h2>
            <svg viewBox="0 0 {{ $trend['width'] }} {{ $trend['height'] }}" preserveAspectRatio="none" role="img" aria-label="Tren mingguan">
              <polyline points="{{ $trend['polyline'] }}" />
              @foreach ($trend['points'] as $point)
                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" />
                <text x="{{ $point['x'] }}" y="{{ $point['y'] - 10 }}" text-anchor="middle">{{ $point['v'] }}</text>
                <text class="isc-ev-x" x="{{ $point['x'] }}" y="{{ $trend['height'] - 6 }}" text-anchor="middle">{{ $point['label'] }}</text>
              @endforeach
            </svg>
          </div>
          <div class="isc-ev-share">
            <h2>SITE SHARE</h2>
            <ul>
              @foreach ($siteShare as $share)
                <li>
                  <span>{{ $share['site'] }}</span>
                  <div><i style="width: {{ $share['pct'] }}%"></i></div>
                  <b>{{ number_format($share['pct'], 1, ',', '.') }}%</b>
                </li>
              @endforeach
            </ul>
          </div>
        </section>

        <section class="isc-ev-kpis" aria-label="Ringkasan">
          <article>
            <small>TOTAL RECORD</small>
            <strong>{{ number_format($totals['records'], 0, ',', '.') }}</strong>
            <span>aktivitas–risiko</span>
          </article>
          <article>
            <small>TOTAL GROUPING AKTIVITAS KRITIS</small>
            <strong>{{ $totals['groupings'] }}</strong>
            <span>aktivitas</span>
          </article>
        </section>

        <section class="isc-ev-heat" aria-label="Heatmap aktivitas">
          <div class="isc-ev-heat-head">
            <h2>TOP 10 GROUPING AKTIVITAS KRITIS — REGISTER PER SITE</h2>
            <div class="isc-ev-scale" aria-hidden="true">
              <span>SKALA</span>
              <i></i><i></i><i></i><i></i><i></i>
            </div>
          </div>
          <div class="isc-ev-table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Grouping Aktivitas Kritis</th>
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
        </section>
      </div>

      <aside class="isc-ev-side">
        <section class="isc-ev-card isc-ev-hazards">
          <h2>Highlight Hazard Di Luar Kabin</h2>
          <div class="isc-ev-hazard-grid">
            @foreach ($hazards as $hazard)
              @php
                $src = $hazard['image'] === 'pit' ? $pitImage : ($hazard['image'] === 'room' ? $roomImage : $heroImage);
              @endphp
              <article>
                <img src="{{ $src }}" alt="" class="is-{{ $hazard['crop'] }}">
                <div>
                  <p>{{ $hazard['text'] }}</p>
                  <span>{{ $hazard['tag'] }}</span>
                </div>
              </article>
            @endforeach
          </div>
        </section>

        <section class="isc-ev-card isc-ev-signals">
          <h2>OUT OF CABIN EXPOSURE SIGNALS</h2>
          <p>Keyword-based approach</p>
          <ul>
            @foreach ($signals as $signal)
              <li>
                <span>{{ $signal['label'] }}</span>
                <div><i style="width: {{ $signal['pct'] }}%; background: {{ $signal['color'] }}"></i></div>
                <b>{{ rtrim(rtrim(number_format($signal['pct'], 1, ',', '.'), '0'), ',') }}%</b>
              </li>
            @endforeach
          </ul>
        </section>
      </aside>
    </div>

    <footer class="isc-ev-foot">
      <a href="{{ $mapsUrl }}">
        <img src="{{ URL::asset('build/images/logo-removebg.png') }}" alt="">
        <small>beraucoal</small>
      </a>
    </footer>
  </div>
</body>
</html>
