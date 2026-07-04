<!DOCTYPE html>
<html class="light" lang="id">
<head>
   <meta charset="utf-8"/>
   <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
   <title>@yield('title', 'Dashboard') — Berau Coal</title>
   <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
   <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
   <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
   <script id="tailwind-config">
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              "crm-primary": "#7366FF",
              "crm-primary-light": "#ECE9FF",
              "crm-bg": "#F4F7F9",
              "crm-text": "#2F2F3A",
              "crm-muted": "#848488",
              "crm-border": "#E6E9EB",
            },
            fontFamily: { "body": ["Poppins"], "headline": ["Poppins"] },
          },
        },
      }
   </script>
   <style>
      .material-symbols-outlined {
         font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
         vertical-align: middle;
      }
      .hide-scrollbar::-webkit-scrollbar { display: none; }
      .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
   </style>
   @stack('head')
</head>
<body class="bg-crm-bg font-body text-crm-text min-h-screen">
@php
   $navActive = $navActive ?? 'dashboard';
   $navItems = $navItems ?? [];
   $userName = auth()->user()->name ?? 'User';
   $userInitials = collect(explode(' ', $userName))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
@endphp

<div class="flex min-h-screen">
   {{-- Sidebar --}}
   <aside class="crm-sidebar hidden lg:flex lg:flex-col lg:w-[260px] lg:shrink-0 bg-white border-r border-crm-border fixed inset-y-0 left-0 z-40">
      <div class="px-5 pt-6 pb-4">
         <a href="{{ route('monitoring-safety-engineering.dashboard') }}" class="flex items-center gap-2.5">
            <span class="font-headline font-bold text-xl text-crm-primary tracking-tight">Berau<span class="text-crm-text"> Coal</span></span>
         </a>
      </div>

      <div class="px-5 pb-5">
         <div class="flex items-center gap-3 p-2.5 rounded-xl bg-crm-bg">
            <div class="crm-avatar crm-avatar--lg">{{ $userInitials }}</div>
            <div class="min-w-0">
               <p class="text-sm font-semibold text-crm-text truncate">{{ $userName }}</p>
               <p class="text-[11px] text-crm-muted">Super Admin</p>
            </div>
         </div>
      </div>

      <nav class="flex-1 overflow-y-auto hide-scrollbar px-3 pb-4">
         <p class="px-3 mb-2 text-[10px] font-bold uppercase tracking-widest text-crm-muted">Main Menu</p>
         <ul class="space-y-0.5">
            @foreach($navItems as $item)
            @php
               $isActive = ($navActive ?? '') === ($item['key'] ?? '');
               $href = $item['url'] ?? (isset($item['route']) ? route($item['route'], $item['params'] ?? []) : '#');
               $icons = [
                  'dashboard' => 'dashboard',
                  'outside-commitment' => 'assignment',
                  'pmr-evaluation' => 'fact_check',
                  'company-overview' => 'apartment',
                  'effectiveness' => 'verified',
                  'upload' => 'cloud_upload',
                  'data-update' => 'table_edit',
               ];
               $icon = $icons[$item['key'] ?? ''] ?? 'circle';
            @endphp
            <li>
               <a href="{{ $href }}" class="crm-nav-link {{ $isActive ? 'crm-nav-link--active' : '' }}">
                  <span class="material-symbols-outlined text-[20px]">{{ $icon }}</span>
                  {{ $item['label'] }}
               </a>
            </li>
            @endforeach
         </ul>

         <p class="px-3 mt-6 mb-2 text-[10px] font-bold uppercase tracking-widest text-crm-muted">Components</p>
         <ul class="space-y-0.5">
            <li><a href="#" class="crm-nav-link"><span class="material-symbols-outlined text-[20px]">widgets</span>Features <span class="material-symbols-outlined text-sm ml-auto">expand_more</span></a></li>
            <li><a href="#" class="crm-nav-link"><span class="material-symbols-outlined text-[20px]">table_chart</span>Forms, Tables &amp; Charts <span class="material-symbols-outlined text-sm ml-auto">expand_more</span></a></li>
            <li><a href="#" class="crm-nav-link"><span class="material-symbols-outlined text-[20px]">apps</span>Apps &amp; Widgets <span class="material-symbols-outlined text-sm ml-auto">expand_more</span></a></li>
         </ul>
      </nav>

      <div class="p-4 mt-auto">
         <div class="crm-promo-card">
            <p class="text-xs font-semibold text-crm-text leading-snug">Monitoring Safety Engineering</p>
            <p class="text-[10px] text-crm-muted mt-1">Best Safety Dashboard here</p>
            <a href="{{ route('monitoring-safety-engineering.company-overview') }}" class="crm-promo-btn mt-3">View Full Report</a>
         </div>
      </div>
   </aside>

   {{-- Main --}}
   <div class="flex-1 lg:ml-[260px] flex flex-col min-h-screen">
      {{-- Top Navbar --}}
      <header class="crm-topbar sticky top-0 z-30 bg-white border-b border-crm-border px-4 md:px-6 py-3">
         <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-1 min-w-0">
               <button type="button" class="crm-icon-btn lg:hidden" aria-label="Menu">
                  <span class="material-symbols-outlined">menu</span>
               </button>
               <div class="crm-search-wrap flex-1 max-w-md hidden sm:block">
                  <span class="material-symbols-outlined crm-search-icon">search</span>
                  <input type="text" class="crm-search-input" placeholder="Search anything here..." readonly>
               </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
               <button type="button" class="crm-icon-btn"><span class="material-symbols-outlined text-[20px]">notifications</span></button>
               <button type="button" class="crm-icon-btn hidden sm:inline-flex"><span class="material-symbols-outlined text-[20px]">calendar_month</span></button>
               <button type="button" class="crm-icon-btn hidden md:inline-flex"><span class="material-symbols-outlined text-[20px]">folder</span></button>
               <button type="button" class="crm-icon-btn hidden md:inline-flex"><span class="material-symbols-outlined text-[20px]">shopping_cart</span></button>
               <button type="button" class="crm-icon-btn hidden md:inline-flex"><span class="material-symbols-outlined text-[20px]">chat</span></button>
               <button type="button" class="crm-icon-btn hidden lg:inline-flex"><span class="material-symbols-outlined text-[20px]">flag</span></button>
               <button type="button" class="crm-icon-btn hidden xl:inline-flex"><span class="material-symbols-outlined text-[20px]">fullscreen</span></button>
               <div class="flex items-center gap-2 pl-2 ml-1 border-l border-crm-border">
                  <div class="crm-avatar">{{ $userInitials }}</div>
                  <div class="hidden sm:block text-left">
                     <p class="text-xs font-semibold text-crm-text leading-tight">{{ $userName }}</p>
                     <p class="text-[10px] text-crm-muted">Berau Coal</p>
                  </div>
                  <span class="material-symbols-outlined text-crm-muted text-lg hidden sm:inline">expand_more</span>
               </div>
            </div>
         </div>
      </header>

      <main class="flex-1 p-4 md:p-6">
         @if(session('success'))
         <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 mb-5" role="status">{{ session('success') }}</div>
         @endif
         @if(session('error'))
         <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900 mb-5" role="alert">{{ session('error') }}</div>
         @endif
         @yield('content')
      </main>
   </div>
</div>
@stack('scripts')
</body>
</html>
