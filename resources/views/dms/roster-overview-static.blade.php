{{--
  /dms/roster-compliance-static/overview

  Halaman contoh bergaya dashboard /pnc-monitoring/dashboard untuk sisi
  SNAPSHOT. Murni statis — lihat catatan di partial _roster-overview.
--}}
@extends('dms.layouts.app')

@section('title', 'Ringkasan Roster — Snapshot')

@section('css')
<link rel="stylesheet" href="{{ asset('dms-assets/roster-compliance/roster-overview.css') }}">
@endsection

@section('content')
  @include('dms.partials._roster-overview', [
    'judul' => 'Ringkasan Kepatuhan Roster — Snapshot',
    'subjudul' => 'Safety & Roster · Potret periode 1 Jan – 28 Sep 2026',
    'lencana' => ['teks' => 'DATA CONTOH · SNAPSHOT', 'kelas' => 'bg-warning-focus text-warning-main'],
  ])
@endsection

@section('scripts')
  @include('dms.partials._roster-overview-scripts')
@endsection
