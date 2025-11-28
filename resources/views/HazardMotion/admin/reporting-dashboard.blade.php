@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Reporting & Analytics - Dashboard Reports - Beraucoal')

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

    .stats-card.completed {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .stats-card.downloads {
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

    .report-type-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .report-type-safety {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .report-type-operational {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .report-type-compliance {
        background-color: #d1fae5;
        color: #065f46;
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

    .status-pending {
        background-color: #fef3c7;
        color: #92400e;
    }

    .status-processing {
        background-color: #dbeafe;
        color: #1e40af;
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

    .btn-generate {
        background-color: #8b5cf6;
        color: white;
    }

    .btn-generate:hover {
        background-color: #7c3aed;
    }
</style>
@endsection

@section('content')
<div class="reporting-header">
    <h1 class="reporting-title">Reporting & Analytics - Dashboard Reports</h1>
    <p class="reporting-subtitle">View and manage dashboard reports</p>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-md-4">
        <div class="stats-card total">
            <div class="stats-number">{{ $stats['total_reports'] }}</div>
            <div class="stats-label">Total Reports</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card completed">
            <div class="stats-number">{{ $stats['completed_reports'] }}</div>
            <div class="stats-label">Completed Reports</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card downloads">
            <div class="stats-number">{{ $stats['total_downloads'] }}</div>
            <div class="stats-label">Total Downloads</div>
        </div>
    </div>
</div>

<!-- Reports List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Dashboard Reports</h5>
        <button class="btn btn-sm btn-primary" onclick="openGenerateReportModal()">
            <i class="material-icons-outlined">add</i> Generate Report
        </button>
    </div>
    <div class="card-body">
        @foreach($dashboardReports as $report)
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="flex-grow-1">
                    <h5 class="mb-1">{{ $report['title'] }}</h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <span class="report-type-badge report-type-{{ $report['type'] }}">
                            {{ $report['type'] }}
                        </span>
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
            
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">
                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">download</i>
                        {{ $report['download_count'] }} downloads
                    </small>
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
        </div>
        @endforeach
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openGenerateReportModal() {
        alert('Open generate report modal');
        // Open modal to generate new report
    }

    function viewReport(reportId) {
        alert('View report: ' + reportId);
        // Open report viewer
    }

    function downloadReport(reportId) {
        window.location.href = '{{ route("reporting.download", "") }}/' + reportId;
    }
</script>
@endsection

