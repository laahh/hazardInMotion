@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Area Kerja & Area CCTV - Beraucoal')

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

    .stats-card.area-kerja {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .stats-card.area-cctv {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .stats-card.week {
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

    .file-name-cell {
        max-width: 200px;
        word-break: break-all;
    }
</style>
@endsection

@section('content')
<x-page-title title="Area Kerja & Area CCTV" pagetitle="Upload dan kelola file GeoJSON untuk area kerja dan area CCTV" />

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
                <span class="stat-label">Total GeoJSON</span>
                <div class="stat-icon">
                    <i class="material-icons-outlined">map</i>
                </div>
            </div>
            <div class="stat-value">{{ $geojsonAreas->total() }}</div>
        </div>
    </div>
    <div class="col-12 col-xl-3 col-lg-6 d-flex">
        <div class="stats-card area-kerja w-100">
            <div class="stat-header">
                <span class="stat-label">Area Kerja</span>
                <div class="stat-icon">
                    <i class="material-icons-outlined">work</i>
                </div>
            </div>
            <div class="stat-value">{{ $areaKerjaCount }}</div>
        </div>
    </div>
    <div class="col-12 col-xl-3 col-lg-6 d-flex">
        <div class="stats-card area-cctv w-100">
            <div class="stat-header">
                <span class="stat-label">Area CCTV</span>
                <div class="stat-icon">
                    <i class="material-icons-outlined">videocam</i>
                </div>
            </div>
            <div class="stat-value">{{ $areaCctvCount }}</div>
        </div>
    </div>
    <div class="col-12 col-xl-3 col-lg-6 d-flex">
        <div class="stats-card week w-100">
            <div class="stat-header">
                <span class="stat-label">Week {{ $currentWeek }} Links</span>
                <div class="stat-icon">
                    <i class="material-icons-outlined">calendar_today</i>
                </div>
            </div>
            <div class="stat-value">{{ $currentWeekCount }}</div>
        </div>
    </div>
</div>

<!-- Upload Form -->
<div class="row">
    <div class="col-12 col-xl-6 d-flex">
        <div class="card rounded-4 w-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-bold">Upload GeoJSON</h5>
                    <small class="text-muted">File akan otomatis diassign ke Week {{ $currentWeek }}, {{ $currentYear }}</small>
                </div>
            </div>
            <div class="card-body">
                <form id="geojsonUploadForm">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Nama Area</label>
                        <input type="text" 
                               class="form-control rounded-3" 
                               id="name" 
                               name="name" 
                               placeholder="Contoh: Area Kerja Blok A" 
                               value="{{ old('name') }}" 
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="type" class="form-label fw-semibold">Tipe Area</label>
                        <select class="form-control rounded-3" id="type" name="type" required>
                            <option value="">Pilih Tipe Area</option>
                            <option value="area_kerja" {{ old('type') == 'area_kerja' ? 'selected' : '' }}>Area Kerja</option>
                            <option value="area_cctv" {{ old('type') == 'area_cctv' ? 'selected' : '' }}>Area CCTV</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="geojson_file" class="form-label fw-semibold">File GeoJSON</label>
                        <input type="file" 
                               class="form-control rounded-3" 
                               id="geojson_file" 
                               name="geojson_file" 
                               accept=".json,.geojson"
                               required>
                        <small class="text-muted">
                            <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">info</i>
                            Format: JSON/GeoJSON (maks. 10MB). File harus berupa FeatureCollection.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Deskripsi (Opsional)</label>
                        <textarea class="form-control rounded-3" 
                                  id="description" 
                                  name="description" 
                                  rows="3" 
                                  placeholder="Deskripsi area...">{{ old('description') }}</textarea>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary rounded-3" id="submitBtn">
                            <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">upload</i>
                            Upload GeoJSON
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
                        <h6 class="mb-1">Format GeoJSON</h6>
                        <p class="mb-0 text-muted small">
                            File GeoJSON harus berupa FeatureCollection dengan struktur yang valid. 
                            Pastikan file memiliki properti "type": "FeatureCollection".
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
                            File GeoJSON harus diupdate setiap minggu untuk memastikan data selalu terbaru.
                        </p>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-3">
                    <div class="bg-warning bg-opacity-10 rounded-3 p-2">
                        <i class="material-icons-outlined text-warning">folder</i>
                    </div>
                    <div>
                        <h6 class="mb-1">Tipe Area</h6>
                        <p class="mb-0 text-muted small">
                            Pilih antara <strong>Area Kerja</strong> untuk area operasional atau 
                            <strong>Area CCTV</strong> untuk area yang tercover oleh CCTV.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- GeoJSON Areas Table -->
<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <h5 class="mb-0 fw-bold">Daftar GeoJSON Areas</h5>
                    <small class="text-muted">
                        Menampilkan {{ $geojsonAreas->firstItem() ?? 0 }}-{{ $geojsonAreas->lastItem() ?? 0 }} dari {{ $geojsonAreas->total() }} data
                    </small>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Nama Area</th>
                                <th>Tipe</th>
                                <th class="file-name-cell">File Name</th>
                                <th style="width: 120px;">Week</th>
                                <th style="width: 100px;">Year</th>
                                <th style="width: 180px;">Created At</th>
                                <th style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($geojsonAreas as $index => $area)
                            <tr>
                                <td class="text-center">{{ $geojsonAreas->firstItem() + $index }}</td>
                                <td>
                                    <strong>{{ $area->name }}</strong>
                                    @if($area->description)
                                    <br><small class="text-muted">{{ Str::limit($area->description, 50) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($area->type === 'area_kerja')
                                    <span class="badge bg-danger bg-opacity-10 text-danger">Area Kerja</span>
                                    @else
                                    <span class="badge bg-info bg-opacity-10 text-info">Area CCTV</span>
                                    @endif
                                </td>
                                <td class="file-name-cell">
                                    <small class="text-muted">
                                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">insert_drive_file</i>
                                        {{ $area->file_name ?? '-' }}
                                    </small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary bg-opacity-10 text-primary">Week {{ $area->week }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success bg-opacity-10 text-success">{{ $area->year }}</span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">schedule</i>
                                        {{ $area->created_at->format('d M Y H:i') }}
                                    </small>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-primary rounded-3" onclick="editGeojsonArea({{ $area->id }})" title="Edit">
                                            <i class="material-icons-outlined" style="font-size: 16px;">edit</i>
                                        </button>
                                        <button class="btn btn-sm btn-danger rounded-3" onclick="deleteGeojsonArea({{ $area->id }})" title="Delete">
                                            <i class="material-icons-outlined" style="font-size: 16px;">delete</i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                        <i class="material-icons-outlined mb-3" style="font-size: 64px; color: #9ca3af; opacity: 0.5;">map</i>
                                        <h6 class="text-muted mb-1">Belum ada GeoJSON area yang diupload</h6>
                                        <p class="text-muted small mb-0">Mulai dengan mengupload file GeoJSON pertama Anda</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($geojsonAreas->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $geojsonAreas->links() }}
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
                <h5 class="modal-title fw-bold" id="editModalLabel">Edit GeoJSON Area</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="edit_geojson_id" name="geojson_id">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label fw-semibold">Nama Area</label>
                        <input type="text" class="form-control rounded-3" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_type" class="form-label fw-semibold">Tipe Area</label>
                        <select class="form-control rounded-3" id="edit_type" name="type" required>
                            <option value="">Pilih Tipe Area</option>
                            <option value="area_kerja">Area Kerja</option>
                            <option value="area_cctv">Area CCTV</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_geojson_file" class="form-label fw-semibold">File GeoJSON (Opsional)</label>
                        <input type="file" class="form-control rounded-3" id="edit_geojson_file" name="geojson_file" accept=".json,.geojson">
                        <small class="text-muted">
                            <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">info</i>
                            Kosongkan jika tidak ingin mengubah file. File akan diupdate ke Week {{ $currentWeek }}, {{ $currentYear }}
                        </small>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label fw-semibold">Deskripsi (Opsional)</label>
                        <textarea class="form-control rounded-3" id="edit_description" name="description" rows="3"></textarea>
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
    let currentGeojsonId = null;

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
    document.getElementById('geojsonUploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        const formData = new FormData(this);
        
        // Show loading
        Swal.fire({
            title: 'Mengupload GeoJSON...',
            text: 'Mohon tunggu',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">hourglass_empty</i> Uploading...';

        fetch('{{ route('geofencing.geojson.store') }}', {
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
                    text: data.message || 'GeoJSON berhasil diupload',
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
                    text: data.message || 'Gagal mengupload GeoJSON',
                    confirmButtonColor: '#f44336',
                    confirmButtonText: 'OK'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">upload</i> Upload GeoJSON';
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Terjadi kesalahan saat mengupload GeoJSON',
                confirmButtonColor: '#f44336',
                confirmButtonText: 'OK'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">upload</i> Upload GeoJSON';
        });
    });

    function editGeojsonArea(id) {
        currentGeojsonId = id;
        
        // Show loading
        Swal.fire({
            title: 'Memuat data...',
            text: 'Mohon tunggu',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Fetch GeoJSON area data
        fetch(`{{ url('geofencing/geojson') }}/${id}`)
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    document.getElementById('edit_geojson_id').value = id;
                    document.getElementById('edit_name').value = data.data.name;
                    document.getElementById('edit_type').value = data.data.type;
                    document.getElementById('edit_description').value = data.data.description || '';
                    editModal.show();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: 'Gagal memuat data GeoJSON area',
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
        if (!currentGeojsonId) return;

        const form = document.getElementById('editForm');
        const formData = new FormData(form);
        
        // Ensure _method is set to PUT
        formData.set('_method', 'PUT');
        
        const updateBtn = document.getElementById('updateBtn');

        // Show loading
        Swal.fire({
            title: 'Mengupdate GeoJSON Area...',
            text: 'Mohon tunggu',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        updateBtn.disabled = true;
        updateBtn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">hourglass_empty</i> Updating...';

        fetch(`{{ url('geofencing/geojson') }}/${currentGeojsonId}`, {
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
                    text: data.message || 'GeoJSON area berhasil diupdate',
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
                    text: data.message || 'Gagal mengupdate GeoJSON area',
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
                text: 'Terjadi kesalahan saat mengupdate GeoJSON area',
                confirmButtonColor: '#f44336',
                confirmButtonText: 'OK'
            });
            updateBtn.disabled = false;
            updateBtn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">update</i> Update';
        });
    }

    function deleteGeojsonArea(id) {
        Swal.fire({
            icon: 'warning',
            title: 'Hapus GeoJSON Area?',
            text: 'Apakah Anda yakin ingin menghapus GeoJSON area ini? Tindakan ini tidak dapat dibatalkan.',
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
                    title: 'Menghapus GeoJSON Area...',
                    text: 'Mohon tunggu',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('_method', 'DELETE');

                fetch(`{{ url('geofencing/geojson') }}/${id}`, {
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
                            text: data.message || 'GeoJSON area berhasil dihapus',
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
                            text: data.message || 'Gagal menghapus GeoJSON area',
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
                        text: 'Terjadi kesalahan saat menghapus GeoJSON area',
                        confirmButtonColor: '#f44336',
                        confirmButtonText: 'OK'
                    });
                });
            }
        });
    }
</script>
@endsection
