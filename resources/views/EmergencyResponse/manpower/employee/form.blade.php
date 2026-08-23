@extends('EmergencyResponse.layouts.app')

@section('page-title', $employee->exists ? 'Edit Personel' : 'Tambah Personel')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">{{ $employee->exists ? 'Edit' : 'Tambah' }} Personel</h6></div>
        <div class="card-body">
            <form action="{{ $employee->exists ? route('emergency-response.manpower.employees.update', $employee) : route('emergency-response.manpower.employees.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @if ($employee->exists)
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">No. Pegawai</label>
                        <input type="text" name="employee_number" class="form-control" value="{{ old('employee_number', $employee->employee_number) }}" required maxlength="50">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="full_name" class="form-control" value="{{ old('full_name', $employee->full_name) }}" required maxlength="255">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jabatan</label>
                        <input type="text" name="position" class="form-control" value="{{ old('position', $employee->position) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Departemen</label>
                        <select name="department_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('department_id', $employee->department_id) === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Site</label>
                        <select name="site_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}" @selected(old('site_id', $employee->site_id) === $site->id)>{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Unit Emergency</label>
                        <select name="emergency_unit_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($emergencyUnits as $unit)
                                <option value="{{ $unit->id }}" @selected(old('emergency_unit_id', $employee->emergency_unit_id) === $unit->id)>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Peran Emergency</label>
                        <input type="text" name="emergency_role" class="form-control" value="{{ old('emergency_role', $employee->emergency_role) }}" placeholder="mis. Fire Warden, First Aider">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Akun Login (opsional)</label>
                        <select name="user_id" class="form-control">
                            <option value="">-- Tidak ada akun --</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(old('user_id', $employee->user_id) === $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $employee->email) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $employee->phone) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status Pekerjaan</label>
                        <select name="employment_status" class="form-control" required>
                            @foreach (\App\Models\EmergencyResponse\Manpower\Employee::EMPLOYMENT_STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(old('employment_status', $employee->employment_status ?? 'permanent') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Keahlian</label>
                    <textarea name="skills" class="form-control" rows="2">{{ old('skills', $employee->skills) }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Kontak Darurat</label>
                        <input type="text" name="emergency_contact_name" class="form-control" value="{{ old('emergency_contact_name', $employee->emergency_contact_name) }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Telepon Kontak Darurat</label>
                        <input type="text" name="emergency_contact_phone" class="form-control" value="{{ old('emergency_contact_phone', $employee->emergency_contact_phone) }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Foto</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        @if ($employee->photo_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->photo_path) }}" class="mt-8" style="max-height: 80px;" alt="Foto saat ini">
                        @endif
                    </div>
                    <div class="col-md-6 mb-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="emp-active" @checked(old('is_active', $employee->is_active ?? true))>
                            <label class="form-check-label" for="emp-active">Aktif</label>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-16">
                    <button type="submit" class="btn btn-primary-600">Simpan</button>
                    <a href="{{ route('emergency-response.manpower.employees.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
