@extends('EmergencyResponse.layouts.app')

@section('page-title', $equipment->exists ? 'Edit Peralatan' : 'Tambah Peralatan')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header">
            <h6 class="mb-0">{{ $equipment->exists ? 'Edit' : 'Tambah' }} Peralatan</h6>
        </div>
        <div class="card-body">
            <form action="{{ $equipment->exists ? route('emergency-response.equipment.update', $equipment) : route('emergency-response.equipment.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @if ($equipment->exists)
                    @method('PUT')
                @endif

                <h6 class="text-sm text-uppercase text-secondary-light mb-16">Identitas Peralatan</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">UUID</label>
                        <input type="text" class="form-control bg-neutral-50" value="{{ $equipment->code ?: 'Dibuat otomatis setelah disimpan' }}" readonly>
                        <small class="text-secondary-light">Format: SITE-Kategori-Nama-Urutan, mis. <code>PMO-Fire Equipment-Hose-1</code>.</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Kategori Peralatan</label>
                        <select name="equipment_category_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('equipment_category_id', $equipment->equipment_category_id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nama Peralatan <span class="text-danger-600">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $equipment->name) }}" required maxlength="191">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">No Registrasi</label>
                        <input type="text" name="registration_number" class="form-control" value="{{ old('registration_number', $equipment->registration_number) }}" maxlength="191">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Klasifikasi Alat</label>
                        <input type="text" name="classification" class="form-control" list="classification-options" value="{{ old('classification', $equipment->classification) }}" maxlength="191">
                        <datalist id="classification-options">
                            @foreach ($classificationSuggestions as $suggestion)
                                <option value="{{ $suggestion }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Detail Peralatan</label>
                        <textarea name="equipment_detail" class="form-control" rows="2">{{ old('equipment_detail', $equipment->equipment_detail) }}</textarea>
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

                <h6 class="text-sm text-uppercase text-secondary-light mb-16 mt-16">Lokasi &amp; Kepemilikan</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">SITE</label>
                        <select name="site_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}" @selected(old('site_id', $equipment->site_id) === $site->id)>{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Perusahaan</label>
                        <select name="company_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected((string) old('company_id', $equipment->company_id) === (string) $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Lokasi</label>
                        <input type="text" name="location_name" class="form-control" value="{{ old('location_name', $equipment->location_name ?: $equipment->location?->name) }}" maxlength="191" placeholder="mis. Gudang A">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Area</label>
                        <input type="text" name="area_name" class="form-control" value="{{ old('area_name', $equipment->area_name ?: $equipment->area?->name) }}" maxlength="191" placeholder="mis. Dekat pintu keluar">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Detail Posisi</label>
                        <input type="text" name="position_detail" class="form-control" value="{{ old('position_detail', $equipment->position_detail) }}" placeholder="mis. Dekat pintu keluar timur">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Latitude</label>
                        <input type="number" step="any" name="latitude" class="form-control" value="{{ old('latitude', $equipment->latitude) }}">
                    </div>
                    <div class="col-md-2 mb-3">
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

                <h6 class="text-sm text-uppercase text-secondary-light mb-16 mt-16">Kondisi &amp; Status</h6>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Kondisi Peralatan <span class="text-danger-600">*</span></label>
                        <select name="condition" class="form-control" required>
                            @foreach ($conditions as $value => $label)
                                <option value="{{ $value }}" @selected(old('condition', $equipment->condition ?? 'baik') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Status Posisi Barang</label>
                        <select name="position_status" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($positionStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('position_status', $equipment->position_status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Status Barang</label>
                        <select name="item_status" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($itemStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('item_status', $equipment->item_status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Status Operasional <span class="text-danger-600">*</span></label>
                        <select name="operational_status" class="form-control" required>
                            @foreach ($operationalStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('operational_status', $equipment->operational_status ?? 'available') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Keterangan Alat</label>
                        <textarea name="equipment_remarks" class="form-control" rows="2">{{ old('equipment_remarks', $equipment->equipment_remarks) }}</textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Keterangan Kerusakan</label>
                        <textarea name="damage_remarks" class="form-control" rows="2">{{ old('damage_remarks', $equipment->damage_remarks) }}</textarea>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tanggal Pembelian</label>
                        <input type="date" name="purchased_at" class="form-control" value="{{ old('purchased_at', optional($equipment->purchased_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mulai Digunakan</label>
                        <input type="date" name="commissioned_at" class="form-control" value="{{ old('commissioned_at', optional($equipment->commissioned_at)->format('Y-m-d')) }}">
                    </div>
                </div>

                <h6 class="text-sm text-uppercase text-secondary-light mb-16 mt-16">Berita Acara (BA)</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Progress BA</label>
                        <select name="ba_progress" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($baProgresses as $value => $label)
                                <option value="{{ $value }}" @selected(old('ba_progress', $equipment->ba_progress) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tanggal Close BA</label>
                        <input type="date" name="ba_closed_at" class="form-control" value="{{ old('ba_closed_at', optional($equipment->ba_closed_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Keterangan BA</label>
                        <textarea name="ba_remarks" class="form-control" rows="2">{{ old('ba_remarks', $equipment->ba_remarks) }}</textarea>
                    </div>
                </div>

                <h6 class="text-sm text-uppercase text-secondary-light mb-16 mt-16">Inspeksi, Kalibrasi &amp; Sertifikasi</h6>
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
