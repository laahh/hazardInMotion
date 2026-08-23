@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Katalog Sertifikasi')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Katalog Sertifikasi</h6>
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
                    <thead><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Lembaga Penerbit</th><th>Masa Berlaku</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($certifications as $cert)
                            <tr>
                                <td>{{ $cert->code }}</td>
                                <td>{{ $cert->name }}</td>
                                <td>{{ $cert->type->name ?? '-' }}</td>
                                <td>{{ $cert->issuing_body ?: '-' }}</td>
                                <td>{{ $cert->default_validity_months ? "{$cert->default_validity_months} bulan" : '-' }}</td>
                                <td>
                                    @if ($cert->is_active)
                                        <span class="badge bg-success-focus text-success-600 px-16 py-4 radius-4">Aktif</span>
                                    @else
                                        <span class="badge bg-neutral-200 text-secondary-light px-16 py-4 radius-4">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#editModal-{{ $cert->id }}"><i class="ri-edit-line"></i></button>
                                    <form action="{{ route('emergency-response.manpower.certifications.destroy', $cert) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus {{ addslashes($cert->name) }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editModal-{{ $cert->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog"><div class="modal-content">
                                    <form action="{{ route('emergency-response.manpower.certifications.update', $cert) }}" method="POST">
                                        @csrf @method('PUT')
                                        <div class="modal-header"><h6 class="modal-title">Edit Sertifikasi</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-6 mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" value="{{ old('code', $cert->code) }}" required></div>
                                                <div class="col-6 mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="{{ old('name', $cert->name) }}" required></div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Kategori</label>
                                                <select name="certification_type_id" class="form-control">
                                                    <option value="">-- Pilih --</option>
                                                    @foreach ($certificationTypes as $type)
                                                        <option value="{{ $type->id }}" @selected($cert->certification_type_id === $type->id)>{{ $type->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="row">
                                                <div class="col-6 mb-3"><label class="form-label">Lembaga Penerbit</label><input type="text" name="issuing_body" class="form-control" value="{{ old('issuing_body', $cert->issuing_body) }}"></div>
                                                <div class="col-6 mb-3"><label class="form-label">Masa Berlaku (bulan)</label><input type="number" name="default_validity_months" class="form-control" value="{{ old('default_validity_months', $cert->default_validity_months) }}"></div>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="is_active" value="0">
                                                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="cert-active-{{ $cert->id }}" @checked($cert->is_active)>
                                                <label class="form-check-label" for="cert-active-{{ $cert->id }}">Aktif</label>
                                            </div>
                                        </div>
                                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Simpan</button></div>
                                    </form>
                                </div></div>
                            </div>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary-light py-24">Belum ada data sertifikasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $certifications->links() }}</div>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.manpower.certifications.store') }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tambah Sertifikasi</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required></div>
                        <div class="col-6 mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="certification_type_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($certificationTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Lembaga Penerbit</label><input type="text" name="issuing_body" class="form-control"></div>
                        <div class="col-6 mb-3"><label class="form-label">Masa Berlaku (bulan)</label><input type="number" name="default_validity_months" class="form-control"></div>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="cert-active-new" checked>
                        <label class="form-check-label" for="cert-active-new">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Simpan</button></div>
            </form>
        </div></div>
    </div>
@endsection
