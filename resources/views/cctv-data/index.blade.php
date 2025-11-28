@extends('layouts.master')

@section('title', 'Data CCTV')
@section('css')
    <link href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection 
@section('content')
<x-page-title title="Data CCTV" pagetitle="Daftar Data CCTV" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">Data CCTV</h5>
                        <p class="mb-0 text-muted">Daftar Data CCTV</p>
                    </div>
                    <div class="dropdown">
                        <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                            data-bs-toggle="dropdown">
                            <span class="material-icons-outlined fs-5">more_vert</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('cctv-data.create') }}"><i class="material-icons-outlined me-2">add</i> Tambah Data</a></li>
                            <li><a class="dropdown-item" href="{{ route('cctv-data.import-form') }}"><i class="material-icons-outlined me-2">upload</i> Import Excel</a></li>
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

                @if(session('import_errors') && count(session('import_errors')) > 0)
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>Peringatan:</strong> Beberapa data gagal diimpor:
                    <ul class="mb-0 mt-2 small">
                        @foreach(session('import_errors') as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif

                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <a href="{{ route('cctv-data.import-form') }}" class="btn btn-success">
                        <i class="material-icons-outlined">upload</i> Import Excel
                    </a>
                    <a href="{{ route('cctv-data.create') }}" class="btn btn-primary">
                        <i class="material-icons-outlined">add</i> Tambah Data
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="cctvDataTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Site</th>
                                <th>Perusahaan</th>
                                <th>No. CCTV</th>
                                <th>Nama CCTV</th>
                                <th>Status</th>
                                <th>Kondisi</th>
                                <th>QR Code</th>
                                <th>Actions</th>
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

<!-- QR Code Modal - akan dibuat secara dinamis -->
<div id="qrModalContainer"></div>

@endsection

@section('scripts')
<script src="{{ URL::asset('build/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ URL::asset('build/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        var table = $('#cctvDataTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('cctv-data.data') }}",
                type: "GET"
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'site', name: 'site' },
                { data: 'perusahaan', name: 'perusahaan' },
                { data: 'no_cctv', name: 'no_cctv' },
                { data: 'nama_cctv', name: 'nama_cctv' },
                { data: 'status', name: 'status', orderable: false, searchable: false },
                { data: 'kondisi', name: 'kondisi', orderable: false, searchable: false },
                { data: 'qr_code', name: 'qr_code', orderable: false, searchable: false },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ],
            order: [[0, 'desc']], // Order by column index 0 (id)
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
                { responsivePriority: 3, targets: 4 },
                { responsivePriority: 4, targets: 8 }
            ],
            drawCallback: function(settings) {
                // Buat modal QR code untuk setiap baris yang ditampilkan
                var api = this.api();
                var data = api.rows({page: 'current'}).data();
                
                // Hapus modal lama
                $('#qrModalContainer').empty();
                
                // Base URL untuk QR code
                var baseUrl = "{{ url('/') }}";
                
                // Buat modal untuk setiap data yang ditampilkan
                $.each(data, function(index, row) {
                    var modalId = 'qrModal' + row.id;
                    var qrCodeUrl = baseUrl + '/cctv-data/' + row.id + '/qr-code';
                    var qrCodeDownloadUrl = baseUrl + '/cctv-data/' + row.id + '/qr-code/download';
                    
                    var modalHtml = '<div class="modal fade" id="' + modalId + '" tabindex="-1" aria-labelledby="qrModalLabel' + row.id + '" aria-hidden="true">' +
                        '<div class="modal-dialog modal-dialog-centered modal-lg">' +
                            '<div class="modal-content">' +
                                '<div class="modal-header">' +
                                    '<h5 class="modal-title" id="qrModalLabel' + row.id + '">QR Code - ' + row.nama_cctv_display + '</h5>' +
                                    '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                                '</div>' +
                                '<div class="modal-body text-center">' +
                                    '<div class="mb-3">' +
                                        '<img src="' + qrCodeUrl + '" alt="QR Code" class="img-fluid" style="max-width: 400px; border: 10px solid white; box-shadow: 0 4px 8px rgba(0,0,0,0.1);" onerror="this.onerror=null; this.parentElement.innerHTML=\'<p class=\\\'text-muted\\\'>Gagal memuat gambar QR code.</p>\';">' +
                                    '</div>' +
                                    '<p class="mt-3 mb-0 text-muted small">Scan QR code untuk melihat data CCTV</p>' +
                                    '<p class="mb-0 text-muted small">No. CCTV: ' + row.no_cctv_display + '</p>' +
                                '</div>' +
                                '<div class="modal-footer">' +
                                    '<a href="' + qrCodeDownloadUrl + '" class="btn btn-primary">' +
                                        '<i class="material-icons-outlined">download</i> Download QR Code' +
                                    '</a>' +
                                    '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                    $('#qrModalContainer').append(modalHtml);
                });
            }
        });

        // Handle delete dengan Sweet Alert
        $(document).on('click', '.btn-delete', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var id = $(this).data('id');
            var nama = $(this).data('nama');
            var deleteUrl = "{{ url('cctv-data') }}/" + id;
            
            console.log('Delete clicked - ID:', id, 'URL:', deleteUrl);
            
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Data CCTV \"" + nama + "\" akan dihapus secara permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Menghapus...',
                        text: 'Mohon tunggu',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Submit via AJAX
                    $.ajax({
                        url: deleteUrl,
                        type: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        data: {
                            _token: '{{ csrf_token() }}',
                            _method: 'DELETE'
                        },
                        dataType: 'json',
                        success: function(response) {
                            console.log('Delete success:', response);
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: response.message || 'Data CCTV berhasil dihapus.',
                                confirmButtonColor: '#3085d6',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                // Reload DataTable
                                table.ajax.reload(null, false);
                            });
                        },
                        error: function(xhr, status, error) {
                            console.error('Delete error:', {
                                status: xhr.status,
                                statusText: xhr.statusText,
                                responseText: xhr.responseText,
                                error: error
                            });
                            
                            var errorMessage = 'Terjadi kesalahan saat menghapus data.';
                            
                            if (xhr.status === 404) {
                                errorMessage = 'Data tidak ditemukan.';
                            } else if (xhr.status === 403) {
                                errorMessage = 'Anda tidak memiliki izin untuk menghapus data ini.';
                            } else if (xhr.status === 500) {
                                errorMessage = 'Terjadi kesalahan server. Silakan coba lagi.';
                            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            } else if (xhr.responseText) {
                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.message) {
                                        errorMessage = response.message;
                                    }
                                } catch (e) {
                                    // If not JSON, use default message
                                }
                            }
                            
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: errorMessage,
                                confirmButtonColor: '#3085d6',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
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

