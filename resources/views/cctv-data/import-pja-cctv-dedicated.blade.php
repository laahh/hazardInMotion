@extends('layouts.master')

@section('title', 'Import PJA CCTV Dedicated')
@section('css')
    
@endsection 
@section('content')
<x-page-title title="PJA CCTV Dedicated" pagetitle="Import Data dari Excel" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">Import PJA CCTV Dedicated dari Excel</h5>
                        <p class="mb-0 text-muted">Upload file Excel (.xlsx, .xls) atau CSV untuk mengimpor data PJA CCTV Dedicated secara massal</p>
                    </div>
                    <div class="dropdown">
                        <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                            data-bs-toggle="dropdown">
                            <span class="material-icons-outlined fs-5">more_vert</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('cctv-data.index') }}"><i class="material-icons-outlined me-2">arrow_back</i> Kembali</a></li>
                        </ul>
                    </div>
                </div>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>Berhasil!</strong> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error!</strong> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('import_errors') && count(session('import_errors')) > 0)
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>Peringatan:</strong> Beberapa data gagal diimpor.
                        <ul class="mb-0 mt-2">
                            @foreach(session('import_errors') as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="row">
                    <div class="col-md-8">
                        <form action="{{ route('cctv-data.import-pja-cctv-dedicated') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            
                            <div class="mb-4">
                                <label for="file" class="form-label fw-bold">Pilih File Excel/CSV <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="file" name="file" accept=".xlsx,.xls,.csv" required>
                                <small class="form-text text-muted">
                                    Format yang didukung: .xlsx, .xls, .csv (Maksimal 10MB)
                                </small>
                            </div>

                            <div class="alert alert-info">
                                <h6 class="fw-bold mb-2">Format File Excel yang Diperlukan:</h6>
                                <p class="mb-2">File Excel harus memiliki header pada baris pertama dengan kolom-kolom berikut:</p>
                                <ul class="mb-0 small">
                                    <li><strong>NO</strong> - Nomor urut (opsional, contoh: BMO 1)</li>
                                    <li><strong>PJA</strong> - Nama PJA (wajib, contoh: Inspektor Safety BC BMO 1)</li>
                                    <li><strong>CCTV Dedicated</strong> - Nama CCTV (wajib, contoh: CCTV 1 MTL, CCTV 10 MTL)</li>
                                </ul>
                                <p class="mb-0 mt-2"><strong>Contoh Data:</strong></p>
                                <table class="table table-sm table-bordered mt-2 mb-0">
                                    <thead>
                                        <tr>
                                            <th>NO</th>
                                            <th>PJA</th>
                                            <th>CCTV Dedicated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>BMO 1</td>
                                            <td>Inspektor Safety BC BMO 1</td>
                                            <td>CCTV 1 MTL</td>
                                        </tr>
                                        <tr>
                                            <td>BMO 1</td>
                                            <td>Inspektor Safety BC BMO 1</td>
                                            <td>CCTV 10 MTL</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons-outlined">upload</i> Import Data
                                </button>
                                <a href="{{ route('cctv-data.index') }}" class="btn btn-secondary">
                                    <i class="material-icons-outlined">close</i> Batal
                                </a>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <div class="card rounded-4 bg-light">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <h6 class="fw-bold mb-0">Petunjuk Import</h6>
                                </div>
                                <ol class="small mb-0">
                                    <li>Pastikan file Excel memiliki header di baris pertama</li>
                                    <li>Kolom wajib: PJA dan CCTV Dedicated</li>
                                    <li>Kolom NO bersifat opsional</li>
                                    <li>Data dimulai dari baris kedua</li>
                                    <li>Baris kosong akan diabaikan</li>
                                    <li>Data duplikat akan di-skip</li>
                                </ol>
                                <div class="mt-3 p-2 bg-warning bg-opacity-10 rounded">
                                    <small class="text-warning">
                                        <strong>Tips:</strong> Pastikan format kolom sesuai dengan contoh di atas. Sistem akan memvalidasi data sebelum menyimpan ke database.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

