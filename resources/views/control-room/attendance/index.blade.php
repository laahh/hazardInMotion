@extends('control-room.layouts.app')

@section('page-title', 'Absen')

@section('content')
    <form method="GET" class="card shadow-none border mb-24">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-sm mb-1">Site</label>
                    <select name="site" class="form-control">
                        @foreach ($sites as $siteOption)
                            <option value="{{ $siteOption->value }}" @selected($site === $siteOption)>{{ $siteOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-sm mb-1">Tahun</label>
                    <input type="number" name="year" value="{{ $year }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-sm mb-1">Minggu</label>
                    <input type="number" name="week" value="{{ $week }}" min="1" max="53" class="form-control">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary-600 w-100">Terapkan Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-none border">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0">Rekap Absen Minggu {{ $week }} / {{ $year }} — {{ $site->label() }}</h6>
            <a href="{{ route('control-room.attendance.check-in.form', ['site' => $site->value]) }}" class="btn btn-primary-600 btn-sm">
                <i class="ri-user-follow-line"></i> Check-in Sekarang
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Shift</th>
                            <th>Personil</th>
                            <th>Status</th>
                            <th>Jam Check-in</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendances as $attendance)
                            <tr>
                                <td>{{ $attendance->date->translatedFormat('D, d M Y') }}</td>
                                <td><span class="badge bg-info-focus text-info-600 px-8 py-2 radius-4">{{ $attendance->shift_code->label() }}</span></td>
                                <td>{{ $attendance->personnel_name_snapshot }}</td>
                                <td>
                                    @php
                                        $statusColor = match ($attendance->status) {
                                            \App\Models\ControlRoom\Attendance::STATUS_SESUAI_JADWAL => 'success',
                                            \App\Models\ControlRoom\Attendance::STATUS_MENGGANTIKAN => 'warning',
                                            default => 'danger',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $statusColor }}-focus text-{{ $statusColor }}-600 px-8 py-2 radius-4">
                                        {{ str_replace('_', ' ', $attendance->status) }}
                                    </span>
                                </td>
                                <td>{{ $attendance->checked_in_at->format('H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('control-room.attendance.show', $attendance) }}" class="btn btn-outline-primary btn-sm">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-secondary-light py-24">Belum ada absen untuk minggu ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-16">{{ $attendances->links() }}</div>
        </div>
    </div>
@endsection
