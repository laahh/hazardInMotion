@extends('layouts.master')

@section('title', 'Baseline PJA')

@section('content')
    <x-page-title title="Baseline PJA" pagetitle="Baseline PJA Management" />

    <div class="row">
        <div class="col-12">
            @if (session('success'))
                <div class="alert alert-success rounded-4">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger rounded-4">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-warning rounded-4">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-xl-6 d-flex">
            <div class="card rounded-4 w-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Input Manual</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('baseline-pja.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="site" class="form-label">Site</label>
                            <input type="text" name="site" id="site" class="form-control" value="{{ old('site') }}">
                        </div>
                        <div class="mb-3">
                            <label for="perusahaan" class="form-label">Perusahaan</label>
                            <input type="text" name="perusahaan" id="perusahaan" class="form-control" value="{{ old('perusahaan') }}">
                        </div>
                        <div class="mb-3">
                            <label for="id_lokasi" class="form-label">ID Lokasi</label>
                            <input type="text" name="id_lokasi" id="id_lokasi" class="form-control" value="{{ old('id_lokasi') }}">
                        </div>
                        <div class="mb-3">
                            <label for="lokasi" class="form-label">Lokasi</label>
                            <input type="text" name="lokasi" id="lokasi" class="form-control" value="{{ old('lokasi') }}">
                        </div>
                        <div class="mb-3">
                            <label for="id_pja" class="form-label">ID PJA</label>
                            <input type="text" name="id_pja" id="id_pja" class="form-control" value="{{ old('id_pja') }}">
                        </div>
                        <div class="mb-3">
                            <label for="pja" class="form-label">PJA</label>
                            <input type="text" name="pja" id="pja" class="form-control" value="{{ old('pja') }}">
                        </div>
                        <div class="mb-3">
                            <label for="tipe_pja" class="form-label">Tipe PJA</label>
                            <input type="text" name="tipe_pja" id="tipe_pja" class="form-control" value="{{ old('tipe_pja') }}">
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary rounded-3">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6 d-flex">
            <div class="card rounded-4 w-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Upload Excel</h5>
                    <small class="text-muted">Format: Site, Perusahaan, ID Lokasi, Lokasi, ID PJA, PJA, Tipe PJA</small>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('baseline-pja.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="excel_file" class="form-label">File (.xlsx, .xls, .csv)</label>
                            <input type="file" name="excel_file" id="excel_file" class="form-control" required>
                            <small class="text-muted">Maksimal 10MB. File akan diproses di background untuk menghindari timeout.</small>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-success rounded-3">Upload</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card rounded-4">
                <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <h5 class="mb-0">Daftar Baseline PJA</h5>
                        <small class="text-muted">Menampilkan {{ $entries->firstItem() ?? 0 }}-{{ $entries->lastItem() ?? 0 }} dari {{ $entries->total() }} data</small>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="GET" action="{{ route('baseline-pja.index') }}" class="d-flex gap-2">
                            <input type="text" name="search" class="form-control" placeholder="Cari..." value="{{ request('search') }}" style="min-width: 200px;">
                            <button type="submit" class="btn btn-outline-primary rounded-3">Cari</button>
                            @if(request('search'))
                                <a href="{{ route('baseline-pja.index') }}" class="btn btn-outline-secondary rounded-3">Reset</a>
                            @endif
                        </form>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary rounded-3 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                {{ $perPage }} per halaman
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['per_page' => 10]) }}">10</a></li>
                                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['per_page' => 25]) }}">25</a></li>
                                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['per_page' => 50]) }}">50</a></li>
                                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['per_page' => 100]) }}">100</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-3">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Site</th>
                                    <th>Perusahaan</th>
                                    <th>ID Lokasi</th>
                                    <th>Lokasi</th>
                                    <th>ID PJA</th>
                                    <th>PJA</th>
                                    <th>Tipe PJA</th>
                                    <th>Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($entries as $entry)
                                    <tr>
                                        <td>{{ ($entries->firstItem() ?? 0) + $loop->index }}</td>
                                        <td>{{ $entry->site ?? '-' }}</td>
                                        <td>{{ $entry->perusahaan ?? '-' }}</td>
                                        <td>{{ $entry->id_lokasi ?? '-' }}</td>
                                        <td>{{ $entry->lokasi ?? '-' }}</td>
                                        <td>{{ $entry->id_pja ?? '-' }}</td>
                                        <td>{{ $entry->pja ?? '-' }}</td>
                                        <td>{{ $entry->tipe_pja ?? '-' }}</td>
                                        <td>{{ $entry->created_at?->format('d M Y H:i') }}</td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="{{ route('baseline-pja.edit', $entry->id) }}" class="btn btn-sm btn-warning rounded-3">Edit</a>
                                                <form method="POST" action="{{ route('baseline-pja.destroy', $entry->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger rounded-3">Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">Belum ada data</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-column flex-lg-row gap-3">
                        <div class="text-muted">
                            Menampilkan {{ $entries->firstItem() ?? 0 }}-{{ $entries->lastItem() ?? 0 }} dari {{ $entries->total() }} data
                        </div>
                        {{ $entries->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

