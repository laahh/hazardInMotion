<aside class="sidebar">
  <button type="button" class="sidebar-close-btn">
    <iconify-icon icon="radix-icons:cross-2"></iconify-icon>
  </button>
  <div>
    <a href="{{ route('dms.index') }}" class="sidebar-logo">
      <img src="{{ asset('evaluasi-well-assets/images/logo.png') }}" alt="site logo" class="light-logo">
      <img src="{{ asset('evaluasi-well-assets/images/logo-light.png') }}" alt="site logo" class="dark-logo">
      <img src="{{ asset('evaluasi-well-assets/images/logo-icon.png') }}" alt="site logo" class="logo-icon">
    </a>
  </div>
  <div class="sidebar-menu-area">
    <ul class="sidebar-menu" id="sidebar-menu">
      <li class="sidebar-menu-group-title">Driver Monitoring System</li>
      <li>
        <a href="{{ route('dms.dashboard') }}" class="{{ request()->routeIs('dms.dashboard') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:home-smile-angle-outline" class="menu-icon"></iconify-icon>
          <span>Dashboard DMS</span>
        </a>
      </li>
      <li>
        <a href="{{ route('dms.dashboard-static') }}" class="{{ request()->routeIs('dms.dashboard-static') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:monitor-smartphone-outline" class="menu-icon"></iconify-icon>
          <span>Realtime Monitoring</span>
        </a>
      </li>
      <li>
        <a href="{{ route('dms.detection') }}" class="{{ request()->routeIs('dms.detection') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:videocamera-record-outline" class="menu-icon"></iconify-icon>
          <span>Deteksi</span>
        </a>
      </li>
      <li>
        <a href="{{ route('dms.fatigue-baseline-static') }}" class="{{ request()->routeIs('dms.fatigue-baseline-static') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:chart-2-outline" class="menu-icon"></iconify-icon>
          <span>Fatigue Baseline</span>
        </a>
      </li>
      <li>
        <a href="{{ route('pra-operasi.dashboard') }}" class="{{ request()->routeIs('pra-operasi.*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:clipboard-check-outline" class="menu-icon"></iconify-icon>
          <span>Pra Operasi</span>
        </a>
      </li>
      <li class="sidebar-menu-group-title">Navigasi</li>
      <li>
        <a href="{{ url('/') }}">
          <iconify-icon icon="solar:arrow-left-outline" class="menu-icon"></iconify-icon>
          <span>Kembali ke Panel</span>
        </a>
      </li>
    </ul>
  </div>
</aside>
