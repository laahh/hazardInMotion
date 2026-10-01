{{--
  /dms/roster-compliance-static/overview

  Halaman bergaya dashboard /pnc-monitoring/dashboard untuk sisi SNAPSHOT.
  Angkanya live dari tabel sinkronisasi RFID — lihat catatan di partial
  _roster-overview untuk daftar variabel dan cabang contohnya.
--}}
@extends('dms.layouts.app')

@section('title', 'Ringkasan Roster — Snapshot')

@section('css')
<link rel="stylesheet" href="{{ asset('dms-assets/roster-compliance/roster-overview.css') }}">
@endsection

@section('content')
  @include('dms.partials._roster-overview', [
    'judul' => 'Ringkasan Kepatuhan Roster',
    'subjudul' => 'Safety & Roster · Potret kepatuhan jam kerja karyawan',
    'lencana' => ['teks' => 'SNAPSHOT', 'kelas' => 'bg-warning-focus text-warning-main'],
  ])
@endsection

@section('scripts')
  @include('dms.partials._roster-overview-scripts')
@endsection
