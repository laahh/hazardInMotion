<aside class="sidebar-wrapper">
    <style>
      .sidebar-wrapper .sidebar-header .logo-icon { width:100%; display:flex; justify-content:center; align-items:center; }
      .sidebar-wrapper .sidebar-header .logo-img { height:70px; width:auto; }
      .sidebar-wrapper .sidebar-header .logo-img.logo-small { display:none; height:40px; width:auto; }
      @media screen and (min-width:1199px){
        body.toggled:not(.sidebar-hovered) .sidebar-wrapper .sidebar-header .logo-img.logo-large { display:none; }
        body.toggled:not(.sidebar-hovered) .sidebar-wrapper .sidebar-header .logo-img.logo-small { display:block; }
      }
      @media screen and (max-width:1199px){
        .toggled .sidebar-wrapper .sidebar-header .logo-img.logo-large { display:none; }
        .toggled .sidebar-wrapper .sidebar-header .logo-img.logo-small { display:block; }
      }
      
      /* Custom: Item sidebar yang tidak aktif tidak berwarna biru */
      .sidebar-wrapper .sidebar-nav .metismenu a {
        color: #5f5f5f !important;
        background-color: transparent !important;
      }
      
      .sidebar-wrapper .sidebar-nav .metismenu a:hover {
        color: #5f5f5f !important;
        background-color: rgba(0, 0, 0, 0.05) !important;
      }
      
      .sidebar-wrapper .sidebar-nav .metismenu a:focus,
      .sidebar-wrapper .sidebar-nav .metismenu a:active {
        color: #5f5f5f !important;
        background-color: rgba(0, 0, 0, 0.05) !important;
      }
      
      /* Item aktif tetap biru */
      .sidebar-wrapper .sidebar-nav .metismenu .mm-active > a {
        color: #008cff !important;
        background-color: rgba(0, 140, 255, 0.05) !important;
      }
      
      /* Submenu yang tidak aktif */
      .sidebar-wrapper .sidebar-nav .metismenu ul a {
        color: #5f5f5f !important;
        background-color: transparent !important;
      }
      
      .sidebar-wrapper .sidebar-nav .metismenu ul a:hover {
        color: #5f5f5f !important;
        background-color: rgba(0, 0, 0, 0.05) !important;
      }
      
      .sidebar-wrapper .sidebar-nav .metismenu ul .mm-active > a {
        color: #008cff !important;
        background-color: rgba(0, 140, 255, 0.05) !important;
      }

      /* Hazard Report Menu Styling */
      .hazard-report-menu {
        padding: 16px;
        margin: 12px;
        background: #ffffff;
        border-radius: 12px;
        /* box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.1); */
        transition: opacity 0.3s ease, transform 0.3s ease;
      }

      /* Hide hazard menu when sidebar is toggled (closed) */
      @media screen and (min-width: 1199px) {
        body.toggled:not(.sidebar-hovered) .hazard-report-menu {
          display: none !important;
        }
      }

      @media screen and (max-width: 1199px) {
        body.toggled .hazard-report-menu {
          display: none !important;
        }
      }

      .hazard-report-title {
        font-size: 14px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .hazard-report-title::before {
        content: "📋";
        font-size: 18px;
      }

      .hazard-menu-list {
        list-style: none;
        padding: 0;
        margin: 0;
      }

      .hazard-menu-item {
        margin-bottom: 8px;
      }

      .hazard-menu-item:last-child {
        margin-bottom: 0;
      }

      .hazard-menu-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        color: #475569;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      }

      .hazard-menu-link:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #1e293b;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.08);
      }

      .hazard-menu-link.active {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-color: #2563eb;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3), 0 2px 4px rgba(0, 0, 0, 0.1);
      }

      .hazard-menu-link.active:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4), 0 2px 4px rgba(0, 0, 0, 0.1);
      }

      .hazard-menu-icon {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
      }

      .hazard-menu-text {
        flex: 1;
      }

      .hazard-menu-badge {
        background: #f1f5f9;
        color: #64748b;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        min-width: 24px;
        text-align: center;
      }

      .hazard-menu-link.active .hazard-menu-badge {
        background: rgba(255, 255, 255, 0.25);
        color: #ffffff;
      }

      .hazard-menu-divider {
        height: 1px;
        background: #e2e8f0;
        margin: 16px 0;
        border: none;
      }

      .hazard-menu-section-title {
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 16px 0 8px 0;
        padding: 0 4px;
      }

      /* Hazard Report Card Styling */
      .hazard-report-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 12px;
        transition: all 0.3s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      }

      .hazard-report-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.08);
        transform: translateY(-1px);
      }

      .hazard-report-card-title {
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 8px;
        line-height: 1.4;
      }

      .hazard-report-card-subtitle {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 6px;
        line-height: 1.4;
      }

      .hazard-report-card-company {
        font-size: 12px;
        color: #94a3b8;
        margin-bottom: 0;
        line-height: 1.4;
      }

    </style>
    <div class="sidebar-header">
      <div class="logo-icon">
        <img src="{{ URL::asset('build/images/logo-removebg.png') }}" class="logo-img logo-large" alt="">
        <img src="{{ URL::asset('build/images/icon-kecil.png') }}" class="logo-img logo-small" alt="">
      </div>
      <!-- <div class="logo-name flex-grow-1">
        <h5 class="mb-0">Berau Coal</h5>
      </div> -->
      <div class="sidebar-close">
        <span class="material-icons-outlined">close</span>
      </div>
    </div>
    <div class="sidebar-nav" data-simplebar="true">
      
        <!--navigation-->
        <ul class="metismenu" id="sidenav">
          
         </ul>
        <!--end navigation-->

        <!-- Hazard Report Menu -->
        <div class="hazard-report-menu">
          <div class="hazard-report-title">Pelaporan Hazard</div>
          
          <!-- Hazard Report Card 1 -->
          <div class="hazard-report-card">
            <div class="hazard-report-card-title">Perawatan Jalan</div>
            <div class="hazard-report-card-subtitle">(B8) Road Management</div>
            <div class="hazard-report-card-company">PT Pamapersada Nusantara</div>
          </div>

          <!-- Hazard Report Card 2 -->
          <div class="hazard-report-card">
            <div class="hazard-report-card-title">Pemeliharaan Alat Berat</div>
            <div class="hazard-report-card-subtitle">(C5) Equipment Maintenance</div>
            <div class="hazard-report-card-company">PT Berau Coal</div>
          </div>

          <!-- Hazard Report Card 3 -->
          <div class="hazard-report-card">
            <div class="hazard-report-card-title">Keselamatan Area Tambang</div>
            <div class="hazard-report-card-subtitle">(A3) Mining Safety Zone</div>
            <div class="hazard-report-card-company">PT Pamapersada Nusantara</div>
          </div>

          <!-- Hazard Report Card 4 -->
          <div class="hazard-report-card">
            <div class="hazard-report-card-title">Pengawasan Lingkungan</div>
            <div class="hazard-report-card-subtitle">(D2) Environmental Monitoring</div>
            <div class="hazard-report-card-company">PT Berau Coal</div>
          </div>

          <!-- Hazard Report Card 5 -->
          <div class="hazard-report-card">
            <div class="hazard-report-card-title">Kontrol Kualitas Material</div>
            <div class="hazard-report-card-subtitle">(E7) Material Quality Control</div>
            <div class="hazard-report-card-company">PT Pamapersada Nusantara</div>
          </div>

          <ul class="hazard-menu-list">
            <li class="hazard-menu-item">
              <a href="{{ route('hazard-motion.index') }}" class="hazard-menu-link {{ request()->routeIs('hazard-motion.index') ? 'active' : '' }}">
                <span class="hazard-menu-icon">🗺️</span>
                <span class="hazard-menu-text">Dashboard Hazard</span>
              </a>
            </li>
          </ul>
        </div>
    </div>
    <div class="sidebar-bottom gap-4">
        <div class="dark-mode">
          <a href="javascript:;" class="footer-icon dark-mode-icon">
            <i class="material-icons-outlined">dark_mode</i>  
          </a>
        </div>
        <div class="dropdown dropup-center dropup dropdown-laungauge">
          <a class="dropdown-toggle dropdown-toggle-nocaret footer-icon" href="avascript:;" data-bs-toggle="dropdown"><img src="{{ URL::asset('build/images/county/02.png') }}" width="22" alt="">
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item d-flex align-items-center py-2" href="javascript:;"><img src="{{ URL::asset('build/images/county/01.png') }}" width="20" alt=""><span class="ms-2">English</span></a>
            </li>
            <li><a class="dropdown-item d-flex align-items-center py-2" href="javascript:;"><img src="{{ URL::asset('build/images/county/02.png') }}" width="20" alt=""><span class="ms-2">Catalan</span></a>
            </li>
            <li><a class="dropdown-item d-flex align-items-center py-2" href="javascript:;"><img src="{{ URL::asset('build/images/county/03.png') }}" width="20" alt=""><span class="ms-2">French</span></a>
            </li>
            <li><a class="dropdown-item d-flex align-items-center py-2" href="javascript:;"><img src="{{ URL::asset('build/images/county/04.png') }}" width="20" alt=""><span class="ms-2">Belize</span></a>
            </li>
            <li><a class="dropdown-item d-flex align-items-center py-2" href="javascript:;"><img src="{{ URL::asset('build/images/county/05.png') }}" width="20" alt=""><span class="ms-2">Colombia</span></a>
            </li>
            <li><a class="dropdown-item d-flex align-items-center py-2" href="javascript:;"><img src="{{ URL::asset('build/images/county/06.png') }}" width="20" alt=""><span class="ms-2">Spanish</span></a>
            </li>
            <li><a class="dropdown-item d-flex align-items-center py-2" href="javascript:;"><img src="{{ URL::asset('build/images/county/07.png') }}" width="20" alt=""><span class="ms-2">Georgian</span></a>
            </li>
            <li><a class="dropdown-item d-flex align-items-center py-2" href="javascript:;"><img src="{{ URL::asset('build/images/county/08.png') }}" width="20" alt=""><span class="ms-2">Hindi</span></a>
            </li>
          </ul>
        </div>
        <div class="dropdown dropup-center dropup dropdown-help">
          <a class="footer-icon  dropdown-toggle dropdown-toggle-nocaret option" href="javascript:;"
            data-bs-toggle="dropdown" aria-expanded="false">
            <span class="material-icons-outlined">
              info
            </span>
          </a>
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
</aside>