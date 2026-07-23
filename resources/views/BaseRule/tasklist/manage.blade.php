@extends('BaseRule.layouts.app')

@section('title', 'Kelola Tasklist')

@push('head')
@include('BaseRule.partials.styles')
@endpush

@section('content')
@include('BaseRule.partials.page-header', [
  'title' => 'Kelola Tasklist #'.$tasklist->id,
  'subtitle' => ($tasklist->site ?: 'Semua Site').' · '.$tasklist->perusahaan,
  'breadcrumb' => 'Tasklist Review',
])

@if(session('success'))
  <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif
@if($errors->any())
  <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
    <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
@endif

<div class="mb-4 hsecm-card rounded-2xl p-4 text-sm flex flex-wrap gap-4 justify-between">
  <div>
    <div>Status: <strong>{{ strtoupper($tasklist->status) }}</strong></div>
    <div>Batch: {{ optional($tasklist->batch_slot)->format('d/m/Y H:i') }}</div>
    <div>Escalate: #{{ $tasklist->escalate_count }}</div>
  </div>
  <div class="text-right">
    <div class="text-xs text-on-surface-variant">Link publik PJO</div>
    <a href="{{ $publicUrl }}" target="_blank" class="text-primary font-semibold text-xs break-all">{{ $publicUrl }}</a>
  </div>
</div>

<div class="hsecm-card rounded-2xl overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-on-surface-variant">
        <tr>
          <th class="px-4 py-3 text-left">Program / Item</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-left">Submit</th>
          <th class="px-4 py-3 text-left">Evidence</th>
          <th class="px-4 py-3 text-left">Review</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        @foreach($items as $item)
          <tr class="align-top">
            <td class="px-4 py-3">
              <div class="font-semibold">{{ $item->title }}</div>
              <div class="text-xs text-on-surface-variant">{{ $item->value_label ?: $item->business_key }}</div>
              <div class="text-xs mt-1">{{ $item->action_hint }}</div>
            </td>
            <td class="px-4 py-3 font-bold">{{ strtoupper($item->status) }}</td>
            <td class="px-4 py-3 text-xs">
              @if($item->submitted_at)
                <div>{{ $item->submitted_by_name }}</div>
                <div>{{ $item->submitted_at->format('d/m/Y H:i') }}</div>
                <div class="mt-1 text-on-surface">{{ $item->remediation_notes }}</div>
              @else
                —
              @endif
              @if($item->rejection_reason)
                <div class="mt-2 text-red-700">Tolak: {{ $item->rejection_reason }}</div>
              @endif
            </td>
            <td class="px-4 py-3 text-xs">
              @forelse($item->evidences as $ev)
                <a href="{{ $ev->publicUrl() }}" target="_blank" class="block text-primary underline mb-1">{{ $ev->original_name }}</a>
              @empty
                —
              @endforelse
            </td>
            <td class="px-4 py-3">
              @if($item->status === 'submitted')
                <form method="POST" action="{{ route('hsecm.tasklist.items.approve', ['itemId' => $item->id]) }}" class="mb-2">
                  @csrf
                  <button class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold">ACC</button>
                </form>
                <form method="POST" action="{{ route('hsecm.tasklist.items.reject', ['itemId' => $item->id]) }}" class="space-y-2">
                  @csrf
                  <textarea name="rejection_reason" rows="2" required class="w-full text-xs rounded-lg border-slate-200" placeholder="Alasan tolak..."></textarea>
                  <button class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-bold">Tolak</button>
                </form>
              @elseif($item->reviewed_at)
                <div class="text-xs">{{ $item->reviewed_by_name }}</div>
                <div class="text-xs text-on-surface-variant">{{ $item->reviewed_at->format('d/m/Y H:i') }}</div>
              @else
                <span class="text-xs text-on-surface-variant">—</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<div class="mt-4">
  <a href="{{ route('hsecm.tasklist.index') }}" class="text-sm text-primary font-semibold">← Kembali ke daftar</a>
</div>
@endsection
