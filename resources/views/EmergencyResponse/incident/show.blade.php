@extends('EmergencyResponse.layouts.app')

@section('page-title', $incident->incident_number)

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-16">
        <a href="{{ route('emergency-response.incident.index') }}" class="text-secondary-light"><i class="ri-arrow-left-line"></i> Kembali ke daftar</a>
        <a href="{{ route('emergency-response.incident.pdf', $incident) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="ri-file-pdf-2-line"></i> Export PDF</a>
    </div>

    @if ($incident->is_possible_duplicate)
        <div class="alert alert-warning d-flex align-items-center justify-content-between">
            <span><i class="ri-error-warning-line"></i> Kemungkinan duplikat dari <a href="{{ route('emergency-response.incident.show', $incident->possibleDuplicateOf) }}">{{ $incident->possibleDuplicateOf->incident_number ?? '-' }}</a>.</span>
            <form action="{{ route('emergency-response.incident.dismiss-duplicate', $incident) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">Bukan Duplikat</button>
            </form>
        </div>
    @endif

    <div class="row gy-4">
        <div class="col-lg-8">
            <div class="card shadow-none border mb-24">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">{{ $incident->incident_number }}</h6>
                    <span class="badge bg-info-focus text-info-600 px-16 py-4 radius-4">{{ $incident->statusLabel() }}</span>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr><th width="200">Jenis Insiden</th><td>{{ $incident->incidentType->name ?? '-' }}</td></tr>
                        <tr><th>Tingkat Keparahan / Prioritas</th><td>{{ $incident->severityLevel->name ?? '-' }} / {{ $incident->priorityLevel->name ?? '-' }}</td></tr>
                        <tr><th>Site / Lokasi</th><td>{{ $incident->site->name ?? '-' }} — {{ $incident->location_detail ?: '-' }}</td></tr>
                        <tr><th>Waktu Kejadian</th><td>{{ $incident->occurred_at->format('d M Y H:i') }}</td></tr>
                        <tr><th>Waktu Dilaporkan</th><td>{{ $incident->reported_at->format('d M Y H:i') }}</td></tr>
                        <tr><th>Deskripsi</th><td>{{ $incident->description }}</td></tr>
                        <tr><th>Potensi Bahaya Lanjutan</th><td>{{ $incident->potential_hazards ?: '-' }}</td></tr>
                        <tr><th>Bantuan Dibutuhkan</th><td>{{ $incident->assistance_needed ?: '-' }}</td></tr>
                        <tr><th>Pelapor</th><td>{{ $incident->reporter_name }} — {{ $incident->reporter_phone ?: '-' }} ({{ $incident->reporter_department ?: '-' }})</td></tr>
                    </table>

                    <hr>
                    <div class="d-flex flex-wrap gap-2">
                        @if ($incident->status === 'open')
                            <form action="{{ route('emergency-response.incident.confirm', $incident) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary-600">Konfirmasi Laporan</button>
                            </form>
                            <a href="{{ route('emergency-response.incident.edit', $incident) }}" class="btn btn-outline-secondary">Edit Laporan</a>
                        @endif
                        @if ($incident->status === 'in_progress')
                            @foreach (['dispatched_at' => 'Tim Berangkat', 'arrived_at' => 'Tim Tiba', 'handling_started_at' => 'Mulai Penanganan', 'contained_at' => 'Kondisi Terkendali', 'handling_completed_at' => 'Penanganan Selesai'] as $field => $label)
                                <form action="{{ route('emergency-response.incident.timestamp', $incident) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="field" value="{{ $field }}">
                                    <button type="submit" class="btn btn-sm {{ $incident->{$field} ? 'btn-success' : 'btn-outline-secondary' }}" @disabled($incident->{$field})>
                                        {{ $label }} {{ $incident->{$field} ? '✓ '.$incident->{$field}->format('H:i') : '' }}
                                    </button>
                                </form>
                            @endforeach
                            <form action="{{ route('emergency-response.incident.resolve', $incident) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-warning">Tandai Resolved</button>
                            </form>
                        @endif
                        @if ($incident->status === 'resolved')
                            <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#closeModal">Tutup Insiden (Closed)</button>
                        @endif
                        <button type="button" class="btn btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#assignPicModal">Assign PIC</button>
                    </div>

                    @if ($incident->responseTimeMinutes() !== null)
                        <p class="text-secondary-light text-sm mt-16 mb-0">Response time: <strong>{{ $incident->responseTimeMinutes() }} menit</strong></p>
                    @endif
                    @if ($incident->status !== 'closed' && $incident->status !== 'resolved')
                        <p class="text-secondary-light text-sm mt-8 mb-0">Waktu berjalan sejak dilaporkan: <strong id="elapsed-timer" data-since="{{ $incident->reported_at->toIso8601String() }}">-</strong></p>
                    @endif

                    @if ($incident->status === 'closed')
                        <hr>
                        <table class="table table-borderless mb-0">
                            <tr><th width="200">Root Cause</th><td>{{ $incident->root_cause ?: '-' }}</td></tr>
                            <tr><th>Corrective Action</th><td>{{ $incident->corrective_action ?: '-' }}</td></tr>
                            <tr><th>Ditutup Oleh</th><td>{{ $incident->closedBy->name ?? '-' }} — {{ optional($incident->closed_at)->format('d M Y H:i') }}</td></tr>
                        </table>
                    @endif
                </div>
            </div>

            <div class="card shadow-none border mb-24">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Korban</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#victimModal">Tambah Korban</button>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($incident->victims as $victim)
                            <li class="list-group-item d-flex align-items-center justify-content-between">
                                <span>{{ $victim->name ?: 'Belum diketahui' }} — <span class="badge bg-danger-focus text-danger-600 px-8 py-2 radius-4">{{ $victim->conditionLabel() }}</span> {{ $victim->details }}</span>
                                <form action="{{ route('emergency-response.incident.victims.destroy', [$incident, $victim]) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                </form>
                            </li>
                        @empty
                            <li class="list-group-item text-secondary-light text-center py-16">Belum ada data korban.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="card shadow-none border mb-24">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Unit & Personel Respons</h6>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#unitModal">Kerahkan Unit</button>
                        <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#personnelModal">Tugaskan Personel</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($incident->responseUnits as $unit)
                            <li class="list-group-item">
                                <div class="d-flex align-items-center justify-content-between">
                                    <strong>{{ $unit->emergencyUnit->name ?? '-' }}</strong>
                                    <span class="badge bg-info-focus text-info-600 px-8 py-2 radius-4">{{ $unit->statusLabel() }}</span>
                                </div>
                                <div class="d-flex gap-2 mt-8">
                                    @if ($unit->status === 'dispatched')
                                        <form action="{{ route('emergency-response.incident.units.status', [$incident, $unit]) }}" method="POST">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="status" value="arrived">
                                            <button type="submit" class="btn btn-xs btn-outline-success">Tandai Tiba</button>
                                        </form>
                                    @elseif ($unit->status === 'arrived')
                                        <form action="{{ route('emergency-response.incident.units.status', [$incident, $unit]) }}" method="POST">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="status" value="returned">
                                            <button type="submit" class="btn btn-xs btn-outline-secondary">Tandai Kembali</button>
                                        </form>
                                    @endif
                                </div>
                                @if ($unit->personnel->isNotEmpty())
                                    <ul class="mt-8 mb-0 ps-16">
                                        @foreach ($unit->personnel as $personnel)
                                            <li>{{ $personnel->user->name ?? '-' }} ({{ $personnel->role_in_response ?: 'Personel' }})</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @empty
                            <li class="list-group-item text-secondary-light text-center py-16">Belum ada unit dikerahkan.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="card shadow-none border mb-24">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Penggunaan Equipment</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary-600" data-bs-toggle="modal" data-bs-target="#equipmentModal">Catat Penggunaan</button>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($incident->equipmentUsages as $usage)
                            <li class="list-group-item d-flex align-items-center justify-content-between">
                                <span>{{ $usage->equipmentable->name ?? '-' }} ({{ $usage->equipmentable->code ?? '-' }}) x{{ $usage->quantity_used }}</span>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('emergency-response.work-order.create', ['incident_id' => $incident->id, 'equipment_type' => $usage->equipmentable_type === \App\Models\EmergencyResponse\Equipment\EmergencyEquipment::class ? 'equipment' : 'safety_device', 'equipment_id' => $usage->equipmentable_id]) }}" class="btn btn-sm btn-outline-warning">Buat WO</a>
                                    <form action="{{ route('emergency-response.incident.equipment-usage.destroy', [$incident, $usage]) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-secondary-light text-center py-16">Belum ada equipment dicatat.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="card shadow-none border mb-24">
                <div class="card-header"><h6 class="mb-0">Lampiran (Foto/Video/Dokumen)</h6></div>
                <div class="card-body">
                    <ul class="list-group list-group-flush mb-16">
                        @forelse ($incident->attachments as $attachment)
                            <li class="list-group-item d-flex align-items-center justify-content-between">
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->file_path) }}" target="_blank">{{ $attachment->original_name }}</a>
                                <form action="{{ route('emergency-response.incident.attachments.destroy', [$incident, $attachment]) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                </form>
                            </li>
                        @empty
                            <li class="list-group-item text-secondary-light text-center py-16">Belum ada lampiran.</li>
                        @endforelse
                    </ul>
                    <form action="{{ route('emergency-response.incident.attachments.store', $incident) }}" method="POST" enctype="multipart/form-data" class="row g-2">
                        @csrf
                        <div class="col-md-3">
                            <select name="type" class="form-control" required>
                                <option value="photo">Foto</option>
                                <option value="video">Video</option>
                                <option value="document">Dokumen</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <input type="file" name="file" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-primary-600 w-100">Unggah</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">Timeline & Komentar</h6></div>
                <div class="card-body">
                    <form action="{{ route('emergency-response.incident.comments.store', $incident) }}" method="POST" class="mb-16">
                        @csrf
                        <textarea name="comment" class="form-control mb-8" rows="2" placeholder="Tulis komentar..." required></textarea>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="form-check">
                                <input type="checkbox" name="is_internal" value="1" class="form-check-input" id="is-internal">
                                <label class="form-check-label" for="is-internal">Komentar internal</label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary-600">Kirim</button>
                        </div>
                    </form>
                    <ul class="list-group list-group-flush">
                        @foreach ($incident->timeline as $entry)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <strong>{{ $entry->creator->name ?? 'Sistem' }}</strong>
                                    <span class="text-secondary-light text-sm">{{ $entry->created_at->format('d M Y H:i') }}</span>
                                </div>
                                <p class="mb-0">{{ $entry->description }} @if($entry->is_internal)<span class="badge bg-neutral-200 text-secondary-light">internal</span>@endif</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">PIC Ditugaskan</h6></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($incident->assignments as $assignment)
                            <li class="list-group-item">
                                {{ $assignment->user->name ?? '-' }}
                                <div class="text-secondary-light text-sm">{{ $assignment->role_note ?: 'PIC' }} — {{ $assignment->assigned_at->format('d M Y H:i') }}</div>
                            </li>
                        @empty
                            <li class="list-group-item text-secondary-light text-center py-16">Belum ada PIC.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Modals --}}
    <div class="modal fade" id="victimModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.incident.victims.store', $incident) }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tambah Korban</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nama (opsional)</label><input type="text" name="name" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Kondisi</label>
                        <select name="condition" class="form-control" required>
                            @foreach (\App\Models\EmergencyResponse\Incident\IncidentVictim::CONDITIONS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Detail</label><textarea name="details" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Simpan</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="unitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.incident.units.store', $incident) }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Kerahkan Unit</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Unit Emergency</label>
                    <select name="emergency_unit_id" class="form-control" required>
                        <option value="">-- Pilih --</option>
                        @foreach ($emergencyUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Kerahkan</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="personnelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.incident.personnel.store', $incident) }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tugaskan Personel</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Unit (opsional)</label>
                        <select name="response_unit_id" class="form-control">
                            <option value="">-- Tanpa unit --</option>
                            @foreach ($incident->responseUnits as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->emergencyUnit->name ?? '-' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Personel</label>
                        <select name="user_id" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Peran</label>
                        <input type="text" name="role_in_response" class="form-control" placeholder="mis. Team Leader, Medic">
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Tugaskan</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="equipmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.incident.equipment-usage.store', $incident) }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Catat Penggunaan Equipment</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tipe</label>
                        <select name="equipment_type" id="equipment_type_select" class="form-control" required>
                            <option value="equipment">Emergency Equipment</option>
                            <option value="safety_device">Safety Device</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Equipment</label>
                        <select name="equipment_id" class="form-control" required>
                            <optgroup label="Emergency Equipment" class="eq-group-equipment">
                                @foreach ($availableEquipment as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->code }})</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Safety Device" class="eq-group-safety_device">
                                @foreach ($availableSafetyDevices as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->code }})</option>
                                @endforeach
                            </optgroup>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah</label>
                        <input type="number" name="quantity_used" class="form-control" value="1" min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Simpan</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="assignPicModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('emergency-response.incident.assign-pic', $incident) }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Assign PIC</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <select name="user_id" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Peran</label>
                        <input type="text" name="role_note" class="form-control" placeholder="mis. Investigator, Coordinator">
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-600">Tugaskan</button></div>
            </form>
        </div></div>
    </div>

    @if ($incident->status === 'resolved')
        <div class="modal fade" id="closeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog"><div class="modal-content">
                <form action="{{ route('emergency-response.incident.close', $incident) }}" method="POST">
                    @csrf
                    <div class="modal-header"><h6 class="modal-title">Tutup Insiden</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Root Cause</label><textarea name="root_cause" class="form-control" rows="2"></textarea></div>
                        <div class="mb-3"><label class="form-label">Corrective Action</label><textarea name="corrective_action" class="form-control" rows="2"></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-dark">Tutup Insiden</button></div>
                </form>
            </div></div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            var timerEl = document.getElementById('elapsed-timer');
            if (!timerEl) return;
            var since = new Date(timerEl.dataset.since).getTime();

            function tick() {
                var diff = Math.max(0, Date.now() - since);
                var h = Math.floor(diff / 3600000);
                var m = Math.floor((diff % 3600000) / 60000);
                var s = Math.floor((diff % 60000) / 1000);
                timerEl.textContent = h + ' jam ' + m + ' menit ' + s + ' detik';
            }
            tick();
            setInterval(tick, 1000);

            var typeSelect = document.getElementById('equipment_type_select');
            if (typeSelect) {
                function toggleGroups() {
                    document.querySelectorAll('.eq-group-equipment').forEach(function (el) { el.style.display = typeSelect.value === 'equipment' ? '' : 'none'; });
                    document.querySelectorAll('.eq-group-safety_device').forEach(function (el) { el.style.display = typeSelect.value === 'safety_device' ? '' : 'none'; });
                }
                typeSelect.addEventListener('change', toggleGroups);
                toggleGroups();
            }
        })();
    </script>
@endpush
