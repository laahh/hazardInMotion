@extends('dms.layouts.app')

@section('title', 'Pengajuan — '.$pengajuan->kode_sid)

@section('css')
@include('dms.roster-master._grid-css')
@include('dms.roster-master._ttd-css')
<style>
  .rp-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 20px; }
  .rp-info span { display: block; font-size: 12px; color: #64748B; }
  .rp-info b { font-weight: 600; color: #0F172A; font-size: 14px; }
  .rp-perubahan .rm-sel { display: inline-block; min-width: 44px; padding: 0 6px; }
</style>
@endsection

@section('content')
@php
  $badge = ['PENDING' => ['Menunggu approval', 'warning'], 'APPROVED' => ['Disetujui', 'success'], 'REJECTED' => ['Ditolak', 'danger']];
  [$label, $warna] = $badge[$pengajuan->status] ?? [$pengajuan->status, 'secondary'];
  $kelasKode = static fn (?string $k): string => $k === null || $k === '' ? 'rm-kosong'
      : (isset(\App\Services\Dms\Roster\DmsRosterMasterService::KODE[$k]) ? 'rm-'.$k : 'rm-lain');
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Pengajuan {{ $pengajuan->kode_sid }} — {{ $pengajuan->nama ?? '—' }}</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium"><a href="{{ route('dms.master-roster.pengajuan.index') }}" class="hover-text-primary">Approval Pengajuan Edit Roster</a></li>
    <li>-</li>
    <li class="fw-medium">Detail</li>
  </ul>
</div>

@include('dms.roster-master._alert')

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-4">Perubahan yang diajukan</h6>
    <p class="text-sm text-secondary-light mb-0">Pastikan SID dan nama sesuai, karena form ini bisa diisi tanpa login.</p>
  </div>
  <div class="card-body p-24">
    <div class="rp-info">
      <div><span>SID</span><b>{{ $pengajuan->kode_sid }}</b></div>
      <div><span>Nama</span><b>{{ $pengajuan->nama ?? '—' }}</b></div>
      <div><span>Perusahaan</span><b>{{ $pengajuan->perusahaan ?? '—' }}</b></div>
      <div><span>Jabatan</span><b>{{ $pengajuan->jabatan ?? '—' }}</b></div>
      <div><span>Site</span><b>{{ $pengajuan->site ?? '—' }}</b></div>
      <div><span>Diajukan oleh</span><b>{{ $pengajuan->diajukan_oleh }}</b> <span>{{ $pengajuan->created_at?->format('d/m/Y H:i') }}</span></div>
    </div>
    @if ($pengajuan->catatan)
      <p class="text-sm mb-16"><span class="d-block text-xs text-secondary-light">Keterangan pengaju</span>{{ $pengajuan->catatan }}</p>
    @endif
    <div class="table-responsive">
      <table class="table bordered-table mb-0 rp-perubahan">
        <thead><tr><th>Tanggal</th><th>Kode saat diajukan</th><th>Kode baru</th>@if ($pengajuan->isPending())<th>Kode saat ini</th>@endif</tr></thead>
        <tbody>
          @foreach ($pengajuan->perubahan as $u)
            @php $kini = $kodeKini[$u['tanggal']] ?? null; @endphp
            <tr>
              <td class="text-nowrap">{{ \Carbon\CarbonImmutable::parse($u['tanggal'])->locale('id')->translatedFormat('D, d M Y') }}</td>
              <td><span class="rm-sel {{ $kelasKode($u['lama']) }}">{{ $u['lama'] ?? '–' }}</span></td>
              <td><span class="rm-sel {{ $kelasKode($u['baru']) }}">{{ $u['baru'] }}</span></td>
              @if ($pengajuan->isPending())
                <td>
                  <span class="rm-sel {{ $kelasKode($kini) }}">{{ $kini ?? '–' }}</span>
                  @if ($kini !== $u['lama'])
                    <span class="text-xs text-warning-600 ms-4">sudah berubah sejak diajukan</span>
                  @endif
                </td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- ══════════ APPROVAL + TANDA TANGAN ══════════ --}}
<div class="card radius-8 border-0 shadow-sm mt-24" id="rmApproval">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-4 d-flex align-items-center gap-2">
      Approval &amp; tanda tangan
      <span class="badge bg-{{ $warna }}-focus text-{{ $warna }}-main">{{ $label }}</span>
    </h6>
    <p class="text-sm text-secondary-light mb-0">Bila disetujui, jadwal {{ count($pengajuan->perubahan) }} tanggal di atas langsung diperbarui dan tercatat di Riwayat edit.</p>
  </div>
  <div class="card-body p-24">
    @if ($pengajuan->isPending())
      @include('dms.roster-master._ttd-form', [
          'aksiSetuju' => route('dms.master-roster.pengajuan.approve', $pengajuan->id, absolute: false),
          'aksiTolak' => route('dms.master-roster.pengajuan.reject', $pengajuan->id, absolute: false),
          'objek' => 'pengajuan',
          'subTolak' => 'Jadwal tidak berubah',
          'contohSetuju' => 'Mis. sudah dikonfirmasi ke pengawas',
          'contohTolak' => 'Mis. tanggal tersebut tetap masuk sesuai rencana kerja',
      ])
    @elseif ($pengajuan->status === 'APPROVED')
      <div class="d-flex flex-wrap gap-24 align-items-end">
        <div class="rm-ttd-hasil">
          <span class="d-block text-xs text-secondary-light mb-4">Disetujui oleh</span>
          @if ($pengajuan->ttd_path)
            <img src="{{ route('dms.master-roster.pengajuan.ttd', $pengajuan->id) }}" alt="Tanda tangan {{ $pengajuan->direview_oleh }}">
          @endif
          <span class="d-block fw-semibold mt-4">{{ $pengajuan->direview_oleh }}</span>
          <span class="d-block text-xs text-secondary-light">
            {{ $pengajuan->direview_pada?->format('d/m/Y H:i') }}
            @if ($pengajuan->ttd_sumber) &middot; {{ $pengajuan->ttd_sumber === 'UPLOAD' ? 'gambar di-upload' : 'digambar' }} @endif
          </span>
        </div>
        @if ($pengajuan->catatan_review)
          <div class="text-sm"><span class="d-block text-secondary-light text-xs">Catatan</span>{{ $pengajuan->catatan_review }}</div>
        @endif
      </div>
    @else
      <p class="text-sm mb-0">Ditolak oleh <b>{{ $pengajuan->direview_oleh }}</b> · {{ $pengajuan->direview_pada?->format('d/m/Y H:i') }}
        @if ($pengajuan->catatan_review) — <i>{{ $pengajuan->catatan_review }}</i> @endif</p>
    @endif
  </div>
</div>
@endsection

@section('page-scripts')
@if ($pengajuan->isPending())
@include('dms.roster-master._ttd-js', ['objek' => 'pengajuan'])
@endif
@endsection
