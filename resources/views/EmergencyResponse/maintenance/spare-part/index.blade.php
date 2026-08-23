@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Spare Part')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Spare Part</h6>
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
                        <tr><th>Kode</th><th>Nama</th><th>Satuan</th><th>Harga/Unit</th><th>Stok</th><th>Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($spareParts as $part)
                            <tr>
                                <td>{{ $part->code }}</td>
                                <td>{{ $part->name }}</td>
                                <td>{{ $part->unit }}</td>
                                <td>{{ $part->unit_cost !== null ? number_format((float) $part->unit_cost, 0, ',', '.') : '-' }}</td>
                                <td>{{ $part->stock_quantity }}</td>
                                <td>
                                    @if ($part->is_active)
                                        <span class="badge bg-success-focus text-success-600 px-16 py-4 radius-4">Aktif</span>
                                    @else
                                        <span class="badge bg-neutral-200 text-secondary-light px-16 py-4 radius-4">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#editModal-{{ $part->id }}"><i class="ri-edit-line"></i></button>
                                    <form action="{{ route('emergency-response.maintenance.spare-parts.destroy', $part) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus {{ addslashes($part->name) }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editModal-{{ $part->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog"><div class="modal-content">
                                    <form action="{{ route('emergency-response.maintenance.spare-parts.update', $part) }}" method="POST">
                                        @csrf @method('PUT')
                                        <div class="modal-header"><h6 class="modal-title">Edit Spare Part</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-6 mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" value="{{ old('code', $part->code) }}" required></div>
                                                <div class="col-6 mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="{{ old('name', $part->name) }}" required></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-4 mb-3"><label class="form-label">Satuan</label><input type="text" name="unit" class="form-control" value="{{ old('unit', $part->unit) }}" required></div>
                                                <div class="col-4 mb-3"><label class="form-label">Harga/Unit</label><input type="number" step="any" name="unit_cost" class="form-control" value="{{ old('unit_cost', $part->unit_cost) }}"></div>
                                                <div class="col-4 mb-3"><label class="form-label">Stok</label><input type="number" name="stock_quantity" class="form-control" value="{{ old('stock_quantity', $part->stock_quantity) }}"></div>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="is_active" value="0">
                                                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="sp-active-{{ $part->id }}" @checked($part->is_active)>
                                                <label class="form-check-label" for="sp-active-{{ $part->id }}">Aktif</label>
                                            </div>
                                        </div>
                                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Simpan</button></div>
                                    </form>
                                </div></div>
                            </div>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary-light py-24">Belum ada data spare part.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $spareParts->links() }}</div>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.maintenance.spare-parts.store') }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tambah Spare Part</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required></div>
                        <div class="col-6 mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
                    </div>
                    <div class="row">
                        <div class="col-4 mb-3"><label class="form-label">Satuan</label><input type="text" name="unit" class="form-control" value="pcs" required></div>
                        <div class="col-4 mb-3"><label class="form-label">Harga/Unit</label><input type="number" step="any" name="unit_cost" class="form-control"></div>
                        <div class="col-4 mb-3"><label class="form-label">Stok</label><input type="number" name="stock_quantity" class="form-control" value="0"></div>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="sp-active-new" checked>
                        <label class="form-check-label" for="sp-active-new">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Simpan</button></div>
            </form>
        </div></div>
    </div>
@endsection
