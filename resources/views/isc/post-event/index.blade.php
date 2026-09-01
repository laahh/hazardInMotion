@extends('layouts.master')

@section('title', 'Rekap Post-Event ISC')

@section('content')
<x-page-title title="ISC" pagetitle="Rekap Post-Event" />

@php
    $totals = $report['totals'] ?? [];
    $duration = (int) ($totals['duration_seconds'] ?? 0);
    $hours = intdiv($duration, 3600);
    $mins = intdiv($duration % 3600, 60);
@endphp

@if ($report['demo'] ?? false)
    <div class="alert alert-info">Menampilkan rekap <strong>dummy</strong> untuk preview mekanisme post-event.</div>
@endif

<form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-md-3">
        <label class="form-label">Dari</label>
        <input type="date" name="from" class="form-control" value="{{ $from }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Sampai</label>
        <input type="date" name="to" class="form-control" value="{{ $to }}">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary" type="submit">Terapkan</button>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Frekuensi event</small><h4 class="mb-0">{{ $totals['events'] ?? 0 }}</h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Durasi total</small><h4 class="mb-0">{{ $hours }}j {{ $mins }}m</h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Selesai / verified</small><h4 class="mb-0">{{ $totals['closed'] ?? 0 }} / {{ $totals['verified'] ?? 0 }}</h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Trend ulang (&gt;1)</small><h4 class="mb-0">{{ $totals['repeat_people'] ?? 0 }}</h4></div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <h6>Status</h6>
            <ul class="mb-0">
                @forelse ($report['by_status'] ?? [] as $row)<li>{{ $row['key'] }}: {{ $row['count'] }}</li>
                @empty<li class="text-muted">Tidak ada data.</li>@endforelse
            </ul>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <h6>Per site</h6>
            <ul class="mb-0">
                @forelse ($report['by_site'] ?? [] as $row)<li>{{ $row['key'] }}: {{ $row['count'] }}</li>
                @empty<li class="text-muted">Tidak ada data.</li>@endforelse
            </ul>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <h6>Per perusahaan</h6>
            <ul class="mb-0">
                @forelse ($report['by_company'] ?? [] as $row)<li>{{ $row['key'] }}: {{ $row['count'] }}</li>
                @empty<li class="text-muted">Tidak ada data.</li>@endforelse
            </ul>
        </div></div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <h6>Personel berulang</h6>
        <table class="table table-sm">
            <thead><tr><th>Nama</th><th>SID</th><th>Perusahaan</th><th>Event</th></tr></thead>
            <tbody>
                @forelse ($report['repeat_offenders'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['sid'] ?: '—' }}</td>
                        <td>{{ $row['company'] ?: '—' }}</td>
                        <td>{{ $row['count'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Tidak ada pengulangan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
