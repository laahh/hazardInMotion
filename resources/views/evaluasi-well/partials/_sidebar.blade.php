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
      <li>
        <a href="{{ route('evaluasi-well.index') }}" class="{{ request()->routeIs('evaluasi-well.index') || request()->routeIs('evaluasi-well.summary') || request()->routeIs('evaluasi-well.trend') || request()->routeIs('evaluasi-well.distribution') || request()->routeIs('evaluasi-well.leaderboard') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:home-smile-angle-outline" class="menu-icon"></iconify-icon>
          <span>Dashboard</span>
        </a>
      </li>
      <li>
        <a href="{{ route('evaluasi-well.health-nutrition.index') }}" class="{{ request()->routeIs('evaluasi-well.health-nutrition.*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:heart-pulse-outline" class="menu-icon"></iconify-icon>
          <span>Risiko MCU × Nutrisi</span>
        </a>
      </li>
      <li>
        <a href="{{ route('evaluasi-well.nutrition.index') }}" class="{{ request()->routeIs('evaluasi-well.nutrition.*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:cup-hot-outline" class="menu-icon"></iconify-icon>
          <span>Evaluasi Nutrisi</span>
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
