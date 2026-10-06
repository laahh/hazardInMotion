{{--
  Sidebar OHS Score Card — pohon tiga tingkat.

      Tingkat 0  menu utama      Incident Management, Risk Exposure
      Tingkat 1  kelompok        submenu langsung, atau departemen (SOD, SIRC, …)
      Tingkat 2  indikator       parameter score card milik departemen itu

  Daftarnya didefinisikan sekali di $deptMenus lalu dibungkus $mainMenus, supaya
  menambah indikator cukup ubah array; markup dan garis pohonnya ikut sendiri.
  Menambah menu utama ketiga nanti cukup satu entri baru di $mainMenus.

  Begitu halaman tujuan dibuat, isi key 'url' (mis. 'url' => route('...'));
  selama kosong, link-nya javascript:void(0) supaya tidak menghasilkan 404.

  TIDAK MEMAKAI KELAS .dropdown/.sidebar-submenu BAWAAN TEMA, dan itu disengaja.
  app.js mengikat handler ke `.sidebar-menu .dropdown` tanpa menghentikan
  perambatan event, jadi mengklik simpul tingkat 2 akan ikut menutup induknya di
  tingkat 1. Pohon ini memakai kelas sendiri (.osc-node/.osc-row/.osc-children)
  dengan JS sendiri di bawah, sehingga app.js yang dipakai bareng modul lain
  tidak perlu disentuh.
--}}
@php
    $deptMenus = [
        'SOD' => [
            ['label' => 'Ratio TBC',                                     'icon' => 'solar:chart-square-outline', 'url' => route('ohs-score-card.ratio-tbc-gr.index')],
            // ['label' => 'Ratio GR',                                      'icon' => 'solar:chart-square-outline'],
            ['label' => 'Coverage Daily',                                          'icon' => 'solar:map-outline', 'url' => route('ohs-score-card.coverage-area-daily.index')],
            ['label' => 'Blindspot TBC',                             'icon' => 'solar:eye-closed-outline', 'url' => route('ohs-score-card.blindspot-tbc.index')],
            ['label' => 'Blindspot GR',                              'icon' => 'solar:eye-closed-outline', 'url' => route('ohs-score-card.blindspot-gr.index')],
            ['label' => 'Coverage Area Kritis Pengawas Suptend up',                     'icon' => 'solar:map-point-outline', 'url' => route('ohs-score-card.coverage-area-kritis.index')],
            ['label' => '%Pengawasan Berjarak',                                        'icon' => 'solar:ruler-outline', 'url' => route('ohs-score-card.pengawasan-berjarak.index')],
            ['label' => '%Blindspot temuan Real Time',                                 'icon' => 'solar:alarm-outline', 'url' => route('ohs-score-card.blindspot-real-time.index')],
            ['label' => 'Coverage Daily Area Kritis Pengawas Safety',                   'icon' => 'solar:shield-check-outline', 'url' => route('ohs-score-card.coverage-daily-area-kritis-safety.index')],
            ['label' => 'Speak up fatigue',                                             'icon' => 'solar:user-speak-outline', 'url' => route('ohs-score-card.speak-up-fatigue.index')],
            ['label' => 'Tidak ada temuan penggunaan HP',                               'icon' => 'solar:smartphone-outline', 'url' => route('ohs-score-card.penggunaan-hp.index')],
            // ['label' => 'GR Seatbelt',                                                  'icon' => 'mdi:seatbelt'],
            ['label' => 'Incident dengan Gap Coverage CCTV & Gap pada DMS',             'icon' => 'solar:videocamera-outline', 'url' => route('ohs-score-card.incident-gap-cctv-dms.index')],
            ['label' => 'Leadtime Alert DMS masuk ke Server',                           'icon' => 'solar:server-outline', 'url' => route('ohs-score-card.leadtime-alert-bedms.index')],
            ['label' => 'Kinerja Pengawasan Control Room DMS',                          'icon' => 'solar:monitor-outline', 'url' => route('ohs-score-card.kinerja-control-room-dms.index')],
        ],
        'SIRC' => [
            ['label' => 'Perulangan rekomendasi hasil investigasi',                     'icon' => 'solar:refresh-outline', 'url' => route('ohs-score-card.perulangan-rekomendasi.index')],
        ],
        'OC' => [
            ['label' => 'Kesesuaian Implementasi IKK',                                  'icon' => 'solar:clipboard-check-outline', 'url' => route('ohs-score-card.compliance-ikk.index')],
            ['label' => '% SPIP yang dilakukan Commissioning',                          'icon' => 'solar:settings-outline', 'url' => route('ohs-score-card.spip-commissioning.index')],
            ['label' => 'Laporan Perizinan Usaha Jasa',                                 'icon' => 'solar:document-text-outline'],
            ['label' => '% Blindspot TBC dengan PIC Subcontractor',                     'icon' => 'solar:users-group-rounded-outline', 'url' => route('ohs-score-card.blindspot-tbc-pic-subcont.index')],
        ],
        'HSECT' => [
            ['label' => 'Peer Pressure',                                                'icon' => 'solar:users-group-two-rounded-outline', 'url' => route('ohs-score-card.peer-pressure.index')],
            ['label' => 'Pemenuhan Sertifikasi Pengawas Teknis',                        'icon' => 'solar:diploma-outline', 'url' => route('ohs-score-card.sertifikasi-pengawas-teknis.index')],
            ['label' => 'Pemenuhan Sertifikasi Tenaga Teknis',                          'icon' => 'solar:diploma-verified-outline', 'url' => route('ohs-score-card.sertifikasi-tenaga-teknis.index')],
        ],
        'SGI' => [
            ['label' => 'Jalan sesuai standar',                                         'icon' => 'solar:routing-outline', 'url' => route('ohs-score-card.jalan-sesuai-standar.index')],
        ],
        'SIRM' => [
            ['label' => 'Deviasi Rekayasa Engineering Seatbelt',                        'icon' => 'mdi:seatbelt', 'url' => route('ohs-score-card.gr-seatbelt.index')],
            ['label' => 'Deviasi Rekayasa Engineering Overspeed',                       'icon' => 'solar:speedometer-outline', 'url' => route('ohs-score-card.pelanggaran-overspeed.index')],
            ['label' => 'Pemenuhan Regulasi',                                           'icon' => 'solar:clipboard-list-outline'],
            ['label' => 'Penuntasan pengendalian rekayasa',                             'icon' => 'solar:wrench-outline', 'url' => route('ohs-score-card.penuntasan-pengendalian-rekayasa.index')],
        ],
        'G&H' => [
            ['label' => 'Utilisasi BeSigma',                                            'icon' => 'solar:widget-outline', 'url' => route('ohs-score-card.utilisasi-besigma.index')],
        ],
        'OH & IH' => [
            ['label' => 'Rasio kelayakan kerja (wellbeing)',                            'icon' => 'solar:heart-pulse-outline', 'url' => route('ohs-score-card.rasio-kelayakan-kerja.index')],
            ['label' => 'Pemeriksaan Fit to Work awal shift pekerja',                   'icon' => 'solar:stethoscope-outline', 'url' => route('ohs-score-card.fit-to-work-awal-shift.index')],
            ['label' => 'Pelaksanaan Sobriety Test Jam Kritis dan Pengecekan Sobriety Test', 'icon' => 'solar:test-tube-outline', 'url' => route('ohs-score-card.sobriety-test.index')],
        ],
        'ER & SS' => [
            ['label' => 'Tidak ada pelaporan melewati batas golden time',               'icon' => 'solar:clock-circle-outline', 'url' => route('ohs-score-card.golden-time-emergency.index')],
            ['label' => 'Kesiapan alat Emergency',                                      'icon' => 'solar:siren-outline'],
        ],
    ];

    /*
     * Menu utama. Dua bentuk anak yang dikenali, dan hanya dua:
     *
     *   'items'  -> daun langsung di tingkat 1 (Incident Management)
     *   'groups' -> simpul tingkat 1 yang masing-masing punya daun di tingkat 2
     *               (Risk Exposure, isinya $deptMenus)
     *
     * Menu utama ketiga belum ditentukan; menambahkannya cukup satu entri di
     * sini dengan salah satu dari dua bentuk di atas.
     */
    $mainMenus = [
        [
            'key' => 'incident-management',
            'label' => 'Incident Management',
            'icon' => 'solar:danger-triangle-outline',
            'items' => [
                ['label' => 'Dashboard',        'icon' => 'solar:chart-2-outline', 'url' => route('ohs-score-card.incident-management.dashboard.index')],
                ['label' => 'Korelasi Insiden', 'icon' => 'solar:graph-new-outline'],
                ['label' => 'Master Data',      'icon' => 'solar:database-outline', 'url' => route('ohs-score-card.incident-management.master-data.index')],
            ],
        ],
        [
            'key' => 'risk-exposure',
            'label' => 'Risk Exposure',
            'icon' => 'solar:shield-warning-outline',
            'groups' => $deptMenus,
        ],
    ];

    /*
     * Cabang yang memuat halaman yang sedang dibuka harus terbuka saat sidebar
     * dirender, bukan menunggu JS: kalau JS gagal, menu aktifnya tetap terlihat.
     */
    $urlSekarang = url()->current();
    $sedangDibuka = static fn (array $menu): bool => isset($menu['url']) && $menu['url'] === $urlSekarang;

    foreach ($mainMenus as $i => $main) {
        $terbuka = false;

        foreach ($main['items'] ?? [] as $item) {
            $terbuka = $terbuka || $sedangDibuka($item);
        }

        foreach ($main['groups'] ?? [] as $dept => $menus) {
            $deptTerbuka = false;

            foreach ($menus as $menu) {
                $deptTerbuka = $deptTerbuka || $sedangDibuka($menu);
            }

            $mainMenus[$i]['groups_terbuka'][$dept] = $deptTerbuka;
            $terbuka = $terbuka || $deptTerbuka;
        }

        $mainMenus[$i]['terbuka'] = $terbuka;
    }
@endphp
<style>
  /* Semua aturan discope ke .osc-sidebar karena style.css dipakai bareng
     modul lain; tidak ada satu pun selector di sini yang bocor keluar. */

  /* Label indikator panjang-panjang (ada yang >60 karakter) dipotong jadi "…"
     supaya tiap baris tetap setinggi satu baris dan ikon tidak ikut gepeng.
     Teks penuhnya tetap terbaca lewat atribut title saat hover.
     Yang dipotong HANYA .osc-label, bukan sembarang <span>, sebab penanda
     buka/tutup juga sebuah <span> dan akan rusak kalau ikut disusutkan. */
  .osc-sidebar .sidebar-menu li a {
    align-items: center;
  }
  .osc-sidebar .sidebar-menu li a .menu-icon {
    flex-shrink: 0;
  }
  .osc-sidebar .sidebar-menu li a .osc-label {
    flex: 1 1 auto;
    min-width: 0; /* wajib, kalau tidak flex item menolak menyusut & ellipsis mati */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* ---- Penanda buka/tutup ------------------------------------------------
     Lingkaran ⊞ / ⊟ digambar dengan CSS, bukan ikon, supaya tidak ada kedipan
     saat iconify belum selesai memuat. Warnanya currentColor, jadi ikut tema
     terang maupun gelap tanpa perlu daftar warna sendiri. */
  .osc-sidebar .osc-mark {
    position: relative;
    flex: 0 0 auto;
    width: 15px;
    height: 15px;
    margin-inline-end: 8px;
    border: 1.5px solid currentColor;
    border-radius: 50%;
    opacity: .65;
  }
  .osc-sidebar .osc-mark::before,
  .osc-sidebar .osc-mark::after {
    content: '';
    position: absolute;
    inset-inline-start: 50%;
    inset-block-start: 50%;
    transform: translate(-50%, -50%);
    background: currentColor;
  }
  .osc-sidebar .osc-mark::before { width: 7px; height: 1.5px; }
  .osc-sidebar .osc-mark::after  { width: 1.5px; height: 7px; }
  /* Terbuka: batang tegaknya hilang, jadi ⊟. */
  .osc-sidebar .osc-row[aria-expanded="true"] .osc-mark::after { display: none; }
  .osc-sidebar .osc-row:hover .osc-mark { opacity: 1; }

  .osc-sidebar .osc-row { cursor: pointer; }

  /* ---- Garis pohon -------------------------------------------------------
     Tiap anak menggambar ruas tegaknya sendiri, bukan satu garis panjang milik
     <ul>, supaya anak terakhir otomatis membentuk siku "└" tanpa perlu tahu
     berapa jumlah anaknya. */
  .osc-sidebar .osc-children {
    position: relative;
    margin: 2px 0 2px;
    padding-inline-start: 28px;
    list-style: none;
  }
  .osc-sidebar .osc-children > li {
    position: relative;
  }
  .osc-sidebar .osc-children > li::before { /* ruas tegak */
    content: '';
    position: absolute;
    inset-inline-start: -15px;
    inset-block-start: 0;
    inset-block-end: 0;
    width: 1px;
    background: currentColor;
    opacity: .22;
  }
  .osc-sidebar .osc-children > li:last-child::before {
    inset-block-end: auto;
    height: 19px; /* berhenti tepat di siku, membentuk "└" */
  }
  .osc-sidebar .osc-children > li::after { /* siku mendatar */
    content: '';
    position: absolute;
    inset-inline-start: -15px;
    inset-block-start: 19px;
    width: 11px;
    height: 1px;
    background: currentColor;
    opacity: .22;
  }

  /* Departemen di tingkat 1 tidak berikon: tanpa ikon, perbedaan tingkatnya
     terbaca dari indentasi dan garis pohon saja, dan baris jadi lebih pendek. */
  .osc-sidebar .osc-row--dept .osc-label {
    font-weight: 600;
    letter-spacing: .02em;
    text-transform: uppercase;
    font-size: .75rem;
  }

  .osc-sidebar .osc-children--tutup { display: none; }

  /* ---- Sidebar terlipat --------------------------------------------------
     Saat hanya ikon yang tampil, pohon dan penandanya tidak ada gunanya dan
     justru merusak perataan ikon. */
  .sidebar.active.osc-sidebar .osc-children,
  .sidebar.active.osc-sidebar .osc-mark {
    display: none !important;
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
          <span class="osc-label">Dashboard</span>
        </a>
      </li>

      @foreach ($mainMenus as $main)
      <li class="osc-node" data-osc-key="{{ $main['key'] }}">
        <a href="javascript:void(0)" class="osc-row" role="button"
           aria-expanded="{{ $main['terbuka'] ? 'true' : 'false' }}" title="{{ $main['label'] }}">
          <span class="osc-mark" aria-hidden="true"></span>
          <iconify-icon icon="{{ $main['icon'] }}" class="menu-icon"></iconify-icon>
          <span class="osc-label">{{ $main['label'] }}</span>
        </a>

        <ul class="osc-children {{ $main['terbuka'] ? '' : 'osc-children--tutup' }}">

          {{-- Bentuk pertama: daun langsung di tingkat 1. --}}
          @foreach ($main['items'] ?? [] as $item)
          <li>
            <a href="{{ $item['url'] ?? 'javascript:void(0)' }}" title="{{ $item['label'] }}"
               class="{{ ($item['url'] ?? null) === $urlSekarang ? 'active-page' : '' }}">
              <iconify-icon icon="{{ $item['icon'] }}" class="menu-icon"></iconify-icon>
              <span class="osc-label">{{ $item['label'] }}</span>
            </a>
          </li>
          @endforeach

          {{-- Bentuk kedua: simpul tingkat 1 yang punya daun di tingkat 2. --}}
          @foreach ($main['groups'] ?? [] as $dept => $menus)
          @php $deptTerbuka = $main['groups_terbuka'][$dept] ?? false; @endphp
          <li class="osc-node" data-osc-key="{{ $main['key'] }}/{{ $dept }}">
            <a href="javascript:void(0)" class="osc-row osc-row--dept" role="button"
               aria-expanded="{{ $deptTerbuka ? 'true' : 'false' }}" title="{{ $dept }}">
              <span class="osc-mark" aria-hidden="true"></span>
              <span class="osc-label">{{ $dept }}</span>
            </a>

            <ul class="osc-children {{ $deptTerbuka ? '' : 'osc-children--tutup' }}">
              @foreach ($menus as $menu)
              <li>
                <a href="{{ $menu['url'] ?? 'javascript:void(0)' }}" title="{{ $menu['label'] }}"
                   class="{{ ($menu['url'] ?? null) === $urlSekarang ? 'active-page' : '' }}">
                  <iconify-icon icon="{{ $menu['icon'] }}" class="menu-icon"></iconify-icon>
                  <span class="osc-label">{{ $menu['label'] }}</span>
                </a>
              </li>
              @endforeach
            </ul>
          </li>
          @endforeach

        </ul>
      </li>
      @endforeach

    </ul>
  </div>
</aside>
<script>
// ---- Buka/tutup simpul pohon ------------------------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.osc-sidebar');
    if (!root) { return; }

    var KUNCI = 'osc-sidebar-terbuka';

    /* Status buka/tutup disimpan supaya berpindah halaman tidak mengulang
       membuka cabang yang sama. localStorage bisa melempar di mode privat atau
       saat data situs diblokir, jadi dua-duanya dibungkus try/catch dan
       kegagalannya tidak boleh menjatuhkan sidebar. */
    function baca() {
        try {
            var mentah = window.localStorage.getItem(KUNCI);
            var isi = mentah ? JSON.parse(mentah) : null;
            return Object.prototype.toString.call(isi) === '[object Array]' ? isi : [];
        } catch (err) {
            return [];
        }
    }

    function simpan(daftar) {
        try {
            window.localStorage.setItem(KUNCI, JSON.stringify(daftar));
        } catch (err) {
            /* diabaikan: menyimpan posisi menu bukan hal yang pantas
               menggagalkan navigasi */
        }
    }

    function anakDari(node) {
        /* Hanya <ul> anak langsung: querySelector biasa akan menemukan cucu
           lebih dulu dan membuat simpul tingkat 1 menutup isi tingkat 2. */
        for (var i = 0; i < node.children.length; i++) {
            if (node.children[i].classList.contains('osc-children')) {
                return node.children[i];
            }
        }
        return null;
    }

    function setel(node, buka) {
        var baris = node.querySelector(':scope > .osc-row');
        var anak = anakDari(node);
        if (!baris || !anak) { return; }

        baris.setAttribute('aria-expanded', buka ? 'true' : 'false');
        anak.classList.toggle('osc-children--tutup', !buka);
    }

    var nodes = Array.prototype.slice.call(root.querySelectorAll('.osc-node'));

    // Terapkan status tersimpan dulu…
    var tersimpan = baca();
    nodes.forEach(function (node) {
        if (tersimpan.indexOf(node.dataset.oscKey) !== -1) {
            setel(node, true);
        }
    });

    // …lalu paksa buka cabang yang memuat halaman aktif. Urutannya penting:
    // halaman yang sedang dibuka harus selalu terlihat, apa pun yang tersimpan.
    var aktif = root.querySelector('.osc-children a.active-page');
    if (aktif) {
        var naik = aktif.parentElement;
        while (naik && naik !== root) {
            if (naik.classList && naik.classList.contains('osc-node')) {
                setel(naik, true);
            }
            naik = naik.parentElement;
        }
    }

    root.addEventListener('click', function (event) {
        var baris = event.target.closest('.osc-row');
        if (!baris || !root.contains(baris)) { return; }

        event.preventDefault();
        // Tanpa ini, klik di tingkat 2 ikut terbaca sebagai klik di tingkat 1
        // dan menutup induknya seketika setelah dibuka.
        event.stopPropagation();

        var node = baris.parentElement;
        var buka = baris.getAttribute('aria-expanded') !== 'true';
        setel(node, buka);

        var daftar = [];
        nodes.forEach(function (n) {
            var r = n.querySelector(':scope > .osc-row');
            if (r && r.getAttribute('aria-expanded') === 'true') {
                daftar.push(n.dataset.oscKey);
            }
        });
        simpan(daftar);
    });
})();
</script>
