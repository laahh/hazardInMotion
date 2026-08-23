@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Jadwal Maintenance')

@php
    $equipmentClass = \App\Models\EmergencyResponse\Equipment\EmergencyEquipment::class;
@endphp

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Jadwal Preventive Maintenance</h6>
            <button type="button" class="btn btn-primary-600 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="ri-add-line"></i> Tambah Jadwal
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead>
                        <tr><th>Target</th><th>Jenis Maintenance</th><th>Frekuensi</th><th>Jatuh Tempo Berikutnya</th><th>Teknisi</th><th>Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($schedules as $schedule)
                            <tr class="{{ $schedule->next_due_date->isPast() ? 'table-danger' : '' }}">
                                <td>{{ $schedule->target->name ?? '-' }} ({{ $schedule->target->code ?? '-' }})</td>
                                <td>{{ $schedule->maintenanceType->name ?? '-' }}</td>
                                <td>Setiap {{ $schedule->frequency_days }} hari</td>
                                <td>{{ $schedule->next_due_date->format('d M Y') }}</td>
                                <td>{{ $schedule->assignedTechnician->name ?? '-' }}</td>
                                <td>
                                    @if ($schedule->is_active)
                                        <span class="badge bg-success-focus text-success-600 px-16 py-4 radius-4">Aktif</span>
                                    @else
                                        <span class="badge bg-neutral-200 text-secondary-light px-16 py-4 radius-4">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <form action="{{ route('emergency-response.maintenance.schedules.destroy', $schedule) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus jadwal ini?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary-light py-24">Belum ada jadwal maintenance.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $schedules->links() }}</div>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.maintenance.schedules.store') }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tambah Jadwal Maintenance</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tipe Target</label>
                        <select name="target_type" id="ms-target-type" class="form-control" required>
                            <option value="equipment">Emergency Equipment</option>
                            <option value="safety_device">Safety Device</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target</label>
                        <select name="target_id" class="form-control" required>
                            <optgroup label="Emergency Equipment" class="ms-group-equipment">
                                @foreach ($equipmentList as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->code }})</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Safety Device" class="ms-group-safety_device">
                                @foreach ($safetyDeviceList as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->code }})</option>
                                @endforeach
                            </optgroup>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Maintenance</label>
                        <select name="maintenance_type_id" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            @foreach ($maintenanceTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Frekuensi (hari)</label>
                            <input type="number" name="frequency_days" class="form-control" min="1" value="30" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Jatuh Tempo Berikutnya</label>
                            <input type="date" name="next_due_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teknisi (opsional)</label>
                        <select name="assigned_technician_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($technicians as $technician)
                                <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Simpan</button></div>
            </form>
        </div></div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var select = document.getElementById('ms-target-type');
            function toggle() {
                document.querySelectorAll('.ms-group-equipment').forEach(function (el) { el.style.display = select.value === 'equipment' ? '' : 'none'; });
                document.querySelectorAll('.ms-group-safety_device').forEach(function (el) { el.style.display = select.value === 'safety_device' ? '' : 'none'; });
            }
            select.addEventListener('change', toggle);
            toggle();
        })();
    </script>
@endpush
