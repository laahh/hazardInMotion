@extends('layouts.master')

@section('title', 'Insiden Tabel')

@section('content')
    <x-page-title title="Insiden Tabel" pagetitle="Manajemen Insiden" />

    <div class="row">
        <div class="col-12">
            @if (session('success'))
                <div class="alert alert-success rounded-4">{{ session('success') }}</div>
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

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card rounded-4 h-100">
                <div class="card-header">
                    <h5 class="mb-0">Filter Data</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label for="search" class="form-label">Cari</label>
                            <input type="text" name="search" id="search" value="{{ $search }}" class="form-control"
                                placeholder="No Kecelakaan / Site / Kategori">
                        </div>
                        <div class="col-md-4">
                            <label for="per_page" class="form-label">Baris per halaman</label>
                            <select name="per_page" id="per_page" class="form-select">
                                @foreach ([10, 25, 50, 100] as $option)
                                    <option value="{{ $option }}" {{ $perPage === $option ? 'selected' : '' }}>
                                        {{ $option }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary rounded-3">Terapkan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card rounded-4 h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Upload Excel</h5>
                    <small class="text-muted">.xlsx/.xls/.csv</small>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('insiden-tabel.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="excel_file" class="form-label">File Excel</label>
                            <input type="file" name="excel_file" id="excel_file" class="form-control" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success rounded-3">Upload &amp; Proses</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card rounded-4">
                <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <h5 class="mb-0">Data Insiden</h5>
                        <small class="text-muted">Menampilkan {{ $insidens->firstItem() ?? 0 }}-{{ $insidens->lastItem() ?? 0 }}
                            dari {{ $insidens->total() }} data</small>
                    </div>
                    <a href="{{ route('insiden-tabel.create') }}" class="btn btn-primary rounded-3">Tambah Data</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-3" style="white-space: nowrap;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Kategori</th>
                                    <th>Site</th>
                                    <th>Layer</th>
                                    <th>Jenis Item IPLS</th>
                                    <th>Detail Layer</th>
                                    <th>Klasifikasi Layer</th>
                                    <th>Keterangan Layer</th>
                                    <th>Status LPI</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $rowNumber = $insidens->firstItem() ?? 1;
                                @endphp
                                @forelse ($groupedInsidens as $noKecelakaan => $records)
                                    <tr class="table-secondary">
                                        <td colspan="11" class="fw-semibold">
                                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
                                                <div>
                                                    No Kecelakaan: {{ $noKecelakaan }}
                                                    <span class="badge bg-primary bg-opacity-10 text-primary ms-2">
                                                        {{ $records->count() }} entri
                                                    </span>
                                                </div>
                                                <div class="text-muted small">
                                                    Site: {{ $records->first()->site ?? '-' }} |
                                                    Kategori: {{ $records->first()->kategori ?? '-' }} |
                                                    Status LPI: {{ $records->first()->status_lpi ?? '-' }}
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @foreach ($records as $insiden)
                                        <tr>
                                            <td>{{ $rowNumber++ }}</td>
                                            <td>{{ $insiden->kategori ?? '-' }}</td>
                                            <td>{{ $insiden->site ?? '-' }}</td>
                                            <td>{{ $insiden->layer ?? '-' }}</td>
                                            <td>{{ $insiden->jenis_item_ipls ?? '-' }}</td>
                                            <td>{{ $insiden->detail_layer ?? '-' }}</td>
                                            <td>{{ $insiden->klasifikasi_layer ?? '-' }}</td>
                                            <td>{{ $insiden->keterangan_layer ?? '-' }}</td>
                                            <td>{{ $insiden->status_lpi ?? '-' }}</td>
                                            <td>{{ $insiden->tanggal?->format('d M Y') ?? '-' }}</td>
                                            <td class="d-flex gap-2">
                                                <a href="{{ route('insiden-tabel.edit', $insiden) }}"
                                                    class="btn btn-sm btn-outline-primary rounded-3">Edit</a>
                                                <form method="POST" action="{{ route('insiden-tabel.destroy', $insiden) }}"
                                                    onsubmit="return confirm('Yakin ingin menghapus data ini?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-3">Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted">Belum ada data</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-column flex-lg-row gap-3">
                        <div class="text-muted">
                            Halaman {{ $insidens->currentPage() }} dari {{ $insidens->lastPage() }}
                        </div>
                        {{ $insidens->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

