@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Geofence Rules - Beraucoal')

@section('css')
<style>
    .rules-header {
        margin-bottom: 24px;
    }

    .rules-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .rules-subtitle {
        font-size: 14px;
        color: #6b7280;
    }

    .rule-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        transition: all 0.3s ease;
        background: white;
    }

    .rule-card:hover {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .rule-card.active {
        border-left: 4px solid #10b981;
    }

    .rule-card.inactive {
        border-left: 4px solid #6b7280;
        opacity: 0.7;
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

    .status-active {
        background-color: #d1fae5;
        color: #065f46;
    }

    .status-inactive {
        background-color: #f3f4f6;
        color: #6b7280;
    }

    .action-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        background-color: #eff6ff;
        color: #1e40af;
    }

    .rule-actions {
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

    .btn-edit {
        background-color: #3b82f6;
        color: white;
    }

    .btn-edit:hover {
        background-color: #2563eb;
    }

    .btn-delete {
        background-color: #ef4444;
        color: white;
    }

    .btn-delete:hover {
        background-color: #dc2626;
    }

    .notify-users {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 8px;
    }

    .user-badge {
        display: inline-block;
        padding: 2px 8px;
        background-color: #f3f4f6;
        color: #374151;
        border-radius: 4px;
        font-size: 11px;
    }
</style>
@endsection

@section('content')
<div class="rules-header">
    <h1 class="rules-title">Geofence Rules</h1>
    <p class="rules-subtitle">Configure rules and actions for geofence zone violations</p>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Geofence Rules</h5>
        <button class="btn btn-sm btn-primary" onclick="openCreateRuleModal()">
            <i class="material-icons-outlined">add</i> Create Rule
        </button>
    </div>
    <div class="card-body">
        @foreach($geofenceRules as $rule)
        <div class="rule-card {{ $rule['status'] === 'active' ? 'active' : 'inactive' }}">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="flex-grow-1">
                    <h6 class="mb-1">{{ $rule['name'] }}</h6>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <span class="severity-badge severity-{{ $rule['severity'] }}">
                            {{ $rule['severity'] }}
                        </span>
                        <span class="action-badge">
                            {{ $rule['action'] }}
                        </span>
                        <span class="status-badge status-{{ $rule['status'] }}">
                            {{ $rule['status'] }}
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="mb-2">
                <p class="mb-1"><strong>Zone:</strong> {{ $rule['zone_name'] }} ({{ $rule['zone_id'] }})</p>
                <p class="mb-1"><strong>Trigger Type:</strong> {{ $rule['trigger_type'] }}</p>
                <p class="mb-1"><strong>Action:</strong> {{ $rule['action'] }}</p>
            </div>
            
            <div class="mb-2">
                <strong>Notify Users:</strong>
                <div class="notify-users">
                    @foreach($rule['notify_users'] as $user)
                    <span class="user-badge">{{ $user }}</span>
                    @endforeach
                </div>
            </div>
            
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">Created: {{ $rule['created_at'] }}</small>
                <div class="rule-actions">
                    <button class="btn-action btn-edit" onclick="editRule('{{ $rule['id'] }}')">
                        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">edit</i>
                        Edit
                    </button>
                    <button class="btn-action btn-delete" onclick="deleteRule('{{ $rule['id'] }}')">
                        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">delete</i>
                        Delete
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openCreateRuleModal() {
        alert('Open create rule modal');
        // Open modal to create new rule
    }

    function editRule(ruleId) {
        alert('Edit rule: ' + ruleId);
        // Open edit modal
    }

    function deleteRule(ruleId) {
        if (confirm('Are you sure you want to delete this rule?')) {
            alert('Delete rule: ' + ruleId);
            // Delete rule logic
        }
    }
</script>
@endsection

