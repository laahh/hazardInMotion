<aside class="sidebar">
  <button type="button" class="sidebar-close-btn">
    <iconify-icon icon="radix-icons:cross-2"></iconify-icon>
  </button>
  <div>
    @php
      $authUser = auth()->user();
      $accessService = app(\App\Services\SportEvaluation\SportEvaluationAccessService::class);
      $isMitraOnlyUser = $accessService->isMitraOnlyUser($authUser);
      $canManageMitraAssignment = $accessService->canManageAssignments($authUser);
      $homeRoute = $isMitraOnlyUser
        ? route('evaluasi-well.mitra.index')
        : route('evaluasi-well.index');
    @endphp
    <a href="{{ $homeRoute }}" class="sidebar-logo">
      <img src="{{ asset('evaluasi-well-assets/images/logo.png') }}" alt="site logo" class="light-logo">
      <img src="{{ asset('evaluasi-well-assets/images/logo-light.png') }}" alt="site logo" class="dark-logo">
      <img src="{{ asset('evaluasi-well-assets/images/logo-icon.png') }}" alt="site logo" class="logo-icon">
    </a>
  </div>
  <div class="sidebar-menu-area">
    <ul class="sidebar-menu" id="sidebar-menu">
      @if ($isMitraOnlyUser)
      <li>
        <a href="{{ route('evaluasi-well.mitra.index') }}" class="{{ request()->routeIs('evaluasi-well.mitra.*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:buildings-2-outline" class="menu-icon"></iconify-icon>
          <span>Mitra Kerja</span>
        </a>
      </li>
      <li>
        <a href="{{ route('evaluasi-well.pvt.index') }}" class="{{ request()->routeIs('evaluasi-well.pvt.*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:eye-scan-outline" class="menu-icon"></iconify-icon>
          <span>Evaluasi PVT</span>
        </a>
      </li>
      @else
      <li>
        <a href="{{ route('evaluasi-well.index') }}" class="{{ request()->routeIs('evaluasi-well.index') || request()->routeIs('evaluasi-well.summary') || request()->routeIs('evaluasi-well.trend') || request()->routeIs('evaluasi-well.distribution') || request()->routeIs('evaluasi-well.leaderboard') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:home-smile-angle-outline" class="menu-icon"></iconify-icon>
          <span>Dashboard</span>
        </a>
      </li>
      <li>
        <a href="{{ route('evaluasi-well.mitra.index') }}" class="{{ request()->routeIs('evaluasi-well.mitra.*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:buildings-2-outline" class="menu-icon"></iconify-icon>
          <span>Mitra Kerja</span>
        </a>
      </li>
      @if ($canManageMitraAssignment)
      <li>
        <a href="{{ route('evaluasi-well.mitra-assignments.index') }}" class="{{ request()->routeIs('evaluasi-well.mitra-assignments.*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:user-speak-outline" class="menu-icon"></iconify-icon>
          <span>Assignment Mitra</span>
        </a>
      </li>
      @endif
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
      <li>
        <a href="{{ route('evaluasi-well.pvt.index') }}" class="{{ request()->routeIs('evaluasi-well.pvt.*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:eye-scan-outline" class="menu-icon"></iconify-icon>
          <span>Evaluasi PVT</span>
        </a>
      </li>
      <li>
        <a href="{{ route('evaluasi-well.weekly-uploads.index') }}" class="{{ request()->routeIs('evaluasi-well.weekly-uploads.*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:calendar-mark-outline" class="menu-icon"></iconify-icon>
          <span>Upload Mingguan</span>
        </a>
      </li>
      <li>
        <a href="{{ route('evaluasi-well.users.index') }}" class="{{ request()->routeIs('evaluasi-well.users.*') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:users-group-rounded-outline" class="menu-icon"></iconify-icon>
          <span>Manajemen User</span>
        </a>
      </li>
      <li class="sidebar-menu-group-title">Navigasi</li>
      <li>
        <a href="{{ url('/') }}">
          <iconify-icon icon="solar:arrow-left-outline" class="menu-icon"></iconify-icon>
          <span>Kembali ke Panel</span>
        </a>
      </li>
      @endif
    </ul>
  </div>
</aside>
