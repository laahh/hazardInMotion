@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Laporan Insiden')

@section('content')
    <div class="row gy-4 mb-24">
        <div class="col-md-3">
            <div class="card shadow-none border h-100"><div class="card-body text-center"><p class="text-secondary-light text-sm mb-4">Rata-rata Response Time</p><h6 class="mb-0">{{ $avgResponseTime !== null ? $avgResponseTime.' menit' : '-' }}</h6></div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-none border h-100"><div class="card-body text-center"><p class="text-secondary-light text-sm mb-4">Jenis Terbanyak</p><h6 class="mb-0">{{ $byIncidentType->sortDesc()->keys()->first() ?? '-' }}</h6></div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-none border h-100"><div class="card-body text-center"><p class="text-secondary-light text-sm mb-4">Site Terbanyak</p><h6 class="mb-0">{{ $bySite->sortDesc()->keys()->first() ?? '-' }}</h6></div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-none border h-100"><div class="card-body text-center"><p class="text-secondary-light text-sm mb-4">Keparahan Terbanyak</p><h6 class="mb-0">{{ $bySeverity->sortDesc()->keys()->first() ?? '-' }}</h6></div></div>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Laporan Insiden per Periode</h6>
            <a href="{{ route('emergency-response.report.incidents.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm"><i class="ri-file-excel-2-line"></i> Export</a>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-16">
                <div class="col-md-2"><input type="date" name="date_from" value="{{ $dateFrom->format('Y-m-d') }}" class="form-control"></div>
                <div class="col-md-2"><input type="date" name="date_to" value="{{ $dateTo->format('Y-m-d') }}" class="form-control"></div>
                <div class="col-md-3">
                    <select name="site_id" class="form-control">
                        <option value="">Semua Site</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected(request('site_id') === $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="incident_type_id" class="form-control">
                        <option value="">Semua Jenis</option>
                        @foreach ($incidentTypes as $type)
                            <option value="{{ $type->id }}" @selected(request('incident_type_id') === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary-600 w-100">Terapkan</button></div>
            </form>
            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead><tr><th>No. Insiden</th><th>Jenis</th><th>Site</th><th>Keparahan</th><th>Status</th><th>Response Time</th></tr></thead>
                    <tbody>
                        @forelse ($incidents as $incident)
                            <tr>
                                <td><a href="{{ route('emergency-response.incident.show', $incident) }}">{{ $incident->incident_number }}</a></td>
                                <td>{{ $incident->incidentType->name ?? '-' }}</td>
                                <td>{{ $incident->site->name ?? '-' }}</td>
                                <td>{{ $incident->severityLevel->name ?? '-' }}</td>
                                <td>{{ $incident->statusLabel() }}</td>
                                <td>{{ $incident->responseTimeMinutes() !== null ? $incident->responseTimeMinutes().' menit' : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $incidents->links() }}</div>
        </div>
    </div>
@endsection
