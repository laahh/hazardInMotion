@extends('EmergencyResponse.layouts.app')

@section('page-title', $employee->full_name)

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-16">
        <a href="{{ route('emergency-response.manpower.employees.index') }}" class="text-secondary-light"><i class="ri-arrow-left-line"></i> Kembali ke daftar</a>
        <a href="{{ route('emergency-response.manpower.employees.edit', $employee) }}" class="btn btn-primary-600 btn-sm"><i class="ri-edit-line"></i> Edit</a>
    </div>

    <div class="row gy-4">
        <div class="col-lg-4">
            <div class="card shadow-none border">
                <div class="card-body text-center">
                    @if ($employee->photo_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->photo_path) }}" class="rounded-circle mb-16" style="width: 100px; height: 100px; object-fit: cover;" alt="Foto">
                    @else
                        <div class="rounded-circle bg-primary-100 text-primary-600 mx-auto mb-16 d-flex align-items-center justify-content-center" style="width: 100px; height: 100px; font-size: 2rem;">{{ strtoupper(substr($employee->full_name, 0, 1)) }}</div>
                    @endif
                    <h6 class="mb-4">{{ $employee->full_name }}</h6>
                    <p class="text-secondary-light text-sm mb-16">{{ $employee->position ?: '-' }}</p>
                    <table class="table table-borderless text-start mb-0">
                        <tr><th width="140">No. Pegawai</th><td>{{ $employee->employee_number }}</td></tr>
                        <tr><th>Departemen</th><td>{{ $employee->department->name ?? '-' }}</td></tr>
                        <tr><th>Site</th><td>{{ $employee->site->name ?? '-' }}</td></tr>
                        <tr><th>Unit Emergency</th><td>{{ $employee->emergencyUnit->name ?? '-' }}</td></tr>
                        <tr><th>Peran Emergency</th><td>{{ $employee->emergency_role ?: '-' }}</td></tr>
                        <tr><th>Email</th><td>{{ $employee->email ?: '-' }}</td></tr>
                        <tr><th>Telepon</th><td>{{ $employee->phone ?: '-' }}</td></tr>
                        <tr><th>Status</th><td>{{ $employee->employmentStatusLabel() }} — {{ $employee->is_active ? 'Aktif' : 'Nonaktif' }}</td></tr>
                        <tr><th>Keahlian</th><td>{{ $employee->skills ?: '-' }}</td></tr>
                        <tr><th>Kontak Darurat</th><td>{{ $employee->emergency_contact_name ?: '-' }} ({{ $employee->emergency_contact_phone ?: '-' }})</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-none border mb-24">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Riwayat Training</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#trainingModal">Tambah</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table bordered-table mb-0">
                            <thead><tr><th>Training</th><th>Tanggal</th><th>Hasil</th><th>Expired</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($employee->trainings as $training)
                                    <tr>
                                        <td>{{ $training->training->name ?? '-' }}</td>
                                        <td>{{ $training->trained_at->format('d M Y') }}</td>
                                        <td>{{ $training->score ?: '-' }}</td>
                                        <td>{{ optional($training->expires_at)->format('d M Y') ?? '-' }}</td>
                                        <td><span class="badge bg-info-focus text-info-600 px-8 py-2 radius-4">{{ $training->statusLabel() }}</span></td>
                                        <td>
                                            <form action="{{ route('emergency-response.manpower.employees.trainings.destroy', [$employee, $training]) }}" method="POST">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-secondary-light py-16">Belum ada riwayat training.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-none border mb-24">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Sertifikasi</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#certModal">Tambah</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table bordered-table mb-0">
                            <thead><tr><th>Sertifikasi</th><th>No. Sertifikat</th><th>Diterbitkan</th><th>Expired</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($employee->certifications as $cert)
                                    <tr>
                                        <td>{{ $cert->certification->name ?? '-' }}</td>
                                        <td>{{ $cert->certificate_number ?: '-' }}</td>
                                        <td>{{ $cert->issued_at->format('d M Y') }}</td>
                                        <td>{{ optional($cert->expires_at)->format('d M Y') ?? '-' }}</td>
                                        <td><span class="badge bg-info-focus text-info-600 px-8 py-2 radius-4">{{ $cert->statusLabel() }}</span></td>
                                        <td>
                                            <form action="{{ route('emergency-response.manpower.employees.certifications.destroy', [$employee, $cert]) }}" method="POST">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-secondary-light py-16">Belum ada sertifikasi.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">Riwayat Kehadiran Terakhir</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table bordered-table mb-0">
                            <thead><tr><th>Tanggal</th><th>Status</th><th>Check-in</th><th>Check-out</th></tr></thead>
                            <tbody>
                                @forelse ($employee->attendance->take(10) as $attendance)
                                    <tr>
                                        <td>{{ $attendance->date->format('d M Y') }}</td>
                                        <td>{{ $attendance->statusLabel() }}</td>
                                        <td>{{ optional($attendance->check_in_at)->format('H:i') ?? '-' }}</td>
                                        <td>{{ optional($attendance->check_out_at)->format('H:i') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-secondary-light py-16">Belum ada data kehadiran.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="trainingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.manpower.employees.trainings.store', $employee) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tambah Riwayat Training</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Training</label>
                        <select name="training_id" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            @foreach ($trainingCatalog as $training)
                                <option value="{{ $training->id }}">{{ $training->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Provider</label><input type="text" name="provider" class="form-control"></div>
                        <div class="col-6 mb-3"><label class="form-label">Tanggal Training</label><input type="date" name="trained_at" class="form-control" required></div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Nilai/Hasil</label><input type="text" name="score" class="form-control"></div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Status Lulus</label>
                            <select name="is_passed" class="form-control">
                                <option value="1">Lulus</option>
                                <option value="0">Tidak Lulus</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3"><label class="form-label">Dokumen Sertifikat</label><input type="file" name="certificate" class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Simpan</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="certModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.manpower.employees.certifications.store', $employee) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tambah Sertifikasi</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Sertifikasi</label>
                        <select name="certification_id" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            @foreach ($certificationCatalog as $cert)
                                <option value="{{ $cert->id }}">{{ $cert->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">No. Sertifikat</label><input type="text" name="certificate_number" class="form-control"></div>
                        <div class="col-6 mb-3"><label class="form-label">Lembaga Penerbit</label><input type="text" name="issuing_body" class="form-control"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Tanggal Terbit</label><input type="date" name="issued_at" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">File Sertifikat</label><input type="file" name="certificate" class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Simpan</button></div>
            </form>
        </div></div>
    </div>
@endsection
