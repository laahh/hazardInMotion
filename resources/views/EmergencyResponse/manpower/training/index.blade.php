@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Katalog Training')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Katalog Training</h6>
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
                    <thead><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Provider</th><th>Masa Berlaku</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($trainings as $training)
                            <tr>
                                <td>{{ $training->code }}</td>
                                <td>{{ $training->name }}</td>
                                <td>{{ $training->type->name ?? '-' }}</td>
                                <td>{{ $training->provider ?: '-' }}</td>
                                <td>{{ $training->default_validity_months ? "{$training->default_validity_months} bulan" : '-' }}</td>
                                <td>
                                    @if ($training->is_active)
                                        <span class="badge bg-success-focus text-success-600 px-16 py-4 radius-4">Aktif</span>
                                    @else
                                        <span class="badge bg-neutral-200 text-secondary-light px-16 py-4 radius-4">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#editModal-{{ $training->id }}"><i class="ri-edit-line"></i></button>
                                    <form action="{{ route('emergency-response.manpower.trainings.destroy', $training) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus {{ addslashes($training->name) }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editModal-{{ $training->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog"><div class="modal-content">
                                    <form action="{{ route('emergency-response.manpower.trainings.update', $training) }}" method="POST">
                                        @csrf @method('PUT')
                                        <div class="modal-header"><h6 class="modal-title">Edit Training</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-6 mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" value="{{ old('code', $training->code) }}" required></div>
                                                <div class="col-6 mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="{{ old('name', $training->name) }}" required></div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Kategori</label>
                                                <select name="training_type_id" class="form-control">
                                                    <option value="">-- Pilih --</option>
                                                    @foreach ($trainingTypes as $type)
                                                        <option value="{{ $type->id }}" @selected($training->training_type_id === $type->id)>{{ $type->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="row">
                                                <div class="col-6 mb-3"><label class="form-label">Provider</label><input type="text" name="provider" class="form-control" value="{{ old('provider', $training->provider) }}"></div>
                                                <div class="col-6 mb-3"><label class="form-label">Masa Berlaku (bulan)</label><input type="number" name="default_validity_months" class="form-control" value="{{ old('default_validity_months', $training->default_validity_months) }}"></div>
                                            </div>
                                            <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2">{{ old('description', $training->description) }}</textarea></div>
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="is_active" value="0">
                                                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="tr-active-{{ $training->id }}" @checked($training->is_active)>
                                                <label class="form-check-label" for="tr-active-{{ $training->id }}">Aktif</label>
                                            </div>
                                        </div>
                                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Simpan</button></div>
                                    </form>
                                </div></div>
                            </div>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary-light py-24">Belum ada data training.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $trainings->links() }}</div>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.manpower.trainings.store') }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tambah Training</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required></div>
                        <div class="col-6 mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="training_type_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($trainingTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Provider</label><input type="text" name="provider" class="form-control"></div>
                        <div class="col-6 mb-3"><label class="form-label">Masa Berlaku (bulan)</label><input type="number" name="default_validity_months" class="form-control"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="tr-active-new" checked>
                        <label class="form-check-label" for="tr-active-new">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Simpan</button></div>
            </form>
        </div></div>
    </div>
@endsection
