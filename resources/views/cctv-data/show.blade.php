@extends('layouts.master')

@section('title', 'Detail Data CCTV')
@section('css')
    
@endsection 
@section('content')
<x-page-title title="Data CCTV" pagetitle="Detail Data CCTV" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">Detail Data CCTV</h5>
                        <p class="mb-0 text-muted">No. CCTV: {{ $cctvData->no_cctv ?? '-' }}</p>
                    </div>
                    <div class="dropdown">
                        <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                            data-bs-toggle="dropdown">
                            <span class="material-icons-outlined fs-5">more_vert</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('cctv-data.edit', $cctvData->id) }}"><i class="material-icons-outlined me-2">edit</i> Edit</a></li>
                            <li><a class="dropdown-item" href="{{ route('cctv-data.index') }}"><i class="material-icons-outlined me-2">arrow_back</i> Kembali</a></li>
                        </ul>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Site</label>
                        <p class="mb-0">{{ $cctvData->site ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Perusahaan</label>
                        <p class="mb-0">{{ $cctvData->perusahaan ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Kategori</label>
                        <p class="mb-0">{{ $cctvData->kategori ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">No. CCTV</label>
                        <p class="mb-0">{{ $cctvData->no_cctv ?? '-' }}</p>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Nama CCTV</label>
                        <p class="mb-0">{{ $cctvData->nama_cctv ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Fungsi CCTV</label>
                        <p class="mb-0">{{ $cctvData->fungsi_cctv ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Bentuk Instalasi CCTV</label>
                        <p class="mb-0">{{ $cctvData->bentuk_instalasi_cctv ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Jenis</label>
                        <p class="mb-0">{{ $cctvData->jenis ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tipe CCTV</label>
                        <p class="mb-0">{{ $cctvData->tipe_cctv ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Radius Pengawasan</label>
                        <p class="mb-0">{{ $cctvData->radius_pengawasan ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Jenis Spesifikasi Zoom</label>
                        <p class="mb-0">{{ $cctvData->jenis_spesifikasi_zoom ?? '-' }}</p>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Lokasi Pemasangan</label>
                        <p class="mb-0">{{ $cctvData->lokasi_pemasangan ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Control Room</label>
                        <p class="mb-0">{{ $cctvData->control_room ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Status</label>
                        <p class="mb-0">
                            @if($cctvData->status)
                                <span class="badge bg-{{ $cctvData->status == 'Live View' ? 'success' : 'secondary' }}">
                                    {{ $cctvData->status }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Kondisi</label>
                        <p class="mb-0">
                            @if($cctvData->kondisi)
                                <span class="badge bg-{{ $cctvData->kondisi == 'Baik' ? 'success' : 'warning' }}">
                                    {{ $cctvData->kondisi }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Longitude</label>
                        <p class="mb-0">{{ $cctvData->longitude ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Latitude</label>
                        <p class="mb-0">{{ $cctvData->latitude ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Coverage Lokasi</label>
                        <p class="mb-0">{{ $cctvData->coverage_lokasi ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Coverage Detail Lokasi</label>
                        <p class="mb-0">{{ $cctvData->coverage_detail_lokasi ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Kategori Area Tercapture</label>
                        <p class="mb-0">{{ $cctvData->kategori_area_tercapture ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Kategori Aktivitas Tercapture</label>
                        <p class="mb-0">{{ $cctvData->kategori_aktivitas_tercapture ?? '-' }}</p>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Link Akses</label>
                        <p class="mb-0">
                            @if($cctvData->link_akses)
                                <a href="{{ $cctvData->link_akses }}" target="_blank" class="text-primary">{{ $cctvData->link_akses }}</a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">User Name</label>
                        <p class="mb-0">{{ $cctvData->user_name ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Password</label>
                        <p class="mb-0">{{ $cctvData->password ? '••••••••' : '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Connected</label>
                        <p class="mb-0">
                            @if($cctvData->connected)
                                <span class="badge bg-{{ $cctvData->connected == 'Yes' ? 'success' : 'danger' }}">
                                    {{ $cctvData->connected }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Mirrored</label>
                        <p class="mb-0">
                            @if($cctvData->mirrored)
                                <span class="badge bg-{{ $cctvData->mirrored == 'Yes' ? 'success' : 'danger' }}">
                                    {{ $cctvData->mirrored }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Fitur Auto Alert</label>
                        <p class="mb-0">
                            @if($cctvData->fitur_auto_alert)
                                <span class="badge bg-{{ $cctvData->fitur_auto_alert == 'Yes' ? 'success' : 'danger' }}">
                                    {{ $cctvData->fitur_auto_alert }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Keterangan</label>
                        <p class="mb-0">{{ $cctvData->keterangan ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Verifikasi By Petugas OCR</label>
                        <p class="mb-0">{{ $cctvData->verifikasi_by_petugas_ocr ?? '-' }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Bulan Update</label>
                        <p class="mb-0">{{ $cctvData->bulan_update ?? '-' }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tahun Update</label>
                        <p class="mb-0">{{ $cctvData->tahun_update ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Created At</label>
                        <p class="mb-0">{{ $cctvData->created_at ? $cctvData->created_at->format('d/m/Y H:i:s') : '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Updated At</label>
                        <p class="mb-0">{{ $cctvData->updated_at ? $cctvData->updated_at->format('d/m/Y H:i:s') : '-' }}</p>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">QR Code</label>
                        <div class="text-center">
                            <div class="mb-3">
                                <img src="{{ route('cctv-data.qr-code', $cctvData->id) }}" alt="QR Code" class="img-fluid" style="max-width: 400px; border: 10px solid white; box-shadow: 0 4px 8px rgba(0,0,0,0.1);" onerror="this.onerror=null; this.parentElement.innerHTML='<p class=\'text-muted\'>Gagal memuat gambar QR code.</p>';">
                            </div>
                            <p class="text-muted small">Scan QR code untuk melihat data CCTV</p>
                            <a href="{{ route('cctv-data.qr-code.download', $cctvData->id) }}" class="btn btn-primary mt-2">
                                <i class="material-icons-outlined">download</i> Download QR Code
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection


