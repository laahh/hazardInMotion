@extends('EmergencyResponse.layouts.app')

@section('page-title', $inspection->inspection_number)

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-16">
        <a href="{{ route('emergency-response.inspection.index') }}" class="text-secondary-light"><i class="ri-arrow-left-line"></i> Kembali ke daftar</a>
        <a href="{{ route('emergency-response.inspection.pdf', $inspection) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="ri-file-pdf-2-line"></i> Export PDF</a>
    </div>

    <div class="row gy-4">
        <div class="col-lg-8">
            <div class="card shadow-none border mb-24">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">{{ $inspection->inspection_number }}</h6>
                    <span class="badge bg-info-focus text-info-600 px-16 py-4 radius-4">{{ $inspection->statusLabel() }}</span>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr><th width="200">Target</th><td>{{ $inspection->target->name ?? '-' }} ({{ $inspection->target->code ?? '-' }})</td></tr>
                        <tr><th>Site</th><td>{{ $inspection->site->name ?? '-' }}</td></tr>
                        <tr><th>Template Checklist</th><td>{{ $inspection->checklistTemplate->name ?? '-' }}</td></tr>
                        <tr><th>Inspector</th><td>{{ $inspection->inspector->name ?? '-' }}</td></tr>
                        <tr><th>Waktu Inspeksi</th><td>{{ optional($inspection->inspected_at)->format('d M Y H:i') ?? '-' }}</td></tr>
                        <tr><th>Kondisi Hasil Observasi</th><td>{{ $inspection->condition_result ?? '-' }}</td></tr>
                        <tr><th>Koordinat GPS</th><td>{{ $inspection->latitude && $inspection->longitude ? "{$inspection->latitude}, {$inspection->longitude}" : '-' }}</td></tr>
                        <tr><th>Catatan</th><td>{{ $inspection->notes ?: '-' }}</td></tr>
                        @if ($inspection->signature_path)
                            <tr><th>Tanda Tangan</th><td><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($inspection->signature_path) }}" style="max-height: 80px;" alt="Tanda tangan"></td></tr>
                        @endif
                        @if ($inspection->status === 'approved' || $inspection->status === 'follow_up_required')
                            <tr><th>Disetujui Oleh</th><td>{{ $inspection->approvedBy->name ?? '-' }} — {{ optional($inspection->approved_at)->format('d M Y H:i') }}</td></tr>
                        @endif
                        @if ($inspection->status === 'rejected')
                            <tr><th>Ditolak Oleh</th><td>{{ $inspection->rejectedBy->name ?? '-' }} — {{ optional($inspection->rejected_at)->format('d M Y H:i') }}</td></tr>
                            <tr><th>Alasan Penolakan</th><td>{{ $inspection->approval_notes }}</td></tr>
                        @endif
                    </table>

                    @if ($inspection->status === 'submitted' && (auth()->user()->hasRole('hse-admin') || auth()->user()->hasRole('super-admin')))
                        <hr>
                        <div class="d-flex gap-2">
                            <form action="{{ route('emergency-response.inspection.approve', $inspection) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-success">Approve</button>
                            </form>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card shadow-none border mb-24">
                <div class="card-header"><h6 class="mb-0">Hasil Checklist</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table bordered-table mb-0">
                            <thead><tr><th>Item</th><th>Jawaban</th><th>Catatan</th><th>Foto</th></tr></thead>
                            <tbody>
                                @foreach ($inspection->results as $result)
                                    <tr class="{{ $result->isNonCompliant() ? 'table-danger' : '' }}">
                                        <td>{{ $result->item_text_snapshot }}</td>
                                        <td>
                                            @if ($result->answer_type_snapshot === 'compliance')
                                                {{ \App\Models\EmergencyResponse\Inspection\InspectionResult::COMPLIANCE_VALUES[$result->answer_value] ?? $result->answer_value }}
                                            @else
                                                {{ $result->answer_value ?: '-' }}
                                            @endif
                                        </td>
                                        <td>{{ $result->notes ?: '-' }}</td>
                                        <td>
                                            @if ($result->photo_before_path)
                                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($result->photo_before_path) }}" target="_blank">Sebelum</a>
                                            @endif
                                            @if ($result->photo_after_path)
                                                | <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($result->photo_after_path) }}" target="_blank">Sesudah</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($inspection->findings->isNotEmpty())
                <div class="card shadow-none border">
                    <div class="card-header"><h6 class="mb-0">Temuan & Tindak Lanjut</h6></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table bordered-table mb-0">
                                <thead><tr><th>Deskripsi</th><th>PIC</th><th>Target</th><th>Status</th></tr></thead>
                                <tbody>
                                    @foreach ($inspection->findings as $finding)
                                        <tr>
                                            <td>{{ $finding->description }}</td>
                                            <td>{{ $finding->pic->name ?? '-' }}</td>
                                            <td>{{ optional($finding->target_date)->format('d M Y') ?? '-' }}</td>
                                            <td><span class="badge bg-warning-focus text-warning-600 px-16 py-4 radius-4">{{ $finding->statusLabel() }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="p-16">
                            <a href="{{ route('emergency-response.inspection.findings.index') }}" class="text-primary-600">Kelola temuan di halaman Temuan Terbuka →</a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('emergency-response.inspection.reject', $inspection) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h6 class="modal-title">Tolak Inspeksi</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Alasan Penolakan</label>
                        <textarea name="approval_notes" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Tolak</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
