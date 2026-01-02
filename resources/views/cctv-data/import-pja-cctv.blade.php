@extends('layouts.master')

@section('title', 'Import PJA-CCTV Mapping')
@section('css')
    
@endsection 
@section('content')
<x-page-title title="PJA-CCTV Mapping" pagetitle="Import Mapping dari Excel" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">Import PJA-CCTV Mapping dari Excel</h5>
                        <p class="mb-0 text-muted">Upload file Excel (.xlsx, .xls) atau CSV untuk mengimpor data mapping PJA dengan CCTV secara massal</p>
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
                        <p class="mb-0 mt-2"><small>Proses import sedang berjalan di background. Silakan cek log atau refresh halaman ini beberapa saat lagi untuk melihat hasil.</small></p>
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
                        <form action="{{ route('cctv-data.import-pja-cctv') }}" method="POST" enctype="multipart/form-data">
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
                                    <li><strong>NO</strong> - Nomor urut (opsional)</li>
                                    <li><strong>PJA</strong> - Nama PJA (contoh: PJA Facility & Infrastructure BC BMO 1, Inspektor Safety BC BMO 1)</li>
                                    <li><strong>CCTV Dedicated</strong> - Nama CCTV (contoh: CCTV 1 MTL, BMO1-MTL-0001)</li>
                                </ul>
                                <p class="mb-0 mt-2"><strong>Catatan Penting:</strong></p>
                                <ul class="mb-0 small mt-1">
                                    <li>Proses import berjalan di <strong>background</strong> untuk performa yang lebih baik</li>
                                    <li>Data PJA akan diambil dari ClickHouse (tabel nitip.pja_full_hierarchical_view_fix) - <strong>sekali saja di awal</strong></li>
                                    <li>Data CCTV akan diambil dari database (tabel cctv_data_bmo2) - <strong>sekali saja di awal</strong></li>
                                    <li>Sistem mendukung fuzzy matching untuk handle typo pada nama PJA (contoh: "Fasility" akan dicocokkan dengan "Facility")</li>
                                    <li>Sistem mendukung berbagai format nomor CCTV (contoh: "CCTV 1 MTL" akan dicocokkan dengan "BMO1-MTL-0001")</li>
                                    <li>Data mapping yang sudah ada akan di-skip (tidak duplikat)</li>
                                    <li>Hasil import dapat dilihat di log: <code>storage/logs/laravel.log</code></li>
                                </ul>
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
                                    <li>Data dimulai dari baris kedua</li>
                                    <li>Baris kosong akan diabaikan</li>
                                    <li>Pastikan ClickHouse terhubung untuk mengambil data PJA</li>
                                    <li>Sistem akan mencocokkan nama PJA dan CCTV secara fleksibel</li>
                                    <li>Data mapping duplikat akan di-skip</li>
                                </ol>
                                <div class="mt-3 p-2 bg-warning bg-opacity-10 rounded">
                                    <small class="text-warning">
                                        <strong>Tips:</strong> Jika PJA atau CCTV tidak ditemukan, pastikan nama sesuai dengan data di database. Sistem akan mencoba mencocokkan dengan berbagai variasi penulisan.
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

