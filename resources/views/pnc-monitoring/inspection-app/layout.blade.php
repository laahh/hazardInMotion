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
            primary: "#0E8A4F",
            "primary-dark": "#0A6B3E",
            "primary-light": "#E3F5EA",
            surface: "#EFFAF4",
            "surface-container-lowest": "#FFFFFF",
            "surface-container-low": "#F4FBF7",
            "surface-container": "#EAF6EF",
            "surface-container-high": "#DFF0E6",
            "surface-container-highest": "#D2E8DA",
            "on-surface": "#132A1D",
            "on-surface-variant": "#5C6F63",
            "outline-variant": "#D3E8DA",
            tertiary: "#B3441F",
            "tertiary-light": "#FDECE6",
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
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24; }
    body { font-family: 'Inter', sans-serif; }
    h1, h2, h3 { font-family: 'Plus Jakarta Sans', sans-serif; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    body { min-height: max(884px, 100dvh); }
  </style>
  @yield('head')
</head>
<body class="bg-surface text-on-surface antialiased max-w-[480px] mx-auto min-h-screen relative pb-28">

  <header class="sticky top-0 left-0 w-full z-50 flex justify-between items-center px-5 py-4 bg-surface max-w-[480px] mx-auto border-b border-outline-variant/60">
    <div class="flex items-center gap-3">
      @hasSection('back-url')
        <a href="@yield('back-url')" class="flex items-center justify-center w-10 h-10 rounded-full bg-surface-container-lowest shadow-sm hover:bg-surface-container transition-colors active:scale-95 duration-150">
          <span class="material-symbols-outlined text-on-surface">arrow_back</span>
        </a>
      @else
        <img src="https://besentry-dev.beraucoal.co.id/build/images/logo-removebg.png" alt="Logo" class="w-9 h-9 object-contain">
      @endif
      <div class="flex flex-col leading-tight">
        <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">PNC Monitoring</span>
        <span class="text-lg font-extrabold text-on-surface -mt-0.5">@yield('header-title', 'Inspeksi Alat')</span>
      </div>
    </div>

    @hasSection('back-url')
      <a href="{{ route('pnc-monitoring.dashboard.inventory') }}" class="w-10 h-10 flex items-center justify-center rounded-xl bg-surface-container-lowest shadow-sm hover:bg-surface-container transition-colors" title="Menu Admin">
        <span class="material-symbols-outlined text-on-surface text-[20px]">apps</span>
      </a>
    @else
      <button type="button" class="relative w-10 h-10 flex items-center justify-center rounded-full bg-surface-container-lowest shadow-sm hover:bg-surface-container transition-colors">
        <span class="material-symbols-outlined text-on-surface">notifications</span>
        <span class="absolute top-2 right-2 w-2 h-2 bg-tertiary rounded-full ring-2 ring-surface-container-lowest"></span>
      </button>
    @endif
  </header>

  <main>
    @yield('content')
  </main>

  <nav class="fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-4 pb-5 pt-3 bg-white border-t border-outline-variant/60 max-w-[480px] mx-auto">
    @php
      $navItems = [
        ['route' => 'pnc-monitoring.inventory-inspection.home', 'icon' => 'home', 'label' => 'Beranda'],
        ['route' => 'pnc-monitoring.inventory-inspection.scan', 'icon' => 'qr_code_scanner', 'label' => 'Scan'],
        ['route' => 'pnc-monitoring.inventory-inspection.history', 'icon' => 'history', 'label' => 'Riwayat'],
      ];
    @endphp
    @foreach ($navItems as $item)
      @php $isActive = request()->routeIs($item['route']); @endphp
      <a href="{{ route($item['route']) }}" class="flex flex-col items-center justify-center gap-0.5 rounded-2xl px-6 py-2 transition-all active:scale-90 duration-200 ease-out {{ $isActive ? 'bg-primary-light text-primary' : 'text-on-surface-variant/70' }}">
        <span class="material-symbols-outlined text-[22px]" @if($isActive) style="font-variation-settings: 'FILL' 1;" @endif>{{ $item['icon'] }}</span>
        <span class="font-semibold text-[11px] tracking-wide">{{ $item['label'] }}</span>
      </a>
    @endforeach
  </nav>

  @yield('scripts')
</body>
</html>
