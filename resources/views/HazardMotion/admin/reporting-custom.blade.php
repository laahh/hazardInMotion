@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Reporting & Analytics - Custom Reports - Beraucoal')

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

    .custom-report-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 16px;
        transition: all 0.3s ease;
        background: white;
    }

    .custom-report-card:hover {
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

    .status-active {
        background-color: #d1fae5;
        color: #065f46;
    }

    .status-inactive {
        background-color: #f3f4f6;
        color: #6b7280;
    }

    .schedule-badge {
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

    .btn-edit {
        background-color: #3b82f6;
        color: white;
    }

    .btn-edit:hover {
        background-color: #2563eb;
    }

    .btn-run {
        background-color: #10b981;
        color: white;
    }

    .btn-run:hover {
        background-color: #059669;
    }

    .btn-delete {
        background-color: #ef4444;
        color: white;
    }

    .btn-delete:hover {
        background-color: #dc2626;
    }

    .create-report-section {
        background: #ffffff;
        border: 2px dashed #e5e7eb;
        border-radius: 8px;
        padding: 40px;
        text-align: center;
        margin-bottom: 20px;
    }
</style>
@endsection

@section('content')
<div class="reporting-header">
    <h1 class="reporting-title">Reporting & Analytics - Custom Reports</h1>
    <p class="reporting-subtitle">Create and manage custom reports</p>
</div>

<!-- Create New Report Section -->
<div class="create-report-section">
    <h5 class="mb-3">Create New Custom Report</h5>
    <p class="text-muted mb-4">Design your own report with custom parameters and sections</p>
    <button class="btn btn-primary" onclick="openCreateReportModal()">
        <i class="material-icons-outlined">add</i> Create Custom Report
    </button>
</div>

<!-- Custom Reports List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Custom Reports</h5>
    </div>
    <div class="card-body">
        @forelse($customReports as $report)
        <div class="custom-report-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="flex-grow-1">
                    <h5 class="mb-1">{{ $report['title'] }}</h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <span class="status-badge status-{{ $report['status'] }}">
                            {{ $report['status'] }}
                        </span>
                        <span class="schedule-badge">
                            {{ $report['schedule'] }}
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Created by: <strong>{{ $report['created_by'] }}</strong></small>
                        <small class="text-muted d-block">Created at: {{ $report['created_at'] }}</small>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Last run: {{ $report['last_run'] }}</small>
                        <small class="text-muted d-block">Report ID: {{ $report['id'] }}</small>
                    </div>
                </div>
            </div>
            
            <div class="report-actions">
                <button class="btn-action btn-run" onclick="runReport('{{ $report['id'] }}')">
                    <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">play_arrow</i>
                    Run Report
                </button>
                <button class="btn-action btn-edit" onclick="editReport('{{ $report['id'] }}')">
                    <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">edit</i>
                    Edit
                </button>
                <button class="btn-action btn-delete" onclick="deleteReport('{{ $report['id'] }}')">
                    <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">delete</i>
                    Delete
                </button>
            </div>
        </div>
        @empty
        <div class="text-center text-muted py-5">
            <p>No custom reports found. Create your first custom report above.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openCreateReportModal() {
        alert('Open create custom report modal');
        // Open modal to create new custom report
    }

    function runReport(reportId) {
        if (confirm('Run this custom report now?')) {
            alert('Running report: ' + reportId);
            // Run report logic
        }
    }

    function editReport(reportId) {
        alert('Edit report: ' + reportId);
        // Open edit modal
    }

    function deleteReport(reportId) {
        if (confirm('Are you sure you want to delete this custom report?')) {
            alert('Delete report: ' + reportId);
            // Delete report logic
        }
    }
</script>
@endsection

