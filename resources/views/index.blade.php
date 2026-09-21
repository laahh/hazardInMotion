@extends('layouts.master-home')

@section('title', 'Dashboard')

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.css" />
<style>
  html, body {
    height: 100%;
    background-color: #ffffff !important;
  }

  /* Cancel the default header/main spacing so the portal fits one screen */
  .top-header.mb-5 {
    margin-bottom: 0 !important;
  }
  main.container-fluid.mt-5 {
    margin-top: 0 !important;
  }
  .main-content {
    box-sizing: border-box;
    padding-top: 70px;
    background-color: #ffffff;
    height: 100vh;
    min-height: 620px;
    overflow: hidden;
  }

  .portal-shell {
    position: relative;
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 16px 3rem 14px;
    overflow: hidden;
  }
  .portal-shell::before {
    content: '';
    position: absolute;
    top: -30px;
    left: 0;
    width: 320px;
    height: 160px;
    background-image:
      repeating-linear-gradient(35deg, rgba(47,158,68,.16) 0 1.5px, transparent 1.5px 14px),
      radial-gradient(circle at 100% 0%, rgba(47,158,68,.12), transparent 60%);
    background-position: 0 0, 0 0;
    background-repeat: no-repeat, no-repeat;
    background-size: 280px 150px, 420px 260px;
    pointer-events: none;
    z-index: 0;
  }
  .portal-shell::after {
    content: '';
    position: absolute;
    right: 0;
    bottom: 0;
    width: 260px;
    height: 170px;
    background-image:
      radial-gradient(rgba(47,158,68,.4) 1.3px, transparent 1.6px),
      radial-gradient(circle at 100% 100%, rgba(47,158,68,.14), transparent 60%);
    background-position: right 10px bottom 30px, 0 0;
    background-size: 14px 14px, 100% 100%;
    background-repeat: repeat, no-repeat;
    opacity: .6;
    pointer-events: none;
    z-index: 0;
  }
  .portal-shell > * {
    position: relative;
    z-index: 1;
  }
  .portal-card-shadow {
    box-shadow: 0 10px 30px rgba(20, 50, 20, .07);
  }

  /* Search + ESG pillar tabs */
  .portal-search-row {
    flex: 0 0 auto;
  }
  .portal-search-row .card-body {
    padding: 10px 20px;
  }
  .portal-tabs {
    gap: 2px;
  }
  .portal-tabs .tab-item {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .1em;
    color: #8a938c;
    text-transform: uppercase;
    padding: 6px 2px;
    cursor: pointer;
    user-select: none;
  }
  .portal-tabs .tab-sep {
    color: #c7cfc8;
    font-weight: 600;
    padding: 0 8px;
  }
  .portal-tabs .tab-item.active {
    color: #16330f;
  }

  /* Divisi banner */
  .portal-banner-row {
    flex: 1 1 auto;
    min-height: 0;
  }
  .divisi-swiper, .divisi-swiper .swiper-wrapper, .divisi-swiper .swiper-slide {
    height: 100%;
  }
  .divisi-banner-slide {
    position: relative;
    height: 100%;
    min-height: 230px;
    border-radius: 1.5rem;
    overflow: hidden;
    background-size: cover;
    background-position: center;
    color: #16330f;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: clamp(18px, 3vh, 34px) clamp(20px, 3vw, 42px);
  }
  .divisi-banner-slide::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(100deg, rgba(255,255,255,.97) 0%, rgba(255,255,255,.9) 24%, rgba(255,255,255,.5) 40%, rgba(20,40,20,.08) 54%, transparent 66%);
  }
  .divisi-banner-slide > * {
    position: relative;
    z-index: 1;
  }
  .divisi-accent-bar {
    width: 56px;
    height: 5px;
    background: linear-gradient(90deg, #2f9e44, #7cc77f);
    border-radius: 4px;
    margin-bottom: 10px;
  }
  .divisi-label {
    color: #2f9e44;
    letter-spacing: .28em;
    font-weight: 700;
    font-size: 12px;
  }
  .divisi-name {
    font-size: clamp(2.2rem, 5.4vh, 4rem);
    font-weight: 800;
    color: #0f2913;
    line-height: 1;
    margin: 6px 0 8px;
  }
  .divisi-tagline {
    color: #294a2c;
    font-weight: 500;
    font-size: clamp(.95rem, 1.7vh, 1.15rem);
  }
  .divisi-underline {
    width: 80px;
    height: 3px;
    background: #16330f;
    margin-bottom: 12px;
    opacity: .6;
  }
  .divisi-strip {
    font-size: 11px;
    letter-spacing: .16em;
    font-weight: 700;
    color: #16330f;
    text-transform: uppercase;
  }
  .divisi-badge-side {
    position: absolute;
    top: clamp(16px, 3vh, 32px);
    right: clamp(18px, 3vw, 40px);
    text-align: right;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .12em;
    color: #f4f8f2;
    text-shadow: 0 1px 4px rgba(0,0,0,.55);
    white-space: pre-line;
    max-width: 190px;
    z-index: 2;
    line-height: 1.55;
  }
  .divisi-badge-mid {
    position: absolute;
    top: 46%;
    left: 53%;
    transform: translateY(-50%);
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .1em;
    color: #ffffff;
    text-shadow: 0 1px 4px rgba(0,0,0,.5);
    line-height: 1.6;
    z-index: 2;
  }
  .divisi-hud {
    position: absolute;
    top: 14%;
    left: 51%;
    z-index: 2;
    display: flex;
    align-items: flex-end;
    gap: 10px;
    pointer-events: none;
  }
  .divisi-hud svg {
    display: block;
    filter: drop-shadow(0 1px 3px rgba(0,0,0,.35));
  }
  .divisi-hud-bars {
    display: flex;
    align-items: flex-end;
    gap: 3px;
    background: rgba(255,255,255,.16);
    border: 1px solid rgba(255,255,255,.45);
    border-radius: 8px;
    padding: 7px 8px 6px;
    margin-bottom: 8px;
    backdrop-filter: blur(2px);
  }
  .divisi-hud-bars span {
    display: block;
    width: 4px;
    background: #ffffff;
    border-radius: 2px;
    opacity: .9;
  }
  .divisi-hud-bars span:nth-child(1) { height: 7px; }
  .divisi-hud-bars span:nth-child(2) { height: 13px; }
  .divisi-hud-bars span:nth-child(3) { height: 19px; }
  .divisi-badge-corner {
    position: absolute;
    right: clamp(18px, 3vw, 40px);
    bottom: clamp(14px, 2.4vh, 26px);
    text-align: right;
    color: #ffffff;
    text-shadow: 0 1px 4px rgba(0,0,0,.55);
    z-index: 2;
  }
  .divisi-badge-corner strong {
    display: block;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .1em;
  }
  .divisi-badge-corner span {
    display: block;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    opacity: .9;
  }

  /* Department cards */
  .portal-dept-row {
    flex: 0 0 auto;
  }
  .dept-card {
    border-radius: 1.1rem;
    border: 1px solid rgba(47, 158, 68, .18);
    background: linear-gradient(135deg, #e8f6ea 0%, #f4fbf3 42%, #ffffff 100%);
    transition: all .25s ease;
    height: 100%;
    overflow: hidden;
  }
  .dept-card:hover {
    box-shadow: 0 10px 24px rgba(20, 50, 20, .14);
    transform: translateY(-2px);
    border-color: #9fd6a8;
  }
  .dept-card .card-body {
    position: relative;
    padding: 12px 16px;
    min-height: 88px;
    overflow: hidden;
  }
  .dept-icon-wrap {
    position: relative;
    z-index: 2;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: #ffffff;
    color: #2f9e44;
    box-shadow: 0 3px 8px rgba(20, 50, 20, .12);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .dept-icon-wrap .material-icons-outlined {
    font-size: 22px;
  }
  .dept-text {
    position: relative;
    z-index: 2;
    padding-right: 36%;
    min-width: 0;
  }
  .dept-text h6 {
    font-size: .95rem;
  }
  .dept-text p {
    color: #5f7062;
  }
  .dept-photo {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: 52%;
    background-size: cover;
    background-position: center;
    -webkit-mask-image: linear-gradient(115deg, transparent 0%, transparent 10%, rgba(0,0,0,.18) 26%, rgba(0,0,0,.55) 40%, rgba(0,0,0,.85) 52%, #000 64%);
    mask-image: linear-gradient(115deg, transparent 0%, transparent 10%, rgba(0,0,0,.18) 26%, rgba(0,0,0,.55) 40%, rgba(0,0,0,.85) 52%, #000 64%);
  }
  .dept-photo.dept-photo-contain {
    width: 56%;
    background-size: 62% auto;
    background-repeat: no-repeat;
    background-position: center right 8%;
    -webkit-mask-image: linear-gradient(115deg, transparent 0%, transparent 22%, rgba(0,0,0,.5) 42%, #000 58%);
    mask-image: linear-gradient(115deg, transparent 0%, transparent 22%, rgba(0,0,0,.5) 42%, #000 58%);
  }
  .dept-photo::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(120deg, rgba(216, 240, 219, .75) 0%, rgba(216, 240, 219, .15) 45%, transparent 70%);
    mix-blend-mode: soft-light;
  }
  .dept-arrow-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 3;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #ffffff;
    color: #16330f;
    box-shadow: 0 3px 10px rgba(0,0,0,.2);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .2s ease;
  }
  .dept-arrow-btn .material-icons-outlined {
    font-size: 18px;
  }
  .dept-card:hover .dept-arrow-btn {
    background: #2f9e44;
    color: #fff;
  }

  /* Bottom row: tagline + pillar pagination */
  .portal-footer-row {
    flex: 0 0 auto;
  }
  .portal-footer-tag {
    font-size: 11px;
    letter-spacing: .12em;
    color: #8a938c;
    font-weight: 600;
  }
  .portal-footer-tag .dash {
    display: inline-block;
    width: 22px;
    height: 2px;
    background: #2f9e44;
    margin-right: 10px;
    vertical-align: middle;
  }
  .portal-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }
  .portal-pagination .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #d7ded7;
  }
  .portal-pagination .dot.active {
    background: #2f9e44;
  }

  @media (max-width: 767px) {
    .main-content {
      height: auto;
      overflow: visible;
      min-height: 100vh;
    }
    .portal-shell {
      height: auto;
      overflow: visible;
      padding: 16px 1rem 24px;
    }
    .divisi-badge-side, .divisi-badge-mid, .divisi-badge-corner, .divisi-hud {
      display: none;
    }
    .dept-photo {
      display: none;
    }
    .dept-text {
      padding-right: 40px;
    }
    .portal-banner-row {
      flex: 0 0 auto;
    }
    .divisi-swiper, .divisi-swiper .swiper-wrapper, .divisi-swiper .swiper-slide {
      height: auto;
    }
    .divisi-banner-slide {
      justify-content: flex-start;
      gap: 22px;
      height: auto;
      min-height: 0;
      padding: 22px;
    }
    .divisi-tagline {
      display: block;
    }
    .divisi-underline {
      margin-top: 4px;
    }
    .divisi-banner-slide::before {
      background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, rgba(255,255,255,.86) 55%, rgba(255,255,255,.7) 100%);
    }
  }
</style>
@endsection

@section('content')
<div class="portal-shell">

  <!-- Search + ESG pillar tabs -->
  <div class="portal-search-row">
    <div class="card rounded-4 border-0 portal-card-shadow mb-0">
      <div class="card-body d-flex align-items-center gap-3 flex-wrap">
        <div class="position-relative flex-grow-1" style="min-width: 240px;">
          <input type="text" id="deptSearch" class="form-control form-control-lg rounded-5 px-5" placeholder="Cari departemen..." autocomplete="off">
          <span class="material-icons-outlined position-absolute ms-3 translate-middle-y start-0 top-50" style="color: #6c757d;">search</span>
        </div>
        <div class="d-flex align-items-center portal-tabs flex-shrink-0">
          <span class="tab-item active">People</span><span class="tab-sep">|</span><span class="tab-item">Planet</span><span class="tab-sep">|</span><span class="tab-item">Progress</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Divisi banner carousel -->
  <div class="portal-banner-row">
    <div class="swiper divisi-swiper">
      <div class="swiper-wrapper">
        @foreach ($divisions as $division)
        <div class="swiper-slide">
          <div class="divisi-banner-slide" style="background-image: url('{{ URL::asset($division['background']) }}'); background-position: center 25%;">
            @if (!empty($division['badgeSide']))
              <div class="divisi-badge-side">{{ $division['badgeSide'] }}</div>
            @endif
            <div class="divisi-hud">
              <svg width="76" height="76" viewBox="0 0 76 76" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="38" cy="38" r="26" stroke="rgba(255,255,255,.55)" stroke-width="1"/>
                <circle cx="38" cy="38" r="17" stroke="rgba(255,255,255,.4)" stroke-width="1"/>
                <circle cx="38" cy="38" r="3" fill="#ffffff"/>
                <path d="M38 12V3M38 64V73M12 38H3M64 38H73" stroke="rgba(255,255,255,.4)" stroke-width="1"/>
              </svg>
              <div class="divisi-hud-bars"><span></span><span></span><span></span></div>
            </div>
            <div class="divisi-badge-mid">PEOPLE<br>SAFETY<br>SUSTAINABILITY</div>
            <div>
              <div class="divisi-accent-bar"></div>
              <div class="divisi-label">DIVISI</div>
              <div class="divisi-name">{{ $division['name'] }}</div>
              <div class="divisi-tagline">{{ $division['tagline'] }}</div>
            </div>
            <div>
              <div class="divisi-underline"></div>
              <div class="divisi-strip">{{ implode(' | ', $division['strip']) }}</div>
            </div>
            <div class="divisi-badge-corner">
              <strong>BERAUCOAL</strong>
              <span>For a Better Tomorrow</span>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  <!-- Department cards -->
  @foreach ($divisions as $division)
  <div class="portal-dept-row row g-2">
    @foreach ($division['depts'] as $i => $dept)
    <div class="col-lg-4 col-md-6 dept-card-col" data-dept-name="{{ strtolower($dept['name'].' '.$dept['subtitle']) }}">
      <a href="{{ route('home.dept', $dept['key']) }}" class="text-decoration-none">
        <div class="card dept-card">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="dept-icon-wrap">
              <span class="material-icons-outlined">{{ $dept['icon'] }}</span>
            </div>
            <div class="dept-text">
              <h6 class="mb-1 text-dark fw-bold">{{ $dept['name'] }}</h6>
              <p class="mb-0 f-13 text-secondary text-truncate">{{ $dept['subtitle'] }}</p>
            </div>
            <div class="dept-photo {{ ($dept['imageFit'] ?? 'cover') === 'contain' ? 'dept-photo-contain' : '' }}" style="background-image: url('{{ URL::asset($dept['image'] ?? $division['background']) }}');"></div>
            <div class="dept-arrow-btn">
              <span class="material-icons-outlined">chevron_right</span>
            </div>
          </div>
        </div>
      </a>
    </div>
    @endforeach
  </div>
  @endforeach

  <!-- Tagline + pillar pagination -->
  <div class="portal-footer-row d-flex align-items-center justify-content-between">
    <div class="portal-footer-tag">
      <span class="dash"></span> MINING FOR A BRIGHTER TOMORROW
    </div>
    <div class="portal-pagination">
      <span class="dot active"></span>
      <span class="dot"></span>
      <span class="dot"></span>
    </div>
    <div style="width: 140px;"></div>
  </div>

</div>
@endsection

@section('scripts')

  <script src="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js"></script>
  <script>
    var divisiSwiper = new Swiper(".divisi-swiper", {
      slidesPerView: 1,
      spaceBetween: 20,
    });

    $(document).ready(function () {
      $('#deptSearch').on('keyup', function () {
        var term = $(this).val().toLowerCase().trim();
        $('.dept-card-col').each(function () {
          var haystack = String($(this).data('dept-name') || '');
          $(this).toggle(!term || haystack.indexOf(term) !== -1);
        });
      });

      $('.portal-tabs .tab-item').on('click', function () {
        $('.portal-tabs .tab-item').removeClass('active');
        $(this).addClass('active');
      });
    });
  </script>

@endsection
