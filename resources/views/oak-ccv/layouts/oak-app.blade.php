<!DOCTYPE html>
<html class="light" lang="id">
   <head>
      <meta charset="utf-8"/>
      <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
      <title>@yield('title', 'OAK CCV Evaluation') — PT.Berau Coal</title>
      <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
      <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&amp;family=Inter:wght@300;400;500;600&amp;display=swap" rel="stylesheet"/>
      <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
      <script id="tailwind-config">
         tailwind.config = {
           darkMode: "class",
           theme: {
             extend: {
               colors: {
                 "outline": "#747779",
                 "primary-container": "#859aff",
                 "on-error-container": "#510017",
                 "on-error": "#ffefef",
                 "secondary-fixed": "#e4c6ff",
                 "on-tertiary-fixed-variant": "#00377b",
                 "primary-fixed-dim": "#748cf9",
                 "surface-container-low": "#eef1f3",
                 "secondary-dim": "#653b91",
                 "secondary-fixed-dim": "#dab4ff",
                 "on-primary-container": "#001867",
                 "error": "#b41340",
                 "error-dim": "#a70138",
                 "on-tertiary-fixed": "#00163b",
                 "secondary": "#72479e",
                 "error-container": "#f74b6d",
                 "surface-container-high": "#dfe3e6",
                 "on-secondary-fixed": "#481c73",
                 "surface-tint": "#3952bc",
                 "on-secondary-container": "#5d3288",
                 "surface-container": "#e5e9eb",
                 "inverse-primary": "#7991ff",
                 "on-background": "#2c2f31",
                 "surface": "#f5f7f9",
                 "surface-container-lowest": "#ffffff",
                 "on-primary-fixed-variant": "#00207e",
                 "inverse-on-surface": "#9a9d9f",
                 "on-secondary": "#fbefff",
                 "tertiary-fixed": "#8ab0ff",
                 "tertiary-fixed-dim": "#73a2ff",
                 "surface-container-highest": "#d9dde0",
                 "surface-variant": "#d9dde0",
                 "tertiary": "#0057bd",
                 "on-primary-fixed": "#000000",
                 "primary": "#3952bc",
                 "background": "#f5f7f9",
                 "on-surface": "#2c2f31",
                 "surface-bright": "#f5f7f9",
                 "secondary-container": "#e4c6ff",
                 "on-tertiary-container": "#002e6a",
                 "on-secondary-fixed-variant": "#663c92",
                 "inverse-surface": "#0b0f10",
                 "surface-dim": "#d0d5d8",
                 "outline-variant": "#abadaf",
                 "on-tertiary": "#f0f2ff",
                 "primary-dim": "#2b45af",
                 "tertiary-dim": "#004ca6",
                 "primary-fixed": "#859aff",
                 "tertiary-container": "#8ab0ff",
                 "on-primary": "#f2f1ff",
                 "on-surface-variant": "#595c5e"
               },
               fontFamily: {
                 "headline": ["Poppins"],
                 "body": ["Poppins"],
                 "label": ["Poppins"]
               },
               borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1rem", "full": "9999px"},
             },
           },
         }
      </script>
      <style>
         .material-symbols-outlined {
         font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
         vertical-align: middle;
         }
         .anchored-card {
         box-shadow: 0 4px 0px 0px rgba(0, 0, 0, 0.05), 0 12px 24px -4px rgba(0, 0, 0, 0.15);
         border: 1px solid rgba(0, 0, 0, 0.08);
         transition: transform 0.2s ease, box-shadow 0.2s ease;
         }
         .anchored-card:hover {
         transform: translateY(-2px);
         box-shadow: 0 6px 0px 0px rgba(0, 0, 0, 0.05), 0 20px 32px -8px rgba(0, 0, 0, 0.2);
         }
      </style>
      @stack('head')
   </head>
   <body class="bg-[#f0f2f5] font-body text-on-surface min-h-screen flex flex-col">
      @php $navActive = $navActive ?? 'overview'; @endphp
      <header class="w-full sticky top-0 bg-[#ffffff] border-b border-[#dfe3e6] z-50 shadow-sm">
         <div class="mx-auto px-8 py-4 flex justify-between items-center">
            <div class="flex items-center gap-10">
               <div class="flex flex-col">
                  <h1 class="font-headline font-bold text-[#3952bc] text-xl tracking-tighter leading-tight">PT.Berau Coal</h1>
                  <p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest">OAK CCV Evaluation</p>
               </div>
               <div class="h-8 w-px bg-[#dfe3e6] hidden lg:block"></div>
               <nav class="hidden md:flex gap-8">
                  <a class="{{ $navActive === 'overview' ? 'text-[#3952bc] border-b-2 border-[#3952bc] pb-1 font-bold text-sm tracking-tight' : 'text-[#595c5e] hover:text-[#3952bc] font-semibold text-sm tracking-tight transition-colors' }}" href="{{ route('oak-ccv.dashboard') }}">Overview</a>
               </nav>
            </div>
            <div class="flex items-center gap-3">
               <div class="flex items-center gap-2 p-1.5 pr-4 bg-white rounded-full border border-outline-variant/30 shadow-sm">
                  <span class="material-symbols-outlined text-3xl text-primary" data-icon="account_circle">account_circle</span>
                  <div class="text-left">
                     <p class="text-[10px] font-bold text-primary uppercase leading-none">Safety Admin</p>
                     <p class="text-[9px] text-on-surface-variant font-medium">Site Manager</p>
                  </div>
               </div>
            </div>
         </div>
      </header>
      <main class="flex-grow w-full mx-auto p-8 space-y-8">
         @yield('content')
      </main>
   </body>
</html>
