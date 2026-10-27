{{-- Legenda kode roster (ringkas: D1–D13, N1–N7, …). Warnanya dari kelas .rm-KODE di _grid-css. --}}
        <div class="d-flex flex-wrap gap-3 text-xs text-secondary-light">
          @foreach (\App\Services\Dms\Roster\DmsRosterMasterService::legenda() as [$kodeLeg, $ketLeg, $warnaLeg, $mLeg, $kLeg])
            <span><span class="rm-sel rm-{{ explode('–', $kodeLeg)[0] }} d-inline-flex align-items-center justify-content-center px-6" style="width:auto;min-width:30px;height:20px;line-height:20px">{{ $kodeLeg }}</span> {{ $ketLeg }}@if ($mLeg) <span class="text-secondary-light">({{ $mLeg }}–{{ $kLeg }})</span>@endif</span>
          @endforeach
          <span><span class="rm-sel rm-kosong d-inline-block align-middle" style="width:30px;height:20px;border:1px dashed #CBD5E1"></span> tidak dijadwalkan</span>
        </div>
