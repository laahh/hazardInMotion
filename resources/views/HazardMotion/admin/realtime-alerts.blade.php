@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Real-time Alerts - Beraucoal')

@section('css')
<style>
    .alerts-header {
        margin-bottom: 24px;
    }

    .alerts-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .alerts-subtitle {
        font-size: 14px;
        color: #6b7280;
    }

    .stats-card {
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        color: white;
    }

    .stats-card.total {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .stats-card.unread {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .stats-card.critical {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }

    .stats-card.unacknowledged {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .stats-number {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .stats-label {
        font-size: 14px;
        opacity: 0.9;
    }

    .alert-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        transition: all 0.3s ease;
        background: white;
        position: relative;
    }

    .alert-card:hover {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .alert-card.unread {
        border-left: 4px solid #ef4444;
        background: #fef2f2;
    }

    .alert-card.read {
        border-left: 4px solid #10b981;
    }

    .alert-card.critical {
        border-left: 4px solid #dc2626;
        background: #fee2e2;
    }

    .alert-card.high {
        border-left: 4px solid #f59e0b;
        background: #fef3c7;
    }

    .alert-card.medium {
        border-left: 4px solid #3b82f6;
        background: #dbeafe;
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

    .priority-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .priority-critical {
        background-color: #dc2626;
        color: white;
    }

    .priority-high {
        background-color: #f59e0b;
        color: white;
    }

    .priority-medium {
        background-color: #3b82f6;
        color: white;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-unread {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .status-read {
        background-color: #d1fae5;
        color: #065f46;
    }

    .alert-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 12px;
    }

    .alert-title {
        font-size: 16px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 4px;
    }

    .alert-message {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 12px;
    }

    .alert-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 12px;
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

    .alert-actions {
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

    .btn-acknowledge {
        background-color: #10b981;
        color: white;
    }

    .btn-acknowledge:hover {
        background-color: #059669;
    }

    .btn-view {
        background-color: #3b82f6;
        color: white;
    }

    .btn-view:hover {
        background-color: #2563eb;
    }

    .btn-dismiss {
        background-color: #6b7280;
        color: white;
    }

    .btn-dismiss:hover {
        background-color: #4b5563;
    }

    .filter-controls {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .alert-time {
        font-size: 12px;
        color: #9ca3af;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .pulse-indicator {
        width: 8px;
        height: 8px;
        background-color: #ef4444;
        border-radius: 50%;
        display: inline-block;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }

    .acknowledged-badge {
        display: inline-block;
        padding: 4px 8px;
        background-color: #d1fae5;
        color: #065f46;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }
</style>
@endsection

@section('content')
<div class="alerts-header">
    <h1 class="alerts-title">Real-time Alerts</h1>
    <p class="alerts-subtitle">Monitor and manage real-time safety alerts from operational areas</p>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-md-3">
        <div class="stats-card total">
            <div class="stats-number">{{ $stats['total_alerts'] }}</div>
            <div class="stats-label">Total Alerts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card unread">
            <div class="stats-number">{{ $stats['unread_alerts'] }}</div>
            <div class="stats-label">Unread Alerts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card critical">
            <div class="stats-number">{{ $stats['critical_alerts'] }}</div>
            <div class="stats-label">Critical Alerts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card unacknowledged">
            <div class="stats-number">{{ $stats['unacknowledged_alerts'] }}</div>
            <div class="stats-label">Unacknowledged</div>
        </div>
    </div>
</div>

<!-- Filter Controls -->
<div class="filter-controls">
    <div class="row">
        <div class="col-md-3">
            <label for="statusFilter" class="form-label">Status Filter</label>
            <select id="statusFilter" class="form-select">
                <option value="all">All Status</option>
                <option value="unread">Unread</option>
                <option value="read">Read</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="severityFilter" class="form-label">Severity Filter</label>
            <select id="severityFilter" class="form-select">
                <option value="all">All Severity</option>
                <option value="critical">Critical</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="typeFilter" class="form-label">Type Filter</label>
            <select id="typeFilter" class="form-select">
                <option value="all">All Types</option>
                <option value="Personnel Violation">Personnel Violation</option>
                <option value="Equipment Violation">Equipment Violation</option>
                <option value="Safety Protocol">Safety Protocol</option>
                <option value="Unauthorized Access">Unauthorized Access</option>
                <option value="Geofence Violation">Geofence Violation</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="acknowledgedFilter" class="form-label">Acknowledged</label>
            <select id="acknowledgedFilter" class="form-select">
                <option value="all">All</option>
                <option value="unacknowledged">Unacknowledged</option>
                <option value="acknowledged">Acknowledged</option>
            </select>
        </div>
    </div>
</div>

<!-- Alerts List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Active Alerts</h5>
        <div>
            <button class="btn btn-sm btn-primary" onclick="refreshAlerts()">
                <i class="material-icons-outlined">refresh</i> Refresh
            </button>
            <a href="{{ route('realtime-alerts.history') }}" class="btn btn-sm btn-outline-secondary">
                <i class="material-icons-outlined">history</i> View History
            </a>
        </div>
    </div>
    <div class="card-body">
        <div id="alertsList">
            @foreach($activeAlerts as $alert)
            <div class="alert-card {{ $alert['status'] }} {{ $alert['severity'] }}" 
                 data-alert-id="{{ $alert['id'] }}"
                 data-status="{{ $alert['status'] }}"
                 data-severity="{{ $alert['severity'] }}"
                 data-type="{{ $alert['type'] }}"
                 data-acknowledged="{{ $alert['acknowledged'] ? 'true' : 'false' }}">
                <div class="alert-header">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <h6 class="alert-title mb-0">{{ $alert['title'] }}</h6>
                            @if($alert['status'] === 'unread')
                            <span class="pulse-indicator"></span>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="severity-badge severity-{{ $alert['severity'] }}">
                                {{ $alert['severity'] }}
                            </span>
                            <span class="priority-badge priority-{{ $alert['priority'] }}">
                                {{ $alert['priority'] }} Priority
                            </span>
                            <span class="status-badge status-{{ $alert['status'] }}">
                                {{ $alert['status'] }}
                            </span>
                            @if($alert['acknowledged'])
                            <span class="acknowledged-badge">
                                ✓ Acknowledged by {{ $alert['acknowledged_by'] }}
                            </span>
                            @endif
                        </div>
                    </div>
                    <div class="alert-time">
                        <i class="material-icons-outlined" style="font-size: 14px;">schedule</i>
                        <span>{{ $alert['duration'] }}</span>
                    </div>
                </div>
                
                <p class="alert-message">{{ $alert['message'] }}</p>
                
                <div class="alert-details">
                    <div class="detail-item">
                        <span class="detail-label">Location / Zone</span>
                        <span class="detail-value">{{ $alert['zone'] }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">CCTV</span>
                        <span class="detail-value">{{ $alert['cctv_name'] }} ({{ $alert['cctv_id'] }})</span>
                    </div>
                    @if($alert['personnel_name'])
                    <div class="detail-item">
                        <span class="detail-label">Personnel</span>
                        <span class="detail-value">{{ $alert['personnel_name'] }}</span>
                    </div>
                    @endif
                    @if($alert['equipment_id'])
                    <div class="detail-item">
                        <span class="detail-label">Equipment</span>
                        <span class="detail-value">{{ $alert['equipment_id'] }} 
                            @if(isset($alert['equipment_type']))
                            ({{ $alert['equipment_type'] }})
                            @endif
                        </span>
                    </div>
                    @endif
                    <div class="detail-item">
                        <span class="detail-label">Distance</span>
                        <span class="detail-value">{{ $alert['distance'] }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Detected At</span>
                        <span class="detail-value">{{ $alert['detected_at'] }}</span>
                    </div>
                </div>
                
                @if(!$alert['acknowledged'])
                <div class="alert-actions">
                    <button class="btn-action btn-acknowledge" onclick="acknowledgeAlert('{{ $alert['id'] }}')">
                        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">check_circle</i>
                        Acknowledge
                    </button>
                    <button class="btn-action btn-view" onclick="viewAlertDetails('{{ $alert['id'] }}')">
                        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">visibility</i>
                        View Details
                    </button>
                    <button class="btn-action btn-dismiss" onclick="dismissAlert('{{ $alert['id'] }}')">
                        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">close</i>
                        Dismiss
                    </button>
                </div>
                @else
                <div class="alert-actions">
                    <span class="text-muted" style="font-size: 12px;">
                        Acknowledged by {{ $alert['acknowledged_by'] }} at {{ $alert['acknowledged_at'] }}
                    </span>
                    <button class="btn-action btn-view" onclick="viewAlertDetails('{{ $alert['id'] }}')">
                        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">visibility</i>
                        View Details
                    </button>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Auto-refresh alerts every 30 seconds
    let refreshInterval;
    
    function startAutoRefresh() {
        refreshInterval = setInterval(function() {
            refreshAlerts();
        }, 30000); // 30 seconds
    }
    
    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    }
    
    // Start auto-refresh when page loads
    document.addEventListener('DOMContentLoaded', function() {
        startAutoRefresh();
    });
    
    // Stop auto-refresh when page is hidden
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
        }
    });
    
    function refreshAlerts() {
        // Fetch new alerts via API
        fetch('{{ route("realtime-alerts.api.alerts") }}')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Alerts refreshed:', data.count);
                    // Update UI with new alerts
                    // For now, just reload the page
                    // In production, update DOM dynamically
                }
            })
            .catch(error => {
                console.error('Error refreshing alerts:', error);
            });
    }
    
    function acknowledgeAlert(alertId) {
        if (confirm('Are you sure you want to acknowledge this alert?')) {
            fetch(`{{ route('realtime-alerts.acknowledge', '') }}/${alertId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Alert acknowledged successfully!');
                    location.reload();
                } else {
                    alert('Failed to acknowledge alert');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error acknowledging alert');
            });
        }
    }
    
    function viewAlertDetails(alertId) {
        // Find alert data
        const alertCard = document.querySelector(`[data-alert-id="${alertId}"]`);
        if (alertCard) {
            const title = alertCard.querySelector('.alert-title').textContent;
            const message = alertCard.querySelector('.alert-message').textContent;
            
            // Show modal or navigate to detail page
            alert(`Alert Details:\n\nID: ${alertId}\nTitle: ${title}\nMessage: ${message}`);
        }
    }
    
    function dismissAlert(alertId) {
        if (confirm('Are you sure you want to dismiss this alert?')) {
            // Mark as read/dismissed
            const alertCard = document.querySelector(`[data-alert-id="${alertId}"]`);
            if (alertCard) {
                alertCard.style.opacity = '0.5';
                alertCard.querySelector('.status-badge').textContent = 'read';
                alertCard.classList.remove('unread');
                alertCard.classList.add('read');
            }
        }
    }
    
    // Filter functionality
    document.getElementById('statusFilter').addEventListener('change', filterAlerts);
    document.getElementById('severityFilter').addEventListener('change', filterAlerts);
    document.getElementById('typeFilter').addEventListener('change', filterAlerts);
    document.getElementById('acknowledgedFilter').addEventListener('change', filterAlerts);
    
    function filterAlerts() {
        const statusFilter = document.getElementById('statusFilter').value;
        const severityFilter = document.getElementById('severityFilter').value;
        const typeFilter = document.getElementById('typeFilter').value;
        const acknowledgedFilter = document.getElementById('acknowledgedFilter').value;
        
        const alertCards = document.querySelectorAll('.alert-card');
        alertCards.forEach(function(card) {
            const status = card.getAttribute('data-status');
            const severity = card.getAttribute('data-severity');
            const type = card.getAttribute('data-type');
            const acknowledged = card.getAttribute('data-acknowledged');
            
            let show = true;
            
            if (statusFilter !== 'all' && status !== statusFilter) show = false;
            if (severityFilter !== 'all' && severity !== severityFilter) show = false;
            if (typeFilter !== 'all' && type !== typeFilter) show = false;
            if (acknowledgedFilter === 'acknowledged' && acknowledged !== 'true') show = false;
            if (acknowledgedFilter === 'unacknowledged' && acknowledged !== 'false') show = false;
            
            card.style.display = show ? 'block' : 'none';
        });
    }
</script>
@endsection

