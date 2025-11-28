@extends('layouts.master')

@section('title', 'Daily Report - PPT Generator')
@section('css')
<style>
    .daily-report-container {
        padding: 24px;
        background-color: #f9fafb;
        min-height: calc(100vh - 70px);
    }

    .daily-report-header {
        margin-bottom: 24px;
    }

    .daily-report-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .daily-report-subtitle {
        font-size: 14px;
        color: #6b7280;
    }

    .daily-report-content {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }

    .daily-report-actions {
        display: flex;
        gap: 12px;
        margin-top: 24px;
        justify-content: center;
    }

    .btn {
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
    }

    .btn-primary {
        background-color: #22c55e;
        color: #ffffff;
    }

    .btn-primary:hover {
        background-color: #16a34a;
    }

    .btn-secondary {
        background-color: #6b7280;
        color: #ffffff;
    }

    .btn-secondary:hover {
        background-color: #4b5563;
    }

    /* PPT Preview Styles */
    .ppt-preview {
        width: 100%;
        max-width: 1200px;
        aspect-ratio: 16/9;
        background: #ffffff;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        position: relative;
        display: flex;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .ppt-preview-left {
        width: 50%;
        background: #ffffff;
        padding: 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    .ppt-preview-right {
        width: 50%;
        background: #064e3b;
        padding: 60px;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        position: relative;
    }

    .ppt-logo {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .ppt-logo img {
        max-width: 250px;
        width: 100%;
        height: auto;
        object-fit: contain;
    }

    .ppt-tags {
        display: flex;
        gap: 10px;
        margin-bottom: 40px;
    }

    .ppt-tag {
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 500;
        color: #ffffff;
        outline: none;
        border: 2px dashed transparent;
        cursor: text;
        transition: all 0.2s;
        min-width: 60px;
        display: inline-block;
    }

    .ppt-tag:hover {
        border-color: rgba(255, 255, 255, 0.3);
    }

    .ppt-tag:focus {
        border-color: rgba(255, 255, 255, 0.5);
        background-color: rgba(255, 255, 255, 0.1);
    }

    .ppt-tag-black {
        background-color: #000000;
    }

    .ppt-tag-green {
        background-color: #22c55e;
    }

    .ppt-title {
        font-size: 56px;
        font-weight: 700;
        color: #ffffff;
        margin-bottom: 20px;
        line-height: 1.2;
        outline: none;
        border: 2px dashed transparent;
        padding: 8px;
        border-radius: 4px;
        cursor: text;
        transition: all 0.2s;
        min-height: 60px;
    }

    .ppt-title:hover {
        border-color: rgba(255, 255, 255, 0.3);
        background-color: rgba(255, 255, 255, 0.05);
    }

    .ppt-title:focus {
        border-color: rgba(255, 255, 255, 0.5);
        background-color: rgba(255, 255, 255, 0.1);
    }

    .ppt-subtitle {
        font-size: 22px;
        color: #ffffff;
        outline: none;
        border: 2px dashed transparent;
        padding: 8px;
        border-radius: 4px;
        cursor: text;
        transition: all 0.2s;
        min-height: 28px;
    }

    .ppt-subtitle:hover {
        border-color: rgba(255, 255, 255, 0.3);
        background-color: rgba(255, 255, 255, 0.05);
    }

    .ppt-subtitle:focus {
        border-color: rgba(255, 255, 255, 0.5);
        background-color: rgba(255, 255, 255, 0.1);
    }

    .ppt-editable[contenteditable="true"]:empty:before {
        content: attr(data-placeholder);
        color: #9ca3af;
        opacity: 0.6;
    }

    @media (max-width: 1024px) {
        .ppt-preview {
            max-width: 100%;
        }

        .ppt-preview-left,
        .ppt-preview-right {
            padding: 30px;
        }

        .ppt-title {
            font-size: 36px;
        }

        .ppt-logo {
            font-size: 32px;
        }
    }
</style>
@endsection

@section('content')
<div class="daily-report-container">
    <div class="daily-report-header">
        <h1 class="daily-report-title">Daily Report - PPT Template Generator</h1>
        <p class="daily-report-subtitle">Customize your presentation template. All text fields are dynamic.</p>
    </div>

    <form id="pptForm" action="{{ route('daily-report.generate') }}" method="POST">
        @csrf
        <!-- Hidden inputs to store values -->
        <input type="hidden" name="activity_tag1" id="hidden_tag1" value="{{ $defaults['activity_tag1'] }}">
        <input type="hidden" name="activity_tag2" id="hidden_tag2" value="{{ $defaults['activity_tag2'] }}">
        <input type="hidden" name="report_title" id="hidden_title" value="{{ $defaults['report_title'] }}">
        <input type="hidden" name="site_name" id="hidden_site_name" value="{{ $defaults['site_name'] }}">
        <input type="hidden" name="date" id="hidden_date" value="{{ $defaults['date'] }}">

        <div class="daily-report-content">
            <div class="ppt-preview">
                <div class="ppt-preview-left">
                    <div class="ppt-logo">
                        <img src="{{ asset('build/images/logo-beraucoal.png') }}" alt="Beraucoal Logo">
                    </div>
                </div>
                <div class="ppt-preview-right">
                    <div class="ppt-tags">
                        <div class="ppt-tag ppt-tag-black ppt-editable" 
                             contenteditable="true" 
                             data-placeholder="SDCER"
                             id="editable_tag1"
                             onblur="updateHiddenInput('activity_tag1', this.textContent)">{{ $defaults['activity_tag1'] }}</div>
                        <div class="ppt-tag ppt-tag-green ppt-editable" 
                             contenteditable="true" 
                             data-placeholder="Routine Activity"
                             id="editable_tag2"
                             onblur="updateHiddenInput('activity_tag2', this.textContent)">{{ $defaults['activity_tag2'] }}</div>
                    </div>
                    <div class="ppt-title ppt-editable" 
                         contenteditable="true" 
                         data-placeholder="Safety Daily Report"
                         id="editable_title"
                         onblur="updateHiddenInput('report_title', this.textContent)">{{ $defaults['report_title'] }}</div>
                    <div class="ppt-subtitle ppt-editable" 
                         contenteditable="true" 
                         data-placeholder="Site HO - 17 Oktober 2025"
                         id="editable_subtitle"
                         onblur="updateSubtitle()">{{ $defaults['site_name'] }} - {{ $defaults['date'] }}</div>
                </div>
            </div>

            <div class="daily-report-actions">
                <button type="submit" class="btn btn-primary">Generate PPT</button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    // Update hidden input when editable content changes
    function updateHiddenInput(fieldName, value) {
        const hiddenInput = document.getElementById('hidden_' + fieldName);
        if (hiddenInput) {
            hiddenInput.value = value.trim();
        }
    }

    // Update subtitle (combines site_name and date)
    function updateSubtitle() {
        const subtitle = document.getElementById('editable_subtitle');
        const text = subtitle.textContent.trim();
        
        // Try to split by " - " to separate site name and date
        const parts = text.split(' - ');
        if (parts.length >= 2) {
            document.getElementById('hidden_site_name').value = parts[0].trim();
            document.getElementById('hidden_date').value = parts.slice(1).join(' - ').trim();
        } else {
            // If no " - " found, treat entire text as site name
            document.getElementById('hidden_site_name').value = text;
            document.getElementById('hidden_date').value = '{{ $defaults['date'] }}';
        }
    }

    // Prevent line breaks on Enter key for single-line fields
    document.addEventListener('DOMContentLoaded', function() {
        const singleLineFields = document.querySelectorAll('.ppt-tag');
        
        singleLineFields.forEach(field => {
            field.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });
        });

        // Handle subtitle separately (allows " - " separator)
        const subtitle = document.getElementById('editable_subtitle');
        if (subtitle) {
            subtitle.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.blur();
                }
            });
        }
    });

    function resetForm() {
        if (confirm('Are you sure you want to reset all fields to default values?')) {
            // Reset editable content
            document.getElementById('editable_tag1').textContent = '{{ $defaults['activity_tag1'] }}';
            document.getElementById('editable_tag2').textContent = '{{ $defaults['activity_tag2'] }}';
            document.getElementById('editable_title').textContent = '{{ $defaults['report_title'] }}';
            document.getElementById('editable_subtitle').textContent = '{{ $defaults['site_name'] }} - {{ $defaults['date'] }}';
            
            // Reset hidden inputs
            document.getElementById('hidden_tag1').value = '{{ $defaults['activity_tag1'] }}';
            document.getElementById('hidden_tag2').value = '{{ $defaults['activity_tag2'] }}';
            document.getElementById('hidden_title').value = '{{ $defaults['report_title'] }}';
            document.getElementById('hidden_site_name').value = '{{ $defaults['site_name'] }}';
            document.getElementById('hidden_date').value = '{{ $defaults['date'] }}';
        }
    }
</script>
@endsection

