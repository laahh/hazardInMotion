@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Safety Device')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Safety Device</h6>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('emergency-response.safety-device.export') }}" class="btn btn-outline-secondary btn-sm"><i class="ri-file-excel-2-line"></i> Export</a>
                <a href="{{ route('emergency-response.safety-device.create') }}" class="btn btn-primary-600 btn-sm"><i class="ri-add-line"></i> Tambah</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-16">
                <div class="col-md-3">
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari kode/nama...">
                </div>
                <div class="col-md-2">
                    <select name="safety_device_type_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Jenis</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected(request('safety_device_type_id') === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="site_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Site</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected(request('site_id') === $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="condition" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Kondisi</option>
                        @foreach ($conditions as $value => $label)
                            <option value="{{ $value }}" @selected(request('condition') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="operational_status" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        @foreach ($operationalStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(request('operational_status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-outline-primary-600 w-100"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Jenis</th>
                            <th>Lokasi</th>
                            <th>Kondisi</th>
                            <th>Status</th>
                            <th>Kalibrasi Berikutnya</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($devices as $device)
                            <tr>
                                <td>{{ $device->code }}</td>
                                <td><a href="{{ route('emergency-response.safety-device.show', $device) }}">{{ $device->name }}</a></td>
                                <td>{{ $device->type->name ?? '-' }}</td>
                                <td>{{ $device->site->name ?? '-' }} @if($device->location) / {{ $device->location->name }} @endif</td>
                                <td><span class="badge bg-info-focus text-info-600 px-16 py-4 radius-4">{{ $device->conditionLabel() }}</span></td>
                                <td><span class="badge bg-success-focus text-success-600 px-16 py-4 radius-4">{{ $device->operationalStatusLabel() }}</span></td>
                                <td>{{ optional($device->next_calibration_at)->format('d M Y') ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('emergency-response.safety-device.show', $device) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-eye-line"></i></a>
                                    <a href="{{ route('emergency-response.safety-device.edit', $device) }}" class="btn btn-sm btn-outline-primary-600"><i class="ri-edit-line"></i></a>
                                    <form action="{{ route('emergency-response.safety-device.destroy', $device) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus {{ addslashes($device->name) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-secondary-light py-24">Belum ada data safety device.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $devices->links() }}</div>
        </div>
    </div>
@endsection
