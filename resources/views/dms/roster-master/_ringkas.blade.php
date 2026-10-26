{{-- Tiga angka ringkas Master Roster; tiap kartu membuka Approval dengan filter statusnya. --}}
<div class="row gy-4 mb-24">
  @foreach ([
      ['PENDING', 'Menunggu approval', 'solar:hourglass-line-bold', 'warning'],
      ['APPROVED', 'Disetujui', 'solar:check-circle-bold', 'success'],
      ['REJECTED', 'Ditolak', 'solar:close-circle-bold', 'danger'],
  ] as [$kode, $label, $ikon, $warna])
    <div class="col-md-4">
      <a href="{{ route('dms.master-roster.approval', ['status' => $kode]) }}" class="card p-3 shadow-2 radius-8 border h-100 d-block">
        <div class="d-flex align-items-center gap-2">
          <span class="w-44-px h-44-px bg-{{ $warna }}-focus text-{{ $warna }}-main flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-0">
            <iconify-icon icon="{{ $ikon }}" class="icon"></iconify-icon>
          </span>
          <div>
            <span class="d-block fw-medium text-secondary-light text-sm">{{ $label }}</span>
            <h6 class="fw-semibold mb-0">{{ number_format($ringkas[$kode] ?? 0, 0, ',', '.') }} <span class="text-sm text-secondary-light fw-normal">roster</span></h6>
          </div>
        </div>
      </a>
    </div>
  @endforeach
</div>
