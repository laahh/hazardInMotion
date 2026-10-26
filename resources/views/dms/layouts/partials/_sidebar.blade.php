{{--
  Sidebar DMS — menu dikelompokkan jadi pohon satu tingkat (Kesesuaian Roster,
  Fatigue Monitoring). Tampilan dan perilakunya mengikuti sidebar OHS Score Card
  (ohs-score-card/partials/_sidebar): penanda ⊞/⊟, garis pohon, status buka/tutup
  diingat di localStorage. Kelas .dropdown bawaan tema sengaja tidak dipakai —
  lihat catatan di sidebar OHS Score Card.

  Aktif-tidaknya menu dihitung dari routeIs (bukan perbandingan URL) karena
  beberapa menu mencakup banyak route, mis. Master Roster Banned yang harus
  mengecualikan halaman treatment.
--}}
@php
    $groups = [
        [
            'key' => 'kesesuaian-roster',
            'label' => 'Kesesuaian Roster',
            'icon' => 'solar:calendar-mark-outline',
            'items' => [
                [
                    'label' => 'Ringkasan Roster',
                    'icon' => 'solar:chart-square-outline',
                    'url' => route('dms.roster-compliance-static.overview'),
                    'active' => request()->routeIs('dms.roster-compliance-static.overview'),
                ],
                [
                    'label' => 'Master Roster Banned',
                    'icon' => 'solar:forbidden-circle-outline',
                    'url' => route('pra-operasi.roster-banned.index'),
                    'active' => request()->routeIs('pra-operasi.roster-banned.*') && ! request()->routeIs('pra-operasi.roster-banned.treatment.*'),
                ],
                [
                    'label' => 'Review Bukti Treatment',
                    'icon' => 'solar:shield-check-outline',
                    'url' => route('pra-operasi.roster-banned.treatment.index'),
                    'active' => request()->routeIs('pra-operasi.roster-banned.treatment.*'),
                ],
            ],
        ],
        [
            'key' => 'master-roster',
            'label' => 'Master Roster',
            'icon' => 'solar:calendar-add-outline',
            'items' => [
                [
                    'label' => 'Upload Roster',
                    'icon' => 'solar:upload-outline',
                    'url' => route('dms.master-roster.upload'),
                    'active' => request()->routeIs('dms.master-roster.upload'),
                ],
                [
                    'label' => 'Approval Roster',
                    'icon' => 'solar:checklist-minimalistic-outline',
                    'url' => route('dms.master-roster.approval'),
                    'active' => request()->routeIs('dms.master-roster.approval') || request()->routeIs('dms.master-roster.show'),
                ],
            ],
        ],
        [
            'key' => 'fatigue-monitoring',
            'label' => 'Fatigue Monitoring',
            'icon' => 'solar:sleeping-outline',
            'items' => [
                [
                    'label' => 'Pra Operasi',
                    'icon' => 'solar:clipboard-check-outline',
                    'url' => route('pra-operasi.dashboard'),
                    'active' => request()->routeIs('pra-operasi.dashboard') || request()->routeIs('pra-operasi.operator-profile'),
                ],
                [
                    'label' => 'Saat Operasi',
                    'icon' => 'solar:wheel-angle-outline',
                    'url' => route('pra-operasi.saat-operasi'),
                    'active' => request()->routeIs('pra-operasi.saat-operasi'),
                ],
                [
                    'label' => 'Evaluasi Harian',
                    'icon' => 'solar:document-text-outline',
                    'url' => route('pra-operasi.evaluasi-harian'),
                    'active' => request()->routeIs('pra-operasi.evaluasi-harian'),
                ],
                [
                    'label' => 'Monitoring Alert DMS',
                    'icon' => 'solar:shield-warning-outline',
                    'url' => route('pra-operasi.dms-monitoring'),
                    'active' => request()->routeIs('pra-operasi.dms-monitoring*'),
                ],
            ],
        ],
    ];

    // Kelompok yang memuat halaman aktif dibuka sejak render, tidak menunggu JS.
    foreach ($groups as $i => $group) {
        $groups[$i]['terbuka'] = collect($group['items'])->contains('active', true);
    }
@endphp
<style>
  /* Semua aturan discope ke .dms-sidebar karena style.css dipakai bareng modul lain. */
  .dms-sidebar .sidebar-menu li a { align-items: center; }
  .dms-sidebar .sidebar-menu li a .menu-icon { flex-shrink: 0; }
  .dms-sidebar .sidebar-menu li a .dms-label {
    flex: 1 1 auto;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* Penanda ⊞ / ⊟ digambar dengan CSS supaya tidak berkedip menunggu iconify. */
  .dms-sidebar .dms-mark {
    position: relative;
    flex: 0 0 auto;
    width: 15px;
    height: 15px;
    margin-inline-end: 8px;
    border: 1.5px solid currentColor;
    border-radius: 50%;
    opacity: .65;
  }
  .dms-sidebar .dms-mark::before,
  .dms-sidebar .dms-mark::after {
    content: '';
    position: absolute;
    inset-inline-start: 50%;
    inset-block-start: 50%;
    transform: translate(-50%, -50%);
    background: currentColor;
  }
  .dms-sidebar .dms-mark::before { width: 7px; height: 1.5px; }
  .dms-sidebar .dms-mark::after  { width: 1.5px; height: 7px; }
  .dms-sidebar .dms-row[aria-expanded="true"] .dms-mark::after { display: none; }
  .dms-sidebar .dms-row:hover .dms-mark { opacity: 1; }
  .dms-sidebar .dms-row { cursor: pointer; }

  /* Garis pohon: tiap anak menggambar ruas tegaknya sendiri supaya anak
     terakhir otomatis membentuk siku "└". */
  .dms-sidebar .dms-children {
    position: relative;
    margin: 2px 0;
    padding-inline-start: 28px;
    list-style: none;
  }
  .dms-sidebar .dms-children > li { position: relative; }
  .dms-sidebar .dms-children > li::before {
    content: '';
    position: absolute;
    inset-inline-start: -15px;
    inset-block: 0;
    width: 1px;
    background: currentColor;
    opacity: .22;
  }
  .dms-sidebar .dms-children > li:last-child::before {
    inset-block-end: auto;
    height: 19px;
  }
  .dms-sidebar .dms-children > li::after {
    content: '';
    position: absolute;
    inset-inline-start: -15px;
    inset-block-start: 19px;
    width: 11px;
    height: 1px;
    background: currentColor;
    opacity: .22;
  }

  .dms-sidebar .dms-children--tutup { display: none; }

  /* Sidebar terlipat (hanya ikon): pohon dan penandanya disembunyikan. */
  .sidebar.active.dms-sidebar .dms-children,
  .sidebar.active.dms-sidebar .dms-mark {
    display: none !important;
  }
</style>
<aside class="sidebar dms-sidebar">
  <button type="button" class="sidebar-close-btn">
    <iconify-icon icon="radix-icons:cross-2"></iconify-icon>
  </button>
  <div>
    <a href="{{ route('dms.index') }}" class="sidebar-logo" style="justify-content: center;">
      <img src="https://besentry-dev.beraucoal.co.id/build/images/logo-removebg.png" alt="site logo" class="light-logo">
      <img src="https://besentry-dev.beraucoal.co.id/build/images/logo-removebg.png" alt="site logo" class="dark-logo">
      <img src="https://besentry-dev.beraucoal.co.id/build/images/logo-removebg.png" alt="site logo" class="logo-icon">
    </a>
  </div>
  <div class="sidebar-menu-area">
    <ul class="sidebar-menu" id="sidebar-menu">
      <li class="sidebar-menu-group-title">Sistem Manajemen Roster</li>

      @foreach ($groups as $group)
      <li class="dms-node" data-dms-key="{{ $group['key'] }}">
        <a href="javascript:void(0)" class="dms-row" role="button"
           aria-expanded="{{ $group['terbuka'] ? 'true' : 'false' }}" title="{{ $group['label'] }}">
          <span class="dms-mark" aria-hidden="true"></span>
          <iconify-icon icon="{{ $group['icon'] }}" class="menu-icon"></iconify-icon>
          <span class="dms-label">{{ $group['label'] }}</span>
        </a>

        <ul class="dms-children {{ $group['terbuka'] ? '' : 'dms-children--tutup' }}">
          @foreach ($group['items'] as $item)
          <li>
            <a href="{{ $item['url'] }}" title="{{ $item['label'] }}" class="{{ $item['active'] ? 'active-page' : '' }}">
              <iconify-icon icon="{{ $item['icon'] }}" class="menu-icon"></iconify-icon>
              <span class="dms-label">{{ $item['label'] }}</span>
            </a>
          </li>
          @endforeach
        </ul>
      </li>
      @endforeach

      <li>
        <a href="{{ route('dms.fatigue-baseline-static') }}" class="{{ request()->routeIs('dms.fatigue-baseline-static') ? 'active-page' : '' }}">
          <iconify-icon icon="solar:chart-2-outline" class="menu-icon"></iconify-icon>
          <span class="dms-label">Fatigue Baseline</span>
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
<script>
// ---- Buka/tutup kelompok menu -----------------------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.dms-sidebar');
    if (!root) { return; }

    var KUNCI = 'dms-sidebar-terbuka';

    // localStorage bisa melempar di mode privat; kegagalannya tidak boleh
    // menjatuhkan sidebar.
    function baca() {
        try {
            var isi = JSON.parse(window.localStorage.getItem(KUNCI) || 'null');
            return Array.isArray(isi) ? isi : [];
        } catch (err) {
            return [];
        }
    }

    function simpan(daftar) {
        try {
            window.localStorage.setItem(KUNCI, JSON.stringify(daftar));
        } catch (err) {
            /* diabaikan: posisi menu bukan alasan menggagalkan navigasi */
        }
    }

    function setel(node, buka) {
        var baris = node.querySelector(':scope > .dms-row');
        var anak = node.querySelector(':scope > .dms-children');
        if (!baris || !anak) { return; }

        baris.setAttribute('aria-expanded', buka ? 'true' : 'false');
        anak.classList.toggle('dms-children--tutup', !buka);
    }

    var nodes = Array.prototype.slice.call(root.querySelectorAll('.dms-node'));

    // Status tersimpan dulu, lalu kelompok halaman aktif selalu dibuka.
    var tersimpan = baca();
    nodes.forEach(function (node) {
        if (tersimpan.indexOf(node.dataset.dmsKey) !== -1) {
            setel(node, true);
        }
    });

    var aktif = root.querySelector('.dms-children a.active-page');
    if (aktif) {
        setel(aktif.closest('.dms-node'), true);
    }

    root.addEventListener('click', function (event) {
        var baris = event.target.closest('.dms-row');
        if (!baris || !root.contains(baris)) { return; }

        event.preventDefault();

        setel(baris.parentElement, baris.getAttribute('aria-expanded') !== 'true');

        simpan(nodes
            .filter(function (n) {
                var r = n.querySelector(':scope > .dms-row');
                return r && r.getAttribute('aria-expanded') === 'true';
            })
            .map(function (n) { return n.dataset.dmsKey; }));
    });
})();
</script>
