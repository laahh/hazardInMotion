@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Data Personel')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Data Personel</h6>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('emergency-response.manpower.employees.export') }}" class="btn btn-outline-secondary btn-sm"><i class="ri-file-excel-2-line"></i> Export</a>
                <a href="{{ route('emergency-response.manpower.employees.create') }}" class="btn btn-primary-600 btn-sm"><i class="ri-add-line"></i> Tambah</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-16">
                <div class="col-md-4">
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari no. pegawai/nama...">
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
                    <select name="department_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Departemen</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(request('department_id') === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary-600 w-100"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead>
                        <tr><th>No. Pegawai</th><th>Nama</th><th>Jabatan</th><th>Departemen</th><th>Site</th><th>Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr>
                                <td>{{ $employee->employee_number }}</td>
                                <td><a href="{{ route('emergency-response.manpower.employees.show', $employee) }}">{{ $employee->full_name }}</a></td>
                                <td>{{ $employee->position ?: '-' }}</td>
                                <td>{{ $employee->department->name ?? '-' }}</td>
                                <td>{{ $employee->site->name ?? '-' }}</td>
                                <td>
                                    @if ($employee->is_active)
                                        <span class="badge bg-success-focus text-success-600 px-16 py-4 radius-4">Aktif</span>
                                    @else
                                        <span class="badge bg-neutral-200 text-secondary-light px-16 py-4 radius-4">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('emergency-response.manpower.employees.show', $employee) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-eye-line"></i></a>
                                    <a href="{{ route('emergency-response.manpower.employees.edit', $employee) }}" class="btn btn-sm btn-outline-primary-600"><i class="ri-edit-line"></i></a>
                                    <form action="{{ route('emergency-response.manpower.employees.destroy', $employee) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus {{ addslashes($employee->full_name) }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary-light py-24">Belum ada data personel.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $employees->links() }}</div>
        </div>
    </div>
@endsection
