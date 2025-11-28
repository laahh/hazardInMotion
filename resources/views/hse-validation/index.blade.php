@extends('layouts.master')

@section('title', 'HSE Validation')
@section('css')
    <link href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
@endsection 
@section('content')
<x-page-title title="HSE Validation" pagetitle="Validasi Laporan HSE Berbasis AI" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <h5 class="mb-3 fw-bold">Upload File Excel/CSV</h5>
                <p class="text-muted mb-4">
                    Upload file Excel atau CSV yang berisi kolom <strong>Deskripsi</strong> dan <strong>Url Photo</strong>.
                    Sistem akan melakukan validasi otomatis menggunakan AI untuk setiap baris data.
                </p>

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <form action="{{ route('hse-validation.process') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="file" class="form-label">Pilih File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="file" name="file" accept=".xlsx,.xls,.csv" required>
                        <small class="form-text text-muted">Format yang didukung: .xlsx, .xls, .csv (Maksimal 10MB)</small>
                    </div>

                    <div class="mb-3">
                        <div class="alert alert-info">
                            <h6 class="alert-heading">Format File:</h6>
                            <p class="mb-0">File harus memiliki kolom berikut:</p>
                            <ul class="mb-0">
                                <li><strong>Deskripsi</strong> - Teks temuan HSE</li>
                                <li><strong>Url Photo</strong> - Tautan ke foto temuan</li>
                            </ul>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="ri-upload-cloud-2-line me-1"></i> Upload dan Validasi
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection 
@section('scripts')  
    <script src="{{ URL::asset('build/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
@endsection

