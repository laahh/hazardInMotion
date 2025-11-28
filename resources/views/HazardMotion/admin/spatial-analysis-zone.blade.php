@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Spatial Analysis - Zone Analysis - Beraucoal')

@section('css')
<style>
    .analysis-header {
        margin-bottom: 24px;
    }

    .analysis-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .analysis-subtitle {
        font-size: 14px;
        color: #6b7280;
    }

    .zone-analysis-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
        background: white;
    }

    .zone-analysis-card:hover {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .risk-score {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .risk-high {
        color: #dc2626;
    }

    .risk-medium {
        color: #f59e0b;
    }

    .risk-low {
        color: #10b981;
    }

    .risk-level-badge {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .risk-level-high {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .risk-level-medium {
        background-color: #fef3c7;
        color: #92400e;
    }

    .risk-level-low {
        background-color: #d1fae5;
        color: #065f46;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
        margin-top: 16px;
    }

    .stat-item {
        text-align: center;
        padding: 12px;
        background: #f9fafb;
        border-radius: 6px;
    }

    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #111827;
    }

    .stat-label {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }
</style>
@endsection

@section('content')
<div class="analysis-header">
    <h1 class="analysis-title">Spatial Analysis - Zone Analysis</h1>
    <p class="analysis-subtitle">Analyze activity and events by zone</p>
</div>

@foreach($zoneAnalysis as $zone)
<div class="zone-analysis-card">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h5 class="mb-1">{{ $zone['zone_name'] }}</h5>
            <p class="text-muted mb-0" style="font-size: 13px;">Zone ID: {{ $zone['zone_id'] }}</p>
        </div>
        <div class="text-end">
            <div class="risk-score risk-{{ $zone['risk_score'] >= 7 ? 'high' : ($zone['risk_score'] >= 4 ? 'medium' : 'low') }}">
                {{ $zone['risk_score'] }}/10
            </div>
            <span class="risk-level-badge risk-level-{{ $zone['risk_score'] >= 7 ? 'high' : ($zone['risk_score'] >= 4 ? 'medium' : 'low') }}">
                {{ $zone['risk_score'] >= 7 ? 'High' : ($zone['risk_score'] >= 4 ? 'Medium' : 'Low') }} Risk
            </span>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-value">{{ $zone['total_events'] }}</div>
            <div class="stat-label">Total Events</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" style="color: #dc2626;">{{ $zone['violations'] }}</div>
            <div class="stat-label">Violations</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" style="color: #f59e0b;">{{ $zone['alerts'] }}</div>
            <div class="stat-label">Alerts</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" style="color: #3b82f6;">{{ $zone['personnel_count'] }}</div>
            <div class="stat-label">Personnel</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" style="color: #10b981;">{{ $zone['equipment_count'] }}</div>
            <div class="stat-label">Equipment</div>
        </div>
    </div>

    <div class="mt-3">
        <small class="text-muted">Last Activity: {{ $zone['last_activity'] }}</small>
    </div>
</div>
@endforeach
@endsection

@section('scripts')
<script>
    // Additional JavaScript if needed
</script>
@endsection

