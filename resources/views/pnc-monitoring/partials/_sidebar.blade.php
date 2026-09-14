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
        <a href="{{ route('pnc-monitoring.index') }}" class="{{ request()->routeIs('pnc-monitoring.index') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:home-smile-angle-outline" class="menu-icon"></iconify-icon>
          <span>Portal</span>
        </a>
      </li>
      <li>
        <a href="{{ route('pnc-monitoring.dashboard.ikk') }}" class="{{ request()->routeIs('pnc-monitoring.dashboard.ikk*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:chart-2-outline" class="menu-icon"></iconify-icon>
          <span>Dashboard IKK</span>
        </a>
      </li>
      <li>
        <a href="{{ route('pnc-monitoring.dashboard.pengawas') }}" class="{{ request()->routeIs('pnc-monitoring.dashboard.pengawas*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:user-check-outline" class="menu-icon"></iconify-icon>
          <span>Dashboard Pengawas</span>
        </a>
      </li>
      <li>
        <a href="{{ route('pnc-monitoring.ikk-records.index') }}" class="{{ request()->routeIs('pnc-monitoring.ikk-records.*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:document-text-outline" class="menu-icon"></iconify-icon>
          <span>Data IKK</span>
        </a>
      </li>
      <li>
        <a href="{{ route('pnc-monitoring.commissionings.index') }}" class="{{ request()->routeIs('pnc-monitoring.commissionings.*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:clipboard-list-outline" class="menu-icon"></iconify-icon>
          <span>Data Commissioning</span>
        </a>
      </li>
    </ul>
  </div>
</aside>
