@extends('BaseRule.layouts.app')

@section('title', 'Kirim WA & Email Summary — HSECM')

@push('head')
@include('BaseRule.partials.styles')
<style>
   .hsecm-check {
      width: 1.05rem;
      height: 1.05rem;
      border-radius: 0.3rem;
      border-color: #99f6e4;
      color: #0f766e;
   }
   .hsecm-bulk-bar {
      position: sticky;
      bottom: 1rem;
      z-index: 40;
   }
   .hsecm-edit-modal {
      position: fixed;
      inset: 0;
      z-index: 60;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      background: rgba(15, 23, 42, 0.45);
   }
   .hsecm-edit-modal.is-open {
      display: flex;
   }
</style>
@endpush

@section('content')
@include('BaseRule.partials.page-header', [
   'title' => 'Kirim WA & Email Summary',
   'subtitle' => 'Checklist penerima, lalu kirim summary HSECM per site & perusahaan via WhatsApp atau Email.',
   'breadcrumb' => 'Kirim WA & Email',
])

@if(session('email_send_details'))
<div class="hsecm-card rounded-2xl p-4 mb-4 border border-teal-100">
   <p class="text-sm font-bold text-on-background mb-2">Hasil pengiriman email</p>
   <ul class="space-y-1 text-xs text-on-surface-variant">
      @foreach(session('email_send_details') as $detail)
      <li class="flex items-start gap-2">
         <span class="material-symbols-outlined text-sm {{ ($detail['success'] ?? false) ? 'text-emerald-600' : 'text-red-600' }}">
            {{ ($detail['success'] ?? false) ? 'check_circle' : 'error' }}
         </span>
         <span>
            <strong class="text-on-background">{{ $detail['nama'] }}</strong>
            ({{ $detail['email'] }}) — {{ $detail['message'] }}
         </span>
      </li>
      @endforeach
   </ul>
</div>
@endif

<form method="GET" action="{{ route('hsecm.wa-notify.index') }}" class="hsecm-card rounded-2xl p-4 mb-6">
   <div class="flex flex-wrap items-end gap-3">
      <div class="min-w-[8rem]">
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Week</label>
         <select name="week" class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
            <option value="">Semua Week</option>
            @foreach($filterOptions['weeks'] ?? [] as $week)
            <option value="{{ $week }}" @selected(($filters['week'] ?? '') === (string) $week)>{{ $week }}</option>
            @endforeach
         </select>
      </div>
      <div class="min-w-[7rem]">
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Year</label>
         <select name="year" class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
            <option value="">Semua Year</option>
            @foreach($filterOptions['years'] ?? [] as $year)
            <option value="{{ $year }}" @selected(($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
            @endforeach
         </select>
      </div>
      <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white hover:opacity-95">
         <span class="material-symbols-outlined text-sm">refresh</span>
         Refresh Summary
      </button>
      @if($fonnteConfigured)
      <span class="hsecm-badge hsecm-badge--success">Fonnte siap</span>
      @else
      <span class="hsecm-badge hsecm-badge--warning">Fonnte belum dikonfigurasi — gunakan Buka WA</span>
      @endif
   </div>
</form>

@guest
<div class="hsecm-card rounded-2xl p-4 mb-6 border border-slate-200 bg-slate-50 text-sm text-on-surface-variant">
   Mode lihat saja — login diperlukan untuk kirim WA/email dan kelola penerima.
</div>
@endguest

{{-- Panel kirim terjadwal midshift / endshift (night & day) --}}
@auth
<form method="POST" action="{{ route('hsecm.wa-notify.send-shift-email') }}" id="hsecm-shift-email-form" class="hsecm-card rounded-2xl p-5 mb-6 border border-amber-100">
   @csrf
   <input type="hidden" name="week" value="{{ $filters['week'] ?? '' }}">
   <input type="hidden" name="year" value="{{ $filters['year'] ?? '' }}">
   <input type="hidden" name="mode" id="hsecm-shift-mode" value="midshift">
   <input type="hidden" name="shift" id="hsecm-shift-value" value="night">
   <div id="hsecm-shift-indexes"></div>

   <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
      <div>
         <h2 class="font-headline font-bold text-lg text-on-background">Kirim Email Shift (Manual)</h2>
         <p class="text-xs text-on-surface-variant mt-0.5">
            Midshift = snapshot slot cut-off · Endshift = snapshot latest batch_slot + tasklist.
            Night / Day hanya membedakan label jadwal.
            Centang penerima di tabel di bawah untuk membatasi, atau kosongkan = semua penerima.
         </p>
      </div>
      <span class="hsecm-badge hsecm-badge--warning">Scheduler override</span>
   </div>

   <div class="flex flex-wrap items-end gap-3 mb-4">
      <div class="min-w-[16rem] flex-1">
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Email uji (opsional)</label>
         <input type="email" name="email" value="{{ old('email') }}" placeholder="kosongkan = pakai daftar / centangan"
                class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm bg-white">
      </div>
      <label class="inline-flex items-center gap-2 text-sm font-semibold text-on-surface px-2 py-2">
         <input type="checkbox" name="dry_run" value="1" class="hsecm-check" @checked(old('dry_run'))>
         Dry-run (tidak kirim sungguhan)
      </label>
   </div>

   <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
      <button type="submit" class="hsecm-shift-btn inline-flex flex-col items-start gap-1 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-left hover:bg-teal-100"
              data-mode="midshift" data-shift="night"
              onclick="return window.hsecmConfirmShift(this)">
         <span class="text-xs font-bold uppercase tracking-wide text-teal-800">Midshift Night</span>
         <span class="text-[11px] text-teal-700">01:00 · snapshot slot 00:00</span>
      </button>
      <button type="submit" class="hsecm-shift-btn inline-flex flex-col items-start gap-1 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-left hover:bg-teal-100"
              data-mode="midshift" data-shift="day"
              onclick="return window.hsecmConfirmShift(this)">
         <span class="text-xs font-bold uppercase tracking-wide text-teal-800">Midshift Day</span>
         <span class="text-[11px] text-teal-700">13:00 · snapshot slot 12:00</span>
      </button>
      <button type="submit" class="hsecm-shift-btn inline-flex flex-col items-start gap-1 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-left hover:bg-amber-100"
              data-mode="endshift" data-shift="night"
              onclick="return window.hsecmConfirmShift(this)">
         <span class="text-xs font-bold uppercase tracking-wide text-amber-900">Endshift Night</span>
         <span class="text-[11px] text-amber-800">07:30 · snapshot latest batch_slot</span>
      </button>
      <button type="submit" class="hsecm-shift-btn inline-flex flex-col items-start gap-1 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-left hover:bg-amber-100"
              data-mode="endshift" data-shift="day"
              onclick="return window.hsecmConfirmShift(this)">
         <span class="text-xs font-bold uppercase tracking-wide text-amber-900">Endshift Day</span>
         <span class="text-[11px] text-amber-800">20:30 · snapshot latest batch_slot</span>
      </button>
   </div>
</form>
@endauth

@auth
<form method="POST" action="{{ route('hsecm.wa-notify.recipients.store') }}" class="hsecm-card rounded-2xl p-5 mb-6 border border-teal-100">
   @csrf
   <input type="hidden" name="week" value="{{ $filters['week'] ?? '' }}">
   <input type="hidden" name="year" value="{{ $filters['year'] ?? '' }}">

   <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
      <div>
         <h2 class="font-headline font-bold text-lg text-on-background">Tambah Penerima Email</h2>
         <p class="text-xs text-on-surface-variant mt-0.5">
            Kontak baru disimpan terpisah dari daftar bawaan. Kosongkan site/perusahaan untuk scope agregat semua data.
         </p>
      </div>
      <span class="hsecm-badge">Custom storage</span>
   </div>

   @if($errors->any())
   <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700">
      <ul class="list-disc pl-4 space-y-0.5">
         @foreach($errors->all() as $error)
         <li>{{ $error }}</li>
         @endforeach
      </ul>
   </div>
   @endif

   <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
      <div>
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Nama <span class="text-red-500">*</span></label>
         <input type="text" name="nama" value="{{ old('nama') }}" required maxlength="150"
            class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white"
            placeholder="Nama penerima">
      </div>
      <div>
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Email <span class="text-red-500">*</span></label>
         <input type="email" name="email" value="{{ old('email') }}" required maxlength="190"
            class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white"
            placeholder="nama@perusahaan.com">
      </div>
      <div>
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">No. WA</label>
         <input type="text" name="no" value="{{ old('no') }}" maxlength="30"
            class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white"
            placeholder="08xxxxxxxxxx">
      </div>
      <div>
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Role</label>
         <input type="text" name="role" value="{{ old('role') }}" maxlength="150"
            class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white"
            placeholder="PROJECT MANAGER">
      </div>
      <div>
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Site</label>
         <select name="site" class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
            <option value="">Semua Site</option>
            @foreach($filterOptions['sites'] ?? [] as $site)
            <option value="{{ $site }}" @selected(old('site') === (string) $site)>{{ $site }}</option>
            @endforeach
         </select>
      </div>
      <div>
         <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Perusahaan</label>
         <select name="perusahaan" class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
            <option value="">Semua Perusahaan</option>
            @foreach($filterOptions['companies'] ?? [] as $company)
            <option value="{{ $company }}" @selected(old('perusahaan') === (string) $company)>{{ $company }}</option>
            @endforeach
         </select>
      </div>
   </div>

   <div class="mt-4 flex flex-wrap items-center gap-2">
      <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white hover:opacity-95">
         <span class="material-symbols-outlined text-sm">person_add</span>
         Tambah Penerima
      </button>
      <p class="text-[11px] text-on-surface-variant">Wajib: nama &amp; email. Site/perusahaan mengatur scope isi email.</p>
   </div>
</form>
@endauth

<form id="hsecm-email-bulk-form" method="POST" action="{{ route('hsecm.wa-notify.send-email-bulk') }}">
   @csrf
   <input type="hidden" name="week" value="{{ $filters['week'] ?? '' }}">
   <input type="hidden" name="year" value="{{ $filters['year'] ?? '' }}">

   <div class="hsecm-card rounded-2xl overflow-hidden mb-6">
      <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
         <div>
            <h2 class="font-headline font-bold text-lg text-on-background">Daftar Penerima</h2>
            <p class="text-xs text-on-surface-variant mt-0.5">Centang kontak lalu kirim email berurutan (satu per satu) sesuai site & perusahaan masing-masing</p>
         </div>
         <div class="flex flex-wrap items-center gap-2">
            <label class="inline-flex items-center gap-2 text-xs font-semibold text-on-surface-variant cursor-pointer select-none">
               <input type="checkbox" id="hsecm-check-all" class="hsecm-check">
               Pilih semua
            </label>
            <span class="hsecm-badge">{{ count($recipients) }} kontak</span>
            <span id="hsecm-selected-count" class="hsecm-badge hsecm-badge--success">0 dipilih</span>
         </div>
      </div>

      <div class="overflow-x-auto">
         <table class="hsecm-table w-full text-sm">
            <thead>
               <tr>
                  <th class="px-4 py-3 text-left w-10"></th>
                  <th class="px-4 py-3 text-left">Penerima</th>
                  <th class="px-3 py-3 text-left">Site</th>
                  <th class="px-3 py-3 text-left">Perusahaan</th>
                  <th class="px-3 py-3 text-left">Kontak</th>
                  <th class="px-3 py-3 text-right">Records</th>
                  <th class="px-3 py-3 text-left">Preview KPI</th>
                  <th class="px-3 py-3 text-right">Aksi</th>
               </tr>
            </thead>
            <tbody>
               @foreach($recipients as $row)
               <tr class="border-t border-slate-50 align-top">
                  <td class="px-4 py-3">
                     <input
                        type="checkbox"
                        name="indexes[]"
                        value="{{ $row['index'] }}"
                        class="hsecm-check hsecm-row-check"
                        @disabled(trim((string) $row['email']) === '')
                     >
                  </td>
                  <td class="px-4 py-3">
                     <div class="flex flex-wrap items-center gap-1.5">
                        <p class="font-bold text-on-background">{{ $row['nama'] }}</p>
                        @if(($row['source'] ?? '') === 'custom')
                        <span class="hsecm-badge hsecm-badge--success">Custom</span>
                        @endif
                     </div>
                     <p class="text-[11px] text-on-surface-variant">{{ $row['role'] }}</p>
                  </td>
                  <td class="px-3 py-3 whitespace-nowrap">
                     {{ $row['site'] ?? '—' }}
                     @if(($row['resolved_site'] ?? null) && ($row['site'] ?? null) && strcasecmp((string) $row['resolved_site'], (string) $row['site']) !== 0)
                     <div class="text-[10px] text-amber-700">→ {{ $row['resolved_site'] }}</div>
                     @endif
                  </td>
                  <td class="px-3 py-3 min-w-[12rem]">{{ $row['perusahaan'] !== '' ? $row['perusahaan'] : '—' }}</td>
                  <td class="px-3 py-3 whitespace-nowrap">
                     <div>{{ $row['no'] !== '' ? $row['no'] : '—' }}</div>
                     <div class="text-[11px] text-on-surface-variant truncate max-w-[14rem]">{{ $row['email'] }}</div>
                  </td>
                  <td class="px-3 py-3 text-right font-bold">{{ number_format($row['total_records']) }}</td>
                  <td class="px-3 py-3">
                     <div class="flex flex-wrap gap-1 max-w-[22rem]">
                        @foreach(array_slice($row['kpis'], 0, 4) as $kpi)
                        <span class="hsecm-badge" title="{{ $kpi['hint'] }}">{{ $kpi['label'] }}: {{ $kpi['value'] }}</span>
                        @endforeach
                     </div>
                     <details class="mt-2">
                        <summary class="text-[11px] font-semibold text-primary cursor-pointer">Preview pesan WA</summary>
                        <pre class="mt-2 text-[11px] whitespace-pre-wrap bg-slate-50 border border-slate-100 rounded-xl p-3 max-w-[28rem] max-h-48 overflow-auto">{{ $row['message'] }}</pre>
                     </details>
                  </td>
                  <td class="px-3 py-3 text-right whitespace-nowrap">
                     <div class="inline-flex flex-col gap-2 items-end">
                        <button
                           type="submit"
                           form="hsecm-email-single-{{ $row['index'] }}"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-3 py-2 text-xs font-bold text-white hover:opacity-95"
                           @disabled(trim((string) $row['email']) === '')
                           onclick="return confirm('Kirim email summary ke {{ $row['nama'] }}?')"
                        >
                           <span class="material-symbols-outlined text-sm">mail</span>
                           Kirim Email
                        </button>
                        <button type="submit" form="hsecm-wa-{{ $row['index'] }}" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:opacity-95" @disabled($row['wa_url'] === '')>
                           <span class="material-symbols-outlined text-sm">chat</span>
                           Buka WA
                        </button>
                        @if($fonnteConfigured)
                        <button type="submit" form="hsecm-fonnte-{{ $row['index'] }}" class="inline-flex items-center gap-1.5 rounded-xl border border-teal-300 bg-white px-3 py-2 text-xs font-bold text-teal-800 hover:bg-teal-50" onclick="return confirm('Kirim pesan via Fonnte ke {{ $row['nama'] }}?')">
                           <span class="material-symbols-outlined text-sm">send</span>
                           Kirim Fonnte
                        </button>
                        @endif
                        @if(!empty($row['editable']))
                        <button
                           type="button"
                           class="hsecm-edit-btn inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                           data-id="{{ $row['id'] }}"
                           data-nama="{{ $row['nama'] }}"
                           data-email="{{ $row['email'] }}"
                           data-no="{{ $row['no'] }}"
                           data-role="{{ $row['role'] }}"
                           data-site="{{ $row['site'] ?? '' }}"
                           data-perusahaan="{{ $row['perusahaan'] }}"
                           data-source="{{ $row['source'] ?? '' }}"
                        >
                           <span class="material-symbols-outlined text-sm">edit</span>
                           Edit
                        </button>
                        @endif
                        @if(!empty($row['deletable']))
                        <button
                           type="submit"
                           form="hsecm-delete-{{ $row['index'] }}"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50"
                           onclick="return confirm('Hapus penerima {{ $row['nama'] }} dari daftar?')"
                        >
                           <span class="material-symbols-outlined text-sm">delete</span>
                           Hapus
                        </button>
                        @endif
                     </div>
                  </td>
               </tr>
               @endforeach
            </tbody>
         </table>
      </div>
   </div>

   <div class="hsecm-bulk-bar">
      <div class="hsecm-card rounded-2xl p-4 flex flex-wrap items-center justify-between gap-3 border border-teal-100 shadow-lg">
         <div>
            <p class="text-sm font-bold text-on-background">Kirim Email Massal</p>
            <p class="text-xs text-on-surface-variant">Mengirim satu per satu ke setiap kontak tercentang, isi email mengikuti site &amp; perusahaan masing-masing.</p>
         </div>
         <button
            type="submit"
            id="hsecm-bulk-email-btn"
            class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white hover:opacity-95 disabled:opacity-40 disabled:cursor-not-allowed"
            disabled
            onclick="return confirm('Kirim email summary ke semua kontak yang dicentang? Proses dilakukan berurutan.');"
         >
            <span class="material-symbols-outlined text-base">forward_to_inbox</span>
            Kirim Email Terpilih
         </button>
      </div>
   </div>
</form>

{{-- Form aksi individu di luar form bulk agar tidak nested --}}
@foreach($recipients as $row)
<form id="hsecm-email-single-{{ $row['index'] }}" method="POST" action="{{ route('hsecm.wa-notify.send-email', $row['index']) }}" class="hidden">
   @csrf
   <input type="hidden" name="week" value="{{ $filters['week'] ?? '' }}">
   <input type="hidden" name="year" value="{{ $filters['year'] ?? '' }}">
</form>
<form id="hsecm-wa-{{ $row['index'] }}" method="POST" action="{{ route('hsecm.wa-notify.send', $row['index']) }}" class="hidden">
   @csrf
   <input type="hidden" name="channel" value="wa_me">
   <input type="hidden" name="week" value="{{ $filters['week'] ?? '' }}">
   <input type="hidden" name="year" value="{{ $filters['year'] ?? '' }}">
</form>
@if($fonnteConfigured)
<form id="hsecm-fonnte-{{ $row['index'] }}" method="POST" action="{{ route('hsecm.wa-notify.send', $row['index']) }}" class="hidden">
   @csrf
   <input type="hidden" name="channel" value="fonnte">
   <input type="hidden" name="week" value="{{ $filters['week'] ?? '' }}">
   <input type="hidden" name="year" value="{{ $filters['year'] ?? '' }}">
</form>
@endif
@if(!empty($row['deletable']))
<form id="hsecm-delete-{{ $row['index'] }}" method="POST" action="{{ route('hsecm.wa-notify.recipients.destroy', $row['id']) }}" class="hidden">
   @csrf
   @method('DELETE')
   <input type="hidden" name="week" value="{{ $filters['week'] ?? '' }}">
   <input type="hidden" name="year" value="{{ $filters['year'] ?? '' }}">
</form>
@endif
@endforeach

{{-- Modal edit penerima --}}
<div id="hsecm-edit-modal" class="hsecm-edit-modal" aria-hidden="true">
   <div class="hsecm-card w-full max-w-2xl rounded-2xl p-5 border border-teal-100 shadow-xl bg-white" role="dialog" aria-modal="true" aria-labelledby="hsecm-edit-title">
      <form method="POST" id="hsecm-edit-form" action="">
         @csrf
         @method('PUT')
         <input type="hidden" name="week" value="{{ $filters['week'] ?? '' }}">
         <input type="hidden" name="year" value="{{ $filters['year'] ?? '' }}">

         <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
               <h2 id="hsecm-edit-title" class="font-headline font-bold text-lg text-on-background">Edit Penerima</h2>
               <p class="text-xs text-on-surface-variant mt-0.5">
                  Ubah nama, email, nomor WA, role, site, atau perusahaan.
                  <span id="hsecm-edit-source-hint" class="block mt-0.5"></span>
               </p>
            </div>
            <button type="button" id="hsecm-edit-close" class="inline-flex items-center justify-center rounded-xl border border-slate-200 w-9 h-9 text-slate-600 hover:bg-slate-50" aria-label="Tutup">
               <span class="material-symbols-outlined text-base">close</span>
            </button>
         </div>

         <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
               <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Nama <span class="text-red-500">*</span></label>
               <input type="text" name="nama" id="hsecm-edit-nama" required maxlength="150"
                  class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
            </div>
            <div>
               <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Email <span class="text-red-500">*</span></label>
               <input type="email" name="email" id="hsecm-edit-email" required maxlength="190"
                  class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
            </div>
            <div>
               <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">No. WA</label>
               <input type="text" name="no" id="hsecm-edit-no" maxlength="30"
                  class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
            </div>
            <div>
               <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Role</label>
               <input type="text" name="role" id="hsecm-edit-role" maxlength="150"
                  class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
            </div>
            <div>
               <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Site</label>
               <select name="site" id="hsecm-edit-site" class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
                  <option value="">Semua Site</option>
                  @foreach($filterOptions['sites'] ?? [] as $site)
                  <option value="{{ $site }}">{{ $site }}</option>
                  @endforeach
               </select>
            </div>
            <div>
               <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1">Perusahaan</label>
               <select name="perusahaan" id="hsecm-edit-perusahaan" class="w-full rounded-xl border border-outline-variant/40 px-3 py-2 text-sm font-semibold bg-white">
                  <option value="">Semua Perusahaan</option>
                  @foreach($filterOptions['companies'] ?? [] as $company)
                  <option value="{{ $company }}">{{ $company }}</option>
                  @endforeach
               </select>
            </div>
         </div>

         <div class="mt-5 flex flex-wrap items-center justify-end gap-2">
            <button type="button" id="hsecm-edit-cancel" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">
               Batal
            </button>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white hover:opacity-95">
               <span class="material-symbols-outlined text-sm">save</span>
               Simpan Perubahan
            </button>
         </div>
      </form>
   </div>
</div>
@endsection

@push('scripts')
@if(session('wa_url'))
<script>
   window.open(@json(session('wa_url')), '_blank');
</script>
@endif
<script>
(() => {
   const checkAll = document.getElementById('hsecm-check-all');
   const rowChecks = Array.from(document.querySelectorAll('.hsecm-row-check'));
   const selectedCount = document.getElementById('hsecm-selected-count');
   const bulkBtn = document.getElementById('hsecm-bulk-email-btn');
   const shiftIndexes = document.getElementById('hsecm-shift-indexes');
   const shiftMode = document.getElementById('hsecm-shift-mode');
   const shiftValue = document.getElementById('hsecm-shift-value');

   const sync = () => {
      const enabled = rowChecks.filter((el) => !el.disabled);
      const checked = enabled.filter((el) => el.checked);
      if (selectedCount) selectedCount.textContent = `${checked.length} dipilih`;
      if (bulkBtn) bulkBtn.disabled = checked.length === 0;
      if (checkAll) {
         checkAll.checked = enabled.length > 0 && checked.length === enabled.length;
         checkAll.indeterminate = checked.length > 0 && checked.length < enabled.length;
      }
   };

   const syncShiftIndexes = () => {
      if (!shiftIndexes) return;
      shiftIndexes.innerHTML = '';
      rowChecks.filter((el) => el.checked && !el.disabled).forEach((el) => {
         const input = document.createElement('input');
         input.type = 'hidden';
         input.name = 'indexes[]';
         input.value = el.value;
         shiftIndexes.appendChild(input);
      });
   };

   window.hsecmConfirmShift = (btn) => {
      const mode = btn.getAttribute('data-mode') || 'midshift';
      const shift = btn.getAttribute('data-shift') || 'night';
      if (shiftMode) shiftMode.value = mode;
      if (shiftValue) shiftValue.value = shift;
      syncShiftIndexes();
      const dry = document.querySelector('#hsecm-shift-email-form input[name="dry_run"]')?.checked;
      const label = `${mode.toUpperCase()} / ${shift.toUpperCase()}`;
      return confirm(
         (dry ? '[DRY-RUN] ' : '') +
         `Kirim email ${label} sekarang?\n` +
         (document.querySelector('#hsecm-shift-email-form input[name="email"]')?.value
            ? 'Target: email uji yang diisi.'
            : (shiftIndexes.children.length > 0
               ? `Target: ${shiftIndexes.children.length} penerima tercentang.`
               : 'Target: semua penerima.'))
      );
   };

   checkAll?.addEventListener('change', () => {
      rowChecks.forEach((el) => {
         if (!el.disabled) el.checked = checkAll.checked;
      });
      sync();
   });

   rowChecks.forEach((el) => el.addEventListener('change', sync));
   sync();

   // Edit modal
   const editModal = document.getElementById('hsecm-edit-modal');
   const editForm = document.getElementById('hsecm-edit-form');
   const editSourceHint = document.getElementById('hsecm-edit-source-hint');
   const updateUrlTemplate = @json(route('hsecm.wa-notify.recipients.update', ['id' => '__ID__']));

   const ensureSelectValue = (selectEl, value) => {
      if (!selectEl) return;
      const normalized = value || '';
      if (normalized !== '' && !Array.from(selectEl.options).some((opt) => opt.value === normalized)) {
         const opt = document.createElement('option');
         opt.value = normalized;
         opt.textContent = normalized;
         selectEl.appendChild(opt);
      }
      selectEl.value = normalized;
   };

   const openEditModal = (btn) => {
      if (!editModal || !editForm) return;
      const id = btn.getAttribute('data-id') || '';
      editForm.action = updateUrlTemplate.replace('__ID__', encodeURIComponent(id));
      document.getElementById('hsecm-edit-nama').value = btn.getAttribute('data-nama') || '';
      document.getElementById('hsecm-edit-email').value = btn.getAttribute('data-email') || '';
      document.getElementById('hsecm-edit-no').value = btn.getAttribute('data-no') || '';
      document.getElementById('hsecm-edit-role').value = btn.getAttribute('data-role') || '';
      ensureSelectValue(document.getElementById('hsecm-edit-site'), btn.getAttribute('data-site') || '');
      ensureSelectValue(document.getElementById('hsecm-edit-perusahaan'), btn.getAttribute('data-perusahaan') || '');
      if (editSourceHint) {
         editSourceHint.textContent = (btn.getAttribute('data-source') || '') === 'config'
            ? 'Kontak bawaan: perubahan disimpan sebagai override (tanpa ubah config).'
            : 'Kontak custom: perubahan langsung disimpan ke storage.';
      }
      editModal.classList.add('is-open');
      editModal.setAttribute('aria-hidden', 'false');
      document.getElementById('hsecm-edit-nama')?.focus();
   };

   const closeEditModal = () => {
      if (!editModal) return;
      editModal.classList.remove('is-open');
      editModal.setAttribute('aria-hidden', 'true');
   };

   document.querySelectorAll('.hsecm-edit-btn').forEach((btn) => {
      btn.addEventListener('click', () => openEditModal(btn));
   });
   document.getElementById('hsecm-edit-close')?.addEventListener('click', closeEditModal);
   document.getElementById('hsecm-edit-cancel')?.addEventListener('click', closeEditModal);
   editModal?.addEventListener('click', (e) => {
      if (e.target === editModal) closeEditModal();
   });
   document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && editModal?.classList.contains('is-open')) {
         closeEditModal();
      }
   });
})();
</script>
@endpush
