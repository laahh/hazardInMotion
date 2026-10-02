{{--
  Sidebar OHS Score Card.

  Setiap section = departemen, isinya indikator score card milik dept tersebut.
  Daftarnya didefinisikan sekali di $deptMenus lalu di-render lewat loop, supaya
  menambah/menghapus indikator cukup ubah array — markup-nya ikut.

  Begitu halaman tujuan dibuat, isi key 'url' (mis. 'url' => route('...'));
  selama kosong, link-nya javascript:void(0) supaya tidak menghasilkan 404.

  Label indikator di sini panjang-panjang (ada yang >60 karakter), jadi ada
  style khusus di bawah supaya teks wrap rapi dan icon-nya tidak ikut gepeng.
  Style-nya discope ke .osc-sidebar karena style.css dipakai bareng modul lain.
--}}
@php
    $deptMenus = [
        'SOD' => [
            ['label' => 'Ratio TBC & GR',                                     'icon' => 'solar:chart-square-outline'],
            ['label' => 'Coverage Daily',                                          'icon' => 'solar:map-outline'],
            ['label' => 'Blindspot TBC',                             'icon' => 'solar:eye-closed-outline'],
            ['label' => 'Blindspot GR',                              'icon' => 'solar:eye-closed-outline'],
            ['label' => 'Coverage Area Kritis Pengawas Suptend up',                     'icon' => 'solar:map-point-outline'],
            ['label' => '%Pengawasan Berjarak',                                        'icon' => 'solar:ruler-outline'],
            ['label' => '%Blindspot temuan Real Time',                                 'icon' => 'solar:alarm-outline'],
            ['label' => 'Coverage Daily Area Kritis Pengawas Safety',                   'icon' => 'solar:shield-check-outline'],
            ['label' => 'Speak up fatigue',                                             'icon' => 'solar:user-speak-outline'],
            ['label' => 'Tidak ada temuan penggunaan HP',                               'icon' => 'solar:smartphone-outline'],
            ['label' => 'Incident dengan Gap Coverage CCTV & Gap pada DMS',             'icon' => 'solar:videocamera-outline'],
            ['label' => 'Leadtime Alert DMS masuk ke Server',                           'icon' => 'solar:server-outline'],
            ['label' => 'Kinerja Pengawasan Control Room DMS',                          'icon' => 'solar:monitor-outline'],
        ],
        'SIRC' => [
            ['label' => 'Perulangan rekomendasi hasil investigasi',                     'icon' => 'solar:refresh-outline'],
        ],
        'OC' => [
            ['label' => 'Kesesuaian Implementasi IKK',                                  'icon' => 'solar:clipboard-check-outline'],
            ['label' => '% SPIP yang dilakukan Commissioning',                          'icon' => 'solar:settings-outline'],
            ['label' => 'Laporan Perizinan Usaha Jasa',                                 'icon' => 'solar:document-text-outline'],
            ['label' => '% Blindspot TBC dengan PIC Subcontractor',                     'icon' => 'solar:users-group-rounded-outline'],
        ],
        'HSECT' => [
            ['label' => 'Peer Pressure',                                                'icon' => 'solar:users-group-two-rounded-outline', 'url' => route('ohs-score-card.peer-pressure.index')],
            ['label' => 'Pemenuhan Sertifikasi Pengawas Teknis',                        'icon' => 'solar:diploma-outline'],
            ['label' => 'Pemenuhan Sertifikasi Tenaga Teknis',                          'icon' => 'solar:diploma-verified-outline'],
        ],
        'SGI' => [
            ['label' => 'Jalan sesuai standar',                                         'icon' => 'solar:routing-outline', 'url' => route('ohs-score-card.jalan-sesuai-standar.index')],
        ],
        'SIRM' => [
            ['label' => 'Deviasi Rekayasa Engineering Seatbelt',                        'icon' => 'mdi:seatbelt'],
            ['label' => 'Deviasi Rekayasa Engineering Overspeed',                       'icon' => 'solar:speedometer-outline'],
            ['label' => 'Pemenuhan Regulasi',                                           'icon' => 'solar:clipboard-list-outline'],
            ['label' => 'Penuntasan pengendalian rekayasa',                             'icon' => 'solar:wrench-outline'],
        ],
        'G&H' => [
            ['label' => 'Utilisasi BeSigma',                                            'icon' => 'solar:widget-outline'],
        ],
        'OH & IH' => [
            ['label' => 'Rasio kelayakan kerja (wellbeing)',                            'icon' => 'solar:heart-pulse-outline'],
            ['label' => 'Pemeriksaan Fit to Work awal shift pekerja',                   'icon' => 'solar:stethoscope-outline'],
            ['label' => 'Pelaksanaan Sobriety Test Jam Kritis dan Pengecekan Sobriety Test', 'icon' => 'solar:test-tube-outline'],
        ],
        'ER & SS' => [
            ['label' => 'Tidak ada pelaporan melewati batas golden time',               'icon' => 'solar:clock-circle-outline'],
            ['label' => 'Kesiapan alat Emergency',                                      'icon' => 'solar:siren-outline'],
        ],
    ];
@endphp
<style>
  /* Label indikator panjang dipotong jadi "…" supaya tiap item tetap 1 baris:
     sidebar tidak memanjang dan icon tidak ikut gepeng. Teks penuhnya tetap
     bisa dibaca lewat atribut title (tooltip saat hover).
     Catatan: `display` sengaja tidak di-set di <span> — rule bawaan
     `.sidebar.active .sidebar-menu li a span { display:none }` specificity-nya
     lebih tinggi, jadi mode sidebar collapsed tetap jalan seperti biasa. */
  .osc-sidebar .sidebar-menu li a {
    align-items: center;
  }
  .osc-sidebar .sidebar-menu li a .menu-icon {
    flex-shrink: 0;
  }
  .osc-sidebar .sidebar-menu li a span {
    flex: 1 1 auto;
    min-width: 0; /* wajib, kalau tidak flex item menolak menyusut & ellipsis mati */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
</style>
<aside class="sidebar osc-sidebar">
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
        <a href="{{ $menu['url'] ?? 'javascript:void(0)' }}" title="{{ $menu['label'] }}">
          <iconify-icon icon="{{ $menu['icon'] }}" class="menu-icon"></iconify-icon>
          <span>{{ $menu['label'] }}</span>
        </a>
      </li>
        @endforeach
      @endforeach

    </ul>
  </div>
</aside>
