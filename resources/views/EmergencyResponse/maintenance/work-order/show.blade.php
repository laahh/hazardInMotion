@extends('EmergencyResponse.layouts.app')

@section('page-title', $workOrder->work_order_number)

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-16">
        <a href="{{ route('emergency-response.work-order.index') }}" class="text-secondary-light"><i class="ri-arrow-left-line"></i> Kembali ke daftar</a>
        <a href="{{ route('emergency-response.work-order.pdf', $workOrder) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="ri-file-pdf-2-line"></i> Export PDF</a>
    </div>

    <div class="row gy-4">
        <div class="col-lg-8">
            <div class="card shadow-none border mb-24">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">{{ $workOrder->work_order_number }}</h6>
                    <span class="badge bg-info-focus text-info-600 px-16 py-4 radius-4">{{ $workOrder->statusLabel() }}</span>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr><th width="200">Equipment</th><td>{{ $workOrder->equipmentable->name ?? '-' }} ({{ $workOrder->equipmentable->code ?? '-' }})</td></tr>
                        <tr><th>Jenis Pekerjaan</th><td>{{ $workOrder->workTypeLabel() }}</td></tr>
                        <tr><th>Sumber</th><td>{{ \App\Models\EmergencyResponse\Maintenance\WorkOrder::SOURCES[$workOrder->source] ?? $workOrder->source }}</td></tr>
                        <tr><th>Deskripsi</th><td>{{ $workOrder->description }}</td></tr>
                        <tr><th>Prioritas</th><td>{{ $workOrder->priorityLevel->name ?? '-' }}</td></tr>
                        <tr><th>Vendor</th><td>{{ $workOrder->vendor->name ?? 'Internal' }}</td></tr>
                        <tr><th>Teknisi</th><td>{{ $workOrder->assignedTechnician->name ?? '-' }}</td></tr>
                        <tr><th>Target</th><td>{{ optional($workOrder->target_start_at)->format('d M Y') ?? '-' }} s/d {{ optional($workOrder->target_end_at)->format('d M Y') ?? '-' }}</td></tr>
                        <tr><th>Aktual</th><td>{{ optional($workOrder->actual_start_at)->format('d M Y H:i') ?? '-' }} s/d {{ optional($workOrder->actual_end_at)->format('d M Y H:i') ?? '-' }}</td></tr>
                        <tr><th>Estimasi / Biaya Aktual</th><td>{{ $workOrder->estimated_cost ?? '-' }} / {{ $workOrder->actual_cost ?? '-' }}</td></tr>
                        @if ($workOrder->result_notes)
                            <tr><th>Hasil Pekerjaan</th><td>{{ $workOrder->result_notes }}</td></tr>
                        @endif
                    </table>

                    <hr>
                    <div class="d-flex flex-wrap gap-2">
                        @if ($workOrder->status === 'requested')
                            <form action="{{ route('emergency-response.work-order.approve', $workOrder) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary-600">Approve</button>
                            </form>
                        @endif
                        @if ($workOrder->status === 'approved')
                            <button type="button" class="btn btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#assignModal">Assign Teknisi</button>
                        @endif
                        @if ($workOrder->status === 'assigned')
                            <form action="{{ route('emergency-response.work-order.start', $workOrder) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary-600">Mulai Pekerjaan</button>
                            </form>
                        @endif
                        @if ($workOrder->status === 'in_progress')
                            <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#holdModal">On Hold</button>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#completeModal">Tandai Selesai</button>
                        @endif
                        @if ($workOrder->status === 'on_hold')
                            <form action="{{ route('emergency-response.work-order.resume', $workOrder) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary-600">Lanjutkan Pekerjaan</button>
                            </form>
                        @endif
                        @if ($workOrder->status === 'completed')
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#verifyModal">Verifikasi</button>
                        @endif
                        @if ($workOrder->status === 'verified')
                            <form action="{{ route('emergency-response.work-order.close', $workOrder) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-dark">Tutup Work Order</button>
                            </form>
                        @endif
                    </div>

                    @if ($workOrder->technician_signature_path || $workOrder->verifier_signature_path)
                        <div class="d-flex gap-24 mt-16">
                            @if ($workOrder->technician_signature_path)
                                <div>
                                    <p class="text-secondary-light text-sm mb-4">Tanda Tangan Teknisi</p>
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($workOrder->technician_signature_path) }}" style="max-height: 80px;" alt="TTD Teknisi">
                                </div>
                            @endif
                            @if ($workOrder->verifier_signature_path)
                                <div>
                                    <p class="text-secondary-light text-sm mb-4">Tanda Tangan Verifier</p>
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($workOrder->verifier_signature_path) }}" style="max-height: 80px;" alt="TTD Verifier">
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="card shadow-none border mb-24">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Spare Part Digunakan</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#sparePartModal">Tambah</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table bordered-table mb-0">
                            <thead><tr><th>Spare Part</th><th>Jumlah</th><th>Harga/Unit</th><th>Subtotal</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($workOrder->spareParts as $usage)
                                    <tr>
                                        <td>{{ $usage->sparePart->name ?? '-' }}</td>
                                        <td>{{ $usage->quantity_used }} {{ $usage->sparePart->unit ?? '' }}</td>
                                        <td>{{ $usage->unit_cost_snapshot ?? '-' }}</td>
                                        <td>{{ number_format($usage->subtotal(), 0, ',', '.') }}</td>
                                        <td>
                                            <form action="{{ route('emergency-response.work-order.spare-parts.destroy', [$workOrder, $usage]) }}" method="POST">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-secondary-light py-16">Belum ada spare part dicatat.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-none border mb-24">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Dokumen / Foto Sebelum-Sesudah</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#docModal">Unggah</button>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($workOrder->documents as $document)
                            <li class="list-group-item d-flex align-items-center justify-content-between">
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->file_path) }}" target="_blank">{{ $document->original_name }}</a>
                                <form action="{{ route('emergency-response.work-order.documents.destroy', [$workOrder, $document]) }}" method="POST">
                                    @csrf @method('DELETE')
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
                <div class="card-header"><h6 class="mb-0">Riwayat Status</h6></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach ($workOrder->statusHistories as $history)
                            <li class="list-group-item">
                                <strong>{{ \App\Models\EmergencyResponse\Maintenance\WorkOrder::STATUSES[$history->to_status] ?? $history->to_status }}</strong>
                                <span class="text-secondary-light text-sm"> — {{ $history->changed_at->format('d M Y H:i') }} oleh {{ $history->changedBy->name ?? 'Sistem' }}</span>
                                @if ($history->notes)
                                    <p class="mb-0 text-sm">{{ $history->notes }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Modals --}}
    <div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.work-order.assign', $workOrder) }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Assign Teknisi</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <select name="assigned_technician_id" class="form-control" required>
                        <option value="">-- Pilih --</option>
                        @foreach ($technicians as $technician)
                            <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Simpan</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="holdModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.work-order.hold', $workOrder) }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tahan Sementara (On Hold)</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><textarea name="notes" class="form-control" rows="3" placeholder="Alasan on hold" required></textarea></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-warning">Tahan</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="sparePartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.work-order.spare-parts.store', $workOrder) }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tambah Spare Part</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Spare Part</label>
                        <select name="spare_part_id" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            @foreach ($spareParts as $part)
                                <option value="{{ $part->id }}">{{ $part->name }} ({{ $part->unit }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Jumlah</label><input type="number" name="quantity_used" class="form-control" min="1" value="1" required></div>
                    <div class="mb-3"><label class="form-label">Catatan</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Simpan</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="docModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.work-order.documents.store', $workOrder) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Unggah Dokumen/Foto</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Jenis</label>
                        <select name="type" class="form-control" required>
                            <option value="photo">Foto</option>
                            <option value="document">Dokumen</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">File</label><input type="file" name="file" class="form-control" required></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Unggah</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.work-order.complete', $workOrder) }}" method="POST" id="complete-form">
                @csrf
                <input type="hidden" name="signature_data" id="complete-signature-data">
                <div class="modal-header"><h6 class="modal-title">Tandai Pekerjaan Selesai</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Hasil Pekerjaan</label><textarea name="result_notes" class="form-control" rows="3" required></textarea></div>
                    <div class="mb-3"><label class="form-label">Biaya Aktual (opsional, default dari total spare part)</label><input type="number" step="any" name="actual_cost" class="form-control"></div>
                    <div class="mb-3">
                        <label class="form-label">Tanda Tangan Teknisi</label>
                        <canvas id="complete-signature-pad" width="360" height="120" style="border: 1px solid #ccc; touch-action: none; max-width: 100%;"></canvas>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-4" onclick="window.erClearSignature('complete-signature-pad')">Hapus</button>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success">Selesai</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="verifyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.work-order.verify', $workOrder) }}" method="POST" id="verify-form">
                @csrf
                <input type="hidden" name="signature_data" id="verify-signature-data">
                <div class="modal-header"><h6 class="modal-title">Verifikasi Hasil Pekerjaan</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Tanda Tangan Verifier</label>
                    <canvas id="verify-signature-pad" width="360" height="120" style="border: 1px solid #ccc; touch-action: none; max-width: 100%;"></canvas>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-4" onclick="window.erClearSignature('verify-signature-pad')">Hapus</button>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success">Verifikasi</button></div>
            </form>
        </div></div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var pads = {};

            function setupPad(id) {
                var canvas = document.getElementById(id);
                if (!canvas) return;
                var ctx = canvas.getContext('2d');
                var drawing = false;
                pads[id] = ctx;

                function pos(e) {
                    var rect = canvas.getBoundingClientRect();
                    var point = e.touches ? e.touches[0] : e;
                    return { x: (point.clientX - rect.left) * (canvas.width / rect.width), y: (point.clientY - rect.top) * (canvas.height / rect.height) };
                }
                function start(e) { drawing = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
                function move(e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
                function end() { drawing = false; }

                canvas.addEventListener('mousedown', start);
                canvas.addEventListener('mousemove', move);
                window.addEventListener('mouseup', end);
                canvas.addEventListener('touchstart', start);
                canvas.addEventListener('touchmove', move);
                canvas.addEventListener('touchend', end);
            }

            window.erClearSignature = function (id) {
                var canvas = document.getElementById(id);
                pads[id].clearRect(0, 0, canvas.width, canvas.height);
            };

            setupPad('complete-signature-pad');
            setupPad('verify-signature-pad');

            var completeForm = document.getElementById('complete-form');
            if (completeForm) {
                completeForm.addEventListener('submit', function () {
                    document.getElementById('complete-signature-data').value = document.getElementById('complete-signature-pad').toDataURL('image/png');
                });
            }
            var verifyForm = document.getElementById('verify-form');
            if (verifyForm) {
                verifyForm.addEventListener('submit', function () {
                    document.getElementById('verify-signature-data').value = document.getElementById('verify-signature-pad').toDataURL('image/png');
                });
            }
        })();
    </script>
@endpush
