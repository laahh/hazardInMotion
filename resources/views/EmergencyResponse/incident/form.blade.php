@extends('EmergencyResponse.layouts.app')

@section('page-title', $incident->exists ? 'Edit Laporan Insiden' : 'Laporkan Insiden')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">{{ $incident->exists ? 'Edit' : '' }} Laporan Insiden</h6></div>
        <div class="card-body">
            <form action="{{ $incident->exists ? route('emergency-response.incident.update', $incident) : route('emergency-response.incident.store') }}" method="POST">
                @csrf
                @if ($incident->exists)
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tanggal & Waktu Kejadian</label>
                        <input type="datetime-local" name="occurred_at" class="form-control" value="{{ old('occurred_at', $incident->occurred_at?->format('Y-m-d\TH:i')) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jenis Insiden</label>
                        <select name="incident_type_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($incidentTypes as $type)
                                <option value="{{ $type->id }}" @selected(old('incident_type_id', $incident->incident_type_id) === $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Site</label>
                        <select name="site_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}" @selected(old('site_id', $incident->site_id) === $site->id)>{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tingkat Keparahan</label>
                        <select name="severity_level_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($severityLevels as $level)
                                <option value="{{ $level->id }}" @selected(old('severity_level_id', $incident->severity_level_id) === $level->id)>{{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tingkat Prioritas</label>
                        <select name="priority_level_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($priorityLevels as $level)
                                <option value="{{ $level->id }}" @selected(old('priority_level_id', $incident->priority_level_id) === $level->id)>{{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Detail Lokasi</label>
                        <input type="text" name="location_detail" class="form-control" value="{{ old('location_detail', $incident->location_detail) }}">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Latitude</label>
                        <input type="number" step="any" name="latitude" class="form-control" value="{{ old('latitude', $incident->latitude) }}">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Longitude</label>
                        <input type="number" step="any" name="longitude" class="form-control" value="{{ old('longitude', $incident->longitude) }}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Deskripsi Kejadian</label>
                    <textarea name="description" class="form-control" rows="4" required>{{ old('description', $incident->description) }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jumlah Korban</label>
                        <input type="number" name="victim_count" class="form-control" value="{{ old('victim_count', $incident->victim_count ?? 0) }}" min="0">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Potensi Bahaya Lanjutan</label>
                        <input type="text" name="potential_hazards" class="form-control" value="{{ old('potential_hazards', $incident->potential_hazards) }}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bantuan yang Dibutuhkan</label>
                    <textarea name="assistance_needed" class="form-control" rows="2">{{ old('assistance_needed', $incident->assistance_needed) }}</textarea>
                </div>

                <h6 class="text-sm text-uppercase text-secondary-light mb-16 mt-16">Kontak Pelapor</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nama Pelapor</label>
                        <input type="text" name="reporter_name" class="form-control" value="{{ old('reporter_name', $incident->reporter_name ?? auth()->user()->name) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nomor Kontak</label>
                        <input type="text" name="reporter_phone" class="form-control" value="{{ old('reporter_phone', $incident->reporter_phone) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Unit/Departemen</label>
                        <input type="text" name="reporter_department" class="form-control" value="{{ old('reporter_department', $incident->reporter_department) }}">
                    </div>
                </div>

                <div class="d-flex gap-2 mt-16">
                    <button type="submit" class="btn btn-danger">{{ $incident->exists ? 'Simpan Perubahan' : 'Kirim Laporan' }}</button>
                    <a href="{{ route('emergency-response.incident.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
