@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Alert Sistem')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0">Alert Sistem (H-90..H-0 & Overdue)</h6>
            <a href="{{ route('emergency-response.notification.index') }}" class="btn btn-outline-secondary btn-sm">Kembali ke Notifikasi Saya</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead><tr><th>Jenis Alert</th><th>Target</th><th>Jatuh Tempo</th><th>Threshold</th><th>Status</th><th>Dikirim</th></tr></thead>
                    <tbody>
                        @forelse ($alerts as $alert)
                            <tr>
                                <td>{{ $alert->alert_type }}</td>
                                <td>{{ $alert->alertable->name ?? $alert->alertable->work_order_number ?? $alert->alertable_id }}</td>
                                <td>{{ optional($alert->due_date)->format('d M Y') ?? '-' }}</td>
                                <td>{{ $alert->threshold_days === -1 ? 'Overdue' : ($alert->threshold_days !== null ? "H-{$alert->threshold_days}" : '-') }}</td>
                                <td><span class="badge bg-info-focus text-info-600 px-16 py-4 radius-4">{{ ucfirst($alert->status) }}</span></td>
                                <td>{{ optional($alert->sent_at)->format('d M Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary-light py-24">Belum ada alert.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $alerts->links() }}</div>
        </div>
    </div>
@endsection
