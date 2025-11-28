@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Reporting & Analytics - Operational Reports - Beraucoal')

@section('css')
<style>
    .reporting-header {
        margin-bottom: 24px;
    }

    .reporting-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .reporting-subtitle {
        font-size: 14px;
        color: #6b7280;
    }

    .report-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 16px;
        transition: all 0.3s ease;
        background: white;
    }

    .report-card:hover {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-completed {
        background-color: #d1fae5;
        color: #065f46;
    }

    .sections-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }

    .section-badge {
        display: inline-block;
        padding: 4px 12px;
        background-color: #eff6ff;
        color: #1e40af;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .report-actions {
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

    .btn-download {
        background-color: #10b981;
        color: white;
    }

    .btn-download:hover {
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
<div class="reporting-header">
    <h1 class="reporting-title">Reporting & Analytics - Operational Reports</h1>
    <p class="reporting-subtitle">View and manage operational reports</p>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Operational Reports</h5>
        <button class="btn btn-sm btn-primary" onclick="openGenerateReportModal()">
            <i class="material-icons-outlined">add</i> Generate Report
        </button>
    </div>
    <div class="card-body">
        @foreach($operationalReports as $report)
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="flex-grow-1">
                    <h5 class="mb-1">{{ $report['title'] }}</h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <span class="status-badge status-{{ $report['status'] }}">
                            {{ $report['status'] }}
                        </span>
                        <span class="text-muted" style="font-size: 13px;">{{ $report['period'] }}</span>
                    </div>
                </div>
                <div class="text-end">
                    <small class="text-muted d-block">{{ $report['generated_at'] }}</small>
                    <small class="text-muted">{{ $report['file_size'] }}</small>
                </div>
            </div>
            
            <div class="mb-3">
                <strong>Report Sections:</strong>
                <div class="sections-list">
                    @foreach($report['sections'] as $section)
                    <span class="section-badge">{{ $section }}</span>
                    @endforeach
                </div>
            </div>
            
            <div class="report-actions">
                <button class="btn-action btn-view" onclick="viewReport('{{ $report['id'] }}')">
                    <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">visibility</i>
                    View
                </button>
                <button class="btn-action btn-download" onclick="downloadReport('{{ $report['id'] }}')">
                    <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">download</i>
                    Download
                </button>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openGenerateReportModal() {
        alert('Open generate report modal');
    }

    function viewReport(reportId) {
        alert('View report: ' + reportId);
    }

    function downloadReport(reportId) {
        window.location.href = '{{ route("reporting.download", "") }}/' + reportId;
    }
</script>
@endsection

