@extends('layouts.master')

@section('title', 'Daily Report - PPT Preview')
@section('css')
<style>
    .ppt-preview-container {
        padding: 24px;
        background-color: #f9fafb;
        min-height: calc(100vh - 70px);
    }

    .ppt-preview-header {
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ppt-preview-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
    }

    .ppt-preview-actions {
        display: flex;
        gap: 12px;
    }

    .btn {
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-block;
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

    .ppt-slide {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        aspect-ratio: 16/9;
        background: #ffffff;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        position: relative;
        display: flex;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .ppt-slide-left {
        width: 50%;
        background: #ffffff;
        padding: 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    .ppt-slide-right {
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
    }

    .ppt-subtitle {
        font-size: 22px;
        color: #ffffff;
    }
</style>
@endsection

@section('content')
<div class="ppt-preview-container">
    <div class="ppt-preview-header">
        <h1 class="ppt-preview-title">PPT Template Preview</h1>
        <div class="ppt-preview-actions">
            <a href="{{ route('daily-report.index') }}" class="btn btn-secondary">Back to Editor</a>
            <button onclick="window.print()" class="btn btn-primary">Print / Save as PDF</button>
        </div>
    </div>

    <div class="ppt-slide">
        <div class="ppt-slide-left">
            <div class="ppt-logo">
                <img src="{{ asset('build/images/logo-beraucoal.png') }}" alt="Beraucoal Logo">
            </div>
        </div>
        <div class="ppt-slide-right">
            <div class="ppt-tags">
                <div class="ppt-tag ppt-tag-black">{{ $data['activity_tag1'] ?? 'SDCER' }}</div>
                <div class="ppt-tag ppt-tag-green">{{ $data['activity_tag2'] ?? 'Routine Activity' }}</div>
            </div>
            <div class="ppt-title">{{ $data['report_title'] ?? 'Safety Daily Report' }}</div>
            <div class="ppt-subtitle">{{ $data['site_name'] ?? 'Site HO' }} - {{ $data['date'] ?? date('d F Y') }}</div>
        </div>
    </div>
</div>
@endsection

