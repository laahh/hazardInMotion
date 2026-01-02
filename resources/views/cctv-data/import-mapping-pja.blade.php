@extends('layouts.master')

@section('title', 'Import Mapping PJA CCTV')
@section('css')
    
@endsection 
@section('content')
<x-page-title title="Import Mapping PJA CCTV" pagetitle="Upload Excel Mapping PJA ke CCTV" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">Import Mapping PJA ke CCTV</h5>
                        <p class="mb-0 text-muted">Upload file Excel yang sudah diisi untuk mapping PJA ke CCTV</p>
                    </div>
                    <div class="dropdown">
                        <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                            data-bs-toggle="dropdown">
                            <span class="material-icons-outlined fs-5">more_vert</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('cctv-data.unmapped-cctv.index') }}"><i class="material-icons-outlined me-2">list</i> CCTV Belum Termapping</a></li>
                            <li><a class="dropdown-item" href="{{ route('cctv-data.pja-cctv-dedicated.index') }}"><i class="material-icons-outlined me-2">list</i> Data PJA CCTV Dedicated</a></li>
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
                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Langkah-langkah:</h6>
                                <ol class="mb-0">
                                    <li class="mb-2">Download template Excel dengan klik tombol <strong>"Download Template"</strong> di bawah</li>
                                    <li class="mb-2">Buka file Excel yang sudah didownload</li>
                                    <li class="mb-2">Isi kolom <strong>PJA</strong> dengan nama PJA yang akan di-mapping ke CCTV tersebut</li>
                                    <li class="mb-2">Jika satu CCTV memiliki beberapa PJA, buat baris baru untuk setiap PJA</li>
                                    <li class="mb-2">Simpan file Excel</li>
                                    <li class="mb-2">Upload file Excel yang sudah diisi melalui form di bawah</li>
                                </ol>
                            </div>
                        </div>

                        <form action="{{ route('cctv-data.import-mapping-pja') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            
                            <div class="mb-4">
                                <label for="file" class="form-label fw-bold">Upload File Excel yang Sudah Diisi <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="file" name="file" accept=".xlsx,.xls,.csv" required>
                                <small class="form-text text-muted">
                                    Format yang didukung: .xlsx, .xls, .csv (Maksimal 10MB)
                                </small>
                            </div>

                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons-outlined">upload</i> Import Mapping
                                </button>
                                <a href="{{ route('cctv-data.unmapped-cctv.index') }}" class="btn btn-secondary">
                                    <i class="material-icons-outlined">close</i> Batal
                                </a>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <div class="card rounded-4 bg-light">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <h6 class="fw-bold mb-0">Download Template</h6>
                                </div>
                                <p class="small mb-3">Download template Excel yang berisi CCTV yang belum termapping PJA. Template sudah siap untuk diisi kolom PJA-nya.</p>
                                <a href="{{ route('cctv-data.download-template-mapping-pja') }}" class="btn btn-success w-100" id="btnDownloadTemplate">
                                    <i class="material-icons-outlined">download</i> Download Template
                                </a>
                                
                                <div class="mt-3 p-2 bg-info bg-opacity-10 rounded">
                                    <small class="text-info">
                                        <strong>Tips:</strong> Template akan berisi semua CCTV yang belum termapping PJA. Isi kolom PJA dengan nama PJA yang sesuai, kemudian upload kembali file tersebut.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-4 bg-light mt-3">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <h6 class="fw-bold mb-0">Format File Excel</h6>
                                </div>
                                <p class="small mb-2">File Excel harus memiliki kolom-kolom berikut:</p>
                                <ul class="small mb-0">
                                    <li><strong>NO</strong> - Nomor urut (opsional)</li>
                                    <li><strong>CCTV</strong> - Nama CCTV (wajib, jangan diubah)</li>
                                    <li><strong>PJA</strong> - Nama PJA (wajib, harus diisi)</li>
                                    <li><strong>Keterangan</strong> - Opsional</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        // Handle download template button click
        $('#btnDownloadTemplate').on('click', function(e) {
            // Show loading
            Swal.fire({
                title: 'Menyiapkan template...',
                text: 'Mohon tunggu',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });

        // Show success/error message dari session
        @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '{{ session('success') }}',
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'OK'
        });
        @endif

        @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '{{ session('error') }}',
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'OK'
        });
        @endif
    });
</script>
@endsection

