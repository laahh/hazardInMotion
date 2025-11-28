@extends('layouts.master')

@section('title', 'GR Table')

@section('content')
    <x-page-title title="GR Table" pagetitle="GR Management" />

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
                    <form method="POST" action="{{ route('gr-table.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="tasklist" class="form-label">Tasklist</label>
                            <input type="text" name="tasklist" id="tasklist" class="form-control" value="{{ old('tasklist') }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="gr" class="form-label">GR</label>
                            <input type="text" name="gr" id="gr" class="form-control" value="{{ old('gr') }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="catatan" class="form-label">Catatan</label>
                            <textarea name="catatan" id="catatan" class="form-control" rows="3">{{ old('catatan') }}</textarea>
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
                    <small class="text-muted">Format kolom: Tasklist, GR, Catatan</small>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('gr-table.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="excel_file" class="form-label">File (.xlsx, .xls, .csv)</label>
                            <input type="file" name="excel_file" id="excel_file" class="form-control" required>
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
                        <h5 class="mb-0">Daftar GR</h5>
                        <small class="text-muted">Menampilkan {{ $entries->firstItem() ?? 0 }}-{{ $entries->lastItem() ?? 0 }} dari {{ $entries->total() }} data</small>
                    </div>
                    
                </div>
                <div class="card-body">
                    
                    <div class="table-responsive">
                    <table class="table table-striped align-middle mb-3">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tasklist</th>
                                <th>GR</th>
                                <th>Catatan</th>
                                <th>Dibuat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                <tr>
                                    <td>{{ ($entries->firstItem() ?? 0) + $loop->index }}</td>
                                    <td>{{ $entry->tasklist }}</td>
                                    <td>{{ $entry->gr }}</td>
                                    <td>{{ $entry->catatan ?? '-' }}</td>
                                    <td>{{ $entry->created_at?->format('d M Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Belum ada data</td>
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

