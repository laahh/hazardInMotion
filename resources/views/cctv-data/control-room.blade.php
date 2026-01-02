@extends('layouts.master')

@section('title', 'CCTV Control Room')
@section('css')
    <link href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        #controlRoomTable td {
            vertical-align: top;
        }
        #controlRoomTable .cctv-list {
            max-width: 400px;
        }
    </style>
@endsection 
@section('content')
<x-page-title title="CCTV Control Room" pagetitle="Daftar CCTV per Control Room" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">CCTV Control Room</h5>
                        <p class="mb-0 text-muted">Daftar CCTV yang dikelompokkan berdasarkan Control Room</p>
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

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="controlRoomTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Control Room</th>
                                <th>Site</th>
                                <th>Perusahaan</th>
                                <th>Jumlah CCTV</th>
                                <th>Daftar CCTV</th>
                                <th>Pengawas</th>
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

<!-- Modal untuk Tambah Pengawas -->
<div class="modal fade" id="pengawasModal" tabindex="-1" aria-labelledby="pengawasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pengawasModalLabel">Tambah Pengawas Control Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="pengawasForm">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="control_room" name="control_room">
                    
                    <div class="mb-3">
                        <label for="control_room_display" class="form-label fw-bold">Control Room <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="control_room_display" readonly>
                        <small class="text-muted">Control Room tidak dapat diubah</small>
                    </div>

                    <div class="mb-3">
                        <label for="nama_pengawas" class="form-label fw-bold">Nama Pengawas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_pengawas" name="nama_pengawas" required>
                    </div>

                    <div class="mb-3">
                        <label for="email_pengawas" class="form-label">Email Pengawas</label>
                        <input type="email" class="form-control" id="email_pengawas" name="email_pengawas">
                    </div>

                    <div class="mb-3">
                        <label for="no_hp_pengawas" class="form-label">No. HP Pengawas</label>
                        <input type="text" class="form-control" id="no_hp_pengawas" name="no_hp_pengawas">
                    </div>

                    <div class="mb-3">
                        <label for="keterangan" class="form-label">Keterangan</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="material-icons-outlined">add</i> Tambah Pengawas
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
        var table = $('#controlRoomTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('cctv-data.control-room.data') }}",
                type: "GET"
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'control_room', name: 'control_room' },
                { data: 'site', name: 'site' },
                { data: 'perusahaan', name: 'perusahaan' },
                { data: 'cctv_count', name: 'cctv_count', orderable: true, searchable: false },
                { data: 'cctv_list', name: 'cctv_list', orderable: false, searchable: false },
                { data: 'pengawas', name: 'pengawas', orderable: false, searchable: false },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
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
                { responsivePriority: 2, targets: 1 },
                { responsivePriority: 3, targets: 4 },
                { responsivePriority: 4, targets: 7 },
                { 
                    targets: 5, // CCTV List column
                    className: 'text-start'
                },
                {
                    targets: 4, // Jumlah CCTV column
                    className: 'text-center'
                }
            ],
            drawCallback: function(settings) {
                // Buat tombol actions untuk setiap baris
                var api = this.api();
                var data = api.rows({page: 'current'}).data();
                
                $.each(data, function(index, row) {
                    var controlRoom = row.control_room_raw || '';
                    var pengawasList = row.pengawas_list || [];
                    
                    // Buat tombol actions - hanya tombol tambah pengawas
                    var actionsHtml = '<div class="d-flex gap-2 flex-wrap">' +
                        '<button type="button" class="btn btn-sm btn-primary btn-add-pengawas" ' +
                        'data-control-room="' + encodeURIComponent(controlRoom) + '" ' +
                        'title="Tambah Pengawas">' +
                        '<i class="material-icons-outlined">person_add</i></button>' +
                        '</div>';
                    
                    // Update kolom actions
                    var rowNode = api.row(index).node();
                    if (rowNode) {
                        $(rowNode).find('td:last').html(actionsHtml);
                    }
                    
                });
            }
        });

        // Handle tambah pengawas
        $(document).on('click', '.btn-add-pengawas', function() {
            var controlRoom = decodeURIComponent($(this).data('control-room'));
            
            $('#control_room').val(controlRoom);
            $('#control_room_display').val(controlRoom);
            
            // Reset form
            $('#nama_pengawas').val('');
            $('#email_pengawas').val('');
            $('#no_hp_pengawas').val('');
            $('#keterangan').val('');
            
            $('#pengawasModalLabel').text('Tambah Pengawas Control Room');
            $('#pengawasModal').modal('show');
        });

        // Handle submit form pengawas
        $('#pengawasForm').on('submit', function(e) {
            e.preventDefault();
            
            var formData = {
                control_room: $('#control_room').val(),
                nama_pengawas: $('#nama_pengawas').val(),
                email_pengawas: $('#email_pengawas').val(),
                no_hp_pengawas: $('#no_hp_pengawas').val(),
                keterangan: $('#keterangan').val(),
                _token: '{{ csrf_token() }}'
            };

            Swal.fire({
                title: 'Menyimpan data...',
                text: 'Mohon tunggu',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: "{{ route('cctv-data.control-room.pengawas.store') }}",
                type: "POST",
                data: formData,
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: response.message || 'Data pengawas berhasil disimpan.',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        $('#pengawasModal').modal('hide');
                        // Reset form
                        $('#pengawasForm')[0].reset();
                        $('#control_room').val('');
                        $('#control_room_display').val('');
                        table.ajax.reload(null, false);
                    });
                },
                error: function(xhr) {
                    var errorMessage = 'Terjadi kesalahan saat menyimpan data.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
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
        });

        // Handle delete pengawas
        $(document).on('click', '.btn-delete-pengawas', function() {
            var pengawasId = $(this).data('pengawas-id');
            var controlRoom = decodeURIComponent($(this).data('control-room'));
            
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Data pengawas untuk Control Room \"" + controlRoom + "\" akan dihapus!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus...',
                        text: 'Mohon tunggu',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: "{{ url('cctv-data/control-room/pengawas') }}/" + pengawasId,
                        type: "DELETE",
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: response.message || 'Data pengawas berhasil dihapus.',
                                confirmButtonColor: '#3085d6',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                table.ajax.reload(null, false);
                            });
                        },
                        error: function(xhr) {
                            var errorMessage = 'Terjadi kesalahan saat menghapus data.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
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

