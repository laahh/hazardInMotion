@extends('layouts.master')

@section('title', 'PJA CCTV Dedicated')
@section('css')
    <link href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        #pjaCctvDedicatedTable td {
            vertical-align: top;
        }
        #pjaCctvDedicatedTable .pja-list {
            max-width: 500px;
        }
    </style>
@endsection 
@section('content')
<x-page-title title="PJA CCTV Dedicated" pagetitle="Daftar Data PJA CCTV Dedicated" />





    <div class="col-12  d-flex">
            <div class="card rounded-4 w-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-around flex-wrap gap-4 p-4">
                  <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <a href="javascript:;" class="mb-2 wh-48 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">videocam</i>
                    </a>
                    <h3 class="mb-0">{{ number_format($stats['total_cctv'] ?? 0) }}</h3>
                    <p class="mb-0">Total CCTV</p>
                  </div>
                  <div class="vr"></div>
                  <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <a href="javascript:;" class="mb-2 wh-48 bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">check_circle</i>
                    </a>
                    <h3 class="mb-0">{{ number_format($stats['mapped_cctv'] ?? 0) }}</h3>
                    <p class="mb-0">CCTV Sudah Ada PJA</p>
                  </div>
                  <div class="vr"></div>
                  <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <a href="javascript:;" class="mb-2 wh-48 bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">notifications</i>
                    </a>
                    <h3 class="mb-0">{{ number_format($stats['unmapped_cctv'] ?? 0) }}</h3>
                    <p class="mb-0">CCTV Belum Ada PJA</p>
                  </div>
                  <div class="vr"></div>
                  
                  <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <a href="javascript:;" class="mb-2 wh-48 bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">warning</i>
                    </a>
                    <h3 class="mb-0">{{ $stats['mapped_percentage'] ?? 0 }}%</h3>
                    <p class="mb-0">Presentase Sudah Ada PJA</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div><!--end row-->






<div class="row  p-4 ">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <div>
                            <h5 class="mb-0 fw-bold">Data PJA CCTV Dedicated</h5>
                            <p class="mb-0 text-muted">Daftar Data PJA CCTV Dedicated</p>
                        </div>
                        <div class="dropdown">
                            <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                                data-bs-toggle="dropdown">
                                <span class="material-icons-outlined fs-5">more_vert</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('cctv-data.import-pja-cctv-dedicated-form') }}"><i class="material-icons-outlined me-2">upload</i> Import Excel</a></li>
                                <li><a class="dropdown-item" href="{{ route('cctv-data.unmapped-cctv.index') }}"><i class="material-icons-outlined me-2">warning</i> CCTV Belum Termapping</a></li>
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

                    <div class="d-flex gap-2 mb-3 flex-wrap">
                       
                        <a href="{{ route('cctv-data.unmapped-cctv.index') }}" class="btn btn-warning">
                            <i class="material-icons-outlined">warning</i> CCTV Belum Termapping
                        </a>
                        <a href="{{ route('cctv-data.index') }}" class="btn btn-secondary">
                            <i class="material-icons-outlined">arrow_back</i> Kembali
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover" id="pjaCctvDedicatedTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>NO</th>
                                    <th>CCTV Dedicated</th>
                                    <th>Jumlah PJA</th>
                                    <th>PJA</th>
                                    <th>Created At</th>
                                    <th>Updated At</th>
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
</div>

@endsection

@section('scripts')
<script src="{{ URL::asset('build/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ URL::asset('build/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        var table = $('#pjaCctvDedicatedTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('cctv-data.pja-cctv-dedicated.data') }}",
                type: "GET"
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'no', name: 'no' },
                { data: 'cctv_dedicated', name: 'cctv_dedicated' },
                { data: 'pja_count', name: 'pja_count', orderable: true, searchable: false },
                { data: 'pja', name: 'pja', orderable: false, searchable: true },
                { data: 'created_at', name: 'created_at' },
                { data: 'updated_at', name: 'updated_at' }
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
                { responsivePriority: 2, targets: 2 },
                { responsivePriority: 3, targets: 3 },
                { responsivePriority: 4, targets: 4 },
                { 
                    targets: 4, // PJA column
                    className: 'text-start',
                    render: function(data, type, row) {
                        if (type === 'display' || type === 'type') {
                            return data; // HTML sudah diformat di server
                        }
                        return row.pja_raw || ''; // Untuk search/filter
                    }
                },
                {
                    targets: 3, // Jumlah PJA column
                    className: 'text-center'
                }
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

