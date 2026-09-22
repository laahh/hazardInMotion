<aside class="sidebar">
  <button type="button" class="sidebar-close-btn">
    <iconify-icon icon="radix-icons:cross-2"></iconify-icon>
  </button>
  <div>
    <a href="{{ route('pnc-monitoring.index') }}" class="sidebar-logo">
      <img src="https://besentry-dev.beraucoal.co.id/build/images/logo-removebg.png" alt="site logo" class="light-logo">
      <img src="https://besentry-dev.beraucoal.co.id/build/images/logo-removebg.png" alt="site logo" class="dark-logo">
      <img src="https://besentry-dev.beraucoal.co.id/build/images/logo-removebg.png" alt="site logo" class="logo-icon">
    </a>
  </div>
  <div class="sidebar-menu-area">
    <ul class="sidebar-menu" id="sidebar-menu">
      <li>
        <a href="{{ route('pnc-monitoring.dashboard.main') }}" class="{{ request()->routeIs('pnc-monitoring.dashboard.main*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:pie-chart-2-outline" class="menu-icon"></iconify-icon>
          <span>Dashboard Utama</span>
        </a>
      </li>
      <li>
        <a href="{{ route('pnc-monitoring.index') }}" class="{{ request()->routeIs('pnc-monitoring.index') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:home-smile-angle-outline" class="menu-icon"></iconify-icon>
          <span>Portal</span>
        </a>
      </li>

      @php
        $ikkActive = request()->routeIs('pnc-monitoring.dashboard.ikk*') || request()->routeIs('pnc-monitoring.ikk-records.*');
        $commissioningActive = request()->routeIs('pnc-monitoring.dashboard.pengawas*') || request()->routeIs('pnc-monitoring.commissionings.*');
      @endphp

      <li class="dropdown {{ $ikkActive ? 'open' : '' }}">
        <a href="javascript:void(0)" class="{{ $ikkActive ? 'active-page' : '' }}">
          <iconify-icon icon="solar:widget-5-outline" class="menu-icon"></iconify-icon>
          <span>IKK</span>
        </a>
        <ul class="sidebar-submenu" @style(['display: block' => $ikkActive])>
          <li>
            <a href="{{ route('pnc-monitoring.dashboard.ikk') }}" class="{{ request()->routeIs('pnc-monitoring.dashboard.ikk*') ? 'active-page' : '' }}">
              <i class="ri-circle-fill circle-icon text-primary-600 w-auto"></i> Dashboard
            </a>
          </li>
          <li>
            <a href="{{ route('pnc-monitoring.ikk-records.index') }}" class="{{ request()->routeIs('pnc-monitoring.ikk-records.*') ? 'active-page' : '' }}">
              <i class="ri-circle-fill circle-icon text-warning-main w-auto"></i> Master Data
            </a>
          </li>
        </ul>
      </li>

      <li class="dropdown {{ $commissioningActive ? 'open' : '' }}">
        <a href="javascript:void(0)" class="{{ $commissioningActive ? 'active-page' : '' }}">
          <iconify-icon icon="solar:clipboard-check-outline" class="menu-icon"></iconify-icon>
          <span>Commissioning</span>
        </a>
        <ul class="sidebar-submenu" @style(['display: block' => $commissioningActive])>
          <li>
            <a href="{{ route('pnc-monitoring.dashboard.pengawas') }}" class="{{ request()->routeIs('pnc-monitoring.dashboard.pengawas*') ? 'active-page' : '' }}">
              <i class="ri-circle-fill circle-icon text-success-main w-auto"></i> Dashboard
            </a>
          </li>
          <li>
            <a href="{{ route('pnc-monitoring.commissionings.index') }}" class="{{ request()->routeIs('pnc-monitoring.commissionings.*') ? 'active-page' : '' }}">
              <i class="ri-circle-fill circle-icon text-purple w-auto"></i> Master Data
            </a>
          </li>
        </ul>
      </li>
    </ul>
  </div>
</aside>
