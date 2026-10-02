{{--
  Sidebar OHS Score Card.

  Struktur menu mengikuti template WowDash (grup Application & UI Elements).
  Selain Dashboard, item-item di bawah belum punya halaman di aplikasi ini,
  jadi href-nya sengaja javascript:void(0) supaya tidak menghasilkan 404 —
  ganti ke route(...) begitu halaman tujuannya dibuat.

  Dropdown (.dropdown > .sidebar-submenu) digerakkan oleh
  public/evaluasi-well-assets/js/app.js yang sudah dimuat di layout.
--}}
<aside class="sidebar">
  <button type="button" class="sidebar-close-btn">
    <iconify-icon icon="radix-icons:cross-2"></iconify-icon>
  </button>
  <div>
    <a href="{{ route('ohs-score-card.index') }}" class="sidebar-logo">
      <img src="https://besentry-dev.beraucoal.co.id/build/images/logo-removebg.png" alt="site logo" class="light-logo">
      <img src="https://besentry-dev.beraucoal.co.id/build/images/logo-removebg.png" alt="site logo" class="dark-logo">
      <img src="https://besentry-dev.beraucoal.co.id/build/images/logo-removebg.png" alt="site logo" class="logo-icon">
    </a>
  </div>
  <div class="sidebar-menu-area">
    <ul class="sidebar-menu" id="sidebar-menu">

      <li>
        <a href="{{ route('ohs-score-card.index') }}" class="{{ request()->routeIs('ohs-score-card.index') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:home-smile-angle-outline" class="menu-icon"></iconify-icon>
          <span>Dashboard</span>
        </a>
      </li>

      <li class="sidebar-menu-group-title">SOD</li>

      <li>
        <a href="javascript:void(0)">
          <iconify-icon icon="mage:email" class="menu-icon"></iconify-icon>
          <span>PJA Performance</span>
        </a>
      </li>
      <li>
        <a href="javascript:void(0)">
          <iconify-icon icon="bi:chat-dots" class="menu-icon"></iconify-icon>
          <span>Supervisory Layering System</span>
        </a>
      </li>
      <li>
        <a href="javascript:void(0)">
          <iconify-icon icon="solar:calendar-outline" class="menu-icon"></iconify-icon>
          <span>Speak up</span>
        </a>
      </li>
      <li>
        <a href="javascript:void(0)">
          <iconify-icon icon="material-symbols:map-outline" class="menu-icon"></iconify-icon>
          <span>Traffic Management</span>
        </a>
      </li>
      <li>
        <a href="">
          <iconify-icon icon="material-symbols:map-outline" class="menu-icon"></iconify-icon>
          <span>Utilisasi Tools Pengawasan Teknologi</span>
        </a>
      </li>

       <li>
        <a href="">
          <iconify-icon icon="material-symbols:map-outline" class="menu-icon"></iconify-icon>
          <span>Pengawasan Control Room</span>
        </a>
      </li>

      <li>
        <a href="">
          <iconify-icon icon="material-symbols:map-outline" class="menu-icon"></iconify-icon>
          <span>Pengawasan Control Room</span>
        </a>
      </li>
     
    

      <li class="sidebar-menu-group-title">HSECT</li>

      <li class="dropdown">
        <a href="javascript:void(0)">
          <iconify-icon icon="heroicons:document" class="menu-icon"></iconify-icon>
          <span>Safety Behavior Treatment</span>
        </a>
        <ul class="sidebar-submenu">
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-primary-600 w-auto"></i> Input Forms</a></li>
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-warning-main w-auto"></i> Input Layout</a></li>
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-success-main w-auto"></i> Form Validation</a></li>
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-danger-main w-auto"></i> Form Wizard</a></li>
        </ul>
      </li>
      <li class="dropdown">
        <a href="javascript:void(0)">
          <iconify-icon icon="mingcute:storage-line" class="menu-icon"></iconify-icon>
          <span>Table</span>
        </a>
        <ul class="sidebar-submenu">
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-primary-600 w-auto"></i> Basic Table</a></li>
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-warning-main w-auto"></i> Data Table</a></li>
        </ul>
      </li>
      <li class="dropdown">
        <a href="javascript:void(0)">
          <iconify-icon icon="solar:pie-chart-outline" class="menu-icon"></iconify-icon>
          <span>Chart</span>
        </a>
        <ul class="sidebar-submenu">
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-danger-main w-auto"></i> Line Chart</a></li>
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-warning-main w-auto"></i> Column Chart</a></li>
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-success-main w-auto"></i> Pie Chart</a></li>
        </ul>
      </li>
      <li>
        <a href="javascript:void(0)">
          <iconify-icon icon="fe:vector" class="menu-icon"></iconify-icon>
          <span>Widgets</span>
        </a>
      </li>
      <li class="dropdown">
        <a href="javascript:void(0)">
          <iconify-icon icon="flowbite:users-group-outline" class="menu-icon"></iconify-icon>
          <span>Users</span>
        </a>
        <ul class="sidebar-submenu">
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-primary-600 w-auto"></i> Users List</a></li>
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-warning-main w-auto"></i> Users Grid</a></li>
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-info-main w-auto"></i> Add User</a></li>
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-danger-main w-auto"></i> View Profile</a></li>
        </ul>
      </li>
      <li class="dropdown">
        <a href="javascript:void(0)">
          <i class="ri-user-settings-line text-xl me-14 d-flex w-auto"></i>
          <span>Role &amp; Access</span>
        </a>
        <ul class="sidebar-submenu">
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-primary-600 w-auto"></i> Role &amp; Access</a></li>
          <li><a href="javascript:void(0)"><i class="ri-circle-fill circle-icon text-warning-main w-auto"></i> Assign Role</a></li>
        </ul>
      </li>

    </ul>
  </div>
</aside>
