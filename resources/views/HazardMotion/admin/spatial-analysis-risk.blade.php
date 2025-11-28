@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Spatial Analysis - Risk Assessment - Beraucoal')

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

    .risk-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
        background: white;
    }

    .risk-card:hover {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .risk-card.high {
        border-left: 4px solid #dc2626;
    }

    .risk-card.medium {
        border-left: 4px solid #f59e0b;
    }

    .risk-card.low {
        border-left: 4px solid #10b981;
    }

    .risk-score {
        font-size: 48px;
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
        padding: 8px 20px;
        border-radius: 12px;
        font-size: 16px;
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

    .risk-factors {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }

    .factor-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        background: #f9fafb;
        border-radius: 6px;
        margin-bottom: 8px;
    }

    .factor-name {
        font-weight: 600;
        color: #111827;
    }

    .factor-score {
        font-size: 18px;
        font-weight: 700;
        color: #dc2626;
    }

    .recommendations {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }

    .recommendation-item {
        display: flex;
        align-items: start;
        padding: 12px;
        background: #eff6ff;
        border-radius: 6px;
        margin-bottom: 8px;
    }

    .recommendation-item::before {
        content: "→";
        margin-right: 12px;
        color: #3b82f6;
        font-weight: bold;
    }
</style>
@endsection

@section('content')
<div class="analysis-header">
    <h1 class="analysis-title">Spatial Analysis - Risk Assessment</h1>
    <p class="analysis-subtitle">Comprehensive risk assessment for operational zones</p>
</div>

@foreach($riskAssessment as $risk)
<div class="risk-card {{ $risk['risk_level'] }}">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h5 class="mb-1">{{ $risk['zone_name'] }}</h5>
            <p class="text-muted mb-0" style="font-size: 13px;">Zone ID: {{ $risk['zone_id'] }}</p>
        </div>
        <div class="text-end">
            <div class="risk-score risk-{{ $risk['risk_level'] }}">
                {{ $risk['risk_score'] }}/10
            </div>
            <span class="risk-level-badge risk-level-{{ $risk['risk_level'] }}">
                {{ ucfirst($risk['risk_level']) }} Risk
            </span>
        </div>
    </div>

    <div class="risk-factors">
        <h6 class="mb-3">Risk Factors</h6>
        @foreach($risk['factors'] as $factor => $score)
        <div class="factor-item">
            <span class="factor-name">{{ $factor }}</span>
            <span class="factor-score">{{ $score }}/10</span>
        </div>
        @endforeach
    </div>

    <div class="recommendations">
        <h6 class="mb-3">Recommendations</h6>
        @foreach($risk['recommendations'] as $recommendation)
        <div class="recommendation-item">
            {{ $recommendation }}
        </div>
        @endforeach
    </div>
</div>
@endforeach
@endsection

@section('scripts')
<script>
    // Additional JavaScript if needed
</script>
@endsection

