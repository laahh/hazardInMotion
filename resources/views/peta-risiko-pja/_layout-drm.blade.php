{{--
  Layout Peta Risiko PJA (DRM 2.1) dengan konsep template /peer-pressure-edukasi/dashboard-peer.
  Navigasi SATU bilah (keputusan user 24 Nov 2026 — sebelumnya nav tab + hero-aksi berisi tautan balik yang
  sama-sama "navigasi" tumpang tindih di satu bilah, bikin orang tersesat): baris 1 = 4 bagian besar (Dashboard /
  Master Mapping / Alur Baseline / Versi Lama), baris 2 = sub-tab bagian yang sedang aktif. Halaman detail
  (pja.blade.php, master-baris-unggah.blade.php) boleh tetap punya SATU tautan "kembali" kontekstual di
  hero-aksi; itu bukan navigasi umum jadi tidak didobel dengan bilah ini.
  Section:
    hero-aksi    tombol aksi KONTEKSTUAL di kanan bilah atas (unduh, kembali ke item asal) — bukan navigasi umum
    nav          HANYA dipakai halaman Dashboard (index.blade.php) untuk tombol sub-tab sudut pandang (.dr-tabs)
    hero         breadcrumb (.pr-kicker), judul (.pr-title), subjudul (.pr-sub)
    header-aksi  filter / tombol di kanan judul halaman
    content, scripts
  Halaman arsip (versi lama) tetap memakai _layout.
--}}
@php
  $grupNavAktif = match (true) {
    request()->routeIs('peta-risiko-pja.master.*') => 'master',
    request()->routeIs('peta-risiko-pja.korelasi.*') => 'korelasi',
    request()->routeIs('peta-risiko-pja.arsip.*') => 'arsip',
    default => 'dashboard',
  };
@endphp
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
      <nav class="tp-nav" aria-label="Bagian Peta Risiko PJA">
        <a href="{{ route('peta-risiko-pja.index') }}" @if ($grupNavAktif === 'dashboard') aria-current="page" @endif>Dashboard</a>
        <a href="{{ route('peta-risiko-pja.master.index') }}" @if ($grupNavAktif === 'master') aria-current="page" @endif>Master Mapping</a>
        <a href="{{ route('peta-risiko-pja.korelasi.index') }}" @if ($grupNavAktif === 'korelasi') aria-current="page" @endif>Alur Baseline</a>
        <a href="{{ route('peta-risiko-pja.arsip.index') }}" @if ($grupNavAktif === 'arsip') aria-current="page" @endif>Versi Lama</a>
      </nav>
      <div class="tp-aksi">@yield('hero-aksi')</div>
    </div>
    @if ($grupNavAktif === 'master' || request()->routeIs('peta-risiko-pja.index'))
      <div class="tp-subbar">
        <div class="tp-subbar-in">
          @if ($grupNavAktif === 'master')
            <nav class="tp-subnav" aria-label="Master Mapping">
              <a href="{{ route('peta-risiko-pja.master.index') }}" @if (request()->routeIs('peta-risiko-pja.master.index')) aria-current="page" @endif>Tabel Master</a>
              <a href="{{ route('peta-risiko-pja.master.baris.index') }}" @if (request()->routeIs('peta-risiko-pja.master.baris.*')) aria-current="page" @endif>Master per Baris</a>
              <a href="{{ route('peta-risiko-pja.master.grup.index') }}" @if (request()->routeIs('peta-risiko-pja.master.grup.*')) aria-current="page" @endif>Grup Lokasi &amp; Aktivitas</a>
              @if (\App\Services\PetaRisiko\Drm\Master\DrmMasterDb::siap())
                <a href="{{ route('peta-risiko-pja.master.log') }}" @if (request()->routeIs('peta-risiko-pja.master.log')) aria-current="page" @endif>Riwayat</a>
              @endif
            </nav>
          @else
            @yield('nav')
          @endif
        </div>
      </div>
    @endif
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
