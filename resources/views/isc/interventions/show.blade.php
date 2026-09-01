@extends('layouts.master')

@section('title', 'Intervensi ISC')

@section('content')
@php
    $demo = $demo ?? false;
    $name = data_get($event, 'name');
    $sid = data_get($event, 'sid') ?: data_get($event, 'person_key');
    $interventions = data_get($event, 'interventions', []);
@endphp
<x-page-title title="ISC" pagetitle="Detail Event" />

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<p><a href="{{ route('isc.interventions.index') }}">← Daftar event</a></p>

@if ($demo)
    <div class="alert alert-info">Preview dummy. Form simpan/verifikasi aktif setelah migration dikonfirmasi.</div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">{{ $name }}</h5>
                <p class="text-muted mb-2">{{ $sid }} · {{ data_get($event, 'job_title') }} · {{ data_get($event, 'company') ?: '—' }}</p>
                <ul class="list-unstyled mb-0">
                    <li>Zona: <strong>{{ data_get($event, 'iupk_site') ?: '—' }}</strong></li>
                    <li>Bahaya: <strong>{{ data_get($event, 'hazard_name') ?: '—' }}</strong></li>
                    <li>Status: <span class="badge bg-secondary">{{ data_get($event, 'status') }}</span></li>
                </ul>
            </div>
        </div>
        @if ($canCreate && ! $demo)
            <div class="card mt-3">
                <div class="card-body">
                    <h6>Catat intervensi</h6>
                    <form method="post" action="{{ route('isc.interventions.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="event_id" value="{{ data_get($event, 'id') }}">
                        <div class="mb-2">
                            <label class="form-label">Jenis</label>
                            <select name="type" class="form-select" required>
                                <option value="himbauan">Himbauan</option>
                                <option value="evakuasi">Evakuasi</option>
                                <option value="penghentian_aktivitas">Penghentian aktivitas</option>
                                <option value="dampingan">Dampingan</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Catatan</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Evidence</label>
                            <input type="file" name="evidences[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                        </div>
                        <button class="btn btn-primary" type="submit">Simpan</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
    <div class="col-lg-7">
        @forelse ($interventions as $intervention)
            @php
                $ivId = data_get($intervention, 'id');
                $ivStatus = data_get($intervention, 'status');
                $evidences = data_get($intervention, 'evidences', []);
                $verification = data_get($intervention, 'verification');
            @endphp
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <strong>{{ data_get($intervention, 'type') }}</strong>
                        <span class="badge bg-{{ $ivStatus === 'verified' ? 'success' : ($ivStatus === 'rejected' ? 'danger' : 'warning') }}">{{ $ivStatus }}</span>
                    </div>
                    <p class="mb-1 text-muted">PIC: {{ data_get($intervention, 'pic.name') ?: data_get($intervention, 'pic_name') ?: '—' }}</p>
                    <p>{{ data_get($intervention, 'notes') ?: '—' }}</p>
                    @if (! empty($evidences))
                        <ul>
                            @foreach ($evidences as $evidence)
                                <li>{{ data_get($evidence, 'original_name') ?: data_get($evidence, 'path') }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($verification)
                        <p class="mb-0 small">Verifikasi: {{ data_get($verification, 'result') }} oleh {{ data_get($verification, 'verifier.name') ?: data_get($verification, 'verifier_name') }} — {{ data_get($verification, 'notes') }}</p>
                    @elseif (! $demo && $ivId && auth()->user()?->can('verify', is_object($intervention) ? $intervention : null))
                        <form method="post" action="{{ route('isc.interventions.verify', $ivId) }}" class="row g-2">
                            @csrf
                            <div class="col-md-3">
                                <select name="result" class="form-select">
                                    <option value="verified">Verified</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-6"><input type="text" name="notes" class="form-control" placeholder="Catatan"></div>
                            <div class="col-md-3"><button class="btn btn-success" type="submit">Verifikasi</button></div>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-muted">Belum ada intervensi.</p>
        @endforelse
    </div>
</div>
@endsection
