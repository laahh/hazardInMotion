@extends('peer-pressure-edukasi.layouts.peer-app')

@section('title', 'Validasi TBC')

@push('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endpush

@section('content')
@php
   $openImportModal = request('modal') === 'import' || $errors->any();
   $importLogs = $importLogs ?? collect();
   $hasPendingImport = $hasPendingImport ?? false;
@endphp

{{-- Modal Import Excel --}}
<div id="vtbc-import-modal" class="fixed inset-0 z-[100] {{ $openImportModal ? 'flex' : 'hidden' }} items-center justify-center p-4" aria-modal="true" role="dialog" aria-labelledby="vtbc-import-modal-title" aria-hidden="{{ $openImportModal ? 'false' : 'true' }}">
   <div class="absolute inset-0 z-0 bg-black/40 backdrop-blur-[1px]" data-vtbc-close-modal tabindex="-1" aria-hidden="true"></div>
   <div class="relative z-10 w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-card-heavy border border-outline-variant/20">
      <div class="sticky top-0 flex items-start justify-between gap-3 border-b border-outline-variant/15 bg-white px-5 py-4 rounded-t-2xl">
         <div>
            <h3 id="vtbc-import-modal-title" class="font-headline font-bold text-lg text-on-surface">Import Excel — Validasi TBC</h3>
            <p class="text-[11px] text-on-surface-variant mt-1 leading-relaxed">Sheet pertama; tiap baris satu record. Header baris pertama harus sama persis dengan template. File besar diproses di latar belakang — pastikan <code class="rounded bg-[#e2e8f0] px-1 py-0.5 text-[10px]">QUEUE_CONNECTION=database</code> di <code class="rounded bg-[#e2e8f0] px-1 py-0.5 text-[10px]">.env</code> dan jalankan <code class="rounded bg-[#e2e8f0] px-1 py-0.5 text-[10px]">php artisan queue:work</code>.</p>
         </div>
         <button type="button" class="shrink-0 rounded-lg p-1.5 text-on-surface-variant hover:bg-[#f1f5f9] hover:text-on-surface" data-vtbc-close-modal aria-label="Tutup">
            <span class="material-symbols-outlined text-xl">close</span>
         </button>
      </div>
      <div class="px-5 py-4 space-y-4">
         @if ($errors->any())
         <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-900" role="alert">
            <p class="font-bold mb-1">Periksa file</p>
            <ul class="list-disc list-inside space-y-0.5">
               @foreach ($errors->all() as $err)
                  <li>{{ $err }}</li>
               @endforeach
            </ul>
         </div>
         @endif
         <div class="rounded-xl border border-outline-variant/20 bg-[#f8fafc] p-3 text-[11px] text-on-surface-variant leading-relaxed max-h-40 overflow-y-auto">
            <p class="font-bold text-on-surface mb-1">Kolom (urutan tetap)</p>
            <p class="text-[10px]">Tasklist, TobeConcernedHazard, GR, Catatan, Blindspot terlapor BC, No Item PSPP, Kategori GR, SID Pekerja Terlibat (pelaku/pelanggar), Nama (pelaku/pelanggar).</p>
         </div>
         <div class="flex flex-wrap gap-2">
            <a href="{{ route('peer-pressure-edukasi.validasi-tbc.template') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-outline-variant/30 bg-white px-3 py-2 text-[11px] font-bold shadow-sm hover:bg-surface-container-high">
               <span class="material-symbols-outlined text-base">download</span> Unduh template
            </a>
         </div>
         <form method="post" action="{{ route('peer-pressure-edukasi.validasi-tbc.import') }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
               <label class="block text-[11px] font-bold text-on-surface-variant mb-1.5">File Excel (.xlsx / .xls, maks. 50 MB) — diproses di antrian</label>
               <input type="file" name="excel_file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" required class="block w-full text-xs text-on-surface file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:font-bold file:text-primary">
            </div>
            <div class="flex flex-wrap justify-end gap-2 pt-1">
               <button type="button" class="inline-flex items-center justify-center rounded-xl border border-outline-variant/30 bg-white px-4 py-2 text-xs font-bold text-on-surface hover:bg-[#f8fafc]" data-vtbc-close-modal>Batal</button>
               <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs font-bold text-white shadow-md hover:opacity-95">
                  <span class="material-symbols-outlined text-base">upload_file</span> Unggah &amp; impor
               </button>
            </div>
         </form>
      </div>
   </div>
</div>

<div class="mb-6 bg-white rounded-2xl anchored-card overflow-hidden border border-outline-variant/15">
   <div class="border-b border-outline-variant/15 bg-[#f8fafc] px-5 py-4">
      <h3 class="font-headline text-base font-bold text-on-surface">Riwayat impor Excel</h3>
      <p class="mt-1 text-[11px] leading-snug text-on-surface-variant">Status dari antrian worker: <span class="font-semibold text-amber-800">Menunggu</span>, <span class="font-semibold text-emerald-800">Selesai</span> (jumlah baris), atau <span class="font-semibold text-red-800">Gagal</span> (pesan error). Refresh halaman jika masih menunggu.</p>
   </div>
   @if ($importLogs->isEmpty())
   <p class="px-5 py-8 text-center text-sm text-on-surface-variant">Belum ada impor. Gunakan tombol <span class="font-semibold text-on-surface">Import Excel</span> di bawah.</p>
   @else
   <div class="overflow-x-auto">
      <table class="w-full min-w-[640px] text-left text-sm">
         <thead class="border-b border-outline-variant/15 bg-white text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">
            <tr>
               <th class="px-4 py-3 whitespace-nowrap">Waktu</th>
               <th class="px-4 py-3 whitespace-nowrap">Status</th>
               <th class="px-4 py-3 text-right whitespace-nowrap">Baris diimpor</th>
               <th class="px-4 py-3">Keterangan / error</th>
            </tr>
         </thead>
         <tbody class="divide-y divide-outline-variant/10">
            @foreach ($importLogs as $log)
            <tr class="hover:bg-[#f8fafc]">
               <td class="px-4 py-2.5 text-[12px] tabular-nums text-on-surface-variant">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
               <td class="px-4 py-2.5">
                  @if ($log->status === 'pending')
                  <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900">Menunggu</span>
                  @elseif ($log->status === 'completed')
                  <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-900">Selesai</span>
                  @else
                  <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-red-900">Gagal</span>
                  @endif
               </td>
               <td class="px-4 py-2.5 text-right text-[12px] font-semibold tabular-nums text-on-surface">
                  {{ $log->rows_imported !== null ? number_format($log->rows_imported) : '—' }}
               </td>
               <td class="px-4 py-2.5 text-[11px] leading-snug text-on-surface">
                  @if (filled($log->error_message))
                  <span class="text-red-800">{{ \Illuminate\Support\Str::limit($log->error_message, 280) }}</span>
                  @else
                  <span class="text-on-surface-variant">—</span>
                  @endif
               </td>
            </tr>
            @endforeach
         </tbody>
      </table>
   </div>
   @if (!empty($hasPendingImport) && $hasPendingImport)
   <p class="border-t border-outline-variant/10 px-5 py-2 text-center text-[10px] text-on-surface-variant">Ada impor yang masih menunggu — halaman akan di-refresh otomatis dalam beberapa detik.</p>
   @endif
   @endif
</div>

<div class="bg-white rounded-2xl anchored-card overflow-hidden">
   <div class="p-6 border-b border-outline-variant/20 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div>
         <h2 class="font-headline font-bold text-xl text-on-surface">Validasi TBC</h2>
         <p class="text-xs text-on-surface-variant font-medium">Tasklist, hazard, GR, blindspot terlapor BC &amp; pekerja terlibat (pelaku/pelanggar).</p>
      </div>
      <div class="flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
         <form method="get" action="{{ route('peer-pressure-edukasi.validasi-tbc.index') }}" class="flex w-full min-w-0 flex-1 flex-col gap-2 sm:max-w-md sm:flex-row sm:items-center">
            <div class="relative min-w-0 flex-1">
               <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 z-0 -translate-y-1/2 text-lg text-on-surface-variant">search</span>
               <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="Cari di semua kolom…" autocomplete="off" class="w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] py-2 pl-10 pr-3 text-sm text-on-surface outline-none transition-shadow placeholder:text-on-surface-variant/60 focus:border-primary/40 focus:ring-2 focus:ring-primary/15" aria-label="Cari">
            </div>
            <div class="flex shrink-0 gap-2">
               <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-outline-variant/30 bg-white px-4 py-2 text-xs font-bold shadow-sm transition-colors hover:bg-surface-container-high">Cari</button>
               @if(filled($q ?? null))
               <a href="{{ route('peer-pressure-edukasi.validasi-tbc.index') }}" class="inline-flex items-center justify-center rounded-xl px-3 py-2 text-xs font-bold text-on-surface-variant hover:bg-[#f1f5f9]">Reset</a>
               @endif
            </div>
         </form>
         <div class="flex flex-wrap gap-2">
            <a href="{{ route('peer-pressure-edukasi.validasi-tbc.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-xs font-bold text-white shadow-md transition-transform active:scale-95">
               <span class="material-symbols-outlined text-base">add</span> Tambah data
            </a>
            <a href="{{ route('peer-pressure-edukasi.validasi-tbc.template') }}" class="inline-flex items-center gap-2 rounded-xl border border-outline-variant/30 bg-white px-4 py-2 text-xs font-bold shadow-sm hover:bg-surface-container-high">
               <span class="material-symbols-outlined text-base">download</span> Template Excel
            </a>
            <button type="button" id="vtbc-open-import-modal" class="inline-flex items-center gap-2 rounded-xl border border-outline-variant/30 bg-white px-4 py-2 text-xs font-bold shadow-sm hover:bg-surface-container-high">
               <span class="material-symbols-outlined text-base">upload_file</span> Import Excel
            </button>
         </div>
      </div>
   </div>
   <div class="overflow-x-auto">
      <table class="w-full text-sm text-left min-w-[1400px]">
         <thead class="bg-[#f8fafc] text-on-surface-variant font-bold text-[10px] uppercase tracking-[0.12em] border-b border-outline-variant/20">
            <tr>
               <th class="px-5 py-4 whitespace-nowrap">Tasklist</th>
               <th class="px-5 py-4 min-w-[180px]">TobeConcernedHazard</th>
               <th class="px-5 py-4">GR</th>
               <th class="px-5 py-4 min-w-[180px]">Catatan</th>
               <th class="px-5 py-4 min-w-[180px]">Blindspot terlapor BC</th>
               <th class="px-5 py-4 whitespace-nowrap">No Item PSPP</th>
               <th class="px-5 py-4 whitespace-nowrap">Kategori GR</th>
               <th class="px-5 py-4 whitespace-nowrap">SID pekerja terlibat</th>
               <th class="px-5 py-4 whitespace-nowrap">Nama (pelaku/pelanggar)</th>
               <th class="px-5 py-4 w-32">Aksi</th>
            </tr>
         </thead>
         <tbody class="divide-y divide-outline-variant/10">
            @forelse ($rows as $r)
               <tr class="hover:bg-[#f8fafc] transition-colors align-top">
                  <td class="px-5 py-4 text-xs font-bold text-on-surface max-w-[120px]">{{ filled($r->tasklist) ? \Illuminate\Support\Str::limit($r->tasklist, 32) : '—' }}</td>
                  <td class="px-5 py-4 text-[11px] text-on-surface-variant max-w-[220px]">{{ filled($r->to_be_concerned_hazard) ? \Illuminate\Support\Str::limit($r->to_be_concerned_hazard, 90) : '—' }}</td>
                  <td class="px-5 py-4 text-xs">{{ filled($r->gr) ? \Illuminate\Support\Str::limit($r->gr, 24) : '—' }}</td>
                  <td class="px-5 py-4 text-[11px] text-on-surface-variant max-w-[220px]">{{ filled($r->catatan) ? \Illuminate\Support\Str::limit($r->catatan, 90) : '—' }}</td>
                  <td class="px-5 py-4 text-[11px] text-on-surface-variant max-w-[220px]">{{ filled($r->blindspot_terlapor_bc) ? \Illuminate\Support\Str::limit($r->blindspot_terlapor_bc, 90) : '—' }}</td>
                  <td class="px-5 py-4 text-xs">{{ filled($r->no_item_pspp) ? \Illuminate\Support\Str::limit($r->no_item_pspp, 24) : '—' }}</td>
                  <td class="px-5 py-4 text-xs">{{ filled($r->kategori_gr) ? \Illuminate\Support\Str::limit($r->kategori_gr, 24) : '—' }}</td>
                  <td class="px-5 py-4 text-xs tabular-nums">{{ filled($r->sid_pekerja_terlibat) ? \Illuminate\Support\Str::limit($r->sid_pekerja_terlibat, 24) : '—' }}</td>
                  <td class="px-5 py-4 text-xs">{{ filled($r->nama_pekerja_terlibat) ? \Illuminate\Support\Str::limit($r->nama_pekerja_terlibat, 36) : '—' }}</td>
                  <td class="px-5 py-4">
                     <div class="flex flex-col gap-2">
                        <a href="{{ route('peer-pressure-edukasi.validasi-tbc.edit', $r->id) }}" class="inline-flex items-center justify-center gap-1 rounded-lg border border-primary/30 bg-primary/5 px-3 py-1.5 text-[11px] font-bold text-primary hover:bg-primary/10">Edit</a>
                        <form action="{{ route('peer-pressure-edukasi.validasi-tbc.destroy', $r->id) }}" method="post" onsubmit="return confirm('Hapus data #{{ $r->id }}?');">
                           @csrf
                           @method('DELETE')
                           <button type="submit" class="w-full inline-flex items-center justify-center gap-1 rounded-lg border border-error/30 bg-red-50 px-3 py-1.5 text-[11px] font-bold text-error hover:bg-red-100">Hapus</button>
                        </form>
                     </div>
                  </td>
               </tr>
            @empty
               <tr>
                  <td colspan="10" class="px-8 py-10 text-center text-sm text-on-surface-variant font-medium">
                     @if(filled($q ?? null))Tidak ada hasil untuk pencarian ini.@else Belum ada data validasi TBC.@endif
                  </td>
               </tr>
            @endforelse
         </tbody>
      </table>
   </div>
   <div class="p-6 bg-[#f8fafc] flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-t border-outline-variant/20">
      <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">
         @if ($rows->total() === 0)
            Menampilkan 0 entri
         @else
            Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ number_format($rows->total()) }} entri
         @endif
      </p>
      <div class="flex gap-2">
         @if ($rows->onFirstPage())
            <button type="button" class="p-2 border border-outline-variant/30 rounded-lg opacity-40 cursor-not-allowed" disabled aria-disabled="true"><span class="material-symbols-outlined text-sm">chevron_left</span></button>
         @else
            <a href="{{ $rows->previousPageUrl() }}" class="p-2 border border-outline-variant/30 rounded-lg hover:bg-white hover:shadow-md transition-all inline-flex" rel="prev"><span class="material-symbols-outlined text-sm">chevron_left</span></a>
         @endif
         @if (! $rows->hasMorePages())
            <button type="button" class="p-2 border border-outline-variant/30 rounded-lg opacity-40 cursor-not-allowed" disabled aria-disabled="true"><span class="material-symbols-outlined text-sm">chevron_right</span></button>
         @else
            <a href="{{ $rows->nextPageUrl() }}" class="p-2 border border-outline-variant/30 rounded-lg hover:bg-white hover:shadow-md transition-all inline-flex" rel="next"><span class="material-symbols-outlined text-sm">chevron_right</span></a>
         @endif
      </div>
   </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
  var modal = document.getElementById('vtbc-import-modal');
  var openBtn = document.getElementById('vtbc-open-import-modal');
  function openModal() {
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  function closeModal() {
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
  if (openBtn) openBtn.addEventListener('click', openModal);
  if (modal) {
    modal.querySelectorAll('[data-vtbc-close-modal]').forEach(function (el) {
      el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeModal();
    });
  }
  @if ($openImportModal)
  openModal();
  @endif
})();
document.addEventListener('DOMContentLoaded', function () {
  @if (session('notify_success'))
  Swal.fire({
    icon: 'success',
    title: 'Berhasil',
    text: @json(session('notify_success')),
    confirmButtonColor: '#3952bc'
  });
  @endif
  @if (session('notify_error'))
  Swal.fire({
    icon: 'error',
    title: 'Import tidak bisa dilanjutkan',
    text: @json(session('notify_error')),
    width: '560px',
    confirmButtonText: 'Mengerti',
    confirmButtonColor: '#3952bc'
  });
  @endif
});
</script>
@if ($hasPendingImport)
<script>
(function () {
  setTimeout(function () {
    window.location.reload();
  }, 6500);
})();
</script>
@endif
@endsection
