{{--
  /dms/roster-compliance/overview

  Halaman contoh bergaya dashboard /pnc-monitoring/dashboard untuk sisi LIVE.
  Tetap murni statis: belum ada satu pun angka yang berasal dari RFID atau
  database — lihat catatan di partial _roster-overview.
--}}
@extends('dms.layouts.app')

@section('title', 'Ringkasan Roster — Live')

@section('css')
<link rel="stylesheet" href="{{ asset('dms-assets/roster-compliance/roster-overview.css') }}">
@endsection

@section('content')
  @include('dms.partials._roster-overview', [
    'judul' => 'Ringkasan Kepatuhan Roster — Live',
    'subjudul' => 'Safety & Roster · Kepatuhan jam kerja dari scan RFID berjalan',
    'lencana' => ['teks' => 'LIVE', 'kelas' => 'bg-info-focus text-info-main'],
  ])
@endsection

@section('scripts')
  @include('dms.partials._roster-overview-scripts')
@endsection
