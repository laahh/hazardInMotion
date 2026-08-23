@extends('EmergencyResponse.layouts.app')

@section('page-title', $device->name)

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-16">
        <a href="{{ route('emergency-response.safety-device.index') }}" class="text-secondary-light"><i class="ri-arrow-left-line"></i> Kembali ke daftar</a>
        <div class="d-flex gap-2">
            <a href="{{ route('emergency-response.safety-device.print', $device) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="ri-printer-line"></i> Cetak Label QR</a>
            <a href="{{ route('emergency-response.safety-device.edit', $device) }}" class="btn btn-primary-600 btn-sm"><i class="ri-edit-line"></i> Edit</a>
        </div>
    </div>

    <div class="row gy-4">
        <div class="col-lg-8">
            <div class="card shadow-none border mb-24">
                <div class="card-header"><h6 class="mb-0">Informasi Safety Device</h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <table class="table table-borderless mb-0">
                                <tr><th width="180">Kode Aset</th><td>{{ $device->code }}</td></tr>
                                <tr><th>Nama</th><td>{{ $device->name }}</td></tr>
                                <tr><th>Jenis</th><td>{{ $device->type->name ?? '-' }}</td></tr>
                                <tr><th>Merek/Model</th><td>{{ $device->brand ?: '-' }} {{ $device->model ? '/ '.$device->model : '' }}</td></tr>
                                <tr><th>No. Seri</th><td>{{ $device->serial_number ?: '-' }}</td></tr>
                                <tr><th>Lokasi</th><td>{{ $device->site->name ?? '-' }} / {{ $device->location->name ?? '-' }} / {{ $device->area->name ?? '-' }}</td></tr>
                                <tr><th>Departemen</th><td>{{ $device->department->name ?? '-' }}</td></tr>
                                <tr><th>Tanggal Instalasi</th><td>{{ optional($device->installed_at)->format('d M Y') ?? '-' }}</td></tr>
                                <tr><th>Kondisi</th><td><span class="badge bg-info-focus text-info-600 px-16 py-4 radius-4">{{ $device->conditionLabel() }}</span></td></tr>
                                <tr><th>Status Operasional</th><td><span class="badge bg-success-focus text-success-600 px-16 py-4 radius-4">{{ $device->operationalStatusLabel() }}</span></td></tr>
                                <tr><th>Inspeksi Terakhir</th><td>{{ optional($device->last_inspection_at)->format('d M Y') ?? '-' }}</td></tr>
                                <tr><th>Inspeksi Berikutnya</th><td>{{ optional($device->next_inspection_at)->format('d M Y') ?? '-' }}</td></tr>
                                <tr><th>Kalibrasi Terakhir</th><td>{{ optional($device->last_calibration_at)->format('d M Y') ?? '-' }}</td></tr>
                                <tr><th>Kalibrasi Berikutnya</th><td>{{ optional($device->next_calibration_at)->format('d M Y') ?? '-' }}</td></tr>
                                <tr><th>Masa Berlaku Sertifikat</th><td>{{ optional($device->certificate_expires_at)->format('d M Y') ?? '-' }}</td></tr>
                                <tr><th>Catatan</th><td>{{ $device->notes ?: '-' }}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-4 text-center">
                            @if ($device->photo_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($device->photo_path) }}" class="img-fluid rounded mb-16" alt="Foto device">
                            @endif
                            <img src="{{ route('emergency-response.safety-device.qr', $device) }}" alt="QR Code" style="width: 160px; height: 160px;">
                            <p class="text-secondary-light text-sm mt-8 mb-0">Scan untuk buka halaman ini</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-none border mb-24">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Dokumen & Foto</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#uploadDocModal"><i class="ri-upload-2-line"></i> Unggah</button>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($device->documents as $document)
                            <li class="list-group-item d-flex align-items-center justify-content-between">
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->file_path) }}" target="_blank">
                                    <i class="ri-file-line"></i> {{ $document->original_name }}
                                </a>
                                <form action="{{ route('emergency-response.safety-device.documents.destroy', [$device, $document]) }}" method="POST" onsubmit="return confirm('Hapus dokumen ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                </form>
                            </li>
                        @empty
                            <li class="list-group-item text-secondary-light text-center py-16">Belum ada dokumen.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">Riwayat Kondisi & Status</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table bordered-table mb-0">
                            <thead><tr><th>Waktu</th><th>Field</th><th>Dari</th><th>Ke</th><th>Oleh</th></tr></thead>
                            <tbody>
                                @forelse ($device->statusHistories as $history)
                                    <tr>
                                        <td>{{ $history->changed_at->format('d M Y H:i') }}</td>
                                        <td>{{ $history->field_changed === 'condition' ? 'Kondisi' : 'Status Operasional' }}</td>
                                        <td>{{ $history->old_value }}</td>
                                        <td>{{ $history->new_value }}</td>
                                        <td>{{ $history->changedBy->name ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-secondary-light py-16">Belum ada perubahan kondisi/status.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-none border mb-24">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Riwayat Inspeksi</h6>
                    <a href="{{ route('emergency-response.inspection.create', ['type' => 'safety_device', 'id' => $device->id]) }}" class="btn btn-sm btn-primary-600">Inspeksi</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($device->inspections()->latest('inspected_at')->limit(5)->get() as $inspection)
                            <li class="list-group-item">
                                <a href="{{ route('emergency-response.inspection.show', $inspection) }}">{{ $inspection->inspection_number }}</a>
                                <span class="badge bg-info-focus text-info-600 px-8 py-2 radius-4 float-end">{{ $inspection->statusLabel() }}</span>
                                <div class="text-secondary-light text-sm">{{ optional($inspection->inspected_at)->format('d M Y') }}</div>
                            </li>
                        @empty
                            <li class="list-group-item text-secondary-light text-center py-16">Belum ada inspeksi.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
            <div class="card shadow-none border">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Riwayat Work Order</h6>
                    <a href="{{ route('emergency-response.work-order.create', ['equipment_type' => 'safety_device', 'equipment_id' => $device->id]) }}" class="btn btn-sm btn-primary-600">Buat WO</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($device->workOrders()->limit(5)->get() as $wo)
                            <li class="list-group-item">
                                <a href="{{ route('emergency-response.work-order.show', $wo) }}">{{ $wo->work_order_number }}</a>
                                <span class="badge bg-info-focus text-info-600 px-8 py-2 radius-4 float-end">{{ $wo->statusLabel() }}</span>
                                <div class="text-secondary-light text-sm">{{ $wo->workTypeLabel() }}</div>
                            </li>
                        @empty
                            <li class="list-group-item text-secondary-light text-center py-16">Belum ada work order.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('emergency-response.safety-device.documents.store', $device) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h6 class="modal-title">Unggah Dokumen/Foto</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Jenis</label>
                            <select name="type" class="form-control" required>
                                <option value="photo">Foto</option>
                                <option value="document">Dokumen</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File</label>
                            <input type="file" name="file" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary-600">Unggah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
