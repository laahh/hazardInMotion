@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Shift')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Shift</h6>
            <div class="d-flex align-items-center gap-3">
                <form method="GET" class="navbar-search">
                    <input type="text" name="q" value="{{ $q }}" placeholder="Cari kode/nama...">
                    <iconify-icon icon="ion:search-outline" class="icon"></iconify-icon>
                </form>
                <button type="button" class="btn btn-primary-600 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="ri-add-line"></i> Tambah
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Jam Mulai</th>
                            <th>Jam Selesai</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shifts as $shift)
                            <tr>
                                <td>{{ $shift->code }}</td>
                                <td>{{ $shift->name }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($shift->start_time)->format('H:i') }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($shift->end_time)->format('H:i') }}</td>
                                <td>
                                    @if ($shift->is_active)
                                        <span class="badge bg-success-focus text-success-600 px-16 py-4 radius-4">Aktif</span>
                                    @else
                                        <span class="badge bg-neutral-200 text-secondary-light px-16 py-4 radius-4">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#editModal-{{ $shift->id }}"><i class="ri-edit-line"></i></button>
                                    <form action="{{ route('emergency-response.master-data.shifts.destroy', $shift) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus {{ addslashes($shift->name) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editModal-{{ $shift->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('emergency-response.master-data.shifts.update', $shift) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <h6 class="modal-title">Edit Shift</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-6 mb-3">
                                                        <label class="form-label">Kode</label>
                                                        <input type="text" name="code" class="form-control" value="{{ old('code', $shift->code) }}" required maxlength="50">
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <label class="form-label">Nama</label>
                                                        <input type="text" name="name" class="form-control" value="{{ old('name', $shift->name) }}" required maxlength="255">
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6 mb-3">
                                                        <label class="form-label">Jam Mulai</label>
                                                        <input type="time" name="start_time" class="form-control" value="{{ old('start_time', \Illuminate\Support\Carbon::parse($shift->start_time)->format('H:i')) }}" required>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <label class="form-label">Jam Selesai</label>
                                                        <input type="time" name="end_time" class="form-control" value="{{ old('end_time', \Illuminate\Support\Carbon::parse($shift->end_time)->format('H:i')) }}" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Deskripsi</label>
                                                    <textarea name="description" class="form-control" rows="2">{{ old('description', $shift->description) }}</textarea>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="shift-active-{{ $shift->id }}" @checked($shift->is_active)>
                                                    <label class="form-check-label" for="shift-active-{{ $shift->id }}">Aktif</label>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-primary-600">Simpan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary-light py-24">Belum ada data shift.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $shifts->links() }}</div>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('emergency-response.master-data.shifts.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h6 class="modal-title">Tambah Shift</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Kode</label>
                                <input type="text" name="code" class="form-control" value="{{ old('code') }}" required maxlength="50">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Nama</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="255">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Jam Mulai</label>
                                <input type="time" name="start_time" class="form-control" value="{{ old('start_time') }}" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Jam Selesai</label>
                                <input type="time" name="end_time" class="form-control" value="{{ old('end_time') }}" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="shift-active-new" checked>
                            <label class="form-check-label" for="shift-active-new">Aktif</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary-600">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
