@extends('BaseRule.layouts.app')

@section('title', 'Tasklist Review')

@push('head')
@include('BaseRule.partials.styles')
@endpush

@section('content')
@include('BaseRule.partials.page-header', [
  'title' => 'Tasklist Review',
  'subtitle' => 'ACC atau tolak submit evidence dari PJO/PIC.',
  'breadcrumb' => 'Tasklist Review',
])

@if(session('success'))
  <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<form method="GET" action="{{ route('hsecm.tasklist.index') }}" class="mb-4 flex gap-2 items-end">
  <div>
    <label class="block text-xs font-bold uppercase text-on-surface-variant mb-1">Status</label>
    <select name="status" class="rounded-xl border-slate-200 text-sm">
      <option value="">Semua</option>
      @foreach(['open','partial','closed'] as $st)
        <option value="{{ $st }}" @selected($statusFilter === $st)>{{ strtoupper($st) }}</option>
      @endforeach
    </select>
  </div>
  <button class="px-4 py-2 rounded-xl bg-primary text-white text-sm font-bold">Filter</button>
</form>

<div class="hsecm-card rounded-2xl overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase tracking-wide text-on-surface-variant">
        <tr>
          <th class="px-4 py-3 text-left">Batch</th>
          <th class="px-4 py-3 text-left">Scope</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-left">Items</th>
          <th class="px-4 py-3 text-left">Escalate</th>
          <th class="px-4 py-3 text-left"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        @forelse($tasklists as $tl)
          <tr>
            <td class="px-4 py-3">{{ optional($tl->batch_slot)->format('d/m/Y H:i') }}</td>
            <td class="px-4 py-3">
              <div class="font-semibold">{{ $tl->site ?: 'Semua Site' }}</div>
              <div class="text-xs text-on-surface-variant">{{ $tl->perusahaan }}</div>
            </td>
            <td class="px-4 py-3 font-bold">{{ strtoupper($tl->status) }}</td>
            <td class="px-4 py-3 text-xs">
              O {{ $tl->open_count }} · S {{ $tl->submitted_count }} · R {{ $tl->rejected_count }} · A {{ $tl->approved_count }}
              <div class="text-on-surface-variant">Total {{ $tl->items_count }}</div>
            </td>
            <td class="px-4 py-3 text-xs">
              #{{ $tl->escalate_count }}
              @if($tl->next_escalate_at)
                <div>Next {{ $tl->next_escalate_at->format('d/m H:i') }}</div>
              @endif
            </td>
            <td class="px-4 py-3 text-right">
              <a href="{{ route('hsecm.tasklist.manage', ['id' => $tl->id]) }}" class="text-primary font-bold text-sm">Kelola</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-4 py-8 text-center text-on-surface-variant">Belum ada tasklist.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="px-4 py-3">{{ $tasklists->links() }}</div>
</div>
@endsection
