@extends('layouts.master')

@section('title', 'Data CCTV - QR Code Scan')
@section('css')
    
@endsection 
@section('content')
<x-page-title title="Data CCTV" pagetitle="Data CCTV dari QR Code" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">Data CCTV</h5>
                        <p class="mb-0 text-muted">No. CCTV: {{ $cctvData->no_cctv ?? '-' }}</p>
                    </div>
                    <div class="dropdown">
                        <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                            data-bs-toggle="dropdown">
                            <span class="material-icons-outlined fs-5">more_vert</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('cctv-data.show', $cctvData->id) }}"><i class="material-icons-outlined me-2">visibility</i> Detail Lengkap</a></li>
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
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

