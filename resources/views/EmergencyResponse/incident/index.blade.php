@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Incident Reporting')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Daftar Insiden</h6>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('emergency-response.incident.export') }}" class="btn btn-outline-secondary btn-sm"><i class="ri-file-excel-2-line"></i> Export</a>
                <a href="{{ route('emergency-response.incident.create') }}" class="btn btn-danger btn-sm"><i class="ri-alarm-warning-line"></i> Laporkan Insiden</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-16">
                <div class="col-md-3">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor insiden...">
                </div>
                <div class="col-md-3">
                    <select name="site_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Site</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected(request('site_id') === $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="incident_type_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Jenis</option>
                        @foreach ($incidentTypes as $type)
                            <option value="{{ $type->id }}" @selected(request('incident_type_id') === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-outline-primary-600 w-100"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead>
                        <tr>
                            <th>No. Insiden</th>
                            <th>Jenis</th>
                            <th>Keparahan</th>
                            <th>Prioritas</th>
                            <th>Site</th>
                            <th>Status</th>
                            <th>Dilaporkan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($incidents as $incident)
                            <tr class="{{ $incident->is_possible_duplicate ? 'table-warning' : '' }}">
                                <td>{{ $incident->incident_number }} @if($incident->is_possible_duplicate)<i class="ri-error-warning-line text-warning-main" title="Kemungkinan duplikat"></i>@endif</td>
                                <td>{{ $incident->incidentType->name ?? '-' }}</td>
                                <td>{{ $incident->severityLevel->name ?? '-' }}</td>
                                <td>{{ $incident->priorityLevel->name ?? '-' }}</td>
                                <td>{{ $incident->site->name ?? '-' }}</td>
                                <td><span class="badge bg-info-focus text-info-600 px-16 py-4 radius-4">{{ $incident->statusLabel() }}</span></td>
                                <td>{{ $incident->reported_at->format('d M Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('emergency-response.incident.show', $incident) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-eye-line"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-secondary-light py-24">Belum ada laporan insiden.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $incidents->links() }}</div>
        </div>
    </div>
@endsection
