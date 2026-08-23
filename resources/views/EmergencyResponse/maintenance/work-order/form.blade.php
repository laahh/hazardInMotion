@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Buat Work Order')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">Buat Work Order</h6></div>
        <div class="card-body">
            <form action="{{ route('emergency-response.work-order.store') }}" method="POST">
                @csrf
                @if ($sourceFindingId)
                    <input type="hidden" name="source" value="inspection">
                    <input type="hidden" name="source_inspection_finding_id" value="{{ $sourceFindingId }}">
                @elseif ($sourceIncidentId)
                    <input type="hidden" name="source" value="incident">
                    <input type="hidden" name="source_incident_id" value="{{ $sourceIncidentId }}">
                @endif

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tipe Equipment</label>
                        <select name="equipment_type" id="wo-equipment-type" class="form-control">
                            <option value="">-- Tidak terkait equipment --</option>
                            <option value="equipment" @selected($equipmentType === 'equipment')>Emergency Equipment</option>
                            <option value="safety_device" @selected($equipmentType === 'safety_device')>Safety Device</option>
                        </select>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Equipment</label>
                        <select name="equipment_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            <optgroup label="Emergency Equipment" class="wo-group-equipment">
                                @foreach ($equipmentList as $item)
                                    <option value="{{ $item->id }}" @selected($equipmentId === $item->id)>{{ $item->name }} ({{ $item->code }})</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Safety Device" class="wo-group-safety_device">
                                @foreach ($safetyDeviceList as $item)
                                    <option value="{{ $item->id }}" @selected($equipmentId === $item->id)>{{ $item->name }} ({{ $item->code }})</option>
                                @endforeach
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jenis Pekerjaan</label>
                        <select name="work_type" class="form-control" required>
                            @foreach (\App\Models\EmergencyResponse\Maintenance\WorkOrder::WORK_TYPES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Prioritas</label>
                        <select name="priority_level_id" class="form-control">
                            <option value="">-- Pilih --</option>
                            @foreach ($priorityLevels as $level)
                                <option value="{{ $level->id }}">{{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Deskripsi Kerusakan / Pekerjaan</label>
                    <textarea name="description" class="form-control" rows="3" required></textarea>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Vendor (opsional)</label>
                        <select name="vendor_id" class="form-control">
                            <option value="">-- Internal --</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Target Mulai</label>
                        <input type="date" name="target_start_at" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Target Selesai</label>
                        <input type="date" name="target_end_at" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Estimasi Biaya</label>
                    <input type="number" step="any" name="estimated_cost" class="form-control" style="max-width: 240px;">
                </div>

                <div class="d-flex gap-2 mt-16">
                    <button type="submit" class="btn btn-primary-600">Buat Work Order</button>
                    <a href="{{ route('emergency-response.work-order.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var select = document.getElementById('wo-equipment-type');
            function toggle() {
                document.querySelectorAll('.wo-group-equipment').forEach(function (el) { el.style.display = select.value === 'equipment' ? '' : 'none'; });
                document.querySelectorAll('.wo-group-safety_device').forEach(function (el) { el.style.display = select.value === 'safety_device' ? '' : 'none'; });
            }
            select.addEventListener('change', toggle);
            toggle();
        })();
    </script>
@endpush
