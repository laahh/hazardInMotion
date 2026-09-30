@extends('MonitoringSafetyEngginering.layouts.crm')

@section('title', 'Role Akses — Monitoring Safety Engineering')

@push('head')
@include('MonitoringSafetyEngginering.partials.crm-styles')
@include('MonitoringSafetyEngginering.role-access._styles')
@endpush

@section('content')
@php
   $allSitesLabel = \App\Models\MonitoringSafetyEngineeringPicAssignment::ALL_SITES;
@endphp

<div class="ra-page-head">
   <div>
      <h1 class="ra-page-title">Role Akses</h1>
      <p class="ra-page-subtitle">
         Assign orang (nama atau SID) ke perusahaan dan site yang dia pegang. Satu orang boleh memegang
         beberapa perusahaan, dan tiap perusahaan boleh memegang beberapa site. User yang punya assignment
         hanya melihat dan mengubah data pada scope tersebut; admin tetap melihat semuanya.
      </p>
   </div>
   <div class="ra-head-actions">
      <form method="POST" action="{{ route('monitoring-safety-engineering.role-access.import-legacy') }}"
            onsubmit="return confirm('Impor assignment dari NAMA_PIC.json? Data yang sudah ada tidak akan diduplikasi.');">
         @csrf
         <button type="submit" class="ra-btn ra-btn--ghost">
            <span class="material-symbols-outlined text-[18px]">download</span>
            Impor NAMA_PIC.json
         </button>
      </form>
      <a href="{{ route('monitoring-safety-engineering.role-access.create') }}" class="ra-btn ra-btn--primary">
         <span class="material-symbols-outlined text-[18px]">person_add</span>
         Tambah PIC
      </a>
   </div>
</div>

@if($errors->any())
<div class="ra-alert ra-alert--error" role="alert">
   <ul>
      @foreach($errors->all() as $error)
      <li>{{ $error }}</li>
      @endforeach
   </ul>
</div>
@endif

@unless($tablesReady)
<div class="ra-alert ra-alert--info" role="status">
   Tabel <code>monitoring_safety_engineering_pic_assignments</code> belum tersedia. Jalankan migration
   terlebih dahulu, lalu gunakan tombol <strong>Impor NAMA_PIC.json</strong> untuk mengisi data awal.
</div>
@endunless

<div class="ra-card">
   <div class="ra-page-head" style="margin-bottom: 1rem;">
      <div>
         <p class="ra-card-title">Daftar Assignment</p>
         <p class="ra-card-hint">{{ $assignments->count() }} PIC terdaftar.</p>
      </div>
      <form method="GET" action="{{ route('monitoring-safety-engineering.role-access.index') }}" class="ra-search">
         <input type="search" name="q" value="{{ $search }}" class="ra-input" placeholder="Cari nama, SID, perusahaan, atau site…">
         <button type="submit" class="ra-btn ra-btn--ghost">Cari</button>
         @if($search !== '')
         <a href="{{ route('monitoring-safety-engineering.role-access.index') }}" class="ra-btn ra-btn--ghost">Reset</a>
         @endif
      </form>
   </div>

   <div class="ra-table-wrap">
      <table class="ra-table">
         <thead>
            <tr>
               <th>PIC</th>
               <th>Akun Login</th>
               <th>Perusahaan &amp; Site</th>
               <th>Status</th>
               <th>Aksi</th>
            </tr>
         </thead>
         <tbody>
            @forelse($assignments as $assignment)
            @php $grouped = $assignment->groupedCompanySites(); @endphp
            <tr>
               <td>
                  <div class="ra-identity">
                     {{ $assignment->nama }}
                     @if($assignment->sid)<span class="ra-sid">{{ $assignment->sid }}</span>@endif
                  </div>
                  @if($assignment->jabatan)
                  <div class="ra-identity-meta">{{ $assignment->jabatan }}</div>
                  @endif
                  @if($assignment->catatan)
                  <div class="ra-identity-meta">{{ $assignment->catatan }}</div>
                  @endif
               </td>
               <td>
                  @if($assignment->user)
                  <div class="ra-identity-meta" style="font-weight:600;color:#2F2F3A;">{{ $assignment->user->name }}</div>
                  <div class="ra-identity-meta">{{ $assignment->user->email }}</div>
                  @else
                  <span class="ra-identity-meta">Dicocokkan lewat nama / SID</span>
                  @endif
               </td>
               <td>
                  @if($grouped === [])
                  <span class="ra-scope-none">Belum ada scope</span>
                  @else
                  <div class="ra-scope-list">
                     @foreach($grouped as $row)
                     <div class="ra-scope-item">
                        <span class="ra-scope-company">{{ $row['perusahaan'] }}</span>
                        @foreach($row['sites'] as $site)
                        <span class="ra-scope-site {{ \App\Models\MonitoringSafetyEngineeringPicAssignment::isAllSitesLabel($site) ? 'ra-scope-site--all' : '' }}">{{ $site }}</span>
                        @endforeach
                     </div>
                     @endforeach
                  </div>
                  @endif
               </td>
               <td>
                  <span class="ra-pill {{ $assignment->is_active ? 'ra-pill--on' : 'ra-pill--off' }}">
                     {{ $assignment->is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
               </td>
               <td>
                  <div class="ra-row-actions">
                     <a href="{{ route('monitoring-safety-engineering.role-access.edit', $assignment->id) }}"
                        class="ra-btn ra-btn--ghost ra-btn--sm">Ubah</a>
                     <form method="POST" action="{{ route('monitoring-safety-engineering.role-access.destroy', $assignment->id) }}"
                           onsubmit="return confirm('Hapus role akses untuk {{ addslashes($assignment->identityLabel()) }}?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="ra-btn ra-btn--danger ra-btn--sm">Hapus</button>
                     </form>
                  </div>
               </td>
            </tr>
            @empty
            <tr>
               <td colspan="5" class="ra-table-empty">
                  @if($search !== '')
                     Tidak ada PIC yang cocok dengan "{{ $search }}".
                  @else
                     Belum ada assignment. Tambah manual, atau impor dari NAMA_PIC.json.
                  @endif
               </td>
            </tr>
            @endforelse
         </tbody>
      </table>
   </div>

   <p class="ra-card-hint" style="margin-top: 0.9rem;">
      Site <strong>{{ $allSitesLabel }}</strong> berarti PIC memegang seluruh site pada perusahaan tersebut.
   </p>
</div>
@endsection
