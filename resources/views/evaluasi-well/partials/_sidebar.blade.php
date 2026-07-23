<aside class="sidebar">
  <button type="button" class="sidebar-close-btn">
    <iconify-icon icon="radix-icons:cross-2"></iconify-icon>
  </button>
  <div>
    <a href="{{ route('evaluasi-well.index') }}" class="sidebar-logo">
      <img src="{{ asset('evaluasi-well-assets/images/logo.png') }}" alt="site logo" class="light-logo">
      <img src="{{ asset('evaluasi-well-assets/images/logo-light.png') }}" alt="site logo" class="dark-logo">
      <img src="{{ asset('evaluasi-well-assets/images/logo-icon.png') }}" alt="site logo" class="logo-icon">
    </a>
  </div>
  <div class="sidebar-menu-area">
    <ul class="sidebar-menu" id="sidebar-menu">
      <li class="dropdown open">
        <a href="javascript:void(0)">
          <iconify-icon icon="solar:home-smile-angle-outline" class="menu-icon"></iconify-icon>
          <span>Dashboard</span>
        </a>
        <ul class="sidebar-submenu" style="display:block;">
          <li>
            <a href="{{ route('evaluasi-well.index') }}" class="{{ request()->routeIs('evaluasi-well.index') ? 'active-page' : '' }}">
              <i class="ri-circle-fill circle-icon text-primary-600 w-auto"></i> Evaluasi Olahraga
            </a>
          </li>
          <li>
            <a href="{{ route('evaluasi-well.nutrition.index') }}" class="{{ request()->routeIs('evaluasi-well.nutrition.*') ? 'active-page' : '' }}">
              <i class="ri-circle-fill circle-icon text-success-main w-auto"></i> Evaluasi Nutrisi
            </a>
          </li>
          <li>
            <a href="{{ route('evaluasi-well.activities.index') }}" class="{{ request()->routeIs('evaluasi-well.activities.*') || request()->routeIs('evaluasi-well.employees.*') ? 'active-page' : '' }}">
              <i class="ri-circle-fill circle-icon text-warning-main w-auto"></i> Detail Aktivitas
            </a>
          </li>
        </ul>
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
