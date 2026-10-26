{{-- Legenda kode roster (ringkas: D1–D13, N1–N7, …). --}}
        <div class="d-flex flex-wrap gap-3 text-xs text-secondary-light">
          @foreach (\App\Services\Dms\Roster\DmsRosterMasterService::legenda() as [$kodeLeg, $ketLeg, $warnaLeg, $mLeg, $kLeg])
            <span><span class="rm-sel d-inline-flex align-items-center justify-content-center px-4" style="width:auto;min-width:30px;height:18px;background:#{{ $warnaLeg }}">{{ $kodeLeg }}</span> {{ $ketLeg }}@if ($mLeg) <span class="text-secondary-light">({{ $mLeg }}–{{ $kLeg }})</span>@endif</span>
          @endforeach
          <span><span class="rm-sel rm-kosong d-inline-block align-middle" style="width:30px;height:18px"></span> tidak dijadwalkan</span>
        </div>
