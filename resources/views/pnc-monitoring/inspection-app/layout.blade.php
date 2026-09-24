<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>@yield('title', 'Inspeksi Alat') | PNC Monitoring</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            "surface-container": "#eaefe8",
            "error-container": "#ffdad6",
            "on-secondary": "#ffffff",
            "surface-bright": "#f5fbf3",
            "outline": "#6e7a70",
            "error": "#ba1a1a",
            "surface": "#f5fbf3",
            "on-primary-container": "#f6fff5",
            "primary-container": "#058651",
            "inverse-on-surface": "#edf2eb",
            "outline-variant": "#bdcabe",
            "secondary-container": "#e1e3e4",
            "on-surface": "#171d19",
            "surface-container-lowest": "#ffffff",
            "on-error": "#ffffff",
            "tertiary-container": "#ba545f",
            "surface-container-high": "#e4eae2",
            "surface-container-highest": "#dee4dd",
            "on-error-container": "#93000a",
            "on-tertiary": "#ffffff",
            "surface-dim": "#d6dcd4",
            "on-surface-variant": "#3e4941",
            "secondary": "#5c5f60",
            "tertiary": "#9b3c47",
            "primary": "#006a3f",
            "inverse-primary": "#72db9e",
            "on-tertiary-container": "#fffbff",
            "on-primary": "#ffffff",
            "on-background": "#171d19",
            "secondary-fixed": "#e1e3e4",
            "surface-variant": "#dee4dd",
            "background": "#f5fbf3",
            "surface-container-low": "#f0f5ee",
            "on-secondary-container": "#626566",
          },
          fontFamily: {
            headline: ["Plus Jakarta Sans"],
            body: ["Inter"],
          },
        },
      },
    }
  </script>
  <style>
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    body { font-family: 'Inter', sans-serif; }
    h1, h2, h3 { font-family: 'Plus Jakarta Sans', sans-serif; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    body { min-height: max(884px, 100dvh); }
  </style>
  @yield('head')
</head>
<body class="bg-surface text-on-surface antialiased max-w-[480px] mx-auto min-h-screen relative pb-32">

  <header class="fixed top-0 left-0 w-full z-50 flex justify-between items-center px-5 py-4 bg-emerald-50/80 backdrop-blur-xl max-w-[480px] mx-auto">
    <div class="flex items-center gap-2">
      @hasSection('back-url')
        <a href="@yield('back-url')" class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-emerald-100/50 transition-colors active:scale-95 duration-150 -ml-2">
          <span class="material-symbols-outlined text-emerald-700">arrow_back</span>
        </a>
      @else
        <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">construction</span>
      @endif
      <div class="flex flex-col leading-tight">
        <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">PNC Monitoring</span>
        <span class="text-base font-extrabold text-on-surface">@yield('header-title', 'Inspeksi Alat')</span>
      </div>
    </div>
    <a href="{{ route('pnc-monitoring.dashboard.inventory') }}" class="w-10 h-10 flex items-center justify-center rounded-full bg-surface-container-highest/50 hover:bg-emerald-100/50 transition-colors" title="Kembali ke Admin">
      <span class="material-symbols-outlined text-emerald-700">dashboard</span>
    </a>
  </header>

  <main class="pt-24">
    @yield('content')
  </main>

  <nav class="fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-4 pb-6 pt-3 bg-white/80 backdrop-blur-xl rounded-t-3xl shadow-[0_-8px_24px_rgba(23,29,25,0.06)] max-w-[480px] mx-auto">
    @php
      $navItems = [
        ['route' => 'pnc-monitoring.inventory-inspection.home', 'icon' => 'home_max', 'label' => 'Beranda'],
        ['route' => 'pnc-monitoring.inventory-inspection.scan', 'icon' => 'qr_code_scanner', 'label' => 'Scan'],
        ['route' => 'pnc-monitoring.inventory-inspection.history', 'icon' => 'history', 'label' => 'Riwayat'],
      ];
    @endphp
    @foreach ($navItems as $item)
      @php $isActive = request()->routeIs($item['route']); @endphp
      <a href="{{ route($item['route']) }}" class="flex flex-col items-center justify-center rounded-2xl px-5 py-2.5 transition-all active:scale-90 duration-200 ease-out {{ $isActive ? 'bg-emerald-100/60 text-emerald-800' : 'text-zinc-400 hover:text-emerald-500' }}">
        <span class="material-symbols-outlined" @if($isActive) style="font-variation-settings: 'FILL' 1;" @endif>{{ $item['icon'] }}</span>
        <span class="font-medium text-[11px] tracking-wide mt-1">{{ $item['label'] }}</span>
      </a>
    @endforeach
  </nav>

  @yield('scripts')
</body>
</html>
