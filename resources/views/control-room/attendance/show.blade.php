@extends('control-room.layouts.app')

@section('page-title', 'Detail Absen')

@section('content')
    <div class="card shadow-none border mb-24">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0">Detail Absen — {{ $attendance->personnel_name_snapshot }}</h6>
            <a href="{{ route('control-room.attendance.index') }}" class="text-primary-600 text-sm">&larr; Kembali</a>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-md-3">Tanggal</dt>
                <dd class="col-md-9">{{ $attendance->date->translatedFormat('l, d M Y') }}</dd>

                <dt class="col-md-3">Shift</dt>
                <dd class="col-md-9">{{ $attendance->shift_code->label() }}</dd>

                <dt class="col-md-3">Site</dt>
                <dd class="col-md-9">{{ $attendance->site_code->label() }}</dd>

                <dt class="col-md-3">Status</dt>
                <dd class="col-md-9">{{ str_replace('_', ' ', $attendance->status) }}</dd>

                @if ($attendance->replacing_source_key)
                    <dt class="col-md-3">Menggantikan</dt>
                    <dd class="col-md-9">
                        {{ $attendance->replacing_source_key }}
                        @if ($replacedPersonnel)
                            ({{ $replacedPersonnel->personnel_name_snapshot }})
                        @endif
                    </dd>
                @endif

                @if ($attendance->absence_reason)
                    <dt class="col-md-3">Alasan Tidak Hadir</dt>
                    <dd class="col-md-9">{{ $attendance->absence_reason }}</dd>
                @endif

                <dt class="col-md-3">Jam Check-in</dt>
                <dd class="col-md-9">{{ $attendance->checked_in_at->format('d M Y H:i:s') }}</dd>

                @if ($attendance->proofUrl())
                    <dt class="col-md-3">Bukti</dt>
                    <dd class="col-md-9">
                        @if ($attendance->proofIsImage())
                            <img src="{{ $attendance->proofUrl() }}" alt="Bukti absensi" style="max-width: 320px; border-radius: 8px;">
                        @else
                            <a href="{{ $attendance->proofUrl() }}" target="_blank" rel="noopener">Lihat file bukti</a>
                        @endif
                    </dd>
                @endif

                @if ($attendance->correction_reason)
                    <dt class="col-md-3">Koreksi Terakhir</dt>
                    <dd class="col-md-9">{{ $attendance->correction_reason }} (oleh {{ $attendance->correctedBy->name ?? '-' }})</dd>
                @endif
            </dl>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">Koreksi Absen</h6></div>
        <div class="card-body">
            <form method="POST" action="{{ route('control-room.attendance.update', $attendance) }}">
                @csrf
                @method('PUT')
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label text-sm mb-1">Status</label>
                        <select name="status" class="form-control">
                            <option value="hadir_sesuai_jadwal" @selected($attendance->status === 'hadir_sesuai_jadwal')>Hadir Sesuai Jadwal</option>
                            <option value="hadir_menggantikan" @selected($attendance->status === 'hadir_menggantikan')>Hadir Menggantikan</option>
                            <option value="tidak_hadir" @selected($attendance->status === 'tidak_hadir')>Tidak Hadir</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-sm mb-1">Menggantikan (source key, jika ada)</label>
                        <input type="text" name="replacing_source_key" value="{{ $attendance->replacing_source_key }}" class="form-control">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label text-sm mb-1">Alasan Tidak Hadir (jika ada)</label>
                        <textarea name="absence_reason" class="form-control">{{ $attendance->absence_reason }}</textarea>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label text-sm mb-1">Alasan Koreksi (wajib)</label>
                        <textarea name="correction_reason" class="form-control" required></textarea>
                    </div>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary-600">Simpan Koreksi</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
