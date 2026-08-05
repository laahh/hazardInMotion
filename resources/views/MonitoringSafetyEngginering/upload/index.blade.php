@extends('MonitoringSafetyEngginering.layouts.app')

@section('title', 'Upload Data — Monitoring Safety Engineering')

@push('head')
@include('MonitoringSafetyEngginering.partials.styles')
@endpush

@section('content')
<div class="space-y-6 max-w-4xl">
   <section>
      <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-on-surface-variant mb-1">Monitoring Safety Engineering</p>
      <h1 class="font-headline font-extrabold text-2xl text-primary">Upload Data Excel</h1>
      <p class="text-sm text-on-surface-variant mt-1">
         Unduh template, isi sesuai format kolom spreadsheet manajemen, lalu upload file .xlsx.
      </p>
   </section>

   @unless($tablesReady)
   <div class="mse-alert-info">
      <span class="material-symbols-outlined text-sm align-middle mr-1">info</span>
      Tabel database belum tersedia. Jalankan migration terlebih dahulu sebelum upload.
   </div>
   @endunless

   <div class="mse-card p-6 space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-gray-100">
         <div>
            <h2 class="font-headline font-bold text-primary flex items-center gap-2">
               <span class="material-symbols-outlined">download</span>
               Template Excel
            </h2>
            <p class="text-sm text-on-surface-variant mt-1">
               {{ $columnCount }} kolom · 2 baris header · sheet <strong>Data Komitmen</strong> + <strong>Referensi</strong>
            </p>
         </div>
         <a href="{{ route('monitoring-safety-engineering.upload.template') }}" class="mse-btn mse-btn--secondary shrink-0">
            <span class="material-symbols-outlined text-sm">file_download</span>
            Download Template
         </a>
      </div>

      <div class="rounded-lg bg-slate-50 border border-slate-200 p-4 text-sm text-on-surface-variant space-y-2">
         <p class="font-bold text-primary text-xs uppercase tracking-wide">Format Template</p>
         <ul class="list-disc pl-5 space-y-1">
            <li>Baris 1–2: header grup & sub-kolom — <strong>jangan diubah</strong></li>
            <li>Data mulai baris 3</li>
            <li>Kolom <strong>AKTIVITAS</strong> berada di dekat akhir tabel, setelah kolom Analisis (mengikuti format terbaru)</li>
            <li>Dropdown: SITE, PERUSAHAAN, SUMBER REKAYASA, Status fase (Not Yet / In Progress / Done), EFEKTIVITAS REKAYASA (L1–L5), dll.</li>
            <li>Evidence: kolom opsional di Excel; upload file evidence nanti via sistem</li>
            <li>Jika POTENSI PENINGKATAN = <strong>Ya</strong>, kolom pengendalian peningkatan efektivitas wajib diisi</li>
            <li>4 kolom baru di akhir tabel: TOTAL RISIKO SIGNIFIKAN, LINK LIST RISIKO SIGNIFIKAN, JUMLAH & LINK RISIKO SIGNIFIKAN POTENSI TERCOVER REKAYASA</li>
         </ul>
      </div>

      <form method="POST" action="{{ route('monitoring-safety-engineering.upload.import') }}" enctype="multipart/form-data" class="space-y-4">
         @csrf

         <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
               <label class="mse-form-label" for="period_year">Tahun Periode</label>
               <select name="period_year" id="period_year" class="mse-form-input" required>
                  @foreach($planYears as $year)
                  <option value="{{ $year }}" @selected((int) old('period_year', now()->year) === (int) $year)>{{ $year }}</option>
                  @endforeach
               </select>
            </div>
            <div>
               <label class="mse-form-label" for="excel_file">File Excel (.xlsx)</label>
               <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls" class="mse-form-input" required>
               <p class="text-[11px] text-on-surface-variant mt-1">Maks. 10 MB</p>
            </div>
         </div>

         <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="mse-btn mse-btn--primary" @disabled(! $tablesReady)>
               <span class="material-symbols-outlined text-sm">upload_file</span>
               Upload & Import
            </button>
            <a href="{{ route('monitoring-safety-engineering.dashboard') }}" class="mse-btn mse-btn--secondary">
               Lihat Dashboard
            </a>
         </div>
      </form>
   </div>

   @if(session('importErrors'))
   <div class="mse-card p-5 border border-amber-200">
      <h3 class="font-bold text-amber-800 text-sm mb-3">Detail Error Import</h3>
      <ul class="list-disc pl-5 text-sm text-amber-900 space-y-1 max-h-64 overflow-y-auto">
         @foreach(session('importErrors') as $error)
         <li>{{ $error }}</li>
         @endforeach
      </ul>
   </div>
   @endif
</div>
@endsection
