{{--
  Tabel riwayat upload Master Roster — dipakai halaman Upload dan Approval.
  $daftar: LengthAwarePaginator<DmsRosterMasterUpload>
  $hapus:  true = tampilkan tombol Hapus batch (halaman Upload) bagi pengupload/admin.
--}}
@include('dms.roster-master._ui-css')
@php
  $badge = [
      'PENDING' => ['Menunggu approval', 'warning'],
      'APPROVED' => ['Disetujui', 'success'],
      'REJECTED' => ['Ditolak', 'danger'],
  ];
  $hapus = (bool) ($hapus ?? false);
  $pengguna = auth()->user();
@endphp
<div class="table-responsive">
  <table class="table bordered-table mb-0 w-100 rm-daftar">
    <thead>
      <tr>
        <th>Judul</th>
        <th>Periode</th>
        <th>Isi</th>
        <th>Status</th>
        <th>Diupload</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($daftar as $u)
        @php [$label, $warna] = $badge[$u->status] ?? [$u->status, 'secondary']; @endphp
        <tr>
          <td class="fw-medium rm-daftar__judul">{{ $u->judul }}<span class="d-block text-xs text-secondary-light">{{ $u->nama_file }}</span></td>
          <td class="text-nowrap">{{ $u->periode_mulai->format('d/m/Y') }} – {{ $u->periode_selesai->format('d/m/Y') }}</td>
          <td class="text-nowrap">
            {{ number_format($u->jumlah_karyawan, 0, ',', '.') }} karyawan
            <span class="d-block text-xs text-secondary-light">{{ number_format($u->jumlah_jadwal, 0, ',', '.') }} jadwal</span>
          </td>
          <td class="text-nowrap">
            <span class="badge bg-{{ $warna }}-focus text-{{ $warna }}-main">{{ $label }}</span>
            @if ($u->direview_oleh)
              <span class="d-block text-xs text-secondary-light mt-4">{{ $u->direview_oleh }} · {{ $u->direview_pada?->format('d/m/Y H:i') }}</span>
            @endif
          </td>
          <td class="text-nowrap">{{ $u->diupload_oleh }}<span class="d-block text-xs text-secondary-light">{{ $u->created_at?->format('d/m/Y H:i') }}</span></td>
          <td class="text-nowrap">
            <a href="{{ route('dms.master-roster.show', $u->id) }}" class="btn btn-sm btn-outline-primary-600 radius-8">
              {{ $u->isPending() ? 'Review' : 'Lihat' }}
            </a>
            @if ($hapus && $u->bolehDihapusOleh($pengguna))
              <form action="{{ route('dms.master-roster.destroy', $u->id, absolute: false) }}" method="POST" class="d-inline"
                    onsubmit="return confirm({{ json_encode('Hapus batch roster “'.$u->judul.'”?'."\n\n".'Seluruh '.number_format($u->jumlah_jadwal, 0, ',', '.').' jadwal ('.number_format($u->jumlah_karyawan, 0, ',', '.').' karyawan) dari file ini ikut terhapus dan tidak bisa dikembalikan.', JSON_UNESCAPED_UNICODE) }});">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger-600 radius-8 ms-4" title="Hapus batch ini beserta seluruh jadwalnya">Hapus</button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center text-secondary-light py-24">Belum ada roster.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@if ($daftar->hasPages())
  <div class="mt-16">{{ $daftar->links() }}</div>
@endif
