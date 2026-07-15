{{-- Partial: pass $title, $subtitle, $breadcrumb via @include --}}
<section class="pb-5 border-b border-outline-variant/40 mb-6">
   <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-4">
      <div class="min-w-0">
         @if(!empty($breadcrumb))
         <nav class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.08em] text-on-surface-variant mb-2.5">
            <span>HSECM</span>
            <span class="material-symbols-outlined text-[13px] opacity-60">chevron_right</span>
            <span class="text-primary">{{ $breadcrumb }}</span>
         </nav>
         @endif
         <h1 class="font-headline font-extrabold text-3xl text-on-background tracking-tight">{{ $title }}</h1>
         @if(!empty($subtitle))
         <p class="mt-1.5 text-sm text-on-surface-variant max-w-3xl">{{ $subtitle }}</p>
         @endif
      </div>
      @isset($actions)
      <div class="flex flex-wrap items-center gap-2 shrink-0">
         {{ $actions }}
      </div>
      @endisset
   </div>
</section>
