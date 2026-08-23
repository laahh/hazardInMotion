@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Escalation Matrix')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Escalation Matrix</h6>
            <div class="d-flex align-items-center gap-3">
                <form method="GET" class="navbar-search">
                    <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama...">
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
                            <th>Nama</th>
                            <th>Berlaku Untuk</th>
                            <th>Level</th>
                            <th>Delay (menit)</th>
                            <th>Notify Role</th>
                            <th>Channel</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($matrices as $matrix)
                            <tr>
                                <td>{{ $matrix->name }}</td>
                                <td>{{ $appliesToOptions[$matrix->applies_to] ?? $matrix->applies_to }}</td>
                                <td>{{ $matrix->level }}</td>
                                <td>{{ $matrix->delay_minutes }}</td>
                                <td>{{ $matrix->notifyRole->name ?? '-' }}</td>
                                <td>{{ $channelOptions[$matrix->channel] ?? $matrix->channel }}</td>
                                <td>
                                    @if ($matrix->is_active)
                                        <span class="badge bg-success-focus text-success-600 px-16 py-4 radius-4">Aktif</span>
                                    @else
                                        <span class="badge bg-neutral-200 text-secondary-light px-16 py-4 radius-4">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#editModal-{{ $matrix->id }}"><i class="ri-edit-line"></i></button>
                                    <form action="{{ route('emergency-response.master-data.escalation-matrices.destroy', $matrix) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus {{ addslashes($matrix->name) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editModal-{{ $matrix->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('emergency-response.master-data.escalation-matrices.update', $matrix) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <h6 class="modal-title">Edit Escalation Matrix</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Nama</label>
                                                    <input type="text" name="name" class="form-control" value="{{ old('name', $matrix->name) }}" required maxlength="255">
                                                </div>
                                                <div class="row">
                                                    <div class="col-6 mb-3">
                                                        <label class="form-label">Berlaku Untuk</label>
                                                        <select name="applies_to" class="form-control" required>
                                                            @foreach ($appliesToOptions as $value => $label)
                                                                <option value="{{ $value }}" @selected($matrix->applies_to === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <label class="form-label">Level Eskalasi</label>
                                                        <input type="number" name="level" class="form-control" value="{{ old('level', $matrix->level) }}" required min="1" max="10">
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6 mb-3">
                                                        <label class="form-label">Delay (menit)</label>
                                                        <input type="number" name="delay_minutes" class="form-control" value="{{ old('delay_minutes', $matrix->delay_minutes) }}" required min="1">
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <label class="form-label">Channel</label>
                                                        <select name="channel" class="form-control" required>
                                                            @foreach ($channelOptions as $value => $label)
                                                                <option value="{{ $value }}" @selected($matrix->channel === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Notify Role</label>
                                                    <select name="notify_role_id" class="form-control">
                                                        <option value="">-- Tidak ada --</option>
                                                        @foreach ($roles as $role)
                                                            <option value="{{ $role->id }}" @selected($matrix->notify_role_id === $role->id)>{{ $role->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Deskripsi</label>
                                                    <textarea name="description" class="form-control" rows="2">{{ old('description', $matrix->description) }}</textarea>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="esc-active-{{ $matrix->id }}" @checked($matrix->is_active)>
                                                    <label class="form-check-label" for="esc-active-{{ $matrix->id }}">Aktif</label>
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
                            <tr><td colspan="8" class="text-center text-secondary-light py-24">Belum ada data escalation matrix.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $matrices->links() }}</div>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('emergency-response.master-data.escalation-matrices.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h6 class="modal-title">Tambah Escalation Matrix</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="255">
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Berlaku Untuk</label>
                                <select name="applies_to" class="form-control" required>
                                    @foreach ($appliesToOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Level Eskalasi</label>
                                <input type="number" name="level" class="form-control" value="{{ old('level', 1) }}" required min="1" max="10">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Delay (menit)</label>
                                <input type="number" name="delay_minutes" class="form-control" value="{{ old('delay_minutes') }}" required min="1">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Channel</label>
                                <select name="channel" class="form-control" required>
                                    @foreach ($channelOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notify Role</label>
                            <select name="notify_role_id" class="form-control">
                                <option value="">-- Tidak ada --</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="esc-active-new" checked>
                            <label class="form-check-label" for="esc-active-new">Aktif</label>
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
