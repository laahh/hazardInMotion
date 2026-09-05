@extends('control-room.layouts.app')

@section('page-title', 'Riwayat Perubahan Jadwal')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0">Riwayat Perubahan Jadwal Terkunci</h6>
            <a href="{{ route('control-room.schedule.index') }}" class="text-primary-600 text-sm">&larr; Kembali ke Jadwal</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Jadwal</th>
                            <th>Field</th>
                            <th>Dari</th>
                            <th>Ke</th>
                            <th>Alasan</th>
                            <th>Diubah oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($changes as $change)
                            <tr>
                                <td>{{ $change->changed_at->format('d M Y H:i') }}</td>
                                <td>#{{ $change->schedule_plan_id }}</td>
                                <td>{{ $change->field }}</td>
                                <td>{{ $change->old_value }}</td>
                                <td>{{ $change->new_value }}</td>
                                <td>{{ $change->reason }}</td>
                                <td>{{ $change->changedBy->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-secondary-light py-24">Belum ada perubahan tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-16">{{ $changes->links() }}</div>
        </div>
    </div>
@endsection
