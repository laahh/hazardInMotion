@extends('AutoBanned.layouts.app')

@section('title', 'Inputasi Auto Banned')

@section('page-header')
   @include('AutoBanned.partials.page-header', [
      'breadcrumbCurrent' => 'Inputasi',
      'pageTitle' => 'Inputasi Data',
      'pageSubtitle' => 'Pencatatan LV dan orang masuk/keluar area',
   ])
@endsection

@section('content')
   <div class="mb-4">
      <a href="{{ route('auto-banned.index') }}" class="inline-flex items-center gap-1 text-sm font-bold text-primary hover:underline">
         <span class="material-symbols-outlined text-base">arrow_back</span>
         Kembali ke Overview
      </a>
   </div>

   <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 max-w-6xl">
      <button type="button" data-ab-open-inputasi="lv" class="group flex items-center gap-4 rounded-2xl border border-outline-variant/15 bg-white p-6 text-left shadow-sm transition-all duration-300 hover:border-primary/20 hover:shadow-md">
         <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-transform duration-300 group-hover:scale-105">
            <span class="material-symbols-outlined text-2xl">local_shipping</span>
         </span>
         <div class="min-w-0 flex-1">
            <p class="font-headline font-bold text-base text-on-background">Inputasi LV</p>
            <p class="mt-1 text-xs text-on-surface-variant">Catat unit LV masuk/keluar — shift & control room otomatis</p>
         </div>
         <span class="material-symbols-outlined text-on-surface-variant/40 transition-transform duration-300 group-hover:translate-x-0.5">arrow_forward</span>
      </button>

      <button type="button" data-ab-open-inputasi="orang" class="group flex items-center gap-4 rounded-2xl border border-outline-variant/15 bg-white p-6 text-left shadow-sm transition-all duration-300 hover:border-primary/20 hover:shadow-md">
         <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-transform duration-300 group-hover:scale-105">
            <span class="material-symbols-outlined text-2xl">groups</span>
         </span>
         <div class="min-w-0 flex-1">
            <p class="font-headline font-bold text-base text-on-background">Inputasi Orang</p>
            <p class="mt-1 text-xs text-on-surface-variant">Catat personel masuk/keluar — data karyawan dari SID</p>
         </div>
         <span class="material-symbols-outlined text-on-surface-variant/40 transition-transform duration-300 group-hover:translate-x-0.5">arrow_forward</span>
      </button>

      <button type="button" data-ab-open-inputasi="treatment" class="group flex items-center gap-4 rounded-2xl border border-violet-200/60 bg-gradient-to-br from-violet-50/80 to-white p-6 text-left shadow-sm transition-all duration-300 hover:border-violet-300 hover:shadow-md">
         <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700 transition-transform duration-300 group-hover:scale-105">
            <span class="material-symbols-outlined text-2xl">upload_file</span>
         </span>
         <div class="min-w-0 flex-1">
            <p class="font-headline font-bold text-base text-on-background">Upload Evidence Treatment</p>
            <p class="mt-1 text-xs text-on-surface-variant">Input pengajuan unban internal — status Menunggu Review SOD</p>
         </div>
         <span class="material-symbols-outlined text-on-surface-variant/40 transition-transform duration-300 group-hover:translate-x-0.5">arrow_forward</span>
      </button>

      <a href="{{ route('auto-banned.inputasi.reconcile.index') }}" class="group flex items-center gap-4 rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50/80 to-white p-6 text-left shadow-sm transition-all duration-300 hover:border-amber-300 hover:shadow-md">
         <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800 transition-transform duration-300 group-hover:scale-105">
            <span class="material-symbols-outlined text-2xl">sync</span>
         </span>
         <div class="min-w-0 flex-1">
            <p class="font-headline font-bold text-base text-on-background">Rekonsiliasi Log Unban</p>
            <p class="mt-1 text-xs text-on-surface-variant">Backfill request approved + log unban untuk unban manual di luar sistem</p>
         </div>
         <span class="material-symbols-outlined text-on-surface-variant/40 transition-transform duration-300 group-hover:translate-x-0.5">arrow_forward</span>
      </a>

      <a href="{{ route('auto-banned.public.treatment.form') }}" target="_blank" rel="noopener" class="group flex items-center gap-4 rounded-2xl border border-emerald-200/60 bg-gradient-to-br from-emerald-50/80 to-white p-6 text-left shadow-sm transition-all duration-300 hover:border-emerald-300 hover:shadow-md md:col-span-2 xl:col-span-1">
         <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 transition-transform duration-300 group-hover:scale-105">
            <span class="material-symbols-outlined text-2xl">clinical_notes</span>
         </span>
         <div class="min-w-0 flex-1">
            <p class="font-headline font-bold text-base text-on-background">Form Publik Treatment</p>
            <p class="mt-1 text-xs text-on-surface-variant">Buka form untuk karyawan — tanpa login, bisa dishare via link</p>
         </div>
         <span class="material-symbols-outlined text-on-surface-variant/40 transition-transform duration-300 group-hover:translate-x-0.5">open_in_new</span>
      </a>
   </div>

   @include('AutoBanned.inputasi.partials.modals')
@endsection
