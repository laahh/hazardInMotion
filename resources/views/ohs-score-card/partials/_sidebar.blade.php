{{--
  Sidebar OHS Score Card.

  Setiap section = departemen, isinya menu milik dept tersebut.
  Struktur menu didefinisikan sekali di $deptMenus lalu di-render lewat loop,
  karena beberapa menu dipakai di lebih dari satu dept (mis. "PJA Performance"
  di SOD & SIRC, "Traffic Management" di SOD, SGI, & SIRM).

  Menambah/menghapus menu cukup ubah array di bawah — markup-nya ikut.
  Begitu halaman tujuan dibuat, isi key 'url' (mis. 'url' => route('...'));
  selama kosong, link-nya javascript:void(0) supaya tidak menghasilkan 404.
--}}
@php
    $deptMenus = [
        'SOD' => [
            ['label' => 'PJA Performance',                     'icon' => 'solar:chart-square-outline'],
            ['label' => 'Supervisory Layering System',         'icon' => 'solar:users-group-two-rounded-outline'],
            ['label' => 'Speak up',                            'icon' => 'solar:user-speak-outline'],
            ['label' => 'Traffic Management',                  'icon' => 'solar:traffic-outline'],
            ['label' => 'Utilisasi Tools Pengawasan Teknologi', 'icon' => 'solar:videocamera-outline'],
            ['label' => 'Pengawasan Control Room',             'icon' => 'solar:monitor-outline'],
        ],
        'SIRC' => [
            ['label' => 'PJA Performance',                     'icon' => 'solar:chart-square-outline'],
        ],
        'OC' => [
            ['label' => 'Supervisory Layering System',         'icon' => 'solar:users-group-two-rounded-outline'],
            ['label' => 'SPIP management',                     'icon' => 'solar:clipboard-check-outline'],
            ['label' => 'CSMS',                                'icon' => 'solar:documents-outline'],
        ],
        'HSECT' => [
            ['label' => 'Safety Behavior Treatment',           'icon' => 'solar:shield-user-outline'],
            ['label' => 'Competency',                          'icon' => 'solar:medal-star-outline'],
        ],
        'SGI' => [
            ['label' => 'Traffic Management',                  'icon' => 'solar:traffic-outline'],
        ],
        'SIRM' => [
            ['label' => 'Traffic Management',                  'icon' => 'solar:traffic-outline'],
            ['label' => 'K3L Compliance',                      'icon' => 'solar:clipboard-list-outline'],
            ['label' => 'Risk Management',                     'icon' => 'solar:danger-triangle-outline'],
        ],
        'G&H' => [
            ['label' => 'Geotechnical Management',             'icon' => 'material-symbols:terrain'],
        ],
        'OH & IH' => [
            ['label' => 'Health Management',                   'icon' => 'solar:heart-pulse-outline'],
            ['label' => 'Fatigue Management',                  'icon' => 'solar:bed-outline'],
        ],
        'ER & SS' => [
            ['label' => 'Emergency Response',                  'icon' => 'solar:siren-outline'],
        ],
    ];
@endphp
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

      @foreach ($deptMenus as $dept => $menus)
      <li class="sidebar-menu-group-title">{{ $dept }}</li>
        @foreach ($menus as $menu)
      <li>
        <a href="{{ $menu['url'] ?? 'javascript:void(0)' }}">
          <iconify-icon icon="{{ $menu['icon'] }}" class="menu-icon"></iconify-icon>
          <span>{{ $menu['label'] }}</span>
        </a>
      </li>
        @endforeach
      @endforeach

    </ul>
  </div>
</aside>
