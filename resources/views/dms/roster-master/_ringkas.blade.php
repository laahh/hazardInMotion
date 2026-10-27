{{-- Tiga angka ringkas Master Roster; tiap kartu membuka Approval dengan filter statusnya. --}}
@include('dms.roster-master._ui-css')
<div class="rov-kpi rov-kpi--3 mb-24">
  @foreach ([
      ['PENDING', 'Menunggu approval', 'solar:hourglass-bold', 'oranye', 'Perlu direview approver'],
      ['APPROVED', 'Disetujui', 'solar:check-circle-bold', 'hijau', 'Berlaku sebagai jadwal kerja'],
      ['REJECTED', 'Ditolak', 'solar:close-circle-bold', 'merah', 'Perlu diperbaiki & upload ulang'],
  ] as [$kode, $label, $ikon, $warna, $sub])
    <a href="{{ route('dms.master-roster.approval', ['status' => $kode]) }}" class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-{{ $warna }}"><iconify-icon icon="{{ $ikon }}"></iconify-icon></span>
      <div>
        <div class="rov-kpi__label">{{ $label }}</div>
        <div class="rov-kpi__nilai">{{ number_format($ringkas[$kode] ?? 0, 0, ',', '.') }} <span class="text-sm text-secondary-light fw-normal">roster</span></div>
        <div class="rov-kpi__sub">{{ $sub }}</div>
      </div>
    </a>
  @endforeach
</div>
