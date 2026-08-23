@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Temuan Inspeksi')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Temuan Inspeksi</h6>
            <form method="GET">
                <select name="status" class="form-control" onchange="this.form.submit()">
                    <option value="">Belum Selesai (default)</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead>
                        <tr>
                            <th>Inspeksi</th>
                            <th>Target</th>
                            <th>Deskripsi</th>
                            <th>PIC</th>
                            <th>Target Selesai</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($findings as $finding)
                            <tr>
                                <td><a href="{{ route('emergency-response.inspection.show', $finding->inspection) }}">{{ $finding->inspection->inspection_number }}</a></td>
                                <td>{{ $finding->inspection->target->name ?? '-' }}</td>
                                <td>{{ $finding->description }}</td>
                                <td>{{ $finding->pic->name ?? '-' }}</td>
                                <td>{{ optional($finding->target_date)->format('d M Y') ?? '-' }}</td>
                                <td><span class="badge bg-warning-focus text-warning-600 px-16 py-4 radius-4">{{ $finding->statusLabel() }}</span></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#assignModal-{{ $finding->id }}">Assign</button>
                                    @if (! $finding->work_order_id)
                                        <a href="{{ route('emergency-response.work-order.create', ['finding_id' => $finding->id, 'equipment_type' => $finding->inspection->target_type === \App\Models\EmergencyResponse\Equipment\EmergencyEquipment::class ? 'equipment' : 'safety_device', 'equipment_id' => $finding->inspection->target_id]) }}" class="btn btn-sm btn-outline-warning">Buat WO</a>
                                    @endif
                                    @if ($finding->status !== 'resolved')
                                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#resolveModal-{{ $finding->id }}">Selesai</button>
                                    @endif
                                </td>
                            </tr>

                            <div class="modal fade" id="assignModal-{{ $finding->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('emergency-response.inspection.findings.assign', $finding) }}" method="POST">
                                            @csrf
                                            <div class="modal-header">
                                                <h6 class="modal-title">Assign PIC</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">PIC</label>
                                                    <select name="pic_id" class="form-control">
                                                        <option value="">-- Pilih --</option>
                                                        @foreach ($users as $user)
                                                            <option value="{{ $user->id }}" @selected($finding->pic_id === $user->id)>{{ $user->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Target Selesai</label>
                                                    <input type="date" name="target_date" class="form-control" value="{{ optional($finding->target_date)->format('Y-m-d') }}">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-primary-600">Simpan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="resolveModal-{{ $finding->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('emergency-response.inspection.findings.resolve', $finding) }}" method="POST">
                                            @csrf
                                            <div class="modal-header">
                                                <h6 class="modal-title">Tandai Selesai</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <label class="form-label">Catatan Penyelesaian</label>
                                                <textarea name="resolved_notes" class="form-control" rows="3"></textarea>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-success">Selesai</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary-light py-24">Tidak ada temuan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $findings->links() }}</div>
        </div>
    </div>
@endsection
