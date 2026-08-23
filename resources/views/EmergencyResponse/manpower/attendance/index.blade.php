@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Kehadiran')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Kehadiran — {{ $date->translatedFormat('d F Y') }}</h6>
            <form method="GET" class="d-flex gap-2">
                <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="form-control" onchange="this.form.submit()">
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead>
                        <tr><th>Nama</th><th>Shift</th><th>Status</th><th>Check-in</th><th>Check-out</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php $attendance = $employee->attendance->first(); @endphp
                            <tr>
                                <td>{{ $employee->full_name }}</td>
                                <td>
                                    <form action="{{ route('emergency-response.manpower.attendance.store', $employee) }}" method="POST" class="d-flex gap-2">
                                        @csrf
                                        <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                                        <select name="shift_id" class="form-control form-control-sm" onchange="this.form.submit()">
                                            <option value="">-- Shift --</option>
                                            @foreach ($shifts as $shift)
                                                <option value="{{ $shift->id }}" @selected(optional($attendance)->shift_id === $shift->id)>{{ $shift->name }}</option>
                                            @endforeach
                                        </select>
                                        <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                            @foreach (\App\Models\EmergencyResponse\Manpower\Attendance::STATUSES as $value => $label)
                                                <option value="{{ $value }}" @selected(optional($attendance)->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td>{{ $attendance ? $attendance->statusLabel() : '-' }}</td>
                                <td>{{ optional(optional($attendance)->check_in_at)->format('H:i') ?? '-' }}</td>
                                <td>{{ optional(optional($attendance)->check_out_at)->format('H:i') ?? '-' }}</td>
                                <td class="text-end">
                                    <form action="{{ route('emergency-response.manpower.attendance.check-in', $employee) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">Check-in</button>
                                    </form>
                                    <form action="{{ route('emergency-response.manpower.attendance.check-out', $employee) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Check-out</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary-light py-24">Belum ada data personel aktif.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
