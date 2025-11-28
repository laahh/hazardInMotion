@extends('layouts.master')

@section('title', 'Edit Data CCTV')
@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection 
@section('content')
<x-page-title title="Data CCTV" pagetitle="Edit Data CCTV" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">Edit Data CCTV</h5>
                        <p class="mb-0 text-muted">No. CCTV: {{ $cctvData->no_cctv ?? '-' }}</p>
                    </div>
                    <div class="dropdown">
                        <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                            data-bs-toggle="dropdown">
                            <span class="material-icons-outlined fs-5">more_vert</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('cctv-data.show', $cctvData->id) }}"><i class="material-icons-outlined me-2">visibility</i> Detail</a></li>
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

                <form id="editForm" action="{{ route('cctv-data.update', $cctvData->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="site" class="form-label">Site</label>
                            <input type="text" class="form-control" id="site" name="site" value="{{ old('site', $cctvData->site) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="perusahaan" class="form-label">Perusahaan</label>
                            <input type="text" class="form-control" id="perusahaan" name="perusahaan" value="{{ old('perusahaan', $cctvData->perusahaan) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="kategori" class="form-label">Kategori</label>
                            <input type="text" class="form-control" id="kategori" name="kategori" value="{{ old('kategori', $cctvData->kategori) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="no_cctv" class="form-label">No. CCTV</label>
                            <input type="text" class="form-control" id="no_cctv" name="no_cctv" value="{{ old('no_cctv', $cctvData->no_cctv) }}">
                        </div>
                        <div class="col-md-12">
                            <label for="nama_cctv" class="form-label">Nama CCTV</label>
                            <input type="text" class="form-control" id="nama_cctv" name="nama_cctv" value="{{ old('nama_cctv', $cctvData->nama_cctv) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="fungsi_cctv" class="form-label">Fungsi CCTV</label>
                            <input type="text" class="form-control" id="fungsi_cctv" name="fungsi_cctv" value="{{ old('fungsi_cctv', $cctvData->fungsi_cctv) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="bentuk_instalasi_cctv" class="form-label">Bentuk Instalasi CCTV</label>
                            <input type="text" class="form-control" id="bentuk_instalasi_cctv" name="bentuk_instalasi_cctv" value="{{ old('bentuk_instalasi_cctv', $cctvData->bentuk_instalasi_cctv) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="jenis" class="form-label">Jenis</label>
                            <input type="text" class="form-control" id="jenis" name="jenis" value="{{ old('jenis', $cctvData->jenis) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="tipe_cctv" class="form-label">Tipe CCTV</label>
                            <input type="text" class="form-control" id="tipe_cctv" name="tipe_cctv" value="{{ old('tipe_cctv', $cctvData->tipe_cctv) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="radius_pengawasan" class="form-label">Radius Pengawasan</label>
                            <input type="text" class="form-control" id="radius_pengawasan" name="radius_pengawasan" value="{{ old('radius_pengawasan', $cctvData->radius_pengawasan) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="jenis_spesifikasi_zoom" class="form-label">Jenis Spesifikasi Zoom</label>
                            <input type="text" class="form-control" id="jenis_spesifikasi_zoom" name="jenis_spesifikasi_zoom" value="{{ old('jenis_spesifikasi_zoom', $cctvData->jenis_spesifikasi_zoom) }}">
                        </div>
                        <div class="col-md-12">
                            <label for="lokasi_pemasangan" class="form-label">Lokasi Pemasangan</label>
                            <input type="text" class="form-control" id="lokasi_pemasangan" name="lokasi_pemasangan" value="{{ old('lokasi_pemasangan', $cctvData->lokasi_pemasangan) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="control_room" class="form-label">Control Room</label>
                            <input type="text" class="form-control" id="control_room" name="control_room" value="{{ old('control_room', $cctvData->control_room) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <input type="text" class="form-control" id="status" name="status" value="{{ old('status', $cctvData->status) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="kondisi" class="form-label">Kondisi</label>
                            <input type="text" class="form-control" id="kondisi" name="kondisi" value="{{ old('kondisi', $cctvData->kondisi) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="longitude" class="form-label">Longitude</label>
                            <input type="number" step="0.00000001" class="form-control" id="longitude" name="longitude" value="{{ old('longitude', $cctvData->longitude) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="latitude" class="form-label">Latitude</label>
                            <input type="number" step="0.00000001" class="form-control" id="latitude" name="latitude" value="{{ old('latitude', $cctvData->latitude) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="coverage_lokasi" class="form-label">Coverage Lokasi</label>
                            <input type="text" class="form-control" id="coverage_lokasi" name="coverage_lokasi" value="{{ old('coverage_lokasi', $cctvData->coverage_lokasi) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="coverage_detail_lokasi" class="form-label">Coverage Detail Lokasi</label>
                            <input type="text" class="form-control" id="coverage_detail_lokasi" name="coverage_detail_lokasi" value="{{ old('coverage_detail_lokasi', $cctvData->coverage_detail_lokasi) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="kategori_area_tercapture" class="form-label">Kategori Area Tercapture</label>
                            <input type="text" class="form-control" id="kategori_area_tercapture" name="kategori_area_tercapture" value="{{ old('kategori_area_tercapture', $cctvData->kategori_area_tercapture) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="kategori_aktivitas_tercapture" class="form-label">Kategori Aktivitas Tercapture</label>
                            <input type="text" class="form-control" id="kategori_aktivitas_tercapture" name="kategori_aktivitas_tercapture" value="{{ old('kategori_aktivitas_tercapture', $cctvData->kategori_aktivitas_tercapture) }}">
                        </div>
                        <div class="col-md-12">
                            <label for="link_akses" class="form-label">Link Akses</label>
                            <textarea class="form-control" id="link_akses" name="link_akses" rows="2">{{ old('link_akses', $cctvData->link_akses) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="user_name" class="form-label">User Name</label>
                            <input type="text" class="form-control" id="user_name" name="user_name" value="{{ old('user_name', $cctvData->user_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="text" class="form-control" id="password" name="password" value="{{ old('password', $cctvData->password) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="connected" class="form-label">Connected</label>
                            <select class="form-select" id="connected" name="connected">
                                <option value="">Pilih...</option>
                                <option value="Yes" {{ old('connected', $cctvData->connected) == 'Yes' ? 'selected' : '' }}>Yes</option>
                                <option value="No" {{ old('connected', $cctvData->connected) == 'No' ? 'selected' : '' }}>No</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="mirrored" class="form-label">Mirrored</label>
                            <select class="form-select" id="mirrored" name="mirrored">
                                <option value="">Pilih...</option>
                                <option value="Yes" {{ old('mirrored', $cctvData->mirrored) == 'Yes' ? 'selected' : '' }}>Yes</option>
                                <option value="No" {{ old('mirrored', $cctvData->mirrored) == 'No' ? 'selected' : '' }}>No</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="fitur_auto_alert" class="form-label">Fitur Auto Alert</label>
                            <select class="form-select" id="fitur_auto_alert" name="fitur_auto_alert">
                                <option value="">Pilih...</option>
                                <option value="Yes" {{ old('fitur_auto_alert', $cctvData->fitur_auto_alert) == 'Yes' ? 'selected' : '' }}>Yes</option>
                                <option value="No" {{ old('fitur_auto_alert', $cctvData->fitur_auto_alert) == 'No' ? 'selected' : '' }}>No</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="keterangan" class="form-label">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="3">{{ old('keterangan', $cctvData->keterangan) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="verifikasi_by_petugas_ocr" class="form-label">Verifikasi By Petugas OCR</label>
                            <input type="text" class="form-control" id="verifikasi_by_petugas_ocr" name="verifikasi_by_petugas_ocr" value="{{ old('verifikasi_by_petugas_ocr', $cctvData->verifikasi_by_petugas_ocr) }}">
                        </div>
                        <div class="col-md-3">
                            <label for="bulan_update" class="form-label">Bulan Update</label>
                            <input type="number" min="1" max="12" class="form-control" id="bulan_update" name="bulan_update" value="{{ old('bulan_update', $cctvData->bulan_update) }}">
                        </div>
                        <div class="col-md-3">
                            <label for="tahun_update" class="form-label">Tahun Update</label>
                            <input type="number" min="2000" max="2100" class="form-control" id="tahun_update" name="tahun_update" value="{{ old('tahun_update', $cctvData->tahun_update) }}">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="btnUpdate">
                            <i class="material-icons-outlined">save</i> Update
                        </button>
                        <a href="{{ route('cctv-data.index') }}" class="btn btn-secondary">
                            <i class="material-icons-outlined">close</i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        // Handle form submission dengan Sweet Alert
        $('#editForm').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var formData = form.serialize();
            var url = form.attr('action');
            
            // Disable submit button
            $('#btnUpdate').prop('disabled', true).html('<i class="material-icons-outlined">hourglass_empty</i> Memproses...');
            
            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: 'Data CCTV berhasil diperbarui.',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "{{ route('cctv-data.index') }}";
                        }
                    });
                },
                error: function(xhr) {
                    var errorMessage = 'Terjadi kesalahan saat memperbarui data.';
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        var errors = xhr.responseJSON.errors;
                        var errorList = '<ul class="text-start">';
                        $.each(errors, function(key, value) {
                            errorList += '<li>' + value[0] + '</li>';
                        });
                        errorList += '</ul>';
                        errorMessage = errorList;
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        html: errorMessage,
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    });
                    
                    // Enable submit button
                    $('#btnUpdate').prop('disabled', false).html('<i class="material-icons-outlined">save</i> Update');
                }
            });
        });
    });
</script>
@endsection

