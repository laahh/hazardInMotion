@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Emergency Response')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">Dispatch Board — Insiden Aktif</h6></div>
        <div class="card-body">
            <div class="row gy-4">
                @forelse ($activeIncidents as $incident)
                    <div class="col-md-6 col-lg-4">
                        <div class="card shadow-none border h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-8">
                                    <h6 class="mb-0">{{ $incident->incident_number }}</h6>
                                    <span class="badge bg-info-focus text-info-600 px-8 py-2 radius-4">{{ $incident->statusLabel() }}</span>
                                </div>
                                <p class="text-secondary-light text-sm mb-8">{{ $incident->incidentType->name ?? '-' }} — {{ $incident->site->name ?? '-' }}</p>
                                <p class="text-sm mb-8">Unit dikerahkan: <strong>{{ $incident->responseUnits->count() }}</strong></p>
                                <a href="{{ route('emergency-response.incident.show', $incident) }}" class="btn btn-sm btn-primary-600 w-100">Kelola Respons</a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center text-secondary-light py-40">Tidak ada insiden aktif saat ini.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
