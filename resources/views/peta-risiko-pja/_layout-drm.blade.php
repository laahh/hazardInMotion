{{--
  Layout Peta Risiko PJA (DRM 2.1) dengan konsep template /peer-pressure-edukasi/dashboard-peer.
  Tautan "ke halaman lain" (keputusan user 24 Nov 2026 — dulu tiap halaman mengisi hero-aksi dengan daftar
  tautan BEDA-BEDA ke halaman lain, ada yang menyebut tujuan yang sama dengan label berbeda; orang jadi salah
  masuk). SEKARANG: satu set tautan TETAP, sama persis di semua halaman, didefinisikan di layout ini saja —
  halaman masing-masing tidak lagi boleh menambah tautan "ke halaman lain" sendiri di hero-aksi.
  Section:
    hero-aksi    HANYA untuk aksi KONTEKSTUAL halaman itu sendiri (unduh, kembali ke item asal) — bukan tautan
                 ke halaman lain; tautan ke halaman lain sudah otomatis dari layout ini
    nav          menu di bilah atas (halaman indeks mengisi dengan tombol tab; bawaan: tautan ke tab indeks)
    hero         breadcrumb (.pr-kicker), judul (.pr-title), subjudul (.pr-sub)
    header-aksi  filter / tombol di kanan judul halaman
    content, scripts
  Halaman arsip (versi lama) tetap memakai _layout.
--}}
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title') | BeSentry</title>
  <link rel="icon" href="{{ asset('build/images/favicon.ico') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  @include('peta-risiko-pja._style')
  @include('peta-risiko-pja.drm._tema')
  @stack('styles')
</head>
<body class="pr tp">
  <header class="tp-bar">
    <div class="tp-bar-in">
      <a class="tp-brand" href="{{ route('peta-risiko-pja.index') }}">
        <img src="{{ asset('build/images/logo-removebg.png') }}" alt="">
        <div><b>PT. Berau Coal</b><span></span></div>
      </a>
      <div class="tp-sep" aria-hidden="true"></div>
      @hasSection('nav')
        @yield('nav')
      @else
        <nav class="tp-nav" aria-label="Menu Peta Risiko PJA">
          @foreach (['site' => 'Overview', 'grup' => 'Per Group Lokasi', 'proses' => 'Per Kelompok Aktivitas', 'pja' => 'Semua PJA', 'bpja' => 'Beranda PJA'] as $tab => $label)
            <a href="{{ route('peta-risiko-pja.index', ['tab' => $tab]) }}">{{ $label }}</a>
          @endforeach
        </nav>
      @endif
      <div class="tp-aksi">
        @yield('hero-aksi')
        <nav class="tp-aksi-nav" aria-label="Ke halaman lain">
          <a class="pr-back" href="{{ route('peta-risiko-pja.master.index') }}" @if (request()->routeIs('peta-risiko-pja.master.index')) aria-current="page" @endif><span class="material-symbols-outlined" style="font-size:18px">table_view</span> Tabel Master</a>
          <a class="pr-back" href="{{ route('peta-risiko-pja.master.baris.index') }}" @if (request()->routeIs('peta-risiko-pja.master.baris.*')) aria-current="page" @endif><span class="material-symbols-outlined" style="font-size:18px">view_list</span> Master per Baris</a>
          <a class="pr-back" href="{{ route('peta-risiko-pja.master.grup.index') }}" @if (request()->routeIs('peta-risiko-pja.master.grup.*')) aria-current="page" @endif><span class="material-symbols-outlined" style="font-size:18px">account_tree</span> Grup Lokasi &amp; Aktivitas</a>
          <a class="pr-back" href="{{ route('peta-risiko-pja.master.pic.index') }}" @if (request()->routeIs('peta-risiko-pja.master.pic.*')) aria-current="page" @endif><span class="material-symbols-outlined" style="font-size:18px">badge</span> Lokasi &amp; PIC</a>
          <a class="pr-back" href="{{ route('peta-risiko-pja.korelasi.index') }}" @if (request()->routeIs('peta-risiko-pja.korelasi.*')) aria-current="page" @endif><span class="material-symbols-outlined" style="font-size:18px">alt_route</span> Alur Baseline</a>
          {{-- Riwayat Pengecualian disembunyikan dari nav (keputusan user 27 Nov 2026) — halaman & rute tetap ada di /peta-risiko-pja/baseline-pengecualian. --}}
        </nav>
      </div>
    </div>
  </header>
  <main class="pr-main tp-main">
    <div class="tp-head">
      <div class="tp-head-teks">@yield('hero')</div>
      <div class="tp-head-aksi">@yield('header-aksi')</div>
    </div>
    @yield('content')
  </main>
  @include('peta-risiko-pja._status')
  @yield('scripts')
</body>
</html>
