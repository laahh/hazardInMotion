{{-- 1 parameter = summary cards + tabel Site/Perusahaan vertikal (+ modal detail) --}}
@php
   $key = (string) ($program['key'] ?? '');
   $label = (string) ($program['label'] ?? $key);
   $totalGap = (int) ($program['total_gap'] ?? 0);
   $totalPerulangan = (int) ($program['total_perulangan'] ?? 0);
   $perbaikanTanpa = (int) ($program['perbaikan_tanpa_perulangan'] ?? 0);
   $matrixRows = $program['matrix_rows'] ?? [];
@endphp
<div
   id="gap-program-{{ $key }}"
   class="gap-program-strip hsecm-card rounded-2xl overflow-hidden border border-slate-100"
   data-program-key="{{ $key }}"
>
   <div class="px-5 py-3 border-b border-slate-100 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
         <h3 class="font-headline font-bold text-base text-on-background">{{ $label }}</h3>
         <p class="text-xs text-on-surface-variant mt-0.5">Ringkasan per Site → Perusahaan · klik angka untuk detail</p>
      </div>
      <span class="inline-flex items-center rounded-md bg-teal-600 text-white text-xs font-bold px-2.5 py-1 tracking-wide shrink-0">
         {{ number_format($totalGap) }} / {{ number_format($totalPerulangan) }} / {{ number_format($perbaikanTanpa) }}
      </span>
   </div>

   <div class="px-4 pt-4">
      @include('BaseRule.gap-evaluasi.partials._overview-cards', [
         'wrapClass' => 'mb-4',
         'overview' => [
            'total_gap' => (int) ($program['total_gap'] ?? 0),
            'total_perulangan' => (int) ($program['total_perulangan'] ?? 0),
            'perbaikan_total' => (int) ($program['perbaikan_total'] ?? 0),
            'perbaikan_tanpa_perulangan' => (int) ($program['perbaikan_tanpa_perulangan'] ?? 0),
            'tindaklanjut_berhasil' => (int) ($program['tindaklanjut_berhasil'] ?? 0),
            'tindaklanjut_tanpa_perulangan' => (int) ($program['tindaklanjut_tanpa_perulangan'] ?? 0),
         ],
      ])
   </div>

   <div class="border-t border-slate-100">
      <div class="px-5 py-3 border-b border-slate-50">
         <p class="text-xs font-semibold text-on-background">Matriks Site / Perusahaan</p>
      </div>
      <div class="overflow-x-auto">
         <table class="hsecm-table w-full text-sm">
            <thead>
               <tr>
                  <th class="px-4 py-2 text-left">Site</th>
                  <th class="px-4 py-2 text-left">Perusahaan</th>
                  <th class="px-4 py-2 text-right">Total Gap</th>
                  <th class="px-4 py-2 text-right">Total Perulangan</th>
                  <th class="px-4 py-2 text-right">Perbaikan tanpa Perulangan</th>
               </tr>
            </thead>
            <tbody>
               @forelse($matrixRows as $m)
               @php
                  $g = (int) ($m['total_gap'] ?? 0);
                  $p = (int) ($m['total_perulangan'] ?? 0);
                  $b = (int) ($m['perbaikan_tanpa_perulangan'] ?? 0);
                  $scopeKey = (string) ($m['key'] ?? '');
                  $scopeTitle = trim(($m['site'] ?? '').' · '.($m['company_code'] ?? ''));
               @endphp
               <tr class="border-t border-slate-50">
                  <td class="px-4 py-2 font-semibold text-on-background whitespace-nowrap">{{ $m['site'] }}</td>
                  <td class="px-4 py-2 whitespace-nowrap" title="{{ $m['company_name'] ?? '' }}">
                     <span class="font-semibold">{{ $m['company_code'] }}</span>
                     @if(($m['company_name'] ?? '') !== '' && ($m['company_name'] ?? '') !== ($m['company_code'] ?? ''))
                        <span class="block text-[11px] text-on-surface-variant">{{ $m['company_name'] }}</span>
                     @endif
                  </td>
                  <td class="px-4 py-2 text-right">
                     @if($g > 0)
                     <button type="button" class="gap-eval-open-modal font-semibold text-red-600 underline decoration-red-300 underline-offset-2 hover:text-red-700"
                        data-program-key="{{ $key }}" data-program-label="{{ $label }}" data-scope-key="{{ $scopeKey }}"
                        data-scope-title="{{ $scopeTitle }}" data-metric="gap" data-metric-label="Total Gap">{{ number_format($g) }}</button>
                     @else
                     <span class="font-semibold text-on-surface-variant">0</span>
                     @endif
                  </td>
                  <td class="px-4 py-2 text-right">
                     @if($p > 0)
                     <button type="button" class="gap-eval-open-modal font-semibold text-red-600 underline decoration-red-300 underline-offset-2 hover:text-red-700"
                        data-program-key="{{ $key }}" data-program-label="{{ $label }}" data-scope-key="{{ $scopeKey }}"
                        data-scope-title="{{ $scopeTitle }}" data-metric="perulangan" data-metric-label="Total Perulangan">{{ number_format($p) }}</button>
                     @else
                     <span class="font-semibold text-on-surface-variant">0</span>
                     @endif
                  </td>
                  <td class="px-4 py-2 text-right">
                     @if($b > 0)
                     <button type="button" class="gap-eval-open-modal font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-800"
                        data-program-key="{{ $key }}" data-program-label="{{ $label }}" data-scope-key="{{ $scopeKey }}"
                        data-scope-title="{{ $scopeTitle }}" data-metric="perbaikan" data-metric-label="Perbaikan tanpa Perulangan">{{ number_format($b) }}</button>
                     @else
                     <span class="font-semibold text-on-surface-variant">0</span>
                     @endif
                  </td>
               </tr>
               @empty
               <tr>
                  <td colspan="5" class="px-4 py-8 text-center text-on-surface-variant">Belum ada data scope.</td>
               </tr>
               @endforelse
            </tbody>
         </table>
      </div>
      @php
         $scopeDetailPayload = [];
         foreach ($matrixRows as $mRow) {
            $sk = (string) ($mRow['key'] ?? '');
            if ($sk === '') {
               continue;
            }
            // Hanya kirim detail yang punya data (hemat HTML).
            $gap = $mRow['detail_gap'] ?? [];
            $per = $mRow['detail_perulangan'] ?? [];
            $perb = $mRow['detail_perbaikan'] ?? [];
            if ($gap === [] && $per === [] && $perb === []) {
               continue;
            }
            $scopeDetailPayload[$sk] = [
               'gap' => $gap,
               'perulangan' => $per,
               'perbaikan' => $perb,
            ];
         }
      @endphp
      @if($scopeDetailPayload !== [])
      <script type="application/json" class="gap-eval-scope-json" data-program-key="{{ $key }}">@json($scopeDetailPayload)</script>
      @endif
   </div>
</div>
