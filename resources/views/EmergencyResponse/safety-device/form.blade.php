@extends('EmergencyResponse.layouts.app')

@section('page-title', $device->exists ? 'Edit Safety Device' : 'Tambah Safety Device')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header">
            <h6 class="mb-0">{{ $device->exists ? 'Edit' : 'Tambah' }} Safety Device</h6>
        </div>
        <div class="card-body">
            <form action="{{ $device->exists ? route('emergency-response.safety-device.update', $device) : route('emergency-response.safety-device.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @if ($device->exists)
                    @method('PUT')
                @endif

                <h6 class="text-sm text-uppercase text-secondary-light mb-16">Identitas</h6>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Kode Aset</label>
                        <input type="text" name="code" class="form-control" value="{{ old('code', $device->code) }}" required maxlength="50">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Nama Device</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $device->name) }}" required maxlength="255">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jenis</label>
                        <select name="safety_device_type_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}" @selected(old('safety_device_type_id', $device->safety_device_type_id) === $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Merek</label>
                        <input type="text" name="brand" class="form-control" value="{{ old('brand', $device->brand) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Model</label>
                        <input type="text" name="model" class="form-control" value="{{ old('model', $device->model) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nomor Seri</label>
                        <input type="text" name="serial_number" class="form-control" value="{{ old('serial_number', $device->serial_number) }}">
                    </div>
                </div>

                <h6 class="text-sm text-uppercase text-secondary-light mb-16 mt-16">Lokasi & Kepemilikan</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Site</label>
                        <select name="site_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}" @selected(old('site_id', $device->site_id) === $site->id)>{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Lokasi</label>
                        <select name="location_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected(old('location_id', $device->location_id) === $location->id)>{{ $location->site->name }} - {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Area</label>
                        <select name="area_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($areas as $area)
                                <option value="{{ $area->id }}" @selected(old('area_id', $device->area_id) === $area->id)>{{ $area->location->name }} - {{ $area->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Detail Posisi</label>
                        <input type="text" name="position_detail" class="form-control" value="{{ old('position_detail', $device->position_detail) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Latitude</label>
                        <input type="number" step="any" name="latitude" class="form-control" value="{{ old('latitude', $device->latitude) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Longitude</label>
                        <input type="number" step="any" name="longitude" class="form-control" value="{{ old('longitude', $device->longitude) }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Departemen</label>
                        <select name="department_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('department_id', $device->department_id) === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tanggal Instalasi</label>
                        <input type="date" name="installed_at" class="form-control" value="{{ old('installed_at', optional($device->installed_at)->format('Y-m-d')) }}">
                    </div>
                </div>

                <h6 class="text-sm text-uppercase text-secondary-light mb-16 mt-16">Kondisi & Status</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kondisi</label>
                        <select name="condition" class="form-control" required>
                            @foreach ($conditions as $value => $label)
                                <option value="{{ $value }}" @selected(old('condition', $device->condition ?? 'baik') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status Operasional</label>
                        <select name="operational_status" class="form-control" required>
                            @foreach ($operationalStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('operational_status', $device->operational_status ?? 'available') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <h6 class="text-sm text-uppercase text-secondary-light mb-16 mt-16">Inspeksi & Kalibrasi</h6>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Inspeksi Terakhir</label>
                        <input type="date" name="last_inspection_at" class="form-control" value="{{ old('last_inspection_at', optional($device->last_inspection_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Inspeksi Berikutnya</label>
                        <input type="date" name="next_inspection_at" class="form-control" value="{{ old('next_inspection_at', optional($device->next_inspection_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Kalibrasi Terakhir</label>
                        <input type="date" name="last_calibration_at" class="form-control" value="{{ old('last_calibration_at', optional($device->last_calibration_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Kalibrasi Berikutnya</label>
                        <input type="date" name="next_calibration_at" class="form-control" value="{{ old('next_calibration_at', optional($device->next_calibration_at)->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Masa Berlaku Sertifikat</label>
                        <input type="date" name="certificate_expires_at" class="form-control" value="{{ old('certificate_expires_at', optional($device->certificate_expires_at)->format('Y-m-d')) }}">
                    </div>
                </div>

                <h6 class="text-sm text-uppercase text-secondary-light mb-16 mt-16">Lainnya</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Foto</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        @if ($device->photo_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($device->photo_path) }}" class="mt-8" style="max-height: 80px;" alt="Foto saat ini">
                        @endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $device->notes) }}</textarea>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-16">
                    <button type="submit" class="btn btn-primary-600">Simpan</button>
                    <a href="{{ route('emergency-response.safety-device.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
