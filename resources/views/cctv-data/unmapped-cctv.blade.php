@extends('layouts.master')

@section('title', 'CCTV Belum Termapping PJA')
@section('css')
    <link href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        #unmappedCctvTable td {
            vertical-align: middle;
        }
    </style>
@endsection 
@section('content')
<x-page-title title="CCTV Belum Termapping PJA" pagetitle="Daftar CCTV yang Belum Termapping PJA" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">CCTV Belum Termapping PJA</h5>
                        <p class="mb-0 text-muted">Daftar CCTV dari database utama yang belum memiliki mapping PJA</p>
                    </div>
                    <div class="dropdown">
                        <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                            data-bs-toggle="dropdown">
                            <span class="material-icons-outlined fs-5">more_vert</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('cctv-data.download-template-mapping-pja') }}"><i class="material-icons-outlined me-2">download</i> Download Template Mapping</a></li>
                            <li><a class="dropdown-item" href="{{ route('cctv-data.import-mapping-pja-form') }}"><i class="material-icons-outlined me-2">upload</i> Import Mapping PJA</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('cctv-data.pja-cctv-dedicated.index') }}"><i class="material-icons-outlined me-2">list</i> Data PJA CCTV Dedicated</a></li>
                            <li><a class="dropdown-item" href="{{ route('cctv-data.index') }}"><i class="material-icons-outlined me-2">arrow_back</i> Kembali</a></li>
                        </ul>
                    </div>
                </div>

                @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif

                @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif

                <div class="alert alert-warning">
                    <i class="material-icons-outlined me-2">info</i>
                    <strong>Informasi:</strong> Halaman ini menampilkan CCTV dari database utama (<code>cctv_data_bmo2</code>) yang belum memiliki mapping PJA di tabel <code>pja_cctv_dedicated</code>.
                </div>

                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <a href="{{ route('cctv-data.download-template-mapping-pja') }}" class="btn btn-success" id="btnDownloadTemplate">
                        <i class="material-icons-outlined">download</i> Download Template Mapping
                    </a>
                    <a href="{{ route('cctv-data.unmapped-cctv.export') }}" class="btn btn-info" id="btnExport">
                        <i class="material-icons-outlined">download</i> Export Excel
                    </a>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="material-icons-outlined">upload</i> Upload Mapping PJA
                    </button>
                    <a href="{{ route('cctv-data.pja-cctv-dedicated.index') }}" class="btn btn-info">
                        <i class="material-icons-outlined">list</i> Data PJA CCTV Dedicated
                    </a>
                    <a href="{{ route('cctv-data.index') }}" class="btn btn-secondary">
                        <i class="material-icons-outlined">arrow_back</i> Kembali
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="unmappedCctvTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Site</th>
                                <th>Perusahaan</th>
                                <th>No. CCTV</th>
                                <th>Nama CCTV</th>
                                <th>Status</th>
                                <th>Kondisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data akan dimuat via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Upload Mapping PJA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('cctv-data.import-mapping-pja') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="material-icons-outlined me-2">info</i>
                        <strong>Petunjuk:</strong>
                        <ol class="mb-0 mt-2 small">
                            <li>Download template Excel terlebih dahulu</li>
                            <li>Isi kolom <strong>PJA</strong> untuk setiap CCTV</li>
                            <li>Kolom <strong>NO</strong> bersifat opsional</li>
                            <li>Kolom <strong>CCTV Dedicated</strong> jangan diubah</li>
                            <li>Upload file Excel yang sudah diisi</li>
                        </ol>
                    </div>

                    @if(session('import_errors') && count(session('import_errors')) > 0)
                    <div class="alert alert-warning">
                        <strong>Peringatan:</strong> Beberapa data gagal diimpor:
                        <ul class="mb-0 mt-2 small">
                            @foreach(session('import_errors') as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <div class="mb-3">
                        <label for="file" class="form-label fw-bold">Pilih File Excel <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="file" name="file" accept=".xlsx,.xls" required>
                        <small class="form-text text-muted">
                            Format yang didukung: .xlsx, .xls (Maksimal 10MB)
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="material-icons-outlined">upload</i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ URL::asset('build/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ URL::asset('build/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        var table = $('#unmappedCctvTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('cctv-data.unmapped-cctv.data') }}",
                type: "GET"
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'site', name: 'site' },
                { data: 'perusahaan', name: 'perusahaan' },
                { data: 'no_cctv', name: 'no_cctv' },
                { data: 'nama_cctv', name: 'nama_cctv' },
                { data: 'status', name: 'status', orderable: false, searchable: false },
                { data: 'kondisi', name: 'kondisi', orderable: false, searchable: false }
            ],
            order: [[0, 'desc']],
            pageLength: 25,
            responsive: true,
            scrollX: true,
            language: {
                processing: "Memproses data...",
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                infoFiltered: "(disaring dari _MAX_ total data)",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                    previous: "Sebelumnya"
                },
                emptyTable: "Tidak ada data yang tersedia",
                zeroRecords: "Tidak ada data yang cocok dengan pencarian"
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            columnDefs: [
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: 3 },
                { responsivePriority: 3, targets: 4 }
            ]
        });

        // Handle export button click
        $('#btnExport').on('click', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            
            // Show loading
            Swal.fire({
                title: 'Mengekspor data...',
                text: 'Mohon tunggu',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Create a temporary form to download the file
            var form = $('<form>', {
                'method': 'GET',
                'action': url
            });
            $('body').append(form);
            form.submit();
            
            // Remove form after a delay
            setTimeout(function() {
                form.remove();
                Swal.close();
            }, 2000);
        });

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

        // Handle upload form submit
        $('#uploadForm').on('submit', function(e) {
            var fileInput = $('#file')[0];
            if (!fileInput.files || !fileInput.files[0]) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Silakan pilih file Excel terlebih dahulu.',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'OK'
                });
                return false;
            }

            // Show loading
            Swal.fire({
                title: 'Mengunggah file...',
                text: 'Mohon tunggu, sedang memproses data',
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

