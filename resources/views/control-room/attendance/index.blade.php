@extends('control-room.layouts.app')

@section('page-title', 'Absen')

@push('styles')
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-dashboard.css') }}?v={{ filemtime(public_path('wowdash-admin/assets/css/control-room-dashboard.css')) }}">
@endpush

@section('content')
    <form method="GET" class="ocr-card mb-24" action="{{ route('control-room.attendance.index') }}">
        <div class="ocr-toolbar">
            <div class="ocr-toolbar-left">
                <div>
                    <label class="form-label text-sm mb-1" for="ocr-absen-site">Site</label>
                    <select name="site" id="ocr-absen-site" class="form-control" onchange="this.form.submit()">
                        @foreach ($sites as $siteOption)
                            <option value="{{ $siteOption->value }}" @selected($site === $siteOption)>{{ $siteOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @include('control-room.partials.week-period-filter', [
                    'weekInputId' => 'ocr-absen-iso-week',
                    'isoWeekValue' => $isoWeekValue,
                    'weekRangeLabel' => $weekRangeLabel,
                    'year' => $year,
                    'week' => $week,
                    'prevWeekUrl' => route('control-room.attendance.index', ['site' => $site->value, 'year' => $prevYear, 'week' => $prevWeek]),
                    'nextWeekUrl' => route('control-room.attendance.index', ['site' => $site->value, 'year' => $nextYear, 'week' => $nextWeek]),
                ])
            </div>
        </div>
    </form>

    <div class="card shadow-none border">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0">Rekap Absen {{ $weekRangeLabel }} — {{ $site->label() }}</h6>
            <a href="{{ route('control-room.attendance.form') }}" class="btn btn-primary-600 btn-sm">
                <i class="ri-camera-line"></i> Form Absensi
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
