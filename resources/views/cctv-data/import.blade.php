@extends('layouts.master')

@section('title', 'Import Data CCTV')
@section('css')
    
@endsection 
@section('content')
<x-page-title title="Data CCTV" pagetitle="Import Data dari Excel" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">Import Data CCTV dari Excel</h5>
                        <p class="mb-0 text-muted">Upload file Excel (.xlsx, .xls) atau CSV untuk mengimpor data CCTV secara massal</p>
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
                        <form action="{{ route('cctv-data.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            
                            <div class="mb-4">
                                <label for="file" class="form-label fw-bold">Pilih File Excel/CSV <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="file" name="file" accept=".xlsx,.xls,.csv" required>
                                <small class="form-text text-muted">
                                    Format yang didukung: .xlsx, .xls, .csv (Maksimal 10MB)
                                </small>
                            </div>

                            <div class="alert alert-info">
                                <h6 class="fw-bold mb-2">Format File Excel:</h6>
                                <p class="mb-2">File Excel harus memiliki header pada baris pertama dengan kolom-kolom berikut:</p>
                                <ul class="mb-0 small">
                                    <li>Site, Perusahaan, Kategori</li>
                                    <li>No. CCTV, Nama CCTV, Fungsi CCTV</li>
                                    <li>Bentuk Instalasi CCTV, Jenis, Tipe CCTV</li>
                                    <li>Radius Pengawasan, Jenis Spesifikasi Zoom</li>
                                    <li>Lokasi Pemasangan, Control Room</li>
                                    <li>Status, Kondisi</li>
                                    <li>Longitude, Latitude</li>
                                    <li>Coverage Lokasi, Coverage Detail Lokasi</li>
                                    <li>Kategori Area Tercapture, Kategori Aktivitas Tercapture</li>
                                    <li>Link Akses, User Name, Password</li>
                                    <li>Connected, Mirrored, Fitur Auto Alert</li>
                                    <li>Keterangan, Verifikasi By Petugas OCR</li>
                                    <li>Bulan Update, Tahun Update</li>
                                </ul>
                                <p class="mb-0 mt-2"><strong>Catatan:</strong> Nama kolom tidak case-sensitive dan dapat menggunakan spasi atau underscore.</p>
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
                                    <li>Nama kolom harus sesuai dengan field yang tersedia</li>
                                    <li>Data dimulai dari baris kedua</li>
                                    <li>Baris kosong akan diabaikan</li>
                                    <li>Data yang sudah ada akan tetap ditambahkan (tidak replace)</li>
                                    <li>Pastikan format data sesuai (angka untuk longitude/latitude, dll)</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

