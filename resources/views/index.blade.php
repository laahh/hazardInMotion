@extends('layouts.master-home')

@section('title', 'Dashboard')

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.css" />
<style>
  body {
    background-color: #ffffff !important;
  }
  .main-content {
    padding-top: 2rem;
    background-color: #ffffff;
    min-height: 100vh;
  }
  .mega-menu-widgets {
    padding-top: 3rem;
  }

  .portal-tabs {
    gap: 4px;
  }
  .portal-tabs .tab-item {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .08em;
    color: #8a938c;
    text-transform: uppercase;
    padding: 6px 4px;
    cursor: pointer;
    user-select: none;
  }
  .portal-tabs .tab-item + .tab-item {
    border-left: 1px solid #d9dfda;
    padding-left: 12px;
    margin-left: 8px;
  }
  .portal-tabs .tab-item.active {
    color: #16330f;
  }

  .divisi-swiper {
    width: 100%;
  }
  .divisi-banner-slide {
    position: relative;
    min-height: 380px;
    border-radius: 1.75rem;
    overflow: hidden;
    background-size: cover;
    background-position: center;
    color: #16330f;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 36px 44px;
  }
  .divisi-banner-slide::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(100deg, rgba(255,255,255,.95) 0%, rgba(255,255,255,.82) 30%, rgba(20,40,20,.25) 58%, rgba(8,18,8,.6) 100%);
  }
  .divisi-banner-slide > * {
    position: relative;
    z-index: 1;
  }
  .divisi-accent-bar {
    width: 60px;
    height: 5px;
    background: linear-gradient(90deg, #2f9e44, #7cc77f);
    border-radius: 4px;
    margin-bottom: 14px;
  }
  .divisi-label {
    color: #2f9e44;
    letter-spacing: .28em;
    font-weight: 700;
    font-size: 13px;
  }
  .divisi-name {
    font-size: 4rem;
    font-weight: 800;
    color: #0f2913;
    line-height: 1;
    margin: 6px 0 12px;
  }
  .divisi-tagline {
    color: #294a2c;
    font-weight: 500;
    font-size: 1.15rem;
  }
  .divisi-underline {
    width: 90px;
    height: 3px;
    background: #16330f;
    margin-bottom: 16px;
    opacity: .6;
  }
  .divisi-strip {
    font-size: 12px;
    letter-spacing: .16em;
    font-weight: 700;
    color: #16330f;
    text-transform: uppercase;
  }
  .divisi-badge-side {
    position: absolute;
    top: 36px;
    right: 44px;
    text-align: right;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .12em;
    color: #f4f8f2;
    text-shadow: 0 1px 4px rgba(0,0,0,.55);
    white-space: pre-line;
    max-width: 200px;
    z-index: 2;
    line-height: 1.6;
  }

  .divisi-pagination {
    position: relative;
    margin-top: 18px;
    text-align: center;
  }
  .divisi-pagination .swiper-pagination-bullet {
    background: #2f9e44;
    opacity: .3;
    width: 8px;
    height: 8px;
  }
  .divisi-pagination .swiper-pagination-bullet-active {
    opacity: 1;
  }

  .dept-card {
    border-radius: 1.25rem;
    border: 1px solid #eef1ee;
    transition: all .25s ease;
    height: 100%;
  }
  .dept-card:hover {
    box-shadow: 0 10px 24px rgba(20, 50, 20, .1);
    transform: translateY(-2px);
    border-color: #d7ebd7;
  }
  .dept-icon-wrap {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: #eaf6ec;
    color: #2f9e44;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .dept-icon-wrap .material-icons-outlined {
    font-size: 26px;
  }
  .dept-arrow-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 1px solid #e2e6e2;
    color: #16330f;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all .2s ease;
  }
  .dept-card:hover .dept-arrow-btn {
    background: #2f9e44;
    border-color: #2f9e44;
    color: #fff;
  }

  .portal-footer-tag {
    font-size: 12px;
    letter-spacing: .12em;
    color: #8a938c;
    font-weight: 600;
  }
  .portal-footer-tag .dash {
    display: inline-block;
    width: 24px;
    height: 2px;
    background: #2f9e44;
    margin-right: 10px;
    vertical-align: middle;
  }

  @media (max-width: 767px) {
    .divisi-name {
      font-size: 2.6rem;
    }
    .divisi-banner-slide {
      padding: 24px;
      min-height: 320px;
    }
    .divisi-badge-side {
      display: none;
    }
  }
</style>
@endsection

@section('content')
<div class="mega-menu-widgets container-fluid bg-white">

  <!-- Search + ESG pillar tabs -->
  <div class="row mb-4 mt-3">
    <div class="col-12">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
          <div class="position-relative flex-grow-1" style="min-width: 240px;">
            <input type="text" id="deptSearch" class="form-control form-control-lg rounded-5 px-5" placeholder="Cari departemen..." autocomplete="off">
            <span class="material-icons-outlined position-absolute ms-3 translate-middle-y start-0 top-50" style="color: #6c757d;">search</span>
          </div>
          <div class="d-flex align-items-center portal-tabs flex-shrink-0">
            <span class="tab-item active">People</span>
            <span class="tab-item">Planet</span>
            <span class="tab-item">Progress</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Divisi banner carousel -->
  <div class="row mb-2">
    <div class="col-12">
      <div class="swiper divisi-swiper">
        <div class="swiper-wrapper">
          @foreach ($divisions as $division)
          <div class="swiper-slide">
            <div class="divisi-banner-slide" style="background-image: url('{{ URL::asset($division['background']) }}');">
              @if (!empty($division['badgeSide']))
                <div class="divisi-badge-side">{{ $division['badgeSide'] }}</div>
              @endif
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
            </div>
          </div>
          @endforeach
        </div>
      </div>
      <div class="divisi-pagination"></div>
    </div>
  </div>

  <!-- Department cards -->
  @foreach ($divisions as $division)
  <div class="row g-3 mt-3 dept-grid">
    @foreach ($division['depts'] as $dept)
    <div class="col-lg-4 col-md-6 dept-card-col" data-dept-name="{{ strtolower($dept['name'].' '.$dept['subtitle']) }}">
      <a href="{{ route('home.dept', $dept['key']) }}" class="text-decoration-none">
        <div class="card dept-card">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="dept-icon-wrap">
              <span class="material-icons-outlined">{{ $dept['icon'] }}</span>
            </div>
            <div class="flex-grow-1">
              <h5 class="mb-1 text-dark">{{ $dept['name'] }}</h5>
              <p class="mb-0 f-14 text-secondary">{{ $dept['subtitle'] }}</p>
            </div>
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

  <div class="d-flex align-items-center mt-5 mb-3 portal-footer-tag">
    <span class="dash"></span> MINING FOR A BRIGHTER TOMORROW
  </div>

</div>
@endsection

@section('scripts')

  <script src="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js"></script>
  <script>
    var divisiSwiper = new Swiper(".divisi-swiper", {
      slidesPerView: 1,
      spaceBetween: 20,
      pagination: {
        el: ".divisi-pagination",
        clickable: true,
      },
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
