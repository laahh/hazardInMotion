@extends('EmergencyResponse.layouts.app')

@section('page-title', $equipment->exists ? 'Edit Emergency Equipment' : 'Tambah Emergency Equipment')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header">
            <h6 class="mb-0">{{ $equipment->exists ? 'Edit' : 'Tambah' }} Emergency Equipment</h6>
        </div>
        <div class="card-body">
            <form action="{{ $equipment->exists ? route('emergency-response.equipment.update', $equipment) : route('emergency-response.equipment.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @if ($equipment->exists)
                    @method('PUT')
                @endif

                <h6 class="text-sm text-uppercase text-secondary-light mb-16">Identitas</h6>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Kode Aset</label>
                        <input type="text" name="code" class="form-control" value="{{ old('code', $equipment->code) }}" required maxlength="50">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Nama Equipment</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $equipment->name) }}" required maxlength="255">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="equipment_category_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('equipment_category_id', $equipment->equipment_category_id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tipe/Model</label>
                        <input type="text" name="type_model" class="form-control" value="{{ old('type_model', $equipment->type_model) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Merek</label>
                        <input type="text" name="brand" class="form-control" value="{{ old('brand', $equipment->brand) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nomor Seri</label>
                        <input type="text" name="serial_number" class="form-control" value="{{ old('serial_number', $equipment->serial_number) }}">
                    </div>
                </div>

                <h6 class="text-sm text-uppercase text-secondary-light mb-16 mt-16">Lokasi & Kepemilikan</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Site</label>
                        <select name="site_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}" @selected(old('site_id', $equipment->site_id) === $site->id)>{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Lokasi</label>
                        <select name="location_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected(old('location_id', $equipment->location_id) === $location->id)>{{ $location->site->name }} - {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Area</label>
                        <select name="area_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($areas as $area)
                                <option value="{{ $area->id }}" @selected(old('area_id', $equipment->area_id) === $area->id)>{{ $area->location->name }} - {{ $area->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Detail Posisi</label>
                        <input type="text" name="position_detail" class="form-control" value="{{ old('position_detail', $equipment->position_detail) }}" placeholder="mis. Dekat pintu keluar timur">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Latitude</label>
                        <input type="number" step="any" name="latitude" class="form-control" value="{{ old('latitude', $equipment->latitude) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Longitude</label>
                        <input type="number" step="any" name="longitude" class="form-control" value="{{ old('longitude', $equipment->longitude) }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Departemen Pemilik</label>
                        <select name="department_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('department_id', $equipment->department_id) === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Unit Emergency</label>
                        <select name="emergency_unit_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($emergencyUnits as $unit)
                                <option value="{{ $unit->id }}" @selected(old('emergency_unit_id', $equipment->emergency_unit_id) === $unit->id)>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <h6 class="text-sm text-uppercase text-secondary-light mb-16 mt-16">Kondisi & Status</h6>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Kondisi</label>
                        <select name="condition" class="form-control" required>
                            @foreach ($conditions as $value => $label)
                                <option value="{{ $value }}" @selected(old('condition', $equipment->condition ?? 'baik') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Status Operasional</label>
                        <select name="operational_status" class="form-control" required>
                            @foreach ($operationalStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('operational_status', $equipment->operational_status ?? 'available') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Tanggal Pembelian</label>
                        <input type="date" name="purchased_at" class="form-control" value="{{ old('purchased_at', optional($equipment->purchased_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Mulai Digunakan</label>
                        <input type="date" name="commissioned_at" class="form-control" value="{{ old('commissioned_at', optional($equipment->commissioned_at)->format('Y-m-d')) }}">
                    </div>
                </div>

                <h6 class="text-sm text-uppercase text-secondary-light mb-16 mt-16">Inspeksi, Kalibrasi & Sertifikasi</h6>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Inspeksi Terakhir</label>
                        <input type="date" name="last_inspection_at" class="form-control" value="{{ old('last_inspection_at', optional($equipment->last_inspection_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Inspeksi Berikutnya</label>
                        <input type="date" name="next_inspection_at" class="form-control" value="{{ old('next_inspection_at', optional($equipment->next_inspection_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Kalibrasi Terakhir</label>
                        <input type="date" name="last_calibration_at" class="form-control" value="{{ old('last_calibration_at', optional($equipment->last_calibration_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Tanggal Kedaluwarsa</label>
                        <input type="date" name="expires_at" class="form-control" value="{{ old('expires_at', optional($equipment->expires_at)->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nomor Sertifikat/SKO</label>
                        <input type="text" name="certificate_number" class="form-control" value="{{ old('certificate_number', $equipment->certificate_number) }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Masa Berlaku Sertifikat/SKO</label>
                        <input type="date" name="certificate_expires_at" class="form-control" value="{{ old('certificate_expires_at', optional($equipment->certificate_expires_at)->format('Y-m-d')) }}">
                    </div>
                </div>

                <h6 class="text-sm text-uppercase text-secondary-light mb-16 mt-16">Lainnya</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Foto</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        @if ($equipment->photo_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($equipment->photo_path) }}" class="mt-8" style="max-height: 80px;" alt="Foto saat ini">
                        @endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $equipment->notes) }}</textarea>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-16">
                    <button type="submit" class="btn btn-primary-600">Simpan</button>
                    <a href="{{ route('emergency-response.equipment.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
