@extends('dms.layouts.app')

@section('title', 'Roster — '.$upload->judul)

@section('css')
<style>
  .rm-grid { border-collapse: separate; border-spacing: 2px; font-size: 11.5px; }
  .rm-grid th, .rm-grid td { white-space: nowrap; }
  .rm-grid thead th { position: sticky; top: 0; background: #fff; z-index: 1; font-weight: 600; color: #64748B; text-align: center; padding: 4px 2px; }
  .rm-grid .rm-id { position: sticky; left: 0; background: #fff; z-index: 2; text-align: left; padding: 4px 10px 4px 0; min-width: 220px; }
  .rm-grid thead .rm-id { z-index: 3; }
  .rm-sel { width: 34px; height: 24px; text-align: center; border-radius: 5px; font-weight: 700; font-size: 10.5px; }
  /* Warna kode sama dengan template Excel (DmsRosterMasterService::KODE). */
  @foreach (\App\Services\Dms\Roster\DmsRosterMasterService::KODE as $kodeCss => [, $warnaCss])
  .rm-{{ $kodeCss }} { background: #{{ $warnaCss }}; color: #1F2937; }
  @endforeach
  .rm-kosong { background: #F8FAFC; }
  .rm-libur { color: #DC2626 !important; }
</style>
@endsection

@section('content')
@php
  $badge = ['PENDING' => ['Menunggu approval', 'warning'], 'APPROVED' => ['Disetujui', 'success'], 'REJECTED' => ['Ditolak', 'danger']];
  [$label, $warna] = $badge[$upload->status] ?? [$upload->status, 'secondary'];
  $hariSingkat = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">{{ $upload->judul }}</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium"><a href="{{ route('dms.master-roster.approval') }}" class="hover-text-primary">Approval Roster</a></li>
    <li>-</li>
    <li class="fw-medium">Detail</li>
  </ul>
</div>

@include('dms.roster-master._alert')

<div class="row gy-4 mb-24">
  <div class="col-xxl-8">
    <div class="card radius-8 border-0 shadow-sm h-100">
      <div class="card-body p-24">
        <div class="d-flex align-items-center gap-2 mb-16">
          <span class="badge bg-{{ $warna }}-focus text-{{ $warna }}-main">{{ $label }}</span>
          <span class="text-sm text-secondary-light">#{{ $upload->id }}</span>
        </div>
        <div class="row gy-3 text-sm">
          <div class="col-sm-6"><span class="d-block text-secondary-light">Periode</span>
            <b>{{ $upload->periode_mulai->format('d/m/Y') }} – {{ $upload->periode_selesai->format('d/m/Y') }}</b>
            ({{ $upload->periode_mulai->diffInDays($upload->periode_selesai) + 1 }} hari)</div>
          <div class="col-sm-6"><span class="d-block text-secondary-light">Isi</span>
            <b>{{ number_format($upload->jumlah_karyawan, 0, ',', '.') }}</b> karyawan · <b>{{ number_format($upload->jumlah_jadwal, 0, ',', '.') }}</b> jadwal</div>
          <div class="col-sm-6"><span class="d-block text-secondary-light">Diupload</span>
            {{ $upload->diupload_oleh }} · {{ $upload->created_at?->format('d/m/Y H:i') }}</div>
          <div class="col-sm-6"><span class="d-block text-secondary-light">File</span>
            <a href="{{ route('dms.master-roster.file', $upload->id) }}" class="text-primary-600">{{ $upload->nama_file }}</a></div>
          @if ($upload->departemen_site)
            <div class="col-sm-6"><span class="d-block text-secondary-light">Departemen / Site</span>{{ $upload->departemen_site }}</div>
          @endif
          @if ($upload->catatan)
            <div class="col-12"><span class="d-block text-secondary-light">Catatan pengupload</span>{{ $upload->catatan }}</div>
          @endif
          @if ($upload->direview_oleh)
            <div class="col-12"><span class="d-block text-secondary-light">Review</span>
              {{ $label }} oleh {{ $upload->direview_oleh }} · {{ $upload->direview_pada?->format('d/m/Y H:i') }}
              @if ($upload->catatan_review) — <i>{{ $upload->catatan_review }}</i> @endif
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4">
    <div class="card radius-8 border-0 shadow-sm h-100">
      <div class="card-body p-24">
        @if ($upload->isPending())
          <h6 class="text-md fw-semibold mb-8">Approval</h6>
          @if ($tumpang > 0)
            <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-12 py-8 mb-12 radius-8 text-sm">
              {{ number_format($tumpang, 0, ',', '.') }} jadwal di roster ini bertumpuk dengan roster lain yang sudah disetujui
              (SID &amp; tanggal sama). Bila disetujui, roster ini yang berlaku untuk tanggal-tanggal itu.
            </div>
          @endif
          <form action="{{ route('dms.master-roster.approve', $upload->id) }}" method="POST" class="mb-12">
            @csrf
            <textarea name="catatan_review" rows="2" class="form-control form-control-sm mb-8" maxlength="1000" placeholder="Catatan (opsional)"></textarea>
            <button type="submit" class="btn btn-success-600 radius-8 w-100 d-inline-flex align-items-center justify-content-center gap-1"
                    onclick="return confirm('Setujui roster ini?');">
              <iconify-icon icon="solar:check-circle-bold"></iconify-icon>Setujui
            </button>
          </form>
          <form action="{{ route('dms.master-roster.reject', $upload->id) }}" method="POST">
            @csrf
            <textarea name="catatan_review" rows="2" class="form-control form-control-sm mb-8" maxlength="1000" placeholder="Alasan penolakan (wajib)" required></textarea>
            <button type="submit" class="btn btn-outline-danger radius-8 w-100 d-inline-flex align-items-center justify-content-center gap-1">
              <iconify-icon icon="solar:close-circle-bold"></iconify-icon>Tolak
            </button>
          </form>
        @else
          <h6 class="text-md fw-semibold mb-8">Status</h6>
          <p class="text-sm text-secondary-light mb-0">Roster ini sudah {{ mb_strtolower($label) }}.</p>
        @endif

        @if ($upload->status !== 'APPROVED')
          <form action="{{ route('dms.master-roster.destroy', $upload->id) }}" method="POST" class="mt-16"
                onsubmit="return confirm('Hapus roster ini beserta seluruh jadwalnya?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-link text-danger-600 p-0">Hapus roster ini</button>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="text-lg fw-semibold mb-4">Jadwal per karyawan</h6>
        <div class="d-flex flex-wrap gap-3 text-xs text-secondary-light">
          @foreach (\App\Services\Dms\Roster\DmsRosterMasterService::KODE as $kodeLeg => [$ketLeg])
            <span><span class="rm-sel rm-{{ $kodeLeg }} d-inline-flex align-items-center justify-content-center" style="width:30px;height:18px">{{ $kodeLeg }}</span> {{ $ketLeg }}</span>
          @endforeach
          <span><span class="rm-sel rm-kosong d-inline-block align-middle" style="width:30px;height:18px"></span> tidak dijadwalkan</span>
        </div>
      </div>
      <form method="GET" action="{{ route('dms.master-roster.show', $upload->id) }}">
        <input type="search" name="q" value="{{ $cari }}" class="form-control form-control-sm" placeholder="Cari SID / nama… (Enter)">
      </form>
    </div>
  </div>
  <div class="card-body p-24">
    <div class="table-responsive" style="max-height:70vh">
      <table class="rm-grid">
        <thead>
          <tr>
            <th class="rm-id">Karyawan</th>
            @foreach ($tanggal as $t)
              <th class="{{ $t->dayOfWeek === 0 ? 'rm-libur' : '' }}" title="{{ $t->format('d/m/Y') }}">
                {{ $t->format('d') }}<span class="d-block fw-normal">{{ $hariSingkat[$t->dayOfWeek] }}</span>
              </th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @forelse ($grid['karyawan'] as $k)
            <tr>
              <td class="rm-id">
                <span class="fw-semibold">{{ $k['nama'] ?? '—' }}</span>
                <span class="d-block text-secondary-light">{{ $k['sid'] }} · {{ $k['perusahaan'] ?? '—' }} · {{ $k['jabatan'] ?? '—' }} · {{ $k['site'] ?? '—' }}</span>
              </td>
              @foreach ($tanggal as $t)
                @php
                  $h = $k['jadwal'][$t->toDateString()] ?? null;
                  $kode = $h['kode'] ?? null;
                  $jamTeks = $h && $h['masuk'] ? ' · '.$h['masuk'].'–'.$h['keluar'].' ('.round($h['menit'] / 60, 2).' jam)' : '';
                @endphp
                <td><div class="rm-sel {{ $kode ? 'rm-'.$kode : 'rm-kosong' }}" title="{{ $t->format('d/m/Y') }} — {{ $kode ?? 'tidak dijadwalkan' }}{{ $jamTeks }}">{{ $kode }}</div></td>
              @endforeach
            </tr>
          @empty
            <tr><td colspan="{{ count($tanggal) + 1 }}" class="text-secondary-light py-16">Tidak ada karyawan yang cocok.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-16">
      <span class="text-sm text-secondary-light">{{ number_format($grid['total'], 0, ',', '.') }} karyawan · hal {{ $halaman }}/{{ $totalHalaman }}</span>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman <= 1 ? 'disabled' : '' }}"
           href="{{ request()->fullUrlWithQuery(['hal' => $halaman - 1]) }}">&laquo; Sebelumnya</a>
        <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman >= $totalHalaman ? 'disabled' : '' }}"
           href="{{ request()->fullUrlWithQuery(['hal' => $halaman + 1]) }}">Berikutnya &raquo;</a>
      </div>
    </div>
  </div>
</div>
@endsection
