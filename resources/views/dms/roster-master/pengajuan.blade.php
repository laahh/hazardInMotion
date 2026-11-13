@extends('dms.layouts.app')

@section('title', 'Approval Pengajuan Edit Roster')

@section('content')
@php
  $badge = [
      'PENDING' => ['Menunggu approval', 'warning'],
      'APPROVED' => ['Disetujui', 'success'],
      'REJECTED' => ['Ditolak', 'danger'],
  ];
@endphp
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Approval Pengajuan Edit Roster</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('dms.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>DMS
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Master Roster</li>
    <li>-</li>
    <li class="fw-medium">Approval Pengajuan Edit Roster</li>
  </ul>
</div>

@include('dms.roster-master._alert')

@unless ($siap)
  <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8" role="alert">
    Fitur ini belum aktif: tabel <code>dms_roster_pengajuan</code> belum dimigrasi.
  </div>
@endunless

@if (($departemen ?? null) !== null && $cakupan !== null)
  <div class="alert alert-info bg-info-50 text-info-600 border-info-100 px-24 py-13 mb-24 radius-8 text-sm" role="alert">
    Sebagai manager <b>{{ $departemen }}</b>, Anda melihat pengajuan karyawan {{ $ptDepartemen }} di departemen ini
    ({{ number_format($jumlahSidDepartemen, 0, ',', '.') }} karyawan){{ $cakupan !== [] ? ', ditambah perusahaan: '.implode(', ', $cakupan) : '' }}.
    @if ($jumlahSidDepartemen === 0)
      <br>Daftar karyawan departemen belum terbaca, coba muat ulang beberapa saat lagi.
    @endif
  </div>
@elseif ($cakupan === [])
  <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8" role="alert">
    Akun Anda belum di-assign ke perusahaan mana pun, jadi belum ada pengajuan yang bisa Anda approve.
    Minta admin menambahkan perusahaan Anda di menu <b>Assign Role</b>.
  </div>
@elseif ($cakupan !== null)
  <div class="alert alert-info bg-info-50 text-info-600 border-info-100 px-24 py-13 mb-24 radius-8 text-sm" role="alert">
    Anda hanya melihat pengajuan perusahaan: <b>{{ implode(', ', $cakupan) }}</b>.
  </div>
@endif

@include('dms.roster-master._ui-css')
<div class="rov-kpi rov-kpi--3 mb-24">
  @foreach ([
      ['PENDING', 'Menunggu approval', 'solar:hourglass-bold', 'oranye', 'Perlu direview approver'],
      ['APPROVED', 'Disetujui', 'solar:check-circle-bold', 'hijau', 'Jadwal sudah diperbarui'],
      ['REJECTED', 'Ditolak', 'solar:close-circle-bold', 'merah', 'Jadwal tidak berubah'],
  ] as [$kode, $label, $ikon, $warna, $sub])
    <a href="{{ route('dms.master-roster.pengajuan.index', ['status' => $kode]) }}" class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-{{ $warna }}"><iconify-icon icon="{{ $ikon }}"></iconify-icon></span>
      <div>
        <div class="rov-kpi__label">{{ $label }}</div>
        <div class="rov-kpi__nilai">{{ number_format($ringkas[$kode] ?? 0, 0, ',', '.') }} <span class="text-sm text-secondary-light fw-normal">pengajuan</span></div>
        <div class="rov-kpi__sub">{{ $sub }}</div>
      </div>
    </a>
  @endforeach
</div>

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="text-lg fw-semibold mb-4">Daftar pengajuan</h6>
        <p class="text-sm text-secondary-light mb-0">Pengajuan dari <a href="{{ route('dms.master-roster.edit-form.index', absolute: false) }}" target="_blank" rel="noopener" class="text-primary-600">Form edit per SID</a>. Buka untuk melihat perubahannya, lalu setujui atau tolak.</p>
      </div>
      <form method="GET" action="{{ route('dms.master-roster.pengajuan.index', absolute: false) }}">
        <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()" aria-label="Filter status">
          @foreach (['PENDING' => 'Menunggu approval', 'APPROVED' => 'Disetujui', 'REJECTED' => 'Ditolak', 'SEMUA' => 'Semua status'] as $kode => $label)
            <option value="{{ $kode }}" @selected($status === $kode)>{{ $label }}</option>
          @endforeach
        </select>
      </form>
    </div>
  </div>
  <div class="card-body p-24">
    <div class="table-responsive">
      <table class="table bordered-table mb-0 w-100 rm-daftar">
        <thead>
          <tr>
            <th>Karyawan</th>
            <th>Perusahaan</th>
            <th>Tanggal diubah</th>
            <th>Status</th>
            <th>Diajukan</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($daftar as $p)
            @php
              [$labelStatus, $warnaStatus] = $badge[$p->status] ?? [$p->status, 'secondary'];
              $tgl = array_column($p->perubahan, 'tanggal');
              $awal = \Carbon\CarbonImmutable::parse(reset($tgl));
              $akhir = \Carbon\CarbonImmutable::parse(end($tgl));
            @endphp
            <tr>
              <td class="fw-medium rm-daftar__judul">{{ $p->nama ?? '—' }}<span class="d-block text-xs text-secondary-light">{{ $p->kode_sid }} · {{ $p->jabatan ?? '—' }} · {{ $p->site ?? '—' }}</span></td>
              <td>{{ $p->perusahaan ?? '—' }}</td>
              <td class="text-nowrap">
                {{ count($tgl) }} tanggal
                <span class="d-block text-xs text-secondary-light">{{ $awal->format('d/m/Y') }}{{ count($tgl) > 1 ? ' – '.$akhir->format('d/m/Y') : '' }}</span>
              </td>
              <td class="text-nowrap">
                <span class="badge bg-{{ $warnaStatus }}-focus text-{{ $warnaStatus }}-main">{{ $labelStatus }}</span>
                @if ($p->direview_oleh)
                  <span class="d-block text-xs text-secondary-light mt-4">{{ $p->direview_oleh }} · {{ $p->direview_pada?->format('d/m/Y H:i') }}</span>
                @endif
              </td>
              <td class="text-nowrap">{{ $p->diajukan_oleh }}<span class="d-block text-xs text-secondary-light">{{ $p->created_at?->format('d/m/Y H:i') }}</span></td>
              <td class="text-nowrap">
                <a href="{{ route('dms.master-roster.pengajuan.show', $p->id) }}" class="btn btn-sm btn-outline-primary-600 radius-8">{{ $p->isPending() ? 'Review' : 'Lihat' }}</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-secondary-light py-24">Belum ada pengajuan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($daftar->hasPages())
      <div class="mt-16">{{ $daftar->links() }}</div>
    @endif
  </div>
</div>
@endsection
