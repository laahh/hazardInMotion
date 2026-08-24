@extends('EmergencyResponse.layouts.app')

@section('page-title', $equipment->name)

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-16">
        <a href="{{ route('emergency-response.equipment.index') }}" class="text-secondary-light"><i class="ri-arrow-left-line"></i> Kembali ke daftar</a>
        <div class="d-flex gap-2">
            <a href="{{ route('emergency-response.equipment.print', $equipment) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="ri-printer-line"></i> Cetak Label QR</a>
            <a href="{{ route('emergency-response.equipment.edit', $equipment) }}" class="btn btn-primary-600 btn-sm"><i class="ri-edit-line"></i> Edit</a>
        </div>
    </div>

    <div class="row gy-4">
        <div class="col-lg-8">
            <div class="card shadow-none border mb-24">
                <div class="card-header"><h6 class="mb-0">Informasi Equipment</h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <table class="table table-borderless mb-0">
                                <tr><th width="180">Kode Aset</th><td>{{ $equipment->code }}</td></tr>
                                <tr><th>Nama</th><td>{{ $equipment->name }}</td></tr>
                                <tr><th>Kategori</th><td>{{ $equipment->category->name ?? '-' }}</td></tr>
                                <tr><th>Tipe/Model</th><td>{{ $equipment->type_model ?: '-' }}</td></tr>
                                <tr><th>Merek</th><td>{{ $equipment->brand ?: '-' }}</td></tr>
                                <tr><th>No. Seri</th><td>{{ $equipment->serial_number ?: '-' }}</td></tr>
                                <tr><th>Lokasi</th><td>{{ $equipment->site->name ?? '-' }} / {{ $equipment->locationLabel() ?? '-' }} / {{ $equipment->areaLabel() ?? '-' }}</td></tr>
                                <tr><th>Detail Posisi</th><td>{{ $equipment->position_detail ?: '-' }}</td></tr>
                                <tr><th>Departemen</th><td>{{ $equipment->department->name ?? '-' }}</td></tr>
                                <tr><th>Unit Emergency</th><td>{{ $equipment->emergencyUnit->name ?? '-' }}</td></tr>
                                <tr><th>Kondisi</th><td><span class="badge bg-info-focus text-info-600 px-16 py-4 radius-4">{{ $equipment->conditionLabel() }}</span></td></tr>
                                <tr><th>Status Operasional</th><td><span class="badge bg-success-focus text-success-600 px-16 py-4 radius-4">{{ $equipment->operationalStatusLabel() }}</span></td></tr>
                                <tr><th>Inspeksi Terakhir</th><td>{{ optional($equipment->last_inspection_at)->format('d M Y') ?? '-' }}</td></tr>
                                <tr><th>Inspeksi Berikutnya</th><td>{{ optional($equipment->next_inspection_at)->format('d M Y') ?? '-' }}</td></tr>
                                <tr><th>Kalibrasi Terakhir</th><td>{{ optional($equipment->last_calibration_at)->format('d M Y') ?? '-' }}</td></tr>
                                <tr><th>Kedaluwarsa</th><td>{{ optional($equipment->expires_at)->format('d M Y') ?? '-' }}</td></tr>
                                <tr><th>No. Sertifikat/SKO</th><td>{{ $equipment->certificate_number ?: '-' }}</td></tr>
                                <tr><th>Masa Berlaku Sertifikat</th><td>{{ optional($equipment->certificate_expires_at)->format('d M Y') ?? '-' }}</td></tr>
                                <tr><th>Catatan</th><td>{{ $equipment->notes ?: '-' }}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-4 text-center">
                            @if ($equipment->photo_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($equipment->photo_path) }}" class="img-fluid rounded mb-16" alt="Foto equipment">
                            @endif
                            <img src="{{ route('emergency-response.equipment.qr', $equipment) }}" alt="QR Code" style="width: 160px; height: 160px;">
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
                        @forelse ($equipment->documents as $document)
                            <li class="list-group-item d-flex align-items-center justify-content-between">
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->file_path) }}" target="_blank">
                                    <i class="ri-file-line"></i> {{ $document->original_name }}
                                </a>
                                <form action="{{ route('emergency-response.equipment.documents.destroy', [$equipment, $document]) }}" method="POST" onsubmit="return confirm('Hapus dokumen ini?');">
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
                                @forelse ($equipment->statusHistories as $history)
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
                    <a href="{{ route('emergency-response.inspection.create', ['type' => 'equipment', 'id' => $equipment->id]) }}" class="btn btn-sm btn-primary-600">Inspeksi</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($equipment->inspections()->latest('inspected_at')->limit(5)->get() as $inspection)
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
                    <a href="{{ route('emergency-response.work-order.create', ['equipment_type' => 'equipment', 'equipment_id' => $equipment->id]) }}" class="btn btn-sm btn-primary-600">Buat WO</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($equipment->workOrders()->limit(5)->get() as $wo)
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
                <form action="{{ route('emergency-response.equipment.documents.store', $equipment) }}" method="POST" enctype="multipart/form-data">
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
