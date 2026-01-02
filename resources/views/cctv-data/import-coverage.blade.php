@extends('layouts.master')

@section('title', 'Import CCTV Coverage')
@section('css')
    <link href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection 
@section('content')
<x-page-title title="CCTV Coverage" pagetitle="Import Coverage dari Excel" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">Import CCTV Coverage dari Excel</h5>
                        <p class="mb-0 text-muted">Upload file Excel (.xlsx, .xls) atau CSV untuk mengimpor data coverage CCTV secara massal</p>
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
                                    <li class="mb-2">Isi data sesuai dengan format yang ada di template</li>
                                    <li class="mb-2">Pastikan Site, Perusahaan CCTV, dan Nomer CCTV sesuai dengan data di database</li>
                                    <li class="mb-2">Simpan file Excel</li>
                                    <li class="mb-2">Upload file Excel yang sudah diisi melalui form di bawah</li>
                                </ol>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mb-3">
                            <a href="{{ route('cctv-data.download-template-coverage') }}" class="btn btn-success" id="btnDownloadTemplate">
                                <i class="material-icons-outlined">download</i> Download Template Excel
                            </a>
                        </div>

                        <form action="{{ route('cctv-data.import-coverage') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            
                            <div class="mb-4">
                                <label for="file" class="form-label fw-bold">Pilih File Excel/CSV yang Sudah Diisi <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="file" name="file" accept=".xlsx,.xls,.csv" required>
                                <small class="form-text text-muted">
                                    Format yang didukung: .xlsx, .xls, .csv (Maksimal 10MB)
                                </small>
                            </div>

                            <div class="alert alert-info">
                                <h6 class="fw-bold mb-2">Format File Excel yang Diperlukan:</h6>
                                <p class="mb-2">File Excel harus memiliki header pada baris pertama dengan kolom-kolom berikut:</p>
                                <ul class="mb-0 small">
                                    <li><strong>Site</strong> - Site CCTV (contoh: HO, LMO)</li>
                                    <li><strong>Perusahaan CCTV</strong> - Nama perusahaan (contoh: PT Fajar Anugerah Dinamika)</li>
                                    <li><strong>Nomer CCTV</strong> - Nomor CCTV (contoh: CCTV 01 FAD LMO, LMO-FAD-0001, atau 2 (Dermaga PMO-BMO))</li>
                                    <li><strong>Coverage Lokasi</strong> - Lokasi coverage (contoh: Dermaga, Workshop FAD)</li>
                                    <li><strong>Coverage Detail Lokasi</strong> - Detail lokasi coverage (contoh: Dermaga FAD Prapatan, Base Workshop)</li>
                                </ul>
                                <p class="mb-0 mt-2"><strong>Catatan Penting:</strong></p>
                                <ul class="mb-0 small mt-1">
                                    <li>Data CCTV harus sudah ada di database terlebih dahulu</li>
                                    <li>Sistem akan mencocokkan berdasarkan Site, Perusahaan CCTV, dan Nomer CCTV</li>
                                    <li>Sistem mendukung berbagai format nomor CCTV (contoh: "CCTV 01 FAD LMO" akan dicocokkan dengan "LMO-FAD-0001")</li>
                                    <li>Data coverage yang sudah ada akan di-skip (tidak duplikat)</li>
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
                                    <li>Kolom wajib: Site, Perusahaan CCTV, Nomer CCTV</li>
                                    <li>Data dimulai dari baris kedua</li>
                                    <li>Baris kosong akan diabaikan</li>
                                    <li>Data CCTV harus sudah ada di database</li>
                                    <li>Sistem akan mencocokkan nomor CCTV secara fleksibel</li>
                                    <li>Data coverage duplikat akan di-skip</li>
                                </ol>
                                <div class="mt-3 p-2 bg-warning bg-opacity-10 rounded">
                                    <small class="text-warning">
                                        <strong>Tips:</strong> Jika CCTV tidak ditemukan, pastikan Site, Perusahaan, dan format Nomer CCTV sesuai dengan data di database.
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

<!-- Data Table Section -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">Data CCTV Coverage</h5>
                        <p class="mb-0 text-muted">Daftar Data CCTV Coverage yang sudah diimport</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="coverageDataTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>No. CCTV</th>
                                <th>Coverage Lokasi</th>
                                <th>Coverage Detail Lokasi</th>
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

@endsection

@section('scripts')
<script src="{{ URL::asset('build/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ URL::asset('build/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        var table = $('#coverageDataTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('cctv-data.coverage.data') }}",
                type: "GET"
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'no_cctv', name: 'no_cctv' },
                { data: 'coverage_lokasi', name: 'coverage_lokasi' },
                { data: 'coverage_detail_lokasi', name: 'coverage_detail_lokasi' },
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
                { responsivePriority: 2, targets: 1 },
                { responsivePriority: 3, targets: 2 }
            ]
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

        // Show success/error message dari session
        @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '{{ session('success') }}',
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'OK'
        }).then(() => {
            // Reload DataTable setelah import berhasil
            table.ajax.reload(null, false);
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

