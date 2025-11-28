@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Alert History - Beraucoal')

@section('css')
<style>
    .history-header {
        margin-bottom: 24px;
    }

    .history-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .history-subtitle {
        font-size: 14px;
        color: #6b7280;
    }

    .history-table {
        font-size: 14px;
    }

    .severity-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .severity-critical {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .severity-high {
        background-color: #fef3c7;
        color: #92400e;
    }

    .severity-medium {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-resolved {
        background-color: #d1fae5;
        color: #065f46;
    }
</style>
@endsection

@section('content')
<div class="history-header">
    <h1 class="history-title">Alert History</h1>
    <p class="history-subtitle">View historical alerts and their resolution status</p>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Alert History</h5>
        <a href="{{ route('realtime-alerts.index') }}" class="btn btn-sm btn-primary">
            <i class="material-icons-outlined">notifications_active</i> View Active Alerts
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover history-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Severity</th>
                        <th>Title</th>
                        <th>Zone</th>
                        <th>Detected At</th>
                        <th>Resolved At</th>
                        <th>Duration</th>
                        <th>Resolved By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($alertHistory as $alert)
                    <tr>
                        <td>{{ $alert['id'] }}</td>
                        <td>{{ $alert['type'] }}</td>
                        <td>
                            <span class="severity-badge severity-{{ $alert['severity'] }}">
                                {{ $alert['severity'] }}
                            </span>
                        </td>
                        <td>{{ $alert['title'] }}</td>
                        <td>{{ $alert['zone'] }}</td>
                        <td>{{ $alert['detected_at'] }}</td>
                        <td>{{ $alert['resolved_at'] }}</td>
                        <td>{{ $alert['duration'] }}</td>
                        <td>{{ $alert['resolved_by'] }}</td>
                        <td>
                            <span class="status-badge status-{{ $alert['status'] }}">
                                {{ $alert['status'] }}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewDetails('{{ $alert['id'] }}')">
                                View
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">
                            No alert history found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function viewDetails(alertId) {
        alert('View details for alert: ' + alertId);
    }
</script>
@endsection

