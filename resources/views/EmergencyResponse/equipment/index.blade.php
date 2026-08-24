@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Emergency Equipment')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Emergency Equipment</h6>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('emergency-response.equipment.export') }}" class="btn btn-outline-secondary btn-sm"><i class="ri-file-excel-2-line"></i> Export</a>
                <a href="{{ route('emergency-response.equipment.import-template') }}" class="btn btn-outline-secondary btn-sm"><i class="ri-download-2-line"></i> Download Template</a>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal"><i class="ri-upload-2-line"></i> Import</button>
                <a href="{{ route('emergency-response.equipment.create') }}" class="btn btn-primary-600 btn-sm"><i class="ri-add-line"></i> Tambah</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-16">
                <div class="col-md-3">
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari kode/nama...">
                </div>
                <div class="col-md-2">
                    <select name="equipment_category_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('equipment_category_id') === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="site_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Site</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected(request('site_id') === $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="condition" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Kondisi</option>
                        @foreach ($conditions as $value => $label)
                            <option value="{{ $value }}" @selected(request('condition') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="operational_status" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        @foreach ($operationalStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(request('operational_status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-outline-primary-600 w-100"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Lokasi</th>
                            <th>Kondisi</th>
                            <th>Status</th>
                            <th>Inspeksi Berikutnya</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($equipment as $item)
                            <tr>
                                <td>{{ $item->code }}</td>
                                <td><a href="{{ route('emergency-response.equipment.show', $item) }}">{{ $item->name }}</a></td>
                                <td>{{ $item->category->name ?? '-' }}</td>
                                <td>{{ $item->site->name ?? '-' }} @if($item->locationLabel()) / {{ $item->locationLabel() }} @endif</td>
                                <td><span class="badge bg-info-focus text-info-600 px-16 py-4 radius-4">{{ $item->conditionLabel() }}</span></td>
                                <td><span class="badge bg-success-focus text-success-600 px-16 py-4 radius-4">{{ $item->operationalStatusLabel() }}</span></td>
                                <td>{{ optional($item->next_inspection_at)->format('d M Y') ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('emergency-response.equipment.show', $item) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-eye-line"></i></a>
                                    <a href="{{ route('emergency-response.equipment.edit', $item) }}" class="btn btn-sm btn-outline-primary-600"><i class="ri-edit-line"></i></a>
                                    <form action="{{ route('emergency-response.equipment.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus {{ addslashes($item->name) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-secondary-light py-24">Belum ada data emergency equipment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $equipment->links() }}</div>
        </div>
    </div>

    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('emergency-response.equipment.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h6 class="modal-title">Import Emergency Equipment</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-secondary-light text-sm">
                            Unduh <a href="{{ route('emergency-response.equipment.import-template') }}">template Excel</a> terlebih dahulu.
                            Data akan diproses di background (bisa perlu waktu beberapa saat).
                        </p>
                        <div class="mb-3">
                            <label class="form-label">File Excel/CSV</label>
                            <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary-600">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
