@extends('BaseRule.layouts.app')

@section('title', 'Gap Evaluasi')

@push('head')
@include('BaseRule.partials.styles')
<style>
   .gap-eval-card { min-height: 6.5rem; }
   .gap-eval-table { max-height: 22rem; overflow: auto; }
</style>
@endpush

@section('content')
@include('BaseRule.partials.page-header', [
   'title' => 'Gap Evaluasi',
   'subtitle' => 'Evaluasi harian gap Identifikasi & Intervensi (scrape) + efektivitas pasca tindak lanjut tasklist',
   'breadcrumb' => 'Gap Evaluasi',
])

@include('BaseRule.partials.filter-bar', [
   'actionRoute' => 'hsecm.gap-evaluasi',
   'filters' => $filters,
   'filterOptions' => $filterOptions,
   'showCompany' => true,
   'showSearch' => false,
   'showDate' => true,
])

@if($periodLabel ?? null)
<div class="mb-6 flex flex-wrap items-center gap-2 text-sm text-on-surface-variant">
   <span class="material-symbols-outlined text-base text-primary">compare_arrows</span>
   <span class="font-semibold text-on-background">Periode:</span>
   <span>{{ $periodLabel }}</span>
   @if(!empty($slotsD))
      <span class="hsecm-badge ml-1">{{ count($slotsD) }} slot hari evaluasi</span>
   @endif
   @if(!empty($slotsPrev))
      <span class="hsecm-badge">{{ count($slotsPrev) }} slot hari pembanding</span>
   @endif
</div>
@endif

<p class="mb-4 text-xs text-on-surface-variant">
   Pakai <strong>Tanggal Dari</strong> sebagai hari evaluasi (D). Hari pembanding = tanggal scrape sebelumnya.
   Status tasklist tidak dipakai di Blok A; Blok B menilai perbaikan scrape setelah item di-submit/ACC.
</p>

@include('BaseRule.gap-evaluasi.partials._cards-scrape', ['scrape' => $scrape])
@include('BaseRule.gap-evaluasi.partials._cards-tasklist', ['tasklist' => $tasklist])

<div class="space-y-8 mt-8">
   @include('BaseRule.gap-evaluasi.partials._table', [
      'title' => 'Tidak ada perbaikan — masih berulang (tetap)',
      'hint' => 'Ada di hari pembanding dan masih muncul di hari evaluasi',
      'rows' => $scrape['details']['tetap'] ?? [],
      'truncated' => (int) ($scrape['truncated']['tetap'] ?? 0),
      'tone' => 'danger',
   ])
   @include('BaseRule.gap-evaluasi.partials._table', [
      'title' => 'Perbaikan scrape (hilang)',
      'hint' => 'Ada di hari pembanding, tidak muncul lagi di hari evaluasi',
      'rows' => $scrape['details']['hilang'] ?? [],
      'truncated' => (int) ($scrape['truncated']['hilang'] ?? 0),
      'tone' => 'success',
   ])
   @include('BaseRule.gap-evaluasi.partials._table', [
      'title' => 'Gap baru',
      'hint' => 'Muncul di hari evaluasi, belum ada di hari pembanding',
      'rows' => $scrape['details']['baru'] ?? [],
      'truncated' => (int) ($scrape['truncated']['baru'] ?? 0),
      'tone' => 'warning',
   ])
   @include('BaseRule.gap-evaluasi.partials._table', [
      'title' => 'Kembali muncul (re-open)',
      'hint' => 'Muncul lagi setelah pernah absen / improve sebelumnya',
      'rows' => $scrape['details']['kembali'] ?? [],
      'truncated' => (int) ($scrape['truncated']['kembali'] ?? 0),
      'tone' => 'warning',
   ])

   @include('BaseRule.gap-evaluasi.partials._table-tasklist', [
      'title' => 'Ditindaklanjuti + perbaikan scrape',
      'hint' => 'Sudah submit/ACC tasklist dan key tidak ada di scrape hari evaluasi',
      'rows' => $tasklist['details']['tindaklanjut_berhasil'] ?? [],
      'tone' => 'success',
   ])
   @include('BaseRule.gap-evaluasi.partials._table-tasklist', [
      'title' => 'Ditindaklanjuti + masih gap',
      'hint' => 'Sudah submit/ACC tetapi key masih muncul di scrape hari evaluasi',
      'rows' => $tasklist['details']['tindaklanjut_belum_efektif'] ?? [],
      'tone' => 'danger',
   ])
   @include('BaseRule.gap-evaluasi.partials._table-tasklist', [
      'title' => 'Belum ditindaklanjuti + masih gap',
      'hint' => 'Item tasklist masih open/rejected dan gap masih ada di scrape',
      'rows' => $tasklist['details']['belum_tindaklanjut_masih_gap'] ?? [],
      'tone' => 'warning',
   ])
</div>
@endsection
