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
          <li>
            <a href="{{ route('hazard-detection.index') }}">
              <div class="parent-icon"><i class="material-icons-outlined">dashboard</i>
              </div>
              <div class="menu-title">Dashboard</div>
            </a>
          </li>
          
          <li class="menu-label">Hazard in Motion</li>
          
          <li>
            <a href="{{ route('map-wms') }}">
              <div class="parent-icon"><i class="material-icons-outlined">map</i>
              </div>
              <div class="menu-title">Live Monitoring</div>
            </a>
          </li>
          
          <li>
            <a href="{{ route('maps.map') }}">
              <div class="parent-icon"><i class="material-icons-outlined">warning</i>
              </div>
              <div class="menu-title">Live Maps</div>
            </a>
          </li>
          
          <li>
            <a class="has-arrow" href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">notifications_active</i>
              </div>
              <div class="menu-title">Real-time Alerts</div>
            </a>
            <ul>
              <li><a href="{{ route('realtime-alerts.index') }}"><i class="material-icons-outlined">arrow_right</i>Active Alerts</a></li>
              <li><a href="{{ route('realtime-alerts.history') }}"><i class="material-icons-outlined">arrow_right</i>Alert History</a></li>
              <li><a href="{{ route('realtime-alerts.settings') }}"><i class="material-icons-outlined">arrow_right</i>Notification Settings</a></li>
            </ul>
          </li>
          
          <li>
            <a class="has-arrow" href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">gps_fixed</i>
              </div>
              <div class="menu-title">Spasial </div>
            </a>
            <ul>
              <li><a href="{{ route('geofencing.index') }}"><i class="material-icons-outlined">arrow_right</i>WMS</a></li>
              <li><a href="{{ route('geofencing.rules') }}"><i class="material-icons-outlined">arrow_right</i>Area Kerja + Area CCTV</a></li>
              <li><a href="{{ route('geofencing.monitoring') }}"><i class="material-icons-outlined">arrow_right</i>Boundary Monitoring</a></li>
            </ul>
          </li>
          
          <li>
            <a class="has-arrow" href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">dangerous</i>
              </div>
              <div class="menu-title">Violation Tracking</div>
            </a>
            <ul>
              <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>Violation Detected</a></li>
              <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>Violation Reports</a></li>
              <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>Violation Analytics</a></li>
            </ul>
          </li>
          
          <li>
            <a class="has-arrow" href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">location_on</i>
              </div>
              <div class="menu-title">Spatial Analysis</div>
            </a>
            <ul>
              <li><a href="{{ route('spatial-analysis.heatmap') }}"><i class="material-icons-outlined">arrow_right</i>Heat Map</a></li>
              <li><a href="{{ route('spatial-analysis.zone') }}"><i class="material-icons-outlined">arrow_right</i>Zone Analysis</a></li>
              <li><a href="{{ route('spatial-analysis.movement') }}"><i class="material-icons-outlined">arrow_right</i>Movement Patterns</a></li>
              <li><a href="{{ route('spatial-analysis.risk') }}"><i class="material-icons-outlined">arrow_right</i>Risk Assessment</a></li>
            </ul>
          </li>
          
          <li>
            <a class="has-arrow" href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">supervisor_account</i>
              </div>
              <div class="menu-title">Supervisory Control</div>
            </a>
            <ul>
              <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>Control Panel</a></li>
              <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>System Status</a></li>
              <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>Operational Control</a></li>
            </ul>
          </li>
          
          <li>
            <a href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">join_right</i>
              </div>
              <div class="menu-title">Timeline & Events</div>
            </a>
          </li>
          
          <li>
            <a class="has-arrow" href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">analytics</i>
              </div>
              <div class="menu-title">Reporting & Analytics</div>
            </a>
            <ul>
              <li><a href="{{ route('reporting.dashboard') }}"><i class="material-icons-outlined">arrow_right</i>Dashboard Reports</a></li>
              <li><a href="{{ route('reporting.operational') }}"><i class="material-icons-outlined">arrow_right</i>Operational Reports</a></li>
              <li><a href="{{ route('reporting.safety') }}"><i class="material-icons-outlined">arrow_right</i>Safety Reports</a></li>
              <li><a href="{{ route('reporting.custom') }}"><i class="material-icons-outlined">arrow_right</i>Custom Reports</a></li>
            </ul>
          </li>
          
        
          <li class="menu-label">CCTV & Monitoring</li>
          
          <li>
            <a class="has-arrow" href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">camera</i>
              </div>
              <div class="menu-title">CCTV Management</div>
            </a>
            <ul>
              <li><a href="{{ route('cctv-data.index') }}"><i class="material-icons-outlined">arrow_right</i>CCTV Database</a></li>
              <li><a href="{{ route('cctv-data.pja-cctv-dedicated.index') }}"><i class="material-icons-outlined">arrow_right</i>CCTV PJA DEDICATED</a></li>
              <li><a href="{{ route('cctv-data.import-form') }}"><i class="material-icons-outlined">arrow_right</i>CCTV COVERAGE</a></li>
              <li><a href="{{ route('cctv-data.control-room.index') }}"><i class="material-icons-outlined">arrow_right</i>Pengawas Control Room</a></li>
              {{-- <li><a href="{{ route('cctv-data.import-pja-cctv-form') }}"><i class="material-icons-outlined">arrow_right</i>Import PJA-CCTV Mapping</a></li> --}}
              {{-- <li><a href="{{ route('cctv-management.status') }}"><i class="material-icons-outlined">arrow_right</i>CCTV Status</a></li> --}}
            </ul>
          </li>
          
          <li>
            <a class="has-arrow" href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">videocam</i>
              </div>
              <div class="menu-title">Live Streaming</div>
            </a>
            <ul>
              <li><a href="{{ route('live-streaming.active') }}"><i class="material-icons-outlined">arrow_right</i>Active Streams</a></li>
              <li><a href="{{ route('live-streaming.archive') }}"><i class="material-icons-outlined">arrow_right</i>Stream Archive</a></li>
            </ul>
          </li>
          
          <li>
            <a class="has-arrow" href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">meeting_room</i>
              </div>
              <div class="menu-title">Control Room</div>
            </a>
            <ul>
              @forelse($controlRooms ?? [] as $controlRoom)
                <li>
                  <a class="has-arrow" href="javascript:;">
                    <i class="material-icons-outlined">arrow_right</i>
                    {{ $controlRoom['name'] }} 
                    <span class="badge bg-primary rounded-pill ms-2">{{ $controlRoom['cctv_count'] }}</span>
                  </a>
                  <ul>
                    @foreach($controlRoom['cctv_list'] as $cctv)
                      <li>
                        <a href="javascript:;" 
                           @if($cctv['link_akses']) 
                             onclick="window.open('{{ $cctv['link_akses'] }}', '_blank');" 
                           @endif
                           title="{{ $cctv['lokasi_pemasangan'] ?? '' }}">
                          <i class="material-icons-outlined">camera_alt</i>
                          {{ $cctv['nama_cctv'] ?? $cctv['no_cctv'] ?? 'CCTV #' . $cctv['id'] }}
                          @if($cctv['kondisi'] === 'Baik')
                            <span class="badge bg-success rounded-pill ms-2">Baik</span>
                          @elseif($cctv['kondisi'] === 'Rusak')
                            <span class="badge bg-danger rounded-pill ms-2">Rusak</span>
                          @endif
                        </a>
                      </li>
                    @endforeach
                  </ul>
                </li>
              @empty
                <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>No Control Room Available</a></li>
              @endforelse
            </ul>
          </li>
          
          <li class="menu-label">Integration & Settings</li>
          
          <li>
            <a class="has-arrow" href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">settings</i>
              </div>
              <div class="menu-title">System Integration</div>
            </a>
            <ul>
              <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>API Management</a></li>
              <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>Data Sources</a></li>
              <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>Third-party Integration</a></li>
            </ul>
          </li>
          
          <li>
            <a class="has-arrow" href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">tune</i>
              </div>
              <div class="menu-title">Settings</div>
            </a>
            <ul>
              <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>System Settings</a></li>
              <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>User Management</a></li>
              <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>Permissions</a></li>
              <li><a href="javascript:;"><i class="material-icons-outlined">arrow_right</i>Configuration</a></li>
            </ul>
          </li>
          
          <li>
            <a href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">help_outline</i>
              </div>
              <div class="menu-title">Help & Support</div>
            </a>
          </li>
         </ul>
        <!--end navigation-->
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