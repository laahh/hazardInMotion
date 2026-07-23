<!DOCTYPE html>
<html class="light" lang="id">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title>Tasklist — Daily Monitoring</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { primary: '#0f766e', 'on-background': '#1f2937', 'on-surface-variant': '#64748b' },
          fontFamily: { body: ['Poppins'] },
        },
      },
    }
  </script>
</head>
<body class="bg-[#f1f5f9] font-body text-slate-700 min-h-screen">
@php
  $statusTone = [
    'open' => 'bg-slate-100 text-slate-700',
    'submitted' => 'bg-amber-100 text-amber-800',
    'rejected' => 'bg-red-100 text-red-800',
    'approved' => 'bg-emerald-100 text-emerald-800',
  ];
@endphp
<div class="max-w-5xl mx-auto px-4 py-8">
  <div class="bg-white border border-teal-100 rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-teal-700 to-teal-900 text-white px-6 py-5">
      <p class="text-[11px] uppercase tracking-widest font-bold opacity-90">Tasklist Monitoring &amp; Intervensi</p>
      <h1 class="text-xl font-bold mt-1">{{ $tasklist->site ?: 'Semua Site' }} · {{ $tasklist->perusahaan }}</h1>
      <p class="text-sm mt-1 opacity-90">
        Batch {{ optional($tasklist->batch_slot)->format('d/m/Y H:i') }} ·
        Status tasklist: <strong>{{ strtoupper($tasklist->status) }}</strong>
      </p>
    </div>

    <div class="p-6 space-y-5">
      @if(session('success'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
      @endif
      @if($errors->any())
        <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
          <ul class="list-disc pl-4 space-y-1">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @if($tasklist->status === 'closed')
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 px-4 py-3 text-sm font-semibold">
          Tasklist sudah closed — semua item telah di-ACC HSE.
        </div>
      @endif

      <form method="POST" action="{{ route('hsecm.tasklist.submit', ['token' => $tasklist->token]) }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Nama pengirim</label>
            <input type="text" name="submitted_by_name" value="{{ old('submitted_by_name') }}" required
                   class="w-full rounded-xl border-slate-200 text-sm" placeholder="Nama PJO / PIC"
                   @disabled($tasklist->status === 'closed') />
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Catatan perbaikan (untuk item terpilih)</label>
            <textarea name="remediation_notes" rows="2" required class="w-full rounded-xl border-slate-200 text-sm"
                      placeholder="Jelaskan tindakan perbaikan..."
                      @disabled($tasklist->status === 'closed')>{{ old('remediation_notes') }}</textarea>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-100 rounded-xl">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2 text-left">Pilih</th>
                <th class="px-3 py-2 text-left">Program</th>
                <th class="px-3 py-2 text-left">Item</th>
                <th class="px-3 py-2 text-left">Status</th>
                <th class="px-3 py-2 text-left">Aksi / Evidence</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              @forelse($items as $item)
                @php $canSubmit = in_array($item->status, ['open', 'rejected'], true) && $tasklist->status !== 'closed'; @endphp
                <tr class="align-top">
                  <td class="px-3 py-3">
                    @if($canSubmit)
                      <input type="checkbox" name="items[]" value="{{ $item->id }}" class="rounded border-slate-300 text-teal-700" />
                    @else
                      <span class="text-slate-300">—</span>
                    @endif
                  </td>
                  <td class="px-3 py-3">
                    <div class="font-semibold text-slate-800">{{ $item->title }}</div>
                    <div class="text-xs text-slate-500 mt-1">{{ $item->action_hint }}</div>
                  </td>
                  <td class="px-3 py-3">
                    <div>{{ $item->value_label ?: $item->business_key }}</div>
                    @if($item->status === 'rejected' && $item->rejection_reason)
                      <div class="mt-2 text-xs text-red-700 bg-red-50 border border-red-100 rounded-lg px-2 py-1">
                        Ditolak: {{ $item->rejection_reason }}
                      </div>
                    @endif
                    @if($item->remediation_notes)
                      <div class="mt-2 text-xs text-slate-500">Catatan terakhir: {{ $item->remediation_notes }}</div>
                    @endif
                  </td>
                  <td class="px-3 py-3">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold {{ $statusTone[$item->status] ?? 'bg-slate-100' }}">
                      {{ strtoupper($item->status) }}
                    </span>
                  </td>
                  <td class="px-3 py-3 min-w-[12rem]">
                    @if($canSubmit)
                      <input type="file" name="evidence[{{ $item->id }}]" class="block w-full text-xs" accept=".jpg,.jpeg,.png,.pdf,.webp,.doc,.docx,.xls,.xlsx" />
                    @endif
                    @foreach($item->evidences->sortByDesc('id')->take(2) as $ev)
                      <a href="{{ $ev->publicUrl() }}" target="_blank" class="block text-xs text-teal-700 mt-1 underline">
                        {{ $ev->original_name }} (batch {{ $ev->submission_batch }})
                      </a>
                    @endforeach
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Tidak ada item.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($tasklist->status !== 'closed')
          <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-teal-700 text-white text-sm font-bold hover:bg-teal-800">
              Submit item terpilih
            </button>
          </div>
        @endif
      </form>
    </div>
  </div>
</div>
</body>
</html>
