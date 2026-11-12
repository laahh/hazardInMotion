@extends('dms.layouts.app')

@section('title', 'Roster — '.$upload->judul)

@section('css')
@include('dms.roster-master._grid-css')
@include('dms.roster-master._ttd-css')
@endsection

@section('content')
@php
  $badge = ['PENDING' => ['Menunggu approval', 'warning'], 'APPROVED' => ['Disetujui', 'success'], 'REJECTED' => ['Ditolak', 'danger']];
  [$label, $warna] = $badge[$upload->status] ?? [$upload->status, 'secondary'];
  $hariSingkat = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
  $bulanSingkat = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
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

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div class="rm-kepala-teks">
        <h6 class="text-lg fw-semibold mb-4">Jadwal per karyawan</h6>
        @include('dms.roster-master._legenda')
      </div>
      <form method="GET" action="{{ route('dms.master-roster.show', $upload->id, absolute: false) }}" class="rm-cari">
        <input type="search" name="q" value="{{ $cari }}" class="form-control form-control-sm" placeholder="Cari SID / nama… (Enter)" aria-label="Cari SID atau nama">
      </form>
    </div>
  </div>
  <div class="card-body rm-isi">
    <div class="table-responsive rm-gulir">
      @include('dms.roster-master._grid', ['karyawanGrid' => $grid['karyawan'], 'tanggal' => $tanggal, 'nomorAwal' => ($halaman - 1) * 25])
    </div>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-16">
      <span class="text-sm text-secondary-light">{{ number_format($grid['total'], 0, ',', '.') }} karyawan · hal {{ $halaman }}/{{ $totalHalaman }}</span>
      <div class="d-flex gap-2 rm-hal">
        <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman <= 1 ? 'disabled' : '' }}"
           href="{{ request()->fullUrlWithQuery(['hal' => $halaman - 1]) }}">&laquo; Sebelumnya</a>
        <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman >= $totalHalaman ? 'disabled' : '' }}"
           href="{{ request()->fullUrlWithQuery(['hal' => $halaman + 1]) }}">Berikutnya &raquo;</a>
      </div>
    </div>
  </div>
</div>

{{-- ══════════ APPROVAL + TANDA TANGAN (di bawah tabel jadwal) ══════════ --}}
<div class="card radius-8 border-0 shadow-sm mt-24" id="rmApproval">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="text-lg fw-semibold mb-4 d-flex align-items-center gap-2">
          Approval &amp; tanda tangan
          <span class="badge bg-{{ $warna }}-focus text-{{ $warna }}-main">{{ $label }}</span>
        </h6>
        <p class="text-sm text-secondary-light mb-0">
          {{ $upload->judul }} &middot; {{ $upload->periode_mulai->format('d/m/Y') }} – {{ $upload->periode_selesai->format('d/m/Y') }}
          &middot; {{ number_format($upload->jumlah_karyawan, 0, ',', '.') }} karyawan
          &middot; {{ implode(', ', $ptUpload) }}
          &middot; diupload {{ $upload->namaPengupload() }} {{ $upload->created_at?->format('d/m/Y H:i') }}
          @unless ($upload->isOtomatis() || $upload->path_file === '')
          &middot; <a href="{{ route('dms.master-roster.file', $upload->id) }}" class="text-primary-600">file asli</a>
          @endunless
        </p>
      </div>
      @if ($upload->bolehDihapusOleh(auth()->user()))
        <form action="{{ route('dms.master-roster.destroy', $upload->id, absolute: false) }}" method="POST"
              onsubmit="return confirm('Hapus roster ini beserta seluruh jadwalnya?');">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-sm btn-link text-danger-600 p-0">Hapus roster ini</button>
        </form>
      @endif
    </div>
  </div>
  <div class="card-body p-24">
    @if ($upload->isPending() && ! $bolehReview)
      {{-- Assign Role: approve berlaku untuk seluruh isi file, jadi semua perusahaannya harus di-assign. --}}
      <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-16 py-12 mb-0 radius-8 text-sm">
        Roster ini memuat perusahaan yang tidak di-assign ke akun Anda
        ({{ implode(', ', $ptUpload) }}), jadi Anda hanya bisa melihatnya.
        Approval dilakukan oleh user yang di-assign ke seluruh perusahaan tersebut.
      </div>
    @elseif ($upload->isPending())
      @if ($lingkupDepartemen !== null)
        <div class="alert alert-info bg-info-50 text-info-600 border-info-100 px-12 py-8 mb-16 radius-8 text-sm">
          Keputusan Anda hanya berlaku untuk <b>{{ number_format($lingkupDepartemen['karyawan'], 0, ',', '.') }} karyawan departemen {{ $lingkupDepartemen['nama'] }}</b>
          dari {{ number_format($lingkupDepartemen['total'], 0, ',', '.') }} karyawan di roster ini.
          @if ($lingkupDepartemen['karyawan'] < $lingkupDepartemen['total'])
            Tabel di atas hanya menampilkan karyawan departemen Anda. Karyawan lainnya tetap menunggu approval.
          @endif
        </div>
      @endif
      @if ($tumpang > 0)
        <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-12 py-8 mb-16 radius-8 text-sm">
          {{ number_format($tumpang, 0, ',', '.') }} jadwal di roster ini bertumpuk dengan roster lain yang sudah disetujui
          (SID &amp; tanggal sama). Bila disetujui, roster ini yang berlaku untuk tanggal-tanggal itu.
        </div>
      @endif

      @include('dms.roster-master._ttd-form', [
          'aksiSetuju' => route('dms.master-roster.approve', $upload->id, absolute: false),
          'aksiTolak' => route('dms.master-roster.reject', $upload->id, absolute: false),
          'objek' => 'roster',
          'subTolak' => 'Pengupload perlu memperbaiki lalu upload ulang',
          'contohSetuju' => 'Mis. sudah dicek sesuai rencana kerja site',
          'contohTolak' => 'Mis. jadwal SID 10023451 tanggal 15–20 belum sesuai pola D/N',
      ])
    @elseif ($upload->status === 'APPROVED')
      <div class="d-flex flex-wrap gap-24 align-items-end">
        <div class="rm-ttd-hasil">
          <span class="d-block text-xs text-secondary-light mb-4">Disetujui oleh</span>
          @if ($upload->ttd_path)
            <img src="{{ route('dms.master-roster.ttd', $upload->id) }}" alt="Tanda tangan {{ $upload->direview_oleh }}">
          @else
            <span class="text-secondary-light text-sm">(tanpa tanda tangan)</span>
          @endif
          <span class="d-block fw-semibold mt-4">{{ $upload->direview_oleh }}</span>
          <span class="d-block text-xs text-secondary-light">
            {{ $upload->direview_pada?->format('d/m/Y H:i') }}
            @if ($upload->ttd_sumber) &middot; {{ $upload->ttd_sumber === 'UPLOAD' ? 'gambar di-upload' : 'digambar' }} @endif
          </span>
        </div>
        @if ($upload->catatan_review)
          <div class="text-sm"><span class="d-block text-secondary-light text-xs">Catatan</span>{{ $upload->catatan_review }}</div>
        @endif
      </div>
    @else
      <p class="text-sm mb-0">Ditolak oleh <b>{{ $upload->direview_oleh }}</b> · {{ $upload->direview_pada?->format('d/m/Y H:i') }}
        @if ($upload->catatan_review) — <i>{{ $upload->catatan_review }}</i> @endif</p>
    @endif
  </div>
</div>
@endsection

@section('page-scripts')
@if ($upload->isPending() && $bolehReview)
@include('dms.roster-master._ttd-js', ['objek' => 'roster'])
@endif
@endsection
