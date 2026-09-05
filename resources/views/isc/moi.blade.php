@php
$icons = [
    'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22c5-3 8-7 8-12V5l-8-3-8 3v5c0 5 3 9 8 12z"/></svg>',
    'target' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>',
    'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/></svg>',
    'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'people' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.4"/><path d="M3 20c0-3.4 2.7-6 6-6s6 2.6 6 6M14 19c0-2.2 1.6-4 3.5-4S21 16.8 21 19"/></svg>',
    'binoculars' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="7" cy="16" r="4"/><circle cx="17" cy="16" r="4"/><path d="M11 16h2M7 12V6l3-2h4l3 2v6"/></svg>',
    'gear' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/></svg>',
    'team' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="8" r="3"/><path d="M2 20c0-3.3 2.7-6 6-6s6 2.7 6 6M16 11h6M19 8v6"/></svg>',
    'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22c5-3 8-7 8-12V5l-8-3-8 3v5c0 5 3 9 8 12z"/><path d="M8.5 12l2.3 2.3L16 9"/></svg>',
    'bars' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16M7 16V9M12 16V5M17 16v-4"/></svg>',
    'truck' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 16h2l2-6h8l3 3h3v3"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/><path d="M7 10V6h6l3 4"/></svg>',
    'room' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/><path d="M6 9h4M6 12h7"/></svg>',
    'tech' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="6" width="16" height="12" rx="2"/><path d="M18 10l4-2v8l-4-2"/><circle cx="10" cy="12" r="3"/></svg>',
    'map' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 4l-6 2v14l6-2 6 2 6-2V4l-6 2-6-2z"/><path d="M9 4v14M15 6v14"/></svg>',
    'gap' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 7h8v4H8zM8 13h8v4H8z"/><path d="M4 9h4M16 9h4M4 15h4M16 15h4"/></svg>',
    'safety' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3a4 4 0 0 1 4 4v1H8V7a4 4 0 0 1 4-4z"/><path d="M6 8h12v2H6z"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>',
];

$bgMap = [
    'safety' => $safetyImage,
    'highrisk' => $highRiskImage,
    'ric' => $ricImage,
    'tech' => $techImage,
];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Assessment Efektivitas Risk Intervention Center · PT Berau Coal</title>
  <link rel="icon" href="{{ $logoUrl }}" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Unbounded:wght@500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('isc-assets/isc-moi.css') }}?v={{ filemtime(public_path('isc-assets/isc-moi.css')) }}">
</head>
<body class="moi-body{{ request()->boolean('skipintro') ? ' moi-skip-intro' : '' }}">
  <div class="moi-intro" id="moi-intro">
    <div class="moi-intro-mark">
      <img src="{{ $logoUrl }}" alt="">
      <strong>Berau Coal</strong>
      <span>Special Project MOI 2026</span>
      <div class="moi-intro-bar"><i></i></div>
    </div>
  </div>

  <div class="moi-grain" aria-hidden="true"></div>
  <div class="moi-scan" aria-hidden="true"></div>
  <div class="moi-progress" id="moi-progress"></div>

  <header class="moi-nav">
    <a class="moi-brand" href="{{ rtrim(request()->root(), '/') }}/isc/moi/1">
      <img src="{{ $logoUrl }}" alt="">
      <span>
        <strong>beraucoal</strong>
        <small>Be GeMS · MOI 2026</small>
      </span>
    </a>
    <nav class="moi-links" aria-label="Halaman">
      @foreach ($pages as $i => $navPage)
        <button type="button" data-moi-link class="{{ $page === $i + 1 ? 'is-on' : '' }}">{{ $navPage['label'] }}</button>
      @endforeach
    </nav>
    <div class="moi-count" id="moi-count">{{ str_pad((string) $page, 2, '0', STR_PAD_LEFT) }} / 06</div>
  </header>

  <nav class="moi-dots" aria-label="Indikator halaman">
    @foreach ($pages as $i => $navPage)
      <button type="button" data-moi-dot class="{{ $page === $i + 1 ? 'is-on' : '' }}" aria-label="{{ $navPage['label'] }}"></button>
    @endforeach
  </nav>

  <main class="moi-stage" id="moi-stage" data-start-page="{{ $page }}" data-base-url="{{ rtrim(request()->root(), '/') }}/isc/moi">
    {{-- PAGE 1: HERO --}}
    <section class="moi-page moi-page--hero {{ $page === 1 ? 'is-active' : '' }}" data-page="1">
      <div class="moi-bg" style="--moi-hero: url('{{ $controlRoomImage }}')"></div>
      <div class="moi-veil"></div>
      <div class="moi-inner">
        <div class="hero-grid">
          <div>
            <div class="hero-pills rise">
              <span>Thematic Special Project MOI</span>
              <span>Pengawasan Live &amp; Post Event</span>
            </div>
            <p class="moi-kicker rise d2"><b>01</b> Risk Intervention Center</p>
            <h1 class="moi-title rise d3">Assessment Efektivitas <em>RIC</em></h1>
            <p class="moi-lead rise d4">Pengawasan Cerdas, Respons Cepat, Operasi Aman &amp; Produktif. Special Project MOI 2026 untuk penguatan pengawasan langsung berjarak di PT Berau Coal.</p>
            <div class="hero-cta rise d5">
              <button type="button" class="btn-lime" data-moi-goto="2">Masuk materi
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
              </button>
              <button type="button" class="btn-ghost" data-moi-goto="5">Lihat proses live</button>
            </div>
            <div class="motto rise d6">Safety First · Siaga 1 Keselamatan · Prioritas, bukan pilihan</div>
          </div>
          <div class="pillar-list">
            @foreach ($pillars as $i => $pillar)
              <article class="pillar rise d{{ min($i + 2, 6) }}">
                <i>{!! $icons[$pillar['icon']] !!}</i>
                <b>{{ $pillar['title'] }}</b>
              </article>
            @endforeach
          </div>
        </div>
      </div>
      <div class="cap-row">
        @foreach ($capabilities as $cap)
          <div class="cap"><span></span>{{ $cap['title'] }}</div>
        @endforeach
      </div>
    </section>

    {{-- PAGE 2: LATAR --}}
    <section class="moi-page {{ $page === 2 ? 'is-active' : '' }}" data-page="2">
      <div class="moi-bg" style="--moi-hero: url('{{ $heroImage }}')"></div>
      <div class="moi-veil"></div>
      <div class="moi-inner">
        <div class="latar-head">
          <div>
            <p class="moi-kicker rise"><b>02</b> Latar Belakang</p>
            <h2 class="moi-title rise d2">Mengapa pengawasan berjarak harus diperkuat.</h2>
          </div>
          <aside class="siaga-panel rise d3">
            <span class="siaga-badge">Siaga 1 Keselamatan</span>
            <h2>Keselamatan adalah prioritas, bukan pilihan.</h2>
            <p>Pengendalian risiko harus lebih cepat, konsisten, dan terdokumentasi. RIC menjadi hub deteksi dini, verifikasi, intervensi, eskalasi, dan pemantauan tindak lanjut.</p>
          </aside>
        </div>
        <div class="latar-cards">
          @foreach ($backgrounds as $item)
            <article class="latar-card rise">
              <img src="{{ $bgMap[$item['image']] }}" alt="">
              <div>
                <h3>{{ $item['title'] }}</h3>
                <p>{{ $item['body'] }}</p>
              </div>
            </article>
          @endforeach
        </div>
      </div>
    </section>

    {{-- PAGE 3: TUJUAN --}}
    <section class="moi-page {{ $page === 3 ? 'is-active' : '' }}" data-page="3">
      <div class="moi-bg" style="--moi-hero: url('{{ $highRiskImage }}')"></div>
      <div class="moi-veil"></div>
      <div class="moi-inner">
        <div class="tujuan-layout">
          <div>
            <p class="moi-kicker rise"><b>03</b> Tujuan Special Project MOI</p>
            <h2 class="moi-title rise d2">Lima sasaran penguatan RIC.</h2>
          </div>
          <div class="goal-row">
            @foreach ($objectives as $i => $goal)
              <article class="goal rise d{{ min($i + 2, 6) }}">
                <span class="n">{{ $goal['n'] }}</span>
                <i style="width:28px;height:28px;color:var(--moi-lime);display:block">{!! $icons[$goal['icon']] !!}</i>
                <p>{{ $goal['text'] }}</p>
              </article>
            @endforeach
          </div>
          <div class="time-row">
            <div class="out-card rise">
              <small>Hasil yang diharapkan</small>
              <p>Special project ini memperkuat efektivitas pengawasan dan kualitas intervensi keselamatan di lapangan.</p>
            </div>
            @foreach ($timeline as $item)
              <div class="time-card rise">
                <small>{{ $item['date'] }}</small>
                <b>{{ $item['title'] }}</b>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </section>

    {{-- PAGE 4: LINGKUP --}}
    <section class="moi-page moi-page--dark {{ $page === 4 ? 'is-active' : '' }}" data-page="4" id="lingkup-root">
      <div class="moi-bg" style="--moi-hero: url('{{ $ricImage }}')"></div>
      <div class="moi-veil"></div>
      <div class="moi-inner">
        <div class="lingkup-layout">
          <div>
            <p class="moi-kicker rise"><b>04</b> Pengawasan Langsung Berjarak</p>
            <h2 class="moi-title rise d2" style="max-width:22ch">Ruang lingkup, alat, dan definisi.</h2>
          </div>
          <div class="seg rise d3">
            <button type="button" class="is-on" data-tab="alat">Alat &amp; Teknologi</button>
            <button type="button" data-tab="lokasi">Lokasi &amp; Objek</button>
            <button type="button" data-tab="definisi">Definisi</button>
            <button type="button" data-tab="layer">Layer Pengawas</button>
            <button type="button" data-tab="kecuali">Pengecualian</button>
          </div>

          <div class="panel-box is-on" data-panel="alat">
            <div class="tbl-wrap">
              <table class="moi-tbl">
                <thead>
                  <tr><th>No</th><th>Alat bantu &amp; teknologi</th><th>Live</th><th>Post-event</th></tr>
                </thead>
                <tbody>
                  @foreach ($tools as $tool)
                    <tr>
                      <td>{{ $tool['n'] }}</td>
                      <td>{{ $tool['name'] }}</td>
                      <td>@if ($tool['live'])<span class="ok">✓</span>@else<span class="no">–</span>@endif</td>
                      <td>@if ($tool['post'])<span class="ok">✓</span>@else<span class="no">–</span>@endif</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>

          <div class="panel-box" data-panel="lokasi">
            <div class="loc-grid loc-wrap">
              @foreach ($locations as $i => $loc)
                <article class="mini">
                  <div class="n">0{{ $i + 1 }}</div>
                  <h4>{{ $loc['place'] }}</h4>
                  <p>{{ $loc['tools'] }}</p>
                </article>
              @endforeach
              <article class="mini">
                <div class="n">Objek</div>
                <h4>Yang diawasi</h4>
                <p>Seluruh aktivitas operasional yang terpantau jelas. Khusus MEA: manusia dan jarak aman HD–LV.</p>
              </article>
            </div>
          </div>

          <div class="panel-box" data-panel="definisi">
            <div class="def-grid def-wrap">
              @foreach ($definitions as $group => $items)
                @foreach ($items as $def)
                  <article class="mini">
                    <small>{{ $group }}</small>
                    <h4>{{ $def['term'] }}</h4>
                    <p>{{ $def['body'] }}</p>
                  </article>
                @endforeach
              @endforeach
            </div>
          </div>

          <div class="panel-box" data-panel="layer">
            <div class="layer-grid">
              <div class="layer-col">
                <h4>PT Berau Coal</h4>
                <ul>
                  @foreach ($layers['bc'] as $i => $who)
                    <li><span>LAYER {{ $i + 1 }}</span>{{ $who }}</li>
                  @endforeach
                </ul>
              </div>
              <div class="layer-col">
                <h4>Mitra Kerja</h4>
                <ul>
                  @foreach ($layers['mitra'] as $i => $who)
                    <li><span>LAYER {{ $i + 1 }}</span>{{ $who }}</li>
                  @endforeach
                </ul>
              </div>
            </div>
          </div>

          <div class="panel-box" data-panel="kecuali">
            <div class="warn-box">
              <h4>Pengawasan berjarak terbatas jika:</h4>
              <ul>
                @foreach ($exceptions as $ex)
                  <li>{{ $ex }}</li>
                @endforeach
              </ul>
              <p class="warn-note">Jika kondisi ini terjadi, pengawas wajib melakukan pengawasan jarak dekat / ke lapangan berdasarkan assessment risiko.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    {{-- PAGE 5: LIVE --}}
    <section class="moi-page {{ $page === 5 ? 'is-active' : '' }}" data-page="5" id="live-root">
      <div class="moi-bg" style="--moi-hero: url('{{ $controlRoomImage }}')"></div>
      <div class="moi-veil"></div>
      <div class="moi-inner">
        <p class="moi-kicker rise"><b>05</b> Proses Pengawasan Langsung Berjarak (Live)</p>
        <h2 class="moi-title rise d2" style="max-width:20ch;margin-bottom:18px">Alur pengawasan aman, efektif, terdokumentasi.</h2>
        <div class="proc-layout rise d3">
          <div class="proc-rail">
            @foreach ($liveSteps as $i => $step)
              <button type="button" data-step class="{{ $i === 0 ? 'is-on' : '' }}" data-title="{{ $step['title'] }}" data-owner="{{ $step['owner'] }}" data-items="{{ e(json_encode($step['items'])) }}">
                <span class="pn">{{ $step['n'] }}</span>
                <span>{{ $step['title'] }}<small>{{ $step['owner'] }}</small></span>
              </button>
            @endforeach
          </div>
          <article class="proc-detail">
            <div class="proc-owner" data-proc-owner>{{ $liveSteps[0]['owner'] }}</div>
            <h3 data-proc-title>{{ $liveSteps[0]['title'] }}</h3>
            <ul data-proc-list>
              @foreach ($liveSteps[0]['items'] as $item)
                <li>{{ $item }}</li>
              @endforeach
            </ul>
            <div class="chip-row">
              @foreach ($liveTools as $tool)
                <span class="chip">{{ $tool }}</span>
              @endforeach
            </div>
            <p class="close-line">Pengawasan berjarak, keselamatan meningkat, produktivitas berkelanjutan.</p>
          </article>
        </div>
      </div>
    </section>

    {{-- PAGE 6: POST EVENT --}}
    <section class="moi-page {{ $page === 6 ? 'is-active' : '' }}" data-page="6" id="post-root">
      <div class="moi-bg" style="--moi-hero: url('{{ $footerImage }}')"></div>
      <div class="moi-veil"></div>
      <div class="moi-inner">
        <p class="moi-kicker rise"><b>06</b> Proses Pengawasan Post Event</p>
        <h2 class="moi-title rise d2" style="max-width:22ch;margin-bottom:18px">Review akurat, objektif, dan terdokumentasi.</h2>
        <div class="proc-layout rise d3">
          <div class="proc-rail">
            @foreach ($postSteps as $i => $step)
              <button type="button" data-step class="{{ $i === 0 ? 'is-on' : '' }}" data-title="{{ $step['title'] }}" data-owner="{{ $step['owner'] }}" data-items="{{ e(json_encode($step['items'])) }}">
                <span class="pn">{{ $step['n'] }}</span>
                <span>{{ $step['title'] }}<small>{{ $step['owner'] }}</small></span>
              </button>
            @endforeach
          </div>
          <article class="proc-detail">
            <div class="proc-owner" data-proc-owner>{{ $postSteps[0]['owner'] }}</div>
            <h3 data-proc-title>{{ $postSteps[0]['title'] }}</h3>
            <ul data-proc-list>
              @foreach ($postSteps[0]['items'] as $item)
                <li>{{ $item }}</li>
              @endforeach
            </ul>
            <div class="chip-row">
              @foreach ($postTools as $tool)
                <span class="chip">{{ $tool }}</span>
              @endforeach
            </div>
            <p class="close-line">Review post event yang baik memperkuat keselamatan, kepatuhan, dan pembelajaran berkelanjutan.</p>
          </article>
        </div>
      </div>
    </section>
  </main>

  <footer class="moi-foot">
    <div class="moi-hint">Scroll · Panah · Spasi</div>
    <div class="moi-nav-btns">
      <button type="button" data-moi-prev aria-label="Halaman sebelumnya">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
      </button>
      <button type="button" data-moi-next aria-label="Halaman berikutnya">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </button>
    </div>
  </footer>

  <script src="{{ asset('isc-assets/isc-moi.js') }}?v={{ filemtime(public_path('isc-assets/isc-moi.js')) }}"></script>
</body>
</html>
