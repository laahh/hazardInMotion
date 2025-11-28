@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Boundary Monitoring - Beraucoal')

@section('css')
<style>
    .monitoring-header {
        margin-bottom: 24px;
    }

    .monitoring-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .monitoring-subtitle {
        font-size: 14px;
        color: #6b7280;
    }

    .event-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        transition: all 0.3s ease;
        background: white;
    }

    .event-card:hover {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .event-card.active {
        border-left: 4px solid #ef4444;
        background: #fef2f2;
    }

    .event-card.resolved {
        border-left: 4px solid #10b981;
        background: #f0fdf4;
    }

    .event-type-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .event-type-entry {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .event-type-unauthorized_entry {
        background-color: #fef3c7;
        color: #92400e;
    }

    .event-type-exit {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .entity-type-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 600;
        background-color: #eff6ff;
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

    .status-active {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .status-resolved {
        background-color: #d1fae5;
        color: #065f46;
    }

    .event-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #e5e7eb;
    }

    .detail-item {
        display: flex;
        flex-direction: column;
    }

    .detail-label {
        font-size: 12px;
        color: #6b7280;
        font-weight: 500;
        margin-bottom: 4px;
    }

    .detail-value {
        font-size: 14px;
        color: #111827;
        font-weight: 600;
    }

    .event-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }

    .btn-action {
        padding: 6px 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-resolve {
        background-color: #10b981;
        color: white;
    }

    .btn-resolve:hover {
        background-color: #059669;
    }

    .btn-view {
        background-color: #3b82f6;
        color: white;
    }

    .btn-view:hover {
        background-color: #2563eb;
    }
</style>
@endsection

@section('content')
<div class="monitoring-header">
    <h1 class="monitoring-title">Boundary Monitoring</h1>
    <p class="monitoring-subtitle">Monitor real-time boundary events and zone violations</p>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Boundary Events</h5>
        <button class="btn btn-sm btn-primary" onclick="refreshEvents()">
            <i class="material-icons-outlined">refresh</i> Refresh
        </button>
    </div>
    <div class="card-body">
        @foreach($boundaryEvents as $event)
        <div class="event-card {{ $event['status'] === 'active' ? 'active' : 'resolved' }}">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="flex-grow-1">
                    <h6 class="mb-1">{{ $event['zone_name'] }}</h6>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <span class="event-type-badge event-type-{{ $event['event_type'] }}">
                            {{ str_replace('_', ' ', $event['event_type']) }}
                        </span>
                        <span class="entity-type-badge">
                            {{ $event['entity_type'] }}
                        </span>
                        <span class="status-badge status-{{ $event['status'] }}">
                            {{ $event['status'] }}
                        </span>
                    </div>
                </div>
                <small class="text-muted">{{ $event['timestamp'] }}</small>
            </div>
            
            <div class="event-details">
                <div class="detail-item">
                    <span class="detail-label">Entity ID</span>
                    <span class="detail-value">{{ $event['entity_id'] }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Entity Name</span>
                    <span class="detail-value">{{ $event['entity_name'] }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Zone</span>
                    <span class="detail-value">{{ $event['zone_name'] }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Location</span>
                    <span class="detail-value">{{ $event['location']['lat'] }}, {{ $event['location']['lng'] }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Action Taken</span>
                    <span class="detail-value">{{ $event['action_taken'] }}</span>
                </div>
            </div>
            
            @if($event['status'] === 'active')
            <div class="event-actions">
                <button class="btn-action btn-resolve" onclick="resolveEvent('{{ $event['id'] }}')">
                    <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">check_circle</i>
                    Resolve
                </button>
                <button class="btn-action btn-view" onclick="viewEventDetails('{{ $event['id'] }}')">
                    <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">visibility</i>
                    View Details
                </button>
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endsection

@section('scripts')
<script>
    function refreshEvents() {
        location.reload();
    }

    function resolveEvent(eventId) {
        if (confirm('Are you sure you want to resolve this event?')) {
            alert('Resolve event: ' + eventId);
            // Resolve event logic
            location.reload();
        }
    }

    function viewEventDetails(eventId) {
        alert('View details for event: ' + eventId);
        // Show event details modal
    }
</script>
@endsection

