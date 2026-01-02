<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!--favicon-->
    <link rel="icon" href="{{ URL::asset('build/images/logo-removebg.png') }}" type="image/png">
    <title>@yield('title') Evaluator HUB </title>

    <script>
      (function loadBotpress() {
        var inject = document.createElement('script');
        inject.src = 'https://cdn.botpress.cloud/webchat/v3.3/inject.js';
        inject.async = true;
        inject.onload = function() {
          var cfg = document.createElement('script');
          cfg.src = 'https://files.bpcontent.cloud/2025/10/23/01/20251023014904-08L72VH4.js';
          cfg.defer = true;
          cfg.onload = function() {
            setTimeout(function () {
              if (window.botpressWebChat && typeof window.botpressWebChat.init === 'function') {
                try {
                  window.botpressWebChat.init({
                    configUrl: 'https://files.bpcontent.cloud/2025/10/23/01/20251023014904-4YEA6SB3.json'
                  });
                } catch (e) {
                  console.error('Botpress manual init failed', e);
                }
              }
            }, 1500);
          };
          cfg.onerror = function() { console.error('Botpress config script failed to load'); };
          document.head.appendChild(cfg);
        };
        inject.onerror = function() { console.error('Botpress inject script failed to load'); };
        document.head.appendChild(inject);
      })();
    </script>

    @yield('css')

    @include('layouts.head-css')

    <style>
        /* Fix header agar full ke kiri - khusus untuk master-home.blade.php */
        .top-header {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            left: 0 !important;
            right: 0 !important;
            position: relative !important;
        }
        
        .top-header .navbar {
            margin: 0 auto !important;
            padding-left: 3rem !important;
            padding-right: 3rem !important;
            width: 100% !important;
            max-width: 100% !important;
            left: 0 !important;
            right: 0 !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
        }
        
        /* Styling untuk Logo - lebih ke kanan dengan padding left lebih besar */
        .top-header .btn-toggle {
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding: 8px 12px 8px 2rem !important;
            display: flex !important;
            align-items: center !important;
            transition: all 0.3s ease !important;
            flex-shrink: 0 !important;
        }
        
        .top-header .btn-toggle a {
            display: flex !important;
            align-items: center !important;
            text-decoration: none !important;
            transition: all 0.3s ease !important;
        }
        
        .top-header .btn-toggle img {
            height: 60px !important;
            width: auto !important;
            transition: transform 0.3s ease !important;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)) !important;
        }
        
        .top-header .btn-toggle:hover img {
            transform: scale(1.05) !important;
        }
        
        /* Styling untuk Menu Navbar - berada di tengah */
        .top-header .navbar-menu {
            padding-left: 0 !important;
            padding-right: 0 !important;
            display: flex !important;
            justify-content: center !important;
            flex-grow: 1 !important;
        }
        
        /* Styling untuk Nav Right Links (Profile, Notifications) - lebih ke kiri dengan padding right lebih besar */
        .top-header .nav-right-links {
            padding-right: 2rem !important;
            gap: 8px !important;
            flex-shrink: 0 !important;
        }
        
        .top-header .navbar-menu .navbar-nav {
            gap: 8px !important;
            justify-content: center !important;
            margin: 0 auto !important;
        }
        
        .top-header .navbar-menu .nav-item .nav-link {
            padding: 10px 16px !important;
            border-radius: 8px !important;
            color: #5f5f5f !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
            position: relative !important;
        }
        
        .top-header .navbar-menu .nav-item .nav-link i {
            font-size: 20px !important;
            transition: transform 0.3s ease !important;
        }
        
        .top-header .navbar-menu .nav-item .nav-link:hover {
            background-color: rgba(0, 140, 255, 0.1) !important;
            color: #008cff !important;
            transform: translateY(-2px) !important;
        }
        
        .top-header .navbar-menu .nav-item .nav-link:hover i {
            transform: scale(1.1) !important;
        }
        
        .top-header .navbar-menu .nav-item.active .nav-link,
        .top-header .navbar-menu .nav-item .nav-link.active {
            background-color: rgba(0, 140, 255, 0.15) !important;
            color: #008cff !important;
        }
        
        /* Dropdown Menu Styling */
        .top-header .navbar-menu .dropdown-menu {
            border: none !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
            border-radius: 10px !important;
            padding: 8px !important;
            margin-top: 8px !important;
        }
        
        .top-header .navbar-menu .dropdown-item {
            padding: 10px 16px !important;
            border-radius: 6px !important;
            transition: all 0.2s ease !important;
        }
        
        .top-header .navbar-menu .dropdown-item:hover {
            background-color: rgba(0, 140, 255, 0.1) !important;
            color: #008cff !important;
            transform: translateX(4px) !important;
        }
        
        /* Pastikan tidak ada container yang membatasi */
        .top-header .container,
        .top-header .container-fluid {
            max-width: 100% !important;
            width: 100% !important;
            margin: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        
        /* Override semua margin dan padding yang mungkin membuat header menggantung */
        .top-header * {
            box-sizing: border-box;
        }
        
        /* Responsive untuk mobile */
        @media (max-width: 991px) {
            .top-header .navbar {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            .top-header .btn-toggle {
                padding-left: 0.5rem !important;
            }
            .top-header .nav-right-links {
                padding-right: 0.5rem !important;
            }
            .top-header .navbar-menu .nav-item .nav-link span {
                display: none !important;
            }
            .top-header .navbar-menu .nav-item .nav-link {
                padding: 10px 12px !important;
            }
        }
        
        /* Responsive untuk desktop besar */
        @media (min-width: 1200px) {
            .top-header .navbar {
                padding-left: 4rem !important;
                padding-right: 4rem !important;
            }
            .top-header .btn-toggle {
                padding-left: 2.5rem !important;
            }
            .top-header .nav-right-links {
                padding-right: 2.5rem !important;
            }
        }
    </style>

</head>

<body>

<header class="top-header mb-5">
    <nav class="navbar navbar-expand align-items-center ">
      <div class="btn-toggle">
        <a href="{{ route('hazard-detection.index') ?? '/' }}" title="Home">
          <img src="{{ URL::asset('build/images/logo-removebg.png') }}" alt="Logo">
        </a>
      </div>
      <div class="navbar-menu flex-grow-1">
        <ul class="navbar-nav d-flex flex-row align-items-center">
          <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2" href="{{ route('hazard-detection.index') ?? '/' }}">
              <i class="material-icons-outlined">home</i>
              <span>Home</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2" href="javascript:;">
              <i class="material-icons-outlined">dashboard</i>
              <span>Dashboard</span>
            </a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="javascript:;" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="material-icons-outlined">apps</i>
              <span>Services</span>
            </a>
            <ul class="dropdown-menu shadow-sm">
              <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('map-wms') ?? 'javascript:;' }}">
                <i class="material-icons-outlined fs-6">map</i>
                <span>Live Monitoring</span>
              </a></li>
              <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('maps.map') ?? 'javascript:;' }}">
                <i class="material-icons-outlined fs-6">warning</i>
                <span>Live Maps</span>
              </a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('realtime-alerts.index') ?? 'javascript:;' }}">
                <i class="material-icons-outlined fs-6">notifications_active</i>
                <span>Real-time Alerts</span>
              </a></li>
              <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('geofencing.index') ?? 'javascript:;' }}">
                <i class="material-icons-outlined fs-6">gps_fixed</i>
                <span>Geofencing</span>
              </a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="javascript:;" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="material-icons-outlined">analytics</i>
              <span>Reports</span>
            </a>
            <ul class="dropdown-menu shadow-sm">
              <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('reporting.dashboard') ?? 'javascript:;' }}">
                <i class="material-icons-outlined fs-6">dashboard</i>
                <span>Dashboard Reports</span>
              </a></li>
              <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('reporting.operational') ?? 'javascript:;' }}">
                <i class="material-icons-outlined fs-6">assessment</i>
                <span>Operational Reports</span>
              </a></li>
              <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('reporting.safety') ?? 'javascript:;' }}">
                <i class="material-icons-outlined fs-6">security</i>
                <span>Safety Reports</span>
              </a></li>
            </ul>
          </li>
          <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2" href="javascript:;">
              <i class="material-icons-outlined">help_outline</i>
              <span>Help</span>
            </a>
          </li>
        </ul>
      </div>
      <ul class="navbar-nav gap-1 nav-right-links align-items-center">
        <li class="nav-item dropdown position-static">
          <a class="nav-link dropdown-toggle dropdown-toggle-nocaret" data-bs-auto-close="outside"
          data-bs-toggle="dropdown" href="javascript:;"><i class="material-icons-outlined">done_all</i></a>
          <div class="dropdown-menu dropdown-menu-end mega-menu shadow-lg p-4 p-lg-5">
            <div class="mega-menu-widgets">
             <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-3 g-4 g-lg-5">
                <div class="col">
                  <div class="card rounded-4 shadow-none border mb-0">
                    <div class="card-body">
                      <div class="d-flex align-items-start gap-3">
                        <!-- <div class="mega-menu-icon flex-shrink-0">
                          <i class="material-icons-outlined">question_answer</i>
                        </div> -->
                        <img src="{{ URL::asset('build/images/megaIcons/06.png') }}" width="40" alt="">
                        <div class="mega-menu-content">
                           <h5>Marketing</h5>
                           <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                             the visual form of a document.</p>
                        </div>
                     </div>
                    </div>
                  </div>
                </div>
                <div class="col">
                  <div class="card rounded-4 shadow-none border mb-0">
                    <div class="card-body">
                      <div class="d-flex align-items-start gap-3">
                        <img src="{{ URL::asset('build/images/megaIcons/02.png') }}" width="40" alt="">
                        <div class="mega-menu-content">
                           <h5>Website</h5>
                           <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                             the visual form of a document.</p>
                        </div>
                     </div>
                    </div>
                  </div>
                </div>
                <div class="col">
                  <div class="card rounded-4 shadow-none border mb-0">
                    <div class="card-body">
                      <div class="d-flex align-items-start gap-3">
                        <img src="{{ URL::asset('build/images/megaIcons/03.png') }}" width="40" alt="">
                        <div class="mega-menu-content">
                            <h5>Subscribers</h5>
                           <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                             the visual form of a document.</p>
                        </div>
                     </div>
                    </div>
                  </div>
                </div>
                <div class="col">
                  <div class="card rounded-4 shadow-none border mb-0">
                    <div class="card-body">
                      <div class="d-flex align-items-start gap-3">
                        <img src="{{ URL::asset('build/images/megaIcons/01.png') }}" width="40" alt="">
                        <div class="mega-menu-content">
                           <h5>Hubspot</h5>
                           <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                             the visual form of a document.</p>
                        </div>
                     </div>
                    </div>
                  </div>
                </div>
                <div class="col">
                  <div class="card rounded-4 shadow-none border mb-0">
                    <div class="card-body">
                      <div class="d-flex align-items-start gap-3">
                        <img src="{{ URL::asset('build/images/megaIcons/11.png') }}" width="40" alt="">
                        <div class="mega-menu-content">
                           <h5>Templates</h5>
                           <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                             the visual form of a document.</p>
                        </div>
                     </div>
                    </div>
                  </div>
                </div>
                <div class="col">
                  <div class="card rounded-4 shadow-none border mb-0">
                    <div class="card-body">
                      <div class="d-flex align-items-start gap-3">
                        <img src="{{ URL::asset('build/images/megaIcons/13.png') }}" width="40" alt="">
                        <div class="mega-menu-content">
                           <h5>Ebooks</h5>
                           <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                             the visual form of a document.</p>
                        </div>
                     </div>
                    </div>
                  </div>
                </div>
                <div class="col">
                  <div class="card rounded-4 shadow-none border mb-0">
                    <div class="card-body">
                      <div class="d-flex align-items-start gap-3">
                        <img src="{{ URL::asset('build/images/megaIcons/12.png') }}" width="40" alt="">
                        <div class="mega-menu-content">
                           <h5>Sales</h5>
                           <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                             the visual form of a document.</p>
                        </div>
                     </div>
                    </div>
                  </div>
                </div>
                <div class="col">
                  <div class="card rounded-4 shadow-none border mb-0">
                    <div class="card-body">
                      <div class="d-flex align-items-start gap-3">
                        <img src="{{ URL::asset('build/images/megaIcons/08.png') }}" width="40" alt="">
                        <div class="mega-menu-content">
                           <h5>Tools</h5>
                           <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                             the visual form of a document.</p>
                        </div>
                     </div>
                    </div>
                  </div>
                </div>
                <div class="col">
                  <div class="card rounded-4 shadow-none border mb-0">
                    <div class="card-body">
                      <div class="d-flex align-items-start gap-3">
                        <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
                        <div class="mega-menu-content">
                           <h5>Academy</h5>
                           <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                             the visual form of a document.</p>
                        </div>
                     </div>
                    </div>
                  </div>
                </div>
             </div><!--end row-->
            </div>
          </div>
        </li>
       
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle dropdown-toggle-nocaret position-relative" data-bs-auto-close="outside"
            data-bs-toggle="dropdown" href="javascript:;"><i class="material-icons-outlined">notifications</i>
            <span class="badge-notify">5</span>
          </a>
          <div class="dropdown-menu dropdown-notify dropdown-menu-end shadow">
            <div class="px-3 py-1 d-flex align-items-center justify-content-between border-bottom">
              <h5 class="notiy-title mb-0">Notifications</h5>
              <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle dropdown-toggle-nocaret option" type="button"
                  data-bs-toggle="dropdown" aria-expanded="false">
                  <span class="material-icons-outlined">
                    more_vert
                  </span>
                </button>
                <div class="dropdown-menu dropdown-option dropdown-menu-end shadow">
                  <div><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
                        class="material-icons-outlined fs-6">inventory_2</i>Archive All</a></div>
                  <div><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
                        class="material-icons-outlined fs-6">done_all</i>Mark all as read</a></div>
                  <div><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
                        class="material-icons-outlined fs-6">mic_off</i>Disable Notifications</a></div>
                  <div><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
                        class="material-icons-outlined fs-6">grade</i>What's new ?</a></div>
                  <div>
                    <hr class="dropdown-divider">
                  </div>
                  <div><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
                        class="material-icons-outlined fs-6">leaderboard</i>Reports</a></div>
                </div>
              </div>
            </div>
            <div class="notify-list">
              <div>
                <a class="dropdown-item border-bottom py-2" href="javascript:;">
                  <div class="d-flex align-items-center gap-3">
                    <div class="">
                      <img src="{{ URL::asset('build/images/avatars/01.png') }}" class="rounded-circle" width="45" height="45" alt="">
                    </div>
                    <div class="">
                      <h5 class="notify-title">Congratulations Jhon</h5>
                      <p class="mb-0 notify-desc">Many congtars jhon. You have won the gifts.</p>
                      <p class="mb-0 notify-time">Today</p>
                    </div>
                    <div class="notify-close position-absolute end-0 me-3">
                      <i class="material-icons-outlined fs-6">close</i>
                    </div>
                  </div>
                </a>
              </div>
              <div>
                <a class="dropdown-item border-bottom py-2" href="javascript:;">
                  <div class="d-flex align-items-center gap-3">
                    <div class="user-wrapper bg-primary text-primary bg-opacity-10">
                      <span>RS</span>
                    </div>
                    <div class="">
                      <h5 class="notify-title">New Account Created</h5>
                      <p class="mb-0 notify-desc">From USA an user has registered.</p>
                      <p class="mb-0 notify-time">Yesterday</p>
                    </div>
                    <div class="notify-close position-absolute end-0 me-3">
                      <i class="material-icons-outlined fs-6">close</i>
                    </div>
                  </div>
                </a>
              </div>
              <div>
                <a class="dropdown-item border-bottom py-2" href="javascript:;">
                  <div class="d-flex align-items-center gap-3">
                    <div class="">
                      <img src="{{ URL::asset('build/images/apps/13.png') }}" class="rounded-circle" width="45" height="45" alt="">
                    </div>
                    <div class="">
                      <h5 class="notify-title">Payment Recived</h5>
                      <p class="mb-0 notify-desc">New payment recived successfully</p>
                      <p class="mb-0 notify-time">1d ago</p>
                    </div>
                    <div class="notify-close position-absolute end-0 me-3">
                      <i class="material-icons-outlined fs-6">close</i>
                    </div>
                  </div>
                </a>
              </div>
              <div>
                <a class="dropdown-item border-bottom py-2" href="javascript:;">
                  <div class="d-flex align-items-center gap-3">
                    <div class="">
                      <img src="{{ URL::asset('build/images/apps/14.png') }}" class="rounded-circle" width="45" height="45" alt="">
                    </div>
                    <div class="">
                      <h5 class="notify-title">New Order Recived</h5>
                      <p class="mb-0 notify-desc">Recived new order from michle</p>
                      <p class="mb-0 notify-time">2:15 AM</p>
                    </div>
                    <div class="notify-close position-absolute end-0 me-3">
                      <i class="material-icons-outlined fs-6">close</i>
                    </div>
                  </div>
                </a>
              </div>
              <div>
                <a class="dropdown-item border-bottom py-2" href="javascript:;">
                  <div class="d-flex align-items-center gap-3">
                    <div class="">
                      <img src="{{ URL::asset('build/images/avatars/06.png') }}" class="rounded-circle" width="45" height="45" alt="">
                    </div>
                    <div class="">
                      <h5 class="notify-title">Congratulations Jhon</h5>
                      <p class="mb-0 notify-desc">Many congtars jhon. You have won the gifts.</p>
                      <p class="mb-0 notify-time">Today</p>
                    </div>
                    <div class="notify-close position-absolute end-0 me-3">
                      <i class="material-icons-outlined fs-6">close</i>
                    </div>
                  </div>
                </a>
              </div>
              <div>
                <a class="dropdown-item py-2" href="javascript:;">
                  <div class="d-flex align-items-center gap-3">
                    <div class="user-wrapper bg-danger text-danger bg-opacity-10">
                      <span>PK</span>
                    </div>
                    <div class="">
                      <h5 class="notify-title">New Account Created</h5>
                      <p class="mb-0 notify-desc">From USA an user has registered.</p>
                      <p class="mb-0 notify-time">Yesterday</p>
                    </div>
                    <div class="notify-close position-absolute end-0 me-3">
                      <i class="material-icons-outlined fs-6">close</i>
                    </div>
                  </div>
                </a>
              </div>
            </div>
          </div>
        </li>
     
        <li class="nav-item dropdown">
          <a href="javascrpt:;" class="dropdown-toggle dropdown-toggle-nocaret" data-bs-toggle="dropdown">
             <img src="{{ URL::asset('build/images/avatars/01.png') }}" class="rounded-circle p-1 border" width="45" height="45">
          </a>
          <div class="dropdown-menu dropdown-user dropdown-menu-end shadow">
            <a class="dropdown-item  gap-2 py-2" href="javascript:;">
              <div class="text-center">
                <img src="{{ URL::asset('build/images/avatars/01.png') }}" class="rounded-circle p-1 shadow mb-3" width="90" height="90"
                  alt="">
                <h5 class="user-name mb-0 fw-bold">Hello, {{ Auth::user()->name }} </h5>
              </div>
            </a>
            <hr class="dropdown-divider">
            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
              class="material-icons-outlined">person_outline</i>Profile</a>
            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
              class="material-icons-outlined">local_bar</i>Setting</a>
            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
              class="material-icons-outlined">dashboard</i>Dashboard</a>
            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
              class="material-icons-outlined">account_balance</i>Earning</a>
              <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
                class="material-icons-outlined">cloud_download</i>Downloads</a>
            <hr class="dropdown-divider">
            <form method="POST" action="{{ route('logout') }}" x-data>
                @csrf
                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 py-2">
                    <i data-lucide="log-out" class="material-icons-outlined">power_settings_new</i>
                    {{ __('Sign Out') }}
                </button>
            </form>
          </div>
        </li>
      </ul>
    </nav>
  </header>


<!--start main wrapper-->
<main class="container-fluid mt-5">
    <div class="main-content">

        @yield('content')

    </div>
</main>
<!--end main wrapper-->

<!--start overlay-->
    <div class="overlay btn-toggle"></div>
<!--end overlay-->


  @include('layouts.cart')

  @include('layouts.right-sidebar')

  @include('layouts.vendor-scripts')

  @yield('scripts')

 
  
  <script>
    (function initBp(){
      var btn = document.getElementById('bp-toggle');
      if (!btn) return;

      function enableBtn() {
        if (!btn.disabled) return;
        btn.disabled = false;
        btn.addEventListener('click', function () {
          if (window.botpressWebChat) {
            if (typeof window.botpressWebChat.open === 'function') {
              window.botpressWebChat.open();
            } else if (typeof window.botpressWebChat.toggle === 'function') {
              window.botpressWebChat.toggle();
            } else {
              console.log('Botpress API belum siap');
            }
          } else {
            console.log('window.botpressWebChat belum ada');
          }
        }, { once: false });
      }

      function bindReady() {
        if (window.botpressWebChat && typeof window.botpressWebChat.onEvent === 'function') {
          window.botpressWebChat.onEvent(function () {
            enableBtn();
          }, ['LIFECYCLE.READY']);
          return true;
        }
        return false;
      }

      var tries = 0;
      (function waitApi(){
        tries++;
        if (bindReady() || (window.botpressWebChat && (window.botpressWebChat.open || window.botpressWebChat.toggle))) {
          enableBtn();
          return;
        }
        if (tries < 80) setTimeout(waitApi, 250);
      })();

      setTimeout(enableBtn, 5000);
    })();
  </script>
    

</body>
  
</html>
