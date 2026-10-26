{{--
  Tabel riwayat upload Master Roster — dipakai halaman Upload dan Approval.
  $daftar: LengthAwarePaginator<DmsRosterMasterUpload>
--}}
@php
  $badge = [
      'PENDING' => ['Menunggu approval', 'warning'],
      'APPROVED' => ['Disetujui', 'success'],
      'REJECTED' => ['Ditolak', 'danger'],
  ];
@endphp
<div class="table-responsive">
  <table class="table bordered-table mb-0 w-100">
    <thead>
      <tr>
        <th>Judul</th>
        <th>Periode</th>
        <th class="text-end">Karyawan</th>
        <th class="text-end">Jadwal</th>
        <th>Status</th>
        <th>Diupload</th>
        <th>Direview</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($daftar as $u)
        @php [$label, $warna] = $badge[$u->status] ?? [$u->status, 'secondary']; @endphp
        <tr>
          <td class="fw-medium">{{ $u->judul }}<span class="d-block text-xs text-secondary-light">{{ $u->nama_file }}</span></td>
          <td class="text-nowrap">{{ $u->periode_mulai->format('d/m/Y') }} – {{ $u->periode_selesai->format('d/m/Y') }}</td>
          <td class="text-end">{{ number_format($u->jumlah_karyawan, 0, ',', '.') }}</td>
          <td class="text-end">{{ number_format($u->jumlah_jadwal, 0, ',', '.') }}</td>
          <td><span class="badge bg-{{ $warna }}-focus text-{{ $warna }}-main">{{ $label }}</span></td>
          <td class="text-nowrap">{{ $u->diupload_oleh }}<span class="d-block text-xs text-secondary-light">{{ $u->created_at?->format('d/m/Y H:i') }}</span></td>
          <td class="text-nowrap">
            @if ($u->direview_oleh)
              {{ $u->direview_oleh }}<span class="d-block text-xs text-secondary-light">{{ $u->direview_pada?->format('d/m/Y H:i') }}</span>
            @else
              <span class="text-secondary-light">—</span>
            @endif
          </td>
          <td class="text-nowrap">
            <a href="{{ route('dms.master-roster.show', $u->id) }}" class="btn btn-sm btn-outline-primary-600 radius-8">
              {{ $u->isPending() ? 'Review' : 'Lihat' }}
            </a>
          </td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-secondary-light py-24">Belum ada roster.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@if ($daftar->hasPages())
  <div class="mt-16">{{ $daftar->links() }}</div>
@endif
