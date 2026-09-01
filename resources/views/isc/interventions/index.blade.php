@extends('layouts.master')

@section('title', 'Risk Intervention Center')

@section('content')
<x-page-title title="ISC" pagetitle="Risk Intervention Center" />

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if ($demo ?? false)
    <div class="alert alert-info">
        Menampilkan <strong>data dummy</strong> alur deteksi → PIC → evidence → verifikasi.
        @if (! ($ready ?? false))
            Tabel event belum dimigrasi; jalankan <code>php artisan migrate</code> setelah konfirmasi untuk data live.
        @endif
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="" @selected($status === '')>Terbuka (open + in progress)</option>
                    <option value="open" @selected($status === 'open')>Open</option>
                    <option value="in_progress" @selected($status === 'in_progress')>In progress</option>
                    <option value="closed" @selected($status === 'closed')>Closed</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary" type="submit">Filter</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Masuk</th>
                        <th>Personel</th>
                        <th>Perusahaan</th>
                        <th>Zona</th>
                        <th>Bahaya</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        @php
                            $id = data_get($event, 'id');
                            $entered = data_get($event, 'entered_at');
                            $enteredLabel = is_object($entered) && method_exists($entered, 'timezone')
                                ? $entered->timezone(config('app.timezone'))->format('d/m/Y H:i')
                                : (is_string($entered) ? \Illuminate\Support\Carbon::parse($entered)->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—');
                        @endphp
                        <tr>
                            <td>{{ $enteredLabel }}</td>
                            <td>
                                <strong>{{ data_get($event, 'name') }}</strong><br>
                                <small class="text-muted">{{ data_get($event, 'sid') ?: data_get($event, 'person_key') }} · {{ data_get($event, 'job_title') }}</small>
                            </td>
                            <td>{{ data_get($event, 'company') ?: '—' }}</td>
                            <td>{{ data_get($event, 'iupk_site') ?: '—' }}</td>
                            <td>{{ data_get($event, 'hazard_name') ?: '—' }}</td>
                            <td>
                                @php $st = data_get($event, 'status'); @endphp
                                <span class="badge bg-{{ $st === 'closed' ? 'secondary' : ($st === 'in_progress' ? 'warning' : 'danger') }}">{{ $st }}</span>
                            </td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('isc.interventions.show', $id) }}">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">Tidak ada event.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (is_object($events) && method_exists($events, 'links'))
            {{ $events->links() }}
        @endif
    </div>
</div>
@endsection
