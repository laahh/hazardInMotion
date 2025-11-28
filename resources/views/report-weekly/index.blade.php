@extends('layouts.master')

@section('title', 'Report Weekly')
@section('css')
<style>
    .report-weekly-container {
        display: flex;
        height: calc(100vh - 70px);
        background-color: #ffffff;
    }
    
    .report-weekly-sidebar {
        width: 320px;
        border-right: 1px solid #e5e7eb;
        background-color: #ffffff;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .report-weekly-sidebar-header {
        padding: 16px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .report-weekly-sidebar-title {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
    }
    
    .report-weekly-filter-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }
    
    .report-weekly-filter-dropdown {
        flex: 1;
        padding: 6px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background-color: #ffffff;
        font-size: 14px;
        color: #374151;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .report-weekly-filter-dropdown:hover {
        background-color: #f9fafb;
    }
    
    .report-weekly-action-btn {
        width: 32px;
        height: 32px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background-color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #6b7280;
    }
    
    .report-weekly-action-btn:hover {
        background-color: #f9fafb;
        color: #111827;
    }
    
    .report-weekly-new-btn {
        padding: 6px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background-color: #ffffff;
        font-size: 14px;
        color: #374151;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .report-weekly-new-btn:hover {
        background-color: #f9fafb;
    }
    
    .report-weekly-list {
        flex: 1;
        overflow-y: auto;
        padding: 8px;
    }
    
    .report-weekly-list-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .report-weekly-list-table th {
        text-align: left;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .report-weekly-list-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #f3f4f6;
        cursor: pointer;
    }
    
    .report-weekly-list-table tr:hover td {
        background-color: #f9fafb;
    }
    
    .report-weekly-list-table tr.active td {
        background-color: #eff6ff;
    }
    
    .report-weekly-list-table tr.active .report-weekly-meeting-name {
        color: #2563eb;
        font-weight: 500;
    }
    
    .report-weekly-meeting-name {
        font-size: 14px;
        color: #111827;
        font-weight: 400;
    }
    
    .report-weekly-meeting-date {
        font-size: 13px;
        color: #6b7280;
    }
    
    .report-weekly-add-new {
        padding: 12px;
        border-top: 1px solid #e5e7eb;
        margin-top: auto;
    }
    
    .report-weekly-add-new-btn {
        width: 100%;
        padding: 8px 12px;
        border: 1px dashed #d1d5db;
        border-radius: 6px;
        background-color: #ffffff;
        font-size: 14px;
        color: #6b7280;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }
    
    .report-weekly-add-new-btn:hover {
        background-color: #f9fafb;
        border-color: #9ca3af;
        color: #374151;
    }
    
    .report-weekly-content {
        flex: 1;
        overflow-y: auto;
        background-color: #ffffff;
    }
    
    .report-weekly-content-header {
        padding: 24px 32px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .report-weekly-content-title {
        font-size: 32px;
        font-weight: 600;
        color: #111827;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .report-weekly-content-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .report-weekly-action-icon {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #6b7280;
    }
    
    .report-weekly-action-icon:hover {
        background-color: #f3f4f6;
        color: #111827;
    }
    
    .report-weekly-content-body {
        padding: 24px 32px;
        max-width: 900px;
    }
    
    .report-weekly-metadata {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .report-weekly-metadata-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .report-weekly-metadata-label {
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
    }
    
    .report-weekly-metadata-value {
        font-size: 14px;
        color: #111827;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .report-weekly-section {
        margin-bottom: 32px;
    }
    
    .report-weekly-section-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
    }
    
    .report-weekly-section-title {
        font-size: 16px;
        font-weight: 600;
        color: #111827;
    }
    
    .report-weekly-section-content {
        padding: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background-color: #f9fafb;
        min-height: 40px;
        color: #6b7280;
        font-size: 14px;
    }
    
    .report-weekly-section-content.empty {
        color: #9ca3af;
        font-style: italic;
    }
    
    .report-weekly-tabs {
        display: flex;
        gap: 24px;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 16px;
    }
    
    .report-weekly-tab {
        padding: 8px 0;
        font-size: 14px;
        color: #6b7280;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        transition: all 0.2s;
    }
    
    .report-weekly-tab:hover {
        color: #111827;
    }
    
    .report-weekly-tab.active {
        color: #111827;
        border-bottom-color: #111827;
        font-weight: 500;
    }
    
    .report-weekly-transcribe-btn {
        margin-left: auto;
        padding: 6px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background-color: #ffffff;
        font-size: 13px;
        color: #374151;
        cursor: pointer;
    }
    
    .report-weekly-transcribe-btn:hover {
        background-color: #f9fafb;
    }
    
    .report-weekly-editor {
        min-height: 200px;
        padding: 16px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background-color: #ffffff;
    }
    
    .report-weekly-editor-heading {
        font-size: 16px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }
    
    .report-weekly-editor-content {
        font-size: 14px;
        color: #374151;
        line-height: 1.6;
    }
</style>
@endsection

@section('content')
<div class="report-weekly-container">
    <!-- Left Sidebar -->
    <div class="report-weekly-sidebar">
        <div class="report-weekly-sidebar-header">
            <div class="report-weekly-sidebar-title">
                <i class="material-icons-outlined">event</i>
                Log Book
            </div>
            <div class="report-weekly-filter-bar">
                <div class="report-weekly-filter-dropdown">
                    <span>★ All Meetings</span>
                    <i class="material-icons-outlined" style="font-size: 18px;">arrow_drop_down</i>
                </div>
                <div class="report-weekly-action-btn">
                    <i class="material-icons-outlined" style="font-size: 18px;">sort</i>
                </div>
                <div class="report-weekly-action-btn">
                    <i class="material-icons-outlined" style="font-size: 18px;">search</i>
                </div>
                <div class="report-weekly-action-btn">
                    <i class="material-icons-outlined" style="font-size: 18px;">filter_list</i>
                </div>
            </div>
            <div style="margin-top: 8px;">
                <div class="report-weekly-new-btn">
                    <span>New</span>
                    <i class="material-icons-outlined" style="font-size: 18px;">arrow_drop_down</i>
                </div>
            </div>
        </div>
        
        <div class="report-weekly-list">
            <table class="report-weekly-list-table">
                <thead>
                    <tr>
                        <th>Meeting name</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="active">
                        <td>
                            <div class="report-weekly-meeting-name">Day 1</div>
                        </td>
                        <td>
                            <div class="report-weekly-meeting-date">November 3, 2025</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="report-weekly-meeting-name">Day 2</div>
                        </td>
                        <td>
                            <div class="report-weekly-meeting-date">November 4, 2025</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="report-weekly-meeting-name">Day 3</div>
                        </td>
                        <td>
                            <div class="report-weekly-meeting-date">November 5, 2025</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="report-weekly-meeting-name">Day 4</div>
                        </td>
                        <td>
                            <div class="report-weekly-meeting-date">November 6, 2025</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="report-weekly-add-new">
            <div class="report-weekly-add-new-btn">
                <i class="material-icons-outlined" style="font-size: 18px;">add</i>
                <span>New meeting</span>
            </div>
        </div>
    </div>
    
    <!-- Right Content -->
    <div class="report-weekly-content">
        <div class="report-weekly-content-header">
            <div class="report-weekly-content-title">
                <i class="material-icons-outlined" style="font-size: 32px;">event</i>
                Day 1
            </div>
            <div class="report-weekly-content-actions">
                <div class="report-weekly-action-icon">
                    <i class="material-icons-outlined" style="font-size: 20px;">fullscreen</i>
                </div>
                <div class="report-weekly-action-icon">
                    <i class="material-icons-outlined" style="font-size: 20px;">format_size</i>
                </div>
                <div class="report-weekly-action-icon">
                    <i class="material-icons-outlined" style="font-size: 20px;">close</i>
                </div>
                <div class="report-weekly-action-icon">
                    <i class="material-icons-outlined" style="font-size: 20px;">share</i>
                </div>
                <div class="report-weekly-action-icon">
                    <i class="material-icons-outlined" style="font-size: 20px;">star_border</i>
                </div>
                <div class="report-weekly-action-icon">
                    <i class="material-icons-outlined" style="font-size: 20px;">more_vert</i>
                </div>
            </div>
        </div>
        
        <div class="report-weekly-content-body">
            <!-- Metadata -->
            <div class="report-weekly-metadata">
                <div class="report-weekly-metadata-item">
                    <div class="report-weekly-metadata-label">Date</div>
                    <div class="report-weekly-metadata-value">November 3, 2025</div>
                </div>
                <div class="report-weekly-metadata-item">
                    <div class="report-weekly-metadata-label">Category</div>
                    <div class="report-weekly-metadata-value">Empty</div>
                </div>
                <div class="report-weekly-metadata-item">
                    <div class="report-weekly-metadata-label">Created by</div>
                    <div class="report-weekly-metadata-value">
                        <i class="material-icons-outlined" style="font-size: 18px;">person</i>
                        IFA APRILLIANTO
                        <i class="material-icons-outlined" style="font-size: 18px; color: #f59e0b;">info</i>
                    </div>
                </div>
            </div>
            
            <!-- Attendees Section -->
            <div class="report-weekly-section">
                <div class="report-weekly-section-header">
                    <i class="material-icons-outlined" style="font-size: 20px; color: #6b7280;">group</i>
                    <div class="report-weekly-section-title">Attendees</div>
                </div>
                <div class="report-weekly-section-content empty">Empty</div>
            </div>
            
            <!-- Summary Section -->
            <div class="report-weekly-section">
                <div class="report-weekly-section-header">
                    <i class="material-icons-outlined" style="font-size: 20px; color: #6b7280;">auto_awesome</i>
                    <div class="report-weekly-section-title">Summary</div>
                    <span style="padding: 2px 6px; background-color: #dbeafe; color: #1e40af; border-radius: 4px; font-size: 11px; font-weight: 600;">AI</span>
                </div>
                <div class="report-weekly-section-content empty">Empty</div>
            </div>
            
            <!-- Comments Section -->
            <div class="report-weekly-section">
                <div class="report-weekly-section-header">
                    <i class="material-icons-outlined" style="font-size: 20px; color: #6b7280;">comment</i>
                    <div class="report-weekly-section-title">Comments</div>
                </div>
                <div class="report-weekly-section-content">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="color: #9ca3af;">Add a comment...</span>
                        <i class="material-icons-outlined" style="font-size: 18px; color: #f59e0b;">info</i>
                    </div>
                </div>
            </div>
            
            <!-- Notes/Transcript Tabs -->
            <div style="margin-top: 32px;">
                <div style="display: flex; align-items: center; margin-bottom: 16px;">
                    <div class="report-weekly-tabs">
                        <div class="report-weekly-tab active">Notes</div>
                        <div class="report-weekly-tab">Transcript</div>
                    </div>
                    <button class="report-weekly-transcribe-btn">Start transcribing</button>
                </div>
                
                <div class="report-weekly-editor">
                    <div class="report-weekly-editor-heading">Kegiatan :</div>
                    <div class="report-weekly-editor-content">
                        Tanggal 03 November 2025, Hari pertama Kerja, kegiatannya
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Tab switching
    document.querySelectorAll('.report-weekly-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.report-weekly-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // List item click
    document.querySelectorAll('.report-weekly-list-table tbody tr').forEach(row => {
        row.addEventListener('click', function() {
            document.querySelectorAll('.report-weekly-list-table tbody tr').forEach(r => r.classList.remove('active'));
            this.classList.add('active');
        });
    });
</script>
@endsection

