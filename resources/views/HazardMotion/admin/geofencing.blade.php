@extends('layouts.masterMotionHazardAdmin')

@section('title', 'WMS Link Management - Beraucoal')

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    .stats-card {
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        color: white;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .stats-card.total {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .stats-card.week {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .stats-card.year {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .stats-card.links {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .stat-label {
        font-size: 14px;
        opacity: 0.9;
        font-weight: 500;
    }

    .stat-icon {
        font-size: 24px;
        opacity: 0.8;
    }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        margin: 0;
    }

    .wms-link-cell {
        max-width: 400px;
        word-break: break-all;
    }

    .wms-link-cell a {
        color: #2196F3;
        text-decoration: none;
        transition: color 0.2s;
    }

    .wms-link-cell a:hover {
        color: #1976D2;
        text-decoration: underline;
    }
</style>
@endsection

@section('content')
<x-page-title title="WMS Link Management" pagetitle="Upload and manage WMS (Web Map Service) links" />

<div class="row">
    <div class="col-12">
        @if($errors->any())
        <div class="alert alert-warning alert-dismissible fade show rounded-4" role="alert">
            <i class="material-icons-outlined me-2">warning</i>
            <strong>Peringatan:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif
    </div>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-12 col-xl-3 col-lg-6 d-flex">
        <div class="stats-card total w-100">
            <div class="stat-header">
                <span class="stat-label">Total WMS Links</span>
                <div class="stat-icon">
                    <i class="material-icons-outlined">link</i>
                </div>
            </div>
            <div class="stat-value">{{ $wmsLinks->total() }}</div>
        </div>
    </div>
    <div class="col-12 col-xl-3 col-lg-6 d-flex">
        <div class="stats-card week w-100">
            <div class="stat-header">
                <span class="stat-label">Current Week</span>
                <div class="stat-icon">
                    <i class="material-icons-outlined">calendar_today</i>
                </div>
            </div>
            <div class="stat-value">{{ $currentWeek }}</div>
        </div>
    </div>
    <div class="col-12 col-xl-3 col-lg-6 d-flex">
        <div class="stats-card year w-100">
            <div class="stat-header">
                <span class="stat-label">Current Year</span>
                <div class="stat-icon">
                    <i class="material-icons-outlined">event</i>
                </div>
            </div>
            <div class="stat-value">{{ $currentYear }}</div>
        </div>
    </div>
    <div class="col-12 col-xl-3 col-lg-6 d-flex">
        <div class="stats-card links w-100">
            <div class="stat-header">
                <span class="stat-label">This Week Links</span>
                <div class="stat-icon">
                    <i class="material-icons-outlined">map</i>
                </div>
            </div>
            <div class="stat-value">{{ $currentWeekLinksCount }}</div>
        </div>
    </div>
</div>

<!-- Upload Form -->
<div class="row">
    <div class="col-12 col-xl-6 d-flex">
        <div class="card rounded-4 w-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-bold">Upload WMS Link</h5>
                    <small class="text-muted">Links akan otomatis diassign ke Week {{ $currentWeek }}, {{ $currentYear }}</small>
                </div>
            </div>
            <div class="card-body">
                <form id="wmsUploadForm">
                    @csrf
                    <div class="mb-3">
                        <label for="location_name" class="form-label fw-semibold">Nama Lokasi</label>
                        <input type="text" 
                               class="form-control rounded-3" 
                               id="location_name" 
                               name="location_name" 
                               placeholder="Contoh: BMO Blok 10" 
                               value="{{ old('location_name') }}" 
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="wms_link" class="form-label fw-semibold">Link WMS</label>
                        <input type="url" 
                               class="form-control rounded-3" 
                               id="wms_link" 
                               name="wms_link" 
                               placeholder="https://sgi.beraucoal.co.id/server/services/Basemap_Layer_BMO_BLOCK_10/MapServer/WMSServer" 
                               value="{{ old('wms_link') }}" 
                               required>
                        <small class="text-muted">
                            <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">info</i>
                            Masukkan URL lengkap dari WMS server
                        </small>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary rounded-3" id="submitBtn">
                            <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">upload</i>
                            Upload WMS Link
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6 d-flex">
        <div class="card rounded-4 w-100">
            <div class="card-header">
                <h5 class="mb-0 fw-bold">Informasi</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div class="bg-primary bg-opacity-10 rounded-3 p-2">
                        <i class="material-icons-outlined text-primary">info</i>
                    </div>
                    <div>
                        <h6 class="mb-1">Week & Year Management</h6>
                        <p class="mb-0 text-muted small">
                            Setiap upload WMS link akan otomatis diassign ke week dan year saat ini. 
                            Week akan reset ke 1 setiap tahun baru.
                        </p>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div class="bg-success bg-opacity-10 rounded-3 p-2">
                        <i class="material-icons-outlined text-success">update</i>
                    </div>
                    <div>
                        <h6 class="mb-1">Update Weekly</h6>
                        <p class="mb-0 text-muted small">
                            WMS links harus diupdate setiap minggu untuk memastikan data selalu terbaru.
                        </p>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-3">
                    <div class="bg-warning bg-opacity-10 rounded-3 p-2">
                        <i class="material-icons-outlined text-warning">link</i>
                    </div>
                    <div>
                        <h6 class="mb-1">Format Link</h6>
                        <p class="mb-0 text-muted small">
                            Pastikan link WMS adalah URL lengkap yang valid dan dapat diakses.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WMS Links Table -->
<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <h5 class="mb-0 fw-bold">Daftar WMS Links</h5>
                    <small class="text-muted">
                        Menampilkan {{ $wmsLinks->firstItem() ?? 0 }}-{{ $wmsLinks->lastItem() ?? 0 }} dari {{ $wmsLinks->total() }} data
                    </small>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Nama Lokasi</th>
                                <th>Link WMS</th>
                                <th style="width: 120px;">Week</th>
                                <th style="width: 100px;">Year</th>
                                <th style="width: 180px;">Created At</th>
                                <th style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($wmsLinks as $index => $link)
                            <tr>
                                <td class="text-center">{{ $wmsLinks->firstItem() + $index }}</td>
                                <td>
                                    <strong>{{ $link->location_name }}</strong>
                                </td>
                                <td class="wms-link-cell">
                                    <a href="{{ $link->wms_link }}" target="_blank" title="{{ $link->wms_link }}" class="d-inline-flex align-items-center gap-1">
                                        <i class="material-icons-outlined" style="font-size: 16px;">open_in_new</i>
                                        {{ Str::limit($link->wms_link, 60) }}
                                    </a>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary bg-opacity-10 text-primary">Week {{ $link->week }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success bg-opacity-10 text-success">{{ $link->year }}</span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">schedule</i>
                                        {{ $link->created_at->format('d M Y H:i') }}
                                    </small>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-primary rounded-3" onclick="editWmsLink({{ $link->id }})" title="Edit">
                                            <i class="material-icons-outlined" style="font-size: 16px;">edit</i>
                                        </button>
                                        <button class="btn btn-sm btn-danger rounded-3" onclick="deleteWmsLink({{ $link->id }})" title="Delete">
                                            <i class="material-icons-outlined" style="font-size: 16px;">delete</i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                        <i class="material-icons-outlined mb-3" style="font-size: 64px; color: #9ca3af; opacity: 0.5;">inbox</i>
                                        <h6 class="text-muted mb-1">Belum ada WMS link yang diupload</h6>
                                        <p class="text-muted small mb-0">Mulai dengan mengupload WMS link pertama Anda</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($wmsLinks->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $wmsLinks->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editModalLabel">Edit WMS Link</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="edit_wms_id" name="wms_id">
                    <div class="mb-3">
                        <label for="edit_location_name" class="form-label fw-semibold">Nama Lokasi</label>
                        <input type="text" class="form-control rounded-3" id="edit_location_name" name="location_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_wms_link" class="form-label fw-semibold">Link WMS</label>
                        <input type="url" class="form-control rounded-3" id="edit_wms_link" name="wms_link" required>
                        <small class="text-muted">
                            <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">info</i>
                            Link akan diupdate ke Week {{ $currentWeek }}, {{ $currentYear }}
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">
                    <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">close</i>
                    Cancel
                </button>
                <button type="button" class="btn btn-primary rounded-3" id="updateBtn" onclick="submitEditForm()">
                    <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">update</i>
                    Update
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let editModal;
    let currentWmsId = null;

    document.addEventListener('DOMContentLoaded', function() {
        editModal = new bootstrap.Modal(document.getElementById('editModal'));

        // Show success/error message dari session
        @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '{{ session('success') }}',
            confirmButtonColor: '#2196F3',
            confirmButtonText: 'OK',
            timer: 3000,
            timerProgressBar: true
        });
        @endif

        @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '{{ session('error') }}',
            confirmButtonColor: '#f44336',
            confirmButtonText: 'OK'
        });
        @endif
    });

    // Upload Form Handler
    document.getElementById('wmsUploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        const formData = new FormData(this);
        
        // Show loading
        Swal.fire({
            title: 'Mengupload WMS Link...',
            text: 'Mohon tunggu',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">hourglass_empty</i> Uploading...';

        fetch('{{ route('geofencing.wms.store') }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: data.message || 'WMS link berhasil diupload',
                    confirmButtonColor: '#2196F3',
                    confirmButtonText: 'OK',
                    timer: 3000,
                    timerProgressBar: true
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: data.message || 'Gagal mengupload WMS link',
                    confirmButtonColor: '#f44336',
                    confirmButtonText: 'OK'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">upload</i> Upload WMS Link';
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Terjadi kesalahan saat mengupload WMS link',
                confirmButtonColor: '#f44336',
                confirmButtonText: 'OK'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">upload</i> Upload WMS Link';
        });
    });

    function editWmsLink(id) {
        currentWmsId = id;
        
        // Show loading
        Swal.fire({
            title: 'Memuat data...',
            text: 'Mohon tunggu',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Fetch WMS link data
        fetch(`{{ url('geofencing/wms') }}/${id}`)
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    document.getElementById('edit_wms_id').value = id;
                    document.getElementById('edit_location_name').value = data.data.location_name;
                    document.getElementById('edit_wms_link').value = data.data.wms_link;
                    editModal.show();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: 'Gagal memuat data WMS link',
                        confirmButtonColor: '#f44336',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Terjadi kesalahan saat memuat data',
                    confirmButtonColor: '#f44336',
                    confirmButtonText: 'OK'
                });
            });
    }

    function submitEditForm() {
        if (!currentWmsId) return;

        const form = document.getElementById('editForm');
        const formData = new FormData(form);
        
        // Ensure _method is set to PUT
        formData.set('_method', 'PUT');
        
        const updateBtn = document.getElementById('updateBtn');

        // Show loading
        Swal.fire({
            title: 'Mengupdate WMS Link...',
            text: 'Mohon tunggu',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        updateBtn.disabled = true;
        updateBtn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">hourglass_empty</i> Updating...';

        fetch(`{{ url('geofencing/wms') }}/${currentWmsId}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                editModal.hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: data.message || 'WMS link berhasil diupdate',
                    confirmButtonColor: '#2196F3',
                    confirmButtonText: 'OK',
                    timer: 3000,
                    timerProgressBar: true
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: data.message || 'Gagal mengupdate WMS link',
                    confirmButtonColor: '#f44336',
                    confirmButtonText: 'OK'
                });
                updateBtn.disabled = false;
                updateBtn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">update</i> Update';
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Terjadi kesalahan saat mengupdate WMS link',
                confirmButtonColor: '#f44336',
                confirmButtonText: 'OK'
            });
            updateBtn.disabled = false;
            updateBtn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">update</i> Update';
        });
    }

    function deleteWmsLink(id) {
        Swal.fire({
            icon: 'warning',
            title: 'Hapus WMS Link?',
            text: 'Apakah Anda yakin ingin menghapus WMS link ini? Tindakan ini tidak dapat dibatalkan.',
            showCancelButton: true,
            confirmButtonColor: '#f44336',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Menghapus WMS Link...',
                    text: 'Mohon tunggu',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('_method', 'DELETE');

                fetch(`{{ url('geofencing/wms') }}/${id}`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: data.message || 'WMS link berhasil dihapus',
                            confirmButtonColor: '#2196F3',
                            confirmButtonText: 'OK',
                            timer: 3000,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: data.message || 'Gagal menghapus WMS link',
                            confirmButtonColor: '#f44336',
                            confirmButtonText: 'OK'
                        });
                    }
                })
                .catch(error => {
                    Swal.close();
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Terjadi kesalahan saat menghapus WMS link',
                        confirmButtonColor: '#f44336',
                        confirmButtonText: 'OK'
                    });
                });
            }
        });
    }
</script>
@endsection
